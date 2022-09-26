<?php

namespace Moyasar\Mysr\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Moyasar\Mysr\Model\Payment\MoyasarPayments;

class BeforeOrderPlaceObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();
        $payment = $order->getPayment();

        if ($payment && $payment->getMethod() == MoyasarPayments::CODE) {
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
