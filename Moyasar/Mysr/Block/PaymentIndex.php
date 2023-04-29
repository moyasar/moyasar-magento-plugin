<?php

namespace Moyasar\Mysr\Block;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Moyasar\Mysr\Helper\CurrencyHelper;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Moyasar\Mysr\Model\Config\PaymentConfigProvider;

class PaymentIndex extends Template
{

    /** @var MoyasarHelper */
    private $helper;

    /** @var PaymentConfigProvider */
    private $configProvider;

    public function __construct(
        Template\Context $context,
        MoyasarHelper $helper,
        PaymentConfigProvider $configProvider
    ) {
        parent::__construct($context);

        $this->helper = $helper;
        $this->configProvider = $configProvider;
    }

    public function formConfig()
    {
        $order = $this->order();
        $config = array_values($this->configProvider->getConfig())[0];

        $metadata = [
            'order_id' => $order->getId(),
            'real_order_id' => $order->getRealOrderId(),
        ];

        if ($address = $order->getShippingAddress()) {
            $metadata = array_merge($metadata, $this->mapAddress($address));
        }

        return [
            'element' => '.mysr-form',
            'amount' => CurrencyHelper::amountToMinor($order->getBaseGrandTotal(), $order->getBaseCurrencyCode()),
            'currency' => $order->getBaseCurrencyCode(),
            'description' => 'Payment for order ' . $order->getRealOrderId(),
            'publishable_api_key' => $this->helper->publishableApiKey(),
            'callback_url' => $this->getUrl('moyasar_mysr/redirect/response'),
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
        $order = $this->order();

        return $order->getBaseCurrencyCode() . ' ' .
            number_format($order->getBaseGrandTotal(), CurrencyHelper::fractionFor($order->getBaseCurrencyCode()));
    }

    public function backUrl()
    {
        return $this->getUrl('moyasar_mysr/payment/cancel') . '?order_id=' . $this->order()->getId();
    }

    private function order()
    {
        return $this->getData('order');
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
