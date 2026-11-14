<?php
namespace SPPMod\Parikshak\DSL;

class Expectation {
    private mixed $value;
    private bool $isNot = false;

    public function __construct(mixed $value, bool $isNot = false) {
        $this->value = $value;
        $this->isNot = $isNot;
    }

    /**
     * Negation property: expect($val)->not->toBe(...)
     */
    public function __get(string $name) {
        if ($name === 'not') {
            return new self($this->value, !$this->isNot);
        }
        throw new \InvalidArgumentException("Undefined expectation property '{$name}'.");
    }

    /**
     * Negation method: expect($val)->not()->toBe(...)
     */
    public function not(): self {
        return new self($this->value, !$this->isNot);
    }

    /**
     * Chaining helper: expect($val)->toBe(1)->and($other)->toBe(2)
     */
    public function and(mixed $value): self {
        return new self($value);
    }

    protected function assert(bool $condition, string $failureMessage): self {
        $passed = $this->isNot ? !$condition : $condition;
        if (!$passed) {
            $prefix = $this->isNot ? "Failed asserting that value NOT " : "Failed asserting that value ";
            throw new \Exception($prefix . $failureMessage);
        }
        return $this;
    }

    private function repr(mixed $val): string {
        if ($val === null) return 'null';
        if (is_bool($val)) return $val ? 'true' : 'false';
        if (is_scalar($val)) return (string)$val;
        if (is_array($val)) return 'array(' . count($val) . ')';
        if (is_object($val)) return get_class($val);
        return gettype($val);
    }

    public function toBe(mixed $expected): self {
        return $this->assert(
            $this->value === $expected,
            "strictly equals expected '{$this->repr($expected)}', got '{$this->repr($this->value)}'."
        );
    }

    public function toEqual(mixed $expected): self {
        return $this->assert(
            $this->value == $expected,
            "matches expected '{$this->repr($expected)}', got '{$this->repr($this->value)}'."
        );
    }

    public function toBeTrue(): self {
        return $this->assert(
            $this->value === true,
            "is true, got '{$this->repr($this->value)}'."
        );
    }

    public function toBeFalse(): self {
        return $this->assert(
            $this->value === false,
            "is false, got '{$this->repr($this->value)}'."
        );
    }

    public function toBeNull(): self {
        return $this->assert(
            $this->value === null,
            "is null, got '{$this->repr($this->value)}'."
        );
    }

    public function toBeNotNull(): self {
        return $this->assert(
            $this->value !== null,
            "is not null."
        );
    }

    public function toBeEmpty(): self {
        return $this->assert(
            empty($this->value),
            "is empty, got '{$this->repr($this->value)}'."
        );
    }

    public function toBeNotEmpty(): self {
        return $this->assert(
            !empty($this->value),
            "is not empty."
        );
    }

    public function toBeGreaterThan(mixed $expected): self {
        return $this->assert(
            $this->value > $expected,
            "is greater than '{$this->repr($expected)}', got '{$this->repr($this->value)}'."
        );
    }

    public function toBeGreaterThanOrEqual(mixed $expected): self {
        return $this->assert(
            $this->value >= $expected,
            "is greater than or equal to '{$this->repr($expected)}', got '{$this->repr($this->value)}'."
        );
    }

    public function toBeLessThan(mixed $expected): self {
        return $this->assert(
            $this->value < $expected,
            "is less than '{$this->repr($expected)}', got '{$this->repr($this->value)}'."
        );
    }

    public function toBeLessThanOrEqual(mixed $expected): self {
        return $this->assert(
            $this->value <= $expected,
            "is less than or equal to '{$this->repr($expected)}', got '{$this->repr($this->value)}'."
        );
    }

    public function toContain(mixed $needle): self {
        $condition = false;
        if (is_string($this->value) && is_string($needle)) {
            $condition = str_contains($this->value, $needle);
        } elseif (is_array($this->value)) {
            $condition = in_array($needle, $this->value, true);
        }
        return $this->assert(
            $condition,
            "contains '{$this->repr($needle)}'."
        );
    }

    public function toHaveCount(int $count): self {
        $actualCount = is_countable($this->value) ? count($this->value) : (is_array($this->value) ? count($this->value) : 0);
        return $this->assert(
            $actualCount === $count,
            "has count {$count}, found {$actualCount}."
        );
    }

    public function toHaveKey(string|int $key): self {
        $hasKey = is_array($this->value) && array_key_exists($key, $this->value);
        return $this->assert(
            $hasKey,
            "has key '{$key}'."
        );
    }

    public function toBeInstanceOf(string $class): self {
        return $this->assert(
            $this->value instanceof $class,
            "is an instance of {$class}, got '{$this->repr($this->value)}'."
        );
    }

    public function toBeString(): self {
        return $this->assert(is_string($this->value), "is a string, got '{$this->repr($this->value)}'.");
    }

    public function toBeInt(): self {
        return $this->assert(is_int($this->value), "is an int, got '{$this->repr($this->value)}'.");
    }

    public function toBeFloat(): self {
        return $this->assert(is_float($this->value), "is a float, got '{$this->repr($this->value)}'.");
    }

    public function toBeArray(): self {
        return $this->assert(is_array($this->value), "is an array, got '{$this->repr($this->value)}'.");
    }

    public function toBeBool(): self {
        return $this->assert(is_bool($this->value), "is a boolean, got '{$this->repr($this->value)}'.");
    }

    public function toMatch(string $pattern): self {
        $condition = is_string($this->value) && preg_match($pattern, $this->value);
        return $this->assert(
            (bool)$condition,
            "matches regex pattern '{$pattern}', got '{$this->repr($this->value)}'."
        );
    }

    public function toStartWith(string $prefix): self {
        $condition = is_string($this->value) && str_starts_with($this->value, $prefix);
        return $this->assert(
            $condition,
            "starts with prefix '{$prefix}'."
        );
    }

    public function toEndWith(string $suffix): self {
        $condition = is_string($this->value) && str_ends_with($this->value, $suffix);
        return $this->assert(
            $condition,
            "ends with suffix '{$suffix}'."
        );
    }

    public function toThrow(string $exceptionClass = \Throwable::class): self {
        if (!is_callable($this->value)) {
            throw new \InvalidArgumentException("Value must be a callable to assert exceptions.");
        }
        $thrown = false;
        $caught = null;
        try {
            ($this->value)();
        } catch (\Throwable $e) {
            $caught = $e;
            if ($e instanceof $exceptionClass) {
                $thrown = true;
            }
        }

        if ($this->isNot) {
            if ($thrown) {
                throw new \Exception("Failed asserting that callable does NOT throw {$exceptionClass}.");
            }
        } else {
            if (!$thrown) {
                $got = $caught ? get_class($caught) . ': ' . $caught->getMessage() : 'nothing thrown';
                throw new \Exception("Failed asserting that callable throws {$exceptionClass}, got {$got}.");
            }
        }
        return $this;
    }

    public function toMatchSnapshot(?string $snapshotName = null): self {
        \SPPMod\Parikshak\SnapshotManager::assertMatches($this->value, $snapshotName);
        return $this;
    }

    public function toRunUnder(float|int $maxMilliseconds): self {
        if (!is_callable($this->value)) {
            throw new \InvalidArgumentException("Value must be a callable to measure execution time.");
        }
        $start = hrtime(true);
        ($this->value)();
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        return $this->assert(
            $elapsedMs <= $maxMilliseconds,
            sprintf("ran in %.2fms, exceeding budget of %.2fms.", $elapsedMs, (float)$maxMilliseconds)
        );
    }

    public function toUseQueriesLessThan(int $maxQueries): self {
        if (!is_callable($this->value)) {
            throw new \InvalidArgumentException("Value must be a callable to count database queries.");
        }
        $start = class_exists('\SPP\DB') ? \SPP\DB::getQueryCount() : 0;
        ($this->value)();
        $end = class_exists('\SPP\DB') ? \SPP\DB::getQueryCount() : 0;
        $used = $end - $start;

        return $this->assert(
            $used < $maxQueries,
            "used {$used} database queries, exceeding limit of < {$maxQueries}."
        );
    }

    public function toUseMemoryLessThan(string|int $maxMemory): self {
        if (!is_callable($this->value)) {
            throw new \InvalidArgumentException("Value must be a callable to measure memory usage.");
        }
        $maxBytes = is_int($maxMemory) ? $maxMemory : (function(string $m) {
            $unit = strtoupper(substr($m, -2));
            $val = (float)substr($m, 0, -2);
            if ($unit === 'KB') return (int)($val * 1024);
            if ($unit === 'MB') return (int)($val * 1024 * 1024);
            if ($unit === 'GB') return (int)($val * 1024 * 1024 * 1024);
            return (int)$m;
        })($maxMemory);

        $before = memory_get_usage();
        ($this->value)();
        $peak = memory_get_peak_usage() - $before;

        return $this->assert(
            $peak <= $maxBytes,
            "consumed {$peak} bytes, exceeding budget of {$maxBytes} bytes."
        );
    }
}
