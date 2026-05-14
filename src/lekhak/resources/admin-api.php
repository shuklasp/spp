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
        case 'get_dashboard_stats':
            $logFile = SPP_LOG_DIR . '/api_debug.log';
            error_log("[" . date('Y-m-d H:i:s') . "] Lekhak API: get_dashboard_stats hit\n", 3, $logFile);
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('nodes');
            $total = $db->execute_query("SELECT COUNT(*) as count FROM {$table}")[0]['count'];
            $published = $db->execute_query("SELECT COUNT(*) as count FROM {$table} WHERE status='published'")[0]['count'];
            $drafts = $db->execute_query("SELECT COUNT(*) as count FROM {$table} WHERE status='draft'")[0]['count'];
            
            // Get recent nodes
            $recent = $db->execute_query("SELECT id, title, status, changed FROM {$table} ORDER BY changed DESC LIMIT 5");
            
            sendResponse(true, [
                'stats' => [
                    'total' => (int)$total,
                    'published' => (int)$published,
                    'drafts' => (int)$drafts,
                    'engagement' => 84 // Mock engagement score
                ],
                'recent' => $recent
            ]);
            break;

        case 'list_nodes':
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('nodes');
            $nodes = $db->execute_query("SELECT id, title, status, changed FROM {$table} ORDER BY changed DESC LIMIT 50");
            sendResponse(true, ['nodes' => $nodes]);
            break;

        case 'get_node':
            $id = $_GET['id'] ?? null;
            if (!$id) sendResponse(false, [], "ID required.");
            
            $node = new \SPPMod\Lekhak\Core\LekhakNode($id);
            sendResponse(true, ['node' => [
                'id' => $node->id,
                'title' => $node->title,
                'body' => $node->body,
                'status' => $node->status,
                'alias' => $node->alias,
                'changed' => $node->changed
            ]]);
            break;

        case 'publish':
            $_POST['status'] = 'published';
            // fallthrough
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
                
                sendResponse(true, [
                    'id' => $node->id,
                    'url' => $publicUrl
                ], "Document " . ($status === 'published' ? 'published' : 'saved') . " successfully.");
            } catch (\Exception $e) {
                sendResponse(false, [], "Save failed: " . $e->getMessage());
            }
            break;

        default:
            sendResponse(false, [], "Unknown action: $action");
            break;
    }
} catch (\Throwable $e) {
    sendResponse(false, [], "API Error: " . $e->getMessage());
}
