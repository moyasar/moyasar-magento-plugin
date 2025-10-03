<?php

namespace Moyasar\Magento2\Helper;

use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;

class MoyasarLogs
{
    /**
     * Log a message to a custom file.
     *
     * @param string $level The log level (e.g., 'info', 'error', 'debug').
     * @param string $message The message to log.
     * @param array $context Additional context for the log message.
     */
    public function log($level = 'info', $message = '', $context = [])
    {
        // Use ObjectManager to get the logger instance
        $logger = ObjectManager::getInstance()->get(LoggerInterface::class);

        // Define the full file path for the log file
        $filePath = BP . '/var/log/' . 'moyasar.log';
        $time = date('Y-m-d H:i:s');


        // Additionally, log directly to the custom file
        error_log( '[' . $time . '] - ' . $message . '-' . json_encode($context) . PHP_EOL, 3, $filePath);
    }

    public function info($message, $context = [])
    {
        $this->log('info', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('warning', $message, $context);
    }

}
