/**
 * LifecycleView - Environment Sync & Deployment Workbench
 */
export default class LifecycleView extends BaseComponent {
    constructor(app, container, props = {}) {
        super(app, container, props);
        this.state = {
            target: 'production',
            environments: {},
            manifests: null,
            deltas: null,
            loading: false
        };
    }

    async onInit() {
        console.log("LifecycleView Initialized");
        await this.loadEnvironments();
    }

    async loadEnvironments() {
        try {
            const res = await this.admin.api('lifecycle_get_envs');
            if (res.success) {
                this.environments = res.data.environments;
            }
        } catch (err) {
            console.error("Failed to load environments:", err);
        }
    }

    async update() {
        await this.render(this.container);
    }

    async render(container) {
        let envOptions = '';
        Object.keys(this.environments).forEach(name => {
            const selected = name === this.target ? 'selected' : '';
            envOptions += `<option value="${name}" ${selected}>${name}</option>`;
        });

        container.innerHTML = `
            <div class="view-header">
                <div class="view-title">
                    <span class="icon">🚀</span>
                    <h1>Lifecycle & Deployment</h1>
                </div>
                <div class="view-actions">
                    <button class="btn btn-secondary" id="btn-backup">
                        <span class="icon">📦</span> Local Backup
                    </button>
                    <button class="btn btn-primary" id="btn-compare">
                        <span class="icon">🔍</span> Check Sync Status
                    </button>
                </div>
            </div>

            <div class="lifecycle-grid">
                <div class="card env-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>Environment Target</h3>
                        <button class="btn btn-sm ghost-btn" id="btn-config-env" title="Configure Target">⚙️</button>
                    </div>
                    <div class="form-group">
                        <label>Target Server</label>
                        <select id="target-env" class="form-control">
                            ${envOptions}
                        </select>
                    </div>
                    <div id="env-status" class="status-indicator">
                        Status: <span class="badge badge-neutral">Unknown</span>
                    </div>
                </div>

                <div class="card security-card">
                    <h3>Security & Authorization</h3>
                    <div class="form-group">
                        <label>Local Deployment Token</label>
                        <div class="input-group-with-btn" style="display: flex; gap: 8px;">
                            <input type="password" id="local-token" class="form-control" readonly value="••••••••••••••••">
                            <button class="btn btn-sm btn-secondary" id="btn-show-token" title="Show/Hide">👁️</button>
                            <button class="btn btn-sm btn-outline-danger" id="btn-rotate-token" title="Rotate Token">🔄</button>
                        </div>
                        <small class="muted">This token is required by remote servers to authorize pushes from this machine.</small>
                    </div>
                </div>

                <div class="card remote-config-card">
                    <h3>Remote Config Management</h3>
                    <div id="remote-config-status">
                        <button class="btn btn-sm btn-outline-primary" id="btn-fetch-remote-config">
                            Fetch Production Settings
                        </button>
                    </div>
                    <div id="remote-config-list" style="margin-top: 15px; display: none;">
                        <div class="muted" style="font-size: 0.8rem; margin-bottom: 5px;">Manage production settings without leaving local workbench.</div>
                        <div class="remote-config-table-wrapper" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm" id="remote-config-table">
                                <tbody id="remote-config-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card delta-card" id="delta-container" style="display: none;">
                <h3>Pending Changes</h3>
                <div class="delta-list-wrapper">
                    <table class="table" id="delta-table">
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
                <div class="view-footer">
                    <button class="btn btn-success" id="btn-sync-all">
                        Deploy All Changes
                    </button>
                </div>
            </div>
        `;

        this.bindEvents(container);
    }

    bindEvents(container) {
        container.querySelector('#btn-compare').addEventListener('click', () => this.checkStatus());
        container.querySelector('#btn-backup').addEventListener('click', () => this.createBackup());
        container.querySelector('#btn-sync-all').addEventListener('click', () => this.syncAll());
        container.querySelector('#btn-fetch-remote-config').addEventListener('click', () => this.fetchRemoteConfig());
        container.querySelector('#btn-config-env').addEventListener('click', () => this.openEnvConfig());
        
        container.querySelector('#target-env').addEventListener('change', (e) => {
            this.target = e.target.value;
            console.log("Sync Target Switched to:", this.target);
        });

        container.querySelector('#btn-show-token').addEventListener('click', () => this.toggleTokenVisibility());
        container.querySelector('#btn-rotate-token').addEventListener('click', () => this.rotateToken());
        
        // Initial fetch
        this.fetchSecurity();
    }

    async fetchSecurity() {
        try {
            const res = await this.admin.api('lifecycle_get_security');
            if (res.success) {
                this.localToken = res.data.token;
                document.getElementById('local-token').value = this.localToken;
            }
        } catch (err) {
            console.error(err);
        }
    }

    toggleTokenVisibility() {
        const input = document.getElementById('local-token');
        const btn = document.getElementById('btn-show-token');
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '🙈';
        } else {
            input.type = 'password';
            btn.innerHTML = '👁️';
        }
    }

    async rotateToken() {
        if (!confirm("Are you sure you want to rotate the deployment token? All existing connections using the old token will fail.")) return;
        
        try {
            const res = await this.admin.api('lifecycle_rotate_token');
            if (res.success) {
                this.localToken = res.data.token;
                document.getElementById('local-token').value = this.localToken;
                alert("Security token rotated successfully.");
            }
        } catch (err) {
            console.error(err);
        }
    }

    async openEnvConfig() {
        const env = this.environments[this.target] || { url: '', token: '', exclude: [] };
        
        const content = SPPUX.html`
            <div class="form-group">
                <label>Environment Name</label>
                <input type="text" id="env-name" class="form-control" value="${this.target}">
            </div>
            <div class="form-group">
                <label>Remote Server Root</label>
                <input type="text" id="env-url" class="form-control" value="${env.url}" placeholder="e.g. http://production-server.local/">
                <small class="muted">The framework will automatically discover the API endpoint.</small>
            </div>
            <div class="form-group">
                <label>Deployment Token</label>
                <input type="password" id="env-token" class="form-control" value="${env.token}">
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
                    description: "Updated from local workbench"
                };

                const res = await this.admin.api('lifecycle_save_env', data);
                if (res.success) {
                    this.target = data.name;
                    await this.loadEnvironments();
                    this.update();
                    this.admin.closeModal();
                } else {
                    alert(res.message);
                }
            }}
        ]);
    }

    async fetchRemoteConfig() {
        this.admin.showLoading(true);
        try {
            const res = await this.admin.api('lifecycle_get_remote_config', { target: this.target });
            if (res.success) {
                this.renderRemoteConfig(res.data.config);
            } else {
                alert("Failed to fetch remote config: " + res.message);
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.admin.showLoading(false);
        }
    }

    renderRemoteConfig(config) {
        const list = document.getElementById('remote-config-list');
        const body = document.getElementById('remote-config-body');
        list.style.display = 'block';
        body.innerHTML = '';

        // Focus on global settings
        const global = config.global || {};
        Object.entries(global).forEach(([key, val]) => {
            if (typeof val === 'object') return; // Skip complex structs for simple list
            body.innerHTML += `
                <tr>
                    <td><code>${key}</code></td>
                    <td><input type="text" class="form-control form-control-sm remote-config-input" data-key="global:${key}" value="${val}"></td>
                    <td><button class="btn btn-sm btn-primary btn-save-remote-config" data-key="global:${key}">💾</button></td>
                </tr>
            `;
        });

        body.querySelectorAll('.btn-save-remote-config').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const key = e.currentTarget.dataset.key;
                const input = body.querySelector(`input[data-key="${key}"]`);
                this.saveRemoteConfig(key, input.value, e.currentTarget);
            });
        });
    }

    async saveRemoteConfig(key, value, btn) {
        btn.disabled = true;
        try {
            const res = await this.admin.api('lifecycle_save_remote_config', {
                target: this.target,
                key: key,
                value: value
            });
            if (res.success) {
                btn.innerHTML = '✅';
                setTimeout(() => btn.innerHTML = '💾', 2000);
            } else {
                alert("Save failed: " + res.message);
            }
        } catch (err) {
            console.error(err);
        } finally {
            btn.disabled = false;
        }
    }

    async checkStatus() {
        this.admin.showLoading(true);
        try {
            const res = await this.admin.api('lifecycle_compare', { target: this.target });
            if (res.success) {
                this.deltas = res.data.deltas;
                this.renderDeltas();
            } else {
                alert("Comparison failed: " + res.message);
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.admin.showLoading(false);
        }
    }

    renderDeltas() {
        const container = document.getElementById('delta-container');
        const body = document.getElementById('delta-body');
        const stats = document.getElementById('sync-stats');
        
        container.style.display = 'block';
        body.innerHTML = '';

        let total = 0;
        
        // Files to upload
        this.deltas.files.upload.forEach(f => {
            total++;
            body.innerHTML += `
                <tr>
                    <td><span class="badge badge-primary">CODE</span></td>
                    <td><code>${f.path}</code></td>
                    <td>Upload to Remote</td>
                    <td><button class="btn btn-sm btn-outline-primary btn-sync-single" data-type="file" data-path="${f.path}">Push</button></td>
                </tr>
            `;
        });

        // XDB Collections to push
        this.deltas.xdb.push.forEach(x => {
            total++;
            body.innerHTML += `
                <tr>
                    <td><span class="badge badge-warning">DATA</span></td>
                    <td>Collection: <strong>${x.collection}</strong> (${x.db})</td>
                    <td>Merge with Remote</td>
                    <td><button class="btn btn-sm btn-outline-warning btn-sync-single" data-type="xdb" data-db="${x.db}" data-coll="${x.collection}">Push</button></td>
                </tr>
            `;
        });

        stats.innerHTML = `
            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-val">${total}</span>
                    <span class="stat-label">Changes Detected</span>
                </div>
            </div>
        `;

        // Bind single sync buttons
        body.querySelectorAll('.btn-sync-single').forEach(btn => {
            btn.addEventListener('click', (e) => this.syncSingle(e.target));
        });
    }

    async syncSingle(btn) {
        const type = btn.dataset.type;
        const path = btn.dataset.path;
        const db = btn.dataset.db;
        const coll = btn.dataset.coll;

        btn.disabled = true;
        btn.innerHTML = 'Syncing...';

        try {
            const res = await this.admin.api('lifecycle_push', {
                target: this.target,
                type: type,
                path: path,
                db: db,
                coll: coll
            });

            if (res.success) {
                btn.innerHTML = '✅ Done';
                btn.classList.add('btn-success');
            } else {
                btn.innerHTML = '❌ Failed';
                alert(res.message);
            }
        } catch (err) {
            btn.innerHTML = '❌ Error';
        }
    }

    async syncAll() {
        if (!confirm("Are you sure you want to deploy all pending changes to production?")) return;
        
        const buttons = document.querySelectorAll('.btn-sync-single');
        for (const btn of buttons) {
            if (btn.innerHTML !== '✅ Done') {
                await this.syncSingle(btn);
            }
        }
        alert("Sync complete!");
    }

    async createBackup() {
        this.admin.showLoading(true);
        try {
            const res = await this.admin.api('lifecycle_backup');
            if (res.success) {
                alert("Backup created: " + res.data.filename);
            } else {
                alert("Backup failed: " + res.message);
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.admin.showLoading(false);
        }
    }
}
