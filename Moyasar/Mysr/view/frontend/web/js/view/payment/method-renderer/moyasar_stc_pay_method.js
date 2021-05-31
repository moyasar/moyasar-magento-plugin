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
            getCancelOrderUrl: function () {
                return url.build('moyasar_mysr/order/cancel');
            },
            getOrderId: function () {
                return $.ajax({
                    url: url.build('moyasar_mysr/order/data'),
                    method: 'GET',
                    dataType: 'json'
                });
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
            getAmountSmallUnit: function (amount) {
                var currency = this.getCurrency();
                var fractionSize = window.checkoutConfig.moyasar_stc_pay.currencies_fractions[currency];

                if (!fractionSize) {
                    fractionSize = window.checkoutConfig.moyasar_stc_pay.currencies_fractions['DEFAULT'];
                }

                var total = amount ? amount : this.getAmount();

                return total * (10 ** fractionSize);
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

                fullScreenLoader.startLoader();

                this.placeMagentoOrder().done(function (orderId) {
                    self.getOrderId().done(function (orderData) {

                        var paymentData = formData.concat(
                            `&amount=${self.getAmountSmallUnit(orderData['total'])}`+
                            `&currency=${self.getCurrency()}`+
                            `&metadata[order_id]=${orderData['orderId']}`
                        );

                        var request = $.ajax({
                            url: self.moyasarPaymentUrl(),
                            type: 'POST',
                            data: paymentData,
                            dataType: 'json',
                        });

                        request
                            .done(function (data) {
                                self.updateOrderPayment(data).done(function (){
                                    self.isPlaceOrderActionAllowed(true);
                                    if (data.source.transaction_url) {
                                        fullScreenLoader.stopLoader();
                                        self.showingOtp(true);
                                        self.transactionUrl = data.source.transaction_url;
                                    } else {
                                        self.afterPlaceOrder(
                                            self.getCancelOrderUrl() +
                                            '?id=' + data.id +
                                            '&message=' + data.source.message,
                                            true
                                        );
                                    }
                                })
                                .fail(function (){
                                    self.isPlaceOrderActionAllowed(true);
                                    globalMessageList.addErrorMessage({
                                        message: mage('Error! Could not place order.')
                                    });
                                    self.afterPlaceOrder(self.getCancelOrderUrl(), true);
                                });
                            })
                            .fail(function (xhr, status, error) {
                                self.transactionUrl = null;
                                self.isPlaceOrderActionAllowed(true);
                                globalMessageList.addErrorMessage({ message: mage('Error! Payment failed, please try again later.') });
                                if (xhr.responseJSON.message) {
                                    globalMessageList.addErrorMessage({ message: xhr.responseJSON.message });
                                }
                                self.afterPlaceOrder(self.getCancelOrderUrl(), true);
                            });
                        })
                    .fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                        self.afterPlaceOrder(self.getCancelOrderUrl(), true);
                    })
                })
                .fail(function () {
                    self.isPlaceOrderActionAllowed(true);
                    self.afterPlaceOrder(self.getCancelOrderUrl(), true);
                });

                return true;
            },
            submitToken: function (data, event) {
                if (!this.showingOtp()) {
                    return false;
                }

                if (!this.transactionUrl) {
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

                        self.afterPlaceOrder(self.getRedirectUrl() + '?status=' + data.status + '&id=' + data.id);
                    })
                    .fail(function (xhr, status, error) {
                        self.isPlaceOrderActionAllowed(true);
                        self.showingOtp(false);
                        self.transactionUrl = null;
                        globalMessageList.addErrorMessage({ message: mage('Error! Payment failed, please try again later.') });
                        if (xhr.responseJSON.message) {
                            globalMessageList.addErrorMessage({ message: xhr.responseJSON.message });
                        }
                        self.afterPlaceOrder(self.getCancelOrderUrl(), true);
                    });

                return true;
            },
            placeMagentoOrder: function (paymentId) {
                return $.when(placeOrderAction(this.getData(), this.messageContainer));
            },
            updateOrderPayment: function (payment) {
                return $.ajax({
                    url: url.build('moyasar_mysr/order/update'),
                    method: 'POST',
                    data: payment,
                    dataType: 'json'
                });
            },
            afterPlaceOrder: function (url, failed) {
                if (failed) {
                    globalMessageList.addErrorMessage({ message: mage('Error! Payment failed, please try again later.') });
                }
                fullScreenLoader.stopLoader();
                window.location.href = url;
            }
        });
    }
);
