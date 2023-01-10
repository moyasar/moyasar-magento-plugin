<?php

namespace Moyasar\Mysr\Schedule;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Reports\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\Order;
use Moyasar\Mysr\Helper\Http\QuickHttp;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;

class CheckCanceled
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
        foreach ($this->canceledOrders() as $order) {
            if ($this->isChecked($order->getIncrementId())) {
                continue;
            }

            $this->logger->info('Checking canceled order payments ' . $order->getIncrementId() . '...');

            try {
                $this->updateOrder($order);
                $this->markChecked($order->getIncrementId());
            } catch (Exception $exception) {
                $this->logger->error($exception);
            }
        }
    }

    private function updateOrder($order): void
    {
        $payments = $this->getOrderPayments($order->getIncrementId());
        $payments = array_filter($payments, function ($p) {
            return $p['status'] == 'paid';
        });

        if (count($payments) == 0) {
            return;
        }

        $this->logger->info('Found paid payment for order ' . $order->getIncrementId());

        $firstPayment = array_shift($payments);
        $errors = $this->moyasarHelper->checkPaymentForErrors($order, $firstPayment);
        if (count($errors) > 0) {
            array_unshift($errors, 'Un-matching payment details ' . $firstPayment['id']);
            $this->processFailedPayment($firstPayment, $order, $errors);
        } else {
            $this->moyasarHelper->processSuccessfulOrder($order, $firstPayment);
        }

        // Add comment for duplicate payments
        foreach ($payments as $payment) {
            $order->addCommentToStatusHistory("Order has duplicate payment " . $payment['id'] . ", you need to void or refund it.");
        }

        if (count($payments) > 0) {
            $order->save();
        }
    }

    /**
     * @return Order[]
     */
    private function canceledOrders(): array
    {
        $query = $this->orderCollection
            ->getSelect()
            ->join(['pp' => 'sales_order_payment'], 'main_table.entity_id = pp.parent_id')
            ->where('updated_at >= ?', $this->date()->sub(DateInterval::createFromDateString('2 hour'))->format('Y-m-d H:i:s'))
            ->where('updated_at <= ?', $this->date()->format('Y-m-d H:i:s'))
            ->where('main_table.status = ?', 'canceled')
            ->where('pp.method = ?', 'moyasar_payments');

        return $this->orderCollection->load($query)->getItems();
    }

    private function http(): QuickHttp
    {
        return new QuickHttp();
    }

    private function cache(): FrontendInterface
    {
        return $this->cachePool->current();
    }

    private function cacheKey(string $id): string
    {
        return "moy_check_cancel_order_$id";
    }

    private function isChecked(string $id): bool
    {
        return $this->cache()->load($this->cacheKey($id)) == 'true';
    }

    private function markChecked(string $id): void
    {
        $this->cache()->save('true', $this->cacheKey($id), [], 60 * 15);
    }

    private function getOrderPayments(string $id): array
    {
        $payments = [];
        $currentPage = 1;
        $lastPage = 1;

        do {
            $response = $this->http()
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->get(
                    $this->moyasarHelper->apiBaseUrl('/v1/payments'),
                    ['metadata[real_order_id]' => $id, 'page' => $currentPage]
                )->json();

            $payments = array_merge($payments, $response['payments']);
            $lastPage = $response['meta']['total_pages'];
            $currentPage++;
        } while ($currentPage <= $lastPage);

        return $payments;
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
