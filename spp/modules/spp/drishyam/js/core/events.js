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
export const _handlerRegistry = new WeakMap();

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
    'input', 'change', 'focus', 'blur', 'keydown', 'keyup', 'keypress',
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
        let target = e.target;
        let handled = false;

        // Walk up the DOM tree from the event target to the root
        while (target && target !== root && target !== document) {
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

                    // Stop propagation if the handler requested it or
                    // if it's not a drag event (matching v11 behavior)
                    if (e.cancelBubble || (!eventType.startsWith('drag') && eventType !== 'drop')) {
                        break;
                    }
                }
            }

            target = target.parentElement;
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
export function registerHandler(element, eventType, handler) {
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
export function removeHandler(element, eventType) {
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
export function removeAllHandlers(element) {
    _handlerRegistry.delete(element);
}

/**
 * Get the handler registered on an element for a specific event type.
 * 
 * @param {Element} element - DOM element
 * @param {string} eventType - Event type
 * @returns {Function|undefined}
 */
export function getHandler(element, eventType) {
    const handlers = _handlerRegistry.get(element);
    return handlers ? handlers.get(eventType) : undefined;
}

/**
 * Initialize the event delegation system.
 * Pre-delegates all common event types on the specified root element.
 * 
 * @param {EventTarget} [rootElement=document] - Root element for delegation
 */
export function initDelegation(rootElement) {
    _rootElement = rootElement || document;
    for (const evt of _commonEvents) {
        _ensureDelegated(evt);
    }
}
