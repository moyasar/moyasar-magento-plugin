<?php

namespace Moyasar\Mysr\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class MoyasarPayments extends AbstractMethod
{
    const CODE = 'moyasar_payments';

    protected $_code = self::CODE;
    protected $_canUseInternal = false;
    protected $_isGateway = true;
}
