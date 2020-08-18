<?php

namespace Moyasar\Mysr\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Moyasar\Mysr\Model\Payment\MoyasarApplePay;
use Moyasar\Mysr\Model\Payment\MoyasarCc;
use Moyasar\Mysr\Model\Payment\MoyasarSadad;

class BeforeOrderPlaceObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $methods = [
            MoyasarApplePay::CODE,
            MoyasarCc::CODE,
            MoyasarSadad::CODE
        ];

        $order = $observer->getOrder();

        if (in_array($order->getPayment()->getMethod(), $methods)) {
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
