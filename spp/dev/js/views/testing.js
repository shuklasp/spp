/**
 * TestingView Component (Unified Testing & Telemetry Workspace)
 * 
 * Unifies Parikshak Automated Evolutionary Test Suite and
 * Event Tracing & Distributed Telemetry into a single developer cockpit.
 */

export default class TestingView extends BaseComponent {
    async onInit() {
        const hash = location.hash;
        let initialTab = 'parikshak';
        if (hash.includes('tab=trace') || hash.includes('#trace')) initialTab = 'trace';
        else if (hash.includes('tab=parikshak') || hash.includes('#parikshak')) initialTab = 'parikshak';

        this.state = {
            activeTab: initialTab,
            subViewInstance: null,
            loading: true
        };

        await this.loadActiveTab(this.state.activeTab);
    }

    async switchTab(tab) {
        if (this.state.activeTab === tab && this.state.subViewInstance) return;
        this.setState({ activeTab: tab, loading: true });
        await this.loadActiveTab(tab);
    }

    async loadActiveTab(tab) {
        const mount = document.getElementById('testing-subview-mount');
        if (!mount) {
            this.setState({ loading: false });
            return;
        }

        mount.innerHTML = '<div class="loading-state" style="padding: 2rem; text-align: center;"><div class="sppux-spinner"></div> Loading Testing Suite...</div>';

        try {
            let ViewClass = null;
            if (tab === 'parikshak') {
                const mod = await import('./parikshak.js');
                ViewClass = mod.default;
            } else if (tab === 'trace') {
                const mod = await import('./trace.js');
                ViewClass = mod.default;
            }

            if (ViewClass) {
                mount.innerHTML = '';
                const instance = new ViewClass(this.app || this.admin, mount, { app: (this.app || this.admin).selectedApp });
                if (instance.onInit) await instance.onInit();
                if (typeof instance.update === 'function') {
                    await instance.update();
                } else if (typeof instance.render === 'function') {
                    const rendered = instance.render();
                    if (rendered) mount.appendChild(rendered instanceof Node ? rendered : rendered);
                }
                this.setState({ subViewInstance: instance, loading: false });
            }
        } catch (e) {
            console.error(`[TestingView] Failed to load sub-tab ${tab}:`, e);
            mount.innerHTML = `<div class="alert error" style="margin: 1.5rem;">Failed to load ${tab} view: ${e.message}</div>`;
            this.setState({ loading: false });
        }
    }

    render() {
        const { activeTab } = this.state;

        setTimeout(() => {
            const mount = document.getElementById('testing-subview-mount');
            if (mount && (!mount.children.length || mount.querySelector('.loading-state'))) {
                this.loadActiveTab(this.state.activeTab);
            }
        }, 10);

        return html`
            <div class="testing-workspace" style="display: flex; flex-direction: column; height: 100%;">
                <!-- Sub-Navigation Toolbar -->
                <div class="tabs-toolbar" style="margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); display: flex; gap: 8px; padding-bottom: 0.5rem; flex-wrap: wrap;">
                    <button class="tab-btn ${activeTab === 'parikshak' ? 'active' : ''}" 
                            @click=${() => this.switchTab('parikshak')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'parikshak' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'parikshak' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'parikshak' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>🧪</span> Parikshak Evolutionary Test Suite
                    </button>
                    <button class="tab-btn ${activeTab === 'trace' ? 'active' : ''}" 
                            @click=${() => this.switchTab('trace')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'trace' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'trace' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'trace' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>🐛</span> Event Tracing & Telemetry
                    </button>
                </div>

                <!-- Subview Mount Container -->
                <div id="testing-subview-mount" style="flex: 1; min-height: 550px; position: relative;">
                    <div class="loading-state" style="padding: 2rem; text-align: center;">
                        <div class="sppux-spinner"></div> Loading Testing Workspace...
                    </div>
                </div>
            </div>
        `;
    }
}
