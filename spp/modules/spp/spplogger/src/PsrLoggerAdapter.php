<?php

namespace SPP\Core;

use Psr\Log\LoggerInterface;
use SPPMod\SPPLogger\SPP_Logger;

class PsrLoggerAdapter implements LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::EMERGENCY, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::ALERT, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::CRITICAL, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::ERROR, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::WARNING, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::NOTICE, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::INFO, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        SPP_Logger::write_to_log((string) $message, SPP_Logger::DEBUG, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $validLevels = [
            SPP_Logger::EMERGENCY, SPP_Logger::ALERT, SPP_Logger::CRITICAL,
            SPP_Logger::ERROR, SPP_Logger::WARNING, SPP_Logger::NOTICE,
            SPP_Logger::INFO, SPP_Logger::DEBUG
        ];

        if (!in_array($level, $validLevels, true)) {
            throw new \Psr\Log\InvalidArgumentException("Invalid log level: {$level}");
        }

        SPP_Logger::write_to_log((string) $message, (string) $level, $context);
    }
}
