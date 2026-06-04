<?php

namespace SPPMod\Parikshak;

/**
 * Class SPPTestCase
 * Base class for Parikshak Automated Unit Tests.
 */
abstract class SPPTestCase
{
    public function setUp(): void
    {
        // Setup logic before each test runs
    }

    public function tearDown(): void
    {
        // Teardown logic after each test runs
    }

    protected function assertTrue($condition, string $message = ''): void
    {
        if ($condition !== true) {
            throw new \Exception($message ?: "Failed asserting that value is true.");
        }
    }

    protected function assertFalse($condition, string $message = ''): void
    {
        if ($condition !== false) {
            throw new \Exception($message ?: "Failed asserting that value is false.");
        }
    }

    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $expectedStr = is_scalar($expected) ? (string)$expected : gettype($expected);
            $actualStr = is_scalar($actual) ? (string)$actual : gettype($actual);
            throw new \Exception($message ?: "Failed asserting that '{$actualStr}' matches expected '{$expectedStr}'.");
        }
    }

    protected function assertSame($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \Exception($message ?: "Failed asserting that values are strictly identical.");
        }
    }

    protected function assertInstanceOf(string $expected, $actual, string $message = ''): void
    {
        if (!($actual instanceof $expected)) {
            $type = is_object($actual) ? get_class($actual) : gettype($actual);
            throw new \Exception($message ?: "Failed asserting that {$type} is an instance of {$expected}.");
        }
    }

    protected function expectException(string $exceptionClass, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return; // Passed
            }
            throw new \Exception("Expected exception {$exceptionClass}, but got " . get_class($e));
        }
        throw new \Exception("Expected exception {$exceptionClass} was not thrown.");
    }
}
