<?php

namespace Moyasar\Mysr\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManager;
use Moyasar\Mysr\Helper\CurrencyHelper;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Moyasar\Mysr\Model\Payment\MoyasarPayments;

class PaymentConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var MoyasarHelper
     */
    protected $moyasarHelper;

    /**
     * @var CurrencyHelper
     */
    protected $currencyHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManager $storeManager,
        MoyasarHelper $moyasarHelper,
        CurrencyHelper $currencyHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->moyasarHelper = $moyasarHelper;
        $this->currencyHelper = $currencyHelper;
    }

    public function getConfig()
    {
        $storeUrl = $this->storeManager->getStore()->getBaseUrl();
        preg_match('/^.+:\/\/([A-Za-z0-9\-\.]+)\/?.*$/', $storeUrl, $matches);

        $config = [
            'api_key' => $this->moyasarHelper->publishableApiKey(),
            'base_url' => $this->moyasarHelper->apiBaseUrl(),
            'country' => $this->scopeConfig->getValue('general/country/default'),
            'store_name' => $this->storeManager->getStore()->getName(),
            'domain_name' => $matches[1],
            'supported_networks' => explode(',', $this->scopeConfig->getValue('payment/moyasar_payments/schemes')),
            'methods' => explode(',', $this->scopeConfig->getValue('payment/moyasar_payments/methods'))
        ];

        return [
            MoyasarPayments::CODE => $config
        ];
    }
}
