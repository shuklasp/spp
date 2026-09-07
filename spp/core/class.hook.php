<?php

namespace SPP;

/**
 * Class Hook
 *
 * Universal Kernel Hook Bus for the SPP Framework (Drupal module_invoke_all & drupal_alter parity).
 * Provides event-driven, decoupled extensibility across the framework, modules, themes, and applications.
 *
 * Implements dual event bus firing:
 *   - \SPP\SPPEvent::fireEvent()
 *   - \SPP\SPPEvent::triggerHook()
 *
 * Supports:
 *   1. Programmatic callback registration with priority ordering.
 *   2. Procedural module hooks (e.g. {module}_{hook}() or hook_{hook}()).
 *   3. Data alteration through references (invokeAlter()).
 *
 * @package SPP
 * @author Satya Prakash Shukla
 */
class Hook
{
    /** @var array<string,array<int,array{callback:callable,priority:int,id:string}>> Registered hook listeners */
    private static array $hooks = [];

    /**
     * Register a callback listener for a specific hook.
     *
     * @param string $hook Name of the hook (e.g. 'issue_insert', 'theme_init')
     * @param callable $callback Executable listener callback
     * @param int $priority Execution priority (higher executes earlier, default 10)
     * @param string|null $id Unique identifier for unregistering/debugging
     */
    public static function register(string $hook, callable $callback, int $priority = 10, ?string $id = null): void
    {
        $hook = strtolower(trim($hook));
        $id = $id ?? ('hook_' . bin2hex(random_bytes(6)));

        if (!isset(self::$hooks[$hook])) {
            self::$hooks[$hook] = [];
        }

        self::$hooks[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'id' => $id,
        ];

        // Sort descending by priority
        usort(self::$hooks[$hook], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Unregister a hook listener by ID or clear all listeners for a hook.
     *
     * @param string $hook Name of the hook
     * @param string|null $id Specific listener ID to remove, or null to remove all
     */
    public static function unregister(string $hook, ?string $id = null): void
    {
        $hook = strtolower(trim($hook));
        if (!isset(self::$hooks[$hook])) {
            return;
        }

        if ($id === null) {
            unset(self::$hooks[$hook]);
            return;
        }

        self::$hooks[$hook] = array_values(array_filter(
            self::$hooks[$hook],
            fn($entry) => $entry['id'] !== $id
        ));
    }

    /**
     * Check if a hook has registered listeners or callable procedural functions.
     *
     * @param string $hook Name of the hook
     * @return bool
     */
    public static function has(string $hook): bool
    {
        $hook = strtolower(trim($hook));
        if (!empty(self::$hooks[$hook])) {
            return true;
        }

        if (function_exists('hook_' . $hook)) {
            return true;
        }

        $context = class_exists('\SPP\Scheduler', false) ? \SPP\Scheduler::getContext() : '';
        if ($context && function_exists($context . '_' . $hook)) {
            return true;
        }

        return false;
    }

    /**
     * Invoke a hook across all registered listeners, procedural functions, and dual event buses.
     * Matches Drupal module_invoke_all semantics.
     *
     * @param string $hook Name of the hook (e.g. 'issue_insert', 'page_render')
     * @param array $args Arguments passed to listeners
     * @return array Collected non-null return values keyed by listener ID or module
     */
    public static function invokeAll(string $hook, array $args = []): array
    {
        $hook = strtolower(trim($hook));
        $results = [];

        // 1. Dual Event Bus dispatching
        if (class_exists('\SPP\SPPEvent', false)) {
            try {
                $evtParams = new \SPP\EventParams($args);
                \SPP\SPPEvent::fireEvent("hook.{$hook}", $evtParams);
                \SPP\SPPEvent::fireEvent($hook, $evtParams);
                $argsCopy1 = $args;
                \SPP\SPPEvent::triggerHook("hook:{$hook}", $argsCopy1);
                $argsCopy2 = $args;
                \SPP\SPPEvent::triggerHook($hook, $argsCopy2);
            } catch (\Throwable $e) {
                error_log("[Hook Event Bus Error] ({$hook}): " . $e->getMessage());
            }
        }

        // 2. Invoke registered programmatic callbacks
        if (!empty(self::$hooks[$hook])) {
            foreach (self::$hooks[$hook] as $entry) {
                try {
                    $ref = null;
                    try {
                        if (is_array($entry['callback'])) {
                            $ref = new \ReflectionMethod($entry['callback'][0], $entry['callback'][1]);
                        } elseif ($entry['callback'] instanceof \Closure || is_string($entry['callback'])) {
                            $ref = new \ReflectionFunction($entry['callback']);
                        }
                    } catch (\Throwable $e) {}

                    if ($ref && $ref->getNumberOfParameters() === 1 && count($args) > 1 && array_keys($args) !== range(0, count($args) - 1)) {
                        $callArgs = [$args];
                    } else {
                        $callArgs = array_values($args);
                    }

                    $res = call_user_func_array($entry['callback'], $callArgs);
                    if ($res !== null) {
                        $results[$entry['id']] = $res;
                    }
                } catch (\Throwable $e) {
                    error_log("[Hook Callback Exception] ({$hook} => {$entry['id']}): " . $e->getMessage());
                }
            }
        }

        // 3. Invoke procedural global function hooks (Drupal style)
        $candidates = [];

        // Context-specific hook (e.g. sppdocs_issue_insert)
        $context = class_exists('\SPP\Scheduler', false) ? \SPP\Scheduler::getContext() : '';
        if ($context !== '') {
            $candidates[strtolower($context)] = strtolower($context) . '_' . $hook;
        }

        // Universal hook_{hook}
        $candidates['global'] = 'hook_' . $hook;

        // Check active modules if SPP\Module is available
        if (class_exists('\SPP\Module', false)) {
            try {
                $activeModules = \SPP\Module::getActiveModules();
                if (is_array($activeModules)) {
                    foreach ($activeModules as $mod) {
                        $modName = is_array($mod) ? ($mod['name'] ?? '') : (string)$mod;
                        if ($modName !== '') {
                            $candidates[strtolower($modName)] = strtolower($modName) . '_' . $hook;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        foreach ($candidates as $key => $func) {
            if (function_exists($func)) {
                try {
                    $res = call_user_func_array($func, $callArgs);
                    if ($res !== null) {
                        $results[$key] = $res;
                    }
                } catch (\Throwable $e) {
                    error_log("[Hook Procedural Function Exception] ({$func}): " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Alter data through all registered alter listeners and procedural alter functions.
     * Matches Drupal drupal_alter semantics.
     *
     * @param string $hook Name of the alter hook (e.g. 'theme_page', 'form')
     * @param mixed $data Reference to data structure to be modified in-place
     * @param mixed $context Additional read-only context passed to listeners
     */
    public static function invokeAlter(string $hook, mixed &$data, mixed $context = null): void
    {
        $hook = strtolower(trim($hook));
        $alterHook = $hook . '_alter';

        // 1. Dual Event Bus dispatching
        if (class_exists('\SPP\SPPEvent', false)) {
            try {
                $evtParams = new \SPP\EventParams(['data' => &$data, 'context' => $context]);
                \SPP\SPPEvent::fireEvent("hook.{$alterHook}", $evtParams);
                $alterPayload1 = ['data' => &$data, 'context' => $context];
                \SPP\SPPEvent::triggerHook("hook:{$alterHook}", $alterPayload1);
                $alterPayload2 = ['data' => &$data, 'context' => $context];
                \SPP\SPPEvent::triggerHook($alterHook, $alterPayload2);
            } catch (\Throwable $e) {
                error_log("[Hook Alter Event Bus Error] ({$alterHook}): " . $e->getMessage());
            }
        }

        // 2. Invoke registered programmatic callbacks
        if (!empty(self::$hooks[$alterHook])) {
            foreach (self::$hooks[$alterHook] as $entry) {
                try {
                    ($entry['callback'])($data, $context);
                } catch (\Throwable $e) {
                    error_log("[Hook Alter Callback Exception] ({$alterHook} => {$entry['id']}): " . $e->getMessage());
                }
            }
        }

        // 3. Invoke procedural global function hooks
        $candidates = [];
        $appContext = class_exists('\SPP\Scheduler', false) ? \SPP\Scheduler::getContext() : '';
        if ($appContext !== '') {
            $candidates[strtolower($appContext)] = strtolower($appContext) . '_' . $alterHook;
        }

        $candidates['global'] = 'hook_' . $alterHook;

        if (class_exists('\SPP\Module', false)) {
            try {
                $activeModules = \SPP\Module::getActiveModules();
                if (is_array($activeModules)) {
                    foreach ($activeModules as $mod) {
                        $modName = is_array($mod) ? ($mod['name'] ?? '') : (string)$mod;
                        if ($modName !== '') {
                            $candidates[strtolower($modName)] = strtolower($modName) . '_' . $alterHook;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        foreach ($candidates as $func) {
            if (function_exists($func)) {
                try {
                    $func($data, $context);
                } catch (\Throwable $e) {
                    error_log("[Hook Alter Function Exception] ({$func}): " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get all currently registered hooks and their listener counts.
     *
     * @return array<string,int>
     */
    public static function getRegisteredHooks(): array
    {
        $summary = [];
        foreach (self::$hooks as $hook => $listeners) {
            $summary[$hook] = count($listeners);
        }
        return $summary;
    }

    /**
     * Reset all registered hooks (primarily for testing and worker daemon isolation).
     */
    public static function reset(): void
    {
        self::$hooks = [];
    }
}

// Alias to \SPP\Core\Hook if referenced
if (!class_exists('\SPP\Core\Hook', false)) {
    class_alias(\SPP\Hook::class, '\SPP\Core\Hook');
}
