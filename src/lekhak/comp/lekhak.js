import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js';

/**
 * LekhakView - Modern Dashboard for Lekhak CMS
 */
export default class LekhakView extends BaseComponent {
    constructor(admin, container, props = {}) {
        super(admin, container, { ...props, apiBase: '../../src/lekhak/resources/admin-api.php' });
    }

    async onInit() {
        this.state = {
            stats: { total: 0, published: 0, drafts: 0, engagement: 0 },
            recent: [],
            loading: true
        };
        
        // Ensure we are in the Lekhak app context
        if (this.admin.selectedApp !== 'lekhak') {
            this.admin.onAppContextChange('lekhak');
        }
    }

    async onMount() {
        await this.fetchData();
    }

    async fetchData() {
        try {
            const res = await this.api.getDashboardStats();
            if (res.success) {
                this.setState({
                    stats: res.stats,
                    recent: res.recent,
                    loading: false
                });
            }
        } catch (e) {
            console.error('Lekhak fetchData error:', e);
            this.admin.notify(`Failed to load dashboard stats: ${e.message}`, 'error');
            this.setState({ loading: false });
        }
    }

    render() {
        const { stats, recent, loading } = this.state;

        if (loading) {
            return html`<div class="loading-state glass-panel">🔮 Syncing Lekhak Workspace...</div>`;
        }

        return html`
            <div class="lekhak-dashboard">
                <header class="dashboard-header">
                    <div class="header-main">
                        <h1>Workspace Overview</h1>
                        <p>Real-time synchronization for your content ecosystem.</p>
                    </div>
                    <button class="btn-create" @click="${() => location.hash = 'editor'}">
                        ＋ New Document
                    </button>
                </header>

                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-label">All Nodes</span>
                        <span class="stat-value">${stats.total}</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Published</span>
                        <span class="stat-value" style="color: #4ade80;">${stats.published}</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Pending</span>
                        <span class="stat-value" style="color: #fbbf24;">${stats.drafts}</span>
                    </div>
                </div>

                <div class="dashboard-layout">
                    <section class="main-panel">
                        <div class="panel-header">
                            <h2>Recent Activity</h2>
                            <a href="#content">Manage All</a>
                        </div>
                        <div class="activity-table">
                            ${recent.length > 0 ? recent.map(node => html`
                                <div class="activity-row" @click="${() => this.editNode(node.id)}">
                                    <div class="node-indicator ${node.status}"></div>
                                    <div class="node-info">
                                        <div class="node-title">${node.title}</div>
                                        <div class="node-date">Modified: ${node.changed}</div>
                                    </div>
                                    <div class="node-status-tag">${node.status}</div>
                                    <div class="row-actions">
                                        <a href="${this.admin.config.baseUrl}/node/${node.id}" target="_blank" class="btn-edit-inline" @click="${(e) => e.stopPropagation()}">View</a>
                                        <button class="btn-edit-inline" @click="${(e) => { e.stopPropagation(); this.editNode(node.id); }}">Edit</button>
                                    </div>
                                </div>
                            `) : html`<div class="empty-state">No recent activity.</div>`}
                        </div>
                    </section>

                    <aside class="side-panel">
                        <div class="action-card">
                            <h3>System Tools</h3>
                            <div class="tool-links">
                                <a class="tool-link" @click="${() => location.hash = 'canvas'}">🎨 Launch Visual Canvas</a>
                                <a class="tool-link" @click="${() => this.admin.notify('Rebuilding...', 'info')}">🔍 Rebuild Search Index</a>
                                <a class="tool-link" @click="${() => location.hash = 'settings'}">⚙️ Application Setup</a>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <style>
                .lekhak-dashboard { font-family: 'Inter', sans-serif; color: #f8fafc; }
                
                .dashboard-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    padding-bottom: 2rem;
                    margin-bottom: 2rem;
                    border-bottom: 1px solid #334155;
                }
                .dashboard-header h1 { font-family: 'Outfit'; font-size: 2rem; margin: 0; }
                .dashboard-header p { color: #94a3b8; margin: 5px 0 0 0; }
                
                .btn-create {
                    background: #6366f1;
                    color: white;
                    border: none;
                    padding: 0.8rem 1.5rem;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    font-family: 'Outfit';
                }

                .stats-row {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 1.5rem;
                    margin-bottom: 2.5rem;
                }
                .stat-box {
                    background: #1e293b;
                    padding: 1.5rem;
                    border-radius: 12px;
                    border: 1px solid #334155;
                    display: flex;
                    flex-direction: column;
                }
                .stat-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
                .stat-value { font-size: 2rem; font-weight: 700; font-family: 'Outfit'; }

                .dashboard-layout {
                    display: grid;
                    grid-template-columns: 1fr 300px;
                    gap: 2rem;
                }

                .main-panel h2 { font-size: 1.25rem; margin-bottom: 1rem; }
                .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
                .panel-header a { color: #6366f1; text-decoration: none; font-size: 0.875rem; font-weight: 500; }

                .activity-table { background: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
                .activity-row {
                    display: flex;
                    align-items: center;
                    padding: 1rem 1.5rem;
                    border-bottom: 1px solid #334155;
                    cursor: pointer;
                    transition: background 0.2s;
                    gap: 1rem;
                }
                .activity-row:hover { background: #334155; }
                .activity-row:last-child { border-bottom: none; }

                .node-indicator { width: 8px; height: 8px; border-radius: 50%; }
                .node-indicator.published { background: #4ade80; }
                .node-indicator.draft { background: #94a3b8; }
                
                .node-info { flex-grow: 1; }
                .node-title { font-weight: 500; font-size: 1rem; }
                .node-date { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }

                .node-status-tag {
                    font-size: 0.7rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    padding: 4px 8px;
                    background: #0f172a;
                    border-radius: 4px;
                    color: #94a3b8;
                }

                .row-actions { display: flex; gap: 8px; }
                .btn-edit-inline {
                    background: transparent;
                    border: 1px solid #475569;
                    color: #f1f5f9;
                    padding: 4px 12px;
                    border-radius: 4px;
                    font-size: 0.75rem;
                    cursor: pointer;
                    text-decoration: none;
                }

                .action-card { background: #1e293b; padding: 1.5rem; border-radius: 12px; border: 1px solid #334155; }
                .action-card h3 { font-size: 1rem; margin-bottom: 1.25rem; color: #94a3b8; }
                
                .tool-links { display: flex; flex-direction: column; gap: 0.5rem; }
                .tool-link {
                    padding: 0.75rem;
                    background: #0f172a;
                    border-radius: 8px;
                    font-size: 0.875rem;
                    color: #f1f5f9;
                    text-decoration: none;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .tool-link:hover { background: #6366f1; }
                
                .empty-state { padding: 3rem; text-align: center; color: #64748b; }
            </style>
        `;
    }

    renderStatCard(label, value, icon, color) {
        return html`
            <div class="stat-card glass-panel" style="border-bottom: 3px solid ${color}">
                <div class="stat-icon">${icon}</div>
                <div class="stat-value" style="color: ${color}">${value}</div>
                <div class="stat-label">${label}</div>
            </div>
        `;
    }

    editNode(id) {
        location.hash = `editor?id=${id}`;
    }
}
