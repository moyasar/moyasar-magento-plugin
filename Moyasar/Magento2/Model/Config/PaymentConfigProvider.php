<?php

namespace Moyasar\Magento2\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManager;
use Moyasar\Magento2\Helper\CurrencyHelper;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Moyasar\Magento2\Model\Payment\MoyasarPayments;

class PaymentConfigProvider implements ConfigProviderInterface
{

    const XML_PATH_STORE_NAME = 'general/store_information/name';

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


    protected $checkoutSession;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Session $checkoutSession,
        StoreManager $storeManager,
        MoyasarHelper $moyasarHelper,
        CurrencyHelper $currencyHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->moyasarHelper = $moyasarHelper;
        $this->currencyHelper = $currencyHelper;
        $this->checkoutSession = $checkoutSession;
    }

    public function getConfig()
    {
        $storeUrl = $this->storeManager->getStore()->getBaseUrl();
        preg_match('/^.+:\/\/([A-Za-z0-9\-\.]+)\/?.*$/', $storeUrl, $matches);

        $config = [
            'api_key' => $this->moyasarHelper->publishableApiKey(),
            'base_url' => $this->moyasarHelper->apiBaseUrl(),
            'country' => $this->scopeConfig->getValue('general/country/default'),
            'store_name' => $this->getStoreName(),
            'domain_name' => $matches[1],
            'supported_networks' => explode(',', $this->scopeConfig->getValue('payment/moyasar_payments/schemes')),
            'methods' => explode(',', $this->scopeConfig->getValue('payment/moyasar_payments/methods')),
            'messages' => [
                'creditcard' => [
                    'card_required' => __('Card Number is required.'),
                    'card_not_supported' => __('Card Type is not supported.'),
                    'cardholder_required' => __('Cardholder Name is required.'),
                    'cardholder_full_name' => __('Cardholder Name must have first name and last name.'),
                    'expiry_required' => __('Expiration Date is required.'),
                    'cvv_required' => __('CVV is required.'),

                ],
                'stcpay' => [
                    'otp_sent' => __('OTP has been sent.'),
                    'otp_required' => __('OTP is required.'),
                    'phone_start' => __('Phone number must start with 05.'),
                    'submit' => __('Submit'),
                ]

            ]
        ];

        return [
            MoyasarPayments::CODE => $config
        ];
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
        if (!preg_match('/\A\p{ASCII}+\z/', $store_name)) {
            $store_name = 'Store';
        }
        return $store_name;
    }
}
