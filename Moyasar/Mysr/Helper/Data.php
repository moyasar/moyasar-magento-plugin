<?php

namespace Moyasar\Mysr\Helper;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;

class Data extends AbstractHelper
{
    protected $orderManagement;
    protected $_objectManager;
    protected $_curl;
    protected $storeManager;
    protected $directoryList;

    public function __construct(
        Context $context,
        OrderManagementInterface $orderManagement,
        ObjectManagerInterface $objectManager,
        Curl $curl,
        StoreManager $storeManager,
        DirectoryList $directoryList
    ) {
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->_curl = $curl;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;

        parent::__construct($context);
    }

    /**
     * Save last order and change status to proccessing
     *
     * @param Order $order to be saved
     * @return bool True if order saved, false otherwise
     */
    public function processOrder($order, $id)
    {
        if ($order->getId() && $order->getState() != Order::STATE_PROCESSING) {
            $order->setStatus(Order::STATE_PROCESSING);
            $order->setState(Order::STATE_PROCESSING);
            $customerNotified = $this->sendOrderEmail($order);
            $order->addStatusToHistory(Order::STATE_PROCESSING, 'Moyasar Payment Successfully completed. ID: ' . $id, $customerNotified);

            $this->saveOrder($order);
            // $invoice = $order->prepareInvoice()->register();
            return true;
        }
        return false;
    }

    /**
     * @param $order Order
     * @return bool
     */
    public function sendOrderEmail($order)
    {
        $result = true;
        try {
            if ($order->getId() && $order->getState() != $order::STATE_PROCESSING) {
                $orderCommentSender = $this->_objectManager
                    ->create('Magento\Sales\Model\Order\Email\Sender\OrderCommentSender');
                $orderCommentSender->send($order, true, '');
            } else {
                $this->orderManagement->notify($order->getEntityId());
            }
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Cancel last placed order with specified comment message
     *
     * @param string $id Comment appended to order history
     * @param Order $order to be cancelled
     * @return bool True if order cancelled, false otherwise
     * @throws LocalizedException
     */
    public function cancelCurrentOrder($order, $id)
    {
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation('Moyasar Payment Failed. ID: ' . $id);
            $this->saveOrder($order);
            return true;
        }

        return false;
    }

    /**
     * @param $order Order
     * @param $payment_id
     * @param array|null $response
     * @return bool
     */
    public function verifyAmount($order, $payment_id, $response = null)
    {
        $order_amount = $order->getGrandTotal() * 100;
        $order_currency = mb_strtoupper($order->getBaseCurrencyCode());

        if ($order->getId() && $order->getState() != Order::STATE_PAYMENT_REVIEW) {
            $order->setStatus(Order::STATE_PAYMENT_REVIEW);
            $order->setState(Order::STATE_PAYMENT_REVIEW);
            $order->addStatusToHistory(Order::STATE_PAYMENT_REVIEW, 'Reviewing payment ID: ' . $payment_id);
            $this->saveOrder($order);
        }

        try {
            if (is_null($response)) {
                $response = $this->fetchMoyasarPayment($payment_id);
            }

            if (isset($response['message'])) {
                $this->_logger->addDebug($payment_id . ' Moyasar Payment Verification Failed: ' . $response['message']);
                $order->addStatusHistoryComment('Payment Review Failed: check the transaction manualy in Moyasar Dashboard.');
            }

            if (!(isset($response['amount']) && $response['amount'] == $order_amount)) {
                $order->addStatusToHistory(Order::STATUS_FRAUD, 'Payment Review Failed: ***possible tampering** | Actual amount paid: ' . $response['amount_format']);
                $this->saveOrder($order);
                return false;
            }

            if (!(isset($response['currency']) && mb_strtoupper($response['currency']) == $order_currency)) {
                $order->addStatusToHistory(Order::STATUS_FRAUD, 'Payment Review Failed: ***possible tampering** | Payment currency: ' . $response['currency'] . ', order currency: ' . $order_currency);
                $this->saveOrder($order);
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->_logger->critical('Error: ', ['exception' => $e]);
            return false;
        }
    }

    public function getUrl($route, $params = [])
    {
        return $this->_getUrl($route, $params);
    }

    public function saveOrder(Order $order)
    {
        // Save method is deprecated in new versions of Magento
        if (! interface_exists('\Magento\Sales\Model\Spi\OrderResourceInterface')) {
            $order->save();
            return;
        }

        /** @var OrderResourceInterface $orderResource */
        $orderResource = ObjectManager::getInstance()->get(OrderResourceInterface::class);

        $orderResource->save($order);
    }

    public function fetchMoyasarPayment($paymentId)
    {
        $secretApiKey = $this->moyasarSecretApiKey();

        $this->_curl->setCredentials($secretApiKey, '');
        $this->_curl->get("https://api.moyasar.com/v1/payments/$paymentId");

        return json_decode($this->_curl->getBody(), true);
    }

    public function moyasarSecretApiKey()
    {
        return $this->scopeConfig->getValue('payment/moyasar_cc/secret_api_key', ScopeInterface::SCOPE_STORE);
    }

    public function validateApplePayMerchant($validationUrl)
    {
        if (!$validationUrl) {
            return null;
        }

        $body = [
            'merchantIdentifier' => $this->getMerchantApplePayIdentifier(),
            'displayName' => $this->getCurrentStoreName(),
            'initiative' => 'web',
            'initiativeContext' => $this->getInitiativeContext()
        ];

        $this->_curl->setOptions([
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT => $this->getMerchantCertificatePath(),
            CURLOPT_SSLKEY => $this->getMerchantCertificateKeyPath(),
            CURLOPT_SSLKEYPASSWD => $this->getMerchantCertificateKeyPassword(),
            CURLOPT_RETURNTRANSFER => true
        ]);

        try {
            $this->_curl->post($validationUrl, json_encode($body));
        } catch (Exception $e) {
            $this->_logger->warning('Could not validate merchant with Apple, error: ' . $e->getMessage());
            return null;
        }

        return json_decode($this->_curl->getBody());
    }

    public function getMerchantCertificatePath()
    {
        return $this->getFilePath('payment/moyasar_apple_pay/validate_merchant_cert');
    }

    public function getMerchantCertificateKeyPath()
    {
        return $this->getFilePath('payment/moyasar_apple_pay/validate_merchant_pk');
    }

    protected function getFilePath($key)
    {
        $varDir = $this->directoryList->getPath(DirectoryList::VAR_DIR);
        $moyasarUploadDir = 'moyasar/apple-pay/certificates';
        $path = $this->scopeConfig->getValue($key, ScopeInterface::SCOPE_STORE);

        return "$varDir/$moyasarUploadDir/$path";
    }

    public function getMerchantCertificateKeyPassword()
    {
        $password = $this->scopeConfig->getValue('payment/moyasar_apple_pay/validate_merchant_pk_password', ScopeInterface::SCOPE_STORE);

        if (!is_string($password)) {
            return '';
        }

        return $password;
    }

    public function getMerchantApplePayIdentifier()
    {
        return $this->scopeConfig->getValue('payment/moyasar_apple_pay/merchant_id', ScopeInterface::SCOPE_STORE);
    }

    protected function getCurrentStoreName()
    {
        return $this->storeManager->getStore()->getName();
    }

    protected function getInitiativeContext()
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        if (preg_match('/^.+:\/\/([A-Za-z0-9\-\.]+)\/?.*$/', $baseUrl, $matches) !== 1) {
            return $this->getMerchantApplePayIdentifier();
        }

        return $matches[1];
    }

    public function authorizeApplePayPayment($amount, $description, $currency, $paymentData)
    {
        $data = [
            'amount' => $amount,
            'description' => $description,
            'currency' => $currency,
            'source' => [
                'type' => 'applepay',
                'token' => $paymentData
            ]
        ];

        $this->_curl->setCredentials($this->moyasarApplePaySecretApiKey(), '');
        $this->_curl->addHeader('Content-Type', 'application/json');

        try {
            $this->_curl->post('https://api.moyasar.com/v1/payments', json_encode($data));
        } catch (Exception $e) {
            $this->_logger->warning('Error while trying to authorize Apple Pay payment', ['error' => $e]);
            return null;
        }

        if ($this->_curl->getStatus() != 201) {
            $this->_logger->warning('Error while trying to authorize Apple Pay payment, didn\'t get 201 from Moyasar, instead got ' . $this->_curl->getStatus(), [
                'response' => @json_decode($this->_curl->getBody(), true)
            ]);
            return null;
        }

        return json_decode($this->_curl->getBody(), true);
    }

    private function moyasarApplePaySecretApiKey()
    {
        return $this
            ->scopeConfig
            ->getValue('payment/moyasar_apple_pay/secret_api_key', ScopeInterface::SCOPE_STORE);
    }
}
