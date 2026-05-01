<?php
try {
    $db = new PDO('mysql:host=127.0.0.1;port=3307', 'root', 'hello123');
    $db->exec('CREATE DATABASE IF NOT EXISTS school');
    echo "Database 'school' ensured.\n";
    
    $db->exec('USE school');
    $stmt = $db->query('SHOW TABLES');
    echo "Existing tables:\n";
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
