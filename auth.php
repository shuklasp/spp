<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/spp/sppinit.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['sppdocs_admin_auth'] = true;
$_SESSION['sppdocs_user'] = 'admin';
$_SESSION['sppdocs_role'] = 'admin';
$_SESSION['sppdocs_csrf'] = 'test';
echo session_id();
