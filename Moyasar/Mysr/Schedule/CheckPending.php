<?php

namespace Moyasar\Mysr\Schedule;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Reports\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class CheckPending
{
    private $orderCollection;
    private $moyasarHelper;
    private $cachePool;
    private $logger;

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

        $apiPayments = $this->moyasarHelper->getOrderPayments($order->getId());
        usort($apiPayments, function ($a, $b) {
            return new DateTime($a['created_at']) < new DateTime($b['created_at']) ? -1 : 1;
        });

        if (count($apiPayments) == 0) {
            try {
                $order->registerCancellation('Order was canceled because there were no payment attempts within 15 minutes.', false);
                $order->save();
            } catch (LocalizedException $e) {
                $order->addCommentToStatusHistory('Order cannot be canceled automatically, order must be canceled manually.');
            }

            return;
        }

        foreach ($apiPayments as $payment) {
            $order->addCommentToStatusHistory(
                'Payment: ' .
                $payment['id'] .
                ', status: ' .
                $payment['status'] .
                ', message: ' .
                $payment['source']['message']
            );
        }

        $paidPayments = array_filter($apiPayments, function ($p) {
            return $p['status'] == 'paid';
        });

        // No successful payments, add all IDs in history and cancel order
        if (count($paidPayments) == 0) {
            $order->getPayment()->setLastTransId($apiPayments[count($apiPayments) - 1]['id']);

            try {
                $order->registerCancellation('No successful payments, order canceled.', false);
                $order->save();
            } catch (LocalizedException $e) {
                $order->addCommentToStatusHistory('Order cannot be canceled automatically, order must be canceled manually.');
            }

            return;
        }

        $payment = $paidPayments[0];
        $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
        if (count($errors) > 0) {
            array_unshift($errors, 'Un-matching payment details ' . $payment['id']);

            $order->registerCancellation(implode("\n", $errors));
            $order->getPayment()->setLastTransId($payment['id']);
            $order->addCommentToStatusHistory('Order was canceled automatically by cron jobs.');
            $order->save();

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
            ->where('updated_at >= ?', $this->date()->sub(DateInterval::createFromDateString('6 hour'))->format('Y-m-d H:i:s'))
            ->where('updated_at <= ?', $this->date()->sub(DateInterval::createFromDateString('15 minutes'))->format('Y-m-d H:i:s'))
            ->where('main_table.state in (?)', ['new', 'pending_payment'])
            ->where('main_table.status = ?', 'pending')
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

    /**
     * @param string $id
     * @return void
     */
    private function markChecked(string $id): void
    {
        $this->cache()->save('true', $this->cacheKey($id), [], 3600 * 12);
    }

    private function date()
    {
        return new DateTime('now', new DateTimeZone('utc'));
    }
}
