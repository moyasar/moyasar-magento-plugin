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
                type: 'moyasar_stcpay',
                component: 'Moyasar_Mysr/js/view/payment/method-renderer/moyasar_stc_pay_method'
            }
        );
        return Component.extend({});
    }
);
