/**
 * TestingView Component
 * 
 * Provides an interface for Automated Evolutionary Testing.
 */
export default class ParikshakView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: false,
            running: false,
            results: null,
            appname: this.selectedApp || 'default',
            testEndpoint: '/school1/api/v1/',
            testPayload: '{\n  "action": "ping"\n}',
            testResponse: ''
        };
    }

    async runTests() {
        if (this.state.running) return;

        this.setState({ running: true, results: null });
        try {
            const res = await this.apiPost('run_auto_tests', {
                appname: this.state.appname
            });
            
            if (res.success) {
                this.setState({ results: res.data });
                this.notify(`Evolutionary tests completed for ${this.state.appname}.`, 'success');
            } else {
                this.notify(res.message, 'error');
            }
        } catch (e) {
            this.notify('Network error during automated testing.', 'error');
        } finally {
            this.setState({ running: false });
        }
    }

    async runMonkeyBot() {
        this.notify('🐒 Monkey Bot Launched: Stress-testing UI...', 'info');
        // Simulate random clicks and form inputs
        const buttons = document.querySelectorAll('.btn, a');
        const randomBtn = buttons[Math.floor(Math.random() * buttons.length)];
        if (randomBtn) {
            randomBtn.style.outline = '3px solid var(--accent-color)';
            setTimeout(() => randomBtn.click(), 500);
        }
    }

    async generateBlueprint(entityClass) {
        const res = await this.apiCall('generate_blueprint', { entity_class: entityClass });
        if (res.success) {
            console.log("Blueprint for " + entityClass, res.data);
            this.notify('Blueprint generated! check console for code.', 'success');
        }
    }

    async bulkEliteUpgrade() {
        this.notify('🚀 Initiating system-wide Elite Upgrade...', 'info');
        const res = await this.apiCall('bulk_elite_upgrade');
        if (res.success) {
            this.notify(`Success! ${res.data.upgraded}/${res.data.total} entities upgraded. Run "System Update" to sync DB.`, 'success');
        } else {
            this.notify('Upgrade failed: ' + res.message, 'danger');
        }
    }

    async dreamEntity() {
        const input = document.getElementById('dreamer-input');
        const shorthand = input.value;
        if (!shorthand) return;
        
        this.notify('Dreaming your entity into existence...', 'info');
        const res = await this.apiCall('dream_entity', { shorthand: shorthand });
        if (res.success) {
            this.notify('Entity created! Run "System Update" then "Sync" to see it.', 'success');
            input.value = '';
        } else {
            this.notify('Dream failed: ' + res.message, 'danger');
        }
    }

    async applyFix(entityClass, fix) {
        this.notify('Applying autonomous repair...', 'info');
        const res = await this.apiCall('apply_fix', {
            entity_class: entityClass,
            fix: fix
        });
        
        if (res.success) {
            this.notify('Manifest updated! Please run "System Update" to sync database.', 'success');
        } else {
            this.notify('Repair failed: ' + res.message, 'danger');
        }
    }

    async sendTestRequest() {
        const url = this.state.testEndpoint;
        const payloadStr = this.state.testPayload;
        let payload = null;

        if (payloadStr.trim()) {
            try {
                payload = JSON.parse(payloadStr);
            } catch (e) {
                this.setState({ testResponse: 'Error: Invalid JSON Payload\n' + e.message });
                return;
            }
        }

        this.setState({ testResponse: 'Sending request...' });
        try {
            const options = {
                method: payload ? 'POST' : 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            };
            if (payload) options.body = JSON.stringify(payload);

            const startTime = performance.now();
            const res = await fetch(url, options);
            const endTime = performance.now();
            const timeMs = Math.round(endTime - startTime);

            const rawText = await res.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                data = rawText;
            }

            const responseStr = `Status: ${res.status} ${res.statusText} (${timeMs}ms)\n\n` + 
                (typeof data === 'object' ? JSON.stringify(data, null, 2) : data);
                
            this.setState({ testResponse: responseStr });
        } catch (e) {
            this.setState({ testResponse: 'Network Error:\n' + e.message });
        }
    }

    render() {
        const { running, results, appname } = this.state;

        return html`
            <div class="view-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                <div>
                    <h2 style="margin:0;">🧬 Parikshak</h2>
                    <p style="opacity:0.6; margin-top:5px;">Zero-code Automated Evolutionary Testing for [${appname}]</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn ghost-btn" onclick="location.hash='#trace'">🛰️ View Trace Log</button>
                    <button class="btn accent-btn" @click="${() => this.runTests()}" ?disabled="${running}">
                        ${running ? html`<span class="spinner"></span> Running Analysis...` : '⚡ Run System Scan'}
                    </button>
                </div>
            </div>

            ${running ? html`
                <div class="glass-panel" style="text-align:center; padding:4rem 2rem;">
                    <div class="scanning-animation">
                        <div class="scan-ring"></div>
                        <div class="scan-core">🔍</div>
                    </div>
                    <h3 style="margin-top:2rem;">Analyzing System Metadata...</h3>
                    <p style="opacity:0.7;">Fuzzing entity invariants and verifying persistence cycles.</p>
                </div>
            ` : ''}

            ${!running && !results ? html`
                <div class="empty-state glass-panel">
                    <div style="font-size:4rem; margin-bottom:1rem;">🧬</div>
                    <h3>Engine Ready</h3>
                    <p>Trigger a full system scan to auto-generate and execute tests for all entities in the current context.</p>
                    <div style="margin-top:2rem; display:flex; gap:10px; justify-content:center;">
                        <span class="tag info-tag">Shadow DB: Enabled</span>
                        <span class="tag success-tag">Isolation: spptest__</span>
                    </div>
                </div>

                <div class="glass-panel" style="margin-top: 2rem; padding: 1.5rem; display: flex; gap: 2rem;">
                    <div style="flex: 1;">
                        <h3>Interactive API Runner</h3>
                        <p style="opacity:0.7; font-size: 0.9rem; margin-bottom: 1rem;">Test Auto-APIs and Polyglot Services directly</p>
                        
                        <div class="input-group">
                            <label>Endpoint / Route</label>
                            <input type="text" value="${this.state.testEndpoint}" @change=${e => this.state.testEndpoint = e.target.value} class="form-control" placeholder="/api/v1/user">
                        </div>
                        
                        <div class="input-group">
                            <label>JSON Payload (Leave empty for GET)</label>
                            <textarea class="form-control" style="font-family: monospace; height: 120px;" @change=${e => this.state.testPayload = e.target.value}>${this.state.testPayload}</textarea>
                        </div>
                        
                        <button class="btn accent-btn" @click=${() => this.sendTestRequest()}>🚀 Send Request</button>
                    </div>
                    
                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <h3>Response Console</h3>
                        <pre style="flex-grow: 1; background: rgba(0,0,0,0.3); border-radius: 8px; padding: 1rem; color: #0f0; border: 1px solid rgba(255,255,255,0.1); overflow-y: auto; white-space: pre-wrap; font-size: 0.9rem; margin-top: 1rem;">${this.state.testResponse || 'Awaiting request...'}</pre>
                    </div>
                </div>
            ` : ''}

            ${results ? this.renderResults(results) : ''}

            <style>
                .scanning-animation {
                    position: relative;
                    width: 100px;
                    height: 100px;
                    margin: 0 auto;
                }
                .scan-ring {
                    width: 100%;
                    height: 100%;
                    border: 4px solid var(--accent-color);
                    border-radius: 50%;
                    border-top-color: transparent;
                    animation: spin 1s linear infinite;
                }
                .scan-core {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-size: 2rem;
                }
                @keyframes spin { to { transform: rotate(360deg); } }

                .entity-card {
                    transition: transform 0.2s;
                    cursor: pointer;
                }
                .entity-card:hover {
                    transform: translateY(-5px);
                    background: rgba(255,255,255,0.05);
                }
            </style>
        `;
    }

    renderResults(results) {
        const { summary, entities } = results;
        const healthScore = Math.round((summary.passed / summary.total) * 100) || 0;
        const scoreColor = healthScore >= 90 ? 'var(--success)' : healthScore >= 60 ? 'var(--warning)' : 'var(--danger)';

        return html`
            <div class="dashboard-grid">
                <div class="info-card" style="grid-column: span 3; background: var(--glass-bg-accent);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3 style="margin:0;">System Health Score</h3>
                            <p style="opacity:0.6; margin:5px 0 0 0;">Based on ${summary.total} entities analyzed</p>
                        </div>
                        <div style="font-size:3rem; font-weight:bold; color:${scoreColor}; text-shadow: 0 0 20px ${scoreColor}44;">
                            ${healthScore}%
                        </div>
                    </div>
                    <div style="margin-top:20px; display:flex; flex-direction:column; gap:10px;">
                        <div style="display:flex; gap:5px;">
                            <input id="dreamer-input" type="text" placeholder="EntityName(attr:type, ...)" class="form-control btn-xs" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; flex-grow:1; padding: 4px 8px; border-radius: 4px;">
                            <button class="btn btn-xs accent" @click="${() => this.dreamEntity()}">✨ Dream</button>
                        </div>
                        <div style="display:flex; gap:5px;">
                            <button class="btn btn-xs success" style="flex-grow:1;" @click="${() => this.runMonkeyBot()}">🐒 Monkey Bot</button>
                            <button class="btn btn-xs secondary" style="flex-grow:1;" @click="${() => this.apiCall('run_oracle').then(r => this.notify(r.data.insight, 'info'))}">🔮 The Oracle</button>
                            <button class="btn btn-xs accent" style="flex-grow:1;" @click="${() => this.bulkEliteUpgrade()}">🚀 Elite Upgrade</button>
                        </div>
                    </div>
                    <div class="progress-bar-wrap" style="height:10px; background:rgba(255,255,255,0.1); border-radius:5px; margin-top:20px; overflow:hidden;">
                        <div class="progress-bar" style="height:100%; width:${healthScore}%; background:${scoreColor}; transition: width 1s ease-out;"></div>
                    </div>
                </div>
            </div>

            <h3 style="margin-top:2.5rem; margin-bottom:1.5rem;">Entity Status Reports</h3>
            <div class="entity-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
                ${entities.map(e => html`
                    <div class="entity-card glass-panel ${e.status === 'passed' ? 'border-success' : 'border-danger'}" style="padding:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <h4 style="margin:0;">${e.name} ${e.duration_ms ? html`<small style="font-size:0.6rem; opacity:0.4; margin-left:10px;">⏱️ ${e.duration_ms}ms</small>` : ''}</h4>
                                <code style="font-size:0.7rem; opacity:0.5;">${e.class}</code>
                                ${e.coverage ? html`
                                    <div style="margin-top:5px; display:flex; align-items:center; gap:8px;">
                                        <div style="width:50px; height:4px; background:rgba(255,255,255,0.1); border-radius:2px; overflow:hidden;">
                                            <div style="height:100%; width:${e.coverage.coverage_pct}%; background:var(--accent-color);"></div>
                                        </div>
                                        <span style="font-size:0.65rem; opacity:0.6;">${e.coverage.coverage_pct}% Coverage</span>
                                    </div>
                                ` : ''}
                            </div>
                            <span class="badge ${e.status === 'passed' ? 'success' : 'danger'}">${e.status.toUpperCase()}</span>
                        </div>
                        
                        <div class="scenario-list" style="margin-top:1.5rem;">
                            ${e.scenarios.map(s => html`
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; font-size:0.85rem;">
                                    <span style="opacity:0.8;">${s.name}</span>
                                    <span style="color:${s.status === 'passed' ? 'var(--success)' : 'var(--danger)'}">
                                        ${s.status === 'passed' ? '✓' : '✗'}
                                    </span>
                                </div>
                            `)}
                        </div>

                        ${e.diagnostics && e.diagnostics.length > 0 ? html`
                            <div class="diagnostics-panel" style="margin-top:15px; padding:12px; background:rgba(var(--accent-rgb), 0.05); border-left:3px solid var(--accent-color); border-radius:4px;">
                                <div style="font-size:0.7rem; text-transform:uppercase; opacity:0.6; margin-bottom:8px; font-weight:bold;">💡 Heuristic Diagnosis</div>
                                ${e.diagnostics.map(d => html`
                                    <div style="margin-bottom:10px;">
                                        <div style="font-size:0.85rem; font-weight:600;">${d.type}: ${d.message}</div>
                                        <div style="font-size:0.8rem; opacity:0.8; margin-top:2px;">Proposed Fix: ${d.action}</div>
                                        <button class="btn btn-xs success" style="margin-top:8px;" @click="${() => this.applyFix(e.class, d)}">⚡ One-Click Repair</button>
                                    </div>
                                `)}
                            </div>
                        ` : ''}

                        ${e.errors && e.errors.length > 0 && (!e.diagnostics || e.diagnostics.length === 0) ? html`
                            <div class="error-box" style="margin-top:15px; padding:10px; background:rgba(255,0,0,0.1); border-radius:5px; font-size:0.8rem; color:var(--danger);">
                                <strong>Log:</strong> ${e.errors[0]}
                            </div>
                        ` : ''}

                        <div style="margin-top:1.5rem; text-align:right; display:flex; justify-content:space-between; align-items:center;">
                             <span style="font-size:0.65rem; opacity:0.4;">Seed: ${results.seed}</span>
                             <div style="display:flex; gap:5px;">
                                <button class="btn ghost-btn btn-xs" @click="${() => this.generateBlueprint(e.class)}">📐 Blueprint</button>
                                <button class="btn ghost-btn btn-xs" @click="${() => this.notify('CI Report: ' + (results.report_path || 'Generating...'), 'info')}">📂 JUnit XML</button>
                             </div>
                        </div>
                    </div>
                `)}
            </div>
        `;
    }
}
