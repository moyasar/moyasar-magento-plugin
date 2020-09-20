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

class MoyasarHelper extends AbstractHelper
{
    protected $orderManagement;
    protected $_objectManager;
    protected $_curl;
    protected $storeManager;
    protected $directoryList;
    private $currencyHelper;

    /**
     * MoyasarHelper constructor.
     * @param Context $context
     * @param OrderManagementInterface $orderManagement
     * @param ObjectManagerInterface $objectManager
     * @param Curl $curl
     * @param StoreManager $storeManager
     * @param DirectoryList $directoryList
     * @param CurrencyHelper $currencyHelper
     */
    public function __construct(
        Context $context,
        OrderManagementInterface $orderManagement,
        ObjectManagerInterface $objectManager,
        Curl $curl,
        StoreManager $storeManager,
        DirectoryList $directoryList,
        CurrencyHelper $currencyHelper
    ) {
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->_curl = $curl;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;

        parent::__construct($context);
        $this->currencyHelper = $currencyHelper;
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
     * Save last order and change status to processing
     *
     * @param $order Order
     * @param $comment string
     * @return bool
     */
    public function processOrder($order, $comment)
    {
        if (!$order || !$order->getId()) {
            return false;
        }

        if ($order->getState() == Order::STATE_PROCESSING) {
            return false;
        }

        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);

        $notified = $this->sendOrderEmail($order);
        $order->setEmailSent((int) ($notified && true));
        $order->addStatusToHistory(Order::STATE_PROCESSING, $comment, $notified);
        $this->saveOrder($order);

        return true;
    }

    /**
     * Cancel last placed order with specified comment message
     *
     * @param string $comment
     * @param Order $order to be cancelled
     * @return bool True if order cancelled, false otherwise
     * @throws LocalizedException
     */
    public function cancelCurrentOrder($order, $comment)
    {
        if (!$order || !$order->getId()) {
            return false;
        }

        if ($order->getState() == Order::STATE_CANCELED) {
            return false;
        }

        $order->registerCancellation($comment);
        $this->saveOrder($order);

        return true;
    }

    public function orderCurrency($order)
    {
        return $order_currency = mb_strtoupper($order->getBaseCurrencyCode());
    }

    public function orderAmount($order)
    {
        return $order->getGrandTotal();
    }

    /**
     * @param $order Order
     * @return int|null
     */
    public function orderAmountInSmallestCurrencyUnit($order)
    {
        return $this->amountSmallUnit($this->orderAmount($order), $this->orderCurrency($order));
    }

    public function amountSmallUnit($amount, $currency)
    {
        return (int) $amount * (10 ** $this->currencyHelper->fractionDigits($currency));
    }

    /**
     * @param $order Order
     * @param $moyasarPaymentId
     * @return string
     */
    public function verifyAndProcess($order, $moyasarPaymentId)
    {
        if (!$order) {
            return 'failed';
        }

        if (!$moyasarPaymentId) {
            return 'failed';
        }

        if (!$order->getId()) {
            return 'failed';
        }

        $currency = $this->orderCurrency($order);
        $amount = $this->orderAmountInSmallestCurrencyUnit($order);

        if ($order->getState() != Order::STATE_PAYMENT_REVIEW) {
            $order->setStatus(Order::STATE_PAYMENT_REVIEW);
            $order->setState(Order::STATE_PAYMENT_REVIEW);
            $order->addStatusToHistory(Order::STATE_PAYMENT_REVIEW, 'Reviewing payment ID: ' . $moyasarPaymentId);
            $this->saveOrder($order);
        }

        try {
            $response = $this->fetchMoyasarPayment($moyasarPaymentId);

            $result = 'paid';

            if (!isset($response['id'])) {
                $this->_logger->warning("Moyasar payment with ID $moyasarPaymentId was not found", $response);
                $order->addCommentToStatusHistory("Payment Review Failed: payment with ID $moyasarPaymentId was not found");
                return $result = 'failed';
            }

            if (!isset($response['status']) || !isset($response['amount']) || !isset($response['currency'])) {
                $this->_logger->warning("Malformed payment response", $response);
                $order->addCommentToStatusHistory("Payment Review Failed: cannot read amount nor currency");
                return $result = 'failed';
            }

            $status = mb_strtolower($response['status']);

            if ($status == 'initiated') {
                $order->setStatus(Order::STATE_PENDING_PAYMENT);
                $order->setState(Order::STATE_PENDING_PAYMENT);
                $order->addStatusToHistory(Order::STATE_PENDING_PAYMENT, "Moyasar payment with ID $moyasarPaymentId is still pending");
                $this->saveOrder($order);
                return $result = 'pending';
            }

            if ($status != 'paid') {
                $order->addStatusToHistory(Order::STATE_CANCELED, "Moyasar payment with ID $moyasarPaymentId has status $status, order will be canceled");
                $this->cancelCurrentOrder($order, "Order canceled, payment with ID $moyasarPaymentId has status $status");
                return $result = 'failed';
            }

            if ($response['amount'] != $amount) {
                $order->addStatusToHistory(Order::STATUS_FRAUD, 'Payment Review Failed: ***possible tampering** | Actual amount paid: ' . $response['amount_format']);
                $this->cancelCurrentOrder($order, "Order canceled, payment with ID $moyasarPaymentId may be fraudulent");
                $result = 'failed';
            }

            if (mb_strtoupper($response['currency']) != $currency) {
                $order->addStatusToHistory(Order::STATUS_FRAUD, 'Payment Review Failed: ***possible tampering** | Payment currency: ' . $response['currency'] . ', order currency: ' . $currency);
                $this->cancelCurrentOrder($order, "Order canceled, payment with ID $moyasarPaymentId may be fraudulent");
                $result = 'failed';
            }

            if ($result != 'paid') {
                return $result;
            }

            $this->processOrder($order, "Payment is successful, ID: $moyasarPaymentId");

            return $result;
        } catch (Exception $e) {
            $this->_logger->critical('Error: ', ['exception' => $e]);
            return 'failed';
        }
    }

    public function moyasarPublishableApiKey()
    {
        return $this->scopeConfig->getValue('payment/moyasar_api_conf/publishable_api_key', ScopeInterface::SCOPE_STORE);
    }

    public function moyasarSecretApiKey()
    {
        return $this->scopeConfig->getValue('payment/moyasar_api_conf/secret_api_key', ScopeInterface::SCOPE_STORE);
    }

    public function buildMoyasarUrl($path)
    {
        $isStaging = true;
        $base = 'https://api.moyasar.com/v1/';

        if ($isStaging) {
            $base = 'https://apimig.moyasar.com/v1/';
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    public function fetchMoyasarPayment($paymentId)
    {
        $secretApiKey = $this->moyasarSecretApiKey();

        $this->_curl->setCredentials($secretApiKey, '');
        $this->_curl->get($this->buildMoyasarUrl("payments/$paymentId"));

        return @json_decode($this->_curl->getBody(), true);
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
        $moyasarUploadDir = 'moyasar/applepay/certificates';
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

        if ($this->_curl->getStatus() > 299) {
            $this->_logger->warning('Error while trying to validate merchant ' . $this->_curl->getStatus(), [
                'response' => @json_decode($this->_curl->getBody(), true)
            ]);
            return null;
        }

        return @json_decode($this->_curl->getBody());
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

        $this->_curl->setCredentials($this->moyasarSecretApiKey(), '');
        $this->_curl->addHeader('Content-Type', 'application/json');

        try {
            $this->_curl->post($this->buildMoyasarUrl('payments'), json_encode($data));
        } catch (Exception $e) {
            $this->_logger->warning('Error while trying to authorize Apple Pay payment', ['error' => $e]);
            return null;
        }

        if ($this->_curl->getStatus() > 299) {
            $this->_logger->warning('Error while trying to authorize Apple Pay payment, didn\'t get 201 from Moyasar, instead got ' . $this->_curl->getStatus(), [
                'response' => @json_decode($this->_curl->getBody(), true)
            ]);

            return null;
        }

        return @json_decode($this->_curl->getBody(), true);
    }
}
