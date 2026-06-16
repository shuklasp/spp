<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=school;port=3306', 'root', ''); 
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $stmt = $pdo->query('DESCRIBE users');
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);
} catch (Exception $e) {
    echo $e->getMessage();
}
