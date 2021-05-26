<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\ResultFactory;

class Data extends Action
{
    protected $checkoutSession;

    public function __construct(Context $context, Session $checkoutSession)
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $order   = $this->currentOrder();

        $orderId = $order->getIncrementId();
        $total   = $order->getGrandTotal();

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)
            ->setData([
                'orderId' => $orderId,
                'total' => $total,
            ]);
    }

    protected function currentOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }
}
