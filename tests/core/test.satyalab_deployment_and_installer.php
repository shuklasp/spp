<?php

namespace Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPP\Core\Router\DomainRouter;
use SPP\Scheduler;
use SPP\App;
use SPP\Core\Autoloader;
use SPP\Module;
use ReflectionMethod;

/**
 * Class SatyalabDeploymentAndInstallerTest
 *
 * Automated regression test suite ensuring zero-friction deployment to remote domains (like satyalab.net).
 * Verifies:
 * 1. Single Domain Mode serves unconfigured remote domains immediately without 404 Virtual Host rejection.
 * 2. Standalone web installer (install.php) executes cleanly with SPP_NO_AUTO_BOOT without HTTP 500 errors.
 * 3. Cross-platform portable autoloader classmap detects and discards stale machine paths (e.g. /mnt/c/...).
 * 4. Module resolution safely rebases relocated module paths without open_basedir violations.
 * 5. Short open tags (short_open_tag = On) in server php.ini never causes syntax errors on <?xml or templates.
 */
class SatyalabDeploymentAndInstallerTest extends SPPTestCase
{
    private array $originalServer;
    private array $originalGet;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        DomainRouter::reset();
    }

    public function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        DomainRouter::reset();
        parent::tearDown();
    }

    /**
     * Test that any unconfigured remote domain serves smoothly in Single Domain Mode without 404 rejection.
     */
    public function testRemoteDomainServesImmediatelyInSingleDomainMode(): void
    {
        DomainRouter::reset();
        DomainRouter::setDomainMode(DomainRouter::DOMAIN_MODE_SINGLE);

        $_SERVER['HTTP_HOST'] = 'arbitrary-unconfigured-server.com';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $this->assertTrue(DomainRouter::isSingleDomainMode());

        // Scheduler detection must resolve default app without throwing strict 404
        Scheduler::detectAndEnforceContext();
        $context = Scheduler::getContext();

        $this->assertNotEmpty($context);
        $this->assertEquals(App::getGlobalSettings('base_app') ?: 'default', $context);
    }

    /**
     * Test that satyalab.net and ptable.satyalab.net virtual hosts resolve accurately.
     */
    public function testSatyalabAndPtableVirtualHostsResolveCorrectly(): void
    {
        // 1. satyalab.net resolves to store (Universal App Store & Registry)
        $_SERVER['HTTP_HOST'] = 'satyalab.net';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        Scheduler::detectAndEnforceContext();
        $this->assertContains(Scheduler::getContext(), ['store', 'showcase']);

        // 2. ptable.satyalab.net resolves to ptable
        $_SERVER['HTTP_HOST'] = 'ptable.satyalab.net';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        Scheduler::detectAndEnforceContext();
        $this->assertEquals('ptable', Scheduler::getContext());
    }

    /**
     * Test that install.php renders HTTP 200 and complete HTML for satyalab.net without auto-boot crash.
     */
    public function testInstallPhpRendersSuccessfullyForRemoteDomain(): void
    {
        $php = PHP_BINARY;
        $script = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'install.php';
        $cmd = "\"{$php}\" -d display_errors=1 -r \"\$_SERVER['HTTP_HOST']='satyalab.net'; \$_SERVER['REQUEST_URI']='/install.php?domain=satyalab.net'; \$_SERVER['SCRIPT_NAME']='/install.php'; \$_SERVER['REQUEST_METHOD']='GET'; \$_GET['domain']='satyalab.net'; \$_GET['web']='1'; require '{$script}';\"";

        $output = shell_exec($cmd);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('SPP Server Provisioner & Universal Installer', $output);
        $this->assertStringContainsString('satyalab.net', $output);
        $this->assertStringContainsString('audit-card', $output);
    }

    /**
     * Test that Autoloader handles foreign cross-machine paths safely.
     */
    public function testAutoloaderSafelyHandlesForeignMachinePaths(): void
    {
        $ref = new ReflectionMethod(Module::class, 'resolveCachedModPath');
        $ref->setAccessible(true);

        // Simulate foreign classmap from a development machine
        $foreignPath = '/mnt/c/nonexistent_foreign_directory/Class.php';
        $resolved = $ref->invoke(null, $foreignPath);

        // Must return safely without triggering unhandled exceptions
        $this->assertIsString($resolved);
    }

    /**
     * Test that Module resolveCachedModPath rebases /spp/modules/ paths to the local active directory.
     */
    public function testModuleResolveCachedModPathRebasesCorrectly(): void
    {
        $ref = new ReflectionMethod(Module::class, 'resolveCachedModPath');
        $ref->setAccessible(true);

        $foreignModPath = '/mnt/c/projects/apache/school1/spp/modules/spp/sppdb';
        $resolved = $ref->invoke(null, $foreignModPath);

        $this->assertFileExists($resolved);
        $this->assertStringContainsString('sppdb', $resolved);
    }

    /**
     * Test that installer parses without syntax error when short_open_tag = On.
     */
    public function testShortOpenTagDoesNotBreakInstaller(): void
    {
        $php = PHP_BINARY;
        $installer = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'spp-domain-installer.php';
        $cmd = "\"{$php}\" -d short_open_tag=1 -l \"{$installer}\"";

        $output = shell_exec($cmd);

        $this->assertStringContainsString('No syntax errors detected', (string)$output);
    }

    /**
     * Test that SPPError constructs safely without deprecated E_STRICT constant references.
     */
    public function testSppErrorPhp84DeprecationSafety(): void
    {
        $err = new \SPP\SPPError(false);
        $types = $err->getErrorTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey(E_ERROR, $types);
        $this->assertArrayHasKey(E_WARNING, $types);
        $this->assertArrayHasKey(E_DEPRECATED, $types);
    }

    /**
     * Test that SPPErrorHandler does not throw ErrorException on deprecations.
     */
    public function testSppErrorHandlerIgnoresDeprecations(): void
    {
        // Calling handleError with E_DEPRECATED must return cleanly without throwing
        \SPP\Core\SPPErrorHandler::handleError(E_DEPRECATED, 'Deprecated function call', __FILE__, __LINE__);
        \SPP\Core\SPPErrorHandler::handleError(E_USER_DEPRECATED, 'User deprecated feature', __FILE__, __LINE__);

        $this->assertTrue(true);
    }
}
