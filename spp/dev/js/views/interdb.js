/**
 * SPP Admin: InterDB Mesh View
 * 
 * Handles the management of federated database mappings and 
 * aggregation modes for the SPPInterDB module.
 */

export default class InterDBView extends BaseComponent {
    constructor(app, container, props = {}) {
        super(app, container, props);
        this.state = {
            loading: true,
            mode: 'interdb',
            mappings: {}
        };
    }

    async onInit() {
        await this.loadConfig();
    }

    async loadConfig() {
        this.setState({ loading: true });
        try {
            const res = await this.api('get_interdb_config');
            if (res.success) {
                this.setState({
                    mode: res.data.mode || 'interdb',
                    mappings: res.data.mappings || {},
                    loading: false
                });
            } else {
                throw new Error(res.message);
            }
        } catch (e) {
            this.setState({ loading: false, error: e.message });
        }
    }

    render() {
        if (this.state.loading) return html`<div class="loading-state">Loading InterDB Configuration...</div>`;

        return html`
            <div class="glass-panel" style="padding: 2rem;">
                <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.5rem;">Federated Data Orchestration</h3>
                        <p style="color: var(--text-dim); margin: 0.5rem 0 0 0;">Manage cross-engine entity mappings and gateway behavior.</p>
                    </div>
                    <div class="actions">
                        <button class="btn primary-btn btn-sm" @click=${() => this.saveConfig()}>
                            <span>💾 Save Config</span>
                        </button>
                    </div>
                </header>

                <div class="grid-2" style="gap: 2rem;">
                    <section>
                        <div class="input-group">
                            <label>Aggregation Mode</label>
                            <select id="interdb-mode" class="spp-element" .value=${this.state.mode}>
                                <option value="interdb">InterDB (Multi-Database Federation)</option>
                                <option value="standalone">Standalone (Single-DB GraphQL)</option>
                            </select>
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-dim);">
                                Use 'InterDB' for complex ecosystems. 'Standalone' provides a GraphQL interface for your primary DB.
                            </small>
                        </div>
                    </section>
                </div>

                <div style="margin-top: 3rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h4 style="margin: 0;">Entity Mappings</h4>
                        <button class="btn ghost-btn btn-xs" @click=${() => this.addMapping()}>+ Add Mapping</button>
                    </div>
                    <table class="spp-table">
                        <thead>
                            <tr>
                                <th>Entity (Type)</th>
                                <th>Engine / Alias</th>
                                <th>Target Table</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="interdb-mappings-body">
                            ${Object.entries(this.state.mappings).map(([type, map]) => this.renderMappingRow(type, map.engine, map.table))}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderMappingRow(type, engine, table) {
        return html`
            <tr>
                <td><input type="text" class="spp-element mapping-type" value="${type}" placeholder="e.g. user"></td>
                <td>
                    <select class="spp-element mapping-engine">
                        <option value="default" ?selected=${engine === 'default'}>Default (SQL)</option>
                        <option value="xdb" ?selected=${engine === 'xdb'}>SPPXDB (XML)</option>
                        <option value="pdo" ?selected=${engine === 'pdo'}>Generic PDO</option>
                    </select>
                </td>
                <td><input type="text" class="spp-element mapping-table" value="${table}" placeholder="e.g. users"></td>
                <td>
                    <button class="btn ghost-btn btn-xs color-danger" @click=${(e) => e.target.closest('tr').remove()}>🗑️</button>
                </td>
            </tr>
        `;
    }

    addMapping() {
        const newMappings = { ...this.state.mappings, '': { engine: 'default', table: '' } };
        this.setState({ mappings: newMappings });
    }

    async saveConfig() {
        const mode = document.getElementById('interdb-mode').value;
        const mappings = {};
        
        document.querySelectorAll('#interdb-mappings-body tr').forEach(row => {
            const type = row.querySelector('.mapping-type').value.trim();
            const engine = row.querySelector('.mapping-engine').value;
            const table = row.querySelector('.mapping-table').value.trim();
            
            if (type) {
                mappings[type] = { engine, table };
            }
        });

        const res = await this.api('save_interdb_config', { mode, mappings });
        if (res.success) {
            this.notify('InterDB configuration saved successfully.', 'success');
        } else {
            this.notify('Failed to save configuration: ' + res.message, 'error');
        }
    }
}
