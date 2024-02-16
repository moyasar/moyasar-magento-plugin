<?php

namespace Moyasar\Magento2\Model\Config;

use Magento\Framework\Data\OptionSourceInterface;

class Schemes implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'mada', 'label' => 'mada'],
            ['value' => 'amex', 'label' => 'American Express'],
            ['value' => 'visa', 'label' => 'Visa'],
            ['value' => 'mastercard', 'label' => 'Mastercard'],
        ];
    }
}
