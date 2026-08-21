/**
 * SPP-UX Developer Tools — Debug Inspector & Profiler
 * 
 * Dev-only module providing component inspection, render counting,
 * and performance profiling. Not loaded in production.
 * 
 * Usage: import('./devtools.js') or SPPUX.debug = true
 * 
 * @module devtools
 * @version 13.0.0
 */

const _renderCounts = new Map();
const _renderTimings = new Map();

/**
 * Component Inspector — shows component tree, state, and props.
 * 
 * @param {Element} element - DOM element to inspect
 * @returns {Object|null} Component info or null
 */
export function inspect(element) {
    if (!window.SPPUX || !SPPUX._components) return null;

    for (const comp of SPPUX._components) {
        if (comp.container === element || comp.container?.contains(element)) {
            return {
                name: comp.constructor.name,
                state: JSON.parse(JSON.stringify(comp.state || {})),
                props: JSON.parse(JSON.stringify(comp.props || {})),
                container: comp.container,
                isDisposed: !!comp.isDisposed,
                handlerCount: comp._handlers?.size || 0,
                subscriptionCount: comp._subscriptions?.length || 0,
                renderCount: _renderCounts.get(comp) || 0,
                avgRenderTime: _getAvgRenderTime(comp)
            };
        }
    }
    return null;
}

/**
 * Get a snapshot of all active components.
 * @returns {Object[]}
 */
export function listComponents() {
    if (!window.SPPUX || !SPPUX._components) return [];
    return Array.from(SPPUX._components).map(comp => ({
        name: comp.constructor.name,
        container: comp.container?.tagName + (comp.container?.id ? `#${comp.container.id}` : ''),
        stateKeys: Object.keys(comp.state || {}),
        handlers: comp._handlers?.size || 0,
        renders: _renderCounts.get(comp) || 0,
        disposed: !!comp.isDisposed
    }));
}

/**
 * Track a render for a component (called by patched update()).
 * @param {BaseComponent} comp
 * @param {number} duration - Render duration in ms
 */
export function trackRender(comp, duration) {
    const count = (_renderCounts.get(comp) || 0) + 1;
    _renderCounts.set(comp, count);

    let timings = _renderTimings.get(comp);
    if (!timings) {
        timings = [];
        _renderTimings.set(comp, timings);
    }
    timings.push(duration);
    if (timings.length > 100) timings.shift();

    // Warn if rendering too frequently
    if (count % 50 === 0) {
        console.warn(
            `[SPPUX DevTools] ${comp.constructor.name} has rendered ${count} times. ` +
            `Avg: ${_getAvgRenderTime(comp).toFixed(2)}ms`
        );
    }
}

/**
 * Get average render time for a component.
 * @param {BaseComponent} comp
 * @returns {number} Average ms per render
 * @private
 */
function _getAvgRenderTime(comp) {
    const timings = _renderTimings.get(comp);
    if (!timings || timings.length === 0) return 0;
    return timings.reduce((a, b) => a + b, 0) / timings.length;
}

/**
 * Performance report for all components.
 * @returns {Object[]}
 */
export function performanceReport() {
    if (!window.SPPUX || !SPPUX._components) return [];
    return Array.from(SPPUX._components)
        .map(comp => ({
            name: comp.constructor.name,
            renders: _renderCounts.get(comp) || 0,
            avgMs: _getAvgRenderTime(comp).toFixed(2),
            totalMs: ((_renderTimings.get(comp) || []).reduce((a, b) => a + b, 0)).toFixed(2)
        }))
        .sort((a, b) => parseFloat(b.totalMs) - parseFloat(a.totalMs));
}

/**
 * Print a formatted component tree to the console.
 */
export function printTree() {
    const comps = listComponents();
    console.group('🌳 SPPUX Component Tree');
    comps.forEach(c => {
        const status = c.disposed ? '💀' : '✅';
        console.log(
            `${status} ${c.name} (${c.container}) — ` +
            `${c.renders} renders, ${c.handlers} handlers, ` +
            `state: [${c.stateKeys.join(', ')}]`
        );
    });
    console.groupEnd();
}

// Auto-register on window for console access
if (typeof window !== 'undefined') {
    window.__SPPUX_DEVTOOLS__ = {
        inspect,
        listComponents,
        performanceReport,
        printTree,
        trackRender
    };
    console.log('🔧 SPPUX DevTools loaded. Use __SPPUX_DEVTOOLS__.printTree() to inspect.');
}
