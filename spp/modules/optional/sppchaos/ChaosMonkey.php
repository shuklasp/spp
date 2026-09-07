<?php

namespace SPPMod\SPPChaos;

use SPPMod\SPPReport\W3CTraceContext;

/**
 * ChaosMonkey
 * Enterprise Chaos Engineering & Resilience Injector. Designed to selectively inject simulated latency,
 * network jitter, cURL timeouts, and database disconnects in staging environments to verify CQRS outbox
 * retries and DAG failovers.
 */
class ChaosMonkey
{
    private static array $config = [
        'enabled' => false,
        'injection_rate_percentage' => 5, // 5% probability of injecting chaos
        'fault_types' => ['latency', 'network_jitter', 'curl_timeout', 'db_disconnect']
    ];

    public static function configure(bool $enabled, int $injectionRate = 5, array $faultTypes = []): void
    {
        self::$config['enabled'] = $enabled;
        self::$config['injection_rate_percentage'] = min(100, max(0, $injectionRate));
        if (!empty($faultTypes)) {
            self::$config['fault_types'] = $faultTypes;
        }
    }

    public static function injectChaos(string $targetScope = 'general'): void
    {
        if (!self::$config['enabled']) {
            return;
        }

        // Check probability
        if (random_int(1, 100) > self::$config['injection_rate_percentage']) {
            return; // Safe this time
        }

        // Select a random fault type to inject
        $faultType = self::$config['fault_types'][array_rand(self::$config['fault_types'])];

        if (class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
            W3CTraceContext::startSpan("chaos_injection.{$faultType}", [
                'chaos.scope' => $targetScope,
                'chaos.fault' => $faultType
            ]);
        }

        switch ($faultType) {
            case 'latency':
                // Simulate 500ms latency spike
                usleep(500000);
                break;
            case 'network_jitter':
                // Simulate random jitter between 100ms and 800ms
                usleep(random_int(100000, 800000));
                break;
            case 'curl_timeout':
                // Throw a simulated cURL timeout exception to test Transactional Outbox retry fallback
                throw new \RuntimeException("Simulated ChaosMonkey Fault: cURL connection timed out after 1000ms to target scope: {$targetScope}");
            case 'db_disconnect':
                // Throw a simulated PDO/Database connection drop to test DAG job failover
                throw new \PDOException("Simulated ChaosMonkey Fault: SQLSTATE[HY000] [2002] Connection refused (simulated db drop)");
        }
    }

    public static function getConfig(): array
    {
        return self::$config;
    }
}
