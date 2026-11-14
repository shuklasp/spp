<?php

namespace SPPMod\SPPWorkflow;

use SPP\Core\Interfaces\WorkflowableInterface;

/**
 * Class SPPWorkflowManager
 * Generic state machine and parallel marking workflow engine with Saga rollback and rich auditing.
 */
class SPPWorkflowManager
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
        // Check in self-contained app conf dir, APP_ETC_DIR and SPP_ETC_DIR
        $files = [];
        $dirs = [];
        $appname = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';

        if (class_exists('\SPP\App')) {
            try {
                $app = \SPP\App::getApp($appname);
                if ($app) {
                    $appConfDir = $app->getAppConfDir();
                    if ($appConfDir && is_dir($appConfDir)) {
                        $files[] = $appConfDir . SPP_DS . 'workflows.yml';
                        $dirs[] = $appConfDir . SPP_DS . 'workflows';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore if app instance cannot be instantiated
            }

            if (defined('SPP_APP_DIR')) {
                $srcEtcDir = SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $appname . SPP_DS . 'etc';
                if (is_dir($srcEtcDir)) {
                    $files[] = $srcEtcDir . SPP_DS . 'workflows.yml';
                    $dirs[] = $srcEtcDir . SPP_DS . 'workflows';
                }
            }
        }

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
                            // Check if this is an un-wrapped single workflow definition
                            if (isset($parsed['states']) || isset($parsed['transitions'])) {
                                $rawName = $parsed['name'] ?? pathinfo($file, PATHINFO_FILENAME);
                                $wfDef = $parsed;

                                // Normalize root-level timeout if declared
                                if (isset($wfDef['timeout']) && isset($wfDef['timeout_transition'])) {
                                    $t = $wfDef['timeout'];
                                    if (is_string($t) && !is_numeric($t)) {
                                        $val = strtotime(($t[0] === '+' ? '' : '+') . $t, 0);
                                        $t = $val !== false ? $val : (int)$t;
                                    }
                                    $initSt = $wfDef['initial_state'] ?? (is_array($wfDef['states']) ? (array_key_first($wfDef['states']) ?? 'draft') : 'draft');
                                    if (isset($wfDef['states'][$initSt]) && is_array($wfDef['states'][$initSt])) {
                                        $wfDef['states'][$initSt]['timeout'] = (int)$t;
                                        $wfDef['states'][$initSt]['timeout_transition'] = (string)$wfDef['timeout_transition'];
                                    }
                                }

                                // Normalize state timeouts
                                if (isset($wfDef['states']) && is_array($wfDef['states'])) {
                                    foreach ($wfDef['states'] as $sk => $sv) {
                                        if (is_array($sv) && isset($sv['timeout'])) {
                                            $t = $sv['timeout'];
                                            if (is_string($t) && !is_numeric($t)) {
                                                $val = strtotime(($t[0] === '+' ? '' : '+') . $t, 0);
                                                $t = $val !== false ? $val : (int)$t;
                                            }
                                            $wfDef['states'][$sk]['timeout'] = (int)$t;
                                        }
                                        if (is_array($sv) && isset($sv['timeout_transition'])) {
                                            $wfDef['states'][$sk]['timeout_transition'] = (string)$sv['timeout_transition'];
                                        }
                                    }
                                }

                                self::$workflows[$rawName] = $wfDef;

                                if (!empty($parsed['entity'])) {
                                    $entityParts = explode('\\', $parsed['entity']);
                                    $entityShort = strtolower(array_pop($entityParts));
                                    $entityShort = preg_replace('/entity$/', '', $entityShort) ?: $entityShort;
                                    self::$workflows[$entityShort] = $wfDef;
                                    self::$workflows["{$entityShort}.default"] = $wfDef;
                                }

                                if (str_ends_with($rawName, '_workflow')) {
                                    $baseName = substr($rawName, 0, -9);
                                    self::$workflows[$baseName] = $wfDef;
                                    self::$workflows["{$baseName}.default"] = $wfDef;
                                }
                            } else {
                                // Wrapped format: key => [states => ..., transitions => ...]
                                foreach ($parsed as $wk => $wv) {
                                    if (is_array($wv) && (isset($wv['states']) || isset($wv['transitions']))) {
                                        if (isset($wv['states']) && is_array($wv['states'])) {
                                            foreach ($wv['states'] as $sk => $sv) {
                                                if (is_array($sv) && isset($sv['timeout'])) {
                                                    $t = $sv['timeout'];
                                                    if (is_string($t) && !is_numeric($t)) {
                                                        $val = strtotime(($t[0] === '+' ? '' : '+') . $t, 0);
                                                        $t = $val !== false ? $val : (int)$t;
                                                    }
                                                    $parsed[$wk]['states'][$sk]['timeout'] = (int)$t;
                                                }
                                                if (is_array($sv) && isset($sv['timeout_transition'])) {
                                                    $parsed[$wk]['states'][$sk]['timeout_transition'] = (string)$sv['timeout_transition'];
                                                }
                                            }
                                        }
                                        self::$workflows[$wk] = $parsed[$wk];
                                    }
                                }
                            }
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
        $candidates = [
            "{$entityType}.{$bundle}",
            $entityType,
            strtolower($entityType),
            strtolower("{$entityType}.{$bundle}")
        ];

        // Suffix resolution: handle article_workflow -> article and article -> article_workflow
        $lower = strtolower($entityType);
        if (str_ends_with($lower, '_workflow')) {
            $base = substr($lower, 0, -9);
            $candidates[] = $base;
            $candidates[] = "{$base}.{$bundle}";
        } else {
            $candidates[] = "{$lower}_workflow";
            $candidates[] = "{$lower}_workflow.{$bundle}";
        }

        foreach ($candidates as $cand) {
            if (isset(self::$workflows[$cand])) {
                return self::$workflows[$cand];
            }
        }

        return null;
    }

    /**
     * Get a workflow definition by entity type or workflow name (alias for getWorkflow).
     */
    public static function get(string $entityType, string $bundle = 'default'): ?array
    {
        return self::getWorkflow($entityType, $bundle);
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

                if ((count(array_intersect($oldStatuses, $from)) > 0 || in_array('*', $from)) && ($to === $targetStatus || $transitionName === $targetStatus)) {
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
        $requiredPermissions = [];
        if (!empty($transitionMeta['permission'])) {
            $requiredPermissions[] = $transitionMeta['permission'];
        }
        if (!empty($transitionMeta['permissions'])) {
            $requiredPermissions = array_merge($requiredPermissions, (array)$transitionMeta['permissions']);
        }

        $requiredRoles = [];
        if (!empty($transitionMeta['role'])) {
            $requiredRoles[] = $transitionMeta['role'];
        }
        if (!empty($transitionMeta['roles'])) {
            $requiredRoles = array_merge($requiredRoles, (array)$transitionMeta['roles']);
        }

        if (empty($requiredPermissions) && empty($requiredRoles)) {
            return true;
        }

        $eventParams = new \SPP\EventParams([
            'permission' => $requiredPermissions[0] ?? null,
            'permissions' => $requiredPermissions,
            'roles' => $requiredRoles,
            'context' => $context,
            'authorized' => null // Listeners should set this to true or false
        ]);
        
        \SPP\SPPEvent::fireEvent('workflow.transition.authorize', $eventParams);
        
        $authorized = $eventParams->get('authorized');
        if ($authorized === null) {
            // Bridge to SPPAuth if no custom listener handled authorization
            $user = $context['user'] ?? null;
            if ($user === null && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
                $user = \SPPMod\SPPAuth\SPPAuth::user();
            }
            if ($user && is_object($user)) {
                $hasPerm = empty($requiredPermissions);
                foreach ($requiredPermissions as $perm) {
                    if ((method_exists($user, 'can') && $user->can($perm)) ||
                        (method_exists($user, 'hasPermission') && $user->hasPermission($perm))) {
                        $hasPerm = true;
                        break;
                    }
                }

                $hasRole = empty($requiredRoles);
                foreach ($requiredRoles as $role) {
                    if (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole($role))) {
                        $hasRole = true;
                        break;
                    }
                }

                if ($hasPerm && $hasRole) {
                    $authorized = true;
                }
            }
        }

        // If still not authorized, deny.
        if ($authorized !== true) {
            throw new \SPP\Exceptions\SPPException("Workflow Authorization Error: Insufficient permissions.");
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

        // Resolve transition action name to target state if transition action name was supplied
        $transitionName = is_string($newStatus) ? $newStatus : 'transition';
        $workflow = self::getWorkflow($entityType, ($entity instanceof WorkflowableInterface) ? $entity->getWorkflowBundle() : 'default');
        $transitions = $workflow['transitions'] ?? [];
        if (is_string($newStatus) && isset($transitions[$newStatus]['to'])) {
            $newStatus = $transitions[$newStatus]['to'];
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
            
            // Re-validate the newly computed parallel state combination
            self::validateTransition($entity, $oldStatus, $effectiveNewStatus, $user, $context);
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

        // 4.5 Register SLA Timeout for Daemon Orchestration
        $workflow = self::getWorkflow($entityType, ($entity instanceof WorkflowableInterface) ? $entity->getWorkflowBundle() : 'default');
        $targets = is_array($effectiveNewStatus) ? $effectiveNewStatus : (is_string($effectiveNewStatus) && json_decode($effectiveNewStatus, true) ? json_decode($effectiveNewStatus, true) : [$effectiveNewStatus]);
        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $slaTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_workflow_slas');
                if (!$db->tableExists($slaTable)) {
                    $db->exec_squery("CREATE TABLE %tab% (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        entity_type VARCHAR(255),
                        entity_id VARCHAR(255),
                        state VARCHAR(255),
                        timeout_transition VARCHAR(255),
                        expires_at DATETIME,
                        INDEX (expires_at)
                    )", $slaTable);
                } else {
                    // Clear previous active SLAs for this entity upon transition
                    $db->exec_squery("DELETE FROM %tab% WHERE entity_type = ? AND entity_id = ?", $slaTable, [$entityType, (string)$entityId]);
                }

                foreach ($targets as $target) {
                    if (isset($workflow['states'][$target]['timeout']) && isset($workflow['states'][$target]['timeout_transition'])) {
                        $timeout = $workflow['states'][$target]['timeout'];
                        $timeoutTransition = $workflow['states'][$target]['timeout_transition'];
                        $expiresAt = date('Y-m-d H:i:s', time() + (int)$timeout);
                        $db->exec_squery(
                            "INSERT INTO %tab% (entity_type, entity_id, state, timeout_transition, expires_at) VALUES (?, ?, ?, ?, ?)",
                            $slaTable,
                            [$entityType, (string)$entityId, $target, $timeoutTransition, $expiresAt]
                        );
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to register SLA: " . $e->getMessage());
            }
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
                    $oldStr = is_array($oldStatus) ? json_encode($oldStatus) : (string)$oldStatus;
                    $newStr = is_array($effectiveNewStatus) ? json_encode($effectiveNewStatus) : (string)$effectiveNewStatus;
                    self::insertWorkflowHistory($db, $table, $entityType, (string)$entityId, $oldStr, $newStr, $transitionName, $user, $comment, $context);
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
    public static function rollback($entity, $user = null, $commentOrContext = [], array $context = []): bool
    {
        $comment = is_string($commentOrContext) ? $commentOrContext : "Saga Rollback applied.";
        $context = is_array($commentOrContext) ? $commentOrContext : $context;
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
                "SELECT * FROM %tab% WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC, transition_timestamp DESC",
                $table,
                [$entityType, (string)$entityId]
            );

            $transitions = $workflow['transitions'] ?? [];
            $targetRestoredStatus = null;
            $matchedRecord = null;
            $rollbackDepth = 0;

            foreach ($history as $record) {
                $tName = $record['transition_name'] ?? '';
                $comment = $record['comment'] ?? '';
                $ctx = $record['context_data'] ?? '';
                if ($tName === 'rollback' || str_starts_with($comment, 'Saga Rollback') || (is_string($ctx) && str_contains($ctx, '"is_rollback":true'))) {
                    $rollbackDepth++;
                    continue;
                }

                // If this forward transition was already rolled back by a subsequent rollback, skip it
                if ($rollbackDepth > 0) {
                    $rollbackDepth--;
                    continue;
                }

                // Found the most recent uncompensated forward transition!
                $oldStatus = $record['previous_status'] ?? $record['old_status'] ?? null;
                $newStatus = $record['new_status'];
                $matchedRecord = $record;

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

            if ($targetRestoredStatus !== null && $matchedRecord !== null) {
                $eventParams = new \SPP\EventParams([
                    'entity' => $entity,
                    'old_status' => $matchedRecord['new_status'],
                    'new_status' => $targetRestoredStatus,
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

                // Clear active SLA for this entity on rollback
                $slaTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_workflow_slas');
                if ($db->tableExists($slaTable)) {
                    $db->exec_squery("DELETE FROM %tab% WHERE entity_type = ? AND entity_id = ?", $slaTable, [$entityType, (string)$entityId]);
                }

                \SPP\SPPEvent::fireEvent('workflow.after_transition', $eventParams);
                if (method_exists('\SPP\SPPEvent', 'triggerHook')) {
                    \SPP\SPPEvent::triggerHook('workflow:after_transition', $eventParams);
                }

                self::insertWorkflowHistory($db, $table, $entityType, (string)$entityId, (string)$matchedRecord['new_status'], (string)$targetRestoredStatus, 'rollback', $user, $comment, array_merge($context, ['is_rollback' => true]));
                return true;
            }
        } catch (\Exception $e) {
            error_log("Failed to execute Saga rollback: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Resilient database insertion into spp_entity_workflow_history.
     */
    private static function insertWorkflowHistory(\SPPMod\SPPDB\SPPDB $db, string $table, string $entityType, string $entityId, string $oldStatus, string $newStatus, string $transitionName, $user, string $comment, array $context): void
    {
        $userId = is_object($user) ? (method_exists($user, 'getId') ? $user->getId() : ($user->id ?? 0)) : (is_numeric($user) ? (int)$user : 0);
        if (!$userId && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            $currentUser = \SPPMod\SPPAuth\SPPAuth::user();
            $userId = $currentUser->id ?? (method_exists($currentUser, 'getId') ? $currentUser->getId() : 0);
        }

        $cols = ['entity_type', 'entity_id'];
        $vals = [$entityType, (string)$entityId];

        if ($db->columnExists($table, 'previous_status')) {
            $cols[] = 'previous_status';
            $vals[] = $oldStatus;
        } elseif ($db->columnExists($table, 'old_status')) {
            $cols[] = 'old_status';
            $vals[] = $oldStatus;
        }

        $cols[] = 'new_status';
        $vals[] = $newStatus;

        if ($db->columnExists($table, 'transition_name')) {
            $cols[] = 'transition_name';
            $vals[] = $transitionName;
        }

        $cols[] = 'user_id';
        $vals[] = (string)$userId;

        $cols[] = 'transition_timestamp';
        $vals[] = date('Y-m-d H:i:s');

        $cols[] = 'comment';
        $vals[] = $comment;

        if ($db->columnExists($table, 'context_data')) {
            $cols[] = 'context_data';
            $vals[] = json_encode($context);
        }

        $colList = implode(', ', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $db->exec_squery("INSERT INTO %tab% ({$colList}) VALUES ({$placeholders})", $table, $vals);
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

    /**
     * Retrieves all valid transition definitions for an entity or state,
     * evaluating authorization, guards, and color-coded UI button styles.
     *
     * @param object|string $entity Entity instance or entity type string
     * @param mixed $user Optional user context
     * @param array $context Additional context data
     * @return array List of available transitions with metadata and button CSS classes
     */
    public static function getAvailableTransitions($entity, $user = null, array $context = []): array
    {
        $entityType = is_object($entity) ? (new \ReflectionClass($entity))->getShortName() : (string)$entity;
        $bundle = (is_object($entity) && method_exists($entity, 'getBundle')) ? $entity->getBundle() : 'default';
        $workflow = self::getWorkflow($entityType, $bundle);
        if (!$workflow) {
            return [];
        }

        $currentState = 'draft';
        if (is_object($entity)) {
            if (method_exists($entity, 'getWorkflowState')) {
                $st = $entity->getWorkflowState();
                $currentState = is_array($st) ? ($st[0] ?? 'draft') : (string)$st;
            } elseif (isset($entity->status)) {
                $currentState = (string)$entity->status;
            }
        } elseif (is_string($entity)) {
            $currentState = $context['current_state'] ?? 'draft';
        }

        $transitions = $workflow['transitions'] ?? [];
        $available = [];

        foreach ($transitions as $name => $meta) {
            $from = (array)($meta['from'] ?? []);
            if (in_array($currentState, $from, true) || in_array('*', $from, true)) {
                if (is_object($entity)) {
                    try {
                        if (!self::canTransition($entity, $name, $user, $context)) {
                            continue;
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }

                $label = $meta['label'] ?? ucfirst(str_replace('_', ' ', $name));
                $to = $meta['to'] ?? '';

                // Smart UX Color coding
                $lower = strtolower($name . ' ' . $to);
                $btnClass = 'btn-secondary';
                if (preg_match('/(approve|publish|complete|activate|verify|accept)/', $lower)) {
                    $btnClass = 'btn-success';
                } elseif (preg_match('/(reject|archive|cancel|delete|fail|deactivate)/', $lower)) {
                    $btnClass = 'btn-danger';
                } elseif (preg_match('/(submit|review|send|forward|start)/', $lower)) {
                    $btnClass = 'btn-primary';
                }

                $available[] = [
                    'name' => $name,
                    'label' => $label,
                    'to' => $to,
                    'class' => $btnClass,
                    'meta' => $meta
                ];
            }
        }

        return $available;
    }
}

// Backward compatibility alias for legacy references
class_alias('\SPPMod\SPPWorkflow\SPPWorkflowManager', '\SPP\Core\WorkflowManager');
class_alias('\SPPMod\SPPWorkflow\SPPWorkflowManager', '\SPPMod\SPPWorkflow\WorkflowManager');
