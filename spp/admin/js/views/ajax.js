/**
 * SPP Admin: LiveService & AJAX Registry
 * 
 * Manages the registration and orchestration of asynchronous services.
 */

export default class AjaxView extends BaseComponent {
    constructor(app, container, props = {}) {
        super(app, container, props);
        this.state = {
            loading: true,
            services: [],
            showAddModal: false,
            newService: {
                name: '',
                script: '',
                method: 'POST',
                source: 'yaml'
            }
        };
    }

    async onInit() {
        await this.loadServices();
    }

    async loadServices() {
        this.setState({ loading: true });
        try {
            const res = await this.api('get_ajax_services');
            if (res.success) {
                this.setState({
                    services: res.data.services || [],
                    loading: false
                });
            }
        } catch (e) {
            this.setState({ loading: false, error: e.message });
        }
    }

    render() {
        if (this.state.loading) return html`<div class="loading-state">Loading Service Registry...</div>`;

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
