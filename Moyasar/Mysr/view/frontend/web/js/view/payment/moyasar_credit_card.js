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
                type: 'moyasar_credit_card',
                component: 'Moyasar_Mysr/js/view/payment/method-renderer/moyasar_credit_card_method'
            }
        );
        return Component.extend({});
    }
);
