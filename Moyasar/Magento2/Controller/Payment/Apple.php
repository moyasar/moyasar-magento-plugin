<?php

namespace Moyasar\Magento2\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Apple implements ActionInterface
{
    const XML_PATH_STORE_NAME = 'general/store_information/name';
    const XML_PATH_DEFAULT_COUNTRY = 'general/country/default';


    protected $context;
    protected $checkoutSession;
    protected $urlBuilder;
    protected $http;
    protected $messageManager;
    protected $logger;
    private $resultJsonFactory;

    /** @var MoyasarHelper */
    private $moyasarHelper;

    private $token;

    /** @var Order */
    private $order;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        Context              $context,
        Session              $checkoutSession,
        MoyasarHelper        $helper,
        UrlInterface         $urlBuilder,
        ManagerInterface     $messageManager,
        LoggerInterface      $logger,
        JsonFactory          $resultJsonFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,

    )
    {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper = $helper;
        $this->urlBuilder = $urlBuilder;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        // Get Post Data
        $resultJson = $this->resultJsonFactory->create();

        // check session
        if (!$this->checkoutSession->getLastRealOrderId()) {
            $this->logger->warning('Moyasar payment accessed without active order.');
            return $resultJson->setData(['message' => 'Invalid request.']);
        }

        $this->order = $this->lastOrder();

        $amount = $this->order->getGrandTotal();
        $response = [
            'countryCode' => $this->getDefaultCountryCode(),
            'currencyCode' => $this->order->getOrderCurrencyCode(),
            'supportedNetworks' => explode(',', $this->scopeConfig->getValue('payment/moyasar_payments/schemes')),
            'merchantCapabilities' => ['supports3DS'],
            'total' => [
                'label' => $this->getStoreName(),
                'amount' => "$amount",
            ]
        ];

        // Success
        return $resultJson->setData($response);
    }


    private function lastOrder()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        // Work around real_last_order_id is lost from current session
        if (!$order->getId()) {
            $order->loadByAttribute('entity_id', $this->checkoutSession->getLastOrderId());
        }

        return $order;
    }

    /**
     * Get default (merchant's) country code
     *
     * @return string
     */
    public function getDefaultCountryCode()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DEFAULT_COUNTRY);
    }

    /**
     * Get store name
     *
     * @return string
     */
    public function getStoreName()
    {
        $store_name = $this->scopeConfig->getValue(self::XML_PATH_STORE_NAME) ?? $this->storeManager->getStore()->getName() ?? 'Store';
        // Check is store english (Regex)
        if (!preg_match('/[A-Za-z]/', $store_name)) {
            $store_name = 'Store';
        }
        return $store_name;
    }


}
