/**
 * Framework Configuration View
 * Manages global-settings.yml and app-specific configurations via SPPConfig.
 */
export default class ConfigView extends BaseComponent {
    async onInit() {
        this.state = {
            config: { global: {}, app: {}, sys: {} },
            activeTab: 'global',
            loading: true
        };
        await this.refresh();
    }

    async refresh() {
        try {
            const res = await this.api('get_config_all');
            if (res.success) {
                this.setState({
                    config: res.data.config,
                    loading: false
                });
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
    }

    async updateValue(key, value) {
        const { activeTab } = this.state;
        const fullKey = activeTab === 'global' ? `global:${key}` : (activeTab === 'sys' ? `sys:${key}` : `app:${key}`);
        
        try {
            const res = await this.apiPost('save_config_value', { key: fullKey, value });
            if (res.success) {
                this.notify(`Updated ${fullKey} to ${value}`, 'success');
                await this.refresh();
            } else {
                this.notify(`Failed to update ${fullKey}: ${res.message}`, 'error');
            }
        } catch (err) {
            this.notify(`Update request failed: ${err.message}`, 'error');
        }
    }

    render() {
        const { config, activeTab, loading } = this.state;
        const currentData = config[activeTab] || {};
        const entries = Object.entries(currentData);

        if (loading) return html`<div class="loading-state">Loading registry...</div>`;

        return html`
            <div class="config-container">
                <div class="tabs-toolbar" style="margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 1px solid var(--glass-border);">
                    <button class="tab-btn ${activeTab === 'global' ? 'active' : ''}" @click=${() => this.switchTab('global')}>
                        🌍 Global Settings
                    </button>
                    <button class="tab-btn ${activeTab === 'app' ? 'active' : ''}" @click=${() => this.switchTab('app')}>
                        📱 App context
                    </button>
                    <button class="tab-btn ${activeTab === 'sys' ? 'active' : ''}" @click=${() => this.switchTab('sys')}>
                        🖥️ infrastructure
                    </button>
                    <div style="flex: 1;"></div>
                    <button class="btn ghost-btn btn-sm" @click=${this.refresh}>🔄 Refresh Config</button>
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
                                        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Source: ${activeTab.toUpperCase()} config</div>
                                    </div>
                                    <div style="flex: 2;">
                                        ${typeof v === 'boolean' 
                                            ? html`<label class="toggle-switch">
                                                <input type="checkbox" ?checked="${v}" @change=${(e) => this.updateValue(k, e.target.checked)}>
                                                <span class="toggle-slider"></span>
                                               </label>`
                                            : html`<input type="text" class="spp-element" value="${v}" 
                                                style="width: 100%; font-family: monospace;"
                                                @change=${(e) => this.updateValue(k, e.target.value)}>`
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
                        <strong>Hierarchical Resolution:</strong> The SPP Config system resolves values by checking <code>App</code> context first, then <code>Global</code> defaults. 
                        Infrastructure settings (<code>sys</code>) manage application routing and paths.
                    </div>
                </div>
            </div>
        `;
    }
}
