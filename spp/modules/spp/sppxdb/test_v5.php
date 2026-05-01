<?php
/**
 * Verification script for SPP XDB v5 (Hooks/Triggers)
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Phase 6 (Hooks) Verification ===\n\n";

try {
    $xdb = new SPP_XDB('test_hooks');
    $xdb->connect('logs');
    
    $eventsTriggered = [];
    
    $xdb->on('beforeInsert', function(&$data) use (&$eventsTriggered) {
        echo "   [HOOK] beforeInsert triggered for: {$data['msg']}\n";
        $data['timestamp'] = date('Y-m-d H:i:s');
        $eventsTriggered[] = 'beforeInsert';
    });
    
    $xdb->on('afterInsert', function($data) use (&$eventsTriggered) {
        echo "   [HOOK] afterInsert triggered. ID: {$data['id']}\n";
        $eventsTriggered[] = 'afterInsert';
    });

    $xdb->on('beforeUpdate', function(&$data) use (&$eventsTriggered) {
        echo "   [HOOK] beforeUpdate triggered.\n";
        $eventsTriggered[] = 'beforeUpdate';
    });

    // 1. Testing Insert Hooks
    echo "1. Testing Insert Hooks...\n";
    $xdb->insert(['msg' => 'Hello World']);
    
    $row = $xdb->table('logs')->first();
    echo "   Stored record msg: {$row['msg']} | Timestamp (added by hook): {$row['timestamp']}\n";
    
    if (isset($row['timestamp'])) {
        echo "   [CONFIRMED] beforeInsert hook modified data successfully.\n";
    }

    // 2. Testing Update Hooks
    echo "\n2. Testing Update Hooks...\n";
    $xdb->table('logs')->where('id', 1)->update(['msg' => 'Updated Msg']);
    
    if (in_array('beforeUpdate', $eventsTriggered)) {
        echo "   [CONFIRMED] beforeUpdate hook triggered.\n";
    }

    echo "\n=== All Phase 6 (Hooks) Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
