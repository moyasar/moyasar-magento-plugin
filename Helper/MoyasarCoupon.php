<?php

namespace Moyasar\Magento2\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\StoreManagerInterface;
use Moyasar\Magento2\Helper\CurrencyHelper;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Moyasar\Magento2\Helper\MoyasarLogs;


class MoyasarCoupon extends AbstractHelper
{
    /**
     * @var CurrencyHelper
     */
    protected $currencyHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param CurrencyHelper $currencyHelper
     */
    public function __construct(
        Context               $context,
        CurrencyHelper        $currencyHelper
    )
    {
        parent::__construct($context);
        $this->currencyHelper = $currencyHelper;
        $this->logger = new MoyasarLogs();;
    }

    /**
     * Try applying a coupon to an existing order, based on Payment metadata.
     *
     * **Note**: This will NOT re-collect or re-price an already placed order.
     * It simply stores the coupon code & notes on the order.
     *
     * @param $order
     * @param $payment
     */
    public function tryApplyCouponToOrder($order, $payment)
    {
        // We expect these custom keys from your payment metadata
        if (!isset($payment['metadata']['#coupon_id'])) {
            return; // No coupon metadata to apply
        }
        // Extract discount info from Payment metadata:
        $capturedAmount = $payment['amount'] ?? 0;
        $totalPrice = $payment['metadata']['#coupon_original_amount'] ?? 0;
        $currency = $payment['currency'] ?? $order->getOrderCurrencyCode();
        $discountedAmount = $this->currencyHelper->amountToMajor($totalPrice - $capturedAmount, $currency);
        if ($order->getSubtotal() !== $order->getGrandTotal()) {
            $taxPercent = $order->getTaxAmount() / $order->getSubtotal();
            $discountedAmount = $discountedAmount / (1 + $taxPercent);
        }


        $order->setDiscountAmount(-$discountedAmount);
        $order->setDiscountDescription('Moyasar Discount: ' . $payment['metadata']['#coupon_id']);
        $order->setBaseDiscountAmount(-$discountedAmount);

        // Recalculate grand total
        $newGrandTotal = $this->currencyHelper->amountToMajor($capturedAmount, $currency);
        $order->setGrandTotal($newGrandTotal);
        $order->setBaseGrandTotal($newGrandTotal);
        $order->setTotalPaid($newGrandTotal);
        $order->save();
        $this->logger->info("[Moyasar][Coupon] Successfully applied coupon code '{$payment['metadata']['#coupon_id']}' to order #{$order->getIncrementId()} (no recalculation).");
    }
}
