<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\TestCase;
use SPPMod\SPPMigrate\Commands\MakeCommand;
use SPPMod\SPPMigrate\Commands\RunCommand;
use SPP\DB;

class SPPMigrateTest extends TestCase
{
    public function setUp(): void
    {
        // Set up in-memory sqlite
        $settings = \SPP\App::getGlobalSettings();
        if (!isset($settings['apps']['default']['db_config'])) {
            $settings['apps']['default']['db_config'] = [];
        }
        $settings['apps']['default']['db_config']['dbtype'] = 'sqlite';
        $settings['apps']['default']['db_config']['sqlite_path'] = ':memory:';
        
        // ensure migrations table is cleared
        DB::execute("DROP TABLE IF EXISTS spp_migrations");
        DB::execute("DROP TABLE IF EXISTS spp_test_migration_table");
    }

    public function testMakeCommand()
    {
        $cmd = new MakeCommand();
        $this->assertEquals('sys:migrate:make', $cmd->getName());
        $this->assertTrue(strlen($cmd->getDescription()) > 0, 'Description should not be empty');
    }

    public function testRunCommand()
    {
        $cmd = new RunCommand();
        $this->assertEquals('sys:migrate:run', $cmd->getName());
        
        // Test migrations table creation logic by directly calling it via reflection
        $reflection = new \ReflectionClass($cmd);
        $method = $reflection->getMethod('ensureMigrationsTable');
        $method->setAccessible(true);
        $method->invoke($cmd);

        $tables = DB::query("SELECT name FROM sqlite_master WHERE type='table' AND name='spp_migrations'");
        $this->assertEquals(1, count($tables), "spp_migrations table should be created");
    }
}
