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
        'mage/translate'
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
        mage
    ) {
        'use strict';
        return Component.extend({
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
                var fractionSize = window.checkoutConfig.moyasar_credit_card.currencies_fractions[currency];

                if (!fractionSize) {
                    fractionSize = window.checkoutConfig.moyasar_credit_card.currencies_fractions['DEFAULT'];
                }

                return this.getAmount() * (10 ** fractionSize);
            },
            getEmail: function () {
                if (quote.guestEmail) {
                    return "By: " + quote.guestEmail;
                }

                return "By: " + window.checkoutConfig.customerData.email;
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
            fetchOrderID: function () {
                return $.when(placeOrderAction(this.getData(), this.messageContainer));
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

                // place an order and get the orderID
                var fetchOrder = this.fetchOrderID();
                fetchOrder
                    .done(function (data) {
                        // the data here is OrderID
                        $form.find('input[name="description"]').val("Order ID: #" + data + ". " + self.getEmail());
                        var formData = $form.serialize();

                        var mPaymentResult = createMoyasarPayment(formData, self.moyasarPaymentUrl());
                        mPaymentResult
                            .done(function (data) {
                                // the data.id is a moyasar_payment_id 
                                self.addMoyasarPaymentID(data.id)
                                    .done(function () {
                                        self.afterPlaceOrder(data.source.transaction_url);
                                    })
                                    .fail(function (xhr, status, error) {
                                        self.isPlaceOrderActionAllowed(true);
                                    });
                            })
                            .fail(function (xhr, status, error) {
                                self.isPlaceOrderActionAllowed(true);
                                globalMessageList.addErrorMessage({ message: mage('Error! Payment failed, please try again later.') });
                                if (xhr.responseJSON.message) {
                                    globalMessageList.addErrorMessage({
                                        message: xhr.responseJSON.message + ' : ' + JSON.stringify(xhr.responseJSON.errors)
                                    });
                                }
                            });
                    })
                    .fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    });

                return true;
            },
            addMoyasarPaymentID: function (paymentId) {
                return $.ajax({
                    url: url.build('moyasar_mysr/afterpayment/update'),
                    method: "POST",
                    data: { paymentId: paymentId },
                    dataType: "json"
                });
            },
            afterPlaceOrder: function (redirectUrl) {
                window.location.href = redirectUrl;
            }
        });
    }
);
