<?php

namespace Moyasar\Magento2\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManager;
use Moyasar\Magento2\Helper\Http\QuickHttp;
use Psr\Log\LoggerInterface;

class MoyasarHelper extends AbstractHelper
{
    const VERSION = '5.1.3';

    const XML_PATH_CREDIT_CARD_IS_ACTIVE = 'payment/moyasar_payments/active';
    const XML_PATH_APPLE_PAY_IS_ACTIVE = 'payment/moyasar_payments_apple_pay/active';
    const XML_PATH_STC_PAY_IS_ACTIVE = 'payment/moyasar_payments_stc_pay/active';

    const XML_PATH_SAMSUNG_PAY_IS_ACTIVE = 'payment/moyasar_payments_samsung_pay/active';



    protected $orderManagement;
    protected $_objectManager;
    protected $_curl;
    protected $storeManager;
    protected $directoryList;
    protected $currencyHelper;
    protected $invoiceService;
    protected $invoiceSender;
    protected $apiBaseUrl;
    protected $session;
    protected $orderSender;
    protected $logger;

    public function __construct(
        Context $context,
        OrderManagementInterface $orderManagement,
        ObjectManagerInterface $objectManager,
        Curl $curl,
        StoreManager $storeManager,
        DirectoryList $directoryList,
        CurrencyHelper $currencyHelper,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Session $session,
        OrderSender $orderSender,
        LoggerInterface  $logger
    ) {
        parent::__construct($context);

        $this->orderManagement = $orderManagement;
        $this->_objectManager = $objectManager;
        $this->_curl = $curl;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->session = $session;
        $this->currencyHelper = $currencyHelper;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->orderSender = $orderSender;
        $this->logger = $logger;

        $this->apiBaseUrl = "https://api.moyasar.com";
    }

    public function apiBaseUrl($path = '')
    {
        return rtrim($this->apiBaseUrl . '/' . ltrim($path, '/'), '/');
    }

    public function publishableApiKey()
    {
        return $this->scopeConfig->getValue('payment/moyasar_payments/publishable_api_key');
    }

    public function secretApiKey()
    {
        return $this->scopeConfig->getValue('payment/moyasar_payments/secret_api_key');
    }

    public function autoVoid()
    {
        return $this->scopeConfig->getValue('payment/moyasar_payments/auto_void');
    }

    public function isCronEnabled()
    {
        return $this->scopeConfig->getValue('payment/moyasar_payments/enable_cron');
    }

    public function generateInvoice()
    {
        return $this->scopeConfig->getValue('payment/moyasar_payments/generate_invoice');
    }

    public function webhookSharedSecret()
    {
        return $this->scopeConfig->getValue('payment/moyasar_payments/webhook_secret');
    }

    /**
     * @param Order $order
     * @param array $payment
     * @return array
     */
    public function checkPaymentForErrors($order, $payment): array
    {
        $errors = [];

        if (strtoupper($order->getBaseCurrencyCode()) !== strtoupper($payment['currency'])) {
            $errors[] = __('Order and payment currencies does not match, %currency.', ['currency' => strtoupper($order->getBaseCurrencyCode()) . ' : ' . strtoupper($payment['currency'])]);
        }

        $orderAmount = CurrencyHelper::amountToMinor($order->getBaseGrandTotal(), $order->getBaseCurrencyCode());
        if ($orderAmount != $payment['amount']) {
            $total = $order->getBaseGrandTotal() . ' : ' . CurrencyHelper::amountToMajor($payment['amount'], $payment['currency']);
            $errors[] = __('Order and payment amounts do not match %total.', ['total' => $total]);
        }

        return $errors;
    }

    /**
     * @param Order $order
     * @param $paymentId
     * @param $method
     * @return void
     */
    public function processInitiateOrder($order, $paymentId, $method): void
    {
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation('moyasar_payment_id', $paymentId);
        $orderPayment->setAdditionalInformation('moyasar_payment_method', $method);
        $orderPayment->save();
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->addStatusHistoryComment("Payment initiated with Moyasar. Payment ID: $paymentId, Method: $method", Order::STATE_PENDING_PAYMENT);
        $order->save();
    }

    /**
     * @param Order $order
     * @param array $payment
     * @return void
     */
    public function processSuccessfulOrder($order, $payment): void
    {
        $orderPayment = $order->getPayment();

        $generateInvoice = $this->generateInvoice() == true;
        if ($generateInvoice) {
            $invoice = $order->prepareInvoice();
            $invoice->setTransactionId($payment['id']);
            $invoice->register();
            $invoice->pay();
        }

        $orderPayment->setTransactionId($payment['id']);
        $orderPayment->addTransaction(TransactionInterface::TYPE_CAPTURE, $invoice ?? null, true);
        $orderPayment->setCcStatus('paid');

        $order->addCommentToStatusHistory('Payment is successful: ' . $payment['id'], Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);

        if ($generateInvoice) {
            $invoice->setSendEmail(true);
            $invoice->save();
        }
        $order->setSendEmail(true);
        $order->save();

        // Send Emails
        $this->sendEmail($order, $this->orderSender, 'Confirmation');
        if ($generateInvoice){
            $this->sendEmail($invoice, $this->invoiceSender, 'Invoice');
        }
    }

    private function sendEmail($object, $method, $type)
    {
        $this->logger->info("[Moyasar] [Email] [$type] payment method detected. Sending confirmation email...");
        try {
            $isSent = $method->send($object);

            if ($isSent){
                $this->logger->info("[Moyasar] [Email] [$type] Confirmation email sent successfully.");
            } else {
                $this->logger->info("[Moyasar] [Email] [$type] Confirmation email was not sent, maybe will be handled by cron job.");
            }

        } catch (\Exception $e) {
            $this->logger->error("[Moyasar] [Email] [$type] Error sending confirmation email: " . $e->getMessage());
        };
    }

    public function getOrderPayments(string $id): array
    {
        $payments = [];
        $currentPage = 1;

        do {
            $response = $this->http()
                ->basic_auth($this->secretApiKey())
                ->get(
                    $this->apiBaseUrl('/v1/payments'),
                    ['metadata[order_id]' => $id, 'page' => $currentPage]
                )->json();

            $payments = array_merge($payments, $response['payments']);
            $lastPage = $response['meta']['total_pages'];
            $currentPage++;
        } while ($currentPage <= $lastPage);

        return $payments;
    }

    public function http()
    {
        return new QuickHttp();
    }

}
