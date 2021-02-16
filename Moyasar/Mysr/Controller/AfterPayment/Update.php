<?php

namespace Moyasar\Mysr\Controller\AfterPayment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;

class Update extends Action
{
    protected $checkoutSession;

    public function __construct(Context $context, Session $checkoutSession)
    {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $order = $this->currentOrder();
        $payment = $order->getPayment();
        if (!is_null($payment)) {
            $payment->setAdditionalInformation('moyasar_payment_id', $_POST['paymentId']);
            $order->save();
        }    
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
}
