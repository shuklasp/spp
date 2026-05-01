<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPDB\SPPDB;

/**
 * Class StorageOrchestrator
 * Coordinates between different storage strategies and ensures schema integrity.
 */
class StorageOrchestrator
{
    protected SPPDB $db;

    public function __construct()
    {
        $this->db = new SPPDB();
    }

    /**
     * Ensures all necessary tables for a content type exist.
     */
    public function ensureSchema(string $entityClass): void
    {
        $strategy = $entityClass::getMetadata('storage_strategy', 'flat');
        
        if ($strategy === 'dynamic') {
            $this->ensureDynamicSchema($entityClass);
        } else {
            // Standard SPP install handles flat schema
            $entityClass::install();
        }
    }

    protected function ensureDynamicSchema(string $entityClass): void
    {
        // 1. Ensure Base Table
        $table = $entityClass::getMetadata('table');
        if (!$this->db->tableExists($table)) {
            $idField = $entityClass::getMetadata('id_field', 'id');
            $this->db->exec_squery("CREATE TABLE %tab% ($idField BIGINT PRIMARY KEY)", $table);
        }

        // 2. Ensure Base Columns
        $baseColumns = [
            'title' => 'varchar(255)',
            'status' => 'varchar(20)',
            'langcode' => 'varchar(10)',
            'translation_id' => 'bigint',
            'author_id' => 'bigint',
            'created' => 'datetime',
            'changed' => 'datetime'
        ];
        $this->db->add_columns($table, $baseColumns);

        // 3. Ensure Field Tables for dynamic attributes
        $attributes = $entityClass::getMetadata('attributes', []);
        foreach ($attributes as $name => $type) {
            if (isset($baseColumns[$name])) continue;
            
            $fieldTable = 'lek_field_' . $name;
            if (!$this->db->tableExists($fieldTable)) {
                $this->db->exec_squery("CREATE TABLE %tab% (
                    entity_id BIGINT,
                    bundle VARCHAR(50),
                    langcode VARCHAR(10),
                    value LONGTEXT,
                    PRIMARY KEY (entity_id, bundle, langcode)
                )", $fieldTable);
            }
        }
    }
}
