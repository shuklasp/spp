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
            'bundle' => 'varchar(50)',
            'status' => 'varchar(20)',
            'langcode' => 'varchar(10)',
            'translation_id' => 'bigint',
            'author_id' => 'bigint',
            'created' => 'datetime',
            'changed' => 'datetime'
        ];
        $this->db->add_columns($table, $baseColumns);

        // 3. Ensure Field Tables for dynamic attributes
        // We fetch registered fields for this bundle
        $bundle = 'page'; // Default or from instance if we had one
        // In this static context, we might need to be careful or just ensure all fields for all bundles
        
        $fieldsTable = \SPPMod\SPPDB\SPPDB::sppTable('fields');
        if ($this->db->tableExists($fieldsTable)) {
            $fields = $this->db->execute_query("SELECT field_name, type FROM {$fieldsTable}");
            foreach ($fields as $f) {
                $name = $f['field_name'];
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
}
