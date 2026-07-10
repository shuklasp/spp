<?php
namespace SPPMod\SPPIntegrations;

use SPPMod\SPPWorkflow\CQRS\EventStore;

/**
 * Class IntegrationSyncJob
 * 
 * Represents a single fault-tolerant sync task for the Saga pattern.
 */
class IntegrationSyncJob
{
    /**
     * Executes the sync logic and logs to EventStore.
     */
    public static function process(array $payload): bool
    {
        $driverAlias = $payload['driver_alias'];
        $userData = $payload['user_data'];

        // Restore W3C Trace Context Telemetry if provided
        if (isset($payload['trace_id']) && class_exists('\SPPMod\SPPReport\W3CTraceContext')) {
            \SPPMod\SPPReport\W3CTraceContext::restore($payload['trace_id']);
        }

        try {
            $driver = IntegrationFactory::getDriver($driverAlias);
            $success = $driver->syncUser($userData);

            // CQRS Event Logging for Immutable Audit Trail
            if (class_exists(EventStore::class)) {
                EventStore::log(
                    'integration_sync',
                    $userData['id'] ?? 'unknown',
                    [
                        'target' => $driverAlias,
                        'status' => $success ? 'success' : 'failed',
                        'data'   => $userData
                    ]
                );
            }

            return $success;
        } catch (\Exception $e) {
            // Log failure to CQRS
            if (class_exists(EventStore::class)) {
                EventStore::log(
                    'integration_sync_error', 
                    $userData['id'] ?? 'unknown', 
                    ['target' => $driverAlias, 'error' => $e->getMessage()]
                );
            }
            throw $e; // Throwing allows the DagJobOrchestrator to trigger compensations/retries
        }
    }
}
