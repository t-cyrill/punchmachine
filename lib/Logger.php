<?php
namespace Punchmachine;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        echo date('H:i:s'), "[{$level}] {$message}", PHP_EOL;
    }
}
