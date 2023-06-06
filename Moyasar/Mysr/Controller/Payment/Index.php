<?php

namespace Moyasar\Mysr\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\CurrencyHelper;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Moyasar\Mysr\Model\Config\PaymentConfigProvider;
use Psr\Log\LoggerInterface;

class Index implements ActionInterface
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

    /** @var ResultFactory */
    private $resultFactory;

    /** @var MoyasarHelper */
    private $helper;

    /** @var PaymentConfigProvider */
    private $configProvider;

    /** @var Order */
    private $order;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        LoggerInterface $logger,
        QuoteRepository $quoteRepository,
        ManagerInterface $eventManager,
        ResultFactory $resultFactory,
        MoyasarHelper $helper,
        PaymentConfigProvider $configProvider

    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->eventManager = $eventManager;
        $this->resultFactory = $resultFactory;
        $this->helper = $helper;
        $this->configProvider = $configProvider;
    }

    public function execute()
    {
        if (!isset($_GET['order_id'])) {
            $this->logger->warning('Moyasar payment page accessed without order_id argument.');
            return $this->redirectToCart();
        }

        $this->order = $order = $this->lastOrder();
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

        $view = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $view->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $view->setContents($this->renderCheckoutPage());

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

    public function formConfig()
    {
        $config = array_values($this->configProvider->getConfig())[0];

        $metadata = [
            'order_id' => $this->order->getId(),
            'real_order_id' => $this->order->getRealOrderId(),
        ];

        if ($address = $this->order->getShippingAddress()) {
            $metadata = array_merge($metadata, $this->mapAddress($address));
        }

        return [
            'element' => '.mysr-form',
            'amount' => CurrencyHelper::amountToMinor($this->order->getBaseGrandTotal(), $this->order->getBaseCurrencyCode()),
            'currency' => $this->order->getBaseCurrencyCode(),
            'description' => 'Payment for order ' . $this->order->getRealOrderId(),
            'publishable_api_key' => $this->helper->publishableApiKey(),
            'callback_url' => $this->context->getUrl()->getUrl('moyasar_mysr/redirect/response'),
            'methods' => $config['methods'],
            'supported_networks' => $config['supported_networks'],
            'base_url' => $config['base_url'],
            'metadata' => $metadata,
            'apple_pay' => [
                'label' => $config['domain_name'],
                'validate_merchant_url' => 'https://api.moyasar.com/v1/applepay/initiate',
                'country' => $config['country']
            ]
        ];
    }

    public function title()
    {
        $order = $this->order;

        return $order->getBaseCurrencyCode() . ' ' .
            number_format($order->getBaseGrandTotal(), CurrencyHelper::fractionFor($order->getBaseCurrencyCode()));
    }

    public function backUrl()
    {
        return $this->context->getUrl()->getUrl('moyasar_mysr/payment/cancel') . '?order_id=' . $this->order->getId();
    }

    private function renderCheckoutPage()
    {
        $block = $this;

        ob_start();
        include __DIR__ . '/checkout.php';

        return ob_get_flush();
    }

    private function mapAddress(OrderAddressInterface $address)
    {
        $keys = [
            OrderAddressInterface::FIRSTNAME,
            OrderAddressInterface::MIDDLENAME,
            OrderAddressInterface::LASTNAME,
            OrderAddressInterface::STREET,
            OrderAddressInterface::CITY,
            OrderAddressInterface::REGION,
            OrderAddressInterface::POSTCODE,
            OrderAddressInterface::EMAIL,
            OrderAddressInterface::TELEPHONE,
            OrderAddressInterface::COMPANY,
        ];

        $prefix = $address->getAddressType();

        return array_merge(...array_map(function ($key) use ($address, $prefix) {
            return [$prefix . "_" . $key => $address->getData($key)];
        }, $keys));
    }
}
