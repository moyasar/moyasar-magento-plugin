<?php
namespace Moyasar\Mysr\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;

class MoyasarCreditCard extends AbstractMethod
{
    const CODE = 'moyasar_credit_card';

    protected $_code = self::CODE;
     
    public function isAvailable(CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }
}
