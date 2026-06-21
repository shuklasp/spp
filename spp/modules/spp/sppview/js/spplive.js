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
    }

    init() {
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
                });
            });
            this.cleanupObserver.observe(document.body, { childList: true, subtree: true });
        }
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
        setInterval(() => {
            if (navigator.onLine) {
                fetch('?__spa=1&__svc=live_presence', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-SPP-Ajax': '1' },
                    body: JSON.stringify({ topics: this.topics })
                }).catch(err => console.error('[SPPLive] Heartbeat failed:', err));
            }
        }, 15000); // Every 15 seconds
    }

    flushOfflineQueue() {
        console.log('[SPPLive] Back online. Flushing queue...');
        while (this.offlineQueue.length > 0) {
            const payload = this.offlineQueue.shift();
            this.dispatchPayload(payload);
        }
    }

    connect() {
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
        
        const processHtml = (html) => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            document.title = doc.title;
            if (window.SPPUX && typeof window.SPPUX.morph === 'function') {
                document.dispatchEvent(new CustomEvent('spplive:morphing', { detail: { id: 'root', el: document.body, html: doc.body.outerHTML } }));
                window.SPPUX.morph(document.body, doc.body.outerHTML);
                document.dispatchEvent(new CustomEvent('spplive:morphed', { detail: { id: 'root', el: document.body } }));
            } else {
                document.body.replaceWith(doc.body);
            }

            if (pushState) history.pushState(null, doc.title, url);
            this.init(); // Re-initialize after navigation
        };

        if (this.prefetchedPages[url] && this.prefetchedPages[url] !== 'fetching') {
            processHtml(this.prefetchedPages[url]);
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

    attachListeners() {
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
                const prop = modelEl.getAttribute('wire:model');
                let debounceMs = 0;
                
                // Parse wire:model.debounce.Xms
                if (prop.includes('.debounce.')) {
                    const match = prop.match(/\.debounce\.(\d+)ms/);
                    if (match) debounceMs = parseInt(match[1], 10);
                }

                const propName = prop.split('.')[0]; // strip modifiers
                const root = modelEl.closest('[wire\\:id]');
                if (root) {
                    const id = root.getAttribute('wire:id');
                    if (this.components[id]) {
                        this.markDirty(id, propName, modelEl);
                        if (modelEl.type === 'file') {
                            this.handleFileUpload(modelEl, id, propName);
                        } else {
                            this.components[id].updates[propName] = modelEl.value;
                            
                            if (prop.includes('.defer')) {
                                return;
                            }
                            
                            if (debounceMs > 0) {
                                clearTimeout(this.debounceTimers[propName]);
                                this.debounceTimers[propName] = setTimeout(() => {
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
                const prop = modelEl.getAttribute('wire:model');
                if (prop.includes('.defer')) return;

                const propName = prop.split('.')[0];
                const root = modelEl.closest('[wire\\:id]');
                if (root) {
                    const id = root.getAttribute('wire:id');
                    if (this.components[id]) {
                        this.markDirty(id, propName, modelEl);
                        this.sendUpdate(id, null);
                    }
                }
            }
        });
    }

    tryWebSocket() {
        const host = window.location.hostname;
        const port = window.SPPLivePort || 8080;
        
        this.socket = new WebSocket(`ws://${host}:${port}`);
        this.socket.onopen = () => {
            this.useWebSocket = true;
            console.log('[SPPLive] Connected to WebSocket');
            // Send topic subscription
            this.socket.send(JSON.stringify({ action: 'subscribe', topics: this.topics }));
        };
        this.socket.onmessage = (msg) => {
            const data = JSON.parse(msg.data);
            this.handleResponse(data);
        };
        this.socket.onerror = () => {
            this.useWebSocket = false;
            console.log('[SPPLive] WebSocket failed, falling back to SSE');
            if (window.SPPLiveUseSSE !== false) {
                this.trySSE();
            }
        };
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

        const payload = {
            component: comp.componentClass,
            state: comp.state,
            updates: comp.updates,
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
        this.dispatchPayload(payload);
    }

    startLoading(id, action) {
        const comp = this.components[id];
        if (!comp) return;

        comp.loadingNodes = [];

        // wire:loading
        const loaders = comp.el.querySelectorAll('[wire\\:loading]');
        loaders.forEach(el => {
            const target = el.getAttribute('wire:target');
            if (target && target !== action && !target.split(',').includes(action)) return;

            comp.loadingNodes.push({ el, type: 'display', original: el.style.display });
            el.style.display = 'inline-block';
        });

        // wire:loading.remove
        const removeLoaders = comp.el.querySelectorAll('[wire\\:loading\\.remove]');
        removeLoaders.forEach(el => {
            const target = el.getAttribute('wire:target');
            if (target && target !== action && !target.split(',').includes(action)) return;

            comp.loadingNodes.push({ el, type: 'display.remove', original: el.style.display });
            el.style.display = 'none';
        });

        // wire:loading.class
        const classLoaders = comp.el.querySelectorAll('[wire\\:loading\\.class]');
        classLoaders.forEach(el => {
            const target = el.getAttribute('wire:target');
            if (target && target !== action && !target.split(',').includes(action)) return;

            const classes = el.getAttribute('wire:loading.class').split(' ');
            comp.loadingNodes.push({ el, type: 'class', classes: classes });
            el.classList.add(...classes);
        });

        // wire:loading.class.remove
        const classRemoveLoaders = comp.el.querySelectorAll('[wire\\:loading\\.class\\.remove]');
        classRemoveLoaders.forEach(el => {
            const target = el.getAttribute('wire:target');
            if (target && target !== action && !target.split(',').includes(action)) return;

            const classes = el.getAttribute('wire:loading.class.remove').split(' ');
            comp.loadingNodes.push({ el, type: 'class.remove', classes: classes });
            el.classList.remove(...classes);
        });

        // wire:loading.attr
        const attrLoaders = comp.el.querySelectorAll('[wire\\:loading\\.attr]');
        attrLoaders.forEach(el => {
            const target = el.getAttribute('wire:target');
            if (target && target !== action && !target.split(',').includes(action)) return;

            const attr = el.getAttribute('wire:loading.attr');
            comp.loadingNodes.push({ el, type: 'attr', attr: attr });
            el.setAttribute(attr, 'true');
        });
    }

    stopLoading(id) {
        const comp = this.components[id];
        if (!comp || !comp.loadingNodes) return;

        comp.loadingNodes.forEach(item => {
            if (item.type === 'display') {
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
        
        const file = input.files[0];
        const formData = new FormData();
        formData.append('file', file);
        
        console.log('[SPPLive] Uploading file to staging...');
        
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
                    if (data.success && data.token) {
                        this.components[id].updates[propName] = data.token;
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

    dispatchPayload(payload) {
        if (this.useWebSocket && this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ action: 'live_update', payload }));
        } else {
            // Fallback to SPPAjax (this works even if SSE is the downstream connection)
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
                    this.handleResponse(data.result); // Unpack 'result' from SPPAjax envelope
                } else {
                    console.error('[SPPLive] Server error:', data);
                }
            })
            .catch(err => {
                console.error('[SPPLive] Fetch error:', err);
                this.stopLoading(payload.state.id);
            });
        }
    }

    handleResponse(data) {
        if (!data) return;

        if (data.flash) {
            if (window.SPPUX && typeof window.SPPUX.notify === 'function') {
                window.SPPUX.notify(data.flash.message, data.flash.type);
            } else {
                alert(`[${data.flash.type.toUpperCase()}] ${data.flash.message}`);
            }
            if (data.flash.type === 'error') return; // Optionally stop rendering on hard error
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
        
        // Always attempt to stop loaders
        this.stopLoading(id);

        if (comp) {
            // Intelligent DOM Patching if SPPUX morph is available
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.html.trim();
            const newEl = tempDiv.firstChild;
            
            // Ensure necessary wire tags are preserved on root
            newEl.setAttribute('wire:id', id);
            newEl.setAttribute('wire:state', JSON.stringify(data.state));
            newEl.setAttribute('wire:checksum', data.checksum);
            newEl.setAttribute('wire:component', comp.componentClass);

            // Update JS memory object!
            comp.state = data.state;
            comp.checksum = data.checksum;
            comp.updates = {};
            if (data.isolated !== undefined) {
                comp.isolated = data.isolated;
            }

            if (window.SPPUX && typeof window.SPPUX.morph === 'function') {
                // Restore teleported elements back to their placeholders before morph
                document.body.querySelectorAll('[wire\\:teleported]').forEach(el => {
                    const tid = el.getAttribute('wire:teleported');
                    const placeholder = comp.el.querySelector(`template[wire\\:teleport-placeholder="${tid}"]`);
                    if (placeholder) {
                        el.removeAttribute('wire:teleported');
                        placeholder.replaceWith(el);
                    }
                });

                // Snapshot wire:transition elements before morphing
                const oldTransitions = Array.from(comp.el.querySelectorAll('[wire\\:transition]')).map(el => el.outerHTML);

                // Populate ID from wire:key to ensure morpher tracks them
                comp.el.querySelectorAll('[wire\\:key]').forEach(el => {
                    if (!el.id) el.id = el.getAttribute('wire:key');
                });
                newEl.querySelectorAll('[wire\\:key]').forEach(el => {
                    if (!el.id) el.id = el.getAttribute('wire:key');
                });

                // Dispatch morphing event
                document.dispatchEvent(new CustomEvent('spplive:morphing', { detail: { id, el: comp.el, html: newEl.outerHTML } }));

                // Capture Scroll Positions
                const scrollX = window.scrollX;
                const scrollY = window.scrollY;
                const internalScrolls = [];
                comp.el.querySelectorAll('*').forEach((el, index) => {
                    if (el.scrollTop > 0 || el.scrollLeft > 0) {
                        internalScrolls.push({ id: el.id, index: index, top: el.scrollTop, left: el.scrollLeft });
                    }
                });

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

                // Focus Protection (The Typewriter Race Condition Fix)
                const activeEl = document.activeElement;
                let selectionStart = null, selectionEnd = null, activeModelName = null;
                if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                    activeModelName = activeEl.getAttribute('wire:model');
                    if (activeModelName) {
                        const newActive = newEl.querySelector(`[wire\\:model="${activeModelName}"]`);
                        if (newActive) {
                            newActive.value = activeEl.value; // Prevent server overwrite
                        }
                    }
                    try {
                        selectionStart = activeEl.selectionStart;
                        selectionEnd = activeEl.selectionEnd;
                    } catch (e) {}
                }
                
                window.SPPUX.morph(comp.el, newEl.outerHTML);
                // Update element reference after morphing might not be needed if reference stays same, but safe to re-query
                comp.el = document.querySelector(`[wire\\:id="${id}"]`) || comp.el;
                
                // Restore Scroll Positions and Focus
                window.scrollTo(scrollX, scrollY);
                const newEls = Array.from(comp.el.querySelectorAll('*'));
                internalScrolls.forEach(s => {
                    const target = s.id ? comp.el.querySelector(`#${s.id}`) : newEls[s.index];
                    if (target) {
                        target.scrollTop = s.top;
                        target.scrollLeft = s.left;
                    }
                });

                if (activeModelName) {
                    const restoredActive = comp.el.querySelector(`[wire\\:model="${activeModelName}"]`);
                    if (restoredActive) {
                        restoredActive.focus();
                        if (selectionStart !== null && selectionEnd !== null) {
                            try { restoredActive.setSelectionRange(selectionStart, selectionEnd); } catch (e) {}
                        }
                    }
                }
                
                // Fade in new wire:transition elements
                const newTransitions = Array.from(comp.el.querySelectorAll('[wire\\:transition]'));
                newTransitions.forEach(node => {
                    if (!oldTransitions.includes(node.outerHTML)) {
                        node.style.opacity = '0';
                        setTimeout(() => {
                            node.style.transition = 'opacity 0.3s ease';
                            node.style.opacity = '1';
                        }, 20);
                    }
                });

                // Handle DOM Teleportation
                comp.el.querySelectorAll('[wire\\:teleport]').forEach(el => {
                    const targetSelector = el.getAttribute('wire:teleport') || 'body';
                    const targetEl = document.querySelector(targetSelector);
                    if (targetEl) {
                        const teleportId = el.id || 'teleport-' + Math.random().toString(36).substr(2, 9);
                        el.setAttribute('wire:teleported', teleportId);
                        
                        const placeholder = document.createElement('template');
                        placeholder.setAttribute('wire:teleport-placeholder', teleportId);
                        
                        el.replaceWith(placeholder);
                        targetEl.appendChild(el);
                    }
                });

                // Dispatch morphed event
                document.dispatchEvent(new CustomEvent('spplive:morphed', { detail: { id, el: comp.el } }));
            } else {
                comp.el.replaceWith(newEl);
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
                    console.log(`[SPPLive] Event broadcasted: ${evt.name} to ${target}`, evt.params);
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
            
            // Re-scan components
            this.scanComponents();
        }
    }

    store(id) {
        if (!this.components[id]) return null;
        
        return new Proxy(this.components[id].state, {
            set: (target, prop, value) => {
                target[prop] = value;
                // Dispatch update to sync PHP state
                this.sendUpdate(id, null);
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
                // Return from updates if it was recently changed, otherwise state
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
}

document.addEventListener('DOMContentLoaded', () => {
    window.SPP = window.SPP || {};
    window.SPP.Live = new SPPLive();
    window.SPP.Live.init();
    
    // Global alias for entangle
    window.SPPEntangle = (element, propName) => window.SPP.Live.entangle(element, propName);
});
