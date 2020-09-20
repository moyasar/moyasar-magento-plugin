<?php

namespace Moyasar\Mysr\Model;

use Magento\Framework\Option\ArrayInterface;

class CreditCardType implements ArrayInterface
{
    const VISA_MASTERCARD = 'viMc';
    const MADA = 'mada';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => static::VISA_MASTERCARD,
                'label' => __('Visa and MasterCard')
            ],
            [
                'value' => static::MADA,
                'label' => __('Mada Online')
            ]
        ];
    }
}
