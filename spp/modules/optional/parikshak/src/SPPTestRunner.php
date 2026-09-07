<?php

namespace SPPMod\Parikshak;

/**
 * Class SPPTestRunner
 * Discovers and executes standard unit tests extending SPPTestCase.
 */
class SPPTestRunner
{
    private array $results = [];

    public function run(string $appname, bool $withCoverage = false): array
    {
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
            'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0],
            'tests' => []
        ];

        // Core Tests (Framework level)
        $coreTestDir = SPP_APP_DIR . '/tests/core';
        $this->scanAndRun($coreTestDir, 'Core');

        // App Tests
        $appTestDir = SPP_APP_DIR . '/src/' . $appname . '/tests';
        $this->scanAndRun($appTestDir, ucfirst($appname));

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

    private function scanAndRun(string $dir, string $scope): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_starts_with($file->getFilename(), 'test.') && $file->getExtension() === 'php') {
                $this->executeTestFile($file->getPathname(), $scope);
            }
        }
    }

    private function executeTestFile(string $filePath, string $scope): void
    {
        // Require DSL functions if they exist
        $dslFunctions = dirname(__DIR__) . '/src/DSL/functions.php';
        if (file_exists($dslFunctions)) {
            require_once $dslFunctions;
        }

        $classes = $this->extractClassesFromFile($filePath);
        require_once $filePath;

        foreach ($classes as $class) {
            if (class_exists($class) && is_subclass_of($class, SPPTestCase::class)) {
                $this->executeTestCase($class, $scope);
            }
        }

        // Check for Functional DSL tests registered for this file
        if (class_exists('\SPPMod\Parikshak\DSL\Registry')) {
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

        foreach ($tests as $test) {
            $methodName = $test['description'];
            $closure = $test['closure'];
            
            if ($closure instanceof \Closure) {
                $closure = $closure->bindTo($dummyClass, $dummyClass);
            }

            $this->runTestSingle($dummyClass, $className, $methodName, $scope, [], $closure);
        }
        
        \SPPMod\Parikshak\DSL\Registry::clear($filePath);
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

    private function executeTestCase(string $className, string $scope): void
    {
        $refl = new \ReflectionClass($className);
        if ($refl->isAbstract()) {
            return;
        }

        $instance = $refl->newInstance();
        $methods = $refl->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (str_starts_with($methodName, 'test')) {
                // Check for DataProvider attribute
                $attributes = $method->getAttributes(\SPPMod\Parikshak\Attributes\DataProvider::class);
                if (!empty($attributes)) {
                    $providerName = $attributes[0]->getArguments()[0];
                    if (method_exists($instance, $providerName)) {
                        $testData = $instance->$providerName();
                        foreach ($testData as $dataRow) {
                            $this->runTestSingle($instance, $className, $methodName, $scope, is_array($dataRow) ? $dataRow : [$dataRow]);
                        }
                        continue;
                    }
                }
                
                $this->runTestSingle($instance, $className, $methodName, $scope, []);
            }
        }
    }

    private function runTestSingle($instance, string $className, string $methodName, string $scope, array $args, ?callable $closure = null): void
    {
        $this->results['summary']['total']++;
        $report = [
            'scope'  => $scope,
            'class'  => $className,
            'method' => $methodName . (!empty($args) ? ' with data set' : ''),
            'status' => 'passed',
            'error'  => null
        ];

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
        } catch (\Throwable $e) {
            $report['status'] = 'failed';
            $report['error'] = $e->getMessage();
            try {
                $instance->tearDown();
            } catch (\Throwable $e2) {
                $report['error'] .= "\nTeardown Error: " . $e2->getMessage();
            }
        }

        if ($report['status'] === 'passed') {
            $this->results['summary']['passed']++;
        } else {
            $this->results['summary']['failed']++;
        }

        $this->results['tests'][] = $report;
    }
}
