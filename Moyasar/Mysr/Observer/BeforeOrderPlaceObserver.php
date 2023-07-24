<?php

namespace Moyasar\Mysr\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Model\Payment\MoyasarPayments;

class BeforeOrderPlaceObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        $payment = $order->getPayment();

        if ($payment && $payment->getMethod() == MoyasarPayments::CODE) {
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
