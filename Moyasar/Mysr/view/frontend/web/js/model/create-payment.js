define(
    [
        'jquery'
    ],
    function ($) {
        'use strict';

        return function (formData) {
            return $.ajax({
                url: 'https://api.moyasar.com/v1/payments',
                type: 'POST',
                data: formData,
                dataType: 'json',
            });
        };
    }
);
