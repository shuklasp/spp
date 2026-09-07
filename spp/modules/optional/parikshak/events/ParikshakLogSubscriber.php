<?php

namespace SPPMod\Parikshak\Events;

use SPP\EventHandler;

/**
 * Subscriber to log Parikshak testing events.
 */
class ParikshakLogSubscriber extends EventHandler
{
    /**
     * Subscribe to Parikshak lifecycle events.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'parikshak.suite_started'      => 'onSuiteStarted',
            'parikshak.entity_test_failed' => ['onTestFailed', 900], // High priority for critical failures
            'parikshak.suite_completed'    => 'onSuiteCompleted'
        ];
    }

    public function onSuiteStarted(\SPP\EventParams $params)
    {
        $payload = $params->getPayload();
        $this->log("--- Parikshak Suite Started for App: {$payload['app']} ---");
    }

    public function onTestFailed(\SPP\EventParams $params)
    {
        $payload = $params->getPayload();
        $errors = implode(' | ', $payload['errors'] ?? []);
        $this->log("[CRITICAL] Test FAILED for Entity: {$payload['class']}. Errors: {$errors}");
    }

    public function onSuiteCompleted(\SPP\EventParams $params)
    {
        $payload = $params->getPayload();
        $summary = $payload['summary'];
        $this->log("--- Parikshak Suite Completed. Passed: {$summary['passed']}, Failed: {$summary['failed']} ---");
    }

    private function log(string $message): void
    {
        $logDir = SPP_APP_DIR . '/var/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $file = $logDir . '/parikshak_events.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
    }
}
