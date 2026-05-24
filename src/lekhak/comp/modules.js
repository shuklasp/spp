import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { html } from '../../../spp/modules/spp/sppux/js/sppux.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta } from './lekhak-nav.js';

export default class ModulesView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            modules: [],
            categories: [],
            activeCategory: 'All',
            searchQuery: ''
        };
        registerNavHandlers();
        setPageMeta('Extend', 'Install and manage Lekhak modules and extensions.');
        await this.fetchModules();
    }

    async fetchModules() {
        this.setState({ loading: true });
        try {
            const res = await this.admin.api('list_modules');
            if (res && res.success) {
                const modules = res.modules || [];
                const cats = new Set(modules.map(m => m.category));
                this.setState({
                    modules: modules,
                    categories: ['All', ...Array.from(cats)],
                    loading: false
                });
            }
        } catch (e) {
            console.error("Failed to fetch modules", e);
            this.setState({ loading: false });
        }
    }

    async toggleModule(machine_name, currentStatus) {
        const newStatus = currentStatus === 1 ? 0 : 1;
        this.admin?.notify?.(`${newStatus ? 'Installing' : 'Uninstalling'} module ${machine_name}...`, "info");
        
        try {
            const res = await this.admin.api('toggle_module', {
                machine_name: machine_name,
                status: newStatus
            });

            if (res && res.success) {
                this.admin?.notify?.(`Module ${machine_name} ${newStatus ? 'installed' : 'uninstalled'} successfully.`, "success");
                await this.fetchModules();
            } else {
                this.admin?.notify?.(res.message || "Failed to toggle module.", "error");
            }
        } catch (e) {
            console.error("Failed to toggle module", e);
            this.admin?.notify?.("Network error while toggling module.", "error");
        }
    }

    render() {
        if (this.state.loading) {
            return `
                <div style="padding:40px; text-align:center; color:var(--text-dim);">
                    <h2>Loading Module Registry...</h2>
                    <p>Fetching packages and checking environment status.</p>
                </div>
            `;
        }

        const { modules, categories, activeCategory, searchQuery } = this.state;
        
        // Filter modules based on search and category
        let filteredModules = modules;
        if (activeCategory !== 'All') {
            filteredModules = filteredModules.filter(m => m.category === activeCategory);
        }
        if (searchQuery.trim() !== '') {
            const q = searchQuery.toLowerCase();
            filteredModules = filteredModules.filter(m => 
                m.title.toLowerCase().includes(q) || 
                m.machine_name.toLowerCase().includes(q) || 
                m.desc.toLowerCase().includes(q)
            );
        }

        // Group the final filtered list by category for display
        const grouped = {};
        filteredModules.forEach(m => {
            if (!grouped[m.category]) grouped[m.category] = [];
            grouped[m.category].push(m);
        });

        const contentStr = `
<div style="padding: 2.5rem 2rem; max-width: 1400px; margin: 0 auto; font-family: 'Inter', sans-serif;">
    <header style="margin-bottom: 2rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; font-weight: 800; margin: 0; color: var(--text);">Extend</h1>
            <p style="color: var(--text-dim); font-size: 0.95rem; margin-top: 4px;">Install, configure, and manage modules extending the CMS capabilities.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="module-search" value="${searchQuery}" placeholder="Search modules..." style="background:var(--input-bg, #fff); border:1px solid var(--border); padding:10px 15px; border-radius:8px; width:250px; outline:none; color:var(--text);">
        </div>
    </header>

    <div style="display: flex; gap: 2rem; align-items: flex-start;">
        <!-- Sidebar Category Filter -->
        <div style="width: 250px; flex-shrink: 0; background: rgba(0,0,0,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 10px; position: sticky; top: 70px;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; margin: 10px 10px 15px; color: var(--text-dim);">Categories</h3>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px;">
                ${categories.map(cat => `
                    <li>
                        <button class="btn-cat-filter" data-cat="${cat}" style="width: 100%; text-align: left; padding: 8px 12px; border: none; background: ${activeCategory === cat ? 'rgba(99,102,241,0.1)' : 'transparent'}; color: ${activeCategory === cat ? '#6366f1' : 'var(--text)'}; font-weight: ${activeCategory === cat ? '700' : '500'}; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                            ${cat}
                        </button>
                    </li>
                `).join('')}
            </ul>
        </div>

        <!-- Module Grid -->
        <div style="flex-grow: 1;">
            ${Object.keys(grouped).length === 0 ? `
                <div style="padding: 3rem; text-align: center; color: var(--text-dim); background: rgba(0,0,0,0.02); border: 1px dashed var(--border); border-radius: 12px;">
                    <span style="font-size: 2rem; margin-bottom: 1rem; display: block;">🔍</span>
                    <p style="font-size: 1.1rem; font-weight: 500;">No modules found matching your criteria.</p>
                </div>
            ` : Object.keys(grouped).map(catName => `
                <div style="margin-bottom: 2.5rem;">
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; border-bottom: 2px solid rgba(0,0,0,0.05); padding-bottom: 8px; margin-bottom: 1.2rem; color: var(--text); display: flex; align-items: center; gap: 10px;">
                        ${this.getCategoryIcon(catName)} ${catName}
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1rem;">
                        ${grouped[catName].map(m => `
                            <div style="background: var(--glass-bg, #fff); border: 1px solid ${m.status === 1 ? '#10b981' : 'var(--glass-border)'}; border-radius: 12px; padding: 1.2rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden; box-shadow: ${m.status === 1 ? '0 4px 12px rgba(16,185,129,0.1)' : 'none'};">
                                ${m.status === 1 ? '<div style="position:absolute; top:0; left:0; width:4px; height:100%; background:#10b981;"></div>' : ''}
                                
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                        <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.15rem; color: var(--text);">${m.title}</h3>
                                        ${m.status === 1 ? '<span style="background:rgba(16,185,129,0.1); color:#10b981; font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:4px; text-transform:uppercase;">Installed</span>' : ''}
                                    </div>
                                    <div style="font-size: 0.75rem; font-family: monospace; color: var(--text-dim); margin-bottom: 12px; opacity: 0.8;">machine_name: ${m.machine_name}</div>
                                    <p style="font-size: 0.9rem; color: var(--text-dim); line-height: 1.4; margin: 0 0 1.2rem 0;">${m.desc}</p>
                                </div>
                                
                                <div style="display: flex; justify-content: flex-end; align-items: center; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 12px; margin-top: auto;">
                                    <button class="btn-toggle-module" data-machine="${m.machine_name}" data-status="${m.status}" style="background: ${m.status === 1 ? 'transparent' : '#f97316'}; color: ${m.status === 1 ? '#ef4444' : '#fff'}; border: 1px solid ${m.status === 1 ? 'rgba(239,68,68,0.3)' : '#f97316'}; padding: 6px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;">
                                        ${m.status === 1 ? 'Uninstall' : 'Install Module'}
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('')}
        </div>
    </div>
</div>
        `;
        return { content: contentStr, __isTrusted: true, toString: function() { return this.content; } };
    }

    getCategoryIcon(cat) {
        const icons = {
            'Site Building': '🏗️',
            'SEO & Routing': '🗺️',
            'Security & Administration': '🛡️',
            'Media & Content': '🖼️',
            'Performance': '⚡'
        };
        return icons[cat] || '📦';
    }

    afterUpdate() {
        // Search listener
        const searchInput = document.getElementById('module-search');
        if (searchInput && !searchInput.oninput) {
            searchInput.oninput = (e) => {
                this.setState({ searchQuery: e.target.value });
            };
            searchInput.focus();
            searchInput.selectionStart = searchInput.selectionEnd = searchInput.value.length;
        }

        // Category filters
        document.querySelectorAll('.btn-cat-filter').forEach(btn => {
            if (btn.onclick) return;
            btn.onclick = (e) => {
                const cat = btn.getAttribute('data-cat');
                this.setState({ activeCategory: cat });
            };
        });

        // Module install/uninstall
        document.querySelectorAll('.btn-toggle-module').forEach(btn => {
            if (btn.onclick) return;
            btn.onclick = (e) => {
                const machineName = btn.getAttribute('data-machine');
                const status = parseInt(btn.getAttribute('data-status'), 10);
                this.toggleModule(machineName, status);
            };
        });
    }
}
