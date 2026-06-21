<?php
require 'sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$db->execute_query('CREATE TABLE IF NOT EXISTS lek_login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address VARCHAR(45) NOT NULL, username VARCHAR(255) NOT NULL, attempts INTEGER DEFAULT 1, last_attempt DATETIME)');
$db->execute_query('CREATE TABLE IF NOT EXISTS lek_loginrec (sessid VARCHAR(100) PRIMARY KEY, uid INTEGER, logintime DATETIME, ipaddr VARCHAR(45), lastaccess DATETIME)');
$db->execute_query('CREATE TABLE IF NOT EXISTS lek_remember_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER, token VARCHAR(128) UNIQUE NOT NULL, expires_at DATETIME)');
echo "Created tables.\n";
