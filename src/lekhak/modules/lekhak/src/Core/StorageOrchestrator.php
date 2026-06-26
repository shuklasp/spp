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

        $this->ensureRevisionSchema();
        $this->ensureTranslationSchema();
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
            'changed' => 'datetime',
            'metadata' => 'longtext' // JSON column for Field API dynamic storage
        ];
        $this->db->add_columns($table, $baseColumns);

        // 3. (Deprecated) Field Tables for dynamic attributes
        // We now use the `metadata` JSON column instead of building a table per field.
    }

    public function ensureRevisionSchema(): void
    {
        $table = \SPPMod\SPPDB\SPPDB::sppTable('entity_revisions');
        if (!$this->db->tableExists($table)) {
            $this->db->exec_squery("CREATE TABLE %tab% (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(255),
                entity_id BIGINT,
                revision_timestamp DATETIME,
                author_id BIGINT,
                state_delta LONGTEXT
            )", $table);
        }
    }

    public function ensureTranslationSchema(): void
    {
        $table = \SPPMod\SPPDB\SPPDB::sppTable('lekhak_translations');
        if (!$this->db->tableExists($table)) {
            $this->db->exec_squery("CREATE TABLE %tab% (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(255),
                entity_id BIGINT,
                langcode VARCHAR(10),
                field_name VARCHAR(255),
                translation_value LONGTEXT,
                UNIQUE KEY (entity_type(100), entity_id, langcode, field_name(100))
            )", $table);
        }
    }
}
