<?php

namespace SPP\Core;

use Psr\Log\LoggerInterface;
use SPPMod\SPPLogger\SPP_Logger;

class PsrLoggerAdapter implements LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $validLevels = [
            'emergency', 'alert', 'critical',
            'error', 'warning', 'notice',
            'info', 'debug'
        ];

        if (!in_array($level, $validLevels, true)) {
            throw new \Psr\Log\InvalidArgumentException("Invalid log level: {$level}");
        }

        \SPP\SPPEvent::dispatch('spp.log', ['level' => $level, 'message' => (string)$message, 'context' => $context]);
    }
}
