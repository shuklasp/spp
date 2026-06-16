<?php
$sql = 'SELECT * FROM spp_modules WHERE name = ?';
preg_match('/^SELECT\s+(.+?)\s+FROM\s+([a-zA-Z0-9_\.]+)(?:\s+WHERE\s+(.+?))?(?:\s+ORDER BY\s+(.+?))?(?:\s+LIMIT\s+(.+?))?$/i', $sql, $matches);
print_r($matches);
