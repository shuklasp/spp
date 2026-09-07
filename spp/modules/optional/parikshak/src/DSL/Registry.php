<?php
namespace SPPMod\Parikshak\DSL;

class Registry {
    private static array $tests = [];
    
    public static function addTest(string $file, string $description, callable $closure) {
        self::$tests[$file][] = [
            'description' => $description,
            'closure' => $closure
        ];
    }
    
    public static function getTests(string $file): array {
        return self::$tests[$file] ?? [];
    }

    public static function clear(string $file): void {
        unset(self::$tests[$file]);
    }
}
