<?php
declare(strict_types=1);

namespace SPPMod\SPPLogger;

if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::listen('log', function(\SPP\EventParams $params) {
        if (!class_exists('\\SPPMod\\SPPLogger\\SPP_Logger')) {
            require_once __DIR__ . '/class.spplogger.php';
        }
        
        $level = $params->get('level');
        $message = $params->get('message');
        
        if ($level === 'info') {
            SPP_Logger::info($message);
        } elseif ($level === 'error') {
            SPP_Logger::error($message);
        } elseif ($level === 'warn' || $level === 'warning') {
            SPP_Logger::warning($message);
        } else {
            SPP_Logger::debug($message);
        }
    });
}
