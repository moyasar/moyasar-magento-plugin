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
        'Moyasar_Mysr/js/model/create-payment',
        'Magento_Ui/js/model/messageList',
        'mage/translate',
        'Moyasar_Mysr/js/model/cancel-order',
        'Moyasar_Mysr/js/model/extract-api-errors'
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
        createMoyasarPayment,
        globalMessageList,
        mage,
        sendCancelOrder,
        extractApiErrors
    ) {
        'use strict';
        return Component.extend({
            payment: null,
            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_credit_card'
            },
            getCode: function () {
                return 'moyasar_credit_card';
            },
            isActive: function () {
                return true;
            },
            getBaseUrl: function () {
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
                return window.checkoutConfig.moyasar_credit_card.api_key;
            },
            getCardsType: function () {
                return window.checkoutConfig.moyasar_credit_card.supported_cards.split(',');
            },
            isShowLegend: function () {
                return true;
            },
            getAmount: function () {
                return parseFloat(quote.totals()['base_grand_total']).toFixed(2);
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
                var fractionSize = window.checkoutConfig.moyasar_credit_card.currencies_fractions[currency];

                if (!fractionSize) {
                    fractionSize = window.checkoutConfig.moyasar_credit_card.currencies_fractions['DEFAULT'];
                }

                var total = amount ? amount : this.getAmount();

                return total * (10 ** fractionSize);
            },
            getEmail: function () {
                if (quote.guestEmail) {
                    return "Order By a guest : " + quote.guestEmail;
                }

                return "Order By a customer : " + window.checkoutConfig.customerData.email;
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
            moyasarPaymentUrl: function () {
                return window.checkoutConfig.moyasar_credit_card.payment_url;
            },
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (!this.validate() || !additionalValidators.validate()) {
                    return false;
                }

                this.isPlaceOrderActionAllowed(false);
                this.payment = null;

                var $form = $('#' + this.getCode() + '-form');
                var formData = $form.serialize();

                fullScreenLoader.startLoader();

                this.placeMagentoOrder().done(function (orderId) {
                    self.getOrderId().done(function (orderData) {

                        var paymentData = formData.concat(
                            `&amount=${self.getAmountSmallUnit(orderData['total'])}`+
                            `&currency=${self.getCurrency()}`+
                            `&description=${self.getEmail()}`+
                            `&metadata[order_id]=${orderData['orderId']}`
                        );

                        var mPaymentResult = createMoyasarPayment(paymentData, self.moyasarPaymentUrl());

                        mPaymentResult
                            .done(function (paymentObject) {
                                self.payment = paymentObject;

                                self.updateOrderPayment(paymentObject)
                                    .done(function () {
                                        if (paymentObject.status === 'initiated') {
                                            window.location.href = paymentObject.source.transaction_url;
                                        } else if (paymentObject.status === 'authorized') {
                                            window.location.href = paymentObject.callback_url;
                                        } else {
                                            self.cancelOrder(extractApiErrors(xhr.responseJSON));
                                        }
                                    })
                                    .fail(function () {
                                        self.cancelOrder([ mage('Error! Could not place order.') ]);
                                    });
                            })
                            .fail(function (xhr) {
                                var errors = extractApiErrors(xhr.responseJSON);
                                errors.push(mage('Error! Payment failed, please try again later.'));
                                self.cancelOrder(errors);
                            });
                        })
                        .fail(self.handlePlaceOrderFail)
                    })
                    .fail(self.handlePlaceOrderFail);

                return true;
            },
            placeMagentoOrder: function () {
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
            handlePlaceOrderFail: function () {
                this.isPlaceOrderActionAllowed(true);
                globalMessageList.addErrorMessage({ message: mage('Could not place order.') });
            },
            cancelOrder(errors) {
                var self = this;
                var paymentId = this.payment ? this.payment.id : null;

                sendCancelOrder(paymentId, errors).always(function (data) {
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                    globalMessageList.addErrorMessage({ message: errors.join(", ") });
                });
            }
        });
    }
);