<?php
$db = new PDO('sqlite:c:/projects/apache/school1/var/db/lekhak.sqlite');
try {
    $db->exec("ALTER TABLE lek_content_types ADD COLUMN storage_strategy VARCHAR(20) DEFAULT 'flat'");
    $db->exec("ALTER TABLE lek_content_types ADD COLUMN is_revisionable TINYINT(1) DEFAULT 0");
    $db->exec("ALTER TABLE lek_content_types ADD COLUMN created DATETIME");
    echo "Columns added successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
