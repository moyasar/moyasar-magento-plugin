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
                type: 'moyasar_cc',
                component: 'Moyasar_Mysr/js/view/payment/method-renderer/moyasar_cc-method'
            }
        );
        return Component.extend({});
    }
);
