<?php
namespace SPPMod\SPPLogger;

interface LoggerTargetInterface
{
    /**
     * Write a log entry to the target.
     * 
     * @param string $message The log message
     * @param string $level   The log level
     * @param array  $metadata Contextual metadata
     * @param array  $context  Additional context
     * @return bool True on success, false on failure
     */
    public function write(string $message, string $level, array $metadata, array $context = []): bool;
}
