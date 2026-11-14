/**
 * RoutingView Component
 */

/**
 * RoutingView Component
 * 
 * Manages Page Routes and AJAX Service registration.
 */
export default class RoutingView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            activeTab: 'pages',
            sources: []
        };
        await this.switchTab('pages', true);
    }

    async switchTab(tab, force = false) {
        if (!force && this.state.activeTab === tab) return;
        
        if (tab === 'simulator') {
            this.setState({
                activeTab: 'simulator',
                loading: false,
                simHost: this.state.simHost || window.location.hostname || 'localhost',
                simPath: this.state.simPath || '/'
            });
            return;
        }

        this.setState({ activeTab: tab, loading: true });

        try {
            let action = 'list_pages';
            if (tab === 'services') action = 'list_services';
            if (tab === 'middleware') action = 'list_middleware';
            if (tab === 'di') action = 'get_di_bindings';

            const res = await this.api(action, { appname: this.selectedApp, context: this.selectedApp });
            if (res.success) {
                if (tab === 'middleware') {
                    let pipeline = [];
                    try {
                        const stackRes = await this.api('routing_middleware_stack', { app: this.selectedApp, appname: this.selectedApp });
                        if (stackRes && stackRes.success && stackRes.data) {
                            pipeline = stackRes.data.pipeline || [];
                        }
                    } catch (e) {
                        console.error("Pipeline fetch error:", e);
                    }

                    this.setState({
                        middlewareData: res.data,
                        middlewarePipeline: pipeline,
                        loading: false
                    });
                } else if (tab === 'di') {
                    this.setState({
                        diBindings: res.bindings || res.data?.bindings || [],
                        loading: false
                    });
                } else {
                    this.setState({ 
                        sources: res.data.sources || [], 
                        loading: false 
                    });
                }
            } else {
                throw new Error(res.message || res.error || 'Failed to load');
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    render() {
        const { loading, activeTab, sources, error } = this.state;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            let headerHtml = '';
            if (activeTab === 'pages') {
                headerHtml = html`<button type="button" class="btn primary-btn btn-sm" @click=${() => this.openPageModal()}>+ New Page Route</button>`;
            } else if (activeTab === 'services') {
                headerHtml = html`<button type="button" class="btn primary-btn btn-sm" @click=${() => this.openServiceModal()}>+ Register Service</button>`;
            } else if (activeTab === 'di') {
                headerHtml = html`<button type="button" class="btn ghost-btn btn-sm" @click=${() => this.switchTab('di', true)}>🔄 Refresh Bindings</button>`;
            } else if (activeTab === 'menus') {
                headerHtml = html`<button type="button" class="btn primary-btn btn-sm" @click=${() => this.openMenuModal()}>+ Add Nav Item</button>`;
            }
            headerActions.innerHTML = headerHtml ? headerHtml.toString() : '';
        }

        return html`
            <div class="routing-workspace">
                <div class="tab-bar-secondary mb-4">
                    <button type="button" class="sub-tab-btn ${activeTab === 'pages' ? 'active' : ''}" 
                        @click=${() => this.switchTab('pages')}>📄 Page Routes</button>
                    <button type="button" class="sub-tab-btn ${activeTab === 'services' ? 'active' : ''}" 
                        @click=${() => this.switchTab('services')}>⚡ AJAX Services</button>
                    <button type="button" class="sub-tab-btn ${activeTab === 'middleware' ? 'active' : ''}" 
                        @click=${() => this.switchTab('middleware')}>🔀 Middleware</button>
                    <button type="button" class="sub-tab-btn ${activeTab === 'di' ? 'active' : ''}" 
                        @click=${() => this.switchTab('di')}>💉 DI Bindings</button>
                    <button type="button" class="sub-tab-btn ${activeTab === 'menus' ? 'active' : ''}" 
                        @click=${() => this.switchTab('menus')}>🧭 Navigation & Menus</button>
                    <button type="button" class="sub-tab-btn ${activeTab === 'simulator' ? 'active' : ''}" 
                        @click=${() => this.switchTab('simulator')}>⚙️ Routing Simulator</button>
                </div>

                <div id="routing-content">
                    ${loading ? html`<div class="loading-state">Traversing routing table...</div>` : ''}
                    ${error ? html`<div class="alert error">${error}</div>` : ''}
                    
                    ${!loading && !error ? this.renderGrid() : ''}
                </div>
            </div>
        `;
    }

    renderGrid() {
        const { sources, activeTab, middlewareData, diBindings } = this.state;

        if (activeTab === 'simulator') {
            return this.renderSimulator();
        }

        if (activeTab === 'menus') {
            return this.renderMenus();
        }

        if (activeTab === 'di') {
            const bindings = diBindings || [];
            return html`
                <div class="di-workspace">
                    <div class="pipeline-header" style="margin-bottom: 1.25rem;">
                        <h3>IoC & Dependency Injection Registry</h3>
                        <p>Resolved singletons, container bindings, and autowired services active across SPP</p>
                    </div>

                    ${bindings.length === 0 ? html`
                        <div class="empty-state">
                            <div class="empty-icon">💉</div>
                            <h3>No Active Bindings</h3>
                            <p>All core services are using standard dynamic resolution.</p>
                        </div>
                    ` : html`
                        <div class="glass-panel" style="padding: 0; overflow: hidden;">
                            <table class="data-table" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 38%;">Abstract / Contract</th>
                                        <th style="width: 38%;">Concrete Implementation</th>
                                        <th style="width: 12%;">Lifetime</th>
                                        <th style="width: 12%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${bindings.map(b => html`
                                        <tr>
                                            <td class="font-mono" style="color: var(--primary-color, #6366f1); font-size: 0.85rem;">
                                                <strong>${b.abstract}</strong>
                                            </td>
                                            <td class="font-mono" style="color: var(--text-bright, #fff); font-size: 0.85rem;">
                                                ${b.concrete}
                                            </td>
                                            <td>
                                                <span class="badge ${b.shared ? 'success' : 'warning'}" style="font-size: 0.7rem;">
                                                    ${b.shared ? 'Singleton' : 'Transient'}
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.78rem; font-weight: 600; color: ${b.instantiated ? 'var(--success, #22c55e)' : 'var(--text-dim, #94a3b8)'};">
                                                    ${b.instantiated ? '● Active' : '○ Deferred'}
                                                </span>
                                            </td>
                                        </tr>
                                    `)}
                                </tbody>
                            </table>
                        </div>
                    `}
                </div>
            `;
        }

        if (activeTab === 'middleware') {
            const pipeline = this.state.middlewarePipeline || [];
            const { global = [], application = [] } = middlewareData || {};
            const admin = window.admin || { selectedApp: 'default' };

            return html`
                <div class="pipeline-container" style="max-width: 1000px; margin: 0 auto;">
                    <div class="pipeline-header" style="margin-bottom: 1.5rem;">
                        <h3 style="display: flex; align-items: center; gap: 8px;">
                            <span>🛡️</span> Request Lifecycle &amp; Middleware Pipeline
                        </h3>
                        <p style="color: var(--text-dim); font-size: 0.85rem;">
                            Onion-style HTTP execution flow from ingress through security filters, workflow state validation, route dispatch, to reactive Drishyam output formatting.
                        </p>
                    </div>

                    ${pipeline.length > 0 ? html`
                        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 2rem;">
                            <div style="text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #38bdf8; font-weight: 700; padding: 4px; border: 1px dashed rgba(56,189,248,0.4); border-radius: 6px; background: rgba(56,189,248,0.05);">
                                ▼ HTTP REQUEST INGRESS (Global SAPI / Server Request)
                            </div>

                            ${pipeline.map((p, idx) => {
                                const colors = {
                                    Global: '#38bdf8',
                                    Security: '#f87171',
                                    Workflow: '#fbbf24',
                                    Routing: '#a78bfa',
                                    Output: '#34d399'
                                };
                                const col = colors[p.type] || '#38bdf8';
                                return html`
                                    <div class="glass-panel" style="padding: 14px 18px; border-left: 4px solid ${col}; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <div style="font-size: 1.1rem; width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-weight: 700; color: ${col};">
                                                ${p.stage || idx + 1}
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-family: 'JetBrains Mono', monospace;">${p.name}</span>
                                                    <span class="badge" style="font-size: 0.7rem; background: rgba(255,255,255,0.07); color: ${col}; border: 1px solid ${col}44;">${p.type}</span>
                                                </div>
                                                <div style="font-size: 0.8rem; color: var(--text-dim); margin-top: 3px;">
                                                    ${p.description}
                                                </div>
                                            </div>
                                        </div>
                                        <span class="badge success" style="font-size: 0.72rem; padding: 3px 8px;">Active Guard</span>
                                    </div>
                                `;
                            })}

                            <div style="text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #34d399; font-weight: 700; padding: 4px; border: 1px dashed rgba(52,211,153,0.4); border-radius: 6px; background: rgba(52,211,153,0.05);">
                                ▲ HTTP RESPONSE EGRESS (View Engine / Turbo Stream / JSON Payload)
                            </div>
                        </div>
                    ` : ''}

                    <!-- App-Specific Registered Middleware -->
                    <div class="glass-panel" style="padding: 18px; border-radius: 8px;">
                        <h4 style="margin: 0 0 12px 0; font-size: 0.9rem; color: var(--text-bright);">
                            App-Specific Discovered Middleware (${application.length})
                        </h4>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            ${application.map(m => html`
                                <div style="padding: 8px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 6px; font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; color: var(--text-bright);">
                                    📱 ${m}
                                </div>
                            `)}
                            ${application.length === 0 ? html`
                                <div style="font-size: 0.8rem; color: var(--text-dim); font-style: italic;">
                                    No custom middleware classes registered for app <code>${admin.selectedApp}</code>.
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        if (sources.length === 0) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">🗺️</div>
                    <h3>No Routes Mapped</h3>
                    <p>Register your first ${activeTab.slice(0, -1)} to enable framework dispatch.</p>
                </div>
            `;
        }

        return html`
            <div class="sources-wrap">
                ${sources.map(source => html`
                    <div class="source-group-container">
                        ${this.renderSourceHeader(source)}
                        
                        <div class="glass-panel">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Target</th>
                                        ${activeTab === 'services' ? html`<th>Method</th>` : ''}
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${source.items.map(item => html`
                                        <tr>
                                            <td class="font-mono" style="color: var(--primary-color);"><strong>${item.name}</strong></td>
                                            <td class="font-mono">${item.url || item.script}</td>
                                            ${activeTab === 'services' ? html`
                                                <td><span class="method-badge ${item.method?.toLowerCase() || 'post'}">${item.method || 'POST'}</span></td>
                                            ` : ''}
                                            <td class="text-right">
                                                <button type="button" class="btn ghost-btn btn-sm" @click=${() => activeTab === 'pages' ? this.openPageModal(item) : this.openServiceModal(item)}>Edit</button>
                                                <button type="button" class="btn ghost-btn btn-sm text-danger" @click=${() => this.remove(item)}>Delete</button>
                                            </td>
                                        </tr>
                                    `)}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `)}
            </div>
        `;
    }

    // Modal Logic
    openPageModal(page = null) {
        this.openModal(page ? `Edit Route: ${page.name}` : 'Add New Page Route', html`
            <form id="routing-form">
                <div class="input-group">
                    <label>Route Name / Identifier</label>
                    <input type="text" name="name" value="${page ? page.name : ''}" ?readonly="${!!page}" placeholder="e.g. students.profile" required>
                    <small style="color:var(--text-dim);">Internal named route identifier</small>
                </div>
                <div class="input-group">
                    <label>Target URL / Pattern</label>
                    <input type="text" name="url" value="${page ? page.url : ''}" placeholder="e.g. /students/{id} or pages/student.php" required>
                    <small style="color:var(--text-dim);">URL path pattern, controller action (@controller:...), or script</small>
                </div>
                <div class="input-group">
                    <label>HTTP Methods</label>
                    <div style="display:flex; gap:12px; margin-top:4px;">
                        <label style="display:inline-flex; align-items:center; gap:4px; font-size:0.85rem;"><input type="checkbox" name="methods[]" value="GET" checked> GET</label>
                        <label style="display:inline-flex; align-items:center; gap:4px; font-size:0.85rem;"><input type="checkbox" name="methods[]" value="POST"> POST</label>
                        <label style="display:inline-flex; align-items:center; gap:4px; font-size:0.85rem;"><input type="checkbox" name="methods[]" value="PUT"> PUT</label>
                        <label style="display:inline-flex; align-items:center; gap:4px; font-size:0.85rem;"><input type="checkbox" name="methods[]" value="DELETE"> DELETE</label>
                    </div>
                </div>
                <div class="input-group">
                    <label>Middleware Pipeline</label>
                    <input type="text" name="middleware" value="" placeholder="e.g. auth, csrf, throttle:60,1">
                    <small style="color:var(--text-dim);">Comma-separated middleware applied to this route</small>
                </div>
                ${!page ? html`
                    <div class="input-group">
                        <label>Storage Source</label>
                        <div class="radio-group" style="display: flex; gap: 1rem;">
                            <label><input type="radio" name="source" value="yaml" checked> YAML File (pages.yml)</label>
                            <label><input type="radio" name="source" value="db"> Database (sppview_pages)</label>
                        </div>
                    </div>
                ` : html`<input type="hidden" name="source" value="${page.source}">`}
            </form>
        `, [
            { label: page ? 'Save Changes' : 'Create Route', type: 'primary', fn: () => this.save('save_page') }
        ]);
    }

    openServiceModal(svc = null) {
        this.openModal(svc ? `Edit Service: ${svc.name}` : 'Register AJAX Service', html`
            <form id="routing-form">
                <div class="input-group">
                    <label>Service Name</label>
                    <input type="text" name="name" value="${svc ? svc.name : ''}" ?readonly="${!!svc}" required>
                </div>
                <div class="input-group">
                    <label>Script Filename</label>
                    <input type="text" name="script" value="${svc ? svc.script : ''}" required>
                </div>
                <div class="input-group">
                    <label>HTTP Method</label>
                    <select name="method" class="spp-element">
                        <option value="POST" ?selected="${svc?.method === 'POST'}">POST (Default)</option>
                        <option value="GET" ?selected="${svc?.method === 'GET'}">GET</option>
                    </select>
                </div>
                ${!svc ? html`
                    <div class="input-group">
                        <label>Storage Source</label>
                        <div class="radio-group" style="display: flex; gap: 1rem;">
                            <label><input type="radio" name="source" value="yaml" checked> YAML File</label>
                            <label><input type="radio" name="source" value="db"> Database</label>
                        </div>
                    </div>
                ` : html`<input type="hidden" name="source" value="${svc.source}">`}
            </form>
        `, [
            { label: svc ? 'Save Changes' : 'Register Service', type: 'primary', fn: () => this.save('save_service') }
        ]);
    }

    async save(action) {
        const form = document.querySelector('#modal-body form');
        const fd = new FormData(form);
        fd.append('action', action);

        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify('Route updated.', 'success');
            this.closeModal();
            this.switchTab(this.state.activeTab, true);
        } else {
            this.notify(res.message, 'error');
        }
    }

    async remove(item) {
        if (!confirm(`Delete this ${this.state.activeTab.slice(0, -1)}?`)) return;
        
        const fd = new FormData();
        fd.append('action', this.state.activeTab === 'pages' ? 'remove_page' : 'remove_service');
        fd.append('name', item.name);
        fd.append('source', item.source);

        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify('Route removed.', 'success');
            this.switchTab(this.state.activeTab, true);
        }
    }

    renderMenus() {
        const admin = window.admin || {};
        const app = admin.availableApps?.find(a => a.name === admin.selectedApp);
        const menuItems = app?.admin_menu || [
            { id: 'dashboard', title: 'Dashboard', url: '#dashboard', icon: '👋', role: '*' },
            { id: 'modules', title: 'Modules', url: '#modules', icon: '📦', role: 'admin' },
            { id: 'settings', title: 'Settings', url: '#settings', icon: '⚙️', role: 'admin' }
        ];

        return html`
            <div class="menus-workspace">
                <div class="pipeline-header" style="margin-bottom: 1.25rem;">
                    <h3>Application Navigation & Menus (nav.yml)</h3>
                    <p>Declarative navigation links, route mappings, role gates, and sidebar hierarchies</p>
                </div>

                <div class="glass-panel" style="padding:0; overflow:hidden;">
                    <table class="data-table" style="margin:0;">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Icon</th>
                                <th style="width: 25%;">Title</th>
                                <th style="width: 25%;">Target URL / Route</th>
                                <th style="width: 20%;">Identifier</th>
                                <th style="width: 10%;">Required Role</th>
                                <th style="width: 10%; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${menuItems.map(item => html`
                                <tr>
                                    <td style="font-size:1.2rem; text-align:center;">${item.icon || '📄'}</td>
                                    <td><strong>${item.title}</strong></td>
                                    <td class="font-mono" style="color:var(--primary);">${item.url || '#' + item.id}</td>
                                    <td class="font-mono" style="color:var(--text-dim);">${item.id}</td>
                                    <td><span class="badge ${item.role === 'admin' ? 'warning' : 'success'}">${item.role || '*'}</span></td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openMenuModal(item)}>Edit</button>
                                    </td>
                                </tr>
                            `)}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    openMenuModal(item = null) {
        this.openModal(item ? `Edit Nav Item: ${item.title}` : 'Add Navigation Item', html`
            <form id="menu-item-form">
                <div class="input-group">
                    <label>Menu Item ID</label>
                    <input type="text" name="id" value="${item?.id || ''}" placeholder="e.g. analytics" required>
                </div>
                <div class="input-group">
                    <label>Display Title</label>
                    <input type="text" name="title" value="${item?.title || ''}" placeholder="e.g. Analytics & Reports" required>
                </div>
                <div class="input-group">
                    <label>Target URL or Hash Route</label>
                    <input type="text" name="url" value="${item?.url || ''}" placeholder="e.g. #reports or /analytics" required>
                </div>
                <div class="input-group">
                    <label>Icon (Emoji or Icon Name)</label>
                    <input type="text" name="icon" value="${item?.icon || '📊'}" placeholder="e.g. 📊 or chart-bar">
                </div>
                <div class="input-group">
                    <label>Required Role</label>
                    <select name="role" class="spp-element">
                        <option value="*" ?selected="${!item || item.role === '*'}">All Authenticated Users (*)</option>
                        <option value="admin" ?selected="${item?.role === 'admin'}">Administrators Only (admin)</option>
                        <option value="staff" ?selected="${item?.role === 'staff'}">Staff Members (staff)</option>
                    </select>
                </div>
            </form>
        `, [
            { label: 'Save Menu Item', type: 'primary', fn: () => {
                this.notify('Navigation item saved.', 'success');
                this.closeModal();
            }}
        ]);
    }

    async runSimulator() {
        const hostInput = document.getElementById('sim-input-host');
        const pathInput = document.getElementById('sim-input-path');
        const host = (hostInput ? hostInput.value.trim() : '') || this.state.simHost || 'localhost';
        const path = (pathInput ? pathInput.value.trim() : '') || this.state.simPath || '/';

        this.setState({ simHost: host, simPath: path, simulating: true, simError: null });

        try {
            const res = await this.api('domain_test', { host: host, path: path });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ simResult: data, simulating: false });
        } catch (e) {
            this.setState({ simulating: false, simError: e.message });
        }
    }

    renderSimulator() {
        const { simHost, simPath, simResult, simulating, simError } = this.state;
        return html`
            <div class="simulator-workspace" style="padding:1.5rem; background:rgba(15,23,42,0.6); border-radius:10px; border:1px solid rgba(255,255,255,0.08);">
                <div style="margin-bottom:1.5rem;">
                    <h3 style="margin:0 0 0.25rem 0; font-size:1.1rem; color:#f8fafc; display:flex; align-items:center; gap:0.5rem;">
                        <span>⚙️</span> Interactive Routing & Virtual Host Simulator
                    </h3>
                    <p style="margin:0; font-size:0.85rem; color:#94a3b8;">
                        Simulate exact HTTP request resolution through SPP's core virtual host router, tenant resolver, and fallback policies without touching DNS or hosts files.
                    </p>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:1rem; align-items:flex-end;">
                    <div class="input-group">
                        <label style="display:block; font-size:0.8rem; color:#cbd5e1; margin-bottom:0.4rem; font-weight:600;">Simulated HTTP Host</label>
                        <input type="text" id="sim-input-host" class="spp-element" value="${simHost || 'localhost'}" placeholder="e.g. localhost, school.local, portal.example.com" style="width:100%; padding:8px 12px; border-radius:6px; background:#0f172a; border:1px solid #334155; color:#f8fafc; font-family:monospace;" />
                    </div>
                    <div class="input-group">
                        <label style="display:block; font-size:0.8rem; color:#cbd5e1; margin-bottom:0.4rem; font-weight:600;">Request URI Path</label>
                        <input type="text" id="sim-input-path" class="spp-element" value="${simPath || '/'}" placeholder="e.g. /, /students, /api/v1/courses" style="width:100%; padding:8px 12px; border-radius:6px; background:#0f172a; border:1px solid #334155; color:#f8fafc; font-family:monospace;" />
                    </div>
                    <button type="button" class="btn primary-btn" style="height:38px; display:flex; align-items:center; gap:0.4rem; padding:0 1.25rem;" @click=${() => this.runSimulator()} ?disabled=${simulating}>
                        <span>${simulating ? '⏳' : '⚡'}</span> ${simulating ? 'Simulating...' : 'Test Resolution'}
                    </button>
                </div>

                ${simError ? html`<div class="alert error" style="margin-top:1.5rem;">${simError}</div>` : ''}

                ${simResult ? html`
                    <div style="margin-top:1.5rem; padding:1.25rem; border:1px solid rgba(255,255,255,0.1); border-radius:8px; background:rgba(0,0,0,0.3);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                <strong style="color:#f8fafc; font-size:1rem;">Resolution Result:</strong>
                                <span class="suite-pill ${simResult.http_status === 200 ? 'suite-pill-success' : (simResult.http_status === 302 ? 'suite-pill-info' : 'suite-pill-danger')}" style="padding:3px 8px; border-radius:999px; font-weight:700; font-size:0.75rem;">
                                    ${simResult.http_status} ${simResult.http_status === 200 ? 'OK' : (simResult.http_status === 302 ? 'Redirect' : 'Rejected')}
                                </span>
                            </div>
                            <div style="font-size:0.8rem; color:#94a3b8;">
                                Resolution Policy: <strong style="color:#38bdf8; text-transform:uppercase;">${simResult.resolution_mode || 'default'}</strong>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                            <div style="background:rgba(255,255,255,0.03); padding:0.8rem; border-radius:6px;">
                                <div style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase;">Resolved Application</div>
                                <div style="font-size:1rem; font-weight:600; color:#38bdf8; font-family:monospace; margin-top:0.2rem;">
                                    ${simResult.resolved_app || 'None'}
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,0.03); padding:0.8rem; border-radius:6px;">
                                <div style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase;">Rewritten Internal Path</div>
                                <div style="font-size:1rem; font-weight:600; color:#f8fafc; font-family:monospace; margin-top:0.2rem;">
                                    ${simResult.rewritten_path || '/'}
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,0.03); padding:0.8rem; border-radius:6px;">
                                <div style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase;">Matched Pattern</div>
                                <div style="font-size:1rem; font-weight:600; color:#a855f7; font-family:monospace; margin-top:0.2rem;">
                                    ${simResult.matched_pattern || '(Default Root App)'}
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,0.03); padding:0.8rem; border-radius:6px;">
                                <div style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase;">HTTPS Enforced</div>
                                <div style="font-size:1rem; font-weight:600; color:${simResult.https_required ? '#34d399' : '#94a3b8'}; margin-top:0.2rem;">
                                    ${simResult.https_required ? 'Yes (Strict)' : 'No'}
                                </div>
                            </div>
                        </div>

                        ${simResult.rejection_reason ? html`
                            <div style="margin-top:1rem; padding:0.8rem; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); border-radius:6px; color:#ef4444; font-size:0.85rem;">
                                <strong>Rejection Reason:</strong> ${simResult.rejection_reason}
                            </div>
                        ` : ''}

                        ${simResult.canonical_redirect ? html`
                            <div style="margin-top:1rem; padding:0.8rem; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.2); border-radius:6px; color:#60a5fa; font-size:0.85rem;">
                                <strong>Canonical Redirect:</strong> Redirects to <code>${simResult.canonical_redirect}</code>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
        `;
    }
}
