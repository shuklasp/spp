<?php
namespace App\ptable\Serv;

class LocalDataService
{
    private static $masterData = null;

    /**
     * Loads the master dataset into memory.
     */
    private static function loadData()
    {
        if (self::$masterData === null) {
            $phpPath = SPP_APP_DIR . '/src/ptable/data/master_elements.php';
            if (file_exists($phpPath)) {
                self::$masterData = require $phpPath;
            } else {
                $jsonPath = SPP_APP_DIR . '/src/ptable/data/master_elements.json';
                if (file_exists($jsonPath)) {
                    self::$masterData = json_decode(file_get_contents($jsonPath), true);
                } else {
                    self::$masterData = [];
                }
            }
        }
    }

    /**
     * Gets all local data for a given element symbol.
     */
    public static function getElementData(string $symbol): ?array
    {
        self::loadData();
        
        // The master data keys might be mixed case (like 'Zn'). We can do a case-insensitive lookup.
        foreach (self::$masterData as $key => $data) {
            if (strtolower($key) === strtolower($symbol)) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Formats electron configuration strings (e.g., 1s2 2s2) with HTML superscripts.
     */
    public static function formatElectronConfig(string $config): string
    {
        // Matches any letter (s, p, d, f) followed by numbers, and wraps the numbers in <sup>
        return preg_replace('/([spdf])([0-9]+)/i', '$1<sup>$2</sup>', htmlspecialchars($config));
    }
}
