<?php

use SPPMod\Parikshak\DSL\Registry;
use SPPMod\Parikshak\DSL\Expectation;

if (!function_exists('test')) {
    function test(string $description, callable $closure) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = 'unknown';
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && $trace['file'] !== __FILE__) {
                $file = $trace['file'];
                break;
            }
        }
        // Normalize file paths to avoid backslash/forward slash mismatches
        $file = str_replace('\\', '/', realpath($file) ?: $file);
        Registry::addTest($file, $description, $closure);
    }
}

if (!function_exists('it')) {
    function it(string $description, callable $closure) {
        test("it " . $description, $closure);
    }
}

if (!function_exists('expect')) {
    function expect($value) {
        return new Expectation($value);
    }
}
