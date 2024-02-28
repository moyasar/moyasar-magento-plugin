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

    /**
     * @var string
     * Method of payment (stcpay, creditcard, applepay)
     */
    protected $method = 'creditcard'; // stcpay, creditcard

    /**
     * @var string
     * Moyasar payment ID
     */
    protected $paymentId;

    /**
     * @var string
     * STC Pay Tokens
     */
    private $otpToken;
    private $otpId;
    private $otp;

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


    private function validateRequest()
    {
        // If Payments is STC Pay then we need to check if the OTP, ID, Token are set
        if ($this->method == 'stcpay') {
            if (!isset($_GET['otp_token']) || !isset($_GET['otp']) || !isset($_GET['otp_id'])){
                return false;
            }
            $this->otpToken = $_GET['otp_token'];
            $this->otpId = $_GET['otp_id'];
            $this->otp = $_GET['otp'];
        }
        return true;
    }

    public function execute()
    {

        $order = $this->lastOrder();
        if (!$order) {
            $this->logger->warning('Moyasar validate payment accessed without active order.');
            return $this->redirectToCart();
        }
        $payment = $order->getPayment();
        $this->paymentId = $payment->getAdditionalInformation('moyasar_payment_id');
        $this->method = $payment->getAdditionalInformation('moyasar_payment_method');
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

        $isValid = $this->validateRequest();
        if (!$isValid){
            $this->logger->warning('Moyasar validate payment accessed with missing arguments');
            return $this->redirectToCart();
        }

        if ($order->getState() == Order::STATE_PROCESSING){
            return $this->redirectToSuccess();
        };

        $this->logger->info("Validating:  [{$this->paymentId}], Method: [{$this->method}]");


        try {
            $payment = $this->getPaymentData();
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

    private function getPaymentData()
    {
        if ($this->method == 'stcpay') {
            return $this->fetchSTCPayment();
        }
        return $this->fetchPayment();
    }

    private function fetchPayment()
    {
        return $this->http()
            ->basic_auth($this->moyasarHelper->secretApiKey())
            ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/{$this->paymentId}"))
            ->json();
    }

    private function fetchSTCPayment()
    {
        return $this->http()
            ->get($this->moyasarHelper->apiBaseUrl("/v1/stc_pays/{$this->otpId}/proceed"), [
                'otp_token' => $this->otpToken,
                'otp_value' => $this->otp
            ])
            ->json();
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
            $this->checkoutSession->replaceQuote($quote);
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
