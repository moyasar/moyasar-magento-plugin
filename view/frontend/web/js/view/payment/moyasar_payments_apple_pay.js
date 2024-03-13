define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'moyasar_payments_apple_pay',
                component: 'Moyasar_Magento2/js/view/payment/method-renderer/moyasar_payments_apple_pay'
            }
        );
        return Component.extend({});
    }
);
