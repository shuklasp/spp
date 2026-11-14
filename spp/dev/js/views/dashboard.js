/**
 * DashboardView - SPP Developer Studio Hub
 * 
 * Re-architected for the modern SPP Dev Studio structure:
 *  - Context-Aware Active Application Telemetry & Quick Switcher
 *  - Executive Metrics Matrix (SPP Studio Pages, Declarative Data, Ecosystem, PHP Engine)
 *  - 3 Core Structural Pillars (12 enterprise tools total):
 *      1. Developer Studio Suite (Code Studio & Scaffolder, Web Tinker REPL)
 *      2. Declarative Architecture & Data (SPP Studio Canvas, Workflows, Entities/XDB, Forms, Routing, Reports)
 *      3. Runtime, AI & Testing (AI Copilot & Playground, Integration Mesh, Parikshak Testing, CLI Commands)
 *  - High-Contrast Theme System (WCAG AAA in Day, Night, and Saffron modes)
 *  - Modular Card Header Architecture (prevents text clipping & flex squishing)
 *  - Live Subsystem Health Monitoring with Instant Diagnostic Refresh
 *  - Interactive Module Filter Pills & Real-Time Fuzzy Search
 *  - Recent Audit Activity Stream with Responsive Event Ledger
 *  - Keyboard Shortcuts Cheatsheet
 */

/**
 * StudioLiveTelemetry - Autonomous Live Component for SPP Studio Health & Telemetry
 * 
 * Features:
 *  - Non-blocking asynchronous diagnostic telemetry polling
 *  - Native Live Component lifecycle with zero full-view re-render overhead
 *  - 1s dynamic human-readable elapsed ticker ("Updated just now", "Updated 12s ago")
 *  - 30s background health polling with { lock: false } to guarantee 0 UI freezing
 *  - Instant manual refresh with smooth 360deg CSS rotation
 *  - Tab visibility synchronization (resumes/syncs when tab becomes visible)
 *  - Event broadcasting via 'studio:telemetry-refreshed'
 */
export class StudioLiveTelemetry extends BaseComponent {
    async onInit() {
        const app = this.props.selectedApp || this.props.dataApp || this.props.app || this.selectedApp || 'default';
        this.state = {
            health: this.props.health || null,
            lastRefreshed: this.props.lastRefreshed || Date.now(),
            isRefreshing: false,
            selectedApp: app
        };

        this._refreshInterval = null;
        this._tickInterval = null;
        this._visibilityHandler = null;

        if (!this.state.health) {
            await this.refresh(false);
        }

        this.startAutoRefresh();
    }

    onAttributeChanged(name, oldValue, newValue) {
        if ((name === 'data-app' || name === 'dataApp' || name === 'app') && newValue && oldValue !== newValue) {
            this.setState({ selectedApp: newValue });
            this.refresh(false);
        }
    }

    setContextApp(newApp) {
        if (!newApp || newApp === this.state.selectedApp) return;
        this.setState({ selectedApp: newApp });
        this.refresh(false);
    }

    onDestroy() {
        if (this._refreshInterval) {
            clearInterval(this._refreshInterval);
            this._refreshInterval = null;
        }
        if (this._tickInterval) {
            clearInterval(this._tickInterval);
            this._tickInterval = null;
        }
        if (this._visibilityHandler && typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', this._visibilityHandler);
            this._visibilityHandler = null;
        }
    }

    startAutoRefresh() {
        this.onDestroy();

        // 1. Precise 1-second dynamic elapsed time ticker
        this._tickInterval = setInterval(() => {
            if (this.state.lastRefreshed) {
                const el = this.container?.querySelector?.('.refresh-seconds');
                if (el) {
                    el.textContent = this.formatElapsedTime(this.state.lastRefreshed);
                }
            }
        }, 1000);

        // 2. Active 30-second background polling for live subsystem health (non-blocking)
        this._refreshInterval = setInterval(() => {
            if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
                this.refresh(false);
            }
        }, 30000);

        // 3. Auto-sync immediately when returning to the tab
        this._visibilityHandler = () => {
            if (typeof document !== 'undefined' && document.visibilityState === 'visible' && this.state.lastRefreshed) {
                const elapsed = (Date.now() - this.state.lastRefreshed) / 1000;
                if (elapsed >= 30) {
                    this.refresh(false);
                }
            }
        };
        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', this._visibilityHandler);
        }
    }

    formatElapsedTime(timestamp) {
        if (!timestamp) return 'Updated just now';
        const elapsed = Math.floor((Date.now() - timestamp) / 1000);
        if (elapsed < 4) return 'Updated just now';
        if (elapsed < 60) return `Updated ${elapsed}s ago`;
        const mins = Math.floor(elapsed / 60);
        return `Updated ${mins}m ago`;
    }

    async refresh(isManual = false) {
        const btn = this.container?.querySelector?.('.refresh-btn');
        if (btn) btn.classList.add('spinning');
        this.setState({ isRefreshing: true });

        try {
            // ALWAYS pass lock: false so background/polling refreshes NEVER freeze the UI
            const res = await this.api('diagnostics_health', { appname: this.state.selectedApp }, { lock: false });
            const healthRaw = res && res.success ? res.data : null;
            const health = healthRaw?.health || healthRaw || this.state.health;

            this.setState({
                health,
                lastRefreshed: Date.now(),
                isRefreshing: false
            });

            const el = this.container?.querySelector?.('.refresh-seconds');
            if (el) el.textContent = 'Updated just now';

            // Dispatch event for parent or sibling components without blocking
            if (typeof window !== 'undefined') {
                window.dispatchEvent(new CustomEvent('studio:telemetry-refreshed', {
                    detail: { health, isManual, app: this.state.selectedApp }
                }));
            }
        } catch (err) {
            console.error('[StudioLiveTelemetry] Telemetry refresh error:', err);
            this.setState({ isRefreshing: false });
        } finally {
            if (btn) {
                setTimeout(() => btn.classList.remove('spinning'), 500);
            }
        }
    }

    getStatusLabel(status) {
        if (!status) return 'Healthy';
        const s = String(status).toUpperCase();
        if (s === 'UP' || s === 'OK') return 'Healthy';
        if (s === 'DEGRADED') return 'Degraded';
        if (s === 'DOWN' || s === 'FAIL') return 'Down';
        return status;
    }

    getStatusColor(status) {
        if (!status) return '#4ade80';
        const s = String(status).toUpperCase();
        if (s === 'UP' || s === 'OK') return '#4ade80';
        if (s === 'DEGRADED') return '#fbbf24';
        if (s === 'DOWN' || s === 'FAIL') return '#f87171';
        return '#94a3b8';
    }

    getComponentLabel(name) {
        const labels = {
            database: 'Database',
            redis: 'Redis Cache',
            fs_var: 'Var Storage',
            fs_logs: 'Log Storage',
            system: 'PHP Engine',
            php_engine: 'PHP Engine',
            engine: 'PHP Engine',
            filesystem: 'Storage / Var'
        };
        return labels[name] || (name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' '));
    }

    render() {
        const health = this.state.health;
        const overallStatus = health?.status || 'UP';
        const statusColor = this.getStatusColor(overallStatus);
        const components = health?.components || {
            database: { status: 'UP' },
            redis: { status: 'UP' },
            fs_var: { status: 'UP' },
            fs_logs: { status: 'UP' },
            system: { status: 'UP' }
        };

        return html`
            <div class="studio-hero-meta" data-live-component="StudioLiveTelemetry" wire:poll.30s="refresh">
                <div class="health-pulse">
                    <div class="health-dot" style="background: ${statusColor}; box-shadow: 0 0 10px ${statusColor};"></div>
                    <span class="health-label" style="color: ${overallStatus === 'UP' || overallStatus === 'OK' ? '#4ade80' : '#f87171'};">
                        System ${this.getStatusLabel(overallStatus)}
                    </span>
                </div>

                <!-- Component Health Chips -->
                <div class="comp-chips-row">
                    ${Object.entries(components).map(([name, comp]) => {
                        const cStatus = typeof comp === 'string' ? comp : (comp?.status || 'UNKNOWN');
                        const cColor = this.getStatusColor(cStatus);
                        return html`
                            <div class="comp-chip" data-key="chip-${name}">
                                <div class="comp-dot" style="background: ${cColor}; box-shadow: 0 0 6px ${cColor};"></div>
                                <span>${this.getComponentLabel(name)}</span>
                                <span style="font-weight: 800; color: ${cColor}; font-size: 0.75rem;">${cStatus === 'UP' || cStatus === 'OK' ? 'UP' : this.getStatusLabel(cStatus)}</span>
                            </div>
                        `;
                    })}
                </div>

                <!-- Live Auto-Sync Health & Refresh Strip -->
                <div class="refresh-indicator">
                    <span class="live-sync-badge">
                        <span class="live-sync-dot"></span>
                        <span>Live (30s)</span>
                    </span>
                    <span class="refresh-seconds">${this.formatElapsedTime(this.state.lastRefreshed)}</span>
                    <button class="refresh-btn ${this.state.isRefreshing ? 'spinning' : ''}" @click=${() => this.refresh(true)} title="Refresh live telemetry now">🔄</button>
                </div>
            </div>
        `;
    }

    template() {
        return this.render();
    }
}

if (typeof customElements !== 'undefined' && !customElements.get('studio-live-telemetry')) {
    if (typeof defineElement === 'function') {
        defineElement('studio-live-telemetry', StudioLiveTelemetry, ['data-app']);
    } else if (window.SPPUX?.defineElement) {
        window.SPPUX.defineElement('studio-live-telemetry', StudioLiveTelemetry, ['data-app']);
    }
}

export default class DashboardView extends BaseComponent {
    async onInit() {
        const activeApp = this.app?.selectedApp || localStorage.getItem('spp_admin_selected_app') || 'default';
        this.state = {
            loading: true,
            health: null,
            appCount: 0,
            moduleCount: 0,
            entityCount: 0,
            pageCount: 0,
            workflowCount: 0,
            auditLogs: [],
            lastRefreshed: null,
            secondsAgo: 0,
            username: this.app?.user?.username || 'Developer',
            selectedApp: activeApp,
            availableApps: [],
            activePillarFilter: 'all',
            searchQuery: ''
        };

        this._telemetryListener = (e) => {
            if (e.detail?.health) {
                this.state.health = e.detail.health;
            }
            if (e.detail?.isManual) {
                this.refreshMetrics(false);
            }
        };
        if (typeof window !== 'undefined') {
            window.addEventListener('studio:telemetry-refreshed', this._telemetryListener);
        }

        await this.fetchAllData();
    }

    afterUpdate() {
        this.startTelemetryTimers();
    }

    startTelemetryTimers() {
        if (this._tickInterval) return;

        // 1. Precise 1-second dynamic elapsed ticker (non-blocking DOM update)
        this._tickInterval = setInterval(() => {
            if (this.state.lastRefreshed) {
                const el = this.container?.querySelector?.('#studio-hero-telemetry-slot .refresh-seconds');
                if (el) {
                    el.textContent = this.formatElapsedTime(this.state.lastRefreshed);
                }
            }
        }, 1000);

        // 2. Active 30-second background polling for live subsystem health (non-blocking)
        this._refreshInterval = setInterval(() => {
            if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
                this.refreshTelemetry(false);
            }
        }, 30000);

        // 3. Tab visibility synchronization
        this._visibilityHandler = () => {
            if (typeof document !== 'undefined' && document.visibilityState === 'visible' && this.state.lastRefreshed) {
                const elapsed = (Date.now() - this.state.lastRefreshed) / 1000;
                if (elapsed >= 30) {
                    this.refreshTelemetry(false);
                }
            }
        };
        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', this._visibilityHandler);
        }
    }

    onDeactivate() {
        if (this._tickInterval) {
            clearInterval(this._tickInterval);
            this._tickInterval = null;
        }
        if (this._refreshInterval) {
            clearInterval(this._refreshInterval);
            this._refreshInterval = null;
        }
        if (this._visibilityHandler && typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', this._visibilityHandler);
            this._visibilityHandler = null;
        }
    }

    onActivate() {
        this.startTelemetryTimers();
    }

    onDestroy() {
        this.onDeactivate();
        if (this._telemetryListener && typeof window !== 'undefined') {
            window.removeEventListener('studio:telemetry-refreshed', this._telemetryListener);
            this._telemetryListener = null;
        }
    }

    async onContextChange(newApp) {
        this.setState({ selectedApp: newApp, loading: true });
        await this.fetchAllData();
        this.update();
        await this.refreshTelemetry(false);
    }

    async refreshTelemetry(isManual = false) {
        const btn = this.container?.querySelector?.('#studio-hero-telemetry-slot .refresh-btn');
        if (btn) btn.classList.add('spinning');
        this.state.isRefreshing = true;

        try {
            // ALWAYS pass lock: false so background/polling refreshes NEVER freeze the UI
            const res = await this.api('diagnostics_health', { appname: this.state.selectedApp }, { lock: false });
            const healthRaw = res && res.success ? res.data : null;
            const health = healthRaw?.health || healthRaw || this.state.health;

            this.state.health = health;
            this.state.lastRefreshed = Date.now();
            this.state.isRefreshing = false;

            const slot = this.container?.querySelector?.('#studio-hero-telemetry-slot');
            if (slot && typeof render === 'function') {
                render(this.renderHeroTelemetry(health, this.state.selectedApp), slot);
            }

            const secEl = slot?.querySelector?.('.refresh-seconds');
            if (secEl) secEl.textContent = 'Updated just now';

            if (isManual) {
                await this.refreshMetrics(false);
            }
        } catch (err) {
            console.error('[DashboardView] Telemetry refresh error:', err);
            this.state.isRefreshing = false;
        } finally {
            if (btn) {
                setTimeout(() => btn.classList.remove('spinning'), 500);
            }
        }
    }

    async refreshMetrics(lock = false) {
        try {
            const currentApp = this.state.selectedApp || 'default';
            const metricsRes = await this.api('get_dashboard_metrics', { appname: currentApp }, { lock });
            if (metricsRes && metricsRes.success && metricsRes.data) {
                const d = metricsRes.data;
                const availableApps = d.apps || [];
                const auditLogs = (d.audit_logs || []).slice(0, 10);
                this.state.availableApps = availableApps;
                this.state.appCount = availableApps.length;
                this.state.moduleCount = (d.modules || []).length;
                this.state.entityCount = (d.entities || []).length;
                this.state.pageCount = (d.pages || []).length;
                this.state.workflowCount = (d.workflows || []).length;
                this.state.auditLogs = auditLogs;
                if (d.health) {
                    this.state.health = d.health;
                    this.state.lastRefreshed = Date.now();
                }
                this.updateMetricValues();
                this.updateAuditLogs(auditLogs);
                return;
            }

            const [appsRes, modsRes, entitiesRes, pagesRes, wfsRes, auditRes] = await Promise.allSettled([
                this.api('list_apps', {}, { lock }),
                this.api('list_modules', {}, { lock }),
                this.api('list_entities', { appname: currentApp, context: currentApp }, { lock }),
                this.api('list_pages', { appname: currentApp, context: currentApp }, { lock }),
                this.api('list_workflows', { appname: currentApp, context: currentApp }, { lock }),
                this.api('list_audit_logs', {}, { lock })
            ]);

            const availableApps = appsRes.status === 'fulfilled' && appsRes.value.success
                ? (appsRes.value.data?.apps || appsRes.value.apps || [])
                : this.state.availableApps;
            const appCount = availableApps.length;
            const moduleCount = modsRes.status === 'fulfilled' && modsRes.value.success
                ? (modsRes.value.data?.modules || modsRes.value.modules || []).length
                : this.state.moduleCount;
            const entityCount = entitiesRes.status === 'fulfilled' && entitiesRes.value.success
                ? (entitiesRes.value.data?.entities || entitiesRes.value.entities || []).length
                : this.state.entityCount;
            const pageCount = pagesRes.status === 'fulfilled' && pagesRes.value.success
                ? (pagesRes.value.data?.pages || pagesRes.value.pages || []).length
                : this.state.pageCount;
            const workflowCount = wfsRes.status === 'fulfilled' && wfsRes.value.success
                ? (wfsRes.value.data?.workflows || wfsRes.value.workflows || []).length
                : this.state.workflowCount;
            const auditLogs = auditRes.status === 'fulfilled' && auditRes.value.success
                ? (auditRes.value.data?.logs || auditRes.value.logs || []).slice(0, 10)
                : this.state.auditLogs;

            this.state.availableApps = availableApps;
            this.state.appCount = appCount;
            this.state.moduleCount = moduleCount;
            this.state.entityCount = entityCount;
            this.state.pageCount = pageCount;
            this.state.workflowCount = workflowCount;
            this.state.auditLogs = auditLogs;

            this.updateMetricValues();
            this.updateAuditLogs(auditLogs);
        } catch (err) {
            console.error('[DashboardView] Metric refresh error:', err);
        }
    }

    updateMetricValues() {
        const elPages = this.container?.querySelector?.('[data-metric-target="pages"]');
        if (elPages) elPages.textContent = this.state.pageCount;
        const elEntities = this.container?.querySelector?.('[data-metric-target="entities"]');
        if (elEntities) elEntities.innerHTML = `${this.state.entityCount} <span style="font-size: 1.2rem; color: var(--hub-text-muted); font-weight: 500;">/ ${this.state.workflowCount}</span>`;
        const elApps = this.container?.querySelector?.('[data-metric-target="apps"]');
        if (elApps) elApps.innerHTML = `${this.state.appCount} <span style="font-size: 1.2rem; color: var(--hub-text-muted); font-weight: 500;">/ ${this.state.moduleCount}</span>`;
    }

    updateAuditLogs(auditLogs) {
        const tbody = this.container?.querySelector?.('#audit-tbody');
        if (!tbody || !auditLogs || auditLogs.length === 0) return;
        const rowsHtml = auditLogs.map((log, idx) => {
            let details = {};
            try { details = log.new_values ? JSON.parse(log.new_values) : {}; } catch(e) {}
            const user = details.user || log.user_id || 'System';
            const ip = details.ip || log.ip_address || '';
            const action = log.entity_id || log.action || 'Event';
            const date = log.created_at || '—';
            const client = ip ? `Client: ${ip}` : 'Local execution';
            return `
                <tr data-key="log-${idx}">
                    <td><span class="metric-tag badge-studio">${action}</span></td>
                    <td style="font-weight: 700; color: var(--hub-text-primary);">${user}</td>
                    <td style="color: var(--hub-text-muted); font-weight: 600;">${date}</td>
                    <td style="color: var(--hub-text-secondary); max-width: 450px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${client}</td>
                </tr>
            `;
        }).join('');
        tbody.innerHTML = rowsHtml;
    }

    async fetchAllData() {
        try {
            const currentApp = this.app?.selectedApp || localStorage.getItem('spp_admin_selected_app') || 'default';
            const metricsRes = await this.api('get_dashboard_metrics', { appname: currentApp }, { lock: false });
            if (metricsRes && metricsRes.success && metricsRes.data) {
                const d = metricsRes.data;
                const availableApps = d.apps || [];
                const auditLogs = (d.audit_logs || []).slice(0, 10);
                this.setState({
                    loading: false,
                    health: d.health || null,
                    availableApps: availableApps,
                    appCount: availableApps.length,
                    moduleCount: (d.modules || []).length,
                    entityCount: (d.entities || []).length,
                    pageCount: (d.pages || []).length,
                    workflowCount: (d.workflows || []).length,
                    auditLogs: auditLogs,
                    lastRefreshed: Date.now()
                });
                return;
            }

            const [healthRes, appsRes, modsRes, entitiesRes, pagesRes, wfsRes, auditRes] = await Promise.allSettled([
                this.api('diagnostics_health', {}, { lock: false }),
                this.api('list_apps', {}, { lock: false }),
                this.api('list_modules', {}, { lock: false }),
                this.api('list_entities', { appname: currentApp, context: currentApp }, { lock: false }),
                this.api('list_pages', { appname: currentApp, context: currentApp }, { lock: false }),
                this.api('list_workflows', { appname: currentApp, context: currentApp }, { lock: false }),
                this.api('list_audit_logs', {}, { lock: false })
            ]);

            const healthRaw = healthRes.status === 'fulfilled' && healthRes.value.success
                ? healthRes.value.data
                : null;
            const health = healthRaw?.health || healthRaw || null;

            const availableApps = appsRes.status === 'fulfilled' && appsRes.value.success
                ? (appsRes.value.data?.apps || appsRes.value.apps || [])
                : [];
            const appCount = availableApps.length;

            const moduleCount = modsRes.status === 'fulfilled' && modsRes.value.success
                ? (modsRes.value.data?.modules || modsRes.value.modules || []).length
                : 0;

            const entityCount = entitiesRes.status === 'fulfilled' && entitiesRes.value.success
                ? (entitiesRes.value.data?.entities || entitiesRes.value.entities || []).length
                : 0;

            const pageCount = pagesRes.status === 'fulfilled' && pagesRes.value.success
                ? (pagesRes.value.data?.pages || pagesRes.value.pages || []).length
                : 0;

            const workflowCount = wfsRes.status === 'fulfilled' && wfsRes.value.success
                ? (wfsRes.value.data?.workflows || wfsRes.value.workflows || []).length
                : 0;

            const auditLogs = auditRes.status === 'fulfilled' && auditRes.value.success
                ? (auditRes.value.data?.logs || auditRes.value.logs || []).slice(0, 10)
                : [];

            this.setState({
                loading: false,
                health,
                appCount,
                moduleCount,
                entityCount,
                pageCount,
                workflowCount,
                availableApps,
                auditLogs,
                selectedApp: currentApp,
                lastRefreshed: Date.now(),
                secondsAgo: 0
            });
        } catch (err) {
            console.error('Dashboard fetch error:', err);
            this.setState({ loading: false });
        }
    }

    switchApp(newApp) {
        if (!newApp || newApp === this.state.selectedApp) return;
        if (this.app?.switchApp) {
            this.app.switchApp(newApp);
        } else if (this.app?.onAppContextChange) {
            this.app.onAppContextChange(newApp);
        } else {
            localStorage.setItem('spp_admin_selected_app', newApp);
            this.onContextChange(newApp);
        }
    }

    getAppPreviewUrl(app) {
        const origin = window.location.origin;
        const p = window.location.pathname;
        const idx = p.indexOf('/sppdev') !== -1 ? p.indexOf('/sppdev') : p.indexOf('/spp/dev');
        const appBase = idx !== -1 ? p.substring(0, idx) : (window.APP_BASE_URI || '');
        const appSegment = (app === 'default' ? '' : '/' + app);
        return `${origin}${appBase}${appSegment}/`;
    }

    getStatusColor(status) {
        if (!status) return '#4ade80';
        const s = String(status).toUpperCase();
        if (s === 'UP' || s === 'OK') return '#4ade80';
        if (s === 'DEGRADED') return '#fbbf24';
        if (s === 'DOWN' || s === 'FAIL') return '#f87171';
        return '#94a3b8';
    }

    getStatusLabel(status) {
        if (!status) return 'Healthy';
        const s = String(status).toUpperCase();
        if (s === 'UP' || s === 'OK') return 'Healthy';
        if (s === 'DEGRADED') return 'Degraded';
        if (s === 'DOWN' || s === 'FAIL') return 'Down';
        return status;
    }

    getChipStatusLabel(status) {
        if (!status) return 'UP';
        const s = String(status).toUpperCase();
        if (s === 'UP' || s === 'OK') return 'UP';
        if (s === 'DEGRADED') return 'Degraded';
        if (s === 'DOWN' || s === 'FAIL') return 'DOWN';
        return s;
    }

    getComponentLabel(name) {
        const labels = {
            database: 'Database',
            redis: 'Redis Cache',
            fs_var: 'Var Storage',
            fs_logs: 'Log Storage',
            system: 'PHP Engine',
            php_engine: 'PHP Engine',
            engine: 'PHP Engine',
            filesystem: 'Storage / Var'
        };
        return labels[name] || (name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' '));
    }

    formatElapsedTime(timestamp) {
        if (!timestamp) return 'Updated just now';
        const elapsed = Math.floor((Date.now() - timestamp) / 1000);
        if (elapsed < 4) return 'Updated just now';
        if (elapsed < 60) return `Updated ${elapsed}s ago`;
        const mins = Math.floor(elapsed / 60);
        return `Updated ${mins}m ago`;
    }

    renderHeroTelemetry(health, selectedApp) {
        const overallStatus = health?.status || 'UP';
        const statusColor = this.getStatusColor(overallStatus);
        const components = health?.components || {
            database: { status: 'UP' },
            redis: { status: 'UP' },
            fs_var: { status: 'UP' },
            fs_logs: { status: 'UP' },
            system: { status: 'UP' }
        };

        return html`
            <div class="studio-hero-meta" data-live-component="StudioLiveTelemetry" wire:poll.30s="refresh">
                <div class="health-pulse">
                    <div class="health-dot" style="background: ${statusColor}; box-shadow: 0 0 10px ${statusColor};"></div>
                    <span class="health-label" style="color: ${overallStatus === 'UP' || overallStatus === 'OK' ? '#4ade80' : '#f87171'};">
                        System ${this.getStatusLabel(overallStatus)}
                    </span>
                </div>

                <!-- Component Health Chips -->
                <div class="comp-chips-row">
                    ${Object.entries(components).map(([name, comp]) => {
                        const cStatus = typeof comp === 'string' ? comp : (comp?.status || 'UNKNOWN');
                        const cColor = this.getStatusColor(cStatus);
                        return html`
                            <div class="comp-chip" data-key="chip-${name}">
                                <div class="comp-dot" style="background: ${cColor}; box-shadow: 0 0 6px ${cColor};"></div>
                                <span>${this.getComponentLabel(name)}</span>
                                <span style="font-weight: 800; color: ${cColor}; font-size: 0.75rem;">${this.getChipStatusLabel(cStatus)}</span>
                            </div>
                        `;
                    })}
                </div>

                <!-- Live Auto-Sync Health & Refresh Strip -->
                <div class="refresh-indicator">
                    <span class="live-sync-badge">
                        <span class="live-sync-dot"></span>
                        <span>Live (30s)</span>
                    </span>
                    <span class="refresh-seconds">${this.formatElapsedTime(this.state.lastRefreshed)}</span>
                    <button class="refresh-btn ${this.state.isRefreshing ? 'spinning' : ''}" @click=${() => this.refreshTelemetry(true)} title="Refresh live telemetry now">🔄</button>
                </div>
            </div>
        `;
    }

    renderCard(card) {
        return html`
            <div class="hub-card pillar-${card.pillar}" data-key="${card.id}">
                <div class="hub-card-body">
                    <div class="hub-card-top-row">
                        <div class="hub-card-icon-box ${card.iconClass || ''}">${card.icon}</div>
                        <span class="hub-badge ${card.badgeTheme}">${card.badge}</span>
                    </div>
                    <h3 class="hub-card-title">${card.title}</h3>
                    <p class="hub-card-desc">${card.description}</p>
                </div>

                <div class="hub-card-footer">
                    <div class="hub-card-shortcuts">
                        ${card.shortcuts.map(s => html`
                            <a href="#${s.hash}" class="hub-shortcut-chip">${s.label}</a>
                        `)}
                    </div>
                    <button class="hub-primary-btn ${card.btnClass}" @click=${() => location.hash = card.route}>
                        <span>${card.primaryAction}</span>
                    </button>
                </div>
            </div>
        `;
    }

    renderPillarSection(pillarKey, title, icon, color, cards) {
        if (!cards || cards.length === 0) return '';
        return html`
            <div class="pillar-section" data-key="section-${pillarKey}">
                <div class="pillar-header">
                    <div class="pillar-title-wrap" style="color: ${color};">
                        <span class="pillar-icon">${icon}</span>
                        <h2 class="pillar-title">${title}</h2>
                        <span class="pillar-count">(${cards.length})</span>
                    </div>
                    <div class="pillar-line"></div>
                </div>

                <div class="studio-cards-grid">
                    ${cards.map(card => this.renderCard(card))}
                </div>
            </div>
        `;
    }

    render() {
        const {
            loading, health, appCount, moduleCount, entityCount, pageCount,
            workflowCount, availableApps, auditLogs, selectedApp,
            activePillarFilter, searchQuery
        } = this.state;

        if (loading) {
            return html`
                <div class="loading-state" style="min-height: 60vh; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 1rem;">
                    <div class="sppux-spinner"></div>
                    <div style="color: var(--text-dim, #64748b); font-size: 0.95rem; font-weight: 600;">
                        Synchronizing SPP Developer Studio telemetry…
                    </div>
                </div>
            `;
        }

        const overallStatus = health?.status || 'UP';
        const statusColor = this.getStatusColor(overallStatus);
        const components = health?.components || {};

        const sysComp = health?.components?.system || {};
        const phpVersion = sysComp.php_version || health?.php_version || 'PHP 8.3.6';
        const memoryUsage = sysComp.memory_usage || health?.memory_usage || '2.4 MB';

        // Complete catalog of 12 Studio Hub Cards across the 3 structural pillars
        const studioCards = [
            // ── Pillar 1: Developer Studio Suite ──
            {
                pillar: 'studio',
                id: 'coder',
                title: 'Code Studio & Scaffolder',
                icon: '💻',
                iconClass: 'icon-studio',
                badge: 'In-Browser IDE',
                badgeTheme: 'badge-studio',
                btnClass: 'btn-studio',
                description: 'Full-featured web IDE with project file tree, syntax highlighting, live linting, and intelligent code generators.',
                primaryAction: 'Launch Code Studio →',
                route: 'coder',
                tags: ['ide', 'editor', 'code', 'scaffold', 'files', 'stubs'],
                shortcuts: [
                    { label: 'Scaffold App', hash: 'coder?action=make_app' },
                    { label: 'New Controller', hash: 'coder?action=make_controller' },
                    { label: 'New Service', hash: 'coder?action=make_service' },
                    { label: 'View Stubs', hash: 'coder?path=spp/commands/stubs' }
                ]
            },
            {
                pillar: 'studio',
                id: 'tinker',
                title: 'Web Tinker REPL',
                icon: '💬',
                iconClass: 'icon-studio',
                badge: 'PHP Shell Sandbox',
                badgeTheme: 'badge-studio',
                btnClass: 'btn-studio',
                description: 'Interactive execution sandbox to evaluate PHP expressions, inspect DI bindings, test services, and query models on the fly.',
                primaryAction: 'Open Tinker Shell →',
                route: 'tinker',
                tags: ['tinker', 'repl', 'shell', 'eval', 'terminal', 'sandbox', 'debug'],
                shortcuts: [
                    { label: 'Eval Expression', hash: 'tinker' },
                    { label: 'Inspect Container', hash: 'tinker?q=container' },
                    { label: 'Dump App State', hash: 'tinker?q=app' }
                ]
            },

            // ── Pillar 2: Declarative Architecture & Data ──
            {
                pillar: 'declarative',
                id: 'pages',
                title: 'SPP Studio',
                icon: '📐',
                iconClass: 'icon-declarative',
                badge: 'Live Fidelity Canvas',
                badgeTheme: 'badge-declarative',
                btnClass: 'btn-declarative',
                description: 'Visual app builder with live fidelity canvas, WYSIWYG element editor, responsive viewports, box model inspector, and split view.',
                primaryAction: 'Open SPP Studio →',
                route: 'pages',
                tags: ['studio', 'pages', 'canvas', 'wysiwyg', 'visual', 'builder', 'htmx', 'partials', 'viewports'],
                shortcuts: [
                    { label: 'Visual Canvas', hash: 'pages' },
                    { label: 'Page Manifests', hash: 'pages?tab=manifest' },
                    { label: 'Partials & Blocks', hash: 'pages?tab=partials' }
                ]
            },
            {
                pillar: 'declarative',
                id: 'workflows',
                title: 'Workflows & State Machines',
                icon: '🔄',
                iconClass: 'icon-declarative',
                badge: 'Saga & SLA Timeouts',
                badgeTheme: 'badge-declarative',
                btnClass: 'btn-declarative',
                description: 'Visual state machine designer supporting parallel markings, SLA automated timeout transitions, and Saga compensating transactions.',
                primaryAction: 'Design Workflows →',
                route: 'workflows',
                tags: ['workflows', 'state', 'transitions', 'saga', 'sla', 'lifecycle', 'timeout'],
                shortcuts: [
                    { label: 'State Topologies', hash: 'workflows' },
                    { label: 'Inspect Transitions', hash: 'workflows?tab=transitions' },
                    { label: 'Timeout Monitor', hash: 'workflows?tab=timeouts' }
                ]
            },
            {
                pillar: 'declarative',
                id: 'entities',
                title: 'Entities & Schemas',
                icon: '🏗️',
                iconClass: 'icon-declarative',
                badge: 'ORM & NoSQL XDB',
                badgeTheme: 'badge-declarative',
                btnClass: 'btn-declarative',
                description: 'Model architect for database tables, Active Record ORM mappings, SQL migrations, XML Database (XDB), and InterDB mesh.',
                primaryAction: 'Explore Schemas & ORM →',
                route: 'entities',
                tags: ['entities', 'models', 'database', 'schema', 'migrations', 'xdb', 'nosql', 'orm'],
                shortcuts: [
                    { label: 'Schema Designer', hash: 'entities' },
                    { label: 'SQL Migrations', hash: 'entities?tab=migrations' },
                    { label: 'NoSQL XDB Ledger', hash: 'entities?tab=xdb' }
                ]
            },
            {
                pillar: 'declarative',
                id: 'forms',
                title: 'Forms & Locales',
                icon: '📝',
                iconClass: 'icon-declarative',
                badge: 'YAML Engine & i18n',
                badgeTheme: 'badge-declarative',
                btnClass: 'btn-declarative',
                description: 'Declarative YAML form builder, field validation guards, multi-locale translation manager (spplang), and responsive preview.',
                primaryAction: 'Open Form Builder →',
                route: 'forms',
                tags: ['forms', 'form engine', 'yaml', 'validation', 'i18n', 'locales', 'translations', 'spplang'],
                shortcuts: [
                    { label: 'Build Form', hash: 'forms' },
                    { label: 'Form Schemas', hash: 'forms?tab=schemas' },
                    { label: 'i18n Translations', hash: 'forms?tab=locales' }
                ]
            },
            {
                pillar: 'declarative',
                id: 'routing',
                title: 'Routing & Endpoints',
                icon: '🛤️',
                iconClass: 'icon-declarative',
                badge: 'Polyglot Services',
                badgeTheme: 'badge-declarative',
                btnClass: 'btn-declarative',
                description: 'Central routing matrix, HTTP middleware pipeline, polyglot RPC service registrations, and live API endpoint tester.',
                primaryAction: 'Routing Matrix →',
                route: 'routing',
                tags: ['routing', 'routes', 'endpoints', 'middleware', 'rpc', 'controllers', 'api tester'],
                shortcuts: [
                    { label: 'Route Matrix', hash: 'routing' },
                    { label: 'API Endpoint Tester', hash: 'routing?tab=tester' },
                    { label: 'Middleware Stack', hash: 'routing?tab=middleware' }
                ]
            },
            {
                pillar: 'declarative',
                id: 'reports',
                title: 'Reports & Analytics',
                icon: '📊',
                iconClass: 'icon-declarative',
                badge: 'Pivot OLAP & BI',
                badgeTheme: 'badge-declarative',
                btnClass: 'btn-declarative',
                description: 'Interactive visual report studio with multidimensional OLAP slicing, pivot summaries, chart generators, and CSV/Excel exports.',
                primaryAction: 'Open Report Studio →',
                route: 'reports',
                tags: ['reports', 'analytics', 'olap', 'pivot', 'bi', 'charts', 'exports', 'csv'],
                shortcuts: [
                    { label: 'Report Studio', hash: 'reports' },
                    { label: 'Pivot OLAP', hash: 'reports?tab=pivot' },
                    { label: 'Export Datasets', hash: 'reports?tab=exports' }
                ]
            },

            // ── Pillar 3: Runtime, AI & Testing ──
            {
                pillar: 'runtime',
                id: 'ai',
                title: 'AI Copilot & Playground',
                icon: '🤖',
                iconClass: 'icon-runtime',
                badge: 'SPPAI Gateway',
                badgeTheme: 'badge-runtime',
                btnClass: 'btn-runtime',
                description: 'AI prompt playground, architectural recipes, automated component generation, and autonomous tool calling via SPPAI.',
                primaryAction: 'Launch AI Copilot →',
                route: 'ai',
                tags: ['ai', 'copilot', 'llm', 'prompts', 'sppai', 'recipes', 'tools'],
                shortcuts: [
                    { label: 'Prompt Playground', hash: 'ai' },
                    { label: 'Architecture Recipes', hash: 'ai?tab=recipes' },
                    { label: 'SPPAI Tool Calling', hash: 'ai?tab=tools' }
                ]
            },
            {
                pillar: 'runtime',
                id: 'mesh',
                title: 'Integration Mesh',
                icon: '🕸️',
                iconClass: 'icon-runtime',
                badge: 'Container RPC',
                badgeTheme: 'badge-runtime',
                btnClass: 'btn-runtime',
                description: 'Inter-application container mesh, microservice RPC bridges, and cross-context event broadcasting.',
                primaryAction: 'Inspect Mesh →',
                route: 'mesh',
                tags: ['mesh', 'rpc', 'interprocess', 'bridge', 'integration', 'containers'],
                shortcuts: [
                    { label: 'Container RPC', hash: 'mesh' },
                    { label: 'Bridge Topologies', hash: 'mesh?tab=bridges' }
                ]
            },
            {
                pillar: 'runtime',
                id: 'testing',
                title: 'Parikshak Test Studio',
                icon: '🧪',
                iconClass: 'icon-runtime',
                badge: 'Primary Native Engine',
                badgeTheme: 'badge-runtime',
                btnClass: 'btn-runtime',
                description: 'SPP official testing engine. Run unit and feature test suites, evolutionary chaos testing, event tracing, and coverage reporting.',
                primaryAction: 'Run Parikshak Suite →',
                route: 'testing',
                tags: ['testing', 'parikshak', 'tests', 'unit', 'chaos', 'coverage', 'trace', 'assertions'],
                shortcuts: [
                    { label: 'Run All Tests', hash: 'testing' },
                    { label: 'Evolutionary Chaos', hash: 'testing?tab=chaos' },
                    { label: 'Event Telemetry', hash: 'testing?tab=trace' }
                ]
            },
            {
                pillar: 'runtime',
                id: 'commands',
                title: 'CLI Command Center',
                icon: '⚡',
                iconClass: 'icon-runtime',
                badge: 'SPP CLI Terminal',
                badgeTheme: 'badge-runtime',
                btnClass: 'btn-runtime',
                description: 'In-browser terminal console, interactive CLI command runner, artisan daemons, background queue orchestrator, and schedulers.',
                primaryAction: 'Open Terminal →',
                route: 'commands',
                tags: ['commands', 'cli', 'terminal', 'artisan', 'console', 'daemon', 'cron', 'queue'],
                shortcuts: [
                    { label: 'Interactive Terminal', hash: 'commands' },
                    { label: 'Command Explorer', hash: 'commands?tab=explorer' },
                    { label: 'DAG Queue Status', hash: 'commands?tab=queue' }
                ]
            }
        ];

        // Filter cards by selected pillar and search text
        const q = (searchQuery || '').trim().toLowerCase();
        const filteredCards = studioCards.filter(card => {
            if (activePillarFilter !== 'all' && card.pillar !== activePillarFilter) {
                return false;
            }
            if (q) {
                const haystack = (card.title + ' ' + card.description + ' ' + card.badge + ' ' + card.tags.join(' ')).toLowerCase();
                return haystack.includes(q);
            }
            return true;
        });

        const studioPillarCards = filteredCards.filter(c => c.pillar === 'studio');
        const declarativePillarCards = filteredCards.filter(c => c.pillar === 'declarative');
        const runtimePillarCards = filteredCards.filter(c => c.pillar === 'runtime');

        return html`
            <style>
                .dash-container {
                    animation: fadeIn 0.35s ease-out;
                    max-width: 1440px;
                    margin: 0 auto;
                    padding: 0 0.5rem 2.5rem 0.5rem;

                    /* ── Default High-Contrast Theme System ── */
                    --hub-bg: var(--card-bg, #ffffff);
                    --hub-text-primary: var(--text-bright, #0f172a);
                    --hub-text-secondary: var(--text, #334155);
                    --hub-text-muted: var(--text-dim, #64748b);
                    --hub-border: var(--glass-border, #cbd5e1);
                    --hub-chip-bg: rgba(0, 0, 0, 0.04);
                    --hub-chip-border: rgba(0, 0, 0, 0.12);
                    --hub-card-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
                    --hub-hero-bg: linear-gradient(135deg, #0b0f19 0%, #111827 50%, #1e1b4b 100%);
                    --hub-hero-border: rgba(255, 255, 255, 0.15);
                }

                /* High-Contrast Overrides for Night / Dark Mode */
                [data-theme="night"] .dash-container {
                    --hub-bg: #141824;
                    --hub-text-primary: #f8fafc;
                    --hub-text-secondary: #cbd5e1;
                    --hub-text-muted: #94a3b8;
                    --hub-border: rgba(255, 255, 255, 0.12);
                    --hub-chip-bg: rgba(255, 255, 255, 0.06);
                    --hub-chip-border: rgba(255, 255, 255, 0.14);
                    --hub-card-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
                    --hub-hero-bg: linear-gradient(135deg, #0b0f19 0%, #111827 50%, #1e1b4b 100%);
                    --hub-hero-border: rgba(255, 255, 255, 0.15);
                }

                /* High-Contrast Overrides for Day / Light Mode */
                [data-theme="day"] .dash-container,
                body:not([data-theme="night"]):not([data-theme="saffron"]) .dash-container {
                    --hub-bg: #ffffff;
                    --hub-text-primary: #0f172a;
                    --hub-text-secondary: #334155;
                    --hub-text-muted: #64748b;
                    --hub-border: #e2e8f0;
                    --hub-chip-bg: #f8fafc;
                    --hub-chip-border: #cbd5e1;
                    --hub-card-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
                    --hub-hero-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
                    --hub-hero-border: rgba(255, 255, 255, 0.15);
                }

                /* High-Contrast Overrides for Saffron Mode */
                [data-theme="saffron"] .dash-container {
                    --hub-bg: #fffbf5;
                    --hub-text-primary: #291507;
                    --hub-text-secondary: #451a03;
                    --hub-text-muted: #78350f;
                    --hub-border: #fed7aa;
                    --hub-chip-bg: #ffedd5;
                    --hub-chip-border: #fb923c;
                    --hub-card-shadow: 0 4px 16px rgba(180, 83, 9, 0.08);
                    --hub-hero-bg: linear-gradient(135deg, #1c0f07 0%, #2e150a 50%, #431407 100%);
                    --hub-hero-border: rgba(251, 146, 60, 0.3);
                }

                /* ── Hero Studio Banner ── */
                .studio-hero {
                    background: var(--hub-hero-bg);
                    border: 1px solid var(--hub-hero-border);
                    border-radius: 20px;
                    padding: 2.25rem 2.25rem 1.75rem 2.25rem;
                    position: relative;
                    overflow: hidden;
                    margin-bottom: 1.75rem;
                    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
                }
                .studio-hero::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -10%;
                    width: 50%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, transparent 70%);
                    pointer-events: none;
                }
                .studio-hero::after {
                    content: '';
                    position: absolute;
                    bottom: -50%;
                    right: -10%;
                    width: 50%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(168, 85, 247, 0.15) 0%, transparent 70%);
                    pointer-events: none;
                }
                .studio-hero-inner {
                    position: relative;
                    z-index: 1;
                }
                .studio-hero-top {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    flex-wrap: wrap;
                    gap: 1.5rem;
                }
                .studio-title-area h1 {
                    font-size: 2.25rem;
                    font-weight: 800;
                    margin: 0 0 0.4rem 0;
                    font-family: 'Outfit', sans-serif;
                    letter-spacing: -0.02em;
                    color: #ffffff;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }
                .studio-subtitle {
                    color: #e2e8f0;
                    font-size: 1rem;
                    margin: 0;
                    max-width: 720px;
                    line-height: 1.55;
                    font-weight: 400;
                }

                /* ── Active Context Switcher Strip ── */
                .app-context-badge-bar {
                    display: flex;
                    align-items: center;
                    gap: 0.85rem;
                    background: rgba(0, 0, 0, 0.45);
                    padding: 8px 16px;
                    border-radius: 12px;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    flex-wrap: wrap;
                }
                .context-selector-label {
                    font-size: 0.75rem;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #cbd5e1;
                    font-weight: 700;
                }
                .context-select {
                    background: #1e293b;
                    color: #ffffff;
                    border: 1px solid rgba(255, 255, 255, 0.25);
                    border-radius: 8px;
                    padding: 7px 14px;
                    font-size: 0.88rem;
                    font-weight: 700;
                    cursor: pointer;
                    outline: none;
                    transition: all 0.2s;
                }
                .context-select:hover, .context-select:focus {
                    border-color: #38bdf8;
                    background: #0f172a;
                    box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.25);
                }
                .context-preview-btn {
                    background: #0284c7;
                    color: #ffffff;
                    border: 1px solid #38bdf8;
                    border-radius: 8px;
                    padding: 7px 14px;
                    font-size: 0.82rem;
                    font-weight: 700;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .context-preview-btn:hover {
                    background: #0369a1;
                    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4);
                }

                /* ── Subsystem Health Bar ── */
                #studio-hero-telemetry-slot,
                studio-live-telemetry {
                    display: block;
                    width: 100%;
                }
                .studio-hero-meta {
                    display: flex;
                    align-items: center;
                    gap: 0.85rem;
                    margin-top: 1.5rem;
                    flex-wrap: wrap;
                }
                .health-pulse {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    background: rgba(0, 0, 0, 0.45);
                    padding: 7px 16px;
                    border-radius: 30px;
                    border: 1px solid rgba(255, 255, 255, 0.15);
                }
                .health-dot {
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    animation: healthPulse 2s ease-in-out infinite;
                    flex-shrink: 0;
                }
                @keyframes healthPulse {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50% { opacity: 0.6; transform: scale(1.3); }
                }
                .health-label {
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    font-weight: 800;
                }
                .comp-chips-row {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .comp-chip {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    background: rgba(0, 0, 0, 0.35);
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    padding: 6px 12px;
                    border-radius: 10px;
                    font-size: 0.78rem;
                    font-weight: 600;
                    color: #ffffff;
                }
                .comp-dot {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    flex-shrink: 0;
                }
                .refresh-indicator {
                    margin-left: auto;
                    font-size: 0.8rem;
                    color: #cbd5e1;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .live-sync-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: rgba(34, 197, 94, 0.18);
                    border: 1px solid rgba(34, 197, 94, 0.35);
                    color: #4ade80;
                    padding: 3px 10px;
                    border-radius: 9999px;
                    font-size: 0.72rem;
                    font-weight: 800;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .live-sync-dot {
                    width: 7px;
                    height: 7px;
                    border-radius: 50%;
                    background: #22c55e;
                    box-shadow: 0 0 6px #22c55e;
                    animation: healthPulse 2s ease-in-out infinite;
                    flex-shrink: 0;
                }
                .refresh-seconds {
                    font-size: 0.78rem;
                    color: #e2e8f0;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .refresh-btn {
                    background: rgba(255, 255, 255, 0.12);
                    border: 1px solid rgba(255, 255, 255, 0.25);
                    color: #ffffff;
                    cursor: pointer;
                    font-size: 0.95rem;
                    padding: 4px 9px;
                    border-radius: 6px;
                    transition: transform 0.3s, background 0.2s;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }
                .refresh-btn:hover {
                    background: rgba(255, 255, 255, 0.25);
                    border-color: rgba(255, 255, 255, 0.4);
                }
                .refresh-btn.spinning {
                    animation: spinRotate 0.7s linear infinite;
                }
                @keyframes spinRotate {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }

                /* ── Context-Aware Metric Cards ── */
                .metrics-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 1.25rem;
                    margin-bottom: 1.75rem;
                }
                @media (max-width: 1100px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 600px) { .metrics-grid { grid-template-columns: 1fr; } }

                .metric-card {
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 16px;
                    padding: 1.35rem 1.35rem 1.15rem 1.35rem;
                    cursor: pointer;
                    position: relative;
                    overflow: hidden;
                    box-shadow: var(--hub-card-shadow);
                    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                }
                .metric-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                }
                .metric-card.accent-purple::before { background: #7c3aed; }
                .metric-card.accent-blue::before { background: #0284c7; }
                .metric-card.accent-pink::before { background: #db2777; }
                .metric-card.accent-green::before { background: #059669; }

                .metric-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.12);
                }
                .metric-card-top {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.65rem;
                }
                .metric-tag {
                    font-size: 0.72rem;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    font-weight: 800;
                    padding: 3px 9px;
                    border-radius: 6px;
                    border: 1px solid;
                }
                .metric-icon {
                    font-size: 1.3rem;
                    width: 36px;
                    height: 36px;
                    background: var(--hub-chip-bg);
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid var(--hub-chip-border);
                }
                .metric-value {
                    font-size: 2.15rem;
                    font-weight: 800;
                    line-height: 1.1;
                    letter-spacing: -0.03em;
                    margin: 0.25rem 0;
                    font-family: 'Outfit', sans-serif;
                    color: var(--hub-text-primary);
                }
                .metric-label {
                    font-size: 0.88rem;
                    font-weight: 700;
                    color: var(--hub-text-primary);
                }
                .metric-sub {
                    font-size: 0.78rem;
                    font-weight: 500;
                    color: var(--hub-text-muted);
                    margin-top: 0.2rem;
                }

                /* ── Filter Bar & Pillar Navigation ── */
                .studio-filter-strip {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 1rem;
                    margin-bottom: 1.75rem;
                    flex-wrap: wrap;
                }
                .pillar-pills {
                    display: flex;
                    gap: 0.5rem;
                    flex-wrap: wrap;
                }
                .pillar-pill {
                    appearance: none;
                    -webkit-appearance: none;
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    color: var(--hub-text-secondary);
                    padding: 8px 18px;
                    border-radius: 9999px;
                    font-size: 0.84rem;
                    font-weight: 700;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    transition: all 0.2s;
                    outline: none;
                }
                .pillar-pill:hover {
                    color: var(--hub-text-primary);
                    border-color: #38bdf8;
                }
                .pillar-pill.active {
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                .pillar-pill.active.pill-all {
                    background: #0f172a;
                    color: #ffffff;
                    border-color: #0f172a;
                }
                [data-theme="night"] .pillar-pill.active.pill-all {
                    background: #334155;
                    border-color: #64748b;
                }
                [data-theme="saffron"] .pillar-pill.active.pill-all {
                    background: #451a03;
                    border-color: #451a03;
                }
                .pillar-pill.active.pill-studio {
                    border-color: #0284c7;
                    color: #ffffff;
                    background: #0284c7;
                }
                .pillar-pill.active.pill-declarative {
                    border-color: #7c3aed;
                    color: #ffffff;
                    background: #7c3aed;
                }
                .pillar-pill.active.pill-runtime {
                    border-color: #059669;
                    color: #ffffff;
                    background: #059669;
                }
                .studio-search-input {
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 10px;
                    padding: 8px 14px;
                    color: var(--hub-text-primary);
                    font-size: 0.85rem;
                    font-weight: 600;
                    outline: none;
                    width: 250px;
                    transition: all 0.2s;
                }
                .studio-search-input:focus {
                    border-color: #38bdf8;
                    width: 290px;
                    box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
                }

                /* ── Structural Pillars Section Grids ── */
                .pillar-section {
                    margin-bottom: 2.25rem;
                }
                .pillar-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 1.15rem;
                }
                .pillar-title-wrap {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .pillar-icon {
                    font-size: 1.25rem;
                }
                .pillar-title {
                    font-size: 1.25rem;
                    font-weight: 800;
                    margin: 0;
                    font-family: 'Outfit', sans-serif;
                    letter-spacing: -0.01em;
                }
                .pillar-count {
                    font-size: 0.85rem;
                    font-weight: 600;
                    opacity: 0.75;
                }
                .pillar-line {
                    flex: 1;
                    height: 2px;
                    background: linear-gradient(to right, var(--hub-border), transparent);
                }

                .studio-cards-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
                    gap: 1.35rem;
                }

                /* ── High-Contrast Enterprise Card Architecture ── */
                .hub-card {
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 16px;
                    padding: 1.45rem;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    position: relative;
                    box-shadow: var(--hub-card-shadow);
                    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                    box-sizing: border-box;
                }
                .hub-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
                }
                .hub-card.pillar-studio:hover { border-color: #0284c7; }
                .hub-card.pillar-declarative:hover { border-color: #7c3aed; }
                .hub-card.pillar-runtime:hover { border-color: #059669; }

                .hub-card-body {
                    margin-bottom: 1.25rem;
                }
                /* Top row: Icon Box on Left, Badge on Right */
                .hub-card-top-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.85rem;
                }
                .hub-card-icon-box {
                    font-size: 1.4rem;
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: var(--hub-chip-bg);
                    border: 1px solid var(--hub-chip-border);
                }
                .hub-card-icon-box.icon-studio { color: #0284c7; }
                .hub-card-icon-box.icon-declarative { color: #7c3aed; }
                .hub-card-icon-box.icon-runtime { color: #059669; }

                /* Full-width Title Row */
                .hub-card-title {
                    font-size: 1.12rem;
                    font-weight: 800;
                    margin: 0 0 0.5rem 0;
                    color: var(--hub-text-primary);
                    line-height: 1.35;
                }
                .hub-badge {
                    font-size: 0.72rem;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    padding: 4px 10px;
                    border-radius: 6px;
                    border: 1px solid;
                    white-space: nowrap;
                }
                /* Badge Color Systems */
                .badge-studio {
                    color: #0369a1;
                    background: #e0f2fe;
                    border-color: #7dd3fc;
                }
                [data-theme="night"] .badge-studio {
                    color: #38bdf8;
                    background: rgba(56, 189, 248, 0.15);
                    border-color: rgba(56, 189, 248, 0.35);
                }
                .badge-declarative {
                    color: #6b21a8;
                    background: #f3e8ff;
                    border-color: #d8b4fe;
                }
                [data-theme="night"] .badge-declarative {
                    color: #c084fc;
                    background: rgba(168, 85, 247, 0.15);
                    border-color: rgba(168, 85, 247, 0.35);
                }
                .badge-runtime {
                    color: #15803d;
                    background: #dcfce7;
                    border-color: #86efac;
                }
                [data-theme="night"] .badge-runtime {
                    color: #22c55e;
                    background: rgba(34, 197, 94, 0.15);
                    border-color: rgba(34, 197, 94, 0.35);
                }

                .hub-card-desc {
                    color: var(--hub-text-secondary);
                    font-size: 0.88rem;
                    font-weight: 500;
                    line-height: 1.55;
                    margin: 0;
                }

                /* Footer: Shortcuts & Primary Action Button */
                .hub-card-footer {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }
                .hub-card-shortcuts {
                    display: flex;
                    gap: 6px;
                    flex-wrap: wrap;
                }
                .hub-shortcut-chip {
                    background: var(--hub-chip-bg);
                    border: 1px solid var(--hub-chip-border);
                    color: var(--hub-text-primary);
                    font-size: 0.78rem;
                    font-weight: 700;
                    padding: 5px 10px;
                    border-radius: 6px;
                    cursor: pointer;
                    text-decoration: none;
                    transition: all 0.2s;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                }
                .hub-shortcut-chip:hover {
                    background: #0284c7;
                    color: #ffffff;
                    border-color: #0284c7;
                }

                /* Modern Enterprise Button Styling (Resilient to Class Splitting) */
                .hub-primary-btn,
                .hub-card-action-btn,
                .btn-studio,
                .btn-declarative,
                .btn-runtime {
                    appearance: none;
                    -webkit-appearance: none;
                    width: 100%;
                    border: none;
                    outline: none;
                    border-radius: 10px;
                    padding: 11px 16px;
                    font-size: 0.9rem;
                    font-weight: 700;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    transition: all 0.25s;
                    color: #ffffff !important;
                    box-sizing: border-box;
                    text-decoration: none;
                }
                .hub-primary-btn.btn-studio,
                .hub-card-action-btn.btn-studio,
                .btn-studio {
                    background: linear-gradient(135deg, #0284c7, #0369a1);
                }
                .hub-primary-btn.btn-studio:hover,
                .hub-card-action-btn.btn-studio:hover,
                .btn-studio:hover {
                    background: linear-gradient(135deg, #0369a1, #075985);
                    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
                }
                .hub-primary-btn.btn-declarative,
                .hub-card-action-btn.btn-declarative,
                .btn-declarative {
                    background: linear-gradient(135deg, #7c3aed, #6d28d9);
                }
                .hub-primary-btn.btn-declarative:hover,
                .hub-card-action-btn.btn-declarative:hover,
                .btn-declarative:hover {
                    background: linear-gradient(135deg, #6d28d9, #5b21b6);
                    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.4);
                }
                .hub-primary-btn.btn-runtime,
                .hub-card-action-btn.btn-runtime,
                .btn-runtime {
                    background: linear-gradient(135deg, #059669, #047857);
                }
                .hub-primary-btn.btn-runtime:hover,
                .hub-card-action-btn.btn-runtime:hover,
                .btn-runtime:hover {
                    background: linear-gradient(135deg, #047857, #065f46);
                    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.4);
                }

                /* ── Recent Activity Table ── */
                .audit-panel {
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: var(--hub-card-shadow);
                    margin-bottom: 2rem;
                }
                .audit-panel table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 0;
                }
                .audit-panel th {
                    background: var(--hub-chip-bg);
                    color: var(--hub-text-muted);
                    font-size: 0.78rem;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    padding: 12px 18px;
                    text-align: left;
                    border-bottom: 1px solid var(--hub-border);
                }
                .audit-panel td {
                    padding: 12px 18px;
                    font-size: 0.86rem;
                    color: var(--hub-text-secondary);
                    font-weight: 500;
                    border-bottom: 1px solid var(--hub-border);
                }
                .audit-panel tr:last-child td {
                    border-bottom: none;
                }
                .audit-empty {
                    text-align: center;
                    padding: 3rem 2rem;
                    color: var(--hub-text-muted);
                }

                /* ── Keyboard Shortcuts Footer ── */
                .shortcuts-footer {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 12px;
                    padding: 12px 20px;
                    font-size: 0.85rem;
                    font-weight: 600;
                    color: var(--hub-text-secondary);
                    box-shadow: var(--hub-card-shadow);
                    flex-wrap: wrap;
                    gap: 1rem;
                }
                .kbd-pill {
                    background: var(--hub-chip-bg);
                    border: 1px solid var(--hub-chip-border);
                    border-radius: 4px;
                    padding: 2px 7px;
                    font-family: monospace;
                    font-size: 0.78rem;
                    font-weight: 700;
                    color: var(--hub-text-primary);
                }
            </style>

            <div class="dash-container">

                <!-- ═══════════════ Hero Studio Banner ═══════════════ -->
                <div class="studio-hero" data-key="studio-hero">
                    <div class="studio-hero-inner">
                        <div class="studio-hero-top">
                            <div class="studio-title-area">
                                <h1>
                                    <span>🚀</span> SPP Developer Studio Hub
                                </h1>
                                <p class="studio-subtitle">
                                    Unified command center for the SPP Framework — architect, build, verify, and ship with zero friction.
                                </p>
                            </div>

                            <!-- Active App Context Switcher -->
                            <div class="app-context-badge-bar">
                                <span class="context-selector-label">Active App:</span>
                                <select class="context-select" @change=${(e) => this.switchApp(e.target.value)}>
                                    ${availableApps.map(app => html`
                                        <option value="${app.name}" ?selected=${app.name === selectedApp}>
                                            ${app.name} (${app.type || 'App'})
                                        </option>
                                    `)}
                                </select>
                                <a href="${this.getAppPreviewUrl(selectedApp)}" target="_blank" class="context-preview-btn" title="Open running app in new tab">
                                    <span>Preview App</span> ↗
                                </a>
                            </div>
                        </div>

                        <!-- Subsystem Health Strip & Live Telemetry -->
                        <div id="studio-hero-telemetry-slot" data-key="studio-hero-telemetry">
                            ${this.renderHeroTelemetry(health, selectedApp)}
                        </div>
                    </div>
                </div>

                <!-- ═══════════════ Executive Metric Cards (Context-Aware) ═══════════════ -->
                <div class="metrics-grid" data-key="metrics-grid">
                    <!-- 1. SPP Studio Pages -->
                    <div class="metric-card accent-purple" data-key="metric-pages" @click=${() => location.hash = 'pages'}>
                        <div class="metric-card-top">
                            <span class="metric-tag badge-declarative">SPP Studio</span>
                            <div class="metric-icon" style="color: #7c3aed;">📐</div>
                        </div>
                        <div class="metric-value" data-metric-target="pages" style="color: #7c3aed;">${pageCount}</div>
                        <div class="metric-label">Pages & Templates</div>
                        <div class="metric-sub">Active in <code>${selectedApp}</code></div>
                    </div>

                    <!-- 2. Entities & Workflows -->
                    <div class="metric-card accent-blue" data-key="metric-entities" @click=${() => location.hash = 'entities'}>
                        <div class="metric-card-top">
                            <span class="metric-tag badge-studio">Declarative Data</span>
                            <div class="metric-icon" style="color: #0284c7;">🏗️</div>
                        </div>
                        <div class="metric-value" data-metric-target="entities" style="color: #0284c7;">${entityCount} <span style="font-size: 1.2rem; color: var(--hub-text-muted); font-weight: 500;">/ ${workflowCount}</span></div>
                        <div class="metric-label">Entities & Workflows</div>
                        <div class="metric-sub">ORM Models • State Machines</div>
                    </div>

                    <!-- 3. Apps & Modules -->
                    <div class="metric-card accent-pink" data-key="metric-ecosystem" @click=${() => location.hash = 'coder'}>
                        <div class="metric-card-top">
                            <span class="metric-tag" style="color: #be185d; background: #fce7f3; border-color: #fbcfe8;">Ecosystem</span>
                            <div class="metric-icon" style="color: #db2777;">📦</div>
                        </div>
                        <div class="metric-value" data-metric-target="apps" style="color: #db2777;">${appCount} <span style="font-size: 1.2rem; color: var(--hub-text-muted); font-weight: 500;">/ ${moduleCount}</span></div>
                        <div class="metric-label">Applications & Modules</div>
                        <div class="metric-sub">Contexts & active modules</div>
                    </div>

                    <!-- 4. PHP Engine & Testing -->
                    <div class="metric-card accent-green" data-key="metric-runtime" @click=${() => location.hash = 'testing'}>
                        <div class="metric-card-top">
                            <span class="metric-tag badge-runtime">PHP Engine</span>
                            <div class="metric-icon" style="color: #059669;">⚡</div>
                        </div>
                        <div class="metric-value" style="font-size: 1.65rem; color: #059669; margin: 0.55rem 0;">${phpVersion}</div>
                        <div class="metric-label">PHP Runtime & Parikshak</div>
                        <div class="metric-sub">Memory: ${memoryUsage} • Native Engine</div>
                    </div>
                </div>

                <!-- ═══════════════ Filter Strip & Pillar Navigation ═══════════════ -->
                <div class="studio-filter-strip" data-key="filter-strip">
                    <div class="pillar-pills">
                        <button class="pillar-pill pill-all ${activePillarFilter === 'all' ? 'active' : ''}" 
                            @click=${() => this.setState({ activePillarFilter: 'all' })}>
                            🌐 All Subsystems (${studioCards.length})
                        </button>
                        <button class="pillar-pill pill-studio ${activePillarFilter === 'studio' ? 'active' : ''}" 
                            @click=${() => this.setState({ activePillarFilter: 'studio' })}>
                            💻 Developer Studio (2)
                        </button>
                        <button class="pillar-pill pill-declarative ${activePillarFilter === 'declarative' ? 'active' : ''}" 
                            @click=${() => this.setState({ activePillarFilter: 'declarative' })}>
                            📐 Declarative Architecture & Data (6)
                        </button>
                        <button class="pillar-pill pill-runtime ${activePillarFilter === 'runtime' ? 'active' : ''}" 
                            @click=${() => this.setState({ activePillarFilter: 'runtime' })}>
                            🧪 Runtime, AI & Testing (4)
                        </button>
                    </div>

                    <input type="text" class="studio-search-input" placeholder="🔍 Filter studio tools..." 
                        .value=${searchQuery} 
                        @input=${(e) => this.setState({ searchQuery: e.target.value })}>
                </div>

                <!-- ═══════════════ The 3 Core Structural Pillars ═══════════════ -->

                <!-- 1. DEVELOPER STUDIO SUITE -->
                ${(activePillarFilter === 'all' || activePillarFilter === 'studio')
                    ? this.renderPillarSection('studio', 'Developer Studio Suite', '💻', '#0284c7', studioPillarCards)
                    : ''}

                <!-- 2. DECLARATIVE ARCHITECTURE & DATA -->
                ${(activePillarFilter === 'all' || activePillarFilter === 'declarative')
                    ? this.renderPillarSection('declarative', 'Declarative Architecture & Data', '📐', '#7c3aed', declarativePillarCards)
                    : ''}

                <!-- 3. RUNTIME, AI & TESTING -->
                ${(activePillarFilter === 'all' || activePillarFilter === 'runtime')
                    ? this.renderPillarSection('runtime', 'Runtime, AI & Testing', '🧪', '#059669', runtimePillarCards)
                    : ''}

                <!-- ═══════════════ Recent Activity & Audit Stream ═══════════════ -->
                <div class="pillar-header" style="margin-top: 2rem;">
                    <div class="pillar-title-wrap" style="color: var(--hub-text-primary);">
                        <span class="pillar-icon">📋</span>
                        <h2 class="pillar-title" style="font-size: 1.15rem;">Recent Platform Activity</h2>
                    </div>
                    <div class="pillar-line"></div>
                    <span style="font-size: 0.78rem; font-weight: 600; color: var(--hub-text-muted);">Last 10 events</span>
                </div>

                <div class="audit-panel" data-key="audit-panel">
                    ${auditLogs.length > 0 ? html`
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 22%;">Action / Entity</th>
                                    <th style="width: 18%;">User</th>
                                    <th style="width: 22%;">Timestamp</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody id="audit-tbody">
                                ${auditLogs.map((log, idx) => {
                                    let details = {};
                                    try { details = log.new_values ? JSON.parse(log.new_values) : {}; } catch(e) {}
                                    const user = details.user || log.user_id || 'System';
                                    const ip = details.ip || log.ip_address || '';
                                    return html`
                                        <tr data-key="log-${idx}">
                                            <td>
                                                <span class="metric-tag badge-studio">
                                                    ${log.entity_id || log.action || 'Event'}
                                                </span>
                                            </td>
                                            <td style="font-weight: 700; color: var(--hub-text-primary);">${user}</td>
                                            <td style="color: var(--hub-text-muted); font-weight: 600;">${log.created_at || '—'}</td>
                                            <td style="color: var(--hub-text-secondary); max-width: 450px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                ${ip ? `Client: ${ip}` : 'Local execution'}
                                            </td>
                                        </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    ` : html`
                        <div class="audit-empty">
                            <div style="font-size: 2.2rem; margin-bottom: 0.5rem; opacity: 0.6;">📭</div>
                            <h4 style="margin: 0 0 0.3rem 0; color: var(--hub-text-primary); font-weight: 700;">No activity recorded yet</h4>
                            <p style="margin: 0; font-size: 0.88rem;">Platform and entity changes will appear here in real-time as you develop.</p>
                        </div>
                    `}
                </div>

                <!-- ═══════════════ Developer Shortcuts Cheatsheet Footer ═══════════════ -->
                <div class="shortcuts-footer" data-key="shortcuts-footer">
                    <div>
                        <strong style="color: var(--hub-text-primary);">💡 Studio Pro-Tips:</strong>
                        <span style="margin-left: 0.5rem;">
                            Press <span class="kbd-pill">Ctrl</span> + <span class="kbd-pill">K</span> for Command Palette, or jump to 
                            <a href="#pages" style="color: #7c3aed; text-decoration: none; font-weight: 700;">#pages</a> (SPP Studio), 
                            <a href="#coder" style="color: #0284c7; text-decoration: none; font-weight: 700;">#coder</a> (IDE), or 
                            <a href="#tinker" style="color: #0369a1; text-decoration: none; font-weight: 700;">#tinker</a> (REPL).
                        </span>
                    </div>
                    <div>
                        <span>Active Context: <code style="color: #0284c7; font-weight: 700;">src/${selectedApp}/</code></span>
                    </div>
                </div>

            </div>
        `;
    }
}
