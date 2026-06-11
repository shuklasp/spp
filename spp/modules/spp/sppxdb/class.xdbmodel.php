<?php

namespace SPPMod\SPPXDB;

/**
 * Class SPP_XDB_Model
 * 
 * Abstract base class for Active Record ORM models in SPP_XDB.
 * Provides Attribute Casting and Relationship definition capabilities.
 */
abstract class SPP_XDB_Model
{
    /**
     * @var string The table associated with the model.
     */
    protected $table;

    /**
     * @var string The database connection name.
     */
    protected $dbName = 'default';

    /**
     * @var array The attributes that should be cast to native types.
     * Example: ['preferences' => 'array']
     */
    protected $casts = [];

    /**
     * @var array The model's actual attributes (row data).
     */
    protected $attributes = [];

    /**
     * @var array The loaded relationships.
     */
    protected $relations = [];

    /**
     * Magic setter for attributes to handle dynamic property assignment and casting.
     */
    public function __set($name, $value)
    {
        if (isset($this->casts[$name]) && $this->casts[$name] === 'array') {
            $this->attributes[$name] = is_array($value) ? json_encode($value) : $value;
        } else {
            $this->attributes[$name] = $value;
        }
    }

    /**
     * Magic getter to retrieve attributes or relationships, handling decryption or casting.
     */
    public function __get($name)
    {
        // 1. Check if it's a known attribute
        if (array_key_exists($name, $this->attributes)) {
            $val = $this->attributes[$name];
            if (isset($this->casts[$name]) && $this->casts[$name] === 'array') {
                return is_string($val) ? json_decode($val, true) : $val;
            }
            return $val;
        }

        // 2. Check if it's a loaded relationship
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        return null;
    }

    /**
     * Called by the QueryBuilder to attach eager-loaded models.
     */
    public function setRelation($name, $value)
    {
        $this->relations[$name] = $value;
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray()
    {
        $array = [];
        foreach ($this->attributes as $k => $v) {
            $array[$k] = $this->__get($k); // triggers casts
        }
        foreach ($this->relations as $k => $v) {
            if (is_array($v)) {
                $array[$k] = array_map(function ($item) {
                    return $item instanceof self ? $item->toArray() : $item;
                }, $v);
            } elseif ($v instanceof self) {
                $array[$k] = $v->toArray();
            } else {
                $array[$k] = $v;
            }
        }
        return $array;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getDbName()
    {
        return $this->dbName;
    }

    // --- Relationship Builders ---

    protected function hasOne($relatedClass, $foreignKey, $localKey = 'id')
    {
        return [
            'type' => 'hasOne',
            'class' => $relatedClass,
            'foreignKey' => $foreignKey,
            'localKey' => $localKey
        ];
    }

    protected function hasMany($relatedClass, $foreignKey, $localKey = 'id')
    {
        return [
            'type' => 'hasMany',
            'class' => $relatedClass,
            'foreignKey' => $foreignKey,
            'localKey' => $localKey
        ];
    }

    protected function belongsTo($relatedClass, $foreignKey, $ownerKey = 'id')
    {
        return [
            'type' => 'belongsTo',
            'class' => $relatedClass,
            'foreignKey' => $foreignKey,
            'ownerKey' => $ownerKey
        ];
    }
}
