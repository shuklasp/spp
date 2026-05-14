import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';

/**
 * ContentView - Content Management Repository View for Lekhak CMS
 * Redesigned with intuitive, responsive premium administrative principles.
 */
export default class ContentView extends BaseComponent {
    constructor(admin, container, props = {}) {
        super(admin, container, { ...props, apiBase: '../../src/lekhak/resources/admin-api.php' });
    }

    async onInit() {
        this.state = {
            nodes: [],
            loading: true,
            filter: '',
            statusTab: 'all' // Local task subset filter: 'all', 'published', 'draft'
        };

        // Extract native handlers mapping for standalone decoupled layout
        window.__spp_handlers = window.__spp_handlers || {};
        window.__spp_handlers['nav-lekhak'] = () => location.hash = 'lekhak';
        window.__spp_handlers['nav-content'] = () => location.hash = 'content';
        window.__spp_handlers['nav-canvas'] = () => location.hash = 'canvas';
        window.__spp_handlers['nav-settings'] = () => location.hash = 'settings';
        window.__spp_handlers['nav-editor'] = () => location.hash = 'editor';
        window.__spp_handlers['status-all'] = () => this.setState({ statusTab: 'all' });
        window.__spp_handlers['status-published'] = () => this.setState({ statusTab: 'published' });
        window.__spp_handlers['status-draft'] = () => this.setState({ statusTab: 'draft' });

        // Subscribe to global Drishyam universal SPA hot navigation events
        window.addEventListener('drishyam:page_navigated', () => {
            this.fetchNodes();
        });
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
            this.admin.notify('Failed to load content repository list', 'error');
            this.setState({ loading: false });
        }
    }

    render() {
        // Return blank object trigger instructing BaseComponent to ingest pre-warmed template headers
        return { content: '' };
    }

    afterUpdate() {
        // Bind search input real-time handler natively
        const searchInput = document.getElementById('spp-content-filter-input');
        if (searchInput && !searchInput.oninput) {
            searchInput.value = this.state.filter || '';
            searchInput.oninput = (e) => this.setState({ filter: e.target.value });
        }

        const tableRows = document.getElementById('spp-content-table-rows');
        if (!tableRows) return;

        const { nodes, filter, statusTab } = this.state;
        const filteredNodes = nodes.filter(n => {
            const matchesSearch = n.title.toLowerCase().includes(filter.toLowerCase());
            if (!matchesSearch) return false;
            if (statusTab === 'published') return n.status === 'published';
            if (statusTab === 'draft') return n.status !== 'published';
            return true;
        });

        if (filteredNodes.length === 0) {
            tableRows.innerHTML = `
                <tr>
                    <td colspan="5" class="empty-table-cell">
                        <span style="font-size: 1.5rem; display: block; margin-bottom: 8px;">📭</span>
                        <span>No items matching filter query parameters parsed.</span>
                    </td>
                </tr>
            `;
            return;
        }

        tableRows.innerHTML = '';
        filteredNodes.forEach(node => {
            const tr = document.createElement('tr');
            tr.className = 'data-row';
            tr.innerHTML = `
                <td class="col-indicator">
                    <div class="row-marker ${node.status}"></div>
                </td>
                <td class="col-title">
                    <div class="title-text">${node.title}</div>
                    <div class="node-id-label">Entity Key ID: #${node.id}</div>
                </td>
                <td class="col-status">
                    <span class="lekhak-status-tag ${node.status}">${node.status}</span>
                </td>
                <td class="col-date">
                    <span class="date-string">${node.changed}</span>
                </td>
                <td class="col-actions" style="text-align: right;">
                    <div class="lekhak-operations-group">
                        <a href="${this.admin.config.baseUrl}/node/${node.id}" target="_blank" class="btn-operation" onclick="event.stopPropagation()">Preview</a>
                        <button class="btn-operation highlight edit-btn">Edit</button>
                        <button class="btn-operation danger del-btn">Delete</button>
                    </div>
                </td>
            `;
            tr.onclick = () => this.editNode(node.id);
            const editBtn = tr.querySelector('.edit-btn');
            if (editBtn) {
                editBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.editNode(node.id);
                };
            }
            const delBtn = tr.querySelector('.del-btn');
            if (delBtn) {
                delBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.deleteNode(node.id);
                };
            }
            tableRows.appendChild(tr);
        });
    }

    editNode(id) {
        location.hash = `editor?id=${id}`;
    }

    async deleteNode(id) {
        if (confirm(`Confirm removing persistent entity ID key #${id} from the master database table array?`)) {
            this.admin.notify('Delete pipeline sequence invocation queued.', 'info');
        }
    }
}
