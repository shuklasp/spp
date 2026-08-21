/**
 * SPP-UX Event Delegation Tests
 * 
 * Tests the O(1) WeakMap-based event system (core/events.js).
 * Browser-only tests — requires a DOM environment.
 */

import { registerHandler, removeHandler, removeAllHandlers, getHandler, _handlerRegistry } from '../core/events.js';

// ─── Test Harness ─────────────────────────────────────────────────

let _passed = 0;
let _failed = 0;
const _errors = [];

function assert(condition, message) {
    if (condition) {
        _passed++;
    } else {
        _failed++;
        _errors.push(message);
        console.error(`  ✗ ${message}`);
    }
}

function assertEqual(actual, expected, message) {
    const pass = JSON.stringify(actual) === JSON.stringify(expected);
    if (pass) {
        _passed++;
    } else {
        _failed++;
        const msg = `${message} — Expected: ${JSON.stringify(expected)}, Got: ${JSON.stringify(actual)}`;
        _errors.push(msg);
        console.error(`  ✗ ${msg}`);
    }
}

function describe(name, fn) {
    console.log(`\n● ${name}`);
    fn();
}

function it(name, fn) {
    try {
        fn();
        console.log(`  ✓ ${name}`);
    } catch (e) {
        _failed++;
        _errors.push(`${name}: ${e.message}`);
        console.error(`  ✗ ${name}: ${e.message}`);
    }
}

// ─── Tests (Browser-only) ─────────────────────────────────────────

if (typeof document !== 'undefined') {
    describe('registerHandler', () => {
        it('should register a handler in the WeakMap', () => {
            const el = document.createElement('button');
            const handler = () => {};
            registerHandler(el, 'click', handler);

            const registered = getHandler(el, 'click');
            assertEqual(registered, handler, 'Handler should be retrievable');
        });

        it('should return a cleanup function', () => {
            const el = document.createElement('button');
            const handler = () => {};
            const cleanup = registerHandler(el, 'click', handler);

            assert(typeof cleanup === 'function', 'Should return a function');
            cleanup();
            assertEqual(getHandler(el, 'click'), undefined, 'Handler should be removed after cleanup');
        });

        it('should handle multiple event types on same element', () => {
            const el = document.createElement('input');
            const clickHandler = () => 'click';
            const inputHandler = () => 'input';

            registerHandler(el, 'click', clickHandler);
            registerHandler(el, 'input', inputHandler);

            assertEqual(getHandler(el, 'click'), clickHandler, 'Click handler');
            assertEqual(getHandler(el, 'input'), inputHandler, 'Input handler');
        });
    });

    describe('removeHandler', () => {
        it('should remove a specific handler', () => {
            const el = document.createElement('div');
            registerHandler(el, 'click', () => {});
            registerHandler(el, 'mouseover', () => {});

            removeHandler(el, 'click');

            assertEqual(getHandler(el, 'click'), undefined, 'Click handler removed');
            assert(getHandler(el, 'mouseover') !== undefined, 'Mouseover handler still present');
        });

        it('should handle non-existent element gracefully', () => {
            const el = document.createElement('div');
            // Should not throw
            removeHandler(el, 'click');
            _passed++;
        });
    });

    describe('removeAllHandlers', () => {
        it('should remove all handlers from an element', () => {
            const el = document.createElement('div');
            registerHandler(el, 'click', () => {});
            registerHandler(el, 'input', () => {});
            registerHandler(el, 'blur', () => {});

            removeAllHandlers(el);

            assertEqual(getHandler(el, 'click'), undefined, 'Click removed');
            assertEqual(getHandler(el, 'input'), undefined, 'Input removed');
            assertEqual(getHandler(el, 'blur'), undefined, 'Blur removed');
        });
    });

    describe('WeakMap GC behavior', () => {
        it('should not prevent GC of removed DOM nodes', () => {
            // We can't directly test GC, but we can verify the WeakMap
            // doesn't hold strong references
            const el = document.createElement('div');
            registerHandler(el, 'click', () => {});
            assert(_handlerRegistry.has(el), 'WeakMap has the element');
            // After el goes out of scope and is GC'd, the WeakMap entry
            // will be automatically cleaned up. We can't verify this in
            // a synchronous test, but the WeakMap guarantees it.
        });
    });

    describe('getHandler', () => {
        it('should return undefined for unregistered elements', () => {
            const el = document.createElement('div');
            assertEqual(getHandler(el, 'click'), undefined, 'No handler registered');
        });

        it('should return undefined for unregistered event types', () => {
            const el = document.createElement('div');
            registerHandler(el, 'click', () => {});
            assertEqual(getHandler(el, 'mouseover'), undefined, 'Wrong event type');
        });
    });
} else {
    console.log('⚠️  Event tests require a browser DOM environment. Skipping.');
}

// ─── Summary ──────────────────────────────────────────────────────

console.log(`\n${'═'.repeat(50)}`);
console.log(`Tests: ${_passed} passed, ${_failed} failed`);
if (_errors.length > 0) {
    console.log('\nFailures:');
    _errors.forEach(e => console.log(`  • ${e}`));
}
console.log(`${'═'.repeat(50)}`);

if (_failed > 0) process.exit(1);
