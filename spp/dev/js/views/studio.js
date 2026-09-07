/**
 * StudioView Component (Unified Developer Studio)
 * 
 * Unifies In-Browser Code Editor, Application & Module Scaffolder,
 * and Codebase Structure & Documentation Explorer into a single cohesive workspace.
 */

export default class StudioView extends BaseComponent {
    async onInit() {
        const hash = location.hash;
        let initialTab = 'editor';
        if (hash.includes('tab=apps') || hash.includes('#apps')) initialTab = 'scaffolder';
        else if (hash.includes('tab=docs') || hash.includes('#docs')) initialTab = 'docs';
        else if (hash.includes('tab=editor') || hash.includes('#editor')) initialTab = 'editor';

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
        const mount = document.getElementById('studio-subview-mount');
        if (!mount) {
            this.setState({ loading: false });
            return;
        }

        mount.innerHTML = '<div class="loading-state" style="padding: 2rem; text-align: center;"><div class="sppux-spinner"></div> Loading Studio Workspace...</div>';

        try {
            let ViewClass = null;
            if (tab === 'editor') {
                const mod = await import('./editor.js');
                ViewClass = mod.default;
            } else if (tab === 'scaffolder') {
                const mod = await import('./apps.js');
                ViewClass = mod.default;
            } else if (tab === 'docs') {
                const mod = await import('./docs.js');
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
            console.error(`[StudioView] Failed to load sub-tab ${tab}:`, e);
            mount.innerHTML = `<div class="alert error" style="margin: 1.5rem;">Failed to load ${tab} view: ${e.message}</div>`;
            this.setState({ loading: false });
        }
    }

    render() {
        const { activeTab } = this.state;

        setTimeout(() => {
            const mount = document.getElementById('studio-subview-mount');
            if (mount && (!mount.children.length || mount.querySelector('.loading-state'))) {
                this.loadActiveTab(this.state.activeTab);
            }
        }, 10);

        return html`
            <div class="studio-workspace" style="display: flex; flex-direction: column; height: 100%;">
                <!-- Sub-Navigation Toolbar -->
                <div class="tabs-toolbar" style="margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); display: flex; gap: 8px; padding-bottom: 0.5rem; flex-wrap: wrap;">
                    <button class="tab-btn ${activeTab === 'editor' ? 'active' : ''}" 
                            @click=${() => this.switchTab('editor')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'editor' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'editor' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'editor' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>💻</span> In-Browser Code Editor
                    </button>
                    <button class="tab-btn ${activeTab === 'scaffolder' ? 'active' : ''}" 
                            @click=${() => this.switchTab('scaffolder')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'scaffolder' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'scaffolder' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'scaffolder' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>📱</span> App & Module Scaffolder
                    </button>
                    <button class="tab-btn ${activeTab === 'docs' ? 'active' : ''}" 
                            @click=${() => this.switchTab('docs')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'docs' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'docs' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'docs' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>📚</span> Code Explorer & Docs
                    </button>
                </div>

                <!-- Subview Mount Container -->
                <div id="studio-subview-mount" style="flex: 1; min-height: 550px; position: relative;">
                    <div class="loading-state" style="padding: 2rem; text-align: center;">
                        <div class="sppux-spinner"></div> Loading Studio Workspace...
                    </div>
                </div>
            </div>
        `;
    }
}
