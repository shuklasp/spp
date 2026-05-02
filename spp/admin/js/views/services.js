/**
 * Service Registry View
 * Displays registered services and bindings in the DI Container.
 */
export default class ServicesView extends BaseComponent {
    async onInit() {
        this.state = {
            bindings: [],
            loading: true
        };
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
        const { bindings, loading } = this.state;

        if (loading) return html`<div class="loading-state">Inspecting DI Container...</div>`;

        return html`
            <div class="services-container">
                <div class="header-tools" style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                    <button class="btn ghost-btn btn-sm" @click=${this.refresh}>🔄 Refresh Registry</button>
                </div>

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
}
