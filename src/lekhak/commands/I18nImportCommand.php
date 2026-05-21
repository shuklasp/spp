<?php
namespace App\Lekhak\Commands;

use SPP\CLI\Command;

/**
 * I18nImportCommand
 * 
 * Imports a JSON or PO file into the spp_translations table.
 */
class I18nImportCommand extends Command
{
    public function getName(): string
    {
        return 'i18n:import';
    }

    public function getDescription(): string
    {
        return 'Import translations from a JSON file into the database.';
    }

    public function execute(array $args): void
    {
        $file = null;
        $locale = 'en';

        foreach ($args as $arg) {
            if ($arg === 'i18n:import' || $arg === 'spp.php') continue;
            if (str_starts_with($arg, '--locale=')) {
                $locale = substr($arg, 9);
            } elseif (!str_starts_with($arg, '--')) {
                $file = $arg;
            }
        }

        if (!$file || !file_exists($file)) {
            $this->error("Usage: php spp.php i18n:import <file.json> [--locale=en]");
            return;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            $this->error("Invalid JSON format.");
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
        
        // Ensure table exists
        $this->ensureSchema($db, $table);

        $imported = 0;
        $driver = $db->getDriver();
        $insertKeyword = ($driver === 'sqlite') ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

        foreach ($data as $key => $val) {
            try {
                $sql = "{$insertKeyword} INTO {$table} (key_code, locale, translation, status) VALUES (?, ?, ?, 'active')";
                $db->execute_query($sql, [$key, $locale, $val]);
                $imported++;
            } catch (\Exception $e) {
                // Ignore duplicates
            }
        }

        $this->info("Imported {$imported} translations for locale '{$locale}'.");
    }

    private function ensureSchema($db, $table)
    {
        if (!$db->tableExists($table)) {
            $isSqlite = $db->getDriver() === 'sqlite';
            if ($isSqlite) {
                $db->execute_query("CREATE TABLE {$table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    key_code VARCHAR(255) NOT NULL,
                    locale VARCHAR(10) NOT NULL,
                    translation TEXT,
                    status VARCHAR(20) DEFAULT 'active',
                    UNIQUE(key_code, locale)
                )");
            } else {
                $db->execute_query("CREATE TABLE {$table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    key_code VARCHAR(255) NOT NULL,
                    locale VARCHAR(10) NOT NULL,
                    translation TEXT,
                    status VARCHAR(20) DEFAULT 'active',
                    UNIQUE KEY `idx_key_locale` (`key_code`, `locale`)
                )");
            }
        }
    }
}
