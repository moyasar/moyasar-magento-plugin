define([
    'Magento_Checkout/js/view/payment/default',
    'ko',
    'jquery',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/place-order',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'domReady!',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/checkout-data',
], function (
    Component,
    ko,
    $,
    fullScreenLoader,
    placeOrderAction,
    url,
    globalMessageList,
    quote,
    totals,
    checkoutData
) {
    'use strict';

    return Component.extend({
        isVisible: ko.observable(false),
        samsungPayClient: null,
        samsungPaymentMethods: null,
        defaults: {
            template: 'Moyasar_Magento2/payment/moyasar_payments_samsung_pay'
        },

        initialize: function () {
            // Call parent
            this._super();
            // 2) Attempt to create the Samsung Pay client
            this.initSamsungPayClient();
            // Force isPlaceOrderActionAllowed to true to avoid billing address validation
            this.isPlaceOrderActionAllowed(true);
        },

        /**
         * Initialize the Samsung Pay client
         */
        initSamsungPayClient: function () {
            // If SamsungPay isn't available in this browser, exit.
            if (typeof SamsungPay === 'undefined' || typeof SamsungPay.PaymentClient === 'undefined') {
                console.warn('[Moyasar] Samsung Pay SDK not found or not supported in this browser.');
                return;
            }
            // Check service id and supported networks
            if (!window.checkoutConfig.moyasar_payments.samsung.service_id || window.checkoutConfig.moyasar_payments.samsung.supported_networks.length === 0) {
                console.error('[Moyasar] Samsung Pay service ID or supported networks not found in store config.');
                return;
            }

            // Check if device is apple
            if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
                console.info('[Moyasar] Samsung Pay is not supported on Apple devices.');
                return;
            }

            // Create the client
            this.samsungPayClient = new SamsungPay.PaymentClient({
                environment: 'PRODUCTION'
            });

            // The same paymentMethods you’d pass to isReadyToPay()
            this.samsungPaymentMethods = {
                version: '2',
                serviceId: window.checkoutConfig.moyasar_payments.samsung.service_id,
                protocol: 'PROTOCOL_3DS',
                allowedBrands: window.checkoutConfig.moyasar_payments.samsung.supported_networks
            };


            // Check if Samsung Pay is actually ready on this device
            this.samsungPayClient.isReadyToPay(this.samsungPaymentMethods)
                .then((response) => {
                    if (response.result) {
                        // If yes, generate the button in #samsung-pay-container
                            this.isVisible(true);
                    } else {
                        console.warn('[Moyasar] Samsung Pay is not supported on this device or no cards set up.');
                    }
                })
                .catch(function (err) {
                    console.error('[Moyasar] Error checking Samsung Pay readiness:', err);
                });
        },
        /**
         * Standard Magento 2 method code
         */
        getCode: function () {
            return 'moyasar_payments_samsung_pay';
        },
        getSamsungPayCode: function () {
            return 'moyasar_payments_samsung_pay';
        },
        isActive: function () {
            return true;
        },
        getData: function () {
            return {
                'method': this.getCode()
            };
        },

        /**
         * Called when the user clicks the Samsung Pay button
         * (linked via onClick in createAndAddButton()).
         */
        placeOrderSamsungPay: function () {
            const self = this;

            // If no PaymentClient, abort
            if (!this.samsungPayClient) {
                globalMessageList.addErrorMessage({message: 'Samsung Pay is not initialized properly.'});
                return;
            }

            // Start Magento’s loader
            fullScreenLoader.startLoader();
            self.isPlaceOrderActionAllowed(true);

            this.startSamsungPayFlow();
        },

        /**
         * After Magento order is placed, start the Samsung Pay flow
         * (i.e., loadPaymentSheet).
         */
        startSamsungPayFlow: function () {
            const self = this;
            const data = checkoutData.totals();
            const amount = data.base_grand_total;

            const transactionDetail = {
                orderNumber: 'ORDER-' + new Date().getTime(),
                merchant: {
                    name: window.checkoutConfig.moyasar_payments.samsung.store_name,
                    countryCode: window.checkoutConfig.moyasar_payments.country || 'SA',
                    url: window.location.hostname
                },
                amount: {
                    option: 'FORMAT_TOTAL_PRICE_ONLY',
                    currency: data.base_currency_code,
                    total: amount
                }
            };

            // Actually request user’s payment
            this.samsungPayClient.loadPaymentSheet(this.samsungPaymentMethods, transactionDetail)
                .then((credentials) => {
                    // The user authorized the payment, get the token
                    $.when(placeOrderAction(this.getData(), this.messageContainer))
                        .done(() => {
                            self.handlePaymentAuthorization(credentials);
                        });

                })
                .catch(function (err) {
                    // The user canceled or something else failed
                    self.abortSamsungPay('Samsung Pay flow canceled or failed.', err);
                    self.samsungPayClient.notify({status: 'CANCELED', provider: 'Moyasar'});
                });
        },
        /**
         * Handle the payment token from Samsung Pay
         */
        handlePaymentAuthorization: function (credentials) {
            const self = this;

            const token = credentials['3DS'] && credentials['3DS']['data'];
            if (!token) {
                self.abortSamsungPay('No payment token received from Samsung Pay.');
                return;
            }

            $.ajax({
                url: url.build('moyasar/payment/initiate'),
                type: 'POST',
                data: {
                    token: credentials['3DS']['data'],
                    method: 'samsungpay'
                },
                success: function (response) {
                    if (response.status === 'failed') {
                        globalMessageList.addErrorMessage({message: response.message});
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        self.samsungPayClient.notify({status: 'ERRED', provider: 'Moyasar'});
                        return;
                    }
                    // Payment success
                    self.samsungPayClient.notify({status: 'CHARGED', provider: 'Moyasar'});
                    self.redirectSuccess(response.redirect_url);
                },
                error: function (xhr) {
                    globalMessageList.addErrorMessage({message: xhr.responseJSON ? xhr.responseJSON.message : 'Payment error'});
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                    self.samsungPayClient.notify({status: 'ERRED', provider: 'Moyasar'});
                }
            });
        },

        /**
         * Abort or fail flow
         */
        abortSamsungPay: function (msg, error) {
            fullScreenLoader.stopLoader();
            this.isPlaceOrderActionAllowed(true);

            console.error(msg, error || '');
            globalMessageList.addErrorMessage({message: msg});
        },

        /**
         * Utility function to add message
         */
        addMessage: function (message) {
            globalMessageList.addErrorMessage({
                message: message
            });
        },

        /**
         * Redirect to success page or any custom route
         */
        redirectSuccess: function (redirectUrl) {
            document.location.href = redirectUrl;
        }
    });
});
