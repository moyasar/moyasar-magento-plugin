<?php

namespace Moyasar\Mysr\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\MoyasarHelper;

class Response extends Action
{
    protected $checkoutSession;
    protected $moyasarHelper;
    protected $urlBuilder;

    public function __construct(Context $context, Session $checkoutSession, MoyasarHelper $helper, UrlInterface $urlBuilder)
    {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper = $helper;
        $this->urlBuilder = $urlBuilder;
    }

    public function execute()
    {
        $order = $this->currentOrder();

        $paymentId = $this->orderPaymentId($order);
        if (!$paymentId) {
            $paymentId = isset($_GET['id']) ? $_GET['id'] : null;
        }

        $callbackUrl = $this->successPath();
        $status = $this->moyasarHelper->verifyAndProcess($order, $paymentId, $_GET['status']);

        if ($status != 'paid') {
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addError(__('Error! Payment failed, please try again later.'));
            $callbackUrl = $this->cartPath();
        }

        $this->getResponse()->setRedirect($callbackUrl);
    }

    /**
     * Get current order object
     *
     * @return Order
     */
    protected function currentOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    protected function successPath()
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success');
    }

    protected function cartPath()
    {
        return $this->urlBuilder->getUrl('checkout/cart');
    }

    protected function orderPaymentId($order)
    {
        $payment = $order->getPayment();

        if (is_null($payment)) {
            return null;
        }

        $additionalInfo = $payment->getAdditionalInformation();

        return isset($additionalInfo['moyasar_payment_id']) ? $additionalInfo['moyasar_payment_id'] : null;
    }
}
