<?php

namespace Moyasar\Mysr\Console\Command;

use DateTime;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class CheckPendingPaymentsCommand extends Command
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var MoyasarHelper
     */
    protected $moyasarHelper;

    /**
     * @var Pool
     */
    protected $cachePool;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        parent::__construct('moyasar:payment:process');
    }

    /**
     * Called by the Scheduler
     */
    public function cron()
    {
        $this->execute(new ArgvInput([]), new ConsoleOutput());
    }

    protected function configure()
    {
        $this->setDescription('Process payments for orders with pending status');
        parent::configure();
    }

    protected function initServices()
    {
        $objectManager = ObjectManager::getInstance();

        $this->orderRepository = $objectManager->get(OrderRepository::class);
        $this->moyasarHelper = $objectManager->get(MoyasarHelper::class);
        $this->cachePool = $objectManager->get(Pool::class);
        $this->logger = $objectManager->get(LoggerInterface::class);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initServices();
        $orders = $this->getPendingOrders();

        foreach ($orders as $order) {
            $this->process($order);
        }

        $this->logger->debug('Moyasar Payments: Checked ' . count($orders) . ' Order/s.');
    }

    /**
     * @param Order $order
     */
    private function process($order)
    {
        if (
            $order->getState() != Order::STATE_NEW &&
            $order->getState() != Order::STATE_PENDING_PAYMENT &&
            $order->getState() != Order::STATE_PAYMENT_REVIEW
        ) {
            return;
        }

        $cacheKey = 'moyasar-order-checked-' . $order->getId();

        // If already checked within the last 5 minutes, skip
        if ($this->cache()->load($cacheKey) == 'checked') {
            return;
        }

        // Cache order for 5 minutes
        $this->cache()->save('checked', $cacheKey, [], 60 * 15);

        $this->processPayment($order);
    }

    /**
     * @param Order $order
     * @return void
     */
    private function processPayment($order)
    {
        $payment = $order->getPayment();

        if (is_null($payment)) {
            return;
        }

        $additionalInfo = $payment->getAdditionalInformation();

        if (! isset($additionalInfo['moyasar_payment_id'])) {
            return;
        }

        $moyasarPaymentId = $additionalInfo['moyasar_payment_id'];

        $this->moyasarHelper->verifyAndProcess($order, $moyasarPaymentId, null);
    }

    private function criteriaBuilder()
    {
        return ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
    }

    private function getPendingOrders()
    {
        $dateStart = new DateTime();
        $dateStart->modify('-5 day');

        $dateEnd = new DateTime();
        $dateEnd->modify('-2 minute');

        $search = $this->criteriaBuilder()
            ->addFilter('state', Order::STATE_NEW)
            ->addFilter('created_at', $dateStart->format('Y-m-d H:i:s'), 'gteq')
            ->addFilter('created_at', $dateEnd->format('Y-m-d H:i:s'), 'lteq')
            ->create();

        $pendingPaymentSearch = $this->criteriaBuilder()
            ->addFilter('state', Order::STATE_PENDING_PAYMENT)
            ->addFilter('created_at', $dateStart->format('Y-m-d H:i:s'), 'gteq')
            ->addFilter('created_at', $dateEnd->format('Y-m-d H:i:s'), 'lteq')
            ->create();

        $reviewPaymentSearch = $this->criteriaBuilder()
            ->addFilter('state', Order::STATE_PAYMENT_REVIEW)
            ->addFilter('created_at', $dateStart->format('Y-m-d H:i:s'), 'gteq')
            ->addFilter('created_at', $dateEnd->format('Y-m-d H:i:s'), 'lteq')
            ->create();

        return array_merge(
            $this->orderRepository->getList($search)->getItems(),
            $this->orderRepository->getList($pendingPaymentSearch)->getItems(),
            $this->orderRepository->getList($reviewPaymentSearch)->getItems()
        );
    }

    private function cache()
    {
        return $this->cachePool->current();
    }
}
