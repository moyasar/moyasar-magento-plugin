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
        mage
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_apple_pay'
            },
            getCode: function () {
                return 'moyasar_apple_pay';
            },
            isActive: function () {
                return true;
            },
            getValidationUrl: function () {
                return url.build('moyasar_mysr/applepay/validate');
            },
            getAuthorizationUrl: function () {
                return url.build('moyasar_mysr/applepay/authorize');
            },
            getAmount: function () {
                var totals = quote.getTotals()();

                if (totals) {
                    return totals.base_grand_total;
                }

                return quote.base_grand_total;
            },
            getFormattedAmount: function (amount) {
                var currency = this.getCurrency(),
                    i, n, x;
                var precision = window.checkoutConfig.moyasar_credit_card.currencies_fractions[currency]
                if (!precision) {
                    precision = window.checkoutConfig.moyasar_credit_card.currencies_fractions['DEFAULT'];
                }

                i = parseInt(
                    amount = Number(Math.round(Math.abs(+amount || 0) + 'e+' + precision) + ('e-' + precision)),
                    10
                ) + '';
                n = Number(Math.round(Math.abs(amount - i) + 'e+' + precision) + ('e-' + precision));
                x = n.toFixed(precision).replace(/-/, 0).slice(2);
                return i + '.' + x;
            },
            getCurrency: function () {
                var totals = quote.getTotals()();

                if (totals) {
                    return totals.base_currency_code;
                }

                return quote.base_currency_code;
            },
            getCountry: function () {
                return window.checkoutConfig.moyasar_apple_pay.country;
            },
            getStoreName: function () {
                return window.checkoutConfig.moyasar_apple_pay.store_name;
            },
            redirectAfterPlaceOrder: false,
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                return true;
            },
            initiateApplePay: function () {
                if (this.applePayManager) {
                    return true;
                }

                var self = this;

                this.applePayManager = new ApplePayManager('.apple-pay-button-area');
                this.applePayManager.initiate({
                    version: 6,
                    amount: this.getFormattedAmount(this.getAmount()),
                    currency: this.getCurrency(),
                    country: this.getCountry(),
                    label: this.getStoreName(),
                    validateMerchantEndpoint: this.getValidationUrl(),
                    authorizePaymentEndpoint: this.getAuthorizationUrl(),
                    onPaymentSuccess: event => {
                        var paymentId = event.payment_id;
                        var status = event.status;
                        var redirect = event.redirect_url;

                        this.isPlaceOrderActionAllowed(false);

                        self.placeMagentoOrder(paymentId)
                            .done(function () {
                                window.location.href = redirect + '?status=' + status + '&id=' + paymentId;
                            })
                            .fail(function () {
                                self.isPlaceOrderActionAllowed(true);
                                globalMessageList.addErrorMessage({
                                    message: mage('Error! Could not place order.')
                                });
                            });
                    },
                    translations: {
                        not_configured: mage('Apple Pay is not properly configured'),
                        not_supported: mage('Apple Pay is not supported on your browser')
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
        });
    }
);
