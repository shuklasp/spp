/**
 * SPP Developer Studio — Parikshak Test Studio
 * 
 * Primary Native Testing Engine for the SPP Framework.
 * Orchestrates SPPTestRunner suites, RefreshDatabase isolations,
 * assertion verification, and autonomous evolutionary chaos testing.
 */

export default class ParikshakComponent extends (window.BaseComponent || class {}) {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
    }

    async onInit() {
        this.state = {
            running: false,
            activeTab: 'runner', // 'runner', 'chaos'
            entityFilter: '',
            withCoverage: false,
            lastRun: null,
            expandedTest: null,
            // Chaos/Evolutionary state
            chaosRunning: false,
            chaosLog: '',
            dreamInput: ''
        };
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
    }

    async runTests() {
        if (this.state.running) return;

        const { entityFilter, withCoverage } = this.state;
        this.setState({ running: true });

        try {
            const res = await this.admin.api('parikshak_run', {
                app: this.admin.selectedApp,
                entity: entityFilter.trim() || null,
                coverage: withCoverage ? 1 : 0
            });

            const data = res.data || res;

            this.setState({
                running: false,
                lastRun: data
            });

            if (res.success && data.summary) {
                const passed = data.summary.passed || 0;
                const total = data.summary.total || 0;
                const failed = data.summary.failed || 0;
                if (failed === 0) {
                    this.admin.notify(`All ${passed}/${total} Parikshak tests passed in ${data.duration_ms || 0} ms!`, 'success');
                } else {
                    this.admin.notify(`${failed} test(s) failed out of ${total}.`, 'error');
                }
            } else {
                this.admin.notify(res.message || res.error || 'Test run encountered an error', 'error');
            }
        } catch (err) {
            console.error(err);
            this.setState({
                running: false,
                lastRun: {
                    success: false,
                    error: err.message || 'Network error running Parikshak.'
                }
            });
            this.admin.notify('Failed to run Parikshak tests.', 'error');
        }
    }

    async runChaosScan() {
        this.setState({ chaosRunning: true, chaosLog: '🧬 Launching Parikshak Evolutionary Chaos Scan...\n' });
        try {
            const res = await this.admin.api('run_parikshak_scan', { appname: this.admin.selectedApp });
            this.setState({
                chaosRunning: false,
                chaosLog: (this.state.chaosLog + '\n' + (res.data?.content || res.message || 'Scan complete.')).trim()
            });
            this.admin.notify('Evolutionary scan finished.', 'success');
        } catch (e) {
            this.setState({
                chaosRunning: false,
                chaosLog: this.state.chaosLog + '\nScan error: ' + e.message
            });
            this.admin.notify('Chaos scan failed', 'error');
        }
    }

    toggleTestExpand(idx) {
        this.setState({
            expandedTest: this.state.expandedTest === idx ? null : idx
        });
    }

    render() {
        const { running, activeTab, entityFilter, withCoverage, lastRun, expandedTest, chaosRunning, chaosLog, dreamInput } = this.state;
        const summary = lastRun?.summary || { total: 0, passed: 0, failed: 0, duration_ms: 0 };
        const tests = lastRun?.tests || [];

        return html`
            <div class="parikshak-suite" style="display: flex; flex-direction: column; gap: 1.5rem; height: 100%;">
                <!-- Hero Header -->
                <div class="suite-hero">
                    <div>
                        <h2>Parikshak Test Studio</h2>
                        <p>Primary Native Testing Engine of the SPP Framework. Execute unit and integration tests with automated in-memory DB isolation (<code>RefreshDatabase</code>) and evolutionary mutation analysis.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                        <span class="suite-pill-blue">Context: ${this.admin.selectedApp || 'default'}</span>
                        <span class="suite-pill-purple">Parikshak Engine v2.5</span>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="suite-stat-card">
                        <div class="stat-label">Total Tests</div>
                        <div class="stat-val" style="color: #38bdf8;">${summary.total}</div>
                        <div class="stat-sub">Executed assertions</div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="stat-label">Passed</div>
                        <div class="stat-val" style="color: #34d399;">${summary.passed}</div>
                        <div class="stat-sub">100% assertions green</div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="stat-label">Failed</div>
                        <div class="stat-val" style="color: ${summary.failed > 0 ? '#f87171' : 'var(--text-dim)'};">${summary.failed}</div>
                        <div class="stat-sub">${summary.failed > 0 ? 'Requires attention' : 'Zero regressions'}</div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="stat-label">Duration</div>
                        <div class="stat-val" style="color: #fbbf24;">${summary.duration_ms || lastRun?.duration_ms || 0} ms</div>
                        <div class="stat-sub">High-speed runner</div>
                    </div>
                </div>

                <!-- Sub-Navigation Tabs -->
                <div class="suite-tabs">
                    <button class="suite-tab-btn ${activeTab === 'runner' ? 'active' : ''}" @click=${() => this.switchTab('runner')}>
                        🧪 Parikshak Test Runner
                    </button>
                    <button class="suite-tab-btn ${activeTab === 'chaos' ? 'active' : ''}" @click=${() => this.switchTab('chaos')}>
                        🧬 Evolutionary & Chaos Testing
                    </button>
                </div>

                <!-- Tab 1: Parikshak Test Runner -->
                ${activeTab === 'runner' ? html`
                    <!-- Toolbar Controls -->
                    <div class="suite-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <div style="display: flex; gap: 1rem; align-items: center; flex: 1; min-width: 320px;">
                            <input type="text"
                                   class="suite-input"
                                   style="flex: 1; max-width: 320px; font-size: 0.85rem;"
                                   placeholder="Filter by entity or test class (e.g. User)..."
                                   .value=${entityFilter}
                                   @input=${(e) => this.setState({ entityFilter: e.target.value })}>

                            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.82rem; color: var(--text-dim); cursor: pointer;">
                                <input type="checkbox"
                                       ?checked=${withCoverage}
                                       @change=${(e) => this.setState({ withCoverage: e.target.checked })}>
                                Code Coverage
                            </label>
                        </div>

                        <button class="btn primary-btn shine-effect"
                                @click=${() => this.runTests()}
                                ?disabled=${running}>
                            ${running ? html`<span class="sppux-spinner" style="width:14px;height:14px;display:inline-block;margin-right:6px;"></span> Running Parikshak...` : html`🚀 Run Parikshak Tests`}
                        </button>
                    </div>

                    <!-- Test Results Pane -->
                    <div class="card" style="padding: 1.25rem; background: rgba(15, 23, 42, 0.65); border: 1px solid var(--glass-border); border-radius: 12px; flex: 1; min-height: 400px; display: flex; flex-direction: column;">
                        ${!lastRun ? html`
                            <div style="padding: 4rem 1rem; text-align: center; color: var(--text-dim); margin: auto;">
                                <div style="font-size: 3rem; margin-bottom: 0.75rem; opacity: 0.4;">🧪</div>
                                <div style="font-size: 1.05rem; font-weight: 600; color: var(--text-bright);">Parikshak Test Suite Ready</div>
                                <div style="font-size: 0.85rem; margin-top: 0.35rem; max-width: 480px; margin-left: auto; margin-right: auto;">
                                    Click <strong>Run Parikshak Tests</strong> above to execute the test suite for application <code>${this.admin.selectedApp || 'default'}</code>.
                                </div>
                            </div>
                        ` : html`
                            ${!lastRun.success ? html`
                                <div class="alert error" style="margin-bottom: 1rem; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
                                    <strong>Runner Error:</strong> ${lastRun.error || lastRun.message}
                                </div>
                            ` : ''}

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-bright);">
                                    Test Cases (${tests.length})
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);">
                                    App: <code>${lastRun.app || this.admin.selectedApp}</code>
                                </div>
                            </div>

                            ${tests.length === 0 ? html`
                                <div style="padding: 3rem; text-align: center; color: var(--text-dim); font-size: 0.85rem;">
                                    ${lastRun.success ? 'No tests found matching criteria. Parikshak tests reside in src/<App>/tests/ or tests/.' : ''}
                                </div>
                            ` : html`
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; overflow-y: auto;">
                                    ${tests.map((t, idx) => html`
                                        <div class="card" style="padding: 0.75rem 1rem; background: rgba(255,255,255,0.02); border: 1px solid ${t.passed ? 'rgba(52, 211, 153, 0.25)' : 'rgba(248, 113, 113, 0.3)'}; border-radius: 8px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;"
                                                 @click=${() => this.toggleTestExpand(idx)}>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <span class="${t.passed ? 'suite-pill-green' : 'suite-pill-red'}" style="font-size: 0.7rem; font-weight: 700;">
                                                        ${t.passed ? 'PASS' : 'FAIL'}
                                                    </span>
                                                    <div>
                                                        <span style="font-weight: 600; font-size: 0.85rem; font-family: 'JetBrains Mono', monospace; color: var(--text-bright);">
                                                            ${t.method || t.name}
                                                        </span>
                                                        <span style="font-size: 0.75rem; color: var(--text-dim); margin-left: 0.5rem;">
                                                            ${t.class || t.file || ''}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div style="display: flex; align-items: center; gap: 1rem;">
                                                    ${t.duration_ms !== undefined ? html`
                                                        <span style="font-size: 0.75rem; color: var(--text-dim);">${t.duration_ms} ms</span>
                                                    ` : ''}
                                                    <span style="font-size: 0.8rem; color: var(--text-dim);">${expandedTest === idx ? '▲' : '▼'}</span>
                                                </div>
                                            </div>

                                            ${expandedTest === idx ? html`
                                                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--glass-border); font-size: 0.8rem;">
                                                    ${t.passed ? html`
                                                        <div style="color: #34d399;">
                                                            ✅ All assertions passed successfully in ${t.duration_ms || 0} ms.
                                                        </div>
                                                    ` : html`
                                                        <div class="alert error" style="margin: 0; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;">
                                                            <strong>Failure:</strong> ${t.message || t.error || 'Assertion failed'}
                                                            ${t.file ? html`<div style="margin-top: 4px; opacity: 0.8;">at ${t.file}:${t.line}</div>` : ''}
                                                            ${t.trace ? html`<pre style="margin-top: 6px; max-height: 120px; overflow-y: auto; font-size: 0.72rem;">${t.trace}</pre>` : ''}
                                                        </div>
                                                    `}
                                                </div>
                                            ` : ''}
                                        </div>
                                    `)}
                                </div>
                            `}
                        `}
                    </div>
                ` : ''}

                <!-- Tab 2: Evolutionary & Chaos Testing -->
                ${activeTab === 'chaos' ? html`
                    <div class="card" style="padding: 1.5rem; background: rgba(15, 23, 42, 0.65); border: 1px solid var(--glass-border); border-radius: 12px; display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0; font-size: 1rem; color: #38bdf8;">Autonomous Chaos Engine & Mutation Testing</h3>
                                <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: var(--text-dim);">Stress-test database entities and form manifests with evolutionary fuzzing and self-healing repair.</p>
                            </div>
                            <button class="btn primary-btn" @click=${() => this.runChaosScan()} ?disabled=${chaosRunning}>
                                ${chaosRunning ? html`<span class="sppux-spinner" style="width:14px;height:14px;display:inline-block;margin-right:6px;"></span> Scanning...` : html`🧬 Trigger Chaos Scan`}
                            </button>
                        </div>

                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-dim); margin-bottom: 0.35rem; text-transform: uppercase;">
                                Autonomous Mutation Activity Log
                            </div>
                            <pre class="suite-terminal" style="margin: 0; padding: 1rem; height: 280px; overflow-y: auto; font-size: 0.82rem; background: #060913; border-radius: 8px;">${chaosLog || 'Click "Trigger Chaos Scan" to launch autonomous evolutionary testing.'}</pre>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }
}
