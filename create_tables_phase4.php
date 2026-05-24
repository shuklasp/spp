<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$db->execute_query('CREATE TABLE IF NOT EXISTS lek_entities (id INTEGER PRIMARY KEY AUTO_INCREMENT, uuid VARCHAR(100), entity_type VARCHAR(100), bundle VARCHAR(100), created DATETIME, changed DATETIME, data LONGTEXT)');
$db->execute_query('CREATE TABLE IF NOT EXISTS lek_files (id INTEGER PRIMARY KEY AUTO_INCREMENT, uuid VARCHAR(100), filename VARCHAR(255), uri VARCHAR(255), filemime VARCHAR(100), filesize BIGINT, status INT DEFAULT 1, created DATETIME, changed DATETIME)');
echo 'Tables created';
