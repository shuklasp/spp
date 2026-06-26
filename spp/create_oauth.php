<?php
require_once __DIR__ . '/spp.php';

$db = new \SPPMod\SPPDB\SPPDB();

// 1. OAuth Clients
$table1 = \SPPMod\SPPDB\SPPDB::sppTable('oauth_clients');
$sql1 = "CREATE TABLE IF NOT EXISTS `$table1` (
  `id` varchar(100) NOT NULL,
  `client_secret` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `redirect_uri` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// 2. OAuth Auth Codes
$table2 = \SPPMod\SPPDB\SPPDB::sppTable('oauth_auth_codes');
$sql2 = "CREATE TABLE IF NOT EXISTS `$table2` (
  `id` varchar(100) NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `redirect_uri` text NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// 3. OAuth Tokens
$table3 = \SPPMod\SPPDB\SPPDB::sppTable('oauth_tokens');
$sql3 = "CREATE TABLE IF NOT EXISTS `$table3` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `access_token` varchar(100) NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`access_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
  $db->execute_query($sql1);
  $db->execute_query($sql2);
  $db->execute_query($sql3);
  echo "OAuth tables created successfully.\n";
} catch (\Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
