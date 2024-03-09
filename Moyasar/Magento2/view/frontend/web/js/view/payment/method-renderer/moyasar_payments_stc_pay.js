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
                template: 'Moyasar_Magento2/payment/moyasar_payments_stc_pay'
            },
            initialize: function () {
                this._super();
                const self = this;
                $(document).ready(function () {
                    $(document).ajaxStop(function () {
                        self.setupStcValidationListeners();
                    });
                    setTimeout(function (){
                        self.setupStcValidationListeners();
                    }, 2500);
                });
            },
            /**
             * Stc Pay Section
             */
            validateStcForm: function(step = 1) {
                let isValid = true;

                if (step === 1 && !this.validatePhoneNumber()) {
                    isValid = false;
                }

                if (step === 2 && !this.validateOtpNumber()) {
                    isValid = false;
                }

                return isValid;
            },
            setupStcValidationListeners: function () {
                const self = this;

                $('#moyasar-stc-input').on('input', function () {
                    self.validatePhoneNumber();
                });

                $('#moyasar-stc-otp').on('input', function () {
                    self.validateOtpNumber();
                });
            },
            /**
             * Validate Phone Number (STC Pay)
             */
            validatePhoneNumber: function () {
                const button = $('.moyasar-stc-pay-button');
                button.prop('disabled', true);

                const errorMessage = $('#moyasar-stc-pay-error-message');
                const input = $('#moyasar-stc-input');
                let value = this.numericInputComputed(input);

                // If value more than 10 digits remove the last one
                if (value.length > 10) {
                    value = value.slice(0, -1);
                    input.val(value);
                }
                input.val(value.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3'));

                if (value === '') {
                    errorMessage.text('');
                    return false;
                }              // Starts with 05
                if (value.length >= 2 && !value.startsWith('05')) {
                    errorMessage.text($.mage.__('Phone number must start with 05.'));
                    return false;
                }
                // Length must be 10
                if (value.length !== 10) {
                    errorMessage.text('');
                    return false;
                }

                button.prop('disabled', false);
                errorMessage.text('');
                return true;
            },
            /**
             * Validate OTP Number (STC Pay)
             */
            validateOtpNumber: function () {
                const button = $('.moyasar-stc-pay-button');
                button.prop('disabled', true);
                const errorMessage = $('#moyasar-stc-pay-error-message');
                const input = $('#moyasar-stc-otp');
                let value = this.numericInputComputed(input);


                if (value === '') {
                    errorMessage.text($.mage.__('OTP is required.'));
                    return false;
                }

                button.prop('disabled', false);
                errorMessage.text('');
                return true;
            },
            resetStcPayErrorMessage: function () {
                const errorMessage = $('#moyasar-stc-pay-error-message');
                errorMessage.text('');
            },
            stcPayShowOtpInput: function () {
                this.resetStcPayErrorMessage();
                const self = this;
                const input = $('#moyasar-stc-input');
                const button = $('.moyasar-stc-pay-button');
                const otpButton = $('#moyasar-stc-otp');
                input.hide();
                otpButton.show();
                otpButton.focus();
                button.text($.mage.__('Submit'));
                button.prop('disabled', true);

                // Replace on click event
                button.off('click');
                button.on('click', function () {
                    self.placeOrderStcPayOtp();
                });
                // Hide Payment  Form
                $('.moyasar-separator').hide();
                $('.moyasar-apple-pay-container').hide();
                $('#moyasar-payment-form').hide();

            },
            /**
             * Start the STC Pay session.
             */
            placeOrderStcPay: function () {
                if (!this.validateStcForm(1)) {
                    return;
                }
                const self = this;
                const phoneNumber = $('#moyasar-stc-input').val().replace(/\s/g, "");
                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                $.when(placeOrderAction(this.getData(), this.messageContainer))
                    .done(function () {
                        $.ajax({
                            url: url.build('moyasar/payment/initiate'),
                            type: "POST",
                            data: {
                                token: phoneNumber,
                                method: 'stcpay',
                            },
                            success: function (response) {
                                window.moyasar_stc_pay = response['stcpay'];
                                fullScreenLoader.stopLoader();
                                globalMessageList.addSuccessMessage({ message: $.mage.__('OTP has been sent.') });

                                // Show OTP Input
                                self.stcPayShowOtpInput();
                            },
                            error: function () {
                                document.location.href = url.build('moyasar/payment/failed');
                            }
                        });

                    })
                    .fail(function (response) {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        globalMessageList.addErrorMessage({message: response.message});
                    });
            },
            /**
             * Submit the OTP code. (STC Pay)
             */
            placeOrderStcPayOtp: function () {
                fullScreenLoader.startLoader();
                if (!this.validateStcForm(2)) {
                    return;
                }
                const otp = $('#moyasar-stc-otp').val().replace(/\s/g, "");
                const link = url.build('moyasar/confirm/stcpay') +
                    '?otp_id=' + window.moyasar_stc_pay['otp_id'] +
                    '&otp_token=' + window.moyasar_stc_pay['otp_token'] +
                    '&otp=' + otp;
                this.redirectSuccess(link);
            },
            getCode: function () {
                return 'moyasar_payments_stc_pay';
            },
            getSTCPayCode: function () {
                return 'moyasar_payments_stc_pay';
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
             * Add a validation error message to the global message list.
             * @param {string} message - The error message to display.
             */
            addMessage: function(message) {
                globalMessageList.addErrorMessage({
                    message: message
                });
            },
            redirectSuccess: function (url) {
                document.location.href = url;
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
        });
    }
);
