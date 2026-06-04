<?php

namespace SPP;

use SPPMod\SPPDB\SPPDB;

class DB
{
    private static ?SPPDB $instance = null;

    public static function getInstance(): SPPDB
    {
        if (self::$instance === null) {
            self::$instance = new SPPDB();
        }
        return self::$instance;
    }

    public static function query(string $query, array $values = [])
    {
        return self::getInstance()->execute_query($query, $values);
    }

    public static function execute(string $sql, array $values = []): bool
    {
        try {
            self::getInstance()->execute_query($sql, $values);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
