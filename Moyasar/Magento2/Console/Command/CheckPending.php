<?php

namespace Moyasar\Magento2\Console\Command;

use Magento\Framework\App\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
        ObjectManager::getInstance()->get(\Moyasar\Magento2\Schedule\CheckPending::class)->cron();
    }
}
