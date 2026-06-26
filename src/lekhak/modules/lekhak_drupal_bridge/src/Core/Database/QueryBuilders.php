<?php
namespace Lekhak\Modules\LekhakDrupalBridge\Core\Database;

use SPPMod\SPPDB\SPPDB;

class DatabaseWrapper
{
    public function query($query, array $args = [], $options = [])
    {
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            $db = new SPPDB();
            return $db->execute_query($query, $args);
        }
        return null;
    }

    public function select($table, $alias = null, array $options = [])
    {
        return new SelectQueryBuilder($table, $alias, $options);
    }

    public function insert($table, array $options = [])
    {
        return new InsertQueryBuilder($table, $options);
    }

    public function update($table, array $options = [])
    {
        return new UpdateQueryBuilder($table, $options);
    }

    public function delete($table, array $options = [])
    {
        return new DeleteQueryBuilder($table, $options);
    }
}

abstract class BaseQueryBuilder
{
    public function __clone()
    {
    }
}

class SelectQueryBuilder
{
    protected $table;
    protected $alias;
    protected $fields = [];
    protected $conditions = [];
    protected $args = [];

    public function __construct($table, $alias, $options)
    {
        $this->table = $table;
        $this->alias = $alias;
    }

    public function fields($table_alias, array $fields = [])
    {
        if (empty($fields)) {
            $this->fields[] = $table_alias . '.*';
        } else {
            foreach ($fields as $field) {
                $this->fields[] = $table_alias . '.' . $field;
            }
        }
        return $this;
    }

    public function condition($field, $value = null, $operator = '=')
    {
        $this->conditions[] = "$field $operator ?";
        $this->args[] = $value;
        return $this;
    }

    public function execute()
    {
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            $db = new SPPDB();
            $fieldsSql = empty($this->fields) ? '*' : implode(', ', $this->fields);
            $sql = "SELECT $fieldsSql FROM {$this->table} {$this->alias}";
            if (!empty($this->conditions)) {
                $sql .= " WHERE " . implode(' AND ', $this->conditions);
            }
            $result = $db->execute_query($sql, $this->args);
            // Mock a result set
            return new StatementMock($result);
        }
        return new StatementMock([]);
    }
}

class InsertQueryBuilder
{
    protected $table;
    protected $fields = [];

    public function __construct($table, $options)
    {
        $this->table = $table;
    }

    public function fields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    public function execute()
    {
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            $db = new SPPDB();
            $db->insertValues($this->table, $this->fields);
            return true;
        }
        return false;
    }
}

class UpdateQueryBuilder
{
    protected $table;
    protected $fields = [];
    protected $conditions = [];
    protected $args = [];

    public function __construct($table, $options)
    {
        $this->table = $table;
    }

    public function fields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    public function condition($field, $value = null, $operator = '=')
    {
        $this->conditions[] = "$field $operator ?";
        $this->args[] = $value;
        return $this;
    }

    public function execute()
    {
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            $db = new SPPDB();
            $where = empty($this->conditions) ? "1=1" : implode(' AND ', $this->conditions);
            // SPPDB updateValues is (table, columns/data, where, args)
            // It expects columns array or assoc array. If assoc, it combines it properly.
            $db->updateValues($this->table, $this->fields, $where, $this->args);
            return clone $this;
        }
        return false;
    }
}

class DeleteQueryBuilder
{
    protected $table;
    protected $conditions = [];
    protected $args = [];

    public function __construct($table, $options)
    {
        $this->table = $table;
    }

    public function condition($field, $value = null, $operator = '=')
    {
        $this->conditions[] = "$field $operator ?";
        $this->args[] = $value;
        return $this;
    }

    public function execute()
    {
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            $db = new SPPDB();
            $where = empty($this->conditions) ? "1=1" : implode(' AND ', $this->conditions);
            $sql = "DELETE FROM {$this->table} WHERE $where";
            $db->execute_query($sql, $this->args);
            return clone $this;
        }
        return false;
    }
}

class StatementMock implements \IteratorAggregate
{
    protected $data;
    public function __construct($data)
    {
        $this->data = $data;
    }
    public function fetchAll()
    {
        return json_decode(json_encode($this->data), false); // return objects
    }
    public function fetchAllAssoc($key)
    {
        $res = [];
        foreach ($this->fetchAll() as $row) {
            $res[$row->{$key}] = $row;
        }
        return $res;
    }
    public function fetchAssoc()
    {
        return empty($this->data) ? false : (array) $this->data[0];
    }
    public function fetchObject()
    {
        return empty($this->data) ? false : (object) $this->data[0];
    }
    public function fetchField()
    {
        if (empty($this->data))
            return false;
        $row = (array) $this->data[0];
        return reset($row);
    }
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->fetchAll());
    }
}
