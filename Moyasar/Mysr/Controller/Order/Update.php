<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class Update implements HttpPostActionInterface
{
    protected $context;
    protected $checkoutSession;
    protected $helper;
    protected $quoteRepository;
    protected $eventManager;
    protected $logger;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        MoyasarHelper $helper,
        QuoteRepository $quoteRepository,
        ManagerInterface $eventManager,
        LoggerInterface $logger
    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->quoteRepository = $quoteRepository;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
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

        // Restore quote to keep cart when the user returns after a failed 3DS attempt
        $this->restoreQuote($order);

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

    /**
     * @param Order $order
     * @return bool
     */
    public function restoreQuote($order)
    {
        try {
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $quote->setIsActive(1)->setReservedOrderId(null);
            $this->quoteRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote)->unsLastRealOrderId();
            $this->eventManager->dispatch('restore_quote', ['order' => $order, 'quote' => $quote]);
            return true;
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}

