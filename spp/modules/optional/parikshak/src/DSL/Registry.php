<?php
namespace SPPMod\Parikshak\DSL;

class Registry {
    private static array $tests = [];
    private static array $beforeEach = [];
    private static array $afterEach = [];
    
    public static function addTest(string $file, string $description, callable $closure): int {
        self::$tests[$file][] = [
            'description' => $description,
            'closure' => $closure,
            'dataset' => null,
            'skip' => false,
            'only' => false,
            'retry' => 0
        ];
        return count(self::$tests[$file]) - 1;
    }

    public static function setDataset(string $file, int $index, array|callable $dataset): void {
        if (isset(self::$tests[$file][$index])) {
            self::$tests[$file][$index]['dataset'] = $dataset;
        }
    }

    public static function setSkip(string $file, int $index, string|bool $condition): void {
        if (isset(self::$tests[$file][$index])) {
            self::$tests[$file][$index]['skip'] = $condition;
        }
    }

    public static function setOnly(string $file, int $index, bool $only): void {
        if (isset(self::$tests[$file][$index])) {
            self::$tests[$file][$index]['only'] = $only;
        }
    }

    public static function setRetry(string $file, int $index, int $retry): void {
        if (isset(self::$tests[$file][$index])) {
            self::$tests[$file][$index]['retry'] = $retry;
        }
    }
    
    public static function getTests(string $file): array {
        return self::$tests[$file] ?? [];
    }

    public static function addBeforeEach(string $file, callable $closure): void {
        self::$beforeEach[$file][] = $closure;
    }

    public static function getBeforeEach(string $file): array {
        return self::$beforeEach[$file] ?? [];
    }

    public static function addAfterEach(string $file, callable $closure): void {
        self::$afterEach[$file][] = $closure;
    }

    public static function getAfterEach(string $file): array {
        return self::$afterEach[$file] ?? [];
    }

    public static function clear(string $file): void {
        unset(self::$tests[$file], self::$beforeEach[$file], self::$afterEach[$file]);
    }
}
