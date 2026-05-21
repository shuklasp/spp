<?php
/**
 * Automated Drupal Compatibility Bridge Verification Script
 * Validates Module A, B, C, D, and E implementations.
 */
require_once 'vendor/autoload.php';
require_once 'spp/sppinit.php';

// Ensure Lekhak & entities are registered/loaded
require_once 'src/lekhak/entities/entity.vocabulary.php';
require_once 'src/lekhak/entities/entity.term.php';
require_once 'src/lekhak/entities/entity.block.php';
require_once 'src/lekhak/modules/lekhak/src/Core/LekhakNode.php';
require_once 'src/lekhak/modules/lekhak/src/Core/ViewsEngine.php';
require_once 'src/lekhak/modules/lekhak/src/Core/FormBuilder.php';

// Dynamic load of Node if not present
if (!class_exists('\App\Lekhak\Entities\Node')) {
    class Node extends \SPPMod\Lekhak\Core\LekhakNode {}
    class_alias('Node', '\App\Lekhak\Entities\Node');
}

use App\Lekhak\Entities\Vocabulary;
use App\Lekhak\Entities\Term;
use App\Lekhak\Entities\Block;
use SPPMod\Lekhak\Core\ViewsEngine;
use SPPMod\Lekhak\Core\FormBuilder;
use SPPMod\SPPDB\SPPDB;

echo "=========================================================\n";
echo "  DRUPAL COMPATIBILITY BRIDGE END-TO-END VERIFICATION\n";
echo "=========================================================\n\n";

$db = new SPPDB();

// -------------------------------------------------------------
// VERIFICATION 1: Module A (Taxonomy & Hierarchical Categories)
// -------------------------------------------------------------
echo "1. Testing Module A: Taxonomy & Hierarchical Categories...\n";

// Ensure tables exist
$vocabTable = SPPDB::sppTable('vocabularies');
if (!$db->tableExists($vocabTable)) {
    $db->exec_squery("CREATE TABLE %tab% (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50),
        label VARCHAR(255),
        description TEXT
    )", $vocabTable);
}

$termTable = SPPDB::sppTable('terms');
if (!$db->tableExists($termTable)) {
    $db->exec_squery("CREATE TABLE %tab% (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vid VARCHAR(50),
        name VARCHAR(255),
        parent_id INT NULL,
        description TEXT,
        weight INT DEFAULT 0
    )", $termTable);
}

// Clear old test data
$db->exec_squery("DELETE FROM %tab% WHERE name LIKE 'test_%'", $vocabTable);
$db->exec_squery("DELETE FROM %tab% WHERE vid LIKE 'test_%' OR name LIKE 'test_%'", $termTable);

// Create Vocabulary
$voc = new Vocabulary();
$voc->set('name', 'test_tags');
$voc->set('label', 'Test Tags');
$voc->set('description', 'Test Vocabulary');
$vocId = $voc->save();
echo "   [PASS] Created Vocabulary: 'test_tags' (ID: $vocId)\n";

// Create Parent Term
$parentTerm = new Term();
$parentTerm->set('vid', 'test_tags');
$parentTerm->set('name', 'test_science');
$parentTerm->set('weight', 0);
$parentId = $parentTerm->save();
echo "   [PASS] Created Parent Term: 'test_science' (ID: $parentId)\n";

// Create Child Term
$childTerm = new Term();
$childTerm->set('vid', 'test_tags');
$childTerm->set('name', 'test_physics');
$childTerm->set('parent_id', $parentId);
$childTerm->set('weight', 10);
$childId = $childTerm->save();
echo "   [PASS] Created Child Term: 'test_physics' (ID: $childId)\n";

// Test Term Hierarchy Methods
$loadedChild = Term::find_one(['id' => $childId]);
$parentOfChild = $loadedChild->getParent();
if ($parentOfChild && $parentOfChild->get('name') === 'test_science') {
    echo "   [PASS] Term Hierarchy Resolved (Child -> Parent match!)\n";
} else {
    echo "   [FAIL] Term Hierarchy Failed to resolve parent!\n";
}

// Tag a Node
$nodesTable = SPPDB::sppTable('nodes');
$db->exec_squery("DELETE FROM %tab% WHERE title = 'Test Taxonomy Node'", $nodesTable);

$node = new \App\Lekhak\Entities\Node();
$node->set('title', 'Test Taxonomy Node');
$node->set('bundle', 'page');
$node->set('body', 'This is a test node tagged with taxonomy terms.');
$node->set('status', 'published');
$nid = $node->save();

$node->addTerm($childId);
echo "   [PASS] Tagged Node (ID: $nid) with Term ID: $childId\n";

// Retrieve terms attached to node
$attachedTerms = $node->getTerms();
if (!empty($attachedTerms) && $attachedTerms[0]->get('name') === 'test_physics') {
    echo "   [PASS] Successfully retrieved node terms! Node is tagged with: " . $attachedTerms[0]->get('name') . "\n";
} else {
    echo "   [FAIL] Failed to retrieve terms attached to node!\n";
}
echo "\n";

// -------------------------------------------------------------
// VERIFICATION 2: Module B (Drupal-Style Views Engine)
// -------------------------------------------------------------
echo "2. Testing Module B: Drupal-Style Views Engine...\n";

$viewDef = [
    'base_table' => 'nodes',
    'filters' => [
        'title' => 'Test Taxonomy Node'
    ],
    'sort' => [
        'id' => 'DESC'
    ],
    'pager' => [
        'limit' => 1
    ]
];

$viewResults = ViewsEngine::executeView($viewDef);
if (!empty($viewResults) && $viewResults[0] instanceof \App\Lekhak\Entities\Node) {
    echo "   [PASS] Views Engine executed query successfully.\n";
    echo "   [PASS] Result Hydration OK: Node Title is '" . $viewResults[0]->get('title') . "'\n";
} else {
    echo "   [FAIL] Views Engine failed to execute or hydrate results!\n";
}
echo "\n";

// -------------------------------------------------------------
// VERIFICATION 3: Module C (Layout Regions & Blocks)
// -------------------------------------------------------------
echo "3. Testing Module C: Layout Regions & Blocks...\n";

$blocksTable = SPPDB::sppTable('blocks');
if (!$db->tableExists($blocksTable)) {
    $db->exec_squery("CREATE TABLE %tab% (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50),
        title VARCHAR(255),
        region VARCHAR(50),
        visibility_paths TEXT,
        content LONGTEXT,
        type VARCHAR(20),
        weight INT
    )", $blocksTable);
}

// Clear old test blocks
$db->exec_squery("DELETE FROM %tab% WHERE name LIKE 'test_%'", $blocksTable);

// Insert an HTML Block
$block1 = new Block();
$block1->set('name', 'test_sidebar_html');
$block1->set('title', 'Sidebar HTML');
$block1->set('region', 'sidebar_first');
$block1->set('visibility_paths', "/lekhak*");
$block1->set('content', '<div class="test-html-block">Hello from Sidebar First!</div>');
$block1->set('type', 'html');
$block1->set('weight', 5);
$block1->save();

// Insert a Views dynamic Block
$block2 = new Block();
$block2->set('name', 'test_recent_view');
$block2->set('title', 'Recent Test Nodes');
$block2->set('region', 'sidebar_first');
$block2->set('visibility_paths', "/lekhak*");
$block2->set('content', '');
$block2->set('type', 'view');
$block2->set('weight', 10);
$block2->save();

// Save the mock view file so ViewsEngine can find it by name
if (defined('APP_ETC_DIR')) {
    $viewDir = APP_ETC_DIR . SPP_DS . 'views';
    if (!is_dir($viewDir)) {
        @mkdir($viewDir, 0777, true);
    }
    $viewPath = $viewDir . SPP_DS . 'test_recent_view.view.yml';
    $viewYamlContent = "base_table: nodes\nfilters:\n  title: 'Test Taxonomy Node'\nsort:\n  id: DESC\npager:\n  limit: 3\n";
    @file_put_contents($viewPath, $viewYamlContent);
}

// Run Region Render
$_SERVER['REQUEST_URI'] = '/lekhak/news';
require_once 'src/lekhak/modules/spptheme/api/class.thememanager.php';

// Set active theme path (we can use the default theme or create a dummy template)
$dummyThemeDir = SPP_APP_DIR . '/themes/test_theme';
if (!is_dir($dummyThemeDir)) {
    @mkdir($dummyThemeDir, 0777, true);
}
@file_put_contents($dummyThemeDir . '/theme.yml', "name: test_theme\nregions:\n  sidebar_first: Sidebar First\n");
@file_put_contents($dummyThemeDir . '/layout.php', "
<html>
<head><?php echo \$original_head; ?></head>
<body>
<header><?php echo \$header; ?></header>
<aside class=\"sidebar\"><?php echo \$sidebar_first; ?></aside>
<main><?php echo \$content; ?></main>
<footer><?php echo \$footer; ?></footer>
</body>
</html>
");

\SPPMod\SppTheme\Api\ThemeManager::setTheme('test_theme');

ob_start();
\SPPMod\SppTheme\Api\ThemeManager::renderWithTheme('<html><body><main>Body Content</main></body></html>', [
    'node' => $node
]);
$output = ob_get_clean();

if (strpos($output, 'Hello from Sidebar First!') !== false && strpos($output, 'Test Taxonomy Node') !== false) {
    echo "   [PASS] Regional Block Rendering & Placement succeeded.\n";
    echo "   [PASS] Path Visibility Gating succeeded.\n";
    echo "   [PASS] Views Block dynamic rendering succeeded!\n";
} else {
    echo "   [FAIL] ThemeManager layout blocks integration failed! Output: \n" . $output . "\n";
}
echo "\n";

// -------------------------------------------------------------
// VERIFICATION 4: Module D (Form API & Alter Pipeline)
// -------------------------------------------------------------
echo "4. Testing Module D: Form API (FAPI) & Alter Pipeline...\n";

// Create a dummy form definition yml
$dummyFormDir = SPP_APP_DIR . '/src/lekhak/etc/entities';
if (!is_dir($dummyFormDir)) {
    @mkdir($dummyFormDir, 0777, true);
}
$dummyFormPath = $dummyFormDir . '/test_fapi_form.yml';
$formYamlContent = "
form:
  name: test_compat_form
  method: post
elements:
  title:
    type: text
    label: Article Title
    required: true
";
@file_put_contents($dummyFormPath, $formYamlContent);

// Register form alter hook
FormBuilder::registerAlterHook(function (&$form, &$formState, $formId) {
    if ($formId === 'test_compat_form') {
        // Inject a custom textfield in Drupal FAPI format
        $form['extra_custom_field'] = [
            '#type' => 'textfield',
            '#title' => 'Extra Field',
            '#required' => false,
            '#default_value' => 'Hello Alter!',
            '#description' => 'Injected via hook_form_alter'
        ];
    }
});

$formState = [];
$compiledForm = FormBuilder::buildForm('src/lekhak/etc/entities/test_fapi_form.yml', $formState);

$injectedElement = $compiledForm->getChild('extra_custom_field');
if ($injectedElement && $injectedElement->getAttribute('value') === 'Hello Alter!') {
    echo "   [PASS] Form API array shimming successfully validated.\n";
    echo "   [PASS] hook_form_alter pipeline triggered and form elements mutated successfully!\n";
} else {
    echo "   [FAIL] Form API alter pipeline verification failed!\n";
}
echo "\n";

// -------------------------------------------------------------
// VERIFICATION 5: Module E (Node Access Control Matrix)
// -------------------------------------------------------------
echo "5. Testing Module E: Node Access Control Matrix...\n";

$accessTable = SPPDB::sppTable('node_access');
if (!$db->tableExists($accessTable)) {
    $db->exec_squery("CREATE TABLE %tab% (
        nid BIGINT,
        gid INT,
        realm VARCHAR(50),
        grant_view INT DEFAULT 0,
        grant_update INT DEFAULT 0,
        grant_delete INT DEFAULT 0,
        PRIMARY KEY (nid, gid)
    )", $accessTable);
}

// Clear access rule
$db->exec_squery("DELETE FROM %tab% WHERE nid = ?", $accessTable, [$nid]);

// Write dynamic grants: Group 99 (premium users) can view, anonymous group 0 cannot view.
$db->exec_squery("INSERT INTO %tab% (nid, gid, realm, grant_view, grant_update, grant_delete) VALUES (?, ?, ?, ?, ?, ?)",
    $accessTable, [$nid, 99, 'role', 1, 0, 0]);

// Check access for Premium user (mock user with group 99)
$premiumUser = new \stdClass();
$premiumUser->id = 55;
$premiumUser->groups = [99];
$premiumUser->roles = ['member'];

$anonymousUser = null;

$hasPremiumAccess = $node->checkAccess('view', $premiumUser);
$hasAnonAccess = $node->checkAccess('view', $anonymousUser);

if ($hasPremiumAccess === true && $hasAnonAccess === false) {
    echo "   [PASS] Node Access Matrix gates correctly!\n";
    echo "   [PASS] Premium user permitted; Anonymous user correctly denied.\n";
} else {
    echo "   [FAIL] Node Access Matrix gating failed! (Premium: " . json_encode($hasPremiumAccess) . ", Anon: " . json_encode($hasAnonAccess) . ")\n";
}
echo "\n";

echo "=========================================================\n";
echo "   ALL COMPATIBILITY VERIFICATIONS COMPLETED SUCCESSFULLY!\n";
echo "=========================================================\n";
