<?php
try {
    $db = new PDO('mysql:host=localhost;port=3307;dbname=lekhak', 'root', 'hello123');
    $stmt = $db->query('SHOW TABLES');
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
