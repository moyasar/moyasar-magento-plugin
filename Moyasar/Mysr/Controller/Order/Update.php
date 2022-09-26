<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\MoyasarHelper;

class Update implements HttpPostActionInterface
{
    protected $context;
    protected $checkoutSession;
    protected $helper;

    public function __construct(Context $context, Session $checkoutSession, MoyasarHelper $helper)
    {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    public function execute()
    {
        $order = $this->lastOrder();
        $payment = $order->getPayment();
        $paymentId = $_POST['id'] ?? null;

        $payment->setLastTransId($paymentId);
        $order->addCommentToStatusHistory("Waiting payment $paymentId");
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->save();

        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_JSON)
            ->setHttpResponseCode(201)
            ->setData([
                'message' => 'Order updated successfully',
                'order_id' => $order->getId(),
                'real_order_id' => $order->getIncrementId()
            ]);
    }

    private function lastOrder()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        // Work around real_last_order_id is lost from current session
        if (! $order->getId()) {
            $order->loadByAttribute('entity_id', $this->checkoutSession->getLastOrderId());
        }

        return $order;
    }
}
