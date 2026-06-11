<?php

namespace SPP\Core\Interfaces;

/**
 * Interface SharedStorageInterface
 * 
 * Defines the contract for distributed or local shared configuration storage
 * within the SPP Registry.
 */
interface SharedStorageInterface
{
    /**
     * Save the shared configuration data to the underlying storage.
     *
     * @param array $data The shared state data.
     * @return void
     */
    public function save(array $data): void;

    /**
     * Load the shared configuration data from the underlying storage.
     *
     * @return array
     */
    public function load(): array;
}
