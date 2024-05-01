<?php

namespace Moyasar\Magento2\Controller\Confirm;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Moyasar\Magento2\Helper\Http\Exceptions\ConnectionException;
use Moyasar\Magento2\Helper\Http\Exceptions\HttpException;
use Moyasar\Magento2\Helper\MoyasarHelper;
use Moyasar\Magento2\Helper\PaymentHelper;
use Psr\Log\LoggerInterface;

class Stcpay implements ActionInterface
{
    use PaymentHelper;

    protected $context;
    protected $checkoutSession;
    protected $moyasarHelper;
    protected $urlBuilder;
    protected $http;
    protected $messageManager;
    protected $logger;

    /**
     * @var string
     * STC Pay Tokens
     */
    private $otpToken;
    private $otpId;
    private $otp;

    public function __construct(
        Context          $context,
        Session          $checkoutSession,
        MoyasarHelper    $helper,
        UrlInterface     $urlBuilder,
        ManagerInterface $messageManager,
        LoggerInterface  $logger
    )
    {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->moyasarHelper = $helper;
        $this->urlBuilder = $urlBuilder;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        $order = $this->lastOrder();
        if (!$order) {
            $this->logger->warning('Moyasar validate payment accessed without active order.');
            return $this->redirectToCart();
        }
        $this->setUpPaymentData($order);

        $isValid = $this->validateRequest();
        if (!$isValid){
            $this->logger->warning('Moyasar validate payment accessed with missing arguments');
            return $this->redirectToCart();
        }

        if ($order->getState() == Order::STATE_PROCESSING){
            return $this->redirectToSuccess();
        };

        $this->logger->info("(STCPay Controller) Validating:  [{$this->paymentId}], Method: [{$this->method}]");


        try {
            $payment = $this->fetchSTCPayment($order);
            $this->logger->info("Payment ID: [{$this->paymentId}], Status:  [{$payment['source']['message']}]");

            if ($payment['status'] != 'paid') {
                $this->processPaymentFail($payment, $order);
                return $this->redirectToCart();
            }

            $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
            if (count($errors) > 0) {
                $this->processUnMatchingInfoFail($payment, $order, $errors);
                return $this->redirectToCart();
            }

            $this->moyasarHelper->processSuccessfulOrder($order, $payment);
            $this->logger->info("Payment [{$this->paymentId}] is successful, redirecting user to checkout/onepage/success: ");

            return $this->redirectToSuccess();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->redirectToCart();

        } catch (HttpException|ConnectionException $e) {
            $this->handleHttpException($e, $order);
            return $this->redirectToCart();
        }
    }


    /**
     * @description Validate STC Params
     * @return bool
     */
    private function validateRequest()
    {
        if (!isset($_GET['otp_token']) || !isset($_GET['otp']) || !isset($_GET['otp_id'])){
            return false;
        }
        $this->otpToken = $_GET['otp_token'];
        $this->otpId = $_GET['otp_id'];
        $this->otp = $_GET['otp'];
        return true;
    }

    /**
     * @description Submit STC Pay OTP
     * @return array
     */
    private function fetchSTCPayment($order)
    {
        return $this->http()
            ->set_headers(['order_id' => $order->getId()])
            ->get($this->moyasarHelper->apiBaseUrl("/v1/stc_pays/{$this->otpId}/proceed"), [
                'otp_token' => $this->otpToken,
                'otp_value' => $this->otp
            ])
            ->json();
    }

}
