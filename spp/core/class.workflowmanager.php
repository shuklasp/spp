<?php
namespace SPP\Core;

/**
 * Class WorkflowManager
 * Generic state machine workflow engine.
 */
class WorkflowManager
{
    protected static ?array $workflows = null;

    /**
     * Load workflow configuration from YAML/etc
     */
    protected static function init()
    {
        if (self::$workflows !== null) return;
        self::$workflows = [];

        // Check in APP_ETC_DIR and SPP_ETC_DIR
        $files = [];
        $appname = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';

        if (defined('APP_ETC_DIR')) {
            $files[] = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'workflows.yml';
            $files[] = APP_ETC_DIR . SPP_DS . 'workflows.yml';
        }
        if (defined('SPP_ETC_DIR')) {
            $files[] = SPP_ETC_DIR . SPP_DS . 'workflows.yml';
        }

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
                    error_log("Failed to load workflows configuration: " . $e->getMessage());
                }
            }
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
     * Validates a state transition for an entity.
     */
    public static function validateTransition($entity, string $oldStatus, string $newStatus, $user = null): bool
    {
        if ($oldStatus === $newStatus) return true;

        $entityClass = get_class($entity);
        $parts = explode('\\', $entityClass);
        $entityType = strtolower(array_pop($parts));
        
        $bundle = 'default';
        if (method_exists($entity, 'get') && $entity->attributeExists('bundle')) {
            $bundle = $entity->get('bundle') ?: 'default';
        }

        $workflow = self::getWorkflow($entityType, $bundle);
        if (!$workflow) {
            // No workflow defined, allow transition by default
            return true;
        }

        $transitions = $workflow['transitions'] ?? [];
        $allowed = false;
        $requiredPermission = null;

        foreach ($transitions as $transitionName => $transitionMeta) {
            $from = (array) ($transitionMeta['from'] ?? []);
            $to = $transitionMeta['to'] ?? '';

            if ((in_array($oldStatus, $from) || in_array('*', $from)) && $to === $newStatus) {
                $allowed = true;
                $requiredPermission = $transitionMeta['permission'] ?? null;
                break;
            }
        }

        if (!$allowed) {
            throw new \SPP\Exceptions\SPPException("Workflow Transition Error: Invalid transition from status '{$oldStatus}' to '{$newStatus}' for {$entityType} ({$bundle}).");
        }

        if ($requiredPermission) {
            if (class_exists('\SPPMod\SPPAuth\SPPAuth')) {
                if (!\SPPMod\SPPAuth\SPPAuth::can($requiredPermission)) {
                    throw new \SPP\Exceptions\SPPException("Workflow Authorization Error: You do not have the required permission '{$requiredPermission}' to transition from '{$oldStatus}' to '{$newStatus}'.");
                }
            } else {
                throw new \SPP\Exceptions\SPPException("Workflow System Error: sppauth module is required but not loaded.");
            }
        }

        return true;
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
        if (!$workflow) return [];

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

