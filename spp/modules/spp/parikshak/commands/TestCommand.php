<?php
namespace SPPMod\Parikshak\Commands;

use SPP\CLI\Command;
use SPPMod\Parikshak\SPPTestRunner;

require_once dirname(__DIR__) . '/src/SPPTestRunner.php';
require_once dirname(__DIR__) . '/src/SPPTestCase.php';

class TestCommand extends Command
{
    protected string $name = 'test';
    protected string $description = 'Run Parikshak Unit and Feature Tests';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $context = \SPP\Scheduler::getContext();
        echo "Running tests for [{$context}]...\n";

        // Enforce Database Isolation for unit tests
        \SPP\Module::setConfig('dbtype', 'sqlite', 'sppdb');
        \SPP\Module::setConfig('sqlite_path', ':memory:', 'sppdb');
        \SPP\DB::setProvider(new \SPPMod\SPPDB\SPPDB());

        $withCoverage = in_array('--coverage', $args);

        $runner = new SPPTestRunner();
        $results = $runner->run($context, $withCoverage);

        $summary = $results['summary'] ?? ['total' => 0, 'passed' => 0, 'failed' => 0];

        foreach ($results['tests'] ?? [] as $test) {
            $status = $test['status'] === 'passed' ? "\033[32m✔ PASS\033[0m" : "\033[31m✘ FAIL\033[0m";
            echo "{$status} {$test['class']}::{$test['method']}\n";
            if ($test['status'] === 'failed' && !empty($test['error'])) {
                echo "   \033[33m" . $test['error'] . "\033[0m\n";
            }
        }

        echo "\nTest Run Summary:\n";
        echo "Total: {$summary['total']}, Passed: {$summary['passed']}, Failed: {$summary['failed']}\n";

        if ($summary['failed'] > 0) {
            exit(1);
        }
    }
}
