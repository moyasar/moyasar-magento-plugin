<?php

namespace Moyasar\Mysr\Console\Command;

use Magento\Framework\App\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCanceled extends Command
{
    public function __construct()
    {
        parent::__construct('moyasar:process_canceled');
    }

    protected function configure()
    {
        $this->setDescription('Process payments for orders with pending status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ObjectManager::getInstance()->get(\Moyasar\Mysr\Schedule\CheckCanceled::class)->cron();
    }
}
