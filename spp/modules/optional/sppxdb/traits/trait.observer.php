<?php

namespace SPPMod\SPPXDB;

/**
 * Global registry for observers to ensure both the Facade and Engines share the same list.
 */
class XDB_ObserverRegistry
{
    public static $observers = [];
}

/**
 * Trait XDB_Observer
 * Provides lifecycle event hooks (creating, created, updating, updated, deleting, deleted)
 */
trait XDB_Observer
{
    /**
     * Register an observer class for a specific table.
     */
    public static function observe($tableName, $observerClass)
    {
        if (!isset(XDB_ObserverRegistry::$observers[$tableName])) {
            XDB_ObserverRegistry::$observers[$tableName] = [];
        }
        XDB_ObserverRegistry::$observers[$tableName][] = $observerClass;
    }

    /**
     * Dispatch an event to all registered observers.
     * Returns false if an observer halts the operation.
     */
    protected function fireObserverEvent($event, &$data)
    {
        $tableName = $this->tableName ?? null;
        if (!$tableName || !isset(XDB_ObserverRegistry::$observers[$tableName])) {
            return true;
        }

        foreach (XDB_ObserverRegistry::$observers[$tableName] as $observerClass) {
            $observer = new $observerClass();
            if (method_exists($observer, $event)) {
                $result = $observer->$event($data);
                if ($result === false) {
                    return false;
                }
            }
        }
        return true;
    }
}
