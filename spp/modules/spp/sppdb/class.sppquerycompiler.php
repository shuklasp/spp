<?php

namespace SPPMod\SPPDB;

use SPPMod\SPPDB\SppEntityQuery;

/**
 * Class SPPQueryCompiler
 * Compiles SPPEntityQuery AST into specific SQL dialects.
 */
class SPPQueryCompiler
{
    /**
     * Compile the AST from the query into a SQL statement and bindings.
     *
     * @param SppEntityQuery $query
     * @param string $baseTable
     * @param string $entityClass
     * @return array ['sql' => string, 'values' => array]
     */
    public function compile(SppEntityQuery $query, string $baseTable, string $entityClass): array
    {
        $dynamicTable = \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_fields');
        $sql = "SELECT base.* FROM {$baseTable} base ";

        $joinIndex = 0;
        $values = [];
        $whereClauses = [];

        // Base table conditions
        foreach ($query->getConditions() as $cond) {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $cond['field']);
            $operator = $cond['operator'];

            if (in_array($operator, ['IN', 'NOT IN']) && is_array($cond['value'])) {
                $placeholders = implode(',', array_fill(0, count($cond['value']), '?'));
                $whereClauses[] = "base.{$field} {$operator} ({$placeholders})";
                $values = array_merge($values, $cond['value']);
            } else {
                if ($cond['value'] === null && in_array($operator, ['IS', 'IS NOT'])) {
                    $whereClauses[] = "base.{$field} {$operator} NULL";
                } else {
                    $whereClauses[] = "base.{$field} {$operator} ?";
                    $values[] = $cond['value'];
                }
            }
        }

        // Dynamic table conditions (joins)
        if (!empty($query->getDynamicConditions()) && class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            foreach ($query->getDynamicConditions() as $cond) {
                $joinAlias = "dyn" . $joinIndex;

                $field = $cond['field'];
                $value = $cond['value'];
                $operator = $cond['operator'];

                // Determine value column based on type
                $col = is_int($value) ? 'value_int' : (is_float($value) ? 'value_decimal' : 'value_string');

                $sql .= " INNER JOIN {$dynamicTable} {$joinAlias} ON base.id = {$joinAlias}.entity_id AND {$joinAlias}.entity_type = ? AND {$joinAlias}.field_name = ? ";
                $values[] = ltrim($entityClass, '\\');
                $values[] = $field;

                if (in_array($operator, ['IN', 'NOT IN']) && is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $whereClauses[] = "{$joinAlias}.{$col} {$operator} ({$placeholders})";
                    $values = array_merge($values, $value);
                } else {
                    if ($value === null && in_array($operator, ['IS', 'IS NOT'])) {
                        $whereClauses[] = "{$joinAlias}.{$col} {$operator} NULL";
                    } else {
                        $whereClauses[] = "{$joinAlias}.{$col} {$operator} ?";
                        $values[] = $value;
                    }
                }

                $joinIndex++;
            }
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if ($query->getSort()) {
            $sql .= " ORDER BY base." . preg_replace('/[^a-zA-Z0-9_\s]/', '', $query->getSort());
        }

        if ($query->getLimit()) {
            $sql .= " LIMIT " . (int) $query->getLimit();
            if ($query->getOffset()) {
                $sql .= " OFFSET " . (int) $query->getOffset();
            }
        }

        return ['sql' => $sql, 'values' => $values];
    }
}
