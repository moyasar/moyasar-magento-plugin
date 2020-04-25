<?php

namespace Moyasar\Mysr\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\Data;

class Response extends Action
{
    protected $_checkoutSession;
    protected $_helper;

    public function __construct(Context $context, Session $checkoutSession, Data $helper)
    {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_helper = $helper;
    }

    public function execute()
    {
        $order = $this->getOrder();
        $callbackUrl = $this->getHelper()->getUrl('checkout/onepage/success');

        if ($_GET['status'] == 'paid') {
            if ($this->getHelper()->verifyAmount($order, $_GET['id'])) {
                $this->getHelper()->processOrder($order, $_GET['id']);
            }
        } else {
            if ($this->getHelper()->cancelCurrentOrder($order, $_GET['message'])) {
                $this->_checkoutSession->restoreQuote();
                $message = __('Error! Payment failed, please try again later.');
                $this->messageManager->addError($message);
                $callbackUrl = $this->getHelper()->getUrl('checkout/cart');
            } else {
                $callbackUrl = $this->getHelper()->getUrl('checkout/cart');
            }
        }

        $this->getResponse()->setRedirect($callbackUrl);
    }

    /**
     * Get order object
     *
     * @return Order
     */
    protected function getOrder()
    {
        return $this->_checkoutSession->getLastRealOrder();
    }

    /**
     * Get moyasar helper
     *
     * @return Data
     */
    protected function getHelper()
    {
        return $this->_helper;
    }
}
