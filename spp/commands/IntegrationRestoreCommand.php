<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPWorkflow\CQRS\EventStore;
use SPPMod\SPPIntegrations\IntegrationFactory;

/**
 * Class IntegrationRestoreCommand
 * 
 * Leverages CQRS Event Sourcing to perform point-in-time recovery (time-travel)
 * for synchronized users across the integration mesh.
 */
class IntegrationRestoreCommand extends Command
{
    protected string $name = 'integration:restore';
    protected string $description = 'Time-travel a user state to a historical point using CQRS Event Sourcing';

    public function isCLIOnly(): bool 
    { 
        return true; 
    }

    public function execute(array $args): void
    {
        if (count($args) < 2) {
            echo "Usage: php spp.php integration:restore <user_id> <timestamp_or_snapshot_id>\n";
            return;
        }

        $userId = $this->getArgument($args, 0);
        $targetTime = $this->getArgument($args, 1);

        echo "Initializing CQRS Time Travel for User {$userId} to {$targetTime}...\n";

        if (!class_exists(EventStore::class)) {
            echo "[ERROR] CQRS EventStore is not available in this environment.\n";
            return;
        }

        try {
            // Retrieve the historical snapshot from the CQRS Event Store
            $snapshot = EventStore::getSnapshotAtTime('integration_sync', $userId, $targetTime);

            if (!$snapshot) {
                echo "[ERROR] No historical snapshot found for User {$userId} at {$targetTime}.\n";
                return;
            }

            echo "Snapshot retrieved. Restoring User Data:\n";
            print_r($snapshot['data']);

            // Re-broadcast the historical state, forcing all drivers to sync backward
            echo "\nRe-broadcasting historical state to the Mesh (Saga Orchestration)...\n";
            $results = IntegrationFactory::broadcastUserSync($snapshot['data']);
            
            echo "Time Travel Initiated Successfully. Jobs queued for processing.\n";

        } catch (\Exception $e) {
            echo "[ERROR] Time travel failed: " . $e->getMessage() . "\n";
        }
    }
}
