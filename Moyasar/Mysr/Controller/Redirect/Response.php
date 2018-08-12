<?php
namespace Moyasar\Mysr\Controller\Redirect;

class Response extends \Magento\Framework\App\Action\Action
{
   protected $checkoutSession;
   protected $_helper;

   public function __construct(
       \Magento\Framework\App\Action\Context $context,
       \Magento\Checkout\Model\Session $checkoutSession,
       \Moyasar\Mysr\Helper\Data $helper
       ){
       parent::__construct($context);
       $this->_checkoutSession = $checkoutSession;
       $this->_helper = $helper;
   }
   public function execute()
   {
    $order = $this->getOrder();
    $callbackUrl = $this->getHelper()->getUrl('checkout/onepage/success');

    if($_GET['status'] == 'paid') {
        $this->getHelper()->processOrder($order);
        $callbackUrl = $this->getHelper()->getUrl('checkout/onepage/success');
    } else {
      if($this->getHelper()->cancelCurrentOrder($order, $_GET['message']))
      {
        $this->_checkoutSession->restoreQuote();
        $message = __('خطأ: فشلت عملية الدفع، الرجاء المحاولة مرة أخرى.');
        $this->messageManager->addError( $message );
        $callbackUrl = $this->getHelper()->getUrl('checkout/cart');
      }
      else {
        $callbackUrl = $this->getHelper()->getUrl('checkout/cart');
      }
    }
        $this->getResponse()->setRedirect($callbackUrl);
    }

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        return $this->_checkoutSession->getLastRealOrder();
    }

    /**
     * Get moyasar helper
     *
     * @return \Moyasar\Mysr\Helper\Data
     */
    protected function getHelper()
    {
        return $this->_helper;
    }
}