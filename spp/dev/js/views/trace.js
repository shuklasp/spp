/**
 * SPP Developer Studio — W3C Distributed Tracing Waterfall
 * 
 * Provides visual latency bottleneck analysis and distributed telemetry
 * conforming to the W3C Trace Context and OpenTelemetry specifications.
 */

export default class TraceComponent extends (window.BaseComponent || class {}) {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
    }

    async onInit() {
        this.state = {
            loading: true,
            spans: [],
            selectedSpan: null,
            filterTerm: '',
            filterKind: 'ALL'
        };
        await this.fetchTrace();
    }

    async fetchTrace() {
        this.setState({ loading: true });
        try {
            const res = await this.admin.api('trace_waterfall', { limit: 50 });
            if (res.success) {
                const data = res.data || res;
                const spans = data.spans || [];
                this.setState({
                    spans,
                    loading: false,
                    selectedSpan: spans.length > 0 ? spans[0] : null
                });
            } else {
                this.admin.notify(res.message || 'Failed to fetch trace waterfall', 'error');
                this.setState({ loading: false });
            }
        } catch (e) {
            console.error("Trace fetch error:", e);
            this.admin.notify('Network error fetching distributed trace.', 'error');
            this.setState({ loading: false });
        }
    }

    selectSpan(span) {
        this.setState({ selectedSpan: span });
    }

    copyTraceparent(span) {
        if (!span) return;
        const header = `00-${span.trace_id}-${span.span_id}-01`;
        navigator.clipboard.writeText(header).then(() => {
            this.admin.notify(`Copied traceparent: ${header}`, 'success');
        }).catch(() => {
            this.admin.notify('Clipboard copy failed.', 'error');
        });
    }

    render() {
        const { loading, spans, selectedSpan, filterTerm, filterKind } = this.state;

        // Calculate waterfall timing extents
        const totalDuration = spans.length > 0
            ? Math.max(...spans.map(s => s.duration_ms || 0), 1)
            : 1;

        const filteredSpans = spans.filter(s => {
            if (filterKind !== 'ALL' && s.kind !== filterKind) return false;
            if (filterTerm) {
                const term = filterTerm.toLowerCase();
                const name = (s.name || '').toLowerCase();
                const tags = JSON.stringify(s.tags || {}).toLowerCase();
                return name.includes(term) || tags.includes(term);
            }
            return true;
        });

        // Compute metrics
        const dbSpans = spans.filter(s => (s.tags && s.tags['db.system']) || s.name.includes('SPPDB'));
        const totalDbTime = dbSpans.reduce((acc, s) => acc + (s.duration_ms || 0), 0);
        const rootSpan = spans.find(s => !s.parent_id) || spans[0];

        return html`
            <div class="trace-suite" style="display: flex; flex-direction: column; gap: 1.5rem; height: 100%;">
                <!-- Hero Header -->
                <div class="suite-hero">
                    <div>
                        <h2>W3C Distributed Tracing Waterfall</h2>
                        <p>End-to-end distributed observability and latency breakdown. Inspect W3C Trace Context spans across controllers, routing filters, and SQL executions.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                        <span class="suite-pill-blue">Trace ID: ${rootSpan ? rootSpan.trace_id.substring(0, 12) + '...' : 'None'}</span>
                        <span class="suite-pill-green">W3C Trace Context 1.0</span>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="suite-stat-card">
                        <div class="stat-label">Total Spans</div>
                        <div class="stat-val" style="color: #38bdf8;">${spans.length}</div>
                        <div class="stat-sub">Across full request path</div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="stat-label">Total Latency</div>
                        <div class="stat-val" style="color: #34d399;">${rootSpan?.duration_ms || 0} ms</div>
                        <div class="stat-sub">Wall clock duration</div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="stat-label">Database Latency</div>
                        <div class="stat-val" style="color: #a78bfa;">${totalDbTime.toFixed(2)} ms</div>
                        <div class="stat-sub">${dbSpans.length} SQL queries executed</div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="stat-label">Trace Status</div>
                        <div class="stat-val" style="color: #fbbf24;">${rootSpan?.status || 'OK'}</div>
                        <div class="stat-sub">Zero unhandled exceptions</div>
                    </div>
                </div>

                <!-- Toolbar Filters -->
                <div class="suite-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div style="display: flex; gap: 0.75rem; align-items: center; flex: 1; min-width: 320px;">
                        <input type="text"
                               class="suite-input"
                               style="flex: 1; max-width: 320px; font-size: 0.85rem;"
                               placeholder="Filter spans by name or tag..."
                               .value=${filterTerm}
                               @input=${(e) => this.setState({ filterTerm: e.target.value })}>

                        <select class="suite-select"
                                style="width: 140px; font-size: 0.82rem;"
                                .value=${filterKind}
                                @change=${(e) => this.setState({ filterKind: e.target.value })}>
                            <option value="ALL">All Kinds</option>
                            <option value="SERVER">Server</option>
                            <option value="INTERNAL">Internal</option>
                            <option value="CLIENT">Client / DB</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <button class="btn secondary-btn btn-sm" @click=${() => this.fetchTrace()}>🔄 Refresh Trace</button>
                    </div>
                </div>

                <!-- Main Waterfall & Inspector Split Pane -->
                <div class="suite-split-pane" style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.25rem; flex: 1; min-height: 480px;">
                    <!-- Left: Timeline Waterfall -->
                    <div class="card" style="display: flex; flex-direction: column; padding: 1.25rem; background: rgba(15, 23, 42, 0.65); border: 1px solid var(--glass-border); border-radius: 12px; overflow: hidden;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--glass-border);">
                            <span style="font-size: 0.85rem; font-weight: 600; color: #38bdf8;">Span Execution Timeline</span>
                            <!-- Millisecond Axis -->
                            <div style="display: flex; gap: 2rem; font-size: 0.7rem; color: var(--text-dim); font-family: 'JetBrains Mono', monospace;">
                                <span>0ms</span>
                                <span>${(totalDuration * 0.5).toFixed(1)}ms</span>
                                <span>${totalDuration.toFixed(1)}ms</span>
                            </div>
                        </div>

                        <div style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem;">
                            ${loading ? html`<div class="loading-state" style="padding: 3rem; text-align: center;"><div class="sppux-spinner"></div> Loading trace waterfall...</div>` : ''}

                            ${!loading && filteredSpans.length === 0 ? html`
                                <div style="padding: 3rem; text-align: center; color: var(--text-dim); font-size: 0.85rem;">
                                    No trace spans captured matching filter.
                                </div>
                            ` : ''}

                            ${filteredSpans.map(span => {
                                const isSelected = selectedSpan === span;
                                const barWidthPct = Math.max(Math.min((span.duration_ms / totalDuration) * 100, 100), 4);
                                const isIndent = span.parent_id !== null;

                                let barColor = 'linear-gradient(90deg, #38bdf8, #60a5fa)';
                                if (span.kind === 'CLIENT' || span.name.includes('DB')) {
                                    barColor = 'linear-gradient(90deg, #a78bfa, #c084fc)';
                                } else if (span.kind === 'INTERNAL') {
                                    barColor = 'linear-gradient(90deg, #34d399, #10b981)';
                                }

                                return html`
                                    <div class="trace-row"
                                         style="padding: 8px 10px; border-radius: 8px; cursor: pointer; border: 1px solid ${isSelected ? '#38bdf8' : 'var(--glass-border)'}; background: ${isSelected ? 'rgba(56, 189, 248, 0.12)' : 'rgba(255,255,255,0.02)'}; transition: all 0.2s;"
                                         @click=${() => this.selectSpan(span)}>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <div style="display: flex; align-items: center; gap: 6px; padding-left: ${isIndent ? '16px' : '0'};">
                                                ${isIndent ? html`<span style="color: var(--text-dim); font-size: 0.75rem;">↳</span>` : ''}
                                                <span style="font-weight: 600; font-size: 0.82rem; font-family: 'JetBrains Mono', monospace; color: ${isSelected ? '#38bdf8' : 'var(--text-bright)'};">
                                                    ${span.name}
                                                </span>
                                                <span class="suite-pill-blue" style="font-size: 0.65rem;">${span.kind}</span>
                                            </div>
                                            <span style="font-size: 0.75rem; font-weight: 600; color: #fbbf24; font-family: 'JetBrains Mono', monospace;">
                                                ${span.duration_ms} ms
                                            </span>
                                        </div>

                                        <!-- Proportional Duration Bar -->
                                        <div style="height: 6px; background: rgba(255,255,255,0.06); border-radius: 3px; overflow: hidden; margin-left: ${isIndent ? '16px' : '0'};">
                                            <div style="height: 100%; width: ${barWidthPct}%; background: ${barColor}; border-radius: 3px; transition: width 0.3s ease;"></div>
                                        </div>
                                    </div>
                                `;
                            })}
                        </div>
                    </div>

                    <!-- Right: Span Inspector -->
                    <div class="card" style="display: flex; flex-direction: column; padding: 1.25rem; background: rgba(15, 23, 42, 0.65); border: 1px solid var(--glass-border); border-radius: 12px; overflow-y: auto;">
                        ${!selectedSpan ? html`
                            <div style="padding: 4rem 1rem; text-align: center; color: var(--text-dim); margin: auto;">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem; opacity: 0.4;">📈</div>
                                <div style="font-size: 1rem; font-weight: 500;">Select a trace span</div>
                                <div style="font-size: 0.82rem; margin-top: 0.25rem;">Click on any span in the waterfall timeline to view W3C headers, timing, and OpenTelemetry attributes.</div>
                            </div>
                        ` : html`
                            <!-- Span Header -->
                            <div style="padding-bottom: 1rem; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border);">
                                <div style="font-size: 1.05rem; font-weight: 700; color: #38bdf8; font-family: 'JetBrains Mono', monospace;">
                                    ${selectedSpan.name}
                                </div>
                                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.5rem;">
                                    <span class="suite-pill-green">${selectedSpan.status || 'OK'}</span>
                                    <span class="suite-pill-purple">${selectedSpan.kind}</span>
                                    <span class="suite-pill-yellow">${selectedSpan.duration_ms} ms</span>
                                </div>
                            </div>

                            <!-- W3C Identifiers -->
                            <div style="margin-bottom: 1.25rem;">
                                <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                    W3C Trace Identifiers
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8rem; font-family: 'JetBrains Mono', monospace;">
                                    <div style="display: flex; justify-content: space-between; padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 6px;">
                                        <span style="color: var(--text-dim);">Trace ID:</span>
                                        <span style="color: var(--text-bright);">${selectedSpan.trace_id}</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 6px;">
                                        <span style="color: var(--text-dim);">Span ID:</span>
                                        <span style="color: var(--text-bright);">${selectedSpan.span_id}</span>
                                    </div>
                                    ${selectedSpan.parent_id ? html`
                                        <div style="display: flex; justify-content: space-between; padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 6px;">
                                            <span style="color: var(--text-dim);">Parent ID:</span>
                                            <span style="color: var(--text-bright);">${selectedSpan.parent_id}</span>
                                        </div>
                                    ` : ''}
                                </div>

                                <button class="btn secondary-btn btn-sm"
                                        style="width: 100%; margin-top: 0.75rem; justify-content: center;"
                                        @click=${() => this.copyTraceparent(selectedSpan)}>
                                    📋 Copy W3C Traceparent Header
                                </button>
                            </div>

                            <!-- Span Attributes / Tags -->
                            <div style="flex: 1;">
                                <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                    Attributes & Tags
                                </div>
                                ${!selectedSpan.tags || Object.keys(selectedSpan.tags).length === 0 ? html`
                                    <div style="padding: 1rem; font-size: 0.8rem; color: var(--text-dim);">No tags attached to this span.</div>
                                ` : html`
                                    <table class="suite-table" style="font-size: 0.8rem;">
                                        <thead>
                                            <tr>
                                                <th>Attribute</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${Object.entries(selectedSpan.tags).map(([k, v]) => html`
                                                <tr>
                                                    <td style="font-weight: 600; font-family: 'JetBrains Mono', monospace; color: #a78bfa;">${k}</td>
                                                    <td style="font-family: 'JetBrains Mono', monospace; color: var(--text-bright); word-break: break-all;">${String(v)}</td>
                                                </tr>
                                            `)}
                                        </tbody>
                                    </table>
                                `}
                            </div>
                        `}
                    </div>
                </div>
            </div>
        `;
    }
}
