<?php

namespace Moyasar\Mysr\Controller\ApplePay;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
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

        $payment = $this->getHelper()->authorizeApplePayPayment($paymentData);

        $order = $this->getOrder();
        $redirectUrl = $this->getHelper()->getUrl('checkout/onepage/success');

        return [
            'success' => true,
            'redirect_url' => $redirectUrl
        ];
    }

    /**
     * Get order object
     *
     * @return Order
     */
    protected function getOrder()
    {
        return $this->_checkoutSession->getLastRealOrder();
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

    }
}
