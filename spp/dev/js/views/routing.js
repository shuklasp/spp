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
        
        this.setState({ activeTab: tab, loading: true });

        try {
            let action = 'list_pages';
            if (tab === 'services') action = 'list_services';
            if (tab === 'middleware') action = 'list_middleware';
            if (tab === 'di') action = 'get_di_bindings';

            const res = await this.api(action, { context: window.admin?.selectedApp });
            if (res.success) {
                if (tab === 'middleware') {
                    this.setState({
                        middlewareData: res.data,
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
            if (!middlewareData) return '';
            const { global, application } = middlewareData;
            const admin = window.admin || { selectedApp: 'default' };

            let globalHtml = global.map(m => `
                <div class="middleware-item glass-panel">
                    <div class="mw-icon">🌐</div>
                    <div class="mw-details">
                        <div class="mw-name">${m}</div>
                        <div class="mw-scope">Global Scope</div>
                    </div>
                </div>
            `).join('');

            let appHtml = application.map(m => `
                <div class="middleware-item glass-panel app-scope">
                    <div class="mw-icon">📱</div>
                    <div class="mw-details">
                        <div class="mw-name">${m}</div>
                        <div class="mw-scope">Application: ${admin.selectedApp}</div>
                    </div>
                </div>
            `).join('');

            return html`
                <div class="pipeline-container">
                    <div class="pipeline-header">
                        <h3>Request Lifecycle</h3>
                        <p>Onion-style middleware execution order (Top to Bottom)</p>
                    </div>
                    <div class="pipeline-visual">
                        <div class="pipeline-flow">
                            <div class="flow-marker start">REQUEST IN</div>
                            ${html([globalHtml || '<div class="empty-mw">No Global Middleware</div>'])}
                            <div class="flow-divider">--- Application Border ---</div>
                            ${html([appHtml || '<div class="empty-mw">No App-Specific Middleware</div>'])}
                            <div class="flow-marker end">APP HANDLER</div>
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
                    <label>Route Name</label>
                    <input type="text" name="name" value="${page ? page.name : ''}" ${page ? 'readonly' : ''} placeholder="e.g. dashboard" required>
                </div>
                <div class="input-group">
                    <label>Target URL</label>
                    <input type="text" name="url" value="${page ? page.url : ''}" placeholder="e.g. /index.php" required>
                </div>
                ${!page ? html`
                    <div class="input-group">
                        <label>Storage Source</label>
                        <div class="radio-group" style="display: flex; gap: 1rem;">
                            <label><input type="radio" name="source" value="yaml" checked> YAML File</label>
                            <label><input type="radio" name="source" value="db"> Database</label>
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
                    <input type="text" name="name" value="${svc ? svc.name : ''}" ${svc ? 'readonly' : ''} required>
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
}
