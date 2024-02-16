<?php

namespace Moyasar\Magento2\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Moyasar\Magento2\Helper\Http\Exceptions\ConnectionException;
use Moyasar\Magento2\Helper\Http\Exceptions\HttpException;
use Moyasar\Magento2\Helper\Http\QuickHttp;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class Validate implements ActionInterface
{
    protected $context;
    protected $checkoutSession;
    protected $moyasarHelper;
    protected $urlBuilder;
    protected $http;
    protected $messageManager;
    protected $logger;

    protected $method = 'creditcard'; // stcpay, creditcard
    protected $paymentId;
    private mixed $otpToken;
    private mixed $otpId;
    private mixed $otp;

    public function __construct(
        Context          $context,
        Session          $checkoutSession,
        MoyasarHelper    $helper,
        UrlInterface     $urlBuilder,
        ManagerInterface $messageManager,
        LoggerInterface  $logger
    )
    {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper = $helper;
        $this->urlBuilder = $urlBuilder;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    private function redirectToCart()
    {
        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('checkout/cart');
    }

    private function redirectToSuccess()
    {
        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('checkout/onepage/success');
    }

    private function fetchPayment()
    {
        $request =  $this->http();
        $url = $this->moyasarHelper->apiBaseUrl("/v1/payments/{$this->paymentId}");

        if ($this->method == 'stcpay') {
            $url = $this->moyasarHelper->apiBaseUrl("/v1/stc_pays/{$this->otpId}/proceed?otp_token={$this->otpToken}&otp_value={$this->otp}");
        }else{
            $request = $this->http()->basic_auth($this->moyasarHelper->secretApiKey());
        }
        return $request->get($url)->json();
    }

    public function execute()
    {
        if (!isset($_GET['payment_id'])) $this->redirectToCart();

        $this->paymentId = $_GET['payment_id'];
        $this->method = $_GET['method'] ?? 'creditcard';
        $this->logger->info("Validating:  [{$this->paymentId}], Method: [{$this->method}]");

        if ($this->method == 'stcpay') {
            if (!isset($_GET['otp_token']) || !isset($_GET['otp']) || !isset($_GET['otp_id'])) $this->redirectToCart();
            list($this->otpToken, $this->otpId, $this->otp) = array($_GET['otp_token'], $_GET['otp_id'], $_GET['otp']);
        }


        $order = $this->lastOrder();
        $orderPayment = $order->getPayment();


        if (!is_null($orderPayment)) {
            $this->paymentId = $orderPayment->getLastTransId() ?? $_GET['payment_id'];
        }

        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

        if ($order->getState() == Order::STATE_PROCESSING) $this->redirectToSuccess();

        try {
            $payment = $this->fetchPayment();
            $this->logger->info("Payment ID: [{$this->paymentId}], Status:  [{$payment['source']['message']}]");

            if ($payment['status'] != 'paid') {
                $this->processPaymentFail($payment, $order);
                return $this->redirectToCart();
            }

            $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
            if (count($errors) > 0) {
                $this->processUnMatchingInfoFail($payment, $order, $errors);
                return $this->redirectToCart();
            }

            $this->moyasarHelper->processSuccessfulOrder($order, $payment);
            $this->logger->info("Payment [{$this->paymentId}] is successful, redirecting user to checkout/onepage/success: ");

            return $this->redirectToSuccess();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->redirectToCart();

        } catch (HttpException|ConnectionException $e) {
            $orderId = $order->getRealOrderId();
            $logErrorId = bin2hex(random_bytes(6));

            $this->logger->critical("[$logErrorId] Cannot verify payment (order $orderId): " . $e->getMessage());

            if ($e instanceof HttpException) {
                $this->logger->critical("[$logErrorId] server response: " . $e->response->body());
            }

            $this->messageManager
                ->addErrorMessage(__('Could not verify your payment for order %order_id: %error. Error ID: %error_id', ['order_id' => $orderId, 'error' => $e->getMessage(), 'error_id' => $logErrorId]));

            return $this->redirectToCart();
        }
    }

    private function processPaymentFail($payment, $order)
    {
        $message = __('Payment failed');
        if ($sourceMessage = $payment['source']['message']) {
            $message .= ': ' . $sourceMessage;
        }
        // Restore quote
        $quoteId = $order->getQuoteId();
        $quote = $this->checkoutSession->getQuote()->load($quoteId);
        if ($quote->getId()) {
            $quote->setIsActive(1);
            $quote->setReservedOrderId(null);
            $quote->save();
        }

        $this->messageManager->addErrorMessage($message);

        $order->registerCancellation($message);
        $order->getPayment()->setCcStatus('failed');
        $order->save();

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
    }

    private function lastOrder()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        // Work around real_last_order_id is lost from current session
        if (!$order->getId()) {
            $order->loadByAttribute('entity_id', $this->checkoutSession->getLastOrderId());
        }

        return $order;
    }

    private function http()
    {
        return new QuickHttp();
    }
}
