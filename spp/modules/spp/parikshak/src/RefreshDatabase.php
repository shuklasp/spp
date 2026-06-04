<?php
namespace SPPMod\Parikshak;

/**
 * Trait RefreshDatabase
 * Wraps tests in database transactions to roll back changes automatically.
 */
trait RefreshDatabase
{
    protected function beginDatabaseTransaction(): void
    {
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            $db = new \SPPMod\SPPDB\SPPDB();
            // Start transaction based on the active driver
            if (method_exists($db->getAdapter(), 'beginTransaction')) {
                $db->getAdapter()->beginTransaction();
            }
        }
    }

    protected function rollbackDatabaseTransaction(): void
    {
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            $db = new \SPPMod\SPPDB\SPPDB();
            if (method_exists($db->getAdapter(), 'rollBack')) {
                $db->getAdapter()->rollBack();
            }
        }
    }
}
