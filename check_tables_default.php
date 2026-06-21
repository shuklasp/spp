<?php
try { 
    $db = new PDO('sqlite:var/db/default.sqlite'); 
    $stmt=$db->query('SELECT name FROM sqlite_master WHERE type="table"'); 
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN)); 
} catch (Exception $e) { 
    echo $e->getMessage(); 
}
