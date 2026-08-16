/**
 * SPP Live - Reactive Frontend State Manager
 * Zero-dependency DOM patching and state synchronization via SPP Ajax & SPP Live WebSockets.
 */

class SPPLive {
    constructor() {
        this.components = {};
        this.socket = null;
        this.eventSource = null;
        this.useWebSocket = false; 
        this.useSSE = false;
        this.topics = window.SPPLiveTopics || ['global'];
        this.offlineQueue = [];
        this.debounceTimers = {};
        this.pollTimers = {};
        this.prefetchedPages = {};
        
        // Phase 5: Request Bundling
        this.pendingUpdates = [];
        this._bundleTimer = null;

        // Phase 3: Fix memory leaks
        this._initialized = false;
        this._heartbeatInterval = null;
        this._wsRetryCount = 0;
    }

    init() {
        if (this._initialized) {
            this.reinitAfterNavigation();
            return;
        }
        this._initialized = true;

        this.injectStyles();
        this.scanComponents();
        this.attachListeners();
        this.attachNavigateListeners();
        this.connect();

        window.addEventListener('online', () => {
            this.toggleOfflineElements(false);
            this.flushOfflineQueue();
        });
        window.addEventListener('offline', () => {
            console.warn('[SPPLive] Went offline. Updates will be queued.');
            this.toggleOfflineElements(true);
        });

        // Start presence heartbeat
        this.startHeartbeat();

        // Dispatch initialization event
        document.dispatchEvent(new CustomEvent('spplive:init'));

        if (!this.cleanupObserver) {
            this.cleanupObserver = new MutationObserver((mutations) => {
                let hasNewLiveComponents = false;
                mutations.forEach(mutation => {
                    mutation.removedNodes.forEach(node => {
                        if (node.nodeType === 1) { 
                            if (node.hasAttribute && node.hasAttribute('wire:id')) {
                                this.cleanupComponent(node.getAttribute('wire:id'));
                            }
                            if (node.querySelectorAll) {
                                node.querySelectorAll('[wire\\:id]').forEach(child => {
                                    this.cleanupComponent(child.getAttribute('wire:id'));
                                });
                            }
                        }
                    });
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) {
                            if (node.hasAttribute && node.hasAttribute('wire:id')) hasNewLiveComponents = true;
                            else if (node.querySelector && node.querySelector('[wire\\:id]')) hasNewLiveComponents = true;
                        }
                    });
                });
                if (hasNewLiveComponents) {
                    this.scanComponents();
                }
            });
            this.cleanupObserver.observe(document.body, { childList: true, subtree: true });
        }
    }

    reinitAfterNavigation() {
        this.scanComponents();
    }

    cleanupComponent(id) {
        const comp = this.components[id];
        if (comp) {
            if (comp.boundListeners) {
                comp.boundListeners.forEach(({ evtName, handler }) => {
                    window.removeEventListener(evtName, handler);
                });
            }
            if (this.pollTimers[id]) {
                clearInterval(this.pollTimers[id]);
                delete this.pollTimers[id];
            }
            delete this.components[id];
        }
    }

    injectStyles() {
        if (!document.getElementById('spplive-styles')) {
            const style = document.createElement('style');
            style.id = 'spplive-styles';
            if (window.SPPLiveNonce) {
                style.setAttribute('nonce', window.SPPLiveNonce);
            }
            style.innerHTML = `
                [wire\\:loading] { display: none; }
                [wire\\:offline] { display: none; }
                [wire\\:dirty] { display: none; }
                .spplive-inline { display: inline-block !important; }
                .spplive-block { display: block !important; }
                .spplive-flex { display: flex !important; }
            `;
            document.head.appendChild(style);
        }
    }

    toggleOfflineElements(isOffline) {
        document.querySelectorAll('[wire\\:offline]').forEach(el => {
            if (isOffline) {
                if (el.hasAttribute('wire:offline.class')) {
                    el.classList.add(...el.getAttribute('wire:offline.class').split(' '));
                } else {
                    el.style.display = 'inline-block';
                }
            } else {
                if (el.hasAttribute('wire:offline.class')) {
                    el.classList.remove(...el.getAttribute('wire:offline.class').split(' '));
                } else {
                    el.style.display = '';
                }
            }
        });
    }

    startHeartbeat() {
        if (this._heartbeatInterval) {
            clearInterval(this._heartbeatInterval);
        }
        this._heartbeatInterval = setInterval(() => {
            if (navigator.onLine) {
                fetch('?__spa=1&__svc=live_presence', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-SPP-Ajax': '1' },
                    body: JSON.stringify({ topics: this.topics })
                }).catch(err => console.error('[SPPLive] Heartbeat failed:', err));
            }
        }, 15000); // Every 15 seconds
    }

    async flushOfflineQueue() {
        console.log('[SPPLive] Back online. Flushing queue sequentially...');
        while (this.offlineQueue.length > 0) {
            const payload = this.offlineQueue.shift();
            try {
                const response = await fetch('?__spa=1&__svc=live_update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-SPP-Ajax': '1' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (data.success) {
                    this.handleResponse(data.result);
                    if (this.offlineQueue.length > 0) {
                        const comp = this.components[data.result.id];
                        if (comp) {
                            this.offlineQueue[0].state = comp.state;
                            this.offlineQueue[0].checksum = comp.checksum;
                        }
                    }
                }
            } catch (e) { 
                console.error('[SPPLive] Offline replay failed:', e); 
                break; 
            }
        }
    }

    connect() {
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        if (window.SPPLiveEnabled) {
            this.tryWebSocket();
        } else if (window.SPPLiveUseSSE !== false) {
            this.trySSE();
        }
    }

    scanComponents() {
        const currentIds = new Set();
        document.querySelectorAll('[wire\\:id]').forEach(el => {
            const id = el.getAttribute('wire:id');
            currentIds.add(id);
            if (!this.components[id]) {
                this.components[id] = {
                    el: el,
                    state: JSON.parse(el.getAttribute('wire:state') || '{}'),
                    updates: {},
                    checksum: el.getAttribute('wire:checksum'),
                    componentClass: el.getAttribute('wire:component'),
                    isolated: el.hasAttribute('wire:isolate')
                };
            } else {
                // Update element reference if it was replaced
                this.components[id].el = el;
            }

            // Lazy Loading (wire:init)
            if (el.hasAttribute('wire:init') && !el.hasAttribute('wire:init-done')) {
                el.setAttribute('wire:init-done', '1');
                this.sendUpdate(id, el.getAttribute('wire:init'));
            }

            // Auto-Polling (wire:poll)
            Array.from(el.attributes).forEach(attr => {
                if (attr.name.startsWith('wire:poll')) {
                    if (!this.pollTimers[id]) {
                        let ms = 2000;
                        const match = attr.name.match(/\.poll\.(\d+)s/);
                        if (match) ms = parseInt(match[1]) * 1000;
                        else {
                            const matchMs = attr.name.match(/\.poll\.(\d+)ms/);
                            if (matchMs) ms = parseInt(matchMs[1]);
                        }
                        
                        const isVisibleOnly = attr.name.includes('.visible');
                        let isCurrentlyVisible = !isVisibleOnly; // if not restricted, always true
                        
                        if (isVisibleOnly) {
                            const observer = new IntersectionObserver((entries) => {
                                isCurrentlyVisible = entries[0].isIntersecting;
                            });
                            observer.observe(el);
                        }

                        this.pollTimers[id] = setInterval(() => {
                            if (document.querySelector(`[wire\\:id="${id}"]`)) {
                                if (isCurrentlyVisible) {
                                    this.sendUpdate(id, attr.value || null);
                                }
                            } else {
                                clearInterval(this.pollTimers[id]);
                                delete this.pollTimers[id];
                            }
                        }, ms);
                    }
                }
            });
        });

        // Cleanup poll timers for removed components
        Object.keys(this.pollTimers).forEach(id => {
            if (!currentIds.has(id)) {
                clearInterval(this.pollTimers[id]);
                delete this.pollTimers[id];
            }
        });
    }

    attachNavigateListeners() {
        document.addEventListener('mouseover', e => {
            const link = e.target.closest('a[wire\\:navigate\\.hover]');
            if (link && link.href) {
                const url = link.href;
                if (!this.prefetchedPages[url]) {
                    this.prefetchedPages[url] = 'fetching';
                    fetch(url)
                        .then(res => res.text())
                        .then(html => this.prefetchedPages[url] = html)
                        .catch(() => delete this.prefetchedPages[url]);
                }
            }
        });

        document.addEventListener('click', e => {
            const link = e.target.closest('a[wire\\:navigate], a[wire\\:navigate\\.hover]');
            if (link) {
                // Ignore modifier keys and special targets
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                if (link.target === '_blank') return;
                if (link.hasAttribute('download')) return;
                if (link.href.startsWith('mailto:')) return;
                
                const urlObj = new URL(link.href, document.baseURI);
                if (urlObj.origin !== window.location.origin) return;

                e.preventDefault();
                this.navigate(link.href);
            }
        });

        window.addEventListener('popstate', () => {
            this.navigate(window.location.href, false);
        });
    }

    navigate(url, pushState = true) {
        console.log(`[SPPLive] Navigating to ${url}`);
        
        // Save scroll position for current URL
        if (pushState) {
            history.replaceState({ scrollY: window.scrollY }, '', window.location.href);
        }
        
        const processHtml = (html) => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            document.title = doc.title;
            
            const updateDOM = () => {
                // Sync Head (diff styles, meta, etc)
                Array.from(document.head.children).forEach(el => {
                    if (el.tagName !== 'SCRIPT' && el.tagName !== 'TITLE') el.remove();
                });
                Array.from(doc.head.children).forEach(el => {
                    if (el.tagName !== 'SCRIPT' && el.tagName !== 'TITLE') {
                        document.head.appendChild(el.cloneNode(true));
                    }
                });
                
                // Re-evaluate new scripts
                Array.from(doc.body.querySelectorAll('script')).forEach(script => {
                    const newScript = document.createElement('script');
                    Array.from(script.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.innerHTML = script.innerHTML;
                    if (window.SPPLiveNonce) newScript.setAttribute('nonce', window.SPPLiveNonce);
                    script.replaceWith(newScript);
                });

                if (window.SPPUX && typeof window.SPPUX.morph === 'function') {
                    document.dispatchEvent(new CustomEvent('spplive:morphing', { detail: { id: 'root', el: document.body, html: doc.body.outerHTML } }));
                    window.SPPUX.morph(document.body, doc.body.outerHTML);
                    document.dispatchEvent(new CustomEvent('spplive:morphed', { detail: { id: 'root', el: document.body } }));
                } else {
                    document.body.replaceWith(doc.body);
                }

                if (pushState) {
                    history.pushState({ scrollY: 0 }, doc.title, url);
                    window.scrollTo(0, 0);
                } else {
                    // Restore scroll position
                    const savedScrollY = history.state && history.state.scrollY !== undefined ? history.state.scrollY : 0;
                    window.scrollTo(0, savedScrollY);
                }
                
                this.init(); // Re-initialize after navigation
            };

            if (document.startViewTransition) {
                document.startViewTransition(updateDOM);
            } else {
                updateDOM();
            }
        };

        if (this.prefetchedPages[url] && this.prefetchedPages[url] !== 'fetching') {
            processHtml(this.prefetchedPages[url]);
            // Clear prefetched cache to prevent stale navigations later
            delete this.prefetchedPages[url];
        } else {
            fetch(url)
                .then(res => res.text())
                .then(processHtml)
                .catch(err => {
                    console.error('[SPPLive] Navigation failed, falling back to standard load', err);
                    window.location.href = url;
                });
        }
    }

    parseModelDirective(raw) {
        // Known modifiers that are NOT property path segments
        const MODIFIERS = ['live', 'blur', 'change', 'defer', 'debounce', 'throttle', 'boolean', 'number', 'fill', 'lazy', 'self'];
        
        const parts = raw.split('.');
        let propParts = [];
        let modifiers = {};
        let i = 0;
        
        while (i < parts.length) {
            if (MODIFIERS.includes(parts[i])) {
                while (i < parts.length) {
                    if (parts[i] === 'debounce' || parts[i] === 'throttle') {
                        modifiers[parts[i]] = parseInt(parts[i + 1], 10) || 150;
                        i += 2;
                    } else {
                        modifiers[parts[i]] = true;
                        i++;
                    }
                }
                break;
            }
            propParts.push(parts[i]);
            i++;
        }
        return { propPath: propParts.join('.'), modifiers };
    }
    
    getModelValue(el, id, propPath) {
        if (el.type === 'checkbox') {
            const comp = this.components[id];
            const currentValue = (comp.updates && comp.updates[propPath] !== undefined) ? comp.updates[propPath] : comp.state[propPath];
            if (Array.isArray(currentValue)) {
                const arr = [...currentValue];
                if (el.checked) { 
                    if (!arr.includes(el.value)) arr.push(el.value); 
                } else { 
                    const idx = arr.indexOf(el.value); 
                    if (idx > -1) arr.splice(idx, 1); 
                }
                return arr;
            }
            return el.checked;
        }
        if (el.type === 'radio') return el.value;
        if (el.tagName === 'SELECT' && el.multiple) {
            return Array.from(el.selectedOptions).map(o => o.value);
        }
        if (el.isContentEditable) return el.innerHTML;
        return el.value;
    }

    attachListeners() {
        document.addEventListener('submit', e => {
            const formEl = e.target.closest('[wire\\:submit]');
            if (formEl) {
                e.preventDefault();
                const action = formEl.getAttribute('wire:submit');
                const root = formEl.closest('[wire\\:id]');
                if (root) {
                    const id = root.getAttribute('wire:id');
                    // Send any pending updates before submitting
                    this.sendUpdate(id, action);
                }
            }
        });

        document.addEventListener('keydown', e => {
            const el = e.target.closest('[wire\\:keydown]');
            if (el) {
                const attr = el.getAttribute('wire:keydown');
                // Basic implementation for wire:keydown.enter
                if (el.hasAttribute('wire:keydown.enter') && e.key === 'Enter') {
                    e.preventDefault();
                    const action = el.getAttribute('wire:keydown.enter');
                    const root = el.closest('[wire\\:id]');
                    if (root) this.sendUpdate(root.getAttribute('wire:id'), action);
                }
            }
        });

        document.addEventListener('click', e => {
            const clickEl = e.target.closest('[wire\\:click]');
            if (clickEl) {
                e.preventDefault();
                let action = clickEl.getAttribute('wire:click');
                
                // Action Confirmation
                if (clickEl.hasAttribute('wire:confirm')) {
                    if (!window.confirm(clickEl.getAttribute('wire:confirm'))) {
                        e.stopImmediatePropagation();
                        return;
                    }
                }
                
                // Optimistic UI
                if (action.includes('.optimistic')) {
                    action = action.replace('.optimistic', '');
                    const optClass = clickEl.getAttribute('wire:optimistic.class');
                    if (optClass) clickEl.classList.add(...optClass.split(' '));
                    
                    const optAttr = clickEl.getAttribute('wire:optimistic.attr');
                    if (optAttr) clickEl.setAttribute(optAttr, 'true');
                }

                const root = clickEl.closest('[wire\\:id]');
                if (root) {
                    this.sendUpdate(root.getAttribute('wire:id'), action);
                }
            }
        });

        document.addEventListener('input', e => {
            const modelEl = e.target.closest('[wire\\:model]');
            if (modelEl) {
                const { propPath, modifiers } = this.parseModelDirective(modelEl.getAttribute('wire:model'));
                const root = modelEl.closest('[wire\\:id]');
                if (root) {
                    const id = root.getAttribute('wire:id');
                    if (this.components[id]) {
                        this.markDirty(id, propPath, modelEl);
                        
                        if (modelEl.type === 'file') {
                            this.handleFileUpload(modelEl, id, propPath);
                        } else {
                            this.components[id].updates[propPath] = this.getModelValue(modelEl, id, propPath);
                            
                            // wire:model without modifiers is deferred by default in Livewire v3 semantics
                            if (!modifiers.live && !modifiers.blur && !modifiers.change) {
                                return;
                            }
                            if (modifiers.live) {
                                const debounceMs = modifiers.debounce || 150;
                                clearTimeout(this.debounceTimers[propPath]);
                                this.debounceTimers[propPath] = setTimeout(() => {
                                    this.sendUpdate(id, null);
                                }, debounceMs);
                            }
                        }
                    }
                }
            }
        });

        document.addEventListener('change', e => {
            const modelEl = e.target.closest('[wire\\:model]');
            if (modelEl) {
                const { propPath, modifiers } = this.parseModelDirective(modelEl.getAttribute('wire:model'));
                if (!modifiers.blur && !modifiers.change) return;

                const root = modelEl.closest('[wire\\:id]');
                if (root) {
                    const id = root.getAttribute('wire:id');
                    if (this.components[id]) {
                        this.components[id].updates[propPath] = this.getModelValue(modelEl, id, propPath);
                        this.markDirty(id, propPath, modelEl);
                        this.sendUpdate(id, null);
                    }
                }
            }
        });
        
        document.addEventListener('blur', e => {
            const modelEl = e.target.closest('[wire\\:model]');
            if (modelEl) {
                const { propPath, modifiers } = this.parseModelDirective(modelEl.getAttribute('wire:model'));
                if (modifiers.blur) {
                    const root = modelEl.closest('[wire\\:id]');
                    if (root) {
                        const id = root.getAttribute('wire:id');
                        if (this.components[id]) {
                            this.components[id].updates[propPath] = this.getModelValue(modelEl, id, propPath);
                            this.markDirty(id, propPath, modelEl);
                            this.sendUpdate(id, null);
                        }
                    }
                }
            }
        }, true); // use capture phase for blur
    }

    tryWebSocket() {
        const protocol = location.protocol === 'https:' ? 'wss' : 'ws';
        const host = window.location.hostname;
        const port = window.SPPLivePort || (location.protocol === 'https:' ? 443 : 8080);
        const path = window.SPPLivePath || '';
        
        this.socket = new WebSocket(`${protocol}://${host}:${port}${path}`);
        
        this.socket.onopen = () => {
            this.useWebSocket = true;
            this._wsRetryCount = 0;
            console.log('[SPPLive] Connected to WebSocket');
            // Send topic subscription
            this.socket.send(JSON.stringify({ action: 'subscribe', topics: this.topics }));
        };
        this.socket.onmessage = (msg) => {
            const data = JSON.parse(msg.data);
            this.handleResponse(data);
        };
        this.socket.onerror = () => {
            console.log('[SPPLive] WebSocket error');
        };
        this.socket.onclose = () => {
            this.useWebSocket = false;
            console.log('[SPPLive] WebSocket closed. Attempting reconnect...');
            this.reconnectWebSocket();
        };
    }
    
    reconnectWebSocket() {
        const delay = Math.min(1000 * Math.pow(2, this._wsRetryCount), 30000);
        this._wsRetryCount++;
        setTimeout(() => this.tryWebSocket(), delay);
    }

    trySSE() {
        if (typeof window.EventSource === "undefined") {
            console.log('[SPPLive] SSE not supported by browser, falling back to SPPAjax polling');
            return;
        }

        const topicsQuery = this.topics.join(',');
        this.eventSource = new EventSource(`?__spa=1&__svc=live_sse&topics=${topicsQuery}`);
        
        this.eventSource.onopen = () => {
            this.useSSE = true;
            console.log('[SPPLive] Connected to SSE');
        };

        this.eventSource.addEventListener('live_update', (e) => {
            try {
                const data = JSON.parse(e.data);
                this.handleResponse(data);
            } catch (err) {
                console.error('[SPPLive] Error parsing SSE data:', err);
            }
        });

        this.eventSource.addEventListener('spplive_connect', (e) => {
            console.log('[SPPLive] SSE Stream active:', e.data);
        });

        this.eventSource.onerror = () => {
            console.log('[SPPLive] SSE Connection error. Browser will auto-reconnect or fallback.');
            // Let the browser handle EventSource reconnect natively
        };
    }

    sendUpdate(id, method, params = []) {
        const comp = this.components[id];
        if (!comp) return;

        // Clone updates and clear local so subsequent inputs queue correctly
        const payloadUpdates = { ...comp.updates };
        comp.updates = {};

        const payload = {
            id: id, // Needed for bundled payloads mapping
            component: comp.componentClass,
            state: comp.state,
            updates: payloadUpdates,
            checksum: comp.checksum,
            method: method,
            params: params,
            topics: this.topics
        };

        if (!navigator.onLine) {
            this.offlineQueue.push(payload);
            console.log('[SPPLive] Queued update while offline');
            return;
        }

        this.startLoading(id, method);
        
        // Phase 5: Request Bundling
        this.scheduleSend(payload);
    }
    
    scheduleSend(payload) {
        this.pendingUpdates.push(payload);
        if (!this._bundleTimer) {
            this._bundleTimer = setTimeout(() => {
                this._bundleTimer = null;
                const batch = this.pendingUpdates.splice(0);
                this.dispatchBundle(batch);
            }, 5); // 5ms microtask batch window
        }
    }

    dispatchBundle(batch) {
        if (batch.length === 0) return;
        
        const payload = batch.length === 1 ? batch[0] : { components: batch };
        
        if (this.useWebSocket && this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ action: 'live_update', payload }));
        } else {
            fetch('?__spa=1&__svc=live_update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-SPP-Ajax': '1'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.result && data.result.results) {
                        data.result.results.forEach(res => this.handleResponse(res));
                    } else if (data.result) {
                        this.handleResponse(data.result);
                    }
                } else {
                    console.error('[SPPLive] Server error:', data);
                }
            })
            .catch(err => {
                console.error('[SPPLive] Fetch error:', err);
                batch.forEach(b => this.stopLoading(b.id || b.state.id));
            });
        }
    }

    startLoading(id, action) {
        const comp = this.components[id];
        if (!comp) return;

        comp.loadingNodes = [];
        
        const applyLoading = (els, type, propName) => {
            els.forEach(el => {
                const target = el.getAttribute('wire:target');
                if (target && target !== action && !target.split(',').includes(action)) return;

                let delayMs = 0;
                if (el.hasAttribute('wire:loading.delay')) {
                    delayMs = 200;
                    if (el.hasAttribute('wire:loading.delay.short')) delayMs = 100;
                    if (el.hasAttribute('wire:loading.delay.long')) delayMs = 300;
                    if (el.hasAttribute('wire:loading.delay.longest')) delayMs = 1000;
                }

                const apply = () => {
                    if (type === 'display') {
                        comp.loadingNodes.push({ el, type, original: el.style.display });
                        el.style.display = 'inline-block';
                    } else if (type === 'display.remove') {
                        comp.loadingNodes.push({ el, type, original: el.style.display });
                        el.style.display = 'none';
                    } else if (type === 'class') {
                        const classes = el.getAttribute('wire:loading.class').split(' ');
                        comp.loadingNodes.push({ el, type, classes });
                        el.classList.add(...classes);
                    } else if (type === 'class.remove') {
                        const classes = el.getAttribute('wire:loading.class.remove').split(' ');
                        comp.loadingNodes.push({ el, type, classes });
                        el.classList.remove(...classes);
                    } else if (type === 'attr') {
                        const attr = el.getAttribute('wire:loading.attr');
                        comp.loadingNodes.push({ el, type, attr });
                        el.setAttribute(attr, 'true');
                    }
                };

                if (delayMs > 0) {
                    el._sppDelayTimeout = setTimeout(apply, delayMs);
                    comp.loadingNodes.push({ el, type: 'delay', timeout: el._sppDelayTimeout });
                } else {
                    apply();
                }
            });
        };

        applyLoading(comp.el.querySelectorAll('[wire\\:loading]:not([wire\\:loading\\.remove]):not([wire\\:loading\\.class]):not([wire\\:loading\\.class\\.remove]):not([wire\\:loading\\.attr])'), 'display');
        applyLoading(comp.el.querySelectorAll('[wire\\:loading\\.remove]'), 'display.remove');
        applyLoading(comp.el.querySelectorAll('[wire\\:loading\\.class]'), 'class');
        applyLoading(comp.el.querySelectorAll('[wire\\:loading\\.class\\.remove]'), 'class.remove');
        applyLoading(comp.el.querySelectorAll('[wire\\:loading\\.attr]'), 'attr');
    }

    stopLoading(id) {
        const comp = this.components[id];
        if (!comp || !comp.loadingNodes) return;

        comp.loadingNodes.forEach(item => {
            if (item.type === 'delay') {
                clearTimeout(item.timeout);
            } else if (item.type === 'display') {
                item.el.style.display = item.original;
            } else if (item.type === 'display.remove') {
                item.el.style.display = item.original;
            } else if (item.type === 'class') {
                item.el.classList.remove(...item.classes);
            } else if (item.type === 'class.remove') {
                item.el.classList.add(...item.classes);
            } else if (item.type === 'attr') {
                item.el.removeAttribute(item.attr);
            }
        });
        comp.loadingNodes = [];
    }

    markDirty(id, propName, modelEl) {
        const comp = this.components[id];
        if (!comp) return;

        // wire:dirty on specific elements targeting this prop
        const dirtyNodes = comp.el.querySelectorAll('[wire\\:dirty]');
        dirtyNodes.forEach(el => {
            const target = el.getAttribute('wire:target');
            if (target && target !== propName && !target.split(',').includes(propName)) return;

            if (el.hasAttribute('wire:dirty.class')) {
                el.classList.add(...el.getAttribute('wire:dirty.class').split(' '));
            } else if (el.hasAttribute('wire:dirty.class.remove')) {
                el.classList.remove(...el.getAttribute('wire:dirty.class.remove').split(' '));
            } else {
                el.style.display = 'inline-block';
            }
        });

        // if the model element itself has a dirty class
        if (modelEl.hasAttribute('wire:dirty.class')) {
            modelEl.classList.add(...modelEl.getAttribute('wire:dirty.class').split(' '));
        }
    }

    handleFileUpload(input, id, propName) {
        if (!input.files.length) return;
        
        const formData = new FormData();
        Array.from(input.files).forEach(file => {
            formData.append('files[]', file);
        });
        
        console.log('[SPPLive] Uploading files to staging...');
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '?__spa=1&__svc=live_upload', true);
        xhr.setRequestHeader('X-SPP-Live-Checksum', this.components[id].checksum);
        xhr.setRequestHeader('X-SPP-Live-Id', id);
        
        // Progress tracking
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                const progressEvent = new CustomEvent('spplive:upload-progress', { detail: { prop: propName, progress: percentComplete } });
                input.dispatchEvent(progressEvent);
                document.dispatchEvent(progressEvent);
                
                // Automatically update UI elements with wire:upload-progress
                if (this.components[id]) {
                    const progressEls = this.components[id].el.querySelectorAll(`[wire\\:upload-progress="${propName}"]`);
                    progressEls.forEach(el => {
                        if (el.tagName === 'PROGRESS') {
                            el.value = percentComplete;
                        } else {
                            el.innerText = `${percentComplete}%`;
                            el.style.setProperty('--progress', `${percentComplete}%`);
                        }
                    });
                }
            }
        };

        xhr.onload = () => {
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success && data.tokens) {
                        this.components[id].updates[propName] = input.multiple ? data.tokens : data.tokens[0];
                        this.sendUpdate(id, null);
                    } else {
                        console.error('[SPPLive] File upload failed:', data);
                    }
                } catch (err) {
                    console.error('[SPPLive] Invalid upload response:', xhr.responseText);
                }
            } else {
                console.error('[SPPLive] Upload error, status:', xhr.status);
            }
        };
        
        xhr.onerror = () => console.error('[SPPLive] Upload network error');
        xhr.send(formData);
    }

    handleResponse(data) {
        if (!data) return;

        if (data.flash) {
            if (window.SPPUX && typeof window.SPPUX.notify === 'function') {
                window.SPPUX.notify(data.flash.message, data.flash.type);
            } else {
                alert(`[${data.flash.type.toUpperCase()}] ${data.flash.message}`);
            }
            if (data.flash.type === 'error') return;
        }

        if (!data.id) return;

        if (data.title) {
            document.title = data.title;
        }

        if (data.download) {
            const a = document.createElement('a');
            a.href = data.download.url;
            if (data.download.name) {
                a.download = data.download.name;
            }
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        const id = data.id;
        const comp = this.components[id];
        
        this.stopLoading(id);

        if (comp) {
            // Check for wire:stream updates before morphing
            if (data.streams && Array.isArray(data.streams)) {
                data.streams.forEach(s => {
                    const target = comp.el.querySelector(`[wire\\:stream="${s.to}"]`);
                    if (target) {
                        if (s.replace) target.innerHTML = s.content;
                        else target.innerHTML += s.content;
                    }
                });
            }

            // Only morph if we have HTML (Renderless actions return empty HTML)
            if (data.html) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.html.trim();
                const newEl = tempDiv.firstChild;
                
                newEl.setAttribute('wire:id', id);
                newEl.setAttribute('wire:state', JSON.stringify(data.state));
                newEl.setAttribute('wire:checksum', data.checksum);
                newEl.setAttribute('wire:component', comp.componentClass);

                if (window.SPPUX && typeof window.SPPUX.morph === 'function') {
                    // Protect wire:ignore elements
                    const ignores = comp.el.querySelectorAll('[wire\\:ignore]');
                    ignores.forEach(oldIgnore => {
                        if (oldIgnore.id) {
                            const newIgnore = newEl.querySelector(`#${oldIgnore.id}`);
                            if (newIgnore) {
                                newIgnore.replaceWith(oldIgnore.cloneNode(true));
                            }
                        }
                    });

                    // Focus Protection
                    const activeEl = document.activeElement;
                    let selectionStart = null, selectionEnd = null, activeModelName = null;
                    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                        activeModelName = activeEl.getAttribute('wire:model');
                        if (activeModelName) {
                            const newActive = newEl.querySelector(`[wire\\:model="${activeModelName}"]`);
                            if (newActive) {
                                newActive.value = activeEl.value;
                            }
                        }
                        try {
                            selectionStart = activeEl.selectionStart;
                            selectionEnd = activeEl.selectionEnd;
                        } catch (e) {}
                    }
                    
                    document.dispatchEvent(new CustomEvent('spplive:morphing', { detail: { id, el: comp.el, html: newEl.outerHTML } }));
                    window.SPPUX.morph(comp.el, newEl.outerHTML);
                    comp.el = document.querySelector(`[wire\\:id="${id}"]`) || comp.el;

                    if (activeModelName) {
                        const restoredActive = comp.el.querySelector(`[wire\\:model="${activeModelName}"]`);
                        if (restoredActive) {
                            restoredActive.focus();
                            if (selectionStart !== null && selectionEnd !== null) {
                                try { restoredActive.setSelectionRange(selectionStart, selectionEnd); } catch (e) {}
                            }
                        }
                    }

                    document.dispatchEvent(new CustomEvent('spplive:morphed', { detail: { id, el: comp.el } }));
                } else {
                    comp.el.replaceWith(newEl);
                }
            }
            
            comp.state = data.state;
            comp.checksum = data.checksum;
            if (data.isolated !== undefined) {
                comp.isolated = data.isolated;
            }

            // Handle Query String Sync
            if (data.queryString && Object.keys(data.queryString).length > 0) {
                const url = new URL(window.location.href);
                let changed = false;
                for (const [key, value] of Object.entries(data.queryString)) {
                    if (value === null || value === '') {
                        if (url.searchParams.has(key)) {
                            url.searchParams.delete(key);
                            changed = true;
                        }
                    } else if (url.searchParams.get(key) !== String(value)) {
                        url.searchParams.set(key, value);
                        changed = true;
                    }
                }
                if (changed) {
                    window.history.replaceState(null, '', url.toString());
                }
            }
            
            // Process any server-dispatched events
            if (data.events && Array.isArray(data.events)) {
                data.events.forEach(evt => {
                    let target = 'global';
                    if (evt.params.length > 0 && typeof evt.params[evt.params.length - 1] === 'string' && evt.params[evt.params.length - 1].startsWith('target:')) {
                        target = evt.params.pop().substring(7);
                    }
                    
                    const customEvt = new CustomEvent(evt.name, { detail: evt.params, bubbles: false });
                    
                    if (target === 'up') {
                        const parentEl = comp.el.parentElement ? comp.el.parentElement.closest('[wire\\:id]') : null;
                        if (parentEl) {
                            parentEl.dispatchEvent(customEvt);
                        }
                    } else if (target !== 'global') {
                        const targetEl = document.querySelector(`[wire\\:id="${target}"]`);
                        if (targetEl) {
                            targetEl.dispatchEvent(customEvt);
                        }
                    } else {
                        window.dispatchEvent(customEvt);
                    }
                });
            }
            
            // Bind #[On] listeners
            if (data.listeners && !comp.listenersRegistered) {
                comp.listenersRegistered = true;
                comp.boundListeners = comp.boundListeners || [];
                for (const [evtName, method] of Object.entries(data.listeners)) {
                    const handler = e => {
                        if (this.components[id]) {
                            if (this.components[id].isolated && e.target === window && !e.detail?.target) return;
                            this.sendUpdate(id, method, [e.detail || []]);
                        }
                    };
                    window.addEventListener(evtName, handler);
                    comp.el.addEventListener(evtName, handler);
                    comp.boundListeners.push({ evtName, handler });
                }
            }
            
            this.scanComponents();
        }
    }

    store(id) {
        if (!this.components[id]) return null;
        const self = this;
        return new Proxy(this.components[id].state, {
            set: (target, prop, value) => {
                self.components[id].updates[prop] = value;
                self.sendUpdate(id, null);
                return true;
            }
        });
    }

    entangle(element, propName) {
        const root = element.closest('[wire\\:id]');
        if (!root) return null;
        
        const id = root.getAttribute('wire:id');
        const comp = this.components[id];
        if (!comp) return null;

        return {
            get value() {
                return comp.updates[propName] !== undefined ? comp.updates[propName] : comp.state[propName];
            },
            set value(val) {
                if (this.value !== val) {
                    comp.updates[propName] = val;
                    window.SPP.Live.sendUpdate(id, null);
                }
            }
        };
    }
    
    getWireProxy(id) {
        const self = this;
        const comp = this.components[id];
        if (!comp) return null;
        
        return new Proxy({}, {
            get(_, prop) {
                if (prop === '$refresh') return () => self.sendUpdate(id, '$refresh');
                if (prop === '$set') return (key, val, live = true) => { comp.updates[key] = val; if (live) self.sendUpdate(id, null); };
                if (prop === '$get') return (key) => comp.updates[key] ?? comp.state[key];
                if (prop === '$dispatch') return (evt, data) => self.sendUpdate(id, '$dispatch', [evt, data]);
                
                // Property access → read from state
                if (prop in comp.state) return comp.state[prop];
                // Method call → send to server
                return (...args) => self.sendUpdate(id, prop, args);
            },
            set(_, prop, value) {
                comp.updates[prop] = value;
                self.sendUpdate(id, null);
                return true;
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.SPP = window.SPP || {};
    window.SPP.Live = new SPPLive();
    window.SPP.Live.init();
    
    // Global alias for entangle
    window.SPPEntangle = (element, propName) => window.SPP.Live.entangle(element, propName);
});
