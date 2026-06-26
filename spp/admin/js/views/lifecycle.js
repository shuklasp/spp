/**
 * LifecycleView - Enterprise Deployment Command Center
 * Refurbished dashboard for SPPDeploy module.
 */
export default class LifecycleView extends BaseComponent {
    constructor(app, container, props = {}) {
        super(app, container, props);
        this.state = {
            target: 'production',
            environments: {},
            activeTab: 'overview',
            deltas: null,
            loading: false,
            logContent: '',
            logOffset: -1,
            logTailing: false,
            logTailTimer: null,
            commandHistory: []
        };
    }

    async onInit() {
        await this.loadEnvironments();
    }

    async loadEnvironments() {
        try {
            const res = await this.admin.api('lifecycle_get_envs');
            if (res.success) {
                this.environments = res.data.environments || {};
            }
        } catch (err) {
            console.error("Failed to load environments:", err);
        }
    }

    async update() {
        await this.render(this.container);
    }

    async render(container) {
        const tabs = [
            { id: 'overview',  icon: '📊', label: 'Overview' },
            { id: 'deploy',    icon: '🚀', label: 'Deploy' },
            { id: 'rollback',  icon: '🔄', label: 'Rollback & Backups' },
            { id: 'console',   icon: '🖥️', label: 'Remote Console' },
            { id: 'cluster',   icon: '🌐', label: 'Cluster & Webhooks' },
            { id: 'security',  icon: '🔐', label: 'Security' }
        ];

        let envOptions = '';
        Object.keys(this.environments).forEach(name => {
            const selected = name === this.target ? 'selected' : '';
            envOptions += `<option value="${name}" ${selected}>${name}</option>`;
        });

        container.innerHTML = `
            <style>${this.getStyles()}</style>
            <div class="deploy-dashboard">
                <div class="deploy-header">
                    <div class="deploy-header-left">
                        <div class="deploy-logo">
                            <div class="deploy-logo-icon">⚡</div>
                            <div>
                                <h1 class="deploy-title">SPPDeploy</h1>
                                <span class="deploy-subtitle">Enterprise Deployment Command Center</span>
                            </div>
                        </div>
                    </div>
                    <div class="deploy-header-right">
                        <div class="deploy-env-select">
                            <label>Target</label>
                            <select id="deploy-target-env">${envOptions || '<option value="production">production</option>'}</select>
                        </div>
                        <button class="deploy-btn deploy-btn-ghost" id="btn-health-ping" title="Ping Target">
                            <span class="pulse-dot" id="health-dot"></span> Health
                        </button>
                    </div>
                </div>

                <nav class="deploy-tabs" id="deploy-tabs">
                    ${tabs.map(t => `
                        <button class="deploy-tab ${t.id === this.activeTab ? 'active' : ''}" data-tab="${t.id}">
                            <span class="tab-icon">${t.icon}</span>
                            <span class="tab-label">${t.label}</span>
                        </button>
                    `).join('')}
                    <div class="tab-indicator" id="tab-indicator"></div>
                </nav>

                <div class="deploy-tab-content" id="deploy-tab-content">
                    ${this.renderActiveTab()}
                </div>
            </div>
        `;

        this.bindGlobalEvents(container);
        this.positionTabIndicator();

        // Auto-load data for the active tab
        if (this.activeTab === 'overview') await this.loadOverviewData();
    }

    renderActiveTab() {
        switch (this.activeTab) {
            case 'overview':  return this.renderOverviewTab();
            case 'deploy':    return this.renderDeployTab();
            case 'rollback':  return this.renderRollbackTab();
            case 'console':   return this.renderConsoleTab();
            case 'cluster':   return this.renderClusterTab();
            case 'security':  return this.renderSecurityTab();
            default:          return this.renderOverviewTab();
        }
    }

    // ─── OVERVIEW TAB ────────────────────────────────────────────
    renderOverviewTab() {
        return `
            <div class="deploy-grid deploy-grid-4">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-primary">🚀</div>
                    <div class="stat-value" id="stat-deploys">—</div>
                    <div class="stat-label">Total Deployments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-success">✅</div>
                    <div class="stat-value" id="stat-last-deploy">—</div>
                    <div class="stat-label">Last Deployment</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-warning">📦</div>
                    <div class="stat-value" id="stat-backups">—</div>
                    <div class="stat-label">Active Backups</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-info">⏱️</div>
                    <div class="stat-value" id="stat-health">—</div>
                    <div class="stat-label">Server Latency</div>
                </div>
            </div>

            <div class="deploy-grid deploy-grid-2" style="margin-top: 1.5rem;">
                <div class="deploy-card">
                    <div class="card-header">
                        <h3>⚡ Quick Actions</h3>
                    </div>
                    <div class="quick-actions-grid">
                        <button class="action-btn action-btn-primary" id="btn-quick-deploy">
                            <span class="action-icon">🚀</span>
                            <span>Deploy Now</span>
                        </button>
                        <button class="action-btn action-btn-warning" id="btn-quick-backup">
                            <span class="action-icon">📦</span>
                            <span>Create Backup</span>
                        </button>
                        <button class="action-btn action-btn-danger" id="btn-quick-maintenance">
                            <span class="action-icon">🔧</span>
                            <span>Maintenance Mode</span>
                        </button>
                        <button class="action-btn action-btn-info" id="btn-quick-logs">
                            <span class="action-icon">📋</span>
                            <span>View Logs</span>
                        </button>
                    </div>
                </div>

                <div class="deploy-card">
                    <div class="card-header">
                        <h3>📜 Recent Deployments</h3>
                    </div>
                    <div class="timeline" id="deploy-timeline">
                        <div class="timeline-empty">Loading deployment history...</div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadOverviewData() {
        // Load deployment history
        try {
            const histRes = await this.admin.api('lifecycle_deploy_history');
            if (histRes.success && histRes.data.history) {
                const history = histRes.data.history;
                document.getElementById('stat-deploys').textContent = history.length;
                if (history.length > 0) {
                    const last = history[0];
                    const ago = this.timeAgo(new Date(last.date));
                    document.getElementById('stat-last-deploy').textContent = ago;
                }

                const timeline = document.getElementById('deploy-timeline');
                if (history.length === 0) {
                    timeline.innerHTML = '<div class="timeline-empty">No deployments yet.</div>';
                } else {
                    timeline.innerHTML = history.slice(0, 5).map(h => `
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">${h.filename}</div>
                                <div class="timeline-meta">${h.date} · ${this.formatSize(h.size)}</div>
                            </div>
                        </div>
                    `).join('');
                }
            }
        } catch (e) { console.error(e); }

        // Load backup count
        try {
            const backupRes = await this.admin.api('lifecycle_list_backups');
            if (backupRes.success && backupRes.data.backups) {
                document.getElementById('stat-backups').textContent = backupRes.data.backups.length;
            }
        } catch (e) { console.error(e); }

        // Ping health
        try {
            const healthRes = await this.admin.api('lifecycle_health_check', { target: this.target });
            if (healthRes.success && healthRes.data) {
                document.getElementById('stat-health').textContent = healthRes.data.latency_ms + 'ms';
                const dot = document.getElementById('health-dot');
                if (dot) dot.classList.add(healthRes.data.reachable ? 'dot-green' : 'dot-red');
            }
        } catch (e) { console.error(e); }
    }

    // ─── DEPLOY TAB ──────────────────────────────────────────────
    renderDeployTab() {
        return `
            <div class="deploy-card">
                <div class="card-header">
                    <h3>🔍 Sync Status & File Diff</h3>
                    <div class="card-actions">
                        <button class="deploy-btn deploy-btn-warning" id="btn-db-sync">
                            <span>🗄️</span> Sync DB Schema
                        </button>
                        <button class="deploy-btn deploy-btn-primary" id="btn-check-sync">
                            <span>🔍</span> Check Sync Status
                        </button>
                    </div>
                </div>
                <div id="deploy-diff-container">
                    <div class="empty-state">
                        <div class="empty-icon">📡</div>
                        <p>Click <strong>Check Sync Status</strong> to compare local files against the remote target.</p>
                    </div>
                </div>
            </div>

            <div class="deploy-card" id="delta-deploy-card" style="display: none;">
                <div class="card-header">
                    <h3>📋 Pending Changes</h3>
                    <span class="badge badge-primary" id="delta-count">0</span>
                </div>
                <div class="delta-table-wrapper">
                    <table class="deploy-table" id="delta-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Path / Resource</th>
                                <th>Action</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="delta-body"></tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button class="deploy-btn deploy-btn-success deploy-btn-lg" id="btn-deploy-all">
                        🚀 Deploy All Changes
                    </button>
                </div>
            </div>
        `;
    }

    // ─── ROLLBACK TAB ────────────────────────────────────────────
    renderRollbackTab() {
        return `
            <div class="deploy-card">
                <div class="card-header">
                    <h3>📦 Backup Snapshots</h3>
                    <button class="deploy-btn deploy-btn-primary" id="btn-create-backup">
                        📦 Create Backup Now
                    </button>
                </div>
                <div id="backups-container">
                    <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading backups...</p></div>
                </div>
            </div>
        `;
    }

    async loadBackups() {
        try {
            const res = await this.admin.api('lifecycle_list_backups');
            const container = document.getElementById('backups-container');
            if (!container) return;

            if (res.success && res.data.backups && res.data.backups.length > 0) {
                container.innerHTML = `
                    <table class="deploy-table">
                        <thead>
                            <tr><th>Filename</th><th>Type</th><th>Size</th><th>Created</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            ${res.data.backups.map(b => `
                                <tr>
                                    <td><code>${b.filename}</code></td>
                                    <td><span class="badge badge-${b.type === 'zip' ? 'primary' : 'warning'}">${b.type.toUpperCase()}</span></td>
                                    <td>${b.size_human}</td>
                                    <td>${b.date}</td>
                                    <td>
                                        <button class="deploy-btn deploy-btn-sm deploy-btn-danger btn-restore-backup" data-file="${b.filename}">
                                            🔄 Restore
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;

                container.querySelectorAll('.btn-restore-backup').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        const file = e.currentTarget.dataset.file;
                        if (!confirm(`Are you sure you want to restore backup "${file}"? This will overwrite current files on the target.`)) return;
                        e.currentTarget.disabled = true;
                        e.currentTarget.innerHTML = 'Restoring...';
                        try {
                            const res = await this.admin.api('lifecycle_restore_backup', { filename: file, target: this.target });
                            if (res.success) {
                                this.admin.notify('Backup restored successfully!', 'success');
                            }
                        } catch (err) { console.error(err); }
                    });
                });
            } else {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">📭</div><p>No backups found.</p></div>';
            }
        } catch (e) { console.error(e); }
    }

    // ─── CONSOLE TAB ─────────────────────────────────────────────
    renderConsoleTab() {
        return `
            <div class="deploy-grid deploy-grid-2">
                <div class="deploy-card console-card">
                    <div class="card-header">
                        <h3>📋 Live Log Viewer</h3>
                        <div class="card-actions">
                            <button class="deploy-btn deploy-btn-sm deploy-btn-ghost" id="btn-log-refresh">🔄 Refresh</button>
                            <button class="deploy-btn deploy-btn-sm ${this.logTailing ? 'deploy-btn-danger' : 'deploy-btn-success'}" id="btn-log-tail">
                                ${this.logTailing ? '⏹ Stop Tail' : '▶ Start Tail'}
                            </button>
                        </div>
                    </div>
                    <div class="terminal" id="log-terminal">
                        <pre id="log-output">${this.logContent || 'Click Refresh or Start Tail to load remote logs...'}</pre>
                    </div>
                </div>

                <div class="deploy-card console-card">
                    <div class="card-header">
                        <h3>⌨️ Remote Command Runner</h3>
                    </div>
                    <div class="command-input-row">
                        <span class="command-prompt">$</span>
                        <input type="text" id="remote-cmd-input" class="command-input" placeholder="e.g. php spp.php cache:clear" />
                        <button class="deploy-btn deploy-btn-primary" id="btn-run-cmd">Execute</button>
                    </div>
                    <div class="terminal" id="cmd-terminal">
                        <pre id="cmd-output">${this.commandHistory.length > 0 ? this.commandHistory.map(c => `<span class="cmd-prompt">$ ${c.cmd}</span>\n${c.output}\n<span class="cmd-exit">[exit: ${c.exit_code}]</span>\n`).join('\n') : 'Remote shell output will appear here...'}</pre>
                    </div>
                </div>
            </div>
        `;
    }

    async fetchLogs(tail = false) {
        try {
            const params = { target: this.target };
            if (tail && this.logOffset >= 0) params.offset = this.logOffset;

            const res = await this.admin.api('lifecycle_remote_logs', params);
            if (res.success && res.data) {
                if (tail && this.logContent) {
                    this.logContent += res.data.content || '';
                } else {
                    this.logContent = res.data.content || '(empty log)';
                }
                if (res.data.offset !== undefined) this.logOffset = res.data.offset;

                const output = document.getElementById('log-output');
                if (output) {
                    output.textContent = this.logContent;
                    output.parentElement.scrollTop = output.parentElement.scrollHeight;
                }
            }
        } catch (e) { console.error(e); }
    }

    toggleLogTail() {
        if (this.logTailing) {
            clearInterval(this.logTailTimer);
            this.logTailTimer = null;
            this.logTailing = false;
        } else {
            this.logTailing = true;
            this.fetchLogs(false); // initial fetch
            this.logTailTimer = setInterval(() => this.fetchLogs(true), 2000);
        }
        // Re-render button state
        const btn = document.getElementById('btn-log-tail');
        if (btn) {
            btn.className = `deploy-btn deploy-btn-sm ${this.logTailing ? 'deploy-btn-danger' : 'deploy-btn-success'}`;
            btn.innerHTML = this.logTailing ? '⏹ Stop Tail' : '▶ Start Tail';
        }
    }

    async runRemoteCommand() {
        const input = document.getElementById('remote-cmd-input');
        if (!input || !input.value.trim()) return;

        const cmd = input.value.trim();
        input.value = '';

        const output = document.getElementById('cmd-output');
        if (output) output.innerHTML += `\n<span class="cmd-prompt">$ ${cmd}</span>\nExecuting...\n`;

        try {
            const res = await this.admin.api('lifecycle_remote_run', { target: this.target, command: cmd });
            const entry = {
                cmd: cmd,
                output: res.data?.output || '(no output)',
                exit_code: res.data?.exit_code ?? -1
            };
            this.commandHistory.push(entry);

            if (output) {
                // Replace "Executing..." with actual output
                const lines = output.innerHTML.split('\n');
                lines.pop(); // Remove "Executing..."
                lines.pop();
                output.innerHTML = lines.join('\n') + `\n<span class="cmd-prompt">$ ${cmd}</span>\n${entry.output}\n<span class="cmd-exit">[exit: ${entry.exit_code}]</span>\n`;
                output.parentElement.scrollTop = output.parentElement.scrollHeight;
            }
        } catch (e) {
            if (output) output.innerHTML += `<span class="cmd-exit">[error: ${e.message}]</span>\n`;
        }
    }

    // ─── CLUSTER TAB ─────────────────────────────────────────────
    renderClusterTab() {
        return `
            <div class="deploy-grid deploy-grid-2">
                <div class="deploy-card">
                    <div class="card-header">
                        <h3>🌐 Cluster Nodes</h3>
                        <button class="deploy-btn deploy-btn-sm deploy-btn-primary" id="btn-refresh-cluster">🔄 Refresh Status</button>
                    </div>
                    <div id="cluster-container">
                        <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading cluster status...</p></div>
                    </div>
                </div>

                <div class="deploy-card">
                    <div class="card-header">
                        <h3>🔔 Webhook Notifications</h3>
                    </div>
                    <div id="webhooks-container">
                        <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading webhooks...</p></div>
                    </div>
                    <div class="webhook-add-row" style="margin-top: 1rem;">
                        <input type="text" id="webhook-url-input" class="command-input" placeholder="https://discord.com/api/webhooks/... or https://hooks.slack.com/..." />
                        <button class="deploy-btn deploy-btn-primary" id="btn-add-webhook">Add</button>
                        <button class="deploy-btn deploy-btn-ghost" id="btn-test-webhook">🧪 Test</button>
                    </div>
                </div>
            </div>
        `;
    }

    async loadClusterStatus() {
        try {
            const res = await this.admin.api('lifecycle_cluster_status');
            const container = document.getElementById('cluster-container');
            if (!container) return;

            if (res.success && res.data.clusters && Object.keys(res.data.clusters).length > 0) {
                let html = '';
                for (const [clusterName, nodes] of Object.entries(res.data.clusters)) {
                    html += `<div class="cluster-group"><h4>${clusterName}</h4>`;
                    html += nodes.map(n => `
                        <div class="cluster-node">
                            <span class="pulse-dot ${n.reachable ? 'dot-green' : 'dot-red'}"></span>
                            <span class="node-name">${n.name}</span>
                            <span class="node-latency">${n.reachable ? n.latency_ms + 'ms' : 'Unreachable'}</span>
                        </div>
                    `).join('');
                    html += '</div>';
                }
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">🌐</div><p>No clusters configured. Define clusters in <code>.sppdeploy.yml</code>.</p></div>';
            }
        } catch (e) { console.error(e); }
    }

    async loadWebhooks() {
        try {
            const res = await this.admin.api('lifecycle_get_webhooks');
            const container = document.getElementById('webhooks-container');
            if (!container) return;

            const webhooks = res.data?.webhooks || [];
            if (webhooks.length > 0) {
                container.innerHTML = webhooks.map((url, i) => {
                    const platform = url.includes('discord') ? '🟣 Discord' : url.includes('slack') ? '🟢 Slack' : '🔗 Custom';
                    return `
                        <div class="webhook-item">
                            <span class="webhook-platform">${platform}</span>
                            <code class="webhook-url">${url.substring(0, 60)}...</code>
                            <button class="deploy-btn deploy-btn-sm deploy-btn-danger btn-remove-webhook" data-idx="${i}">✕</button>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = '<div class="empty-state" style="padding:1rem;"><p>No webhooks configured.</p></div>';
            }
        } catch (e) { console.error(e); }
    }

    // ─── SECURITY TAB ────────────────────────────────────────────
    renderSecurityTab() {
        return `
            <div class="deploy-grid deploy-grid-2">
                <div class="deploy-card">
                    <div class="card-header"><h3>🔑 Deployment Token</h3></div>
                    <div class="form-group">
                        <label>Local HMAC Signing Token</label>
                        <div class="token-row">
                            <input type="password" id="sec-token" class="command-input" readonly value="••••••••••••••••" />
                            <button class="deploy-btn deploy-btn-sm deploy-btn-ghost" id="btn-toggle-token">👁️</button>
                            <button class="deploy-btn deploy-btn-sm deploy-btn-danger" id="btn-rotate-token">🔄 Rotate</button>
                        </div>
                        <small class="muted">This token authenticates all CLI and dashboard requests to the target server.</small>
                    </div>
                </div>

                <div class="deploy-card">
                    <div class="card-header"><h3>🛡️ Connection Test</h3></div>
                    <div id="conn-test-result">
                        <div class="empty-state" style="padding:1rem;"><p>Click below to test your connection to the target server.</p></div>
                    </div>
                    <button class="deploy-btn deploy-btn-primary" id="btn-conn-test" style="margin-top:1rem;">
                        📡 Test Connection
                    </button>
                </div>
            </div>

            <div class="deploy-card" style="margin-top: 1.5rem;">
                <div class="card-header"><h3>⚙️ Environment Configuration</h3></div>
                <div class="env-config-actions">
                    <button class="deploy-btn deploy-btn-primary" id="btn-edit-env-config">✏️ Edit Target Configuration</button>
                </div>
            </div>
        `;
    }

    // ─── EVENT BINDING ───────────────────────────────────────────
    bindGlobalEvents(container) {
        // Tab switching
        container.querySelectorAll('.deploy-tab').forEach(tab => {
            tab.addEventListener('click', async (e) => {
                const newTab = e.currentTarget.dataset.tab;
                if (newTab === this.activeTab) return;

                // Stop log tailing when leaving console tab
                if (this.activeTab === 'console' && this.logTailTimer) {
                    clearInterval(this.logTailTimer);
                    this.logTailTimer = null;
                    this.logTailing = false;
                }

                this.activeTab = newTab;
                container.querySelectorAll('.deploy-tab').forEach(t => t.classList.remove('active'));
                e.currentTarget.classList.add('active');
                this.positionTabIndicator();

                const content = document.getElementById('deploy-tab-content');
                content.style.opacity = '0';
                content.style.transform = 'translateY(8px)';
                setTimeout(async () => {
                    content.innerHTML = this.renderActiveTab();
                    this.bindTabEvents();
                    content.style.opacity = '1';
                    content.style.transform = 'translateY(0)';
                    await this.onTabActivated(newTab);
                }, 150);
            });
        });

        // Target env selector
        const envSelect = container.querySelector('#deploy-target-env');
        if (envSelect) {
            envSelect.addEventListener('change', (e) => {
                this.target = e.target.value;
            });
        }

        // Health ping
        const healthBtn = container.querySelector('#btn-health-ping');
        if (healthBtn) {
            healthBtn.addEventListener('click', () => this.pingHealth());
        }

        this.bindTabEvents();
    }

    bindTabEvents() {
        const c = this.container;

        // Overview tab
        c.querySelector('#btn-quick-deploy')?.addEventListener('click', () => { this.activeTab = 'deploy'; this.update(); });
        c.querySelector('#btn-quick-backup')?.addEventListener('click', () => this.createBackup());
        c.querySelector('#btn-quick-maintenance')?.addEventListener('click', () => this.toggleMaintenance());
        c.querySelector('#btn-quick-logs')?.addEventListener('click', () => { this.activeTab = 'console'; this.update(); });

        // Deploy tab
        c.querySelector('#btn-check-sync')?.addEventListener('click', () => this.checkStatus());
        c.querySelector('#btn-db-sync')?.addEventListener('click', () => this.sysUpgrade());
        c.querySelector('#btn-deploy-all')?.addEventListener('click', () => this.syncAll());

        // Rollback tab
        c.querySelector('#btn-create-backup')?.addEventListener('click', () => this.createBackup());

        // Console tab
        c.querySelector('#btn-log-refresh')?.addEventListener('click', () => this.fetchLogs(false));
        c.querySelector('#btn-log-tail')?.addEventListener('click', () => this.toggleLogTail());
        c.querySelector('#btn-run-cmd')?.addEventListener('click', () => this.runRemoteCommand());
        c.querySelector('#remote-cmd-input')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') this.runRemoteCommand();
        });

        // Cluster tab
        c.querySelector('#btn-refresh-cluster')?.addEventListener('click', () => this.loadClusterStatus());
        c.querySelector('#btn-add-webhook')?.addEventListener('click', () => this.addWebhook());
        c.querySelector('#btn-test-webhook')?.addEventListener('click', () => this.testWebhook());

        // Security tab
        c.querySelector('#btn-toggle-token')?.addEventListener('click', () => this.toggleTokenVisibility());
        c.querySelector('#btn-rotate-token')?.addEventListener('click', () => this.rotateToken());
        c.querySelector('#btn-conn-test')?.addEventListener('click', () => this.testConnection());
        c.querySelector('#btn-edit-env-config')?.addEventListener('click', () => this.openEnvConfig());
    }

    async onTabActivated(tab) {
        switch (tab) {
            case 'overview':  await this.loadOverviewData(); break;
            case 'rollback':  await this.loadBackups(); break;
            case 'cluster':
                await this.loadClusterStatus();
                await this.loadWebhooks();
                break;
            case 'security':  await this.fetchSecurity(); break;
        }
    }

    positionTabIndicator() {
        const activeTab = this.container.querySelector('.deploy-tab.active');
        const indicator = this.container.querySelector('#tab-indicator');
        if (activeTab && indicator) {
            const tabsNav = this.container.querySelector('.deploy-tabs');
            const navRect = tabsNav.getBoundingClientRect();
            const tabRect = activeTab.getBoundingClientRect();
            indicator.style.left = (tabRect.left - navRect.left) + 'px';
            indicator.style.width = tabRect.width + 'px';
        }
    }

    // ─── EXISTING ACTIONS (enhanced) ─────────────────────────────
    async checkStatus() {
        this.admin.showLoading(true);
        try {
            const res = await this.admin.api('lifecycle_compare', { target: this.target });
            if (res.success) {
                this.deltas = res.data.deltas;
                this.renderDeltas();
            } else {
                this.admin.notify("Comparison failed: " + res.message, 'error');
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.admin.showLoading(false);
        }
    }

    renderDeltas() {
        const card = document.getElementById('delta-deploy-card');
        const body = document.getElementById('delta-body');
        const diffContainer = document.getElementById('deploy-diff-container');
        if (!card || !body) return;

        card.style.display = 'block';
        body.innerHTML = '';
        let total = 0;

        if (this.deltas?.files?.upload) {
            this.deltas.files.upload.forEach(f => {
                total++;
                body.innerHTML += `
                    <tr>
                        <td><span class="badge badge-primary">CODE</span></td>
                        <td><code>${f.path}</code></td>
                        <td>Upload to Remote</td>
                        <td><button class="deploy-btn deploy-btn-sm deploy-btn-primary btn-sync-single" data-type="file" data-path="${f.path}">Push</button></td>
                    </tr>
                `;
            });
        }

        if (this.deltas?.xdb?.push) {
            this.deltas.xdb.push.forEach(x => {
                total++;
                body.innerHTML += `
                    <tr>
                        <td><span class="badge badge-warning">DATA</span></td>
                        <td>Collection: <strong>${x.collection}</strong> (${x.db})</td>
                        <td>Merge with Remote</td>
                        <td><button class="deploy-btn deploy-btn-sm deploy-btn-warning btn-sync-single" data-type="xdb" data-db="${x.db}" data-coll="${x.collection}">Push</button></td>
                    </tr>
                `;
            });
        }

        document.getElementById('delta-count').textContent = total;

        if (total === 0 && diffContainer) {
            card.style.display = 'none';
            diffContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon" style="font-size:3rem;">✅</div>
                    <p><strong>All systems synchronized!</strong><br/>No pending changes detected.</p>
                </div>
            `;
        }

        body.querySelectorAll('.btn-sync-single').forEach(btn => {
            btn.addEventListener('click', (e) => this.syncSingle(e.currentTarget));
        });
    }

    async syncSingle(btn) {
        btn.disabled = true;
        btn.innerHTML = 'Syncing...';
        try {
            const res = await this.admin.api('lifecycle_push', {
                target: this.target,
                type: btn.dataset.type,
                path: btn.dataset.path,
                db: btn.dataset.db,
                coll: btn.dataset.coll
            });
            if (res.success) {
                btn.innerHTML = '✅ Done';
                btn.classList.remove('deploy-btn-primary', 'deploy-btn-warning');
                btn.classList.add('deploy-btn-success');
            } else {
                btn.innerHTML = '❌ Failed';
            }
        } catch (err) {
            btn.innerHTML = '❌ Error';
        }
    }

    async syncAll() {
        if (!confirm("Are you sure you want to deploy all pending changes to the target?")) return;
        const buttons = document.querySelectorAll('.btn-sync-single');
        for (const btn of buttons) {
            if (!btn.innerHTML.includes('✅')) await this.syncSingle(btn);
        }
        this.admin.notify("Deployment complete!", "success");
    }

    async createBackup() {
        this.admin.showLoading(true);
        try {
            const res = await this.admin.api('lifecycle_backup');
            if (res.success) {
                this.admin.notify("Backup created: " + (res.data?.filename || 'success'), "success");
            }
        } catch (err) { console.error(err); }
        finally { this.admin.showLoading(false); }
    }

    async sysUpgrade() {
        if (!confirm("Synchronize the database schema from all active modules?")) return;
        this.admin.showLoading(true);
        try {
            const res = await this.admin.api('sys_upgrade');
            if (res.success) this.admin.notify("Schema synchronized!", "success");
        } catch (err) { console.error(err); }
        finally { this.admin.showLoading(false); }
    }

    async toggleMaintenance() {
        const enable = confirm("Enable maintenance mode on the target? (Cancel = Disable)");
        try {
            await this.admin.api('lifecycle_maintenance_toggle', { target: this.target, enable: enable });
        } catch (e) { console.error(e); }
    }

    async fetchSecurity() {
        try {
            const res = await this.admin.api('lifecycle_get_security');
            if (res.success) {
                this.localToken = res.data.token;
                const input = document.getElementById('sec-token');
                if (input) input.value = this.localToken;
            }
        } catch (err) { console.error(err); }
    }

    toggleTokenVisibility() {
        const input = document.getElementById('sec-token');
        const btn = document.getElementById('btn-toggle-token');
        if (!input) return;
        if (input.type === 'password') { input.type = 'text'; btn.innerHTML = '🙈'; }
        else { input.type = 'password'; btn.innerHTML = '👁️'; }
    }

    async rotateToken() {
        if (!confirm("Rotate the deployment token? All existing connections using the old token will fail.")) return;
        try {
            const res = await this.admin.api('lifecycle_rotate_token');
            if (res.success) {
                this.localToken = res.data.token;
                const input = document.getElementById('sec-token');
                if (input) input.value = this.localToken;
                this.admin.notify("Token rotated successfully.", "success");
            }
        } catch (err) { console.error(err); }
    }

    async pingHealth() {
        const dot = document.getElementById('health-dot');
        if (dot) dot.className = 'pulse-dot dot-amber';
        try {
            const res = await this.admin.api('lifecycle_health_check', { target: this.target });
            if (res.success && res.data) {
                if (dot) dot.className = `pulse-dot ${res.data.reachable ? 'dot-green' : 'dot-red'}`;
                this.admin.notify(`Target: ${res.data.reachable ? 'Online' : 'Offline'} (${res.data.latency_ms}ms)`, res.data.reachable ? 'success' : 'error');
            }
        } catch (e) {
            if (dot) dot.className = 'pulse-dot dot-red';
        }
    }

    async testConnection() {
        const container = document.getElementById('conn-test-result');
        if (container) container.innerHTML = '<div class="empty-state" style="padding:1rem;"><p>Testing connection...</p></div>';
        try {
            const res = await this.admin.api('lifecycle_health_check', { target: this.target });
            if (container && res.success && res.data) {
                const d = res.data;
                container.innerHTML = `
                    <div class="conn-result ${d.reachable ? 'conn-ok' : 'conn-fail'}">
                        <div class="conn-status">${d.reachable ? '✅ Connected' : '❌ Unreachable'}</div>
                        <div class="conn-details">
                            <span>HTTP ${d.http_code}</span>
                            <span>Latency: ${d.latency_ms}ms</span>
                            ${d.health ? `<span>PHP: ${d.health.php_version || '?'}</span>` : ''}
                            ${d.health ? `<span>Disk: ${d.health.disk_free_human || '?'}</span>` : ''}
                        </div>
                    </div>
                `;
            }
        } catch (e) {
            if (container) container.innerHTML = '<div class="conn-result conn-fail"><div class="conn-status">❌ Connection Error</div></div>';
        }
    }

    async addWebhook() {
        const input = document.getElementById('webhook-url-input');
        if (!input || !input.value.trim()) return;
        const url = input.value.trim();
        input.value = '';

        try {
            // Get existing webhooks, add new one, save
            const res = await this.admin.api('lifecycle_get_webhooks');
            const webhooks = res.data?.webhooks || [];
            webhooks.push(url);
            await this.admin.api('lifecycle_save_webhooks', { webhooks });
            this.admin.notify('Webhook added!', 'success');
            await this.loadWebhooks();
        } catch (e) { console.error(e); }
    }

    async testWebhook() {
        const input = document.getElementById('webhook-url-input');
        const url = input?.value?.trim();
        if (!url) return this.admin.notify('Enter a webhook URL first.', 'warning');
        try {
            await this.admin.api('lifecycle_test_webhook', { url });
        } catch (e) { console.error(e); }
    }

    openEnvConfig() {
        const env = this.environments[this.target] || { url: '', token: '', exclude: [] };
        const content = `
            <div class="form-group">
                <label>Environment Name</label>
                <input type="text" id="env-name" class="form-control" value="${this.target}">
            </div>
            <div class="form-group">
                <label>Remote Server Root</label>
                <input type="text" id="env-url" class="form-control" value="${env.url || ''}" placeholder="e.g. http://production-server.local/">
            </div>
            <div class="form-group">
                <label>Deployment Token</label>
                <input type="password" id="env-token" class="form-control" value="${env.token || ''}">
            </div>
            <div class="form-group">
                <label>Exclusion Rules (comma separated)</label>
                <textarea id="env-exclude" class="form-control">${(env.exclude || []).join(', ')}</textarea>
                <small class="muted">e.g. etc/db-config.yml, var/logs/</small>
            </div>
        `;

        this.admin.openModal("Configure Environment", content, [
            { label: 'Cancel', type: 'secondary', fn: (m) => m.close() },
            { label: 'Save Changes', type: 'primary', fn: async () => {
                const data = {
                    name: document.getElementById('env-name').value,
                    url: document.getElementById('env-url').value,
                    token: document.getElementById('env-token').value,
                    exclude: document.getElementById('env-exclude').value,
                    description: "Updated from deployment dashboard"
                };
                const res = await this.admin.api('lifecycle_save_env', data);
                if (res.success) {
                    this.target = data.name;
                    await this.loadEnvironments();
                    this.update();
                    this.admin.closeModal();
                }
            }}
        ]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────
    timeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        const days = Math.floor(hours / 24);
        return days + 'd ago';
    }

    formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    // ─── STYLES ──────────────────────────────────────────────────
    getStyles() {
        return `
            .deploy-dashboard {
                padding: 0;
                max-width: 100%;
            }

            /* Header */
            .deploy-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem 0;
                border-bottom: 1px solid var(--glass-border);
                margin-bottom: 1.5rem;
            }
            .deploy-header-left { display: flex; align-items: center; gap: 1rem; }
            .deploy-header-right { display: flex; align-items: center; gap: 1rem; }
            .deploy-logo { display: flex; align-items: center; gap: 1rem; }
            .deploy-logo-icon {
                font-size: 2.2rem;
                width: 52px; height: 52px;
                display: flex; align-items: center; justify-content: center;
                background: linear-gradient(135deg, var(--primary), var(--accent));
                border-radius: var(--radius-lg);
                box-shadow: 0 4px 20px var(--primary-glow);
            }
            .deploy-title {
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--text-bright);
                margin: 0;
                letter-spacing: -0.02em;
            }
            .deploy-subtitle {
                font-size: 0.75rem;
                color: var(--text-dim);
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .deploy-env-select {
                display: flex; align-items: center; gap: 0.5rem;
            }
            .deploy-env-select label {
                font-size: 0.75rem;
                color: var(--text-dim);
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .deploy-env-select select {
                background: var(--input-bg);
                border: 1px solid var(--glass-border);
                border-radius: var(--radius-sm);
                color: var(--text-main);
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                font-family: 'Outfit', sans-serif;
            }

            /* Pulse dot */
            .pulse-dot {
                display: inline-block;
                width: 8px; height: 8px;
                border-radius: 50%;
                background: var(--text-dim);
                margin-right: 4px;
                vertical-align: middle;
            }
            .dot-green { background: var(--success); box-shadow: 0 0 8px var(--success); animation: pulse-glow 2s infinite; }
            .dot-red { background: var(--danger); box-shadow: 0 0 8px var(--danger); }
            .dot-amber { background: var(--warning); box-shadow: 0 0 8px var(--warning); animation: pulse-glow 1s infinite; }
            @keyframes pulse-glow {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }

            /* Tabs */
            .deploy-tabs {
                display: flex;
                gap: 0;
                border-bottom: 1px solid var(--glass-border);
                position: relative;
                margin-bottom: 1.5rem;
                overflow-x: auto;
            }
            .deploy-tab {
                background: none;
                border: none;
                color: var(--text-dim);
                padding: 0.75rem 1.25rem;
                font-size: 0.85rem;
                font-family: 'Outfit', sans-serif;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                transition: color 0.2s ease;
                white-space: nowrap;
            }
            .deploy-tab:hover { color: var(--text-main); }
            .deploy-tab.active { color: var(--primary); font-weight: 600; }
            .tab-indicator {
                position: absolute;
                bottom: -1px;
                height: 2px;
                background: var(--primary);
                border-radius: 2px;
                transition: left 0.3s var(--spring), width 0.3s var(--spring);
            }
            .tab-icon { font-size: 1rem; }

            /* Tab content transition */
            .deploy-tab-content {
                transition: opacity 0.15s ease, transform 0.15s ease;
            }

            /* Grid layouts */
            .deploy-grid { display: grid; gap: 1.25rem; }
            .deploy-grid-4 { grid-template-columns: repeat(4, 1fr); }
            .deploy-grid-2 { grid-template-columns: repeat(2, 1fr); }
            @media (max-width: 900px) {
                .deploy-grid-4 { grid-template-columns: repeat(2, 1fr); }
                .deploy-grid-2 { grid-template-columns: 1fr; }
            }

            /* Stat cards */
            .stat-card {
                background: var(--card-bg);
                border: 1px solid var(--glass-border);
                border-radius: var(--radius-lg);
                padding: 1.5rem;
                text-align: center;
                transition: border-color 0.2s ease, transform 0.2s ease;
            }
            .stat-card:hover {
                border-color: var(--glass-border-hover);
                transform: translateY(-2px);
            }
            .stat-icon { font-size: 1.8rem; margin-bottom: 0.5rem; }
            .stat-value {
                font-size: 1.8rem;
                font-weight: 700;
                color: var(--text-bright);
                font-family: 'JetBrains Mono', monospace;
            }
            .stat-label {
                font-size: 0.75rem;
                color: var(--text-dim);
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-top: 0.25rem;
            }

            /* Cards */
            .deploy-card {
                background: var(--card-bg);
                border: 1px solid var(--glass-border);
                border-radius: var(--radius-lg);
                padding: 1.5rem;
            }
            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.25rem;
            }
            .card-header h3 {
                font-size: 1rem;
                font-weight: 600;
                color: var(--text-bright);
                margin: 0;
            }
            .card-actions { display: flex; gap: 0.5rem; }
            .card-footer {
                margin-top: 1.25rem;
                padding-top: 1rem;
                border-top: 1px solid var(--glass-border);
                text-align: right;
            }

            /* Buttons */
            .deploy-btn {
                border: 1px solid var(--glass-border);
                border-radius: var(--radius-sm);
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
                font-family: 'Outfit', sans-serif;
                cursor: pointer;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                background: var(--btn-soft-bg);
                color: var(--text-main);
            }
            .deploy-btn:hover { background: var(--btn-soft-hover); border-color: var(--glass-border-hover); }
            .deploy-btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
            .deploy-btn-primary:hover { background: var(--primary-hover); }
            .deploy-btn-success { background: var(--success); color: #fff; border-color: var(--success); }
            .deploy-btn-warning { background: var(--warning); color: #1a1a2e; border-color: var(--warning); }
            .deploy-btn-danger { background: var(--danger); color: #fff; border-color: var(--danger); }
            .deploy-btn-info { background: var(--info); color: #fff; border-color: var(--info); }
            .deploy-btn-ghost { background: transparent; border-color: transparent; }
            .deploy-btn-ghost:hover { background: var(--btn-soft-bg); }
            .deploy-btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }
            .deploy-btn-lg { padding: 0.7rem 1.5rem; font-size: 0.9rem; }
            .deploy-btn:disabled { opacity: 0.5; cursor: not-allowed; }

            /* Quick actions */
            .quick-actions-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            .action-btn {
                border: 1px solid var(--glass-border);
                border-radius: var(--radius-md);
                padding: 1rem;
                cursor: pointer;
                text-align: center;
                background: var(--card-bg);
                color: var(--text-main);
                font-family: 'Outfit', sans-serif;
                font-size: 0.85rem;
                transition: all 0.2s ease;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
            }
            .action-btn:hover { transform: translateY(-2px); border-color: var(--glass-border-hover); }
            .action-btn-primary:hover { border-color: var(--primary); box-shadow: 0 4px 15px var(--primary-glow); }
            .action-btn-warning:hover { border-color: var(--warning); box-shadow: 0 4px 15px rgba(251, 191, 36, 0.2); }
            .action-btn-danger:hover { border-color: var(--danger); box-shadow: 0 4px 15px rgba(248, 113, 113, 0.2); }
            .action-btn-info:hover { border-color: var(--info); box-shadow: 0 4px 15px rgba(56, 189, 248, 0.2); }
            .action-icon { font-size: 1.5rem; }

            /* Timeline */
            .timeline { position: relative; padding-left: 1.5rem; }
            .timeline-item {
                position: relative;
                padding: 0.75rem 0;
                border-left: 2px solid var(--glass-border);
                padding-left: 1.25rem;
                margin-left: 0;
            }
            .timeline-dot {
                position: absolute;
                left: -6px;
                top: 1rem;
                width: 10px; height: 10px;
                background: var(--primary);
                border-radius: 50%;
                border: 2px solid var(--panel-bg-solid);
            }
            .timeline-title {
                font-size: 0.85rem;
                color: var(--text-main);
                font-family: 'JetBrains Mono', monospace;
            }
            .timeline-meta {
                font-size: 0.72rem;
                color: var(--text-dim);
                margin-top: 0.2rem;
            }
            .timeline-empty {
                text-align: center;
                padding: 2rem;
                color: var(--text-dim);
                font-size: 0.85rem;
            }

            /* Tables */
            .deploy-table {
                width: 100%;
                border-collapse: collapse;
            }
            .deploy-table th {
                text-align: left;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-dim);
                padding: 0.75rem;
                border-bottom: 1px solid var(--glass-border);
            }
            .deploy-table td {
                padding: 0.75rem;
                font-size: 0.85rem;
                color: var(--text-main);
                border-bottom: 1px solid var(--glass-border);
            }
            .deploy-table tr:hover td { background: var(--glass-accent); }
            .delta-table-wrapper { max-height: 400px; overflow-y: auto; }

            /* Badges */
            .badge {
                display: inline-block;
                padding: 0.15rem 0.5rem;
                font-size: 0.7rem;
                font-weight: 600;
                border-radius: 4px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .badge-primary { background: var(--primary-subtle); color: var(--primary); }
            .badge-warning { background: var(--warning-bg); color: var(--warning); }
            .badge-success { background: var(--success-bg); color: var(--success); }
            .badge-danger { background: var(--danger-bg); color: var(--danger); }

            /* Terminal */
            .terminal {
                background: #0a0a0f;
                border: 1px solid var(--glass-border);
                border-radius: var(--radius-md);
                padding: 1rem;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.78rem;
                color: #a3e635;
                max-height: 350px;
                overflow-y: auto;
                line-height: 1.6;
            }
            .terminal pre {
                margin: 0;
                white-space: pre-wrap;
                word-break: break-all;
            }
            .cmd-prompt { color: #6366f1; font-weight: 600; }
            .cmd-exit { color: var(--text-dim); font-style: italic; }

            /* Command input */
            .command-input-row {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            .command-prompt {
                font-family: 'JetBrains Mono', monospace;
                font-size: 1rem;
                color: var(--primary);
                font-weight: 700;
            }
            .command-input {
                flex: 1;
                background: var(--input-bg);
                border: 1px solid var(--glass-border);
                border-radius: var(--radius-sm);
                color: var(--text-main);
                padding: 0.5rem 0.8rem;
                font-size: 0.85rem;
                font-family: 'JetBrains Mono', monospace;
            }
            .command-input:focus {
                border-color: var(--primary);
                outline: none;
                box-shadow: 0 0 0 2px var(--primary-glow);
            }

            /* Token row */
            .token-row { display: flex; gap: 0.5rem; align-items: center; }
            .form-group { margin-bottom: 1rem; }
            .form-group label {
                display: block;
                font-size: 0.75rem;
                color: var(--text-dim);
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 0.4rem;
            }
            .muted { color: var(--text-dim); font-size: 0.75rem; }

            /* Connection test result */
            .conn-result {
                padding: 1rem;
                border-radius: var(--radius-md);
                border: 1px solid var(--glass-border);
            }
            .conn-ok { border-color: var(--success); background: var(--success-bg); }
            .conn-fail { border-color: var(--danger); background: var(--danger-bg); }
            .conn-status { font-weight: 600; font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-bright); }
            .conn-details { display: flex; gap: 1.5rem; font-size: 0.8rem; color: var(--text-secondary); }

            /* Cluster nodes */
            .cluster-group { margin-bottom: 1rem; }
            .cluster-group h4 {
                font-size: 0.85rem;
                color: var(--text-secondary);
                margin-bottom: 0.5rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .cluster-node {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.6rem 0;
                border-bottom: 1px solid var(--glass-border);
            }
            .node-name { font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--text-main); flex: 1; }
            .node-latency { font-size: 0.8rem; color: var(--text-dim); font-family: 'JetBrains Mono', monospace; }

            /* Webhooks */
            .webhook-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.6rem 0;
                border-bottom: 1px solid var(--glass-border);
            }
            .webhook-platform { font-size: 0.8rem; min-width: 80px; }
            .webhook-url { font-size: 0.75rem; color: var(--text-dim); flex: 1; }
            .webhook-add-row { display: flex; gap: 0.5rem; align-items: center; }

            /* Empty state */
            .empty-state {
                text-align: center;
                padding: 2.5rem 1rem;
                color: var(--text-dim);
            }
            .empty-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
            .empty-state p { font-size: 0.85rem; line-height: 1.6; }

            /* Console card height */
            .console-card .terminal { min-height: 250px; }

            .env-config-actions { display: flex; gap: 0.5rem; }
        `;
    }
}
