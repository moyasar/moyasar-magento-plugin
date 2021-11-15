<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Magento\Framework\Controller\ResultFactory;

class Cancel extends Action
{
    protected $checkoutSession;

    public function __construct(Context $context, Session $checkoutSession, MoyasarHelper $helper)
    {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper   = $helper;
    }

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        // $paymentId = isset($_POST['id']) ? $_POST['id'] : null;
        // $error = isset($_POST['message']) ? ' Error Message: ' . $_POST['message'] : null;

        $paymentId = isset($_GET['id']) ? $_GET['id'] : null;
        $error = isset($_GET['message']) ? ' Error Message: ' . $_GET['message'] : null;

        $errorMsg = is_null($paymentId) ?
                    'Payment Attempt Failed, and Order have been canceled.' :
                    'Payment ' . $paymentId . ' failed and Order have been canceled. ' . $error;

        $this->moyasarHelper->cancelCurrentOrder($order, $errorMsg);
        $this->checkoutSession->restoreQuote();

        return $this->_redirect('checkout', ['_fragment' => 'payment']);
    }
}
