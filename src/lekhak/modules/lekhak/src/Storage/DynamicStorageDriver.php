<?php
namespace SPPMod\Lekhak\Storage;

use SPPMod\Lekhak\Core\LekhakNode;
use SPPMod\SPPDB\SPPDB;

/**
 * Class DynamicStorageDriver
 * Implements Drupal-style field storage (Table-per-field).
 */
class DynamicStorageDriver
{
    protected SPPDB $db;

    public function __construct()
    {
        $this->db = new SPPDB();
    }

    public function save(LekhakNode $node)
    {
        $isNew = $node->getId() === null;
        
        // 1. Save Base Data (to lek_nodes or similar)
        // For simplicity in this v1, we use the main table defined in entity YAML
        $nodeTable = $node->getTable();
        $baseAttributes = ['title', 'status', 'langcode', 'translation_id', 'created', 'changed', 'author_id'];
        
        // We'll extract only base attributes for the main table
        // and send the rest to field tables.
        
        // This is a simplified version of the logic
        $id = $node->save(false); // Call with false to skip strategy recursion
        
        // 2. Save Complex Fields (Dynamic tables)
        $fields = $node->getAttributes();
        foreach ($fields as $fieldName => $type) {
            if (in_array($fieldName, $baseAttributes)) continue;
            
            $this->saveField($node, $fieldName, $node->get($fieldName));
        }

        return $id;
    }

    protected function saveField(LekhakNode $node, string $fieldName, $value)
    {
        $table = 'lek_field_' . $fieldName;
        
        // Ensure table exists
        if (!$this->db->tableExists($table)) {
            $this->db->exec_squery("CREATE TABLE %tab% (
                entity_id BIGINT,
                bundle VARCHAR(50),
                langcode VARCHAR(10),
                value LONGTEXT,
                PRIMARY KEY (entity_id, bundle, langcode)
            )", $table);
        }

        // Upsert logic
        $this->db->exec_squery("REPLACE INTO %tab% (entity_id, bundle, langcode, value) VALUES (%d, %s, %s, %s)", 
            $table, $node->getId(), get_class($node), $node->get('langcode') ?? 'en', $value);
    }

    public function load(LekhakNode $node, $id)
    {
        // 1. Load Base Data
        // (Handled by SPPEntity::load internally via parent call if we structured it that way)
        
        // 2. Load Dynamic Fields
        $fields = $node->getAttributes();
        foreach ($fields as $fieldName => $type) {
            if (strpos($fieldName, 'field_') === 0) { // Naming convention for dynamic fields
                $node->set($fieldName, $this->loadField($node, $id, $fieldName));
            }
        }
    }

    protected function loadField(LekhakNode $node, $id, string $fieldName)
    {
        $table = 'lek_field_' . $fieldName;
        if (!$this->db->tableExists($table)) return null;

        $res = $this->db->exec_squery("SELECT value FROM %tab% WHERE entity_id = %d", $table, $id);
        return $res[0]['value'] ?? null;
    }
}
