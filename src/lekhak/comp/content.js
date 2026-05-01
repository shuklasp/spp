import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js';

/**
 * ContentView - Content Management for Lekhak CMS
 */
export default class ContentView extends BaseComponent {
    constructor(admin, container, props = {}) {
        super(admin, container, { ...props, apiBase: '../../src/lekhak/resources/admin-api.php' });
    }

    async onInit() {
        this.state = {
            nodes: [],
            loading: true,
            filter: ''
        };
    }

    async onMount() {
        await this.fetchNodes();
    }

    async fetchData() {
        return this.fetchNodes();
    }

    async fetchNodes() {
        try {
            const res = await this.api.listNodes();
            if (res.success) {
                this.setState({ nodes: res.nodes, loading: false });
            }
        } catch (e) {
            this.admin.notify('Failed to load content list', 'error');
            this.setState({ loading: false });
        }
    }

    render() {
        const { nodes, loading, filter } = this.state;

        const filteredNodes = nodes.filter(n => 
            n.title.toLowerCase().includes(filter.toLowerCase())
        );

        return html`
            <div class="lekhak-content-manager">
                <header class="view-header">
                    <div class="header-main">
                        <h2>Content Repository</h2>
                        <p>Manage your nodes and articles.</p>
                    </div>
                    <div class="header-actions">
                        <div class="search-field">
                            <input type="text" placeholder="Filter articles..." 
                                .value="${filter}" @input="${(e) => this.setState({ filter: e.target.value })}">
                        </div>
                        <button class="btn-primary" @click="${() => location.hash = 'editor'}">＋ Create New</button>
                    </div>
                </header>

                ${loading ? html`<div class="loading-panel">Scanning database...</div>` : html`
                    <div class="content-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>State</th>
                                    <th>Last Change</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${filteredNodes.length > 0 ? filteredNodes.map(node => html`
                                    <tr>
                                        <td class="col-title">${node.title}</td>
                                        <td>
                                            <span class="badge ${node.status}">${node.status}</span>
                                        </td>
                                        <td class="col-date">${node.changed}</td>
                                        <td style="text-align: right;">
                                            <div class="actions-group">
                                                <a href="${this.admin.config.baseUrl}/node/${node.id}" target="_blank" class="btn-ghost">View</a>
                                                <button class="btn-ghost" @click="${() => this.editNode(node.id)}">Edit</button>
                                                <button class="btn-ghost danger" @click="${() => this.deleteNode(node.id)}">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                `) : html`<tr><td colspan="4" class="empty-cell">No matching articles found.</td></tr>`}
                            </tbody>
                        </table>
                    </div>
                `}
            </div>

            <style>
                .lekhak-content-manager { font-family: 'Inter', sans-serif; color: #f1f5f9; }
                
                .view-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 2.5rem;
                    border-bottom: 1px solid #334155;
                    padding-bottom: 2rem;
                }
                .header-main h2 { font-family: 'Outfit'; font-size: 1.75rem; margin: 0; }
                .header-main p { color: #94a3b8; margin-top: 4px; }

                .header-actions { display: flex; gap: 1rem; }
                .search-field input {
                    padding: 0.75rem 1rem;
                    background: #1e293b;
                    border: 1px solid #334155;
                    border-radius: 8px;
                    color: white;
                    width: 260px;
                }
                .search-field input:focus { border-color: #6366f1; outline: none; }
                
                .btn-primary {
                    background: #6366f1;
                    color: white;
                    border: none;
                    padding: 0.75rem 1.25rem;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    font-family: 'Outfit';
                }

                .content-table-wrapper { background: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
                .data-table { width: 100%; border-collapse: collapse; }
                .data-table th { text-align: left; padding: 1.25rem; background: #0f172a; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; border-bottom: 1px solid #334155; }
                .data-table td { padding: 1.25rem; border-bottom: 1px solid #334155; }
                
                .col-title { font-weight: 600; color: #f8fafc; font-size: 1rem; }
                .col-date { color: #94a3b8; font-size: 0.85rem; }

                .badge {
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.7rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    background: #0f172a;
                    color: #94a3b8;
                }
                .badge.published { color: #4ade80; background: rgba(74, 222, 128, 0.1); }

                .actions-group { display: flex; gap: 8px; justify-content: flex-end; }
                .btn-ghost {
                    background: transparent;
                    border: 1px solid #475569;
                    color: #f1f5f9;
                    padding: 4px 12px;
                    border-radius: 4px;
                    font-size: 0.75rem;
                    cursor: pointer;
                }
                .btn-ghost.danger { color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }
                .btn-ghost:hover { background: #334155; }
                
                .empty-cell { padding: 4rem; text-align: center; color: #64748b; }
                .loading-panel { padding: 4rem; text-align: center; background: #1e293b; border-radius: 12px; border: 1px solid #334155; }
            </style>
        `;
    }

    editNode(id) {
        location.hash = `editor?id=${id}`;
    }

    async deleteNode(id) {
        if (confirm('Are you sure you want to delete this node?')) {
            this.admin.notify('Delete action not yet implemented in API.', 'warning');
        }
    }
}
