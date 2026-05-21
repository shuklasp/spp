<?php
namespace App\Lekhak\Commands;

use SPP\CLI\Command;

/**
 * I18nExportCommand
 * 
 * Exports translations from the spp_translations table to a JSON file.
 */
class I18nExportCommand extends Command
{
    public function getName(): string
    {
        return 'i18n:export';
    }

    public function getDescription(): string
    {
        return 'Export translations for a specific locale to a JSON file.';
    }

    public function execute(array $args): void
    {
        $locale = null;

        foreach ($args as $arg) {
            if ($arg === 'i18n:export' || $arg === 'spp.php') continue;
            if (str_starts_with($arg, '--locale=')) {
                $locale = substr($arg, 9);
            } elseif (!str_starts_with($arg, '--') && !$locale) {
                $locale = $arg; // allow positional
            }
        }

        if (!$locale) {
            $this->error("Usage: php spp.php i18n:export <locale>");
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
        
        if (!$db->tableExists($table)) {
            $this->error("Table {$table} does not exist.");
            return;
        }

        $sql = "SELECT key_code, translation FROM {$table} WHERE locale = ?";
        $rows = $db->execute_query($sql, [$locale]);

        if (empty($rows)) {
            $this->warn("No translations found for locale '{$locale}'.");
            return;
        }

        $data = [];
        foreach ($rows as $row) {
            $data[$row['key_code']] = $row['translation'];
        }

        $outDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : '.') . '/var/exports';
        if (!is_dir($outDir)) mkdir($outDir, 0777, true);

        $outFile = $outDir . "/translations_{$locale}.json";
        file_put_contents($outFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Exported " . count($data) . " translations to {$outFile}.");
    }
}
