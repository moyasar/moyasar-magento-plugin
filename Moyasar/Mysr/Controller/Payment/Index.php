<?php

namespace Moyasar\Mysr\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class Index implements ActionInterface
{
    /** @var Context */
    private $context;

    /** @var Session */
    private $checkoutSession;

    /** @var LoggerInterface */
    private $logger;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var QuoteRepository */
    protected $quoteRepository;

    /** @var ManagerInterface */
    protected $eventManager;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        LoggerInterface $logger,
        LayoutFactory $layoutFactory,
        QuoteRepository $quoteRepository,
        ManagerInterface $eventManager

    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->layoutFactory = $layoutFactory;
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

        $this->restoreQuote($order);

        $this->logger->info('Rendering Moyasar payment page for order ' . $order->getId());

        $view = $this->layoutFactory->create();
        $view->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $view->getLayout()->getBlock('payment_index')->setData('order', $order);

        return $view;
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

    /**
     * @param Order $order
     * @return bool
     * @throws NoSuchEntityException
     */
    public function restoreQuote($order)
    {
        try {
            $quote = $this->quoteRepository->get($order->getQuoteId());
            if (! $quote->getReservedOrderId()) {
                return true;
            }

            $this->logger->info('Restoring quote for order ' . $order->getId() . ', quote ' . $order->getQuoteId());

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
