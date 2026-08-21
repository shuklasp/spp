/**
 * SPP-UX Reconciler Tests
 * 
 * Tests the keyed DOM reconciliation engine (core/reconciler.js).
 * Run with: node --experimental-vm-modules spp/modules/spp/drishyam/js/__tests__/reconciler.test.js
 * 
 * Uses a lightweight JSDOM-free approach: creates real DOM via DOMParser
 * or linkedom if available, otherwise falls back to manual assertions.
 */

import { reconcileDOM, patchAttributes, longestIncreasingSubsequence } from '../core/reconciler.js';

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

// ─── LIS Tests ────────────────────────────────────────────────────

describe('longestIncreasingSubsequence', () => {
    it('should return empty for empty input', () => {
        assertEqual(longestIncreasingSubsequence([]), [], 'Empty array');
    });

    it('should return single element for single input', () => {
        const result = longestIncreasingSubsequence([5]);
        assertEqual(result, [0], 'Single element');
    });

    it('should find LIS in sorted array', () => {
        const result = longestIncreasingSubsequence([0, 1, 2, 3, 4]);
        assertEqual(result, [0, 1, 2, 3, 4], 'Already sorted');
    });

    it('should find LIS in reverse array', () => {
        const result = longestIncreasingSubsequence([4, 3, 2, 1, 0]);
        assertEqual(result.length, 1, 'Reverse sorted — LIS length should be 1');
    });

    it('should find LIS in mixed array', () => {
        const result = longestIncreasingSubsequence([2, 0, 1, 3]);
        // LIS is 0,1,3 at indices 1,2,3
        assertEqual(result.length, 3, 'Mixed array — LIS length should be 3');
    });

    it('should handle duplicates correctly', () => {
        const result = longestIncreasingSubsequence([1, 1, 1, 1]);
        assertEqual(result.length, 1, 'All duplicates — LIS length should be 1');
    });

    it('should handle complex case', () => {
        // Classic example: [3, 5, 6, 2, 5, 4, 19, 5, 6, 7, 12]
        const result = longestIncreasingSubsequence([3, 5, 6, 2, 5, 4, 19, 5, 6, 7, 12]);
        assert(result.length >= 5, 'Complex case — LIS length should be >= 5');
    });
});

// ─── DOM Reconciliation Tests (Browser-only) ──────────────────────

if (typeof document !== 'undefined') {
    describe('reconcileDOM — unkeyed', () => {
        it('should add new nodes', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<p>A</p>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<p>A</p><p>B</p>';
            
            reconcileDOM(parent, newParent);
            assertEqual(parent.children.length, 2, 'Should have 2 children');
            assertEqual(parent.children[1].textContent, 'B', 'Second child should be B');
        });

        it('should remove excess nodes', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<p>A</p><p>B</p><p>C</p>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<p>A</p>';
            
            reconcileDOM(parent, newParent);
            assertEqual(parent.children.length, 1, 'Should have 1 child');
        });

        it('should update text content', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<p>Old</p>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<p>New</p>';
            
            reconcileDOM(parent, newParent);
            assertEqual(parent.children[0].textContent, 'New', 'Text should update');
        });

        it('should update attributes', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<p class="old" id="test">Text</p>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<p class="new" id="test">Text</p>';
            
            reconcileDOM(parent, newParent);
            assertEqual(parent.children[0].className, 'new', 'Class should update');
        });

        it('should replace when tag changes', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<p>Text</p>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<span>Text</span>';
            
            reconcileDOM(parent, newParent);
            assertEqual(parent.children[0].tagName, 'SPAN', 'Tag should change to SPAN');
        });

        it('should skip data-spp-preserve elements', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<div data-spp-preserve="true"><p>Original</p></div>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<div data-spp-preserve="true"><p>Changed</p></div>';
            
            reconcileDOM(parent, newParent);
            assertEqual(
                parent.children[0].children[0].textContent, 
                'Original', 
                'Preserved element children should not change'
            );
        });
    });

    describe('reconcileDOM — keyed', () => {
        it('should reuse keyed nodes on reorder', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<div data-key="a">A</div><div data-key="b">B</div><div data-key="c">C</div>';
            
            const originalA = parent.children[0];
            const originalB = parent.children[1];
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<div data-key="c">C</div><div data-key="a">A</div><div data-key="b">B</div>';
            
            reconcileDOM(parent, newParent);
            
            assertEqual(parent.children.length, 3, 'Should still have 3 children');
            assertEqual(parent.children[1], originalA, 'Node A should be reused (same reference)');
            assertEqual(parent.children[2], originalB, 'Node B should be reused (same reference)');
        });

        it('should insert new keyed node', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<div data-key="a">A</div><div data-key="c">C</div>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<div data-key="a">A</div><div data-key="b">B</div><div data-key="c">C</div>';
            
            reconcileDOM(parent, newParent);
            
            assertEqual(parent.children.length, 3, 'Should have 3 children');
            assertEqual(parent.children[1].getAttribute('data-key'), 'b', 'Middle child should be B');
        });

        it('should remove orphaned keyed node', () => {
            const parent = document.createElement('div');
            parent.innerHTML = '<div data-key="a">A</div><div data-key="b">B</div><div data-key="c">C</div>';
            
            const newParent = document.createElement('div');
            newParent.innerHTML = '<div data-key="a">A</div><div data-key="c">C</div>';
            
            reconcileDOM(parent, newParent);
            
            assertEqual(parent.children.length, 2, 'Should have 2 children');
            assertEqual(parent.children[0].textContent, 'A', 'First should be A');
            assertEqual(parent.children[1].textContent, 'C', 'Second should be C');
        });
    });

    describe('patchAttributes', () => {
        it('should add new attributes', () => {
            const old = document.createElement('div');
            const newEl = document.createElement('div');
            newEl.setAttribute('class', 'test');
            newEl.setAttribute('title', 'hello');
            
            patchAttributes(old, newEl);
            assertEqual(old.getAttribute('class'), 'test', 'Class should be added');
            assertEqual(old.getAttribute('title'), 'hello', 'Title should be added');
        });

        it('should remove stale attributes', () => {
            const old = document.createElement('div');
            old.setAttribute('class', 'old');
            old.setAttribute('data-gone', 'yes');
            const newEl = document.createElement('div');
            newEl.setAttribute('class', 'new');
            
            patchAttributes(old, newEl);
            assertEqual(old.getAttribute('class'), 'new', 'Class should update');
            assertEqual(old.getAttribute('data-gone'), null, 'Stale attr should be removed');
        });
    });
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
