import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';

/**
 * LekhakView - Modern Proprietary Responsive Dashboard for Lekhak CMS
 */
export default class LekhakView extends BaseComponent {
    constructor(admin, container, props = {}) {
        super(admin, container, { ...props, apiBase: '../../src/lekhak/resources/admin-api.php' });
    }

    async onInit() {
        this.state = {
            stats: { total: 0, published: 0, drafts: 0, engagement: 0 },
            recent: [],
            loading: true,
            activeTab: 'overview', // Local Tasks switcher: 'overview', 'published', 'drafts'
            toolbarExpanded: false
        };
        
        // Ensure we are operating smoothly within the Lekhak application workspace runtime context
        if (this.admin.selectedApp !== 'lekhak') {
            this.admin.onAppContextChange('lekhak');
        }

        // Global routing and tab bindings extracted cleanly
        window.__spp_handlers = window.__spp_handlers || {};
        window.__spp_handlers['nav-lekhak'] = () => location.hash = 'lekhak';
        window.__spp_handlers['nav-canvas'] = () => location.hash = 'canvas';
        window.__spp_handlers['nav-settings'] = () => location.hash = 'settings';
        window.__spp_handlers['nav-clear-cache'] = () => this.admin.notify("Clearing caches routing map...", "success");
        window.__spp_handlers['nav-editor'] = () => location.hash = 'editor';
        window.__spp_handlers['tab-overview'] = () => this.setState({ activeTab: 'overview' });
        window.__spp_handlers['tab-published'] = () => this.setState({ activeTab: 'published' });
        window.__spp_handlers['tab-drafts'] = () => this.setState({ activeTab: 'drafts' });
        window.__spp_handlers['nav-master'] = () => location.hash = 'content';
        window.__spp_handlers['tool-rebuild'] = () => this.admin.notify('Index search scan finished.', 'success');

        // Subscribe to global Drishyam universal SPA hot navigation events
        window.addEventListener('drishyam:page_navigated', () => {
            this.fetchData();
        });
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
            this.admin.notify(`Failed to load workspace data: ${e.message}`, 'error');
            this.setState({ loading: false });
        }
    }

    render() {
        // Trigger automagic template framework routing fallback injection natively
        return { content: '' };
    }

    afterUpdate() {
        const rowsContainer = document.getElementById('spp-lekhak-stream-rows');
        if (!rowsContainer) return;

        const { recent, activeTab } = this.state;
        const displayedList = recent.filter(node => {
            if (activeTab === 'published') return node.status === 'published';
            if (activeTab === 'drafts') return node.status !== 'published';
            return true;
        });

        if (displayedList.length === 0) {
            rowsContainer.innerHTML = `
                <div class="lekhak-empty-stream">
                    <span style="font-size: 1.5rem; margin-bottom: 8px; display: block;">📭</span>
                    <span>No items matching local task parameters filter discovered.</span>
                </div>
            `;
            return;
        }

        rowsContainer.innerHTML = '';
        displayedList.forEach(node => {
            const div = document.createElement('div');
            div.className = 'stream-row';
            div.innerHTML = `
                <div class="status-marker ${node.status}"></div>
                <div class="stream-info">
                    <div class="stream-title">${node.title}</div>
                    <div class="stream-meta">Modified payload stream: ${node.changed}</div>
                </div>
                <div class="badge-status ${node.status}">${node.status}</div>
                <div class="lekhak-actions-dropdown">
                    <a href="${this.admin.config.baseUrl}/node/${node.id}" target="_blank" class="btn-action-pill" onclick="event.stopPropagation()">Preview</a>
                    <button class="btn-action-pill highlight edit-btn">Edit</button>
                </div>
            `;
            div.onclick = () => this.editNode(node.id);
            const editBtn = div.querySelector('.edit-btn');
            if (editBtn) {
                editBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.editNode(node.id);
                };
            }
            rowsContainer.appendChild(div);
        });
    }

    editNode(id) {
        location.hash = `editor?id=${id}`;
    }
}
