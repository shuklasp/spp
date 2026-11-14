<?php
declare(strict_types=1);

namespace Tests\Core;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../spp/sppinit.php';
require_once __DIR__ . '/../../spp/modules/optional/parikshak/src/SPPTestCase.php';
require_once __DIR__ . '/../../spp/admin/services/DomainAdminService.php';
require_once __DIR__ . '/../../spp/admin/services/CommandBridge.php';
require_once __DIR__ . '/../../spp/admin/services/AdminRBAC.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\Admin\Services\DomainAdminService;
use SPP\Admin\Services\CommandBridge;
use SPP\Core\Router\DomainRouter;

/**
 * Class DomainAutoPathsAndScaffoldingParikshakTest
 *
 * Verifies accurate calculation and physical scaffolding of domain application
 * and persistent sovereign sppdata paths:
 * 1. Linux user home isolated paths: /home/{account}/domains/{domain}/apps and .../sppdata
 * 2. Framework default paths: domains/{domain}/apps and domains/{domain}/sppdata
 * 3. Automatic apex account derivation when account is empty or default
 * 4. Physical directory creation with valid Manifest OS __spp_file_props.yml contracts
 * 5. CommandBridge API dispatching & AdminRBAC security scope mapping
 * 6. Integration with saveDomain() to ensure auto-scaffolding on save
 */
class DomainAutoPathsAndScaffoldingParikshakTest extends SPPTestCase
{
    private string $testWorkDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->testWorkDir = sys_get_temp_dir() . '/spp_scaffold_test_' . bin2hex(random_bytes(6));
        @mkdir($this->testWorkDir, 0777, true);
        DomainAdminService::setScaffoldRoot($this->testWorkDir);
    }

    public function tearDown(): void
    {
        DomainAdminService::setScaffoldRoot(null);
        if (is_dir($this->testWorkDir)) {
            $this->recursiveDelete($this->testWorkDir);
        }
        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testScaffoldDomainPathsWithCustomAccount(): void
    {
        $res = DomainAdminService::scaffoldDomainPaths('satyalab.net', 'satyalab', null, null, false);

        $this->assertTrue($res['success']);
        $this->assertSame('satyalab.net', $res['domain']);
        $this->assertSame('satyalab', $res['account']);
        $this->assertSame('/home/satyalab/domains/satyalab.net/apps', $res['app_dir']);
        $this->assertSame('/home/satyalab/domains/satyalab.net/sppdata', $res['data_dir']);
    }

    public function testScaffoldDomainPathsWithDefaultAccount(): void
    {
        $res = DomainAdminService::scaffoldDomainPaths('example.org', 'default', null, null, false);

        $this->assertTrue($res['success']);
        $this->assertSame('example.org', $res['domain']);
        $this->assertSame('default', $res['account']);
        $this->assertSame('domains/example.org/apps', $res['app_dir']);
        $this->assertSame('domains/example.org/sppdata', $res['data_dir']);
    }

    public function testScaffoldDomainPathsAutoDerivesApexAccountWhenEmpty(): void
    {
        $res = DomainAdminService::scaffoldDomainPaths('satyalab.net', '', null, null, false);

        $this->assertTrue($res['success']);
        $this->assertSame('satyalab.net', $res['domain']);
        $this->assertSame('satyalab', $res['account']);
        $this->assertSame('/home/satyalab/domains/satyalab.net/apps', $res['app_dir']);
        $this->assertSame('/home/satyalab/domains/satyalab.net/sppdata', $res['data_dir']);
    }

    public function testPhysicalScaffoldingAndManifestOsContract(): void
    {
        $res = DomainAdminService::scaffoldDomainPaths('vshiksha.com', 'satya', null, null, true);

        $this->assertTrue($res['success']);
        $this->assertSame('/home/satya/domains/vshiksha.com/apps', $res['app_dir']);
        $this->assertSame('/home/satya/domains/vshiksha.com/sppdata', $res['data_dir']);

        $expectedAppDir = $this->testWorkDir . '/home/satya/domains/vshiksha.com/apps';
        $expectedDataDir = $this->testWorkDir . '/home/satya/domains/vshiksha.com/sppdata';

        $this->assertTrue(is_dir($expectedAppDir), "Expected app directory must exist on disk: {$expectedAppDir}");
        $this->assertTrue(is_dir($expectedDataDir), "Expected sppdata directory must exist on disk: {$expectedDataDir}");

        $appProps = $expectedAppDir . '/__spp_file_props.yml';
        $dataProps = $expectedDataDir . '/__spp_file_props.yml';

        $this->assertTrue(file_exists($appProps), "App directory must contain __spp_file_props.yml");
        $this->assertTrue(file_exists($dataProps), "Data directory must contain __spp_file_props.yml");

        $appYaml = (string)file_get_contents($appProps);
        $this->assertStringContainsString("version: '2.0'", $appYaml);
        $this->assertStringContainsString("domain: 'vshiksha.com'", $appYaml);
        $this->assertStringContainsString("account: 'satya'", $appYaml);
        $this->assertStringContainsString("scope: 'domain_apps'", $appYaml);

        $dataYaml = (string)file_get_contents($dataProps);
        $this->assertStringContainsString("version: '2.0'", $dataYaml);
        $this->assertStringContainsString("domain: 'vshiksha.com'", $dataYaml);
        $this->assertStringContainsString("account: 'satya'", $dataYaml);
        $this->assertStringContainsString("scope: 'domain_data'", $dataYaml);
    }

    public function testCommandBridgeDispatching(): void
    {
        $res = CommandBridge::handle('domains_scaffold_paths', [
            'domain' => 'satyalab.net',
            'account' => 'satyalab',
            'create_dirs' => 0
        ]);

        $this->assertTrue($res['success']);
        $this->assertSame('/home/satyalab/domains/satyalab.net/apps', $res['app_dir']);
        $this->assertSame('/home/satyalab/domains/satyalab.net/sppdata', $res['data_dir']);
    }

    public function testAdminRBACMapping(): void
    {
        $scopeMap = \getActionScopeMap();

        $this->assertArrayHasKey('domains_scaffold_paths', $scopeMap);
        $this->assertSame('admin.domains', $scopeMap['domains_scaffold_paths']);
        $this->assertArrayHasKey('domain_scaffold_paths', $scopeMap);
        $this->assertSame('admin.domains', $scopeMap['domain_scaffold_paths']);
    }

    public function testSaveDomainAutoScaffolding(): void
    {
        $domainName = 'autotest-' . bin2hex(random_bytes(3)) . '.net';
        $appDir = "/home/testuser/domains/{$domainName}/apps";
        $dataDir = "/home/testuser/domains/{$domainName}/sppdata";

        try {
            $saveRes = DomainAdminService::saveDomain($domainName, [
                'account' => 'testuser',
                'app_dir' => $appDir,
                'data_dir' => $dataDir,
                'default_app' => 'default',
                'routing_mode' => 'spp_path'
            ]);

            $this->assertTrue($saveRes['success'], 'saveDomain must succeed');

            $expectedAppDir = $this->testWorkDir . "/home/testuser/domains/{$domainName}/apps";
            $expectedDataDir = $this->testWorkDir . "/home/testuser/domains/{$domainName}/sppdata";

            $this->assertTrue(is_dir($expectedAppDir), "saveDomain must have scaffolded {$expectedAppDir}");
            $this->assertTrue(is_dir($expectedDataDir), "saveDomain must have scaffolded {$expectedDataDir}");
            $this->assertTrue(file_exists($expectedAppDir . '/__spp_file_props.yml'));
            $this->assertTrue(file_exists($expectedDataDir . '/__spp_file_props.yml'));
        } finally {
            DomainAdminService::deleteDomain($domainName);
        }
    }

    public function testSaveDomainRenameWithOriginalPattern(): void
    {
        $oldDomain = 'old-typo-' . bin2hex(random_bytes(3)) . '.net';
        $newDomain = 'new-correct-' . bin2hex(random_bytes(3)) . '.net';

        try {
            // First save initial domain with typo
            $save1 = DomainAdminService::saveDomain($oldDomain, [
                'account' => 'testuser',
                'default_app' => 'default',
            ]);
            $this->assertTrue($save1['success']);
            $this->assertArrayHasKey($oldDomain, DomainRouter::getAllDomains());

            // Now rename to new domain using original_pattern
            $save2 = DomainAdminService::saveDomain($newDomain, [
                'original_pattern' => $oldDomain,
                'account' => 'testuser',
                'default_app' => 'default',
            ]);
            $this->assertTrue($save2['success']);

            $allDomains = DomainRouter::getAllDomains();
            $this->assertArrayNotHasKey($oldDomain, $allDomains, 'Old typo domain must be removed');
            $this->assertArrayHasKey($newDomain, $allDomains, 'New correct domain must exist');
        } finally {
            DomainAdminService::deleteDomain($oldDomain);
            DomainAdminService::deleteDomain($newDomain);
        }
    }
}
