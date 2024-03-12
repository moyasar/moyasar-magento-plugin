<?php

namespace Moyasar\Magento2\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Moyasar\Magento2\Helper\MoyasarHelper;

class MoyasarPayments extends AbstractMethod
{
    const CODE = 'moyasar_payments';

    protected $_code = self::CODE;
    protected $_canUseInternal = false;
    protected $_isGateway = true;

    /**
     * @var string
     * @description Check if the method is active
     */
    public function isActive($storeId = null)
    {
        return $this->_scopeConfig->getValue(MoyasarHelper::XML_PATH_CREDIT_CARD_IS_ACTIVE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
