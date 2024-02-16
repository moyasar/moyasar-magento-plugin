define(
    [
        'Magento_Checkout/js/view/payment/default',
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
    ],
    function (
        Component,
        $,
        fullScreenLoader,
        placeOrderAction,
        url,
        globalMessageList,
        quote,
        totals,
        checkoutData,
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Magento2/payment/moyasar_payments_apple_pay'
            },
            initialize: function () {
                this._super();
            },
            getCode: function () {
                return 'moyasar_payments_apple_pay';
            },
            getApplePayCode: function () {
                return 'moyasar_payments_apple_pay';
            },
            isActive: function () {
                return true;
            },
            getData: function () {
                return {
                    'method': this.getCode()
                };
            },
            getLocale: function () {
                return window.checkoutConfig.locale;
            },
            /**
             * Start the Apple Pay session.
             */
            placeOrderApplePay() {
                const self = this;
                if (!ApplePaySession) {
                    return;
                }
                self.isPlaceOrderActionAllowed(true);
                fullScreenLoader.startLoader();

                const data = checkoutData.totals();
                const applePayPaymentRequest = {
                    countryCode: window.checkoutConfig.moyasar_payments.country,
                    currencyCode: data.base_currency_code,
                    supportedNetworks: window.checkoutConfig.moyasar_payments.supported_networks,
                    merchantCapabilities: ['supports3DS'],
                    total: {
                        label: window.checkoutConfig.moyasar_payments.store_name,
                        amount: `${data.base_grand_total}`
                    },
                }
                // Starting the Apple Pay Session
                const session = new window.ApplePaySession(3, applePayPaymentRequest);


                // Validating Merchant
                session.onvalidatemerchant = async event => {
                    const body = {
                        "validation_url": event.validationURL,
                        "display_name": applePayPaymentRequest.total.label,
                        "domain_name": window.location.hostname,
                        "publishable_api_key": window.checkoutConfig.moyasar_payments.api_key,
                    }

                    $.ajax({
                        url: window.checkoutConfig.moyasar_payments.base_url + "/v1/applepay/initiate",
                        type: "POST",
                        data: body,
                        success: function (merchantSession) {
                            session.completeMerchantValidation(merchantSession);
                        },
                        error: function () {
                            document.location.href = url.build('moyasar/payment/failed');
                        }
                    });
                }

                session.oncancel = event => {
                    document.location.href = url.build('moyasar/payment/failed');
                }
                session.abort = event => {
                    document.location.href = url.build('moyasar/payment/failed');
                }

                session.onpaymentauthorized = event => {
                    const token = event.payment.token;
                    $.when(placeOrderAction(this.getData(), this.messageContainer))
                        .done(() => {
                            $.ajax({
                                url: url.build('moyasar/payment/initiate'),
                                type: "POST",
                                data: {
                                    token: JSON.stringify(token),
                                    method: 'applepay',
                                },
                                success: function (response) {
                                    if (response['status'] !== 'paid') {
                                        session.completePayment({
                                            status: ApplePaySession.STATUS_FAILURE,
                                            errors: []
                                        });
                                        document.location.href = url.build('moyasar/payment/failed');
                                    } else {
                                        session.completePayment({
                                            status: ApplePaySession.STATUS_SUCCESS
                                        });
                                        self.redirectSuccess(response['redirect_url']);
                                    }
                                },
                                error: function () {
                                    session.completePayment({
                                        status: ApplePaySession.STATUS_FAILURE,
                                        errors: []
                                    });
                                    document.location.href = url.build('moyasar/payment/failed');
                                }
                            });
                        });

                };
                session.begin();
            },
            /**
             * Add a validation error message to the global message list.
             * @param {string} message - The error message to display.
             */
            addMessage(message) {
                globalMessageList.addErrorMessage({
                    message: message
                });
            },

            redirectSuccess(url) {
                document.location.href = url;
            },
        });
    }
);
