/**
 * SPP-UX Standalone Runtime
 * Built on 2026-06-01 04:11:29
 */
(function(global) {
/**
 * SPP-UX Core Runtime (v11-Core)
 *
 * The foundational reactive engine. This file contains only the core classes
 * and reconciliation logic needed for component-based development.
 *
 * Visual UI components are located in sppux-ui.js.
 */
class TrustedHTML {
    constructor(content) {
        this.content = content;
        this.__isTrusted = true;
    }
    toString() {
        return this.content;
    }
    toJSON() {
        return this.content;
    }
}

const html = (strings, ...values) => {
    const escape = (value) => {
        if (value && typeof value === 'object' && (value.__isTrusted || value.content !== undefined)) {
            return value.content;
        }
        if (value === undefined || value === null) return '';
        if (Array.isArray(value)) return value.map(v => escape(v)).join('');
        
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    let raw = "";
    let skipNextQuote = false;
    for (let i = 0; i < strings.length; i++) {
        let str = strings[i];
        if (skipNextQuote) {
            if (str.startsWith('"') || str.startsWith("'")) str = str.substring(1);
            skipNextQuote = false;
        }
        raw += str;

        if (i < values.length) {
            let value = values[i];
            const lastPart = strings[i];
            
            // Handle Event Handlers (@click=${handler})
            const eventMatch = lastPart.match(/@([a-z]+)=["']?$/i);
            if (eventMatch) {
                const eventId = `evt_${Math.random().toString(36).substr(2, 9)}`;
                window.__spp_handlers = window.__spp_handlers || {};
                window.__spp_handlers[eventId] = value;
                
                raw = raw.substring(0, raw.lastIndexOf('@' + eventMatch[1] + '='));
                raw += `data-spp-evt-${eventMatch[1]}="${eventId}" data-spp-type="${eventMatch[1]}"`;
                skipNextQuote = true;
                continue;
            }

            // Handle Boolean Attributes (?checked=${condition})
            const boolMatch = lastPart.match(/\?([a-z-]+)=["']?$/i);
            if (boolMatch) {
                raw = raw.substring(0, raw.lastIndexOf('?' + boolMatch[1] + '='));
                if (value && value !== 'false' && value !== '0') {
                    raw += boolMatch[1];
                }
                skipNextQuote = true;
                continue;
            }

            // Handle Property Bindings (.value=${value})
            const propMatch = lastPart.match(/\.([a-zA-Z0-9_-]+)=["']?$/i);
            if (propMatch) {
                raw = raw.substring(0, raw.lastIndexOf('.' + propMatch[1] + '='));
                raw += `${propMatch[1]}="${escape(value)}"`;
                skipNextQuote = true;
                continue;
            }

            raw += escape(value);
        }
    }

    return new TrustedHTML(raw);
};
window.html = html;

const Fragment = new TrustedHTML('');

class SPPStore {
    constructor(initialState = {}) {
        this.state = initialState;
        this.listeners = new Set();
    }
    get() { return this.state; }
    set(newState) {
        this.state = { ...this.state, ...newState };
        this.notify();
    }
    subscribe(callback) {
        this.listeners.add(callback);
        return () => this.listeners.delete(callback);
    }
    notify() {
        this.listeners.forEach(callback => callback(this.state));
    }
}

/**
 * Signal-based Reactivity (Phase 1)
 */
class Signal {
    constructor(value) {
        this._value = value;
        this.subscribers = new Set();
    }
    get value() {
        if (Signal.activeSubscriber) {
            this.subscribers.add(Signal.activeSubscriber);
        }
        return this._value;
    }
    set value(newValue) {
        if (this._value !== newValue) {
            this._value = newValue;
            this.subscribers.forEach(sub => sub());
        }
    }
}
Signal.activeSubscriber = null;

class Computed extends Signal {
    constructor(fn) {
        super();
        this.fn = fn;
        this.effect = () => {
            const oldVal = this._value;
            this._value = this.fn();
            if (oldVal !== this._value) {
                this.subscribers.forEach(sub => sub());
            }
        };
        this.effect();
    }
    get value() {
        Signal.activeSubscriber?.addDependency?.(this);
        return super.value;
    }
}

class BaseComponent {
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
        this._initHelpers();
        
        // Register with global dispatcher
        if (!SPPUX._components) SPPUX._components = new Set();
        SPPUX._components.add(this);
    }

    get selectedApp() {
        return this.root?.get?.()?.selectedApp || 'default';
    }

    _initHelpers() {

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

        this._onEvent = (e) => {
            // Search all registered containers for a match
            let isOurEvent = false;
            for (const container of this._eventContainers) {
                if (container && (container === e.target || container.contains(e.target))) {
                    isOurEvent = true;
                    break;
                }
            }
            
            // If not in our specific containers, check if we own the handler for this target
            // If not in our specific containers, check if we own the handler for this target
            const el = e.target.closest(`[data-spp-evt], [data-spp-evt-${e.type}]`);
            if (!isOurEvent && el) {
                const id = el.getAttribute(`data-spp-evt-${e.type}`) || el.getAttribute('data-spp-evt');
                if (this._handlers.has(id)) {
                    isOurEvent = true;
                }
            }

            if (!isOurEvent) return;

            if (el) {
                const type = el.getAttribute('data-spp-type');
                const effectiveType = type || 'click';
                const idByType = el.getAttribute(`data-spp-evt-${e.type}`);
                const idLegacy = (effectiveType === e.type) ? el.getAttribute('data-spp-evt') : null;
                const id = idByType || idLegacy;
                
                if (id) {
                    const handler = this._handlers.get(id);
                    if (handler) {
                        const _noPrevent = ['input', 'change', 'focus', 'blur', 'keydown', 'keyup', 'keypress', 'dragstart'];
                        if (!_noPrevent.includes(e.type)) e.preventDefault();
                        if (!e.type.startsWith('drag') && e.type !== 'drop') e.stopPropagation();
                        handler.call(this, e);
                    }
                }
            }
        };
        
        // Global dispatcher handles events for all components
        if (window.SPPUX && !SPPUX._isDispatcherInit) {
            SPPUX._initDispatcher();
        }

        // Signal Auto-Tracking
        this._signalUnsubscribes = new Set();
        this._isRendering = false;

        // Check for Hydration
        if (this.container && this.container.children && this.container.children.length > 0) {
            this._hydrate();
        }
    }

    _hydrate() {
        this.container.querySelectorAll('[data-spp-evt]').forEach(el => {
            const id = el.getAttribute('data-spp-evt');
            // We can't easily recover function closures from SSR, 
            // but we can register global handlers if they were serialized.
            // For now, this prepares the map for subsequent renders.
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

    setState(newState) {
        if (this.isDisposed) return;
        this.state = { ...this.state, ...newState };
        this.update();
    }

    _registerGlobalHandlers(claimedIds) {
        // Claim all handlers currently in the global pool that are present in the DOM
        // Scan both legacy [data-spp-evt] and type-specific [data-spp-evt-*] attributes
        const selectors = '[data-spp-evt], [data-spp-evt-click], [data-spp-evt-change], [data-spp-evt-input], [data-spp-evt-submit]';
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

        // Also register system overlays and their descendants as event sources
        const overlaySelectors = ['#sppux-modal-root', '.sub-modal', '.glass-overlay', '#header-actions', '#sppux-drawer-root', '#studio-modal-overlay'];
        overlaySelectors.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                this._eventContainers.add(el);
                // Also scan the overlay content itself if it was injected via non-framework means
                el.querySelectorAll(selectors).forEach(child => {
                    for (const attr of child.attributes) {
                        if (attr.name.startsWith('data-spp-evt')) {
                            const id = attr.value;
                            if (window.__spp_handlers && window.__spp_handlers[id]) {
                                this._handlers.set(id, window.__spp_handlers[id]);
                                if (claimedIds) claimedIds.add(id);
                            }
                        }
                    }
                });
            });
        });
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
        // Support simple string interpolation tokens like ${state.stats.total} or ${state.loading}
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

    update() {
        if (this.isDisposed) return;
        if (this._isRendering) return; // Prevent infinite loops
        
        this._isRendering = true;
        this._signalUnsubscribes.forEach(unsub => unsub());
        this._signalUnsubscribes.clear();

        const subscriber = () => {
            if (this._isRendering) return; // Only trigger from Signal changes after first render
            this.update();
        };

        try {
            Signal.activeSubscriber = subscriber;
            let template = this.render();
            Signal.activeSubscriber = null;
            
            // Automagic separate HTML template decoupling ingestion integration
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

            // Register handlers from the rendered template and claim from global pool
            const claimedIds = new Set();
            temp.querySelectorAll('*').forEach(el => {
                // Automagic Two-Way Data Binding for Novice DX
                if (el.hasAttribute('spp-model')) {
                    const key = el.getAttribute('spp-model');
                    if (el.type === 'checkbox') {
                        el.checked = !!this.state[key];
                        const eventId = `evt_model_${Math.random().toString(36).substr(2, 9)}`;
                        this._handlers.set(eventId, (e) => this.setState({ [key]: e.target.checked }));
                        el.setAttribute('data-spp-evt-change', eventId);
                    } else {
                        el.value = this.state[key] !== undefined ? this.state[key] : '';
                        const eventId = `evt_model_${Math.random().toString(36).substr(2, 9)}`;
                        this._handlers.set(eventId, (e) => this.setState({ [key]: e.target.value }));
                        el.setAttribute('data-spp-evt-input', eventId);
                    }
                }

                for (const attr of el.attributes) {
                    if (attr.name.startsWith('data-spp-evt')) {
                        const id = attr.value;
                        if (window.__spp_handlers && window.__spp_handlers[id]) {
                            this._handlers.set(id, window.__spp_handlers[id]);
                            claimedIds.add(id);
                        }
                    }
                }
            });
            
            // Also scan global containers (modals, header) for any handlers added during render()
            this._registerGlobalHandlers(claimedIds);
            
            // Only remove dynamic random-ID handlers claimed by this component, preserving static named shared handlers
            for (const id of claimedIds) {
                if (window.__spp_handlers && id.startsWith('evt_')) {
                    delete window.__spp_handlers[id];
                }
            }

            // Reconcile new virtual DOM with actual DOM
            console.log(`[BaseComponent] Updating ${this.constructor.name}...`);
            if (this.container) {
                this._snapshots.push(this.container.innerHTML);
                if (this._snapshots.length > 10) this._snapshots.shift();
            }
            this._reconcile(this.container, temp);
            this.afterUpdate();
        } finally {
            this._isRendering = false;
            Signal.activeSubscriber = null;
        }
    }

    /** Time-travel rolls back layout state exactly to the previous snapshot buffer */
    rollback() {
        if (this._snapshots && this._snapshots.length > 0 && this.container) {
            const previousHtml = this._snapshots.pop();
            const temp = document.createElement('div');
            temp.innerHTML = previousHtml;
            this._reconcile(this.container, temp);
            console.log(`[BaseComponent] Rolled back ${this.constructor.name} layout state successfully.`);
        }
    }

    /**
     * Speculative execution tracker for zero-latency UI mutations.
     * instantly applies predictive optimistic layout states, evaluates action tasks in the background,
     * and autonomously triggers rollback recovery if network payload validations diverge.
     */
    async speculate(actionPromise, optimisticHtml) {
        if (!this.container) return await actionPromise;
        // Capture instantaneous recovery state snapshot
        this._snapshots.push(this.container.innerHTML);
        if (this._snapshots.length > 10) this._snapshots.shift();

        // Apply instant optimistic VDOM mutation
        const temp = document.createElement('div');
        temp.innerHTML = optimisticHtml;
        this._reconcile(this.container, temp);
        console.log(`[BaseComponent] Speculative state applied with 0ms visual latency.`);

        try {
            const res = await actionPromise;
            if (res && (res.status === 'error' || res.success === false)) {
                console.warn(`[BaseComponent] Speculative network payload conflict detected. Reverting state...`);
                this.rollback();
            }
            return res;
        } catch (err) {
            console.error(`[BaseComponent] Speculative network execution transaction failed. Reverting state...`, err);
            this.rollback();
            throw err;
        }
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

    confirm(message) {
        if (window.SPPUX && SPPUX.Confirm) return SPPUX.Confirm(message);
        return window.confirm(message);
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

    /**
     * Recursive search for an item in a tree structure.
     */
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

    /**
     * Deep clone a tree structure and optionally regenerate IDs.
     */
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

    _reconcile(parent, newParent) {
        const oldNodes = Array.from(parent.childNodes);
        const newNodes = Array.from(newParent.childNodes);

        newNodes.forEach((newNode, i) => {
            const oldNode = oldNodes[i];

            if (!oldNode) {
                parent.appendChild(newNode.cloneNode(true));
                return;
            }

            if (oldNode.nodeType !== newNode.nodeType || oldNode.nodeName !== newNode.nodeName) {
                parent.replaceChild(newNode.cloneNode(true), oldNode);
                return;
            }

            if (oldNode.nodeType === Node.TEXT_NODE) {
                if (oldNode.textContent !== newNode.textContent) {
                    oldNode.textContent = newNode.textContent;
                }
                return;
            }

            if (oldNode.nodeType === Node.ELEMENT_NODE) {
                const oldAttrs = oldNode.attributes;
                const newAttrs = newNode.attributes;

                for (const attr of newAttrs) {
                    if (oldNode.getAttribute(attr.name) !== attr.value) {
                        oldNode.setAttribute(attr.name, attr.value);
                    }
                }
                for (const attr of oldAttrs) {
                    if (!newNode.hasAttribute(attr.name)) {
                        oldNode.removeAttribute(attr.name);
                    }
                }

                if (oldNode.value !== undefined && newNode.value !== undefined && oldNode.value !== newNode.value) {
                    if (document.activeElement !== oldNode) {
                        oldNode.value = newNode.value;
                    }
                }

                // Allow custom rich text or contenteditable elements to fully preserve their own internal DOM tree structure
                if (!oldNode.classList?.contains('lekhni-body-editable') && 
                    !oldNode.classList?.contains('lekhni-full-ide-host') && 
                    !oldNode.classList?.contains('lekhni-embedded-block') && 
                    oldNode.getAttribute?.('contenteditable') !== 'true' && 
                    oldNode.getAttribute?.('data-spp-preserve') !== 'true') {
                    this._reconcile(oldNode, newNode);
                }
            }
        });

        while (parent.childNodes.length > newNodes.length) {
            parent.removeChild(parent.lastChild);
        }
    }

    async reconcileOffThread(parent, htmlString) {
        if (!window.Worker || !window.URL) {
            const temp = document.createElement('div');
            temp.innerHTML = htmlString;
            this._reconcile(parent, temp);
            return;
        }
        if (!BaseComponent._vdomWorker) {
            const code = `
                self.onmessage = function(e) {
                    // Simple off-thread parsing validation
                    self.postMessage({ status: 'ready', content: e.data.html });
                };
            `;
            const blob = new Blob([code], { type: 'application/javascript' });
            BaseComponent._vdomWorker = new Worker(URL.createObjectURL(blob));
        }
        return new Promise(resolve => {
            const listener = e => {
                BaseComponent._vdomWorker.removeEventListener('message', listener);
                const temp = document.createElement('div');
                temp.innerHTML = e.data.content;
                this._reconcile(parent, temp);
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

    async service(name, params = {}) {
        // Generic API call for backend services
        return SPPUX.api('service', { name, ...params });
    }

    dispose() {
        this.isDisposed = true;
        this._subscriptions.forEach(unsubscribe => unsubscribe());
        this._signalUnsubscribes.forEach(unsub => unsub());
        
        // Unregister from global dispatcher
        if (window.SPPUX && SPPUX._components) {
            SPPUX._components.delete(this);
        }

        this._handlers.clear();
        this.onDestroy();
    }

    /** Lifecycle: called after every DOM reconciliation. Override in subclasses. */
    afterUpdate() {}

    /** Lifecycle: called once during component bootstrap. */
    async onInit() {}

    /** Lifecycle: called after first render. */
    async onMount() {}

    /** Lifecycle: called on dispose. */
    onDestroy() {}

    /**
     * Generic loading state renderer.
     * @param {string} message - Loading message to display
     * @returns {TrustedHTML} A spinner + message template
     */
    renderLoading(message = 'Loading...') {
        return html`
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; gap:16px;">
                <div style="width:36px; height:36px; border:3px solid rgba(99,102,241,0.15); border-top-color:var(--primary-color,#6366f1); border-radius:50%; animation:sppSpin 0.8s linear infinite;"></div>
                <div style="color:var(--text-dim,#64748b); font-size:0.8rem; font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">${message}</div>
            </div>
            <style>@keyframes sppSpin { to { transform: rotate(360deg); } }</style>
        `;
    }

    /**
     * DOM-based search filter. Shows/hides elements by matching query against data attributes.
     * Does NOT trigger re-render — safe to call from input handlers.
     * @param {Element} container - Parent element to search within
     * @param {string} query - Search query (case-insensitive)
     * @param {Object} options
     * @param {string} options.itemSelector - CSS selector for filterable items (default: '[data-search-name]')
     * @param {string[]} options.attrs - Data attributes to match against (default: ['data-search-name'])
     */
    static domFilter(container, query, { itemSelector = '[data-search-name]', attrs = ['data-search-name'] } = {}) {
        if (!container) return;
        const q = (query || '').toLowerCase();
        container.querySelectorAll(itemSelector).forEach(el => {
            if (!q) { el.style.display = ''; return; }
            const match = attrs.some(a => (el.getAttribute(a) || '').includes(q));
            el.style.display = match ? '' : 'none';
        });
    }

    /**
     * Async concurrency guard. Prevents duplicate concurrent calls for the same key.
     * @param {string} key - Unique guard key
     * @param {Function} fn - Async function to execute
     */
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

    render() { return Fragment; }
}

/**
 * Built-in SPPForm Component
 */
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
                
                // Hide the entire form group/row if possible, otherwise just the element
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

    /**
     * Static helper to enable dependency logic on any container without a full component.
     */
    static autoInit(container) {
        if (!container) return;
        const form = new SPPForm(container);
        form.onMount();
        return form;
    }
}

// Namespace Initialization
window.TrustedHTML = TrustedHTML;
window.html = html;
window.Fragment = Fragment;
window.SPPStore = SPPStore;
window.BaseComponent = BaseComponent;
window.SPPForm = SPPForm;
const SPPUX = {
    TrustedHTML,
    html,
    Fragment,
    SPPStore,
    BaseComponent,
    SPPForm,
    api: async (action, params = {}) => {
        if (window.admin && typeof window.admin.api === 'function') {
            return await window.admin.api(action, params);
        }
        if (window.app && typeof window.app.api === 'function') {
            return await window.app.api(action, params);
        }
        const url = new URL(window.LEKHAK_CONFIG?.apiBase || window.spp_config?.apiBase || 'api.php', window.location.origin);
        url.searchParams.append('action', action);
        const res = await fetch(url.toString(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-SPP-Ajax': '1' },
            body: JSON.stringify(params)
        });
        return await res.json();
    },
    apiPost: async (data) => {
        if (window.admin && typeof window.admin.apiPost === 'function') {
            return await window.admin.apiPost(data);
        }
        if (window.app && typeof window.app.apiPost === 'function') {
            return await window.app.apiPost(data);
        }
        let action = 'custom';
        let body = data;
        let headers = { 'X-SPP-Ajax': '1' };
        if (data instanceof FormData) {
            action = data.get('action') || 'custom';
        } else {
            action = data.action || 'custom';
            body = JSON.stringify(data);
            headers = { 'Content-Type': 'application/json', 'X-SPP-Ajax': '1' };
        }
        const url = new URL(window.LEKHAK_CONFIG?.apiBase || window.spp_config?.apiBase || 'api.php', window.location.origin);
        url.searchParams.append('action', action);
        const res = await fetch(url.toString(), { method: 'POST', headers, body });
        return await res.json();
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
        
        const events = ['click', 'input', 'change', 'submit', 'blur', 'focus', 'keydown', 'keyup', 'keypress', 'dragstart', 'dragover', 'dragleave', 'drop', 'dragend'];
        events.forEach(evt => {
            document.addEventListener(evt, (e) => {
                const selectors = '[data-spp-evt], [data-spp-evt-click], [data-spp-evt-change], [data-spp-evt-input], [data-spp-evt-submit]';
                const target = e.target && e.target.closest ? e.target.closest(selectors) : null;
                // if (target) console.log(`[SPPUX] Event: ${evt} on`, target);
                
                // Find all components that could handle this event
                for (const comp of this._components) {
                    // Check if component container still exists in DOM
                    if (!document.contains(comp.container)) {
                        this._components.delete(comp);
                        continue;
                    }
                    comp._onEvent(e);
                    // If event was handled (default prevented), we stop propagation to other components
                    if (e.defaultPrevented) break;
                }
            }, true);
        });

        // Initialize LiveService Global Listener
        document.addEventListener('click', async (e) => {
            this._handleLocalAction(e, 'click');
            await this._handleLiveEvent(e, 'click');
        });
        document.addEventListener('change', async (e) => this._handleLiveEvent(e, 'change'));
        document.addEventListener('submit', async (e) => this._handleLiveEvent(e, 'submit'));
        document.addEventListener('blur', async (e) => this._handleLiveEvent(e, 'blur'), true);

        // 3. Handle Live Inputs (Debounced)
        document.addEventListener('input', (e) => {
            const el = e.target && e.target.closest ? e.target.closest('[data-spp-live-input]') : null;
            if (!el) return;

            const service = el.getAttribute('data-spp-live-input');
            const delay = parseInt(el.getAttribute('data-live-debounce') || '300');
            
            if (!el._liveDebounce) {
                el._liveDebounce = SPPUX.utils.debounce(async () => {
                    const params = { ...el.dataset, value: el.value };
                    delete params.sppLiveInput;
                    await SPPUX.api(service, params);
                }, delay);
            }
            el._liveDebounce();
        });

        // 4. Handle Polling
        this._initPolling();
    },

    _handleLocalAction(e, eventType) {
        if (eventType !== 'click') return;
        
        const el = e.target && e.target.closest ? e.target.closest('[data-live-toggle], [data-live-remove], [data-live-url]') : null;
        if (!el) return;

        // Toggle Class
        const toggleClass = el.getAttribute('data-live-toggle');
        if (toggleClass) {
            const targetSelector = el.getAttribute('data-live-target');
            let target;
            if (targetSelector && targetSelector.startsWith('closest ')) {
                target = el.closest(targetSelector.replace('closest ', ''));
            } else {
                target = targetSelector ? document.querySelector(targetSelector) : el;
            }
            if (target) target.classList.toggle(toggleClass);
        }

        // Remove Element
        if (el.hasAttribute('data-live-remove')) {
            const targetSelector = el.getAttribute('data-live-target');
            let target;
            if (targetSelector && targetSelector.startsWith('closest ')) {
                target = el.closest(targetSelector.replace('closest ', ''));
            } else {
                target = targetSelector ? document.querySelector(targetSelector) : el;
            }
            if (target) target.remove();
        }

        // URL Navigation
        const url = el.getAttribute('data-live-url');
        if (url) {
            window.location.href = url;
        }
    },

    async _handleLiveEvent(e, eventType) {
        const el = e.target && e.target.closest ? e.target.closest('[data-spp-live]') : null;
        if (!el) return;

        // Verify trigger
        const trigger = el.getAttribute('data-live-trigger') || (el.tagName === 'FORM' ? 'submit' : 'click');
        if (trigger !== eventType) return;

        // 1. Handle Confirmation
        const confirmMsg = el.getAttribute('data-live-confirm');
        if (confirmMsg && !window.confirm(confirmMsg)) {
            e.preventDefault();
            return;
        }

        if (el.tagName === 'FORM' || eventType === 'submit') e.preventDefault();

        // 2. Handle Loading State
        const loaderSelector = el.getAttribute('data-live-loading');
        const loaderEl = loaderSelector ? document.querySelector(loaderSelector) : null;
        if (loaderEl) loaderEl.style.display = '';
        el.classList.add('spp-live-busy');

        // 3. Collect Data
        let params = { ...el.dataset };
        delete params.sppLive;

        // Auto-serialize if it's a form or inside a form
        const form = el.tagName === 'FORM' ? el : el.closest('form');
        if (form) {
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                params[key] = value;
            }
        }
        
        // Target override
        const targetSelector = el.getAttribute('data-live-target');
        const swapMode = el.getAttribute('data-live-swap') || 'morph';

        const service = el.getAttribute('data-spp-live');
        
        try {
            const result = await SPPUX.api(service, params);
            
            // If the service returned HTML in the root data and we have a target, apply it
            if (result.success && result.data && result.data.html && targetSelector) {
                let targetEl;
                if (targetSelector.startsWith('closest ')) {
                    targetEl = el.closest(targetSelector.replace('closest ', ''));
                } else {
                    targetEl = document.querySelector(targetSelector);
                }
                
                if (targetEl) {
                    if (swapMode === 'replace') targetEl.innerHTML = result.data.html;
                    else if (swapMode === 'append') targetEl.insertAdjacentHTML('beforeend', result.data.html);
                    else if (swapMode === 'prepend') targetEl.insertAdjacentHTML('afterbegin', html);
                    else this.morph(targetEl, result.data.html);
                }
            }
        } finally {
            if (loaderEl) loaderEl.style.display = 'none';
            el.classList.remove('spp-live-busy');
        }
    },

    _initPolling() {
        document.querySelectorAll('[data-live-poll]').forEach(el => {
            const interval = parseInt(el.getAttribute('data-live-poll'));
            const service = el.getAttribute('data-spp-live') || el.getAttribute('data-spp-live-input');
            if (!interval || !service) return;

            setInterval(async () => {
                // Only poll if tab is active to save resources
                if (document.hidden) return;
                
                const params = { ...el.dataset, is_poll: true };
                await SPPUX.api(service, params);
            }, interval);
        });
    },
    api: async (actionOrData, data = {}) => {
        if (actionOrData instanceof FormData) {
            return await SPPUX.apiPost(actionOrData);
        }
        const action = actionOrData;
        let endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
        if (endpoint === 'api.php' && !window.location.pathname.includes('/sppadmin') && !window.location.pathname.includes('/spp/admin')) {
            endpoint = window.location.pathname;
        }
        const ts = Date.now();
        
        // Auto-inject app context if available
        if (!data.appname && !data.context) {
            const rootState = window.spp_root_store?.get?.();
            if (rootState?.selectedApp) data.appname = rootState.selectedApp;
        }
        
        // Auto-inject CSRF
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
        
        // Auto-apply LiveAction instructions if present
        if (result.instructions) {
            SPPUX.applyInstructions(result.instructions, result.data || result);
        }
        
        return result; 
    },
    apiPost: async (formData) => {
        let endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
        if (endpoint === 'api.php' && !window.location.pathname.includes('/sppadmin') && !window.location.pathname.includes('/spp/admin')) {
            endpoint = window.location.pathname;
        }
        
        // Auto-inject app context if available
        if (!formData.has('appname') && !formData.has('context')) {
            const rootState = window.spp_root_store?.get?.();
            if (rootState?.selectedApp) formData.append('appname', rootState.selectedApp);
        }
        
        // Auto-inject CSRF
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

        // Auto-apply LiveAction instructions if present
        if (result.instructions) {
            SPPUX.applyInstructions(result.instructions, result.data || result);
        }

        return result;
    },
    applyInstructions: (instructions, context = {}) => {
        if (!instructions || !Array.isArray(instructions)) return;
        instructions.forEach(ins => {
            const { action, selector, html, url, message, type, event, detail, attr, value } = ins;
            const el = selector ? document.querySelector(selector) : null;
            
            switch (action) {
                case 'replace': if (el) el.innerHTML = html; break;
                case 'morph': if (el) this.morph(el, html); break;
                case 'append': if (el) el.insertAdjacentHTML('beforeend', html); break;
                case 'prepend': if (el) el.insertAdjacentHTML('afterbegin', html); break;
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
                case 'dispatch':
                    const target = el || window;
                    target.dispatchEvent(new CustomEvent(event, { detail }));
                    break;
                case 'store':
                    if (window.spp_root_store) window.spp_root_store.set(detail);
                    break;
                case 'script':
                    try { eval(html); } catch (e) { console.error('LiveAction Script Error:', e); }
                    break;
                case 'alert':
                    alert(message);
                    break;
                case 'assign':
                    if (el) el[ins.prop] = value;
                    break;
                case 'call':
                    const fn = window[ins.func];
                    if (typeof fn === 'function') fn(...(ins.args || []));
                    break;
                case 'clear':
                    if (el) el[ins.attr || 'innerHTML'] = '';
                    break;
            }
        });
    },

    morph: (el, newHtml) => {
        const temp = document.createElement('div');
        temp.innerHTML = newHtml;
        const newNode = temp.firstElementChild;
        if (!newNode) return;

        // 1. Update Attributes
        for (const attr of newNode.attributes) {
            if (el.getAttribute(attr.name) !== attr.value) {
                el.setAttribute(attr.name, attr.value);
            }
        }
        for (const attr of el.attributes) {
            if (!newNode.hasAttribute(attr.name)) {
                el.removeAttribute(attr.name);
            }
        }

        // 2. Update Content (Simple Text/HTML Morph)
        // If the structure is complex, we do a swap, otherwise we sync text
        if (newNode.children.length === 0 && el.children.length === 0) {
            if (el.textContent !== newNode.textContent) {
                el.textContent = newNode.textContent;
            }
        } else {
            // For now, if complex children, we swap innerHTML but keep the parent element
            // This preserves focus if the parent is the input/container
            if (el.innerHTML !== newNode.innerHTML) {
                el.innerHTML = newNode.innerHTML;
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
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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
    signal: (v) => new Signal(v),
    computed: (fn) => new Computed(fn),
    effect: (fn) => {
        const sub = () => { Signal.activeSubscriber = sub; fn(); Signal.activeSubscriber = null; };
        sub();
        return sub;
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
                        console.log("⚡ Change detected, reloading...");
                        location.reload();
                    }
                    this.lastTime = time;
                } catch(e) {}
            }, 1000);
        }
    },
    createStore: (initialState = {}) => {
        const listeners = new Set();
        const notify = () => listeners.forEach(fn => fn());
        const proxy = new Proxy(initialState, {
            get(target, prop) {
                if (prop === 'subscribe') return (fn) => { listeners.add(fn); return () => listeners.delete(fn); };
                if (prop === 'get') return () => target;
                if (prop === 'set') return (newState) => { Object.assign(target, newState); notify(); };
                return Reflect.get(target, prop);
            },
            set(target, prop, value) {
                const res = Reflect.set(target, prop, value);
                notify();
                return res;
            }
        });
        return proxy;
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
                // Simple param matching, e.g. /user/:id
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
                
                // Extract params
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
        const id = 'spp-await-' + Math.random().toString(36).substr(2, 9);
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
    }
}; 
window.SPPUX = SPPUX;

// Initialize LiveSync in Dev Mode
// SPPUX.liveSync.init();

/**
 * HTML-First Declarative Directive Initialization Engine.
 * Empowers designers to build complete web applications using pure Vanilla HTML attributes natively.
 */
function initHtmlDirectives() {
    console.log("⚡ SPPUX HTML-First Directives Engine Initialized");

    // 1. Zero-JS Declarative Actions: Intercept clicks/submits on elements carrying data-spp-post or data-spp-action
    document.addEventListener('submit', async (e) => {
        const targetForm = e.target && e.target.closest ? e.target.closest('[data-spp-post], [data-spp-action]') : null;
        if (!targetForm) return;

        e.preventDefault();
        const action = targetForm.getAttribute('data-spp-post') || targetForm.getAttribute('data-spp-action');
        const targetSelector = targetForm.getAttribute('data-spp-target');

        SPPUX.Busy.start();
        try {
            const formData = new FormData(targetForm);
            if (!formData.has('action')) formData.append('action', action);

            const res = await SPPUX.apiPost(formData);
            if (res && res.html && targetSelector) {
                const dest = document.querySelector(targetSelector);
                if (dest) {
                    // Check for transition parameter
                    const transition = targetForm.getAttribute('data-spp-transition');
                    if (transition) dest.classList.add(`spp-transition-${transition}`);
                    
                    // Populate inner payload
                    dest.innerHTML = res.html;
                    console.log(`[SPPUX Directives] Populated payload successfully into target: ${targetSelector}`);
                    
                    if (transition) setTimeout(() => dest.classList.remove(`spp-transition-${transition}`), 300);
                }
            }

            if (res && res.message) {
                const notifyAttr = targetForm.getAttribute('data-spp-notify') || res.message;
                if (SPPUX.Notify) SPPUX.Notify.show(notifyAttr, res.status || 'info');
                else alert(notifyAttr);
            }
        } catch (err) {
            console.error("[SPPUX Directives] Action processing failed:", err);
        } finally {
            SPPUX.Busy.stop();
        }
    });

    // 2. HTML-Native Two-Way Signal Binding (data-spp-bind <-> data-spp-text)
    const sharedSignals = new Map();
    document.addEventListener('input', (e) => {
        const bindKey = e.target.getAttribute('data-spp-bind');
        if (!bindKey) return;

        const val = e.target.value;
        sharedSignals.set(bindKey, val);

        // Instantly propagate to all display target headers without server roundtrips
        document.querySelectorAll(`[data-spp-text="${bindKey}"]`).forEach(node => {
            node.textContent = val;
        });
    });

    // 3. Live DOM Search Filtering (data-spp-search)
    document.addEventListener('input', (e) => {
        const targetGridSelector = e.target.getAttribute('data-spp-search');
        if (!targetGridSelector) return;

        const destGrid = document.querySelector(targetGridSelector);
        if (destGrid) {
            const query = (e.target.value || '').toLowerCase();
            destGrid.querySelectorAll('[data-search-name]').forEach(item => {
                const name = (item.getAttribute('data-search-name') || '').toLowerCase();
                item.style.display = name.includes(query) ? '' : 'none';
            });
        }
    });

    // 4. Custom Component Tag Observers (<spp-component>)
    const observeCustomTags = () => {
        document.querySelectorAll('spp-component:not([data-initialized])').forEach(comp => {
            comp.setAttribute('data-initialized', 'true');
            const name = comp.getAttribute('name');
            const island = comp.getAttribute('data-island') || 'visible';
            
            // Automatically wrap component inside a reactivity island wrapper tag
            comp.setAttribute('data-spp-island', island);
            console.log(`[SPPUX Directives] Bootstrapped HTML Tag Component: <spp-component name="${name}">`);
        });
    };

    observeCustomTags();
    const observer = new MutationObserver(() => observeCustomTags());
    observer.observe(document.body, { childList: true, subtree: true });

    // 5. Scroll Animations (data-spp-animate)
    const animateObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('spp-animated');
                animateObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    const observeAnimations = () => {
        document.querySelectorAll('[data-spp-animate]:not(.spp-animated-tracked)').forEach(el => {
            el.classList.add('spp-animated-tracked');
            animateObserver.observe(el);
        });
    };
    observeAnimations();
    const animMutObserver = new MutationObserver(() => observeAnimations());
    animMutObserver.observe(document.body, { childList: true, subtree: true });

    // 6. Global Hotkeys (data-spp-hotkey)
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        const keys = [];
        if (e.ctrlKey) keys.push('ctrl');
        if (e.altKey) keys.push('alt');
        if (e.shiftKey) keys.push('shift');
        if (e.metaKey) keys.push('meta');
        keys.push(e.key.toLowerCase());
        const combo = keys.join('+');
        
        const target = document.querySelector(`[data-spp-hotkey="${combo}"]`);
        if (target) {
            e.preventDefault();
            target.click();
        }
    });

    // 7. Infinite Scroll (data-spp-infinite-scroll)
    const infiniteObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const eventName = el.getAttribute('data-spp-infinite-scroll');
                if (eventName && window.SPPUX && SPPUX.events) {
                    SPPUX.events.emit(eventName, el);
                }
            }
        });
    }, { rootMargin: '200px' });
    
    const observeInfinite = () => {
        document.querySelectorAll('[data-spp-infinite-scroll]:not(.spp-infinite-tracked)').forEach(el => {
            el.classList.add('spp-infinite-tracked');
            infiniteObserver.observe(el);
        });
    };
    observeInfinite();
    const infMutObserver = new MutationObserver(() => observeInfinite());
    infMutObserver.observe(document.body, { childList: true, subtree: true });

    // 8. Copy to Clipboard (data-spp-copy)
    document.addEventListener('click', async (e) => {
        const copyTarget = e.target && e.target.closest ? e.target.closest('[data-spp-copy]') : null;
        if (copyTarget) {
            const selectorId = copyTarget.getAttribute('data-spp-copy');
            let text = '';
            if (selectorId && document.getElementById(selectorId)) {
                const el = document.getElementById(selectorId);
                text = el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' ? el.value : el.innerText;
            } else {
                text = copyTarget.innerText;
            }
            if (text) {
                try {
                    await navigator.clipboard.writeText(text);
                    const original = copyTarget.innerHTML;
                    copyTarget.innerHTML = '<span>✓ Copied!</span>';
                    setTimeout(() => copyTarget.innerHTML = original, 2000);
                } catch(err) { console.warn('Copy failed', err); }
            }
        }
    });

    // 9. Ripple Effect (data-spp-ripple)
    document.addEventListener('pointerdown', (e) => {
        const rippleEl = e.target && e.target.closest ? e.target.closest('[data-spp-ripple]') : null;
        if (rippleEl) {
            const rect = rippleEl.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const circle = document.createElement('span');
            circle.style.position = 'absolute';
            circle.style.background = 'rgba(255,255,255,0.3)';
            circle.style.borderRadius = '50%';
            circle.style.pointerEvents = 'none';
            circle.style.transform = 'translate(-50%, -50%) scale(0)';
            circle.style.animation = 'sppux-ripple-anim 0.6s linear';
            circle.style.left = x + 'px';
            circle.style.top = y + 'px';
            const size = Math.max(rect.width, rect.height);
            circle.style.width = circle.style.height = size + 'px';
            rippleEl.style.position = rippleEl.style.position === 'static' ? 'relative' : rippleEl.style.position;
            rippleEl.style.overflow = 'hidden';
            rippleEl.appendChild(circle);
            setTimeout(() => circle.remove(), 600);
        }
    });

    // 10. Input Masking (data-spp-mask)
    document.addEventListener('input', (e) => {
        const mask = e.target.getAttribute('data-spp-mask');
        if (mask) {
            let val = e.target.value.replace(/\D/g, '');
            if (mask === 'phone') {
                const match = val.match(/^(\d{0,3})(\d{0,3})(\d{0,4})$/);
                if (match) val = !match[2] ? match[1] : '(' + match[1] + ') ' + match[2] + (match[3] ? '-' + match[3] : '');
            } else if (mask === 'date') {
                const match = val.match(/^(\d{0,2})(\d{0,2})(\d{0,4})$/);
                if (match) val = !match[2] ? match[1] : match[1] + '/' + match[2] + (match[3] ? '/' + match[3] : '');
            }
            e.target.value = val;
        }
    });

    // 11. Parallax (data-spp-parallax)
    window.addEventListener('scroll', () => {
        document.querySelectorAll('[data-spp-parallax]').forEach(el => {
            const speed = parseFloat(el.getAttribute('data-spp-parallax') || '0.5');
            const yPos = -(window.scrollY * speed);
            el.style.transform = `translateY(${yPos}px)`;
        });
    });

    // 12. Form Validation (data-spp-validate)
    const validateField = (el) => {
        const rules = el.getAttribute('data-spp-validate').split('|');
        let error = null;
        const val = el.value.trim();
        for (const r of rules) {
            if (r === 'required' && !val) { error = 'This field is required'; break; }
            if (r === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { error = 'Invalid email address'; break; }
            if (r.startsWith('min:')) {
                const min = parseInt(r.split(':')[1], 10);
                if (val.length < min) { error = `Minimum ${min} characters required`; break; }
            }
        }
        
        let errEl = el.nextElementSibling;
        if (errEl && errEl.classList.contains('spp-err-msg')) errEl.remove();
        
        if (error) {
            el.classList.add('spp-invalid');
            el.insertAdjacentHTML('afterend', `<div class="spp-err-msg" style="color:var(--sppux-danger);font-size:0.8rem;margin-top:4px;">${error}</div>`);
            return false;
        } else {
            el.classList.remove('spp-invalid');
            return true;
        }
    };
    
    document.addEventListener('blur', (e) => {
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-spp-validate')) validateField(e.target);
    }, true);
    
    document.addEventListener('input', (e) => {
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-spp-validate') && e.target.classList.contains('spp-invalid')) {
            validateField(e.target);
        }
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form && form.hasAttribute && form.hasAttribute('data-spp-form')) {
            let valid = true;
            form.querySelectorAll('[data-spp-validate]').forEach(el => {
                if (!validateField(el)) valid = false;
            });
            if (!valid) e.preventDefault();
        }
    });

    // 13. Magnetic Elements (data-spp-magnetic)
    document.addEventListener('mousemove', (e) => {
        document.querySelectorAll('[data-spp-magnetic]').forEach(el => {
            const rect = el.getBoundingClientRect();
            const strength = parseFloat(el.getAttribute('data-spp-magnetic') || '0.2');
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const distanceX = e.clientX - centerX;
            const distanceY = e.clientY - centerY;
            
            // If mouse is within 100px of the center
            if (Math.abs(distanceX) < 100 && Math.abs(distanceY) < 100) {
                el.style.transform = `translate(${distanceX * strength}px, ${distanceY * strength}px)`;
                el.style.transition = 'none';
            } else {
                el.style.transform = 'translate(0px, 0px)';
                el.style.transition = 'transform 0.3s ease';
            }
        });
    });

    // 14. Typewriter Effect (data-spp-typewriter)
    const typeWriterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('spp-typed')) {
                const el = entry.target;
                el.classList.add('spp-typed');
                const text = el.getAttribute('data-spp-typewriter') || el.innerText;
                el.innerText = '';
                let i = 0;
                const speed = 30;
                const type = () => {
                    if (i < text.length) {
                        el.innerHTML += text.charAt(i);
                        i++;
                        setTimeout(type, speed);
                    }
                };
                type();
            }
        });
    });
    const observeTypewriters = () => {
        document.querySelectorAll('[data-spp-typewriter]:not(.spp-typewriter-tracked)').forEach(el => {
            el.classList.add('spp-typewriter-tracked');
            typeWriterObserver.observe(el);
        });
    };
    observeTypewriters();
    const typeMutObserver = new MutationObserver(() => observeTypewriters());
    typeMutObserver.observe(document.body, { childList: true, subtree: true });

    // 15. Pull to Refresh (data-spp-pull-refresh)
    let pullStartY = 0;
    let isPulling = false;
    let pullSpinner = null;
    document.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) {
            pullStartY = e.touches[0].clientY;
            isPulling = true;
        }
    }, {passive: true});
    document.addEventListener('touchmove', (e) => {
        if (!isPulling) return;
        const y = e.touches[0].clientY;
        const dy = y - pullStartY;
        if (dy > 0 && window.scrollY === 0) {
            if (!pullSpinner) {
                pullSpinner = document.createElement('div');
                pullSpinner.innerHTML = '↻';
                pullSpinner.style.cssText = 'position:fixed;top:-40px;left:50%;transform:translateX(-50%);width:30px;height:30px;background:var(--sppux-panel);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(0,0,0,0.2);z-index:9999;transition:0.2s;font-weight:bold;color:var(--sppux-primary);';
                document.body.appendChild(pullSpinner);
            }
            pullSpinner.style.top = Math.min(dy / 2 - 40, 20) + 'px';
            pullSpinner.style.transform = `translateX(-50%) rotate(${dy * 2}deg)`;
        }
    }, {passive: true});
    document.addEventListener('touchend', (e) => {
        if (!isPulling) return;
        isPulling = false;
        if (pullSpinner) {
            if (parseInt(pullSpinner.style.top) >= 20) {
                const target = document.querySelector('[data-spp-pull-refresh]');
                if (target) {
                    pullSpinner.innerHTML = '...';
                    const evt = target.getAttribute('data-spp-pull-refresh');
                    if (evt && window.SPPUX && SPPUX.events) SPPUX.events.emit(evt, target);
                    else location.reload();
                } else {
                    location.reload();
                }
                setTimeout(() => { if (pullSpinner) pullSpinner.remove(); pullSpinner = null; }, 1000);
            } else {
                pullSpinner.remove();
                pullSpinner = null;
            }
        }
    });

    // 16. Canvas Particle Network (data-spp-particles)
    const initParticles = (canvas) => {
        const ctx = canvas.getContext('2d');
        let width = canvas.width = canvas.parentElement.clientWidth || 800;
        let height = canvas.height = canvas.parentElement.clientHeight || 600;
        const color = canvas.getAttribute('data-spp-particles') || 'rgba(255, 255, 255, 0.5)';
        const particles = [];
        for(let i=0; i<50; i++) particles.push({ x: Math.random()*width, y: Math.random()*height, vx: (Math.random()-0.5)*1, vy: (Math.random()-0.5)*1, radius: Math.random()*2+1 });
        
        const draw = () => {
            ctx.clearRect(0, 0, width, height);
            for(let i=0; i<particles.length; i++) {
                const p = particles[i];
                p.x += p.vx; p.y += p.vy;
                if(p.x < 0 || p.x > width) p.vx *= -1;
                if(p.y < 0 || p.y > height) p.vy *= -1;
                ctx.beginPath(); ctx.arc(p.x, p.y, p.radius, 0, Math.PI*2); ctx.fillStyle = color; ctx.fill();
                for(let j=i+1; j<particles.length; j++) {
                    const p2 = particles[j];
                    const dist = Math.hypot(p.x-p2.x, p.y-p2.y);
                    if(dist < 100) {
                        ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p2.x, p2.y);
                        ctx.strokeStyle = color.replace(/[\d\.]+\)$/, (1 - dist/100) + ')'); ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(draw);
        };
        draw();
        window.addEventListener('resize', () => { width = canvas.width = canvas.parentElement.clientWidth; height = canvas.height = canvas.parentElement.clientHeight; });
    };
    
    const particleObserver = new MutationObserver(() => {
        document.querySelectorAll('canvas[data-spp-particles]:not(.spp-particles-init)').forEach(el => {
            el.classList.add('spp-particles-init');
            initParticles(el);
        });
    });
    particleObserver.observe(document.body, { childList: true, subtree: true });
    document.querySelectorAll('canvas[data-spp-particles]').forEach(el => { el.classList.add('spp-particles-init'); initParticles(el); });

    // 17. 3D Tilt Effect (data-spp-tilt)
    document.addEventListener('mousemove', (e) => {
        document.querySelectorAll('[data-spp-tilt]').forEach(el => {
            const rect = el.getBoundingClientRect();
            // Only affect if mouse is hovering over it
            if(e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom) {
                const x = e.clientX - rect.left; // x position within the element.
                const y = e.clientY - rect.top;  // y position within the element.
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * -15; // Max 15deg
                const rotateY = ((x - centerX) / centerX) * 15;
                el.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.05, 1.05, 1.05)`;
                el.style.transition = 'transform 0.1s ease-out';
            } else {
                el.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
                el.style.transition = 'transform 0.5s ease-out';
            }
        });
    });
    if (window.DeviceOrientationEvent) {
        window.addEventListener('deviceorientation', (e) => {
            document.querySelectorAll('[data-spp-tilt]').forEach(el => {
                const rotateX = Math.max(-15, Math.min(15, e.beta - 45)); // assuming 45 is neutral holding
                const rotateY = Math.max(-15, Math.min(15, e.gamma));
                el.style.transform = `perspective(1000px) rotateX(${-rotateX}deg) rotateY(${rotateY}deg)`;
            });
        });
    }

    // 18. Voice Input Dictation (data-spp-voice-input)
    document.addEventListener('click', (e) => {
        const btn = e.target && e.target.closest ? e.target.closest('[data-spp-voice-input]') : null;
        if (btn) {
            const targetId = btn.getAttribute('data-spp-voice-input');
            const targetEl = document.getElementById(targetId);
            if (!targetEl) return;
            
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                if (window.SPPUX && SPPUX.notify) SPPUX.notify('Speech Recognition API not supported in this browser.', 'warning');
                return;
            }
            
            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = true;
            
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '🎙️ (Listening...)';
            btn.style.color = 'var(--sppux-danger)';
            
            recognition.onresult = (event) => {
                let text = '';
                for (let i = 0; i < event.results.length; i++) {
                    text += event.results[i][0].transcript;
                }
                targetEl.value = text;
                if(window.SPPUX && window.SPPUX.events) SPPUX.events.emit('input', targetEl);
            };
            
            recognition.onerror = (event) => { console.warn('Speech error', event.error); };
            recognition.onend = () => {
                btn.innerHTML = originalHtml;
                btn.style.color = '';
            };
            
            recognition.start();
        }
    });

    // 5. Enterprise Native Developer Mode Live-Reload / View Synchronization Observer
    /*
    if (document.body.hasAttribute('data-spp-navigation') || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        let lastModHash = '';
        console.log("[SPPUX Directives] Bootstrapping background Hot Module Replacement (HMR) state tracker loop.");
        
        setInterval(async () => {
            try {
                const urlObj = new URL(window.location.href);
                urlObj.searchParams.set('__svc', 'spp:dev_modcheck');
                const res = await fetch(urlObj.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-SPP-Ajax': '1' } });
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.hash) {
                        if (!lastModHash) {
                            lastModHash = data.hash;
                        } else if (lastModHash !== data.hash) {
                            console.warn("[SPPUX Directives] Source document signature updated server-side! Triggering fluid UI Live Reload state morph.");
                            lastModHash = data.hash;
                            
                            const headerBar = document.querySelector('header') || document.body;
                            const origBg = headerBar.style.backgroundColor;
                            headerBar.style.transition = 'background-color 0.3s ease';
                            headerBar.style.backgroundColor = 'rgba(168, 85, 247, 0.2)';
                            
                            setTimeout(() => {
                                headerBar.style.backgroundColor = origBg;
                                window.location.reload();
                            }, 500);
                        }
                    }
                }
            } catch (err) {}
        }, 3000);
    }
    */
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHtmlDirectives);
} else {
    initHtmlDirectives();
}

/**
 * Universal Mount Function for SPPUX Standalone Usage
 * @param {typeof BaseComponent} ComponentClass 
 * @param {HTMLElement} container 
 * @returns {BaseComponent}
 */
const mount = (ComponentClass, container) => {
    const instance = new ComponentClass(null, container, {});
    const p = instance.onInit();
    if (p instanceof Promise) {
        p.then(() => instance.update());
    } else {
        instance.update();
    }
    return instance;
};

// ==========================================
// SPPUX Drag and Drop Engine
// ==========================================

class Draggable {
    constructor(element, options = {}) {
        this.element = typeof element === 'string' ? document.querySelector(element) : element;
        if (!this.element) return;
        this.options = Object.assign({
            handle: null,
            onDragStart: () => {},
            onDrag: () => {},
            onDragEnd: () => {}
        }, options);

        this.isDragging = false;
        this.handleNode = this.options.handle ? this.element.querySelector(this.options.handle) || this.element : this.element;
        
        this.onPointerDown = this.onPointerDown.bind(this);
        this.onPointerMove = this.onPointerMove.bind(this);
        this.onPointerUp = this.onPointerUp.bind(this);

        this.handleNode.addEventListener('pointerdown', this.onPointerDown);
        this.handleNode.style.cursor = 'move';
        this.handleNode.style.touchAction = 'none';
    }

    onPointerDown(e) {
        if (e.button && e.button !== 0) return;
        this.isDragging = true;
        this.startX = e.clientX;
        this.startY = e.clientY;
        
        const rect = this.element.getBoundingClientRect();
        const parentRect = this.element.offsetParent ? this.element.offsetParent.getBoundingClientRect() : {left: 0, top: 0};
        
        const styleLeft = parseFloat(this.element.style.left);
        const styleTop = parseFloat(this.element.style.top);
        
        this.startLeft = isNaN(styleLeft) ? (rect.left - parentRect.left) : styleLeft;
        this.startTop = isNaN(styleTop) ? (rect.top - parentRect.top) : styleTop;

        document.addEventListener('pointermove', this.onPointerMove);
        document.addEventListener('pointerup', this.onPointerUp);
        
        this.element.setPointerCapture(e.pointerId);
        this.originalZ = this.element.style.zIndex;
        this.element.style.zIndex = '9999';
        
        this.options.onDragStart(this.element);
    }

    onPointerMove(e) {
        if (!this.isDragging) return;
        const dx = e.clientX - this.startX;
        const dy = e.clientY - this.startY;
        
        const newLeft = this.startLeft + dx;
        const newTop = this.startTop + dy;
        
        this.element.style.left = newLeft + 'px';
        this.element.style.top = newTop + 'px';
        
        this.options.onDrag(this.element, newLeft, newTop);
    }

    onPointerUp(e) {
        if (!this.isDragging) return;
        this.isDragging = false;
        
        document.removeEventListener('pointermove', this.onPointerMove);
        document.removeEventListener('pointerup', this.onPointerUp);
        
        try { this.element.releasePointerCapture(e.pointerId); } catch(err){}
        this.element.style.zIndex = this.originalZ;
        
        this.options.onDragEnd(this.element, parseFloat(this.element.style.left), parseFloat(this.element.style.top));
    }
    
    destroy() {
        this.handleNode.removeEventListener('pointerdown', this.onPointerDown);
        this.handleNode.style.cursor = '';
        this.handleNode.style.touchAction = '';
    }
}

class Sortable {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        if (!this.container) return;
        this.options = Object.assign({
            itemSelector: '> *',
            handle: null,
            onSortEnd: () => {}
        }, options);

        this.onPointerDown = this.onPointerDown.bind(this);
        this.onPointerMove = this.onPointerMove.bind(this);
        this.onPointerUp = this.onPointerUp.bind(this);

        this.container.addEventListener('pointerdown', this.onPointerDown);
        this.container.style.touchAction = 'none';
    }

    onPointerDown(e) {
        if (e.button && e.button !== 0) return;
        
        let target = e.target;
        if (this.options.handle) {
            const handleEl = target.closest(this.options.handle);
            if (!handleEl || !this.container.contains(handleEl)) return;
            target = handleEl;
        }
        
        const item = target.closest(this.options.itemSelector);
        if (!item || item === this.container) return;

        e.preventDefault();
        
        this.dragItem = item;
        this.items = Array.from(this.container.querySelectorAll(this.options.itemSelector));
        this.dragIndex = this.items.indexOf(this.dragItem);
        
        this.startX = e.clientX;
        this.startY = e.clientY;
        
        const rect = this.dragItem.getBoundingClientRect();
        
        this.ghost = this.dragItem.cloneNode(true);
        this.ghost.style.position = 'fixed';
        this.ghost.style.left = rect.left + 'px';
        this.ghost.style.top = rect.top + 'px';
        this.ghost.style.width = rect.width + 'px';
        this.ghost.style.height = rect.height + 'px';
        this.ghost.style.margin = '0';
        this.ghost.style.zIndex = '99999';
        this.ghost.style.opacity = '0.9';
        this.ghost.style.pointerEvents = 'none';
        this.ghost.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
        this.ghost.style.transition = 'none';
        
        document.body.appendChild(this.ghost);
        
        this.dragItem.style.opacity = '0';
        
        this.items.forEach(el => {
            if (el !== this.dragItem) {
                el.style.transition = 'transform 0.2s ease-in-out';
            }
        });

        document.addEventListener('pointermove', this.onPointerMove);
        document.addEventListener('pointerup', this.onPointerUp);
    }

    onPointerMove(e) {
        if (!this.dragItem) return;
        
        const dx = e.clientX - this.startX;
        const dy = e.clientY - this.startY;
        
        this.ghost.style.transform = `translate(${dx}px, ${dy}px)`;
        
        let overItem = null;
        for (const item of this.items) {
            if (item === this.dragItem) continue;
            const rect = item.getBoundingClientRect();
            if (e.clientY > rect.top && e.clientY < rect.bottom && e.clientX > rect.left && e.clientX < rect.right) {
                overItem = item;
                break;
            }
        }
        
        if (overItem) {
            const overIndex = this.items.indexOf(overItem);
            const isMovingDown = overIndex > this.dragIndex;
            
            if (isMovingDown) {
                this.container.insertBefore(this.dragItem, overItem.nextSibling);
            } else {
                this.container.insertBefore(this.dragItem, overItem);
            }
            
            this.items = Array.from(this.container.querySelectorAll(this.options.itemSelector));
            this.dragIndex = this.items.indexOf(this.dragItem);
        }
    }

    onPointerUp(e) {
        if (!this.dragItem) return;
        
        document.removeEventListener('pointermove', this.onPointerMove);
        document.removeEventListener('pointerup', this.onPointerUp);
        
        const rect = this.dragItem.getBoundingClientRect();
        this.ghost.style.transition = 'transform 0.2s ease, opacity 0.2s ease';
        this.ghost.style.transform = `translate(${rect.left - parseFloat(this.ghost.style.left)}px, ${rect.top - parseFloat(this.ghost.style.top)}px)`;
        
        setTimeout(() => {
            if (this.ghost && this.ghost.parentNode) {
                this.ghost.parentNode.removeChild(this.ghost);
            }
            this.ghost = null;
            if (this.dragItem) this.dragItem.style.opacity = '';
            
            this.items.forEach(el => {
                el.style.transition = '';
            });
            
            if (this.dragItem) {
                const newIndex = this.items.indexOf(this.dragItem);
                this.options.onSortEnd(this.dragItem, newIndex, this.items);
            }
            this.dragItem = null;
        }, 200);
    }
}

if (window.SPPUX) {
    window.SPPUX.Draggable = Draggable;
    window.SPPUX.Sortable = Sortable;
}


    // Expose Global SPPUX Object
    global.SPPUX = {
        BaseComponent: BaseComponent,
        html: html,
        mount: mount,
        TrustedHTML: TrustedHTML,
        Fragment: Fragment,
        SPPStore: typeof SPPStore !== 'undefined' ? SPPStore : null
    };

    // Also expose top-level for absolute novice convenience
    global.BaseComponent = BaseComponent;
    global.html = html;
    global.mount = mount;
})(typeof window !== 'undefined' ? window : this);
