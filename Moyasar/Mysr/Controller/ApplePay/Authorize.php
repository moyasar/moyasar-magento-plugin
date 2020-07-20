<?php

namespace Moyasar\Mysr\Controller\ApplePay;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Model\Quote;
use Moyasar\Mysr\Helper\Data;

class Authorize extends Action implements CsrfAwareActionInterface
{
    protected $_checkoutSession;
    protected $_helper;

    public function __construct(Context $context, Session $checkoutSession, Data $helper)
    {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_helper = $helper;
    }

    public function execute()
    {
        if (!$this->isPost()) {
            return $this->errorJson('Only POST allowed');
        }

        $paymentData = $this->json('payment_data');
        if (!$paymentData) {
            return $this->errorJson('Payment data is required');
        }

        if (!is_string($paymentData)) {
            return $this->errorJson('Payment data must be a stringifier JSON object (must be string)');
        }

        try {
            $quote = $this->getQuote();
        } catch (Exception $e) {
            return $this->errorJson('Could not get quote for current session');
        }

        $amount = (int) $quote->getBaseGrandTotal() * 100;
        $currency = mb_strtoupper($quote->getBaseCurrencyCode());

        if (!$amount || $amount <= 0 || !$currency || strlen($currency) != 3) {
            return $this->errorJson('Could not get correct quote information');
        }

        $description = "Order for " . $quote->getCustomerEmail();
        $payment = $this->getHelper()->authorizeApplePayPayment($amount, $description, $currency, $paymentData);

        if (!$payment) {
            return $this->errorJson('Payment failed');
        }

        $paid = $this->isPaymentPaid($payment);
        $status = $this->paymentStatus($payment);
        $paymentId = $this->paymentId($payment);
        $redirectUrl = $this->getHelper()->getUrl('checkout/onepage/success');

        return [
            'success' => $paid,
            'status' => $status,
            'payment_id' => $paymentId,
            'redirect_url' => $redirectUrl
        ];
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @return Quote
     */
    protected function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }

    /**
     * Get moyasar helper
     *
     * @return Data
     */
    protected function getHelper()
    {
        return $this->_helper;
    }

    protected function isPost()
    {
        return mb_strtoupper($_SERVER['REQUEST_METHOD']) == 'POST';
    }

    protected function json($key = null)
    {
        static $requestJson = null;

        if (is_null($requestJson)) {
            $requestBody = $this->getRequest()->getContent();
            $requestJson = @json_decode($requestBody, true);
        }

        if (!$requestJson) {
            $requestJson = [];
        }

        if ($key && isset($requestJson[$key])) {
            return $requestJson[$key];
        }

        return $requestJson;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    protected function errorJson($message)
    {
        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData([
                'success' => false,
                'error' => $message
            ])
            ->setStatusHeader(400, null, 'Bad Request');
    }

    protected function isPaymentPaid($payment)
    {
        return is_array($payment) && isset($payment['status']) && mb_strtolower($payment['status']) == 'paid';
    }

    protected function paymentId($payment)
    {
        if (is_array($payment) && isset($payment['id'])) {
            return $payment['id'];
        }

        return null;
    }

    protected function paymentStatus($payment)
    {
        if (is_array($payment) && isset($payment['status'])) {
            return $payment['status'];
        }

        return null;
    }
}
