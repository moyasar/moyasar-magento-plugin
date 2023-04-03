<?php

namespace Moyasar\Mysr\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

class InitialInstall implements DataPatchInterface, PatchRevertableInterface
{
    /** @var WriterInterface */
    private  $configWriter;

    /** @var ScopeConfigInterface */
    private $configReader;

    /** @var UrlInterface */
    private UrlInterface $urlBuilder;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
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
    public function apply()
    {
        if (! $this->configReader->getValue('payment/moyasar_payments/webhook_secret')) {
            $this->configWriter->save('payment/moyasar_payments/webhook_secret', bin2hex(random_bytes(16)));
        }

        $url = $this->urlBuilder->getUrl('moyasar_mysr/order/webhook');
        $this->configWriter->save('payment/moyasar_payments/webhook_url', $url);

        return $this;
    }

    public function revert()
    {
        $this->configWriter->delete('payment/moyasar_payments/webhook_secret');
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        /**
         * This is dependency to another patch. Dependency should be applied first
         * One patch can have few dependencies
         * Patches do not have versions, so if in old approach with Install/Ugrade data scripts you used
         * versions, right now you need to point from patch with higher version to patch with lower version
         * But please, note, that some of your patches can be independent and can be installed in any sequence
         * So use dependencies only if this important for you
         */
        return [
            // SomeDependency::class
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        /**
         * This internal Magento method, that means that some patches with time can change their names,
         * but changing name should not affect installation process, that's why if we will change name of the patch
         * we will add alias here
         */
        return [];
    }
}
