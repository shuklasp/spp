<?php
require 'sppinit.php';
$db = new SPPMod\SPPDB\SPPDB();
try {
    $db->execute_query('ALTER TABLE '.SPPMod\SPPDB\SPPDB::sppTable('users').' ADD COLUMN mfa_secret VARCHAR(64) NULL, ADD COLUMN mfa_enabled TINYINT(1) DEFAULT 0');
    echo "Columns added.\n";
} catch(Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo $e->getMessage() . "\n";
    }
}
