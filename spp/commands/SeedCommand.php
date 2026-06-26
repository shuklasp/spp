<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

class SeedCommand extends Command
{
    protected string $name = 'sys:seed';
    protected string $description = 'Run all database seeders for an application';

    public function execute(array $args): void
    {
        $appname = $args[2] ?? 'default';

        $seederPath = SPP_APP_DIR . "/src/{$appname}/seeders";

        if (!is_dir($seederPath)) {
            echo "No seeders found for app '{$appname}'.\n";
            return;
        }

        echo "Starting Database Seeding for app '{$appname}'...\n";
        $db = new SPPDB();

        $files = glob($seederPath . '/*Seeder.php');
        if (empty($files)) {
            echo "No seeder files found.\n";
            return;
        }

        foreach ($files as $file) {
            require_once $file;
            $className = basename($file, '.php');
            $fullClass = "\\App\\Seeders\\{$className}";

            if (class_exists($fullClass)) {
                echo "Running seeder: {$className}\n";
                $seeder = new $fullClass();
                if (method_exists($seeder, 'run')) {
                    $seeder->run($db);
                }
            }
        }

        echo "Database seeding completed successfully.\n";
    }
}
