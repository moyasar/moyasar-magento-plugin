define(
    [
        'jquery',
        'mage/url'
    ],
    function ($, url) {
        'use strict';

        return function (paymentId = null, errors = []) {
            if (! (errors instanceof Array)) {
                errors = [errors.toString()];
            }

            return $.ajax({
                url: url.build('moyasar_mysr/order/cancel'),
                type: 'POST',
                data: {
                    payment_id: paymentId,
                    errors: errors
                },
                dataType: 'json',
            });
        };
    }
);
