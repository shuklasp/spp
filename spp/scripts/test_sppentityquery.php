<?php
require_once __DIR__ . '/../sppinit.php';
try {
    \SPP\Scheduler::regProc(new \SPP\App('lekhak'));
} catch (\Exception $e) {}
\SPP\Scheduler::setContext('lekhak');

echo "Testing SppEntityQuery and Dynamic Fields...\n";

// Ensure dynamic fields table exists via ConfigSyncCommand
require_once __DIR__ . '/../commands/ConfigSyncCommand.php';
$sync = new \SPP\Commands\ConfigSyncCommand();
$sync->execute(['fields']);

if (class_exists('\\SPPMod\\Lekhak\\Core\\LekhakNode')) {
    try {
        \SPPMod\Lekhak\Core\LekhakNode::install();
    } catch (\Exception $e) {}
    \SPPMod\Lekhak\Core\LekhakNode::setMetadata('dynamic_attributes', [
        'seo_score' => 'int',
        'tags' => 'string'
    ]);
}

$node = new \SPPMod\Lekhak\Core\LekhakNode();
$node->title = "Test SppEntityQuery Node " . time();
$node->alias = "test-query-" . time();
$node->bundle = "page";
$node->status = "draft";
$node->save();
echo "Created Test Node ID: {$node->id}\n";

// Set a dynamic field
$node->set('seo_score', 85);
$node->set('tags', 'test, query');
$node->save();
echo "Saved dynamic fields.\n";

// Test SppEntityQuery
$query = new \SPPMod\SppDb\SppEntityQuery('\\SPPMod\\Lekhak\\Core\\LekhakNode');
$query->condition('bundle', 'page');
$query->dynamicCondition('seo_score', 80, '>');
$query->sort('id', 'DESC');
$query->limit(1);

$results = $query->execute();
echo "Query Results Count: " . count($results) . "\n";
if (count($results) > 0) {
    $resNode = $results[0];
    echo "Found Node ID: {$resNode->id}\n";
    echo "Found Node Title: {$resNode->title}\n";
    echo "Found Node SEO Score: " . $resNode->get('seo_score') . "\n";
} else {
    echo "FAILED: Expected to find the test node.\n";
}

echo "\nTesting Workflow Transition...\n";
if (class_exists('\\SPPMod\\Lekhak\\Core\\LekhakNode')) {
    $resNode = new \SPPMod\Lekhak\Core\LekhakNode();
    $resNode->setValues([
        'title' => 'Workflow Node',
        'alias' => 'workflow-node',
        'status' => 'draft',
        'bundle' => 'article',
        'author_id' => 1
    ]);
    $resNode->save();

    echo "Initial Status: " . $resNode->get('status') . "\n";
    try {
        $resNode->transitionStatus('published');
        echo "Successfully transitioned to published.\n";
    } catch (\Exception $e) {
        echo "Transition failed as expected (no auth context): " . $e->getMessage() . "\n";
    }
} else {
    echo "LekhakNode class not found. Skipping workflow test.\n";
}

// Cleanup
$db = new \SPPMod\SPPDB\SPPDB();
$db->exec("DELETE FROM " . \SPPMod\SPPDB\SPPDB::sppTable('nodes') . " WHERE id = " . $node->id);
$db->exec("DELETE FROM " . \SPPMod\SPPDB\SPPDB::sppTable('spp_entity_fields') . " WHERE entity_id = " . $node->id);
echo "Cleanup done.\n";
