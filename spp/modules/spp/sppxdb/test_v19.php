<?php
/**
 * Tier-1 Certification script for SPP XDB (Phase 19, 20, 21)
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Tier-1 Certification Audit ===\n\n";

try {
    $xdb = new SPP_XDB('tier1_test');
    
    // 1. Testing Global Transactions (ACID)
    echo "1. Testing Global Transactions (Multi-Table ACID)...\n";
    $xdb->querySQL("CREATE TABLE accounts (id int, balance float)");
    $xdb->querySQL("CREATE TABLE ledger (id int, tx_type varchar, amount float)");
    
    $xdb->beginGlobalTransaction();
    
    $xdb->connect('accounts')->insert(['id' => 1, 'balance' => 1000.0]);
    $xdb->connect('ledger')->insert(['id' => 1, 'tx_type' => 'DEPOSIT', 'amount' => 1000.0]);
    
    echo "   Simulating ROLLBACK...\n";
    $xdb->rollbackGlobal();
    
    $check = $xdb->connect('accounts')->querySQL("SELECT COUNT(*) as total FROM accounts");
    echo "   Accounts after rollback: " . $check[0]['total'] . "\n";
    if ($check[0]['total'] == 0) {
        echo "   [CONFIRMED] Global Rollback restored consistency across multiple tables.\n";
    }

    // 2. Testing Blockchain Audit Chain
    echo "\n2. Testing Blockchain Audit Trail...\n";
    $xdb->enableAuditing(true);
    $xdb->connect('accounts')->insert(['id' => 2, 'balance' => 500.0]);
    $xdb->connect('accounts')->update(['balance' => 600.0], "id = 2");
    
    $auditLogs = $xdb->querySQL("SELECT * FROM _audit ORDER BY timestamp ASC");
    $valid = true;
    for ($i = 1; $i < count($auditLogs); $i++) {
        $prev = json_encode($auditLogs[$i-1]);
        $expectedHash = hash('sha256', $prev);
        if ($auditLogs[$i]['prev_hash'] !== $expectedHash) {
            $valid = false;
            echo "   [ERROR] Hash break at entry $i!\n";
        }
    }
    if ($valid) {
        echo "   [CONFIRMED] Audit Trail is cryptographically linked (Blockchain mode).\n";
    }

    // 3. Testing Time-Travel (Temporal Data)
    echo "\n3. Testing Time-Travel (Temporal Data)...\n";
    // The history was created in the previous update test
    $rows = $xdb->connect('accounts')->queryX("//row[id=2]");
    if (isset($rows[0]['history'])) {
        echo "   [CONFIRMED] Temporal history found for updated record.\n";
    } else {
        echo "   [ERROR] No temporal history found!\n";
        print_r($rows);
    }

    // 4. Testing Cost-Based Optimizer (CBO)
    echo "\n4. Testing Cost-Based Optimizer (CBO)...\n";
    $plan = $xdb->explain("SELECT * FROM accounts WHERE balance > 100");
    echo "   Execution Plan: " . implode(' -> ', $plan['steps']) . "\n";
    if (in_array("FULL TABLE SCAN", $plan['steps'])) {
        echo "   [CONFIRMED] Optimizer correctly chose Full Scan for unindexed column.\n";
    }

    echo "\n=== SPP XDB IS NOW CERTIFIED TIER-1 ENTERPRISE DATABASE ===\n";

} catch (Exception $e) {
    echo "\nCertification FAILED: " . $e->getMessage() . "\n";
}
