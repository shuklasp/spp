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
    /**
     * Initializes an XDB Entity by name.
     * Delegates to the legacy SPPDB/SPPXDB engines.
     */
    public static function getEntity(string $entityName)
    {
        if (class_exists('\\SPPMod\\SPPXDB\\SPPXDB')) {
            return new \SPPMod\SPPXDB\SPPXDB($entityName);
        }

        throw new \Exception("SPPXDB legacy module is not loaded or available.");
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
