/**
 * SPP-UX Template Engine — Tagged Template Literals (v14)
 * 
 * Provides the `html` tagged template literal. In v14, this returns a
 * `TemplateResult` object instead of an evaluated HTML string. 
 * The `render()` function handles caching and fine-grained DOM updates.
 * 
 * @module core/template
 */

import { TemplateResult, getTemplate, TemplateInstance } from './parts.js';

// ─── TrustedHTML (v13 Backward Compatibility) ──────────────

export class TrustedHTML {
    constructor(content) {
        this.content = content;
        this.__isTrusted = true;
    }
    toString() { return this.content; }
    toJSON() { return this.content; }
}

export const Fragment = new TrustedHTML('');

// ─── Legacy Handler Interop ────────────────────────────────

export const _pendingHandlers = new Map();
export function consumePendingHandlers() {
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
export const html = (strings, ...values) => {
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
export function repeat(items, keyFn, templateFn) {
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
export function until(promise, defaultContent) {
    return { _isUntil: true, promise, defaultContent };
}

/**
 * Escapes the DOM hierarchy by rendering a TemplateResult into a specific target element (e.g., document.body).
 */
export function portal(templateResult, targetElement) {
    return { _isPortal: true, templateResult, targetElement };
}

/**
 * Binds the actual DOM element to a callback immediately upon creation.
 */
export function ref(callback) {
    return { _isRef: true, callback };
}

/**
 * Two-way binds a Signal to an input/checkbox element.
 */
export function bind(signal) {
    return { _isBind: true, signal };
}

/**
 * Form action directive. Automatically prevents default, extracts FormData, and calls API.
 */
export function action(apiFunction) {
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
export function render(result, container) {
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
