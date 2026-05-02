/**
 * EventsView Component
 * 
 * Visualizes the event propagation graph for real-time debugging.
 */
import BaseComponent from '../../../modules/spp/sppux/js/BaseComponent.js';

export default class EventsView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            traces: [],
            selectedTrace: null
        };
        await this.fetchData();
    }

    async fetchData() {
        try {
            const res = await this.api('get_event_trace');
            if (res.success) {
                const traces = res.data.traces || [];
                this.setState({
                    traces: traces.reverse(), // Newest first
                    loading: false,
                    selectedTrace: traces.length > 0 ? traces[0] : null
                });
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    selectTrace(trace) {
        this.setState({ selectedTrace: trace });
    }

    render() {
        const { loading, traces, selectedTrace, error } = this.state;

        if (loading) return html`<div class="loading-state">Loading event traces...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        return html`
            <div class="events-container" style="display: flex; height: 100%; gap: 20px;">
                <!-- Sidebar: Trace List -->
                <div class="trace-list" style="width: 300px; border-right: 1px solid var(--glass-border); overflow-y: auto; padding-right: 10px;">
                    <h3 style="font-size: 0.9rem; margin-bottom: 15px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Recent Requests</h3>
                    ${traces.map(t => html`
                        <div class="trace-item ${selectedTrace === t ? 'active' : ''}" 
                             style="padding: 12px; border-radius: 8px; cursor: pointer; margin-bottom: 8px; border: 1px solid ${selectedTrace === t ? 'var(--accent-light)' : 'transparent'}; background: ${selectedTrace === t ? 'rgba(99, 102, 241, 0.1)' : 'transparent'}"
                             @click="${() => this.selectTrace(t)}">
                            <div style="font-size: 0.85rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${t.request_uri}</div>
                            <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;">${t.timestamp}</div>
                        </div>
                    `)}
                    ${traces.length === 0 ? html`<p style="font-size: 0.8rem; color: var(--text-dim);">No traces captured yet.</p>` : ''}
                </div>

                <!-- Main: Visual Graph -->
                <div class="trace-visual" style="flex: 1; overflow-y: auto;">
                    ${selectedTrace ? this.renderTraceGraph(selectedTrace) : html`<div class="empty-state">Select a request to view event flow</div>`}
                </div>
            </div>
        `;
    }

    renderTraceGraph(trace) {
        return html`
            <div class="trace-header" style="margin-bottom: 25px;">
                <h2 style="font-size: 1.2rem; margin-bottom: 5px;">${trace.request_uri}</h2>
                <span class="badge info" style="font-size: 0.7rem;">${trace.trace.length} Events Fired</span>
            </div>

            <div class="graph-timeline" style="position: relative; padding-left: 40px; border-left: 2px solid var(--glass-border); margin-left: 10px;">
                ${trace.trace.map(ev => html`
                    <div class="event-node" style="position: relative; margin-bottom: 30px;">
                        <!-- Event Marker -->
                        <div style="position: absolute; left: -51px; top: 0; width: 20px; height: 20px; border-radius: 50%; background: var(--accent-light); border: 4px solid var(--bg-dark); box-shadow: 0 0 10px var(--accent-light);"></div>
                        
                        <div class="event-card" style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 12px; padding: 15px; backdrop-filter: blur(10px);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; color: var(--accent-light); font-weight: bold;">${ev.event}</span>
                                <span style="font-size: 0.7rem; color: var(--text-dim);">${ev.handlers.length} Handlers</span>
                            </div>

                            <div class="handler-chain" style="display: flex; flex-direction: column; gap: 8px;">
                                ${ev.handlers.map(h => html`
                                    <div class="handler-row" style="display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 6px; background: rgba(255,255,255,0.03); border-left: 3px solid ${this.getStageColor(h.stage)}">
                                        <span class="stage-label" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; background: ${this.getStageColor(h.stage)}; color: white; min-width: 60px; text-align: center; text-transform: uppercase;">${h.stage}</span>
                                        <span style="font-size: 0.8rem; font-family: 'JetBrains Mono', monospace; color: var(--text-main); flex: 1;">${h.handler}</span>
                                        ${h.stopped ? html`<span class="badge danger" style="font-size: 0.6rem;">STOPPED</span>` : ''}
                                    </div>
                                `)}
                                ${ev.handlers.length === 0 ? html`<div style="font-size: 0.8rem; color: var(--text-dim); font-style: italic;">No handlers registered</div>` : ''}
                            </div>
                        </div>
                    </div>
                `)}
            </div>
        `;
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
