<?php require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$sql = 'CREATE TABLE IF NOT EXISTS lek_comments (id INT AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(50) NOT NULL, entity_id INT NOT NULL, author_id INT, body TEXT, status INT DEFAULT 1, created DATETIME, changed DATETIME)';
$db->execute_query($sql);
echo 'Table lek_comments created.';
