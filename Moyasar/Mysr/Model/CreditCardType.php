<?php

namespace Moyasar\Mysr\Model;

use Magento\Framework\Option\ArrayInterface;

class CreditCardType implements ArrayInterface
{
    const VISA = 'visa';
    const MASTERCARD = 'mastercard';
    const MADA = 'mada';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => static::VISA,
                'label' => __('Visa')
            ],
            [
                'value' => static::MASTERCARD,
                'label' => __('Mastercard')
            ],
            [
                'value' => static::MADA,
                'label' => __('Mada')
            ]
        ];
    }
}
