<?php

namespace Moyasar\Mysr\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Moyasar\Mysr\Model\Payment\MoyasarOnlinePayment;
use Moyasar\Mysr\Model\Payment\MoyasarStcPay;

class BeforeOrderPlaceObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $methods = [
            MoyasarOnlinePayment::CODE,
            MoyasarStcPay::CODE
        ];

        $order = $observer->getOrder();
        if (!$order) {
            return;
        }

        $payment = $order->getPayment();

        if ($payment && in_array($payment->getMethod(), $methods)) {
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
