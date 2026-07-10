<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class DeleteAppCommand
 * Removes an SPP application and all its associated resources, caches, and configuration.
 *
 * Usage:
 *   php spp.php delete:app <AppName> [--force] [--keep-db]
 *
 * Options:
 *   --force    Skip confirmation prompt
 *   --keep-db  Preserve database tables (only remove files and config)
 *   --dry-run  Show what would be deleted without actually deleting anything
 *
 * Examples:
 *   php spp.php delete:app MyApp
 *   php spp.php delete:app MyApp --force
 *   php spp.php delete:app MyApp --dry-run
 *   php spp.php delete:app MyApp --force --keep-db
 */
class DeleteAppCommand extends Command
{
    protected string $name = 'delete:app';
    protected string $description = 'Delete an SPP application context and all its data (files, config, caches, views)';

    /** @var int Count of items deleted/would-be-deleted */
    private int $deletedCount = 0;

    /** @var bool Dry-run mode */
    private bool $dryRun = false;

    public function execute(array $args): void
    {
        // ── Parse arguments ──────────────────────────────────────────
        $appName = $args['AppNameToConfirm'] ?? $args[2] ?? null;

        if (!$appName) {
            $appName = $this->prompt("Enter application name to delete");
            if (!$appName) {
                $this->error("App name is required.");
                echo "Usage: php spp.php delete:app <AppName> [--force] [--keep-db] [--dry-run]\n";
                return;
            }
        }

        // Strip flags from appName if accidentally included
        $appName = trim($appName, '-');

        // ── Validate ────────────────────────────────────────────────
        if (in_array($appName, ['default', 'admin', 'spp', 'core'])) {
            $this->error("Cannot delete system application '{$appName}'.");
            return;
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $appName)) {
            $this->error("Invalid application name '{$appName}'. Must start with a letter and contain only alphanumeric characters and underscores.");
            return;
        }

        $force   = in_array('--force', $args) || isset($args['--force']) || $this->hasFlag($args, 'force');
        $keepDb  = in_array('--keep-db', $args) || isset($args['--keep-db']) || $this->hasFlag($args, 'keep-db');
        $this->dryRun = in_array('--dry-run', $args) || isset($args['--dry-run']) || $this->hasFlag($args, 'dry-run');

        // ── Check if app exists ─────────────────────────────────────
        $settingsPath = SPP_APP_DIR . "/spp/etc/global-settings.yml";
        $appExists = false;
        $appConfig = [];
        $srcDirExists = is_dir(SPP_APP_DIR . "/src/{$appName}");
        $etcDirExists = is_dir(SPP_APP_DIR . "/etc/apps/{$appName}");

        if (file_exists($settingsPath)) {
            $settings = Yaml::parseFile($settingsPath);
            if (isset($settings['apps'][$appName])) {
                $appExists = true;
                $appConfig = $settings['apps'][$appName];
            }
        }

        if (!$appExists && !$srcDirExists && !$etcDirExists) {
            $this->error("Application '{$appName}' not found.");
            echo "  Checked: global-settings.yml, src/{$appName}/, etc/apps/{$appName}/\n";
            return;
        }

        // ── Display what will be deleted ────────────────────────────
        $appType = $appConfig['type'] ?? 'unknown';
        $baseUrl = $appConfig['base_url'] ?? 'N/A';
        $prefix  = $appConfig['table_prefix'] ?? 'N/A';

        echo "\n";
        if ($this->dryRun) {
            echo "  ╔══════════════════════════════════════════════╗\n";
            echo "  ║          DRY RUN — Nothing will be deleted   ║\n";
            echo "  ╚══════════════════════════════════════════════╝\n\n";
        }

        echo "  Application: {$appName}\n";
        echo "  Mode:        {$appType}\n";
        echo "  Base URL:    {$baseUrl}\n";
        echo "  DB Prefix:   {$prefix}\n";
        echo "\n";

        // Build list of targets
        $targets = $this->buildDeleteTargets($appName, $keepDb);

        if (empty($targets)) {
            $this->info("Nothing to delete for '{$appName}'.");
            return;
        }

        echo "  The following will be " . ($this->dryRun ? "checked" : "deleted") . ":\n";
        foreach ($targets as $target) {
            $icon = $target['exists'] ? '✓' : '✗';
            $status = $target['exists'] ? '' : ' (not found)';
            echo "    {$icon} {$target['label']}{$status}\n";
        }
        echo "\n";

        // ── Confirm ─────────────────────────────────────────────────
        if (!$force && !$this->dryRun) {
            $confirm = $this->prompt(
                "⚠ Are you sure you want to PERMANENTLY delete '{$appName}' and all its data? Type the app name to confirm",
                ""
            );
            if ($confirm !== $appName) {
                echo "\n  Deletion cancelled. You must type the exact app name to confirm.\n";
                return;
            }
        }

        // ── Execute deletion ────────────────────────────────────────
        echo "\n";
        $this->deletedCount = 0;
        $dbHandled = false;

        // 0. Database cleanup (if not keep-db)
        $sppdbConfPath = SPP_APP_DIR . "/etc/apps/{$appName}/modsconf/sppdb/config.yml";
        if (!$keepDb && file_exists($sppdbConfPath)) {
            $sppdbConf = Yaml::parseFile($sppdbConfPath);
            $vars = $sppdbConf['variables'] ?? [];
            $dbtype = $vars['dbtype'] ?? 'mysql';
            $prefix = $vars['global_table_prefix'] ?? $appConfig['table_prefix'] ?? '';

            if ($dbtype === 'sqlite') {
                $sqlitePath = $vars['sqlite_path'] ?? "var/db/{$appName}.sqlite";
                $fullSqlitePath = SPP_APP_DIR . '/' . $sqlitePath;
                if (file_exists($fullSqlitePath)) {
                    if (!$force && !$this->dryRun) {
                        $confirmDb = $this->prompt("Delete SQLite database file '{$sqlitePath}'? (Y/n)", "Y");
                        if (strtolower($confirmDb) === 'y' || strtolower($confirmDb) === 'yes') {
                            $this->deleteFile($fullSqlitePath, "SQLite database file ({$sqlitePath})");
                            $dbHandled = true;
                        } else {
                            echo "  ℹ Skipped SQLite database deletion.\n";
                            $dbHandled = true;
                        }
                    } else {
                        $this->deleteFile($fullSqlitePath, "SQLite database file ({$sqlitePath})");
                        $dbHandled = true;
                    }
                }
            } else {
                $dbname = $vars['dbname'] ?? $appName;
                $dbhost = $vars['dbhost'] ?? 'localhost';
                $dbport = $vars['dbport'] ?? 3306;
                $dbuser = $vars['dbuser'] ?? 'root';
                $dbpasswd = $vars['dbpasswd'] ?? '';

                if ($this->dryRun) {
                    echo "  → Would connect to {$dbtype} ({$dbhost}) and check database '{$dbname}' for drop/cleanup\n";
                    $dbHandled = true;
                } else {
                    try {
                        $dsn = "{$dbtype}:host={$dbhost};port={$dbport}";
                        $pdo = new \PDO($dsn, $dbuser, $dbpasswd, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                        
                        $action = 'keep';
                        if ($force) {
                            // In force mode, if dbname equals appName, drop the whole DB, else drop tables
                            if ($dbname === $appName) {
                                $action = 'drop-db';
                            } elseif (!empty($prefix)) {
                                $action = 'drop-tables';
                            }
                        } else {
                            echo "\n  Database '{$dbname}' found on {$dbhost}.\n";
                            echo "  Options for database cleanup:\n";
                            echo "    1. drop-db     — Drop the entire database '{$dbname}' (Best if dedicated to this app)\n";
                            echo "    2. drop-tables — Drop only tables matching prefix '{$prefix}' (Best if shared database)\n";
                            echo "    3. keep        — Do not touch the database\n";
                            $dbChoice = $this->prompt("Choose database cleanup action (drop-db/drop-tables/keep)", "keep");
                            $action = strtolower(trim($dbChoice));
                        }

                        if ($action === 'drop-db' || $action === '1') {
                            if ($dbtype === 'mysql') {
                                $pdo->exec("DROP DATABASE IF EXISTS `{$dbname}`");
                            } else {
                                $pdo->exec("DROP DATABASE {$dbname}");
                            }
                            echo "  ✓ Dropped entire database '{$dbname}' on {$dbhost}\n";
                            $this->deletedCount++;
                            $dbHandled = true;
                        } elseif (($action === 'drop-tables' || $action === '2') && !empty($prefix)) {
                            $pdo->exec("USE `{$dbname}`");
                            if ($dbtype === 'mysql') {
                                $stmt = $pdo->query("SHOW TABLES LIKE '{$prefix}%'");
                                $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                                if (!empty($tables)) {
                                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                                    foreach ($tables as $table) {
                                        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                                        echo "  ✓ Dropped table `{$table}`\n";
                                        $this->deletedCount++;
                                    }
                                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                                } else {
                                    echo "  ℹ No tables found with prefix '{$prefix}' in database '{$dbname}'.\n";
                                }
                                $dbHandled = true;
                            } else {
                                echo "  ℹ Table prefix dropping is currently automated for MySQL. Please drop tables manually for {$dbtype}.\n";
                            }
                        } else {
                            echo "  ℹ Kept database '{$dbname}' untouched.\n";
                            $dbHandled = true;
                        }
                    } catch (\Exception $e) {
                        echo "  ⚠ Could not connect to database server {$dbhost} ({$e->getMessage()}). Skipping automated database cleanup.\n";
                    }
                }
            }
        }

        // 1. Remove source directory: src/{AppName}/
        $this->deleteDirectory(
            SPP_APP_DIR . "/src/{$appName}",
            "Source directory (src/{$appName}/)"
        );

        // 2. Remove config directory: etc/apps/{AppName}/
        $this->deleteDirectory(
            SPP_APP_DIR . "/etc/apps/{$appName}",
            "Config directory (etc/apps/{$appName}/)"
        );
        $this->deleteDirectory(
            SPP_APP_DIR . "/spp/etc/apps/{$appName}",
            "Secondary Config directory (spp/etc/apps/{$appName}/)"
        );

        // 3. Remove resources directory: resources/{AppName}/
        $this->deleteDirectory(
            SPP_APP_DIR . "/resources/{$appName}",
            "Resources directory (resources/{$appName}/)"
        );
        $this->deleteDirectory(
            SPP_APP_DIR . "/resources/views/{$appName}",
            "Resources views directory (resources/views/{$appName}/)"
        );

        // 4. Remove Blade/Twig compiled view cache: var/cache/{AppName}/
        $this->deleteDirectory(
            SPP_APP_DIR . "/var/cache/{$appName}",
            "View cache (var/cache/{$appName}/)"
        );

        // 5. Remove compiled route cache: var/cache/routes_{AppName}.php
        $this->deleteFile(
            SPP_APP_DIR . "/var/cache/routes_{$appName}.php",
            "Route cache (var/cache/routes_{$appName}.php)"
        );

        // 6. Remove attribute route cache (lowercase variant)
        $lcName = strtolower($appName);
        $this->deleteFile(
            SPP_APP_DIR . "/var/cache/routes_{$lcName}.php",
            "Route cache (var/cache/routes_{$lcName}.php)"
        );

        // 7. Remove from global-settings.yml
        if ($appExists && !$this->dryRun) {
            $settings = Yaml::parseFile($settingsPath);
            unset($settings['apps'][$appName]);
            file_put_contents($settingsPath, Yaml::dump($settings, 10, 2));
            echo "  ✓ Removed '{$appName}' from global-settings.yml\n";
            $this->deletedCount++;
        } elseif ($appExists && $this->dryRun) {
            echo "  → Would remove '{$appName}' from global-settings.yml\n";
        }

        // 8. Invalidate compiled config cache
        $configCache = SPP_APP_DIR . '/var/cache/system/config.php';
        if (file_exists($configCache) && !$this->dryRun) {
            @unlink($configCache);
            echo "  ✓ Cleared compiled config cache\n";
            $this->deletedCount++;
        } elseif (file_exists($configCache) && $this->dryRun) {
            echo "  → Would clear compiled config cache\n";
        }

        // 9. Clear events cache (may reference this app's listeners)
        $eventsCache = SPP_APP_DIR . '/var/cache/events_compiled.php';
        if (file_exists($eventsCache) && !$this->dryRun) {
            @unlink($eventsCache);
            echo "  ✓ Cleared compiled events cache\n";
            $this->deletedCount++;
        } elseif (file_exists($eventsCache) && $this->dryRun) {
            echo "  → Would clear compiled events cache\n";
        }

        // 10. Clear modules cache (may reference this app's modules)
        $this->deleteFile(
            SPP_APP_DIR . "/var/cache/modules_{$appName}.php",
            "Modules cache (var/cache/modules_{$appName}.php)"
        );

        // 11. Clear class map cache (may contain app's classes)
        $classMap = SPP_APP_DIR . '/var/cache/system/classmap.php';
        if (file_exists($classMap) && !$this->dryRun) {
            @unlink($classMap);
            echo "  ✓ Cleared class map cache\n";
            $this->deletedCount++;
        } elseif (file_exists($classMap) && $this->dryRun) {
            echo "  → Would clear class map cache\n";
        }

        // ── Summary ─────────────────────────────────────────────────
        echo "\n";
        if ($this->dryRun) {
            $this->info("Dry run complete. No files were deleted.");
        } elseif ($this->deletedCount > 0) {
            echo "  ╔══════════════════════════════════════════════════════╗\n";
            echo "  ║  ✅ Application '{$appName}' deleted successfully   ║\n";
            echo "  ╚══════════════════════════════════════════════════════╝\n";
            echo "\n";
            if (!$keepDb && !$dbHandled) {
                $this->warn("Database tables with prefix '{$prefix}' were NOT automatically dropped.");
                echo "  To drop them manually, run:\n";
                echo "    php spp.php db:drop-tables --prefix={$prefix}\n";
                echo "  Or in SQL:\n";
                echo "    SHOW TABLES LIKE '{$prefix}%';\n\n";
            }
        } else {
            $this->info("Nothing was found to delete for '{$appName}'.");
        }
    }

    /**
     * Build the list of all targets that will be checked for deletion.
     */
    private function buildDeleteTargets(string $appName, bool $keepDb): array
    {
        $targets = [];
        $lcName = strtolower($appName);

        $sppdbConfPath = SPP_APP_DIR . "/etc/apps/{$appName}/modsconf/sppdb/config.yml";
        if (!$keepDb && file_exists($sppdbConfPath)) {
            $sppdbConf = Yaml::parseFile($sppdbConfPath);
            $vars = $sppdbConf['variables'] ?? [];
            $dbtype = $vars['dbtype'] ?? 'mysql';
            if ($dbtype === 'sqlite') {
                $sqlitePath = $vars['sqlite_path'] ?? "var/db/{$appName}.sqlite";
                $targets[] = [
                    'label' => "SQLite database file ({$sqlitePath})",
                    'path' => SPP_APP_DIR . '/' . $sqlitePath,
                    'type' => 'db',
                    'exists' => file_exists(SPP_APP_DIR . '/' . $sqlitePath),
                ];
            } else {
                $dbname = $vars['dbname'] ?? $appName;
                $dbhost = $vars['dbhost'] ?? 'localhost';
                $targets[] = [
                    'label' => "Database / Tables on {$dbtype} ({$dbname} @ {$dbhost})",
                    'path' => $dbname,
                    'type' => 'db',
                    'exists' => true,
                ];
            }
        }

        $dirs = [
            ["src/{$appName}/", SPP_APP_DIR . "/src/{$appName}"],
            ["etc/apps/{$appName}/", SPP_APP_DIR . "/etc/apps/{$appName}"],
            ["spp/etc/apps/{$appName}/", SPP_APP_DIR . "/spp/etc/apps/{$appName}"],
            ["resources/{$appName}/", SPP_APP_DIR . "/resources/{$appName}"],
            ["resources/views/{$appName}/", SPP_APP_DIR . "/resources/views/{$appName}"],
            ["var/cache/{$appName}/", SPP_APP_DIR . "/var/cache/{$appName}"],
        ];

        foreach ($dirs as [$label, $path]) {
            $targets[] = [
                'label' => $label,
                'path' => $path,
                'type' => 'dir',
                'exists' => is_dir($path),
            ];
        }

        $files = [
            ["var/cache/routes_{$appName}.php", SPP_APP_DIR . "/var/cache/routes_{$appName}.php"],
            ["var/cache/routes_{$lcName}.php", SPP_APP_DIR . "/var/cache/routes_{$lcName}.php"],
            ["var/cache/modules_{$appName}.php", SPP_APP_DIR . "/var/cache/modules_{$appName}.php"],
        ];

        foreach ($files as [$label, $path]) {
            $targets[] = [
                'label' => $label,
                'path' => $path,
                'type' => 'file',
                'exists' => file_exists($path),
            ];
        }

        $targets[] = [
            'label' => 'global-settings.yml entry',
            'path' => 'config',
            'type' => 'config',
            'exists' => true, // Already checked
        ];

        $targets[] = [
            'label' => 'Compiled caches (config, events, classmap)',
            'path' => 'caches',
            'type' => 'caches',
            'exists' => true,
        ];

        return $targets;
    }

    /**
     * Delete a directory and all its contents recursively.
     */
    private function deleteDirectory(string $path, string $label): void
    {
        if (!is_dir($path)) {
            return;
        }

        if ($this->dryRun) {
            $count = $this->countItems($path);
            echo "  → Would delete: {$label} ({$count} items)\n";
            return;
        }

        $count = $this->countItems($path);
        $this->recursiveDelete($path);
        echo "  ✓ Deleted: {$label} ({$count} items)\n";
        $this->deletedCount++;
    }

    /**
     * Delete a single file.
     */
    private function deleteFile(string $path, string $label): void
    {
        if (!file_exists($path)) {
            return;
        }

        if ($this->dryRun) {
            $size = $this->humanSize(filesize($path));
            echo "  → Would delete: {$label} ({$size})\n";
            return;
        }

        $size = $this->humanSize(filesize($path));
        @unlink($path);
        echo "  ✓ Deleted: {$label} ({$size})\n";
        $this->deletedCount++;
    }

    /**
     * Recursively delete a directory and all its contents.
     */
    private function recursiveDelete(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Count all files and directories within a path.
     */
    private function countItems(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $count++;
        }

        return $count;
    }

    /**
     * Format byte count to human-readable string.
     */
    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . " KB";
        return round($bytes / 1048576, 1) . " MB";
    }

    /**
     * Custom Admin UI for delete:app — shows a dropdown of existing apps.
     */
    public function renderAdminUI(): string
    {
        $settingsPath = SPP_APP_DIR . "/spp/etc/global-settings.yml";
        $apps = [];

        if (file_exists($settingsPath)) {
            $settings = Yaml::parseFile($settingsPath);
            foreach ($settings['apps'] ?? [] as $name => $config) {
                if (!in_array($name, ['default', 'admin', 'spp', 'core'])) {
                    $apps[] = [
                        'name' => $name,
                        'type' => $config['type'] ?? 'unknown',
                        'url'  => $config['base_url'] ?? 'N/A',
                    ];
                }
            }
        }

        $name = htmlspecialchars($this->getName());
        $desc = htmlspecialchars($this->getDescription());

        $html = '<div class="command-ui-container">';
        $html .= '  <h3>Command: <code>' . $name . '</code></h3>';
        $html .= '  <p>' . $desc . '</p>';

        if (empty($apps)) {
            $html .= '  <div class="command-help" style="margin-top: 15px; padding: 15px; background: var(--glass-bg); border-left: 4px solid var(--warning); border-radius: 6px; color: var(--text);">';
            $html .= '    No deletable applications found.';
            $html .= '  </div>';
        } else {
            $html .= '  <div class="form-group" style="margin-top: 15px;">';
            $html .= '    <label>Select Application to Delete:</label>';
            $html .= '    <select id="cmdAppSelect" class="spp-input" style="background:var(--bg-color-alt); color:var(--text); border:1px solid var(--border-color); padding:8px;">';

            foreach ($apps as $app) {
                $html .= '      <option value="' . htmlspecialchars($app['name']) . '">'
                    . htmlspecialchars($app['name'])
                    . ' (' . htmlspecialchars($app['type']) . ', ' . htmlspecialchars($app['url']) . ')'
                    . '</option>';
            }

            $html .= '    </select>';
            $html .= '  </div>';

            $html .= '  <div class="form-group" style="margin-top: 10px;">';
            $html .= '    <label><input type="checkbox" id="cmdKeepDb"> Keep database tables</label>';
            $html .= '  </div>';

            $html .= '  <div style="display:flex; gap:10px; margin-top:15px;">';
            $html .= '    <button class="spp-btn" style="background:var(--warning);" onclick="'
                . 'const app = document.getElementById(\'cmdAppSelect\').value;'
                . 'const keepDb = document.getElementById(\'cmdKeepDb\').checked;'
                . 'const flags = \'--force\' + (keepDb ? \' --keep-db\' : \'\');'
                . 'if(confirm(\'Are you sure you want to delete \\\'\' + app + \'\\\'? This cannot be undone.\')) {'
                . '  executeCommand(\'' . $name . '\', app + \' \' + flags);'
                . '}'
                . '">🗑️ Delete Application</button>';
            $html .= '    <button class="spp-btn" style="background:var(--glass-bg);" onclick="'
                . 'const app = document.getElementById(\'cmdAppSelect\').value;'
                . 'executeCommand(\'' . $name . '\', app + \' --dry-run\');'
                . '">👁️ Dry Run (Preview)</button>';
            $html .= '  </div>';
        }

        $html .= '</div>';
        return $html;
    }
}
