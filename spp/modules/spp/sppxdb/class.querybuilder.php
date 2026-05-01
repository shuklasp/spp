<?php

namespace SPPMod\SPPXDB;

use Exception;

/**
 * Class QueryBuilder
 * Provides a fluent interface for SPP_XDB.
 */
class QueryBuilder {
    protected $db;
    protected $tableName;
    protected $wheres = [];
    protected $orders = [];
    protected $limit = null;
    protected $distinct = false;

    public function __construct(SPP_XDB $db, $table) {
        $this->db = $db;
        $this->tableName = $table;
        // Connect automatically
        $this->db->connect($table);
    }

    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    public function orWhere($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'OR'];
        return $this;
    }

    public function whereIn($column, array $values) {
        $this->wheres[] = ['column' => $column, 'operator' => 'IN', 'value' => $values, 'boolean' => 'AND'];
        return $this;
    }

    public function whereLike($column, $value) {
        $this->wheres[] = ['column' => $column, 'operator' => 'LIKE', 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    public function orderBy($column, $direction = 'ASC') {
        $this->orders[] = ['column' => $column, 'direction' => strtoupper($direction)];
        return $this;
    }

    public function limit($limit) {
        $this->limit = (int)$limit;
        return $this;
    }

    public function distinct() {
        $this->distinct = true;
        return $this;
    }

    /**
     * Executes the query and returns results.
     */
    public function get($fields = '*') {
        $sql = "SELECT " . ($this->distinct ? "DISTINCT " : "") . (is_array($fields) ? implode(', ', $fields) : $fields);
        $sql .= " FROM " . $this->tableName;
        
        $params = [];
        if (!empty($this->wheres)) {
            $sql .= " WHERE ";
            foreach ($this->wheres as $i => $where) {
                if ($i > 0) $sql .= " " . $where['boolean'] . " ";
                
                if ($where['operator'] === 'IN') {
                    $inVals = array_map(function($v) { return "'" . addslashes($v) . "'"; }, $where['value']);
                    $sql .= $where['column'] . " IN (" . implode(',', $inVals) . ")";
                } else {
                    $sql .= $where['column'] . " " . $where['operator'] . " ?";
                    $params[] = $where['value'];
                }
            }
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY ";
            foreach ($this->orders as $i => $order) {
                if ($i > 0) $sql .= ", ";
                $sql .= $order['column'] . " " . $order['direction'];
            }
        }

        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        return $this->db->querySQL($sql, $params);
    }

    public function first($fields = '*') {
        $this->limit(1);
        $results = $this->get($fields);
        return $results[0] ?? null;
    }

    public function count() {
        $res = $this->get("COUNT(*)");
        return $res[0]['COUNT(*)'] ?? 0;
    }

    public function sum($column) {
        $res = $this->get("SUM($column)");
        return $res[0]["SUM($column)"] ?? 0;
    }

    public function max($column) {
        $res = $this->get("MAX($column)");
        return $res[0]["MAX($column)"] ?? null;
    }

    public function insert(array $data) {
        return $this->db->insert($data);
    }

    public function update(array $data) {
        // This is tricky because querySQL doesn't easily support UPDATE with complex WHERE from Builder yet
        // But we can translate it.
        $whereSql = "";
        $params = [];
        if (!empty($this->wheres)) {
            foreach ($this->wheres as $i => $where) {
                if ($i > 0) $whereSql .= " " . $where['boolean'] . " ";
                $whereSql .= $where['column'] . " " . $where['operator'] . " ?";
                $params[] = $where['value'];
            }
        }
        return $this->db->update($data, $whereSql, $params);
    }

    public function delete() {
        $whereSql = "";
        $params = [];
        if (!empty($this->wheres)) {
            foreach ($this->wheres as $i => $where) {
                if ($i > 0) $whereSql .= " " . $where['boolean'] . " ";
                $whereSql .= $where['column'] . " " . $where['operator'] . " ?";
                $params[] = $where['value'];
            }
        }
        return $this->db->delete($whereSql, $params);
    }
}
