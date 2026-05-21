import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta, renderBreadcrumbs } from './lekhak-nav.js';

/**
 * LekhakView - Dashboard for Lekhak CMS
 * Features: system status, quick actions, recent activity with relative timestamps.
 */
export default class LekhakView extends BaseComponent {
    constructor(admin, container, props = {}) {
        super(admin, container, { ...props, apiBase: '../../src/lekhak/resources/admin-api.php' });
    }

    async onInit() {
        this.state = {
            stats: { total: 0, published: 0, drafts: 0, engagement: 0 },
            recent: [],
            systemStatus: null,
            loading: true,
            activeTab: 'overview'
        };

        if (this.admin.selectedApp !== 'lekhak') {
            this.admin.onAppContextChange?.('lekhak');
        }

        // SPPEX: Shared navigation handlers (replaces 9 duplicated lines)
        registerNavHandlers();
        setPageMeta('Dashboard', 'Lekhak CMS administration dashboard');

        window.__spp_handlers['tab-overview'] = () => this.setState({ activeTab: 'overview' });
        window.__spp_handlers['tab-published'] = () => this.setState({ activeTab: 'published' });
        window.__spp_handlers['tab-drafts'] = () => this.setState({ activeTab: 'drafts' });
        window.__spp_handlers['nav-master'] = () => location.hash = 'content';
        window.__spp_handlers['qa-new-article'] = () => location.hash = 'editor';
        window.__spp_handlers['qa-view-site'] = () => {
            const base = this.admin?.config?.baseUrl || '';
            window.open(base + '/', '_blank');
        };

        this._boundPageNav = () => this.fetchData();
        window.addEventListener('drishyam:page_navigated', this._boundPageNav);
    }

    onDestroy() {
        window.removeEventListener('drishyam:page_navigated', this._boundPageNav);
    }

    async onMount() { await this.fetchData(); }

    async fetchData() {
        // SPPEX.Query: Cached data fetching with stale-while-revalidate
        if (typeof SPPEX !== 'undefined' && SPPEX.Query) {
            try {
                const [statsQuery, statusQuery] = await Promise.all([
                    SPPEX.Query.use('dashboard-stats', () => this.api.getDashboardStats(), { staleTime: 10000, component: this }),
                    SPPEX.Query.use('system-status', () => this.api.getSystemStatus({}, { lock: false }), { staleTime: 30000, component: this })
                ]);
                this.setState({
                    stats: statsQuery.data?.stats || this.state.stats,
                    recent: statsQuery.data?.recent || [],
                    systemStatus: statusQuery.data?.status || null,
                    loading: false
                });
            } catch (e) {
                console.error('Dashboard fetch error:', e);
                this.setState({ loading: false });
            }
        } else {
            // Fallback: original fetch logic
            try {
                const [statsRes, statusRes] = await Promise.all([
                    this.api.getDashboardStats(),
                    this.api.getSystemStatus({}, { lock: false })
                ]);
                this.setState({
                    stats: statsRes?.stats || this.state.stats,
                    recent: statsRes?.recent || [],
                    systemStatus: statusRes?.status || null,
                    loading: false
                });
            } catch (e) {
                console.error('Dashboard fetch error:', e);
                this.setState({ loading: false });
            }
        }
    }

    _relativeTime(dateStr) {
        if (!dateStr) return '';
        const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return dateStr.split(' ')[0];
    }

    render() { return { content: '' }; }

    afterUpdate() {
        // SPPEX.Skeleton: Show shimmering loaders while data is loading
        if (this.state.loading && typeof SPPEX !== 'undefined' && SPPEX.Skeleton) {
            const skelTarget = document.getElementById('spp-lekhak-stream-rows');
            if (skelTarget && !skelTarget.querySelector('.sppex-skeleton')) {
                skelTarget.innerHTML = SPPEX.Skeleton.render(4);
            }
            return; // Don't render real data yet
        }

        // SPPEX.Breadcrumbs: Render navigation trail
        const breadcrumbSlot = document.getElementById('spp-dashboard-breadcrumbs');
        if (breadcrumbSlot && !breadcrumbSlot._rendered) {
            breadcrumbSlot.innerHTML = renderBreadcrumbs('Dashboard');
            breadcrumbSlot._rendered = true;
        }

        // Stats cards
        const elTotal = document.getElementById('spp-stat-total');
        const elPub = document.getElementById('spp-stat-published');
        const elDraft = document.getElementById('spp-stat-drafts');
        const elEng = document.getElementById('spp-stat-engagement');
        if (elTotal) elTotal.textContent = this.state.stats.total;
        if (elPub) elPub.textContent = this.state.stats.published;
        if (elDraft) elDraft.textContent = this.state.stats.drafts;
        if (elEng) elEng.textContent = this.state.stats.engagement + '%';

        // System status
        const statusContainer = document.getElementById('spp-system-status');
        if (statusContainer && this.state.systemStatus) {
            const s = this.state.systemStatus;
            statusContainer.innerHTML = `
                <div class="status-row"><span>PHP Version</span><span class="status-val">${s.php_version}</span></div>
                <div class="status-row"><span>Database</span><span class="status-val">${s.db_engine}</span></div>
                <div class="status-row"><span>Active Theme</span><span class="status-val">${s.active_theme}</span></div>
                <div class="status-row"><span>Media Storage</span><span class="status-val">${s.media_disk}</span></div>
                <div class="status-row"><span>Server</span><span class="status-val">${s.server}</span></div>
                <div class="status-row"><span>Memory Limit</span><span class="status-val">${s.memory_limit}</span></div>
            `;
        }

        // Recent activity
        const rowsContainer = document.getElementById('spp-lekhak-stream-rows');
        if (!rowsContainer) return;

        const { recent, activeTab } = this.state;
        const displayed = recent.filter(n => {
            if (activeTab === 'published') return n.status === 'published';
            if (activeTab === 'drafts') return n.status !== 'published';
            return true;
        });

        if (!displayed.length) {
            rowsContainer.innerHTML = `<div class="lekhak-empty-stream"><span style="font-size:1.5rem;margin-bottom:8px;display:block;">📭</span><span>No content items found.</span></div>`;
            return;
        }

        rowsContainer.innerHTML = '';
        displayed.forEach(node => {
            const div = document.createElement('div');
            div.className = 'stream-row';
            div.innerHTML = `
                <div class="status-marker ${node.status}"></div>
                <div class="stream-info">
                    <div class="stream-title">${node.title}</div>
                    <div class="stream-meta">${this._relativeTime(node.changed)} · ${node.bundle || 'Article'}</div>
                </div>
                <div class="badge-status ${node.status}">${node.status}</div>
                <div class="lekhak-actions-dropdown">
                    <button class="btn-action-pill highlight edit-btn">Edit</button>
                </div>
            `;
            div.onclick = () => location.hash = `editor?id=${node.id}`;
            div.querySelector('.edit-btn').onclick = (e) => { e.stopPropagation(); location.hash = `editor?id=${node.id}`; };
            rowsContainer.appendChild(div);
        });
    }
}
