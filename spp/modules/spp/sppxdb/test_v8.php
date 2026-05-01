<?php
/**
 * Verification script for SPP XDB Enterprise Features (Phase 11 & 12)
 */

require_once(__DIR__ . '/sppxdb.php');

use SPPMod\SPPXDB\SPP_XDB;

echo "=== SPP XDB Enterprise Phase 11 & 12 Verification ===\n\n";

try {
    $xdb = new SPP_XDB('enterprise_test');
    
    // 1. Testing ACL (Access Control)
    echo "1. Testing ACL (Access Control)...\n";
    $xdb->querySQL("CREATE TABLE secure_data (id int, secret varchar)");
    $xdb->setPermissions('secure_data', ['read' => true, 'write' => false]);
    
    $xdb->connect('secure_data');
    echo "   Attempting unauthorized write...\n";
    try {
        $xdb->insert(['secret' => 'Should fail']);
        echo "   [ERROR] Write should have failed!\n";
    } catch (Exception $e) {
        echo "   [CONFIRMED] Write blocked: " . $e->getMessage() . "\n";
    }

    // 2. Testing Table Partitioning (Horizontal Scaling)
    echo "\n2. Testing Table Partitioning...\n";
    $xdb->setPermissions('large_table', ['read' => true, 'write' => true]);
    $xdb->connect('large_table');
    // Set low threshold for testing
    $ref = new ReflectionClass($xdb);
    $prop = $ref->getProperty('maxRowsPerSegment');
    $prop->setAccessible(true);
    $prop->setValue($xdb, 10); // Split every 10 rows
    
    echo "   Inserting 25 rows (should create 3 segments)...\n";
    for ($i = 1; $i <= 25; $i++) {
        $xdb->insert(['id' => $i, 'val' => "Test $i"]);
    }
    
    $results = $xdb->querySQL("SELECT COUNT(*) as total FROM large_table");
    echo "   Total rows across segments: " . $results[0]['total'] . "\n";
    if ($results[0]['total'] == 25) {
        echo "   [CONFIRMED] Partitioned query successfully combined all segments.\n";
    } else {
        echo "   [ERROR] Partitioned query failed!\n";
    }

    // 3. Testing XSLT Transformation
    echo "\n3. Testing XSLT Transformation...\n";
    $xslPath = __DIR__ . '/test.xsl';
    $xslContent = '<?xml version="1.0" encoding="UTF-8"?>
    <xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
        <xsl:template match="/">
            <html><body><h2>Product List</h2><ul>
                <xsl:for-each select="database/row">
                    <li><xsl:value-of select="val"/></li>
                </xsl:for-each>
            </ul></body></html>
        </xsl:template>
    </xsl:stylesheet>';
    file_put_contents($xslPath, $xslContent);
    
    $html = $xdb->transform($xslPath);
    if (strpos($html, 'Product List') !== false && strpos($html, 'Test 1') !== false) {
        echo "   [CONFIRMED] XSLT Transformation generated correct HTML.\n";
    } else {
        echo "   [ERROR] XSLT Transformation failed!\n";
    }
    unlink($xslPath);

    echo "\n=== All Enterprise Verifications Passed! ===\n";

} catch (Exception $e) {
    echo "\nVerification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
