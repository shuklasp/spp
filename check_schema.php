<?php
$pdo = new PDO('sqlite:var/db/merged_fix_verified.sqlite');
$stmt = $pdo->query('PRAGMA table_info(lek_nodes);');
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($result);
