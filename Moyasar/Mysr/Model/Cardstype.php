<?php
namespace Moyasar\Mysr\Model;

class Cardstype implements \Magento\Framework\Option\ArrayInterface
{
    const VI_MC  = "viMc";
    const MADA = "mada";
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::VI_MC,
                'label' => __('Visa and MasterCard'),
            ],
            [
                'value' => self::MADA,
                'label' => __('Mada Online')
            ]
        ];
    }
}
