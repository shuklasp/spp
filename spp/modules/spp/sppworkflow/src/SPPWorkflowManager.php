<?php

namespace SPP\Core;

use SPP\Core\Interfaces\WorkflowableInterface;

/**
 * Class WorkflowManager
 * Generic state machine and parallel marking workflow engine with Saga rollback and rich auditing.
 */
class WorkflowManager
{
    protected static ?array $workflows = null;

    /**
     * Load workflow configuration from YAML/etc, Cache, and Database.
     */
    protected static function init()
    {
        if (self::$workflows !== null) {
            return;
        }

        // 1. Try loading from SPPCacheManager
        $cacheCid = 'spp_workflows_cache';
        if (class_exists('\\SPPMod\\SPPCache\\SPPCacheManager') && \SPP\SPPConfig::get('system.auto_cache', true)) {
            $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheCid);
            if (is_array($cached)) {
                self::$workflows = $cached;
                return;
            }
        }

        self::$workflows = [];

        // 2. Load from YAML files and recursive workflows directories
        // Check in APP_ETC_DIR and SPP_ETC_DIR
        $files = [];
        $dirs = [];
        $appname = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';

        if (defined('APP_ETC_DIR')) {
            $files[] = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'workflows.yml';
            $files[] = APP_ETC_DIR . SPP_DS . 'workflows.yml';
            $dirs[] = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'workflows';
            $dirs[] = APP_ETC_DIR . SPP_DS . 'workflows';
        }
        if (defined('SPP_ETC_DIR')) {
            $files[] = SPP_ETC_DIR . SPP_DS . 'workflows.yml';
            $dirs[] = SPP_ETC_DIR . SPP_DS . 'workflows';
        }

        // Scan directories recursively for all .yml and .yaml files
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                try {
                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
                    foreach ($iterator as $fileInfo) {
                        if ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['yml', 'yaml'], true)) {
                            $files[] = $fileInfo->getPathname();
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Failed to scan workflows directory '{$dir}': " . $e->getMessage());
                }
            }
        }

        // Remove duplicates if any
        $files = array_unique($files);

        foreach ($files as $file) {
            if (file_exists($file)) {
                try {
                    $content = file_get_contents($file);
                    if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                        $parsed = \Symfony\Component\Yaml\Yaml::parse($content);
                        if (is_array($parsed)) {
                            self::$workflows = array_merge(self::$workflows, $parsed);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Failed to load workflows configuration from '{$file}': " . $e->getMessage());
                }
            }
        }

        // 3. Try loading from Database table spp_workflows if it exists
        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_workflows');
                if ($db->tableExists($table)) {
                    $results = $db->exec_squery("SELECT entity_type, bundle, definition FROM %tab%", $table);
                    foreach ($results as $row) {
                        $key = ($row['bundle'] !== 'default') ? "{$row['entity_type']}.{$row['bundle']}" : $row['entity_type'];
                        $def = json_decode($row['definition'], true);
                        if (is_array($def)) {
                            self::$workflows[$key] = $def;
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to load workflows from database: " . $e->getMessage());
            }
        }

        // 4. Save to Cache
        if (class_exists('\\SPPMod\\SPPCache\\SPPCacheManager') && \SPP\SPPConfig::get('system.auto_cache', true)) {
            \SPPMod\SPPCache\SPPCacheManager::set($cacheCid, self::$workflows);
        }
    }

    /**
     * Checks if a workflow is defined for a given entity type or bundle.
     */
    public static function getWorkflow(string $entityType, string $bundle = 'default'): ?array
    {
        self::init();
        $key = "{$entityType}.{$bundle}";
        if (isset(self::$workflows[$key])) {
            return self::$workflows[$key];
        }
        if (isset(self::$workflows[$entityType])) {
            return self::$workflows[$entityType];
        }
        return null;
    }

    /**
     * Validates a state transition for an entity, checking state graph, permissions, dynamic guards, and context.
     */
    public static function validateTransition($entity, $oldStatus, $newStatus, $user = null, array $context = []): bool
    {
        if ($oldStatus === $newStatus) {
            return true;
        }

        if ($entity instanceof WorkflowableInterface) {
            $entityType = $entity->getWorkflowEntityType();
            $bundle = $entity->getWorkflowBundle();
        } else {
            $entityClass = get_class($entity);
            $parts = explode('\\', $entityClass);
            $entityType = strtolower(array_pop($parts));

            $bundle = 'default';
            if (method_exists($entity, 'attributeExists') && $entity->attributeExists('bundle')) {
                $bundle = $entity->get('bundle') ?: 'default';
            } elseif (method_exists($entity, 'get') && isset($entity->bundle)) {
                $bundle = $entity->get('bundle') ?: 'default';
            } elseif (isset($entity->bundle)) {
                $bundle = $entity->bundle ?: 'default';
            }
        }

        $workflow = self::getWorkflow($entityType, $bundle);
        if (!$workflow) {
            // No workflow defined, allow transition by default
            return true;
        }

        // Handle Parallel Markings (Arrays of statuses)
        $oldStatuses = is_array($oldStatus) ? $oldStatus : (json_decode((string)$oldStatus, true) ?? [$oldStatus]);
        if (!is_array($oldStatuses)) {
            $oldStatuses = [$oldStatus];
        }
        $newStatuses = is_array($newStatus) ? $newStatus : (json_decode((string)$newStatus, true) ?? [$newStatus]);
        if (!is_array($newStatuses)) {
            $newStatuses = [$newStatus];
        }

        $transitions = $workflow['transitions'] ?? [];
        $allowed = false;
        $matchedTransitions = [];

        // Check if there is a valid transition from any active old status to the target new status(es)
        foreach ($newStatuses as $targetStatus) {
            if (in_array($targetStatus, $oldStatuses, true)) {
                continue;
            }
            $targetAllowed = false;
            foreach ($transitions as $transitionName => $transitionMeta) {
                $from = (array) ($transitionMeta['from'] ?? []);
                $to = $transitionMeta['to'] ?? '';

                if ((count(array_intersect($oldStatuses, $from)) > 0 || in_array('*', $from)) && $to === $targetStatus) {
                    $targetAllowed = true;
                    $matchedTransitions[] = $transitionMeta;
                    break;
                }
            }
            if ($targetAllowed) {
                $allowed = true;
            } else {
                $allowed = false;
                break;
            }
        }

        if (!$allowed) {
            $oldStr = is_array($oldStatus) ? json_encode($oldStatus) : (string)$oldStatus;
            $newStr = is_array($newStatus) ? json_encode($newStatus) : (string)$newStatus;
            throw new \SPP\Exceptions\SPPException("Workflow Transition Error: Invalid transition from status '{$oldStr}' to '{$newStr}' for {$entityType} ({$bundle}).");
        }

        // Execute Dynamic Guard Callbacks if present
        foreach ($matchedTransitions as $matchedTransition) {
            $guards = $matchedTransition['guards'] ?? [];
            if (!is_array($guards)) {
                $guards = [$guards];
            }
            foreach ($guards as $guard) {
                if (is_callable($guard)) {
                    if (!call_user_func($guard, $entity, $oldStatus, $newStatus, $user, $context)) {
                        $newStr = is_array($newStatus) ? json_encode($newStatus) : (string)$newStatus;
                        throw new \SPP\Exceptions\SPPException("Workflow Guard Error: Transition guard check failed for status '{$newStr}'.");
                    }
                }
            }
            self::validateTransitionPermission($matchedTransition, array_merge(['entity' => $entity, 'user' => $user], $context));
        }

        return true;
    }

    private static function validateTransitionPermission(array $transitionMeta, array $context): bool
    {
        $requiredPermission = $transitionMeta['permission'] ?? null;
        if ($requiredPermission) {
            $eventParams = new \SPP\EventParams([
                'permission' => $requiredPermission,
                'context' => $context,
                'authorized' => null // Listeners should set this to true or false
            ]);
            
            \SPP\SPPEvent::fireEvent('workflow.transition.authorize', $eventParams);
            
            // If no listener explicitly authorized it, deny by default.
            if ($eventParams->get('authorized') !== true) {
                throw new \SPP\Exceptions\SPPException("Workflow Authorization Error: Insufficient permissions.");
            }
        }
        return true;
    }

    public static function canTransition($entity, $newStatus, $user = null, array $context = []): bool
    {
        if ($entity instanceof WorkflowableInterface) {
            $oldStatus = $entity->getWorkflowStatus();
        } else {
            $statusField = 'status';
            if (method_exists($entity, 'getWorkflowStatusField')) {
                $statusField = $entity->getWorkflowStatusField();
            }

            $oldStatus = 'draft';
            if (method_exists($entity, 'get')) {
                try {
                    $oldStatus = $entity->get($statusField) ?: 'draft';
                } catch (\Exception $e) {
                    $oldStatus = 'draft';
                }
            } elseif (isset($entity->$statusField)) {
                $oldStatus = $entity->$statusField ?: 'draft';
            }
        }

        try {
            return self::validateTransition($entity, $oldStatus, $newStatus, $user, $context);
        } catch (\SPP\Exceptions\SPPException $e) {
            return false;
        }
    }

    /**
     * Actively apply a workflow transition to an entity, firing lifecycle events and recording audit history.
     */
    public static function applyTransition($entity, $newStatus, $user = null, string $comment = '', array $context = []): bool
    {
        if ($entity instanceof WorkflowableInterface) {
            $oldStatus = $entity->getWorkflowStatus();
            $entityType = $entity->getWorkflowEntityType();
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : ($entity->id ?? 0);
        } else {
            $statusField = 'status';
            if (method_exists($entity, 'getWorkflowStatusField')) {
                $statusField = $entity->getWorkflowStatusField();
            }

            $oldStatus = 'draft';
            if (method_exists($entity, 'get')) {
                try {
                    $oldStatus = $entity->get($statusField) ?: 'draft';
                } catch (\Exception $e) {
                    $oldStatus = 'draft';
                }
            } elseif (isset($entity->$statusField)) {
                $oldStatus = $entity->$statusField ?: 'draft';
            }

            $entityClass = get_class($entity);
            $parts = explode('\\', $entityClass);
            $entityType = strtolower(array_pop($parts));
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : ($entity->id ?? 0);
        }

        if ($oldStatus === $newStatus) {
            return true;
        }

        // 1. Validate Transition (throws SPPException if invalid)
        self::validateTransition($entity, $oldStatus, $newStatus, $user, $context);

        // Calculate parallel marking resolution if newStatus is a single state but oldStatus is an array
        $effectiveNewStatus = $newStatus;
        if (is_array($oldStatus) || (is_string($oldStatus) && json_decode($oldStatus, true) !== null && is_array(json_decode($oldStatus, true)))) {
            $oldArr = is_array($oldStatus) ? $oldStatus : json_decode($oldStatus, true);
            $newArr = is_array($newStatus) ? $newStatus : [$newStatus];
            // Merge parallel markings, replacing the matched origin state
            $workflow = self::getWorkflow($entityType, ($entity instanceof WorkflowableInterface) ? $entity->getWorkflowBundle() : 'default');
            $transitions = $workflow['transitions'] ?? [];
            foreach ($newArr as $target) {
                foreach ($transitions as $transMeta) {
                    $from = (array)($transMeta['from'] ?? []);
                    if ($transMeta['to'] === $target) {
                        $oldArr = array_diff($oldArr, $from);
                        $oldArr[] = $target;
                    }
                }
            }
            $effectiveNewStatus = array_values(array_unique($oldArr));
            if (is_string($oldStatus)) {
                $effectiveNewStatus = json_encode($effectiveNewStatus);
            }
        }

        // 2. Fire Before Transition Event
        $eventParams = new \SPP\EventParams([
            'entity' => $entity,
            'old_status' => $oldStatus,
            'new_status' => $effectiveNewStatus,
            'user' => $user,
            'comment' => $comment,
            'context' => $context
        ]);
        \SPP\SPPEvent::fireEvent('workflow.before_transition', $eventParams);
        if (method_exists('\SPP\SPPEvent', 'triggerHook')) {
            \SPP\SPPEvent::triggerHook('workflow:before_transition', $eventParams);
        }
        if ($eventParams->isPropagationStopped()) {
            return false;
        }

        // 3. Update Status on Entity
        if ($entity instanceof WorkflowableInterface) {
            $entity->setWorkflowStatus($effectiveNewStatus);
        } else {
            $statusField = method_exists($entity, 'getWorkflowStatusField') ? $entity->getWorkflowStatusField() : 'status';
            if (method_exists($entity, 'set')) {
                $entity->set($statusField, $effectiveNewStatus);
            } elseif (property_exists($entity, $statusField) || isset($entity->$statusField)) {
                $entity->$statusField = $effectiveNewStatus;
            }
        }

        // 4. Save Entity if applicable
        if (method_exists($entity, 'save')) {
            $entity->save();
        }

        // 5. Fire After Transition Event
        \SPP\SPPEvent::fireEvent('workflow.after_transition', $eventParams);
        if (method_exists('\SPP\SPPEvent', 'triggerHook')) {
            \SPP\SPPEvent::triggerHook('workflow:after_transition', $eventParams);
        }

        // 6. Record Audit History
        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_workflow_history');
                if ($db->tableExists($table)) {
                    $userId = is_object($user) ? (method_exists($user, 'getId') ? $user->getId() : ($user->id ?? 0)) : (is_numeric($user) ? (int)$user : 0);
                    if (!$userId && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
                        $currentUser = \SPPMod\SPPAuth\SPPAuth::user();
                        $userId = $currentUser->id ?? (method_exists($currentUser, 'getId') ? $currentUser->getId() : 0);
                    }
                    $oldStr = is_array($oldStatus) ? json_encode($oldStatus) : (string)$oldStatus;
                    $newStr = is_array($effectiveNewStatus) ? json_encode($effectiveNewStatus) : (string)$effectiveNewStatus;
                    $contextStr = json_encode($context);

                    $db->exec_squery(
                        "INSERT INTO %tab% (entity_type, entity_id, old_status, new_status, user_id, transition_timestamp, comment, context_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        $table,
                        [$entityType, (string)$entityId, $oldStr, $newStr, (int)$userId, date('Y-m-d H:i:s'), $comment, $contextStr]
                    );
                }
            } catch (\Exception $e) {
                error_log("Failed to log workflow history: " . $e->getMessage());
            }
        }

        // 7. Append to CQRS EventStore
        if (class_exists('\SPPMod\SPPWorkflow\CQRS\EventStore')) {
            \SPPMod\SPPWorkflow\CQRS\EventStore::append(
                $entityType,
                (string)$entityId,
                'workflow.transitioned',
                [
                    'old_status' => $oldStatus,
                    'new_status' => $effectiveNewStatus,
                    'comment' => $comment,
                    'context' => $context
                ],
                ['user' => is_object($user) ? (method_exists($user, 'getId') ? $user->getId() : ($user->id ?? null)) : $user]
            );
        }

        return true;
    }

    /**
     * Rollback an entity's workflow transitions using the Saga Pattern (executing compensating transactions).
     */
    public static function rollback($entity, $user = null, array $context = []): bool
    {
        if ($entity instanceof WorkflowableInterface) {
            $entityType = $entity->getWorkflowEntityType();
            $bundle = $entity->getWorkflowBundle();
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : ($entity->id ?? 0);
        } else {
            $entityClass = get_class($entity);
            $parts = explode('\\', $entityClass);
            $entityType = strtolower(array_pop($parts));
            $bundle = method_exists($entity, 'get') ? ($entity->get('bundle') ?: 'default') : ($entity->bundle ?? 'default');
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : ($entity->id ?? 0);
        }

        $workflow = self::getWorkflow($entityType, $bundle);
        if (!$workflow || !class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return false;
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_workflow_history');
            if (!$db->tableExists($table)) {
                return false;
            }

            // Get transition history in reverse chronological order
            $history = $db->exec_squery(
                "SELECT * FROM %tab% WHERE entity_type = ? AND entity_id = ? ORDER BY transition_timestamp DESC",
                $table,
                [$entityType, (string)$entityId]
            );

            $transitions = $workflow['transitions'] ?? [];
            $targetRestoredStatus = null;

            foreach ($history as $record) {
                $oldStatus = $record['old_status'];
                $newStatus = $record['new_status'];

                // Find matching transition definition
                foreach ($transitions as $tName => $tMeta) {
                    $from = (array)($tMeta['from'] ?? []);
                    if ((in_array($oldStatus, $from) || in_array('*', $from)) && $tMeta['to'] === $newStatus) {
                        // Check for compensations
                        $compensations = $tMeta['compensations'] ?? [];
                        if (!is_array($compensations)) {
                            $compensations = [$compensations];
                        }
                        foreach ($compensations as $comp) {
                            if (is_callable($comp)) {
                                call_user_func($comp, $entity, $newStatus, $oldStatus, $user, $context);
                            }
                        }
                    }
                }
                $targetRestoredStatus = $oldStatus;
                break; // Rollback one step at a time
            }

            if ($targetRestoredStatus !== null) {
                if ($entity instanceof WorkflowableInterface) {
                    $entity->setWorkflowStatus($targetRestoredStatus);
                } else {
                    $statusField = method_exists($entity, 'getWorkflowStatusField') ? $entity->getWorkflowStatusField() : 'status';
                    if (method_exists($entity, 'set')) {
                        $entity->set($statusField, $targetRestoredStatus);
                    } elseif (property_exists($entity, $statusField) || isset($entity->$statusField)) {
                        $entity->$statusField = $targetRestoredStatus;
                    }
                }

                if (method_exists($entity, 'save')) {
                    $entity->save();
                }

                $userId = is_object($user) ? (method_exists($user, 'getId') ? $user->getId() : ($user->id ?? 0)) : (is_numeric($user) ? (int)$user : 0);
                $contextStr = json_encode($context);
                $db->exec_squery(
                    "INSERT INTO %tab% (entity_type, entity_id, old_status, new_status, user_id, transition_timestamp, comment, context_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    $table,
                    [$entityType, (string)$entityId, $record['new_status'], $targetRestoredStatus, (int)$userId, date('Y-m-d H:i:s'), "Saga Rollback applied.", $contextStr]
                );
                return true;
            }
        } catch (\Exception $e) {
            error_log("Failed to execute Saga rollback: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Register a workflow definition at runtime.
     * This allows applications (e.g., Lekhak) to define workflows programmatically
     * without requiring a workflows.yml file.
     *
     * @param string $entityType  e.g. 'node', 'article'
     * @param array  $definition  ['states' => [...], 'transitions' => [...]]
     * @param string $bundle      Optional bundle name (default: 'default')
     */
    public static function registerWorkflow(string $entityType, array $definition, string $bundle = 'default'): void
    {
        self::init();
        $key = ($bundle !== 'default') ? "{$entityType}.{$bundle}" : $entityType;
        self::$workflows[$key] = $definition;
        
        // Invalidate Cache
        if (class_exists('\\SPPMod\\SPPCache\\SPPCacheManager') && \SPP\SPPConfig::get('system.auto_cache', true)) {
            \SPPMod\SPPCache\SPPCacheManager::delete('spp_workflows_cache');
        }
    }

    /**
     * Return all registered/loaded workflow definitions.
     *
     * @return array
     */
    public static function getWorkflows(): array
    {
        self::init();
        return self::$workflows;
    }

    /**
     * Get valid next states from the current state for a given entity type.
     *
     * @param string $entityType
     * @param string $currentState
     * @param string $bundle
     * @return string[] List of valid target states
     */
    public static function getNextStates(string $entityType, string $currentState, string $bundle = 'default'): array
    {
        $workflow = self::getWorkflow($entityType, $bundle);
        if (!$workflow) {
            return [];
        }

        $transitions = $workflow['transitions'] ?? [];
        $nextStates = [];

        foreach ($transitions as $name => $meta) {
            $from = (array)($meta['from'] ?? []);
            if (in_array($currentState, $from, true) || in_array('*', $from, true)) {
                $nextStates[] = $meta['to'];
            }
        }

        return array_unique($nextStates);
    }
}
