<?php

namespace SPP;

class DB
{
    private static $provider = null;

    /**
     * Set the global database provider dynamically.
     */
    public static function setProvider($provider): void
    {
        self::$provider = $provider;
    }

    /**
     * Get the registered database provider instance.
     */
    public static function getInstance()
    {
        if (self::$provider === null) {
            throw new \Exception("Database provider not registered.");
        }
        return self::$provider;
    }

    /**
     * Get the registered database provider instance (alias for getInstance).
     */
    public static function getProvider()
    {
        return self::getInstance();
    }

    /**
     * Get properly prefixed table name.
     */
    public static function sppTable(string $tname): string
    {
        if (self::$provider !== null && method_exists(self::$provider, 'sppTable')) {
            return self::$provider::sppTable($tname);
        }
        $prefix = \SPP\App::getGlobalSettings('db_prefix') ?: 'spp_';
        return $prefix . $tname;
    }

    private static int $queryCount = 0;

    public static function getQueryCount(): int
    {
        return self::$queryCount;
    }

    public static function resetQueryCount(): void
    {
        self::$queryCount = 0;
    }

    public static function incrementQueryCount(int $amount = 1): void
    {
        self::$queryCount += $amount;
    }

    public static function query(string $query, array $values = [])
    {
        self::$queryCount++;
        return self::getInstance()->execute_query($query, $values);
    }

    public static function execute(string $sql, array $values = []): bool
    {
        self::$queryCount++;
        try {
            self::getInstance()->execute_query($sql, $values);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
