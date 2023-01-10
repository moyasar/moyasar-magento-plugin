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
    public function __construct()
    {
        parent::__construct('moyasar:process_pending');
    }

    protected function configure()
    {
        $this->setDescription('Process payments for orders with pending status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ObjectManager::getInstance()->get(\Moyasar\Mysr\Schedule\CheckPending::class)->cron();
    }
}
