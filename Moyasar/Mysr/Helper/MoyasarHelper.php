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
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

class MoyasarHelper extends AbstractHelper
{
    protected $orderManagement;
    protected $_objectManager;
    protected $_curl;
    protected $storeManager;
    protected $directoryList;
    private $currencyHelper;
    protected $invoiceService;
    protected $invoiceSender;

    /**
     * MoyasarHelper constructor.
     * @param Context $context
     * @param OrderManagementInterface $orderManagement
     * @param ObjectManagerInterface $objectManager
     * @param Curl $curl
     * @param StoreManager $storeManager
     * @param DirectoryList $directoryList
     * @param CurrencyHelper $currencyHelper
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        Context $context,
        OrderManagementInterface $orderManagement,
        ObjectManagerInterface $objectManager,
        Curl $curl,
        StoreManager $storeManager,
        DirectoryList $directoryList,
        CurrencyHelper $currencyHelper,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender
    ) {
        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->_curl = $curl;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;

        parent::__construct($context);
        $this->currencyHelper = $currencyHelper;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
    }
    public function methodEnabled()
    {
        $methods = [];
        $lookup = [
            'payment/moyasar_online_payment/crdit_card' => 'creditcard',
            'payment/moyasar_online_payment/stc_pay' => 'stcpay',
            'payment/moyasar_online_payment/apple_pay' => 'applepay'
        ];
        foreach ($lookup as $key => $method) {
            if($this->scopeConfig->getValue($key, ScopeInterface::SCOPE_STORE)) {
                $methods[] = $method;
            }
        }
        return $methods;
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
        $this->generateInvoice($order);

        return true;
    }

     /**
     * Generate invoice for the completed order
     *
     * @param $order Order
     */
    public function generateInvoice($order)
    {
        if ($this->isInvoiceGeneratingEnabled() && $order->canInvoice()) {
            $invoice =  $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->pay();

            // Send Invoice mail to customer
            $this->invoiceSender->send($invoice);

            $order = $invoice->getOrder();

            $history = $order
                ->addCommentToStatusHistory(__('Notified customer of invoice creation.'))
                ->setIsCustomerNotified(true);

            $transactionSave = $this->_objectManager
                ->create(\Magento\Framework\DB\Transaction::class)
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->addObject($history);

            $transactionSave->save();
        }
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

    public function rejectFraudPayment($order, $response)
    {
        $order->addStatusToHistory(Order::STATUS_FRAUD, 'Payment Review Failed: ***possible tampering** | Actual payment: ' . $response['amount_format']);
        $this->cancelCurrentOrder($order, "Order canceled, payment with ID ". $response['id'] . " may be fraudulent");
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
        return (int) ($amount * (10 ** $this->currencyHelper->fractionDigits($currency)));
    }

    /**
     * @param $order Order
     * @param $moyasarPaymentId
     * @return string
     */
    public function verifyAndProcess($order, $moyasarPaymentId, $returnedStatus)
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

        // ALOOOT of cleaning needed here.
        if (!$this->isAmountVerificationEnabled() && $returnedStatus == 'paid') {
            $this->processOrder($order, "Payment is successful, ID: $moyasarPaymentId");
            return 'paid';
        }

        if (!$this->isAmountVerificationEnabled() && $returnedStatus == 'failed') {
            $order->addStatusToHistory(Order::STATE_CANCELED, "Moyasar payment with ID $moyasarPaymentId has status $returnedStatus, order will be canceled");
            $this->cancelCurrentOrder($order, "Order canceled, payment with ID $moyasarPaymentId has status $returnedStatus");
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
                $this->rejectFraudPayment($order, $response);
                $result = 'failed';
            }

            if (mb_strtoupper($response['currency']) != $currency) {
                $this->rejectFraudPayment($order, $response);
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

    public function isAmountVerificationEnabled()
    {
        return $this->scopeConfig->getValue('payment/moyasar_api_conf/is_amount_verification_enabled', ScopeInterface::SCOPE_STORE);
    }

    public function isInvoiceGeneratingEnabled()
    {
        $isEnabled = $this->scopeConfig->getValue('payment/moyasar_api_conf/is_invoice_generating_enabled', ScopeInterface::SCOPE_STORE);

        return $isEnabled;
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
        $isStaging = false;
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

    protected function getCurrentStoreName()
    {
        return $this->storeManager->getStore()->getName();
    }
 
    public function getInitiativeContext()
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        if (preg_match('/^.+:\/\/([A-Za-z0-9\-\.]+)\/?.*$/', $baseUrl, $matches) !== 1) {
        }

        return $matches[1];
    }
}
