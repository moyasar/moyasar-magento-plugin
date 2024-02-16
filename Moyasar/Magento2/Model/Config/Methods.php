<?php

namespace Moyasar\Magento2\Model\Config;

use Magento\Framework\Data\OptionSourceInterface;

class Methods implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'creditcard', 'label' => 'Credit Card'],
            ['value' => 'applepay', 'label' => 'Apple Pay'],
            ['value' => 'stcpay', 'label' => 'STC Pay'],
        ];
    }
}
