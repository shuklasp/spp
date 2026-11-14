<?php
namespace SPP\CLI\Commands;

use SPPMod\SPPWorkflow\SPPWorkflowManager;

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

        $workflows = SPPWorkflowManager::getWorkflows();
        if (empty($workflows)) {
            echo "No active workflows registered. Exiting.\n";
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $processedCount = 0;

        // 1. Primary Engine: Process registered deadlines from spp_workflow_slas
        $slaTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_workflow_slas');
        $now = date('Y-m-d H:i:s');

        if ($db->tableExists($slaTable)) {
            $expiredSlas = $db->exec_squery(
                "SELECT * FROM %tab% WHERE expires_at <= ? ORDER BY expires_at ASC",
                $slaTable,
                [$now]
            );

            foreach ($expiredSlas as $sla) {
                $entityType = $sla['entity_type'];
                $entityId = $sla['entity_id'];
                $state = $sla['state'];
                $timeoutTransition = $sla['timeout_transition'];

                try {
                    $entity = null;
                    if (class_exists('\\SPPMod\\SPPDB\\SPPEntity')) {
                        $entity = \SPPMod\SPPDB\SPPEntity::load($entityType, $entityId);
                    }

                    if ($entity) {
                        $currentState = method_exists($entity, 'getWorkflowState') ? $entity->getWorkflowState() : ($entity->status ?? 'draft');
                        $currentStates = is_array($currentState) ? $currentState : [$currentState];

                        if (in_array($state, $currentStates, true)) {
                            echo "SLA breach detected for {$entityType} #{$entityId} in state '{$state}'. Escalating via '{$timeoutTransition}'...\n";
                            SPPWorkflowManager::applyTransition($entity, $timeoutTransition, null, "SLA Timeout breached. Escalation transition '{$timeoutTransition}' applied automatically.");
                            $processedCount++;
                        }
                    }

                    // Remove processed or expired SLA entry
                    $db->exec_squery("DELETE FROM %tab% WHERE id = ?", $slaTable, [$sla['id']]);
                } catch (\Throwable $e) {
                    echo "Failed to process SLA for entity #{$entityId}: " . $e->getMessage() . "\n";
                }
            }
        }

        // 2. Secondary Engine: Evaluate declarative timeouts defined on states and transitions
        $tableHistory = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_workflow_history');
        if ($db->tableExists($tableHistory)) {
            foreach ($workflows as $key => $workflow) {
                $parts = explode('.', $key);
                $entityType = $parts[0];
                $bundle = $parts[1] ?? 'default';

                $states = $workflow['states'] ?? [];
                $transitions = $workflow['transitions'] ?? [];

                // Evaluate state-level timeouts: states.{state}.timeout & states.{state}.timeout_transition
                foreach ($states as $sName => $sMeta) {
                    if (is_array($sMeta) && isset($sMeta['timeout']) && isset($sMeta['timeout_transition'])) {
                        $tSec = (int)$sMeta['timeout'];
                        $timeoutTransition = (string)$sMeta['timeout_transition'];
                        $targetTimestamp = date('Y-m-d H:i:s', time() - $tSec);

                        $jsonLikePattern = '%"' . $sName . '"%';
                        $records = $db->exec_squery(
                            "SELECT entity_type, entity_id, new_status, transition_timestamp FROM %tab% WHERE entity_type = ? AND (new_status = ? OR new_status LIKE ?) AND transition_timestamp <= ? ORDER BY transition_timestamp ASC",
                            $tableHistory,
                            [$entityType, $sName, $jsonLikePattern, $targetTimestamp]
                        );

                        foreach ($records as $record) {
                            $entityId = $record['entity_id'];
                            try {
                                if (class_exists('\\SPPMod\\SPPDB\\SPPEntity')) {
                                    $entity = \SPPMod\SPPDB\SPPEntity::load($entityType, $entityId);
                                    if ($entity) {
                                        $currSt = method_exists($entity, 'getWorkflowState') ? $entity->getWorkflowState() : ($entity->status ?? 'draft');
                                        $currArr = is_array($currSt) ? $currSt : [$currSt];
                                        if (in_array($sName, $currArr, true)) {
                                            echo "State SLA breach detected for {$entityType} #{$entityId} in state '{$sName}'. Escalating via '{$timeoutTransition}'...\n";
                                            SPPWorkflowManager::applyTransition($entity, $timeoutTransition, null, "SLA Timeout breached. Escalation transition '{$timeoutTransition}' applied automatically.");
                                            $processedCount++;
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {
                                echo "Failed to process state timeout for entity #{$entityId}: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                }

                // Evaluate transition-level timeouts
                foreach ($transitions as $tName => $meta) {
                    if (!isset($meta['timeout']) || !isset($meta['timeout_transition'])) {
                        continue;
                    }

                    $timeout = $meta['timeout']; // e.g. '48 hours', '1 day', '3600 seconds'
                    $timeoutTransition = $meta['timeout_transition'];
                    $froms = (array)($meta['from'] ?? []);

                    foreach ($froms as $fromState) {
                        $targetTimestamp = date('Y-m-d H:i:s', strtotime("-{$timeout}"));
                        $jsonLikePattern = '%"' . $fromState . '"%';
                        $records = $db->exec_squery(
                            "SELECT entity_type, entity_id, new_status, transition_timestamp FROM %tab% WHERE entity_type = ? AND (new_status = ? OR new_status LIKE ?) AND transition_timestamp <= ? ORDER BY transition_timestamp ASC",
                            $tableHistory,
                            [$entityType, $fromState, $jsonLikePattern, $targetTimestamp]
                        );

                        foreach ($records as $record) {
                            $entityId = $record['entity_id'];
                            try {
                                if (class_exists('\\SPPMod\\SPPDB\\SPPEntity')) {
                                    $entity = \SPPMod\SPPDB\SPPEntity::load($entityType, $entityId);
                                    if ($entity) {
                                        $currSt = method_exists($entity, 'getWorkflowState') ? $entity->getWorkflowState() : ($entity->status ?? 'draft');
                                        $currArr = is_array($currSt) ? $currSt : [$currSt];
                                        if (in_array($fromState, $currArr, true)) {
                                            echo "Transition SLA breach detected for {$entityType} #{$entityId} in state '{$fromState}'. Escalating via '{$timeoutTransition}'...\n";
                                            SPPWorkflowManager::applyTransition($entity, $timeoutTransition, null, "SLA Timeout breached ({$timeout}). Escalation transition '{$timeoutTransition}' applied automatically.");
                                            $processedCount++;
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {
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
