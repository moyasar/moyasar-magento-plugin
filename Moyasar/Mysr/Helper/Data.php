<?php
namespace Moyasar\Mysr\Helper;

use Magento\Sales\Model\Order;

class Data extends \Magento\Payment\Helper\Data
{
    /**
     * Save last order and change status to proccessing
     *
     * @param Orderobject $order to be saved
     * @return bool True if order saved, false otherwise
     */
    public function processOrder($order) {
        if ($order->getState() != Order::STATE_PROCESSING) {
            $order->setStatus(Order::STATE_PROCESSING);
            $order->setState(Order::STATE_PROCESSING);
            $order->save();
            $order->addStatusToHistory( Order::STATE_PROCESSING , 'Moyasar_Mysr :: Order has been paid.' );
            $order->save();
            return true;
        }
        return false;
    }

    /**
     * Cancel last placed order with specified comment message
     *
     * @param string $comment Comment appended to order history
     * @param Orderobject $order to be cancelled
     * @return bool True if order cancelled, false otherwise
     */
    public function cancelCurrentOrder($order, $comment)
    {
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->setStatus(Order::STATE_CANCELED);
            $order->setState(Order::STATE_CANCELED);
            $order->registerCancellation($comment+' FAILED')->save();
            $order->addStatusHistoryComment( Order::STATE_CANCELED , 'Moyasar_Mysr :: Order failed.'+$comment );
            $order->save();
            return true;
        }
        return false;
    }

    public function getUrl($route, $params = [])
    {
        return $this->_getUrl($route, $params);
    }
}