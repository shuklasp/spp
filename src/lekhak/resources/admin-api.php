<?php
/**
 * Lekhak Admin API
 * Self-contained entry point for Lekhak CMS management.
 */
if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__, 3) . '/spp');
}
require_once SPP_BASE_DIR . '/sppinit.php';

// Force Lekhak Context
$appname = 'lekhak';
try {
    try {
        \SPP\Scheduler::getProcObj($appname);
    } catch (\Exception $e) {
        new \SPP\App($appname, false, 1); 
    }
    \SPP\Scheduler::setContext($appname);
} catch (\Exception $e) {
    error_log("Lekhak API Error: Context failed - " . $e->getMessage());
}

// Security Gate: Ensure user is authenticated
use SPPMod\SPPAuth\SPPAuth;
$isAuthenticated = false;
try {
    $isAuthenticated = SPPAuth::authSessionExists() || isset($_SESSION['spp_admin_fallback']);
} catch (\Exception $e) {
    $isAuthenticated = isset($_SESSION['spp_admin_fallback']);
}

if (!$isAuthenticated) {
    // We don't have sendResponse defined yet, but we'll define it below or use a quick exit
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Session expired. Please login."]);
    exit;
}

if (!function_exists('sendResponse')) {
function sendResponse($success, $data = [], $message = '') {
    if (ob_get_level()) ob_get_clean();
    header('Content-Type: application/json');
    $json = json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ] + $data);
    
    if ($json === false) {
        $err = json_last_error_msg();
        echo json_encode(['success' => false, 'message' => "JSON Encode Error: $err"]);
    } else {
        echo $json;
    }
    exit;
}
}

if (!function_exists('_lekhak_dir_size')) {
function _lekhak_dir_size($dir) {
    $size = 0;
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}
}

if (!function_exists('_lekhak_format_bytes')) {
function _lekhak_format_bytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
}

if (!function_exists('_lekhak_ensure_table')) {
function _lekhak_ensure_table($db, $table, $schema) {
    try {
        $db->execute_query("SELECT 1 FROM {$table} LIMIT 1");
    } catch (\Exception $e) {
        $db->execute_query("CREATE TABLE IF NOT EXISTS {$table} ({$schema})");
    }
}
}

try {
    // Handle JSON input
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (is_array($data)) {
            $_POST = array_merge($_POST, $data);
            $_REQUEST = array_merge($_REQUEST, $data);
        }
    }

    $action = $_REQUEST['action'] ?? '';

    // Route modular actions to Lekhni
    if (str_starts_with($action, 'lekhni_')) {
        $modularAction = substr($action, 7);
        $res = \SPPMod\Lekhni\Api\LekhniApi::handleRequest($modularAction, $_REQUEST);
        sendResponse($res['success'], $res, $res['message'] ?? '');
    }

    // Auto-install/verify nodes table schema gracefully to prevent DB exception loops
    \SPPMod\Lekhak\Core\LekhakNode::install();

    switch ($action) {
        case 'get_system_status':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('nodes');
            $total = $db->execute_query("SELECT COUNT(*) as count FROM {$table}")[0]['count'];
            $mediaPath = dirname(__DIR__) . '/var/media';
            $diskUsage = is_dir($mediaPath) ? _lekhak_dir_size($mediaPath) : 0;
            $configPath = \SPP\App::getApp()->getAppConfDir() . '/drishyam.yml';
            $activeTheme = 'premium';
            if (file_exists($configPath)) {
                $dc = \Symfony\Component\Yaml\Yaml::parseFile($configPath);
                $activeTheme = $dc['contexts']['site'] ?? 'premium';
            }
            sendResponse(true, [
                'status' => [
                    'php_version' => PHP_VERSION,
                    'db_engine' => 'SQLite (SPPXDB)',
                    'active_theme' => $activeTheme,
                    'total_content' => (int)$total,
                    'media_disk' => _lekhak_format_bytes($diskUsage),
                    'spp_version' => defined('SPP_VERSION') ? SPP_VERSION : '11.x',
                    'server' => php_uname('s') . ' ' . php_uname('r'),
                    'memory_limit' => ini_get('memory_limit')
                ]
            ]);
            break;

        case 'get_dashboard_stats':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('nodes');
            $total = $db->execute_query("SELECT COUNT(*) as count FROM {$table}")[0]['count'];
            $published = $db->execute_query("SELECT COUNT(*) as count FROM {$table} WHERE status='published'")[0]['count'];
            $drafts = $db->execute_query("SELECT COUNT(*) as count FROM {$table} WHERE status='draft'")[0]['count'];
            $recent = $db->execute_query("SELECT id, title, status, bundle, changed FROM {$table} ORDER BY changed DESC LIMIT 10");
            sendResponse(true, [
                'stats' => [
                    'total' => (int)$total,
                    'published' => (int)$published,
                    'drafts' => (int)$drafts,
                    'engagement' => min(100, (int)$published * 42)
                ],
                'recent' => $recent
            ]);
            break;

        case 'list_nodes':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('nodes');
            $page = max(1, (int)($_REQUEST['page'] ?? 1));
            $limit = max(1, min(100, (int)($_REQUEST['limit'] ?? 20)));
            $sort = in_array($_REQUEST['sort'] ?? '', ['title','status','changed','bundle']) ? $_REQUEST['sort'] : 'changed';
            $order = strtoupper($_REQUEST['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $bundle = $_REQUEST['bundle'] ?? '';
            $offset = ($page - 1) * $limit;

            $where = '';
            if ($bundle) $where = "WHERE bundle=" . $db->quote($bundle);
            $totalRows = $db->execute_query("SELECT COUNT(*) as count FROM {$table} {$where}")[0]['count'];
            $nodes = $db->execute_query("SELECT id, title, status, bundle, changed FROM {$table} {$where} ORDER BY {$sort} {$order} LIMIT {$limit} OFFSET {$offset}");
            sendResponse(true, [
                'nodes' => $nodes,
                'total' => (int)$totalRows,
                'page' => $page,
                'pages' => max(1, ceil((int)$totalRows / $limit)),
                'limit' => $limit
            ]);
            break;

        case 'bulk_action':
            $bulkOp = $_POST['operation'] ?? '';
            $ids = $_POST['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) sendResponse(false, [], "No items selected.");
            $count = 0;
            foreach ($ids as $nid) {
                try {
                    $node = new \SPPMod\Lekhak\Core\LekhakNode($nid);
                    if ($bulkOp === 'delete') {
                        $node->delete(); $count++;
                    } elseif ($bulkOp === 'publish') {
                        $node->status = 'published'; $node->changed = date("Y-m-d H:i:s"); $node->save(); $count++;
                    } elseif ($bulkOp === 'unpublish') {
                        $node->status = 'draft'; $node->changed = date("Y-m-d H:i:s"); $node->save(); $count++;
                    }
                } catch (\Exception $e) { /* skip failed items */ }
            }
            sendResponse(true, ['affected' => $count], "Bulk operation completed: {$count} items affected.");
            break;

        case 'get_node':
            $id = $_REQUEST['id'] ?? null;
            if (!$id) sendResponse(false, [], "ID required.");
            $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
            sendResponse(true, ['node' => [
                'id' => $node->id, 'title' => $node->title, 'body' => $node->body,
                'status' => $node->status, 'alias' => $node->alias,
                'bundle' => $node->bundle ?? 'Article',
                'created' => $node->created ?? '', 'changed' => $node->changed
            ]]);
            break;

        case 'publish':
            $_POST['status'] = 'published';
        case 'save_node':
            $id = $_POST['id'] ?? null;
            $title = $_POST['title'] ?? 'Untitled Document';
            $body = $_POST['body'] ?? '';
            $status = $_POST['status'] ?? 'draft';
            $bundle = $_POST['bundle'] ?? null;
            $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
            $node->title = $title;
            $node->body = $body;
            $node->status = $status;
            if ($bundle) {
                $node->bundle = $bundle;
            } elseif (!$id && empty($node->bundle)) {
                $node->bundle = 'Article';
            }
            $node->changed = date("Y-m-d H:i:s");
            if (!$id) {
                $node->created = date("Y-m-d H:i:s");
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
                $node->alias = $slug . '-' . time();
            }
            try {
                $node->save();
                $baseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
                $publicUrl = rtrim($baseUri, '/') . '/lekhak/node/' . $node->alias;
                sendResponse(true, ['id' => $node->id, 'url' => $publicUrl],
                    "Document " . ($status === 'published' ? 'published' : 'saved') . " successfully.");
            } catch (\Exception $e) {
                sendResponse(false, [], "Save failed: " . $e->getMessage());
            }
            break;

        case 'delete_node':
            $id = $_REQUEST['id'] ?? null;
            if (!$id) sendResponse(false, [], "ID required.");
            try {
                $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
                $node->delete();
                sendResponse(true, [], "Document deleted successfully.");
            } catch (\Exception $e) {
                sendResponse(false, [], "Delete failed: " . $e->getMessage());
            }
            break;

        case 'upload_media':
            $mediaDir = dirname(__DIR__) . '/var/media/lekhni';
            if (!is_dir($mediaDir)) @mkdir($mediaDir, 0755, true);
            if (empty($_FILES['file'])) sendResponse(false, [], "No file uploaded.");
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','svg','mp4','pdf'];
            if (!in_array($ext, $allowed)) sendResponse(false, [], "File type not allowed.");
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $dest = $mediaDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $baseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
                $url = rtrim($baseUri, '/') . '/src/lekhak/var/media/lekhni/' . $filename;
                sendResponse(true, ['url' => $url, 'filename' => $filename], "File uploaded.");
            } else {
                sendResponse(false, [], "Upload failed.");
            }
            break;

        case 'list_media':
            $mediaDir = dirname(__DIR__) . '/var/media/lekhni';
            $files = [];
            if (is_dir($mediaDir)) {
                $baseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
                foreach (scandir($mediaDir) as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $fp = $mediaDir . '/' . $f;
                    $files[] = [
                        'name' => $f,
                        'url' => rtrim($baseUri, '/') . '/src/lekhak/var/media/lekhni/' . $f,
                        'size' => _lekhak_format_bytes(filesize($fp)),
                        'modified' => date("Y-m-d H:i:s", filemtime($fp)),
                        'type' => mime_content_type($fp) ?: 'application/octet-stream'
                    ];
                }
                usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));
            }
            sendResponse(true, ['files' => $files, 'total' => count($files)]);
            break;

        case 'delete_media':
            $filename = $_POST['filename'] ?? '';
            if (!$filename) sendResponse(false, [], "Filename required.");
            $mediaDir = dirname(__DIR__) . '/var/media/lekhni';
            $fp = $mediaDir . '/' . basename($filename);
            if (file_exists($fp)) {
                @unlink($fp);
                sendResponse(true, [], "File deleted.");
            } else {
                sendResponse(false, [], "File not found.");
            }
            break;

        case 'list_products':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('products');
            _lekhak_ensure_table($db, $table, "id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255), sku VARCHAR(50), price VARCHAR(20) DEFAULT '\$0.00', stock INTEGER DEFAULT 0, active INTEGER DEFAULT 1, created TEXT, changed TEXT");
            $products = $db->execute_query("SELECT * FROM {$table} ORDER BY changed DESC");
            sendResponse(true, ['products' => $products]);
            break;

        case 'save_product':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('products');
            _lekhak_ensure_table($db, $table, "id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255), sku VARCHAR(50), price VARCHAR(20) DEFAULT '\$0.00', stock INTEGER DEFAULT 0, active INTEGER DEFAULT 1, created TEXT, changed TEXT");
            $pid = $_POST['id'] ?? null;
            $pTitle = $_POST['title'] ?? 'New Product';
            $pSku = $_POST['sku'] ?? 'SKU-' . strtoupper(substr(md5(time()), 0, 6));
            $pPrice = $_POST['price'] ?? '$0.00';
            $pStock = (int)($_POST['stock'] ?? 0);
            $pActive = (int)($_POST['active'] ?? 1);
            $now = date("Y-m-d H:i:s");
            if ($pid) {
                $db->execute_query("UPDATE {$table} SET title=?, sku=?, price=?, stock=?, active=?, changed=? WHERE id=?",
                    [$pTitle, $pSku, $pPrice, $pStock, $pActive, $now, $pid]);
            } else {
                $db->execute_query("INSERT INTO {$table} (title, sku, price, stock, active, created, changed) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$pTitle, $pSku, $pPrice, $pStock, $pActive, $now, $now]);
                $pid = $db->execute_query("SELECT last_insert_rowid() as id")[0]['id'] ?? null;
            }
            sendResponse(true, ['id' => $pid], "Product saved.");
            break;

        case 'delete_product':
            $pid = $_REQUEST['id'] ?? null;
            if (!$pid) sendResponse(false, [], "Product ID required.");
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('products');
            $db->execute_query("DELETE FROM {$table} WHERE id=?", [$pid]);
            sendResponse(true, [], "Product deleted.");
            break;

        case 'get_locales':
            $localesPath = \SPP\App::getApp()->getAppConfDir() . '/locales.yml';
            $locales = [];
            if (file_exists($localesPath)) {
                $locales = \Symfony\Component\Yaml\Yaml::parseFile($localesPath) ?: [];
            }
            if (empty($locales)) {
                $locales = [
                    ['id' => 'en-us', 'flag' => '🇺🇸', 'name' => 'English (United States)', 'code' => 'en_US', 'progress' => 100, 'status' => 'active'],
                    ['id' => 'hi-in', 'flag' => '🇮🇳', 'name' => 'Hindi (India)', 'code' => 'hi_IN', 'progress' => 0, 'status' => 'ghost']
                ];
            }
            sendResponse(true, ['locales' => $locales]);
            break;

        case 'save_locale':
            $localesPath = \SPP\App::getApp()->getAppConfDir() . '/locales.yml';
            $locales = file_exists($localesPath) ? (\Symfony\Component\Yaml\Yaml::parseFile($localesPath) ?: []) : [];
            $loc = $_POST;
            unset($loc['action']);
            $found = false;
            foreach ($locales as &$l) {
                if ($l['id'] === $loc['id']) { $l = array_merge($l, $loc); $found = true; break; }
            }
            unset($l);
            if (!$found) $locales[] = $loc;
            file_put_contents($localesPath, \Symfony\Component\Yaml\Yaml::dump($locales, 4, 2));
            sendResponse(true, [], "Locale saved.");
            break;

        case 'delete_locale':
            $localesPath = \SPP\App::getApp()->getAppConfDir() . '/locales.yml';
            $locales = file_exists($localesPath) ? (\Symfony\Component\Yaml\Yaml::parseFile($localesPath) ?: []) : [];
            $delId = $_POST['id'] ?? '';
            $locales = array_values(array_filter($locales, fn($l) => $l['id'] !== $delId));
            file_put_contents($localesPath, \Symfony\Component\Yaml\Yaml::dump($locales, 4, 2));
            sendResponse(true, [], "Locale removed.");
            break;

        case 'list_landing_pages':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_pages');
            _lekhak_ensure_table($db, $table, "id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255), alias VARCHAR(100), layout TEXT, created TEXT, changed TEXT");
            $pages = $db->execute_query("SELECT id, title, alias, changed FROM {$table} ORDER BY changed DESC");
            sendResponse(true, ['pages' => $pages]);
            break;

        case 'get_landing_page':
            $id = $_REQUEST['id'] ?? null;
            if (!$id) sendResponse(false, [], "ID required.");
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_pages');
            $page = $db->execute_query("SELECT * FROM {$table} WHERE id=?", [$id])[0] ?? null;
            if (!$page) sendResponse(false, [], "Landing page not found.");
            sendResponse(true, ['page' => $page]);
            break;

        case 'save_landing_page':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_pages');
            _lekhak_ensure_table($db, $table, "id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255), alias VARCHAR(100), layout TEXT, created TEXT, changed TEXT");
            $id = $_POST['id'] ?? null;
            $title = $_POST['title'] ?? 'New Landing Page';
            $alias = $_POST['alias'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title)) . '-' . time();
            $layout = $_POST['layout'] ?? '[]';
            $now = date("Y-m-d H:i:s");
            if ($id) {
                $db->execute_query("UPDATE {$table} SET title=?, alias=?, layout=?, changed=? WHERE id=?",
                    [$title, $alias, $layout, $now, $id]);
            } else {
                $db->execute_query("INSERT INTO {$table} (title, alias, layout, created, changed) VALUES (?, ?, ?, ?, ?)",
                    [$title, $alias, $layout, $now, $now]);
                $id = $db->execute_query("SELECT last_insert_rowid() as id")[0]['id'] ?? null;
            }
            sendResponse(true, ['id' => $id], "Landing page saved.");
            break;

        case 'delete_landing_page':
            $id = $_REQUEST['id'] ?? null;
            if (!$id) sendResponse(false, [], "ID required.");
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_pages');
            $db->execute_query("DELETE FROM {$table} WHERE id=?", [$id]);
            sendResponse(true, [], "Landing page deleted.");
            break;

        case 'list_blocks':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks');
            _lekhak_ensure_table($db, $table, "id INTEGER PRIMARY KEY AUTOINCREMENT, block_type VARCHAR(50), region VARCHAR(50), weight INTEGER DEFAULT 0, page_id INTEGER DEFAULT 0, data TEXT, created TEXT, changed TEXT");
            $blocks = $db->execute_query("SELECT * FROM {$table} ORDER BY region ASC, weight ASC");
            foreach ($blocks as &$b) {
                $b['data'] = json_decode($b['data'] ?? '{}', true);
            }
            unset($b);
            sendResponse(true, ['blocks' => $blocks]);
            break;

        case 'save_block':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks');
            _lekhak_ensure_table($db, $table, "id INTEGER PRIMARY KEY AUTOINCREMENT, block_type VARCHAR(50), region VARCHAR(50), weight INTEGER DEFAULT 0, page_id INTEGER DEFAULT 0, data TEXT, created TEXT, changed TEXT");
            
            $bid = $_POST['id'] ?? null;
            $blockType = $_POST['block_type'] ?? 'custom_html';
            $region = $_POST['region'] ?? 'sidebar_first';
            $weight = (int)($_POST['weight'] ?? 0);
            $pageId = (int)($_POST['page_id'] ?? 0);
            
            $blockData = $_POST['data'] ?? [];
            if (is_string($blockData)) {
                $blockData = json_decode($blockData, true) ?: [];
            }
            $dataStr = json_encode($blockData);
            $now = date("Y-m-d H:i:s");
            
            if ($bid) {
                $db->execute_query("UPDATE {$table} SET block_type=?, region=?, weight=?, page_id=?, data=?, changed=? WHERE id=?",
                    [$blockType, $region, $weight, $pageId, $dataStr, $now, $bid]);
            } else {
                $db->execute_query("INSERT INTO {$table} (block_type, region, weight, page_id, data, created, changed) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$blockType, $region, $weight, $pageId, $dataStr, $now, $now]);
                $bid = $db->execute_query("SELECT last_insert_rowid() as id")[0]['id'] ?? null;
            }
            sendResponse(true, ['id' => $bid], "Block saved successfully.");
            break;

        case 'delete_block':
            $bid = $_REQUEST['id'] ?? null;
            if (!$bid) sendResponse(false, [], "Block ID required.");
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks');
            $db->execute_query("DELETE FROM {$table} WHERE id=?", [$bid]);
            sendResponse(true, [], "Block deleted successfully.");
            break;

        case 'list_types':
            sendResponse(true, [
                'types' => [
                    ['id' => 'custom_html', 'name' => 'Custom HTML Block', 'description' => 'Render arbitrary HTML markup directly into a template region.'],
                    ['id' => 'dynamic_view', 'name' => 'Drupal Views Dynamic Query', 'description' => 'Fetch Lekhak CMS Nodes dynamically with sorting, limit, and responsive layouts.'],
                    ['id' => 'text', 'name' => 'Simple Markdown/Text', 'description' => 'A basic textual element matching system aesthetic guidelines.']
                ]
            ]);
            break;

        case 'get_settings':
            $settingsPath = \SPP\App::getApp()->getAppConfDir() . '/settings.yml';
            $settingsConfig = [];
            if (file_exists($settingsPath)) {
                $settingsConfig = \Symfony\Component\Yaml\Yaml::parseFile($settingsPath) ?: [];
            }
            $defaults = [
                'enable_edge_consensus' => true, 'enable_merkle_trace' => false,
                'speculative_offline' => true, 'strict_sri' => false,
                'ambient_scale' => '1.05', 'primary_accent' => '#f97316'
            ];
            $configs = array_merge($defaults, $settingsConfig);
            $configPath = \SPP\App::getApp()->getAppConfDir() . '/drishyam.yml';
            $activeAdmin = 'glass_admin'; $activeSite = 'premium';
            if (file_exists($configPath)) {
                $drishConfig = \Symfony\Component\Yaml\Yaml::parseFile($configPath);
                $activeAdmin = $drishConfig['contexts']['admin'] ?? 'glass_admin';
                $activeSite = $drishConfig['contexts']['site'] ?? 'premium';
            }
            sendResponse(true, ['configs' => $configs, 'activeAdminTheme' => $activeAdmin, 'activeSiteTheme' => $activeSite]);
            break;

        case 'save_settings':
            $adminTheme = $_POST['adminTheme'] ?? null;
            $siteTheme = $_POST['siteTheme'] ?? null;
            $configs = $_POST['configs'] ?? null;
            $configPath = \SPP\App::getApp()->getAppConfDir() . '/drishyam.yml';
            try {
                $config = file_exists($configPath) ? (\Symfony\Component\Yaml\Yaml::parseFile($configPath) ?: []) : [];
                if ($adminTheme) $config['contexts']['admin'] = $adminTheme;
                if ($siteTheme) $config['contexts']['site'] = $siteTheme;
                file_put_contents($configPath, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));
                if ($configs) {
                    $settingsPath = \SPP\App::getApp()->getAppConfDir() . '/settings.yml';
                    $sc = file_exists($settingsPath) ? (\Symfony\Component\Yaml\Yaml::parseFile($settingsPath) ?: []) : [];
                    file_put_contents($settingsPath, \Symfony\Component\Yaml\Yaml::dump(array_merge($sc, $configs), 4, 2));
                }
                sendResponse(true, [], "Configuration saved successfully.");
            } catch (\Exception $e) {
                sendResponse(false, [], "Failed to save configuration: " . $e->getMessage());
            }
            break;

        default:
            sendResponse(false, [], "Unknown action: $action");
            break;
    }
} catch (\Throwable $e) {
    sendResponse(false, [], "API Error: " . $e->getMessage());
}
