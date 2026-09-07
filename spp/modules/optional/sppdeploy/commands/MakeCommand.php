<?php

namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;
use SPP\App;

class MakeCommand extends Command
{
    public function isCLIOnly(): bool { return true; }

    public function getName(): string
    {
        return 'migrate:make';
    }

    public function getDescription(): string
    {
        return 'Generate a new database migration class.';
    }

    public function execute(array $args): void
    {
        $name = null;
        $appname = \SPP\Scheduler::getContext() ?: 'default';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--name=')) {
                $name = substr($arg, 7);
            } elseif (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            } elseif (!str_starts_with($arg, '--') && $arg !== 'spp.php' && $arg !== 'migrate:make' && !str_ends_with($arg, 'spp.php')) {
                if ($name === null) {
                    $name = $arg;
                }
            }
        }

        if (!$name) {
            $this->error("Migration name is required. Usage: php spp.php migrate:make <name>");
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            $this->error("Invalid migration name. Use alphanumeric and underscores only.");
            return;
        }

        $app = App::getApp($appname);
        $migrationsDir = $app->resolvePath('db/migrations', $app->getAppSrcDir());

        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $className = 'Migration_' . $timestamp . '_' . $name;
        $filename = $className . '.php';
        $filepath = $migrationsDir . DIRECTORY_SEPARATOR . $filename;

        $ns = "App\\" . ucfirst($appname) . "\\Migrations";

        $stub = "<?php\n\nnamespace {$ns};\n\nuse SPP\\Core\\Migration;\n\nclass {$className} extends Migration\n{\n    public function getVersion(): string\n    {\n        return '1.0.0';\n    }\n\n    public function up(): void\n    {\n        // \$this->executeSql(\"CREATE TABLE ...\");\n    }\n\n    public function down(): void\n    {\n        // \$this->executeSql(\"DROP TABLE ...\");\n    }\n}\n";

        file_put_contents($filepath, $stub);

        $this->info("Migration created successfully: " . $filepath);
    }
}
