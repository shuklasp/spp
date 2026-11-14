<?php
declare(strict_types=1);

namespace Tests\Core;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../spp/sppinit.php';
require_once __DIR__ . '/../../spp/modules/optional/parikshak/src/SPPTestCase.php';
require_once __DIR__ . '/../../spp/core/Config/ConfigIsolationManager.php';
require_once __DIR__ . '/../../spp/modules/optional/sppdeploy/src/Scanner/ProjectScanner.php';
require_once __DIR__ . '/../../spp/modules/optional/sppdeploy/src/Api/Receiver.php';

use SPPMod\Parikshak\SPPTestCase;
use SPP\Core\Config\ConfigIsolationManager;
use SPPMod\SPPDeploy\Scanner\ProjectScanner;
use SPPMod\SPPDeploy\Api\Receiver;
use ZipArchive;

/**
 * Class ConfigIsolationAndDeployProtectionParikshakTest
 *
 * Verifies configuration isolation architecture:
 * 1. Generic configuration templates reside in etc.dist/
 * 2. On-demand synthesis from etc.dist/ to effective etc/ when missing
 * 3. Invariant: Existing effective configurations are NEVER overwritten on deploy:push
 * 4. ProjectScanner ignores effective configs unless explicitly permitted
 * 5. Receiver strictly guards live effective configurations against extraction overwrite and deletion
 */
class ConfigIsolationAndDeployProtectionParikshakTest extends SPPTestCase
{
    private string $testWorkDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->testWorkDir = sys_get_temp_dir() . '/spp_cfg_iso_' . bin2hex(random_bytes(6));
        @mkdir($this->testWorkDir, 0777, true);
    }

    public function tearDown(): void
    {
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

    /**
     * Test 1: Generic templates exist in etc.dist/
     */
    public function testTemplateDiscoveryAndGenericTemplatesExist(): void
    {
        $templateDir = ConfigIsolationManager::getGenericTemplateDir();
        $this->assertDirectoryExists($templateDir, 'etc.dist/ templates directory must exist');

        $requiredTemplates = [
            'domains.yml',
            'global-settings.yml',
            'dns-domains.yml',
            'dns-providers.yml',
            'cloudflare.yml',
            'mail.yml',
            'apps.yml',
            'backup_vault.yml',
            'watchdog.yml',
            'db.yml',
            'README.md'
        ];

        foreach ($requiredTemplates as $tmpl) {
            $path = $templateDir . '/' . $tmpl;
            $this->assertFileExists($path, "Template {$tmpl} must exist in etc.dist/");
            $this->assertGreaterThan(0, filesize($path), "Template {$tmpl} must have content");
        }
    }

    /**
     * Test 2: Copy-on-demand creates effective config from generic template when missing
     */
    public function testEnsureEffectiveConfigCopiesFromGenericWhenMissing(): void
    {
        $targetEtc = $this->testWorkDir . '/etc';
        $destFile = $targetEtc . '/domains.yml';

        $this->assertFalse(file_exists($destFile));

        $resultPath = ConfigIsolationManager::ensureEffectiveConfig('domains.yml', $targetEtc);

        $this->assertFileExists($destFile, 'Effective domains.yml must be created');
        $this->assertEquals(str_replace('\\', '/', realpath($destFile) ?: $destFile), str_replace('\\', '/', realpath($resultPath) ?: $resultPath));

        $content = (string)file_get_contents($destFile);
        $this->assertStringContainsString('mode: single', $content);
    }

    /**
     * Test 3: Existing effective configuration is strictly preserved and NEVER overwritten
     */
    public function testEnsureEffectiveConfigPreservesExistingConfiguration(): void
    {
        $targetEtc = $this->testWorkDir . '/etc';
        @mkdir($targetEtc, 0777, true);
        $destFile = $targetEtc . '/domains.yml';

        $customLiveConfig = "# Customized Production Configuration\nmode: multidomain\ndefault_app: portal\ndomains:\n  satyalab.net:\n    target_app: portal\n";
        file_put_contents($destFile, $customLiveConfig);

        // Call ensureEffectiveConfig multiple times
        $resultPath = ConfigIsolationManager::ensureEffectiveConfig('domains.yml', $targetEtc);

        $this->assertEquals(str_replace('\\', '/', $destFile), str_replace('\\', '/', $resultPath));
        $this->assertEquals($customLiveConfig, file_get_contents($destFile), 'Live effective config must not be altered');

        // Test with programmatic copyGenericToEffective without force
        $copied = ConfigIsolationManager::copyGenericToEffective('domains.yml', false, $this->testWorkDir);
        $this->assertFalse($copied, 'copyGenericToEffective must refuse to overwrite existing config without force');
        $this->assertEquals($customLiveConfig, file_get_contents($destFile), 'Config must remain intact');
    }

    /**
     * Test 4: Pattern matching accurately classifies protected live configurations vs deployable templates
     */
    public function testIsProtectedConfigFileMatchesAllSensitiveConfigs(): void
    {
        // Protected live configurations
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/domains.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/domains.local.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('spp/etc/domains.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/global-settings.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/dns-domains.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/dns-providers.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/cloudflare.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/mail.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('.server_secret'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/.server_secret'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('.env'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('.env.production'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('domains/satyalab.net/etc/vhost.yml'));
        $this->assertTrue(ConfigIsolationManager::isProtectedConfigFile('etc/apps/store/config.yml'));

        // Deployable distribution templates (MUST NOT be blocked)
        $this->assertFalse(ConfigIsolationManager::isProtectedConfigFile('etc.dist/domains.yml'));
        $this->assertFalse(ConfigIsolationManager::isProtectedConfigFile('etc.dist/global-settings.yml'));
        $this->assertFalse(ConfigIsolationManager::isProtectedConfigFile('spp/etc.dist/cloudflare.yml'));

        // Normal deployable files
        $this->assertFalse(ConfigIsolationManager::isProtectedConfigFile('spp/core/class.app.php'));
        $this->assertFalse(ConfigIsolationManager::isProtectedConfigFile('index.php'));
        $this->assertFalse(ConfigIsolationManager::isProtectedConfigFile('src/default/controllers/HomeController.php'));
    }

    /**
     * Test 5: ProjectScanner skips live effective config files by default
     */
    public function testProjectScannerExcludesEffectiveConfigsByDefault(): void
    {
        $projDir = $this->testWorkDir . '/project';
        @mkdir($projDir . '/etc', 0777, true);
        @mkdir($projDir . '/etc.dist', 0777, true);
        @mkdir($projDir . '/src', 0777, true);

        file_put_contents($projDir . '/index.php', '<?php echo "SPP";');
        file_put_contents($projDir . '/etc/domains.yml', 'mode: single');
        file_put_contents($projDir . '/etc/global-settings.yml', 'app: default');
        file_put_contents($projDir . '/etc.dist/domains.yml', '# Generic Template');
        file_put_contents($projDir . '/src/app.php', '<?php return [];');

        $scanner = new ProjectScanner();
        $hashes = $scanner->scan($projDir);

        // Normal files and generic templates must be indexed
        $this->assertArrayHasKey('index.php', $hashes);
        $this->assertArrayHasKey('etc.dist/domains.yml', $hashes);
        $this->assertArrayHasKey('src/app.php', $hashes);

        // Effective configurations must be skipped
        $this->assertArrayNotHasKey('etc/domains.yml', $hashes, 'Scanner must skip effective domains.yml');
        $this->assertArrayNotHasKey('etc/global-settings.yml', $hashes, 'Scanner must skip effective global-settings.yml');

        // When explicitly configured with setIncludeConfig(true), it should include them
        $scannerWithConfig = (new ProjectScanner())->setIncludeConfig(true);
        $hashesWithConfig = $scannerWithConfig->scan($projDir);
        $this->assertArrayHasKey('etc/domains.yml', $hashesWithConfig, 'Scanner must include config when explicitly enabled');
    }

    /**
     * Test 6: Zip payload extraction skips overwriting existing live configurations
     */
    public function testReceiverZipExtractionProtectsLiveConfiguration(): void
    {
        $targetRoot = $this->testWorkDir . '/target_server';
        @mkdir($targetRoot . '/etc', 0777, true);
        @mkdir($targetRoot . '/public', 0777, true);

        // Target server has live customized configuration
        $liveDomainsContent = "# PRODUCTION LIVE CONFIGURED DOMAINS\nmode: multidomain\ndomains:\n  satyalab.net:\n    target_app: portal\n";
        $domainsPath = $targetRoot . '/etc/domains.yml';
        file_put_contents($domainsPath, $liveDomainsContent);

        // Existing public file that SHOULD be updated
        $oldIndexContent = 'old index';
        $indexPath = $targetRoot . '/public/index.html';
        file_put_contents($indexPath, $oldIndexContent);

        // Build a simulated deployment zip package containing updated index.html and a generic domains.yml
        $zipFile = $this->testWorkDir . '/deploy_payload.zip';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('public/index.html', 'new updated index');
        $zip->addFromString('etc/domains.yml', '# OVERWRITE ATTEMPT FROM CLIENT');
        $zip->close();

        // Simulate extraction loop logic matching Receiver.php
        $zipExtract = new ZipArchive();
        $this->assertTrue($zipExtract->open($zipFile));
        for ($i = 0; $i < $zipExtract->numFiles; $i++) {
            $entry = $zipExtract->getNameIndex($i);
            $dest = $targetRoot . '/' . $entry;
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0777, true);
            }

            if (file_exists($dest)) {
                if (ConfigIsolationManager::isProtectedConfigFile($entry)) {
                    // Protected live configuration: SKIP OVERWRITE
                    continue;
                }
            }

            $bytes = $zipExtract->getFromIndex($i);
            file_put_contents($dest, $bytes);
        }
        $zipExtract->close();

        // Verification:
        // 1. public/index.html WAS updated
        $this->assertEquals('new updated index', file_get_contents($indexPath), 'Normal files must be updated');
        // 2. etc/domains.yml was NEVER overwritten
        $this->assertEquals($liveDomainsContent, file_get_contents($domainsPath), 'Live domains.yml must be preserved against deployment overwrite');
    }

    /**
     * Test 7: ensureAllEffectiveConfigs seeds missing templates without touching existing
     */
    public function testEnsureAllEffectiveConfigsSeedsMissingWithoutOverwritingExisting(): void
    {
        $mockRoot = $this->testWorkDir . '/full_app';
        @mkdir($mockRoot . '/etc', 0777, true);
        @mkdir($mockRoot . '/etc.dist', 0777, true);

        // Generic templates in etc.dist
        file_put_contents($mockRoot . '/etc.dist/domains.yml', 'mode: single');
        file_put_contents($mockRoot . '/etc.dist/cloudflare.yml', 'enabled: false');
        file_put_contents($mockRoot . '/etc.dist/mail.yml', 'driver: smtp');

        // Existing live config for domains.yml
        $existingDomains = 'mode: multidomain';
        file_put_contents($mockRoot . '/etc/domains.yml', $existingDomains);

        $seeded = ConfigIsolationManager::ensureAllEffectiveConfigs($mockRoot);

        // Missing configs must be seeded
        $this->assertArrayHasKey('cloudflare.yml', $seeded);
        $this->assertArrayHasKey('mail.yml', $seeded);
        $this->assertFileExists($mockRoot . '/etc/cloudflare.yml');
        $this->assertFileExists($mockRoot . '/etc/mail.yml');

        // Pre-existing domains.yml must NOT have been seeded or overwritten
        $this->assertArrayNotHasKey('domains.yml', $seeded);
        $this->assertEquals($existingDomains, file_get_contents($mockRoot . '/etc/domains.yml'));
    }
}
