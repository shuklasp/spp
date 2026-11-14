<?php
declare(strict_types=1);

namespace Tests\Core;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../spp/sppinit.php';
require_once __DIR__ . '/../../spp/modules/optional/parikshak/src/SPPTestCase.php';
require_once __DIR__ . '/../../spp/admin/services/DnsZoneManager.php';
require_once __DIR__ . '/../../spp/admin/services/DomainAdminService.php';
require_once __DIR__ . '/../../spp/admin/services/CommandBridge.php';
require_once __DIR__ . '/../../spp/admin/services/AdminRBAC.php';
require_once __DIR__ . '/../../spp/modules/spp/sppstorage/src/FilePropsManager.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\Admin\Services\DnsZoneManager;
use SPP\Admin\Services\DomainAdminService;
use SPP\Admin\Services\CommandBridge;
use SPPMod\SPPStorage\FilePropsManager;

/**
 * Class DnsManifestAndRollbackTest
 *
 * Parikshak integration test suite for:
 * 1. DNS Zone point-in-time revision history tracking.
 * 2. Previewing rollback diffs across historic revisions.
 * 3. 1-click atomic zone rollback with SOA serial number increments.
 * 4. Automatic manifest synchronization registering zone files in var/dns/__spp_file_props.yml.
 * 5. DomainAdminService bridge and CommandBridge RBAC dispatch.
 */
class DnsManifestAndRollbackTest extends SPPTestCase
{
    private string $testDomain;
    private string $storageDir;
    private string $historyDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->testDomain = 'rollback-test-' . bin2hex(random_bytes(3)) . '.example.org';
        $this->storageDir = DnsZoneManager::getStorageDir();
        $this->historyDir = $this->storageDir . '/history/' . $this->testDomain;
    }

    public function tearDown(): void
    {
        $metaFile = $this->storageDir . "/{$this->testDomain}.json";
        $zoneFile = $this->storageDir . "/{$this->testDomain}.zone";
        if (file_exists($metaFile)) @unlink($metaFile);
        if (file_exists($zoneFile)) @unlink($zoneFile);

        if (is_dir($this->historyDir)) {
            $files = glob($this->historyDir . '/*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) @unlink($f);
                }
            }
            @rmdir($this->historyDir);
        }

        // Clean up domain entry from var/dns/__spp_file_props.yml
        $manifestPath = $this->storageDir . '/__spp_file_props.yml';
        if (file_exists($manifestPath)) {
            $props = FilePropsManager::loadManifest($this->storageDir);
            $zoneName = "{$this->testDomain}.zone";
            if (isset($props['files'][$zoneName])) {
                unset($props['files'][$zoneName]);
                FilePropsManager::writeManifestAtomic($manifestPath, $props);
            }
        }

        parent::tearDown();
    }

    /**
     * Test history tracking on zone mutations: save, update, delete.
     */
    public function testRecordHistoryTrackingOnMutation(): void
    {
        // 1. Initial zone setup
        $init = DnsZoneManager::getZone($this->testDomain, '203.0.113.50');
        $this->assertTrue($init['success']);

        // 2. Add A record
        $addRes = DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'portal',
            'type' => 'A',
            'value' => '198.51.100.99',
            'ttl' => 3600,
            'comment' => 'Client Portal'
        ], 'admin_test');
        $this->assertTrue($addRes['success']);
        $recordId = $addRes['record']['id'];

        // 3. Update A record
        $updateRes = DnsZoneManager::saveRecord($this->testDomain, [
            'id' => $recordId,
            'name' => 'portal',
            'type' => 'A',
            'value' => '198.51.100.100',
            'ttl' => 1800,
            'comment' => 'Client Portal Updated IP'
        ], 'admin_test');
        $this->assertTrue($updateRes['success']);

        // 4. Delete A record
        $delRes = DnsZoneManager::deleteRecord($this->testDomain, $recordId, 'admin_test');
        $this->assertTrue($delRes['success']);

        // 5. Retrieve history
        $historyRes = DnsZoneManager::getZoneHistory($this->testDomain);
        $this->assertTrue($historyRes['success']);
        $this->assertGreaterThanOrEqual(3, $historyRes['total']);

        $actions = array_column($historyRes['history'], 'action');
        $this->assertContains('save_record', $actions);
        $this->assertContains('delete_record', $actions);

        $latest = $historyRes['history'][0];
        $this->assertNotEmpty($latest['id']);
        $this->assertNotEmpty($latest['timestamp']);
        $this->assertEquals('delete_record', $latest['action']);
    }

    /**
     * Test preview rollback computes accurate record differences.
     */
    public function testPreviewRollbackComputesDiff(): void
    {
        // Initialize zone
        DnsZoneManager::getZone($this->testDomain, '203.0.113.50');

        // Capture initial revision
        $add1 = DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'v1',
            'type' => 'A',
            'value' => '10.0.0.1',
            'ttl' => 3600
        ]);
        $history1 = DnsZoneManager::getZoneHistory($this->testDomain);
        $this->assertNotEmpty($history1['history']);
        $baselineRevId = $history1['history'][0]['id'];

        // Add second record
        DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'v2',
            'type' => 'A',
            'value' => '10.0.0.2',
            'ttl' => 3600
        ]);

        // Preview rollback to baselineRevId
        $preview = DnsZoneManager::previewRollback($this->testDomain, $baselineRevId);
        $this->assertTrue($preview['success']);
        $this->assertEquals($baselineRevId, $preview['revision_id']);

        // Rolling back to baselineRevId means 'v2' will be removed
        $removedNames = array_column($preview['removed'], 'name');
        $this->assertContains('v2', $removedNames);
    }

    /**
     * Test atomic zone rollback restores prior state and increments SOA serial.
     */
    public function testRollbackZoneAppliesPriorStateAndIncrementsSerial(): void
    {
        // Setup zone
        DnsZoneManager::getZone($this->testDomain, '203.0.113.50');

        // Add record A
        DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'stable-service',
            'type' => 'A',
            'value' => '10.10.10.10',
            'ttl' => 3600
        ]);

        $histBefore = DnsZoneManager::getZoneHistory($this->testDomain);
        $goodRevId = $histBefore['history'][0]['id'];

        // Add unwanted record B
        DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'bad-deployment',
            'type' => 'A',
            'value' => '10.10.10.99',
            'ttl' => 300
        ]);

        // Check zone has bad-deployment
        $zoneDirty = DnsZoneManager::getZone($this->testDomain);
        $dirtyNames = array_column($zoneDirty['records'], 'name');
        $this->assertContains('bad-deployment', $dirtyNames);

        $dirtySerial = 0;
        foreach ($zoneDirty['records'] as $r) {
            if ($r['type'] === 'SOA') {
                $dirtySerial = (int)$r['serial'];
                break;
            }
        }

        // Execute 1-click atomic rollback to goodRevId
        $rbRes = DnsZoneManager::rollbackZone($this->testDomain, $goodRevId, 'Reverting bad deployment in test', 'admin_tester');
        $this->assertTrue($rbRes['success']);
        $this->assertGreaterThan($dirtySerial, (int)$rbRes['new_serial'], "Rollback must increment SOA serial.");

        // Verify bad-deployment is gone and stable-service remains
        $zoneClean = DnsZoneManager::getZone($this->testDomain);
        $cleanNames = array_column($zoneClean['records'], 'name');
        $this->assertNotContains('bad-deployment', $cleanNames);
        $this->assertContains('stable-service', $cleanNames);

        // Verify new history entry created for the rollback
        $histAfter = DnsZoneManager::getZoneHistory($this->testDomain);
        $this->assertEquals('rollback', $histAfter['history'][0]['action']);
        $this->assertEquals($goodRevId, $histAfter['history'][0]['rolled_back_to']);
    }

    /**
     * Test synchronizing zone metadata into var/dns/__spp_file_props.yml.
     */
    public function testSyncToManifestRegistersZoneInFileProps(): void
    {
        // Create and populate zone
        DnsZoneManager::getZone($this->testDomain, '203.0.113.77');
        DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'cdn',
            'type' => 'CNAME',
            'value' => 'fastly.net.',
            'ttl' => 3600
        ]);

        // Sync to manifest
        $syncRes = DnsZoneManager::syncToManifest($this->testDomain);
        $this->assertTrue($syncRes['success']);

        $manifestPath = $this->storageDir . '/__spp_file_props.yml';
        $this->assertTrue(file_exists($manifestPath), "Manifest file must exist in var/dns.");

        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $zoneKey = "{$this->testDomain}.zone";
        $this->assertArrayHasKey($zoneKey, $parsed['files'] ?? [], "Zone file must be registered in manifest.");

        $entry = $parsed['files'][$zoneKey];
        $this->assertStringContainsString($this->testDomain, $entry['description']);
        $this->assertNotEmpty($entry['checksum'], "Zone file checksum must be calculated.");
        $this->assertNotEmpty($entry['security']);
        $this->assertTrue($entry['security']['allow_network'], "DNS zone security policy must declare allow_network.");
        $this->assertTrue($entry['security']['allow_filesystem'], "DNS zone security policy must declare allow_filesystem.");
        $this->assertFalse($entry['security']['allow_shell'], "DNS zone security policy must block allow_shell.");
    }

    /**
     * Test DomainAdminService bridge methods.
     */
    public function testDomainAdminServiceBridgeMethods(): void
    {
        DnsZoneManager::getZone($this->testDomain, '203.0.113.88');
        DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'bridge-test',
            'type' => 'TXT',
            'value' => 'v=spf1 -all',
            'ttl' => 3600
        ]);

        // 1. History
        $hist = DomainAdminService::getZoneHistory($this->testDomain);
        $this->assertTrue($hist['success']);
        $this->assertNotEmpty($hist['history']);
        $revId = $hist['history'][0]['id'];

        // 2. Preview rollback
        $prev = DomainAdminService::previewZoneRollback($this->testDomain, $revId);
        $this->assertTrue($prev['success']);

        // 3. Rollback
        $rb = DomainAdminService::rollbackZone($this->testDomain, $revId, 'Bridge test rollback');
        $this->assertTrue($rb['success']);

        // 4. Sync manifest
        $sync = DomainAdminService::syncZoneManifest($this->testDomain);
        $this->assertTrue($sync['success']);
    }

    /**
     * Test AdminRBAC gating and CommandBridge action dispatch.
     */
    public function testCommandBridgeAndRbacDnsDispatch(): void
    {
        DnsZoneManager::getZone($this->testDomain, '203.0.113.99');
        DnsZoneManager::saveRecord($this->testDomain, [
            'name' => 'cb-test',
            'type' => 'A',
            'value' => '1.1.1.1',
            'ttl' => 300
        ]);

        // 1. RBAC must allow mapped actions
        $this->assertTrue(gateAdminAction('domains_zone_history'));
        $this->assertTrue(gateAdminAction('domains_preview_rollback'));
        $this->assertTrue(gateAdminAction('domains_rollback_zone'));
        $this->assertTrue(gateAdminAction('domains_sync_manifest'));

        // 2. CommandBridge dispatch
        $cbHist = CommandBridge::handleAction('domains_zone_history', ['domain' => $this->testDomain]);
        $this->assertTrue($cbHist['success']);
        $this->assertArrayHasKey('history', $cbHist);

        $cbSync = CommandBridge::handleAction('domains_sync_manifest', ['domain' => $this->testDomain]);
        $this->assertTrue($cbSync['success']);
    }
}
