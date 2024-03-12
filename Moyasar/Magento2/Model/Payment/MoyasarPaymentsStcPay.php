<?php

namespace Moyasar\Magento2\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Moyasar\Magento2\Helper\MoyasarHelper;

class MoyasarPaymentsStcPay extends AbstractMethod
{
    const CODE = 'moyasar_payments_stc_pay';

    protected $_code = self::CODE;
    protected $_canUseInternal = false;
    protected $_isGateway = true;

    /**
     * @var string
     * @description Check if the method is active
     */
    public function isActive($storeId = null)
    {
        return $this->_scopeConfig->getValue(MoyasarHelper::XML_PATH_STC_PAY_IS_ACTIVE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
