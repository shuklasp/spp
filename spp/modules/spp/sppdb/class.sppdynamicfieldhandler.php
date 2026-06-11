<?php

namespace SPPMod\SPPEntity;

/**
 * Class SppDynamicFieldHandler
 * Handles polymorphic dynamic fields for entities.
 */
class SppDynamicFieldHandler
{
    /**
     * Creates the polymorphic field storage table if it doesn't exist.
     */
    public static function install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_fields');
        if (!$db->tableExists($table)) {
            $sql = "CREATE TABLE {$table} (
                entity_type VARCHAR(255) NOT NULL,
                entity_id VARCHAR(255) NOT NULL,
                bundle VARCHAR(100) DEFAULT 'default',
                field_name VARCHAR(100) NOT NULL,
                value_string VARCHAR(255),
                value_text TEXT,
                value_int INT,
                value_decimal DECIMAL(10,2),
                PRIMARY KEY (entity_type, entity_id, field_name)
            )";
            $db->exec($sql);
            if (class_exists('\\SPPMod\\SPPLogger\\SPP_Logger')) {
                \SPPMod\SPPLogger\SPP_Logger::info("Created polymorphic dynamic fields table: {$table}");
            }
        }
    }

    /**
     * Batch loads dynamic fields for a list of entities.
     */
    public static function loadFields(array $entities)
    {
        if (empty($entities)) {
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_fields');
        if (!$db->tableExists($table)) {
            return;
        }

        // Group by entity type
        $byType = [];
        foreach ($entities as $entity) {
            if (!$entity->getId()) {
                continue;
            }
            $type = get_class($entity);
            if (!isset($byType[$type])) {
                $byType[$type] = [];
            }
            $byType[$type][] = $entity->getId();
        }

        foreach ($byType as $type => $ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM {$table} WHERE entity_type = ? AND entity_id IN ($placeholders)";
            $params = array_merge([$type], $ids);

            $results = $db->exec_squery($sql, $table, $params);

            $fieldData = [];
            foreach ($results as $row) {
                $fieldData[$row['entity_id']][$row['field_name']] = self::getActualValue($row);
            }

            foreach ($entities as $entity) {
                if (get_class($entity) === $type && isset($fieldData[$entity->getId()])) {
                    if (method_exists($entity, '_setDynamicFields')) {
                        $entity->_setDynamicFields($fieldData[$entity->getId()]);
                    }
                }
            }
        }
    }

    /**
     * Saves dynamic fields for a specific entity.
     */
    public static function saveFields($entity, array $dynamicFields)
    {
        if (empty($dynamicFields)) {
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_fields');
        if (!$db->tableExists($table)) {
            self::install();
        }

        $type = get_class($entity);
        $id = $entity->getId();
        $bundle = $entity->attributeExists('bundle') ? ($entity->get('bundle') ?: 'default') : 'default';

        foreach ($dynamicFields as $fieldName => $value) {
            $col = self::getValueColumn($value);

            // Check if exists
            $checkSql = "SELECT 1 FROM {$table} WHERE entity_type = ? AND entity_id = ? AND field_name = ?";
            $exists = $db->exec_squery($checkSql, $table, [$type, $id, $fieldName]);

            if (!empty($exists)) {
                $sql = "UPDATE {$table} SET value_string = NULL, value_text = NULL, value_int = NULL, value_decimal = NULL, {$col} = ?, bundle = ? WHERE entity_type = ? AND entity_id = ? AND field_name = ?";
                $db->exec_squery($sql, $table, [$value, $bundle, $type, $id, $fieldName]);
            } else {
                $sql = "INSERT INTO {$table} (entity_type, entity_id, bundle, field_name, {$col}) VALUES (?, ?, ?, ?, ?)";
                $db->exec_squery($sql, $table, [$type, $id, $bundle, $fieldName, $value]);
            }
        }
    }

    /**
     * Deletes all dynamic fields associated with an entity.
     */
    public static function deleteFields($entity)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_fields');
        if (!$db->tableExists($table)) {
            return;
        }

        $type = get_class($entity);
        $id = $entity->getId();

        $sql = "DELETE FROM {$table} WHERE entity_type = ? AND entity_id = ?";
        $db->exec_squery($sql, $table, [$type, $id]);
    }

    /**
     * Extracts the actual polymorphic value from a database row.
     */
    private static function getActualValue($row)
    {
        if ($row['value_int'] !== null) {
            return (int)$row['value_int'];
        }
        if ($row['value_decimal'] !== null) {
            return (float)$row['value_decimal'];
        }
        if ($row['value_string'] !== null) {
            return $row['value_string'];
        }
        if ($row['value_text'] !== null) {
            return $row['value_text'];
        }
        return null;
    }

    /**
     * Determines which database column to use for a given value type.
     */
    private static function getValueColumn($value)
    {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value))) {
            return 'value_int';
        }
        if (is_float($value) || (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value))) {
            return 'value_decimal';
        }
        if (is_string($value) && strlen($value) > 255) {
            return 'value_text';
        }
        return 'value_string';
    }
}
