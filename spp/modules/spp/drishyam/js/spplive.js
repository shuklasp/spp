/**
 * SPPLive Full-Stack Integration Engine (Ironclad Core)
 * Features: wire:navigate, Server-Driven DOM Morphing, Differential Request Handling,
 * Cursor Retention, and State Corruption Failsafes.
 */

import { reconcileDOM } from './core/reconciler.js';

const activeSyncRequests = new Map(); // Tracks AbortControllers for wire:model only

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
                id: active.id || active.getAttribute('wire:model'), // Fallback to wire:model as identifier
                start: active.selectionStart,
                end: active.selectionEnd,
                direction: active.selectionDirection
            };
        } catch (e) {
            // Some input types (e.g., number, email) throw an error on selectionStart access.
        }
    }

    // Perform the morph
    reconcileDOM(currentEl, newEl);

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
        if (current.hasAttribute('wire:component') && current !== component) return false; // Belongs to a child component
        current = current.parentElement;
    }
    return false;
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
        // Safe read/sync operations (wire:model) can be safely aborted
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

        const response = await fetch('/?__svc=live_update', fetchOptions);

        if (response.status === 401) throw new Error('401 Unauthorized');
        if (response.status === 403) throw new Error('403 Forbidden - State Tampering Detected');
        
        // --- STATE CORRUPTION FAILSAFE (Fixes State Desync) ---
        if (!response.ok || response.status >= 500) {
            console.error('[SPPLive] Fatal Server Error. State is compromised. Forcing hard reload.');
            window.location.reload(); 
            return; // Halt execution
        }
        
        const jsonResponse = await response.json();
        if (jsonResponse.status === 'error') throw new Error(jsonResponse.message || 'Unknown backend error');

        const result = jsonResponse.result || jsonResponse;
        if (result.html) {
            const temp = document.createElement('div');
            temp.innerHTML = result.html;
            const newComponentEl = temp.firstElementChild;
            
            if (newComponentEl) {
                if (result.state) newComponentEl.setAttribute('wire:state', JSON.stringify(result.state));
                if (result.checksum) newComponentEl.setAttribute('wire:checksum', result.checksum);
                
                // Use the safe morpher that retains user cursor selection
                morphWithCursorRetention(componentEl, newComponentEl);
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

function initLiveComponents(root = document) {
    const elements = root.querySelectorAll('*');
    
    elements.forEach(el => {
        for (const attr of el.attributes) {
            if (attr.name.startsWith('wire:click')) {
                if (el.hasAttribute('data-live-bound-click')) continue;
                el.setAttribute('data-live-bound-click', '1');
                
                const modifiers = parseModifiers(attr.name);
                let handler = (e) => {
                    e.preventDefault();
                    const componentEl = el.closest('[wire\\:component]');
                    // isMutation = true
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
                    const componentEl = el.closest('[wire\\:component]');
                    if (componentEl) {
                        // isMutation = false
                        sendLiveRequest(componentEl, 'syncModel', {
                            [attr.value]: e.target.type === 'checkbox' ? e.target.checked : e.target.value
                        }, [], false);
                    }
                };

                if (modifiers.debounce) handler = debounce(handler, modifiers.debounce);
                const eventType = (el.type === 'checkbox' || el.type === 'radio' || el.tagName === 'SELECT') ? 'change' : 'input';
                el.addEventListener(eventType, handler);
            }
        }
    });
}

export function bootSPPLive() {
    initLiveComponents(document);
    console.log('[SPPLive] Ironclad Engine Booted: Cursor Retention, State Failsafes, and Mutation Locks Active.');
}

if (typeof document !== 'undefined' && document.readyState !== 'loading') bootSPPLive();
else if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', bootSPPLive);
