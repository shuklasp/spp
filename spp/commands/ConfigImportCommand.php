<?php
namespace SPP\CLI\Commands;

/**
 * ConfigImportCommand
 *
 * Imports a previously exported configuration file (SQL, SQLite, or XDB)
 * back into the current database.
 *
 * Usage:
 *   php spp.php config:import <file> [--on-conflict=drop|merge|abort]
 *
 * Defaults:
 *   --on-conflict = prompt (interactive confirmation)
 *
 * Conflict strategies:
 *   drop   – DROP existing tables before importing
 *   merge  – INSERT rows, skipping duplicates (INSERT IGNORE / INSERT OR IGNORE)
 *   abort  – Cancel the import if any target table already has data
 */
class ConfigImportCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'config:import';
    }

    public function getDescription(): string
    {
        return 'Import database tables and settings from an exported SQL, SQLite, or XDB file';
    }

    public function execute(array $args): void
    {
        $options    = $this->parseOptions($args);
        $positional = $this->parsePositional($args);
        $file       = $positional[0] ?? null;
        $onConflict = $options['on-conflict'] ?? 'prompt';

        if (!$file) {
            $this->error("Usage: php spp.php config:import <file> [--on-conflict=drop|merge|abort]");
            return;
        }

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $format = $this->detectFormat($file);
        $this->info("Importing from {$file} (format={$format}, on-conflict={$onConflict})");

        try {
            $db = new \SPPMod\SPPDB\SPPDB();

            switch ($format) {
                case 'sql':
                    $this->importSQL($db, $file, $onConflict);
                    break;
                case 'sqlite':
                    $this->importSQLite($db, $file, $onConflict);
                    break;
                case 'xdb':
                    $this->importXDB($db, $file, $onConflict);
                    break;
                default:
                    $this->error("Could not determine file format for: {$file}");
                    return;
            }

            $this->info("Import complete.");

        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
        }
    }

    // ── SQL Import ────────────────────────────────────────────────────

    private function importSQL(\SPPMod\SPPDB\SPPDB $db, string $file, string $onConflict): void
    {
        $content = file_get_contents($file);

        // Extract and restore settings if present
        if (preg_match('/-- SETTINGS --\n(.*?)\n-- \/SETTINGS --/s', $content, $m)) {
            $this->restoreSettings($m[1]);
            $content = preg_replace('/-- SETTINGS --\n.*?\n-- \/SETTINGS --/s', '', $content);
        }

        // Pre-check tables for conflict
        if ($onConflict !== 'drop') {
            $tablesToCheck = $this->extractTableNamesFromSQL($content);
            if (!$this->handleConflict($db, $tablesToCheck, $onConflict)) {
                return;
            }
        }

        if ($onConflict === 'merge') {
            // Replace INSERT INTO with INSERT IGNORE INTO
            $driver = $db->getDriver();
            if ($driver === 'sqlite') {
                $content = str_ireplace('INSERT INTO', 'INSERT OR IGNORE INTO', $content);
            } else {
                $content = str_ireplace('INSERT INTO', 'INSERT IGNORE INTO', $content);
            }
            // Don't drop tables in merge mode
            $content = preg_replace('/DROP TABLE IF EXISTS.*?;\n/i', '', $content);
        }

        // Execute statements
        $statements = $this->splitSQLStatements($content);
        $count = 0;
        foreach ($statements as $stmt) {
            $trimmed = trim($stmt);
            if (empty($trimmed) || str_starts_with($trimmed, '--')) continue;
            try {
                $db->exec($trimmed);
                $count++;
            } catch (\Exception $e) {
                $this->warn("Statement failed: " . substr($trimmed, 0, 80) . "... — " . $e->getMessage());
            }
        }
        $this->line("  SQL: {$count} statement(s) executed");
    }

    // ── SQLite Import ─────────────────────────────────────────────────

    private function importSQLite(\SPPMod\SPPDB\SPPDB $db, string $file, string $onConflict): void
    {
        $sqlite = new \PDO('sqlite:' . $file);
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Restore settings
        try {
            $stmt = $sqlite->query("SELECT value FROM _spp_settings WHERE key = 'global_settings_yml'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $this->restoreSettings($row['value']);
            }
        } catch (\Exception $e) {
            // No settings table — fine
        }

        // Get list of tables (excluding internal)
        $tables = [];
        $tablesResult = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != '_spp_settings'");
        foreach ($tablesResult as $row) {
            $tables[] = $row['name'];
        }

        if (!$this->handleConflict($db, $tables, $onConflict)) {
            return;
        }

        foreach ($tables as $table) {
            $rows = $sqlite->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            if ($onConflict === 'drop') {
                try { $db->exec("DROP TABLE IF EXISTS {$table}"); } catch (\Exception $e) {}
            }

            // Ensure table exists on the target
            // (For drop mode, we need to recreate; for merge, it must already exist)
            if ($onConflict === 'drop') {
                $schemaSql = $sqlite->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
                if ($schemaSql) {
                    // Convert SQLite CREATE to MySQL-compatible if needed
                    if ($db->getDriver() !== 'sqlite') {
                        $schemaSql = $this->convertSqliteToMysql($schemaSql);
                    }
                    try { $db->exec($schemaSql); } catch (\Exception $e) {}
                }
            }

            $cols = array_keys($rows[0]);
            $colStr = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));

            $insertKeyword = ($onConflict === 'merge')
                ? ($db->getDriver() === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE')
                : 'INSERT';

            $imported = 0;
            foreach ($rows as $row) {
                try {
                    $sql = "{$insertKeyword} INTO {$table} ({$colStr}) VALUES ({$placeholders})";
                    $db->execute_query($sql, array_values($row));
                    $imported++;
                } catch (\Exception $e) {
                    // Skip on error
                }
            }
            $this->line("  {$table}: {$imported}/" . count($rows) . " row(s) imported");
        }
    }

    // ── XDB Import ────────────────────────────────────────────────────

    private function importXDB(\SPPMod\SPPDB\SPPDB $db, string $file, string $onConflict): void
    {
        $dom = new \DOMDocument();
        $dom->load($file);

        // Restore settings
        $settingsNodes = $dom->getElementsByTagName('settings');
        if ($settingsNodes->length > 0) {
            $this->restoreSettings($settingsNodes->item(0)->textContent);
        }

        $tables = $dom->getElementsByTagName('table');
        $tableNames = [];
        for ($i = 0; $i < $tables->length; $i++) {
            $tableNames[] = $tables->item($i)->getAttribute('name');
        }

        if (!$this->handleConflict($db, $tableNames, $onConflict)) {
            return;
        }

        for ($i = 0; $i < $tables->length; $i++) {
            $tableNode = $tables->item($i);
            $tableName = $tableNode->getAttribute('name');

            if ($onConflict === 'drop') {
                try { $db->exec("DROP TABLE IF EXISTS {$tableName}"); } catch (\Exception $e) {}
            }

            $rowNodes = $tableNode->getElementsByTagName('row');
            $imported = 0;

            for ($j = 0; $j < $rowNodes->length; $j++) {
                $rowNode = $rowNodes->item($j);
                $colNodes = $rowNode->getElementsByTagName('col');

                $data = [];
                for ($k = 0; $k < $colNodes->length; $k++) {
                    $colNode = $colNodes->item($k);
                    $colName = $colNode->getAttribute('name');
                    $isNull = $colNode->getAttribute('null') === 'true';
                    $data[$colName] = $isNull ? null : $colNode->textContent;
                }

                if (empty($data)) continue;

                $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($data)));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));
                $insertKeyword = ($onConflict === 'merge')
                    ? ($db->getDriver() === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE')
                    : 'INSERT';

                try {
                    $db->execute_query("{$insertKeyword} INTO {$tableName} ({$cols}) VALUES ({$placeholders})", array_values($data));
                    $imported++;
                } catch (\Exception $e) {
                    // Skip on error
                }
            }
            $this->line("  {$tableName}: {$imported}/{$rowNodes->length} row(s) imported");
        }
    }

    // ── Settings Restore ──────────────────────────────────────────────

    private function restoreSettings(string $yamlContent): void
    {
        $settingsPath = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : dirname(__DIR__, 2) . '/etc')
            . '/global-settings.yml';

        // Create backup
        if (file_exists($settingsPath)) {
            copy($settingsPath, $settingsPath . '.bak.' . date('YmdHis'));
        }

        file_put_contents($settingsPath, $yamlContent);
        $this->line("  Settings restored to global-settings.yml");
    }

    // ── Conflict Handling ─────────────────────────────────────────────

    private function handleConflict(\SPPMod\SPPDB\SPPDB $db, array $tables, string $onConflict): bool
    {
        if ($onConflict === 'drop') return true; // will drop tables anyway

        $conflicting = [];
        foreach ($tables as $table) {
            try {
                if ($db->tableExists($table)) {
                    $count = $db->execute_query("SELECT COUNT(*) as cnt FROM {$table}");
                    if (!empty($count) && (int)($count[0]['cnt'] ?? 0) > 0) {
                        $conflicting[] = $table . " (" . $count[0]['cnt'] . " rows)";
                    }
                }
            } catch (\Exception $e) {
                // Table might not exist — no conflict
            }
        }

        if (empty($conflicting)) return true;

        if ($onConflict === 'abort') {
            $this->error("Aborting — the following tables already contain data:");
            foreach ($conflicting as $t) $this->line("  - {$t}");
            return false;
        }

        if ($onConflict === 'prompt') {
            $this->warn("The following tables already contain data:");
            foreach ($conflicting as $t) $this->line("  - {$t}");
            $this->line("Options: [d]rop existing data, [m]erge, [a]bort");
            $this->line("Proceeding with merge by default (non-interactive mode).");
            return true; // Default to merge in non-interactive
        }

        // merge
        return true;
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function detectFormat(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['sql'])) return 'sql';
        if (in_array($ext, ['sqlite', 'db', 'sqlite3'])) return 'sqlite';
        if (in_array($ext, ['xdb', 'xml'])) return 'xdb';
        return $ext;
    }

    private function extractTableNamesFromSQL(string $sql): array
    {
        $tables = [];
        preg_match_all('/(?:INSERT\s+(?:IGNORE\s+)?INTO|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?)\s+`?(\w+)`?/i', $sql, $matches);
        if (!empty($matches[1])) {
            $tables = array_unique($matches[1]);
        }
        return $tables;
    }

    private function splitSQLStatements(string $sql): array
    {
        return preg_split('/;\s*\n/', $sql);
    }

    private function convertSqliteToMysql(string $createSql): string
    {
        $sql = str_ireplace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT AUTO_INCREMENT PRIMARY KEY', $createSql);
        $sql = str_ireplace('TEXT', 'TEXT', $sql);
        return $sql;
    }

    private function parseOptions(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            }
        }
        return $options;
    }

    private function parsePositional(array $args): array
    {
        $positional = [];
        $skipNext = false;
        foreach ($args as $i => $arg) {
            if ($skipNext) { $skipNext = false; continue; }
            if ($arg === 'config:import' || $arg === 'spp.php') continue;
            if (str_starts_with($arg, '--')) continue;
            $positional[] = $arg;
        }
        return $positional;
    }
}
