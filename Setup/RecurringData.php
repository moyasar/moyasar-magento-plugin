<?php

namespace Moyasar\Magento2\Setup;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\UrlInterface;

class RecurringData implements InstallDataInterface
{
    /** @var WriterInterface */
    private $configWriter;

    /** @var ScopeConfigInterface */
    private $configReader;

    /** @var UrlInterface UrlInterface */
    private $urlBuilder;

    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $configReader,
        UrlInterface $urlBuilder
    ) {
        $this->configWriter = $configWriter;
        $this->configReader = $configReader;
        $this->urlBuilder = $urlBuilder;
    }


    /**
     * @description This function is used to install the module, will set a webhook secret and webhook url
     * @inheritdoc
     * @throws Exception
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        // Ensure this only runs once
        if (! $this->configReader->getValue('payment/moyasar_payments/webhook_secret')) {
            $this->configWriter->save('payment/moyasar_payments/webhook_secret', $this->generateToken());
        }

        $url = $this->urlBuilder->getUrl('moyasar_magento2/order/webhook');
        $this->configWriter->save('payment/moyasar_payments/webhook_url', $url);

        $setup->endSetup();
    }

    /**
     * @description Generate token for the webhook endpoint
     * @throws Exception
     */
    private function generateToken()
    {
        return bin2hex(random_bytes(16));
    }
}
