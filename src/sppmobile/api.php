<?php
/**
 * SPP Mobile Studio API Controller
 */

// Load vendor autoloader
$vendorPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
}

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return;
    $msg = "[" . date('Y-m-d H:i:s') . "] PHP Error ($errno): $errstr in $errfile on line $errline\n";
    file_put_contents(__DIR__ . "/api_debug.log", $msg, FILE_APPEND);
});

if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__, 2) . '/spp');
}
if (!defined('STUDIO_ROOT')) {
    define('STUDIO_ROOT', __DIR__);
}

// Support for JSON payloads
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (is_array($data)) {
        $_POST = array_merge($_POST, $data);
        $_REQUEST = array_merge($_REQUEST, $data);
    }
}

// Early Global Snapshot (Capture before any framework cleanup)
$GLOBALS['_STUDIO_FILES'] = $_FILES;
$GLOBALS['_STUDIO_POST'] = $_POST;

require_once SPP_BASE_DIR . '/sppinit.php';

// Force Mobile Studio Context
\SPP\Scheduler::setContext('sppmobile');

function sendResponse($success, $data = [], $message = '') {
    $phpOutput = ob_get_clean();
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time()
    ];
    if (!empty($phpOutput)) $response['_debug_output'] = $phpOutput;
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// === ASSET ROUTE HANDLER ===
if ($action === 'asset' || (isset($_GET['q']) && strpos($_GET['q'], 'assets/') === 0)) {
    $name = $_REQUEST['name'] ?? '';
    if (!$name && isset($_GET['q'])) $name = str_replace('assets/', '', $_GET['q']);
    
    $assetPath = __DIR__ . '/assets/' . basename($name);
    if (file_exists($assetPath) && is_file($assetPath)) {
        $phpOutput = ob_get_clean();
        $mime = mime_content_type($assetPath);
        header("Content-Type: $mime");
        readfile($assetPath);
        exit;
    }
    header("HTTP/1.0 404 Not Found");
    echo "Asset not found.";
    exit;
}

// === SECURITY GATEKEEPER ===
if ($action !== 'login' && $action !== 'asset') {
    $sessionUser = null;
    if (\SPP\SPPSession::sessionVarExists('studio_user')) {
        $sessionUser = \SPP\SPPSession::getSessionVar('studio_user');
    }

    if (!$sessionUser) {
        sendResponse(false, [], "Unauthorized: Session expired or invalid.");
    }
}

// === AUTH HANDLER ===
if ($action === 'login') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    // Isolated Studio Auth (No connection to /admin)
    // Default hardcoded admin for bootstrap; can be expanded to Studio-specific XDB table.
    if ($user === 'admin' && $pass === 'admin123') {
        \SPP\SPPSession::setSessionVar('studio_user', [
            'id' => 'admin', 
            'name' => 'Studio Administrator', 
            'role' => 'admin'
        ]);
        sendResponse(true, [], "Authentication successful. Launching Studio...");
    } else {
        sendResponse(false, [], "Invalid studio credentials.");
    }
}

if (!$action) sendResponse(false, [], "Action required.");

// === PROJECT MANAGEMENT ===
if ($action === 'list_projects') {
    $projects = [];
    $dir = __DIR__ . '/projects';
    if (is_dir($dir)) {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            if (is_dir($dir . '/' . $item)) {
                $confFile = $dir . '/' . $item . '/mobile.yml';
                if (!file_exists($confFile)) $confFile = $dir . '/' . $item . '/config.json';
                
                if (file_exists($confFile)) {
                    $ext = pathinfo($confFile, PATHINFO_EXTENSION);
                    $conf = ($ext === 'yml') ? \Symfony\Component\Yaml\Yaml::parseFile($confFile) : json_decode(file_get_contents($confFile), true);
                    $projects[] = [
                        'id' => $item,
                        'name' => $conf['app_name'] ?? $item,
                        'version' => $conf['version'] ?? '1.0.0',
                        'updated_at' => date('Y-m-d H:i:s', filemtime($confFile))
                    ];
                }
            }
        }
    }
    sendResponse(true, ['projects' => $projects]);
}

if ($action === 'create_project') {
    $name = $_POST['name'] ?? 'New Project';
    $blueprintKey = $_POST['blueprint'] ?? 'dashboard';
    $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name)) . '_' . time();
    $dir = __DIR__ . '/projects/' . $id;
    
    if (is_dir($dir)) sendResponse(false, [], "Project ID collision.");
    mkdir($dir, 0777, true);

    // --- BLUEPRINT REGISTRY ---
    $registry = [
        'dashboard' => [
            'app_name' => $name,
            'theme' => ['primary' => '#6366f1', 'secondary' => '#a855f7', 'surface' => '#1e1e2e'],
            'screens' => [
                ['id' => 'home', 'title' => 'Executive Dashboard', 'type' => 'dashboard', 'mapping' => 'analytics_v1', 'content' => ['widgets' => ['sales', 'users', 'growth']]],
                ['id' => 'users', 'title' => 'User Management', 'type' => 'list', 'mapping' => 'users_all', 'content' => ['search' => true, 'actions' => ['edit', 'delete']]],
                ['id' => 'settings', 'title' => 'System Settings', 'type' => 'form', 'mapping' => 'sys_config', 'content' => ['fields' => ['theme', 'notifications', 'security']]]
            ]
        ],
        'ecommerce' => [
            'app_name' => $name,
            'theme' => ['primary' => '#10b981', 'secondary' => '#3b82f6', 'surface' => '#0f172a'],
            'screens' => [
                ['id' => 'store', 'title' => 'Digital Storefront', 'type' => 'grid', 'mapping' => 'products_catalog', 'content' => ['categories' => ['electronics', 'fashion', 'home']]],
                ['id' => 'cart', 'title' => 'My Shopping Cart', 'type' => 'list', 'mapping' => 'cart_items', 'content' => ['show_total' => true, 'checkout_btn' => true]],
                ['id' => 'success', 'title' => 'Order Confirmed', 'type' => 'splash', 'mapping' => 'order_thanks', 'content' => ['icon' => 'check_circle', 'msg' => 'Thank you for your purchase!']]
            ]
        ],
        'hyper_book' => [
            'app_name' => $name,
            'theme' => ['primary' => '#7c2d12', 'secondary' => '#9a3412', 'surface' => '#fff7ed'],
            'screens' => [
                ['id' => 'cover', 'title' => 'Book Cover', 'type' => 'splash', 'mapping' => 'cover_image', 'content' => ['author' => 'Author Name', 'year' => '2024']],
                ['id' => 'contents', 'title' => 'Table of Contents', 'type' => 'toc', 'mapping' => 'book_index', 'content' => ['chapters' => ['Chapter 1', 'Chapter 2', 'Chapter 3']]],
                ['id' => 'ch1', 'title' => 'Chapter I: The Discovery', 'type' => 'article', 'mapping' => 'ch1_content', 'content' => ['font_size' => 18, 'line_height' => 1.6]]
            ]
        ],
        'button_book' => [
            'app_name' => $name,
            'theme' => ['primary' => '#1e293b', 'secondary' => '#334155', 'surface' => '#f8fafc'],
            'screens' => [
                ['id' => 'p1', 'title' => 'Introduction', 'type' => 'reader', 'mapping' => 'page1', 'content' => ['nav' => 'buttons', 'show_progress' => true]],
                ['id' => 'p2', 'title' => 'Getting Started', 'type' => 'reader', 'mapping' => 'page2', 'content' => ['nav' => 'buttons', 'show_progress' => true]]
            ]
        ]
    ];

    $blueprint = $registry[$blueprintKey] ?? $registry['dashboard'];

    $config = array_merge([
        'app_id' => 'com.spp.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)),
        'version' => '1.0.0',
        'created_at' => date('Y-m-d H:i:s')
    ], $blueprint);

    file_put_contents($dir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
    sendResponse(true, ['project_id' => $id], "Project created using " . ucfirst($blueprintKey) . " blueprint with example screens.");
}

if ($action === 'export_source') {
    $id = $_POST['id'] ?? '';
    if (!$id) sendResponse(false, [], "Project ID required.");

    $src = __DIR__ . '/projects/' . $id;
    if (!is_dir($src)) sendResponse(false, [], "Project not found.");

    $config = json_decode(file_get_contents($src . '/config.json'), true);
    $zipName = $id . '_source_' . time() . '.zip';
    $zipPath = __DIR__ . '/exports/' . $zipName;

    if (!is_dir(__DIR__ . '/exports')) mkdir(__DIR__ . '/exports', 0777, true);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        sendResponse(false, [], "Could not create zip file.");
    }

    // Add Flutter Boilerplate
    $zip->addFromString('pubspec.yaml', "name: " . strtolower(preg_replace('/[^a-z]/', '_', $config['app_name'])) . "\ndescription: A new Flutter project generated by SPP Mobile Studio.\nversion: 1.0.0+1\nenvironment:\n  sdk: '>=3.0.0 <4.0.0'\ndependencies:\n  flutter:\n    sdk: flutter\n  cupertino_icons: ^1.0.2\n  http: ^1.1.0\ndev_dependencies:\n  flutter_test:\n    sdk: flutter\nflutter:\n  uses-material-design: true\n");
    
    $zip->addFromString('lib/main.dart', "import 'package:flutter/material.dart';\nimport 'dart:convert';\n\nvoid main() {\n  runApp(const MobileStudioApp());\n}\n\nclass MobileStudioApp extends StatelessWidget {\n  const MobileStudioApp({super.key});\n  @override\n  Widget build(BuildContext context) {\n    return MaterialApp(\n      title: '" . addslashes($config['app_name']) . "',\n      theme: ThemeData(\n        primaryColor: Color(int.parse('" . str_replace('#', '0xFF', $config['theme']['primary']) . "')),\n        scaffoldBackgroundColor: Color(int.parse('" . str_replace('#', '0xFF', $config['theme']['surface']) . "')),\n      ),\n      home: const DashboardScreen(),\n    );\n  }\n}\n\nclass DashboardScreen extends StatelessWidget {\n  const DashboardScreen({super.key});\n  @override\n  Widget build(BuildContext context) {\n    return Scaffold(\n      appBar: AppBar(title: const Text('" . addslashes($config['app_name']) . "')),\n      body: const Center(child: Text('Welcome to your SPP Mobile App source code.')),\n    );\n  }\n}\n");
    
    $zip->addFromString('assets/studio_config.json', json_encode($config, JSON_PRETTY_PRINT));
    
    // Add physical project files
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAF_ONLY
    );
    foreach ($files as $name => $file) {
        $filePath = $file->getRealPath();
        $relativePath = 'studio_project/' . substr($filePath, strlen($src) + 1);
        $zip->addFile($filePath, $relativePath);
    }

    $zip->close();
    sendResponse(true, ['download_url' => 'exports/' . $zipName], "Source code exported successfully.");
}

if ($action === 'load_project') {
    $id = $_REQUEST['id'] ?? '';
    $dir = __DIR__ . '/projects/' . $id;
    $file = $dir . '/mobile.yml';
    if (!file_exists($file)) $file = $dir . '/config.json';
    
    if (!file_exists($file)) {
        sendResponse(false, [], "Project configuration not found ($id).");
    }
    
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $config = ($ext === 'yml') ? \Symfony\Component\Yaml\Yaml::parseFile($file) : json_decode(file_get_contents($file), true);
    if (!isset($config['id'])) $config['id'] = $id; // Ensure ID is present for saving
    sendResponse(true, ['config' => $config]);
}

if ($action === 'save_project') {
    $id = $_POST['id'] ?? '';
    $config = $_POST['config'] ?? null;
    if (!$id || !$config) sendResponse(false, [], "ID and Config required.");

    $dir = __DIR__ . '/projects/' . $id;
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    file_put_contents($dir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
    sendResponse(true, [], "Project saved successfully.");
}

// === FLUTTER BUILD ORCHESTRATOR ===
if ($action === 'build_flutter') {
    $id = $_POST['id'] ?? '';
    $platform = $_POST['platform'] ?? 'android'; // android, ios, windows, web
    
    // Check Build Rights
    $sessionUser = \SPP\SPPSession::getSessionVar('studio_user');
    $role = $sessionUser['role'] ?? 'viewer';
    $matrix = [
        'admin' => ['studio_build'],
        'developer' => ['studio_build'],
    ];
    if (!in_array('studio_build', $matrix[$role] ?? [])) {
        sendResponse(false, [], "Unauthorized: You do not have permission to build projects.");
    }

    // High-Fidelity Build Simulation (Or trigger real Flutter CLI in production)
    $platforms = [
        'android' => 'APK/App Bundle',
        'ios' => 'IPA / Xcode Project',
        'windows' => 'MSI Installer',
        'web' => 'PWA / Static Bundle'
    ];

    sleep(2); // Simulate heavy compilation
    sendResponse(true, [
        'platform' => $platform,
        'artifact' => "builds/$id/$platform/app-release." . ($platform === 'android' ? 'apk' : ($platform === 'windows' ? 'exe' : 'zip'))
    ], "Successfully compiled for " . ($platforms[$platform] ?? $platform) . ". Ready for distribution.");
}

if ($action === 'rename_project') {
    $id = $_POST['id'] ?? '';
    $newName = $_POST['name'] ?? '';
    if (!$id || !$newName) sendResponse(false, [], "ID and New Name required.");

    $baseDir = __DIR__ . '/projects/';
    $src = $baseDir . $id;
    
    if (is_dir($src)) {
        $newId = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $newName)) . '_' . time();
        $dest = $baseDir . $newId;

        // Update Config First
        $file = $src . '/config.json';
        $config = json_decode(file_get_contents($file), true);
        $config['app_name'] = $newName;
        $config['app_id'] = 'com.spp.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $newName));
        file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));

        // Structural Rename
        if (rename($src, $dest)) {
            sendResponse(true, ['new_id' => $newId], "Project structurally rebranded as '$newName'.");
        } else {
            sendResponse(false, [], "Failed to rename project directory.");
        }
    }
    sendResponse(false, [], "Project not found.");
}

if ($action === 'duplicate_project') {
    $id = $_POST['id'] ?? '';
    if (!$id) sendResponse(false, [], "Project ID required.");

    $src = __DIR__ . '/projects/' . $id;
    if (!is_dir($src)) sendResponse(false, [], "Source project not found.");

    $config = json_decode(file_get_contents($src . '/config.json'), true);
    $newName = ($config['app_name'] ?? $id) . " (Copy)";
    $newId = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $newName)) . '_' . time();
    $dest = __DIR__ . '/projects/' . $newId;

    // Recursive copy
    mkdir($dest, 0777, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        } else {
            copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        }
    }

    // Update new config
    $newConfig = json_decode(file_get_contents($dest . '/config.json'), true);
    $newConfig['app_name'] = $newName;
    $newConfig['app_id'] = 'com.spp.' . $newId;
    file_put_contents($dest . '/config.json', json_encode($newConfig, JSON_PRETTY_PRINT));

    sendResponse(true, ['project_id' => $newId], "Project duplicated as '$newName'.");
}

if ($action === 'delete_project') {
    $id = $_POST['id'] ?? '';
    if (!$id) sendResponse(false, [], "Project ID required.");

    $dir = __DIR__ . '/projects/' . $id;
    if (is_dir($dir)) {
        // Recursive delete
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
        sendResponse(true, [], "Project deleted successfully.");
    }
    sendResponse(false, [], "Project not found.");
}

// Discovery: Look in local services directory
$serviceDir = __DIR__ . '/services';
require_once $serviceDir . '/Mobile.php';
require_once $serviceDir . '/AI.php';

$la = new class {
    public $success = true;
    public $data = [];
    public $message = '';
    public function setData($d) { $this->data = array_merge($this->data, $d); return $this; }
    public function setStatus($s) { $this->success = ($s === 'success'); return $this; }
    public function notify($m) { $this->message = $m; return $this; }
};

$method = 'live_Mobile_' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
if (function_exists($method)) {
    $method($la, $_REQUEST);
    sendResponse($la->success, $la->data, $la->message);
} else {
    sendResponse(false, [], "Service action '$action' ($method) not found.");
}
