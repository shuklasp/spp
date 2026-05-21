import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta, renderBreadcrumbs } from './lekhak-nav.js';

/**
 * Lekhak Translations View Controller
 * Locales are persisted to locales.yml via the admin API.
 */
export default class TranslationsView extends BaseComponent {
    async onInit(params = {}) {
        this.state = { locales: [], loading: true };

        // SPPEX: Shared navigation handlers (replaces 9 duplicated lines)
        registerNavHandlers();
        setPageMeta('Translations', 'Manage locales and translation progress');
    }

    async onMount() { await this.fetchLocales(); }

    async fetchLocales() {
        try {
            const res = await this.api.getLocales();
            if (res.success) this.setState({ locales: res.locales || [], loading: false });
        } catch (e) {
            console.error('Translations fetch error:', e);
            this.setState({ loading: false });
        }
    }

    async addLocale() {
        const name = prompt("Locale name (e.g., German (Germany)):");
        if (!name) return;
        const flag = prompt("Flag emoji:", "🌐") || "🌐";
        const code = prompt("Locale code:", "de_DE") || "de_DE";
        const id = code.toLowerCase().replace('_', '-');

        if (this.state.locales.some(l => l.id === id)) {
            this.admin?.notify?.("Locale already exists.", "error");
            return;
        }

        try {
            await this.api.saveLocale({ id, flag, name, code, progress: 0, status: 'ghost', statusLabel: 'Not Started' });
            this.admin?.notify?.("Locale added.", "success");
            await this.fetchLocales();
        } catch (e) {
            this.admin?.notify?.("Failed to add locale.", "error");
        }
    }

    async updateProgress(loc) {
        const val = prompt(`Translation progress for ${loc.name} (0-100):`, loc.progress);
        if (val === null) return;
        const progress = Math.min(100, Math.max(0, parseInt(val, 10) || 0));
        const status = progress === 100 ? 'active' : (progress > 0 ? 'warning' : 'ghost');
        const statusLabel = progress === 100 ? 'Active' : (progress > 0 ? 'In Progress' : 'Not Started');
        try {
            await this.api.saveLocale({ ...loc, progress, status, statusLabel });
            this.admin?.notify?.("Progress updated.", "success");
            await this.fetchLocales();
        } catch (e) {
            this.admin?.notify?.("Update failed.", "error");
        }
    }

    async deleteLocale(id) {
        if (!confirm("Remove this locale?")) return;
        try {
            await this.api.deleteLocale({ id });
            this.admin?.notify?.("Locale removed.", "info");
            await this.fetchLocales();
        } catch (e) {
            this.admin?.notify?.("Delete failed.", "error");
        }
    }

    render() { return { content: '' }; }

    afterUpdate() {
        const countEl = document.getElementById('spp-trans-count');
        if (countEl) countEl.textContent = this.state.locales.length;

        // SPPEX.Breadcrumbs: Render navigation trail
        const breadcrumbSlot = document.getElementById('spp-translations-breadcrumbs');
        if (breadcrumbSlot && !breadcrumbSlot._rendered) {
            breadcrumbSlot.innerHTML = renderBreadcrumbs('Translations');
            breadcrumbSlot._rendered = true;
        }

        const bodyEl = document.getElementById('spp-translations-container-body');
        if (!bodyEl) return;

        const addBtn = document.getElementById('spp-translations-add-btn');
        if (addBtn && !addBtn._bound) { addBtn.onclick = () => this.addLocale(); addBtn._bound = true; }

        bodyEl.innerHTML = '';
        this.state.locales.forEach(loc => {
            const tr = document.createElement('tr');
            tr.className = 'data-row';
            const badgeClass = loc.status === 'active' ? 'badge-success' : (loc.status === 'warning' ? 'badge-warning' : 'badge-ghost');
            tr.innerHTML = `
                <td class="col-indicator"><div class="row-marker ${loc.status}"></div></td>
                <td class="col-title">
                    <div class="title-text">
                        <span style="font-size:1.2rem;">${loc.flag}</span>
                        <span>${loc.name}</span>
                        <span class="locale-code">${loc.code}</span>
                    </div>
                </td>
                <td class="col-status"><span class="badge ${badgeClass}">${loc.statusLabel || loc.status}</span></td>
                <td class="col-date">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div id="sppex-progress-${loc.id}" style="flex:1;"></div>
                        <span style="font-size:0.8rem;font-weight:600;color:var(--text-dim);">${loc.progress}%</span>
                    </div>
                </td>
                <td class="col-actions" style="text-align:right;">
                    <div class="lekhak-operations-group">
                        <button class="btn-operation highlight translate-btn">Translate</button>
                        <button class="btn-operation del-loc" style="color:#ef4444;">Remove</button>
                    </div>
                </td>`;
            tr.querySelector('.translate-btn').onclick = () => this.updateProgress(loc);
            tr.querySelector('.del-loc').onclick = () => this.deleteLocale(loc.id);
            bodyEl.appendChild(tr);

            // SPPEX.ProgressBar: Replace manual progress bar with native module
            if (typeof SPPEX !== 'undefined' && SPPEX.ProgressBar) {
                SPPEX.ProgressBar.linear(`#sppex-progress-${loc.id}`, loc.progress);
            }
        });
    }
}
