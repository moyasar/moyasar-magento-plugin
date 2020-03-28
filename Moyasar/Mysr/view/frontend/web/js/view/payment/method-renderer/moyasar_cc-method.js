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
        'jquery/ui',
        'Moyasar_Mysr/js/model/create-payment'
    ],
    function (
        Component,
        quote,
        $,
        fullScreenLoader,
        placeOrderAction,
        additionalValidators,
        url,
        jqueryUi,
        createMoyasarPayment
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_cc'
            },
            getCode: function () {
                return 'moyasar_cc';
            },
            isActive: function () {
                return true;
            },
            getBaseUrl: function () {
                return url.build('moyasar_mysr/redirect/response');
            },
            getApiKey: function () {
                return window.checkoutConfig.moyasar_cc.apiKey;
            },
            getCardsType: function () {
                var methods = window.checkoutConfig.moyasar_cc.cardsType.split(',');
                return methods;
            },
            isShowLegend: function () {
                return true;
            },
            getAmount: function () {
                var totals = quote.getTotals()();
                if (totals) return totals.base_grand_total * 100;
                else return quote.base_grand_total * 100;
            },
            getEmail: function () {
                if (quote.guestEmail) return "Order By a guest : " + quote.guestEmail;
                else return "Order By a customer : " + window.checkoutConfig.customerData.email;
            },
            getCurrency: function () {
                var totals = quote.getTotals()();
                if (totals) return totals.base_currency_code;
                else return quote.base_currency_code;
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
            redirectAfterPlaceOrder: false,
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (!this.validate() || !additionalValidators.validate()) {
                    return false;
                }

                this.isPlaceOrderActionAllowed(false);

                var $form = $('#' + this.getCode() + '-form');
                var formData = $form.serialize();

                var mPaymentResult = createMoyasarPayment(formData);

                mPaymentResult
                    .done(function (data) {
                        self.placeMagentoOrder(data.id)
                            .done(function () {
                                self.afterPlaceOrder(data.source.transaction_url);
                            })
                            .fail(function () {
                                self.isPlaceOrderActionAllowed(true);
                            });
                    })
                    .fail(function (xhr, status, error) {
                        self.isPlaceOrderActionAllowed(true);
                        console.log(xhr.responseJSON);
                    });



                return true;
            },
            placeMagentoOrder: function (paymentId) {
                var paymentData = this.getData();
                paymentData.additional_data = {
                    'moyasar_payment_id': paymentId
                };

                return $.when(placeOrderAction(paymentData, this.messageContainer));
            },
            afterPlaceOrder: function (redirectUrl) {
                window.location.href = redirectUrl;
            }
        });
    });
