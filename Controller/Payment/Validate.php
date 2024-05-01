<?php

namespace Moyasar\Magento2\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Moyasar\Magento2\Helper\Http\Exceptions\ConnectionException;
use Moyasar\Magento2\Helper\Http\Exceptions\HttpException;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Moyasar\Magento2\Helper\PaymentHelper;
use Psr\Log\LoggerInterface;

class Validate implements ActionInterface
{

    use PaymentHelper;

    protected $context;
    protected $checkoutSession;
    protected $moyasarHelper;
    protected $urlBuilder;
    protected $http;
    protected $messageManager;
    protected $logger;

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

    public function execute()
    {
        $order = $this->lastOrder();

        if (!$order) {
            $this->logger->warning('Moyasar validate payment accessed without active order.');
            return $this->redirectToCart();
        }
        $this->setUpPaymentData($order);


        if ($order->getState() == Order::STATE_PROCESSING){
            return $this->redirectToSuccess();
        };

        $this->logger->info("(Validate Controller) Validating:  [{$this->paymentId}], Method: [{$this->method}]");


        try {
            $payment = $this->fetchPayment($order);
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
            $this->handleHttpException($e, $order);
            return $this->redirectToCart();
        }
    }

    /**
     * @description Fetch Payment
     * @return bool
     */
    private function fetchPayment($order)
    {
        return $this->http()
            ->basic_auth($this->moyasarHelper->secretApiKey())
            ->set_headers(['order_id' => $order->getId()])
            ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/{$this->paymentId}"))
            ->json();
    }

}
