<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
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
        $order = $this->checkout->getLastRealOrder();
        if (!$order || !$order->getId()) {
            return $this->context
                ->getResultFactory()
                ->create(ResultFactory::TYPE_RAW)
                ->setHttpResponseCode(200);
        }

        $paymentId = $this->getJson('payment_id');
        $errors = $this->getJson('errors', []);
        $errorMsg = is_null($paymentId) ?
                    __('Payment Attempt Failed, and Order have been canceled.') :
                    __('Payment %payment_id failed and order has been canceled', ['payment_id' => $paymentId]);

        foreach ($errors as $error) {
            $order->addCommentToStatusHistory($error);
        }

        $this->checkout->restoreQuote();
        $order->registerCancellation($errorMsg);
        $order->save();

        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_JSON)
            ->setData([
                'message' => __('Order canceled'),
                'redirect_to' => $this->url->getUrl('checkout/cart')
            ]);
    }
}
