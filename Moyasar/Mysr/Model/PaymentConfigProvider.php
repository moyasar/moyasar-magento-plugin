<?php

namespace Moyasar\Mysr\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Store\Model\StoreManager;
use Moyasar\Mysr\Model\Payment\MoyasarApplePay;
use Moyasar\Mysr\Model\Payment\MoyasarCc;
use Moyasar\Mysr\Model\Payment\MoyasarSTCPay;

class PaymentConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        MoyasarApplePay::CODE,
        MoyasarCc::CODE,
        MoyasarSTCPay::CODE,
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
     * Payment ConfigProvider constructor.
     * @param Data $paymentHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManager $storeManager
     * @throws LocalizedException
     */
    public function __construct(Data $paymentHelper, ScopeConfigInterface $scopeConfig, StoreManager $storeManager) {
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

	public function getConfig()
	{
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                if ($code != MoyasarApplePay::CODE) {
                    $output[$code]['apiKey'] = $this->methods[$code]->getConfigData('api_key');
                }

                if ($code == MoyasarCc::CODE) {
                    $output[$code]['cardsType'] = $this->methods[$code]->getConfigData('cards_type');
                }

                if ($code == MoyasarApplePay::CODE) {
                    $output[$code]['country'] = $this->scopeConfig->getValue('general/country/default');
                    $output[$code]['store_name'] = $this->storeManager->getStore()->getName();
                }
            }
        }

		return $output;
	}
}
