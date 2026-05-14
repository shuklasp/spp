<?php
header('Content-Type: text/plain');
print_r($_SERVER);
echo "\nQ: " . ($_GET['q'] ?? 'NULL');
?>
