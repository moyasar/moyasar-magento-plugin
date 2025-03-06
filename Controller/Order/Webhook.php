<?php

namespace Moyasar\Magento2\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Moyasar\Magento2\Controller\ReadsJson;
use Moyasar\Magento2\Helper\Http\Exceptions\ConnectionException;
use Moyasar\Magento2\Helper\Http\Exceptions\HttpException;
use Moyasar\Magento2\Helper\Http\QuickHttp;
use Moyasar\Magento2\Helper\MoyasarCoupon;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class Webhook implements HttpPostActionInterface, CsrfAwareActionInterface
{
    use ReadsJson;

    /** @var Context */
    protected $context;

    /** @var MoyasarHelper */
    protected $moyasarHelper;

    /** @var UrlInterface */
    private $url;

    /** @var OrderRepository */
    private $orderRepo;

    /** @var LoggerInterface */
    private $logger;

    protected $moyasarCoupon;

    public function __construct(
        Context $context,
        MoyasarHelper $helper,
        OrderRepository $orderRepo,
        LoggerInterface $logger,
        MoyasarCoupon $moyasarCoupon
    ) {
        $this->context = $context;
        $this->moyasarHelper = $helper;
        $this->url = $context->getUrl();
        $this->orderRepo = $orderRepo;
        $this->logger = $logger;
        $this->moyasarCoupon = $moyasarCoupon;
    }

    public function execute()
    {
        $payload = $this->payload();
        $this->basicResponse('Invalid shared token.', 401);

        $sharedSecret = $this->moyasarHelper->webhookSharedSecret();
        if ( !isset($payload['secret_token']) || $payload['secret_token'] != $sharedSecret) {
            return $this->basicResponse('Invalid shared token.', 401);
        }

        $payment = $payload['data'];
        $paymentId = $payment['id'];
        sleep(5); // wait for the platform update the payment status to avoid duplicate processing
        $order = $this->orderRepo->get($payment['metadata']['order_id']);

        if ($order->getState() != Order::STATE_PENDING_PAYMENT) {
            $this->logger->info('[Moyasar] [Webhook] Order is not pending for payment, skipping.');
            return $this->basicResponse('Order is not pending for payment, skipping.');
        }

        if (! in_array($payment['status'], ['paid', 'authorized', 'captured'])) {
            return $this->basicResponse('Payment is not a success, it will be taken care by cron job.');
        }

        try {
            $payment = $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId"))
                ->json();

            $this->moyasarCoupon->tryApplyCouponToOrder($order, $payment);
            $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
            if (count($errors) > 0) {
                $this->processUnMatchingInfoFail($payment, $order, $errors);
                return $this->basicResponse('Processed payments with errors.');
            }
            $this->logger->info("[Moyasar] [Webhook] Payment ID: $paymentId is successful.");
            $this->moyasarHelper->processSuccessfulOrder($order, $payment);

            return $this->basicResponse('Processed payment successfully.');
        } catch (LocalizedException $e) {
            return $this->basicResponse($e->getMessage(), 400);
        } catch (HttpException|ConnectionException $e) {
            $orderId = $order->getRealOrderId();
            $logErrorId = bin2hex(random_bytes(6));

            $this->logger->critical("[Moyasar] [Webhook] [$logErrorId] Cannot verify payment (order $orderId): " . $e->getMessage());

            if ($e instanceof HttpException) {
                $this->logger->critical("[Moyasar] [Webhook] [$logErrorId] server response: " . $e->response->body());
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
        $paymentId = $payment['id'];

        array_unshift($errors, __('Un-matching payment details %payment_id.', ['payment_id' => $paymentId]));

        $order->registerCancellation(implode("\n", $errors));
        $order->getPayment()->setCcStatus('failed');
        $order->save();

        //auto void
        if ($this->moyasarHelper->autoVoid()) {
            $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->post($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId/void"));

            $order->addStatusHistoryComment('Order value was voided automatically.', false);
            $order->save();
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
