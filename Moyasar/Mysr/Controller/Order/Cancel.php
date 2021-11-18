<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\UrlInterface;
use Moyasar\Mysr\Controller\ReadsJson;
use Moyasar\Mysr\Helper\MoyasarHelper;

class Cancel implements HttpPostActionInterface
{
    use ReadsJson;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Session
     */
    protected $checkout;

    /**
     * @var MoyasarHelper
     */
    protected $moyasarHelper;

    /**
     * @var UrlInterface
     */
    private $url;

    public function __construct(Context $context, Session $checkout, MoyasarHelper $helper)
    {
        $this->context = $context;
        $this->checkout = $checkout;
        $this->moyasarHelper = $helper;
        $this->url = $context->getUrl();
    }

    public function execute()
    {
        $response = $this->context->getResponse();

        $order = $this->checkout->getLastRealOrder();
        if (!$order || !$order->getId()) {
            $response->setStatusCode(400);
            $response->representJson(json_encode([
                'message' => 'No order available'
            ]));

            return $response;
        }

        $paymentId = $this->getJson('payment_id');
        $errors = $this->getJson('errors', []);
        $errorMsg = is_null($paymentId) ?
                    'Payment Attempt Failed, and Order have been canceled.' :
                    'Payment ' . $paymentId . ' failed and order has been canceled.';

        foreach ($errors as $error) {
            $order->addCommentToStatusHistory($error);
        }

        $this->moyasarHelper->cancelCurrentOrder($order, $errorMsg);
        $this->checkout->restoreQuote();

        $response->representJson(json_encode([
            'message' => 'Order canceled',
            'redirect_to' => $this->url->getUrl('checkout/cart')
        ]));

        return $response;
    }
}
