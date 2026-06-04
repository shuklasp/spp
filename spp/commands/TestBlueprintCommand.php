<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class TestBlueprintCommand extends Command
{
    protected string $name = 'test:blueprint';
    protected string $description = 'Generate a structural blueprint for an entity';

    public function execute(array $args): void
    {
        $appname = 'default';
        $entity = null;
        
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            if (!$entity) $entity = $arg;
        }
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        if (!$entity) {
            echo "Usage: php spp.php test:blueprint <EntityClass>\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($entity) {
            try {
                \SPP\Module::loadModule('parikshak');
                if (!class_exists('\\SPPMod\\Parikshak\\Parikshak')) {
                    echo "Parikshak module is not installed or active.\n";
                    return;
                }
                
                $tester = new \SPPMod\Parikshak\Parikshak();
                $blueprint = $tester->generateBlueprint($entity);
                echo "Blueprint generated for {$entity}:\n";
                print_r($blueprint);
            } catch (\Exception $e) {
                echo "Error generating blueprint: " . $e->getMessage() . "\n";
            }
        });
    }
}
