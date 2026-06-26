<?php

define('SPP_BASE_DIR', dirname(__DIR__));
require_once dirname(__DIR__) . '/spp/spp.php';
require_once dirname(__DIR__) . '/spp/modules/spp/sppdb/class.sppdb.php';
require_once dirname(__DIR__) . '/spp/modules/spp/sppdb/class.sppblueprint.php';
require_once dirname(__DIR__) . '/spp/modules/spp/sppdb/class.sppmigration.php';

use SPPMod\SPPDB\SPPDB;

echo "--- SPPDB Migration Runner ---\n";

$db = new SPPDB();

// 1. Ensure migrations table exists
$db->exec("
    CREATE TABLE IF NOT EXISTS spp_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// 2. Scan migrations directory
$migrationsDir = __DIR__ . '/migrations';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0755, true);
    echo "Created migrations directory at {$migrationsDir}\n";
}

$files = scandir($migrationsDir);
$files = array_filter($files, function ($f) {
    return preg_match('/\.php$/', $f); });
sort($files);

// 3. Get already executed migrations
$executed = $db->query("SELECT migration FROM spp_migrations")->execute();
$executedMigrations = array_column($executed, 'migration');

// 4. Run pending migrations
$ran = 0;
foreach ($files as $file) {
    if (!in_array($file, $executedMigrations)) {
        echo "Migrating: {$file}\n";

        require_once $migrationsDir . '/' . $file;

        // Assume class name is studly cased filename without extension
        // e.g. 2026_06_10_create_users_table.php -> CreateUsersTable
        $className = preg_replace('/^[0-9_]+/', '', $file);
        $className = str_replace('.php', '', $className);
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));

        if (class_exists($className)) {
            $migration = new $className();
            $migration->up();

            $db->insert('spp_migrations', ['migration' => $file]);
            echo "Migrated:  {$file}\n";
            $ran++;
        } else {
            echo "Error: Class {$className} not found in {$file}\n";
        }
    }
}

if ($ran === 0) {
    echo "Nothing to migrate.\n";
} else {
    echo "Successfully migrated {$ran} file(s).\n";
}
