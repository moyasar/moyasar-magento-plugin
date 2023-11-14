<?php

namespace Moyasar\Mysr\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\Http\Exceptions\ConnectionException;
use Moyasar\Mysr\Helper\Http\Exceptions\HttpException;
use Moyasar\Mysr\Helper\Http\QuickHttp;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class Response implements ActionInterface
{
    protected $context;
    protected $checkoutSession;
    protected $moyasarHelper;
    protected $urlBuilder;
    protected $http;
    protected $messageManager;
    protected $logger;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        MoyasarHelper $helper,
        UrlInterface $urlBuilder,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper = $helper;
        $this->urlBuilder = $urlBuilder;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        $paymentId = $_GET['id'];
        
        //fetch payment
        $payment = $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId"))
                ->json();

        $order = $this->lastOrder($payment);
        $orderPayment = $order->getPayment();

        $paymentId = $_GET['id'];
        if (! is_null($orderPayment)) {
            $paymentId = $orderPayment->getLastTransId() ?? $_GET['id'];
        }

        if (! $paymentId) {
            $this->messageManager->addErrorMessage(__('No payment was found for your order.'));

            return $this->context
                ->getResultFactory()
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('checkout/cart');
        }

        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

        if ($order->getState() == Order::STATE_PROCESSING) {
            return $this->context
                ->getResultFactory()
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('checkout/onepage/success');
        }

        try {
            $payment = $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId"))
                ->json();

            if ($payment['status'] != 'paid') {
                return $this->processPaymentFail($payment, $order);
            }

            $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
            if (count($errors) > 0) {
                return $this->processUnMatchingInfoFail($payment, $order, $errors);
            }

            $this->moyasarHelper->processSuccessfulOrder($order, $payment);
            $this->logger->info("Payment [$paymentId] is successful, redirecting user to checkout/onepage/success: ");

            return $this->context
                ->getResultFactory()
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('checkout/onepage/success');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->context
                ->getResultFactory()
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('checkout/cart');

        } catch (HttpException|ConnectionException $e) {
            $orderId = $order->getRealOrderId();
            $logErrorId = bin2hex(random_bytes(6));

            $this->logger->critical("[$logErrorId] Cannot verify payment (order $orderId): " . $e->getMessage());

            if ($e instanceof HttpException) {
                $this->logger->critical("[$logErrorId] server response: " . $e->response->body());
            }

            $this->messageManager
                ->addErrorMessage(__('Could not verify your payment for order %order_id: %error. Error ID: %error_id', ['order_id' => $orderId, 'error' => $e->getMessage(), 'error_id' => $logErrorId]));

            return $this->context
                ->getResultFactory()
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('checkout/cart');
        }
    }

    private function processPaymentFail($payment, $order)
    {
        $message = __('Payment failed');
        if ($sourceMessage = $payment['source']['message']) {
            $message .= ': ' . $sourceMessage;
        }

        $this->messageManager->addErrorMessage($message);

        $order->registerCancellation($message);
        $order->getPayment()->setCcStatus('failed');
        $order->save();

        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('checkout/cart');
    }

    private function processUnMatchingInfoFail($payment, $order, $errors)
    {
        $paymentId = $payment['id'];

        array_unshift($errors, __('Un-matching payment details %payment_id.', ['payment_id' => $paymentId]));

        $order->registerCancellation(implode("\n", $errors));
        $order->getPayment()->setCcStatus('failed');
        $order->save();

        //auto void
        if ($this->moyasarHelper->autoVoid()) {
            $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->post($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId/void"));

            $order->addStatusHistoryComment('Order value was voided automatically.', false);
            $order->save();
        }

        $this->messageManager->addErrorMessage(implode("\n", $errors));

        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('checkout/cart');
    }

    private function lastOrder($payment)
    {
        $orderId = $payment['metadata']['order_id'];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId);

        // Work around real_last_order_id is lost from current session
        if (! $order->getId()) {
            $order->loadByAttribute('order_id', $order);
        }

        return $order;
    }

    private function http()
    {
        return new QuickHttp();
    }
}
