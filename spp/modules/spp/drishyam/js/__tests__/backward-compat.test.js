/**
 * SPP-UX Backward Compatibility Tests
 * 
 * Verifies that all existing v11 APIs and patterns still work in v13.
 * This is the most critical test file — if these pass, existing
 * components will work without modifications.
 * 
 * Browser-only — requires a DOM environment.
 */

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

// ─── Global Availability Tests ────────────────────────────────────

if (typeof window !== 'undefined') {
    describe('Window Globals', () => {
        it('window.BaseComponent should be defined', () => {
            assert(typeof window.BaseComponent === 'function', 'BaseComponent is a function');
        });

        it('window.html should be defined', () => {
            assert(typeof window.html === 'function', 'html is a function');
        });

        it('window.Fragment should be defined', () => {
            assert(window.Fragment !== undefined, 'Fragment exists');
            assert(window.Fragment.__isTrusted === true, 'Fragment is TrustedHTML');
        });

        it('window.TrustedHTML should be defined', () => {
            assert(typeof window.TrustedHTML === 'function', 'TrustedHTML is a function');
        });

        it('window.SPPStore should be defined', () => {
            assert(typeof window.SPPStore === 'function', 'SPPStore is a function');
        });

        it('window.SPPUX should be defined', () => {
            assert(typeof window.SPPUX === 'object', 'SPPUX is an object');
        });

        it('window.__spp_handlers should be defined (legacy compat)', () => {
            assert(typeof window.__spp_handlers === 'object', '__spp_handlers exists');
        });
    });

    describe('SPPUX Namespace', () => {
        it('SPPUX.api should be a function', () => {
            assert(typeof window.SPPUX.api === 'function', 'api is a function');
        });

        it('SPPUX.apiPost should be a function', () => {
            assert(typeof window.SPPUX.apiPost === 'function', 'apiPost is a function');
        });

        it('SPPUX.createStore should be a function', () => {
            assert(typeof window.SPPUX.createStore === 'function', 'createStore is a function');
        });

        it('SPPUX.signal should be a function', () => {
            assert(typeof window.SPPUX.signal === 'function', 'signal is a function');
        });

        it('SPPUX.computed should be a function', () => {
            assert(typeof window.SPPUX.computed === 'function', 'computed is a function');
        });

        it('SPPUX.effect should be a function', () => {
            assert(typeof window.SPPUX.effect === 'function', 'effect is a function');
        });

        it('SPPUX.batch should be a function', () => {
            assert(typeof window.SPPUX.batch === 'function', 'batch is a function');
        });

        it('SPPUX.render should be a function', () => {
            assert(typeof window.SPPUX.render === 'function', 'render is a function');
        });

        it('SPPUX.defineElement should be a function', () => {
            assert(typeof window.SPPUX.defineElement === 'function', 'defineElement is a function');
        });

        it('SPPUX.Busy should have start/stop/reset', () => {
            assert(typeof window.SPPUX.Busy.start === 'function', 'Busy.start');
            assert(typeof window.SPPUX.Busy.stop === 'function', 'Busy.stop');
            assert(typeof window.SPPUX.Busy.reset === 'function', 'Busy.reset');
        });

        it('SPPUX.utils should have standard helpers', () => {
            assert(typeof window.SPPUX.utils.debounce === 'function', 'debounce');
            assert(typeof window.SPPUX.utils.serializeForm === 'function', 'serializeForm');
            assert(typeof window.SPPUX.utils.escapeHtml === 'function', 'escapeHtml');
        });

        it('SPPUX.Router should exist', () => {
            assert(typeof window.SPPUX.Router === 'object', 'Router exists');
            assert(typeof window.SPPUX.Router.init === 'function', 'Router.init');
            assert(typeof window.SPPUX.Router.push === 'function', 'Router.push');
        });
    });

    describe('html Tagged Template', () => {
        it('should return TrustedHTML', () => {
            const result = window.html`<div>Hello</div>`;
            assert(result.__isTrusted === true, 'Is TrustedHTML');
            assert(result.content.includes('<div>Hello</div>'), 'Contains HTML');
        });

        it('should escape interpolated values', () => {
            const result = window.html`<div>${'<script>alert(1)</script>'}</div>`;
            assert(!result.content.includes('<script>'), 'Script tags escaped');
            assert(result.content.includes('&lt;script&gt;'), 'Escaped correctly');
        });

        it('should pass through TrustedHTML values', () => {
            const inner = new window.TrustedHTML('<strong>Bold</strong>');
            const result = window.html`<div>${inner}</div>`;
            assert(result.content.includes('<strong>Bold</strong>'), 'TrustedHTML not escaped');
        });

        it('should handle arrays', () => {
            const items = [1, 2, 3].map(n => window.html`<li>${n}</li>`);
            const result = window.html`<ul>${items}</ul>`;
            assert(result.content.includes('<li>1</li>'), 'Array item 1');
            assert(result.content.includes('<li>3</li>'), 'Array item 3');
        });

        it('should handle boolean attributes', () => {
            const result = window.html`<input ?disabled=${true}>`;
            assert(result.content.includes('disabled'), 'Disabled present when true');

            const result2 = window.html`<input ?disabled=${false}>`;
            assert(!result2.content.includes('disabled'), 'Disabled absent when false');
        });
    });

    describe('BaseComponent Lifecycle', () => {
        it('should instantiate with container', () => {
            const container = document.createElement('div');
            const comp = new window.BaseComponent(null, container, { test: true });
            assertEqual(comp.container, container, 'Container set');
            assertEqual(comp.props.test, true, 'Props set');
            comp.dispose();
        });

        it('setState should update state', () => {
            const container = document.createElement('div');
            const comp = new window.BaseComponent(null, container);
            comp.setState({ count: 5 });
            assertEqual(comp.state.count, 5, 'State updated');
            comp.dispose();
        });

        it('setState with function updater', () => {
            const container = document.createElement('div');
            const comp = new window.BaseComponent(null, container);
            comp.state = { count: 1 };
            comp.setState(prev => ({ count: prev.count + 1 }));
            assertEqual(comp.state.count, 2, 'Function updater works');
            comp.dispose();
        });

        it('dispose should cleanup', () => {
            const container = document.createElement('div');
            const comp = new window.BaseComponent(null, container);
            comp.dispose();
            assertEqual(comp.isDisposed, true, 'isDisposed flag set');
        });

        it('render should return Fragment by default', () => {
            const comp = new window.BaseComponent(null, document.createElement('div'));
            const result = comp.render();
            assertEqual(result.content, '', 'Default render returns Fragment');
            comp.dispose();
        });

        it('_reconcile should be callable (backward compat)', () => {
            const comp = new window.BaseComponent(null, document.createElement('div'));
            assert(typeof comp._reconcile === 'function', '_reconcile exists');
            comp.dispose();
        });
    });

    describe('SPPStore Backward Compatibility', () => {
        it('should work as class with get/set/subscribe', () => {
            const store = new window.SPPStore({ count: 0 });
            let received = null;
            store.subscribe(state => { received = state; });
            store.set({ count: 42 });
            assertEqual(received.count, 42, 'Subscriber receives updated state');
            assertEqual(store.get().count, 42, 'get() returns updated state');
        });
    });

    describe('SPPUX.createStore', () => {
        it('should create a Proxy store with subscribe', () => {
            const store = window.SPPUX.createStore({ x: 1 });
            let notified = false;
            store.subscribe(() => { notified = true; });
            store.x = 2;
            assertEqual(notified, true, 'Subscribe callback fired');
            assertEqual(store.x, 2, 'Value updated through proxy');
        });
    });

    describe('spp-model two-way binding support', () => {
        it('should set up model binding on render', () => {
            const container = document.createElement('div');
            class TestComp extends window.BaseComponent {
                onInit() { this.state = { name: 'hello' }; }
                render() {
                    return window.html`<input spp-model="name" value="${this.state.name}">`;
                }
            }
            const comp = new TestComp(null, container);
            comp.onInit();
            comp.forceUpdate();
            
            const input = container.querySelector('input');
            if (input) {
                assert(input.value === 'hello' || input.getAttribute('value') === 'hello', 'spp-model binds value');
            }
            comp.dispose();
        });
    });
} else {
    console.log('⚠️  Backward compatibility tests require a browser DOM environment. Skipping.');
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
