<?php

namespace Moyasar\Mysr\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\QuoteRepository;
use Psr\Log\LoggerInterface;

class Cancel implements ActionInterface
{
    /** @var Context */
    private $context;

    /** @var Session */
    private $checkoutSession;

    /** @var LoggerInterface */
    private $logger;

    /** @var QuoteRepository */
    protected $quoteRepository;

    /** @var ManagerInterface */
    protected $eventManager;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        LoggerInterface $logger,
        QuoteRepository $quoteRepository,
        ManagerInterface $eventManager

    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->eventManager = $eventManager;
    }

    public function execute()
    {
        if (!isset($_GET['order_id'])) {
            $this->logger->warning('Moyasar payment page accessed without order_id argument.');
            return $this->redirectToCart();
        }

        $order = $this->lastOrder();
        if (! $order->getId()) {
            $this->logger->warning('Moyasar payment page accessed without last order set.');
            return $this->redirectToCart();
        }

        if ($_GET['order_id'] != $order->getId()) {
            $this->logger->warning('Moyasar payment page accessed with un-matching order ID.');
            return $this->redirectToCart();
        }

        $order->registerCancellation('Payment canceled by user.');
        $order->getPayment()->setCcStatus('failed');
        $order->save();

        return $this->redirectToCart();
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

    private function redirectToCart()
    {
        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('checkout/cart');
    }
}
