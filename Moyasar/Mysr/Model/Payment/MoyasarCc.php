<?php
namespace Moyasar\Mysr\Model\Payment;

class MoyasarCc extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'moyasar_cc';
    protected $_code = self::CODE;
     
    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}