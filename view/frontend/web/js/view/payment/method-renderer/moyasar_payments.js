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
        'domReady!',
        'Magento_Checkout/js/checkout-data',
        'mage/translate'
    ],
    function (
        Component,
        $,
        fullScreenLoader,
        placeOrderAction,
        url,
        globalMessageList
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Moyasar_Magento2/payment/moyasar_payments'
            },
            initialize: function () {
                this._super();
                const self = this;
                $(document).ready(function () {
                    $(document).ajaxStop(function () {
                        self.setupValidationListeners();
                    });
                    setTimeout(function (){
                        self.setupValidationListeners();
                    }, 2500);
                    // Trigger when total price is changed
                    self.watchPrice();
                });

                // Force isPlaceOrderActionAllowed to true to avoid billing address validation
                this.isPlaceOrderActionAllowed(true);
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
            isLuhn(text) {
                let sum = 0
                let bit = 1
                let array = [0, 2, 4, 6, 8, 1, 3, 5, 7, 9]
                let length = text.length
                let value

                while(length) {
                    value = parseInt(text.charAt(--length), 10)
                    bit ^= 1
                    sum += bit ? array[value] : value
                }

                return sum % 10 === 0
            },
            validateCardNumber: function(ignoreEmpty = false) {
                const errorMessage = $('#moyasar-card-number-error-message');
                const input = $('#moyasar-card-number');
                let value = this.numericInputComputed(input);

                if (value === '') {
                    this.detectCardType(value);
                    errorMessage.text(ignoreEmpty ? '' : $.mage.__('Card Number is required.'));
                    return false;
                }

                if (!this.detectCardType(value)) {
                    errorMessage.text($.mage.__('Card Type is not supported.'));
                    return false;
                }

                if (!this.isLuhn(value) && value.length === 16) {
                    errorMessage.text($.mage.__('Invalid credit card.'));
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
                    errorMessage.text(ignoreEmpty ? '' : $.mage.__('Cardholder Name is required.'));
                    return false;
                }

                // Name must have first and last name
                if (!ignoreEmpty && value.split(' ').length < 2) {
                    errorMessage.text($.mage.__('Cardholder Name must have first name and last name.'));
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
                    errorMessage.text(ignoreEmpty ? '' : $.mage.__('Expiration Date is required.'));
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
                    errorMessage.text(ignoreEmpty ? '' : $.mage.__('CVV is required.'));
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
             * Open IFrame with Native JS Modal
             */
             openIframeCustom: function (link, callback) {
                const modal = document.getElementById('mysr-3d-secure-popup');
                const iframe = document.getElementById('mysr-3d-secure-iframe');
                let interval;
                // Show modal
                modal.style.display = 'flex';
                iframe.src = link;


                // Listen for iframe location change
                interval = setInterval(function () {
                    try {
                        const location = iframe.contentWindow.location;
                        const href = location.href;
                        if (href && href.includes('payment')) {
                            clearInterval(interval);
                            closeModal();
                            if (typeof callback === 'function') callback();
                        }
                    } catch (e) {
                        console.error('[Moysar] Error accessing iframe content:', e);
                    }
                }, 50);

                function closeModal() {
                    modal.style.display = 'none';
                    iframe.src = 'about:blank';
                    clearInterval(interval);
                    if (typeof callback === 'function') callback();
                }


                modal.onclick = function(e) {
                    if (e.target === modal) closeModal();
                };

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

                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: window.checkoutConfig.moyasar_payments.base_url + "/v1/tokens",
                        type: "POST",
                        data: data,
                        headers: {
                            'Mysr-Client': window.checkoutConfig.moyasar_payments.version,
                        },
                        success: function (response) {
                            resolve(response.id);
                        },
                        error: function (error) {
                            self.isPlaceOrderActionAllowed(true);
                            fullScreenLoader.stopLoader();
                            let json = error.responseJSON;
                            let message = json['message'];
                            if (json['errors']) {
                                for (let key in json['errors']) {
                                    message += ' ' + key + ': ' + json['errors'][key][0] + ' ';
                                }
                            }
                            globalMessageList.addErrorMessage({message: message});
                            resolve(false);
                        }
                    });
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
                        if ( response['status'] === 'failed'){
                            self.isPlaceOrderActionAllowed(true);
                            fullScreenLoader.stopLoader();
                            globalMessageList.addErrorMessage({message: response['message']});
                            return;
                        }
                        // Check if 3D Secure is required
                        if (response['required_3ds']) {

                            try {
                                 self.openIframeCustom(response['3d_url'], function () {
                                    fullScreenLoader.startLoader();
                                    self.redirectSuccess(response['redirect_url']);
                                });
                            }catch (e){
                                // Fallback to redirect if modal fails
                                console.error('Error opening 3D Secure IFrame:', e);
                                window.location.href = response['3d_url'];
                            }

                        } else {
                            self.redirectSuccess(response['redirect_url']);
                        }
                    },
                    error: function (error) {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        globalMessageList.addErrorMessage({message: error.responseJSON['message']});
                    }
                });
            },
            redirectSuccess: function (url) {
                document.location.href = url;
            },
            /**
             * Place the order when the "Place Order" button is clicked.
             */
            placeOrder: async function () {
                if (!this.validateForm()) {
                    return;
                }
                const self = this;

                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                let token = await this.startToken();
                if (!token) {
                    return;
                }


                $.when(placeOrderAction(this.getData(), this.messageContainer))
                    .done(function () {
                        self.startPayment(token);

                    })
                    .fail(function (response) {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        globalMessageList.addErrorMessage({message: response.message});
                    });

            },
            /**
             * Watch Price
             */
            watchPrice: function () {
                const self = this;
                var targetEl = document.querySelector('.grand.totals .price');
                if (!targetEl) {
                    return; // Element not found
                }

                var observer = new MutationObserver(function (mutationsList) {
                    mutationsList.forEach(function (mutation) {
                        if (mutation.type === 'childList' || mutation.type === 'characterData') {
                            try{
                                self.updateSubmitButton($(targetEl).text());
                            }catch (e) {
                                console.error(e);
                            }

                        }
                    });
                });

                observer.observe(targetEl, {
                    childList: true,
                    characterData: true,
                    subtree: true
                });
            },
            /**
             * Update Submit Button
             */
            updateSubmitButton: function (text) {
                const submitSpan = $('#moyasar-form-button');
                if (text.includes("SAR") || text.includes("ر.س")) {
                    const SAR = `
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 1124.14 1256.39"
                      fill="currentColor"
                      style="width: 1em; height: 1em; vertical-align: -0.1em;"
                    >
                      <path d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z" />
                      <path d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z" />
                    </svg>
                    `;

                    text = text.replace("SAR", SAR);
                    text = text.replace("ر.س", SAR);
                }
                submitSpan.html($.mage.__('Pay') + ' ' + text);
            }
        });
    }
);
