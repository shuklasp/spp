<?php

namespace SPP\Core;

/**
 * Class EventManager
 * A highly performant, lightweight array-based hook dispatcher for the framework.
 */
class EventManager
{
    private static array $listeners = [];
    private static array $eventDefinitions = [];

    /**
     * Register an event definition.
     *
     * @param string $event
     * @param callable|string|null $defaultHandler
     * @param bool $overridable
     */
    public static function defineEvent(string $event, $defaultHandler = null, bool $overridable = false): void
    {
        self::$eventDefinitions[$event] = [
            'default_handler' => $defaultHandler,
            'overridable' => $overridable,
        ];
    }

    /**
     * Register a callback listener for a specific event hook.
     *
     * @param string $event Event hook identifier (e.g. 'entity:before_save')
     * @param callable|string $callback Listener callback execution
     * @param bool $isOverride Whether this listener overrides the default handler
     */
    public static function listen(string $event, $callback, bool $isOverride = false): void
    {
        if ($isOverride) {
            $def = self::$eventDefinitions[$event] ?? null;
            if ($def && !$def['overridable']) {
                throw new \SPP\SPPException("Event {$event} is not overridable.");
            }
            // If overriding, we clear existing listeners and replace them.
            self::$listeners[$event] = [$callback];
            return;
        }

        self::$listeners[$event][] = $callback;
    }

    /**
     * Trigger all registered listeners for a given event, passing data by reference.
     *
     * @param string $event Event hook identifier
     * @param mixed $data Data context passed by reference
     */
    public static function trigger(string $event, &$data = null): mixed
    {
        // Execute default handler if no listeners have overridden it and it exists
        $hasListeners = !empty(self::$listeners[$event]);
        $def = self::$eventDefinitions[$event] ?? null;
        
        $result = null;

        if (!$hasListeners && $def && $def['default_handler']) {
            $handler = $def['default_handler'];
            if (is_callable($handler)) {
                $result = $handler($data);
            } elseif (is_string($handler) && function_exists($handler)) {
                $result = call_user_func_array($handler, [&$data]);
            }
        }

        if ($hasListeners) {
            foreach (self::$listeners[$event] as $callback) {
                if (is_callable($callback)) {
                    $result = $callback($data);
                } elseif (is_string($callback) && function_exists($callback)) {
                    $result = call_user_func_array($callback, [&$data]);
                }
            }
        }

        return $result;
    }

    /**
     * Clear all listeners for a given event or clear everything.
     */
    public static function clear(?string $event = null): void
    {
        if ($event === null) {
            self::$listeners = [];
        } else {
            unset(self::$listeners[$event]);
        }
    }
}
