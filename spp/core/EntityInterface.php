<?php
declare(strict_types=1);

namespace SPP\Core;

/**
 * Interface EntityInterface
 * Represents a generic storable entity in SPP, decoupling core from SPPMod\SPPEntity\SPPEntity.
 */
interface EntityInterface
{
    /**
     * Delete the entity from storage.
     */
    public function delete();

    /**
     * Save the entity to storage.
     */
    public function save();

    /**
     * Get the associated database table for this entity.
     */
    public function getTable();

    /**
     * Get a metadata configuration value for this entity class.
     */
    public static function getMetadata(string $key);
}
