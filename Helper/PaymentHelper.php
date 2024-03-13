<?php

namespace Moyasar\Magento2\Helper;

use Magento\Framework\Controller\ResultFactory;
use Moyasar\Magento2\Helper\Http\Exceptions\HttpException;
use Moyasar\Magento2\Helper\Http\QuickHttp;

trait PaymentHelper
{
    /**
     * @var string
     * Moyasar payment ID
     */
    protected $paymentId;

    /**
     * @var string
     * Moyasar payment Method (creditcard, applepay, stcpay)
     */
    protected $method;

    private function setUpPaymentData($order){
        $payment = $order->getPayment();
        $this->paymentId = $payment->getAdditionalInformation('moyasar_payment_id');
        $this->method = $payment->getAdditionalInformation('moyasar_payment_method');
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
    }

    private function http()
    {
        return new QuickHttp();
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

    private function handleHttpException($e, $order)
    {
        $orderId = $order->getRealOrderId();
        $logErrorId = bin2hex(random_bytes(6));

        $this->logger->critical("[$logErrorId] Cannot verify payment (order $orderId): " . $e->getMessage());

        if ($e instanceof HttpException) {
            $this->logger->critical("[$logErrorId] server response: " . $e->response->body());
        }

        $this->messageManager
            ->addErrorMessage(__('Could not verify your payment for order %order_id: %error. Error ID: %error_id', ['order_id' => $orderId, 'error' => $e->getMessage(), 'error_id' => $logErrorId]));

    }

}
