/**
 * SPPLive DevTools
 * A floating widget for debugging SPPLive components, state, and network traffic.
 */

class SPPLiveDevTools {
    constructor() {
        this.logs = [];
        this.selectedComponentId = null;
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.simulatedLatency = 0;
        this.ui = null;
    }

    init() {
        this.injectStyles();
        this.buildUI();
        this.hookSPPLive();
        this.render();
        
        setInterval(() => this.renderComponentList(), 2000); // Auto-refresh tree
    }

    injectStyles() {
        if (document.getElementById('spplive-dt-styles')) return;
        const style = document.createElement('style');
        style.id = 'spplive-dt-styles';
        style.innerHTML = `
            #sppdt { position: fixed; bottom: 20px; right: 20px; width: 400px; height: 500px; background: #1f2937; color: #f3f4f6; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-family: monospace; z-index: 999999; display: flex; flex-direction: column; overflow: hidden; border: 1px solid #374151; }
            #sppdt-header { background: #111827; padding: 10px; cursor: grab; font-weight: bold; border-bottom: 1px solid #374151; display: flex; justify-content: space-between; align-items: center; }
            #sppdt-header:active { cursor: grabbing; }
            #sppdt-tabs { display: flex; background: #1f2937; border-bottom: 1px solid #374151; }
            .sppdt-tab { padding: 8px 12px; cursor: pointer; flex: 1; text-align: center; border-right: 1px solid #374151; }
            .sppdt-tab.active { background: #374151; color: #10b981; font-weight: bold; }
            #sppdt-content { flex: 1; overflow-y: auto; padding: 10px; font-size: 12px; }
            .sppdt-log { margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #374151; }
            .sppdt-log-time { color: #9ca3af; font-size: 10px; }
            .sppdt-log-type-out { color: #60a5fa; }
            .sppdt-log-type-in { color: #10b981; }
            .sppdt-comp { padding: 4px; cursor: pointer; border-radius: 4px; }
            .sppdt-comp:hover { background: #374151; }
            .sppdt-comp.active { background: #10b981; color: white; }
            .sppdt-json { background: #111827; padding: 8px; border-radius: 4px; overflow-x: auto; margin-top: 10px; color: #a78bfa; }
            .sppdt-btn { background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; }
            .sppdt-btn.active { background: #b91c1c; }
        `;
        document.head.appendChild(style);
    }

    buildUI() {
        this.ui = document.createElement('div');
        this.ui.id = 'sppdt';
        this.ui.innerHTML = `
            <div id="sppdt-header">
                <span>⚡ SPPLive DevTools</span>
                <button id="sppdt-close" style="background:none;border:none;color:#9ca3af;cursor:pointer;">✖</button>
            </div>
            <div id="sppdt-tabs">
                <div class="sppdt-tab active" data-tab="components">Components</div>
                <div class="sppdt-tab" data-tab="network">Network</div>
                <div class="sppdt-tab" data-tab="chaos">Chaos</div>
            </div>
            <div id="sppdt-content"></div>
        `;
        document.body.appendChild(this.ui);

        // Dragging Logic
        const header = this.ui.querySelector('#sppdt-header');
        header.addEventListener('mousedown', e => {
            if (e.target.id === 'sppdt-close') return;
            this.isDragging = true;
            this.dragOffset.x = e.clientX - this.ui.getBoundingClientRect().left;
            this.dragOffset.y = e.clientY - this.ui.getBoundingClientRect().top;
        });
        document.addEventListener('mousemove', e => {
            if (!this.isDragging) return;
            this.ui.style.left = `${e.clientX - this.dragOffset.x}px`;
            this.ui.style.top = `${e.clientY - this.dragOffset.y}px`;
            this.ui.style.right = 'auto';
            this.ui.style.bottom = 'auto';
        });
        document.addEventListener('mouseup', () => this.isDragging = false);

        // Tabs Logic
        this.ui.querySelectorAll('.sppdt-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.ui.querySelectorAll('.sppdt-tab').forEach(t => t.classList.remove('active'));
                e.target.classList.add('active');
                this.activeTab = e.target.getAttribute('data-tab');
                this.render();
            });
        });

        // Close
        this.ui.querySelector('#sppdt-close').addEventListener('click', () => {
            this.ui.style.display = 'none';
            console.log("SPPLive DevTools hidden. Call window.SPPDevTools.show() to restore.");
        });

        this.activeTab = 'components';
    }

    hookSPPLive() {
        if (!window.SPP || !window.SPP.Live) {
            console.warn("SPPLive not loaded yet.");
            setTimeout(() => this.hookSPPLive(), 500);
            return;
        }

        const core = window.SPP.Live;

        // Hook sendUpdate
        const originalSendUpdate = core.sendUpdate;
        core.sendUpdate = (id, method, params = []) => {
            if (this.simulatedLatency > 0) {
                setTimeout(() => {
                    originalSendUpdate.call(core, id, method, params);
                }, this.simulatedLatency);
            } else {
                originalSendUpdate.call(core, id, method, params);
            }
            this.log('out', id, method || 'sync', params);
        };

        // Hook dispatchPayload (Network)
        const originalDispatchPayload = core.dispatchPayload;
        core.dispatchPayload = (payload) => {
            // Chaos mode offline hook
            if (this.isSimulatingOffline) {
                core.offlineQueue.push(payload);
                this.log('out', payload.state.id, '[QUEUED OFFLINE]', null);
                return;
            }
            originalDispatchPayload.call(core, payload);
        };

        // Hook handleResponse
        const originalHandleResponse = core.handleResponse;
        core.handleResponse = (data) => {
            if (data && data.id) {
                this.log('in', data.id, 'Response', { stateKeys: Object.keys(data.state), hasHtml: !!data.html });
                // Flash the state viewer if active
                if (this.activeTab === 'components' && this.selectedComponentId === data.id) {
                    this.render();
                    const jsonBox = this.ui.querySelector('.sppdt-json');
                    if (jsonBox) {
                        jsonBox.style.backgroundColor = '#064e3b'; // flash green
                        setTimeout(() => jsonBox.style.backgroundColor = '#111827', 300);
                    }
                }
            }
            originalHandleResponse.call(core, data);
        };

        document.addEventListener('spplive:morphing', e => {
            e.detail.startTime = performance.now();
        });
        document.addEventListener('spplive:morphed', e => {
            const duration = (performance.now() - e.detail.startTime).toFixed(2);
            this.log('in', e.detail.id, `Morphed in ${duration}ms`, null);
        });
    }

    log(dir, id, action, details) {
        this.logs.unshift({
            time: new Date().toLocaleTimeString(),
            dir: dir, // 'in' or 'out'
            id: id,
            action: action,
            details: details
        });
        if (this.logs.length > 50) this.logs.pop(); // keep last 50
        if (this.activeTab === 'network') this.render();
    }

    render() {
        const content = this.ui.querySelector('#sppdt-content');
        if (this.activeTab === 'components') {
            this.renderComponentList(content);
        } else if (this.activeTab === 'network') {
            this.renderNetworkLogs(content);
        } else if (this.activeTab === 'chaos') {
            this.renderChaosMode(content);
        }
    }

    renderComponentList(container = null) {
        if (!container) container = this.ui.querySelector('#sppdt-content');
        if (!window.SPP || !window.SPP.Live) return;

        let html = '<div style="margin-bottom:10px;font-weight:bold;">Active Components</div>';
        const comps = window.SPP.Live.components;
        
        if (Object.keys(comps).length === 0) {
            html += '<div style="color:#9ca3af;">No components mounted.</div>';
        }

        for (const [id, comp] of Object.entries(comps)) {
            const isActive = this.selectedComponentId === id ? 'active' : '';
            html += `<div class="sppdt-comp ${isActive}" data-id="${id}">
                        &lt;${comp.componentClass}&gt; <span style="color:#9ca3af;font-size:10px;">${id.substring(0,8)}</span>
                     </div>`;
        }

        if (this.selectedComponentId && comps[this.selectedComponentId]) {
            html += `<div style="margin-top:15px;font-weight:bold;">State:</div>`;
            html += `<pre class="sppdt-json">${JSON.stringify(comps[this.selectedComponentId].state, null, 2)}</pre>`;
        }

        container.innerHTML = html;

        container.querySelectorAll('.sppdt-comp').forEach(el => {
            el.addEventListener('click', (e) => {
                this.selectedComponentId = e.currentTarget.getAttribute('data-id');
                this.render();
            });
        });
    }

    renderNetworkLogs(container) {
        let html = '';
        if (this.logs.length === 0) {
            html = '<div style="color:#9ca3af;">No network activity yet.</div>';
        }
        this.logs.forEach(log => {
            const typeClass = log.dir === 'out' ? 'sppdt-log-type-out' : 'sppdt-log-type-in';
            const arrow = log.dir === 'out' ? '↑ OUT' : '↓ IN ';
            html += `
                <div class="sppdt-log">
                    <span class="sppdt-log-time">[${log.time}]</span> 
                    <strong class="${typeClass}">${arrow}</strong> 
                    <span style="color:#d1d5db;">${log.action}</span> 
                    <span style="color:#9ca3af;font-size:10px;">(${log.id.substring(0,8)})</span>
                    ${log.details ? `<div style="color:#6b7280;margin-top:2px;font-size:10px;">${JSON.stringify(log.details)}</div>` : ''}
                </div>
            `;
        });
        container.innerHTML = html;
    }

    renderChaosMode(container) {
        container.innerHTML = `
            <div style="margin-bottom:15px;">
                <div style="font-weight:bold;margin-bottom:5px;">Simulate Offline</div>
                <p style="color:#9ca3af;margin-bottom:5px;font-size:11px;">Blocks XHR/WS and forces updates into the offline queue.</p>
                <button id="sppdt-btn-offline" class="sppdt-btn ${this.isSimulatingOffline ? 'active' : ''}">
                    ${this.isSimulatingOffline ? 'Disable Network' : 'Enable Network'}
                </button>
            </div>
            <div style="margin-bottom:15px;">
                <div style="font-weight:bold;margin-bottom:5px;">Simulate Latency</div>
                <p style="color:#9ca3af;margin-bottom:5px;font-size:11px;">Artificially delays outgoing requests.</p>
                <input type="range" id="sppdt-range-latency" min="0" max="3000" step="100" value="${this.simulatedLatency}" style="width:100%;">
                <div style="text-align:right;color:#10b981;" id="sppdt-lbl-latency">${this.simulatedLatency}ms</div>
            </div>
        `;

        container.querySelector('#sppdt-btn-offline').addEventListener('click', (e) => {
            this.isSimulatingOffline = !this.isSimulatingOffline;
            if (this.isSimulatingOffline) {
                window.dispatchEvent(new Event('offline'));
            } else {
                window.dispatchEvent(new Event('online'));
            }
            this.render();
        });

        const latencySlider = container.querySelector('#sppdt-range-latency');
        const latencyLabel = container.querySelector('#sppdt-lbl-latency');
        latencySlider.addEventListener('input', (e) => {
            this.simulatedLatency = parseInt(e.target.value, 10);
            latencyLabel.innerText = `${this.simulatedLatency}ms`;
        });
    }

    show() {
        if (this.ui) this.ui.style.display = 'flex';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.SPPDevTools = new SPPLiveDevTools();
    // Wait slightly for SPPLive to mount components
    setTimeout(() => {
        window.SPPDevTools.init();
    }, 100);
});
