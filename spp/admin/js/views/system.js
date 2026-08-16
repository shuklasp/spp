/**
 * SystemView Component
 * 
 * Renders framework diagnostics, event tracing, configuration management,
 * task queue, and polyglot service status.
 * (Absorbs former config.js, queue.js, and polyglot.js modules.)
 */
import TraceView from './trace.js';

export default class SystemView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            system: null,
            bridge: null,
            apps: [],
            syncing: false,
            settings: null,
            editMode: 'form', // 'form' or 'yaml'
            savingSettings: false,
            activeMainTab: 'system', // 'system', 'trace', 'config', 'queue', 'polyglot'
            // --- Inlined Config state ---
            configData: { global: {}, app: {}, sys: {} },
            configActiveTab: 'global',
            configLoading: false,
            // --- Inlined Queue state ---
            queueData: [],
            queueLoading: false,
            queueError: null,
            // --- Inlined Polyglot state ---
            polyglotOutput: '',
            polyglotLoading: false,
            polyglotError: null
        };
        
        this.traceView = new TraceView(this.app, this.container, this.props);
        if (this.traceView.onInit) this.traceView.onInit();

        await this.fetchData();
        await this.fetchSettings();
    }

    async fetchSettings() {
        try {
            const res = await this.api('get_global_settings');
            if (res.success) {
                this.setState({ settings: res.data });
            }
        } catch (e) {
            console.error('Failed to fetch settings:', e);
        }
    }

    async saveSettings() {
        this.setState({ savingSettings: true });
        try {
            const { settings, editMode } = this.state;
            const payload = { mode: editMode };


            if (editMode === 'yaml') {
                const editor = this.container.querySelector('.yaml-editor');
                if (!editor) throw new Error('YAML Editor textarea not found in DOM.');
                payload.yaml = editor.value;
            } else {
                payload.data = JSON.stringify(settings.parsed);
            }


            const res = await this.apiPost('save_global_settings', payload);
            if (res.success) {
                this.notify('Settings saved successfully.', 'success');
                await this.fetchSettings();
            } else {
                this.notify(res.message, 'error');
            }
        } catch (e) {
            this.notify('Network error.', 'error');
        } finally {
            this.setState({ savingSettings: false });
        }
    }

    updateSetting(key, val, root = 'prototyping') {
        const settings = { ...this.state.settings };
        if (root) {
            let current = settings.parsed;
            if (root.includes('.')) {
                const parts = root.split('.');
                for (let i = 0; i < parts.length; i++) {
                    if (!current[parts[i]]) current[parts[i]] = {};
                    current = current[parts[i]];
                }
            } else {
                if (!current[root]) current[root] = {};
                current = current[root];
            }
            current[key] = val;
        } else {
            settings.parsed[key] = val;
        }
        this.setState({ settings });
    }

    async fetchData() {
        try {
            const [sysRes, appsRes] = await Promise.all([
                this.apiPost('execute_command', { command: 'sys:status', args: '--json' }),
                this.api('list_apps')
            ]);

            if (sysRes.success) {
                let systemData = {};
                let bridgeData = null;
                try {
                    const parsed = JSON.parse(sysRes.data.output || sysRes.data);
                    systemData = parsed.system || {};
                    bridgeData = parsed.bridge || null;
                } catch (e) {
                    console.error("Failed to parse sys:status output", e, sysRes.data.output);
                }

                this.setState({
                    system: systemData,
                    bridge: bridgeData,
                    apps: appsRes.data?.apps || [],
                    loading: false
                });
            } else {
                throw new Error(sysRes.message);
            }
        } catch (err) {
            console.error('System data fetch error:', err);
            this.setState({ loading: false, error: err.message });
        }
    }

    async refreshBridge() {
        this.setState({ syncing: true });
        try {
            const res = await this.api('setup_bridge');
            if (res.success) {
                this.notify('Polyglot Bridge environment refreshed.', 'success');
                await this.fetchData();
            } else {
                this.notify(res.message || 'Bridge refresh failed.', 'error');
            }
        } catch (e) {
            this.notify('Network error during bridge refresh.', 'error');
        } finally {
            this.setState({ syncing: false });
        }
    }

    async testRuntime(lang) {
        this.notify(`Testing ${lang} runtime...`, 'info');
        try {
            const res = await this.apiPost('test_bridge', { lang });
            if (res.success) {
                this.notify(`${lang}: ${JSON.stringify(res.data)}`, 'success');
            } else {
                this.notify(`${lang} error: ${res.message}`, 'error');
            }
        } catch (e) {
            this.notify(`Failed to test ${lang} runtime.`, 'error');
        }
    }

    renderHealthReport(report) {
        if (!report) return '';

        const getStatusTheme = (status) => {
            switch (status) {
                case 'OK': return 'success';
                case 'WARN': return 'warning';
                case 'FAIL': return 'danger';
                default: return 'info';
            }
        };

        const scoreColor = report.score >= 90 ? 'var(--success)' : report.score >= 60 ? 'var(--warning)' : 'var(--danger)';

        return html`
            <div class="section-title-bar">
                <h3>System Health Report Card</h3>
                <div class="section-line"></div>
                <div class="health-score" style="display:flex; align-items:center; gap:10px; background: rgba(0,0,0,0.2); padding: 5px 15px; border-radius: 20px;">
                    <span style="font-size:0.75rem; opacity:0.8; text-transform: uppercase;">Overall Health:</span>
                    <strong style="font-size:1.1rem; color: ${scoreColor}; text-shadow: 0 0 10px ${scoreColor}44;">${report.score}%</strong>
                </div>
            </div>

            <div class="health-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                ${report.checks.map(check => html`
                    <div class="health-item-card glass-panel" style="padding: 1.5rem; display: flex; align-items: center; gap: 15px; transition: all 0.3s var(--transition);">
                        <div class="status-indicator ${getStatusTheme(check.status)}" style="width: 12px; height: 12px; box-shadow: 0 0 10px currentColor;"></div>
                        <div style="flex: 1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                                <div style="font-weight: 600; font-size: 0.95rem;">${check.name}</div>
                                <span class="badge ${getStatusTheme(check.status)}" style="font-size: 0.6rem; padding: 2px 6px;">${check.status}</span>
                            </div>
                            <div style="font-size: 0.8rem; opacity: 0.6;">${check.detail}</div>
                        </div>
                    </div>
                `)}
            </div>
        `;
    }

    render() {
        const { system, bridge, apps, loading, syncing, error, activeMainTab } = this.state;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            const headerHtml = html`
                <div class="spp-tabs" style="display: inline-flex;">
                    <div class="tab ${activeMainTab === 'system' ? 'active' : ''}" @click=${() => this.setState({activeMainTab: 'system'})}>🖥️ System</div>
                    <div class="tab ${activeMainTab === 'trace' ? 'active' : ''}" @click=${() => this.setState({activeMainTab: 'trace'})}>📡 Trace</div>
                    <div class="tab ${activeMainTab === 'config' ? 'active' : ''}" @click=${() => this.switchToConfigTab()}>⚙️ Config</div>
                    <div class="tab ${activeMainTab === 'queue' ? 'active' : ''}" @click=${() => this.switchToQueueTab()}>🕒 Queue</div>
                    <div class="tab ${activeMainTab === 'polyglot' ? 'active' : ''}" @click=${() => this.switchToPolyglotTab()}>🌍 Polyglot</div>
                </div>
            `;
            headerActions.innerHTML = headerHtml.toString();
            
            const tabs = headerActions.querySelectorAll('.tab');
            if (tabs[0]) tabs[0].onclick = () => this.setState({activeMainTab: 'system'});
            if (tabs[1]) tabs[1].onclick = () => this.setState({activeMainTab: 'trace'});
            if (tabs[2]) tabs[2].onclick = () => this.switchToConfigTab();
            if (tabs[3]) tabs[3].onclick = () => this.switchToQueueTab();
            if (tabs[4]) tabs[4].onclick = () => this.switchToPolyglotTab();
        }

        if (activeMainTab === 'trace') {
            return this.traceView.render();
        }
        if (activeMainTab === 'config') {
            return this.renderConfigTab();
        }
        if (activeMainTab === 'queue') {
            return this.renderQueueTab();
        }
        if (activeMainTab === 'polyglot') {
            return this.renderPolyglotTab();
        }

        if (loading) return html`<div class="loading-state">Syncing framework diagnostics...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        const activeApp = apps.find(a => a.name === this.selectedApp) || {};

        const truncatePath = (path, len) => {
            if (!path) return 'N/A';
            return path.length > len ? '...' + path.slice(-len) : path;
        };

        return html`
            <style>
                .system-view-container {
                    animation: fadeIn 0.5s ease-out;
                }
                .hero-context {
                    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(167, 139, 250, 0.05) 100%);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 24px;
                    padding: 2.5rem;
                    margin-bottom: 2rem;
                    position: relative;
                    overflow: hidden;
                }
                .hero-context::after {
                    content: '🎯';
                    position: absolute;
                    right: -20px;
                    bottom: -20px;
                    font-size: 12rem;
                    opacity: 0.03;
                    transform: rotate(-15deg);
                }
                .hero-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 2rem;
                }
                .hero-title h2 {
                    font-size: 2rem;
                    margin: 0;
                    background: linear-gradient(to right, #EA580C, var(--accent));
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                }
                .hero-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 2rem;
                }
                .hero-stat-item label {
                    display: block;
                    font-size: 0.75rem;
                    text-transform: uppercase;
                    letter-spacing: 0.1em;
                    color: var(--text-dim);
                    margin-bottom: 0.5rem;
                }
                .hero-stat-item .val {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: var(--text-main);
                }

                .dashboard-compact-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 1.5rem;
                    margin-bottom: 2rem;
                }
                @media (max-width: 1200px) { .dashboard-compact-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 600px) { .dashboard-compact-grid { grid-template-columns: 1fr; } }

                .compact-card {
                    background: var(--panel-bg);
                    border: 1px solid var(--glass-border);
                    border-radius: 16px;
                    padding: 1.5rem;
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                    transition: all 0.3s var(--transition);
                }
                .compact-card:hover {
                    border-color: var(--primary);
                    transform: translateY(-4px);
                    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                }
                .card-top {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .card-icon-sm {
                    font-size: 1.5rem;
                    width: 40px;
                    height: 40px;
                    background: rgba(255,255,255,0.03);
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .card-label {
                    font-size: 0.8rem;
                    color: var(--text-dim);
                    font-weight: 500;
                }
                .card-value {
                    font-size: 1.5rem;
                    font-weight: 700;
                }

                .section-title-bar {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin: 3rem 0 1.5rem 0;
                }
                .section-title-bar h3 {
                    margin: 0;
                    font-size: 1.2rem;
                    font-weight: 600;
                    letter-spacing: -0.01em;
                }
                .section-line {
                    flex: 1;
                    height: 1px;
                    background: linear-gradient(to right, var(--glass-border), transparent);
                }
            </style>

            <div class="system-view-container">
                <!-- Hero Context Section -->
                <div class="hero-context">
                    <div class="hero-header">
                        <div class="hero-title">
                            <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.2em; color: var(--primary);">System Environment</label>
                            <h2>Active Context: ${this.selectedApp}</h2>
                        </div>
                        <div class="hero-actions">
                            <span class="tag ${activeApp.db_config ? 'warning-tag' : 'success-tag'}" style="padding: 6px 12px; font-size: 0.8rem;">
                                ${activeApp.db_config ? '🚀 Custom DB Isolation' : '🛡️ System Default DB'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="hero-grid">
                        <div class="hero-stat-item">
                            <label>Base URL</label>
                            <div class="val"><code class="code-badge primary">${activeApp.base_url || '/'}</code></div>
                        </div>
                        <div class="hero-stat-item">
                            <label>Table Prefix</label>
                            <div class="val"><code class="code-badge warning">${activeApp.table_prefix || '(none)'}</code></div>
                        </div>
                        <div class="hero-stat-item">
                            <label>Shared Resource Group</label>
                            <div class="val"><span class="tag info-tag">${activeApp.shared_group || 'Isolated'}</span></div>
                        </div>
                        <div class="hero-stat-item">
                            <label>Asset Bundling</label>
                            <div class="val"><span class="badge ${system.stats.bundling_enabled ? 'success' : 'secondary'}">${system.stats.bundling_enabled ? 'ENABLED' : 'DISABLED'}</span></div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-compact-grid">
                    <div class="compact-card">
                        <div class="card-top">
                            <span class="card-label">Middleware</span>
                            <div class="card-icon-sm" style="color: var(--primary);">🔀</div>
                        </div>
                        <div class="card-value">${system.stats.middleware_count || 0}</div>
                        <div style="margin-top: auto;">
                            <button class="btn ghost-btn btn-xs" @click=${() => location.hash = 'middleware'}>View Pipeline</button>
                        </div>
                    </div>
                    <div class="compact-card">
                        <div class="card-top">
                            <span class="card-label">Queued Tasks</span>
                            <div class="card-icon-sm" style="color: var(--accent);">🕒</div>
                        </div>
                        <div class="card-value">${system.stats.queue_size || 0}</div>
                        <div style="margin-top: auto;">
                            <button class="btn ghost-btn btn-xs" @click=${() => location.hash = 'queue'}>Manage Queue</button>
                        </div>
                    </div>
                    <div class="compact-card">
                        <div class="card-top">
                            <span class="card-label">Orion Cache</span>
                            <div class="card-icon-sm" style="color: var(--info);">⚡</div>
                        </div>
                        <div class="card-value">${system.orion.cache_size}</div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top: auto;">
                            <span class="badge ${system.orion.cache_exists ? 'success' : 'danger'}" style="font-size: 0.6rem;">${system.orion.cache_exists ? 'OPTIMIZED' : 'LEGACY'}</span>
                            <button class="btn ghost-btn btn-xs" @click=${() => this.apiPost('execute_command', { command: 'cache:compile-registry', args: '--json' }).then(r => { try { const p = JSON.parse(r.data.output); this.notify(p.message, p.success ? 'success' : 'error'); } catch(e) { this.notify('Compiled', 'success'); } })}>Rebuild</button>
                        </div>
                    </div>
                    <div class="compact-card">
                        <div class="card-top">
                            <span class="card-label">DB Status</span>
                            <div class="card-icon-sm" style="color: var(--success);">💾</div>
                        </div>
                        <div class="card-value" style="font-size: 1.2rem; color: ${system.db_status === 'Connected' ? 'var(--success)' : 'var(--danger)'};">
                            ${system.db_status}
                        </div>
                        <div style="margin-top: auto; font-size: 0.7rem; opacity: 0.5;">Framework v${system.spp_version}</div>
                    </div>
                </div>

                ${this.renderHealthReport(system.health_report)}

                <div class="section-title-bar">
                    <h3>Environment Diagnostics</h3>
                    <div class="section-line"></div>
                </div>

                <div class="glass-panel" style="overflow: hidden; padding: 0;">
                    <table class="data-table elegant-table" style="margin: 0;">
                        <tr><th style="width: 250px; background: rgba(255,255,255,0.02);">Parameter</th><th>Value</th></tr>
                        <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">PHP Version</td><td>${system.php_version}</td></tr>
                        <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">Operating System</td><td>${system.os}</td></tr>
                        <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">Server Software</td><td>${system.server_software}</td></tr>
                        <tr><td style="background: rgba(255,255,255,0.01); font-weight: 500;">Framework Root</td><td><code class="path-label" style="font-size: 0.8rem;">${system.spp_base}</code></td></tr>
                    </table>
                </div>

                ${bridge ? html`
                    <div class="section-title-bar">
                        <h3>Polyglot Resource Bridge</h3>
                        <div class="section-line"></div>
                        <button class="btn ghost-btn btn-xs" @click=${() => this.refreshBridge()} ?disabled="${syncing}">
                            ${syncing ? '🔄 Syncing...' : '🔄 Sync Environment'}
                        </button>
                    </div>
                    <div class="bridge-stats-bar glass-panel" style="display: flex; gap: 40px; padding: 1.5rem; margin-bottom: 1.5rem; background: linear-gradient(to right, rgba(99, 102, 241, 0.05), transparent);">
                        <div class="hero-stat-item">
                            <label>Shared Data Root</label>
                            <code class="path-label" title="${bridge.shared_dir}">${truncatePath(bridge.shared_dir, 50)}</code>
                        </div>
                        <div class="hero-stat-item">
                            <label>Config Status</label>
                            <span class="tag ${bridge.config_exists ? 'success-tag' : 'danger-tag'}">${bridge.config_exists ? 'ACTIVE' : 'MISSING'}</span>
                        </div>
                        <div class="hero-stat-item">
                            <label>Last Synchronization</label>
                            <strong>${bridge.last_sync || 'Never'}</strong>
                        </div>
                    </div>

                    <div class="runtime-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
                        ${Object.entries(bridge.runtimes || {}).map(([id, r]) => html`
                            <div class="runtime-card glass-panel" style="padding: 1.5rem; transition: all 0.3s var(--transition); position: relative; overflow: hidden;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1rem;">
                                    <div>
                                        <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-main);">${r.name}</div>
                                        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Runtime Engine</div>
                                    </div>
                                    <div class="status-indicator ${r.path ? 'active' : 'inactive'}" style="width: 10px; height: 10px;"></div>
                                </div>
                                
                                <div style="margin-bottom: 1.5rem;">
                                    <div style="font-size: 0.7rem; color: var(--text-dim); margin-bottom: 4px;">Executable Path</div>
                                    <code style="font-size: 0.75rem; display: block; background: rgba(0,0,0,0.2); padding: 6px; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${r.path}">
                                        ${r.path ? truncatePath(r.path, 40) : 'Not Discovered'}
                                    </code>
                                </div>

                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div class="badge ${r.path ? 'success' : 'secondary'}" style="font-size: 0.65rem;">
                                        ${r.version && r.version !== 'Unknown' ? r.version : (r.path ? 'Detected' : 'Offline')}
                                    </div>
                                    ${r.path ? html`
                                        <button class="btn primary-btn btn-xs" style="padding: 4px 12px; font-size: 0.7rem;" @click=${() => this.testRuntime(id)}>
                                            ⚡ Test Bridge
                                        </button>
                                    ` : html`
                                        <span style="font-size: 0.7rem; opacity: 0.4;">Binary missing</span>
                                    `}
                                </div>
                            </div>
                        `)}
                    </div>
                ` : ''}

                <div class="section-title-bar">
                    <h3>Framework Configuration</h3>
                    <div class="section-line"></div>
                </div>
                ${this.renderSourceHeader({ label: 'etc/global-settings.yml', type: 'yaml' })}
                ${this.renderSettingsEditor()}

                <!-- Action Banner -->
                <div class="action-banner glass-panel mt-4" style="display: flex; justify-content: space-between; align-items: center; padding: 2rem; background: linear-gradient(90deg, rgba(99, 102, 241, 0.05) 0%, transparent 100%);">
                    <div>
                        <h4 style="margin: 0; font-size: 1.1rem;">SPP Developer Workbench</h4>
                        <p style="margin: 5px 0 0 0; color: var(--text-dim); font-size: 0.9rem;">Manage all applications and database sharing in the dedicated section.</p>
                    </div>
                    <div style="display:flex; gap:12px;">
                        <button class="btn ghost-btn" @click=${() => this.apiPost('execute_command', {command: 'cache:clear'}).then(res => this.notify(res.message, res.success ? 'success' : 'error'))}>🧹 Clear Framework Cache</button>
                        <button class="btn ghost-btn" @click=${() => location.hash = 'apps'}>📱 Manage Applications</button>
                        <button type="button" class="btn accent-btn" @click=${() => { console.log('Update button clicked'); this.app.runSystemUpdate(); }} style="background: var(--accent-gradient); color: white; border: none;">🚀 Update System</button>
                    </div>
                </div>
            </div>
        `;
    }

    renderSettingsEditor() {
        const { settings, editMode, savingSettings } = this.state;
        if (!settings) return '';

        const proto = settings.parsed.prototyping || { auto_evolution: 'manual', view_generation: 'php_html' };

        return html`
            <div class="details-section glass-panel mt-4">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h3><span class="view-icon">⚙️</span> Framework Configuration</h3>
                    <div style="display:flex; gap:10px;">
                        <div class="toggle-group" style="background: rgba(0,0,0,0.2); padding: 4px; border-radius: 8px;">
                            <button class="btn btn-xs ${editMode === 'form' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setState({ editMode: 'form' })}>Form</button>
                            <button class="btn btn-xs ${editMode === 'yaml' ? 'primary-btn' : 'ghost-btn'}" @click=${() => this.setState({ editMode: 'yaml' })}>YAML</button>
                        </div>
                        <button class="btn accent-btn btn-sm" @click=${() => this.saveSettings()} ?disabled="${savingSettings}">
                            ${savingSettings ? '💾 Saving...' : '💾 Save Settings'}
                        </button>
                    </div>
                </div>

                ${editMode === 'form' ? html`
                    <div class="settings-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; opacity:0.8;">Framework Debug Mode</label>
                            <select class="form-input" style="width:100%;" @change=${(e) => this.updateSetting('debug', e.target.value === 'true', 'settings')}>
                                <option value="true" ?selected="${settings.parsed.settings?.debug === true}">Enabled (Show Debug Bar)</option>
                                <option value="false" ?selected="${settings.parsed.settings?.debug !== true}">Disabled (Hide Debug Bar)</option>
                            </select>
                            <p style="font-size:0.75rem; opacity:0.5; mt-1;">Controls the visibility of the SPP Debug Bar and error reporting.</p>
                        </div>
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; opacity:0.8;">Auto-Evolution (Schema Sync)</label>
                            <select class="form-input" style="width:100%;" @change=${(e) => this.updateSetting('auto_evolution', e.target.value)}>
                                <option value="manual" ?selected="${proto.auto_evolution === 'manual'}">Manual (CLI command required)</option>
                                <option value="automatic" ?selected="${proto.auto_evolution === 'automatic'}">Automatic (On entity save)</option>
                            </select>
                            <p style="font-size:0.75rem; opacity:0.5; mt-1;">Controls if DB tables are automatically updated when YAML changes.</p>
                        </div>
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; opacity:0.8;">Event Auto-Scan (Dev Mode)</label>
                            <select class="form-input" style="width:100%;" @change=${(e) => this.updateSetting('auto_scan', e.target.value === 'true', 'settings.events')}>
                                <option value="true" ?selected="${settings.parsed.settings?.events?.auto_scan === true}">Enabled (Scan Every Pass)</option>
                                <option value="false" ?selected="${settings.parsed.settings?.events?.auto_scan !== true}">Disabled (Use Cache)</option>
                            </select>
                            <p style="font-size:0.75rem; opacity:0.5; mt-1;">Dynamically scan event directories and bypass event cache during active development.</p>
                        </div>
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; opacity:0.8;">Scaffold View Generation</label>
                            <select class="form-input" style="width:100%;" @change=${(e) => this.updateSetting('view_generation', e.target.value)}>
                                <option value="php_html" ?selected="${proto.view_generation === 'php_html'}">Legacy PHP/HTML Templates</option>
                                <option value="mod_comp" ?selected="${proto.view_generation === 'mod_comp'}">Modern Admin Components</option>
                            </select>
                            <p style="font-size:0.75rem; opacity:0.5; mt-1;">Algorithm used for generating views during code scaffolding.</p>
                        </div>
                    </div>
                ` : html`
                    <div class="editor-wrap" style="position:relative; background: #1a1a1a; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                        <textarea class="yaml-editor" style="width:100%; min-height:300px; background:transparent; color:#d4d4d4; font-family: 'Consolas', monospace; font-size: 0.9rem; padding: 20px; border:none; outline:none; resize:vertical;">${settings.raw}</textarea>
                    </div>
                `}
            </div>
        `;
    }

    // =========================================================================
    //  INLINED: Config (formerly config.js)
    // =========================================================================

    async switchToConfigTab() {
        this.setState({ activeMainTab: 'config' });
        if (!this.state.configLoading && Object.keys(this.state.configData.global).length === 0) {
            await this.fetchConfigData();
        }
    }

    async fetchConfigData() {
        this.setState({ configLoading: true });
        try {
            const res = await this.api('get_config_all');
            if (res.success) {
                this.setState({
                    configData: res.data.config,
                    configLoading: false
                });
            }
        } catch (err) {
            this.setState({ configLoading: false });
            this.notify('Failed to load config: ' + err.message, 'error');
        }
    }

    async updateConfigValue(key, value) {
        const { configActiveTab } = this.state;
        const fullKey = configActiveTab === 'global' ? `global:${key}` : (configActiveTab === 'sys' ? `sys:${key}` : `app:${key}`);
        
        try {
            const res = await this.apiPost('save_config_value', { key: fullKey, value });
            if (res.success) {
                this.notify(`Updated ${fullKey}`, 'success');
                await this.fetchConfigData();
            } else {
                this.notify(`Failed: ${res.message}`, 'error');
            }
        } catch (err) {
            this.notify(`Error: ${err.message}`, 'error');
        }
    }

    renderConfigTab() {
        const { configData, configActiveTab, configLoading } = this.state;
        const currentData = configData[configActiveTab] || {};
        const entries = Object.entries(currentData);

        if (configLoading) return html`<div class="loading-state">Loading configuration registry...</div>`;

        return html`
            <div class="config-container">
                <div class="tabs-toolbar" style="margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 1px solid var(--glass-border);">
                    <button class="tab-btn ${configActiveTab === 'global' ? 'active' : ''}" @click=${() => this.setState({configActiveTab: 'global'})}>
                        🌍 Global Settings
                    </button>
                    <button class="tab-btn ${configActiveTab === 'app' ? 'active' : ''}" @click=${() => this.setState({configActiveTab: 'app'})}>
                        📱 App Context
                    </button>
                    <button class="tab-btn ${configActiveTab === 'sys' ? 'active' : ''}" @click=${() => this.setState({configActiveTab: 'sys'})}>
                        🖥️ Infrastructure
                    </button>
                    <div style="flex: 1;"></div>
                    <button class="btn ghost-btn btn-sm" @click=${() => this.fetchConfigData()}>🔄 Refresh Config</button>
                </div>

                <div class="glass-panel" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--glass-border); font-size: 0.85rem; color: var(--text-dim); display: flex; justify-content: space-between;">
                        <span>PARAMETER KEY</span>
                        <span>VALUE / OVERRIDE</span>
                    </div>
                    <div class="config-list">
                        ${entries.length > 0 ? entries.map(([k, v]) => {
                            if (typeof v === 'object' && v !== null) v = JSON.stringify(v);
                            return html`
                                <div class="config-row" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid var(--glass-border); gap: 20px;">
                                    <div style="flex: 1;">
                                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; color: var(--accent-light);">${k}</div>
                                        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Source: ${configActiveTab.toUpperCase()} config</div>
                                    </div>
                                    <div style="flex: 2;">
                                        ${typeof v === 'boolean' 
                                            ? html`<label class="toggle-switch">
                                                <input type="checkbox" ?checked="${v}" @change=${(e) => this.updateConfigValue(k, e.target.checked)}>
                                                <span class="toggle-slider"></span>
                                               </label>`
                                            : html`<input type="text" class="spp-element" value="${v}" 
                                                style="width: 100%; font-family: monospace;"
                                                @change=${(e) => this.updateConfigValue(k, e.target.value)}>`
                                        }
                                    </div>
                                </div>
                            `;
                        }) : html`<div class="empty-state" style="padding: 40px;">No configuration keys found in this namespace.</div>`}
                    </div>
                </div>

                <div class="alert info-alert" style="margin-top: 20px; display: flex; align-items: flex-start; gap: 10px;">
                    <span style="font-size: 1.2rem;">🛡️</span>
                    <div>
                        <strong>Hierarchical Resolution:</strong> SPP resolves values by checking <code>App</code> context first, then <code>Global</code> defaults. 
                        Infrastructure settings (<code>sys</code>) manage application routing and paths.
                    </div>
                </div>
            </div>
        `;
    }

    // =========================================================================
    //  INLINED: Task Queue (formerly queue.js)
    // =========================================================================

    async switchToQueueTab() {
        this.setState({ activeMainTab: 'queue' });
        await this.fetchQueueData();
    }

    async fetchQueueData() {
        this.setState({ queueLoading: true, queueError: null });
        try {
            const res = await this.api('list_queue');
            if (res.success) {
                this.setState({
                    queueData: res.data.queue || [],
                    queueLoading: false
                });
            } else {
                this.setState({ queueError: res.message, queueLoading: false });
            }
        } catch (err) {
            this.setState({ queueError: err.message, queueLoading: false });
        }
    }

    renderQueueTab() {
        const { queueData, queueLoading, queueError } = this.state;

        if (queueLoading) return html`<div class="loading-state">Fetching task queue...</div>`;
        if (queueError) return html`<div class="error-state">Failed to load task queue: ${queueError}</div>`;

        return html`
            <div class="queue-manager glass-panel">
                <div class="manager-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--glass-border);">
                    <h3 style="margin: 0;">Shared Task Queue</h3>
                    <div class="stats" style="display: flex; gap: 1rem; align-items: center;">
                        <span class="stat-item">Pending: <strong>${queueData.length}</strong></span>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.fetchQueueData()}>🔄 Refresh</button>
                    </div>
                </div>
                <table class="spp-table">
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Worker Class</th>
                            <th>Payload</th>
                            <th>Queued At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${queueData.length > 0 ? queueData.map(job => html`
                            <tr class="job-row">
                                <td><code>${job.id}</code></td>
                                <td><span class="job-class">${job.job}</span></td>
                                <td><pre class="job-data" style="margin:0; font-size:0.8rem; max-width:300px; overflow:auto;">${JSON.stringify(job.data, null, 2)}</pre></td>
                                <td><span class="badge secondary">${new Date(job.created_at * 1000).toLocaleString()}</span></td>
                                <td><span class="status-pill waiting">Waiting</span></td>
                            </tr>
                        `) : html`<tr><td colspan="5" class="text-center" style="padding:2rem; opacity:0.5;">Queue is empty. Workers are idle.</td></tr>`}
                    </tbody>
                </table>
            </div>
        `;
    }

    // =========================================================================
    //  INLINED: Polyglot Services (formerly polyglot.js)
    // =========================================================================

    async switchToPolyglotTab() {
        this.setState({ activeMainTab: 'polyglot' });
        await this.fetchPolyglotData();
    }

    async fetchPolyglotData() {
        this.setState({ polyglotLoading: true, polyglotError: null });
        try {
            const res = await this.api('execute_command', { command: 'polyglot:list' });
            if (res.success) {
                this.setState({
                    polyglotOutput: res.data.output || 'No output.',
                    polyglotLoading: false
                });
            } else {
                this.setState({
                    polyglotError: res.message || 'Failed to load polyglot services.',
                    polyglotLoading: false
                });
            }
        } catch (err) {
            this.setState({ polyglotError: err.message, polyglotLoading: false });
        }
    }

    renderPolyglotTab() {
        const { polyglotOutput, polyglotLoading, polyglotError } = this.state;

        if (polyglotLoading) return html`<div class="loading-state">Loading Polyglot services...</div>`;
        if (polyglotError) return html`<div class="empty-state"><h3>Error</h3><p>${polyglotError}</p></div>`;

        return html`
            <div class="polyglot-manager glass-panel" style="padding: 2rem;">
                <div class="manager-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.4rem; font-weight: 600; color: var(--text-main);">Polyglot Services</h3>
                        <p style="margin: 5px 0 0 0; color: var(--text-dim); font-size: 0.95rem;">Background services written in Go, Python, Node, Java, etc.</p>
                    </div>
                    <button class="btn ghost-btn btn-sm" @click=${() => this.fetchPolyglotData()}>🔄 Refresh</button>
                </div>
                <div class="polyglot-output">
                    <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: 'Consolas', monospace; font-size: 0.85rem; line-height: 1.5; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);">${polyglotOutput}</pre>
                </div>
            </div>
        `;
    }
}
