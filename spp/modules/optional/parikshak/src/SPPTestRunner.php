<?php

namespace SPPMod\Parikshak;

/**
 * Class SPPTestRunner
 * Discovers and executes standard unit tests extending SPPTestCase.
 */
class SPPTestRunner
{
    private array $results = [];
    private bool $teamcity = false;
    private bool $bail = false;
    private bool $stopExecution = false;
    private bool $profile = false;
    private bool $compact = false;
    private array $timings = [];

    public function run(
        string $appname,
        bool $withCoverage = false,
        ?string $filter = null,
        bool $teamcity = false,
        int $parallel = 1,
        bool $bail = false,
        bool $profile = false,
        bool $compact = false
    ): array {
        $this->teamcity = $teamcity;
        $this->bail = $bail;
        $this->profile = $profile;
        $this->compact = $compact;
        $this->stopExecution = false;
        $this->timings = [];

        if (!class_exists('PHPUnit\Framework\TestCase', false)) {
            class_alias(\SPPMod\Parikshak\TestCase::class, 'PHPUnit\Framework\TestCase');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\RefreshDatabase', false)) {
            class_alias(\SPPMod\Parikshak\RefreshDatabase::class, 'SPPMod\Parikshak\Traits\RefreshDatabase');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\InteractsWithApi', false)) {
            class_alias(\SPPMod\Parikshak\InteractsWithApi::class, 'SPPMod\Parikshak\Traits\InteractsWithApi');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\InteractsWithBrowser', false)) {
            class_alias(\SPPMod\Parikshak\InteractsWithBrowser::class, 'SPPMod\Parikshak\Traits\InteractsWithBrowser');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\InteractsWithMockery', false)) {
            class_alias(\SPPMod\Parikshak\InteractsWithMockery::class, 'SPPMod\Parikshak\Traits\InteractsWithMockery');
        }

        $hasCoverage = false;
        if ($withCoverage) {
            if (function_exists('xdebug_start_code_coverage')) {
                xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
                $hasCoverage = true;
            } elseif (function_exists('pcov\start')) {
                \pcov\start();
                $hasCoverage = true;
            } else {
                echo "\n\033[33mWARNING: Code coverage requested but neither Xdebug nor PCOV extensions are loaded. Skipping coverage generation.\033[0m\n";
            }
        }
        
        $this->results = [
            'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0],
            'tests' => []
        ];

        if ($filter) {
            $candidatePath = file_exists($filter) ? $filter : (SPP_APP_DIR . '/' . ltrim($filter, '/\\'));
            if (file_exists($candidatePath) && is_file($candidatePath)) {
                $this->executeTestFile($candidatePath, 'Targeted');
            } else {
                $this->scanAndRun(SPP_APP_DIR . '/tests/core', 'Core', $filter);
                $this->scanAndRun(SPP_APP_DIR . '/src/' . $appname . '/tests', ucfirst($appname), $filter);
            }
        } elseif ($parallel > 1) {
            $this->runInParallel($appname, $parallel);
        } else {
            // Core Tests (Framework level)
            $coreTestDir = SPP_APP_DIR . '/tests/core';
            $this->scanAndRun($coreTestDir, 'Core');

            // App Tests
            $appTestDir = SPP_APP_DIR . '/src/' . $appname . '/tests';
            $this->scanAndRun($appTestDir, ucfirst($appname));
        }

        $reportsDir = SPP_APP_DIR . '/var/reports';
        if (!is_dir($reportsDir)) {
            @mkdir($reportsDir, 0777, true);
        }
        $failedFile = $reportsDir . '/parikshak_last_failed.json';
        $failedItems = [];
        foreach ($this->results['tests'] as $t) {
            if ($t['status'] === 'failed') {
                $failedItems[] = $t['file'] ?? $t['class'];
            }
        }
        @file_put_contents($failedFile, json_encode(array_values(array_unique($failedItems)), JSON_PRETTY_PRINT));

        if ($this->compact && !$this->teamcity) {
            echo "\n";
        }

        if ($this->profile && !$this->teamcity && !empty($this->timings)) {
            usort($this->timings, fn($a, $b) => $b['duration'] <=> $a['duration']);
            $slowest = array_slice($this->timings, 0, 5);
            echo "\n\033[33m⏱ Top Slowest Tests:\033[0m\n";
            foreach ($slowest as $item) {
                echo "  - {$item['name']}: \033[36m{$item['duration']} ms\033[0m\n";
            }
        }

        if ($hasCoverage) {
            if (function_exists('xdebug_get_code_coverage')) {
                $coverageData = xdebug_get_code_coverage();
                xdebug_stop_code_coverage();
                $this->generateCloverXml($coverageData);
            } elseif (function_exists('pcov\collect')) {
                \pcov\stop();
                $coverageData = \pcov\collect();
                $this->generateCloverXml($coverageData);
            }
        }

        return $this->results;
    }

    /**
     * Executes tests residing in a specific target directory (e.g. an isolated module test suite).
     */
    public function runDirectory(
        string $dir,
        string $scope = 'Module',
        bool $withCoverage = false,
        ?string $filter = null,
        bool $teamcity = false,
        bool $bail = false,
        bool $profile = false,
        bool $compact = false
    ): array {
        $this->teamcity = $teamcity;
        $this->bail = $bail;
        $this->profile = $profile;
        $this->compact = $compact;
        $this->stopExecution = false;
        $this->timings = [];

        if (!class_exists('PHPUnit\Framework\TestCase', false)) {
            class_alias(\SPPMod\Parikshak\TestCase::class, 'PHPUnit\Framework\TestCase');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\RefreshDatabase', false)) {
            class_alias(\SPPMod\Parikshak\RefreshDatabase::class, 'SPPMod\Parikshak\Traits\RefreshDatabase');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\InteractsWithApi', false)) {
            class_alias(\SPPMod\Parikshak\InteractsWithApi::class, 'SPPMod\Parikshak\Traits\InteractsWithApi');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\InteractsWithBrowser', false)) {
            class_alias(\SPPMod\Parikshak\InteractsWithBrowser::class, 'SPPMod\Parikshak\Traits\InteractsWithBrowser');
        }
        if (!trait_exists('SPPMod\Parikshak\Traits\InteractsWithMockery', false)) {
            class_alias(\SPPMod\Parikshak\InteractsWithMockery::class, 'SPPMod\Parikshak\Traits\InteractsWithMockery');
        }

        $this->results = [
            'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0],
            'tests' => []
        ];

        $this->scanAndRun($dir, $scope, $filter);

        if ($this->compact && !$this->teamcity) {
            echo "\n";
        }

        if ($this->profile && !$this->teamcity && !empty($this->timings)) {
            usort($this->timings, fn($a, $b) => $b['duration'] <=> $a['duration']);
            $slowest = array_slice($this->timings, 0, 5);
            echo "\n\033[33m⏱ Top Slowest Tests:\033[0m\n";
            foreach ($slowest as $item) {
                echo "  - {$item['name']}: \033[36m{$item['duration']} ms\033[0m\n";
            }
        }

        return $this->results;
    }

    /**
     * Partition test files and execute them across multiple background processes.
     */
    private function runInParallel(string $appname, int $workers): void
    {
        $allFiles = [];
        $coreTestDir = SPP_APP_DIR . '/tests/core';
        $appTestDir = SPP_APP_DIR . '/src/' . $appname . '/tests';

        foreach ([$coreTestDir, $appTestDir] as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                $filename = $file->getFilename();
                if ($file->isFile() && (str_starts_with($filename, 'test.') || str_ends_with($filename, 'Test.php')) && $file->getExtension() === 'php') {
                    $allFiles[] = $file->getPathname();
                }
            }
        }

        if (empty($allFiles)) {
            return;
        }

        $chunks = array_chunk($allFiles, max(1, (int)ceil(count($allFiles) / $workers)));
        $handles = [];
        $pipes = [];

        foreach ($chunks as $idx => $chunk) {
            $cmd = 'php ' . escapeshellarg(SPP_APP_DIR . '/spp.php') . ' test --app=' . escapeshellarg($appname) . ' ' . implode(' ', array_map('escapeshellarg', $chunk));
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $proc = proc_open($cmd, $descriptors, $pipes[$idx]);
            if (is_resource($proc)) {
                $handles[$idx] = $proc;
            }
        }

        foreach ($handles as $idx => $proc) {
            $stdout = stream_get_contents($pipes[$idx][1]);
            fclose($pipes[$idx][0]);
            fclose($pipes[$idx][1]);
            fclose($pipes[$idx][2]);
            proc_close($proc);

            if ($this->teamcity) {
                echo $stdout;
            } else {
                echo $stdout;
            }
        }
    }

    private function generateCloverXml(array $coverageData): void
    {
        $xml = new \SimpleXMLElement('<coverage generated="' . time() . '"></coverage>');
        $project = $xml->addChild('project');
        $project->addAttribute('timestamp', (string)time());
        
        foreach ($coverageData as $file => $lines) {
            if (strpos($file, 'vendor/') !== false || strpos($file, 'tests/') !== false) {
                continue;
            }
            
            $fileNode = $project->addChild('file');
            $fileNode->addAttribute('name', $file);
            
            foreach ($lines as $lineNum => $status) {
                if ($status === 1 || $status === -1) {
                    $lineNode = $fileNode->addChild('line');
                    $lineNode->addAttribute('num', (string)$lineNum);
                    $lineNode->addAttribute('type', 'stmt');
                    $lineNode->addAttribute('count', $status === 1 ? '1' : '0');
                }
            }
        }
        
        $xml->asXML(SPP_APP_DIR . '/coverage.xml');
        echo "\n\033[32mCode coverage generated at coverage.xml\033[0m\n";
    }

    private function scanAndRun(string $dir, string $scope, ?string $filter = null): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($this->stopExecution) break;
            $filename = $file->getFilename();
            if ($file->isFile() && (str_starts_with($filename, 'test.') || str_ends_with($filename, 'Test.php')) && $file->getExtension() === 'php') {
                if ($filter && stripos($file->getPathname(), $filter) === false) {
                    continue;
                }
                $this->executeTestFile($file->getPathname(), $scope);
            }
        }
    }

    private function executeTestFile(string $filePath, string $scope): void
    {
        if ($this->stopExecution) return;

        // Require DSL functions if they exist
        $dslFunctions = dirname(__DIR__) . '/src/DSL/functions.php';
        if (file_exists($dslFunctions)) {
            require_once $dslFunctions;
        }

        $classes = $this->extractClassesFromFile($filePath);
        require_once $filePath;

        foreach ($classes as $class) {
            if ($this->stopExecution) break;
            if (class_exists($class) && is_subclass_of($class, SPPTestCase::class)) {
                $this->executeTestCase($class, $scope, $filePath);
            }
        }

        // Check for Functional DSL tests registered for this file
        if (!$this->stopExecution && class_exists('\SPPMod\Parikshak\DSL\Registry')) {
            $normalizedPath = str_replace('\\', '/', realpath($filePath) ?: $filePath);
            $dslTests = \SPPMod\Parikshak\DSL\Registry::getTests($normalizedPath);
            if (!empty($dslTests)) {
                $this->executeFunctionalTests($normalizedPath, $dslTests, $scope);
            }
        }
    }

    private function executeFunctionalTests(string $filePath, array $tests, string $scope): void
    {
        $dummyClass = new class extends SPPTestCase {};
        $className = basename($filePath);
        $beforeEach = \SPPMod\Parikshak\DSL\Registry::getBeforeEach($filePath);
        $afterEach = \SPPMod\Parikshak\DSL\Registry::getAfterEach($filePath);

        $hasOnly = false;
        foreach ($tests as $t) {
            if (!empty($t['only'])) {
                $hasOnly = true;
                break;
            }
        }
        if ($hasOnly) {
            $tests = array_filter($tests, fn($t) => !empty($t['only']));
        }

        foreach ($tests as $test) {
            if ($this->stopExecution) break;

            $methodName = $test['description'];
            $closure = $test['closure'];
            $skip = $test['skip'] ?? false;

            $shouldSkip = is_callable($skip) ? $skip() : (bool)$skip;
            if ($shouldSkip) {
                $reason = is_string($skip) ? $skip : 'Skipped via DSL ->skip()';
                $this->recordSkipped($className, $methodName, $scope, $reason);
                continue;
            }

            $boundClosure = ($closure instanceof \Closure) ? $closure->bindTo($dummyClass, $dummyClass) : $closure;
            $wrappedClosure = function(...$args) use ($beforeEach, $afterEach, $boundClosure, $dummyClass) {
                foreach ($beforeEach as $fn) {
                    if ($fn instanceof \Closure) {
                        $fn = $fn->bindTo($dummyClass, $dummyClass);
                    }
                    $fn();
                }
                try {
                    $boundClosure(...$args);
                } finally {
                    foreach ($afterEach as $fn) {
                        if ($fn instanceof \Closure) {
                            $fn = $fn->bindTo($dummyClass, $dummyClass);
                        }
                        $fn();
                    }
                }
            };

            $dataset = $test['dataset'] ?? null;
            if (is_callable($dataset)) {
                $dataset = $dataset();
            }

            $retries = $test['retry'] ?? 0;
            if ($dataset !== null) {
                $idx = 0;
                foreach ($dataset as $key => $row) {
                    if ($this->stopExecution) break;
                    $rowArgs = is_array($row) ? $row : [$row];
                    $label = is_string($key) ? "{$methodName} ({$key})" : "{$methodName} (data #{$idx})";
                    $this->runTestSingle($dummyClass, $className, $label, $scope, $rowArgs, $wrappedClosure, $filePath, $retries);
                    $idx++;
                }
            } else {
                $this->runTestSingle($dummyClass, $className, $methodName, $scope, [], $wrappedClosure, $filePath, $retries);
            }
        }
        
        \SPPMod\Parikshak\DSL\Registry::clear($filePath);
    }

    private function recordSkipped(string $className, string $methodName, string $scope, string $reason, ?string $filePath = null): void
    {
        $this->results['summary']['total']++;
        if (!isset($this->results['summary']['skipped'])) {
            $this->results['summary']['skipped'] = 0;
        }
        $this->results['summary']['skipped']++;

        $fullTestName = "{$className}::{$methodName}";
        if ($this->teamcity) {
            echo "##teamcity[testStarted name='" . addslashes($fullTestName) . "']\n";
            echo "##teamcity[testIgnored name='" . addslashes($fullTestName) . "' message='" . addslashes($reason) . "']\n";
        }

        $report = [
            'file'   => $filePath,
            'scope'  => $scope,
            'class'  => $className,
            'method' => $methodName,
            'status' => 'skipped',
            'error'  => $reason
        ];

        if ($this->compact && !$this->teamcity) {
            echo "\033[33mS\033[0m";
        }

        $this->results['tests'][] = $report;
    }

    private function extractClassesFromFile(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        $tokens = token_get_all($contents);
        $classes = [];
        $namespace = '';
        
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $i += 2; // Skip whitespace
                $namespace = '';
                while (isset($tokens[$i]) && (is_array($tokens[$i]) || $tokens[$i] === '\\')) {
                    if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED])) {
                        $namespace .= $tokens[$i][1];
                    } elseif ($tokens[$i] === '\\') {
                        $namespace .= '\\';
                    }
                    $i++;
                }
            }
            if ($tokens[$i][0] === T_CLASS) {
                $i += 2; // Skip whitespace
                if (isset($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $className = $tokens[$i][1];
                    $classes[] = $namespace ? $namespace . '\\' . $className : $className;
                }
            }
        }
        return $classes;
    }

    private function executeTestCase(string $className, string $scope, string $filePath): void
    {
        $refl = new \ReflectionClass($className);
        if ($refl->isAbstract()) {
            return;
        }

        $instance = $refl->newInstance();
        $methods = $refl->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($this->stopExecution) break;
            $methodName = $method->getName();
            if (str_starts_with($methodName, 'test')) {
                // Check for DataProvider attribute
                $attributes = $method->getAttributes(\SPPMod\Parikshak\Attributes\DataProvider::class);
                if (!empty($attributes)) {
                    $providerName = $attributes[0]->getArguments()[0];
                    if (method_exists($instance, $providerName)) {
                        $testData = $instance->$providerName();
                        foreach ($testData as $dataRow) {
                            if ($this->stopExecution) break;
                            $this->runTestSingle($instance, $className, $methodName, $scope, is_array($dataRow) ? $dataRow : [$dataRow], null, $filePath);
                        }
                        continue;
                    }
                }
                
                $this->runTestSingle($instance, $className, $methodName, $scope, [], null, $filePath);
            }
        }
    }

    private function runTestSingle($instance, string $className, string $methodName, string $scope, array $args, ?callable $closure = null, ?string $filePath = null, int $retries = 0): void
    {
        $this->results['summary']['total']++;
        $fullTestName = "{$className}::{$methodName}" . (!empty($args) ? ' with data set' : '');
        $startTime = microtime(true);

        if ($this->teamcity) {
            echo "##teamcity[testStarted name='" . addslashes($fullTestName) . "']\n";
        }

        $report = [
            'file'   => $filePath,
            'scope'  => $scope,
            'class'  => $className,
            'method' => $methodName . (!empty($args) ? ' with data set' : ''),
            'status' => 'passed',
            'error'  => null
        ];

        $maxAttempts = max(1, $retries + 1);
        $lastException = null;
        $success = false;
        $attempt = 1;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $instance->setUp();
                if ($closure) {
                    if (!empty($args)) {
                        $closure(...array_values($args));
                    } else {
                        $closure();
                    }
                } else {
                    if (!empty($args)) {
                        $instance->$methodName(...array_values($args));
                    } else {
                        $instance->$methodName();
                    }
                }
                $instance->tearDown();
                $success = true;
                break;
            } catch (\Throwable $e) {
                $lastException = $e;
                try {
                    $instance->tearDown();
                } catch (\Throwable $e2) {
                    // teardown cleanup error suppressed during retry
                }
            }
        }

        if ($success) {
            $report['status'] = 'passed';
            if ($attempt > 1) {
                $this->recordFlakyQuarantine($fullTestName, $attempt, $lastException?->getMessage() ?: '');
                $report['method'] .= " \033[33m(passed on retry {$attempt})\033[0m";
            }
        } else {
            $report['status'] = 'failed';
            $report['error'] = $lastException?->getMessage();
        }

        $durationMs = (int)round((microtime(true) - $startTime) * 1000);
        $report['duration_ms'] = $durationMs;
        $this->timings[] = [
            'name' => "{$className}::{$report['method']}",
            'duration' => $durationMs
        ];

        if ($report['status'] === 'passed') {
            $this->results['summary']['passed']++;
            if ($this->compact && !$this->teamcity) {
                echo "\033[32m.\033[0m";
            }
            if ($this->teamcity) {
                echo "##teamcity[testFinished name='" . addslashes($fullTestName) . "' duration='{$durationMs}']\n";
            }
        } else {
            $this->results['summary']['failed']++;
            if ($this->compact && !$this->teamcity) {
                echo "\033[31mF\033[0m";
            }
            if ($this->bail) {
                $this->stopExecution = true;
            }
            if ($this->teamcity) {
                $escapedErr = str_replace(["|", "'", "\n", "\r", "[", "]"], ["||", "|'", "|n", "|r", "|[", "|]"], (string)$report['error']);
                echo "##teamcity[testFailed name='" . addslashes($fullTestName) . "' message='{$escapedErr}']\n";
                echo "##teamcity[testFinished name='" . addslashes($fullTestName) . "' duration='{$durationMs}']\n";
            }
        }

        $this->results['tests'][] = $report;
    }

    private function recordFlakyQuarantine(string $testName, int $attempts, string $error): void
    {
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : getcwd());
        $logDir = $appDir . '/var/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/flaky_tests.log';
        $entry = sprintf("[%s] FLAKY QUARANTINE: %s succeeded on attempt %d. Prior failure: %s\n", date('Y-m-d H:i:s'), $testName, $attempts, str_replace(["\r", "\n"], ' ', $error));
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }

    /**
     * Discovers all Parikshak test suites and individual test methods across the project.
     *
     * @param string|null $app Optional application name to filter app-specific tests
     * @return array Structured array of test suites, methods, and counts
     */
    public static function discover(?string $app = null): array
    {
        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : (defined('SPP_APP_DIR') ? SPP_APP_DIR : getcwd());
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');
        $parentDir = rtrim(str_replace('\\', '/', dirname($baseDir)), '/');
        $cwdDir = rtrim(str_replace('\\', '/', getcwd()), '/');
        $candidateRoots = array_values(array_unique([$baseDir, $parentDir, $cwdDir]));

        $scanDirs = [];
        foreach ($candidateRoots as $root) {
            if (is_dir($root . '/tests/core')) {
                $scanDirs[] = $root . '/tests/core';
            }
            if (is_dir($root . '/tests')) {
                $scanDirs[] = $root . '/tests';
            }
            if (!empty($app) && is_dir($root . "/src/{$app}/tests")) {
                $scanDirs[] = $root . "/src/{$app}/tests";
            } elseif (empty($app) && is_dir($root . '/src')) {
                $appDirs = glob($root . '/src/*/tests', GLOB_ONLYDIR) ?: [];
                foreach ($appDirs as $ad) {
                    $scanDirs[] = str_replace('\\', '/', $ad);
                }
            }
        }

        $discoveredFiles = [];
        foreach (array_unique($scanDirs) as $dir) {
            if (!is_dir($dir)) continue;
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $fileInfo) {
                    if (!$fileInfo->isFile()) continue;
                    $fn = $fileInfo->getFilename();
                    if (str_starts_with($fn, 'test.') || str_ends_with($fn, 'Test.php')) {
                        $p = str_replace('\\', '/', $fileInfo->getPathname());
                        $discoveredFiles[$p] = true;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore unreadable dirs
            }
        }

        $suites = [];
        foreach (array_keys($discoveredFiles) as $filePath) {
            $relPath = ltrim(str_replace($candidateRoots, '', $filePath), '/');
            $content = (string)@file_get_contents($filePath);
            if ($content === '') continue;

            $className = null;
            if (preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $mClass)) {
                $className = $mClass[1];
            }

            $methods = [];
            if (preg_match_all('/(?:public\s+)?function\s+(test[A-Za-z0-9_]*)\s*\(/i', $content, $mMethods)) {
                $methods = array_values(array_unique($mMethods[1]));
            }

            // Also search for docblock @test annotations if any
            if (preg_match_all('/\/\*\*[\s\S]*?@test[\s\S]*?\*\/\s*(?:public\s+)?function\s+([A-Za-z0-9_]+)\s*\(/i', $content, $mAnnotated)) {
                $methods = array_values(array_unique(array_merge($methods, $mAnnotated[1])));
            }

            $suites[] = [
                'file' => $relPath,
                'name' => basename($relPath),
                'class' => $className ?: basename($relPath, '.php'),
                'methods' => $methods,
                'count' => count($methods)
            ];
        }

        // Sort suites by name
        usort($suites, fn($a, $b) => strcmp($a['name'], $b['name']));

        $totalTests = 0;
        foreach ($suites as $s) {
            $totalTests += $s['count'];
        }

        return [
            'success' => true,
            'total_suites' => count($suites),
            'total_tests' => $totalTests,
            'suites' => $suites
        ];
    }
}
