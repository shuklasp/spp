<?php
/**
 * ============================================================================
 * Functional Tests (DSL Style) — SPPDocs
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
 *   php spp.php test:run --app=SPPDocs
 *
 * DSL tests are discovered automatically from test.*.php files.
 * They run alongside class-based tests in the same suite.
 * ============================================================================
 */

// ── Basic Value Tests ──

test('app name is a non-empty string', function () {
    $appName = 'SPPDocs';
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
    $dir = SPP_APP_DIR . '/src/SPPDocs';
    expect(is_dir($dir))->toBeTrue();
});

test('pages.yml config exists', function () {
    $file = SPP_APP_DIR . '/src/SPPDocs/etc/pages.yml';
    expect(file_exists($file))->toBeTrue();
});

// ── Project & Documentation Routing Tests ──

test('project demo-app route resolves in SPPRouter', function () {
    \SPP\Scheduler::setContext('SPPDocs');
    $page = \SPPMod\SPPRouter\SPPRouter::getPage('project/demo-app');
    expect(!empty($page))->toBeTrue();
    expect($page['controller'])->toBe('\App\SPPDocs\Controllers\DocsController@project');
    expect($page['named_params']['projectId'] ?? null)->toBe('demo-app');
});

test('project demo-app renders project portal view without errors', function () {
    \SPP\Scheduler::setContext('SPPDocs');
    $_GET['q'] = 'project/demo-app';
    ob_start();
    \SPPMod\SPPView\ViewRouter::showPage();
    $html = ob_get_clean();
    expect(strlen($html) > 0)->toBeTrue();
    expect(str_contains($html, 'My Demo App'))->toBeTrue();
});

test('docs demo-app route resolves and renders markdown doc', function () {
    \SPP\Scheduler::setContext('SPPDocs');
    $_GET['q'] = 'docs/demo-app/v1/index';
    ob_start();
    \SPPMod\SPPView\ViewRouter::showPage();
    $html = ob_get_clean();
    expect(strlen($html) > 0)->toBeTrue();
    expect(str_contains($html, 'Welcome to My Demo App'))->toBeTrue();
});