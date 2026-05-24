<?php
require_once __DIR__ . '/sppinit.php';

$db = new \SPPMod\SPPDB\SPPDB();

$db->execute_query("CREATE TABLE IF NOT EXISTS spp_entity_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_class VARCHAR(255) NOT NULL,
    entity_id INT NOT NULL,
    revision_date DATETIME NOT NULL,
    author_id INT NULL,
    delta_data JSON NOT NULL,
    log_message TEXT
)");
echo "Created table spp_entity_revisions\n";

$db->execute_query("CREATE TABLE IF NOT EXISTS spp_entity_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_class VARCHAR(255) NOT NULL,
    entity_id INT NOT NULL,
    language_code VARCHAR(10) NOT NULL,
    translated_data JSON NOT NULL,
    UNIQUE KEY entity_lang (entity_class, entity_id, language_code)
)");
echo "Created table spp_entity_translations\n";
