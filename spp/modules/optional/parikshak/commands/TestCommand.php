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
        $context = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $context = substr($arg, 6);
                break;
            }
        }
        if (!$context) {
            $context = \SPP\Scheduler::getContext() ?: 'default';
        }
        \SPP\Scheduler::setContext($context);

        if (in_array('--list', $args, true) || in_array('--discover', $args, true)) {
            $discovered = SPPTestRunner::discover($context);
            echo "\n========================================================\n";
            echo "   PARIKSHAK TEST DISCOVERY [{$context}]                 \n";
            echo "========================================================\n\n";
            echo "Found {$discovered['total_suites']} suites with {$discovered['total_tests']} tests total:\n\n";
            foreach ($discovered['suites'] as $suite) {
                echo "  📁 {$suite['name']} ({$suite['count']} tests) [{$suite['file']}]\n";
                foreach ($suite['methods'] as $m) {
                    echo "     - {$m}()\n";
                }
            }
            echo "\n========================================================\n";
            return;
        }

        $teamcity = in_array('--teamcity', $args, true);
        $updateSnapshots = in_array('-u', $args, true) || in_array('--update-snapshots', $args, true);
        if ($updateSnapshots && class_exists('\SPPMod\Parikshak\SnapshotManager')) {
            \SPPMod\Parikshak\SnapshotManager::setUpdateMode(true);
            if (!$teamcity) {
                echo "\033[36mℹ Snapshot update mode enabled.\033[0m\n";
            }
        }

        $interactive = in_array('-i', $args, true) || in_array('--interactive', $args, true);
        if ($interactive && class_exists('\SPPMod\Parikshak\SnapshotManager')) {
            \SPPMod\Parikshak\SnapshotManager::setInteractiveMode(true);
            if (!$teamcity) {
                echo "\033[36mℹ Interactive snapshot review enabled.\033[0m\n";
            }
        }

        $watchMode = in_array('-w', $args, true) || in_array('--watch', $args, true);
        if ($watchMode) {
            $this->runWatchMode($context, $args);
            return;
        }

        $bail = in_array('-x', $args, true) || in_array('--bail', $args, true);
        $profile = in_array('--profile', $args, true);
        $compact = in_array('-c', $args, true) || in_array('--compact', $args, true);

        $retryFailed = in_array('--failed', $args, true) || in_array('--retry', $args, true);
        if ($retryFailed) {
            $failedFile = (defined('SPP_APP_DIR') ? SPP_APP_DIR : getcwd()) . '/var/reports/parikshak_last_failed.json';
            $failedTargets = file_exists($failedFile) ? json_decode((string)file_get_contents($failedFile), true) : [];
            if (empty($failedTargets)) {
                echo "\033[32m✔ No failed tests from previous run to retry.\033[0m\n";
                return;
            }
            if (!$teamcity) {
                echo "\033[33m🔁 Retrying " . count($failedTargets) . " failed test target(s) from previous run...\033[0m\n";
            }
        }

        if (!$teamcity && !$compact) {
            echo "Running tests for [{$context}]...\n";
        }

        $origDbtype = \SPP\Module::getConfig('dbtype', 'sppdb', $context);
        $origSqlitePath = \SPP\Module::getConfig('sqlite_path', 'sppdb', $context);

        try {
            // Enforce Database Isolation for unit tests
            \SPP\Module::setConfig('dbtype', 'sqlite', 'sppdb', $context);
            \SPP\Module::setConfig('sqlite_path', ':memory:', 'sppdb', $context);
            \SPP\DB::setProvider(new \SPPMod\SPPDB\SPPDB('sqlite::memory:', 'root', ''));

            $withCoverage = in_array('--coverage', $args, true);
            $parallel = 1;
            $filter = null;

            if ($retryFailed && !empty($failedTargets)) {
                $filter = implode('|', array_map('preg_quote', $failedTargets));
            } else {
                foreach ($args as $arg) {
                    if ($arg === '--parallel') {
                        $parallel = 4;
                    } elseif (str_starts_with($arg, '--parallel=')) {
                        $parallel = max(1, (int)substr($arg, 11));
                    } elseif (str_starts_with($arg, '--filter=')) {
                        $filter = substr($arg, 9);
                    } elseif (!str_starts_with($arg, '--') && !str_starts_with($arg, '-') && (str_ends_with($arg, '.php') || file_exists($arg))) {
                        $filter = $arg;
                    }
                }
            }

            $runner = new SPPTestRunner();
            $results = $runner->run($context, $withCoverage, $filter, $teamcity, $parallel, $bail, $profile, $compact);

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
                    // In compact mode, show only failures in detail
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

                echo "\nTest Run Summary:\n";
                $skippedCount = $summary['skipped'] ?? 0;
                $skippedText = $skippedCount > 0 ? ", Skipped: {$skippedCount}" : "";
                echo "Total: {$summary['total']}, Passed: {$summary['passed']}{$skippedText}, Failed: {$summary['failed']}\n";
            }

            if ($summary['failed'] > 0) {
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

    private function runWatchMode(string $context, array $args): void
    {
        echo "\n\033[36m========================================================\n";
        echo "   👀 PARIKSHAK CONTINUOUS WATCH MODE [{$context}]       \n";
        echo "========================================================\033[0m\n";
        echo "Watching files in src/ and tests/... (Press Ctrl+C to stop)\n\n";

        $runner = new SPPTestRunner();
        $runner->run($context);

        $lastMtimes = $this->scanMtimes($context);

        while (true) {
            usleep(500000); // 500ms
            clearstatcache();
            $currentMtimes = $this->scanMtimes($context);
            $changedFile = null;

            foreach ($currentMtimes as $file => $mtime) {
                if (!isset($lastMtimes[$file]) || $lastMtimes[$file] !== $mtime) {
                    $changedFile = $file;
                    break;
                }
            }

            if ($changedFile) {
                $lastMtimes = $currentMtimes;
                $time = date('H:i:s');
                $base = basename($changedFile);
                echo "\n\033[33m⚡ [{$time}] Change detected in {$base}. Re-running tests...\033[0m\n";

                $target = (str_ends_with($changedFile, 'Test.php') || str_starts_with(basename($changedFile), 'test.'))
                    ? $changedFile
                    : null;

                $runner->run($context, false, $target);
                echo "\n\033[36m👀 Watching for next change...\033[0m\n";
            }
        }
    }

    private function scanMtimes(string $context): array
    {
        $mtimes = [];
        $dirs = [
            SPP_APP_DIR . '/tests',
            SPP_APP_DIR . '/src/' . $context
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $mtimes[$file->getPathname()] = $file->getMTime();
                }
            }
        }

        return $mtimes;
    }
}
