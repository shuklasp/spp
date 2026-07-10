<?php
namespace SPP\CLI\Commands;

use SPP\Core\WorkflowManager;

class WorkflowProcessTimeoutsCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'workflow:process-timeouts';
    }

    public function getDescription(): string
    {
        return 'Process SLA timeouts on entities and trigger automatic escalation transitions';
    }

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Starting workflow SLA timeout processing daemon...\n";

        if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            echo "Error: SPPDB module is required for evaluating timeout histories.\n";
            return;
        }

        $workflows = WorkflowManager::getWorkflows();
        if (empty($workflows)) {
            echo "No active workflows registered. Exiting.\n";
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $tableHistory = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_workflow_history');

        if (!$db->tableExists($tableHistory)) {
            echo "Workflow history table {$tableHistory} does not exist. Please run config:sync first.\n";
            return;
        }

        $processedCount = 0;

        foreach ($workflows as $key => $workflow) {
            $parts = explode('.', $key);
            $entityType = $parts[0];
            $bundle = $parts[1] ?? 'default';

            $transitions = $workflow['transitions'] ?? [];
            foreach ($transitions as $tName => $meta) {
                if (!isset($meta['timeout']) || !isset($meta['timeout_transition'])) {
                    continue;
                }

                $timeout = $meta['timeout']; // e.g. '48 hours', '1 day', '3600 seconds'
                $timeoutTransition = $meta['timeout_transition'];
                $froms = (array)($meta['from'] ?? []);

                foreach ($froms as $fromState) {
                    // Find all entities currently in $fromState whose last transition timestamp is older than $timeout
                    $targetTimestamp = date('Y-m-d H:i:s', strtotime("-{$timeout}"));

                    $records = $db->exec_squery(
                        "SELECT entity_type, entity_id, new_status, transition_timestamp FROM %tab% WHERE entity_type = ? AND new_status = ? AND transition_timestamp <= ? ORDER BY transition_timestamp ASC",
                        $tableHistory,
                        [$entityType, $fromState, $targetTimestamp]
                    );

                    foreach ($records as $record) {
                        $entityId = $record['entity_id'];
                        echo "SLA breach detected for {$entityType} #{$entityId} in state '{$fromState}' (since {$record['transition_timestamp']}). Escalating via '{$timeoutTransition}'...\n";

                        // Load entity if SPPEntity exists
                        if (class_exists('\\SPPMod\\SPPDB\\SPPEntity')) {
                            try {
                                $entity = \SPPMod\SPPDB\SPPEntity::load($entityType, $entityId);
                                if ($entity) {
                                    // Find target state for timeout_transition
                                    if (isset($transitions[$timeoutTransition])) {
                                        $escalationState = $transitions[$timeoutTransition]['to'];
                                        WorkflowManager::applyTransition($entity, $escalationState, null, "SLA Timeout breached ({$timeout}). Escalation transition '{$timeoutTransition}' applied automatically.");
                                        $processedCount++;
                                    }
                                }
                            } catch (\Exception $e) {
                                echo "Failed to process timeout escalation for entity #{$entityId}: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                }
            }
        }

        echo "SLA timeout processing complete. {$processedCount} entities escalated.\n";
    }
}
