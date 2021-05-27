<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Magento\Framework\Controller\ResultFactory;

class Update extends Action
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
        $order   = $this->currentOrder();
        $payment = $order->getPayment();
        $total   = $this->moyasarHelper->orderAmountInSmallestCurrencyUnit($order);

        $paymentId = isset($_POST['id']) ? $_POST['id'] : null;
        $amount    = isset($_POST['amount']) ? $_POST['amount'] : null;

        if (!is_null($payment)) {
            $payment->setAdditionalInformation('moyasar_payment_id', $paymentId);
            $order->save();
        }

        if ($amount != $total) {
            $this->moyasarHelper->rejectFraudPayment($order, $_POST);
            $this->checkoutSession->restoreQuote();
            return $this->resultFactory
                ->create(ResultFactory::TYPE_JSON)
                ->setStatusHeader(400, null, 'Bad Request');
        }
    }

    protected function currentOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }
}
