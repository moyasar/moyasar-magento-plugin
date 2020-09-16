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
                template: 'Moyasar_Mysr/payment/moyasar_stc_pay'
            },
            getCode: function() {
               return 'moyasar_stc_pay';
            },
            isActive: function() {
               return true;
            },
            redirectAfterPlaceOrder : false,
            afterPlaceOrder: function () {} 
        });
    }
);
