define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'mage/url',
        'Magento_Ui/js/model/messageList',
        'Magento_Ui/js/modal/modal',
        'domReady!',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'domReady!',
        'Magento_Checkout/js/checkout-data'
    ],
    function (
        Component,
        $,
        fullScreenLoader,
        placeOrderAction,
        url,
        globalMessageList,
        modal
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Magento2/payment/moyasar_payments'
            },
            initialize: function () {
                const self = this;
                this._super();
                $(document).ajaxStop(function() {
                    self.setupValidationListeners();
                });
            },
            numericInputComputed: function (value) {
                // Remove spaces
                value = value.val().replace(/\s/g, "");

                // convert to arabic numbers to english
                value = value.replace(/[٠١٢٣٤٥٦٧٨٩]/g, function (d) {
                    return d.charCodeAt(0) - 1632;
                });
                // Replace Characters
                value = value.replace(/[^0-9]/g, '');
                return value;
            },
            setupValidationListeners: function () {
                const self = this;
                $('#moyasar-card-number').on('input', function () {
                    self.validateCardNumber();
                    self.validateForm(true);
                });

                $('#moyasar-cardholder-name').on('input', function () {
                    self.validateCardholderName();
                    self.validateForm(true);
                });

                $('#moyasar-expiration-date').on('input', function () {
                    self.validateExpirationDate();
                    self.validateForm(true);
                });

                $('#moyasar-cvc').on('input', function () {
                    self.validateCvc();
                    self.validateForm(true);
                });
            },
            getCode: function () {
                return 'moyasar_payments';
            },
            isActive: function () {
                return true;
            },
            getData: function () {
                return {
                    'method': this.getCode()
                };
            },
            showCardTypeLogo: function (detected) {
                let logos = $("[class*='moyasar-card-type-logo']");
                logos.each(function () {
                    if (detected !== '')
                        $(this).hasClass('moyasar-card-type-logo-' + detected.toLowerCase()) ? $(this).fadeIn(500) : $(this).hide();
                    if (detected === '')
                        $(this).show();
                });
            },
            /**
             * Validate the Card Number input field.
             */
            detectCardType: function (input) {
                let madaStarts = ["22337902", "22337986", "22402030", "242030", "403024", "406136", "406996", "40719700", "40739500", "407520", "409201", "410621", "410685", "410834", "412565", "417633", "419593", "420132", "421141", "422817", "422818", "422819", "428331", "428671", "428672", "428673", "431361", "432328", "434107", "439954", "440533", "440647", "440795", "442463", "445564", "446393", "446404", "446672", "45488707", "455036", "455708", "457865", "457997", "458456", "462220", "468540", "468541", "468542", "468543", "474491", "483010", "483011", "483012", "484783", "486094", "486095", "486096", "489318", "489319", "504300", "513213", "515079", "516138", "520058", "521076", "52166100", "524130", "524514", "524940", "529415", "529741", "530060", "531196", "535825", "535989", "536023", "537767", "53973776", "543085", "543357", "549760", "554180", "555610", "558563", "588845", "588848", "588850", "589206", "604906", "605141", "636120", "968201", "968202", "968203", "968204", "968205", "968206", "968207", "968208", "968209", "968211"];
                let detected = '';
                // Check for different card types
                if (/^4/.test(input)) {
                    detected = 'visa';
                }
                if (/^5[1-5]/.test(input) || /^2[2-7]/.test(input)) {
                    detected = 'mc';
                }
                if (/^3[47]/.test(input)) {
                    detected = 'amex';
                }
                if (madaStarts.includes(input)) {
                    // if starts with following
                    detected = 'mada';
                }
                this.showCardTypeLogo(detected);
                return detected !== '';
            },
            validateCardNumber: function(ignoreEmpty = false) {
                const errorMessage = $('#moyasar-card-number-error-message');
                const input = $('#moyasar-card-number');
                let value = this.numericInputComputed(input);

                if (value === '') {
                    this.detectCardType(value);
                    errorMessage.text(ignoreEmpty ? '' : window.checkoutConfig.moyasar_payments.messages.creditcard['card_required']);
                    return false;
                }

                if (!this.detectCardType(value)) {
                    errorMessage.text(window.checkoutConfig.moyasar_payments.messages.creditcard['card_not_supported']);
                    return false;
                }
                if (value.length > 16) {
                    value = value.slice(0, -1);
                }

                // Add space every 4 digits except last
                value = value.replace(/(\d{4})/g, '$1 ').trim();
                input.val(value);



                errorMessage.text('');
                return true;
            },

            /**
             * Validate the Cardholder Name input field.
             */
            validateCardholderName: function(ignoreEmpty = false) {
                const errorMessage = $('#moyasar-cardholder-name-error-message');
                const value = $('#moyasar-cardholder-name').val();

                if (value === '') {
                    errorMessage.text(ignoreEmpty ? '' : window.checkoutConfig.moyasar_payments.messages.creditcard['cardholder_required']);
                    return false;
                }

                // Name must have first and last name
                if (!ignoreEmpty && value.split(' ').length < 2) {
                    errorMessage.text(window.checkoutConfig.moyasar_payments.messages.creditcard['cardholder_full_name']);
                    return false;
                }

                errorMessage.text('');
                return true;
            },

            /**
             * Validate the Expiration Date input field.
             */
            validateExpirationDate: function(ignoreEmpty = false) {
                const errorMessage = $('#moyasar-expiration-date-error-message');
                const input = $('#moyasar-expiration-date');
                let value = this.numericInputComputed(input);
                // If value more than 4 digits remove the last one
                if (value.length > 4) {
                    value = value.slice(0, -1);
                    input.val(value);
                }
                // check if first two digits are more than 12 or remove them
                if (value.length > 0) {
                    if ( value[0] !== '0' && value[0] !== '1') {
                        value = value.slice(0, -1);
                        input.val(value);
                        return false;
                    }
                }
                // After month is entered, add slash
                if (value.length > 2) {
                   let firstTwo = value.slice(0, 2);
                   let rest = value.slice(2);
                     value = firstTwo + ' / ' + rest;
                }

                input.val(value);

                if (value === '') {
                    errorMessage.text(ignoreEmpty ? '' : window.checkoutConfig.moyasar_payments.messages.creditcard['expiry_required']);
                    return false;
                }

                errorMessage.text('');
                return true;
            },

            /**
             * Validate the moyasar-cvc/CVV input field.
             */
            validateCvc: function(ignoreEmpty = false) {
                const errorMessage = $('#moyasar-cvc-error-message');
                const input = $('#moyasar-cvc');
                let value = this.numericInputComputed(input);

                // Max length is 4
                if (value.length > 4) {
                    value = value.slice(0, -1);
                    input.val(value);
                }

                if (value === '') {
                    errorMessage.text(ignoreEmpty ? '' : window.checkoutConfig.moyasar_payments.messages.creditcard['cvv_required']);
                    return false;
                }

                errorMessage.text('');
                return true;
            },

            /**
             * Perform overall form validation.
             */
            validateForm: function(ignoreEmpty = false) {
                const button = $('#moyasar-form-button');
                button.prop('disabled', true);
                let isValid = true;

                if (!this.validateCardNumber(ignoreEmpty)) {
                    isValid = false;
                }

                if (!this.validateCardholderName(ignoreEmpty)) {
                    isValid = false;
                }

                if (!this.validateExpirationDate(ignoreEmpty)) {
                    isValid = false;
                }

                if (!this.validateCvc(ignoreEmpty)) {
                    isValid = false;
                }
                button.prop('disabled', !isValid);
                return isValid;
            },

            /**
             * Add a validation error message to the global message list.
             * @param {string} message - The error message to display.
             */
            addMessage: function(message) {
                globalMessageList.addErrorMessage({
                    message: message
                });
            },

            /**
             * Open  IFrame
             */
            openIframe: function (link, callback) {
                const iframe = $('#3d-secure-iframe');
                const modalPopup = $('#3d-secure-popup');
                const popupOptions = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    buttons: []
                };

                // Initialize the modal
                iframe.attr('src', link);
                modal(popupOptions, modalPopup);

                modalPopup.modal('openModal');
                fullScreenLoader.stopLoader();

                // Watch iframe url if changed
                const interval = setInterval(function () {
                    try{
                        var location = iframe.get(0).contentWindow?.location;
                        var href = location?.href;
                        if (href && href.includes('payment')) {
                            clearInterval(interval);
                            modalPopup.modal('closeModal');
                            callback();
                        }
                    }catch (e){

                    }
                }, 50)
                // Watch popup close
                modalPopup.on('modalclosed', function () {
                    clearInterval(interval);
                    callback();
                });
            },
            /**
             * Start the payment process.
             */
            startToken: function () {
                const self = this;
                const cardNumber = $('#moyasar-card-number').val().replace(/\s/g, "");
                const cardholderName = $('#moyasar-cardholder-name').val();
                const [month, year] = $('#moyasar-expiration-date').val().split('/');
                const cvc = $('#moyasar-cvc').val();
                const data = {
                    callback_url: window.location.href + "/payment",
                    publishable_api_key: window.checkoutConfig.moyasar_payments.api_key,
                    name: cardholderName,
                    number: cardNumber,
                    month: month,
                    year: year,
                    cvc: cvc,
                    save_only: true,
                };

                $.ajax({
                    url: window.checkoutConfig.moyasar_payments.base_url + "/v1/tokens",
                    type: "POST",
                    data: data,
                    success: function (response) {
                        self.startPayment(response.id);
                    },
                    error: function () {
                        document.location.href = url.build('moyasar/payment/failed');
                    }
                });
            },
            startPayment: function(token) {
                const self = this;
                const data = {
                    token: token,
                    method: 'creditcard',
                };
                $.ajax({
                    url: url.build('moyasar/payment/initiate'),
                    type: "POST",
                    data: data,
                    success: function (response) {
                        // Check if 3D Secure is required
                        if (response['required_3ds']) {
                            self.openIframe(response['3d_url'], function () {
                                fullScreenLoader.startLoader();
                                self.redirectSuccess(response['redirect_url']);
                            });
                        } else {
                            self.redirectSuccess(response['redirect_url']);
                        }
                    },
                    error: function () {
                        document.location.href = url.build('moyasar/payment/failed');
                    }
                });
            },
            redirectSuccess: function(url) {
                document.location.href = url;
            },
            /**
             * Place the order when the "Place Order" button is clicked.
             */
            placeOrder: function () {
                if (!this.validateForm()) {
                    return;
                }
                const self = this;

                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                $.when(placeOrderAction(this.getData(), this.messageContainer))
                    .done(function () {
                        self.startToken();

                    })
                    .fail(function (response) {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        globalMessageList.addErrorMessage({message: response.message});
                    });
            },
        });
    }
);
