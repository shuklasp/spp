/**
 * TraceView Component
 * 
 * Visualizes the event propagation graph for real-time debugging.
 * Supports both framework events and Parikshak testing logs.
 */
// BaseComponent is provided globally by sppux.js

export default class TraceView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            traces: [],
            selectedTrace: null,
            parikshakLog: '',
            activeTab: 'visual' // 'visual' or 'parikshak'
        };
        await this.fetchData();
    }

    async fetchData() {
        try {
            const [traceRes, parikshakRes] = await Promise.all([
                this.admin.api('get_event_trace'),
                this.admin.api('get_parikshak_trace')
            ]);

            const traces = traceRes.success ? (traceRes.data.traces || []) : [];
            const parikshakLog = parikshakRes.success ? (parikshakRes.data.content || '') : '';

            this.setState({
                traces: [...traces].reverse(), // Newest first
                parikshakLog,
                loading: false,
                selectedTrace: traces.length > 0 ? traces[traces.length - 1] : null
            });
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    selectTrace(trace) {
        this.setState({ selectedTrace: trace });
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
    }

    async runScan() {
        this.admin.notify('Initiating Parikshak Evolutionary Scan...', 'info');
        try {
            const res = await this.admin.apiPost('run_parikshak_scan', { appname: this.admin.selectedApp });
            if (res.success) {
                this.admin.notify('Scan completed successfully.', 'success');
                await this.fetchData();
            } else {
                this.admin.notify(res.message, 'error');
            }
        } catch (err) {
            this.admin.notify('Scan failed: ' + err.message, 'error');
        }
    }

    render() {
        const { loading, traces, selectedTrace, parikshakLog, activeTab, error } = this.state;

        if (loading) return html`<div class="loading-state">Loading tracing data...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        return html`
            <div class="trace-workspace" style="display: flex; flex-direction: column; height: 100%;">
                <!-- Tab Header -->
                <div class="tabs-toolbar" style="margin-bottom: 20px; border-bottom: 1px solid var(--glass-border); display: flex; gap: 4px;">
                    <button class="tab-btn ${activeTab === 'visual' ? 'active' : ''}" @click="${() => this.switchTab('visual')}">🛰️ Event Propagation Graph</button>
                    <button class="tab-btn ${activeTab === 'parikshak' ? 'active' : ''}" @click="${() => this.switchTab('parikshak')}">🧬 Parikshak Activity Log</button>
                    <div style="flex: 1"></div>
                    <button class="tab-btn" @click="${() => this.fetchData()}">🔄 Refresh</button>
                </div>

                <div class="trace-content" style="flex: 1; overflow: hidden;">
                    ${activeTab === 'visual' ? html`
                        <div class="events-container" style="display: flex; height: 100%; gap: 20px;">
                            <!-- Sidebar: Trace List -->
                            <div class="trace-list" style="width: 320px; border-right: 1px solid var(--glass-border); overflow-y: auto; padding-right: 15px;">
                                <h3 style="font-size: 0.75rem; margin-bottom: 15px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Captured Request Timeline</h3>
                                ${traces.map(t => html`
                                    <div class="trace-item ${selectedTrace === t ? 'active' : ''}" 
                                         style="padding: 12px; border-radius: 12px; cursor: pointer; margin-bottom: 8px; border: 1px solid ${selectedTrace === t ? 'var(--accent-light)' : 'var(--glass-border)'}; background: ${selectedTrace === t ? 'rgba(99, 102, 241, 0.15)' : 'rgba(255,255,255,0.02)'}; transition: all 0.2s ease;"
                                         @click="${() => this.selectTrace(t)}">
                                        <div style="font-size: 0.85rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: ${selectedTrace === t ? 'var(--accent-light)' : 'var(--text-main)'};">${t.request_uri}</div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                                            <div style="font-size: 0.7rem; color: var(--text-dim);">${t.timestamp}</div>
                                            <div style="font-size: 0.65rem; color: var(--accent-light); opacity: 0.8;">${t.trace.length} events</div>
                                        </div>
                                    </div>
                                `)}
                                ${traces.length === 0 ? html`<div class="empty-state" style="padding: 20px; font-size: 0.8rem;">No events captured. Browse the site to generate logs.</div>` : ''}
                            </div>

                            <!-- Main: Visual Graph -->
                            <div class="trace-visual" style="flex: 1; overflow-y: auto; padding-right: 10px;">
                                ${selectedTrace ? this.renderTraceGraph(selectedTrace) : html`<div class="empty-state">Select a request timeline from the left to visualize its event flow</div>`}
                            </div>
                        </div>
                    ` : html`
                        <div class="log-view" style="height: 100%; display: flex; flex-direction: column; gap: 15px;">
                             <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.03); padding: 12px 20px; border-radius: 12px; border: 1px solid var(--glass-border);">
                                <div>
                                    <h3 style="font-size: 0.9rem; margin: 0; color: var(--text-main);">Parikshak Engine</h3>
                                    <div style="font-size: 0.7rem; color: var(--text-dim);">Automated Evolutionary Testing System</div>
                                </div>
                                <button class="btn primary" @click="${() => this.runScan()}" style="font-size: 0.8rem; padding: 8px 16px;">🚀 Trigger Evolutionary Scan</button>
                             </div>
                             <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; background: #0f172a; color: #94a3b8; padding: 20px; border-radius: 12px; flex: 1; overflow-y: auto; white-space: pre-wrap; line-height: 1.6; border: 1px solid var(--glass-border);">
                                ${parikshakLog || 'No activity logged yet.'}
                             </div>
                        </div>
                    `}
                </div>
            </div>
        `;
    }

    renderTraceGraph(trace) {
        return html`
            <div class="trace-header" style="margin-bottom: 25px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; border: 1px solid var(--glass-border);">
                <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Request Flow Analysis</div>
                <h2 style="font-size: 1.1rem; margin: 0; color: var(--accent-light); font-family: 'JetBrains Mono', monospace;">${trace.request_uri}</h2>
            </div>

            <div class="graph-timeline" style="position: relative; padding-left: 40px; border-left: 2px solid var(--glass-border); margin-left: 10px; margin-top: 10px;">
                ${trace.trace.map((ev, idx) => html`
                    <div class="event-node" style="position: relative; margin-bottom: 35px;">
                        <!-- Event Marker -->
                        <div style="position: absolute; left: -51px; top: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--accent-light); border: 4px solid var(--bg-dark); box-shadow: 0 0 15px rgba(99, 102, 241, 0.4); display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: white; font-weight: bold;">${idx + 1}</div>
                        
                        <div class="event-card" style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 14px; padding: 18px; backdrop-filter: blur(10px); transition: transform 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <div>
                                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 1rem; color: var(--text-main); font-weight: 700; letter-spacing: -0.02em;">${ev.event}</div>
                                    <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 2px;">Triggered at ${new Date(ev.timestamp * 1000).toLocaleTimeString()}</div>
                                </div>
                                <span class="badge info" style="font-size: 0.65rem; background: rgba(99, 102, 241, 0.2); border: 1px solid var(--accent-light); color: var(--accent-light);">${ev.handlers.length} Interceptors</span>
                            </div>

                            <div class="handler-chain" style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px;">
                                ${ev.handlers.map(h => html`
                                    <div class="handler-row" style="display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 10px; background: ${h.stopped ? 'rgba(239, 68, 68, 0.05)' : 'rgba(255,255,255,0.03)'}; border-left: 4px solid ${this.getStageColor(h.stage)}; border: 1px solid ${h.stopped ? 'rgba(239, 68, 68, 0.2)' : 'var(--glass-border)'}">
                                        <div style="display: flex; flex-direction: column; align-items: center; min-width: 60px;">
                                            <span style="font-size: 0.6rem; color: ${this.getStageColor(h.stage)}; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">${h.stage}</span>
                                        </div>
                                        <div style="flex: 1">
                                            <div style="font-size: 0.85rem; font-family: 'JetBrains Mono', monospace; color: var(--text-main);">${this.formatHandlerName(h.handler)}</div>
                                            ${h.stopped ? html`<div style="font-size: 0.65rem; color: var(--danger); font-weight: 600; margin-top: 2px;">🛑 HALTED PROPAGATION</div>` : ''}
                                        </div>
                                    </div>
                                `)}
                                ${ev.handlers.length === 0 ? html`<div style="font-size: 0.8rem; color: var(--text-dim); font-style: italic; text-align: center; padding: 10px;">Event passed through without interception</div>` : ''}
                            </div>
                        </div>
                    </div>
                `)}
            </div>
        `;
    }

    formatHandlerName(name) {
        // Clean up FQCN for better UI
        return name.replace(/^\\?EventHandlers\\/, '').replace(/^\\?SPPMod\\/, 'Mod:');
    }

    getStageColor(stage) {
        const colors = {
            'before': '#10b981', // Emerald
            'override': '#f59e0b', // Amber
            'default': '#6366f1', // Indigo
            'after': '#8b5cf6'   // Violet
        };
        return colors[stage] || '#6b7280';
    }
}
