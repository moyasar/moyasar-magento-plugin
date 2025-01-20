define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
        'domReady!'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'moyasar_payments_samsung_pay',
                component: 'Moyasar_Magento2/js/view/payment/method-renderer/moyasar_payments_samsung_pay'
            }
        );
        return Component.extend({});
    }
);
