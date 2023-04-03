<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Moyasar\Mysr\Controller\ReadsJson;
use Moyasar\Mysr\Helper\Http\Exceptions\ConnectionException;
use Moyasar\Mysr\Helper\Http\Exceptions\HttpException;
use Moyasar\Mysr\Helper\Http\QuickHttp;
use Moyasar\Mysr\Helper\MoyasarHelper;

class Webhook implements HttpPostActionInterface
{
    use ReadsJson;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var MoyasarHelper
     */
    protected $moyasarHelper;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var OrderRepository
     */
    private $config;

    public function __construct(
        Context $context,
        MoyasarHelper $helper,
        OrderRepository $orderRepo,
        ScopeConfigInterface $config
    ) {
        $this->context = $context;
        $this->moyasarHelper = $helper;
        $this->url = $context->getUrl();
        $this->orderRepo = $orderRepo;
    }

    public function execute()
    {
        $payload = $this->payload();

        $sharedSecret = $this->config->get('payment/moyasar_payments/webhook_secret');
        if ($payload['secret_token'] != $sharedSecret) {
            return $this->basicResponse('Invalid shared token.', 401);
        }

        $payment = $payload['data'];
        $paymentId = $payment['id'];
        $order = $this->orderRepo->get($payment['metadata']['order_id']);

        try {
            $payment = $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId"))
                ->json();

            // paid/authorized/captured + processed
            // Order has been already been processed
            if (in_array($payment['status'], ['paid', 'authorized', 'captured']) && $order->getState() == Order::STATE_PROCESSING) {
                return $this->basicResponse('All good. Order is already processed.');
            }

            // failed/canceled
            if ($payment['status'] == 'failed' && $order->getState() == Order::STATE_CANCELED) {
                return $this->basicResponse('All good. Order is already canceled.');
            }

            if ($payment['status'] == 'failed') {
                $message = __('Payment failed');
                if ($sourceMessage = $payment['source']['message']) {
                    $message .= ': ' . $sourceMessage;
                }

                $order->registerCancellation($message);
                $order->getPayment()->setCcStatus('failed');
                $order->save();

                return $this->basicResponse('Payment failed, order was canceled.');
            }

            $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
            if (count($errors) > 0) {
                $this->processUnMatchingInfoFail($payment, $order, $errors);
                return $this->basicResponse('Processed payments with errors.');
            }

            $this->moyasarHelper->processSuccessfulOrder($order, $payment);

            return $this->basicResponse('Processed payment successfully.');
        } catch (LocalizedException $e) {
            return $this->basicResponse($e->getMessage(), 400);
        } catch (HttpException|ConnectionException $e) {
            $orderId = $order->getRealOrderId();
            $logErrorId = bin2hex(random_bytes(6));

            $this->logger->critical("[$logErrorId] Cannot verify payment (order $orderId): " . $e->getMessage());

            if ($e instanceof HttpException) {
                $this->logger->critical("[$logErrorId] server response: " . $e->response->body());
            }

            return $this->basicResponse(
                __('Could not verify your payment for order %order_id: %error. Error ID: %error_id', ['order_id' => $orderId, 'error' => $e->getMessage(), 'error_id' => $logErrorId]),
                400
            );
        }
    }

    private function payload()
    {
        return json_decode(html_entity_decode(file_get_contents('php://input')), true);
    }

    private function basicResponse($message, $status = 200)
    {
        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_JSON)
            ->setHttpResponseCode($status)
            ->setData(['message' => $message]);
    }

    private function http()
    {
        return new QuickHttp();
    }

    private function processUnMatchingInfoFail($payment, $order, $errors)
    {
        $payment_id = $payment['id'];
        array_unshift($errors, __('Un-matching payment details %payment_id.', ['payment_id' => $payment['id']]));

        $order->registerCancellation(implode("\n", $errors));
        $order->getPayment()->setCcStatus('failed');
        $order->save();

        //auto void
        if ($this->moyasarHelper->autoVoid()) {
            $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->post($this->moyasarHelper->apiBaseUrl("/v1/payments/$payment_id/void"));

            $order->addStatusHistoryComment('Order value was voided automatically.', false);
            $order->save();
        }
    }
}
