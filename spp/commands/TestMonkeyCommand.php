<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class TestMonkeyCommand extends Command
{
    protected string $name = 'test:monkey';
    protected string $description = 'Runs chaos monkey / fuzzing scenarios for an entity';

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
            echo "Usage: php spp.php test:monkey <EntityClass>\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname, $entity) {
            try {
                \SPP\Module::loadModule('parikshak');
                if (!class_exists('\\SPPMod\\Parikshak\\Parikshak')) {
                    echo "Parikshak module is not installed or active.\n";
                    return;
                }
                
                $tester = new \SPPMod\Parikshak\Parikshak();
                echo "Unleashing Chaos Monkey on entity: {$entity}\n";
                
                $tester->testEntity($entity, $appname);
                
                $results = $tester->getResults();
                echo "\nMonkey testing completed.\n";
                if (!empty($results) && isset($results['entities'])) {
                    $last = end($results['entities']);
                    if (!empty($last['errors'])) {
                        echo "Monkey found vulnerabilities:\n";
                        foreach ($last['errors'] as $err) {
                            echo " - {$err}\n";
                        }
                    } else {
                        echo "Entity survived the chaos monkey.\n";
                    }
                }
            } catch (\Exception $e) {
                echo "Monkey Error: " . $e->getMessage() . "\n";
            }
        });
    }
}
