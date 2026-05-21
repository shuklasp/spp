<?php
/**
 * SPP Extensions Verification and Testing Suite
 * Validates dynamic translation scanning/lookup, virtual properties, compression, and delta patching.
 */

// Disable error display outputs so we control the report rendering
ini_set('display_errors', '0');
error_reporting(E_ALL);

define('SPP_BASE_DIR', __DIR__);
require_once __DIR__ . '/sppinit.php';

// Load translation helper class explicitly to guarantee standard global function declaration
require_once __DIR__ . '/core/class.translation.php';

// Define ANSI Colors for terminal output
define('COLOR_RESET', "\033[0m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_CYAN', "\033[36m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_WHITE_BOLD', "\033[1;37m");

function logTitle($msg) {
    echo "\n" . COLOR_WHITE_BOLD . "=== " . $msg . " ===" . COLOR_RESET . "\n";
}

function assertTest($description, $condition) {
    if ($condition) {
        echo COLOR_GREEN . "[PASS] " . COLOR_RESET . $description . "\n";
    } else {
        echo COLOR_RED . "[FAIL] " . COLOR_RESET . $description . "\n";
    }
}

// Ensure database handles and entities are pre-registered
try {
    logTitle("1. Dynamic Translation Engine & spplang Scanner");

    // Clear dynamic translations to prevent residue contamination
    \SPPMod\SPPLang\SPPLang::ensureSchema();
    $db = new \SPPMod\SPPDB\SPPDB();
    $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
    $db->exec_squery("DELETE FROM %tab%", $table);

    // Create a temporary scan file
    $scanDir = __DIR__ . '/scratch';
    if (!is_dir($scanDir)) {
        mkdir($scanDir, 0777, true);
    }
    $scanFile = $scanDir . '/test_scan.php';
    $scanContent = '<?php echo __("Translation Scan Validation Trigger String"); ?>';
    file_put_contents($scanFile, $scanContent);

    // Trigger recursive directory scan
    $discovered = \SPPMod\SPPLang\SPPLang::scanDirectory($scanDir, 'en');
    assertTest("Scanner successfully discovered translatable string in temporary file", in_array("Translation Scan Validation Trigger String", $discovered));

    // Verify key persistence
    $persisted = \SPPMod\SPPLang\SPPLang::getTranslations(['locale' => 'en', 'search' => 'Translation Scan']);
    assertTest("Discovered translation keys successfully persisted to SQLite database", count($persisted) > 0 && $persisted[0]['key_code'] === "Translation Scan Validation Trigger String");

    // Test translation fallback lookup
    $fallbackResult = __("Translation Scan Validation Trigger String", "en");
    assertTest("Global translation helper falls back to returning the key if override is active but empty", $fallbackResult === "Translation Scan Validation Trigger String");

    // Save translation override in another language
    \SPPMod\SPPLang\SPPLang::saveTranslation("Translation Scan Validation Trigger String", "es", "Cadena del disparador de validacion de escaneo de traduccion", "active");
    $overrideResult = __("Translation Scan Validation Trigger String", "es");
    assertTest("Dynamic translation database lookup successfully returns custom translated overrides", $overrideResult === "Cadena del disparador de validacion de escaneo de traduccion");

    // Clean up scanner temp files
    unlink($scanFile);
    @rmdir($scanDir);

} catch (\Throwable $e) {
    echo COLOR_RED . "Translation Suite Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . COLOR_RESET . "\n";
}

try {
    logTitle("2. SPPEntity and LekhakNode Virtual Fields & Lifecycle Events");

    // Set context to lekhak app
    \SPP\Scheduler::setContext('lekhak');

    // Boot RevisionManager to initialize event listeners and trigger schema setups
    \SPPMod\SPPDiff\RevisionManager::boot();

    // Create an instance of LekhakNode (extends SPPEntity)
    $node = new \SPPMod\Lekhak\Core\LekhakNode();
    
    // Register event listeners to record lifecycle fires
    $lifecycleEvents = [];
    \SPP\Core\EventManager::listen('entity:before_save', function($entity) use (&$lifecycleEvents) {
        $lifecycleEvents['before_save'] = true;
    });

    \SPP\Core\EventManager::listen('entity:after_save', function($entity) use (&$lifecycleEvents) {
        $lifecycleEvents['after_save'] = true;
    });

    \SPP\Core\EventManager::listen('entity:after_load', function($entity) use (&$lifecycleEvents) {
        $lifecycleEvents['after_load'] = true;
    });

    // Assign virtual fields dynamically
    $node->title = "Integration Test Node Title";
    $node->status = "published";
    $node->virtual_meta_theme = "outfit-glassmorphic";
    $node->virtual_dynamic_rating = 9.8;

    // Save the node
    $node->save();

    assertTest("entity:before_save lifecycle hook fired successfully during entity save", isset($lifecycleEvents['before_save']));
    assertTest("entity:after_save lifecycle hook fired successfully during entity save", isset($lifecycleEvents['after_save']));

    // Assert virtual fields unboxed/boxed dynamically in 'fields_data' column
    $dbData = $node->get('fields_data');
    $unpacked = json_decode($dbData, true);
    assertTest("Virtual dynamic properties packed successfully into JSON in 'fields_data' column", is_array($unpacked) && $unpacked['virtual_meta_theme'] === "outfit-glassmorphic" && $unpacked['virtual_dynamic_rating'] == 9.8);

    // Retrieve saved node to verify load routing
    $nodeId = $node->id;
    $loadedNode = \SPPMod\Lekhak\Core\LekhakNode::find_one(['id' => $nodeId]);

    assertTest("entity:after_load lifecycle hook fired successfully during entity retrieval", isset($lifecycleEvents['after_load']));
    assertTest("Loaded entity unboxes dynamic virtual properties transparently as object attributes", $loadedNode->virtual_meta_theme === "outfit-glassmorphic" && $loadedNode->virtual_dynamic_rating == 9.8);

} catch (\Throwable $e) {
    echo COLOR_RED . "SPPEntity Virtual Properties Suite Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . COLOR_RESET . "\n";
}

try {
    logTitle("3. sppdiff Compression, Delta Engine & Revisions Management");

    // Verify entity history table exists
    $db = new \SPPMod\SPPDB\SPPDB();
    $histTable = \SPPMod\SPPDB\SPPDB::sppTable('entity_history');
    assertTest("Dynamic history revisions database table 'spp_entity_history' initialized successfully", $db->tableExists($histTable));

    if (isset($loadedNode) && $loadedNode) {
        // Make changes to the loaded node to trigger revision logging
        $originalState = $loadedNode->getValues();
        
        $loadedNode->virtual_meta_theme = "neon-cyberpunk";
        $loadedNode->virtual_dynamic_rating = 10.0;
        $loadedNode->save();

        // Query revisions logged for this entity
        $revisions = $db->exec_squery("SELECT * FROM %tab% WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC", 
            $histTable, [get_class($loadedNode), $loadedNode->id]
        );

        assertTest("Revisions logger dynamically captures and saves compressed state deltas on entity updates", count($revisions) > 0);

        if (count($revisions) > 0) {
            $latestRev = $revisions[0];
            $compressedDelta = $latestRev['delta'];
            
            // Decompress delta and check dictionary structure
            $deltaJson = gzuncompress(base64_decode($compressedDelta));
            $delta = json_decode($deltaJson, true);
            
            assertTest("Revisions delta logged with Gzip compression and Base64 encoding successfully", $deltaJson !== false);
            assertTest("State changes accurately recorded inside dictionary schema delta logs", isset($delta['virtual_meta_theme']) && $delta['virtual_meta_theme']['new'] === "neon-cyberpunk");

            // Verify state reconstruction via delta engine
            $modifiedState = $loadedNode->getValues();
            if (!empty($modifiedState['fields_data'])) {
                $modVirtual = json_decode($modifiedState['fields_data'], true);
                if (is_array($modVirtual)) {
                    $modifiedState = array_merge($modifiedState, $modVirtual);
                }
            }
            $reconstructedState = \SPPMod\SPPDiff\DeltaEngine::patch($modifiedState, $delta);
            
            assertTest("Delta Engine perfectly patches state updates to reconstruct target entity revisions", $reconstructedState['virtual_meta_theme'] === "outfit-glassmorphic" && $reconstructedState['virtual_dynamic_rating'] == 9.8);
        }
    } else {
        echo COLOR_RED . "[FAIL] Could not run revisions tests because entity node was not loaded successfully." . COLOR_RESET . "\n";
    }

} catch (\Throwable $e) {
    echo COLOR_RED . "sppdiff Suite Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . COLOR_RESET . "\n";
}

logTitle("All Tests Completed.");
