<?php
namespace Moyasar\Mysr\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderManagementInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $orderManagement;
    protected $_objectManager;
    
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        OrderManagementInterface $orderManagement,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;

        parent::__construct($context);
    }
    /**
     * Save last order and change status to proccessing
     *
     * @param Orderobject $order to be saved
     * @return bool True if order saved, false otherwise
     */
    public function processOrder($order, $id) {
        if ($order->getId() $order->getState() != Order::STATE_PROCESSING) {
            $order->setStatus(Order::STATE_PROCESSING);
            $order->setState(Order::STATE_PROCESSING);
            $order->save();
            $customerNotified = $this->sendOrderEmail($order);
            $order->addStatusToHistory( Order::STATE_PROCESSING , 'Moyasar payment with ID - ' .$id.' - has been paid.', $customerNotified);
            $order->save();
            return true;
        }
        return false;
    }
    
    public function sendOrderEmail($order) {
        $result = true;
        try{
            if($order->getId() && $order->getState() != $order::STATE_PROCESSING) {
                $orderCommentSender = $this->_objectManager
                    ->create('Magento\Sales\Model\Order\Email\Sender\OrderCommentSender');
                $orderCommentSender->send($order, true, '');
            }
            else{
                $this->orderManagement->notify($order->getEntityId());
            }
        } catch (\Exception $e) {
            $result = false;
        }
        
        return $result;
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