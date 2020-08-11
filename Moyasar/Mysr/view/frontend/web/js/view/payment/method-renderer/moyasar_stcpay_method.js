/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
    ],
    function (Component) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_stcpay'
            },
            getCode: function() {
               return 'moyasar_stcpay';
            },
            isActive: function() {
               return true;
            },
            redirectAfterPlaceOrder : false,
            afterPlaceOrder: function () {} 
        });
    }
);