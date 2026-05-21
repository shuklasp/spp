<?php
/**
 * Lekhak Installation Wizard
 * A self-contained, multi-step web installer for the Lekhak CMS.
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

define('SPP_BASE_DIR', __DIR__ . '/spp');
require_once SPP_BASE_DIR . '/sppinit.php';

// Force lekhak context
\SPP\Scheduler::setContext('lekhak');

// Helper to write to debug log
function install_log($msg) {
    @file_put_contents(__DIR__ . '/var/log/install.log', "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

// Check configuration file
$configFile = __DIR__ . '/src/lekhak/etc/modsconf/sppdb/config.yml';
$existingConfig = [];
$alreadyInstalled = false;

if (file_exists($configFile)) {
    try {
        if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
            if (is_array($parsed) && isset($parsed['variables'])) {
                $existingConfig = $parsed['variables'];
                if (!empty($existingConfig['installed'])) {
                    $alreadyInstalled = true;
                }
            }
        }
    } catch (\Exception $e) {
        install_log("Failed to parse existing config: " . $e->getMessage());
    }
}

// Re-check installation by verifying if the database is configured and users table exists
if (!$alreadyInstalled && !empty($existingConfig)) {
    try {
        $db = new \SPPMod\SPPDB\SPPDB();
        if ($db->tableExists('users')) {
            $alreadyInstalled = true;
        }
    } catch (\Exception $e) {
        // Not installed or db not configured yet
    }
}

// Request fresh install
$freshRequested = isset($_GET['fresh']) && $_GET['fresh'] == '1';

// Handle warning/block if already installed
if ($alreadyInstalled && !$freshRequested) {
    // Show already installed page
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lekhak - Already Installed</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f97316;
            --primary-hover: #ea580c;
            --bg: #0b0f19;
            --card-bg: rgba(30, 41, 59, 0.45);
            --card-border: rgba(255, 255, 255, 0.08);
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }
        body {
            background-color: var(--bg);
            background-image: radial-gradient(circle at top right, rgba(249, 115, 22, 0.08), transparent 400px),
                              radial-gradient(circle at bottom left, rgba(99, 102, 241, 0.05), transparent 400px);
            color: var(--text);
            font-family: "Outfit", "Inter", system-ui, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .wizard-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            text-align: center;
        }
        .logo-img {
            max-height: 80px;
            display: block;
            margin: 0 auto 24px;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 12px;
            color: var(--text);
        }
        p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body>
    <div class="wizard-card">
        <img src="img/lekhak_logo.png" class="logo-img" alt="Lekhak Logo">
        <h1>Lekhak is Already Installed</h1>
        <p>Your Lekhak CMS installation is already completed. To start a fresh installation, please click the button below. Warning: a fresh install will erase your existing database tables.</p>
        <div class="btn-group">
            <a href="lekhak/admin" class="btn">Go to Admin Dashboard</a>
            <a href="install.php?fresh=1" class="btn btn-secondary">Perform Fresh Reinstallation</a>
        </div>
    </div>
</body>
</html>';
    exit;
}

// Handle AJAX DB Test
if (isset($_GET['action']) && $_GET['action'] === 'test_db') {
    header('Content-Type: application/json');
    $dbtype = $_POST['dbtype'] ?? 'sqlite';
    if ($dbtype === 'sqlite') {
        $sqlite_path = $_POST['sqlite_path'] ?? 'var/db/lekhak.sqlite';
        $fullPath = __DIR__ . '/' . $sqlite_path;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        try {
            $pdo = new PDO("sqlite:" . $fullPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo json_encode(['success' => true, 'message' => 'SQLite Database connection verified successfully.']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
    } else {
        $host = $_POST['mysql_host'] ?? '127.0.0.1';
        $port = $_POST['mysql_port'] ?? '3306';
        $dbname = $_POST['mysql_name'] ?? 'lekhak';
        $user = $_POST['mysql_user'] ?? '';
        $pass = $_POST['mysql_pass'] ?? '';
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname}", $user, $pass, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            echo json_encode(['success' => true, 'message' => 'MySQL connection established and database found successfully.']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'MySQL Connection failed: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 6) $step = 1;

// Process steps POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $_SESSION['install_lang'] = $_POST['lang'] ?? 'en';
        header("Location: install.php?step=2" . ($freshRequested ? "&fresh=1" : ""));
        exit;
    }
    
    if ($step === 2) {
        // Requirements step - check again
        $reqs = checkRequirements();
        $ok = true;
        foreach ($reqs as $r) {
            if (!$r['ok'] && $r['required']) {
                $ok = false;
            }
        }
        if ($ok) {
            header("Location: install.php?step=3" . ($freshRequested ? "&fresh=1" : ""));
            exit;
        } else {
            $errors[] = "Please fix requirements before continuing.";
        }
    }
    
    if ($step === 3) {
        $dbtype = $_POST['dbtype'] ?? 'sqlite';
        $_SESSION['db_config'] = [
            'dbtype' => $dbtype,
            'sqlite_path' => $_POST['sqlite_path'] ?? 'var/db/lekhak.sqlite',
            'mysql_host' => $_POST['mysql_host'] ?? '127.0.0.1',
            'mysql_port' => $_POST['mysql_port'] ?? '3306',
            'mysql_name' => $_POST['mysql_name'] ?? 'lekhak',
            'mysql_user' => $_POST['mysql_user'] ?? '',
            'mysql_pass' => $_POST['mysql_pass'] ?? '',
            'table_prefix' => $_POST['table_prefix'] ?? 'lek_'
        ];
        
        // Test connection
        $testRes = testConnection($_SESSION['db_config']);
        if ($testRes['success']) {
            header("Location: install.php?step=4" . ($freshRequested ? "&fresh=1" : ""));
            exit;
        } else {
            $errors[] = $testRes['message'];
        }
    }
    
    if ($step === 4) {
        $site_name = trim($_POST['site_name'] ?? '');
        $site_email = trim($_POST['site_email'] ?? '');
        $admin_username = trim($_POST['admin_username'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_email = trim($_POST['admin_email'] ?? '');
        
        if (empty($site_name)) $errors[] = "Site Name is required.";
        if (empty($admin_username)) $errors[] = "Administrator Username is required.";
        if (strlen($admin_password) < 4) $errors[] = "Administrator Password must be at least 4 characters.";
        if (empty($admin_email)) $errors[] = "Administrator Email is required.";
        
        if (empty($errors)) {
            $_SESSION['site_config'] = [
                'site_name' => $site_name,
                'site_email' => $site_email,
                'admin_username' => $admin_username,
                'admin_password' => $admin_password,
                'admin_email' => $admin_email
            ];
            header("Location: install.php?step=5" . ($freshRequested ? "&fresh=1" : ""));
            exit;
        }
    }
    
    if ($step === 5) {
        // Execute installation
        $res = executeInstallation();
        if ($res['success']) {
            $_SESSION['install_completed'] = true;
            header("Location: install.php?step=6");
            exit;
        } else {
            $errors[] = $res['message'];
        }
    }
}

// Requirements helper
function checkRequirements() {
    $writableDirs = [
        'src/lekhak/etc',
        'var',
        'var/db'
    ];
    $reqs = [
        ['name' => 'PHP Version >= 8.1', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'required' => true, 'desc' => 'Current PHP version is ' . PHP_VERSION],
        ['name' => 'PDO Extension', 'ok' => class_exists('PDO'), 'required' => true, 'desc' => 'Required for database connectivity.'],
        ['name' => 'SQLite Extension', 'ok' => in_array('sqlite', PDO::getAvailableDrivers()), 'required' => false, 'desc' => 'Recommended default storage driver.'],
        ['name' => 'MySQL Extension', 'ok' => in_array('mysql', PDO::getAvailableDrivers()), 'required' => false, 'desc' => 'Required if using external MySQL server.']
    ];
    
    foreach ($writableDirs as $dir) {
        $full = __DIR__ . '/' . $dir;
        $isOk = is_writable($full);
        if (!$isOk && !file_exists($full)) {
            @mkdir($full, 0777, true);
            $isOk = is_writable($full);
        }
        $reqs[] = [
            'name' => 'Writable directory: ' . $dir,
            'ok' => $isOk,
            'required' => true,
            'desc' => $isOk ? 'Directory is writable.' : 'Make sure directories are writable.'
        ];
    }
    return $reqs;
}

// Connection test helper
function testConnection($cfg) {
    if ($cfg['dbtype'] === 'sqlite') {
        $fullPath = __DIR__ . '/' . $cfg['sqlite_path'];
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        try {
            $pdo = new PDO("sqlite:" . $fullPath);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    } else {
        $host = $cfg['mysql_host'];
        $port = $cfg['mysql_port'];
        $dbname = $cfg['mysql_name'];
        $user = $cfg['mysql_user'];
        $pass = $cfg['mysql_pass'];
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname}", $user, $pass, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'MySQL Connection failed: ' . $e->getMessage()];
        }
    }
}

function ensureInstallColumns($db, string $table, array $columns): void {
    foreach ($columns as $column => $type) {
        try {
            if (!$db->columnExists($table, $column)) {
                $db->exec("ALTER TABLE {$table} ADD {$column} {$type}");
                install_log("Added missing column {$table}.{$column}.");
            }
        } catch (\Exception $e) {
            install_log("Warning: failed to ensure column {$table}.{$column}: " . $e->getMessage());
        }
    }
}

// Installation Executer
function executeInstallation() {
    $dbConfig = $_SESSION['db_config'] ?? null;
    $siteConfig = $_SESSION['site_config'] ?? null;
    
    if (!$dbConfig || !$siteConfig) {
        return ['success' => false, 'message' => 'Session config missing. Reset install wizard.'];
    }
    
    // 1. Write the Database Config file FIRST
    $configFile = __DIR__ . '/src/lekhak/etc/modsconf/sppdb/config.yml';
    $configDir = dirname($configFile);
    if (!is_dir($configDir)) {
        @mkdir($configDir, 0777, true);
    }
    
    $yamlData = [
        'variables' => [
            'dbtype' => $dbConfig['dbtype'],
            'sqlite_path' => $dbConfig['sqlite_path'],
            'db_engine' => 'pdo',
            'dbname' => ($dbConfig['dbtype'] === 'sqlite' ? 'spp_main' : $dbConfig['mysql_name']),
            'dbhost' => ($dbConfig['dbtype'] === 'sqlite' ? 'localhost' : $dbConfig['mysql_host']),
            'dbuser' => ($dbConfig['dbtype'] === 'sqlite' ? 'root' : $dbConfig['mysql_user']),
            'dbpasswd' => ($dbConfig['dbtype'] === 'sqlite' ? '' : $dbConfig['mysql_pass']),
            'global_table_prefix' => $dbConfig['table_prefix'],
            'installed' => true
        ]
    ];
    
    try {
        if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
            $yamlStr = \Symfony\Component\Yaml\Yaml::dump($yamlData, 4);
            @file_put_contents($configFile, $yamlStr);
            install_log("Database configuration file updated successfully.");
        } else {
            return ['success' => false, 'message' => 'Symfony YAML component is missing.'];
        }
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Failed to write config: ' . $e->getMessage()];
    }
    
    // 2. Set App database prefix override dynamically in global-settings.yml
    $globalSettingsFile = __DIR__ . '/spp/etc/global-settings.yml';
    if (file_exists($globalSettingsFile)) {
        try {
            $gs = \Symfony\Component\Yaml\Yaml::parseFile($globalSettingsFile);
            if (isset($gs['apps']['lekhak'])) {
                $gs['apps']['lekhak']['table_prefix'] = $dbConfig['table_prefix'];
                $gsStr = \Symfony\Component\Yaml\Yaml::dump($gs, 6);
                @file_put_contents($globalSettingsFile, $gsStr);
                install_log("Global apps prefix setting updated successfully.");
            }
        } catch (\Exception $e) {
            install_log("Warning: failed to update global settings table prefix: " . $e->getMessage());
        }
    }
    
    // 3. Connect to Database using newly written configuration
    try {
        $db = new \SPPMod\SPPDB\SPPDB();
        $driver = $db->getDriver();
        install_log("Database adapter loaded. Active Driver: " . $driver);
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Failed to load DB config: ' . $e->getMessage()];
    }
    
    // Helper to run Schema creations
    try {
        $isSqlite = ($driver === 'sqlite');
        
        // Drop existing tables if fresh install requested
        if (isset($_GET['fresh']) && $_GET['fresh'] == '1') {
            $tablesToDrop = [
                'users', 'roles', 'rights', 'userroles', 'roleright', 'entity_roles',
                'vocabularies', 'terms', 'nodes', 'content_types', 'fields', 'blocks',
                'landing_blocks', 'landing_pages', 'node_access', 'audit_logs',
                'sppview_pages', 'sppview_defaults', 'sppview_specials'
            ];
            foreach ($tablesToDrop as $t) {
                try {
                    $fullName = \SPPMod\SPPDB\SPPDB::sppTable($t);
                    $db->execute_query("DROP TABLE IF EXISTS {$fullName}");
                } catch (\Exception $e) {
                    // Ignore drops error
                }
            }
            install_log("Existing tables dropped for fresh reinstall.");
        }
        
        // Create Routing Tables
        if ($isSqlite) {
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE,
                url VARCHAR(500) NOT NULL
            )');
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_defaults') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                defkey VARCHAR(100) NOT NULL UNIQUE,
                defval VARCHAR(500) NOT NULL
            )');
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_specials') . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                method VARCHAR(100) NOT NULL
            )');
        } else {
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages') . ' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                url VARCHAR(500) NOT NULL
            )');
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_defaults') . ' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                defkey VARCHAR(100) NOT NULL UNIQUE,
                defval VARCHAR(500) NOT NULL
            )');
            $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_specials') . ' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                method VARCHAR(100) NOT NULL
            )');
        }
        
        // Create SPP Auth tables (No prefix because of shared group core)
        $tUsers = \SPPMod\SPPDB\SPPDB::sppTable('users');
        $tRoles = \SPPMod\SPPDB\SPPDB::sppTable('roles');
        $tRights = \SPPMod\SPPDB\SPPDB::sppTable('rights');
        $tUserRoles = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
        $tRoleRight = \SPPMod\SPPDB\SPPDB::sppTable('roleright');
        $tEntityRoles = \SPPMod\SPPDB\SPPDB::sppTable('entity_roles');
        
        if ($isSqlite) {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tUsers} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255),
                password VARCHAR(255),
                role_id INT,
                created_at DATETIME,
                updated_at DATETIME,
                status VARCHAR(20)
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tRoles} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tRights} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT
            )");
        } else {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tUsers} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255),
                password VARCHAR(255),
                role_id INT,
                created_at DATETIME,
                updated_at DATETIME,
                status VARCHAR(20)
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tRoles} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tRights} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT
            )");
        }
        
        $db->execute_query("CREATE TABLE IF NOT EXISTS {$tUserRoles} (
            userid INT NOT NULL,
            roleid INT NOT NULL,
            PRIMARY KEY (userid, roleid)
        )");
        $db->execute_query("CREATE TABLE IF NOT EXISTS {$tRoleRight} (
            roleid INT NOT NULL,
            rightid INT NOT NULL,
            PRIMARY KEY (roleid, rightid)
        )");
        $db->execute_query("CREATE TABLE IF NOT EXISTS {$tEntityRoles} (
            target_class VARCHAR(255) NOT NULL,
            target_id VARCHAR(100) NOT NULL,
            role_id INT NOT NULL,
            PRIMARY KEY (target_class, target_id, role_id)
        )");
        
        // Create Lekhak CMS Specific Tables (prefixed with lek_)
        $tVocab = \SPPMod\SPPDB\SPPDB::sppTable('vocabularies');
        $tTerms = \SPPMod\SPPDB\SPPDB::sppTable('terms');
        $tNodes = \SPPMod\SPPDB\SPPDB::sppTable('nodes');
        $tContentTypes = \SPPMod\SPPDB\SPPDB::sppTable('content_types');
        $tFields = \SPPMod\SPPDB\SPPDB::sppTable('fields');
        $tBlocks = \SPPMod\SPPDB\SPPDB::sppTable('blocks');
        $tLandingBlocks = \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks');
        $tLandingPages = \SPPMod\SPPDB\SPPDB::sppTable('landing_pages');
        $tNodeAccess = \SPPMod\SPPDB\SPPDB::sppTable('node_access');
        $tAuditLogs = \SPPMod\SPPDB\SPPDB::sppTable('audit_logs');
        
        if ($isSqlite) {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tVocab} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                label VARCHAR(255),
                description TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tTerms} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vid VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                parent_id INT,
                description TEXT,
                weight INT DEFAULT 0
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tNodes} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                alias VARCHAR(255),
                body TEXT,
                bundle VARCHAR(50) NOT NULL,
                author_id INTEGER,
                status VARCHAR(20) NOT NULL,
                langcode VARCHAR(10),
                translation_id INTEGER,
                created DATETIME,
                changed DATETIME,
                fields_data TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tContentTypes} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                label VARCHAR(255) NOT NULL,
                description TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tFields} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                field_name VARCHAR(50) NOT NULL,
                label VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                bundle VARCHAR(50) NOT NULL,
                settings TEXT,
                required BOOLEAN DEFAULT 0,
                weight INT DEFAULT 0
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tBlocks} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                title VARCHAR(255),
                region VARCHAR(50),
                visibility_paths TEXT,
                content LONGTEXT,
                type VARCHAR(20),
                weight INT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tLandingBlocks} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                block_type VARCHAR(50),
                region VARCHAR(50),
                weight INTEGER DEFAULT 0,
                page_id INTEGER DEFAULT 0,
                data TEXT,
                created TEXT,
                changed TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tLandingPages} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                alias VARCHAR(255),
                body TEXT,
                bundle VARCHAR(50) NOT NULL DEFAULT 'landing_page',
                author_id INTEGER,
                status VARCHAR(20),
                langcode VARCHAR(10),
                translation_id INTEGER,
                fields_data TEXT,
                is_default INTEGER DEFAULT 0,
                layout_id VARCHAR(50),
                created DATETIME,
                changed DATETIME
            )");
        } else {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tVocab} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                label VARCHAR(255),
                description TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tTerms} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vid VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                parent_id INT,
                description TEXT,
                weight INT DEFAULT 0
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tNodes} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                alias VARCHAR(255),
                body LONGTEXT,
                bundle VARCHAR(50) NOT NULL,
                author_id BIGINT,
                status VARCHAR(20) NOT NULL,
                langcode VARCHAR(10),
                translation_id BIGINT,
                created DATETIME,
                changed DATETIME,
                fields_data LONGTEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tContentTypes} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                label VARCHAR(255) NOT NULL,
                description TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tFields} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                field_name VARCHAR(50) NOT NULL,
                label VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                bundle VARCHAR(50) NOT NULL,
                settings TEXT,
                required BOOLEAN DEFAULT 0,
                weight INT DEFAULT 0
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tBlocks} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                title VARCHAR(255),
                region VARCHAR(50),
                visibility_paths TEXT,
                content LONGTEXT,
                type VARCHAR(20),
                weight INT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tLandingBlocks} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                block_type VARCHAR(50),
                region VARCHAR(50),
                weight INTEGER DEFAULT 0,
                page_id INTEGER DEFAULT 0,
                data TEXT,
                created TEXT,
                changed TEXT
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tLandingPages} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                alias VARCHAR(255),
                body LONGTEXT,
                bundle VARCHAR(50) NOT NULL DEFAULT 'landing_page',
                author_id BIGINT,
                status VARCHAR(20),
                langcode VARCHAR(10),
                translation_id BIGINT,
                fields_data LONGTEXT,
                is_default TINYINT(1) DEFAULT 0,
                layout_id VARCHAR(50),
                created DATETIME,
                changed DATETIME
            )");
        }
        
        $db->execute_query("CREATE TABLE IF NOT EXISTS {$tNodeAccess} (
            nid INT NOT NULL,
            gid INT NOT NULL,
            realm VARCHAR(255) NOT NULL,
            grant_view INT DEFAULT 0,
            grant_update INT DEFAULT 0,
            grant_delete INT DEFAULT 0,
            PRIMARY KEY (nid, gid, realm)
        )");

        if ($isSqlite) {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tAuditLogs} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(100) NOT NULL,
                action VARCHAR(20) NOT NULL,
                old_values TEXT,
                new_values TEXT,
                user_id INTEGER,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            ensureInstallColumns($db, $tNodes, [
                'alias' => 'VARCHAR(255)',
                'author_id' => 'INTEGER',
                'langcode' => 'VARCHAR(10)',
                'translation_id' => 'INTEGER',
                'fields_data' => 'TEXT'
            ]);
            ensureInstallColumns($db, $tFields, [
                'field_name' => 'VARCHAR(50)',
                'required' => 'BOOLEAN DEFAULT 0',
                'weight' => 'INT DEFAULT 0'
            ]);
            ensureInstallColumns($db, $tLandingPages, [
                'alias' => 'VARCHAR(255)',
                'body' => 'TEXT',
                'bundle' => "VARCHAR(50) DEFAULT 'landing_page'",
                'author_id' => 'INTEGER',
                'status' => 'VARCHAR(20)',
                'langcode' => 'VARCHAR(10)',
                'translation_id' => 'INTEGER',
                'fields_data' => 'TEXT',
                'is_default' => 'INTEGER DEFAULT 0',
                'layout_id' => 'VARCHAR(50)'
            ]);
        } else {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$tAuditLogs} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(100) NOT NULL,
                action VARCHAR(20) NOT NULL,
                old_values TEXT,
                new_values TEXT,
                user_id INT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            ensureInstallColumns($db, $tNodes, [
                'alias' => 'VARCHAR(255)',
                'author_id' => 'BIGINT',
                'langcode' => 'VARCHAR(10)',
                'translation_id' => 'BIGINT',
                'fields_data' => 'LONGTEXT'
            ]);
            ensureInstallColumns($db, $tFields, [
                'field_name' => 'VARCHAR(50)',
                'required' => 'BOOLEAN DEFAULT 0',
                'weight' => 'INT DEFAULT 0'
            ]);
            ensureInstallColumns($db, $tLandingPages, [
                'alias' => 'VARCHAR(255)',
                'body' => 'LONGTEXT',
                'bundle' => "VARCHAR(50) DEFAULT 'landing_page'",
                'author_id' => 'BIGINT',
                'status' => 'VARCHAR(20)',
                'langcode' => 'VARCHAR(10)',
                'translation_id' => 'BIGINT',
                'fields_data' => 'LONGTEXT',
                'is_default' => 'TINYINT(1) DEFAULT 0',
                'layout_id' => 'VARCHAR(50)'
            ]);
        }
        
        install_log("Database schema successfully generated.");
        
        // 4. Seed Security Roles & Rights (driver-specific ignore syntax)
        if ($isSqlite) {
            $db->execute_query("INSERT OR IGNORE INTO {$tRoles} (id, role_name, description) VALUES (1, 'SuperAdmin', 'Full administrative permissions.')");
            $db->execute_query("INSERT OR IGNORE INTO {$tRoles} (id, role_name, description) VALUES (2, 'Member', 'Default site members.')");
            
            $db->execute_query("INSERT OR IGNORE INTO {$tRights} (id, name, description) VALUES (1, 'administer site configuration', 'Access system preferences')");
            $db->execute_query("INSERT OR IGNORE INTO {$tRights} (id, name, description) VALUES (2, 'administer nodes', 'CRUD access to all content types')");
            $db->execute_query("INSERT OR IGNORE INTO {$tRights} (id, name, description) VALUES (3, 'administer blocks', 'Manage regional blocks and layouts')");
            $db->execute_query("INSERT OR IGNORE INTO {$tRights} (id, name, description) VALUES (4, 'access administration pages', 'Access CMS backend')");
            
            $db->execute_query("INSERT OR IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 1)");
            $db->execute_query("INSERT OR IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 2)");
            $db->execute_query("INSERT OR IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 3)");
            $db->execute_query("INSERT OR IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 4)");
        } else {
            $db->execute_query("INSERT IGNORE INTO {$tRoles} (id, role_name, description) VALUES (1, 'SuperAdmin', 'Full administrative permissions.')");
            $db->execute_query("INSERT IGNORE INTO {$tRoles} (id, role_name, description) VALUES (2, 'Member', 'Default site members.')");
            
            $db->execute_query("INSERT IGNORE INTO {$tRights} (id, name, description) VALUES (1, 'administer site configuration', 'Access system preferences')");
            $db->execute_query("INSERT IGNORE INTO {$tRights} (id, name, description) VALUES (2, 'administer nodes', 'CRUD access to all content types')");
            $db->execute_query("INSERT IGNORE INTO {$tRights} (id, name, description) VALUES (3, 'administer blocks', 'Manage regional blocks and layouts')");
            $db->execute_query("INSERT IGNORE INTO {$tRights} (id, name, description) VALUES (4, 'access administration pages', 'Access CMS backend')");
            
            $db->execute_query("INSERT IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 1)");
            $db->execute_query("INSERT IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 2)");
            $db->execute_query("INSERT IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 3)");
            $db->execute_query("INSERT IGNORE INTO {$tRoleRight} (roleid, rightid) VALUES (1, 4)");
        }
        
        // 6. Create Administrator User Account
        $pwdHash = password_hash($siteConfig['admin_password'], PASSWORD_DEFAULT);
        $db->execute_query("INSERT INTO {$tUsers} (username, email, password, role_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$siteConfig['admin_username'], $siteConfig['admin_email'], $pwdHash, 1, 'active', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        
        if ($isSqlite) {
            $adminId = $db->execute_query("SELECT last_insert_rowid() as id")[0]['id'] ?? 1;
        } else {
            $adminId = $db->execute_query("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? 1;
        }
        $db->execute_query("INSERT INTO {$tUserRoles} (userid, roleid) VALUES (?, ?)", [$adminId, 1]);
        
        // 7. Seed Initial CMS Content Type
        $db->execute_query("INSERT INTO {$tContentTypes} (name, label, description) VALUES ('page', 'Basic Page', 'Static page layouts for text blocks.')");
        $db->execute_query("INSERT INTO {$tContentTypes} (name, label, description) VALUES ('article', 'Article', 'Blogging and press announcements.')");
        
        // 8. Set global settings site_name
        if (file_exists($globalSettingsFile)) {
            try {
                $gs = \Symfony\Component\Yaml\Yaml::parseFile($globalSettingsFile);
                $gs['settings']['site_name'] = $siteConfig['site_name'];
                $gsStr = \Symfony\Component\Yaml\Yaml::dump($gs, 6);
                @file_put_contents($globalSettingsFile, $gsStr);
            } catch (\Exception $e) {
                // Ignore settings failure
            }
        }
        
        install_log("Administrative account seeded successfully.");
        return ['success' => true];
    } catch (\Exception $e) {
        install_log("Database installation failure: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Schema setup failure: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lekhak - Setup Wizard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f97316;
            --primary-hover: #ea580c;
            --bg: #0b0f19;
            --card-bg: rgba(30, 41, 59, 0.45);
            --card-border: rgba(255, 255, 255, 0.08);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.15);
            --input-focus: #f97316;
            --success: #10b981;
            --error: #ef4444;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: var(--bg);
            background-image: radial-gradient(circle at top right, rgba(249, 115, 22, 0.08), transparent 400px),
                              radial-gradient(circle at bottom left, rgba(99, 102, 241, 0.05), transparent 400px);
            color: var(--text);
            font-family: "Outfit", "Inter", system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .wizard-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        }
        .logo-img {
            max-height: 80px;
            display: block;
            margin: 0 auto 20px;
        }
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }
        .step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1e293b;
            border: 2px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            position: relative;
            z-index: 2;
        }
        .step.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 15px rgba(249, 115, 22, 0.4);
        }
        .step.completed {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }
        h2 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #a7f3d0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-control {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            padding: 12px 16px;
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .input-control:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
        }
        select.input-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23f8fafc'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
        }
        .req-list {
            list-style: none;
            margin-bottom: 25px;
        }
        .req-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .req-name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .req-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pass {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }
        .status-fail {
            background: rgba(239, 68, 68, 0.2);
            color: #fca7a7;
        }
        .btn-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }
        .btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            flex-grow: 1;
        }
        .btn:hover {
            background: var(--primary-hover);
        }
        .btn:disabled {
            background: #475569;
            cursor: not-allowed;
            opacity: 0.5;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="wizard-card">
        <img src="img/lekhak_logo.png" class="logo-img" alt="Lekhak Logo">
        
        <div class="steps">
            <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
            <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
            <div class="step <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">4</div>
            <div class="step <?php echo $step >= 5 ? ($step > 5 ? 'completed' : 'active') : ''; ?>">5</div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error) echo htmlspecialchars($error) . "<br>"; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="installForm">

            <?php if ($step === 1): ?>
                <h2>Choose System Language</h2>
                <div class="form-group">
                    <label for="lang">Language</label>
                    <select name="lang" id="lang" class="input-control">
                        <option value="en" <?php echo ($_SESSION['install_lang'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="hi" <?php echo ($_SESSION['install_lang'] ?? '') === 'hi' ? 'selected' : ''; ?>>Hindi</option>
                    </select>
                </div>
                <div class="btn-group">
                    <div></div> <!-- spacer -->
                    <button type="submit" class="btn">Next &rarr;</button>
                </div>
            <?php endif; ?>

            <?php if ($step === 2): ?>
                <h2>Verify Requirements</h2>
                <ul class="req-list">
                    <?php 
                    $reqs = checkRequirements();
                    $allOk = true;
                    foreach ($reqs as $r): 
                        if (!$r['ok'] && $r['required']) $allOk = false;
                    ?>
                        <li class="req-item">
                            <div>
                                <span class="req-name"><?php echo htmlspecialchars($r['name']); ?></span>
                                <div class="req-desc"><?php echo htmlspecialchars($r['desc']); ?></div>
                            </div>
                            <span class="status-badge <?php echo $r['ok'] ? 'status-pass' : ($r['required'] ? 'status-fail' : 'status-fail'); ?>">
                                <?php echo $r['ok'] ? 'Pass' : ($r['required'] ? 'Fail' : 'Optional'); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="btn-group">
                    <a href="install.php?step=1<?php echo $freshRequested ? '&fresh=1' : ''; ?>" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn" <?php echo !$allOk ? 'disabled' : ''; ?>>Next &rarr;</button>
                </div>
            <?php endif; ?>

            <?php if ($step === 3): 
                $db_cfg = $_SESSION['db_config'] ?? $existingConfig;
                $dbtype = $db_cfg['dbtype'] ?? 'sqlite';
                $sqlite_path = $db_cfg['sqlite_path'] ?? 'var/db/lekhak.sqlite';
                $mysql_host = $db_cfg['dbhost'] ?? $db_cfg['mysql_host'] ?? '127.0.0.1';
                $mysql_port = $db_cfg['mysql_port'] ?? '3306';
                $mysql_name = $db_cfg['dbname'] ?? $db_cfg['mysql_name'] ?? 'lekhak';
                $mysql_user = $db_cfg['dbuser'] ?? $db_cfg['mysql_user'] ?? '';
                $mysql_pass = $db_cfg['dbpasswd'] ?? $db_cfg['mysql_pass'] ?? '';
                $table_prefix = $db_cfg['global_table_prefix'] ?? $db_cfg['table_prefix'] ?? 'lek_';
            ?>
                <h2>Database Configuration</h2>
                
                <div class="form-group">
                    <label for="dbtype">Database Driver</label>
                    <select name="dbtype" id="dbtype" class="input-control" onchange="toggleDbFields()">
                        <option value="sqlite" <?php echo $dbtype === 'sqlite' ? 'selected' : ''; ?>>SQLite (Local File)</option>
                        <option value="mysql" <?php echo $dbtype === 'mysql' ? 'selected' : ''; ?>>MySQL Server</option>
                    </select>
                </div>

                <!-- SQLite Settings -->
                <div id="sqlite-group" class="form-group" style="display: <?php echo $dbtype === 'sqlite' ? 'block' : 'none'; ?>;">
                    <label for="sqlite_path">SQLite Database File Path</label>
                    <input type="text" name="sqlite_path" id="sqlite_path" class="input-control" value="<?php echo htmlspecialchars($sqlite_path); ?>">
                </div>

                <!-- MySQL Settings -->
                <div id="mysql-group" style="display: <?php echo $dbtype === 'mysql' ? 'block' : 'none'; ?>;">
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="mysql_host">Host</label>
                            <input type="text" name="mysql_host" id="mysql_host" class="input-control" value="<?php echo htmlspecialchars($mysql_host); ?>">
                        </div>
                        <div class="form-group">
                            <label for="mysql_port">Port</label>
                            <input type="text" name="mysql_port" id="mysql_port" class="input-control" value="<?php echo htmlspecialchars($mysql_port); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="mysql_name">Database Name</label>
                        <input type="text" name="mysql_name" id="mysql_name" class="input-control" value="<?php echo htmlspecialchars($mysql_name); ?>">
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="mysql_user">Username</label>
                            <input type="text" name="mysql_user" id="mysql_user" class="input-control" value="<?php echo htmlspecialchars($mysql_user); ?>">
                        </div>
                        <div class="form-group">
                            <label for="mysql_pass">Password</label>
                            <input type="password" name="mysql_pass" id="mysql_pass" class="input-control" value="<?php echo htmlspecialchars($mysql_pass); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="table_prefix">Table Prefix</label>
                    <input type="text" name="table_prefix" id="table_prefix" class="input-control" value="<?php echo htmlspecialchars($table_prefix); ?>">
                </div>

                <div class="btn-group">
                    <a href="install.php?step=2<?php echo $freshRequested ? '&fresh=1' : ''; ?>" class="btn btn-secondary">&larr; Back</a>
                    <button type="button" class="btn btn-secondary" onclick="testConnectionAjax()">Test Connection</button>
                    <button type="submit" class="btn">Next &rarr;</button>
                </div>

                <div id="test-alert" class="alert" style="display: none; margin-top: 15px;"></div>

                <script>
                    function toggleDbFields() {
                        var dbtype = document.getElementById('dbtype').value;
                        document.getElementById('sqlite-group').style.display = (dbtype === 'sqlite') ? 'block' : 'none';
                        document.getElementById('mysql-group').style.display = (dbtype === 'mysql') ? 'block' : 'none';
                    }

                    function testConnectionAjax() {
                        var alertDiv = document.getElementById('test-alert');
                        alertDiv.style.display = 'block';
                        alertDiv.className = 'alert';
                        alertDiv.innerHTML = 'Testing connection...';
                        
                        var fd = new FormData(document.getElementById('installForm'));
                        
                        fetch('install.php?action=test_db', {
                            method: 'POST',
                            body: fd
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alertDiv.className = 'alert alert-success';
                                alertDiv.innerHTML = data.message;
                            } else {
                                alertDiv.className = 'alert alert-error';
                                alertDiv.innerHTML = data.message;
                            }
                        })
                        .catch(err => {
                            alertDiv.className = 'alert alert-error';
                            alertDiv.innerHTML = 'An unexpected connection error occurred.';
                        });
                    }
                </script>
            <?php endif; ?>

            <?php if ($step === 4): ?>
                <h2>Site & Admin Configuration</h2>
                
                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" name="site_name" id="site_name" class="input-control" value="<?php echo htmlspecialchars($_SESSION['site_config']['site_name'] ?? 'Lekhak Portal'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="site_email">Site Email Address</label>
                    <input type="email" name="site_email" id="site_email" class="input-control" value="<?php echo htmlspecialchars($_SESSION['site_config']['site_email'] ?? 'admin@lekhak.local'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="admin_username">Admin Username</label>
                    <input type="text" name="admin_username" id="admin_username" class="input-control" value="<?php echo htmlspecialchars($_SESSION['site_config']['admin_username'] ?? 'admin'); ?>" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="admin_password">Admin Password</label>
                        <input type="password" name="admin_password" id="admin_password" class="input-control" required placeholder="Choose password">
                    </div>
                    <div class="form-group">
                        <label for="admin_email">Admin Email</label>
                        <input type="email" name="admin_email" id="admin_email" class="input-control" value="<?php echo htmlspecialchars($_SESSION['site_config']['admin_email'] ?? 'admin@lekhak.local'); ?>" required>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="install.php?step=3<?php echo $freshRequested ? '&fresh=1' : ''; ?>" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn">Next &rarr;</button>
                </div>
            <?php endif; ?>

            <?php if ($step === 5): ?>
                <h2>Ready to Install</h2>
                <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 20px;">We have all the information required. Click the button below to initialize database schemas and install Lekhak.</p>
                
                <div class="btn-group">
                    <a href="install.php?step=4<?php echo $freshRequested ? '&fresh=1' : ''; ?>" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn" onclick="showLoader()">Install Lekhak</button>
                </div>

                <div id="install-loader" style="display: none; text-align: center; margin-top: 20px;">
                    <div class="spinner"></div>
                    <div style="font-size: 0.9rem; color: var(--text-muted);">Configuring entities, building schemas, and seeding roles...</div>
                </div>

                <script>
                    function showLoader() {
                        document.getElementById('install-loader').style.display = 'block';
                    }
                </script>
            <?php endif; ?>

            <?php if ($step === 6): ?>
                <h2>Installation Complete!</h2>
                <div class="alert alert-success" style="text-align: center; margin-bottom: 30px;">
                    🎉 Congratulations! Lekhak CMS has been successfully installed.
                </div>
                
                <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 30px;">The Lekhak core engine and reactive dashboard components are now active. You can log in using the administrator credentials configured in step 4.</p>
                
                <div class="btn-group">
                    <a href="index.php" class="btn btn-secondary">Visit Homepage</a>
                    <a href="lekhak/admin" class="btn">Go to Admin Dashboard</a>
                </div>
            <?php endif; ?>

        </form>
    </div>
</body>
</html>
