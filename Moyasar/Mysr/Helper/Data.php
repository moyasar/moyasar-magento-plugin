<?php
namespace Moyasar\Mysr\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderManagementInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $orderManagement;
    protected $_objectManager;
    protected $_curl;
    
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        OrderManagementInterface $orderManagement,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\HTTP\Client\Curl $curl
    ) {
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->_curl = $curl;

        parent::__construct($context);
    }
    /**
     * Save last order and change status to proccessing
     *
     * @param Orderobject $order to be saved
     * @return bool True if order saved, false otherwise
     */
    public function processOrder($order, $id) {
        if ($order->getId() && $order->getState() != Order::STATE_PROCESSING) {
            $order->setStatus(Order::STATE_PROCESSING);
            $order->setState(Order::STATE_PROCESSING);
            $customerNotified = $this->sendOrderEmail($order);
            $order->addStatusToHistory( Order::STATE_PROCESSING , 'Moyasar Payment Successfully completed. ID: '.$id., $customerNotified);
            $order->save();
            // $invoice = $order->prepareInvoice()->register();

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
     * @param string $id Comment appended to order history
     * @param Orderobject $order to be cancelled
     * @return bool True if order cancelled, false otherwise
     */
    public function cancelCurrentOrder($order, $id)
    {
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation('Moyasar Payment Failed. ID: '.$id)->save();
            return true;
        }
        return false;
    }

    public function verifyAmount($order, $payment_id)
    {
        $order_amount = $order->getGrandTotal()*100;
        if ($order->getId() && $order->getState() != Order::STATE_PAYMENT_REVIEW) {
            $order->setStatus(Order::STATE_PAYMENT_REVIEW);
            $order->setState(Order::STATE_PAYMENT_REVIEW);
            $order->addStatusToHistory(Order::STATE_PAYMENT_REVIEW, 'Reviewing payment ID: ' .$payment_id)->save();
        }
        try{ 
            $secretApiKey = $this->scopeConfig->getValue('payment/moyasar_cc/secret_api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $this->_curl->setCredentials($secretApiKey, '');
            $this->_curl->get('https://api.moyasar.com/v1/payments/'.$payment_id);
            $response = json_decode($this->_curl->getBody(), true);

            if (isset($response['message'])) {
                $this->_logger->addDebug($payment_id.' Moyasar Payment Verification Failed: '.$response['message']);
                $order->addStatusHistoryComment('Payment Review Failed: check the transaction manualy in Moyasar Dashboard.');
            }

            if (isset($response['amount']) && $response['amount'] == $order_amount ) return true;
            else {
                $order->addStatusToHistory(Order::STATUS_FRAUD, 'Payment Review Failed: ***possible tampering** | Actual amount paid: '.$response['amount_format'])->save();
                return false;
            }
        }
        catch (\Exception $e) {
            $this->_logger->critical('Error: ', ['exception' => $e]);
            return false;
        }
    }

    public function getUrl($route, $params = [])
    {
        return $this->_getUrl($route, $params);
    }
}