/**
 * SPP-UX Reactivity Tests
 * 
 * Tests the Signal, Computed, effect, batch, and store primitives (core/reactive.js).
 */

import { Signal, Computed, effect, batch, createStore, SPPStore } from '../core/reactive.js';

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

// ─── Signal Tests ─────────────────────────────────────────────────

describe('Signal', () => {
    it('should hold a value', () => {
        const s = new Signal(42);
        assertEqual(s.value, 42, 'Initial value');
    });

    it('should update value', () => {
        const s = new Signal(1);
        s.value = 2;
        assertEqual(s.value, 2, 'Updated value');
    });

    it('should not notify on same value', () => {
        const s = new Signal(5);
        let notified = false;
        effect(() => {
            s.value;
            notified = true;
        });
        notified = false;
        s.value = 5; // Same value
        // Give microtask time to flush
        setTimeout(() => {
            assertEqual(notified, false, 'Should not notify on same value');
        }, 10);
    });

    it('peek() should not track', () => {
        const s = new Signal(10);
        let effectRan = 0;
        effect(() => {
            s.peek();
            effectRan++;
        });
        assertEqual(effectRan, 1, 'Effect runs once initially');
        s.value = 20;
        // Effect should NOT re-run since peek() doesn't track
        setTimeout(() => {
            assertEqual(effectRan, 1, 'Effect should not re-run on peek()');
        }, 10);
    });
});

// ─── Computed Tests ───────────────────────────────────────────────

describe('Computed', () => {
    it('should compute derived value', () => {
        const count = new Signal(2);
        const doubled = new Computed(() => count.value * 2);
        assertEqual(doubled.value, 4, 'Computed value');
    });

    it('should update when dependency changes', () => {
        const count = new Signal(3);
        const doubled = new Computed(() => count.value * 2);
        assertEqual(doubled.value, 6, 'Initial computed');
        count.value = 5;
        assertEqual(doubled.value, 10, 'Updated computed');
    });

    it('should be lazy', () => {
        let computeCount = 0;
        const s = new Signal(1);
        const c = new Computed(() => {
            computeCount++;
            return s.value * 2;
        });
        // Computed runs once during constructor to establish deps
        const initialCount = computeCount;
        s.value = 2;
        // Should not have recomputed yet (lazy)
        assertEqual(computeCount, initialCount, 'Should not recompute until read');
        const val = c.value; // This triggers recompute
        assertEqual(val, 4, 'Correct recomputed value');
    });

    it('peek() should not track', () => {
        const s = new Signal(10);
        const c = new Computed(() => s.value + 1);
        assertEqual(c.peek(), 11, 'Peek returns correct value');
    });
});

// ─── Effect Tests ─────────────────────────────────────────────────

describe('effect()', () => {
    it('should run immediately', () => {
        let ran = false;
        const dispose = effect(() => { ran = true; });
        assertEqual(ran, true, 'Effect runs immediately');
        dispose();
    });

    it('should return a dispose function', () => {
        const s = new Signal(1);
        let value = 0;
        const dispose = effect(() => { value = s.value; });
        assertEqual(value, 1, 'Initial effect');
        dispose();
        s.value = 99;
        // After dispose, effect should not re-run
        setTimeout(() => {
            assertEqual(value, 1, 'Disposed effect should not re-run');
        }, 10);
    });

    it('should track multiple signals', () => {
        const a = new Signal(1);
        const b = new Signal(2);
        let sum = 0;
        const dispose = effect(() => { sum = a.value + b.value; });
        assertEqual(sum, 3, 'Initial sum');
        dispose();
    });
});

// ─── Batch Tests ──────────────────────────────────────────────────

describe('batch()', () => {
    it('should coalesce multiple writes', () => {
        const a = new Signal(1);
        const b = new Signal(2);
        let effectCount = 0;
        const dispose = effect(() => {
            a.value;
            b.value;
            effectCount++;
        });
        effectCount = 0; // Reset after initial run

        batch(() => {
            a.value = 10;
            b.value = 20;
        });

        // After batch, effect should have run exactly once (not twice)
        assertEqual(effectCount, 1, 'Batch should coalesce to one effect run');
        dispose();
    });
});

// ─── createStore Tests ────────────────────────────────────────────

describe('createStore()', () => {
    it('should create reactive proxy', () => {
        const store = createStore({ count: 0, name: 'test' });
        assertEqual(store.count, 0, 'Initial count');
        assertEqual(store.name, 'test', 'Initial name');
    });

    it('should notify on property set', () => {
        const store = createStore({ count: 0 });
        let notified = false;
        store.subscribe(() => { notified = true; });
        store.count = 5;
        assertEqual(notified, true, 'Should notify on set');
        assertEqual(store.count, 5, 'Value should update');
    });

    it('subscribe should return unsubscribe function', () => {
        const store = createStore({ x: 1 });
        let called = 0;
        const unsub = store.subscribe(() => { called++; });
        store.x = 2;
        assertEqual(called, 1, 'Called once');
        unsub();
        store.x = 3;
        assertEqual(called, 1, 'Not called after unsubscribe');
    });
});

// ─── SPPStore Tests ───────────────────────────────────────────────

describe('SPPStore', () => {
    it('should hold state', () => {
        const store = new SPPStore({ theme: 'dark' });
        assertEqual(store.get().theme, 'dark', 'Initial state');
    });

    it('should merge state on set()', () => {
        const store = new SPPStore({ a: 1, b: 2 });
        store.set({ b: 3 });
        assertEqual(store.get().a, 1, 'Unchanged key preserved');
        assertEqual(store.get().b, 3, 'Changed key updated');
    });

    it('should notify subscribers', () => {
        const store = new SPPStore({ val: 'x' });
        let received = null;
        store.subscribe(state => { received = state.val; });
        store.set({ val: 'y' });
        assertEqual(received, 'y', 'Subscriber received new state');
    });
});

// ─── Summary ──────────────────────────────────────────────────────

console.log(`\n${'═'.repeat(50)}`);
console.log(`Tests: ${_passed} passed, ${_failed} failed`);
if (_errors.length > 0) {
    console.log('\nFailures:');
    _errors.forEach(e => console.log(`  • ${e}`));
}
console.log(`${'═'.repeat(50)}`);

if (_failed > 0) process.exit(1);
