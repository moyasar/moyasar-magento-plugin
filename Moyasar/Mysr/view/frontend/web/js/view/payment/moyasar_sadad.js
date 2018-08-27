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
                type: 'moyasar_sadad',
                component: 'moyasar_mysr/js/view/payment/method-renderer/moyasar_sadad-method'
            }
        );
        return Component.extend({});
    }
);