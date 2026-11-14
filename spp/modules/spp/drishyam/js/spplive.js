/**
 * SPPLive Full-Stack Integration Engine (Unified Core)
 * Features: wire:navigate, Server-Driven DOM Morphing with Keyed LIS diffing,
 * Differential Request Handling, Cursor Retention, wire:poll, wire:dirty,
 * wire:confirm, wire:stream, wire:upload, and State Corruption Failsafes.
 */

import { reconcileDOM } from './core/reconciler.js';

const activeSyncRequests = new Map(); // Tracks AbortControllers for wire:model only
const pollTimers = new Map(); // Tracks active polling intervals by element

// ─── Utilities ───────────────────────────────────────────────────────
function getTelemetryHeaders() {
    const headers = {};
    const traceparent = document.querySelector('meta[name="traceparent"]');
    const tracestate = document.querySelector('meta[name="tracestate"]');
    if (traceparent && traceparent.content) headers['traceparent'] = traceparent.content;
    if (tracestate && tracestate.content) headers['tracestate'] = tracestate.content;
    return headers;
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ─── Cursor Retention Engine (Fixes Cursor Jumps) ────────────────────
function morphWithCursorRetention(currentEl, newEl) {
    const active = document.activeElement;
    let cursorState = null;

    // Capture cursor state if the user is typing in an input/textarea
    if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA') && componentContains(currentEl, active)) {
        try {
            cursorState = {
                id: active.id || active.getAttribute('wire:model'),
                start: active.selectionStart,
                end: active.selectionEnd,
                direction: active.selectionDirection
            };
        } catch (e) {
            // Some input types throw an error on selectionStart access.
        }
    }

    // Perform the morph using Keyed LIS reconciler (with View Transitions if requested)
    if (typeof document !== 'undefined' && document.startViewTransition && (currentEl.hasAttribute('wire:transition') || currentEl.hasAttribute('wire:view-transition'))) {
        document.startViewTransition(() => {
            reconcileDOM(currentEl, newEl);
        });
    } else {
        reconcileDOM(currentEl, newEl);
    }

    // Restore cursor state
    if (cursorState) {
        let restoredInput = null;
        if (cursorState.id) {
            restoredInput = currentEl.querySelector(`[id="${cursorState.id}"]`) || currentEl.querySelector(`[wire\\:model="${cursorState.id}"]`);
        }
        
        if (restoredInput) {
            restoredInput.focus();
            try {
                restoredInput.setSelectionRange(cursorState.start, cursorState.end, cursorState.direction);
            } catch (e) {}
        }
    }
}

function componentContains(component, el) {
    let current = el;
    while (current) {
        if (current === component) return true;
        if (current.hasAttribute('wire:component') && current !== component) return false;
        current = current.parentElement;
    }
    return false;
}

// ─── Dirty State Tracking ────────────────────────────────────────────
function markDirty(componentEl, prop) {
    componentEl.querySelectorAll('[wire\\:dirty]').forEach(el => {
        const target = el.getAttribute('wire:target');
        if (!target || target === prop || target.split(',').map(s => s.trim()).includes(prop)) {
            el.setAttribute('data-spp-dirty', 'true');
            if (el.hasAttribute('wire:dirty.class')) {
                el.classList.add(...el.getAttribute('wire:dirty.class').split(' '));
            }
        }
    });
}

function clearDirty(componentEl) {
    componentEl.querySelectorAll('[wire\\:dirty]').forEach(el => {
        el.removeAttribute('data-spp-dirty');
        if (el.hasAttribute('wire:dirty.class')) {
            el.classList.remove(...el.getAttribute('wire:dirty.class').split(' '));
        }
    });
}

// ─── Network Status ──────────────────────────────────────────────────
window.addEventListener('offline', () => document.body.setAttribute('data-spp-offline', 'true'));
window.addEventListener('online', () => document.body.removeAttribute('data-spp-offline'));

// ─── Component Lifecycle & Morphing ──────────────────────────────────
async function sendLiveRequest(componentEl, method, updates = {}, params = [], isMutation = true) {
    if (!navigator.onLine) return;

    const componentClass = componentEl.getAttribute('wire:component');
    const checksum = componentEl.getAttribute('wire:checksum');
    const stateJson = componentEl.getAttribute('wire:state');
    const componentKey = componentEl; 

    if (!componentClass || !checksum || !stateJson) return;

    let controller = null;

    // --- DIFFERENTIAL REQUEST HANDLING (Fixes Runaway Train) ---
    if (!isMutation) {
        // Safe read/sync operations (wire:model, wire:poll) can be safely aborted
        if (activeSyncRequests.has(componentKey)) {
            activeSyncRequests.get(componentKey).abort();
        }
        controller = new AbortController();
        activeSyncRequests.set(componentKey, controller);
    } else {
        // Unsafe mutations (wire:click) MUST NEVER be aborted by the client.
        // If a mutation is already in flight, we block duplicate submissions natively.
        if (componentEl.hasAttribute('data-mutation-inflight')) {
            console.warn('[SPPLive] Mutation blocked to prevent duplicate backend execution.');
            return; 
        }
        componentEl.setAttribute('data-mutation-inflight', 'true');
    }

    // --- UI LOADING STATES ---
    componentEl.setAttribute('data-live-loading', 'true');
    const loadingEls = Array.from(componentEl.querySelectorAll('[wire\\:loading]'));
    const lockedEls = Array.from(componentEl.querySelectorAll('[wire\\:loading\\.attr="disabled"]'));
    loadingEls.forEach(el => el.setAttribute('data-spp-loading-active', 'true'));
    lockedEls.forEach(el => el.setAttribute('disabled', 'disabled'));

    try {
        const payload = {
            component: componentClass,
            state: JSON.parse(stateJson),
            checksum: checksum,
            updates: updates,
            method: method === 'syncModel' ? null : method,
            params: params
        };

        const fetchOptions = {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json', 'X-SPP-Ajax': '1', ...getTelemetryHeaders() }
        };
        
        if (controller) fetchOptions.signal = controller.signal;

        const liveEndpoint = window.SPP_CONFIG?.liveEndpoint || '?__svc=live_update';
        const response = await fetch(liveEndpoint, fetchOptions);

        if (response.status === 401) throw new Error('401 Unauthorized');
        if (response.status === 403) throw new Error('403 Forbidden - State Tampering Detected');
        
        // --- STATE CORRUPTION FAILSAFE (Fixes State Desync) ---
        if (!response.ok || response.status >= 500) {
            console.error('[SPPLive] Fatal Server Error. State is compromised. Forcing hard reload.');
            window.location.reload(); 
            return;
        }
        
        const jsonResponse = await response.json();
        if (jsonResponse.status === 'error') throw new Error(jsonResponse.message || 'Unknown backend error');

        const result = jsonResponse.result || jsonResponse;

        // Title update
        if (result.title) {
            document.title = result.title;
        }

        // File download handling
        if (result.download) {
            const a = document.createElement('a');
            a.href = result.download.url;
            if (result.download.name) a.download = result.download.name;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Real-time Text Streaming (wire:stream)
        if (result.streams && Array.isArray(result.streams)) {
            result.streams.forEach(s => {
                const target = componentEl.querySelector(`[wire\\:stream="${s.to}"]`);
                if (target) {
                    if (s.replace) target.innerHTML = s.content;
                    else target.innerHTML += s.content;
                }
            });
        }

        if (result.html) {
            const temp = document.createElement('div');
            temp.innerHTML = result.html.trim();
            const newComponentEl = temp.firstElementChild;
            
            if (newComponentEl) {
                if (result.state) newComponentEl.setAttribute('wire:state', JSON.stringify(result.state));
                if (result.checksum) newComponentEl.setAttribute('wire:checksum', result.checksum);
                
                // Use the safe morpher that retains user cursor selection and skips wire:ignore
                morphWithCursorRetention(componentEl, newComponentEl);
                clearDirty(componentEl);
                initLiveComponents(componentEl);
            }
        }
    } catch (err) {
        if (err.name === 'AbortError') return;
        console.error('[SPPLive] Action Error:', err);
    } finally {
        if (!isMutation) {
            if (activeSyncRequests.get(componentKey) === controller) {
                activeSyncRequests.delete(componentKey);
                cleanupUI(componentEl, loadingEls, lockedEls);
            }
        } else {
            componentEl.removeAttribute('data-mutation-inflight');
            cleanupUI(componentEl, loadingEls, lockedEls);
        }
    }
}

function cleanupUI(componentEl, loadingEls, lockedEls) {
    componentEl.removeAttribute('data-live-loading');
    loadingEls.forEach(el => el.removeAttribute('data-spp-loading-active'));
    lockedEls.forEach(el => {
        if (componentEl.contains(el)) el.removeAttribute('disabled');
    });
}

function parseModifiers(attrName) {
    const parts = attrName.split('.');
    const modifiers = {
        debounce: null,
        lazy: false,
        prevent: false,
        stop: false
    };
    for (let i = 1; i < parts.length; i++) {
        const part = parts[i];
        if (part === 'lazy') modifiers.lazy = true;
        else if (part === 'prevent') modifiers.prevent = true;
        else if (part === 'stop') modifiers.stop = true;
        else if (part === 'debounce') {
            const next = parts[i + 1];
            if (next && next.endsWith('ms')) {
                modifiers.debounce = parseInt(next, 10) || 250;
                i++;
            } else {
                modifiers.debounce = 250;
            }
        }
    }
    return modifiers;
}

// ─── File Upload Handler (wire:upload) ───────────────────────────────
function handleFileUpload(input, componentEl, propName) {
    if (!input.files || !input.files.length) return;
    
    const formData = new FormData();
    Array.from(input.files).forEach(file => {
        formData.append('files[]', file);
    });

    const checksum = componentEl.getAttribute('wire:checksum');
    const id = componentEl.getAttribute('wire:id');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '?__spa=1&__svc=live_upload', true);
    xhr.setRequestHeader('X-SPP-Live-Checksum', checksum);
    xhr.setRequestHeader('X-SPP-Live-Id', id);

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            componentEl.querySelectorAll(`[wire\\:upload-progress="${propName}"]`).forEach(pEl => {
                if (pEl.tagName === 'PROGRESS') {
                    pEl.value = percent;
                } else {
                    pEl.innerText = `${percent}%`;
                    pEl.style.setProperty('--progress', `${percent}%`);
                }
            });
        }
    };

    xhr.onload = () => {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success && data.tokens) {
                    const token = input.multiple ? data.tokens : data.tokens[0];
                    sendLiveRequest(componentEl, 'syncModel', { [propName]: token }, [], false);
                } else {
                    console.error('[SPPLive] File upload failed:', data);
                }
            } catch (err) {
                console.error('[SPPLive] Invalid upload response:', xhr.responseText);
            }
        }
    };

    xhr.send(formData);
}

// ─── Polling Engine (wire:poll) ──────────────────────────────────────
function initPolling(root = document) {
    const pollElements = root.querySelectorAll('[wire\\:poll]');
    pollElements.forEach(el => {
        if (pollTimers.has(el)) return;

        const action = el.getAttribute('wire:poll') || '$refresh';
        let interval = 2000;

        // Extract poll frequency modifiers e.g. wire:poll.750ms, wire:poll.5s
        for (const attr of el.attributes) {
            if (attr.name.startsWith('wire:poll')) {
                const parts = attr.name.split('.');
                for (const part of parts) {
                    if (part.endsWith('ms')) interval = parseInt(part, 10) || interval;
                    else if (part.endsWith('s')) interval = (parseInt(part, 10) || 2) * 1000;
                }
            }
        }

        const timer = setInterval(() => {
            if (!document.body.contains(el)) {
                clearInterval(timer);
                pollTimers.delete(el);
                return;
            }

            // Honor visibility modifier (wire:poll.visible)
            if (el.hasAttribute('wire:poll.visible') && document.hidden) return;

            const componentEl = el.closest('[wire\\:component]');
            if (componentEl && !componentEl.hasAttribute('data-mutation-inflight')) {
                sendLiveRequest(componentEl, action === '$refresh' ? null : action, {}, [], false);
            }
        }, interval);

        pollTimers.set(el, timer);
    });
}

// ─── SPA Navigation (wire:navigate) ──────────────────────────────────
let wireNavigateAttached = false;
function initWireNavigate() {
    if (wireNavigateAttached) return;
    wireNavigateAttached = true;

    document.addEventListener('click', async (e) => {
        const link = e.target.closest('a[wire\\:navigate]');
        if (!link || !link.href || link.origin !== window.location.origin) return;
        if (link.target && link.target !== '_self') return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        e.preventDefault();
        try {
            const res = await fetch(link.href, { headers: { 'X-SPP-Navigate': '1', ...getTelemetryHeaders() } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const text = await res.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');

            const persistedMap = new Map();
            document.querySelectorAll('[wire\\:persist]').forEach(el => {
                const key = el.getAttribute('wire:persist');
                if (key) persistedMap.set(key, el);
            });

            const applyContent = () => {
                document.title = doc.title;
                document.body.innerHTML = doc.body.innerHTML;

                if (persistedMap.size > 0) {
                    document.querySelectorAll('[wire\\:persist]').forEach(newEl => {
                        const key = newEl.getAttribute('wire:persist');
                        if (persistedMap.has(key)) {
                            newEl.replaceWith(persistedMap.get(key));
                        }
                    });
                }

                initLiveComponents(document);
                initPolling(document);
            };

            if (typeof document !== 'undefined' && document.startViewTransition) {
                document.startViewTransition(applyContent);
            } else {
                applyContent();
            }
            window.history.pushState({ __spp_wire_nav__: true, url: link.href }, '', link.href);
        } catch (err) {
            window.location.href = link.href;
        }
    });

    window.addEventListener('popstate', async (event) => {
        // Only intercept if this navigation history state was created by wire:navigate
        if (!event.state || !event.state.__spp_wire_nav__) {
            return;
        }

        // Never hijack hash-only navigations (SPA hash changes or anchor links)
        if (event.state.url) {
            try {
                const prev = new URL(event.state.url, window.location.origin);
                const curr = new URL(window.location.href, window.location.origin);
                if (prev.pathname === curr.pathname && prev.search === curr.search) {
                    return;
                }
            } catch (e) {}
        }

        try {
            const res = await fetch(window.location.href, { headers: { 'X-SPP-Navigate': '1', ...getTelemetryHeaders() } });
            const text = await res.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');

            const persistedMap = new Map();
            document.querySelectorAll('[wire\\:persist]').forEach(el => {
                const key = el.getAttribute('wire:persist');
                if (key) persistedMap.set(key, el);
            });

            const applyContent = () => {
                document.title = doc.title;
                document.body.innerHTML = doc.body.innerHTML;

                if (persistedMap.size > 0) {
                    document.querySelectorAll('[wire\\:persist]').forEach(newEl => {
                        const key = newEl.getAttribute('wire:persist');
                        if (persistedMap.has(key)) {
                            newEl.replaceWith(persistedMap.get(key));
                        }
                    });
                }

                initLiveComponents(document);
                initPolling(document);
            };

            if (typeof document !== 'undefined' && document.startViewTransition) {
                document.startViewTransition(applyContent);
            } else {
                applyContent();
            }
        } catch (err) {
            window.location.reload();
        }
    });
}

// ─── Component Directive Bindings ────────────────────────────────────
function initLiveComponents(root = document) {
    const elements = root.querySelectorAll('*');
    
    elements.forEach(el => {
        for (const attr of el.attributes) {
            if (attr.name.startsWith('wire:click')) {
                if (el.hasAttribute('data-live-bound-click')) continue;
                el.setAttribute('data-live-bound-click', '1');
                
                const modifiers = parseModifiers(attr.name);
                let handler = (e) => {
                    // Action Confirmation (wire:confirm)
                    if (el.hasAttribute('wire:confirm')) {
                        if (!window.confirm(el.getAttribute('wire:confirm'))) {
                            e.stopImmediatePropagation();
                            return;
                        }
                    }

                    if (modifiers.prevent) e.preventDefault();
                    if (modifiers.stop) e.stopPropagation();
                    const componentEl = el.closest('[wire\\:component]');
                    if (componentEl) sendLiveRequest(componentEl, attr.value, {}, [], true);
                };

                if (modifiers.debounce) handler = debounce(handler, modifiers.debounce);
                el.addEventListener('click', handler);
            }
            
            else if (attr.name.startsWith('wire:model')) {
                if (el.hasAttribute('data-live-bound-model')) continue;
                el.setAttribute('data-live-bound-model', '1');
                
                const modifiers = parseModifiers(attr.name);
                let handler = (e) => {
                    if (modifiers.prevent) e.preventDefault();
                    if (modifiers.stop) e.stopPropagation();
                    const componentEl = el.closest('[wire\\:component]');
                    if (componentEl) {
                        markDirty(componentEl, attr.value);

                        if (el.type === 'file') {
                            handleFileUpload(el, componentEl, attr.value);
                        } else {
                            let val = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
                            if (modifiers.trim && typeof val === 'string') val = val.trim();
                            if (modifiers.number && typeof val === 'string' && val !== '') val = Number(val);

                            sendLiveRequest(componentEl, 'syncModel', {
                                [attr.value]: val
                            }, [], false);
                        }
                    }
                };

                if (modifiers.debounce) handler = debounce(handler, modifiers.debounce);
                const eventType = (modifiers.lazy || el.type === 'checkbox' || el.type === 'radio' || el.tagName === 'SELECT') ? 'change' : 'input';
                el.addEventListener(eventType, handler);
            }
        }
    });

    initPolling(root);
}

// ─── React / Vue Style Client-Side $wire Proxy ───────────────────────
export function $wire(idOrEl) {
    let el = idOrEl;
    if (typeof idOrEl === 'string') {
        el = document.querySelector(`[wire\\:id="${idOrEl}"]`) || document.getElementById(idOrEl);
    } else if (idOrEl instanceof Event) {
        el = idOrEl.target.closest('[wire\\:id]') || idOrEl.target.closest('[wire\\:component]');
    } else if (idOrEl && idOrEl.closest) {
        el = idOrEl.closest('[wire\\:id]') || idOrEl.closest('[wire\\:component]');
    }
    if (!el) return null;

    return new Proxy({}, {
        get(target, prop) {
            if (prop === '$el') return el;
            if (prop === '$id') return el.getAttribute('wire:id');
            if (prop === '$state') {
                try { return JSON.parse(el.getAttribute('wire:state') || '{}'); } catch (e) { return {}; }
            }
            if (prop === '$set') {
                return (key, val) => sendLiveRequest(el, 'syncModel', { [key]: val }, [], false);
            }
            if (prop === '$call') {
                return (method, ...params) => sendLiveRequest(el, method, {}, params, true);
            }
            if (prop === '$refresh') {
                return () => sendLiveRequest(el, null, {}, [], false);
            }
            const state = JSON.parse(el.getAttribute('wire:state') || '{}');
            if (prop in state) {
                return state[prop];
            }
            return (...args) => sendLiveRequest(el, prop, {}, args, true);
        },
        set(target, prop, value) {
            sendLiveRequest(el, 'syncModel', { [prop]: value }, [], false);
            return true;
        }
    });
}

if (typeof window !== 'undefined') {
    window.$wire = $wire;
}

export function bootSPPLive() {
    initLiveComponents(document);
    initWireNavigate();
    console.log('[SPPLive] Unified Engine Booted: Cursor Retention, Reconciler Morphing, Polling, Dirty States, Streaming, and $wire Proxy Active.');
}

if (typeof document !== 'undefined' && document.readyState !== 'loading') bootSPPLive();
else if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', bootSPPLive);
