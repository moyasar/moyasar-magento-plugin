define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'mage/url',
        'Magento_Ui/js/model/messageList'
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
                template: 'Moyasar_Mysr/payment/moyasar_payments'
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
            placeOrder: function () {
                var self = this;

                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                $.when(placeOrderAction(this.getData(), this.messageContainer))
                    .done(function (orderId) {
                        document.location.href = url.build('moyasar_mysr/payment/index') + '?order_id=' + orderId;
                    })
                    .fail(function (response) {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        globalMessageList.addErrorMessage({ message: response.message });
                    });
            }
        });
    }
);
