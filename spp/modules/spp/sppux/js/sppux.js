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
                raw += `data-spp-evt="${eventId}" data-spp-type="${eventMatch[1]}"`;
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
    constructor(admin, container, props = {}) {
        this.admin = admin || window.spp_admin || null;
        this.container = container;
        this.props = props;
        this.state = {};
        this._subscriptions = [];
        this._handlers = new Map();
        this._eventContainers = new Set([this.container]);
        this.root = window.spp_root_store || null;

        this.api = new Proxy({}, {
            get: (target, prop) => {
                if (typeof prop !== 'string') return target[prop];
                const action = prop.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
                return async (data = {}, options = { lock: true }) => {
                    if (options.lock) SPPUX.Busy.start();
                    try {
                        if (data instanceof FormData) {
                            if (!data.has('action')) data.append('action', action);
                            return await this.admin.apiPost(data);
                        }
                        return await this.admin.api(action, data);
                    } finally {
                        if (options.lock) SPPUX.Busy.stop();
                    }
                };
            }
        });

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
            if (!isOurEvent) return;

            const el = e.target.closest('[data-spp-evt]');
            if (el) {
                const type = el.getAttribute('data-spp-type');
                if (type && type !== e.type) return;

                const id = el.getAttribute('data-spp-evt');
                const handler = this._handlers.get(id);
                if (handler) {
                    e.preventDefault();
                    e.stopPropagation();
                    handler.call(this, e);
                }
            }
        };
        
        ['click', 'input', 'change', 'submit', 'blur', 'focus', 'dragstart', 'dragover', 'dragleave', 'drop', 'dragend'].forEach(evt => {
            document.addEventListener(evt, (e) => this._onEvent(e), true);
        });

        // Signal Auto-Tracking
        this._signalUnsubscribes = new Set();
        this._isRendering = false;

        // Check for Hydration
        if (this.container.children.length > 0) {
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
            }
        });

        // Also register the modal root as an event source if it exists
        const modalRoot = document.getElementById('sppux-modal-root');
        if (modalRoot) {
            this._eventContainers.add(modalRoot);
        }
        
        // And the header actions
        const header = document.getElementById('header-actions');
        if (header) {
            this._eventContainers.add(header);
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

    update() {
        if (this._isRendering) return; // Prevent infinite loops
        
        this._isRendering = true;
        this._signalUnsubscribes.forEach(unsub => unsub());
        this._signalUnsubscribes.clear();

        const subscriber = () => {
            if (this._isRendering) return; // Only trigger from Signal changes after first render
            this.update();
        };

        Signal.activeSubscriber = subscriber;
        const template = this.render();
        Signal.activeSubscriber = null;
        this._isRendering = false;
        
        if (!template || template.content === undefined) return;

        const temp = document.createElement('div');
        temp.innerHTML = template.toString();

        // Register handlers from the rendered template
        temp.querySelectorAll('[data-spp-evt]').forEach(el => {
            const id = el.getAttribute('data-spp-evt');
            if (window.__spp_handlers && window.__spp_handlers[id]) {
                this._handlers.set(id, window.__spp_handlers[id]);
            }
        });
        
        // Also scan global containers (modals, header) for any handlers added during render()
        this._registerGlobalHandlers();
        
        window.__spp_handlers = {};

        this._reconcile(this.container, temp);
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
        return this.admin.callAppService(name, params);
    }

    dispose() {
        this._subscriptions.forEach(unsubscribe => unsubscribe());
        this._signalUnsubscribes.forEach(unsub => unsub());
        ['click', 'input', 'change', 'submit', 'blur', 'focus'].forEach(evt => {
            this.container.removeEventListener(evt, this._onEvent, true);
        });
        this._handlers.clear();
        this.onDestroy();
    }

    async onInit() {}
    async onMount() {}
    onDestroy() {}
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
        const form = new SPPForm(null, container);
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
