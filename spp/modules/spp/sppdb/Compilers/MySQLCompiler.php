<?php

namespace SPPMod\SPPDB\Compilers;

use SPPMod\SPPDB\SppEntityQuery;

/**
 * Class MySQLCompiler
 * Compiles SPPEntityQuery AST into MySQL dialect.
 */
class MySQLCompiler implements CompilerInterface
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

        $lockSql = $this->compileLock($query);
        if ($lockSql) {
            $sql .= " " . $lockSql;
        }

        return ['sql' => trim($sql), 'values' => $values];
    }

    public function compileLock(SppEntityQuery $query): string
    {
        $mode = $query->getLockMode();
        if ($mode === 'update') {
            return 'FOR UPDATE';
        } elseif ($mode === 'shared') {
            return 'LOCK IN SHARE MODE';
        }
        return '';
    }

    public function escapeIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }
        $parts = explode('.', $identifier);
        $escaped = array_map(function($part) {
            return $part === '*' ? '*' : '`' . str_replace('`', '``', $part) . '`';
        }, $parts);
        return implode('.', $escaped);
    }

    public function compileCreateTable(string $table, array $columns): string
    {
        $defs = [];
        $constraints = [];
        foreach ($columns as $colName => $colDef) {
            if (strtoupper($colName) === 'PRIMARY KEY') {
                $constraints[] = "PRIMARY KEY $colDef";
            } elseif (strtoupper($colName) === 'UNIQUE') {
                $constraints[] = "UNIQUE $colDef";
            } else {
                $safeColName = '`' . str_replace('`', '``', $colName) . '`';
                $defs[] = "$safeColName $colDef";
            }
        }
        $allDefs = array_merge($defs, $constraints);
        $safeTableName = '`' . str_replace('`', '``', $table) . '`';
        return "CREATE TABLE {$safeTableName} (" . implode(', ', $allDefs) . ")";
    }

    public function compileAddColumns(string $table, array $columns): array
    {
        $safeTableName = '`' . str_replace('`', '``', $table) . '`';
        $statements = [];
        foreach ($columns as $col => $type) {
            if (strtoupper($col) === 'PRIMARY KEY' || strtoupper($col) === 'UNIQUE') {
                continue;
            }
            $safeCol = '`' . str_replace('`', '``', $col) . '`';
            $statements[] = "ALTER TABLE {$safeTableName} ADD {$safeCol} {$type}";
        }
        return $statements;
    }
}
