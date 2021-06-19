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
use Moyasar\Mysr\Model\Payment\MoyasarApplePay;
use Moyasar\Mysr\Model\Payment\MoyasarCreditCard;
use Moyasar\Mysr\Model\Payment\MoyasarStcPay;

class PaymentConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        MoyasarCreditCard::CODE,
        MoyasarApplePay::CODE,
        MoyasarStcPay::CODE
    ];

    /**
     * @var AbstractMethod[]
     */
    protected $methods = [];

    protected $output = [];

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
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }

        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->moyasarHelper = $moyasarHelper;
        $this->currencyHelper = $currencyHelper;
    }

	public function getConfig()
	{
        $output = [];

        foreach ($this->methods as $method) {
            if (! $method->isAvailable()) {
                continue;
            }

            $code = $method->getCode();
            $configMethod = $this->configMethodName($code);

            $output[$code] = $this->$configMethod($method);
        }

		return $output;
	}

    private function configMethodName($code)
    {
        $code = str_replace('_', ' ', $code);
        $code = ucwords($code);
        $code = str_replace(' ', '', $code);
        return strtolower(substr($code, 0, 1)) . substr($code, 1);
	}

    private function moyasarCreditCard($method)
    {
        return [
            'api_key' => $this->moyasarHelper->moyasarPublishableApiKey(),
            'supported_cards' => $method->getConfigData('cards_type'),
            'currencies_fractions' => $this->currencyHelper->fractionsMap(),
            'payment_url' => $this->moyasarHelper->buildMoyasarUrl('payments')
        ];
	}

    private function moyasarApplePay($method)
    {
        return [
            'api_key' => $this->moyasarHelper->moyasarPublishableApiKey(),
            'country' => $this->scopeConfig->getValue('general/country/default'),
            'store_name' => $this->storeManager->getStore()->getName(),
            'currencies_fractions' => $this->currencyHelper->fractionsMap()
        ];
	}

    private function moyasarStcPay($method)
    {
        return [
            'api_key' => $this->moyasarHelper->moyasarPublishableApiKey(),
            'currencies_fractions' => $this->currencyHelper->fractionsMap(),
            'payment_url' => $this->moyasarHelper->buildMoyasarUrl('payments')
        ];
	}
}
