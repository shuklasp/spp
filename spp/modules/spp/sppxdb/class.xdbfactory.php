<?php

namespace SPPMod\SPPXDB;

use Exception;

/**
 * Class XDB_Factory
 * Generates dummy data for SPP_XDB based on defined blueprints.
 */
class XDB_Factory
{
    protected static $blueprints = [];
    protected static $db = null;

    /**
     * Define a blueprint for a given table or model.
     *
     * @param string $name Model class or table name
     * @param callable $callback Function that returns an array of dummy data.
     */
    public static function define($name, callable $callback)
    {
        self::$blueprints[$name] = $callback;
    }

    /**
     * Create multiple records based on a blueprint.
     *
     * @param string $name Model class or table name
     * @param int $count Number of records to create
     * @param SPP_XDB|null $db Optional database instance.
     * @return array The created records
     */
    public static function create($name, $count = 1, SPP_XDB $db = null)
    {
        if (!isset(self::$blueprints[$name])) {
            throw new Exception("No factory blueprint defined for: " . $name);
        }

        if ($db === null) {
            if (self::$db === null) {
                self::$db = new SPP_XDB();
            }
            $db = self::$db;
        }

        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $data = call_user_func(self::$blueprints[$name]);
            
            // If the name is a class, insert into its table
            if (class_exists($name) && is_subclass_of($name, SPP_XDB_Model::class)) {
                $model = new $name();
                $table = $model->getTable();
                $db->connect($table)->insert($data);
            } else {
                // Assume it's a table name
                $db->connect($name)->insert($data);
            }
            $records[] = $data;
        }

        return $records;
    }
}
