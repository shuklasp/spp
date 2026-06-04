<?php

namespace SPP\Core;

/**
 * Class EventManager
 * A highly performant, lightweight array-based hook dispatcher for the framework.
 */
class EventManager
{
    private static array $listeners = [];

    /**
     * Register a callback listener for a specific event hook.
     *
     * @param string $event Event hook identifier (e.g. 'entity:before_save')
     * @param callable $callback Listener callback execution
     */
    public static function listen(string $event, callable $callback): void
    {
        self::$listeners[$event][] = $callback;
    }

    /**
     * Trigger all registered listeners for a given event, passing data by reference.
     *
     * @param string $event Event hook identifier
     * @param mixed $data Data context passed by reference
     */
    public static function trigger(string $event, &$data = null): void
    {
        if (empty(self::$listeners[$event])) {
            return;
        }

        foreach (self::$listeners[$event] as $callback) {
            $callback($data);
        }
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
