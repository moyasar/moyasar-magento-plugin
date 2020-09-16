<?php

namespace Moyasar\Mysr\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;

class MoyasarStcPay extends AbstractMethod
{
    const CODE = 'moyasar_stc_pay';
    protected $_code = self::CODE;

    public function isAvailable(CartInterface $quote = null) {
        return parent::isAvailable($quote);
    }
}
