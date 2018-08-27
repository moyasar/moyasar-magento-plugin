<?php

namespace Moyasar\Mysr\Model;
use Magento\Checkout\Model\ConfigProviderInterface;

class PaymentConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        \Moyasar\Mysr\Model\Payment\MoyasarCc::CODE,
        \Moyasar\Mysr\Model\Payment\MoyasarSadad::CODE,
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    /**
     * Payment ConfigProvider constructor.
     * @param \Magento\Payment\Helper\Data $paymentHelper
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper
    ) {
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }
	public function getConfig()
	{
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $output[$code]['apiKey'] = $this->methods[$code]->getConfigData('api_key');
            }
        }
		return $output;
	}
}