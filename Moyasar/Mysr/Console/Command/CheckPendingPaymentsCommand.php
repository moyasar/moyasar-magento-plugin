<?php

namespace Moyasar\Mysr\Console\Command;

use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Moyasar\Mysr\Helper\Data;
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
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Data
     */
    protected $dataHelper;

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
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->dataHelper = $objectManager->get(Data::class);
        $this->cachePool = $objectManager->get(Pool::class);
        $this->logger = $objectManager->get(LoggerInterface::class);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute($input, $output)
    {
        $this->initServices();
        $orders = $this->getPendingOrders();

        foreach ($orders as $order) {
            $this->process($order);
        }

        $output->writeln('Moyasar Payments: Checked ' . count($orders) . ' Order/s.');
    }

    /**
     * @param Order $order
     */
    private function process($order)
    {
        if ($order->getState() != Order::STATE_NEW) {
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
            $paymentId = $payment->getEntityId();
            $orderId = $order->getId();
            $this->logger->warning("Payment ($paymentId) of Order ($orderId) does not have Moyasar payment ID");
            return;
        }

        $moyasarPaymentId = $additionalInfo['moyasar_payment_id'];

        $response = null;
        try {
            $response = $this->dataHelper->fetchMoyasarPayment($moyasarPaymentId);
        } catch (Exception $e) {
            $this->logger->error('Could not fetch Moyasar payment', ['exception' => $e]);
            return;
        }

        if (isset($response['type']) && $response['type'] == 'api_error') {
            $this->logger->error('Could not fetch Moyasar payment ' . $moyasarPaymentId);
            return;
        }

        if (! isset($response['status'])) {
            $this->logger->warning('Unrecognized response from Moyasar', ['response' => $response]);
            return;
        }

        $paymentStatus = trim(mb_strtolower($response['status']));

        if ($paymentStatus == 'paid') {
            if ($this->dataHelper->verifyAmount($order, $moyasarPaymentId, $response)) {
                $order->addStatusToHistory(Order::STATE_PAYMENT_REVIEW, 'Automated Check: Payment is successful. ID: ' . $moyasarPaymentId);
                $this->dataHelper->processOrder($order, $moyasarPaymentId);
            }
        } elseif ($paymentStatus == 'initiated') {
            $this->logger->debug('Payment is still new, passing order ' . $order->getId());

            $order->addStatusToHistory(Order::STATE_PAYMENT_REVIEW, 'Automated Check: Payment is still initiated. ID: ' . $moyasarPaymentId);
            $this->dataHelper->saveOrder($order);

            // The customer still did not pay yet, so we will wait
            return;
        } else {
            // Something is wrong with the payment, cancel the order
            $message = $moyasarPaymentId;

            if (isset($response['source']['message'])) {
                $message = $message . '. Moyasar Says: ' . $response['source']['message'];
            }

            try {
                if ($this->dataHelper->cancelCurrentOrder($order, $message)) {
                    $this->logger->info('Order was canceled by automated job', [
                        'payment_status' => $paymentStatus,
                        'payment_id' => $moyasarPaymentId,
                        'order_id' => $order->getId()
                    ]);
                }
            } catch (Exception $e) {
                $this->logger->error('Could not cancel order automatically', [
                    'exception' => $e,
                    'order_id' => $order->getId()
                ]);
            }
        }
    }

    private function getPendingOrders()
    {
        $dateStart = new DateTime();
        $dateStart->modify('-5 day');

        $dateEnd = new DateTime();
        $dateEnd->modify('-2 minute');

        $search = $this->searchCriteriaBuilder
            ->addFilter('state', Order::STATE_NEW)
            ->addFilter('created_at', $dateStart->format('Y-m-d H:i:s'), 'gteq')
            ->addFilter('created_at', $dateEnd->format('Y-m-d H:i:s'), 'lteq')
            ->create();

        return $this->orderRepository->getList($search)->getItems();
    }

    private function cache()
    {
        return $this->cachePool->current();
    }
}
