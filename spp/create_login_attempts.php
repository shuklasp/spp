<?php
require_once 'spp.php';
$db = new \SPPMod\SPPDB\SPPDB();
$sql = "CREATE TABLE IF NOT EXISTS " . \SPPMod\SPPDB\SPPDB::sppTable('login_attempts') . " (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(255) NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_attempt DATETIME NOT NULL
)";
$db->execute_query($sql);
echo "Table login_attempts created successfully.\n";
