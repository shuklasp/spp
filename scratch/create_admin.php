<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$username = 'admin123';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Creating user $username in 'users' table...\n";
try {
    $db->execute_query("INSERT INTO users (username, password, status, role_id) VALUES (?, ?, 'active', 1)", [$username, $hash]);
    echo "User created successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
