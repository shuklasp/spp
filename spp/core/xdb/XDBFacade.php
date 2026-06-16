<?php

namespace SPP\Core\XDB;

/**
 * Class XDBFacade
 *
 * Part of the Phase 2 Decoupling Strategy.
 * This facade provides a clean, modern interface to the legacy SPPXDB engine.
 * It isolates the monolithic database mapping logic so it can be refactored
 * internally without affecting external dependencies.
 */
class XDBFacade
{
    private static $provider = null;

    public static function setProvider($providerClass): void
    {
        self::$provider = $providerClass;
    }

    /**
     * Initializes an XDB Entity by name.
     * Delegates to the registered provider engine.
     */
    public static function getEntity(string $entityName)
    {
        if (self::$provider !== null && class_exists(self::$provider)) {
            $class = self::$provider;
            return new $class($entityName);
        }

        throw new \Exception("XDB provider module is not loaded or available.");
    }

    /**
     * Find a record by its primary ID.
     */
    public static function find(string $entityName, $id)
    {
        $xdb = self::getEntity($entityName);
        return $xdb->get($id);
    }
}
