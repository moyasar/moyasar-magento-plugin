define(
    [
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
    ],
    function (
        Component,
        ko,
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
            isVisible: ko.observable(false),
            defaults: {
                template: 'Moyasar_Magento2/payment/moyasar_payments_apple_pay'
            },
            initialize: function () {
                this._super();
                this.checkApplePaySupport();
            },
            checkApplePaySupport: function () {
                if (window.ApplePaySession && ApplePaySession.canMakePayments()) {
                    this.isVisible(true);
                }
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
                    supportedCountries: ['SA'],
                    merchantCapabilities: ['supports3DS', 'supportsDebit', 'supportsCredit'],
                    total: {
                        label: window.checkoutConfig.moyasar_payments.apple_store_name,
                        amount: `${data.base_grand_total}`
                    },
                }
                // Starting the Apple Pay Session
                const session = new window.ApplePaySession(3, applePayPaymentRequest);


                session.oncancel = event => {
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                    // document.location.href = url.build('moyasar/payment/failed');
                }
                session.abort = event => {
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                    // document.location.href = url.build('moyasar/payment/failed');
                }

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
                        error: function (error) {
                            globalMessageList.addErrorMessage({message: error.responseJSON['message']});
                            session.abort();
                        }
                    });
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
                                    if ( response['status'] === 'failed'){
                                        session.completePayment({
                                            status: ApplePaySession.STATUS_FAILURE,
                                            errors: []
                                        });
                                        self.isPlaceOrderActionAllowed(true);
                                        fullScreenLoader.stopLoader();
                                        globalMessageList.addErrorMessage({message: response['message']});
                                        return;
                                    }

                                    session.completePayment({
                                        status: ApplePaySession.STATUS_SUCCESS
                                    });
                                    self.redirectSuccess(response['redirect_url']);
                                },
                                error: function (error) {
                                    session.completePayment({
                                        status: ApplePaySession.STATUS_FAILURE,
                                        errors: []
                                    });
                                    self.isPlaceOrderActionAllowed(true);
                                    fullScreenLoader.stopLoader();
                                    globalMessageList.addErrorMessage({message: error.responseJSON['message']});
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
