define(
    [
        'jquery'
    ],
    function ($) {
        'use strict';

        return function (formData, url) {
            return $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                dataType: 'json',
            });
        };
    }
);
