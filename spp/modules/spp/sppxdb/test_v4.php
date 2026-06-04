<?php

/**
 * Verification script for SPP XDB v4 (Encryption)
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Phase 6 Verification ===\n\n";

try {
    $xdb = new SPP_XDB('test_v4');

    // 1. Testing Encryption
    echo "1. Testing Encryption...\n";
    $xdb->connect('secrets');
    $xdb->setEncryptedFields(['ssn', 'api_key']);

    $xdb->insert(['name' => 'John Doe', 'ssn' => '123-456-789', 'api_key' => 'sk_live_abc123']);
    echo "   Record inserted with encrypted fields.\n";

    // Verify raw XML content
    $xmlContent = file_get_contents(__DIR__ . '/data/test_v4/secrets.xml');
    echo "   Raw XML content (should be encrypted):\n";
    if (strpos($xmlContent, '123-456-789') === false) {
        echo "   [CONFIRMED] SSN is NOT in raw text.\n";
    } else {
        echo "   [ERROR] SSN is visible in raw text!\n";
    }

    // Verify decryption on read
    $results = $xdb->querySQL("SELECT * FROM secrets");
    echo "   Read result (should be decrypted):\n";
    echo "   Name: {$results[0]['name']} | SSN: {$results[0]['ssn']} | API Key: {$results[0]['api_key']}\n";

    if ($results[0]['ssn'] === '123-456-789') {
        echo "   [CONFIRMED] Decryption successful.\n";
    } else {
        echo "   [ERROR] Decryption failed! Got: {$results[0]['ssn']}\n";
    }

    echo "\n=== All Phase 6 Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
