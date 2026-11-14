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
            domainCount: 1,
            diskUsageFormatted: 'Active',
            auditLogs: [],
            lastRefreshed: null,
            secondsAgo: 0,
            username: this.app?.user?.username || 'Developer',
            selectedApp: activeApp,
            availableApps: [],
            activePillarFilter: 'all',
            searchQuery: '',
            showBeginnerGuide: (typeof localStorage !== 'undefined') ? (localStorage.getItem('spp_beginner_guide_collapsed') !== 'true') : true,
            uiMode: (typeof window !== 'undefined' && window.admin && typeof window.admin.getUIMode === 'function') 
                ? window.admin.getUIMode() 
                : ((typeof localStorage !== 'undefined') ? (localStorage.getItem('spp_admin_ui_mode') || 'normal') : 'normal'),
            // Sovereign System Doctor & Self-Healing state
            doctorData: null,
            runningDoctor: false,
            repairingDoctor: false,
            doctorFilter: 'all'
        };

        if (typeof window !== 'undefined') {
            window.dashboardViewInstance = this;
        }

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
            this._uiModeListener = (e) => {
                if (e.detail?.mode) {
                    this.setState({ uiMode: e.detail.mode });
                }
            };
            window.addEventListener('spp:ui-mode-change', this._uiModeListener);
        }

        await this.fetchAllData();
        // Automatically run Sovereign System Doctor in background to detect any server incompatibilities immediately
        this.runSystemDoctor(true);
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
        if (this._uiModeListener && typeof window !== 'undefined') {
            window.removeEventListener('spp:ui-mode-change', this._uiModeListener);
            this._uiModeListener = null;
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

            const [appsRes, modsRes, entitiesRes, pagesRes, wfsRes, auditRes, domainsRes, diskRes] = await Promise.allSettled([
                this.api('list_apps', {}, { lock }),
                this.api('list_modules', {}, { lock }),
                this.api('list_entities', { appname: currentApp, context: currentApp }, { lock }),
                this.api('list_pages', { appname: currentApp, context: currentApp }, { lock }),
                this.api('list_workflows', { appname: currentApp, context: currentApp }, { lock }),
                this.api('list_audit_logs', {}, { lock }),
                this.api('domains_list', {}, { lock }),
                this.api('storage_disk_usage', {}, { lock })
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
            const domainCount = domainsRes.status === 'fulfilled' && domainsRes.value.success
                ? (domainsRes.value.total_domains ?? domainsRes.value.domains?.length ?? 1)
                : this.state.domainCount;
            const diskUsageFormatted = diskRes.status === 'fulfilled' && diskRes.value.success
                ? (diskRes.value.used_formatted || 'Active')
                : this.state.diskUsageFormatted;
            const auditLogs = auditRes.status === 'fulfilled' && auditRes.value.success
                ? (auditRes.value.data?.logs || auditRes.value.logs || []).slice(0, 10)
                : this.state.auditLogs;

            this.state.availableApps = availableApps;
            this.state.appCount = appCount;
            this.state.moduleCount = moduleCount;
            this.state.entityCount = entityCount;
            this.state.pageCount = pageCount;
            this.state.workflowCount = workflowCount;
            this.state.domainCount = domainCount;
            this.state.diskUsageFormatted = diskUsageFormatted;
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
        const elDomains = this.container?.querySelector?.('[data-metric-target="domains"]');
        if (elDomains) elDomains.textContent = this.state.domainCount;
        const elDisk = this.container?.querySelector?.('[data-metric-target="disk"]');
        if (elDisk) elDisk.textContent = this.state.diskUsageFormatted;
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

            const [healthRes, appsRes, modsRes, entitiesRes, pagesRes, wfsRes, auditRes, domainsRes, diskRes] = await Promise.allSettled([
                this.api('diagnostics_health', {}, { lock: false }),
                this.api('list_apps', {}, { lock: false }),
                this.api('list_modules', {}, { lock: false }),
                this.api('list_entities', { appname: currentApp, context: currentApp }, { lock: false }),
                this.api('list_pages', { appname: currentApp, context: currentApp }, { lock: false }),
                this.api('list_workflows', { appname: currentApp, context: currentApp }, { lock: false }),
                this.api('list_audit_logs', {}, { lock: false }),
                this.api('domains_list', {}, { lock: false }),
                this.api('storage_disk_usage', {}, { lock: false })
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

            const domainCount = domainsRes.status === 'fulfilled' && domainsRes.value.success
                ? (domainsRes.value.total_domains ?? domainsRes.value.domains?.length ?? 1)
                : 1;

            const diskUsageFormatted = diskRes.status === 'fulfilled' && diskRes.value.success
                ? (diskRes.value.used_formatted || 'Active')
                : 'Active';

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
                domainCount,
                diskUsageFormatted,
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

    onUIModeChange(mode) {
        this.setState({ uiMode: mode });
    }

    toggleBeginnerGuide() {
        const nextState = !this.state.showBeginnerGuide;
        this.setState({ showBeginnerGuide: nextState });
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('spp_beginner_guide_collapsed', nextState ? 'false' : 'true');
        }
    }

    renderQuickStartGuide() {
        const isOpen = this.state.showBeginnerGuide !== false;
        const isNormal = this.state.uiMode === 'normal';
        const quickItems = isNormal ? [
            {
                id: 'qs-domains',
                title: 'Add / Manage Websites',
                desc: 'Add domain names, configure subdomains, redirects, and manage free SSL certificates.',
                icon: '🌐',
                route: 'domains',
                actionLabel: 'Manage Domains →',
                shortcuts: [
                    { label: '+ Add Domain', hash: 'domains' },
                    { label: 'Subdomains', hash: 'domains?tab=addons' },
                    { label: 'SSL Certs', hash: 'domains?tab=ssl' }
                ]
            },
            {
                id: 'qs-database',
                title: 'Databases & Tables',
                desc: 'Create MySQL & PostgreSQL databases, provision user credentials, and browse table rows.',
                icon: '🗄️',
                route: 'database',
                actionLabel: 'Databases →',
                shortcuts: [
                    { label: 'Create DB', hash: 'database?tab=provisioning' },
                    { label: 'Data Browser', hash: 'database?tab=data' },
                    { label: 'SQL Console', hash: 'database?tab=sql' }
                ]
            },
            {
                id: 'qs-files',
                title: 'File Manager & Uploads',
                desc: 'Upload files and ZIP archives, edit site code in-browser, and monitor disk storage quota.',
                icon: '📁',
                route: 'files',
                actionLabel: 'Open Files →',
                shortcuts: [
                    { label: 'File Explorer', hash: 'files' },
                    { label: 'Upload ZIP', hash: 'files' },
                    { label: 'Disk Quota', hash: 'files' }
                ]
            },
            {
                id: 'qs-mail',
                title: 'Email Accounts & Webmail',
                desc: 'Create sovereign mailboxes (@yourdomain), set quotas, manage forwarders, and access webmail.',
                icon: '📬',
                route: 'mail',
                actionLabel: 'Open Mail →',
                shortcuts: [
                    { label: '+ New Mailbox', hash: 'mail' },
                    { label: 'Mail Queue', hash: 'mail' },
                    { label: 'DKIM Signer', hash: 'mail' }
                ]
            },
            {
                id: 'qs-apptoolkit',
                title: '1-Click Apps & CMS',
                desc: 'Install WordPress, Ghost, or framework web applications with 1 click and zero shell scripts.',
                icon: '📦',
                route: 'apptoolkit',
                actionLabel: 'Marketplace →',
                shortcuts: [
                    { label: 'WordPress', hash: 'apptoolkit' },
                    { label: 'App Staging', hash: 'apps' },
                    { label: 'Templates', hash: 'apptoolkit' }
                ]
            },
            {
                id: 'qs-deploy',
                title: 'Deployments & Backups',
                desc: 'Create instant full-site snapshot backups, view release history, or roll back in 1 click.',
                icon: '🚀',
                route: 'deploy',
                actionLabel: 'Backups →',
                shortcuts: [
                    { label: 'Snapshots', hash: 'deploy' },
                    { label: '1-Click Backup', hash: 'deploy' },
                    { label: 'Rollback', hash: 'deploy' }
                ]
            }
        ] : [
            {
                id: 'qs-domains',
                title: 'Virtual Hosts & Edge Routing',
                desc: 'Hybrid Nginx/Apache reverse-proxy stack, FastCGI cache purge, vhost preview & Let\'s Encrypt.',
                icon: '🌐',
                route: 'domains',
                actionLabel: 'Manage Domains →',
                shortcuts: [
                    { label: 'Web Server Engine', hash: 'domains?tab=vhost_config' },
                    { label: 'Proxy Routes', hash: 'domains?tab=proxy' },
                    { label: 'DNS Zones', hash: 'domains?tab=zone' }
                ]
            },
            {
                id: 'qs-database',
                title: 'Databases, Redis & Remote IPs',
                desc: 'MySQL & PostgreSQL provisioner, Redis in-memory cache diagnostics, remote host IP ACLs.',
                icon: '🗄️',
                route: 'database',
                actionLabel: 'Databases →',
                shortcuts: [
                    { label: 'Databases & Users', hash: 'database?tab=provisioning' },
                    { label: 'Remote Access', hash: 'database?tab=remote_access' },
                    { label: 'Redis Cache', hash: 'database?tab=redis' }
                ]
            },
            {
                id: 'qs-shield',
                title: 'Firewall, Ports & SSH Hardening',
                desc: 'Manage UFW/Netsh firewall ports, inspect Fail2ban ban jails, and harden SSH daemon security.',
                icon: '🛡️',
                route: 'shield',
                actionLabel: 'Open Security →',
                shortcuts: [
                    { label: 'Firewall Ports', hash: 'shield' },
                    { label: 'Fail2ban Jails', hash: 'shield' },
                    { label: 'SSH Hardening', hash: 'shield' }
                ]
            },
            {
                id: 'qs-server',
                title: 'Docker, Swap & Watchdog',
                desc: 'Container engine, OS system packages, swap file memory allocator, and self-healing watchdog daemon.',
                icon: '🖥️',
                route: 'server',
                actionLabel: 'Server Health →',
                shortcuts: [
                    { label: 'Docker Apps', hash: 'server' },
                    { label: 'Swap & Packages', hash: 'server' },
                    { label: 'Watchdog Alerts', hash: 'server' }
                ]
            },
            {
                id: 'qs-terminal',
                title: 'Web Terminal & Shell',
                desc: 'Direct in-browser command-line shell with ANSI colors, SPP CLI runner, and system diagnostics.',
                icon: '⬛',
                route: 'terminal',
                actionLabel: 'Launch Shell →',
                shortcuts: [
                    { label: 'Interactive Shell', hash: 'terminal' },
                    { label: 'spp status', hash: 'terminal' },
                    { label: 'CLI Center', hash: 'commands' }
                ]
            },
            {
                id: 'qs-fleet',
                title: 'Fleet & BIND DNS Cluster',
                desc: 'Multi-node server fleet orchestrator and real-time authoritative BIND DNS cluster replication.',
                icon: '🌐',
                route: 'fleet',
                actionLabel: 'Fleet Cluster →',
                shortcuts: [
                    { label: 'Cluster Nodes', hash: 'fleet' },
                    { label: 'Sync Nameservers', hash: 'fleet' },
                    { label: 'Node Health', hash: 'fleet' }
                ]
            }
        ];

        return html`
            <div class="quickstart-banner" data-key="quickstart-guide">
                <div class="quickstart-header" @click=${() => this.toggleBeginnerGuide()}>
                    <div class="quickstart-title-wrap">
                        <span class="quickstart-badge">🚀 New User Guide</span>
                        <div>
                            <h3 class="quickstart-title">Quick Start: What would you like to do?</h3>
                            <p class="quickstart-subtitle">Plain-English shortcuts for 1-click server and website administration</p>
                        </div>
                    </div>
                    <button class="quickstart-toggle-btn" type="button" title="${isOpen ? 'Collapse quick start' : 'Expand quick start'}">
                        <span>${isOpen ? 'Hide Guide' : 'Show Guide'}</span>
                        <span>${isOpen ? '▲' : '▼'}</span>
                    </button>
                </div>

                ${isOpen ? html`
                    <div class="quickstart-cards-grid">
                        ${quickItems.map(item => html`
                            <div class="qs-card" data-key="${item.id}">
                                <div class="qs-card-top">
                                    <div class="qs-icon-box">${item.icon}</div>
                                    <div class="qs-card-info">
                                        <h4>${item.title}</h4>
                                        <p>${item.desc}</p>
                                    </div>
                                </div>
                                <div class="qs-card-bottom">
                                    <div class="qs-card-chips">
                                        ${item.shortcuts.map(s => html`
                                            <a href="#${s.hash}" class="qs-chip">${s.label}</a>
                                        `)}
                                    </div>
                                    <a href="#${item.route}" class="qs-action-link">${item.actionLabel}</a>
                                </div>
                            </div>
                        `)}
                    </div>
                ` : ''}
            </div>
        `;
    }

    copyCommand(text, btnElement) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                if (btnElement) {
                    const originalText = btnElement.innerHTML;
                    btnElement.innerHTML = '✅ Copied!';
                    btnElement.style.background = '#059669';
                    btnElement.style.color = '#ffffff';
                    setTimeout(() => {
                        btnElement.innerHTML = originalText;
                        btnElement.style.background = '';
                        btnElement.style.color = '';
                    }, 2500);
                }
                if (window.admin?.notify) {
                    window.admin.notify("Copied command to clipboard! Paste it into your server shell.", "success");
                }
            }).catch(() => {
                prompt("Copy command to clipboard:", text);
            });
        } else {
            prompt("Copy command to clipboard:", text);
        }
    }

    setDoctorFilter(filter) {
        this.setState({ doctorFilter: filter });
    }

    async runSystemDoctor(silent = false) {
        this.setState({ runningDoctor: true });
        try {
            const res = await window.admin?.api('doctor_diagnostics');
            const data = (res && res.success && res.data) ? res.data : res;
            this.setState({ doctorData: data, runningDoctor: false });
            if (data && data.success) {
                if (!silent) {
                    window.admin?.notify(`🩺 System Doctor: ${data.passed_count}/${data.total_checks} checks passed (${data.health_score}% health score).`, data.status === 'HEALTHY' ? 'success' : 'warning');
                } else if (data.status === 'CRITICAL') {
                    window.admin?.notify(`⚠️ System Doctor Alert: ${data.fail_count} critical server configuration issue(s) detected.`, "warning");
                }
            }
        } catch (e) {
            this.setState({ runningDoctor: false });
            if (!silent) {
                window.admin?.notify("Error executing System Doctor diagnostics: " + e.message, "error");
            }
        }
    }

    async repairDoctorItem(checkId) {
        this.setState({ repairingDoctor: true });
        window.admin?.notify(`Executing targeted auto-repair for: ${checkId}...`, "info");
        try {
            const res = await window.admin?.api('doctor_repair', { check_id: checkId });
            const data = (res && res.success && res.data) ? res.data : res;
            if (res?.success || data?.success) {
                window.admin?.notify(data?.message || "Repair completed successfully!", "success");
                await this.runSystemDoctor(false);
            } else {
                window.admin?.notify(data?.error || "Repair returned warnings.", "warning");
            }
        } catch (e) {
            window.admin?.notify("Repair error: " + e.message, "error");
        } finally {
            this.setState({ repairingDoctor: false });
        }
    }

    renderSystemDoctorAlertBanner(doc) {
        if (!doc || doc.status === 'HEALTHY') return '';

        const isCritical = (doc.status === 'CRITICAL');
        const failingChecks = (doc.checks || []).filter(c => c.status !== 'pass');
        const canAutoRepairAny = failingChecks.some(c => c.can_repair);
        const topIssues = failingChecks.slice(0, 3);
        const remainingCount = failingChecks.length - topIssues.length;

        return html`
            <div class="doctor-alert-banner ${isCritical ? 'banner-critical' : 'banner-warning'}" data-key="doctor-alert-banner">
                <div class="doctor-alert-left">
                    <div class="doctor-alert-icon">${isCritical ? '🚨' : '⚠️'}</div>
                    <div style="flex: 1;">
                        <div class="doctor-alert-title">
                            <span>${isCritical ? 'Sovereign Host Incompatibilities Detected' : 'Server Configuration & Connectivity Warnings'}</span>
                            <span class="badge ${isCritical ? 'badge-danger' : 'badge-warning'}" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 9999px; text-transform: uppercase;">
                                ${doc.health_score}% Health Score
                            </span>
                            ${doc.fail_count > 0 ? html`
                                <span class="badge badge-danger" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 9999px;">
                                    ${doc.fail_count} Critical
                                </span>
                            ` : ''}
                            ${doc.warning_count > 0 ? html`
                                <span class="badge badge-warning" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 9999px;">
                                    ${doc.warning_count} Warnings
                                </span>
                            ` : ''}
                        </div>
                        <p class="doctor-alert-desc">
                            Your host environment requires configuration adjustments to ensure uninterrupted web routing, automated TLS, and email deliverability across sovereign domains.
                        </p>
                        <div class="doctor-alert-issues">
                            ${topIssues.map(c => html`
                                <div class="doctor-alert-issue">
                                    <span style="font-size: 0.7rem; font-weight: 800; color: ${c.status === 'fail' ? '#ef4444' : '#f59e0b'};">
                                        ${c.status === 'fail' ? '✖ FAIL' : '⚠ WARN'}
                                    </span>
                                    <strong style="color: var(--hub-text-primary); font-size: 0.82rem;">${c.title}:</strong>
                                    <span style="color: var(--hub-text-secondary); font-size: 0.8rem;">${c.message}</span>
                                </div>
                            `)}
                            ${remainingCount > 0 ? html`
                                <div style="font-size: 0.76rem; color: var(--hub-text-muted); font-weight: 600; margin-top: 2px;">
                                    +${remainingCount} more item${remainingCount > 1 ? 's' : ''} listed in the Compatibility Matrix below.
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <div class="doctor-alert-actions">
                    ${canAutoRepairAny ? html`
                        <button type="button" class="btn btn-sm btn-success" style="background: linear-gradient(135deg, #059669, #10b981); border: none; font-weight: 700; white-space: nowrap;" @click=${() => this.repairDoctorItem('fix_all')} ?disabled=${this.state.repairingDoctor}>
                            ${this.state.repairingDoctor ? 'Healing Host…' : '⚡ 1-Click Auto-Repair All'}
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-sm btn-outline" style="white-space: nowrap; font-size: 0.78rem;" @click=${() => {
                        const el = document.getElementById('system-doctor-section');
                        if (el) el.scrollIntoView({ behavior: 'smooth' });
                    }}>
                        Inspect Details &amp; Commands ↓
                    </button>
                </div>
            </div>
        `;
    }

    renderSystemDoctorWidget() {
        const doc = this.state.doctorData;
        const isRunning = this.state.runningDoctor;
        const isRepairing = this.state.repairingDoctor;
        const currentFilter = this.state.doctorFilter || 'all';

        const checks = doc?.checks || [];
        const failCount = doc?.fail_count || 0;
        const warnCount = doc?.warning_count || 0;
        const passCount = doc?.passed_count || 0;

        const filteredChecks = checks.filter(c => {
            if (currentFilter === 'all') return true;
            if (currentFilter === 'issues') return c.status !== 'pass';
            return c.category === currentFilter;
        });

        const connectivityCount = checks.filter(c => c.category === 'connectivity').length;
        const compatibilityCount = checks.filter(c => c.category === 'compatibility').length;
        const securityCount = checks.filter(c => c.category === 'security').length;
        const performanceCount = checks.filter(c => c.category === 'performance').length;

        return html`
            <div id="system-doctor-section" class="doctor-widget-wrap" data-key="doctor-widget">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                            <h3 style="margin: 0; font-size: 1.15rem; color: var(--hub-text-primary); display: flex; align-items: center; gap: 0.5rem; font-family: 'Outfit', sans-serif;">
                                <span>🩺</span> Sovereign System Doctor &amp; 14-Point Compatibility Matrix
                            </h3>
                            ${doc ? html`
                                <span class="badge ${doc.status === 'HEALTHY' ? 'badge-success' : (doc.status === 'WARNING' ? 'badge-warning' : 'badge-danger')}" style="padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 800; font-size: 0.75rem;">
                                    ${doc.health_score}% HEALTH SCORE &bull; ${doc.status}
                                </span>
                            ` : html`
                                <span class="badge badge-info" style="font-size: 0.75rem; border-radius: 6px;">READY TO SCAN</span>
                            `}
                        </div>
                        <p style="margin: 0.35rem 0 0 0; font-size: 0.82rem; color: var(--hub-text-muted); line-height: 1.5; max-width: 850px;">
                            Autonomous 14-point audit across Web server conflicts (ports 80/443), BIND9 DNS (port 53), Root TLD glue alignment, Multi-Domain SSL SAN coverage, Inbound Mail (port 25, spp-mail.service), Outbound Mail reachability, Deliverability DNS (SPF/DKIM/DMARC/MX), PHP runtime extensions, filesystem permissions, VPS swap space, UFW firewall, SQLite WAL mode, and zero-prompt sudoers.
                        </p>
                    </div>

                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                        <button type="button" class="btn btn-sm btn-primary" style="background: linear-gradient(135deg, #0284c7, #0ea5e9); border: none; font-weight: 700;" @click=${() => this.runSystemDoctor(false)} ?disabled=${isRunning}>
                            ${isRunning ? 'Scanning Infrastructure…' : '🔍 Run 14-Point Health Scan'}
                        </button>
                        ${doc && doc.health_score < 100 ? html`
                            <button type="button" class="btn btn-sm btn-success" style="background: linear-gradient(135deg, #059669, #10b981); border: none; font-weight: 700;" @click=${() => this.repairDoctorItem('fix_all')} ?disabled=${isRepairing}>
                                ${isRepairing ? 'Healing Host…' : '⚡ 1-Click Auto-Repair All'}
                            </button>
                        ` : ''}
                    </div>
                </div>

                ${doc && doc.checks ? html`
                    <!-- Filter Pills Strip -->
                    <div class="doctor-filter-bar">
                        <button type="button" class="doctor-pill ${currentFilter === 'all' ? 'active' : ''}" @click=${() => this.setDoctorFilter('all')}>
                            All Checks (${checks.length})
                        </button>
                        <button type="button" class="doctor-pill ${currentFilter === 'issues' ? 'active' : ''}" @click=${() => this.setDoctorFilter('issues')}>
                            ⚠️ Attention Needed (${failCount + warnCount})
                        </button>
                        <button type="button" class="doctor-pill ${currentFilter === 'connectivity' ? 'active' : ''}" @click=${() => this.setDoctorFilter('connectivity')}>
                            🌐 Connectivity (${connectivityCount})
                        </button>
                        <button type="button" class="doctor-pill ${currentFilter === 'compatibility' ? 'active' : ''}" @click=${() => this.setDoctorFilter('compatibility')}>
                            ⚙️ Compatibility (${compatibilityCount})
                        </button>
                        <button type="button" class="doctor-pill ${currentFilter === 'security' ? 'active' : ''}" @click=${() => this.setDoctorFilter('security')}>
                            🔒 Security (${securityCount})
                        </button>
                        <button type="button" class="doctor-pill ${currentFilter === 'performance' ? 'active' : ''}" @click=${() => this.setDoctorFilter('performance')}>
                            🚀 Performance (${performanceCount})
                        </button>
                    </div>

                    <!-- Diagnostic Cards Grid -->
                    <div class="doctor-cards-grid">
                        ${filteredChecks.map(c => html`
                            <div class="doctor-check-card card-${c.status}" data-key="doc-${c.id}">
                                <div>
                                    <div class="doctor-card-top">
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <span class="badge ${c.category === 'connectivity' ? 'badge-cloud' : (c.category === 'compatibility' ? 'badge-studio' : (c.category === 'security' ? 'badge-declarative' : 'badge-runtime'))}" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 800; padding: 2px 7px; border-radius: 4px;">
                                                ${c.category}
                                            </span>
                                            <span style="font-size: 0.68rem; font-weight: 700; color: var(--hub-text-muted); text-transform: uppercase;">
                                                SEV-${c.severity === 'critical' ? '1' : (c.severity === 'high' ? '2' : (c.severity === 'medium' ? '3' : '4'))}
                                            </span>
                                        </div>
                                        <span style="font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 9999px; background: ${c.status === 'pass' ? 'rgba(16,185,129,0.15)' : (c.status === 'warn' ? 'rgba(245,158,11,0.15)' : 'rgba(239,68,68,0.15)')}; color: ${c.status === 'pass' ? '#10b981' : (c.status === 'warn' ? '#f59e0b' : '#ef4444')};">
                                            ${c.status === 'pass' ? '✔ PASS' : (c.status === 'warn' ? '⚠ WARN' : '✖ FAIL')}
                                        </span>
                                    </div>

                                    <h4 class="doctor-card-title" style="margin-top: 6px;">${c.title}</h4>
                                    <div class="doctor-card-msg">${c.message}</div>

                                    ${c.id === 'ssl_subdomain_coverage' && c.missing_subdomains && c.missing_subdomains.length > 0 ? html`
                                        <div style="margin-top: 6px; font-size: 0.75rem; color: #f59e0b;">
                                            <strong>Missing Subdomains:</strong> ${c.missing_subdomains.join(', ')}
                                        </div>
                                    ` : ''}

                                    ${c.id === 'mail_deliverability_dns' && c.matrix ? html`
                                        <div style="margin-top: 6px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; font-size: 0.72rem; text-align: center;">
                                            <div style="padding: 3px; background: rgba(0,0,0,0.2); border-radius: 4px;">
                                                <span style="display: block; font-weight: 700; color: var(--hub-text-muted);">MX</span>
                                                <span style="color: ${c.matrix.mx ? '#10b981' : '#ef4444'}; font-weight: 800;">${c.matrix.mx ? '✔' : '✖'}</span>
                                            </div>
                                            <div style="padding: 3px; background: rgba(0,0,0,0.2); border-radius: 4px;">
                                                <span style="display: block; font-weight: 700; color: var(--hub-text-muted);">SPF</span>
                                                <span style="color: ${c.matrix.spf ? '#10b981' : '#ef4444'}; font-weight: 800;">${c.matrix.spf ? '✔' : '✖'}</span>
                                            </div>
                                            <div style="padding: 3px; background: rgba(0,0,0,0.2); border-radius: 4px;">
                                                <span style="display: block; font-weight: 700; color: var(--hub-text-muted);">DKIM</span>
                                                <span style="color: ${c.matrix.dkim ? '#10b981' : '#ef4444'}; font-weight: 800;">${c.matrix.dkim ? '✔' : '✖'}</span>
                                            </div>
                                            <div style="padding: 3px; background: rgba(0,0,0,0.2); border-radius: 4px;">
                                                <span style="display: block; font-weight: 700; color: var(--hub-text-muted);">DMARC</span>
                                                <span style="color: ${c.matrix.dmarc ? '#10b981' : '#ef4444'}; font-weight: 800;">${c.matrix.dmarc ? '✔' : '✖'}</span>
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>

                                <!-- Action & Guidance Area -->
                                <div>
                                    ${c.status !== 'pass' ? html`
                                        ${c.resolution_steps ? html`
                                            <div class="doctor-guidance-box" style="margin-bottom: 6px;">
                                                <strong style="color: #38bdf8; display: block; margin-bottom: 2px;">💡 How to Resolve:</strong>
                                                ${c.resolution_steps}
                                            </div>
                                        ` : ''}

                                        ${c.resolution_command ? html`
                                            <div class="doctor-code-row" style="margin-bottom: 6px;">
                                                <code class="doctor-code-text">${c.resolution_command}</code>
                                                <button type="button" class="doctor-copy-btn" @click=${(e) => this.copyCommand(c.resolution_command, e.currentTarget)} title="Copy command to clipboard">
                                                    📋 Copy
                                                </button>
                                            </div>
                                        ` : ''}

                                        ${c.can_repair ? html`
                                            <button type="button" class="btn btn-xs btn-primary" style="background: linear-gradient(135deg, #0284c7, #0ea5e9); border: none; font-weight: 700; padding: 4px 10px; border-radius: 6px; font-size: 0.74rem;" @click=${() => this.repairDoctorItem(c.id)} ?disabled=${isRepairing}>
                                                ⚡ ${c.repair_label || 'Auto-Repair'}
                                            </button>
                                        ` : ''}
                                    ` : html`
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.74rem; color: #10b981; font-weight: 600;">
                                            <span>✔ Configuration verified</span>
                                            ${c.resolution_command ? html`
                                                <button type="button" class="doctor-copy-btn" style="border: none; background: transparent; color: var(--hub-text-muted); font-size: 0.7rem;" @click=${(e) => this.copyCommand(c.resolution_command, e.currentTarget)} title="Copy verification command">
                                                    📋 Command
                                                </button>
                                            ` : ''}
                                        </div>
                                    `}
                                </div>
                            </div>
                        `)}
                    </div>
                ` : html`
                    <div style="padding: 2.5rem 1rem; text-align: center; color: var(--hub-text-muted);">
                        <div style="font-size: 2.2rem; margin-bottom: 0.5rem; opacity: 0.7;">🩺</div>
                        <h4 style="margin: 0 0 0.3rem 0; color: var(--hub-text-primary); font-weight: 700;">Infrastructure Scan Pending</h4>
                        <p style="margin: 0; font-size: 0.85rem;">Click "Run 14-Point Health Scan" above to audit all host services, ports, DNS, SSL, and mail daemon connectivity.</p>
                    </div>
                `}
            </div>
        `;
    }

    render() {
        const {
            loading, health, appCount, moduleCount, entityCount, pageCount,
            workflowCount, availableApps, auditLogs, selectedApp,
            activePillarFilter, searchQuery, uiMode
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

        // Complete catalog of 18 Studio Hub Cards across the 4 structural pillars
        const studioCards = [
            // ── Pillar 1: Cloud & Hosting Control Plane ──
            {
                pillar: 'cloud',
                id: 'domains',
                title: 'Virtual Hosts & Edge Routing',
                icon: '🌐',
                iconClass: 'icon-cloud',
                badge: 'Multi-Tenant VHosts',
                badgeTheme: 'badge-cloud',
                btnClass: 'btn-cloud',
                description: 'Multi-tenant virtual host orchestrator, automated ACME/AutoSSL HTTP-01 certificate challenges, DNS health tracking, and canonical redirection rules.',
                primaryAction: 'Manage Virtual Hosts →',
                route: 'domains',
                tags: ['domains', 'vhost', 'ssl', 'tls', 'dns', 'hosting', 'canonical', 'cloud', 'proxy'],
                shortcuts: [
                    { label: 'Virtual Hosts', hash: 'domains' },
                    { label: 'DNS Diagnostics', hash: 'domains' },
                    { label: 'Microservice Proxy', hash: 'domains' },
                    { label: 'AutoSSL Challenges', hash: 'domains' }
                ]
            },
            {
                pillar: 'cloud',
                id: 'reverse-proxy',
                title: 'Polyglot Microservice Proxy',
                icon: '⚡',
                iconClass: 'icon-cloud',
                badge: 'High-Throughput Gateway',
                badgeTheme: 'badge-cloud',
                btnClass: 'btn-cloud',
                description: 'Upstream reverse proxy routing targeting polyglot backends (Node.js Express, Python FastAPI, Go, .NET, Java) with timeout protection and header forwarding.',
                primaryAction: 'Configure Reverse Proxy →',
                route: 'domains',
                tags: ['proxy', 'reverse proxy', 'node', 'python', 'go', 'dotnet', 'microservices', 'gateway'],
                shortcuts: [
                    { label: 'Proxy Config', hash: 'domains' },
                    { label: 'Node.js Express', hash: 'domains' },
                    { label: 'Python FastAPI', hash: 'domains' }
                ]
            },
            {
                pillar: 'cloud',
                id: 'storage-treemap',
                title: 'Storage Treemap & Quotas',
                icon: '📊',
                iconClass: 'icon-cloud',
                badge: 'Recursive Visualizer',
                badgeTheme: 'badge-cloud',
                btnClass: 'btn-cloud',
                description: 'Interactive squarified disk usage visualizer with deep recursive folder traversal, breadcrumb navigation, quota alerts, and waste detection.',
                primaryAction: 'Open Storage Treemap →',
                route: 'files',
                tags: ['storage', 'treemap', 'disk', 'quota', 'files', 'usage', 'cleanup'],
                shortcuts: [
                    { label: 'Disk Treemap', hash: 'files' },
                    { label: 'File Explorer', hash: 'files' },
                    { label: 'Upload Archive (ZIP)', hash: 'files' }
                ]
            },
            {
                pillar: 'cloud',
                id: 'web-tools',
                title: 'Web Server Utilities',
                icon: '🌐',
                iconClass: 'icon-cloud',
                badge: 'Apache & HTTP Tools',
                badgeTheme: 'badge-cloud',
                btnClass: 'btn-cloud',
                description: 'Custom Error Pages (400, 401, 403, 404, 500) synchronized with .htaccess, directory indexing protection, custom MIME types, and real-time access log tails.',
                primaryAction: 'Web Server Tools →',
                route: 'files',
                tags: ['web server', 'apache', 'error pages', 'indexing', 'mime', 'logs', 'htaccess'],
                shortcuts: [
                    { label: 'Custom Error Pages', hash: 'files' },
                    { label: 'Directory Indexing', hash: 'files' },
                    { label: 'MIME Types', hash: 'files' },
                    { label: 'Access Logs', hash: 'files' }
                ]
            },
            {
                pillar: 'cloud',
                id: 'web-terminal',
                title: 'Cloud Web Terminal',
                icon: '⬛',
                iconClass: 'icon-cloud',
                badge: 'Interactive Shell',
                badgeTheme: 'badge-cloud',
                btnClass: 'btn-cloud',
                description: 'Direct in-browser command-line shell with ANSI color output, command history, tab completion, SPP CLI runner, and system diagnostics.',
                primaryAction: 'Launch Web Terminal →',
                route: 'terminal',
                tags: ['terminal', 'shell', 'cli', 'bash', 'ssh', 'console', 'spp', 'runner'],
                shortcuts: [
                    { label: 'Interactive Shell', hash: 'terminal' },
                    { label: 'Run spp status', hash: 'terminal' },
                    { label: 'CLI Command Center', hash: 'commands' }
                ]
            },
            {
                pillar: 'cloud',
                id: 'deployments',
                title: 'Deployments & Backups',
                icon: '🚀',
                iconClass: 'icon-cloud',
                badge: 'Distributed Mutex',
                badgeTheme: 'badge-cloud',
                btnClass: 'btn-cloud',
                description: 'SPPDeploy automated zero-downtime releases, distributed mutex deployment locks, instant rollbacks, and full archive snapshot backups.',
                primaryAction: 'Open Deployments →',
                route: 'deploy',
                tags: ['deploy', 'backup', 'snapshots', 'git', 'releases', 'rollback', 'sppdeploy'],
                shortcuts: [
                    { label: 'Releases & Status', hash: 'deploy' },
                    { label: 'Create Snapshot', hash: 'deploy' },
                    { label: 'Rollback Release', hash: 'deploy' }
                ]
            },
            {
                pillar: 'cloud',
                id: 'mail',
                title: 'Enterprise Mail & Gateway',
                icon: '📬',
                iconClass: 'icon-cloud',
                badge: 'RFC 6376 & 5321 Gateway',
                badgeTheme: 'badge-cloud',
                btnClass: 'btn-cloud',
                description: 'Pure-PHP RFC 5321 SMTP socket engine, RFC 6376 DKIM signer with 2048-bit RSA keys, SPF/DMARC deliverability health auditor, and SQLite WAL queue spool.',
                primaryAction: 'Open Mail Gateway →',
                route: 'mail',
                tags: ['mail', 'smtp', 'dkim', 'spf', 'dmarc', 'gateway', 'queue', 'spool', 'deliverability'],
                shortcuts: [
                    { label: 'Outbound Queue', hash: 'mail' },
                    { label: 'DKIM Keypair Generator', hash: 'mail' },
                    { label: 'Deliverability Auditor', hash: 'mail' },
                    { label: 'SMTP Diagnostics', hash: 'mail' }
                ]
            },

            // ── Pillar 2: Developer Studio Suite ──
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

        const cloudPillarCards = filteredCards.filter(c => c.pillar === 'cloud');
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

                
                /* ── Sovereign UI Mode Bar ── */
                .dashboard-mode-bar {
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 14px;
                    padding: 0.85rem 1.25rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1.25rem;
                    gap: 1rem;
                    flex-wrap: wrap;
                    box-shadow: var(--hub-card-shadow);
                }
                .dash-mode-info {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .dash-mode-pill {
                    font-size: 0.75rem;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    padding: 4px 10px;
                    border-radius: 6px;
                }
                .dash-mode-pill.pill-normal {
                    background: rgba(2, 132, 199, 0.15);
                    color: #0284c7;
                    border: 1px solid rgba(2, 132, 199, 0.35);
                }
                .dash-mode-pill.pill-expert {
                    background: rgba(245, 158, 11, 0.15);
                    color: #f59e0b;
                    border: 1px solid rgba(245, 158, 11, 0.35);
                }
                .dash-mode-text {
                    font-size: 0.84rem;
                    color: var(--hub-text-secondary);
                    font-weight: 500;
                }
                .dash-mode-toggle-btn {
                    background: var(--hub-chip-bg);
                    border: 1px solid var(--hub-chip-border);
                    color: var(--hub-text-primary);
                    font-size: 0.8rem;
                    font-weight: 700;
                    padding: 6px 14px;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: all 0.2s;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }
                .dash-mode-toggle-btn:hover {
                    border-color: var(--primary);
                    background: var(--primary);
                    color: #ffffff;
                }

                /* ── Sovereign Quick Start Onboarding Deck (Novice-Friendly) ── */
                .quickstart-banner {
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 18px;
                    padding: 1.35rem 1.6rem;
                    margin-bottom: 1.75rem;
                    box-shadow: var(--hub-card-shadow);
                    transition: all 0.25s ease;
                }
                .quickstart-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    cursor: pointer;
                    user-select: none;
                }
                .quickstart-title-wrap {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .quickstart-badge {
                    background: linear-gradient(135deg, #f97316, #ea580c);
                    color: #ffffff;
                    font-size: 0.72rem;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    padding: 4px 10px;
                    border-radius: 6px;
                }
                .quickstart-title {
                    font-size: 1.15rem;
                    font-weight: 800;
                    margin: 0;
                    color: var(--hub-text-primary);
                    font-family: 'Outfit', sans-serif;
                }
                .quickstart-subtitle {
                    font-size: 0.85rem;
                    color: var(--hub-text-muted);
                    margin: 2px 0 0 0;
                }
                .quickstart-toggle-btn {
                    background: var(--hub-chip-bg);
                    border: 1px solid var(--hub-chip-border);
                    color: var(--hub-text-secondary);
                    font-size: 0.82rem;
                    font-weight: 700;
                    padding: 6px 14px;
                    border-radius: 8px;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    transition: all 0.2s;
                }
                .quickstart-toggle-btn:hover {
                    color: var(--hub-text-primary);
                    border-color: #f97316;
                }
                .quickstart-cards-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 1.15rem;
                    margin-top: 1.25rem;
                    padding-top: 1.15rem;
                    border-top: 1px solid var(--hub-border);
                }
                @media (max-width: 1100px) {
                    .quickstart-cards-grid { grid-template-columns: repeat(2, 1fr); }
                }
                @media (max-width: 700px) {
                    .quickstart-cards-grid { grid-template-columns: 1fr; }
                }
                .qs-card {
                    background: var(--hub-chip-bg);
                    border: 1px solid var(--hub-chip-border);
                    border-radius: 14px;
                    padding: 1.15rem;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                    text-decoration: none;
                }
                .qs-card:hover {
                    transform: translateY(-2px);
                    border-color: #f97316;
                    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
                }
                .qs-card-top {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    margin-bottom: 0.75rem;
                }
                .qs-icon-box {
                    font-size: 1.4rem;
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-chip-border);
                    flex-shrink: 0;
                }
                .qs-card-info h4 {
                    font-size: 0.98rem;
                    font-weight: 800;
                    margin: 0 0 4px 0;
                    color: var(--hub-text-primary);
                }
                .qs-card-info p {
                    font-size: 0.8rem;
                    line-height: 1.45;
                    color: var(--hub-text-muted);
                    margin: 0;
                }
                .qs-card-bottom {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-top: 0.85rem;
                    padding-top: 0.75rem;
                    border-top: 1px dashed var(--hub-chip-border);
                }
                .qs-card-chips {
                    display: flex;
                    gap: 6px;
                    flex-wrap: wrap;
                }
                .qs-chip {
                    font-size: 0.72rem;
                    font-weight: 700;
                    padding: 2px 7px;
                    border-radius: 5px;
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-chip-border);
                    color: var(--hub-text-secondary);
                    text-decoration: none;
                    transition: all 0.15s;
                }
                .qs-chip:hover {
                    color: #f97316;
                    border-color: #f97316;
                }
                .qs-action-link {
                    font-size: 0.78rem;
                    font-weight: 800;
                    color: #f97316;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    text-decoration: none;
                    white-space: nowrap;
                }
                .qs-action-link:hover {
                    text-decoration: underline;
                }

                /* ── Context-Aware Metric Cards ── */
                .metrics-grid {
                    display: grid;
                    grid-template-columns: repeat(6, 1fr);
                    gap: 1.15rem;
                    margin-bottom: 1.75rem;
                }
                @media (max-width: 1400px) { .metrics-grid { grid-template-columns: repeat(3, 1fr); } }
                @media (max-width: 800px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 500px) { .metrics-grid { grid-template-columns: 1fr; } }

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
                .metric-card.accent-orange::before { background: #f97316; }
                .metric-card.accent-cyan::before { background: #06b6d4; }
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
                .pillar-pill.active.pill-cloud {
                    border-color: #f97316;
                    color: #ffffff;
                    background: #f97316;
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
                .hub-card.pillar-cloud:hover { border-color: #f97316; }
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
                .hub-card-icon-box.icon-cloud { color: #f97316; }
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
                .badge-cloud {
                    color: #c2410c;
                    background: #ffedd5;
                    border-color: #fed7aa;
                }
                [data-theme="night"] .badge-cloud {
                    color: #fb923c;
                    background: rgba(249, 115, 22, 0.15);
                    border-color: rgba(249, 115, 22, 0.35);
                }
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
                .btn-cloud,
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
                .hub-primary-btn.btn-cloud,
                .hub-card-action-btn.btn-cloud,
                .btn-cloud {
                    background: linear-gradient(135deg, #f97316, #ea580c);
                }
                .hub-primary-btn.btn-cloud:hover,
                .hub-card-action-btn.btn-cloud:hover,
                .btn-cloud:hover {
                    background: linear-gradient(135deg, #ea580c, #c2410c);
                    box-shadow: 0 4px 14px rgba(249, 115, 22, 0.4);
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

                /* ── Sovereign System Doctor & Compatibility Matrix ── */
                .doctor-alert-banner {
                    border-radius: 14px;
                    padding: 1.15rem 1.4rem;
                    margin-bottom: 1.5rem;
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 1.25rem;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.18);
                    transition: all 0.25s ease;
                }
                .doctor-alert-banner.banner-critical {
                    background: linear-gradient(135deg, rgba(239, 68, 68, 0.16), rgba(185, 28, 28, 0.08));
                    border: 1px solid rgba(239, 68, 68, 0.45);
                    border-left: 6px solid #ef4444;
                }
                .doctor-alert-banner.banner-warning {
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(180, 83, 9, 0.08));
                    border: 1px solid rgba(245, 158, 11, 0.45);
                    border-left: 6px solid #f59e0b;
                }
                .doctor-alert-left {
                    display: flex;
                    align-items: flex-start;
                    gap: 14px;
                    flex: 1;
                }
                .doctor-alert-icon {
                    font-size: 1.8rem;
                    line-height: 1;
                    flex-shrink: 0;
                }
                .doctor-alert-title {
                    font-size: 1.05rem;
                    font-weight: 800;
                    margin: 0 0 4px 0;
                    color: var(--hub-text-primary);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .doctor-alert-desc {
                    font-size: 0.85rem;
                    color: var(--hub-text-secondary);
                    margin: 0 0 8px 0;
                    line-height: 1.4;
                }
                .doctor-alert-issues {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .doctor-alert-issue {
                    font-size: 0.82rem;
                    color: var(--hub-text-primary);
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .doctor-alert-actions {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                    flex-shrink: 0;
                    align-items: flex-end;
                }
                @media (max-width: 800px) {
                    .doctor-alert-banner {
                        flex-direction: column;
                    }
                    .doctor-alert-actions {
                        width: 100%;
                        align-items: stretch;
                    }
                }
                .doctor-widget-wrap {
                    margin-bottom: 2rem;
                    background: var(--hub-bg);
                    border: 1px solid var(--hub-border);
                    border-radius: 18px;
                    padding: 1.4rem 1.6rem;
                    box-shadow: var(--hub-card-shadow);
                }
                .doctor-filter-bar {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                    margin: 1.1rem 0 1.25rem 0;
                    padding-bottom: 0.85rem;
                    border-bottom: 1px solid var(--hub-border);
                }
                .doctor-pill {
                    background: var(--hub-chip-bg);
                    border: 1px solid var(--hub-chip-border);
                    color: var(--hub-text-secondary);
                    padding: 6px 14px;
                    border-radius: 9999px;
                    font-size: 0.8rem;
                    font-weight: 700;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .doctor-pill:hover {
                    color: var(--hub-text-primary);
                    border-color: #38bdf8;
                }
                .doctor-pill.active {
                    background: #0284c7;
                    color: #ffffff;
                    border-color: #0284c7;
                    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35);
                }
                .doctor-cards-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
                    gap: 1.15rem;
                }
                @media (max-width: 768px) {
                    .doctor-cards-grid {
                        grid-template-columns: 1fr;
                    }
                }
                .doctor-check-card {
                    background: var(--hub-chip-bg);
                    border-radius: 14px;
                    padding: 1.15rem 1.25rem;
                    border: 1px solid var(--hub-chip-border);
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    gap: 0.85rem;
                    transition: all 0.2s ease;
                }
                .doctor-check-card.card-pass {
                    border-left: 4px solid #10b981;
                }
                .doctor-check-card.card-warn {
                    border-left: 4px solid #f59e0b;
                    background: rgba(245, 158, 11, 0.04);
                }
                .doctor-check-card.card-fail {
                    border-left: 4px solid #ef4444;
                    background: rgba(239, 68, 68, 0.04);
                }
                .doctor-card-top {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 8px;
                }
                .doctor-card-title {
                    font-size: 0.95rem;
                    font-weight: 800;
                    margin: 0;
                    color: var(--hub-text-primary);
                }
                .doctor-card-msg {
                    font-size: 0.82rem;
                    line-height: 1.45;
                    color: var(--hub-text-secondary);
                    margin-top: 4px;
                }
                .doctor-guidance-box {
                    background: rgba(0, 0, 0, 0.22);
                    border-radius: 8px;
                    border: 1px dashed var(--hub-chip-border);
                    padding: 9px 12px;
                    font-size: 0.8rem;
                    line-height: 1.45;
                    color: var(--hub-text-primary);
                }
                .doctor-code-row {
                    background: #090d16;
                    border: 1px solid rgba(255,255,255,0.1);
                    border-radius: 8px;
                    padding: 8px 12px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 0.76rem;
                    overflow-x: auto;
                }
                .doctor-code-text {
                    color: #38bdf8;
                    white-space: pre-wrap;
                    word-break: break-all;
                }
                .doctor-copy-btn {
                    background: rgba(56, 189, 248, 0.15);
                    border: 1px solid rgba(56, 189, 248, 0.4);
                    color: #38bdf8;
                    padding: 4px 10px;
                    border-radius: 6px;
                    font-size: 0.72rem;
                    font-weight: 700;
                    cursor: pointer;
                    white-space: nowrap;
                    transition: all 0.2s;
                    flex-shrink: 0;
                }
                .doctor-copy-btn:hover {
                    background: #0284c7;
                    color: #ffffff;
                }
            </style>

            <div class="dash-container">

                <!-- ═══════════════ Hero Studio Banner ═══════════════ -->
                <div class="studio-hero" data-key="studio-hero">
                    <div class="studio-hero-inner">
                        <div class="studio-hero-top">
                            <div class="studio-title-area">
                                <h1>
                                    <span>🚀</span> SPP Developer & Cloud Studio Hub
                                </h1>
                                <p class="studio-subtitle">
                                    Unified command center for the SPP Framework & Cloud Hosting — architect, reverse proxy, verify, and ship with zero friction.
                                </p>
                                <div class="hero-instance-strip" @click=${() => window.admin && window.admin.copyServingDir()} title="Active Serving Directory for this SPP copy (Click to copy full path)">
                                    <span class="hero-instance-icon">📁</span>
                                    <span class="hero-instance-label">Serving Directory:</span>
                                    <code class="hero-instance-code">${window.SPP_SYSTEM_ENV?.serving_dir || '...'}</code>
                                    <span class="hero-instance-copy" title="Copy path">📋</span>
                                </div>
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

                <!-- ═══════════════ Sovereign UI Mode Indicator Bar ═══════════════ -->
                <div class="dashboard-mode-bar" data-key="dash-mode-bar">
                    <div class="dash-mode-info">
                        <span class="dash-mode-pill ${uiMode === 'normal' ? 'pill-normal' : 'pill-expert'}">
                            ${uiMode === 'normal' ? '🌐 Normal Mode' : '⚡ Expert Mode'}
                        </span>
                        <span class="dash-mode-text">
                            ${uiMode === 'normal' 
                                ? 'Simplified site & application workspace (Websites, Files, Databases, Email).'
                                : 'Full sovereign server control (Nginx/Apache engines, Docker, UFW firewall, Watchdog daemon, Terminal).'}
                        </span>
                    </div>
                    <button type="button" class="dash-mode-toggle-btn" @click=${() => window.admin && window.admin.setUIMode(uiMode === 'normal' ? 'expert' : 'normal')}>
                        ${uiMode === 'normal' ? '⚡ Switch to Expert Mode' : '🌐 Switch to Normal Mode'}
                    </button>
                </div>

                <!-- ═══════════════ Sovereign Server Compatibility & Connectivity Alert Banner ═══════════════ -->
                ${this.renderSystemDoctorAlertBanner(this.state.doctorData)}

                <!-- ═══════════════ Sovereign Quick Start Onboarding (Novice-Friendly) ═══════════════ -->
                ${this.renderQuickStartGuide()}

                <!-- ═══════════════ Sovereign System Doctor & Self-Healing ═══════════════ -->
                ${this.renderSystemDoctorWidget()}

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

                    <!-- 3. Virtual Hosts & Edge Routing -->
                    <div class="metric-card accent-orange" data-key="metric-domains" @click=${() => location.hash = 'domains'}>
                        <div class="metric-card-top">
                            <span class="metric-tag badge-cloud">Cloud & Proxy</span>
                            <div class="metric-icon" style="color: #f97316;">🌐</div>
                        </div>
                        <div class="metric-value" data-metric-target="domains" style="color: #f97316;">${this.state.domainCount || 1}</div>
                        <div class="metric-label">Virtual Hosts & Routing</div>
                        <div class="metric-sub">Reverse Proxy • SSL/TLS</div>
                    </div>

                    <!-- 4. Storage & Treemap -->
                    <div class="metric-card accent-cyan" data-key="metric-storage" @click=${() => location.hash = 'files'}>
                        <div class="metric-card-top">
                            <span class="metric-tag" style="color: #0891b2; background: #cffafe; border-color: #a5f3fc;">Cloud Storage</span>
                            <div class="metric-icon" style="color: #06b6d4;">📊</div>
                        </div>
                        <div class="metric-value" data-metric-target="disk" style="font-size: 1.65rem; color: #0891b2; margin: 0.55rem 0;">${this.state.diskUsageFormatted || 'Active'}</div>
                        <div class="metric-label">Storage & Treemap</div>
                        <div class="metric-sub">Disk Quotas • Web Tools</div>
                    </div>

                    <!-- 5. Apps & Modules -->
                    <div class="metric-card accent-pink" data-key="metric-ecosystem" @click=${() => location.hash = 'coder'}>
                        <div class="metric-card-top">
                            <span class="metric-tag" style="color: #be185d; background: #fce7f3; border-color: #fbcfe8;">Ecosystem</span>
                            <div class="metric-icon" style="color: #db2777;">📦</div>
                        </div>
                        <div class="metric-value" data-metric-target="apps" style="color: #db2777;">${appCount} <span style="font-size: 1.2rem; color: var(--hub-text-muted); font-weight: 500;">/ ${moduleCount}</span></div>
                        <div class="metric-label">Applications & Modules</div>
                        <div class="metric-sub">Contexts & active modules</div>
                    </div>

                    <!-- 6. PHP Engine & Web Shell -->
                    <div class="metric-card accent-green" data-key="metric-runtime" @click=${() => location.hash = 'terminal'}>
                        <div class="metric-card-top">
                            <span class="metric-tag badge-runtime">PHP & Shell</span>
                            <div class="metric-icon" style="color: #059669;">⚡</div>
                        </div>
                        <div class="metric-value" style="font-size: 1.65rem; color: #059669; margin: 0.55rem 0;">${phpVersion}</div>
                        <div class="metric-label">Runtime & Web Terminal</div>
                        <div class="metric-sub">Memory: ${memoryUsage} • Web Shell</div>
                    </div>
                </div>

                <!-- ═══════════════ Filter Strip & Pillar Navigation ═══════════════ -->
                <div class="studio-filter-strip" data-key="filter-strip">
                    <div class="pillar-pills">
                        <button class="pillar-pill pill-all ${activePillarFilter === 'all' ? 'active' : ''}" 
                            @click=${() => this.setState({ activePillarFilter: 'all' })}>
                            🌐 All Subsystems (${studioCards.length})
                        </button>
                        <button class="pillar-pill pill-cloud ${activePillarFilter === 'cloud' ? 'active' : ''}" 
                            @click=${() => this.setState({ activePillarFilter: 'cloud' })}>
                            ☁️ Cloud & Hosting (${cloudPillarCards.length})
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

                <!-- ═══════════════ The 4 Core Structural Pillars ═══════════════ -->

                <!-- 1. CLOUD & HOSTING CONTROL PLANE -->
                ${(activePillarFilter === 'all' || activePillarFilter === 'cloud')
                    ? this.renderPillarSection('cloud', 'Cloud & Hosting Control Plane', '☁️', '#f97316', cloudPillarCards)
                    : ''}

                <!-- 2. DEVELOPER STUDIO SUITE -->
                ${(activePillarFilter === 'all' || activePillarFilter === 'studio')
                    ? this.renderPillarSection('studio', 'Developer Studio Suite', '💻', '#0284c7', studioPillarCards)
                    : ''}

                <!-- 3. DECLARATIVE ARCHITECTURE & DATA -->
                ${(activePillarFilter === 'all' || activePillarFilter === 'declarative')
                    ? this.renderPillarSection('declarative', 'Declarative Architecture & Data', '📐', '#7c3aed', declarativePillarCards)
                    : ''}

                <!-- 4. RUNTIME, AI & TESTING -->
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
