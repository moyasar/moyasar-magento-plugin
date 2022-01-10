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
                type: 'moyasar_online_payment',
                component: 'Moyasar_Mysr/js/view/payment/method-renderer/moyasar_online_payment_method'
            }
        );
        return Component.extend({});
    }
);
