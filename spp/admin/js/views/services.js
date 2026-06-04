/**
 * Service Registry View
 * Displays registered services and bindings in the DI Container.
 * Includes inlined LiveService & AJAX Registry (formerly ajax.js).
 */

export default class ServicesView extends BaseComponent {
    async onInit() {
        this.state = {
            bindings: [],
            loading: true,
            activeTab: 'di', // di, ajax
            // --- Inlined AjaxView state ---
            ajaxLoading: true,
            services: [],
            showAddModal: false,
            newService: {
                name: '',
                script: '',
                method: 'POST',
                source: 'yaml'
            }
        };
        this.loadServices();
        
        await this.refresh();
    }

    async refresh() {
        try {
            const res = await this.api('get_di_bindings');
            if (res.success) {
                this.setState({
                    bindings: res.data.bindings || [],
                    loading: false
                });
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    render() {
        const { bindings, loading, activeTab } = this.state;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            const headerHtml = html`
                <div class="spp-tabs" style="display: inline-flex; margin-right: 1rem;">
                    <div class="tab ${activeTab === 'di' ? 'active' : ''}" @click=${() => this.setState({activeTab: 'di'})}>🔌 Dependency Injection</div>
                    <div class="tab ${activeTab === 'ajax' ? 'active' : ''}" @click=${() => this.setState({activeTab: 'ajax'})}>⚡ LiveServices (AJAX)</div>
                </div>
                ${activeTab === 'di' ? html`<button class="btn ghost-btn btn-sm" @click=${this.refresh}>🔄 Refresh Registry</button>` : ''}
            `;
            headerActions.innerHTML = headerHtml.toString();
            
            const tabs = headerActions.querySelectorAll('.tab');
            if (tabs[0]) tabs[0].onclick = () => this.setState({activeTab: 'di'});
            if (tabs[1]) tabs[1].onclick = () => this.setState({activeTab: 'ajax'});
            const btn = headerActions.querySelector('.btn');
            if (btn && activeTab === 'di') btn.onclick = () => this.refresh();
        }

        if (activeTab === 'ajax') {
            return this.renderAjaxTab();
        }

        if (loading) return html`<div class="loading-state">Inspecting DI Container...</div>`;

        return html`
            <div class="services-container">

                <div class="glass-panel" style="padding: 0; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--glass-border);">
                            <tr>
                                <th style="padding: 15px; font-size: 0.8rem; font-weight: 600; color: var(--text-dim);">ABSTRACT / INTERFACE</th>
                                <th style="padding: 15px; font-size: 0.8rem; font-weight: 600; color: var(--text-dim);">CONCRETE IMPLEMENTATION</th>
                                <th style="padding: 15px; font-size: 0.8rem; font-weight: 600; color: var(--text-dim); text-align: center;">TYPE</th>
                                <th style="padding: 15px; font-size: 0.8rem; font-weight: 600; color: var(--text-dim); text-align: center;">LIFECYCLE</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${bindings.length > 0 ? bindings.map(b => html`
                                <tr style="border-bottom: 1px solid var(--glass-border);">
                                    <td style="padding: 15px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--accent-light);">${b.abstract}</td>
                                    <td style="padding: 15px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--text-dim);">${b.concrete}</td>
                                    <td style="padding: 15px; text-align: center;">
                                        <span class="badge ${b.shared ? 'info' : 'ghost'}" style="font-size: 0.7rem;">${b.shared ? 'SINGLETON' : 'FACTORY'}</span>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <span class="status-dot ${b.instantiated ? 'active' : ''}" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: ${b.instantiated ? '#4ade80' : '#4b5563'}; box-shadow: ${b.instantiated ? '0 0 10px #4ade80' : 'none'};"></span>
                                        <span style="font-size: 0.75rem; margin-left: 8px; color: ${b.instantiated ? 'var(--text-main)' : 'var(--text-dim)'};">
                                            ${b.instantiated ? 'Instantiated' : 'Lazy'}
                                        </span>
                                    </td>
                                </tr>
                            `) : html`<tr><td colspan="4" style="padding: 40px; text-align: center;" class="empty-state">No manual bindings registered. Most services are resolved via Autowiring.</td></tr>`}
                        </tbody>
                    </table>
                </div>

                <div class="info-box" style="margin-top: 20px; display: flex; gap: 15px; align-items: flex-start; padding: 20px; background: rgba(var(--accent-rgb), 0.05); border: 1px solid var(--accent-light); border-radius: 8px;">
                    <span style="font-size: 1.5rem;">🔌</span>
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: var(--accent-light);">Autowired Dependency Injection</h4>
                        <p style="margin: 0; font-size: 0.9rem; line-height: 1.5; opacity: 0.9;">
                            The SPP container automatically resolves dependencies using reflection. 
                            Manual bindings shown above are used for interface aliasing or pre-instantiated singletons (like the <code>App</code> itself).
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    // =============================================
    // INLINED: LiveService & AJAX Registry
    // =============================================

    async loadServices() {
        this.setState({ ajaxLoading: true });
        try {
            const res = await this.api('get_ajax_services');
            if (res.success) {
                this.setState({
                    services: res.data.services || [],
                    ajaxLoading: false
                });
            }
        } catch (e) {
            this.setState({ ajaxLoading: false, error: e.message });
        }
    }

    renderAjaxTab() {
        if (this.state.ajaxLoading) return html`<div class="loading-state">Loading Service Registry...</div>`;

        return html`
            <div class="glass-panel" style="padding: 2rem;">
                <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.5rem;">LiveService Registry</h3>
                        <p style="color: var(--text-dim); margin: 0.5rem 0 0 0;">Manage asynchronous endpoints and reactive service bindings.</p>
                    </div>
                    <button class="btn primary-btn btn-sm" @click=${() => this.setState({ showAddModal: true })}>
                        <span>+ Register Service</span>
                    </button>
                </header>

                <table class="spp-table">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Backend Script</th>
                            <th>Method</th>
                            <th>Source</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.state.services.map(svc => html`
                            <tr>
                                <td style="font-family: 'JetBrains Mono', monospace; color: var(--accent-light);">${svc.name}</td>
                                <td style="font-size: 0.85rem; color: var(--text-dim);">/src/serv/${svc.script}</td>
                                <td><span class="badge ghost">${svc.method}</span></td>
                                <td><span class="badge ${svc.source === 'db' ? 'info' : 'ghost'}">${svc.source.toUpperCase()}</span></td>
                                <td>
                                    <button class="btn ghost-btn btn-xs" @click=${() => this.testService(svc.name)}>⚡ Test</button>
                                </td>
                            </tr>
                        `)}
                    </tbody>
                </table>
            </div>

            ${this.state.showAddModal ? this.renderAddModal() : ''}
        `;
    }

    renderAddModal() {
        return html`
            <div class="glass-overlay" style="display: flex; align-items: center; justify-content: center; z-index: 1000;">
                <div class="glass-panel" style="width: 500px; padding: 2rem;">
                    <h4 style="margin-top: 0;">Register New LiveService</h4>
                    <div class="input-group">
                        <label>Service Identifier</label>
                        <input type="text" class="spp-element" placeholder="e.g. user_update" 
                            .value=${this.state.newService.name}
                            @input=${(e) => this.updateNewSvc('name', e.target.value)}>
                    </div>
                    <div class="input-group">
                        <label>Script Filename</label>
                        <input type="text" class="spp-element" placeholder="e.g. save_profile.php" 
                            .value=${this.state.newService.script}
                            @input=${(e) => this.updateNewSvc('script', e.target.value)}>
                    </div>
                    <div class="grid-2">
                        <div class="input-group">
                            <label>HTTP Method</label>
                            <select class="spp-element" .value=${this.state.newService.method}
                                @change=${(e) => this.updateNewSvc('method', e.target.value)}>
                                <option value="POST">POST (Recommended)</option>
                                <option value="GET">GET</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Storage Source</label>
                            <select class="spp-element" .value=${this.state.newService.source}
                                @change=${(e) => this.updateNewSvc('source', e.target.value)}>
                                <option value="yaml">YAML (etc/services.yml)</option>
                                <option value="db">Database (sppajax_services)</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                        <button class="btn secondary-btn" @click=${() => this.setState({ showAddModal: false })}>Cancel</button>
                        <button class="btn primary-btn" @click=${() => this.saveService()}>Save Service</button>
                    </div>
                </div>
            </div>
        `;
    }

    updateNewSvc(key, value) {
        this.setState({
            newService: { ...this.state.newService, [key]: value }
        });
    }

    async saveService() {
        const res = await this.api('save_ajax_service', this.state.newService);
        if (res.success) {
            this.notify('Service registered successfully.', 'success');
            this.setState({ showAddModal: false, newService: { name: '', script: '', method: 'POST', source: 'yaml' } });
            await this.loadServices();
        } else {
            this.notify('Error: ' + res.message, 'error');
        }
    }

    testService(name) {
        this.notify(`Testing service '${name}'...`, 'info');
        // In a real implementation, we could open a console or modal to provide inputs
        window.SPPUX.api(name, { test: true });
    }
}
