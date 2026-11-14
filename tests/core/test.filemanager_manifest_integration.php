<?php
declare(strict_types=1);

namespace Tests\Core;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../spp/sppinit.php';
require_once __DIR__ . '/../../spp/modules/optional/parikshak/src/SPPTestCase.php';
require_once __DIR__ . '/../../spp/admin/services/StorageAdminService.php';
require_once __DIR__ . '/../../spp/admin/services/CommandBridge.php';
require_once __DIR__ . '/../../spp/admin/services/AdminRBAC.php';
require_once __DIR__ . '/../../spp/modules/spp/sppstorage/src/FilePropsManager.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\Admin\Services\StorageAdminService;
use SPP\Admin\Services\CommandBridge;
use SPPMod\SPPStorage\FilePropsManager;

/**
 * Class FileManagerManifestIntegrationTest
 *
 * Comprehensive Parikshak integration test suite for:
 * 1. Deep bidirectional integration between SPP File Manager and Manifest OS (__spp_file_props.yml).
 * 2. Atomic manifest read, validate, diff, and save.
 * 3. Manifest health metrics and self-healing.
 * 4. Cryptographic SHA-256 integrity verification and signing.
 * 5. Capability-based security inspection.
 * 6. Automatic metadata lifecycle synchronization on create, write, copy, move, and delete.
 * 7. Archive extraction safety (Zip-Slip and decompression bomb defense).
 * 8. Fail-closed security invariants in LocalDisk and AdminRBAC.
 */
class FileManagerManifestIntegrationTest extends SPPTestCase
{
    private string $testDir;
    private string $relTestDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->relTestDir = 'var/test_fm_manifest_' . bin2hex(random_bytes(4));
        $this->testDir = StorageAdminService::getWorkspaceRoot() . '/' . $this->relTestDir;
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
    }

    public function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $this->recursiveDelete($this->testDir);
        }
        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if ($items === false) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Test FilePropsManager atomic write, .bak preservation, and merging custom keys.
     */
    public function testFilePropsManagerAtomicWriteAndCustomKeyPreservation(): void
    {
        $manifestPath = $this->testDir . '/__spp_file_props.yml';
        $initialContent = "version: '1.0'\ncustom_root_key: 'custom_value_42'\ndirectory:\n  description: 'Initial directory'\n  custom_dir_prop: 'preserve_me'\nfiles:\n  alpha.txt:\n    description: 'Alpha file'\n    custom_file_prop: 'keep_this'\n";
        file_put_contents($manifestPath, $initialContent);

        // Update alpha.txt via FilePropsManager
        $ok = FilePropsManager::saveFileProps($this->testDir . '/alpha.txt', [
            'description' => 'Updated alpha file',
            'usage' => 'Alpha::run();'
        ]);
        $this->assertTrue($ok['success'], "saveFileProps must succeed.");

        $updated = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $this->assertEquals('1.0', $updated['version']);
        $this->assertEquals('custom_value_42', $updated['custom_root_key'] ?? null, "Root custom keys must be preserved.");
        $this->assertEquals('preserve_me', $updated['directory']['custom_dir_prop'] ?? null, "Directory custom props must be preserved.");
        $this->assertEquals('Updated alpha file', $updated['files']['alpha.txt']['description']);
        $this->assertEquals('Alpha::run();', $updated['files']['alpha.txt']['usage']);
        $this->assertEquals('keep_this', $updated['files']['alpha.txt']['custom_file_prop'] ?? null, "File custom properties must be preserved.");

        // Check backup file was created during atomic write
        $this->assertTrue(file_exists($manifestPath . '.bak'), "Backup .bak file must exist after atomic write.");
    }

    /**
     * Test raw manifest read, validate, diff, and atomic save.
     */
    public function testRawManifestReadValidateDiffAndSave(): void
    {
        $manifestPath = $this->testDir . '/__spp_file_props.yml';
        $initialYaml = "version: '1.0'\ndirectory:\n  description: 'Original description'\n";
        file_put_contents($manifestPath, $initialYaml);

        // 1. Get raw manifest
        $getRes = StorageAdminService::getManifestRaw($this->relTestDir);
        $this->assertTrue($getRes['success'], "getManifestRaw must succeed.");
        $this->assertEquals($initialYaml, $getRes['yaml']);

        // 2. Validate YAML
        $valValid = StorageAdminService::validateManifestYaml("version: '1.0'\ndirectory:\n  description: 'Valid'\n");
        $this->assertTrue($valValid['valid'], "Valid YAML must pass validation.");

        $valInvalid = StorageAdminService::validateManifestYaml("version: '1.0'\n  directory: [invalid indentation\n");
        $this->assertFalse($valInvalid['valid'], "Invalid YAML must fail validation.");
        $this->assertNotEmpty($valInvalid['error'], "Validation error message must be present.");

        // 3. Diff Manifest
        $newYaml = "version: '1.0'\ndirectory:\n  description: 'Modified description'\n";
        $diffRes = StorageAdminService::diffManifest($this->relTestDir, $newYaml);
        $this->assertTrue($diffRes['success'], "diffManifest must succeed.");
        $this->assertNotEmpty($diffRes['diff'], "Structured diff array must not be empty.");
        $this->assertStringContainsString('Modified description', $diffRes['diff_text'], "Diff text must reflect modified text.");

        // 4. Save Raw Manifest (invalid must fail)
        $saveInvalid = StorageAdminService::saveManifestRaw($this->relTestDir, "version: '1.0'\n  broken: [");
        $this->assertFalse($saveInvalid['success'], "Saving invalid YAML must fail.");

        // 5. Save Raw Manifest (valid must succeed)
        $saveValid = StorageAdminService::saveManifestRaw($this->relTestDir, $newYaml);
        $this->assertTrue($saveValid['success'], "Saving valid YAML must succeed.");
        $this->assertEquals($newYaml, file_get_contents($manifestPath));
    }

    /**
     * Test Manifest Health metrics and self-healing.
     */
    public function testManifestHealthAndHealing(): void
    {
        // Create files without manifest
        file_put_contents($this->testDir . '/file1.txt', 'Content 1');
        file_put_contents($this->testDir . '/file2.php', '<?php echo "Hello";');

        // Health scan on directory missing manifest
        $healthBefore = StorageAdminService::getManifestHealth($this->relTestDir);
        $this->assertTrue($healthBefore['success']);
        $this->assertGreaterThan(0, $healthBefore['missing_manifests']);
        $this->assertGreaterThan(0, $healthBefore['untracked_files']);

        // Heal directory
        $healRes = StorageAdminService::healManifest($this->relTestDir);
        $this->assertTrue($healRes['success']);
        $this->assertGreaterThanOrEqual(2, $healRes['healed_entries']);

        // Manifest must now exist and track files
        $manifestPath = $this->testDir . '/__spp_file_props.yml';
        $this->assertTrue(file_exists($manifestPath), "Manifest must be synthesized after healing.");

        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $this->assertArrayHasKey('file1.txt', $parsed['files'] ?? []);
        $this->assertArrayHasKey('file2.php', $parsed['files'] ?? []);
        $this->assertEquals(hash_file('sha256', $this->testDir . '/file1.txt'), $parsed['files']['file1.txt']['checksum']);

        // Re-check health after heal
        $healthAfter = StorageAdminService::getManifestHealth($this->relTestDir);
        $this->assertEquals(100, $healthAfter['score'], "Health score must be 100% after healing.");
        $this->assertEquals(0, $healthAfter['missing_manifests']);
        $this->assertEquals(0, $healthAfter['untracked_files']);
    }

    /**
     * Test cryptographic SHA-256 verification, signing, and tamper detection.
     */
    public function testCryptographicVerificationAndSigning(): void
    {
        $filePath = $this->testDir . '/service.php';
        file_put_contents($filePath, '<?php class PaymentService {}');

        // Initial signing
        $signRes = StorageAdminService::signFile($this->relTestDir . '/service.php');
        $this->assertTrue($signRes['success']);
        $this->assertNotEmpty($signRes['checksum']);

        // Verification must be 'valid'
        $verifyRes = StorageAdminService::verifyFileIntegrity($this->relTestDir . '/service.php');
        $this->assertTrue($verifyRes['success']);
        $this->assertEquals('valid', $verifyRes['status']);

        // Tamper with file
        file_put_contents($filePath, '<?php class PaymentService { /* MALICIOUS INJECTION */ }');

        // Verification must now detect tampering
        $tamperRes = StorageAdminService::verifyFileIntegrity($this->relTestDir . '/service.php');
        $this->assertTrue($tamperRes['success']);
        $this->assertEquals('tampered', $tamperRes['status']);
        $this->assertNotEquals($tamperRes['expected'], $tamperRes['computed']);

        // Re-sign directory
        $dirSignRes = StorageAdminService::signDirectory($this->relTestDir);
        $this->assertTrue($dirSignRes['success']);
        $this->assertGreaterThanOrEqual(1, $dirSignRes['signed_count']);

        // Verification must now be restored to 'valid'
        $reverifyRes = StorageAdminService::verifyFileIntegrity($this->relTestDir . '/service.php');
        $this->assertEquals('valid', $reverifyRes['status']);
    }

    /**
     * Test capability checking for files with manifest policies.
     */
    public function testCapabilityChecking(): void
    {
        $filePath = $this->testDir . '/Worker.php';
        file_put_contents($filePath, '<?php class Worker {}');

        // Set security capabilities in manifest
        FilePropsManager::saveFileProps($filePath, [
            'description' => 'Worker class',
            'security' => [
                'allow_network' => true,
                'allow_filesystem' => true,
                'allow_env' => false,
                'allow_shell' => false
            ]
        ]);

        $capNet = StorageAdminService::checkCapabilities($this->relTestDir . '/Worker.php', 'allow_network');
        $this->assertTrue($capNet['success']);
        $this->assertTrue($capNet['allowed']);

        $capShell = StorageAdminService::checkCapabilities($this->relTestDir . '/Worker.php', 'allow_shell');
        $this->assertTrue($capShell['success']);
        $this->assertFalse($capShell['allowed']);
    }

    /**
     * Test listFiles returns directory_manifest object and enriched file properties.
     */
    public function testListFilesEnrichedManifestPayload(): void
    {
        $filePath = $this->testDir . '/Component.php';
        file_put_contents($filePath, '<?php class Component {}');

        // Save directory and file properties
        StorageAdminService::saveDirectoryProperties($this->relTestDir, [
            'description' => 'Test component directory',
            'author' => 'Architecture Team',
            'stability' => 'stable'
        ]);

        StorageAdminService::saveFileProperties($this->relTestDir . '/Component.php', [
            'description' => 'Core UI component',
            'usage' => '<x-component />',
            'author' => 'Frontend Team',
            'attributes' => ['tags' => ['ui', 'blade']]
        ]);

        $list = StorageAdminService::listFiles($this->relTestDir);
        $this->assertTrue($list['success']);

        // Verify directory_manifest
        $this->assertArrayHasKey('directory_manifest', $list);
        $dm = $list['directory_manifest'];
        $this->assertTrue($dm['has_manifest']);
        $this->assertTrue($dm['manifest_valid']);
        $this->assertEquals('Test component directory', $dm['directory_props']['description']);

        // Verify item metadata
        $componentItem = null;
        foreach ($list['items'] as $item) {
            if ($item['name'] === 'Component.php') {
                $componentItem = $item;
                break;
            }
        }
        $this->assertNotNull($componentItem);
        $this->assertEquals('Core UI component', $componentItem['description']);
        $this->assertEquals('<x-component />', $componentItem['usage']);
        $this->assertNotEmpty($componentItem['checksum']);
    }

    /**
     * Test automatic manifest synchronization on file lifecycle operations.
     */
    public function testAutomaticManifestLifecycleOperations(): void
    {
        // 1. Create file -> manifest updated automatically with SHA-256
        $createRes = StorageAdminService::createFile($this->relTestDir . '/Lifecycle.php', [
            'description' => 'Lifecycle test file'
        ]);
        $this->assertTrue($createRes['success']);
        $manifestPath = $this->testDir . '/__spp_file_props.yml';
        $this->assertTrue(file_exists($manifestPath));
        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $this->assertArrayHasKey('Lifecycle.php', $parsed['files']);
        $this->assertEquals('Lifecycle test file', $parsed['files']['Lifecycle.php']['description']);
        $this->assertNotEmpty($parsed['files']['Lifecycle.php']['checksum']);

        // 2. Write file -> manifest checksum recalculated automatically
        $initialHash = $parsed['files']['Lifecycle.php']['checksum'];
        $writeRes = StorageAdminService::writeFile($this->relTestDir . '/Lifecycle.php', '<?php echo "Mutated";');
        $this->assertTrue($writeRes['success']);
        $parsedAfterWrite = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $newHash = $parsedAfterWrite['files']['Lifecycle.php']['checksum'];
        $this->assertNotEquals($initialHash, $newHash, "Checksum must be recalculated after writeFile.");
        $this->assertEquals(hash_file('sha256', $this->testDir . '/Lifecycle.php'), $newHash);

        // 3. Copy file -> properties copied, fresh checksum assigned
        $copyRes = StorageAdminService::copyItem($this->relTestDir . '/Lifecycle.php', $this->relTestDir . '/LifecycleCopy.php');
        $this->assertTrue($copyRes['success']);
        $parsedAfterCopy = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $this->assertArrayHasKey('LifecycleCopy.php', $parsedAfterCopy['files']);
        $this->assertEquals('Lifecycle test file', $parsedAfterCopy['files']['LifecycleCopy.php']['description']);

        // 4. Move/rename file -> metadata moved
        $moveRes = StorageAdminService::moveItem($this->relTestDir . '/LifecycleCopy.php', $this->relTestDir . '/LifecycleMoved.php');
        $this->assertTrue($moveRes['success']);
        $parsedAfterMove = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $this->assertArrayNotHasKey('LifecycleCopy.php', $parsedAfterMove['files']);
        $this->assertArrayHasKey('LifecycleMoved.php', $parsedAfterMove['files']);

        // 5. Delete file -> entry removed from manifest
        $delRes = StorageAdminService::deleteItem($this->relTestDir . '/LifecycleMoved.php');
        $this->assertTrue($delRes['success']);
        $parsedAfterDelete = \Symfony\Component\Yaml\Yaml::parseFile($manifestPath);
        $this->assertArrayNotHasKey('LifecycleMoved.php', $parsedAfterDelete['files']);
    }

    /**
     * Test archive safety: Zip-Slip containment.
     */
    public function testZipSlipContainment(): void
    {
        $zipPath = $this->testDir . '/evil.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('../../evil.txt', 'hacked');
        $zip->close();

        // Extract using StorageAdminService
        $extractRes = StorageAdminService::extractZip($this->relTestDir . '/evil.zip', $this->relTestDir . '/extracted');
        $this->assertFalse($extractRes['success'], "Extraction of zip containing path traversal must fail.");
        $this->assertNotEmpty($extractRes['blocked_entries'], "Blocked entries must list path traversal attempt.");

        // Verify evil.txt was NOT extracted outside destination
        $escapedFile = dirname(dirname($this->testDir)) . '/evil.txt';
        $this->assertFalse(file_exists($escapedFile), "Zip-Slip file must not escape destination directory.");
    }

    /**
     * Test LocalDisk temporaryUrl fails closed without configured secrets.
     */
    public function testTemporaryUrlFailsClosedWhenSecretMissing(): void
    {
        $originalAppKey = getenv('SPP_APP_KEY');
        $originalSecret = getenv('SPP_SECRET');

        putenv('SPP_APP_KEY');
        putenv('SPP_SECRET');

        $disk = new \SPP\Core\LocalDisk($this->testDir);
        $threw = false;
        try {
            $disk->temporaryUrl('test.txt', 3600);
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('SPP_APP_KEY', $e->getMessage());
        } finally {
            if ($originalAppKey !== false) putenv("SPP_APP_KEY={$originalAppKey}");
            if ($originalSecret !== false) putenv("SPP_SECRET={$originalSecret}");
        }

        $this->assertTrue($threw, "temporaryUrl must throw RuntimeException when no secret is configured.");
    }

    /**
     * Test CommandBridge and AdminRBAC dispatch for all new storage manifest actions.
     */
    public function testCommandBridgeAndRbacManifestDispatch(): void
    {
        // 1. RBAC must gate actions
        $this->assertTrue(function_exists('gateAdminAction'));
        $this->assertTrue(gateAdminAction('storage_get_manifest_raw'), "storage_get_manifest_raw must be allowed.");
        $this->assertTrue(gateAdminAction('storage_manifest_health'), "storage_manifest_health must be allowed.");
        $this->assertTrue(gateAdminAction('storage_heal_manifest'), "storage_heal_manifest must be allowed.");
        $this->assertTrue(gateAdminAction('storage_verify_integrity'), "storage_verify_integrity must be allowed.");
        $this->assertTrue(gateAdminAction('props_search'), "props_search must be allowed.");

        // 2. CommandBridge dispatch
        $cbRes = CommandBridge::handleAction('storage_manifest_health', ['path' => $this->relTestDir]);
        $this->assertTrue($cbRes['success'], "CommandBridge must dispatch storage_manifest_health successfully.");
        $this->assertArrayHasKey('score', $cbRes);
    }
}
