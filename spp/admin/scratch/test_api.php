<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_REQUEST['action'] = 'get_dashboard_stats';

// Mock php://input
function mock_input($data) {
    $file = tempnam(sys_get_temp_dir(), 'mock_input');
    file_put_contents($file, json_encode($data));
    return $file;
}

// We can't easily mock php://input for file_get_contents in a real script without stream wrappers.
// But we can just set $_POST and $_REQUEST manually.
$_POST = ['action' => 'get_dashboard_stats'];

// Capture output
ob_start();
require_once 'src/lekhak/resources/admin-api.php';
$output = ob_get_clean();

echo "Response:\n";
echo $output . "\n";
