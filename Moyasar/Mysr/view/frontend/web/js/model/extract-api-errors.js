define(
    [],
    function () {
        'use strict';

        return function (serverResponse) {
            if (!serverResponse) {
                return [];
            }

            var errors = [];

            if (typeof serverResponse.message === 'string') {
                errors.push(serverResponse.message);
            }

            if (serverResponse.errors instanceof Array) {
                for (var field in serverResponse.errors) {
                    errors.push(field + ": " + serverResponse.errors[field].join(', '))
                }
            }

            if (serverResponse.source && serverResponse.source.message) {
                errors.push(serverResponse.source.message);
            }

            return errors;
        };
    }
);
