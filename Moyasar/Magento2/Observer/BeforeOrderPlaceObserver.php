<?php

namespace Moyasar\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Moyasar\Magento2\Model\Payment\MoyasarPayments;
use Moyasar\Magento2\Model\Payment\MoyasarPaymentsApplePay;
use Moyasar\Magento2\Model\Payment\MoyasarPaymentsStcPay;

class BeforeOrderPlaceObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        $payment = $order->getPayment();

        if ($payment && (
            $payment->getMethod() == MoyasarPayments::CODE
                || $payment->getMethod() == MoyasarPaymentsStcPay::CODE
                || $payment->getMethod() == MoyasarPaymentsApplePay::CODE
            )) {
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
