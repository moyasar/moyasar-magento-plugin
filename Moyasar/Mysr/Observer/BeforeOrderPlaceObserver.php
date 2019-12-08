<?php
namespace Moyasar\Mysr\Observer;

use Magento\Framework\Event\ObserverInterface;

class BeforeOrderPlaceObserver implements ObserverInterface 
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();
        $order->setCanSendNewEmailFlag(false);
    }
}