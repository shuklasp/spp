<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class TestRunCommand extends Command
{
    protected string $name = 'test:run';
    protected string $description = 'Runs Parikshak evaluation for an entity or the whole suite';

    public function execute(array $args): void
    {
        $appname = $this->getOption($args, 'app', 'default');
        $entity = $this->getArgument($args, 0);
        $withCoverage = in_array('--coverage', $args);

        \SPP\Scheduler::withContext($appname, function() use ($appname, $entity, $withCoverage) {
            try {
                \SPP\Module::loadModule('parikshak');
                if (!class_exists('\\SPPMod\\Parikshak\\Parikshak')) {
                    echo "Parikshak module is not installed or active.\n";
                    return;
                }
                
                $tester = new \SPPMod\Parikshak\Parikshak();
                if ($entity) {
                    echo "Running test for entity: {$entity}\n";
                    $tester->testEntity($entity, $appname);
                } else {
                    echo "Running test suite for app: {$appname}\n";
                    $results = $tester->runSuite($appname, $withCoverage);
                    echo "\nSuite Summary:\n";
                    echo "Passed: " . $results['summary']['passed'] . " / " . $results['summary']['total'] . "\n";
                }
            } catch (\Exception $e) {
                echo "Error running tests: " . $e->getMessage() . "\n";
            }
        });
    }
}
