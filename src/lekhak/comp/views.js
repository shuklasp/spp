import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta } from './lekhak-nav.js';

export default class ViewsBuilder extends BaseComponent {
    async onInit() {
        this.state = {
            loading: false,
            views: [
                { id: 'recent_articles', title: 'Recent Articles', description: 'Lists 5 most recent published articles.' },
                { id: 'featured_products', title: 'Featured Products', description: 'Commerce featured catalog.' }
            ],
            activeView: null
        };
        registerNavHandlers();
        setPageMeta('Views', 'Visual Query Builder for dynamic content lists.');
    }

    render() {
        if (this.state.loading) return `<div style="padding:40px;text-align:center;">Loading...</div>`;

        if (this.state.activeView) {
            return this.renderDesigner();
        }

        return `
        <div style="padding: 2.5rem 2rem; max-width: 1200px; margin: 0 auto;">
            <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; margin: 0; color: var(--text);">Views</h1>
                    <p style="color: var(--text-dim);">Manage dynamic lists of content, users, and commerce products.</p>
                </div>
                <button class="btn" style="background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;border:none;cursor:pointer;font-weight:600;">+ Add View</button>
            </header>

            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                \${this.state.views.map(v => \`
                    <div style="background:var(--glass-bg,#fff); border:1px solid var(--border); border-radius:12px; padding:1.5rem;">
                        <h3 style="margin:0 0 10px 0;">\${v.title}</h3>
                        <p style="color:var(--text-dim); margin-bottom:1.5rem;">\${v.description}</p>
                        <button class="btn-secondary edit-view-btn" data-id="\${v.id}" style="width:100%;">Edit View</button>
                    </div>
                \`).join('')}
            </div>
        </div>
        `;
    }

    renderDesigner() {
        const v = this.state.activeView;
        return `
        <div style="display:flex; height: calc(100vh - 60px); font-family: 'Inter', sans-serif;">
            <div style="width:350px; background:#f8fafc; border-right:1px solid #e2e8f0; display:flex; flex-direction:column;">
                <div style="padding:20px; border-bottom:1px solid #e2e8f0;">
                    <h2 style="margin:0; font-size:1.2rem; font-family:'Outfit',sans-serif;">\${v.title}</h2>
                    <span style="font-size:0.8rem; color:#64748b;">Machine name: \${v.id}</span>
                </div>
                
                <div style="padding:20px; overflow-y:auto; flex-grow:1;">
                    <div style="margin-bottom:20px;">
                        <label style="font-weight:600; display:block; margin-bottom:8px;">Format</label>
                        <select style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                            <option>Unformatted List</option>
                            <option>Grid</option>
                            <option>Table</option>
                            <option>HTML List</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <label style="font-weight:600; display:block; margin-bottom:8px;">Filter Criteria</label>
                        <div style="background:#fff; border:1px solid #cbd5e1; border-radius:4px; padding:10px; font-size:0.9rem;">
                            Content: Published (= Yes)<br>
                            Content Type: Article (= Yes)
                        </div>
                        <button style="margin-top:8px; font-size:0.8rem; padding:4px 8px;">+ Add Filter</button>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="font-weight:600; display:block; margin-bottom:8px;">Sort Criteria</label>
                        <div style="background:#fff; border:1px solid #cbd5e1; border-radius:4px; padding:10px; font-size:0.9rem;">
                            Content: Authored on (desc)
                        </div>
                        <button style="margin-top:8px; font-size:0.8rem; padding:4px 8px;">+ Add Sort</button>
                    </div>
                </div>
                
                <div style="padding:20px; border-top:1px solid #e2e8f0; background:#fff;">
                    <button class="btn" style="width:100%; background:#2563eb; color:#fff; padding:10px; border-radius:6px; border:none; font-weight:bold; cursor:pointer;" onclick="alert('View saved!')">Save View</button>
                    <button class="btn-cancel" style="width:100%; background:transparent; color:#64748b; padding:10px; border:none; margin-top:8px; cursor:pointer;">Cancel</button>
                </div>
            </div>
            
            <div style="flex-grow:1; background:#f1f5f9; padding:2rem; overflow-y:auto;">
                <div style="background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); padding:2rem;">
                    <h3 style="margin-top:0; color:#64748b; font-size:0.9rem; text-transform:uppercase;">Live Preview</h3>
                    <div style="border:2px dashed #cbd5e1; padding:2rem; text-align:center; border-radius:8px; color:#94a3b8;">
                        [Preview rendered via API hook_views_render]
                        <br><br>
                        <em>Query: SELECT * FROM lekhak_nodes WHERE status = 1 ORDER BY created DESC LIMIT 5</em>
                    </div>
                </div>
            </div>
        </div>
        `;
    }

    afterUpdate() {
        this.container.querySelectorAll('.edit-view-btn').forEach(btn => {
            btn.onclick = () => {
                const viewId = btn.getAttribute('data-id');
                const v = this.state.views.find(x => x.id === viewId);
                this.setState({ activeView: v });
            };
        });

        const cancelBtn = this.container.querySelector('.btn-cancel');
        if (cancelBtn) {
            cancelBtn.onclick = () => this.setState({ activeView: null });
        }
    }
}
