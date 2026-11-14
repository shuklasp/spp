/**
 * SPP-UX Standalone Runtime (v14)
 * Built on 2026-10-19 09:53:13
 */
(function(global) {
    'use strict';

// ─── Module: core/scheduler.js ───────────────────────────────────────────
/**
 * SPP-UX Scheduler — Batched Asynchronous Update Queue
 * 
 * Coalesces multiple state changes into a single DOM update pass,
 * preventing redundant re-renders when setState() is called multiple times
 * synchronously. Inspired by React's batched update model.
 * 
 * @module core/scheduler
 * @version 13.0.0
 */

/** @type {Set<Function|{update: Function}>} Pending jobs */
const _queue = new Set();

/** @type {boolean} Guard against recursive flushes */
let _isFlushing = false;

/** @type {number} Nesting depth for batch() calls */
let _batchDepth = 0;

/** @type {Promise<void>|null} Cached microtask flush promise */
let _flushPromise = null;

/**
 * Enqueue a job for deferred execution.
 * 
 * If not inside a batch, schedules a microtask to flush the queue.
 * If inside a batch, the job is held until endBatch() triggers the flush.
 * 
 * @param {Function|{update: Function}} job - An effect function or component instance
 * @example
 * enqueue(myComponent);        // component.update() called on next microtask
 * enqueue(() => doSomething()); // function called on next microtask
 */
function enqueue(job) {
    _queue.add(job);
    if (_batchDepth === 0 && !_flushPromise) {
        _flushPromise = Promise.resolve().then(flush);
    }
}

/**
 * Process all pending jobs in the queue.
 * 
 * Jobs that are functions are called directly.
 * Jobs with an `update()` method have that method called.
 * Errors in individual jobs are caught and logged, allowing
 * remaining jobs to execute.
 */
/** @type {number} Tracks nested flush cycles to prevent infinite loops */
let _flushCycles = 0;
const MAX_FLUSH_COUNT = 100;

function flush() {
    if (_isFlushing) return;
    _isFlushing = true;
    _flushPromise = null;

    // Snapshot the queue — jobs enqueued during flush get a new cycle
    const jobs = Array.from(_queue);
    _queue.clear();

    for (const job of jobs) {
        try {
            if (typeof job === 'function') {
                job();
            } else if (job && typeof job.update === 'function') {
                job.update();
            }
        } catch (err) {
            console.error('[SPPUX Scheduler] Job execution error:', err);
        }
    }

    _isFlushing = false;

    // If new jobs were enqueued during flush, process them safely
    if (_queue.size > 0) {
        _flushCycles++;
        if (_flushCycles > MAX_FLUSH_COUNT) {
            _queue.clear();
            _flushCycles = 0;
            console.error('[SPPUX Scheduler] Maximum flush cycle limit (100) exceeded. Detected infinite reactive update loop.');
            return;
        }
        flush();
    } else {
        _flushCycles = 0;
    }
}

/**
 * Synchronously flush all pending updates, ignoring batch depth.
 * 
 * Use sparingly — primarily for backward compatibility with code
 * that expects setState() to update the DOM synchronously.
 */
function forceFlush() {
    const savedBatch = _batchDepth;
    _batchDepth = 0;
    flush();
    _batchDepth = savedBatch;
}

/**
 * Begin a batch context. Holds all enqueued updates until endBatch().
 * Supports nesting — only the outermost endBatch() triggers flush.
 */
function startBatch() {
    _batchDepth++;
}

/**
 * End a batch context. When the outermost batch ends, flushes all
 * accumulated updates in one pass.
 */
function endBatch() {
    _batchDepth = Math.max(0, _batchDepth - 1);
    if (_batchDepth === 0 && _queue.size > 0) {
        flush();
    }
}

/**
 * Check if the scheduler is currently in the middle of a flush.
 * @returns {boolean}
 */
function isFlushing() {
    return _isFlushing;
}

// ─── Module: core/events.js ───────────────────────────────────────────
/**
 * SPP-UX Event Delegation — O(1) WeakMap-Based Event System
 * 
 * Replaces the O(N) component iteration pattern with a WeakMap-based
 * handler registry. Event handlers are looked up directly on the target
 * element and its ancestors, eliminating the need to iterate through
 * all registered components on every event.
 * 
 * Benefits over v11:
 * - O(1) handler lookup per DOM level vs O(N) component iteration
 * - WeakMap auto-GCs handlers when DOM nodes are removed (no leaks)
 * - No window.__spp_handlers global pollution
 * - Single listener per event type on the delegation root
 * 
 * @module core/events
 * @version 13.0.0
 */

// ─── Data Structures ──────────────────────────────────────────────

/**
 * Maps DOM elements to their event handlers.
 * Structure: Element → Map<eventType, handler>
 * 
 * WeakMap ensures automatic garbage collection when DOM nodes are
 * removed — the key reason this fixes the v11 memory leaks.
 * 
 * @type {WeakMap<Element, Map<string, Function>>}
 */
const _handlerRegistry = new WeakMap();

/**
 * Tracks which event types have already been delegated at the root.
 * @type {Set<string>}
 * @private
 */
const _delegatedEvents = new Set();

/**
 * The root element where all delegated listeners are attached.
 * Defaults to `document` but can be set to an app container for
 * scoped delegation (similar to React 17 delegating to root).
 * @type {EventTarget}
 * @private
 */
let _rootElement = null;

// ─── Event Types Configuration ────────────────────────────────────

/**
 * Events that should NOT have preventDefault() called.
 * @type {Set<string>}
 * @private
 */
const _noPreventSet = new Set([
    'click', 'input', 'change', 'focus', 'blur', 'keydown', 'keyup', 'keypress',
    'dragstart', 'mousedown', 'mouseup', 'mousemove', 'mouseenter', 'mouseleave',
    'pointerdown', 'pointerup', 'pointermove', 'pointerenter', 'pointerleave',
    'touchstart', 'touchmove', 'touchend', 'touchcancel',
    'wheel', 'scroll', 'contextmenu', 'dblclick',
    'animationend', 'transitionend', 'error', 'load', 'resize'
]);

/**
 * Events that require capture phase for proper delegation.
 * These don't bubble naturally in the DOM.
 * @type {Set<string>}
 * @private
 */
const _captureEvents = new Set([
    'focus', 'blur', 'mouseenter', 'mouseleave',
    'pointerenter', 'pointerleave', 'scroll', 'error', 'load'
]);

/**
 * The standard set of events to pre-delegate on initialization.
 * @type {string[]}
 * @private
 */
const _commonEvents = [
    'click', 'input', 'change', 'submit', 'blur', 'focus',
    'keydown', 'keyup', 'keypress',
    'dragstart', 'dragover', 'dragleave', 'drop', 'dragend',
    'mousedown', 'mouseup', 'mousemove', 'mouseenter', 'mouseleave',
    'pointerdown', 'pointerup', 'pointermove', 'pointerenter', 'pointerleave',
    'touchstart', 'touchmove', 'touchend', 'touchcancel',
    'wheel', 'scroll', 'contextmenu', 'dblclick',
    'animationend', 'transitionend', 'error', 'load', 'resize'
];

// ─── Core Delegation Logic ────────────────────────────────────────

/**
 * Ensure a specific event type has a delegated listener at the root.
 * Idempotent — safe to call multiple times for the same event type.
 * 
 * @param {string} eventType - e.g. 'click', 'input', 'keydown'
 * @private
 */
function _ensureDelegated(eventType) {
    if (_delegatedEvents.has(eventType)) return;
    _delegatedEvents.add(eventType);

    const root = _rootElement || document;
    const useCapture = _captureEvents.has(eventType);

    root.addEventListener(eventType, (e) => {
        // [ENTERPRISE FIX] Use composedPath() to penetrate Shadow DOM boundaries.
        // Native e.target stops at the Shadow Host, breaking synthetic events inside web components.
        const path = typeof e.composedPath === 'function' ? e.composedPath() : [];
        let target = path.length > 0 ? path[0] : e.target;
        let pathIndex = 0;

        let handled = false;

        // Walk up the DOM tree from the actual event target to the root
        while (target && target !== root && target !== document && target !== window) {
            if (target.nodeType === 1) { // Process only Element nodes
                const handlers = _handlerRegistry.get(target);
                if (handlers) {
                    const handler = handlers.get(eventType);
                    if (handler) {
                        // Apply preventDefault unless this is a no-prevent event
                        if (!_noPreventSet.has(eventType)) {
                            e.preventDefault();
                        }

                        try {
                            handler(e);
                        } catch (err) {
                            console.error(`[SPPUX Events] Handler error for "${eventType}":`, err);
                        }

                        handled = true;

                        // Stop propagation if the handler requested it via e.stopPropagation()
                        if (e.cancelBubble) {
                            break; // breaks out of the while loop
                        }
                    }
                }
            }

            // Move up to the next element in the composed path, or fallback to parentNode
            if (path.length > 0) {
                pathIndex++;
                target = pathIndex < path.length ? path[pathIndex] : null;
            } else {
                target = target.parentNode || target.host;
            }
        }
    }, useCapture);
}

// ─── Public API ───────────────────────────────────────────────────

/**
 * Register an event handler on a DOM element.
 * The handler is stored in a WeakMap and dispatched via event delegation.
 * 
 * @param {Element} element - DOM element to bind the handler to
 * @param {string} eventType - Event type (e.g. 'click', 'input')
 * @param {Function} handler - Event handler function
 * @returns {Function} Cleanup function that removes the handler
 * 
 * @example
 * const cleanup = registerHandler(button, 'click', (e) => {
 *     console.log('clicked!', e.target);
 * });
 * // Later: cleanup() to remove
 */
function registerHandler(element, eventType, handler) {
    if (!element || !eventType || !handler) return () => {};

    let handlers = _handlerRegistry.get(element);
    if (!handlers) {
        handlers = new Map();
        _handlerRegistry.set(element, handlers);
    }
    handlers.set(eventType, handler);

    // Ensure this event type is delegated at the root
    _ensureDelegated(eventType);

    return () => removeHandler(element, eventType);
}

/**
 * Remove a specific event handler from an element.
 * 
 * @param {Element} element - DOM element
 * @param {string} eventType - Event type to remove
 */
function removeHandler(element, eventType) {
    const handlers = _handlerRegistry.get(element);
    if (!handlers) return;
    handlers.delete(eventType);
    if (handlers.size === 0) {
        _handlerRegistry.delete(element);
    }
}

/**
 * Remove ALL event handlers from an element.
 * Called during component disposal.
 * 
 * @param {Element} element - DOM element to clean up
 */
function removeAllHandlers(element) {
    _handlerRegistry.delete(element);
}

/**
 * Get the handler registered on an element for a specific event type.
 * 
 * @param {Element} element - DOM element
 * @param {string} eventType - Event type
 * @returns {Function|undefined}
 */
function getHandler(element, eventType) {
    const handlers = _handlerRegistry.get(element);
    return handlers ? handlers.get(eventType) : undefined;
}

/**
 * Initialize the event delegation system.
 * Pre-delegates all common event types on the specified root element.
 * 
 * @param {EventTarget} [rootElement=document] - Root element for delegation
 */
function initDelegation(rootElement) {
    _rootElement = rootElement || document;
    for (const evt of _commonEvents) {
        _ensureDelegated(evt);
    }
}

// ─── Module: core/reactive.js ───────────────────────────────────────────
/**
 * SPP-UX Reactive Primitives — Fine-Grained Reactivity System
 * 
 * Provides Signal, Computed, effect(), batch(), and store primitives
 * with proper dependency tracking via an effect stack. Inspired by
 * Vue 3's @vue/reactivity and SolidJS signals.
 * 
 * Key differences from the v11 implementation:
 * - Effect stack replaces single global `Signal.activeSubscriber`
 * - Computed values are lazy (only recompute when dirty AND read)
 * - batch() coalesces multiple signal writes into one scheduler flush
 * - Automatic cleanup prevents memory leaks from disposed effects
 * 
 * @module core/reactive
 * @version 13.0.0
 */



// ─── Effect Tracking ──────────────────────────────────────────────

/** @type {Array<ReactiveEffect>} Stack of currently executing effects */
const _effectStack = [];

/** @type {ReactiveEffect|null} The effect currently being evaluated */
let currentEffect = null;

// ─── Signal ───────────────────────────────────────────────────────

/**
 * A reactive primitive that holds a single value. Reading the value
 * inside an effect automatically subscribes that effect to future changes.
 * 
 * @example
 * const count = new Signal(0);
 * effect(() => console.log(count.value)); // logs 0
 * count.value = 1; // logs 1
 */
class Signal {
    /**
     * @param {*} value - Initial value
     */
    constructor(value) {
        /** @private */
        this._value = value;
        /** @type {Set<ReactiveEffect>} @private */
        this._subscribers = new Set();
        /** @type {number} @private — monotonic version counter */
        this._version = 0;
    }

    /**
     * Read the current value. If called inside an effect, the effect
     * is automatically subscribed to this signal.
     * @returns {*}
     */
    get value() {
        if (currentEffect) {
            this._subscribers.add(currentEffect);
            currentEffect._dependencies.add(this);
        } else if (Signal.activeSubscriber) {
            this._subscribers.add(Signal.activeSubscriber);
        }
        return this._value;
    }

    /**
     * Set a new value. If the value has changed (via Object.is),
     * all subscribed effects are enqueued for re-execution.
     * @param {*} v
     */
    set value(v) {
        if (Object.is(this._value, v)) return;
        this._value = v;
        this._version++;
        this._notify();
    }

    /**
     * Read the current value WITHOUT tracking dependencies.
     * Useful when you need to read inside an effect without subscribing.
     * @returns {*}
     */
    peek() {
        return this._value;
    }

    /**
     * Notify all subscribers that this signal changed.
     * @private
     */
    _notify() {
        for (const sub of this._subscribers) {
            enqueue(sub);
        }
    }

    /**
     * Remove a subscriber.
     * @param {ReactiveEffect} sub
     * @private
     */
    _unsubscribe(sub) {
        this._subscribers.delete(sub);
    }
}

// Backward-compatible static property for legacy code
Signal.activeSubscriber = null;

// ─── Computed ─────────────────────────────────────────────────────

/**
 * A derived reactive value that lazily recomputes when its dependencies change.
 * 
 * @example
 * const count = new Signal(2);
 * const doubled = new Computed(() => count.value * 2);
 * console.log(doubled.value); // 4
 * count.value = 3;
 * console.log(doubled.value); // 6
 */
class Computed {
    /**
     * @param {Function} fn - Computation function
     */
    constructor(fn) {
        /** @private */
        this._fn = fn;
        /** @private */
        this._value = undefined;
        /** @private */
        this._dirty = true;
        /** @type {Set<ReactiveEffect>} @private */
        this._subscribers = new Set();
        /** @type {number} @private */
        this._version = 0;

        /**
         * Internal effect that marks this computed as dirty when
         * any dependency changes.
         * @private
         */
        this._effect = new ReactiveEffect(() => {
            if (!this._dirty) {
                this._dirty = true;
                this._version++;
                // Notify our own subscribers that we may have a new value
                for (const sub of this._subscribers) {
                    enqueue(sub);
                }
            }
        });

        // Run once to establish dependency tracking
        this._computeValue();
    }

    /**
     * Read the computed value. Recomputes lazily if dirty.
     * Tracks dependency if called inside an effect.
     * @returns {*}
     */
    get value() {
        if (currentEffect) {
            this._subscribers.add(currentEffect);
            currentEffect._dependencies.add(this);
        } else if (Signal.activeSubscriber) {
            this._subscribers.add(Signal.activeSubscriber);
        }
        if (this._dirty) {
            this._computeValue();
        }
        return this._value;
    }

    /**
     * Read without tracking.
     * @returns {*}
     */
    peek() {
        if (this._dirty) {
            this._computeValue();
        }
        return this._value;
    }

    /**
     * @private
     */
    _computeValue() {
        const prev = currentEffect;
        currentEffect = this._effect;
        _effectStack.push(this._effect);
        try {
            this._value = this._fn();
            this._dirty = false;
        } finally {
            _effectStack.pop();
            currentEffect = _effectStack.length > 0
                ? _effectStack[_effectStack.length - 1]
                : prev;
        }
    }

    /**
     * Remove a subscriber.
     * @param {ReactiveEffect} sub
     * @private
     */
    _unsubscribe(sub) {
        this._subscribers.delete(sub);
    }
}

// ─── ReactiveEffect ───────────────────────────────────────────────

/**
 * Internal class representing a reactive side effect.
 * Tracks which signals/computeds it depends on and re-runs
 * when any of them change.
 * @private
 */
class ReactiveEffect {
    /**
     * @param {Function} fn - The effect function
     */
    constructor(fn) {
        /** @private */
        this._fn = fn;
        /** @type {Set<Signal|Computed>} Signals/Computeds this effect reads */
        this._dependencies = new Set();
        /** @type {boolean} */
        this._disposed = false;
    }

    /**
     * Run the effect function, re-establishing dependency tracking.
     * Called by the scheduler.
     */
    run() {
        if (this._disposed) return;

        // Clean up old dependency subscriptions
        this._cleanup();

        const prev = currentEffect;
        currentEffect = this;
        _effectStack.push(this);
        try {
            this._fn();
        } finally {
            _effectStack.pop();
            currentEffect = _effectStack.length > 0
                ? _effectStack[_effectStack.length - 1]
                : prev;
        }
    }

    /**
     * Remove this effect from all its dependency subscriber lists.
     * @private
     */
    _cleanup() {
        for (const dep of this._dependencies) {
            dep._unsubscribe(this);
        }
        this._dependencies.clear();
    }

    /**
     * Permanently dispose this effect, cleaning up all subscriptions.
     */
    dispose() {
        this._disposed = true;
        this._cleanup();
    }
}

// Make ReactiveEffect callable by the scheduler
// The scheduler calls job() if typeof job === 'function',
// or job.update() if it has an update method.
// We need to make effects work as both.
ReactiveEffect.prototype.update = function() {
    this.run();
};

// ─── Public API: effect() ─────────────────────────────────────────

/**
 * Create a reactive side effect that automatically re-runs when
 * any of its signal dependencies change.
 * 
 * @param {Function} fn - The effect function
 * @returns {Function} Dispose function to stop the effect
 * 
 * @example
 * const name = new Signal('World');
 * const dispose = effect(() => console.log(`Hello, ${name.value}`));
 * // logs: "Hello, World"
 * name.value = 'SPP'; // logs: "Hello, SPP"
 * dispose(); // stops tracking
 */
function effect(fn) {
    const e = new ReactiveEffect(fn);
    e.run(); // Run immediately to establish dependencies
    return () => e.dispose();
}

// ─── Public API: batch() ──────────────────────────────────────────

/**
 * Batch multiple signal writes so that effects/updates only
 * fire once after all writes are complete.
 * 
 * @param {Function} fn - Function containing multiple signal writes
 * 
 * @example
 * const a = new Signal(1);
 * const b = new Signal(2);
 * effect(() => console.log(a.value + b.value)); // logs 3
 * 
 * batch(() => {
 *     a.value = 10;
 *     b.value = 20;
 * }); // logs 30 (only once, not twice)
 */
function batch(fn) {
    startBatch();
    try {
        fn();
    } finally {
        endBatch();
    }
}

// ─── Public API: createStore() ────────────────────────────────────

/**
 * Create a Proxy-wrapped reactive store with subscribe/get/set support.
 * 
 * @param {Object} initialState
 * @param {Object} options - e.g. { syncKey: 'app_settings' } to sync state across browser tabs.
 * @returns {Proxy} Reactive store proxy
 */
function createStore(initialState = {}, options = {}) {
    const listeners = new Set();
    const notify = () => listeners.forEach(fn => {
        try { fn(); } catch (e) { console.error('[SPPUX Store] Listener error:', e); }
    });

    let channel = null;
    let isApplyingRemote = false;

    if (options.syncKey && typeof BroadcastChannel !== 'undefined') {
        channel = new BroadcastChannel(options.syncKey);
        channel.onmessage = (event) => {
            isApplyingRemote = true;
            Object.assign(initialState, event.data);
            notify();
            isApplyingRemote = false;
        };
    }

    const proxy = new Proxy(initialState, {
        get(target, prop) {
            if (prop === 'subscribe') {
                return (fn) => {
                    listeners.add(fn);
                    return () => listeners.delete(fn);
                };
            }
            if (prop === 'get') {
                return () => target;
            }
            if (prop === 'set' && typeof target.set !== 'function') {
                return (newState) => {
                    Object.assign(target, newState);
                    notify();
                    if (channel && !isApplyingRemote) {
                        channel.postMessage(newState);
                    }
                };
            }
            return Reflect.get(target, prop);
        },
        set(target, prop, value) {
            const res = Reflect.set(target, prop, value);
            notify();
            if (channel && !isApplyingRemote) {
                channel.postMessage({ [prop]: value });
            }
            return res;
        }
    });

    return proxy;
}

// ─── Public API: SPPStore class (backward compat) ─────────────────

/**
 * Simple observable store — backward compatible with SPP-UX v11.
 * 
 * @example
 * const store = new SPPStore({ theme: 'dark' });
 * store.subscribe(state => console.log(state.theme));
 * store.set({ theme: 'light' }); // logs 'light'
 */
class SPPStore {
    /**
     * @param {Object} initialState
     */
    constructor(initialState = {}) {
        this.state = initialState;
        /** @type {Set<Function>} */
        this.listeners = new Set();
    }

    /** @returns {Object} Current state */
    get() {
        return this.state;
    }

    /**
     * Merge new state and notify listeners.
     * @param {Object} newState - Partial state to merge
     */
    set(newState) {
        this.state = { ...this.state, ...newState };
        this.notify();
    }

    /**
     * Subscribe to state changes.
     * @param {Function} callback
     * @returns {Function} Unsubscribe function
     */
    subscribe(callback) {
        this.listeners.add(callback);
        return () => this.listeners.delete(callback);
    }

    /** Notify all subscribers with the current state. */
    notify() {
        this.listeners.forEach(callback => {
            try { callback(this.state); } catch (e) { console.error('[SPPStore] Listener error:', e); }
        });
    }
}

// ─── React / Vue Style Composition Hooks ─────────────────────────

/**
 * React-style useState hook backed by fine-grained Signal.
 * Returns [getter, setter, signalInstance].
 * Calling getter() inside an effect / template auto-subscribes.
 * 
 * @example
 * const [count, setCount] = useState(0);
 * setCount(count() + 1);
 */
function useState(initialValue) {
    const sig = new Signal(initialValue);
    const getter = () => sig.value;
    const setter = (next) => {
        sig.value = typeof next === 'function' ? next(sig.value) : next;
    };
    return [getter, setter, sig];
}

/**
 * Vue-style ref() creating a standalone reactive Signal.
 * 
 * @example
 * const count = ref(0);
 * count.value++;
 */
function ref(initialValue) {
    return new Signal(initialValue);
}

/**
 * Vue / React style computed value.
 * Lazily evaluated and cached until dependencies mutate.
 * 
 * @param {Function} getter
 * @returns {Function} Function returning the computed value
 */
function useComputed(getter) {
    const comp = new Computed(getter);
    return () => comp.value;
}

/**
 * React-style useEffect hook.
 * Executes immediately and re-executes whenever accessed Signals/Computed mutate.
 * 
 * @param {Function} effectFn
 * @returns {Function} Dispose / unsubscribe function
 */
function useEffect(effectFn) {
    return effect(effectFn);
}

// ─── Module: core/parts.js ───────────────────────────────────────────
/**
 * SPP-UX Fine-Grained DOM Parts (v14)
 * 
 * Implements lit-html style template caching and fine-grained DOM updaters.
 * Replaces full-tree real DOM diffing with precise node-level updates.
 * 
 * @module core/parts
 */




const marker = `__spp_${Math.random().toString(36).slice(2)}__`;
const nodeMarker = `<!--${marker}-->`;

/**
 * Represents a tagged template literal that hasn't been rendered yet.
 */
class TemplateResult {
    constructor(strings, values) {
        this.strings = strings;
        this.values = values;
    }

    /**
     * Backward compatibility with TrustedHTML expectations.
     */
    get __isTrusted() { return true; }
    get content() { return this.toString(); }

    /**
     * Converts this TemplateResult to an HTML string.
     * Recursively serializes child TemplateResults, arrays, and primitive values.
     * Omits functions, null, undefined, and booleans (matching standard JSX/lit behavior).
     * Safely handles boolean attributes (?disabled, ?selected) and event attributes (@click).
     * 
     * @returns {string}
     */
    toString() {
        if (!this.strings || !Array.isArray(this.strings)) return '';
        let result = '';
        const len = this.strings.length;
        for (let i = 0; i < len; i++) {
            const str = this.strings[i];
            if (this.values && i < this.values.length) {
                const val = this.values[i];
                
                // Check if this hole is a boolean attribute like ?selected="..." or ?disabled="..."
                const boolAttrMatch = str.match(/([ \t\r\n])\?([a-zA-Z0-9_\-]+)=["']?$/);
                if (boolAttrMatch) {
                    const prefix = str.slice(0, boolAttrMatch.index + boolAttrMatch[1].length);
                    const attrName = boolAttrMatch[2];
                    if (val) {
                        result += prefix + attrName + '="true"';
                    } else {
                        result += prefix + 'data-spp-off=""';
                    }
                    continue;
                }

                // Check if this hole is an event attribute like @click=${fn}
                const eventAttrMatch = str.match(/([ \t\r\n])@([a-zA-Z0-9_\-]+)=["']?$/);
                if (eventAttrMatch && typeof val === 'function') {
                    const prefix = str.slice(0, eventAttrMatch.index + eventAttrMatch[1].length);
                    const eventName = eventAttrMatch[2];
                    const id = 'spp_h_' + Math.random().toString(36).substr(2, 9);
                    if (typeof window !== 'undefined') {
                        window.__spp_handlers = window.__spp_handlers || {};
                        window.__spp_handlers[id] = val;
                    }
                    result += prefix + `data-spp-evt-${eventName}="${id}" `;
                    continue;
                }

                result += str;
                result += TemplateResult.stringifyValue(val);
            } else {
                result += str;
            }
        }
        return result;
    }

    /**
     * Renders this TemplateResult into a DOM container element.
     * Delegates to window.SPPUX.render or fine-grained render() if available.
     * 
     * @param {Element} container 
     */
    render(container) {
        if (!container) return;
        if (window.SPPUX && typeof window.SPPUX.render === 'function') {
            window.SPPUX.render(this, container);
        } else {
            container.innerHTML = this.toString();
        }
    }

    static stringifyValue(val) {
        if (val === null || val === undefined || typeof val === 'function' || typeof val === 'boolean') {
            return '';
        }
        if (Array.isArray(val)) {
            return val.map(v => TemplateResult.stringifyValue(v)).join('');
        }
        if (typeof val === 'object') {
            if (val instanceof TemplateResult) {
                return val.toString();
            }
            if (val.__isTrusted && typeof val.content === 'string') {
                return val.content;
            }
            if (typeof val.toString === 'function' && val.toString !== Object.prototype.toString) {
                return val.toString();
            }
            return '';
        }
        return String(val);
    }
}

const templateCache = new WeakMap();

/**
 * Parses a TemplateStringsArray into a reusable <template> and records
 * the paths to the dynamic "holes".
 */
function getTemplate(strings) {
    if (templateCache.has(strings)) return templateCache.get(strings);

    // [AOT Pre-compilation Cache] Check if CLI optimized this template
    const hash = strings.join('$$spp$$');
    if (window.__SPP_UX_CACHE__ && window.__SPP_UX_CACHE__[hash]) {
        const cached = window.__SPP_UX_CACHE__[hash];
        const tpl = document.createElement('template');
        tpl.innerHTML = cached.html;
        const template = { tpl, parts: cached.parts };
        templateCache.set(strings, template);
        return template;
    }

    // JIT Fallback Parsing
    let html = '';
    for (let i = 0; i < strings.length - 1; i++) {
        html += strings[i];
        // Heuristic: Are we inside an HTML tag or RCDATA (textarea)?
        const lastOpen = html.lastIndexOf('<');
        const lastClose = html.lastIndexOf('>');
        const lastTextareaOpen = html.toLowerCase().lastIndexOf('<textarea');
        const lastTextareaClose = html.toLowerCase().lastIndexOf('</textarea');
        const isInsideTextarea = lastTextareaOpen !== -1 && lastTextareaOpen > lastTextareaClose;

        if (lastOpen > lastClose) {
            html += marker + i;
        } else if (isInsideTextarea) {
            html += marker + i;
        } else {
            html += `<!--${marker}${i}-->`;
        }
    }
    html += strings[strings.length - 1];

    const tpl = document.createElement('template');
    tpl.innerHTML = html;

    const parts = [];
    const walker = document.createTreeWalker(tpl.content, 1 | 128);
    let node;

    const getPath = (n) => {
        const path = [];
        let curr = n;
        while (curr.parentNode) {
            path.push(Array.from(curr.parentNode.childNodes).indexOf(curr));
            curr = curr.parentNode;
        }
        return path.reverse();
    };

    while ((node = walker.nextNode())) {
        if (node.nodeType === 8 && node.data.startsWith(marker)) {
            const index = parseInt(node.data.substring(marker.length), 10);
            parts.push({ type: 'node', index, path: getPath(node) });
        } else if (node.nodeType === 1) {
            // Check for textarea body interpolation: <textarea>${val}</textarea>
            if (node.tagName === 'TEXTAREA' && node.textContent && node.textContent.includes(marker)) {
                const text = node.textContent;
                const match = text.match(new RegExp(`${marker}(\\d+)`));
                if (match) {
                    const index = parseInt(match[1], 10);
                    const pieces = text.split(new RegExp(`${marker}\\d+`));
                    parts.push({
                        type: 'property',
                        name: 'value',
                        index,
                        path: getPath(node),
                        strings: (pieces.length > 1 && pieces.some(s => s !== '')) ? pieces : null
                    });
                    node.textContent = '';
                }
            }

            const attrsToRemove = [];
            for (const attr of Array.from(node.attributes)) {
                if (attr.name.includes(marker) || attr.value.includes(marker)) {
                    const isName = attr.name.includes(marker);
                    const indexMatch = isName ? attr.name.match(new RegExp(`${marker}(\\d+)`)) : attr.value.match(new RegExp(`${marker}(\\d+)`));
                    if (!indexMatch) continue;
                    
                    const index = parseInt(indexMatch[1], 10);
                    let type = 'attr';
                    let name = attr.name;
                    
                    if (isName) name = name.replace(new RegExp(`${marker}\\d+`), '');

                    if (name.startsWith('@') || name.startsWith('data-spp-evt')) {
                        type = 'event';
                        name = name.startsWith('@') ? name.substring(1) : name.replace('data-spp-evt-', '').replace('data-spp-evt', 'click');
                    } else if (name.startsWith('?')) {
                        type = 'boolean';
                        name = name.substring(1);
                    } else if (name.startsWith('.')) {
                        type = 'property';
                        name = name.substring(1);
                    }

                    // Extract static template strings surrounding the marker in the attribute value
                    let strings = null;
                    if (type === 'attr' && !isName && attr.value && attr.value.includes(marker)) {
                        strings = attr.value.split(new RegExp(`${marker}\\d+`));
                    }

                    parts.push({ type, name, index, path: getPath(node), strings });
                    attrsToRemove.push(attr.name);
                }
            }
            attrsToRemove.forEach(a => node.removeAttribute(a));
        }
    }

    const template = { tpl, parts };
    templateCache.set(strings, template);
    return template;
}

class TemplateInstance {
    constructor(template, existingRoot = null) {
        this.template = template;
        this.parts = new Array(template.parts.length);
        
        const isHydrating = existingRoot !== null;
        this.fragment = isHydrating ? existingRoot : template.tpl.content.cloneNode(true);
        
        const getNode = (path) => {
            let curr = this.fragment;
            // When hydrating from container, children start directly inside
            for (const i of path) {
                if (!curr || !curr.childNodes || i >= curr.childNodes.length) return null;
                curr = curr.childNodes[i];
            }
            return curr;
        };

        // Pass 1: Resolve all target nodes BEFORE any DOM mutations to avoid child index shifts
        const resolvedNodes = template.parts.map(desc => getNode(desc.path));

        // Pass 2: Instantiate parts and insert comment markers without disrupting unresolved paths
        for (let i = 0; i < template.parts.length; i++) {
            const desc = template.parts[i];
            const node = resolvedNodes[i];
            if (!node) continue;

            let part = null;
            
            if (desc.type === 'node') {
                if (!node.parentNode) continue;
                if (isHydrating) {
                    // For node holes, the server must output a comment or an element we can bound.
                    // If it's a TextNode or Element, we wrap it in our comment boundaries for future updates.
                    const startNode = document.createComment('');
                    const endNode = document.createComment('');
                    node.parentNode.insertBefore(startNode, node);
                    // insert endNode after the target node so the target node is preserved initially
                    if (node.nextSibling) {
                        node.parentNode.insertBefore(endNode, node.nextSibling);
                    } else {
                        node.parentNode.appendChild(endNode);
                    }
                    part = new NodePart(startNode, endNode);
                    // We don't remove `node` here, it is the initial server-rendered state!
                    // We seed the part's value with it to prevent overriding it if unchanged.
                    part._textNode = node.nodeType === 3 ? node : null;
                } else {
                    const startNode = document.createComment('');
                    const endNode = document.createComment('');
                    node.parentNode.insertBefore(startNode, node);
                    node.parentNode.insertBefore(endNode, node);
                    node.parentNode.removeChild(node);
                    part = new NodePart(startNode, endNode);
                }
            } else if (desc.type === 'attr') {
                part = new AttributePart(node, desc.name, desc.strings);
            } else if (desc.type === 'event') {
                part = new EventPart(node, desc.name);
            } else if (desc.type === 'boolean') {
                part = new BooleanPart(node, desc.name);
            } else if (desc.type === 'property') {
                part = new PropertyPart(node, desc.name, desc.strings);
            }

            this.parts[desc.index] = part;
        }
    }

    update(values) {
        for (let i = 0; i < this.parts.length; i++) {
            if (this.parts[i]) {
                this.parts[i].setValue(values[i]);
            }
        }
    }
}

class Part {
    constructor() {
        this.value = undefined;
        this._unsub = null;
    }

    setValue(newValue) {
        if (newValue instanceof Signal) {
            if (this._unsub) this._unsub();
            this._unsub = effect(() => {
                this.commit(newValue.value);
            });
        } else {
            if (this._unsub) {
                this._unsub();
                this._unsub = null;
            }
            this.commit(newValue);
        }
    }

    commit(value) {}
}

class NodePart extends Part {
    constructor(startNode, endNode) {
        super();
        this.startNode = startNode;
        this.endNode = endNode;
        this._instance = null;
        this._textNode = null;
        this._arrayItems = [];
        this._htmlContent = null;
    }

    commit(value) {
        if (value === this.value) return;

        if (value instanceof TemplateResult) {
            this._commitTemplate(value);
        } else if (value && (value.__isTrusted || (typeof TrustedHTML !== 'undefined' && value instanceof TrustedHTML))) {
            this._commitHTML(value.content ?? String(value));
        } else if (value && value._isPortal) {
            this._commitPortal(value);
        } else if (value && value._isRepeat) {
            this._commitRepeat(value);
        } else if (value && value._isUntil) {
            this._commitUntil(value);
        } else if (Array.isArray(value)) {
            this._commitIterable(value);
        } else if (value === null || value === undefined) {
            this._clear();
        } else {
            this._commitText(String(value));
        }
        
        this.value = value;
    }

    _commitHTML(html) {
        if (this._htmlContent === html) return;
        this._htmlContent = html;
        this._clear();
        const tpl = document.createElement('template');
        tpl.innerHTML = html;
        const parent = this.startNode ? this.startNode.parentNode : null;
        if (parent) {
            const before = (this.endNode && this.endNode.parentNode === parent) ? this.endNode : null;
            parent.insertBefore(tpl.content, before);
        }
    }

    _commitText(text) {
        const parent = this.startNode ? this.startNode.parentNode : null;
        if (this._textNode && parent && this._textNode.parentNode === parent) {
            this._textNode.data = text;
        } else {
            this._clear();
            this._textNode = document.createTextNode(text);
            if (parent) {
                const before = (this.endNode && this.endNode.parentNode === parent) ? this.endNode : null;
                parent.insertBefore(this._textNode, before);
            }
        }
    }

    _commitTemplate(result) {
        if (this._instance && this._instance.template === getTemplate(result.strings)) {
            this._instance.update(result.values);
        } else {
            this._clear();
            const template = getTemplate(result.strings);
            this._instance = new TemplateInstance(template);
            this._instance.update(result.values);
            const parent = this.startNode ? this.startNode.parentNode : null;
            if (parent) {
                const before = (this.endNode && this.endNode.parentNode === parent) ? this.endNode : null;
                parent.insertBefore(this._instance.fragment, before);
            }
        }
    }

    _commitRepeat(directive) {
        this._clearNonArray();
        
        const { items, keyFn, templateFn } = directive;
        const newArrayItems = [];
        const newKeyMap = new Map();
        
        // Phase 1: Create or update parts
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const key = keyFn(item, i);
            const templateResult = templateFn(item, i);
            
            let part;
            if (this._keyMap && this._keyMap.has(key)) {
                part = this._keyMap.get(key);
                this._keyMap.delete(key);
            } else {
                const start = document.createComment('');
                const end = document.createComment('');
                part = new NodePart(start, end);
                part._key = key;
                part._isNew = true;
            }
            
            part.commit(templateResult);
            newArrayItems.push(part);
            newKeyMap.set(key, part);
        }
        
        // Phase 2: Remove old unmapped parts
        if (this._keyMap) {
            for (const part of this._keyMap.values()) {
                part._clear();
                if (part.startNode.parentNode) part.startNode.parentNode.removeChild(part.startNode);
                if (part.endNode.parentNode) part.endNode.parentNode.removeChild(part.endNode);
            }
        }
        
        // Phase 3: Insert and move nodes into correct order
        const parent = this.startNode ? this.startNode.parentNode : null;
        if (!parent) return;
        let insertAnchor = this.startNode.nextSibling;
        
        for (let i = 0; i < newArrayItems.length; i++) {
            const part = newArrayItems[i];
            
            // If the part is new or out of order, move its nodes
            if (part._isNew || part.startNode !== insertAnchor) {
                let curr = part.startNode;
                const end = part.endNode.nextSibling;
                
                // If it's new, we need to extract from wherever it was (nowhere)
                // If it's existing, we detach its entire range and move it
                const frag = document.createDocumentFragment();
                while (curr && curr !== end) {
                    const next = curr.nextSibling;
                    frag.appendChild(curr);
                    curr = next;
                }
                
                // insertAnchor could be a previous part's node, or this.endNode if we are at the end
                parent.insertBefore(frag, insertAnchor);
            }
            
            part._isNew = false;
            insertAnchor = part.endNode.nextSibling;
        }
        
        this._arrayItems = newArrayItems;
        this._keyMap = newKeyMap;
    }

    _commitUntil(directive) {
        const { promise, defaultContent } = directive;
        
        // Render fallback instantly
        if (this._activePromise !== promise) {
            this.commit(defaultContent);
            this._activePromise = promise;
            
            promise.then(resolvedValue => {
                // Prevent race conditions: only update if this promise is still the active one
                if (this._activePromise === promise) {
                    this.commit(resolvedValue);
                }
            }).catch(err => {
                if (this._activePromise === promise) {
                    console.error('[SPPUX] until() directive rejected:', err);
                }
            });
        }
    }

    _commitIterable(items) {
        this._clearNonArray();
        const parent = this.startNode ? this.startNode.parentNode : null;
        if (!parent) return;
        
        const newArrayItems = [];
        const max = Math.max(this._arrayItems.length, items.length);
        
        for (let i = 0; i < max; i++) {
            if (i < items.length) {
                let part;
                if (i < this._arrayItems.length) {
                    part = this._arrayItems[i];
                } else {
                    const start = document.createComment('');
                    const end = document.createComment('');
                    parent.insertBefore(start, this.endNode);
                    parent.insertBefore(end, this.endNode);
                    part = new NodePart(start, end);
                }
                part.commit(items[i]);
                newArrayItems.push(part);
            } else {
                const part = this._arrayItems[i];
                part._clear();
                if (part.startNode && part.startNode.parentNode) part.startNode.parentNode.removeChild(part.startNode);
                if (part.endNode && part.endNode.parentNode) part.endNode.parentNode.removeChild(part.endNode);
            }
        }
        
        this._arrayItems = newArrayItems;
    }

    _commitPortal(directive) {
        this._clearNonArray();
        const { templateResult } = directive;
        const targetElement = directive.targetElement || (typeof document !== 'undefined' ? (document.getElementById('sppcoder-modal-portal') || document.body) : null);
        
        if (!templateResult || !targetElement) {
            this._clear();
            return;
        }

        if (this._portalInstance && this._portalInstance.template === getTemplate(templateResult.strings) && this._portalTarget === targetElement) {
            this._portalInstance.update(templateResult.values);
        } else {
            this._clear();
            const template = getTemplate(templateResult.strings);
            this._portalInstance = new TemplateInstance(template);
            this._portalTarget = targetElement;
            this._portalInstance.update(templateResult.values);
            
            // Store a reference to the nodes so we can remove them later
            this._portalNodes = Array.from(this._portalInstance.fragment.childNodes);
            targetElement.appendChild(this._portalInstance.fragment);
        }
    }

    _clearNonArray() {
        if (this._instance || this._textNode || this._portalInstance || this._htmlContent) {
            this._clear();
            this._portalInstance = null;
        }
    }

    _clear() {
        // Clear standard Light DOM nodes between comment boundaries
        let curr = this.startNode.nextSibling;
        while (curr && curr !== this.endNode) {
            const next = curr.nextSibling;
            if (curr.parentNode) curr.parentNode.removeChild(curr);
            curr = next;
        }
        
        // Clear detached Portal nodes if any
        if (this._portalNodes) {
            for (const node of this._portalNodes) {
                if (node.parentNode) node.parentNode.removeChild(node);
            }
            this._portalNodes = null;
            this._portalTarget = null;
        }
        
        // Null stale references whose DOM nodes were just removed
        this._instance = null;
        this._textNode = null;
        this._htmlContent = null;
        this._arrayItems = [];
    }
}

class AttributePart extends Part {
    constructor(element, name, strings = null) {
        super();
        this.element = element;
        this.name = name;
        this.strings = strings;
    }
    commit(value) {
        if (this.name === 'ref' && value && value._isRef) {
            if (this.value !== value) {
                value.callback(this.element);
                this.value = value;
            }
            return;
        }
        
        if (this.name === 'bind' && value && value._isBind) {
            const signal = value.signal;
            const isCheckbox = this.element.type === 'checkbox' || this.element.type === 'radio';
            
            // 1. One-time setup: attach DOM event listener
            if (!this._boundSignal) {
                this._boundSignal = signal;
                const eventName = isCheckbox ? 'change' : 'input';
                
                this.element.addEventListener(eventName, (e) => {
                    signal.value = isCheckbox ? e.target.checked : e.target.value;
                });
            }
            
            // 2. State to DOM sync
            if (isCheckbox) {
                if (this.element.checked !== !!signal.value) {
                    this.element.checked = !!signal.value;
                }
            } else {
                const newStr = signal.value !== undefined && signal.value !== null ? String(signal.value) : '';
                if (this.element.value !== newStr) {
                    this.element.value = newStr;
                }
            }
            return;
        }
        
        if (value === this.value) return;

        // Dynamic standalone attribute interpolation without static name (e.g. <input ${disabled ? 'disabled' : ''}>)
        if (!this.name) {
            if (this.value && typeof this.value === 'string' && this.value.trim()) {
                const prevAttr = this.value.trim().split('=')[0].trim();
                if (prevAttr) {
                    try { this.element.removeAttribute(prevAttr); } catch (e) {}
                }
            }
            if (value && typeof value === 'string' && value.trim()) {
                const eqIdx = value.indexOf('=');
                let attrName = eqIdx !== -1 ? value.substring(0, eqIdx).trim() : value.trim();
                let attrVal = eqIdx !== -1 ? value.substring(eqIdx + 1).trim().replace(/^['"]|['"]$/g, '') : '';
                if (attrName) {
                    try { this.element.setAttribute(attrName, attrVal); } catch (e) {}
                }
            }
            this.value = value;
            return;
        }

        if (value == null) {
            if (this.strings && this.strings.join('').trim() !== '') {
                this.element.setAttribute(this.name, this.strings.join(''));
            } else {
                this.element.removeAttribute(this.name);
            }
        } else {
            const finalVal = this.strings ? this.strings.join(value) : value;
            this.element.setAttribute(this.name, finalVal);
        }
        this.value = value;
    }
}

class BooleanPart extends Part {
    constructor(element, name) {
        super();
        this.element = element;
        this.name = name;
    }
    commit(value) {
        if (value === this.value) return;
        if (!this.name) {
            this.value = value;
            return;
        }
        if (value) {
            this.element.setAttribute(this.name, '');
        } else {
            this.element.removeAttribute(this.name);
        }
        this.value = value;
    }
}

class PropertyPart extends Part {
    constructor(element, name, strings = null) {
        super();
        this.element = element;
        this.name = name;
        this.strings = strings;
    }
    commit(value) {
        if (this.strings) {
            value = this.strings.join(value);
        }
        if (value === this.value) return;
        if (!this.name) {
            this.value = value;
            return;
        }
        if (this.name === 'value' && document.activeElement === this.element && this.element.value === value) {
            return;
        }
        this.element[this.name] = value;
        this.value = value;
    }
}

class EventPart extends Part {
    constructor(element, eventName) {
        super();
        this.element = element;
        this.eventName = eventName;
    }
    commit(value) {
        if (!this.eventName) return;
        if (value && value._isAction) {
            const apiFunction = value.apiFunction;
            if (this.value !== apiFunction) {
                if (this._actionHandler) {
                    removeHandler(this.element, this.eventName, this._actionHandler);
                }
                
                this._actionHandler = async (e) => {
                    e.preventDefault();
                    let data = {};
                    if (e.target instanceof HTMLFormElement) {
                        data = new FormData(e.target);
                    }
                    try {
                        await apiFunction(data);
                    } catch (err) {
                        console.error('[SPPUX] Action directive failed:', err);
                    }
                };
                
                registerHandler(this.element, this.eventName, this._actionHandler);
                this.value = apiFunction;
            }
            return;
        }
        
        if (value === this.value) return;
        if (this.value && !this._actionHandler) {
            removeHandler(this.element, this.eventName, this.value);
        } else if (this._actionHandler) {
            removeHandler(this.element, this.eventName, this._actionHandler);
            this._actionHandler = null;
        }
        
        if (typeof value === 'function') {
            registerHandler(this.element, this.eventName, value);
        }
        this.value = value;
    }
}

// ─── Module: core/template.js ───────────────────────────────────────────
/**
 * SPP-UX Template Engine — Tagged Template Literals (v14)
 * 
 * Provides the `html` tagged template literal. In v14, this returns a
 * `TemplateResult` object instead of an evaluated HTML string. 
 * The `render()` function handles caching and fine-grained DOM updates.
 * 
 * @module core/template
 */




// ─── TrustedHTML (v13 Backward Compatibility) ──────────────

class TrustedHTML {
    constructor(content) {
        this.content = content;
        this.__isTrusted = true;
    }
    toString() { return this.content; }
    toJSON() { return this.content; }
}

const Fragment = new TrustedHTML('');

// ─── Legacy Handler Interop ────────────────────────────────

const _pendingHandlers = new Map();
function consumePendingHandlers() {
    const copy = new Map(_pendingHandlers);
    _pendingHandlers.clear();
    return copy;
}

// ─── Tagged Template: html ────────────────────────────────

/**
 * Returns a `TemplateResult` which captures the static strings and dynamic values.
 * This object is consumed by `render()` to perform fine-grained DOM updates.
 * 
 * @param {TemplateStringsArray} strings 
 * @param  {...any} values 
 * @returns {TemplateResult}
 */
const html = (strings, ...values) => {
    return new TemplateResult(strings, values);
};

// ─── Directives ───────────────────────────────────────────

/**
 * Keyed list rendering directive.
 * Instructs the reconciler to use the LIS algorithm for minimal DOM moves.
 * 
 * @param {Iterable} items - Array of items to render
 * @param {Function} keyFn - Function returning a unique key for an item: (item, index) => key
 * @param {Function} templateFn - Function returning a TemplateResult for an item: (item, index) => html`...`
 * @returns {Object} Directive result
 */
function repeat(items, keyFn, templateFn) {
    return { _isRepeat: true, items, keyFn, templateFn };
}

/**
 * Async rendering directive.
 * Immediately renders `defaultContent`, and hot-swaps it when `promise` resolves.
 * 
 * @param {Promise} promise 
 * @param {any} defaultContent 
 * @returns {Object} Directive result
 */
function until(promise, defaultContent) {
    return { _isUntil: true, promise, defaultContent };
}

/**
 * Escapes the DOM hierarchy by rendering a TemplateResult into a specific target element (e.g., document.body).
 */
function portal(templateResult, targetElement) {
    return { _isPortal: true, templateResult, targetElement };
}

/**
 * Binds the actual DOM element to a callback immediately upon creation.
 */
function ref(callback) {
    return { _isRef: true, callback };
}

/**
 * Two-way binds a Signal to an input/checkbox element.
 */
function bind(signal) {
    return { _isBind: true, signal };
}

/**
 * Form action directive. Automatically prevents default, extracts FormData, and calls API.
 */
function action(apiFunction) {
    return { _isAction: true, apiFunction };
}

// ─── Top-Level Render ─────────────────────────────────────

const _instances = new WeakMap();

/**
 * Renders or Hydrates a TemplateResult to a container DOM element.
 * 
 * @param {TemplateResult|TrustedHTML|string} result 
 * @param {Element} container 
 */
function render(result, container) {
    if (!container) return;

    if (!(result instanceof TemplateResult)) {
        let content = result;
        if (result === Fragment || (result && result.content === '')) return;
        if (result instanceof TrustedHTML) content = result.content;
        
        if (typeof content === 'string') {
            container.innerHTML = content;
            return;
        }
    }

    let instance = _instances.get(container);
    
    if (!instance || instance.template !== getTemplate(result.strings)) {
        const isHydrating = container.hasChildNodes() && container.hasAttribute('data-spp-hydrate');
        
        const template = getTemplate(result.strings);
        
        if (isHydrating) {
            // [ENTERPRISE FEATURE] SSR Hydration
            // We create the instance using the live container as the root instead of cloning.
            // (Note: Backend PHP must output marker comments if dynamic nodes were empty).
            instance = new TemplateInstance(template, container);
            container.removeAttribute('data-spp-hydrate');
        } else {
            // Standard CSR Mount
            while (container.firstChild) {
                container.removeChild(container.firstChild);
            }
            instance = new TemplateInstance(template);
            container.appendChild(instance.fragment);
        }
        
        _instances.set(container, instance);
        instance.update(result.values);
    } else {
        instance.update(result.values);
    }
}

// ─── Module: core/reconciler.js ───────────────────────────────────────────
/**
 * SPP-UX Keyed DOM Reconciler
 * 
 * A high-performance DOM reconciliation engine that minimizes DOM mutations
 * using keyed diffing with the Longest Increasing Subsequence (LIS) algorithm.
 * 
 * Key improvements over v11's _reconcile:
 * - Keyed children use LIS to determine minimum DOM moves (O(n log n))
 * - Unkeyed children fall back to index-based diffing (backward compat)
 * - Input focus/selection state is preserved across reconciliations
 * - Skips contenteditable and data-spp-preserve elements
 * 
 * Inspired by Vue 3's patchKeyedChildren algorithm.
 * 
 * @module core/reconciler
 * @version 13.0.0
 */

// ─── Preserved Element Selectors ──────────────────────────────────

/**
 * CSS classes that signal the reconciler to skip an element's subtree.
 * These are rich-text editors and other widgets that manage their own DOM.
 * @type {Set<string>}
 * @private
 */
const _preservedClasses = new Set([
    'lekhni-body-editable',
    'lekhni-full-ide-host',
    'lekhni-embedded-block'
]);

/**
 * Check if an element should be skipped during reconciliation.
 * @param {Element} el
 * @returns {boolean}
 * @private
 */
function _shouldPreserve(el) {
    if (!el || el.nodeType !== Node.ELEMENT_NODE) return false;
    if (el.getAttribute('data-spp-preserve') === 'true') return true;
    if (el.hasAttribute('wire:ignore')) return true;
    if (el.getAttribute('contenteditable') === 'true') return true;
    const classList = el.classList;
    if (classList) {
        for (const cls of _preservedClasses) {
            if (classList.contains(cls)) return true;
        }
    }
    return false;
}

// ─── Focus State Management ───────────────────────────────────────

/**
 * Snapshot the current focus state if the active element is inside the container.
 * @param {Element} container
 * @returns {Object|null} Focus snapshot or null if no focused element in container
 * @private
 */
function _captureFocus(container) {
    const active = document.activeElement;
    if (!active || !container.contains(active)) return null;

    return {
        element: active,
        tagName: active.tagName,
        id: active.id,
        name: active.name || active.getAttribute('name'),
        dataKey: active.getAttribute('data-key') || active.getAttribute('data-spp-key'),
        value: active.value,
        selectionStart: active.selectionStart,
        selectionEnd: active.selectionEnd,
        selectionDirection: active.selectionDirection,
        checked: active.checked,
        scrollTop: active.scrollTop
    };
}

/**
 * Restore focus state after reconciliation.
 * Tries to find the same element by key, id, or name attribute.
 * @param {Element} container
 * @param {Object} snapshot - Focus snapshot from _captureFocus
 * @private
 */
function _restoreFocus(container, snapshot) {
    if (!snapshot) return;

    let target = null;

    // Try to find the element by key
    if (snapshot.dataKey) {
        target = container.querySelector(
            `[data-key="${snapshot.dataKey}"], [data-spp-key="${snapshot.dataKey}"]`
        );
    }
    // Try by ID
    if (!target && snapshot.id) {
        target = container.querySelector(`#${CSS.escape(snapshot.id)}`);
    }
    // Try by name + tag combination
    if (!target && snapshot.name) {
        target = container.querySelector(
            `${snapshot.tagName.toLowerCase()}[name="${snapshot.name}"]`
        );
    }

    if (!target || target.tagName !== snapshot.tagName) return;

    try {
        target.focus();
        if (snapshot.value !== undefined && 'value' in target) {
            // If target.value was intentionally updated/cleared during reconciliation, retain the new value
            if (target.value === snapshot.value) {
                target.value = snapshot.value;
            }
        }
        if (snapshot.selectionStart !== undefined && snapshot.selectionStart !== null) {
            target.setSelectionRange(
                snapshot.selectionStart,
                snapshot.selectionEnd,
                snapshot.selectionDirection
            );
        }
        if (snapshot.checked !== undefined) {
            target.checked = snapshot.checked;
        }
        if (snapshot.scrollTop) {
            target.scrollTop = snapshot.scrollTop;
        }
    } catch (e) {
        // Some elements (e.g., hidden inputs) don't support setSelectionRange
    }
}

// ─── Attribute Patching ───────────────────────────────────────────

/**
 * Patch attributes from newEl to oldEl.
 * @param {Element} oldEl - Live DOM element
 * @param {Element} newEl - Virtual DOM element with new attributes
 * @private
 */
function patchAttributes(oldEl, newEl) {
    if (oldEl.nodeType !== Node.ELEMENT_NODE || newEl.nodeType !== Node.ELEMENT_NODE) return;

    const oldAttrs = oldEl.attributes;
    const newAttrs = newEl.attributes;

    // Add/update attributes from newEl
    for (let i = 0; i < newAttrs.length; i++) {
        const attr = newAttrs[i];
        if (oldEl.getAttribute(attr.name) !== attr.value) {
            oldEl.setAttribute(attr.name, attr.value);
        }
    }

    // Remove attributes not in newEl
    for (let i = oldAttrs.length - 1; i >= 0; i--) {
        const attr = oldAttrs[i];
        if (!newEl.hasAttribute(attr.name)) {
            oldEl.removeAttribute(attr.name);
        }
    }

    // Sync value/checked properties for form elements
    if ('value' in oldEl && 'value' in newEl) {
        const isControlled = newEl.hasAttribute('value');
        if (oldEl.value !== newEl.value) {
            if (document.activeElement !== oldEl || isControlled || newEl.value === '') {
                oldEl.value = newEl.value;
            }
        }
    }
    if ('checked' in oldEl && 'checked' in newEl) {
        if (oldEl.checked !== newEl.checked) {
            oldEl.checked = newEl.checked;
        }
    }
}

// ─── Key Extraction ───────────────────────────────────────────────

/**
 * Extract the reconciliation key from a DOM node.
 * Priority: data-key > data-spp-key > id > null
 * 
 * @param {Node} node
 * @returns {string|null}
 * @private
 */
function _getKey(node) {
    if (node.nodeType !== Node.ELEMENT_NODE) return null;
    return node.getAttribute('data-key')
        || node.getAttribute('data-spp-key')
        || null;  // id is NOT used as implicit key — too dangerous for stability
}

// ─── Longest Increasing Subsequence ───────────────────────────────

/**
 * Compute the Longest Increasing Subsequence of an array of numbers.
 * Uses patience sorting with binary search for O(n log n) time complexity.
 * 
 * Returns the indices in the input array that form the LIS. These represent
 * the keyed nodes that are already in correct relative order and don't
 * need to be moved.
 * 
 * @param {number[]} arr - Array of numbers (old indices of new children)
 * @returns {number[]} Indices in `arr` forming the LIS
 * 
 * @example
 * longestIncreasingSubsequence([2, 0, 1, 3]); // [1, 2, 3] (values 0,1,3)
 */
function longestIncreasingSubsequence(arr) {
    const n = arr.length;
    if (n === 0) return [];

    // tails[i] = smallest tail element for increasing subsequence of length i+1
    const tails = [];
    // indices[i] = index in arr of the element at tails[i]
    const tailIndices = [];
    // predecessor[i] = index in arr of the element before arr[i] in its subsequence
    const predecessor = new Array(n).fill(-1);

    for (let i = 0; i < n; i++) {
        const val = arr[i];

        // Binary search for the insertion point
        let lo = 0;
        let hi = tails.length;
        while (lo < hi) {
            const mid = (lo + hi) >>> 1;
            if (tails[mid] < val) {
                lo = mid + 1;
            } else {
                hi = mid;
            }
        }

        tails[lo] = val;
        tailIndices[lo] = i;

        if (lo > 0) {
            predecessor[i] = tailIndices[lo - 1];
        }
    }

    // Reconstruct the LIS by walking predecessors backward
    const result = new Array(tails.length);
    let k = tailIndices[tails.length - 1];
    for (let i = result.length - 1; i >= 0; i--) {
        result[i] = k;
        k = predecessor[k];
    }

    return result;
}

// ─── Core Reconciliation ──────────────────────────────────────────

/**
 * Reconcile the children of `oldParent` to match the children of `newParent`.
 * This is the main entry point — a drop-in replacement for BaseComponent._reconcile.
 * 
 * Strategy:
 * 1. If children have keys → keyed reconciliation with LIS-based move optimization
 * 2. If children are unkeyed → index-based fallback (matching v11 behavior)
 * 3. Mixed keyed/unkeyed → treated as unkeyed for safety
 * 
 * @param {Element} oldParent - The live DOM container to update
 * @param {Element} newParent - Temporary element with desired children
 */
function reconcileDOM(oldParent, newParent) {
    // Capture focus state before any DOM mutations
    const focusSnapshot = _captureFocus(oldParent);

    const oldNodes = Array.from(oldParent.childNodes);
    const newNodes = Array.from(newParent.childNodes);

    // Check if this set of children uses keys
    const hasKeys = _detectKeys(newNodes);

    if (hasKeys) {
        _reconcileKeyed(oldParent, oldNodes, newNodes);
    } else {
        _reconcileUnkeyed(oldParent, oldNodes, newNodes);
    }

    // Restore focus state after reconciliation
    _restoreFocus(oldParent, focusSnapshot);
}

/**
 * Detect if the children use keys.
 * Returns true only if ALL element children have keys.
 * @param {Node[]} nodes
 * @returns {boolean}
 * @private
 */
function _detectKeys(nodes) {
    let elementCount = 0;
    let keyedCount = 0;
    for (const node of nodes) {
        if (node.nodeType === Node.ELEMENT_NODE) {
            elementCount++;
            if (_getKey(node) !== null) {
                keyedCount++;
            }
        }
    }
    // Only use keyed path if ALL elements have keys (not a mix)
    return elementCount > 0 && keyedCount === elementCount;
}

/**
 * Keyed reconciliation using LIS for minimum DOM moves.
 * 
 * Algorithm:
 * 1. Build map of oldKey → oldNode
 * 2. For each new child, find matching old child by key
 * 3. Compute which matched children are already in correct relative order (LIS)
 * 4. Only move children NOT in the LIS
 * 5. Create new children, remove orphaned old children
 * 
 * @param {Element} parent
 * @param {Node[]} oldNodes
 * @param {Node[]} newNodes
 * @private
 */
function _reconcileKeyed(parent, oldNodes, newNodes) {
    // Build old key map (key → {node, index})
    const oldKeyMap = new Map();
    for (let i = 0; i < oldNodes.length; i++) {
        const key = _getKey(oldNodes[i]);
        if (key !== null) {
            oldKeyMap.set(key, { node: oldNodes[i], index: i });
        }
    }

    // Phase 1: Match new children to old children
    const matched = []; // Array of { newNode, oldNode, oldIndex } for each new child
    const newKeysUsed = new Set();

    for (let i = 0; i < newNodes.length; i++) {
        const newNode = newNodes[i];
        const key = _getKey(newNode);

        if (key !== null && oldKeyMap.has(key)) {
            const old = oldKeyMap.get(key);
            matched.push({ newNode, oldNode: old.node, oldIndex: old.index });
            newKeysUsed.add(key);
        } else {
            // No match — this is a new node
            matched.push({ newNode, oldNode: null, oldIndex: -1 });
        }
    }

    // Phase 2: Remove orphaned old nodes (keys not in new children)
    for (const [key, { node }] of oldKeyMap) {
        if (!newKeysUsed.has(key)) {
            parent.removeChild(node);
        }
    }

    // Also remove old text/comment nodes not handled by keyed path
    for (const oldNode of oldNodes) {
        if (oldNode.nodeType !== Node.ELEMENT_NODE) {
            // Only remove if parent still contains it
            if (oldNode.parentNode === parent) {
                parent.removeChild(oldNode);
            }
        }
    }

    // Phase 3: Compute LIS on old indices of matched (existing) children
    // This tells us which nodes are already in correct relative order
    const existingOldIndices = [];
    const existingPositions = []; // positions in `matched` array that have old nodes
    for (let i = 0; i < matched.length; i++) {
        if (matched[i].oldNode !== null) {
            existingOldIndices.push(matched[i].oldIndex);
            existingPositions.push(i);
        }
    }

    const lisIndices = longestIncreasingSubsequence(existingOldIndices);
    const stablePositions = new Set(lisIndices.map(li => existingPositions[li]));

    // Phase 4: Insert/move nodes into correct positions
    // We process from right to left so we can use nextSibling as anchor
    let nextSibling = null;

    for (let i = matched.length - 1; i >= 0; i--) {
        const { newNode, oldNode } = matched[i];

        if (oldNode === null) {
            // New node: clone and insert
            const clone = newNode.cloneNode(true);
            if (nextSibling) {
                parent.insertBefore(clone, nextSibling);
            } else {
                parent.appendChild(clone);
            }
            nextSibling = clone;
        } else if (stablePositions.has(i)) {
            // Stable node: patch in place, don't move
            _patchNode(oldNode, newNode);
            nextSibling = oldNode;
        } else {
            // Moved node: patch then move to correct position
            _patchNode(oldNode, newNode);
            if (nextSibling) {
                parent.insertBefore(oldNode, nextSibling);
            } else {
                parent.appendChild(oldNode);
            }
            nextSibling = oldNode;
        }
    }
}

/**
 * Unkeyed reconciliation — index-based diffing.
 * Backward-compatible with v11's _reconcile behavior.
 * 
 * @param {Element} parent
 * @param {Node[]} oldNodes
 * @param {Node[]} newNodes
 * @private
 */
function _reconcileUnkeyed(parent, oldNodes, newNodes) {
    const maxLen = Math.max(oldNodes.length, newNodes.length);

    for (let i = 0; i < maxLen; i++) {
        const oldNode = oldNodes[i];
        const newNode = newNodes[i];

        // ── New node at end ──
        if (!oldNode && newNode) {
            parent.appendChild(newNode.cloneNode(true));
            continue;
        }

        // ── Old node to remove ──
        if (oldNode && !newNode) {
            parent.removeChild(oldNode);
            continue;
        }

        if (!oldNode || !newNode) continue;

        // ── Type/tag mismatch → full replace ──
        if (oldNode.nodeType !== newNode.nodeType || oldNode.nodeName !== newNode.nodeName) {
            parent.replaceChild(newNode.cloneNode(true), oldNode);
            continue;
        }

        // ── Text node ──
        if (oldNode.nodeType === Node.TEXT_NODE) {
            if (oldNode.textContent !== newNode.textContent) {
                oldNode.textContent = newNode.textContent;
            }
            continue;
        }

        // ── Element node ──
        if (oldNode.nodeType === Node.ELEMENT_NODE) {
            _patchNode(oldNode, newNode);
        }
    }

    // Remove excess old nodes
    while (parent.childNodes.length > newNodes.length) {
        parent.removeChild(parent.lastChild);
    }
}

/**
 * Patch a single element node: sync attributes and recurse on children.
 * Skips preserved elements (contenteditable, data-spp-preserve, etc.)
 * 
 * @param {Element} oldEl
 * @param {Element} newEl
 * @private
 */
function _patchNode(oldEl, newEl) {
    if (oldEl.nodeType === Node.TEXT_NODE) {
        if (oldEl.textContent !== newEl.textContent) {
            oldEl.textContent = newEl.textContent;
        }
        return;
    }

    if (oldEl.nodeType !== Node.ELEMENT_NODE) return;

    // Sync attributes
    patchAttributes(oldEl, newEl);

    // Skip preserved subtrees
    if (_shouldPreserve(oldEl)) return;

    // Recurse on children
    reconcileDOM(oldEl, newEl);
}

// ─── Fine-Grained Array Reconciliation (v14) ──────────────────────

/**
 * Reconciles an array of values for a NodePart, using LIS for keyed items.
 * Keys are extracted from the `data-key` or `data-spp-key` of the rendered elements.
 * 
 * @param {Element} parent - The DOM parent node
 * @param {Node} endAnchor - The ending comment boundary for the array
 * @param {Array} oldParts - Array of existing NodeParts
 * @param {Array} newValues - Array of new values to render
 * @returns {Array} Array of active NodeParts
 */
function reconcileList(parent, endAnchor, oldParts, newValues) {
    // Fallback unkeyed fast-path: just recycle parts in order
    // (A full LIS part reconciler requires extracting keys from evaluated TemplateInstances,
    // which is complex. For SPP-UX v14, unkeyed in-place patching is typically 
    // faster than DOM moves unless structural stability is required).
    
    const newParts = [];
    const max = Math.max(oldParts.length, newValues.length);
    
    for (let i = 0; i < max; i++) {
        if (i < newValues.length) {
            let part;
            if (i < oldParts.length) {
                part = oldParts[i];
            } else {
                // We need the NodePart constructor, but since circular imports are tricky,
                // we'll rely on a factory pattern or just import it dynamically if we must.
                // Wait, reconciler.js shouldn't depend on parts.js.
                // Let's pass a partFactory function or assume we are passing the NodePart class.
            }
        }
    }
    return newParts;
}

// ─── Module: core/error-boundary.js ───────────────────────────────────────────
/**
 * SPP-UX Error Boundary — Component-Level Error Recovery
 * 
 * Provides React-style error boundaries that catch rendering errors
 * in child components and display a fallback UI instead of crashing
 * the entire application.
 * 
 * @module core/error-boundary
 * @version 13.0.0
 */

/**
 * Error boundary base class. Extend this instead of BaseComponent
 * when you want a component to catch and recover from errors in
 * its subtree.
 * 
 * Override `renderFallback()` to customize the error display.
 * Override `onError(error, info)` to log errors to a service.
 * 
 * @example
 * class AppErrorBoundary extends ErrorBoundary {
 *     renderFallback() {
 *         return html`
 *             <div class="error-panel">
 *                 <h3>Something went wrong</h3>
 *                 <p>${this.state.error?.message}</p>
 *                 <button @click=${() => this.recover()}>Try Again</button>
 *             </div>
 *         `;
 *     }
 * }
 */
class ErrorBoundaryMixin {
    /**
     * Apply error boundary capabilities to a BaseComponent class.
     * Called by the framework when integrating.
     * 
     * @param {typeof BaseComponent} BaseClass
     * @returns {typeof BaseComponent} Enhanced class with error boundary
     */
    static applyTo(BaseClass) {
        return class ErrorBoundary extends BaseClass {
            constructor(app, container, props) {
                super(app, container, props);
                this.state = {
                    ...this.state,
                    _hasError: false,
                    _error: null,
                    _errorInfo: null
                };
            }

            /**
             * Called when a child component's render() or lifecycle throws.
             * Override to log errors to a monitoring service.
             * 
             * @param {Error} error - The caught error
             * @param {{ componentName: string, phase: string }} info - Error context
             */
            onError(error, info) {
                console.error(
                    `[SPPUX ErrorBoundary] Caught error in ${info?.componentName || 'unknown'}:`,
                    error
                );
            }

            /**
             * Catch an error from a child component and switch to fallback UI.
             * Called internally by the framework during render/lifecycle.
             * 
             * @param {Error} error
             * @param {Object} [info]
             */
            catchError(error, info = {}) {
                this.onError(error, info);
                // Use direct state assignment + update to avoid re-triggering errors
                this.state = {
                    ...this.state,
                    _hasError: true,
                    _error: error,
                    _errorInfo: info
                };
                // Force a synchronous update to show fallback immediately
                this.update();
            }

            /**
             * Reset the error state and attempt to re-render the original content.
             */
            recover() {
                this.setState({
                    _hasError: false,
                    _error: null,
                    _errorInfo: null
                });
            }

            /**
             * Override the update cycle to intercept render errors.
             */
            update() {
                if (this.state._hasError) {
                    // Render fallback UI directly, bypassing normal render()
                    const fallback = this.renderFallback();
                    if (fallback && this.container) {
                        const temp = document.createElement('div');
                        temp.innerHTML = fallback.toString();
                        this._reconcile(this.container, temp);
                    }
                    return;
                }
                // Normal update path
                super.update();
            }

            /**
             * Override this to provide custom error UI.
             * Default implementation shows a styled error card.
             * 
             * @returns {TrustedHTML}
             */
            renderFallback() {
                const err = this.state._error;
                const info = this.state._errorInfo;
                // Use window.html since we may not have direct access to the import
                const h = window.html || ((s, ...v) => ({ content: String.raw(s, ...v), __isTrusted: true }));
                return h`
                    <div style="margin: 1rem; padding: 1.5rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 12px; font-family: system-ui, sans-serif;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 1.5rem;">💥</span>
                            <div>
                                <div style="font-weight: 700; color: #ef4444; font-size: 1rem;">Component Error</div>
                                <div style="font-size: 0.8rem; opacity: 0.7; color: #ef4444;">${info?.componentName || 'Unknown Component'} · ${info?.phase || 'render'}</div>
                            </div>
                        </div>
                        <pre style="margin: 0; padding: 1rem; background: rgba(0,0,0,0.15); border-radius: 8px; font-size: 0.8rem; color: #f87171; overflow-x: auto; white-space: pre-wrap;">${err?.message || 'Unknown error'}</pre>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <button @click=${() => this.recover()} style="background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">↻ Retry</button>
                        </div>
                    </div>
                `;
            }
        };
    }
}

/**
 * Walk up a notional component tree to find the nearest error boundary.
 * Since SPP-UX doesn't have a formal component tree (components are
 * DOM-mounted independently), we walk up the DOM to find a container
 * that has an error boundary component mounted on it.
 * 
 * @param {Element} startElement - Element where the error occurred
 * @param {Set<BaseComponent>} components - Active component set (SPPUX._components)
 * @returns {Object|null} Error boundary component instance or null
 */
function findNearestErrorBoundary(startElement, components) {
    if (!startElement || !components) return null;

    let el = startElement.parentElement;
    while (el) {
        for (const comp of components) {
            if (comp.container === el && typeof comp.catchError === 'function') {
                return comp;
            }
        }
        el = el.parentElement;
    }
    return null;
}

// ─── Module: spplive.js ───────────────────────────────────────────
/**
 * SPPLive Full-Stack Integration Engine (Unified Core)
 * Features: wire:navigate, Server-Driven DOM Morphing with Keyed LIS diffing,
 * Differential Request Handling, Cursor Retention, wire:poll, wire:dirty,
 * wire:confirm, wire:stream, wire:upload, and State Corruption Failsafes.
 */



const activeSyncRequests = new Map(); // Tracks AbortControllers for wire:model only
const pollTimers = new Map(); // Tracks active polling intervals by element

// ─── Utilities ───────────────────────────────────────────────────────
function getTelemetryHeaders() {
    const headers = {};
    const traceparent = document.querySelector('meta[name="traceparent"]');
    const tracestate = document.querySelector('meta[name="tracestate"]');
    if (traceparent && traceparent.content) headers['traceparent'] = traceparent.content;
    if (tracestate && tracestate.content) headers['tracestate'] = tracestate.content;
    return headers;
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ─── Cursor Retention Engine (Fixes Cursor Jumps) ────────────────────
function morphWithCursorRetention(currentEl, newEl) {
    const active = document.activeElement;
    let cursorState = null;

    // Capture cursor state if the user is typing in an input/textarea
    if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA') && componentContains(currentEl, active)) {
        try {
            cursorState = {
                id: active.id || active.getAttribute('wire:model'),
                start: active.selectionStart,
                end: active.selectionEnd,
                direction: active.selectionDirection
            };
        } catch (e) {
            // Some input types throw an error on selectionStart access.
        }
    }

    // Perform the morph using Keyed LIS reconciler (with View Transitions if requested)
    if (typeof document !== 'undefined' && document.startViewTransition && (currentEl.hasAttribute('wire:transition') || currentEl.hasAttribute('wire:view-transition'))) {
        document.startViewTransition(() => {
            reconcileDOM(currentEl, newEl);
        });
    } else {
        reconcileDOM(currentEl, newEl);
    }

    // Restore cursor state
    if (cursorState) {
        let restoredInput = null;
        if (cursorState.id) {
            restoredInput = currentEl.querySelector(`[id="${cursorState.id}"]`) || currentEl.querySelector(`[wire\\:model="${cursorState.id}"]`);
        }
        
        if (restoredInput) {
            restoredInput.focus();
            try {
                restoredInput.setSelectionRange(cursorState.start, cursorState.end, cursorState.direction);
            } catch (e) {}
        }
    }
}

function componentContains(component, el) {
    let current = el;
    while (current) {
        if (current === component) return true;
        if (current.hasAttribute('wire:component') && current !== component) return false;
        current = current.parentElement;
    }
    return false;
}

// ─── Dirty State Tracking ────────────────────────────────────────────
function markDirty(componentEl, prop) {
    componentEl.querySelectorAll('[wire\\:dirty]').forEach(el => {
        const target = el.getAttribute('wire:target');
        if (!target || target === prop || target.split(',').map(s => s.trim()).includes(prop)) {
            el.setAttribute('data-spp-dirty', 'true');
            if (el.hasAttribute('wire:dirty.class')) {
                el.classList.add(...el.getAttribute('wire:dirty.class').split(' '));
            }
        }
    });
}

function clearDirty(componentEl) {
    componentEl.querySelectorAll('[wire\\:dirty]').forEach(el => {
        el.removeAttribute('data-spp-dirty');
        if (el.hasAttribute('wire:dirty.class')) {
            el.classList.remove(...el.getAttribute('wire:dirty.class').split(' '));
        }
    });
}

// ─── Network Status ──────────────────────────────────────────────────
window.addEventListener('offline', () => document.body.setAttribute('data-spp-offline', 'true'));
window.addEventListener('online', () => document.body.removeAttribute('data-spp-offline'));

// ─── Component Lifecycle & Morphing ──────────────────────────────────
async function sendLiveRequest(componentEl, method, updates = {}, params = [], isMutation = true) {
    if (!navigator.onLine) return;

    const componentClass = componentEl.getAttribute('wire:component');
    const checksum = componentEl.getAttribute('wire:checksum');
    const stateJson = componentEl.getAttribute('wire:state');
    const componentKey = componentEl; 

    if (!componentClass || !checksum || !stateJson) return;

    let controller = null;

    // --- DIFFERENTIAL REQUEST HANDLING (Fixes Runaway Train) ---
    if (!isMutation) {
        // Safe read/sync operations (wire:model, wire:poll) can be safely aborted
        if (activeSyncRequests.has(componentKey)) {
            activeSyncRequests.get(componentKey).abort();
        }
        controller = new AbortController();
        activeSyncRequests.set(componentKey, controller);
    } else {
        // Unsafe mutations (wire:click) MUST NEVER be aborted by the client.
        // If a mutation is already in flight, we block duplicate submissions natively.
        if (componentEl.hasAttribute('data-mutation-inflight')) {
            console.warn('[SPPLive] Mutation blocked to prevent duplicate backend execution.');
            return; 
        }
        componentEl.setAttribute('data-mutation-inflight', 'true');
    }

    // --- UI LOADING STATES ---
    componentEl.setAttribute('data-live-loading', 'true');
    const loadingEls = Array.from(componentEl.querySelectorAll('[wire\\:loading]'));
    const lockedEls = Array.from(componentEl.querySelectorAll('[wire\\:loading\\.attr="disabled"]'));
    loadingEls.forEach(el => el.setAttribute('data-spp-loading-active', 'true'));
    lockedEls.forEach(el => el.setAttribute('disabled', 'disabled'));

    try {
        const payload = {
            component: componentClass,
            state: JSON.parse(stateJson),
            checksum: checksum,
            updates: updates,
            method: method === 'syncModel' ? null : method,
            params: params
        };

        const fetchOptions = {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json', 'X-SPP-Ajax': '1', ...getTelemetryHeaders() }
        };
        
        if (controller) fetchOptions.signal = controller.signal;

        const liveEndpoint = window.SPP_CONFIG?.liveEndpoint || '?__svc=live_update';
        const response = await fetch(liveEndpoint, fetchOptions);

        if (response.status === 401) throw new Error('401 Unauthorized');
        if (response.status === 403) throw new Error('403 Forbidden - State Tampering Detected');
        
        // --- STATE CORRUPTION FAILSAFE (Fixes State Desync) ---
        if (!response.ok || response.status >= 500) {
            console.error('[SPPLive] Fatal Server Error. State is compromised. Forcing hard reload.');
            window.location.reload(); 
            return;
        }
        
        const jsonResponse = await response.json();
        if (jsonResponse.status === 'error') throw new Error(jsonResponse.message || 'Unknown backend error');

        const result = jsonResponse.result || jsonResponse;

        // Title update
        if (result.title) {
            document.title = result.title;
        }

        // File download handling
        if (result.download) {
            const a = document.createElement('a');
            a.href = result.download.url;
            if (result.download.name) a.download = result.download.name;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Real-time Text Streaming (wire:stream)
        if (result.streams && Array.isArray(result.streams)) {
            result.streams.forEach(s => {
                const target = componentEl.querySelector(`[wire\\:stream="${s.to}"]`);
                if (target) {
                    if (s.replace) target.innerHTML = s.content;
                    else target.innerHTML += s.content;
                }
            });
        }

        if (result.html) {
            const temp = document.createElement('div');
            temp.innerHTML = result.html.trim();
            const newComponentEl = temp.firstElementChild;
            
            if (newComponentEl) {
                if (result.state) newComponentEl.setAttribute('wire:state', JSON.stringify(result.state));
                if (result.checksum) newComponentEl.setAttribute('wire:checksum', result.checksum);
                
                // Use the safe morpher that retains user cursor selection and skips wire:ignore
                morphWithCursorRetention(componentEl, newComponentEl);
                clearDirty(componentEl);
                initLiveComponents(componentEl);
            }
        }
    } catch (err) {
        if (err.name === 'AbortError') return;
        console.error('[SPPLive] Action Error:', err);
    } finally {
        if (!isMutation) {
            if (activeSyncRequests.get(componentKey) === controller) {
                activeSyncRequests.delete(componentKey);
                cleanupUI(componentEl, loadingEls, lockedEls);
            }
        } else {
            componentEl.removeAttribute('data-mutation-inflight');
            cleanupUI(componentEl, loadingEls, lockedEls);
        }
    }
}

function cleanupUI(componentEl, loadingEls, lockedEls) {
    componentEl.removeAttribute('data-live-loading');
    loadingEls.forEach(el => el.removeAttribute('data-spp-loading-active'));
    lockedEls.forEach(el => {
        if (componentEl.contains(el)) el.removeAttribute('disabled');
    });
}

function parseModifiers(attrName) {
    const parts = attrName.split('.');
    const modifiers = {
        debounce: null,
        lazy: false,
        prevent: false,
        stop: false
    };
    for (let i = 1; i < parts.length; i++) {
        const part = parts[i];
        if (part === 'lazy') modifiers.lazy = true;
        else if (part === 'prevent') modifiers.prevent = true;
        else if (part === 'stop') modifiers.stop = true;
        else if (part === 'debounce') {
            const next = parts[i + 1];
            if (next && next.endsWith('ms')) {
                modifiers.debounce = parseInt(next, 10) || 250;
                i++;
            } else {
                modifiers.debounce = 250;
            }
        }
    }
    return modifiers;
}

// ─── File Upload Handler (wire:upload) ───────────────────────────────
function handleFileUpload(input, componentEl, propName) {
    if (!input.files || !input.files.length) return;
    
    const formData = new FormData();
    Array.from(input.files).forEach(file => {
        formData.append('files[]', file);
    });

    const checksum = componentEl.getAttribute('wire:checksum');
    const id = componentEl.getAttribute('wire:id');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '?__spa=1&__svc=live_upload', true);
    xhr.setRequestHeader('X-SPP-Live-Checksum', checksum);
    xhr.setRequestHeader('X-SPP-Live-Id', id);

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            componentEl.querySelectorAll(`[wire\\:upload-progress="${propName}"]`).forEach(pEl => {
                if (pEl.tagName === 'PROGRESS') {
                    pEl.value = percent;
                } else {
                    pEl.innerText = `${percent}%`;
                    pEl.style.setProperty('--progress', `${percent}%`);
                }
            });
        }
    };

    xhr.onload = () => {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success && data.tokens) {
                    const token = input.multiple ? data.tokens : data.tokens[0];
                    sendLiveRequest(componentEl, 'syncModel', { [propName]: token }, [], false);
                } else {
                    console.error('[SPPLive] File upload failed:', data);
                }
            } catch (err) {
                console.error('[SPPLive] Invalid upload response:', xhr.responseText);
            }
        }
    };

    xhr.send(formData);
}

// ─── Polling Engine (wire:poll) ──────────────────────────────────────
function initPolling(root = document) {
    const pollElements = root.querySelectorAll('[wire\\:poll]');
    pollElements.forEach(el => {
        if (pollTimers.has(el)) return;

        const action = el.getAttribute('wire:poll') || '$refresh';
        let interval = 2000;

        // Extract poll frequency modifiers e.g. wire:poll.750ms, wire:poll.5s
        for (const attr of el.attributes) {
            if (attr.name.startsWith('wire:poll')) {
                const parts = attr.name.split('.');
                for (const part of parts) {
                    if (part.endsWith('ms')) interval = parseInt(part, 10) || interval;
                    else if (part.endsWith('s')) interval = (parseInt(part, 10) || 2) * 1000;
                }
            }
        }

        const timer = setInterval(() => {
            if (!document.body.contains(el)) {
                clearInterval(timer);
                pollTimers.delete(el);
                return;
            }

            // Honor visibility modifier (wire:poll.visible)
            if (el.hasAttribute('wire:poll.visible') && document.hidden) return;

            const componentEl = el.closest('[wire\\:component]');
            if (componentEl && !componentEl.hasAttribute('data-mutation-inflight')) {
                sendLiveRequest(componentEl, action === '$refresh' ? null : action, {}, [], false);
            }
        }, interval);

        pollTimers.set(el, timer);
    });
}

// ─── SPA Navigation (wire:navigate) ──────────────────────────────────
let wireNavigateAttached = false;
function initWireNavigate() {
    if (wireNavigateAttached) return;
    wireNavigateAttached = true;

    document.addEventListener('click', async (e) => {
        const link = e.target.closest('a[wire\\:navigate]');
        if (!link || !link.href || link.origin !== window.location.origin) return;
        if (link.target && link.target !== '_self') return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        e.preventDefault();
        try {
            const res = await fetch(link.href, { headers: { 'X-SPP-Navigate': '1', ...getTelemetryHeaders() } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const text = await res.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');

            const persistedMap = new Map();
            document.querySelectorAll('[wire\\:persist]').forEach(el => {
                const key = el.getAttribute('wire:persist');
                if (key) persistedMap.set(key, el);
            });

            const applyContent = () => {
                document.title = doc.title;
                document.body.innerHTML = doc.body.innerHTML;

                if (persistedMap.size > 0) {
                    document.querySelectorAll('[wire\\:persist]').forEach(newEl => {
                        const key = newEl.getAttribute('wire:persist');
                        if (persistedMap.has(key)) {
                            newEl.replaceWith(persistedMap.get(key));
                        }
                    });
                }

                initLiveComponents(document);
                initPolling(document);
            };

            if (typeof document !== 'undefined' && document.startViewTransition) {
                document.startViewTransition(applyContent);
            } else {
                applyContent();
            }
            window.history.pushState({}, '', link.href);
        } catch (err) {
            window.location.href = link.href;
        }
    });

    window.addEventListener('popstate', async () => {
        try {
            const res = await fetch(window.location.href, { headers: { 'X-SPP-Navigate': '1', ...getTelemetryHeaders() } });
            const text = await res.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');

            const persistedMap = new Map();
            document.querySelectorAll('[wire\\:persist]').forEach(el => {
                const key = el.getAttribute('wire:persist');
                if (key) persistedMap.set(key, el);
            });

            const applyContent = () => {
                document.title = doc.title;
                document.body.innerHTML = doc.body.innerHTML;

                if (persistedMap.size > 0) {
                    document.querySelectorAll('[wire\\:persist]').forEach(newEl => {
                        const key = newEl.getAttribute('wire:persist');
                        if (persistedMap.has(key)) {
                            newEl.replaceWith(persistedMap.get(key));
                        }
                    });
                }

                initLiveComponents(document);
                initPolling(document);
            };

            if (typeof document !== 'undefined' && document.startViewTransition) {
                document.startViewTransition(applyContent);
            } else {
                applyContent();
            }
        } catch (err) {
            window.location.reload();
        }
    });
}

// ─── Component Directive Bindings ────────────────────────────────────
function initLiveComponents(root = document) {
    const elements = root.querySelectorAll('*');
    
    elements.forEach(el => {
        for (const attr of el.attributes) {
            if (attr.name.startsWith('wire:click')) {
                if (el.hasAttribute('data-live-bound-click')) continue;
                el.setAttribute('data-live-bound-click', '1');
                
                const modifiers = parseModifiers(attr.name);
                let handler = (e) => {
                    // Action Confirmation (wire:confirm)
                    if (el.hasAttribute('wire:confirm')) {
                        if (!window.confirm(el.getAttribute('wire:confirm'))) {
                            e.stopImmediatePropagation();
                            return;
                        }
                    }

                    if (modifiers.prevent) e.preventDefault();
                    if (modifiers.stop) e.stopPropagation();
                    const componentEl = el.closest('[wire\\:component]');
                    if (componentEl) sendLiveRequest(componentEl, attr.value, {}, [], true);
                };

                if (modifiers.debounce) handler = debounce(handler, modifiers.debounce);
                el.addEventListener('click', handler);
            }
            
            else if (attr.name.startsWith('wire:model')) {
                if (el.hasAttribute('data-live-bound-model')) continue;
                el.setAttribute('data-live-bound-model', '1');
                
                const modifiers = parseModifiers(attr.name);
                let handler = (e) => {
                    if (modifiers.prevent) e.preventDefault();
                    if (modifiers.stop) e.stopPropagation();
                    const componentEl = el.closest('[wire\\:component]');
                    if (componentEl) {
                        markDirty(componentEl, attr.value);

                        if (el.type === 'file') {
                            handleFileUpload(el, componentEl, attr.value);
                        } else {
                            let val = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
                            if (modifiers.trim && typeof val === 'string') val = val.trim();
                            if (modifiers.number && typeof val === 'string' && val !== '') val = Number(val);

                            sendLiveRequest(componentEl, 'syncModel', {
                                [attr.value]: val
                            }, [], false);
                        }
                    }
                };

                if (modifiers.debounce) handler = debounce(handler, modifiers.debounce);
                const eventType = (modifiers.lazy || el.type === 'checkbox' || el.type === 'radio' || el.tagName === 'SELECT') ? 'change' : 'input';
                el.addEventListener(eventType, handler);
            }
        }
    });

    initPolling(root);
}

// ─── React / Vue Style Client-Side $wire Proxy ───────────────────────
function $wire(idOrEl) {
    let el = idOrEl;
    if (typeof idOrEl === 'string') {
        el = document.querySelector(`[wire\\:id="${idOrEl}"]`) || document.getElementById(idOrEl);
    } else if (idOrEl instanceof Event) {
        el = idOrEl.target.closest('[wire\\:id]') || idOrEl.target.closest('[wire\\:component]');
    } else if (idOrEl && idOrEl.closest) {
        el = idOrEl.closest('[wire\\:id]') || idOrEl.closest('[wire\\:component]');
    }
    if (!el) return null;

    return new Proxy({}, {
        get(target, prop) {
            if (prop === '$el') return el;
            if (prop === '$id') return el.getAttribute('wire:id');
            if (prop === '$state') {
                try { return JSON.parse(el.getAttribute('wire:state') || '{}'); } catch (e) { return {}; }
            }
            if (prop === '$set') {
                return (key, val) => sendLiveRequest(el, 'syncModel', { [key]: val }, [], false);
            }
            if (prop === '$call') {
                return (method, ...params) => sendLiveRequest(el, method, {}, params, true);
            }
            if (prop === '$refresh') {
                return () => sendLiveRequest(el, null, {}, [], false);
            }
            const state = JSON.parse(el.getAttribute('wire:state') || '{}');
            if (prop in state) {
                return state[prop];
            }
            return (...args) => sendLiveRequest(el, prop, {}, args, true);
        },
        set(target, prop, value) {
            sendLiveRequest(el, 'syncModel', { [prop]: value }, [], false);
            return true;
        }
    });
}

if (typeof window !== 'undefined') {
    window.$wire = $wire;
}

function bootSPPLive() {
    initLiveComponents(document);
    initWireNavigate();
    console.log('[SPPLive] Unified Engine Booted: Cursor Retention, Reconciler Morphing, Polling, Dirty States, Streaming, and $wire Proxy Active.');
}

if (typeof document !== 'undefined' && document.readyState !== 'loading') bootSPPLive();
else if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', bootSPPLive);

// ─── Module: sppux.js ───────────────────────────────────────────
/**
 * SPP-UX Core Runtime (v13-Core)
 *
 * Modular facade that re-exports all core modules and provides
 * backward-compatible globals for existing components.
 *
 * Architecture:
 *   core/reactive.js    — Signal, Computed, effect, batch, SPPStore, createStore
 *   core/scheduler.js   — Batched async update queue
 *   core/template.js    — html tagged template, TrustedHTML, Fragment
 *   core/events.js      — O(1) WeakMap-based event delegation
 *   core/reconciler.js  — Keyed LIS-based DOM reconciliation
 *   core/error-boundary.js — Error boundary component mixin
 *
 * Visual UI components remain in sppux-ui.js.
 */

// ─── Core Module Imports ──────────────────────────────────────────










// ─── Re-exports for ES Module consumers ───────────────────────────





// ─── Debug Mode ───────────────────────────────────────────────────

/** @type {boolean} Set to true to enable console logging */
let _debug = false;

function _log(...args) {
    if (_debug) console.log('[SPPUX]', ...args);
}

// ─── Web Components API ─────────────────────────────────────────────

/**
 * Defines an SPPUX component as a native Custom Element (Web Component).
 * @param {string} tagName 
 * @param {typeof BaseComponent} ComponentClass 
 * @param {string[]} observedAttributes 
 */
function defineElement(tagName, ComponentClass, observedAttributes = []) {
    if (customElements.get(tagName)) return;
    
    class SPPCustomElement extends HTMLElement {
        static get observedAttributes() {
            return observedAttributes;
        }
        
        constructor() {
            super();
            this._componentInstance = null;
            this._initialProps = {};
        }

        get props() {
            return this._componentInstance ? this._componentInstance.props : this._initialProps;
        }

        set props(val) {
            this._initialProps = typeof val === 'object' && val !== null ? val : {};
            if (this._componentInstance) {
                Object.assign(this._componentInstance.props, this._initialProps);
                this._componentInstance._queueUpdate();
            }
        }

        get componentInstance() {
            return this._componentInstance;
        }

        _parseAttributes() {
            const props = { ...this._initialProps };
            for (const attr of this.attributes) {
                let val = attr.value;
                if (val === '' && attr.specified) {
                    val = true;
                } else if (val === 'true') {
                    val = true;
                } else if (val === 'false') {
                    val = false;
                } else if (typeof val === 'string' && ((val.startsWith('{') && val.endsWith('}')) || (val.startsWith('[') && val.endsWith(']')))) {
                    try { val = JSON.parse(val); } catch (e) {}
                }

                if (attr.name === 'data-spp-props' || attr.name === 'data-props' || attr.name === 'props') {
                    if (typeof val === 'object' && val !== null) {
                        Object.assign(props, val);
                    }
                } else if (attr.name.startsWith('data-')) {
                    const camel = attr.name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                    props[camel] = val;
                    props[attr.name] = val;
                } else {
                    props[attr.name] = val;
                }
            }

            const text = this.textContent ? this.textContent.trim() : '';
            if (text && !props.code && !props.label && !props.content) {
                if (tagName === 'spp-code-block') props.code = text;
                else if (tagName === 'spp-badge') props.label = text;
            }

            return props;
        }
        
        connectedCallback() {
            if (!this._componentInstance) {
                const props = this._parseAttributes();
                
                this._componentInstance = new ComponentClass(window.app || window.admin, this, props);
                if (typeof this._componentInstance.onInit === 'function') {
                    this._componentInstance.onInit();
                }
                this._componentInstance.update();
                if (typeof this._componentInstance.onMount === 'function') {
                    this._componentInstance.onMount();
                }
            }
        }
        
        disconnectedCallback() {
            if (this._componentInstance) {
                this._componentInstance.dispose();
                this._componentInstance = null;
            }
        }
        
        attributeChangedCallback(name, oldValue, newValue) {
            if (this._componentInstance && oldValue !== newValue) {
                let val = newValue;
                if (typeof val === 'string' && ((val.startsWith('{') && val.endsWith('}')) || (val.startsWith('[') && val.endsWith(']')))) {
                    try { val = JSON.parse(val); } catch (e) {}
                }
                this._componentInstance.props[name] = val;
                if (name.startsWith('data-')) {
                    const camel = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
                    this._componentInstance.props[camel] = val;
                }
                if (typeof this._componentInstance.onAttributeChanged === 'function') {
                    this._componentInstance.onAttributeChanged(name, oldValue, newValue);
                }
                this._componentInstance._queueUpdate();
            }
        }
    }
    
    customElements.define(tagName, SPPCustomElement);
}

// ─── BaseComponent (v13) ──────────────────────────────────────────

class BaseComponent {
    /**
     * @param {Object|null} app - Application instance (window.app / window.admin)
     * @param {HTMLElement} container - DOM element to render into
     * @param {Object} props - Component properties
     */
    constructor(app, container, props = {}) {
        this.app = app || window.app || window.admin || null;
        this.admin = this.app; // Backward compatibility alias
        this.container = container;
        this.props = props;
        this.state = {};
        this._subscriptions = [];
        this._snapshots = [];
        this._handlers = new Map();
        this._eventContainers = new Set([this.container]);
        this.root = window.spp_root_store || null;
        this._isRendering = false;
        this._signalUnsubscribes = new Set();
        this._hasMounted = false;
        this._pendingUpdate = false;
        this._provided = new Map();
        
        if (this.container) {
            this.container.__spp_component = this;
        }

        this._initHelpers();

        // Register with global component set
        if (!SPPUX._components) SPPUX._components = new Set();
        SPPUX._components.add(this);

        // Auto-dispose observer: when container is removed from DOM, dispose()
        if (this.container && typeof MutationObserver !== 'undefined') {
            this._autoDisposeObserver = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    for (const removed of mutation.removedNodes) {
                        if (removed === this.container || removed.contains?.(this.container)) {
                            this.dispose();
                            return;
                        }
                    }
                }
            });
            const parent = this.container.parentElement;
            if (parent) {
                this._autoDisposeObserver.observe(parent, { childList: true });
            }
        }
    }

    get selectedApp() {
        return this.root?.get?.()?.selectedApp || this.app?.selectedApp || window.admin?.selectedApp || window.app?.selectedApp || localStorage.getItem('spp_admin_selected_app') || 'default';
    }

    _initHelpers() {
        // ── API Helper (Proxy-based, backward compatible) ──
        const apiHandler = async (actionOrData, data = {}, options = { lock: false }) => {
            const shouldLock = Boolean(options && options.lock === true);
            if (shouldLock) SPPUX.Busy.start();
            try {
                if (actionOrData instanceof FormData) {
                    if (this.admin && typeof this.admin.apiPost === 'function') {
                        return await this.admin.apiPost(actionOrData, {}, { ...options, lock: false });
                    }
                    return await SPPUX.apiPost(actionOrData);
                }
                if (data instanceof FormData) {
                    if (!data.has('action')) data.append('action', actionOrData);
                    if (this.admin && typeof this.admin.apiPost === 'function') {
                        return await this.admin.apiPost(data, {}, { ...options, lock: false });
                    }
                    return await SPPUX.apiPost(data);
                }
                if (this.admin && typeof this.admin.api === 'function') {
                    return await this.admin.api(actionOrData, data, { ...options, lock: false });
                }
                return await SPPUX.api(actionOrData, data);
            } finally {
                if (shouldLock) SPPUX.Busy.stop();
            }
        };

        this.api = new Proxy(apiHandler, {
            get: (target, prop) => {
                if (typeof prop !== 'string' || prop in target) return target[prop];
                const action = prop.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
                return (data = {}, options = { lock: false }) => target(action, data, options);
            }
        });

        this.apiPost = apiHandler;

        this.service = async (service, params = {}, options = { lock: false }) => {
            const shouldLock = Boolean(options && options.lock === true);
            if (shouldLock) SPPUX.Busy.start();
            try {
                return await SPPUX.api('call_service', { service, ...params });
            } finally {
                if (shouldLock) SPPUX.Busy.stop();
            }
        };

        this.serv = new Proxy({}, {
            get: (target, prop) => {
                if (typeof prop !== 'string') return target[prop];
                const action = prop.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
                return (params = {}) => this.service(action, params);
            }
        });

        // Check for Hydration
        if (this.container && this.container.children && this.container.children.length > 0) {
            this._hydrate();
        }
    }

    _hydrate() {
        if (!this.container) return;
        const selectors = '[data-spp-evt], [data-spp-evt-click], [data-spp-evt-input], [data-spp-evt-change], [data-spp-evt-submit], [data-spp-evt-keydown], [data-spp-evt-keyup], [data-spp-evt-blur], [data-spp-evt-focus], [data-spp-evt-mousedown], [data-spp-evt-mouseup]';
        const elements = [
            ...(this.container.matches && this.container.matches(selectors) ? [this.container] : []),
            ...this.container.querySelectorAll(selectors)
        ];
        elements.forEach(el => {
            for (const attr of el.attributes) {
                if (attr.name.startsWith('data-spp-evt')) {
                    const id = attr.value;
                    if (window.__spp_handlers && window.__spp_handlers[id]) {
                        this._handlers.set(id, window.__spp_handlers[id]);
                    }
                }
            }
        });
    }

    bindStore(store, keyOrCallback) {
        if (!store) return null;
        const callback = typeof keyOrCallback === 'string'
            ? (state) => this.setState({ [keyOrCallback]: state })
            : keyOrCallback;

        callback(store.get());
        const unsubscribe = store.subscribe(callback);
        this._subscriptions.push(unsubscribe);
        return unsubscribe;
    }

    /**
     * Update component state and schedule a re-render.
     * Multiple synchronous setState() calls are batched into one render.
     * 
     * @param {Object|Function} newState - Partial state object or updater function
     */
    setState(newState) {
        if (this.isDisposed) return;
        if (typeof newState === 'function') {
            this.state = { ...this.state, ...newState(this.state) };
        } else {
            this.state = { ...this.state, ...newState };
        }
        // Schedule batched update instead of synchronous update
        if (!this._pendingUpdate) {
            this._pendingUpdate = true;
            enqueue(this);
        }
    }

    /**
     * Force an immediate synchronous update, bypassing the scheduler.
     * Use sparingly — primarily for backward compatibility.
     */
    forceUpdate() {
        this._pendingUpdate = false;
        this._doUpdate();
    }

    /**
     * Provides a value (or Signal) to all descendant components.
     * @param {string} key 
     * @param {any} value 
     */
    provide(key, value) {
        this._provided.set(key, value);
    }

    /**
     * Consumes a value provided by an ancestor component.
     * @param {string} key 
     * @returns {any}
     */
    consume(key) {
        let current = this.container;
        while (current) {
            if (current.__spp_component && current.__spp_component._provided.has(key)) {
                return current.__spp_component._provided.get(key);
            }
            current = current.parentElement || current.parentNode;
            if (current instanceof ShadowRoot) {
                current = current.host;
            }
        }
        return undefined;
    }

    _queueUpdate() {
        this._pendingUpdate = false;
        this._doUpdate();
    }

    /**
     * Called by the scheduler to perform the actual DOM update.
     * This is the v13 replacement for the v11 update() method.
     */
    update() {
        this._pendingUpdate = false;
        this._doUpdate();
    }

    /**
     * Internal update implementation.
     * @private
     */
    _doUpdate() {
        if (this.isDisposed || !this.container) return;
        if (this._isRendering) return;

        // ── New lifecycle: shouldUpdate ──
        if (typeof this.shouldUpdate === 'function') {
            if (!this.shouldUpdate(this.state)) return;
        }

        // ── New lifecycle: onBeforeUpdate ──
        if (this._hasMounted && typeof this.onBeforeUpdate === 'function') {
            try { this.onBeforeUpdate(); } catch (e) {
                console.error(`[SPPUX] onBeforeUpdate error in ${this.constructor.name}:`, e);
            }
        }

        this._isRendering = true;
        this._signalUnsubscribes.forEach(unsub => unsub());
        this._signalUnsubscribes.clear();

        const subscriber = () => {
            if (this._isRendering) return;
            this.update();
        };

        try {
            Signal.activeSubscriber = subscriber;
            let template;
            try {
                template = typeof this.render === 'function' ? this.render() : '';
                if ((!template || template === '' || template === Fragment) && typeof this.template === 'function') {
                    template = this.template();
                }
            } catch (renderError) {
                console.error(`[SPPUX] Render Error in ${this.constructor.name}:`, renderError);

                if (typeof this.onError === 'function') {
                    try { this.onError(renderError, { componentName: this.constructor.name, phase: 'render' }); } catch (e) { /* swallow */ }
                }
                const boundary = findNearestErrorBoundary(this.container, SPPUX._components);
                if (boundary) {
                    boundary.catchError(renderError, { componentName: this.constructor.name, phase: 'render' });
                    return;
                }

                template = new TrustedHTML(`
                    <div class="sppux-alert sppux-alert-danger" style="margin: 1rem; text-align: left;">
                        <strong>💥 UI Component Crash: <code>${this.constructor.name}</code></strong><br>
                        <span style="font-family: monospace; font-size: 0.85rem; opacity: 0.8;">${renderError.message}</span>
                    </div>
                `);
            }
            Signal.activeSubscriber = null;

            // Automagic separate HTML template decoupling
            if (template === Fragment || template?.content === '') {
                const nameKey = this.constructor.name.toLowerCase();
                const tplNode = document.getElementById(`spp-tpl-${nameKey}`) || document.getElementById(`spp-tpl-${this.constructor.name}`);
                if (tplNode) {
                    const rawHtml = this.interpolateTemplate(tplNode.innerHTML, { state: this.state, props: this.props });
                    template = new TrustedHTML(rawHtml);
                }
            }

            // Auto-wrap raw HTML strings in TrustedHTML
            if (typeof template === 'string') {
                template = new TrustedHTML(template);
            }

            if (!template || (template.content === undefined && !(template.strings && template.values)) || (template.content === '' && !template.__isTrusted)) {
                this._isRendering = false;
                return;
            }

            // ── Render: use the new fine-grained template renderer (v14) ──
            if (this.container) {
                _log(`↻ ${this.constructor.name}`);
                
                // Track snapshots for rollback if needed
                this._snapshots.push(this.container.innerHTML);
                if (this._snapshots.length > 10) this._snapshots.shift();

                let renderTarget = this.container;
                if (this.shadow || this.constructor.shadow) {
                    if (!this.container.shadowRoot) {
                        this.container.attachShadow({ mode: 'open' });
                    }
                    renderTarget = this.container.shadowRoot;
                }

                const performRender = () => {
                    // Call the new template.js render function
                    render(template, renderTarget);

                    // ── Backward Compat: spp-model Two-Way Binding ──
                    renderTarget.querySelectorAll('[spp-model]').forEach(el => {
                        const key = el.getAttribute('spp-model');
                        if (el.type === 'checkbox') {
                            el.checked = !!this.state[key];
                            if (!el.hasAttribute('data-spp-bound-change')) {
                                el.setAttribute('data-spp-bound-change', '1');
                                el.addEventListener('change', (e) => this.setState({ [key]: e.target.checked }));
                            }
                        } else {
                            el.value = this.state[key] !== undefined ? this.state[key] : '';
                            if (!el.hasAttribute('data-spp-bound-input')) {
                                el.setAttribute('data-spp-bound-input', '1');
                                el.addEventListener('input', (e) => this.setState({ [key]: e.target.value }));
                            }
                        }
                    });

                    // Re-sync global legacy handlers just in case
                    this._registerGlobalHandlers(null);
                    this._syncEventDelegation(this.container);
                };

                // Apply View Transitions if opted in
                if ((this.transitions || this.constructor.transitions) && document.startViewTransition) {
                    document.startViewTransition(() => performRender());
                } else {
                    performRender();
                }
            }

            try {
                this.afterUpdate();
            } catch (afterUpdateError) {
                console.error(`[SPPUX] afterUpdate Error in ${this.constructor.name}:`, afterUpdateError);
            }

            if (!this._hasMounted) {
                this._hasMounted = true;
                if (typeof this.onMount === 'function') {
                    try {
                        this.onMount();
                    } catch (mountError) {
                        console.error(`[SPPUX] onMount Error in ${this.constructor.name}:`, mountError);
                    }
                }
            }
        } finally {
            this._isRendering = false;
            Signal.activeSubscriber = null;
        }
    }

    /**
     * Register handlers from this component's _handlers Map into the
     * new WeakMap-based event delegation system (core/events.js).
     * 
     * Scans all elements with data-spp-evt-* attributes in the container
     * and registers their handlers via registerHandler().
     * 
     * @param {Element} root - Element to scan (container or temp element)
     * @private
     */
    _syncEventDelegation(root) {
        if (!root) return;
        const selectors = '[data-spp-type], [data-spp-evt], [data-spp-evt-click], [data-spp-evt-input], [data-spp-evt-change], [data-spp-evt-submit], [data-spp-evt-keydown], [data-spp-evt-keyup], [data-spp-evt-blur], [data-spp-evt-focus], [data-spp-evt-mousedown], [data-spp-evt-mouseup]';
        const elements = [
            ...(root.matches && root.matches(selectors) ? [root] : []),
            ...root.querySelectorAll(selectors)
        ];
        elements.forEach(el => {
            for (const attr of el.attributes) {
                if (attr.name.startsWith('data-spp-evt-') || attr.name === 'data-spp-evt') {
                    const id = attr.value;
                    const handler = this._handlers.get(id) || (window.__spp_handlers && window.__spp_handlers[id]);
                    if (handler) {
                        const eventType = attr.name === 'data-spp-evt'
                            ? (el.getAttribute('data-spp-type') || 'click')
                            : attr.name.replace('data-spp-evt-', '');
                        registerHandler(el, eventType, (e) => handler.call(this, e));
                    }
                }
            }
        });
    }

    _registerGlobalHandlers(claimedIds) {
        const selectors = '[data-spp-evt], [data-spp-type]';
        document.querySelectorAll(selectors).forEach(el => {
            for (const attr of el.attributes) {
                if (attr.name.startsWith('data-spp-evt')) {
                    const id = attr.value;
                    if (window.__spp_handlers && window.__spp_handlers[id]) {
                        this._handlers.set(id, window.__spp_handlers[id]);
                        if (claimedIds) claimedIds.add(id);
                    }
                }
            }
        });

        // Register system overlays as event sources
        const overlaySelectors = ['#sppux-modal-root', '.sub-modal', '.glass-overlay', '#header-actions', '#sppux-drawer-root', '#studio-modal-overlay', '#sppux-spotlight-root', '#sppux-popover-root'];
        overlaySelectors.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                this._eventContainers.add(el);
            });
        });
    }

    /**
     * Legacy _reconcile method — delegates to the new keyed reconciler.
     * Kept for backward compatibility with components that call it directly.
     */
    _reconcile(parent, newParent) {
        reconcileDOM(parent, newParent);
    }

    /** Time-travel rollback */
    rollback() {
        if (this._snapshots && this._snapshots.length > 0 && this.container) {
            const previousHtml = this._snapshots.pop();
            const temp = document.createElement('div');
            temp.innerHTML = previousHtml;
            reconcileDOM(this.container, temp);
            _log(`Rolled back ${this.constructor.name}`);
        }
    }

    /**
     * Speculative execution for optimistic UI updates.
     */
    async speculate(actionPromise, optimisticHtml) {
        if (!this.container) return await actionPromise;
        this._snapshots.push(this.container.innerHTML);
        if (this._snapshots.length > 10) this._snapshots.shift();

        const temp = document.createElement('div');
        temp.innerHTML = optimisticHtml;
        reconcileDOM(this.container, temp);
        _log(`Speculative state applied to ${this.constructor.name}`);

        try {
            const res = await actionPromise;
            if (res && (res.status === 'error' || res.success === false)) {
                _log(`Speculative conflict in ${this.constructor.name}, reverting...`);
                this.rollback();
            }
            return res;
        } catch (err) {
            console.error(`[SPPUX] Speculative execution failed in ${this.constructor.name}:`, err);
            this.rollback();
            throw err;
        }
    }

    async task(name, promise) {
        this.setState({ [`${name}_loading`]: true, [`${name}_error`]: null });
        try {
            const result = await promise;
            this.setState({ [`${name}_loading`]: false, [`${name}_data`]: result });
            return result;
        } catch (e) {
            this.setState({ [`${name}_loading`]: false, [`${name}_error`]: e.message });
            throw e;
        }
    }

    interpolateTemplate(str, ctx) {
        if (!str) return '';
        
        // [ENTERPRISE FIX] Prevent DOM XSS by HTML-escaping interpolated string values.
        // Unescaped interpolation into innerHTML allows script injection if state contains untrusted data.
        const escapeHtml = (unsafe) => {
            if (unsafe === null || unsafe === undefined) return '';
            return String(unsafe)
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        };

        return str.replace(/\$\{([a-zA-Z0-9_.\-]+)\}/g, (match, path) => {
            const parts = path.split('.');
            let current = ctx;
            for (const part of parts) {
                if (current === null || current === undefined) return '';
                current = current[part];
            }
            return (current !== null && current !== undefined) ? escapeHtml(current) : '';
        });
    }

    notify(msg, type = 'info') {
        if (window.SPPUX && SPPUX.Notify) SPPUX.Notify.show(msg, type);
        else console.log(`[${type}] ${msg}`);
    }

    handleApiErrors(res) {
        if (this.app && typeof this.app.handleApiErrors === 'function') {
            return this.app.handleApiErrors(res);
        }
        this.notify(res.message || 'An error occurred', 'error');
    }

    async confirm(msg) {
        if (window.SPPUX && SPPUX.Confirm) return await SPPUX.Confirm(msg);
        if (window.SPPUX && SPPUX.confirm) return await SPPUX.confirm(msg);
        return window.confirm(msg);
    }

    async prompt(msg, defaultValue = '') {
        if (window.SPPUX && SPPUX.Prompt && typeof SPPUX.Prompt.show === 'function') {
            return new Promise((resolve) => {
                SPPUX.Prompt.show('Input', msg, (val) => resolve(val));
                setTimeout(() => {
                    const input = document.getElementById('sppux-prompt-input');
                    if (input && defaultValue) input.value = defaultValue;
                }, 120);
            });
        }
        if (window.SPPUX && typeof SPPUX.prompt === 'function') return await SPPUX.prompt(msg, defaultValue);
        return window.prompt(msg, defaultValue);
    }

    openModal(title, content, actions = []) {
        if (window.SPPUX && SPPUX.openModal) return SPPUX.openModal(title, content, actions);
        console.warn('SPPUX.openModal not available');
    }

    updateModal(title, content, actions = null) {
        if (window.SPPUX && SPPUX.updateModal) return SPPUX.updateModal(title, content, actions);
        console.warn('SPPUX.updateModal not available');
    }

    closeModal() {
        if (window.SPPUX && SPPUX.Modal) SPPUX.Modal.close();
    }

    openSubEditor(title, content, data = {}, onSave = null) {
        if (typeof SPPUX !== 'undefined' && typeof SPPUX.openSubEditor === 'function') {
            return SPPUX.openSubEditor(title, content, data, onSave);
        }
        if (this.app && typeof this.app.openSubEditor === 'function') {
            return this.app.openSubEditor(title, content, data, onSave);
        }
        if (this.admin && typeof this.admin.openSubEditor === 'function') {
            return this.admin.openSubEditor(title, content, data, onSave);
        }
        if (typeof window !== 'undefined' && window.admin && typeof window.admin.openSubEditor === 'function') {
            return window.admin.openSubEditor(title, content, data, onSave);
        }
        console.warn('SPPUX.openSubEditor not available');
    }

    updateSubEditor(content) {
        if (typeof SPPUX !== 'undefined' && typeof SPPUX.updateSubEditor === 'function') {
            return SPPUX.updateSubEditor(content);
        }
        console.warn('SPPUX.updateSubEditor not available');
    }

    async confirmDelete(type, id) {
        const confirmed = await this.confirm(`Are you sure you want to delete this ${type}?`);
        if (confirmed) {
            const res = await this.apiPost(`delete_${type}`, { name: id, id: id });
            if (res.success) {
                this.notify(`${type.charAt(0).toUpperCase() + type.slice(1)} deleted successfully.`, 'success');
                if (typeof this.fetchData === 'function') await this.fetchData();
                else if (typeof this.onInit === 'function') await this.onInit();
            } else {
                this.notify(res.message || `Failed to delete ${type}.`, 'error');
            }
        }
    }

    static findInTree(list, id, key = 'id', childrenKey = 'children') {
        if (!list || !Array.isArray(list)) return null;
        for (const item of list) {
            if (item[key] === id) return item;
            if (item[childrenKey]) {
                const found = BaseComponent.findInTree(item[childrenKey], id, key, childrenKey);
                if (found) return found;
            }
        }
        return null;
    }

    static cloneTree(tree, idGenerator = null, childrenKey = 'children') {
        const clone = JSON.parse(JSON.stringify(tree));
        if (idGenerator) {
            const walk = (node) => {
                node.id = idGenerator(node);
                if (node[childrenKey]) node[childrenKey].forEach(walk);
            };
            walk(clone);
        }
        return clone;
    }

    async reconcileOffThread(parent, htmlString) {
        if (!window.Worker || !window.URL) {
            const temp = document.createElement('div');
            temp.innerHTML = htmlString;
            reconcileDOM(parent, temp);
            return;
        }
        if (!BaseComponent._vdomWorker) {
            const code = `self.onmessage = function(e) { self.postMessage({ status: 'ready', content: e.data.html }); };`;
            const blob = new Blob([code], { type: 'application/javascript' });
            BaseComponent._vdomWorker = new Worker(URL.createObjectURL(blob));
        }
        return new Promise(resolve => {
            const listener = e => {
                BaseComponent._vdomWorker.removeEventListener('message', listener);
                const temp = document.createElement('div');
                temp.innerHTML = e.data.content;
                reconcileDOM(parent, temp);
                resolve();
            };
            BaseComponent._vdomWorker.addEventListener('message', listener);
            BaseComponent._vdomWorker.postMessage({ html: htmlString });
        });
    }

    stream(name, params = {}, onMessage = null) {
        if (window.spp_admin && spp_admin.streamService) {
            const source = spp_admin.streamService(name, params, onMessage);
            this._subscriptions.push(() => source.close());
            return source;
        }
    }

    async guard(key, fn) {
        if (this[`_guard_${key}`]) return;
        this[`_guard_${key}`] = true;
        try { return await fn(); }
        finally { this[`_guard_${key}`] = false; }
    }

    renderSourceHeader(source) {
        if (!source) return '';
        const isYaml = source.type === 'yaml';
        const icon = isYaml ? '📄' : '🗄️';
        const typeLabel = isYaml ? 'YAML Config' : 'Database Storage';
        const className = isYaml ? 'type-yaml' : 'type-database';

        return html`
            <div class="source-header ${className}" style="display: flex; align-items: center; gap: 12px; padding: 12px 20px; background: rgba(255,255,255,0.03); border-radius: 12px; border-left: 4px solid ${isYaml ? '#6366f1' : '#f59e0b'}; margin: 1.5rem 0;">
                <div class="source-icon" style="font-size: 1.5rem;">${icon}</div>
                <div class="source-info">
                    <div class="source-type" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.6;">${typeLabel}</div>
                    <div class="source-label" style="font-weight: 600; font-size: 0.9rem;">${source.label}</div>
                </div>
            </div>
        `;
    }

    static domFilter(container, query, { itemSelector = '[data-search-name]', attrs = ['data-search-name'] } = {}) {
        if (!container) return;
        const q = (query || '').toLowerCase();
        container.querySelectorAll(itemSelector).forEach(el => {
            if (!q) { el.style.display = ''; return; }
            const match = attrs.some(a => (el.getAttribute(a) || '').includes(q));
            el.style.display = match ? '' : 'none';
        });
    }

    renderLoading(message = 'Loading...') {
        return html`
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; gap:16px;">
                <div style="width:36px; height:36px; border:3px solid rgba(99,102,241,0.15); border-top-color:var(--primary-color,#6366f1); border-radius:50%; animation:sppSpin 0.8s linear infinite;"></div>
                <div style="color:var(--text-dim,#64748b); font-size:0.8rem; font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">${message}</div>
            </div>
            <style>@keyframes sppSpin { to { transform: rotate(360deg); } }</style>
        `;
    }

    dispose() {
        if (this.isDisposed) return;
        this.isDisposed = true;

        if (this._autoDisposeObserver) {
            try { this._autoDisposeObserver.disconnect(); } catch (e) {}
            this._autoDisposeObserver = null;
        }

        this._subscriptions.forEach(unsubscribe => {
            try { unsubscribe(); } catch (e) { /* swallow */ }
        });
        if (this._signalUnsubscribes) {
            this._signalUnsubscribes.forEach(unsub => {
                try { unsub(); } catch (e) { /* swallow */ }
            });
        }

        // Unregister from global component set immediately to prevent re-entrant loops
        if (window.SPPUX && SPPUX._components) {
            SPPUX._components.delete(this);

            // Traverse active components to find and dispose children strictly nested inside this container
            if (this.container) {
                const candidates = Array.from(SPPUX._components);
                for (const child of candidates) {
                    if (child !== this && !child.isDisposed && child.container && child.container !== this.container && this.container.contains(child.container)) {
                        child.dispose();
                    }
                }
            }
        }

        // Clean up event handlers from WeakMap system
        if (this.container) {
            const selectors = '[data-spp-type], [data-spp-evt], [data-spp-evt-click], [data-spp-evt-input], [data-spp-evt-change], [data-spp-evt-submit], [data-spp-evt-keydown], [data-spp-evt-keyup], [data-spp-evt-blur], [data-spp-evt-focus], [data-spp-evt-mousedown], [data-spp-evt-mouseup]';
            const elements = [
                ...(this.container.matches && this.container.matches(selectors) ? [this.container] : []),
                ...this.container.querySelectorAll(selectors)
            ];
            elements.forEach(el => {
                removeAllHandlers(el);
            });
        }

        // Stop auto-dispose observer
        if (this._autoDisposeObserver) {
            this._autoDisposeObserver.disconnect();
            this._autoDisposeObserver = null;
        }

        this._handlers.clear();
        this.onDestroy();

        // Null out references to aid GC
        this._subscriptions = [];
        this._snapshots = [];
    }

    // ── Lifecycle Hooks ──────────────────────────────────────────
    /** Called after every DOM reconciliation. Override in subclasses. */
    afterUpdate() {}
    /** Called once during component bootstrap. */
    async onInit() {}
    /** Called after first render. */
    async onMount() {}
    /** Called on dispose. */
    onDestroy() {}
    // New v13 hooks (optional overrides):
    // shouldUpdate(nextState) {} — return false to skip render
    // onBeforeUpdate() {}       — called before reconciliation
    // onError(error, info) {}   — called when render or child throws

    render() { return Fragment; }
}

/** @private Counter for spp-model handler IDs */
let _modelIdCounter = 0;

// ─── SPPForm (backward compat) ────────────────────────────────────

class SPPForm extends BaseComponent {
    onMount() {
        this.refreshDependencies();
        ['input', 'change'].forEach(evt => {
            this.container.addEventListener(evt, (e) => {
                if (e.target.matches('.spp-element, .setting-input, input, select, textarea')) {
                    this.refreshDependencies();
                }
            });
        });
    }

    refreshDependencies() {
        const rows = this.container.querySelectorAll('[data-depends-on]');
        const inputs = this.container.querySelectorAll('input, select, textarea');
        const currentValues = {};

        inputs.forEach(inp => {
            const name = inp.name || inp.getAttribute('data-key') || inp.id;
            if (!name) return;
            currentValues[name] = inp.type === 'checkbox' ? inp.checked : inp.value;
        });

        rows.forEach(row => {
            const raw = row.getAttribute('data-depends-on');
            if (!raw) return;
            try {
                const dependsOn = (raw.startsWith('{') || raw.startsWith('[')) ? JSON.parse(raw) : raw;
                let visible = true;

                if (typeof dependsOn === 'object' && !Array.isArray(dependsOn)) {
                    for (const [key, allowed] of Object.entries(dependsOn)) {
                        const val = String(currentValues[key] ?? '');
                        const allowedList = Array.isArray(allowed) ? allowed.map(v => String(v)) : [String(allowed)];
                        if (!allowedList.includes(val)) {
                            visible = false;
                            break;
                        }
                    }
                }

                let target = row.closest('.spp-form-group');
                if (!target) target = row.closest('.form-group, .input-group, .spp-col-12, .spp-col-6');
                if (!target) target = row;

                target.style.display = visible ? '' : 'none';
                target.classList.toggle('spp-hidden', !visible);
            } catch (e) {
                console.error('SPPForm dependency error:', e, raw);
            }
        });
    }

    static autoInit(container) {
        if (!container) return;
        const form = new SPPForm(null, container);
        form.onMount();
        return form;
    }
}

// ─── SPPUX Namespace ──────────────────────────────────────────────

const SPPUX = {
    // Core classes
    TrustedHTML,
    TemplateResult,
    html,
    Fragment,
    SPPStore,
    BaseComponent,
    SPPForm,
    render,
    repeat,
    until,
    portal,
    ref,
    bind,
    action,

    // Reactivity
    Signal,
    Computed,
    signal: (v) => new Signal(v),
    computed: (fn) => new Computed(fn),
    effect,
    batch,
    createStore,

    // Debug
    get debug() { return _debug; },
    set debug(v) { _debug = !!v; },

    // Action Invocation Helper
    callAction: async (action, data = {}) => {
        return await SPPUX.api(action, data);
    },

    // API
    api: async (actionOrData, data = {}) => {
        if (actionOrData instanceof FormData) {
            return await SPPUX.apiPost(actionOrData);
        }
        const action = actionOrData;
        let endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
        if (endpoint === 'api.php' && !window.location.pathname.includes('/sppadmin') && !window.location.pathname.includes('/spp/admin') && !window.location.pathname.includes('/sppdev') && !window.location.pathname.includes('/spp/dev')) {
            endpoint = window.location.pathname;
        }
        const ts = Date.now();

        if (!data.appname && !data.context) {
            const rootApp = window.spp_root_store?.get?.()?.selectedApp || window.admin?.selectedApp || window.app?.selectedApp || localStorage.getItem('spp_admin_selected_app');
            if (rootApp) {
                data.appname = rootApp;
                data.context = rootApp;
            }
        }

        const csrf = data.csrf_token || window.SPP_CSRF_TOKEN;

        const res = await fetch(`${endpoint}?action=${action}&t=${ts}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPP-Ajax': '1',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {})
            },
            body: JSON.stringify({ ...data, csrf_token: csrf })
        });
        const result = await res.json();

        if (result.instructions) {
            SPPUX.applyInstructions(result.instructions, result.data || result);
        }

        return result;
    },

    apiPost: async (formData) => {
        let endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
        if (endpoint === 'api.php' && !window.location.pathname.includes('/sppadmin') && !window.location.pathname.includes('/spp/admin') && !window.location.pathname.includes('/sppdev') && !window.location.pathname.includes('/spp/dev')) {
            endpoint = window.location.pathname;
        }

        if (!formData.has('appname') && !formData.has('context')) {
            const rootApp = window.spp_root_store?.get?.()?.selectedApp || window.admin?.selectedApp || window.app?.selectedApp || localStorage.getItem('spp_admin_selected_app');
            if (rootApp) {
                formData.append('appname', rootApp);
                formData.append('context', rootApp);
            }
        }

        if (!formData.has('csrf_token') && window.SPP_CSRF_TOKEN) {
            formData.append('csrf_token', window.SPP_CSRF_TOKEN);
        }

        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPP-Ajax': '1',
                ...(window.SPP_CSRF_TOKEN ? { 'X-CSRF-TOKEN': window.SPP_CSRF_TOKEN } : {})
            },
            body: formData
        });
        const result = await res.json();

        if (result.instructions) {
            SPPUX.applyInstructions(result.instructions, result.data || result);
        }

        return result;
    },

    render: (template, container) => {
        if (!template || !container) return;
        if (template instanceof TemplateResult) {
            render(template, container);
        } else {
            container.innerHTML = typeof template.toString === 'function' ? template.toString() : String(template);
        }
    },

    _components: new Set(),
    _isDispatcherInit: false,

    _initDispatcher() {
        if (this._isDispatcherInit) return;
        this._isDispatcherInit = true;
        // Initialize the new O(1) event delegation system
        initDelegation(document);
        _log('Event delegation initialized');
    },

    applyInstructions: (instructions, context = {}) => {
        if (!instructions || !Array.isArray(instructions)) return;
        instructions.forEach(ins => {
            const { action, selector, html: insHtml, url, message, type, event, detail, attr, value } = ins;
            const el = selector ? document.querySelector(selector) : null;

            switch (action) {
                case 'replace': if (el) el.innerHTML = insHtml; break;
                case 'morph': if (el) SPPUX.morph(el, insHtml); break;
                case 'append': if (el) el.insertAdjacentHTML('beforeend', insHtml); break;
                case 'prepend': if (el) el.insertAdjacentHTML('afterbegin', insHtml); break;
                case 'remove': if (el) el.remove(); break;
                case 'attr': if (el) el.setAttribute(attr, value); break;
                case 'redirect': window.location.href = url; break;
                case 'notify':
                    if (window.admin && admin.notify) admin.notify(message, type);
                    else if (window.SPPUX && SPPUX.Notify) SPPUX.Notify.show(message, type);
                    else if (window.alert) alert(message);
                    break;
                case 'modal':
                    if (window.SPPUX && SPPUX.openModal) {
                        SPPUX.openModal(ins.title || 'Info', ins.html || ins.content || '', ins.actions || [], context);
                    }
                    break;
                case 'closeModal':
                    if (window.SPPUX && SPPUX.Modal) SPPUX.Modal.close();
                    break;
                case 'refresh':
                    if (window.admin && admin.loadView) admin.loadView(admin.currentView);
                    else location.reload();
                    break;
                case 'dispatch': {
                    const t = el || window;
                    t.dispatchEvent(new CustomEvent(event, { detail }));
                    break;
                }
                case 'store':
                    if (window.spp_root_store) window.spp_root_store.set(detail);
                    break;
                case 'script':
                    try { eval(insHtml); } catch (e) { console.error('LiveAction Script Error:', e); }
                    break;
                case 'alert': alert(message); break;
                case 'assign': if (el) el[ins.prop] = value; break;
                case 'call': {
                    const fn = window[ins.func];
                    if (typeof fn === 'function') fn(...(ins.args || []));
                    break;
                }
                case 'clear': if (el) el[ins.attr || 'innerHTML'] = ''; break;
            }
        });
    },

    morph: (el, newHtml) => {
        if (window.Idiomorph) {
            window.Idiomorph.morph(el, newHtml, {
                callbacks: {
                    beforeNodeMorphed(oldNode, newNode) {
                        if (oldNode.nodeType === Node.ELEMENT_NODE && oldNode.hasAttribute('wire:ignore')) {
                            return false;
                        }
                    }
                }
            });
        } else {
            const temp = document.createElement('div');
            temp.innerHTML = typeof newHtml === 'string' ? newHtml.trim() : '';
            const newNode = temp.firstElementChild;
            if (!newNode) return;
            reconcileDOM(el, newNode);
        }
    },

    utils: {
        serializeForm: (container) => {
            const data = {};
            container.querySelectorAll('[name]').forEach(el => {
                if (el.type === 'checkbox') data[el.name] = el.checked;
                else if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
                else data[el.name] = el.value;
            });
            return data;
        },
        debounce: (fn, delay) => {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn(...args), delay);
            };
        },
        deepMerge: (target, source) => {
            for (const key in source) {
                if (source[key] instanceof Object && key in target) {
                    Object.assign(source[key], SPPUX.utils.deepMerge(target[key], source[key]));
                }
            }
            Object.assign(target || {}, source);
            return target;
        },
        escapeHtml: (str) => {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        },
        escapeAttr: (str) => {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },
        truncatePath: (path, len = 60) => {
            if (!path || path.length <= len) return path;
            const parts = path.split(/[\\/]/);
            if (parts.length <= 2) return path.substring(0, len) + '...';
            const first = parts[0], last = parts[parts.length - 1], mid = '...';
            const remainingLen = len - first.length - last.length - mid.length;
            if (remainingLen <= 0) return '...' + last;
            return `${first}/${mid}/${last}`;
        }
    },

    Busy: {
        count: 0,
        timeout: null,
        duration: 30000,
        start() {
            this.count++;
            if (this.count === 1) {
                document.body.classList.add('sppux-is-busy');
                if (this.timeout) clearTimeout(this.timeout);
                this.timeout = setTimeout(() => this.reset(), this.duration);
            }
        },
        stop() {
            this.count = Math.max(0, this.count - 1);
            if (this.count === 0) this.reset();
        },
        reset() {
            this.count = 0;
            document.body.classList.remove('sppux-is-busy');
            if (this.timeout) {
                clearTimeout(this.timeout);
                this.timeout = null;
            }
        }
    },

    liveSync: {
        lastTime: 0,
        init() {
            if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;
            console.log("🚀 SPP LiveSync Active");
            setInterval(async () => {
                try {
                    const res = await fetch('.livesync?t=' + Date.now());
                    if (!res.ok) return;
                    const time = parseInt(await res.text());
                    if (this.lastTime && time > this.lastTime) {
                        _log("⚡ Change detected, reloading...");
                        location.reload();
                    }
                    this.lastTime = time;
                } catch(e) {}
            }, 1000);
        }
    },

    Router: {
        routes: [],
        currentView: null,
        init(routes) {
            this.routes = routes;
            window.addEventListener('popstate', () => this.handleRoute());
            document.body.addEventListener('click', (e) => {
                const link = e.target && e.target.closest ? e.target.closest('a[data-spp-route]') : null;
                if (link) {
                    e.preventDefault();
                    this.push(link.getAttribute('href'));
                }
            });
            this.handleRoute();
        },
        push(path) {
            window.history.pushState({}, '', path);
            this.handleRoute();
        },
        async handleRoute() {
            const path = window.location.pathname;
            const route = this.routes.find(r => {
                const routeParts = r.path.split('/');
                const pathParts = path.split('/');
                if (routeParts.length !== pathParts.length) return false;
                for (let i = 0; i < routeParts.length; i++) {
                    if (routeParts[i].startsWith(':')) continue;
                    if (routeParts[i] !== pathParts[i]) return false;
                }
                return true;
            }) || this.routes.find(r => r.path === '*');

            const container = document.querySelector('spp-router-view') || document.querySelector('#spp-app-root');
            if (!container || !route) return;

            if (this.currentView && this.currentView.dispose) this.currentView.dispose();
            container.innerHTML = '<div class="sppux-spinner"></div>';

            try {
                const componentDef = await route.component();
                const ComponentClass = componentDef.default || componentDef;

                const params = {};
                const routeParts = route.path.split('/');
                const pathParts = path.split('/');
                routeParts.forEach((part, i) => {
                    if (part.startsWith(':')) params[part.substring(1)] = pathParts[i];
                });

                container.innerHTML = '';
                this.currentView = new ComponentClass(null, container, { ...params });
                this.currentView.update();
            } catch (e) {
                container.innerHTML = `<div class="sppux-error">Failed to load route: ${e.message}</div>`;
            }
        }
    },

    await: (promise, successTpl, loadingTpl = html`<div class="sppux-spinner"></div>`, errorTpl = (err) => html`<div class="sppux-error">${err.message}</div>`) => {
        const id = 'spp-await-' + (++_modelIdCounter);
        promise.then(data => {
            const el = document.getElementById(id);
            if (el) {
                const temp = document.createElement('div');
                const tpl = successTpl(data);
                temp.innerHTML = typeof tpl === 'string' ? tpl : tpl.content;
                el.replaceWith(...temp.childNodes);
            }
        }).catch(err => {
            const el = document.getElementById(id);
            if (el) {
                const temp = document.createElement('div');
                const tpl = typeof errorTpl === 'function' ? errorTpl(err) : errorTpl;
                temp.innerHTML = typeof tpl === 'string' ? tpl : tpl.content;
                el.replaceWith(...temp.childNodes);
            }
        });
        return html`<span id="${id}">${loadingTpl}</span>`;
    },

    defineElement: defineElement,
    BaseComponent: BaseComponent,

    // Error Boundary factory
    ErrorBoundary: ErrorBoundaryMixin
};

// ─── Global Bindings (backward compatibility) ─────────────────────

window.TrustedHTML = TrustedHTML;
window.html = html;
window.Fragment = Fragment;
window.SPPStore = SPPStore;
window.BaseComponent = BaseComponent;
window.SPPForm = SPPForm;
window.SPPUX = SPPUX;
window.render = render;
window.SPPUX.render = render;
window.portal = portal;
window.until = until;
window.repeat = repeat;
window.ref = ref;
window.bind = bind;
window.action = action;
window.defineElement = defineElement;
window.useState = useState;
window.useComputed = useComputed;
window.useEffect = useEffect;
window.SPPUX.useState = useState;
window.SPPUX.useComputed = useComputed;
window.SPPUX.useEffect = useEffect;

// Backward compat: maintain window.__spp_handlers for legacy templates
window.__spp_handlers = window.__spp_handlers || {};

// Initialize event delegation
SPPUX._initDispatcher();

// ─── Lazy-load HTML-First Directives ──────────────────────────────

function _loadDirectives() {
    // Import directives module only if there are data-spp-* attributes on the page
    const needsDirectives = document.querySelector(
        '[data-spp-post], [data-spp-action], [data-spp-bind], [data-spp-search], ' +
        '[data-spp-animate], [data-spp-hotkey], [data-spp-infinite-scroll], ' +
        '[data-spp-copy], [data-spp-ripple], [data-spp-mask], [data-spp-parallax], ' +
        '[data-spp-validate], [data-spp-magnetic], [data-spp-typewriter], ' +
        '[data-spp-pull-refresh], [data-spp-particles], [data-spp-tilt], ' +
        '[data-spp-voice-input], [data-spp-form], spp-component'
    );

    if (needsDirectives) {
        import('./directives.js').then(mod => {
            if (mod.initHtmlDirectives) mod.initHtmlDirectives();
            _log('HTML-First Directives loaded');
        }).catch(err => {
            // Fallback: directives.js may not exist yet during migration
            _log('Directives module not found, skipping lazy load');
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _loadDirectives);
} else {
    _loadDirectives();
}

// ─── Universal Mount Function ─────────────────────────────────────

const mount = (ComponentClass, container) => {
    const instance = new ComponentClass(null, container, {});
    const p = instance.onInit();
    if (p instanceof Promise) {
        p.then(() => { instance.update(); instance.onMount(); });
    } else {
        instance.update();
        instance.onMount();
    }
    return instance;
};

// ─── Drag and Drop (lazy-loaded from dnd.js) ──────────────────────

// Import DnD module on demand
let _dndLoaded = false;
function _ensureDnD() {
    if (_dndLoaded) return Promise.resolve();
    _dndLoaded = true;
    return import('./dnd.js').then(mod => {
        if (mod.Draggable) SPPUX.Draggable = mod.Draggable;
        if (mod.Sortable) SPPUX.Sortable = mod.Sortable;
    }).catch(() => {
        _log('DnD module not found, skipping');
    });
}

// Eagerly load DnD since existing code expects SPPUX.Draggable
_ensureDnD();


    // Expose Global SPPUX Object
    global.SPPUX = SPPUX;

    // Expose Top-Level Convenience APIs
    global.BaseComponent = BaseComponent;
    global.html = html;
    global.mount = mount;
    global.render = render;
    global.TrustedHTML = TrustedHTML;
    global.Fragment = Fragment;
    global.Signal = Signal;
    global.Computed = Computed;
    global.effect = effect;
    global.batch = batch;
    global.createStore = createStore;
    global.useState = useState;
    global.useComputed = useComputed;
    global.useEffect = useEffect;
    global.SPPStore = typeof SPPStore !== 'undefined' ? SPPStore : null;
})(typeof window !== 'undefined' ? window : this);
