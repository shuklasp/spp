<?php
try { 
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=school;port=3306', 'root', ''); 
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $pdo->exec('CREATE TABLE IF NOT EXISTS test_autoinc_1 (placeholder INT)'); 
    $pdo->exec('ALTER TABLE test_autoinc_1 ADD uid INTEGER PRIMARY KEY AUTO_INCREMENT'); 
    echo 'OK'; 
} catch (Exception $e) { 
    echo $e->getMessage(); 
}
