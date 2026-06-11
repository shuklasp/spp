<?php
namespace SPPMod\SppMigrate;

use SPP\Core\DB;

class SPPSchema {
    
    public static function create(string $table, callable $callback) {
        $blueprint = new SPPBlueprint($table);
        $callback($blueprint);
        
        $sql = $blueprint->buildSql();
        $db = DB::getInstance();
        if ($db) {
            $db->exec($sql);
        } else {
            error_log("SPPSchema: No database connection available to create table $table.");
        }
    }

    public static function dropIfExists(string $table) {
        $sql = "DROP TABLE IF EXISTS $table;";
        $db = DB::getInstance();
        if ($db) {
            $db->exec($sql);
        }
    }
}
