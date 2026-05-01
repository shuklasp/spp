<?php
// Mock session for API
session_start();
$_SESSION['spp_admin_user'] = 'admin';

// Include API logic
define('SPP_BASE_DIR', __DIR__ . '/../spp');
require_once __DIR__ . '/../spp/sppinit.php';

// Capture API output
ob_start();
$_GET['action'] = 'list_apps';
require_once __DIR__ . '/../spp/admin/api.php';
$output = ob_get_clean();

$data = json_decode($output, true);
echo "API Apps List:\n";
foreach ($data['data']['apps'] as $app) {
    echo "- " . $app['name'] . " (Admin: " . ($app['has_admin'] ? 'YES' : 'NO') . ") Path: " . $app['src_path'] . "\n";
}
