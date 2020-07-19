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
                type: 'moyasar_apple_pay',
                component: 'Moyasar_Mysr/js/view/payment/method-renderer/moyasar_apple_pay'
            }
        );
        return Component.extend({});
    }
);
