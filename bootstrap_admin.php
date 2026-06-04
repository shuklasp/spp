<?php
/**
 * Bootstrap script: create auth tables and seed admin user in the default SQLite DB.
 */
require_once 'vendor/autoload.php';
require_once 'spp/sppinit.php';

$dbPath = dirname(SPP_BASE_DIR) . '/var/db/default.sqlite';
echo "DB path: $dbPath\n";

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get prefix from config
$prefix = \SPP\Module::getConfig('global_table_prefix', 'sppdb') ?: 'spp_';

$tables = [
    "{$prefix}users" => "CREATE TABLE IF NOT EXISTS {$prefix}users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'active',
        created_at DATETIME,
        updated_at DATETIME
    )",
    "{$prefix}roles" => "CREATE TABLE IF NOT EXISTS {$prefix}roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE
    )",
    "{$prefix}rights" => "CREATE TABLE IF NOT EXISTS {$prefix}rights (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE
    )",
    "{$prefix}userroles" => "CREATE TABLE IF NOT EXISTS {$prefix}userroles (
        userid INTEGER NOT NULL,
        roleid INTEGER NOT NULL,
        PRIMARY KEY (userid, roleid)
    )",
    "{$prefix}roleright" => "CREATE TABLE IF NOT EXISTS {$prefix}roleright (
        roleid INTEGER NOT NULL,
        rightid INTEGER NOT NULL,
        PRIMARY KEY (roleid, rightid)
    )",
];

foreach ($tables as $name => $sql) {
    $pdo->exec($sql);
    echo "  Created/verified: $name\n";
}

// Seed admin user
$usersTable = "{$prefix}users";
$check = $pdo->prepare("SELECT COUNT(*) FROM {$usersTable} WHERE username = ?");
$check->execute(['admin']);
if ((int)$check->fetchColumn() === 0) {
    $stmt = $pdo->prepare("INSERT INTO {$usersTable} (username, email, password, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    $now = date('Y-m-d H:i:s');
    $stmt->execute(['admin', 'admin@lekhak.local', password_hash('admin', PASSWORD_DEFAULT), 'active', $now, $now]);
    echo "  Seeded admin user (admin/admin)\n";
} else {
    echo "  Admin user already exists\n";
}

// Seed admin role
$rolesTable = "{$prefix}roles";
$check = $pdo->prepare("SELECT COUNT(*) FROM {$rolesTable} WHERE name = ?");
$check->execute(['administrator']);
if ((int)$check->fetchColumn() === 0) {
    $pdo->exec("INSERT INTO {$rolesTable} (name) VALUES ('administrator')");
    echo "  Seeded administrator role\n";
}

// Assign admin role to admin user
$adminId = $pdo->query("SELECT id FROM {$usersTable} WHERE username = 'admin'")->fetchColumn();
$roleId = $pdo->query("SELECT id FROM {$rolesTable} WHERE name = 'administrator'")->fetchColumn();
if ($adminId && $roleId) {
    $urTable = "{$prefix}userroles";
    $check = $pdo->prepare("SELECT COUNT(*) FROM {$urTable} WHERE userid = ? AND roleid = ?");
    $check->execute([$adminId, $roleId]);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO {$urTable} (userid, roleid) VALUES (?, ?)")->execute([$adminId, $roleId]);
        echo "  Assigned administrator role to admin\n";
    }
}

echo "\nDone! Tables in DB:\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "  - $t\n";
}
