<?php
$pdo = new PDO('sqlite:var/db/school.sqlite');
$stmt = $pdo->query('SELECT name FROM sqlite_master WHERE type="table"');
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
