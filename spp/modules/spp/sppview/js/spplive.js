/**
 * SPP Live - Reactive Frontend State Manager
 * Zero-dependency DOM patching and state synchronization via SPP Ajax & SPP Live WebSockets.
 */

class SPPLive {
    constructor() {
        this.components = {};
        this.socket = null;
        this.useWebSocket = false; // Fallback to AJAX by default
    }

    init() {
        this.scanComponents();
        this.attachListeners();
        this.tryWebSocket();
    }

    scanComponents() {
        document.querySelectorAll('[wire\\:id]').forEach(el => {
            const id = el.getAttribute('wire:id');
            if (!this.components[id]) {
                this.components[id] = {
                    el: el,
                    state: JSON.parse(el.getAttribute('wire:state') || '{}'),
                    checksum: el.getAttribute('wire:checksum'),
                    componentClass: el.getAttribute('wire:component')
                };
            } else {
                // Update element reference if it was replaced
                this.components[id].el = el;
            }
        });
    }

    attachListeners() {
        document.addEventListener('click', e => {
            const clickEl = e.target.closest('[wire\\:click]');
            if (clickEl) {
                e.preventDefault();
                const action = clickEl.getAttribute('wire:click');
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
                const root = modelEl.closest('[wire\\:id]');
                if (root) {
                    const id = root.getAttribute('wire:id');
                    if (this.components[id]) {
                        this.components[id].state[prop] = modelEl.value;
                    }
                }
            }
        });

        document.addEventListener('change', e => {
            const modelEl = e.target.closest('[wire\\:model]');
            if (modelEl) {
                const root = modelEl.closest('[wire\\:id]');
                if (root) {
                    // Send update instantly on change
                    this.sendUpdate(root.getAttribute('wire:id'), null);
                }
            }
        });
    }

    tryWebSocket() {
        const host = window.location.hostname;
        const port = window.SPPLivePort || 8080;
        
        // Only try WebSocket if explicitly enabled
        if (window.SPPLiveEnabled) {
            this.socket = new WebSocket(`ws://${host}:${port}`);
            this.socket.onopen = () => {
                this.useWebSocket = true;
                console.log('[SPPLive] Connected to WebSocket');
            };
            this.socket.onmessage = (msg) => {
                const data = JSON.parse(msg.data);
                this.handleResponse(data);
            };
            this.socket.onerror = () => {
                this.useWebSocket = false;
                console.log('[SPPLive] WebSocket failed, falling back to SPPAjax');
            };
        }
    }

    sendUpdate(id, method, params = []) {
        const comp = this.components[id];
        if (!comp) return;

        const payload = {
            component: comp.componentClass,
            state: comp.state,
            checksum: comp.checksum,
            method: method,
            params: params
        };

        if (this.useWebSocket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ action: 'live_update', payload }));
        } else {
            // Fallback to SPPAjax
            fetch('api.php?action=live_update', {
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
                    this.handleResponse(data.data);
                } else {
                    console.error('[SPPLive] Server error:', data);
                }
            })
            .catch(err => console.error('[SPPLive] Fetch error:', err));
        }
    }

    handleResponse(data) {
        if (!data || !data.id) return;

        const id = data.id;
        const comp = this.components[id];
        if (comp) {
            // Simple DOM Replacement (morphdom can be injected later)
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.html.trim();
            const newEl = tempDiv.firstChild;
            
            // Ensure necessary wire tags are preserved on root
            newEl.setAttribute('wire:id', id);
            newEl.setAttribute('wire:state', JSON.stringify(data.state));
            newEl.setAttribute('wire:checksum', data.checksum);
            newEl.setAttribute('wire:component', comp.componentClass);

            comp.el.replaceWith(newEl);
            
            // Re-scan components
            this.scanComponents();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.SPP = window.SPP || {};
    window.SPP.Live = new SPPLive();
    window.SPP.Live.init();
});
