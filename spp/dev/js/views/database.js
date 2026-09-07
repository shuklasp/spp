/**
 * Database & Storage View Component (Unified Data Plane for SPP Dev)
 * 
 * Unifies Relational Database Schema & Entities, XML NoSQL Document Database (XDB),
 * and InterDB Data Mesh Federation into a cohesive, high-performance workspace.
 */

export default class DatabaseView extends BaseComponent {
    async onInit() {
        // Detect tab from hash query param if provided (e.g., #database?tab=xdb or legacy redirect)
        const hash = location.hash;
        let initialTab = 'entities';
        if (hash.includes('tab=xdb') || hash.includes('#xdb')) initialTab = 'xdb';
        else if (hash.includes('tab=interdb') || hash.includes('#interdb')) initialTab = 'interdb';
        
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
        const mount = document.getElementById('database-subview-mount');
        if (!mount) {
            this.setState({ loading: false });
            return;
        }

        mount.innerHTML = '<div class="loading-state" style="padding: 2rem; text-align: center;"><div class="sppux-spinner"></div> Loading Data Workspace...</div>';

        try {
            let ViewClass = null;
            if (tab === 'entities') {
                const mod = await import('./entities.js');
                ViewClass = mod.default;
            } else if (tab === 'xdb') {
                const mod = await import('./xdb.js');
                ViewClass = mod.default;
            } else if (tab === 'interdb') {
                const mod = await import('./interdb.js');
                ViewClass = mod.default;
            }

            if (ViewClass) {
                mount.innerHTML = '';
                const appObj = this.app || this.admin;
                const instance = new ViewClass(appObj, mount, { app: appObj?.selectedApp });
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
            console.error(`[DatabaseView] Failed to load sub-tab ${tab}:`, e);
            mount.innerHTML = `<div class="alert error" style="margin: 1.5rem;">Failed to load ${tab} view: ${e.message}</div>`;
            this.setState({ loading: false });
        }
    }

    render() {
        const { activeTab } = this.state;

        // Auto mount subview on next tick if DOM was just created
        setTimeout(() => {
            const mount = document.getElementById('database-subview-mount');
            if (mount && (!mount.children.length || mount.querySelector('.loading-state'))) {
                this.loadActiveTab(this.state.activeTab);
            }
        }, 10);

        return html`
            <div class="database-workspace" style="display: flex; flex-direction: column; height: 100%;">
                <!-- Sub-Navigation Toolbar -->
                <div class="tabs-toolbar" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--glass-border); display: flex; gap: 8px; padding-bottom: 0.5rem; flex-wrap: wrap;">
                    <button class="tab-btn ${activeTab === 'entities' ? 'active' : ''}" 
                            @click=${() => this.switchTab('entities')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'entities' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'entities' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'entities' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>🏗️</span> Relational Database & Schemas
                    </button>
                    <button class="tab-btn ${activeTab === 'xdb' ? 'active' : ''}" 
                            @click=${() => this.switchTab('xdb')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'xdb' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'xdb' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'xdb' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>🗄️</span> XML NoSQL Database (XDB)
                    </button>
                    <button class="tab-btn ${activeTab === 'interdb' ? 'active' : ''}" 
                            @click=${() => this.switchTab('interdb')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'interdb' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'interdb' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'interdb' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>🕸️</span> InterDB Data Mesh Federation
                    </button>
                </div>

                <!-- Subview Container -->
                <div id="database-subview-mount" style="flex: 1; min-height: 0; display: flex; flex-direction: column;">
                    <div class="loading-state" style="padding: 2rem; text-align: center;">
                        <div class="sppux-spinner"></div> Loading Data Workspace...
                    </div>
                </div>
            </div>
        `;
    }
}
