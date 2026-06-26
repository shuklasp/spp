<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeMigrationCommand
 * Creates a new database migration class.
 */
class MakeMigrationCommand extends BaseMakeCommand
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new migration class';

    public function execute(array $args): void
    {
        $migrationName = $args[2] ?? null;
        if (!$migrationName) {
            echo "Usage: php spp.php make:migration <MigrationName> [--app=appname | --module=modulename]\n";
            return;
        }

        $app = $this->getContext($args);
        $module = $this->getOption('module', $args);

        $timestamp = date('Ymd_His');
        $className = 'm' . $timestamp . '_' . str_replace('-', '_', strtolower($migrationName));

        if ($module) {
            // Module level migration
            $targetDir = SPP_MODULES_DIR . "/spp/{$module}/migrations"; // Attempt spp/ first
            if (!is_dir(SPP_MODULES_DIR . "/spp/{$module}") && is_dir(SPP_MODULES_DIR . "/school/{$module}")) {
                $targetDir = SPP_MODULES_DIR . "/school/{$module}/migrations";
            }
            if (!is_dir(SPP_MODULES_DIR . "/spp/{$module}") && !is_dir(SPP_MODULES_DIR . "/school/{$module}")) {
                // If it's an app-specific module
                $targetDir = SPP_APP_DIR . "/src/{$app}/modules/{$module}/migrations";
            }
        } else {
            // App level migration
            $targetDir = SPP_APP_DIR . "/src/{$app}/migrations";
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = "{$targetDir}/{$className}.php";

        $stub = <<<'STUB'
<?php

use SPPMod\SPPDB\SPPDB;

class {{className}}
{
    public function up(SPPDB $db): void
    {
        // Write your migration logic here
        // e.g., $db->execute_query("CREATE TABLE ...");
    }

    public function down(SPPDB $db): void
    {
        // Write rollback logic here
    }
}
STUB;
        $content = str_replace('{{className}}', $className, $stub);

        file_put_contents($targetPath, $content);

        echo "Success: Migration {$className} created at {$targetPath}\n";
    }
}
