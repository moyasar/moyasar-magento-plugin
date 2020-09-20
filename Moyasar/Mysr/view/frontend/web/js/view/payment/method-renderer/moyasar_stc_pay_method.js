/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/translate',
        'Magento_Checkout/js/action/place-order',
        'Magento_Ui/js/model/messageList',
    ],
    function (
        ko,
        Component,
        url,
        quote,
        $,
        fullScreenLoader,
        additionalValidators,
        mage,
        placeOrderAction,
        globalMessageList
    ) {
        'use strict';

        $.validator.addMethod(
            "saudi_mobile",
            function(value, element, enable) {
                return !enable || this.optional(element) || /^05[503649187][0-9]{7}$/.test(value);
            },
            mage('Please enter a valid Saudi Mobile Number')
        );

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
            getRedirectUrl: function() {
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
            validateMobile: function () {
                var validator = $('#' + this.getCode() + '-form').validate();
                validator.element('#stc_pay_mobile');
            },
            validateOtp: function () {
                var validator = $('#' + this.getCode() + '-form').validate();
                validator.element('#stc_pay_otp');
            },
            isMobileValid: function () {
                return $('#stc_pay_mobile').validation().valid() == true;
            },
            isOtpValid: function () {
                return $('#stc_pay_otp').validation().valid() == true;
            },
            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },
            redirectAfterPlaceOrder : false,
            showingOtp: ko.observable(false),
            transactionUrl: null,
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }

                if (this.showingOtp()) {
                    return this.submitToken(data, event);
                }

                var self = this;

                if (!this.isMobileValid() || !additionalValidators.validate()) {
                    return false;
                }

                this.isPlaceOrderActionAllowed(false);

                var $form = $('#' + this.getCode() + '-form');
                var formData = $form.serialize();

                var request = $.ajax({
                    url: this.moyasarPaymentUrl(),
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                });

                request
                    .done(function (data) {
                        self.isPlaceOrderActionAllowed(true);
                        self.showingOtp(true);
                        self.transactionUrl = data.source.transaction_url;
                    })
                    .fail(function (xhr, status, error) {
                        self.transactionUrl = null;
                        self.isPlaceOrderActionAllowed(true);
                        globalMessageList.addErrorMessage({ message: mage('Error! Payment failed, please try again later.') });
                        if (xhr.responseJSON.message) {
                            globalMessageList.addErrorMessage({ message: xhr.responseJSON.message });
                        }
                    });

                return true;
            },
            submitToken: function (data, event) {
                if (!this.showingOtp()) {
                    return false;
                }

                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (!this.isOtpValid() || !additionalValidators.validate()) {
                    return false;
                }

                this.isPlaceOrderActionAllowed(false);

                var otp = $('#stc_pay_otp').val();

                var request = $.ajax({
                    url: this.transactionUrl,
                    type: 'POST',
                    data: {
                        'otp_value': otp
                    },
                    dataType: 'json',
                });

                request
                    .done(function (data) {
                        if (data.status !== 'paid') {
                            self.isPlaceOrderActionAllowed(true);
                            globalMessageList.addErrorMessage({ message: mage('Error! Payment failed, please try again later.') });

                            if (data.message) {
                                globalMessageList.addErrorMessage({ message: data.message });
                            }

                            self.showingOtp(false);
                            self.transactionUrl = null;
                            return;
                        }

                        self.placeMagentoOrder(data.id)
                            .done(function () {
                                self.afterPlaceOrder(self.getRedirectUrl() + '?status=' + data.status + '&id=' + data.id);
                            })
                            .fail(function () {
                                self.isPlaceOrderActionAllowed(true);
                                self.showingOtp(false);
                                self.transactionUrl = null;
                            });
                    })
                    .fail(function (xhr, status, error) {
                        self.isPlaceOrderActionAllowed(true);
                        self.showingOtp(false);
                        self.transactionUrl = null;
                        globalMessageList.addErrorMessage({ message: mage('Error! Payment failed, please try again later.') });
                        if (xhr.responseJSON.message) {
                            globalMessageList.addErrorMessage({ message: xhr.responseJSON.message });
                        }
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
            afterPlaceOrder: function (url) {
                window.location.href = url;
            }
        });
    }
);
