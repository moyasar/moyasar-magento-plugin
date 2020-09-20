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
                template: 'Moyasar_Mysr/payment/moyasar_stc_pay'
            },
            getCode: function() {
               return 'moyasar_sadad';
            },
            isActive: function() {
               return true;
            },
            getBaseUrl: function() {
                return url.build('moyasar_mysr/redirect/response');
            },
            getApiKey: function () {
                return window.checkoutConfig.moyasar_stc_pay.api_key;
            },
            getAmount: function () {
                var totals = quote.getTotals()();

                if (totals) {
                    return totals.base_grand_total;
                }

                return quote.base_grand_total;
            },
            getCurrency: function () {
                var totals = quote.getTotals()();

                if (totals) {
                    return totals.base_currency_code;
                }

                return quote.base_currency_code;
            },
            getAmountSmallUnit: function () {
                var currency = this.getCurrency();
                var fractionSize = window.checkoutConfig.moyasar_stc_pay.currencies_fractions[currency];

                if (!fractionSize) {
                    fractionSize = window.checkoutConfig.moyasar_stc_pay.currencies_fractions['DEFAULT'];
                }

                return this.getAmount() * (10 ** fractionSize);
            },
            moyasarPaymentUrl: function () {
                return window.checkoutConfig.moyasar_stc_pay.payment_url;
            },
            redirectAfterPlaceOrder : false,
            afterPlaceOrder: function () {
               jQuery(function($) {
                // TODO: Ensure error in submission handled
                var submitBtnSadad = $('#submitBtnSadad');
                   submitBtnSadad.click();
                   // fullScreenLoader.stopLoader();
               });
            } 
        });
    }
);
