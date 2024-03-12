<?php

namespace Moyasar\Magento2\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class Failed implements ActionInterface
{
    protected $context;
    protected $checkoutSession;
    protected $moyasarHelper;
    protected $urlBuilder;
    protected $http;
    protected $messageManager;
    protected $logger;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        MoyasarHelper $helper,
        UrlInterface $urlBuilder,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper = $helper;
        $this->urlBuilder = $urlBuilder;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        $order = $this->lastOrder();
        $message = __('Payment failed');

        // Restore quote
        $quoteId = $order->getQuoteId();
        $quote = $this->checkoutSession->getQuote()->load($quoteId);
        if ($quote->getId()) {
            $quote->setIsActive(1);
            $quote->setReservedOrderId(null);
            $quote->save();
        }

        $this->messageManager->addErrorMessage($message);

        if ($order->getState() == Order::STATE_NEW || $order->getState() == Order::STATE_PENDING_PAYMENT){
            $order->addStatusHistoryComment('The order was automatically canceled because the payment failed.', false);

            $order->setState(Order::STATE_CANCELED);
            $order->setStatus(Order::STATE_CANCELED);
            $order->registerCancellation($message);
            $order->getPayment()->setCcStatus('failed');
            $order->save();
        }

        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('checkout/cart');
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
