import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta, renderBreadcrumbs } from './lekhak-nav.js';

/**
 * ContentView - Content Management Repository for Lekhak CMS
 * Features: bulk operations, column sorting, pagination, bundle filtering, search.
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
            statusTab: 'all',
            selectedIds: new Set(),
            sortCol: 'changed',
            sortOrder: 'DESC',
            page: 1,
            pages: 1,
            total: 0,
            bundleFilter: '',
            bundles: []
        };

        // SPPEX: Shared navigation handlers (replaces 9 duplicated lines)
        registerNavHandlers();
        setPageMeta('Content', 'Manage all content items in Lekhak CMS');

        window.__spp_handlers['status-all'] = () => this.setState({ statusTab: 'all', page: 1 });
        window.__spp_handlers['status-published'] = () => this.setState({ statusTab: 'published', page: 1 });
        window.__spp_handlers['status-draft'] = () => this.setState({ statusTab: 'draft', page: 1 });

        this._boundPageNav = () => this.fetchNodes();
        window.addEventListener('drishyam:page_navigated', this._boundPageNav);
    }

    onDestroy() {
        window.removeEventListener('drishyam:page_navigated', this._boundPageNav);
    }

    async onMount() {
        await this.fetchNodes();
    }

    async fetchData() { return this.fetchNodes(); }

    async fetchNodes() {
        try {
            const res = await this.api.listNodes({
                page: this.state.page,
                limit: 20,
                sort: this.state.sortCol,
                order: this.state.sortOrder,
                bundle: this.state.bundleFilter
            });
            if (res.success) {
                const nodes = res.nodes || [];
                const bundleSet = new Set(nodes.map(n => n.bundle).filter(Boolean));
                this.state.bundles.forEach(b => bundleSet.add(b));
                this.setState({
                    nodes,
                    loading: false,
                    total: res.total || nodes.length,
                    page: res.page || 1,
                    pages: res.pages || 1,
                    bundles: Array.from(bundleSet),
                    selectedIds: new Set()
                });
            }
        } catch (e) {
            this.admin.notify('Failed to load content list', 'error');
            this.setState({ loading: false });
        }
    }

    toggleSort(col) {
        const newOrder = (this.state.sortCol === col && this.state.sortOrder === 'DESC') ? 'ASC' : 'DESC';
        this.state.sortCol = col;
        this.state.sortOrder = newOrder;
        this.state.page = 1;
        this.fetchNodes();
    }

    toggleSelect(id) {
        const s = new Set(this.state.selectedIds);
        if (s.has(id)) s.delete(id); else s.add(id);
        this.setState({ selectedIds: s });
    }

    toggleSelectAll() {
        const filtered = this._getFilteredNodes();
        const allSelected = filtered.length > 0 && filtered.every(n => this.state.selectedIds.has(n.id));
        const s = new Set();
        if (!allSelected) filtered.forEach(n => s.add(n.id));
        this.setState({ selectedIds: s });
    }

    async bulkAction(operation) {
        const ids = Array.from(this.state.selectedIds);
        if (!ids.length) return;
        const labels = { delete: 'delete', publish: 'publish', unpublish: 'unpublish' };
        if (!confirm(`Are you sure you want to ${labels[operation]} ${ids.length} item(s)?`)) return;
        try {
            const res = await this.api.bulkAction({ operation, ids });
            if (res.success) {
                this.admin.notify(res.message || `${res.affected} items affected.`, 'success');
                await this.fetchNodes();
            } else {
                this.admin.notify(res.message || 'Bulk operation failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Bulk operation error.', 'error');
        }
    }

    _getFilteredNodes() {
        const { nodes, filter, statusTab } = this.state;
        return nodes.filter(n => {
            const matchesSearch = !filter || n.title.toLowerCase().includes(filter.toLowerCase());
            if (!matchesSearch) return false;
            if (statusTab === 'published') return n.status === 'published';
            if (statusTab === 'draft') return n.status !== 'published';
            return true;
        });
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

    render() {
        return { content: '' };
    }

    afterUpdate() {
        // SPPEX.Skeleton: Show shimmering loaders while data is loading
        if (this.state.loading && typeof SPPEX !== 'undefined' && SPPEX.Skeleton) {
            const tableRows = document.getElementById('spp-content-table-rows');
            if (tableRows && !tableRows.querySelector('.sppex-skeleton')) {
                tableRows.innerHTML = `<tr><td colspan="6">${SPPEX.Skeleton.render(8)}</td></tr>`;
            }
            return;
        }

        // SPPEX.Breadcrumbs: Render navigation trail
        const breadcrumbSlot = document.getElementById('spp-content-breadcrumbs');
        if (breadcrumbSlot && !breadcrumbSlot._rendered) {
            breadcrumbSlot.innerHTML = renderBreadcrumbs('Content');
            breadcrumbSlot._rendered = true;
        }

        const searchInput = document.getElementById('spp-content-filter-input');
        if (searchInput && !searchInput._bound) {
            searchInput.value = this.state.filter || '';
            searchInput.oninput = (e) => { this.state.filter = e.target.value; this._renderTable(); };
            searchInput._bound = true;
        }

        // Bundle filter dropdown
        const bundleSel = document.getElementById('spp-content-bundle-filter');
        if (bundleSel && !bundleSel._bound) {
            bundleSel.innerHTML = '<option value="">All Types</option>';
            this.state.bundles.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b; opt.textContent = b;
                if (b === this.state.bundleFilter) opt.selected = true;
                bundleSel.appendChild(opt);
            });
            bundleSel.onchange = (e) => { this.state.bundleFilter = e.target.value; this.state.page = 1; this.fetchNodes(); };
            bundleSel._bound = true;
        }

        // Sort headers
        document.querySelectorAll('[data-sort-col]').forEach(th => {
            if (!th._bound) {
                th.onclick = () => this.toggleSort(th.dataset.sortCol);
                th.style.cursor = 'pointer';
                th._bound = true;
            }
            const arrow = this.state.sortCol === th.dataset.sortCol ? (this.state.sortOrder === 'ASC' ? ' ↑' : ' ↓') : '';
            const label = th.dataset.sortLabel || th.textContent.replace(/[↑↓]/g, '').trim();
            th.textContent = label + arrow;
            th.dataset.sortLabel = label;
        });

        // Master checkbox
        const masterCb = document.getElementById('spp-content-master-cb');
        if (masterCb && !masterCb._bound) {
            masterCb.onchange = () => this.toggleSelectAll();
            masterCb._bound = true;
        }

        // Bulk actions bar
        this._renderBulkBar();
        this._renderTable();
        this._renderPagination();
    }

    _renderBulkBar() {
        const bar = document.getElementById('spp-content-bulk-bar');
        if (!bar) return;
        const count = this.state.selectedIds.size;
        if (count === 0) {
            bar.style.display = 'none';
            return;
        }
        bar.style.display = 'flex';
        bar.innerHTML = `
            <span style="font-size:0.85rem; font-weight:600;">${count} item(s) selected</span>
            <div style="display:flex; gap:6px;">
                <button class="btn-bulk publish-sel" style="background:#4ade80; color:#0f172a; border:none; padding:5px 12px; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer;">Publish</button>
                <button class="btn-bulk unpublish-sel" style="background:#facc15; color:#0f172a; border:none; padding:5px 12px; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer;">Unpublish</button>
                <button class="btn-bulk delete-sel" style="background:#ef4444; color:white; border:none; padding:5px 12px; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer;">Delete</button>
            </div>
        `;
        bar.querySelector('.publish-sel').onclick = () => this.bulkAction('publish');
        bar.querySelector('.unpublish-sel').onclick = () => this.bulkAction('unpublish');
        bar.querySelector('.delete-sel').onclick = () => this.bulkAction('delete');
    }

    _renderTable() {
        const tableRows = document.getElementById('spp-content-table-rows');
        if (!tableRows) return;

        const filteredNodes = this._getFilteredNodes();

        // Update master checkbox
        const masterCb = document.getElementById('spp-content-master-cb');
        if (masterCb) masterCb.checked = filteredNodes.length > 0 && filteredNodes.every(n => this.state.selectedIds.has(n.id));

        if (filteredNodes.length === 0) {
            tableRows.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-dim);">
                <span style="font-size:1.5rem; display:block; margin-bottom:8px;">📭</span>No content items found.</td></tr>`;
            return;
        }

        tableRows.innerHTML = '';
        filteredNodes.forEach(node => {
            const tr = document.createElement('tr');
            tr.className = 'data-row';
            const checked = this.state.selectedIds.has(node.id) ? 'checked' : '';
            tr.innerHTML = `
                <td class="col-checkbox"><input type="checkbox" class="row-cb" data-id="${node.id}" ${checked}></td>
                <td class="col-indicator"><div class="row-marker ${node.status}"></div></td>
                <td class="col-title">
                    <div class="title-text">${node.title}</div>
                    <div class="node-id-label">#${node.id} · ${node.bundle || 'Article'}</div>
                </td>
                <td class="col-status"><span class="lekhak-status-tag ${node.status}">${node.status}</span></td>
                <td class="col-date"><span class="date-string" title="${node.changed}">${this._relativeTime(node.changed)}</span></td>
                <td class="col-actions" style="text-align:right;">
                    <div class="lekhak-operations-group">
                        <a href="${this.admin.config?.baseUrl || ''}/node/${node.id}" target="_blank" class="btn-operation" onclick="event.stopPropagation()">View</a>
                        <button class="btn-operation highlight edit-btn">Edit</button>
                        <button class="btn-operation danger del-btn">Delete</button>
                    </div>
                </td>
            `;
            tr.onclick = (e) => {
                if (e.target.closest('.row-cb') || e.target.closest('.btn-operation')) return;
                this.editNode(node.id);
            };
            tr.querySelector('.row-cb').onchange = (e) => { e.stopPropagation(); this.toggleSelect(node.id); };
            tr.querySelector('.edit-btn').onclick = (e) => { e.stopPropagation(); this.editNode(node.id); };
            tr.querySelector('.del-btn').onclick = (e) => { e.stopPropagation(); this.deleteNode(node.id); };
            tableRows.appendChild(tr);
        });
    }

    _renderPagination() {
        const pag = document.getElementById('spp-content-pagination');
        if (!pag) return;
        const { page, pages, total } = this.state;
        if (pages <= 1) { pag.style.display = 'none'; return; }
        pag.style.display = 'flex';
        pag.innerHTML = `
            <span style="font-size:0.8rem; color:var(--text-dim);">Page ${page} of ${pages} (${total} items)</span>
            <div style="display:flex; gap:4px;">
                <button class="btn-operation pag-prev" ${page <= 1 ? 'disabled' : ''}>← Prev</button>
                <button class="btn-operation pag-next" ${page >= pages ? 'disabled' : ''}>Next →</button>
            </div>
        `;
        pag.querySelector('.pag-prev').onclick = () => { if (page > 1) { this.state.page = page - 1; this.fetchNodes(); } };
        pag.querySelector('.pag-next').onclick = () => { if (page < pages) { this.state.page = page + 1; this.fetchNodes(); } };
    }

    editNode(id) { location.hash = `editor?id=${id}`; }

    async deleteNode(id) {
        if (confirm(`Delete content item #${id}?`)) {
            try {
                const res = await this.api.deleteNode({ id });
                if (res?.success) {
                    this.admin.notify('Content deleted.', 'success');
                    await this.fetchNodes();
                } else {
                    this.admin.notify(res?.message || 'Delete failed.', 'error');
                }
            } catch (e) {
                this.admin.notify('Delete error.', 'error');
            }
        }
    }
}
