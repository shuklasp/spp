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

            raw += escape(value);
        }
    }

    return new TrustedHTML(raw);
};

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
        this.app = app;
        this.container = container;
        this.props = props;
        this.state = {};
        this._subscriptions = [];
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
                const idByType = el.getAttribute(`data-spp-evt-${e.type}`);
                const idLegacy = (type === e.type) ? el.getAttribute('data-spp-evt') : null;
                const id = idByType || idLegacy;
                
                if (id) {
                    const handler = this._handlers.get(id);
                    if (handler) {
                        if (e.type !== 'dragstart') e.preventDefault();
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
        this.state = { ...this.state, ...newState };
        this.update();
    }

    _registerGlobalHandlers() {
        // Claim all handlers currently in the global pool that are present in the DOM
        document.querySelectorAll('[data-spp-evt]').forEach(el => {
            const id = el.getAttribute('data-spp-evt');
            if (window.__spp_handlers && window.__spp_handlers[id]) {
                this._handlers.set(id, window.__spp_handlers[id]);
                // Exclusive claim: prevent other components from also registering this handler
                delete window.__spp_handlers[id];
            }
        });

        // Also register system overlays as event sources
        const overlaySelectors = ['#sppux-modal-root', '.sub-modal', '.glass-overlay', '#header-actions', '#sppux-drawer-root'];
        overlaySelectors.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                this._eventContainers.add(el);
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

    update() {
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
            const template = this.render();
            Signal.activeSubscriber = null;
            this._isRendering = false;
            
            if (!template || template.content === undefined) return;

            const temp = document.createElement('div');
            temp.innerHTML = template.toString();

            // Register handlers from the rendered template
            temp.querySelectorAll('*').forEach(el => {
                for (const attr of el.attributes) {
                    if (attr.name.startsWith('data-spp-evt')) {
                        const id = attr.value;
                        if (window.__spp_handlers && window.__spp_handlers[id]) {
                            this._handlers.set(id, window.__spp_handlers[id]);
                        }
                    }
                }
            });
            
            // Also scan global containers (modals, header) for any handlers added during render()
            this._registerGlobalHandlers();
            
            window.__spp_handlers = {};

            // Reconcile new virtual DOM with actual DOM
            console.log(`[BaseComponent] Updating ${this.constructor.name}...`);
            this._reconcile(this.container, temp);
        } finally {
            this._isRendering = false;
            Signal.activeSubscriber = null;
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
        return window.confirm(msg);
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
                    oldNode.value = newNode.value;
                }

                this._reconcile(oldNode, newNode);
            }
        });

        while (parent.childNodes.length > newNodes.length) {
            parent.removeChild(parent.lastChild);
        }
    }

    async service(name, params = {}) {
        // Generic API call for backend services
        return SPPUX.api('service', { name, ...params });
    }

    dispose() {
        this._subscriptions.forEach(unsubscribe => unsubscribe());
        this._signalUnsubscribes.forEach(unsub => unsub());
        
        // Unregister from global dispatcher
        if (window.SPPUX && SPPUX._components) {
            SPPUX._components.delete(this);
        }

        this._handlers.clear();
        this.onDestroy();
    }

    async onInit() {}
    async onMount() {}
    onDestroy() {}
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
window.SPPUX = {
    TrustedHTML,
    html,
    Fragment,
    SPPStore,
    BaseComponent,
    SPPForm,
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
                const target = e.target.closest('[data-spp-evt]');
                if (target) console.log(`[SPPUX] Event: ${evt} on`, target);
                
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
            const el = e.target.closest('[data-spp-live-input]');
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
        
        const el = e.target.closest('[data-live-toggle], [data-live-remove], [data-live-url]');
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
        const el = e.target.closest('[data-spp-live]');
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
        const endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
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
        const endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
        
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
    }
}; 

// Initialize LiveSync in Dev Mode
// SPPUX.liveSync.init();
