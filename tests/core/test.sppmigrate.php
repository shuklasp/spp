<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPP\CLI\Commands\MakeMigrationCommand;
use SPP\CLI\Commands\MigrateCommand;
use SPPMod\SPPDB\Migration\SPPMigrationManager;
use SPP\DB;

class SPPMigrateTest extends SPPTestCase
{
    public function setUp(): void
    {
        // ensure migrations table is cleared for testing
        try {
            DB::execute("DROP TABLE IF EXISTS spp_migrations");
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }
    }

    public function testMakeCommand()
    {
        $cmd = new MakeMigrationCommand();
        $this->assertEquals('make:migration', $cmd->getName());
        $this->assertTrue(strlen($cmd->getDescription()) > 0, 'Description should not be empty');
    }

    public function testRunCommand()
    {
        $cmd = new MigrateCommand();
        $this->assertEquals('migrate', $cmd->getName());

        // Test migrations table creation logic directly on SPPMigrationManager via reflection
        $manager = new SPPMigrationManager(\SPP\Scheduler::getContext() ?: 'default');
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('ensureMigrationsTable');
        $method->setAccessible(true);
        $method->invoke($manager);

        $exists = false;
        try {
            DB::query("SELECT 1 FROM spp_migrations LIMIT 1");
            $exists = true;
        } catch (\Exception $e) {
            $exists = false;
        }
        $this->assertEquals(true, $exists, "spp_migrations table should be created");
    }
}
