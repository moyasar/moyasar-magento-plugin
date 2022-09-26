<?php

namespace Moyasar\Mysr\Console\Command;

use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Moyasar\Mysr\Helper\Http\Exceptions\HttpException;
use Moyasar\Mysr\Helper\Http\QuickHttp;
use Moyasar\Mysr\Helper\MoyasarHelper;
use Moyasar\Mysr\Model\Payment\MoyasarPayments;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class CheckPending extends Command
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

    /**
     * @var QuickHttp
     */
    protected $http;

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
        $this->http = new QuickHttp();
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
            if ($order->getPayment()->getMethod() != MoyasarPayments::CODE) {
                continue;
            }

            $this->process($order);
        }

        $this->logger->debug('Moyasar Payments: Checked ' . count($orders) . ' Order/s.');
    }

    /**
     * @param Order $order
     */
    private function process($order)
    {
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
        $this->logger->info("Processing pending order " . $order->getIncrementId());

        $orderPayment = $order->getPayment();
        $paymentId = $orderPayment->getLastTransId();
        if (! $paymentId) {
            $this->logger->warning("Cannot find payment ID for order " . $order->getIncrementId());
            return;
        }

        try {
            $this->logger->info("Fetching Moyasar payment $paymentId...");

            $payment = $this->http
                ->basic_auth($this->moyasarHelper->secretApiKey())
                ->get($this->moyasarHelper->apiBaseUrl("/v1/payments/$paymentId"))
                ->json();

            $this->logger->info("Fetched payment $paymentId.");

            if ($payment['status'] != 'paid') {
                $message = __('Payment failed');
                if ($sourceMessage = $payment['source']['message']) {
                    $message .= ': ' . $sourceMessage;
                }

                return $this->processFailedPayment($payment, $order, [$message]);
            }

            $errors = $this->moyasarHelper->checkPaymentForErrors($order, $payment);
            if (count($errors) > 0) {
                array_unshift($errors, 'Un-matching payment details ' . $payment['id']);
                return $this->processFailedPayment($payment, $order, $errors);
            }

            $order->addCommentToStatusHistory('Order was canceled automatically by cron jobs.');
            $this->moyasarHelper->processSuccessfulOrder($order, $payment);

            $this->logger->info("Processed order " . $order->getIncrementId());
        } catch (HttpException $e) {
            $this->logger->error($e);
            $this->logger->info($e->response->body());
        } catch (Exception $e) {
            $this->logger->error($e);
        }
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
        $dateEnd->modify('-15 minute');

        $pendingPaymentSearch = $this->criteriaBuilder()
            ->addFilter('state', Order::STATE_PENDING_PAYMENT)
            ->addFilter('created_at', $dateStart->format('Y-m-d H:i:s'), 'gteq')
            ->addFilter('created_at', $dateEnd->format('Y-m-d H:i:s'), 'lteq')
            ->create();

        return $this->orderRepository->getList($pendingPaymentSearch)->getItems();
    }

    private function cache()
    {
        return $this->cachePool->current();
    }

    /**
     * @param array $payment
     * @param Order $order
     * @param array $errors
     * @return mixed
     */
    private function processFailedPayment($payment, $order, $errors)
    {
        $order->registerCancellation(implode("\n", $errors));
        $order->getPayment()->setLastTransId($payment['id']);
        $order->addCommentToStatusHistory('Order was canceled automatically by cron jobs.');
        $order->save();
    }
}
