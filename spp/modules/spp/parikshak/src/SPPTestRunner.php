<?php

namespace SPPMod\Parikshak;

/**
 * Class SPPTestRunner
 * Discovers and executes standard unit tests extending SPPTestCase.
 */
class SPPTestRunner
{
    private array $results = [];

    public function run(string $appname): array
    {
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

        return $this->results;
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
        $declaredBefore = get_declared_classes();
        require_once $filePath;
        $declaredAfter = get_declared_classes();
        $newClasses = array_diff($declaredAfter, $declaredBefore);

        foreach ($newClasses as $class) {
            if (is_subclass_of($class, SPPTestCase::class)) {
                $this->executeTestCase($class, $scope);
            }
        }
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
                $this->results['summary']['total']++;
                $report = [
                    'scope'  => $scope,
                    'class'  => $className,
                    'method' => $methodName,
                    'status' => 'passed',
                    'error'  => null
                ];

                try {
                    $instance->setUp();
                    $instance->$methodName();
                    $instance->tearDown();
                } catch (\Throwable $e) {
                    $report['status'] = 'failed';
                    $report['error'] = $e->getMessage();
                    try {
                        $instance->tearDown();
                    } catch (\Throwable $e2) {
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
    }
}
