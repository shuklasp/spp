/**
 * BaseComponent
 * 
 * Core class for SPP-UX reactive components.
 */
export default class BaseComponent {
    constructor(admin, container, props = {}) {
        this.admin = admin || window.admin || null;
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
                    if (options.lock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.start();
                    try {
                        const apiBase = this.props.apiBase || null;
                        if (data instanceof FormData) {
                            if (!data.has('action')) data.append('action', action);
                            if (apiBase) return await this.admin.apiPost(data, {}, { ...options, endpoint: apiBase });
                            return await this.admin.apiPost(data);
                        }
                        if (apiBase) return await this.admin.api(action, data, { ...options, endpoint: apiBase });
                        return await this.admin.api(action, data);
                    } finally {
                        if (options.lock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.stop();
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

        if (this.container && this.container.children.length > 0) {
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

    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.update();
    }

    _registerGlobalHandlers() {
        document.querySelectorAll('[data-spp-evt]').forEach(el => {
            const id = el.getAttribute('data-spp-evt');
            if (window.__spp_handlers && window.__spp_handlers[id]) {
                this._handlers.set(id, window.__spp_handlers[id]);
            }
        });

        const modalRoot = document.getElementById('sppux-modal-root');
        if (modalRoot) this._eventContainers.add(modalRoot);
        
        const header = document.getElementById('header-actions');
        if (header) this._eventContainers.add(header);
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
        if (this._isRendering) return;
        this._isRendering = true;
        
        if (window.Signal) {
            this._signalUnsubscribes?.forEach(unsub => unsub());
            this._signalUnsubscribes?.clear();
            const subscriber = () => {
                if (this._isRendering) return;
                this.update();
            };
            Signal.activeSubscriber = subscriber;
        }

        const template = this.render();
        if (window.Signal) Signal.activeSubscriber = null;
        this._isRendering = false;
        
        if (!template) return;

        const temp = document.createElement('div');
        temp.innerHTML = template.toString();

        temp.querySelectorAll('[data-spp-evt]').forEach(el => {
            const id = el.getAttribute('data-spp-evt');
            if (window.__spp_handlers && window.__spp_handlers[id]) {
                this._handlers.set(id, window.__spp_handlers[id]);
            }
        });
        
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
                if (oldNode.textContent !== newNode.textContent) oldNode.textContent = newNode.textContent;
                return;
            }
            if (oldNode.nodeType === Node.ELEMENT_NODE) {
                const newAttrs = newNode.attributes;
                for (const attr of newAttrs) {
                    if (oldNode.getAttribute(attr.name) !== attr.value) oldNode.setAttribute(attr.name, attr.value);
                }
                for (const attr of oldNode.attributes) {
                    if (!newNode.hasAttribute(attr.name)) oldNode.removeAttribute(attr.name);
                }
                if (oldNode.value !== undefined && newNode.value !== undefined && oldNode.value !== newNode.value) oldNode.value = newNode.value;
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
        this._signalUnsubscribes?.forEach(unsub => unsub());
        ['click', 'input', 'change', 'submit', 'blur', 'focus'].forEach(evt => {
            document.removeEventListener(evt, this._onEvent, true);
        });
        this._handlers.clear();
        this.onDestroy();
    }

    async onInit() {}
    async onMount() {}
    onDestroy() {}
    render() { return ''; }
}
