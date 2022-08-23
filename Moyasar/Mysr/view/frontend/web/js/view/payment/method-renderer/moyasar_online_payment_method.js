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
        'Moyasar_Mysr/js/model/moyasar',
        'Magento_Checkout/js/model/quote',
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
        MoyasarForm,
        quoteModel,
        sendCancelOrder,
        extractApiErrors
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_online_payment'
            },
            initializeForm: function () {
                var self = this;

                MoyasarForm.init({
                    element: '.mysr-form',
                    amount: this.getAmountSmallUnit(),
                    currency: this.getCurrency(),
                    country: this.getCountry(),
                    description: 'Order for: ' + this.getCustomerEmail(),
                    publishable_api_key: this.getApiKey(),
                    callback_url: this.getBaseUrl(),
                    methods: this.getMethod(),
                    supported_networks: this.getNetwork(),
                    on_initiating: this.onFormInit.bind(this),
                    on_completed: this.onCompleted.bind(this),
                    on_failure: this.onFailure.bind(this),
                    apple_pay: {
                        label: this.getDomain(),
                        validate_merchant_url: 'https://api.moyasar.com/v1/applepay/initiate',
                        country: this.getCountry()
                    },
                });
            },
            getCode: function () {
                return 'moyasar_online_payment';
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
            getCustomerEmail: function () {
                return quoteModel.guestEmail ? quoteModel.guestEmail : window.checkoutConfig.customerData.email;
            },
            getCustomerFName: function () {
                return window.checkoutConfig.moyasar_online_payment.f_name;
            },
            getCustomerLName: function () {
                return window.checkoutConfig.moyasar_online_payment.l_name;
            },
            getCustomerProvince: function () {
                return window.checkoutConfig.moyasar_online_payment.province;
            },
            getCustomerCity: function () {
                return window.checkoutConfig.moyasar_online_payment.city;
            },
            getCustomerZip: function () {
                return window.checkoutConfig.moyasar_online_payment.zip;
            },
            getCustomerTelephone: function () {
                return window.checkoutConfig.moyasar_online_payment.telephone;
            },
            getApiKey: function () {
                return window.checkoutConfig.moyasar_online_payment.api_key;
            },
            getDomain: function () {
                return window.checkoutConfig.moyasar_online_payment.domain_name;
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
                var fractionSize = window.checkoutConfig.moyasar_online_payment.currencies_fractions[currency];

                if (!fractionSize) {
                    fractionSize = window.checkoutConfig.moyasar_online_payment.currencies_fractions['DEFAULT'];
                }

                var total = amount ? amount : this.getAmount();

                return total * (10 ** fractionSize);
            },
            getCountry: function () {
                return window.checkoutConfig.moyasar_online_payment.country;
            },
            getMethod: function () {
                return window.checkoutConfig.moyasar_online_payment.methods;
            },
            getNetwork: function () {
                return window.checkoutConfig.moyasar_online_payment.supported_networks;
            },
            getStoreName: function () {
                return window.checkoutConfig.moyasar_online_payment.store_name;
            },
            getValidationUrl: function () {
                return url.build('moyasar_mysr/applepay/validate');
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
                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();
                var getEmail = this.getCustomerEmail();
                var fName = this.getCustomerFName();
                var lName = this.getCustomerLName();
                var country = this.getCountry();
                var province = this.getCustomerProvince();
                var zip = this.getCustomerZip();
                var telephone = this.getCustomerTelephone();
                var city = this.getCustomerCity();

                return new Promise((resolve, reject) => {
                    this.placeMagentoOrder()
                        .done(function (orderId) {
                            fullScreenLoader.startLoader();
                            resolve({
                                'description': 'Order for: ' + getEmail, 'metadata': { 
                                                                                    'order_id': orderId,
                                                                                    'email': getEmail,
                                                                                    'first_name': fName,
                                                                                    'last_name' : lName,
                                                                                    'country': country,
                                                                                    'province': province,
                                                                                    'zip': zip,
                                                                                    'telephone': telephone,
                                                                                    'city': city
                                                                                    }
                            });
                        })
                        .fail(function (response) {
                            reject( mage('Failed placing order, please try again.'));
                        });
                });
            },
            onCompleted: function (payment) {
                var self = this;

                this.payment = payment;
                this.updateOrderPayment(payment)
                    .done(function () {
                        self.isPlaceOrderActionAllowed(true);

                        if (payment.status == 'initiated') {
                            fullScreenLoader.stopLoader();
                            $('#checkout').trigger('processStop');
                        } else {
                            self.cancelOrder(extractApiErrors(xhr.responseJSON));
                        }
                    })
                    .fail(function (xhr) {
                        var errors = extractApiErrors(xhr.responseJSON);
                        errors.push(mage('Error! Payment failed, please try again later.'));
                        self.cancelOrder(errors);
                    });
            },
            onFailure: function (errors) {
                fullScreenLoader.stopLoader();
                this.cancelOrder(["" + errors]);
            }

        });
    }
);
