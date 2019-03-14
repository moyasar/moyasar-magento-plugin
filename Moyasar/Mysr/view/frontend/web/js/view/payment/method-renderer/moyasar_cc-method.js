/*browser:true*/
/*global define*/
define(
    [
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'jquery',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/url',
    'jquery/ui'
    ],
    function (
        Component,
        quote,
        $, 
        fullScreenLoader,
        placeOrderAction,
        additionalValidators,
        url
        ) {
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
        validateName: function () {
            var validator = $('#' + this.getCode() + '-form').validate();
            validator.element('#credit_card_name');
        },
        validateNumber: function () {
            var validator = $('#' + this.getCode() + '-form').validate();
            validator.element('#credit_card_number');
        },
        validateExp: function () {
            var validator = $('#' + this.getCode() + '-form').validate();
            validator.element('#credit_card_year');
            validator.element('#credit_card_month');
        },
        validate: function () {
            var $form = $('#' + this.getCode() + '-form');
            return $form.validation() && $form.validation('isValid');
        },
        redirectAfterPlaceOrder : false,
        placeOrder: function (data, event) {
           var self = this;

           if (event) {
               event.preventDefault();
           }
           if (this.validate() && additionalValidators.validate()) {
               this.isPlaceOrderActionAllowed(false);
               $.when(
                   placeOrderAction(this.getData(), this.messageContainer)
                   )
               .fail(
                   function () {
                       self.isPlaceOrderActionAllowed(true);
                   }
                   ).done(
                   function () {
                       self.afterPlaceOrder();
                   }
                   );
                   return true;
               }
               return false;
           },
           afterPlaceOrder: function () {
               var submitBtn = $('#submitBtn');
               submitBtn.click();
               // fullScreenLoader.stopLoader();
           } 
       });
    });