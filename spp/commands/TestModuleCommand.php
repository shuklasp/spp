<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\Parikshak\SPPTestRunner;

/**
 * Class TestModuleCommand
 * Executes native Parikshak test suites for an isolated module.
 */
class TestModuleCommand extends Command
{
    public function getName(): string
    {
        return 'test:module';
    }

    public function getDescription(): string
    {
        return 'Run native Parikshak tests for an isolated module';
    }

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $moduleName = $this->getArgument($args, 0) ?? null;
        if (!$moduleName) {
            echo "Usage: php spp.php test:module <modulename> [--filter=...] [--compact] [--profile] [--bail] [--teamcity]\n";
            return;
        }

        \SPP\Module::loadAllModules();
        $module = \SPP\Module::getModule($moduleName);

        if (!$module) {
            echo "Error: Module '{$moduleName}' not found or is not active.\n";
            return;
        }

        $testDir = $module->ModPath . '/tests';

        if (!is_dir($testDir)) {
            echo "Error: Test directory not found in module '{$moduleName}' ({$testDir})\n";
            return;
        }

        // Ensure Parikshak classes are loaded
        $runnerFile = SPP_BASE_DIR . '/spp/modules/optional/parikshak/src/SPPTestRunner.php';
        $caseFile = SPP_BASE_DIR . '/spp/modules/optional/parikshak/src/SPPTestCase.php';
        if (file_exists($runnerFile)) {
            require_once $runnerFile;
        }
        if (file_exists($caseFile)) {
            require_once $caseFile;
        }

        if (!class_exists('\SPPMod\Parikshak\SPPTestRunner')) {
            echo "Error: Parikshak testing engine is not available.\n";
            return;
        }

        $teamcity = in_array('--teamcity', $args, true);
        $bail = in_array('-x', $args, true) || in_array('--bail', $args, true);
        $profile = in_array('--profile', $args, true);
        $compact = in_array('-c', $args, true) || in_array('--compact', $args, true);
        $withCoverage = in_array('--coverage', $args, true);

        $filter = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--filter=')) {
                $filter = substr($arg, 9);
            }
        }

        if (!$teamcity && !$compact) {
            echo "\n🧩 Running Parikshak tests for module '{$moduleName}' ({$testDir})...\n\n";
        }

        $context = \SPP\Scheduler::getContext() ?: 'default';
        $origDbtype = \SPP\Module::getConfig('dbtype', 'sppdb', $context);
        $origSqlitePath = \SPP\Module::getConfig('sqlite_path', 'sppdb', $context);

        try {
            // Enforce database isolation for unit tests
            \SPP\Module::setConfig('dbtype', 'sqlite', 'sppdb', $context);
            \SPP\Module::setConfig('sqlite_path', ':memory:', 'sppdb', $context);
            if (class_exists('\\SPP\\DB') && class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                \SPP\DB::setProvider(new \SPPMod\SPPDB\SPPDB('sqlite::memory:', 'root', ''));
            }

            $runner = new SPPTestRunner();
            $results = $runner->runDirectory($testDir, $moduleName, $withCoverage, $filter, $teamcity, $bail, $profile, $compact);

            $summary = $results['summary'] ?? ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0];

            if (!$teamcity) {
                if (!$compact) {
                    foreach ($results['tests'] ?? [] as $test) {
                        $status = $test['status'] === 'passed' ? "\033[32m✔ PASS\033[0m" : ($test['status'] === 'skipped' ? "\033[33m⚡ SKIP\033[0m" : "\033[31m✘ FAIL\033[0m");
                        echo "{$status} {$test['class']}::{$test['method']}\n";
                        if ($test['status'] === 'failed' && !empty($test['error'])) {
                            echo "   \033[33m" . $test['error'] . "\033[0m\n";
                        }
                    }
                } else {
                    $hasFailures = false;
                    foreach ($results['tests'] ?? [] as $test) {
                        if ($test['status'] === 'failed') {
                            if (!$hasFailures) {
                                echo "\n\033[31mFailures:\033[0m\n";
                                $hasFailures = true;
                            }
                            echo " \033[31m✘ FAIL\033[0m {$test['class']}::{$test['method']}\n";
                            if (!empty($test['error'])) {
                                echo "   \033[33m" . $test['error'] . "\033[0m\n";
                            }
                        }
                    }
                }

                echo "\nModule Test Run Summary [{$moduleName}]:\n";
                $skippedCount = $summary['skipped'] ?? 0;
                $skippedText = $skippedCount > 0 ? ", Skipped: {$skippedCount}" : "";
                echo "Total: {$summary['total']}, Passed: {$summary['passed']}{$skippedText}, Failed: {$summary['failed']}\n";

                if ($summary['total'] === 0) {
                    echo "ℹ Note: No test cases found extending SPPTestCase in {$testDir}.\n";
                } elseif ($summary['failed'] === 0) {
                    echo "🎉 All tests passed for module '{$moduleName}'.\n";
                }
            }

            if (($summary['failed'] ?? 0) > 0) {
                exit(1);
            }
        } finally {
            if ($origDbtype !== null) {
                \SPP\Module::setConfig('dbtype', $origDbtype, 'sppdb', $context);
            }
            if ($origSqlitePath !== null) {
                \SPP\Module::setConfig('sqlite_path', $origSqlitePath, 'sppdb', $context);
            }
        }
    }
}
