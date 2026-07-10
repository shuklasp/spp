<?php
/**
 * ============================================================================
 * Functional Tests (DSL Style) — test_api_app
 * ============================================================================
 *
 * HOW DSL TESTS WORK:
 * Parikshak provides a BDD-style DSL (Domain Specific Language) for tests.
 * Instead of classes, you use simple functions:
 *
 *   test('description', function() {
 *       // Test logic here
 *       expect($value)->toBe($expected);
 *   });
 *
 *   it('should do something', function() {
 *       // Same as test() but prefixes description with "it "
 *       expect(true)->toBeTrue();
 *   });
 *
 * AVAILABLE EXPECTATIONS:
 *   expect($value)->toBe($expected)    — Strict equality (===)
 *   expect($value)->toBeTrue()          — Assert true
 *   expect($value)->toBeFalse()         — Assert false
 *   expect($value)->toBeNull()          — Assert null
 *
 * RUNNING:
 *   php spp.php test:run --app=test_api_app
 *
 * DSL tests are discovered automatically from test.*.php files.
 * They run alongside class-based tests in the same suite.
 * ============================================================================
 */

// ── Basic Value Tests ──

test('app name is a non-empty string', function () {
    $appName = 'test_api_app';
    expect(strlen($appName) > 0)->toBeTrue();
});

test('null is null', function () {
    expect(null)->toBeNull();
});

it('should validate boolean true', function () {
    expect(true)->toBeTrue();
});

it('should validate boolean false', function () {
    expect(false)->toBeFalse();
});

// ── String Tests ──

test('string concatenation works', function () {
    $result = 'Hello' . ' ' . 'World';
    expect($result)->toBe('Hello World');
});

// ── Array Tests ──

test('array operations work correctly', function () {
    $items = ['a', 'b', 'c'];
    expect(count($items))->toBe(3);
    expect(in_array('b', $items))->toBeTrue();
    expect(in_array('z', $items))->toBeFalse();
});

// ── Math Tests ──

test('basic arithmetic is correct', function () {
    expect(2 + 2)->toBe(4);
    expect(10 - 3)->toBe(7);
    expect(3 * 4)->toBe(12);
});

// ── File System Tests ──

test('app directory exists', function () {
    $dir = SPP_APP_DIR . '/src/test_api_app';
    expect(is_dir($dir))->toBeTrue();
});

test('pages.yml config exists', function () {
    $file = SPP_APP_DIR . '/etc/apps/test_api_app/pages.yml';
    expect(file_exists($file))->toBeTrue();
});