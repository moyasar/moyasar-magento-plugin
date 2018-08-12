<?php
namespace Moyasar\Mysr\Model\Payment;

class MoyasarSadad extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'moyasar_sadad';
    protected $_code = self::CODE;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}