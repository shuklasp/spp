<?php
namespace SPP\CLI\Commands;

/**
 * ConfigExportCommand
 *
 * Exports the current database and settings to SQL, SQLite, or XDB format.
 *
 * Usage:
 *   php spp.php config:export [--format=sql|sqlite|xdb] [--tables=t1,t2,...] [--xdb-name=mydb]
 *
 * Defaults:
 *   --format   = sql
 *   --tables   = all (exports every table)
 *   --xdb-name = same as the configured database name
 *
 * Output goes to var/exports/ with a timestamped filename.
 */
class ConfigExportCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'config:export';
    }

    public function getDescription(): string
    {
        return 'Export database tables and global settings to SQL, SQLite, or XDB format';
    }

    public function execute(array $args): void
    {
        $options = $this->parseOptions($args);
        $format  = $options['format']   ?? 'sql';
        $tables  = $options['tables']   ?? null; // null = all
        $xdbName = $options['xdb-name'] ?? null;

        $exportDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : '.') . '/var/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $this->info("Starting config:export (format={$format})");

        try {
            $db = new \SPPMod\SPPDB\SPPDB();

            // Determine which tables to export
            $allTables = $this->getAllTables($db);
            if ($tables) {
                $whitelist = array_map('trim', explode(',', $tables));
                $tablesToExport = array_filter($allTables, fn($t) => in_array($t, $whitelist));
                if (empty($tablesToExport)) {
                    $this->error("None of the specified tables exist: {$tables}");
                    return;
                }
                $this->info("Exporting " . count($tablesToExport) . " whitelisted table(s)");
            } else {
                $tablesToExport = $allTables;
                $this->info("Exporting all " . count($tablesToExport) . " table(s)");
            }

            $timestamp = date('Y-m-d_His');

            switch ($format) {
                case 'sql':
                    $outFile = $exportDir . "/export_{$timestamp}.sql";
                    $this->exportToSQL($db, $tablesToExport, $outFile);
                    break;

                case 'sqlite':
                    $outFile = $exportDir . "/export_{$timestamp}.sqlite";
                    $this->exportToSQLite($db, $tablesToExport, $outFile);
                    break;

                case 'xdb':
                    $dbName = $xdbName ?: $this->getDbName($db);
                    $outFile = $exportDir . "/export_{$timestamp}.xdb";
                    $this->exportToXDB($db, $tablesToExport, $outFile, $dbName);
                    break;

                default:
                    $this->error("Unknown format: {$format}. Use sql, sqlite, or xdb.");
                    return;
            }

            // Append global settings
            $this->exportSettings($outFile, $format);

            $this->info("Export complete: {$outFile}");

        } catch (\Exception $e) {
            $this->error("Export failed: " . $e->getMessage());
        }
    }

    // ── SQL Export ─────────────────────────────────────────────────────

    private function exportToSQL(\SPPMod\SPPDB\SPPDB $db, array $tables, string $outFile): void
    {
        $sql = "-- SPP Config Export (SQL)\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $sql .= "-- Table: {$table}\n";

            // Get CREATE TABLE
            $createResult = $db->execute_query("SHOW CREATE TABLE {$table}");
            if (!empty($createResult)) {
                $createSql = $createResult[0]['Create Table'] ?? '';
                if ($createSql) {
                    $sql .= "DROP TABLE IF EXISTS {$table};\n";
                    $sql .= $createSql . ";\n\n";
                }
            }

            // Get rows
            $rows = $db->execute_query("SELECT * FROM {$table}");
            foreach ($rows as $row) {
                $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($row)));
                $vals = implode(', ', array_map(function ($v) use ($db) {
                    if ($v === null) return 'NULL';
                    return "'" . addslashes($v) . "'";
                }, array_values($row)));
                $sql .= "INSERT INTO {$table} ({$cols}) VALUES ({$vals});\n";
            }
            $sql .= "\n";
        }

        file_put_contents($outFile, $sql);
        $this->line("  SQL: " . count($tables) . " table(s) written");
    }

    // ── SQLite Export ──────────────────────────────────────────────────

    private function exportToSQLite(\SPPMod\SPPDB\SPPDB $db, array $tables, string $outFile): void
    {
        $sqlite = new \PDO('sqlite:' . $outFile);
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        foreach ($tables as $table) {
            // Get schema
            $schema = $this->getTableSchema($db, $table);
            $sqlite->exec($schema['sqlite_create']);

            // Copy data
            $rows = $db->execute_query("SELECT * FROM {$table}");
            if (empty($rows)) continue;

            $cols = array_keys($rows[0]);
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $colStr = implode(', ', $cols);
            $stmt = $sqlite->prepare("INSERT INTO {$table} ({$colStr}) VALUES ({$placeholders})");

            foreach ($rows as $row) {
                $stmt->execute(array_values($row));
            }

            $this->line("  SQLite: {$table} — " . count($rows) . " row(s)");
        }
    }

    // ── XDB Export ─────────────────────────────────────────────────────

    private function exportToXDB(\SPPMod\SPPDB\SPPDB $db, array $tables, string $outFile, string $dbName): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('xdb');
        $root->setAttribute('name', $dbName);
        $root->setAttribute('version', '1.0');
        $root->setAttribute('exported', date('c'));
        $dom->appendChild($root);

        foreach ($tables as $table) {
            $tableNode = $dom->createElement('table');
            $tableNode->setAttribute('name', $table);

            $rows = $db->execute_query("SELECT * FROM {$table}");
            foreach ($rows as $row) {
                $rowNode = $dom->createElement('row');
                foreach ($row as $col => $val) {
                    $colNode = $dom->createElement('col');
                    $colNode->setAttribute('name', $col);
                    if ($val !== null) {
                        $colNode->appendChild($dom->createCDATASection($val));
                    } else {
                        $colNode->setAttribute('null', 'true');
                    }
                    $rowNode->appendChild($colNode);
                }
                $tableNode->appendChild($rowNode);
            }

            $tableNode->setAttribute('rows', (string)count($rows));
            $root->appendChild($tableNode);
            $this->line("  XDB: {$table} — " . count($rows) . " row(s)");
        }

        $dom->save($outFile);
    }

    // ── Settings Export ────────────────────────────────────────────────

    private function exportSettings(string $outFile, string $format): void
    {
        $settingsPath = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : dirname(__DIR__, 2) . '/etc')
            . '/global-settings.yml';
        if (!file_exists($settingsPath)) return;

        $content = file_get_contents($settingsPath);

        if ($format === 'sql') {
            $block = "\n-- SETTINGS --\n" . $content . "\n-- /SETTINGS --\n";
            file_put_contents($outFile, $block, FILE_APPEND);
        } elseif ($format === 'xdb') {
            // Embed as a CDATA node
            $dom = new \DOMDocument();
            $dom->load($outFile);
            $settingsNode = $dom->createElement('settings');
            $settingsNode->appendChild($dom->createCDATASection($content));
            $dom->documentElement->appendChild($settingsNode);
            $dom->save($outFile);
        }
        // SQLite: settings stored in a special _spp_settings table
        elseif ($format === 'sqlite') {
            $sqlite = new \PDO('sqlite:' . $outFile);
            $sqlite->exec("CREATE TABLE IF NOT EXISTS _spp_settings (key TEXT PRIMARY KEY, value TEXT)");
            $sqlite->prepare("INSERT OR REPLACE INTO _spp_settings (key, value) VALUES (?, ?)")
                ->execute(['global_settings_yml', $content]);
        }

        $this->line("  Settings appended to export");
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function getAllTables(\SPPMod\SPPDB\SPPDB $db): array
    {
        $driver = $db->getDriver();
        if ($driver === 'sqlite') {
            $rows = $db->execute_query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            return array_column($rows, 'name');
        }
        $rows = $db->execute_query("SHOW TABLES");
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = array_values($row)[0];
        }
        return $tables;
    }

    private function getDbName(\SPPMod\SPPDB\SPPDB $db): string
    {
        if (class_exists('\\SPP\\SPPConfig')) {
            $name = \SPP\SPPConfig::get('database.name');
            if ($name) return $name;
        }
        return 'spp_export';
    }

    private function getTableSchema(\SPPMod\SPPDB\SPPDB $db, string $table): array
    {
        $cols = [];
        try {
            $desc = $db->execute_query("DESCRIBE {$table}");
            foreach ($desc as $col) {
                $name = $col['Field'];
                $type = strtoupper($col['Type']);
                $sqliteType = 'TEXT';
                if (str_contains($type, 'INT')) $sqliteType = 'INTEGER';
                elseif (str_contains($type, 'REAL') || str_contains($type, 'FLOAT') || str_contains($type, 'DOUBLE') || str_contains($type, 'DECIMAL')) $sqliteType = 'REAL';
                elseif (str_contains($type, 'BLOB')) $sqliteType = 'BLOB';

                $pk = ($col['Key'] === 'PRI') ? ' PRIMARY KEY' : '';
                $auto = str_contains(strtolower($col['Extra'] ?? ''), 'auto_increment') ? ' AUTOINCREMENT' : '';
                $cols[] = "{$name} {$sqliteType}{$pk}{$auto}";
            }
        } catch (\Exception $e) {
            $cols[] = "id INTEGER PRIMARY KEY";
        }

        return [
            'sqlite_create' => "CREATE TABLE IF NOT EXISTS {$table} (" . implode(', ', $cols) . ")"
        ];
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
}
