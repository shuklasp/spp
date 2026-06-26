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

// Get table names from SPPDB resolver
\SPP\Scheduler::setContext('lekhak');
$usersTable = \SPPMod\SPPDB\SPPDB::sppTable('users');
$rolesTable = \SPPMod\SPPDB\SPPDB::sppTable('roles');
$rightsTable = \SPPMod\SPPDB\SPPDB::sppTable('rights');
$userrolesTable = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
$rolerightTable = \SPPMod\SPPDB\SPPDB::sppTable('roleright');

$tables = [
    $usersTable => "CREATE TABLE IF NOT EXISTS {$usersTable} (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'active',
        created_at DATETIME,
        updated_at DATETIME
    )",
    $rolesTable => "CREATE TABLE IF NOT EXISTS {$rolesTable} (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE
    )",
    $rightsTable => "CREATE TABLE IF NOT EXISTS {$rightsTable} (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE
    )",
    $userrolesTable => "CREATE TABLE IF NOT EXISTS {$userrolesTable} (
        userid INTEGER NOT NULL,
        roleid INTEGER NOT NULL,
        PRIMARY KEY (userid, roleid)
    )",
    $rolerightTable => "CREATE TABLE IF NOT EXISTS {$rolerightTable} (
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
$check = $pdo->prepare("SELECT COUNT(*) FROM {$usersTable} WHERE username = ?");
$check->execute(['admin']);
if ((int) $check->fetchColumn() === 0) {
    $stmt = $pdo->prepare("INSERT INTO {$usersTable} (username, email, password, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    $now = date('Y-m-d H:i:s');
    $stmt->execute(['admin', 'admin@lekhak.local', password_hash('admin', PASSWORD_DEFAULT), 'active', $now, $now]);
    echo "  Seeded admin user (admin/admin)\n";
} else {
    echo "  Admin user already exists\n";
}

// Seed admin role
$check = $pdo->prepare("SELECT COUNT(*) FROM {$rolesTable} WHERE name = ?");
$check->execute(['administrator']);
if ((int) $check->fetchColumn() === 0) {
    $pdo->exec("INSERT INTO {$rolesTable} (name) VALUES ('administrator')");
    echo "  Seeded administrator role\n";
}

// Assign admin role to admin user
$adminId = $pdo->query("SELECT id FROM {$usersTable} WHERE username = 'admin'")->fetchColumn();
$roleId = $pdo->query("SELECT id FROM {$rolesTable} WHERE name = 'administrator'")->fetchColumn();
if ($adminId && $roleId) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM {$userrolesTable} WHERE userid = ? AND roleid = ?");
    $check->execute([$adminId, $roleId]);
    if ((int) $check->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO {$userrolesTable} (userid, roleid) VALUES (?, ?)")->execute([$adminId, $roleId]);
        echo "  Assigned administrator role to admin\n";
    }
}

echo "\nDone! Tables in DB:\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "  - $t\n";
}
