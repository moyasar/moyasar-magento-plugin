<?php

namespace Moyasar\Mysr\Setup;

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
     * @inheritdoc
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // Ensure this only runs once
        if (! $this->configReader->getValue('payment/moyasar_payments/webhook_secret')) {
            $this->configWriter->save('payment/moyasar_payments/webhook_secret', bin2hex(random_bytes(16)));
        }

        $url = $this->urlBuilder->getUrl('moyasar_mysr/order/webhook');
        $this->configWriter->save('payment/moyasar_payments/webhook_url', $url);
    }
}
