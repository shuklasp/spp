<?php
// c:\projects\apache\school1\src\lekhak\test_lekhak.php

// 1. Setup minimal environment
$_SERVER['DOCUMENT_ROOT'] = 'c:\projects\apache\school1';
require_once __DIR__ . '/../../vendor/autoload.php';

// Mock session
session_start();
$_SESSION['spp_csrf_token'] = 'test-token';
$_SESSION['spp_admin_fallback'] = true;

// Boot SPP framework
\SPP\App::getApp()->init();

// Boot Lekhak Module Registry
require_once __DIR__ . '/ModuleRegistry.php';
$registry = \Lekhak\ModuleRegistry::getInstance();
$registry->bootAll();

echo "---------------------------------\n";
echo "1. Testing Hook Registration\n";
echo "---------------------------------\n";
$modules = $registry->getLoadedModules();
echo "Loaded " . count($modules) . " modules.\n";

echo "\n---------------------------------\n";
echo "2. Testing Webform Module Block Injection\n";
echo "---------------------------------\n";
$blocks = [];
$registry->invoke_alter('block_alter', $blocks);
if (isset($blocks['webform_contact'])) {
    echo "SUCCESS: webform_contact block found.\n";
    echo "Rendering block:\n";
    $html = $blocks['webform_contact']['handler']();
    echo substr($html, 0, 150) . "...\n";
} else {
    echo "ERROR: webform_contact block not found.\n";
}

echo "\n---------------------------------\n";
echo "3. Testing Webform Form Submission\n";
echo "---------------------------------\n";
// Simulate POST request to submit a contact form
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/lekhak/webform/submit/contact';
$_POST['webform_id'] = 1; // Assuming contact is ID 1
$_POST['data'] = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'message' => 'This is a test message from test_lekhak.php'
];

try {
    // Note: hook_request_init in Webform module calls exit; or header();
    // We will simulate it here manually to avoid the script terminating.
    $db = new \SPPMod\SPPDB\SPPDB();
    $db->execute_query(
        "INSERT INTO lekhak_webform_submissions (webform_id, data, submitted_at, ip_address) VALUES (?, ?, ?, ?)",
        [$_POST['webform_id'], json_encode($_POST['data']), date('Y-m-d H:i:s'), '127.0.0.1']
    );
    echo "SUCCESS: Simulated submission inserted into DB.\n";
    
    // Check DB
    $subs = $db->execute_query("SELECT * FROM lekhak_webform_submissions ORDER BY id DESC LIMIT 1");
    echo "Latest submission: " . print_r($subs[0], true) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n---------------------------------\n";
echo "4. Testing Rules Module (ECA Engine)\n";
echo "---------------------------------\n";
// Trigger entity insert to test Rules evaluating the node status
$node = (object)[
    'title' => 'Test Publish Node',
    'status' => 'published',
    'id' => 101
];
echo "Invoking entity_insert on node...\n";
$registry->invoke_all('entity_insert', $node);

if (!empty($_SESSION['spp_messages'])) {
    echo "SUCCESS: Rules module triggered a system message!\n";
    print_r($_SESSION['spp_messages']);
} else {
    echo "No messages triggered by rules.\n";
}

echo "\nTests Complete.\n";
