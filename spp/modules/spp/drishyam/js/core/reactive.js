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

import { enqueue, startBatch, endBatch } from './scheduler.js';

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
export class Signal {
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
export class Computed {
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
export function effect(fn) {
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
export function batch(fn) {
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
export function createStore(initialState = {}, options = {}) {
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
export class SPPStore {
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
