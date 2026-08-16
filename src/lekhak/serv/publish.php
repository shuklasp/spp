<?php
/**
 * Lekhak Publish Service
 * Handles saving and publishing of documents from the editor.
 */

// Ensure this is called within the SPP context
if (!defined('SPP_PATH'))
    exit;

$action = $_POST['action'] ?? 'save';
$title = $_POST['title'] ?? 'Untitled';
$body = $_POST['body'] ?? '';
$id = $_POST['id'] ?? null;

// Access Control Check
if (!\SPPMod\SPPAuth\SPPAuth::can('publish_document')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied: You do not have permission to publish documents.'
    ]);
    exit;
}

try {
    $node = new \SPPMod\Lekhak\Core\LekhakNode();

    if ($id) {
        $node->load($id);
    } else {
        $node->created = date('Y-m-d H:i:s');
    }

    $node->title = $title;
    $node->body = $body;
    $node->changed = date('Y-m-d H:i:s');

    // Generate alias if not set
    if (!$node->alias) {
        $node->alias = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    }

    if (!$id) {
        $node->save(); // Save once to get ID
    }

    $status = ($action === 'publish') ? 'published' : 'draft';
    if ($node->status !== $status) {
        $node->applyTransition($status);
    } else {
        $node->save();
    }

    $response = [
        'success' => true,
        'message' => 'Document ' . (($action === 'publish') ? 'published' : 'saved') . ' successfully.',
        'data' => [
            'id' => $node->id,
            'alias' => $node->alias,
            'url' => \SPP\App::getAppConf('base_url') . '/node/' . ($node->alias ?: $node->id)
        ]
    ];

} catch (\Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
}
