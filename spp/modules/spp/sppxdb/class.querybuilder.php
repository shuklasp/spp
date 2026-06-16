<?php

namespace SPPMod\SPPXDB;

use Exception;
require_once __DIR__ . '/class.paginator.php';

/**
 * Class QueryBuilder
 * Provides a fluent interface for SPP_XDB.
 */
class QueryBuilder
{
    protected $db;
    protected $tableName;
    protected $wheres = [];
    protected $orders = [];
    protected $limit = null;
    protected $offset = null;
    protected $distinct = false;
    protected $modelClass = null;
    protected $softDeleteState = 'hide'; // 'hide', 'with', 'only'
    protected $with = [];

    public function __construct(SPP_XDB $db, $table)
    {
        $this->db = $db;
        $this->tableName = $table;
        // Connect automatically
        $this->db->connect($table);
    }

    public function asObject($className)
    {
        $this->modelClass = $className;
        return $this;
    }

    public function useSoftDeletes()
    {
        $this->softDeleteState = 'hide';
        return $this;
    }

    public function withTrashed()
    {
        $this->softDeleteState = 'with';
        return $this;
    }

    public function onlyTrashed()
    {
        $this->softDeleteState = 'only';
        return $this;
    }

    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        $this->with = array_merge($this->with, $relations);
        return $this;
    }

    public function search($keywords, array $columns)
    {
        if (empty($keywords) || empty($columns)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'search',
            'columns' => $columns,
            'keywords' => $keywords,
            'boolean' => 'AND'
        ];
        return $this;
    }

    public function where($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $column = preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN', 'IS NULL', 'IS NOT NULL'])) {
            $operator = '=';
        }
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    public function orWhere($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $column = preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN', 'IS NULL', 'IS NOT NULL'])) {
            $operator = '=';
        }
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'OR'];
        return $this;
    }

    public function whereIn($column, array $values)
    {
        $column = preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
        $this->wheres[] = ['column' => $column, 'operator' => 'IN', 'value' => $values, 'boolean' => 'AND'];
        return $this;
    }

    public function whereLike($column, $value)
    {
        $column = preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
        $this->wheres[] = ['column' => $column, 'operator' => 'LIKE', 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $column = preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = ['column' => $column, 'direction' => $direction];
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (int)$limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = (int)$offset;
        return $this;
    }

    public function paginate($perPage = 15, $currentPage = 1)
    {
        // Get total count
        $totalQb = clone $this;
        $total = $totalQb->count();

        // Apply limits
        $this->limit($perPage);
        $this->offset(($currentPage - 1) * $perPage);

        $data = $this->get();

        return new Paginator($data, $total, $perPage, $currentPage);
    }

    public function distinct()
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Executes the query and returns results.
     */
    public function get($fields = '*')
    {
        $sql = "SELECT " . ($this->distinct ? "DISTINCT " : "") . (is_array($fields) ? implode(', ', $fields) : $fields);
        $sql .= " FROM " . $this->tableName;

        $localWheres = $this->wheres;
        if ($this->softDeleteState === 'hide') {
            $localWheres[] = ['column' => 'deleted_at', 'operator' => 'IS NULL', 'value' => false, 'boolean' => 'AND'];
        } elseif ($this->softDeleteState === 'only') {
            $localWheres[] = ['column' => 'deleted_at', 'operator' => 'IS NOT NULL', 'value' => false, 'boolean' => 'AND'];
        }

        $params = [];
        if (!empty($localWheres)) {
            $sql .= " WHERE ";
            foreach ($localWheres as $i => $where) {
                if ($i > 0) {
                    $sql .= " " . $where['boolean'] . " ";
                }

                if (isset($where['type']) && $where['type'] === 'search') {
                    $cols = $where['columns'];
                    $kw = $where['keywords'];
                    $searchParts = [];
                    foreach ($cols as $col) {
                        $searchParts[] = "$col LIKE ?";
                        $params[] = "%$kw%";
                    }
                    $sql .= "(" . implode(" OR ", $searchParts) . ")";
                } elseif ($where['operator'] === 'IN') {
                    $inVals = array_map(function ($v) {
                        return "'" . addslashes($v) . "'";
                    }, $where['value']);
                    $sql .= $where['column'] . " IN (" . implode(',', $inVals) . ")";
                } elseif ($where['operator'] === 'IS NULL' || $where['operator'] === 'IS NOT NULL') {
                    $sql .= $where['column'] . " " . $where['operator'];
                } else {
                    $sql .= $where['column'] . " " . $where['operator'] . " ?";
                    $params[] = $where['value'];
                }
            }
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY ";
            foreach ($this->orders as $i => $order) {
                if ($i > 0) {
                    $sql .= ", ";
                }
                $sql .= $order['column'] . " " . $order['direction'];
            }
        }

        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET " . $this->offset;
        }

        $results = $this->db->querySQL($sql, $params);

        if ($this->modelClass && is_array($results)) {
            $hydrated = [];
            foreach ($results as $row) {
                $obj = new $this->modelClass();
                foreach ($row as $k => $v) {
                    $obj->$k = $v;
                }
                $hydrated[] = $obj;
            }

            // Execute Eager Loading
            if (!empty($this->with)) {
                $dummy = new $this->modelClass();
                foreach ($this->with as $relationName) {
                    if (method_exists($dummy, $relationName)) {
                        $rel = $dummy->$relationName();
                        $relatedClass = $rel['class'] ?? null;
                        if (!$relatedClass) continue;

                        $relatedDummy = new $relatedClass();
                        $relQb = new self($this->db, $relatedDummy->getTable() ?? $this->tableName);
                        $relQb->asObject($relatedClass);

                        if ($rel['type'] === 'hasMany' || $rel['type'] === 'hasOne') {
                            $localKey = $rel['localKey'];
                            $foreignKey = $rel['foreignKey'];
                            $ids = array_filter(array_unique(array_map(function($m) use ($localKey) { return $m->$localKey; }, $hydrated)));
                            if (!empty($ids)) {
                                $relModels = $relQb->whereIn($foreignKey, $ids)->get();
                                $grouped = [];
                                foreach ($relModels as $rm) {
                                    $fkVal = $rm->$foreignKey;
                                    if (!isset($grouped[$fkVal])) $grouped[$fkVal] = [];
                                    $grouped[$fkVal][] = $rm;
                                }
                                foreach ($hydrated as $m) {
                                    $idVal = $m->$localKey;
                                    $m->setRelation($relationName, $rel['type'] === 'hasOne' ? ($grouped[$idVal][0] ?? null) : ($grouped[$idVal] ?? []));
                                }
                            }
                        } elseif ($rel['type'] === 'belongsTo') {
                            $foreignKey = $rel['foreignKey'];
                            $ownerKey = $rel['ownerKey'];
                            $ids = array_filter(array_unique(array_map(function($m) use ($foreignKey) { return $m->$foreignKey; }, $hydrated)));
                            if (!empty($ids)) {
                                $relModels = $relQb->whereIn($ownerKey, $ids)->get();
                                $dict = [];
                                foreach ($relModels as $rm) {
                                    $dict[$rm->$ownerKey] = $rm;
                                }
                                foreach ($hydrated as $m) {
                                    $fkVal = $m->$foreignKey;
                                    $m->setRelation($relationName, $dict[$fkVal] ?? null);
                                }
                            }
                        }
                    }
                }
            }

            return $hydrated;
        }

        return $results;
    }

    public function first($fields = '*')
    {
        $this->limit(1);
        $results = $this->get($fields);
        return $results[0] ?? null;
    }

    public function count()
    {
        $res = $this->get("COUNT(*)");
        return $res[0]['COUNT(*)'] ?? 0;
    }

    public function sum($column)
    {
        $res = $this->get("SUM($column)");
        return $res[0]["SUM($column)"] ?? 0;
    }

    public function max($column)
    {
        $res = $this->get("MAX($column)");
        return $res[0]["MAX($column)"] ?? null;
    }

    public function insert(array $data)
    {
        return $this->db->insert($data);
    }

    public function update(array $data)
    {
        $localWheres = $this->wheres;
        if ($this->softDeleteState === 'hide') {
            $localWheres[] = ['column' => 'deleted_at', 'operator' => 'IS NULL', 'value' => false, 'boolean' => 'AND'];
        }

        $whereSql = "";
        $params = [];
        if (!empty($localWheres)) {
            foreach ($localWheres as $i => $where) {
                if ($i > 0) {
                    $whereSql .= " " . $where['boolean'] . " ";
                }
                if ($where['operator'] === 'IS NULL' || $where['operator'] === 'IS NOT NULL') {
                    $whereSql .= $where['column'] . " " . $where['operator'];
                } else {
                    $whereSql .= $where['column'] . " " . $where['operator'] . " ?";
                    $params[] = $where['value'];
                }
            }
        }
        return $this->db->update($data, $whereSql, $params);
    }

    public function delete()
    {
        $localWheres = $this->wheres;
        if ($this->softDeleteState === 'hide') {
            $localWheres[] = ['column' => 'deleted_at', 'operator' => 'IS NULL', 'value' => false, 'boolean' => 'AND'];
        }

        $whereSql = "";
        $params = [];
        if (!empty($localWheres)) {
            foreach ($localWheres as $i => $where) {
                if ($i > 0) {
                    $whereSql .= " " . $where['boolean'] . " ";
                }
                if ($where['operator'] === 'IS NULL' || $where['operator'] === 'IS NOT NULL') {
                    $whereSql .= $where['column'] . " " . $where['operator'];
                } else {
                    $whereSql .= $where['column'] . " " . $where['operator'] . " ?";
                    $params[] = $where['value'];
                }
            }
        }
        return $this->db->delete($whereSql, $params);
    }

    public function softDelete()
    {
        return $this->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function __call($name, $arguments)
    {
        if ($this->modelClass && method_exists($this->modelClass, 'scope' . ucfirst($name))) {
            $dummy = new $this->modelClass();
            $method = 'scope' . ucfirst($name);
            array_unshift($arguments, $this);
            call_user_func_array([$dummy, $method], $arguments);
            return $this;
        }
        throw new Exception("Method {$name} not found on QueryBuilder.");
    }
}
