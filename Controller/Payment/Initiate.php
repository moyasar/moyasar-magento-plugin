<?php

namespace Moyasar\Magento2\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Moyasar\Magento2\Helper\CurrencyHelper;
use Moyasar\Magento2\Helper\Http\Exceptions\HttpException;
use Moyasar\Magento2\Helper\Http\QuickHttp;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

class Initiate implements ActionInterface
{
    protected $context;
    protected $checkoutSession;
    protected $urlBuilder;
    protected $http;
    protected $messageManager;
    protected $logger;
    private $resultJsonFactory;

    /** @var MoyasarHelper */
    private $moyasarHelper;

    /** @var Order */
    private $order;

    /**
     * @var string
     * Method of payment (stcpay, creditcard, applepay)
     */
    private $method = 'creditcard';

    /**
     * @var string
     * Token from Moyasar.js (stcpyay: phone number, applepay: paymentData)
     */
    private $token;

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        Context          $context,
        Session          $checkoutSession,
        MoyasarHelper    $helper,
        UrlInterface     $urlBuilder,
        ManagerInterface $messageManager,
        LoggerInterface  $logger,
        JsonFactory      $resultJsonFactory,
        RequestInterface $request
    )
    {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper = $helper;
        $this->urlBuilder = $urlBuilder;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
    }

    public function execute()
    {

        // Get Post Data
        $resultJson = $this->resultJsonFactory->create();
        if (!$this->request->isPost()) {
            return $resultJson->setData(['message' => 'Invalid request.'])->setHttpResponseCode(400);
        }
        // check session
        if (!$this->checkoutSession->getLastRealOrderId()) {
            $this->logger->warning('Moyasar payment accessed without active order.');
            return $resultJson->setData(['message' => 'Invalid request.'])->setHttpResponseCode(422);
        }

        $this->token = $this->request->getPostValue('token') ?? null;
        $this->method = $this->request->getPostValue('method') ?? 'creditcard';

        if (!$this->token) {
            return $resultJson->setData(['message' => 'Invalid request.'])->setHttpResponseCode(422);
        }

        $this->order = $this->lastOrder();
        $payloadFunction = $this->method . 'Payload';

        try {
            $response = $this->http()->set_headers(['order_id' => $this->order->getId()])->post($this->moyasarHelper->apiBaseUrl() . '/v1/payments', $this->$payloadFunction())->json();
        } catch (\Exception $e) {
            $this->logger->warning('Moyasar payment failed [Order ID]: ' . $this->order->getId() . ', [Error]: .' . $e->getMessage());
            $this->order->addCommentToStatusHistory('[Error]: .' . $e->getMessage());
            $this->order->save();

            // Reset Cart
            $this->checkoutSession->restoreQuote();

            if ( $e instanceof HttpException) {
                $response = $e->response;
               if ($response->isValidationError()){
                     $message = $response->getValidationMessage();
                     return $resultJson->setData(['message' => $message])->setHttpResponseCode(400);
               }
               if ($response->isAuthenticationError()){
                     $message = 'Authentication Error';
                     return $resultJson->setData(['message' => $message])->setHttpResponseCode(400);
               }
               if ($response->isCardNotSupportedError()){
                     $message = 'Card Not Supported';
                     return $resultJson->setData(['message' => $message])->setHttpResponseCode(400);
               }
            }

            return $resultJson->setData(['message' => "Payment Failed"])->setHttpResponseCode(400);
        }

        $paymentId = $response['id'];
        $this->logger->info("Moyasar payment initiated. id: $paymentId, status {$response['status']}, message: {$response['source']['message']}");

        $responseData = [
            'status' => $response['status'],
            'message' => $response['source']['message'] ?? '',
        ];

        // If Status Failed
        if ($response['status'] == 'failed') {
            $this->logger->warning('Moyasar payment failed [Order ID]: ' . $this->order->getId() . ', [Error]: .' . $responseData['message']);
            $this->order->addCommentToStatusHistory('[Error]: .' . $responseData['message']);
            $this->order->save();
            // Reset Cart
            $this->checkoutSession->restoreQuote();
        }

        if ($this->method == 'stcpay') {
            $responseData = array_merge($responseData, ['stcpay' => $this->stcpayResponseData($response['id'], $response['source']['transaction_url'])]);
        }

        if ($this->method == 'creditcard') {
            $responseData = array_merge($responseData, $this->creditcardResponseData($response));
        }

        if ($this->method == 'applepay') {
            $responseData = array_merge($responseData, $this->applepayResponseData($response));
        }

        if ($this->method == 'samsungpay') {
            $responseData = array_merge($responseData, $this->samsungpayResponseData($response));
        }

        $this->moyasarHelper->processInitiateOrder($this->order, $paymentId, $this->method);

        return $resultJson->setData($responseData);
    }


    private function lastOrder()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        // Work around real_last_order_id is lost from current session
        if (!$order->getId()) {
            $order->loadByAttribute('entity_id', $this->checkoutSession->getLastOrderId());
        }

        return $order;
    }

    private function basePayload()
    {
        $metadata = [
            'order_id' => $this->order->getId(),
            'real_order_id' => $this->order->getRealOrderId(),
        ];

        if ($address = $this->order->getShippingAddress()) {
            $metadata = array_merge($metadata, $this->mapAddress($address));
        }

        return [
            'amount' => CurrencyHelper::amountToMinor($this->order->getGrandTotal(), $this->order->getBaseCurrencyCode()), // $order->getGrandTotal() * 100
            'currency' => $this->order->getOrderCurrencyCode(),
            'description' => 'Order #' . $this->order->getRealOrderId(),
            'publishable_api_key' => $this->moyasarHelper->publishableApiKey(),
            'callback_url' => $this->context->getUrl()->getUrl() . '/payment',
            'metadata' => $metadata,
            'source' => []
        ];
    }

    public function creditcardPayload()
    {
        $basePayload = $this->basePayload();
        $basePayload['source'] = [
            'type' => 'token',
            'token' => $this->token,
            '3ds' => true,
            'manual' => false
        ];
        return $basePayload;
    }

    public function applepayPayload()
    {
        $basePayload = $this->basePayload();
        $basePayload['source'] = [
            'type' => 'applepay',
            'token' => $this->token
        ];
        return $basePayload;
    }

    private function stcpayPayload()
    {
        $basePayload = $this->basePayload();
        $basePayload['source'] = [
            'type' => 'stcpay',
            'mobile' => $this->token,
        ];
        return $basePayload;
    }

    private function samsungpayPayload()
    {
        $basePayload = $this->basePayload();
        $basePayload['source'] = [
            'type' => 'samsungpay',
            'token' => $this->token,
        ];
        return $basePayload;
    }

    private function creditcardResponseData($response)
    {
        return [
            'required_3ds' => $response['status'] == 'initiated',
            '3d_url' => $response['source']['transaction_url'] ?? '',
            'redirect_url' => $this->urlBuilder->getUrl('moyasar/payment/validate') . '?pid=' . $response['id'] . '&m=' . $this->method
        ];
    }

    private function applepayResponseData($response)
    {
        return [
            'redirect_url' => $this->urlBuilder->getUrl('moyasar/payment/validate') . '?pid=' . $response['id'] . '&m=' . $this->method
        ];
    }

        private function samsungpayResponseData($response)
    {
        return [
            'redirect_url' => $this->urlBuilder->getUrl('moyasar/payment/validate') . '?pid=' . $response['id'] . '&m=' . $this->method
        ];
    }


    private function stcpayResponseData($paymentId, $url)
    {
        $otpId = null;
        $otpToken = null;
        if ($url){
            $otpId = explode('/', parse_url($url, PHP_URL_PATH))[3];
            parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
            $otpToken = $queryParams['otp_token'] ?? null;
        }
        return [
            'otp_id' => $otpId,
            'otp_token' => $otpToken,
            'payment_id' => $paymentId,
        ];
    }

    private function mapAddress(OrderAddressInterface $address)
    {
        $keys = [
            OrderAddressInterface::FIRSTNAME,
            OrderAddressInterface::MIDDLENAME,
            OrderAddressInterface::LASTNAME,
            OrderAddressInterface::STREET,
            OrderAddressInterface::CITY,
            OrderAddressInterface::REGION,
            OrderAddressInterface::POSTCODE,
            OrderAddressInterface::EMAIL,
            OrderAddressInterface::TELEPHONE,
            OrderAddressInterface::COMPANY,
        ];

        $prefix = $address->getAddressType();

        return array_merge(...array_map(function ($key) use ($address, $prefix) {
            return [$prefix . "_" . $key => $address->getData($key)];
        }, $keys));
    }

    private function http()
    {
        return new QuickHttp();
    }

}
