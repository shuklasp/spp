<?php
require_once 'vendor/autoload.php';
require_once 'spp/sppinit.php';

$dbPath = dirname(SPP_BASE_DIR) . '/var/db/default.sqlite';
echo "DB path: $dbPath\n";
echo "Exists: " . (file_exists($dbPath) ? 'yes' : 'no') . "\n";
echo "Size: " . filesize($dbPath) . " bytes\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
