<?php

namespace Moyasar\Mysr\Schedule;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Reports\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\Http\QuickHttp;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class CheckPending
{
    private Collection $orderCollection;
    private MoyasarHelper $moyasarHelper;
    private Pool $cachePool;
    private LoggerInterface $logger;

    public function __construct(
        Collection $orderCollection,
        MoyasarHelper $moyasarHelper,
        Pool $cachePool,
        LoggerInterface $logger
    ) {
        $this->orderCollection = $orderCollection;
        $this->moyasarHelper = $moyasarHelper;
        $this->cachePool = $cachePool;
        $this->logger = $logger;
    }

    public function cron(): void
    {
        foreach ($this->pendingOrders() as $order) {
            if ($this->isChecked($order->getIncrementId())) {
                continue;
            }

            $this->logger->info('Checking pending order payments ' . $order->getIncrementId() . '...');

            try {
                $this->processPayment($order);
                $this->markChecked($order->getIncrementId());
            } catch (Exception $exception) {
                $this->logger->error($exception);
            }
        }
    }

    /**
     * @param Order $order
     * @return void
     */
    private function processPayment($order)
    {
        $this->logger->info("Processing pending order " . $order->getIncrementId());

        $orderPayment = $order->getPayment();
        $paymentId = $orderPayment->getLastTransId();
        if (! $paymentId) {
            $this->logger->warning("Cannot find payment ID for order " . $order->getIncrementId());
            return;
        }

        $this->logger->info("Fetching Moyasar payment $paymentId...");

        $payment = $this->http()
            ->basic_auth($this->moyasarHelper->secretApiKey())
            ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId"))
            ->json();

        $this->logger->info("Fetched payment $paymentId.");

        if ($payment['status'] != 'paid') {
            $message = __('Payment failed');
            if ($sourceMessage = $payment['source']['message']) {
                $message .= ': ' . $sourceMessage;
            }

            $this->processFailedPayment($payment, $order, [$message]);
            return;
        }

        $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
        if (count($errors) > 0) {
            array_unshift($errors, 'Un-matching payment details ' . $payment['id']);
            $this->processFailedPayment($payment, $order, $errors);
            return;
        }

        $this->moyasarHelper->processSuccessfulOrder($order, $payment);
        $this->logger->info("Processed order " . $order->getIncrementId());
    }

    /**
     * @return Order[]
     */
    private function pendingOrders(): array
    {
        $query = $this->orderCollection
            ->getSelect()
            ->join(['pp' => 'sales_order_payment'], 'main_table.entity_id = pp.parent_id')
            ->where('updated_at >= ?', $this->date()->sub(DateInterval::createFromDateString('30 minutes'))->format('Y-m-d H:i:s'))
            ->where('updated_at <= ?', $this->date()->format('Y-m-d H:i:s'))
            ->where('main_table.status = ?', Order::STATE_PENDING_PAYMENT)
            ->where('pp.method = ?', 'moyasar_payments');

        return $this->orderCollection->load($query)->getItems();
    }

    private function cache()
    {
        return $this->cachePool->current();
    }

    private function cacheKey(string $id): string
    {
        return "moy_check_pending_order_$id";
    }

    private function isChecked(string $id): bool
    {
        return $this->cache()->load($this->cacheKey($id)) == 'true';
    }

    private function markChecked(string $id): void
    {
        $this->cache()->save('true', $this->cacheKey($id), [], 60 * 15);
    }

    private function http(): QuickHttp
    {
        return new QuickHttp();
    }

    private function processFailedPayment($payment, $order, $errors)
    {
        $order->registerCancellation(implode("\n", $errors));
        $order->getPayment()->setLastTransId($payment['id']);
        $order->addCommentToStatusHistory('Order was canceled automatically by cron jobs.');
        $order->save();
    }

    private function date()
    {
        return new DateTime('now', new DateTimeZone('utc'));
    }
}
