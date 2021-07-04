/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'mage/url',
        'mage/translate',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/quote'
    ],
    function (
        Component,
        quote,
        $,
        fullScreenLoader,
        placeOrderAction,
        url,
        mage,
        globalMessageList,
        quoteModel
    ) {
        'use strict';

        return Component.extend({
            // Instance Fields
            applePaySession: null,
            isOrderPlaced: false,
            placedOrderId: null,
            applePayToken: null,
            stage: 'idle',
            payment: null,

            defaults: {
                template: 'Moyasar_Mysr/payment/moyasar_apple_pay'
            },
            getCode: function () {
                return 'moyasar_apple_pay';
            },
            isActive: function () {
                return true;
            },
            getCustomerEmail: function () {
                return quoteModel.guestEmail ? quoteModel.guestEmail : window.checkoutConfig.customerData.email;
            },
            getValidationUrl: function () {
                return url.build('moyasar_mysr/applepay/validate');
            },
            getRedirectUrl: function() {
                return url.build('moyasar_mysr/redirect/response');
            },
            getCancelOrderUrl: function () {
                return url.build('moyasar_mysr/order/cancel');
            },
            getApiKey: function () {
                return window.checkoutConfig.moyasar_apple_pay.api_key;
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
            getAmountSmallUnit: function (amount) {
                var currency = this.getCurrency();
                var fractionSize = window.checkoutConfig.moyasar_credit_card.currencies_fractions[currency];

                if (!fractionSize) {
                    fractionSize = window.checkoutConfig.moyasar_credit_card.currencies_fractions['DEFAULT'];
                }

                var total = amount ? amount : this.getAmount();

                return total * (10 ** fractionSize);
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
            isApplePayAvailable: function () {
                return window.ApplePaySession && window.ApplePaySession.canMakePayments();
            },
            buildApplePayRequest: function () {
                return {
                    countryCode: this.getCountry(),
                    currencyCode: this.getCurrency(),
                    supportedNetworks: ['visa', 'masterCard', 'mada'],
                    merchantCapabilities: ['supports3DS', 'supportsCredit', 'supportsDebit'],
                    total: {
                        label: this.getStoreName(),
                        amount: this.getAmount()
                    },
                };
            },
            redirectAfterPlaceOrder: false,
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }

                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                if (! this.isApplePayAvailable()) {
                    fullScreenLoader.stopLoader();
                    globalMessageList.addErrorMessage({ message: mage('Apple Pay is not available on your system.') });
                    return;
                }

                var request = this.buildApplePayRequest();
                this.applePaySession = new ApplePaySession(6, request);

                this.isOrderPlaced = false;
                this.placedOrderId = null;
                this.applePayToken = null;
                this.stage = 'idle';
                this.payment = null;

                // Hook Session Events
                this.applePaySession.onvalidatemerchant = this.onValidateMerchant.bind(this);
                this.applePaySession.onpaymentauthorized = this.onAuthPayment.bind(this);
                this.applePaySession.oncancel = this.onApplePayCanceled.bind(this);
                this.applePaySession.begin();
                this.stage = 'session_begin';

                return true;
            },
            onApplePayCanceled: function (event) {
                if (! this.isOrderPlaced) {
                    fullScreenLoader.stopLoader();
                    globalMessageList.addErrorMessage({ message: mage('Apple Pay session was canceled.') });
                    return;
                }

                switch (this.stage) {
                    case 'placing_order':
                    case 'authorizing_payment':
                    case 'after_authorizing_payment':
                    case 'placing_order_failed':
                    case 'authorizing_payment_failed':
                    case 'authorizing_payment_paid':
                        // Do not do anything, just wait for code to finish
                        return;
                    default:
                        this.redirectToCancelOrder(mage('Apple Pay session was canceled, please try again.'));
                }
            },
            onValidateMerchant: function (event) {
                this.stage = 'merchant_validation';

                var request = $.ajax({
                    url: this.getValidationUrl(),
                    type: 'POST',
                    dataType: 'json',
                    data: JSON.stringify({
                        'validation_url': event.validationURL
                    })
                });

                request
                    .done(this.merchantValidationReturned.bind(this))
                    .fail(this.merchantValidationFailed.bind(this));
            },
            merchantValidationReturned: function (data, textStatus, jqXHR) {
                this.stage = 'waiting_payment_data';
                
                try {
                    this.applePaySession.completeMerchantValidation(data);
                } catch (errorThrown) {
                    this.merchantValidationFailed(jqXHR, textStatus, errorThrown);
                }
            },
            merchantValidationFailed: function (jqXHR, textStatus, errorThrown) {
                this.stage = 'merchant_validation_failed';
                this.applePaySession.completeMerchantValidation({});
                fullScreenLoader.stopLoader();
                globalMessageList.addErrorMessage({ message: mage('Merchant validation failed, please try again later.') });
                console.error('Apple Pay merchant validation failed. Status: ' + textStatus);
                console.error(errorThrown);
            },
            onAuthPayment: function (event) {
                this.stage = 'placing_order';

                this.applePayToken = event.payment.token.paymentData;

                this.placeMagentoOrder()
                    .done(this.afterOrderPlaced.bind(this))
                    .fail(this.orderPlacingFailed.bind(this));
            },
            afterOrderPlaced: function (orderId) {
                this.stage = 'authorizing_payment';

                this.isOrderPlaced = true;
                this.placedOrderId = orderId;

                var request = $.ajax({
                    url: 'https://apimig.moyasar.com/v1/payments',
                    type: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        'publishable_api_key': this.getApiKey(),
                        'amount': this.getAmountSmallUnit(),
                        'description': 'Order for ' + this.getCustomerEmail() + '. Order ID: ' + orderId,
                        'currency': this.getCurrency(),
                        'metadata': {
                            'order_id': orderId
                        },
                        'source': {
                            'type': 'applepay',
                            'token': JSON.stringify(this.applePayToken)
                        }
                    }),
                });

                request
                    .done(this.onMoyasarResponse.bind(this))
                    .fail(this.paymentAuthFailed.bind(this));
            },
            orderPlacingFailed: function (payment) {
                this.stage = 'placing_order_failed';
                this.applePaySession.abort();
                fullScreenLoader.stopLoader();
                globalMessageList.addErrorMessage({ message: mage('Failed placing order, please try again.') });
            },
            onMoyasarResponse: function (data, textStatus, jqXHR) {
                this.stage = 'after_authorizing_payment';

                var isOk = jqXHR.status >= 200 && jqXHR.status < 300;
                this.payment = data;

                if (isOk && data.status === 'failed') {
                    this.stage = 'authorizing_payment_failed';

                    try {
                        this.applePaySession.completePayment({ status: ApplePaySession.STATUS_FAILURE, errors: [data.source.message] });
                    } catch {}

                    this.redirectToCancelOrder(mage('Payment failed: ' + data.source.message));
                } else if (! isOk) {
                    this.stage = 'authorizing_payment_failed';

                    try {
                        this.applePaySession.completePayment({ status: ApplePaySession.STATUS_FAILURE, errors: [data.message || null] });
                    } catch {}

                    this.redirectToCancelOrder(mage('Payment authorization failed, please try again.'));
                    console.error('Could not authorize Apple Pay payment: ' + data.message || null);
                    console.error('Server Response Status: ' + jqXHR.status);
                } else {
                    this.stage = 'authorizing_payment_paid';
                    // paid, authorized
                    
                    try {
                        this.applePaySession.completePayment({ status: ApplePaySession.STATUS_SUCCESS });
                    } catch {}

                    window.location.href = this.getRedirectUrl() + '?status=' + data.status + '&id=' + data.id;
                }
            },
            paymentAuthFailed: function (jqXHR, textStatus, errorThrown) {
                this.stage = 'authorizing_payment_failed';
                this.redirectToCancelOrder(mage('Payment authorization failed, please try again.'));
                console.error('Could not authorize Apple Pay payment. Status: ' + textStatus);
                console.error(errorThrown);
            },
            initiateApplePay: function () {
                if (! this.isApplePayAvailable()) {
                    this.removeApplePayButton();
                }
            },
            removeApplePayButton: function () {
                var btn = $('.apple-pay-button');
                var parent = btn.parent();
                var message = mage('Apple Pay is not available on your system.');

                btn.remove();
                parent.append('<span>' + message + '</span>');
            },
            placeMagentoOrder: function (paymentId) {
                var paymentData = this.getData();
                paymentData.additional_data = {
                    'moyasar_payment_id': paymentId
                };

                return $.when(placeOrderAction(paymentData, this.messageContainer));
            },
            redirectToCancelOrder(error) {
                fullScreenLoader.stopLoader();
                globalMessageList.addErrorMessage({ message: error });
                window.location.href = this.getCancelOrderUrl();
            }
        });
    }
);
