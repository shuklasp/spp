<?php
$pdo = new PDO('sqlite:var/db/merged_fix_verified.sqlite');
$pdo->exec('DROP TABLE IF EXISTS lek_spp_entity_fields');
