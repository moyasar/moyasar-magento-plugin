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
        'Moyasar_Mysr/js/model/moyasar',
        'Magento_Checkout/js/model/quote',
        'Moyasar_Mysr/js/model/cancel-order',
        'Moyasar_Mysr/js/model/extract-api-errors',
        'Moyasar_Mysr/js/model/currency-helper'
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
        MoyasarForm,
        quoteModel,
        sendCancelOrder,
        extractApiErrors,
        currencyHelper
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_payments'
            },
            
            initializeForm: function () {
                var config = window.checkoutConfig.moyasar_payments;

                MoyasarForm.init({
                    element: '.mysr-form',
                    amount: currencyHelper.to_minor(this.getAmount(), this.getCurrency()),
                    currency: this.getCurrency(),
                    description: 'Order for: ' + this.getCustomerEmail(),
                    publishable_api_key: config.api_key,
                    callback_url: url.build('moyasar_mysr/redirect/response'),
                    methods: config.methods,
                    supported_networks: config.supported_networks,
                    on_initiating: this.onFormInit.bind(this),
                    on_completed: this.onCompleted.bind(this),
                    on_failure: this.onFailure.bind(this),
                    base_url: config.base_url,
                    apple_pay: {
                        label: config.domain_name,
                        validate_merchant_url: 'https://api.moyasar.com/v1/applepay/initiate',
                        country: config.country
                    },
                });
            },
            getCode: function () {
                return 'moyasar_payments';
            },
            isActive: function () {
                return true;
            },
            getCustomerEmail: function () {
                return quoteModel.guestEmail ? quoteModel.guestEmail : window.checkoutConfig.customerData.email;
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
            getData: function () {
                return {
                    'method': this.getCode()
                };
            },
            fetchOrderData: function () {
                return $.ajax({
                    url: url.build('moyasar_mysr/order/data'),
                    method: 'GET',
                    dataType: 'json'
                });
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
            cancelOrder: function (errors) {
                var self = this;
                var paymentId = this.payment ? this.payment.id : null;

                sendCancelOrder(paymentId, errors).always(function (data) {
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                    globalMessageList.addErrorMessage({ message: errors.join(", ") });
                });
            },
            onFormInit: function () {
                var self = this;

                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                var customerEmail = this.getCustomerEmail();

                return new Promise((resolve, reject) => {
                    this.placeMagentoOrder().done(function (orderId) {
                        fullScreenLoader.startLoader();

                        self.fetchOrderData()
                            .done(function (data) {
                                resolve({
                                    'description': 'Order for: ' + customerEmail,
                                    'metadata': data
                                });
                            })
                            .fail(function (response) {
                                reject(self.jsonErrorMessage(response.responseJSON, mage('Failed loading order data.')))
                            })
                    })
                    .fail(function (response) {
                        reject(self.jsonErrorMessage(response.responseJSON, mage('Failed placing order, please try again.')))
                    });
                });
            },
            onCompleted: function (payment) {
                var self = this;
                this.payment = payment;

                return new Promise(function (resolve, reject) {
                    self.updateOrderPayment(payment)
                        .done(function (data, status, xhr) {
                            self.isPlaceOrderActionAllowed(true);

                            if (payment.status === 'initiated') {
                                fullScreenLoader.stopLoader();
                                $('#checkout').trigger('processStop');
                            } else {
                                self.cancelOrder(extractApiErrors(xhr.responseJSON));
                            }

                            resolve();
                        })
                        .fail(function (xhr) {
                            var errors = extractApiErrors(xhr.responseJSON);
                            errors.push(mage('Error! Payment failed, please try again later.'));
                            self.cancelOrder(errors);

                            reject(errors.join(', '))
                        });
                });
            },
            onFailure: function (errors) {
                fullScreenLoader.stopLoader();
                this.cancelOrder(["" + errors]);
            },
            jsonErrorMessage: function (json, defaultValue) {
                if (!json) {
                    return defaultValue;
                }

                var message = json['errors'] || json['message'] || defaultValue;
                if (message instanceof Array) {
                    message = message.join(', ')
                }

                return message;
            }
        });
    }
);
