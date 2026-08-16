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

import { Signal, Computed, effect, batch, createStore, SPPStore } from './core/reactive.js';
import { enqueue, flush, forceFlush, startBatch, endBatch } from './core/scheduler.js';
import { TrustedHTML, html, Fragment, consumePendingHandlers, _pendingHandlers } from './core/template.js';
import { registerHandler, removeHandler, removeAllHandlers, initDelegation, _handlerRegistry } from './core/events.js';
import { reconcileDOM, patchAttributes, longestIncreasingSubsequence } from './core/reconciler.js';
import { ErrorBoundaryMixin, findNearestErrorBoundary } from './core/error-boundary.js';

// ─── Re-exports for ES Module consumers ───────────────────────────

export { TrustedHTML, html, Fragment, Signal, Computed, SPPStore };
export { effect, batch, createStore };

// ─── Debug Mode ───────────────────────────────────────────────────

/** @type {boolean} Set to true to enable console logging */
let _debug = false;

function _log(...args) {
    if (_debug) console.log('[SPPUX]', ...args);
}

// ─── BaseComponent (v13) ──────────────────────────────────────────

export class BaseComponent {
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
        return this.root?.get?.()?.selectedApp || 'default';
    }

    _initHelpers() {
        // ── API Helper (Proxy-based, backward compatible) ──
        const apiHandler = async (actionOrData, data = {}, options = { lock: true }) => {
            if (options.lock) SPPUX.Busy.start();
            try {
                if (actionOrData instanceof FormData) {
                    return await SPPUX.apiPost(actionOrData);
                }
                if (data instanceof FormData) {
                    if (!data.has('action')) data.append('action', actionOrData);
                    return await SPPUX.apiPost(data);
                }
                if (this.admin && typeof this.admin.api === 'function') {
                    return await this.admin.api(actionOrData, data);
                }
                return await SPPUX.api(actionOrData, data);
            } finally {
                if (options.lock) SPPUX.Busy.stop();
            }
        };

        this.api = new Proxy(apiHandler, {
            get: (target, prop) => {
                if (typeof prop !== 'string' || prop in target) return target[prop];
                const action = prop.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
                return (data = {}, options = { lock: true }) => target(action, data, options);
            }
        });

        this.apiPost = apiHandler;

        this.service = async (service, params = {}, options = { lock: true }) => {
            if (options.lock) SPPUX.Busy.start();
            try {
                return await SPPUX.api('call_service', { service, ...params });
            } finally {
                if (options.lock) SPPUX.Busy.stop();
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
        this.container.querySelectorAll('[data-spp-evt]').forEach(el => {
            const id = el.getAttribute('data-spp-evt');
            if (window.__spp_handlers && window.__spp_handlers[id]) {
                this._handlers.set(id, window.__spp_handlers[id]);
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
        if (this.isDisposed) return;
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
                template = this.render();
            } catch (renderError) {
                console.error(`[SPPUX] Render Error in ${this.constructor.name}:`, renderError);

                // ── New: Error boundary support ──
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

            if (!template || template.content === undefined || (template.content === '' && !template.__isTrusted)) {
                this._isRendering = false;
                return;
            }

            const temp = document.createElement('div');
            temp.innerHTML = template.toString();

            // ── Consume pending handlers from template.js (v13 path) ──
            const newHandlers = consumePendingHandlers();

            // ── Register event handlers from the rendered template ──
            temp.querySelectorAll('*').forEach(el => {
                // Automagic Two-Way Data Binding (spp-model)
                if (el.hasAttribute('spp-model')) {
                    const key = el.getAttribute('spp-model');
                    if (el.type === 'checkbox') {
                        el.checked = !!this.state[key];
                        const eventId = `evt_model_${++_modelIdCounter}`;
                        this._handlers.set(eventId, (e) => this.setState({ [key]: e.target.checked }));
                        el.setAttribute('data-spp-evt-change', eventId);
                    } else {
                        el.value = this.state[key] !== undefined ? this.state[key] : '';
                        const eventId = `evt_model_${++_modelIdCounter}`;
                        this._handlers.set(eventId, (e) => this.setState({ [key]: e.target.value }));
                        el.setAttribute('data-spp-evt-input', eventId);
                    }
                }

                // Claim handlers from the template's _pendingHandlers (v13)
                for (const attr of el.attributes) {
                    if (attr.name.startsWith('data-spp-evt')) {
                        const id = attr.value;
                        if (newHandlers.has(id)) {
                            this._handlers.set(id, newHandlers.get(id));
                        }
                        // Backward compat: also check window.__spp_handlers (v11)
                        else if (window.__spp_handlers && window.__spp_handlers[id]) {
                            this._handlers.set(id, window.__spp_handlers[id]);
                        }
                    }
                }
            });

            // Also scan global containers (modals, header) for handlers
            this._registerGlobalHandlers(null);

            // ── Register handlers in the new O(1) event system ──
            this._syncEventDelegation(temp);

            // ── Reconcile: use the new keyed reconciler ──
            if (this.container) {
                _log(`↻ ${this.constructor.name}`);
                this._snapshots.push(this.container.innerHTML);
                if (this._snapshots.length > 10) this._snapshots.shift();
                reconcileDOM(this.container, temp);
                // After reconciliation, re-sync event handlers on live DOM nodes
                this._syncEventDelegation(this.container);
            }

            try {
                this.afterUpdate();
            } catch (afterUpdateError) {
                console.error(`[SPPUX] afterUpdate Error in ${this.constructor.name}:`, afterUpdateError);
            }

            this._hasMounted = true;
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
        const selectors = '[data-spp-type]';
        root.querySelectorAll(selectors).forEach(el => {
            for (const attr of el.attributes) {
                if (attr.name.startsWith('data-spp-evt-') || attr.name === 'data-spp-evt') {
                    const id = attr.value;
                    const handler = this._handlers.get(id);
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
        return str.replace(/\$\{([a-zA-Z0-9_.\-]+)\}/g, (match, path) => {
            const parts = path.split('.');
            let current = ctx;
            for (const part of parts) {
                if (current === null || current === undefined) return '';
                current = current[part];
            }
            return (current !== null && current !== undefined) ? String(current) : '';
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
        this.isDisposed = true;
        this._subscriptions.forEach(unsubscribe => {
            try { unsubscribe(); } catch (e) { /* swallow */ }
        });
        if (this._signalUnsubscribes) {
            this._signalUnsubscribes.forEach(unsub => {
                try { unsub(); } catch (e) { /* swallow */ }
            });
        }

        // Unregister from global component set
        if (window.SPPUX && SPPUX._components) {
            SPPUX._components.delete(this);
        }

        // Clean up event handlers from WeakMap system
        if (this.container) {
            this.container.querySelectorAll('[data-spp-type]').forEach(el => {
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

export class SPPForm extends BaseComponent {
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

export const SPPUX = {
    // Core classes
    TrustedHTML,
    html,
    Fragment,
    SPPStore,
    BaseComponent,
    SPPForm,

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
            const rootState = window.spp_root_store?.get?.();
            if (rootState?.selectedApp) data.appname = rootState.selectedApp;
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
            const rootState = window.spp_root_store?.get?.();
            if (rootState?.selectedApp) formData.append('appname', rootState.selectedApp);
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
        container.innerHTML = template.toString();
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
            temp.innerHTML = newHtml;
            const newNode = temp.firstElementChild;
            if (!newNode) return;
            patchAttributes(el, newNode);
            if (newNode.children.length === 0 && el.children.length === 0) {
                if (el.textContent !== newNode.textContent) {
                    el.textContent = newNode.textContent;
                }
            } else {
                if (el.innerHTML !== newNode.innerHTML) {
                    el.innerHTML = newNode.innerHTML;
                }
            }
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

    defineElement: (tagName, ComponentClass) => {
        if (customElements.get(tagName)) return;
        class SPPUXElement extends HTMLElement {
            connectedCallback() {
                if (this._comp) return;
                const props = {};
                for (let attr of this.attributes) props[attr.name] = attr.value;
                this._comp = new ComponentClass(null, this, props);
                this._comp.update();
            }
            disconnectedCallback() {
                if (this._comp && this._comp.dispose) {
                    this._comp.dispose();
                    this._comp = null;
                }
            }
        }
        customElements.define(tagName, SPPUXElement);
    },

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

export const mount = (ComponentClass, container) => {
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
