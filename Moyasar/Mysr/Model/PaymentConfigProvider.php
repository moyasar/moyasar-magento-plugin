<?php

namespace Moyasar\Mysr\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Store\Model\StoreManager;
use Moyasar\Mysr\Helper\CurrencyHelper;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Moyasar\Mysr\Model\Payment\MoyasarOnlinePayment;

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

    /**
     * Payment ConfigProvider constructor.
     * @param Data $paymentHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManager $storeManager
     * @param MoyasarHelper $moyasarHelper
     * @param CurrencyHelper $currencyHelper
     * @throws LocalizedException
     */
    public function __construct(
        Data $paymentHelper,
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
        return [
            MoyasarOnlinePayment::CODE => [
                'api_key' => $this->moyasarHelper->moyasarPublishableApiKey(),
                'currencies_fractions' => $this->currencyHelper->fractionsMap(),
                'payment_url' => $this->moyasarHelper->buildMoyasarUrl('payments'),
                'country' => $this->scopeConfig->getValue('general/country/default'),
                'store_name' => $this->storeManager->getStore()->getName(),
                'methods' => $this->moyasarHelper->methodEnabled(),
                'domain_name' => $this->moyasarHelper->getInitiativeContext(),
                'supported_networks' => $this->moyasarHelper->supportNetwork()
            ]           
        ];
	}
}
