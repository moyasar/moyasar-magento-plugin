<?php

namespace Moyasar\Mysr\Helper;

class CurrencyHelper
{
    private $currencies = [
        'ADP' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'AFN' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'ALL' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'AMD' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'BHD' => [
            'fraction_digits' => 3,
            'rounding_increment' => 0
        ],
        'BIF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'BYN' => [
            'fraction_digits' => 2,
            'rounding_increment' => 0
        ],
        'BYR' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'CAD' => [
            'fraction_digits' => 2,
            'rounding_increment' => 5
        ],
        'CHF' => [
            'fraction_digits' => 2,
            'rounding_increment' => 5
        ],
        'CLF' => [
            'fraction_digits' => 4,
            'rounding_increment' => 0
        ],
        'CLP' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'COP' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'CRC' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'CZK' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'DEFAULT' => [
            'fraction_digits' => 2,
            'rounding_increment' => 0
        ],
        'DJF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'DKK' => [
            'fraction_digits' => 2,
            'rounding_increment' => 50
        ],
        'ESP' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'GNF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'GYD' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'HUF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'IDR' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'IQD' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'IRR' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'ISK' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'ITL' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'JOD' => [
            'fraction_digits' => 3,
            'rounding_increment' => 0
        ],
        'JPY' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'KMF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'KPW' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'KRW' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'KWD' => [
            'fraction_digits' => 3,
            'rounding_increment' => 0
        ],
        'LAK' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'LBP' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'LUF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'LYD' => [
            'fraction_digits' => 3,
            'rounding_increment' => 0
        ],
        'MGA' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'MGF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'MMK' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'MNT' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'MRO' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'MUR' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'NOK' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'OMR' => [
            'fraction_digits' => 3,
            'rounding_increment' => 0
        ],
        'PKR' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'PYG' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'RSD' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'RWF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'SEK' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'SLL' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'SOS' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'STD' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'SYP' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'TMM' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'TND' => [
            'fraction_digits' => 3,
            'rounding_increment' => 0
        ],
        'TRL' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'TWD' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'TZS' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'UGX' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'UYI' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'UYW' => [
            'fraction_digits' => 4,
            'rounding_increment' => 0
        ],
        'UZS' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'VEF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'VND' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'VUV' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'XAF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'XOF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'XPF' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'YER' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'ZMK' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ],
        'ZWD' => [
            'fraction_digits' => 0,
            'rounding_increment' => 0
        ]
    ];

    public function fractionDigits($currency)
    {
        if (!isset($this->currencies[$currency]['fraction_digits'])) {
            return $this->currencies['DEFAULT']['fraction_digits'];
        }

        return $this->currencies[$currency]['fraction_digits'];
    }

    public function fractionsMap()
    {
        $items = [];

        foreach ($this->currencies as $currency => $data) {
            $items[$currency] = $data['fraction_digits'];
        }

        return $items;
    }
}
