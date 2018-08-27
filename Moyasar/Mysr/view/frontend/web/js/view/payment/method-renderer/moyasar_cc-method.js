/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (Component, url, quote, $, fullScreenLoader) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_cc'
            },
            getCode: function() {
               return 'moyasar_cc';
            },
            isActive: function() {
               return true;
            },
            getBaseUrl: function() {
                return url.build('moyasar_mysr/redirect/response');
            },
            getApiKey: function () {
                return window.checkoutConfig.moyasar_cc.apiKey;
            },
            isShowLegend: function () {
                return true;
            },
            getAmount: function () {
                var totals = quote.getTotals()();
                var grand_total;
                if (totals) {
                    grand_total = totals.grand_total;
                } else {
                    grand_total = quote.grand_total;
                }
                return grand_total*100;
            },
            redirectAfterPlaceOrder : false,
            afterPlaceOrder: function () {
               jQuery(function($) {
                // TODO: Ensure error in submission handled
                   var submitBtn = $('#submitBtn');
                   submitBtn.click();
                   // fullScreenLoader.stopLoader();
               });
            } 
        });
    }
);
