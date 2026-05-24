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

if (!function_exists('_lekhak_slugify')) {
function _lekhak_slugify($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $converted = function_exists('iconv') ? @iconv('utf-8', 'us-ascii//TRANSLIT', $text) : false;
    if ($converted !== false) $text = $converted;
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
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

        case 'get_sankhyaki_stats':
            if (class_exists('\\Lekhak\\Modules\\Sankhyaki\\Controller\\StatsController')) {
                $ctrl = new \Lekhak\Modules\Sankhyaki\Controller\StatsController();
                echo $ctrl->getStats();
                exit;
            } else {
                sendResponse(false, [], "Sankhyaki module is not enabled.");
            }
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
            $lang = $_REQUEST['lang'] ?? null;
            if (!$id) sendResponse(false, [], "ID required.");
            $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
            
            if ($lang) {
                $node->setLanguage($lang);
            }
            
            $data = [
                'id' => $node->id, 'title' => $node->title, 'body' => $node->body,
                'status' => $node->status, 'alias' => $node->alias,
                'bundle' => $node->bundle ?? 'Article',
                'created' => $node->created ?? '', 'changed' => $node->changed
            ];
            sendResponse(true, ['node' => $data]);
            break;

        case 'publish':
            $_POST['status'] = 'published';
        case 'save_node':
            $id = $_POST['id'] ?? null;
            $lang = $_POST['lang'] ?? null;
            
            if ($lang && $id) {
                $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
                $node->setLanguage($lang);
                
                if (isset($_POST['title'])) $node->title = $_POST['title'];
                if (isset($_POST['body'])) $node->body = $_POST['body'];
                if (isset($_POST['alias'])) $node->alias = $_POST['alias'];
                
                $node->save();
                sendResponse(true, ['id' => $id, 'url' => ''], "Translation saved successfully.");
                break;
            }

            $title = $_POST['title'] ?? 'Untitled Document';
            $body = $_POST['body'] ?? '';
            $status = $_POST['status'] ?? 'draft';
            $bundle = $_POST['bundle'] ?? null;
            $postedAlias = trim((string)($_POST['alias'] ?? ''));
            $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
            $node->title = $title;
            $node->body = $body;
            $node->status = $status;
            if ($postedAlias !== '') {
                $node->alias = _lekhak_slugify($postedAlias);
            }
            if ($bundle) {
                $node->bundle = $bundle;
            } elseif (!$id && empty($node->bundle)) {
                $node->bundle = 'Article';
            }
            $node->changed = date("Y-m-d H:i:s");
            if (!$id) {
                $node->created = date("Y-m-d H:i:s");
                if (empty($node->alias)) {
                    $slug = _lekhak_slugify($title) ?: 'untitled-document';
                    $node->alias = $slug . '-' . time();
                }
            }
            try {
                $node->save();
                $baseUri = defined('APP_BASE_URI') ? APP_BASE_URI : '';
                $publicUrl = rtrim($baseUri, '/') . '/lekhak/node/' . $node->alias;
                sendResponse(true, ['id' => $node->id, 'alias' => $node->alias, 'url' => $publicUrl],
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

        case 'rename_media':
            $oldName = $_POST['oldName'] ?? '';
            $newName = $_POST['newName'] ?? '';
            if (!$oldName || !$newName) sendResponse(false, [], "Both old and new filenames are required.");
            $mediaDir = dirname(__DIR__) . '/var/media/lekhni';
            
            // Clean up names for security
            $oldName = basename($oldName);
            $newName = basename($newName);
            
            $oldFp = $mediaDir . '/' . $oldName;
            $newFp = $mediaDir . '/' . $newName;
            
            if (!file_exists($oldFp)) {
                sendResponse(false, [], "Original file not found.");
            } elseif (file_exists($newFp)) {
                sendResponse(false, [], "A file with the new name already exists.");
            } else {
                if (rename($oldFp, $newFp)) {
                    sendResponse(true, [], "File renamed.");
                } else {
                    sendResponse(false, [], "Failed to rename file.");
                }
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
                $driver = method_exists($db, 'getDriver') ? $db->getDriver() : 'sqlite';
                if ($driver === 'sqlite') {
                    $pid = $db->execute_query("SELECT last_insert_rowid() as id")[0]['id'] ?? null;
                } else {
                    $pid = $db->execute_query("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? null;
                }
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

        case 'get_revisions':
            $id = $_REQUEST['id'] ?? null;
            if (!$id) sendResponse(false, [], "ID required.");
            $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
            $revs = $node->getRevisions();
            sendResponse(true, ['revisions' => $revs]);
            break;

        case 'restore_revision':
            $id = $_POST['id'] ?? null;
            $rev_id = $_POST['rev_id'] ?? null;
            if (!$id || !$rev_id) sendResponse(false, [], "ID and Rev ID required.");
            $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
            if ($node->restoreRevision($rev_id)) {
                sendResponse(true, [], "Revision restored.");
            } else {
                sendResponse(false, [], "Revision not found.");
            }
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
                $driver = method_exists($db, 'getDriver') ? $db->getDriver() : 'sqlite';
                if ($driver === 'sqlite') {
                    $id = $db->execute_query("SELECT last_insert_rowid() as id")[0]['id'] ?? null;
                } else {
                    $id = $db->execute_query("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? null;
                }
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
            
            $module = $_POST['module'] ?? '';
            $title = trim($blockData['title'] ?? 'Untitled Block');
            
            if ($module && !str_starts_with($title, $module . ': ')) {
                $title = $module . ': ' . $title;
                $blockData['title'] = $title;
            }
            
            // Check for duplicate title
            $allBlocks = $db->execute_query("SELECT id, data FROM {$table}");
            foreach ($allBlocks as $b) {
                if ($bid && $b['id'] == $bid) continue;
                $bData = json_decode($b['data'] ?? '{}', true);
                if (($bData['title'] ?? '') === $title) {
                    sendResponse(false, [], "A block with the title '{$title}' already exists.");
                    exit;
                }
            }
            
            $dataStr = json_encode($blockData);
            $now = date("Y-m-d H:i:s");
            
            if ($bid) {
                $db->execute_query("UPDATE {$table} SET block_type=?, region=?, weight=?, page_id=?, data=?, changed=? WHERE id=?",
                    [$blockType, $region, $weight, $pageId, $dataStr, $now, $bid]);
            } else {
                $db->execute_query("INSERT INTO {$table} (block_type, region, weight, page_id, data, created, changed) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$blockType, $region, $weight, $pageId, $dataStr, $now, $now]);
                $driver = method_exists($db, 'getDriver') ? $db->getDriver() : 'sqlite';
                if ($driver === 'sqlite') {
                    $bid = $db->execute_query("SELECT last_insert_rowid() as id")[0]['id'] ?? null;
                } else {
                    $bid = $db->execute_query("SELECT LAST_INSERT_ID() as id")[0]['id'] ?? null;
                }
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
            $types = [
                ['id' => 'custom_html', 'name' => 'Custom HTML Block', 'description' => 'Render arbitrary HTML markup directly into a template region.'],
                ['id' => 'dynamic_view', 'name' => 'Lekhak Views Dynamic Query', 'description' => 'Fetch Lekhak CMS Nodes dynamically with sorting, limit, and responsive layouts.'],
                ['id' => 'text', 'name' => 'Simple Markdown/Text', 'description' => 'A basic textual element matching system aesthetic guidelines.']
            ];
            
            // Allow modules to register their own placeable block types!
            $module_blocks = [];
            lekhak_invoke_alter('block_alter', $module_blocks);
            foreach ($module_blocks as $block_id => $block_def) {
                $types[] = [
                    'id' => $block_id,
                    'name' => $block_def['title'] ?? $block_id,
                    'description' => 'Provided by a Lekhak module. Renders dynamic content.'
                ];
            }
            
            sendResponse(true, ['types' => $types]);
            break;

        case 'get_settings':
            $settingsPath = \SPP\App::getApp()->getAppConfDir() . '/settings.yml';
            $settingsConfig = [];
            if (file_exists($settingsPath)) {
                $settingsConfig = \Symfony\Component\Yaml\Yaml::parseFile($settingsPath) ?: [];
            }
            $defaults = [
                'theme' => 'dark',
                'enable_edge_consensus' => true, 'enable_merkle_trace' => false,
                'speculative_offline' => true, 'strict_sri' => false,
                'ambient_scale' => '1.05', 'primary_accent' => '#f97316',
                'lekhni_default_mode' => 'document', 'lekhni_ai_copilot' => false,
                'lekhni_code_language' => 'html', 'designer_grid_snap' => true,
                'designer_autosave' => 300, 'structure_strict_schema' => false,
                'content_default_status' => 'draft', 'content_revision_tracking' => true
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

        case 'list_modules':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('lekhak_modules');
            _lekhak_ensure_table($db, $table, "machine_name VARCHAR(100) PRIMARY KEY, status INTEGER DEFAULT 0, installed_at TEXT");
            $installed = $db->execute_query("SELECT * FROM {$table}");
            $statusMap = [];
            foreach ($installed as $m) {
                $statusMap[$m['machine_name']] = (int)$m['status'];
            }
            // Master Registry of 50 Modules
            $masterList = [
                // Site Building
                ['machine_name' => 'views', 'title' => 'Views', 'category' => 'Site Building', 'desc' => 'Create customized lists and queries from your database.'],
                ['machine_name' => 'pathauto', 'title' => 'Pathauto', 'category' => 'Site Building', 'desc' => 'Automatically generates URL/path aliases for various kinds of content.'],
                ['machine_name' => 'token', 'title' => 'Token', 'category' => 'Site Building', 'desc' => 'Provides a shared API for replacement of textual placeholders with actual data.'],
                ['machine_name' => 'ctools', 'title' => 'Chaos Tool Suite', 'category' => 'Site Building', 'desc' => 'A set of APIs and tools to improve developer experience.'],
                ['machine_name' => 'panelizer', 'title' => 'Panelizer', 'category' => 'Site Building', 'desc' => 'Allows you to attach panels to any node in the system.'],
                ['machine_name' => 'entity_api', 'title' => 'Entity API', 'category' => 'Site Building', 'desc' => 'Extends the entity API of core, in order to provide a unified way to deal with entities.'],
                ['machine_name' => 'display_suite', 'title' => 'Display Suite', 'category' => 'Site Building', 'desc' => 'Allows you to take full control over how your content is displayed.'],
                ['machine_name' => 'rules', 'title' => 'Rules', 'category' => 'Site Building', 'desc' => 'Allows site administrators to define conditionally executed actions based on occurring events.'],
                ['machine_name' => 'features', 'title' => 'Features', 'category' => 'Site Building', 'desc' => 'Enables the capture and management of features in Drupal.'],
                ['machine_name' => 'libraries', 'title' => 'Libraries API', 'category' => 'Site Building', 'desc' => 'Provides a common repository for libraries in the CMS.'],

                // SEO & Routing
                ['machine_name' => 'metatag', 'title' => 'Metatag', 'category' => 'SEO & Routing', 'desc' => 'Allows you to automatically provide structured metadata.'],
                ['machine_name' => 'redirect', 'title' => 'Redirect', 'category' => 'SEO & Routing', 'desc' => 'Provides the ability to redirect URLs.'],
                ['machine_name' => 'simple_sitemap', 'title' => 'Simple XML sitemap', 'category' => 'SEO & Routing', 'desc' => 'Generates SEO-friendly XML sitemaps.'],
                ['machine_name' => 'google_analytics', 'title' => 'Google Analytics', 'category' => 'SEO & Routing', 'desc' => 'Adds the Google Analytics web statistics tracking system.'],
                ['machine_name' => 'seo_checklist', 'title' => 'SEO Checklist', 'category' => 'SEO & Routing', 'desc' => 'A checklist of best practices to optimize site for search engines.'],
                ['machine_name' => 'xmlsitemap', 'title' => 'XML sitemap', 'category' => 'SEO & Routing', 'desc' => 'Creates a sitemap that conforms to the sitemaps.org specification.'],
                ['machine_name' => 'rabbit_hole', 'title' => 'Rabbit Hole', 'category' => 'SEO & Routing', 'desc' => 'Control what should happen when an entity is viewed at its own page.'],
                ['machine_name' => 'schema_metatag', 'title' => 'Schema.org Metatag', 'category' => 'SEO & Routing', 'desc' => 'Extends Metatag to support Schema.org markup.'],
                ['machine_name' => 'yoast_seo', 'title' => 'Real-time SEO', 'category' => 'SEO & Routing', 'desc' => 'Helps you optimize content around keywords in a fast and easy way.'],
                ['machine_name' => 'search_api', 'title' => 'Search API', 'category' => 'SEO & Routing', 'desc' => 'Provides a framework for easily creating searches on any entity.'],

                // Security & Administration
                ['machine_name' => 'admin_toolbar', 'title' => 'Admin Toolbar', 'category' => 'Security & Administration', 'desc' => 'Improves the default admin menu.'],
                ['machine_name' => 'captcha', 'title' => 'CAPTCHA', 'category' => 'Security & Administration', 'desc' => 'Provides CAPTCHA for adding challenges to forms.'],
                ['machine_name' => 'shield', 'title' => 'Shield', 'category' => 'Security & Administration', 'desc' => 'Creates a simple HTTP basic authentication.'],
                ['machine_name' => 'honeypot', 'title' => 'Honeypot', 'category' => 'Security & Administration', 'desc' => 'Mitigates spam form submissions using the honeypot method.'],
                ['machine_name' => 'security_review', 'title' => 'Security Review', 'category' => 'Security & Administration', 'desc' => 'Automates testing for many of the easy-to-make mistakes in CMS security.'],
                ['machine_name' => 'login_security', 'title' => 'Login Security', 'category' => 'Security & Administration', 'desc' => 'Improves the security of the login page.'],
                ['machine_name' => 'tfa', 'title' => 'Two-factor Authentication', 'category' => 'Security & Administration', 'desc' => 'Provides a framework for setting up two-factor authentication.'],
                ['machine_name' => 'password_policy', 'title' => 'Password Policy', 'category' => 'Security & Administration', 'desc' => 'Provides a way to enforce password policies.'],
                ['machine_name' => 'automated_logout', 'title' => 'Automated Logout', 'category' => 'Security & Administration', 'desc' => 'Logs out users after a specified time of inactivity.'],
                ['machine_name' => 'paranoia', 'title' => 'Paranoia', 'category' => 'Security & Administration', 'desc' => 'Attempts to identify and block all ways that PHP can be evaluated via the web interface.'],

                // Media & Content
                ['machine_name' => 'paragraphs', 'title' => 'Paragraphs', 'category' => 'Media & Content', 'desc' => 'Provides a new way of creating content, making things cleaner.'],
                ['machine_name' => 'webform', 'title' => 'Webform', 'category' => 'Media & Content', 'desc' => 'Make forms and surveys.'],
                ['machine_name' => 'media_library', 'title' => 'Media Library', 'category' => 'Media & Content', 'desc' => 'Enhances the media list with visual galleries.'],
                ['machine_name' => 'field_group', 'title' => 'Field Group', 'category' => 'Media & Content', 'desc' => 'Groups fields together on forms and displays.'],
                ['machine_name' => 'entity_browser', 'title' => 'Entity Browser', 'category' => 'Media & Content', 'desc' => 'A generic entity browser/picker/selector.'],
                ['machine_name' => 'focal_point', 'title' => 'Focal Point', 'category' => 'Media & Content', 'desc' => 'Specify the focal point of an image for cropping.'],
                ['machine_name' => 'dropzonejs', 'title' => 'DropzoneJS', 'category' => 'Media & Content', 'desc' => 'Provides an integration with DropzoneJS.'],
                ['machine_name' => 'crop', 'title' => 'Crop API', 'category' => 'Media & Content', 'desc' => 'Provides basic API for image cropping.'],
                ['machine_name' => 'entity_reference_revisions', 'title' => 'Entity Reference Revisions', 'category' => 'Media & Content', 'desc' => 'Adds a Entity Reference field type with revision support.'],
                ['machine_name' => 'inline_entity_form', 'title' => 'Inline Entity Form', 'category' => 'Media & Content', 'desc' => 'Provides a widget for inline management of referenced entities.'],

                // Performance
                ['machine_name' => 'redis', 'title' => 'Redis', 'category' => 'Performance', 'desc' => 'Integration with Redis memory cache.'],
                ['machine_name' => 'memcache', 'title' => 'Memcache', 'category' => 'Performance', 'desc' => 'Integration with Memcached memory cache.'],
                ['machine_name' => 'advagg', 'title' => 'Advanced CSS/JS Aggregation', 'category' => 'Performance', 'desc' => 'Advanced CSS and JS aggregation to improve front-end performance.'],
                ['machine_name' => 'blazy', 'title' => 'Blazy', 'category' => 'Performance', 'desc' => 'Provides basic bLazy integration for lazy loading and multi-serving images.'],
                ['machine_name' => 'lazy', 'title' => 'Lazy-load', 'category' => 'Performance', 'desc' => 'Image and iframe lazy loading.'],
                ['machine_name' => 'imageapi_optimize', 'title' => 'ImageOptimize', 'category' => 'Performance', 'desc' => 'Optimize images (using 3rd party tools) after they have been generated.'],
                ['machine_name' => 'cdn', 'title' => 'CDN', 'category' => 'Performance', 'desc' => 'Provides easy CDN integration.'],
                ['machine_name' => 'varnish', 'title' => 'Varnish purger', 'category' => 'Performance', 'desc' => 'Invalidate Varnish cache.'],
                ['machine_name' => 'fast_404', 'title' => 'Fast 404', 'category' => 'Performance', 'desc' => 'Delivers fast 404 error pages.'],
                ['machine_name' => 'dblog', 'title' => 'Database Logging', 'category' => 'Performance', 'desc' => 'Logs and records system events to the database.'],
                
                // Analytics
                ['machine_name' => 'sankhyaki', 'title' => 'Sankhyaki Analytics', 'category' => 'Analytics', 'desc' => 'Tracks visitors, referrers, and search engine stats natively.'],
            ];

            foreach ($masterList as &$mod) {
                $mod['status'] = $statusMap[$mod['machine_name']] ?? 0;
            }
            unset($mod);

            sendResponse(true, ['modules' => $masterList]);
            break;

        case 'toggle_module':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('lekhak_modules');
            _lekhak_ensure_table($db, $table, "machine_name VARCHAR(100) PRIMARY KEY, status INTEGER DEFAULT 0, installed_at TEXT");
            
            $machine_name = $_POST['machine_name'] ?? '';
            $status = (int)($_POST['status'] ?? 0);
            $now = date("Y-m-d H:i:s");
            
            if (!$machine_name) {
                sendResponse(false, [], "Machine name required.");
                exit;
            }

            // Check if exists
            $exists = $db->execute_query("SELECT * FROM {$table} WHERE machine_name = ?", [$machine_name]);
            if (!empty($exists)) {
                $db->execute_query("UPDATE {$table} SET status = ?, installed_at = ? WHERE machine_name = ?", [$status, $now, $machine_name]);
            } else {
                $db->execute_query("INSERT INTO {$table} (machine_name, status, installed_at) VALUES (?, ?, ?)", [$machine_name, $status, $now]);
            }

            // Now, simulate the framework loader hook system for full modules
            if ($status == 1) {
                $moduleDir = \SPP\App::getApp()->getAppDir() . '/lekhak/modules/' . $machine_name;
                if (!file_exists($moduleDir)) {
                    mkdir($moduleDir, 0755, true);
                    $stubCode = "<?php\n// Full implementation stub for {$machine_name}\nreturn ['status' => 'enabled', 'name' => '{$machine_name}'];\n";
                    file_put_contents($moduleDir . '/module.php', $stubCode);
                }
            }

            sendResponse(true, ['machine_name' => $machine_name, 'status' => $status], "Module state updated.");
            break;

        default:
            sendResponse(false, [], "Unknown action: $action");
            break;
    }
} catch (\Throwable $e) {
    sendResponse(false, [], "API Error: " . $e->getMessage());
}
