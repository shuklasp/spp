/**
 * DashboardView - Live Metrics Dashboard
 * 
 * Replaces the static welcome page with a real-time operational dashboard
 * featuring system health monitoring, resource counts, quick navigation,
 * and recent audit activity.
 */

export default class DashboardView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            health: null,
            appCount: 0,
            moduleCount: 0,
            entityCount: 0,
            auditLogs: [],
            lastRefreshed: null,
            secondsAgo: 0,
            username: this.app.user?.username || 'Developer'
        };

        this._refreshInterval = null;
        this._tickInterval = null;

        await this.fetchAllData();
        this.startAutoRefresh();
    }

    destroy() {
        if (this._refreshInterval) {
            clearInterval(this._refreshInterval);
            this._refreshInterval = null;
        }
        if (this._tickInterval) {
            clearInterval(this._tickInterval);
            this._tickInterval = null;
        }
    }

    startAutoRefresh() {
        // Guard against duplicate intervals
        this.destroy();

        // Auto-refresh disabled per user request
        // this._refreshInterval = setInterval(() => this.fetchAllData(), 30000);

        // Tick the "seconds ago" counter every second — DOM-only update, no re-render
        this._tickInterval = setInterval(() => {
            if (this.state.lastRefreshed) {
                const elapsed = Math.floor((Date.now() - this.state.lastRefreshed) / 1000);
                const el = this.container?.querySelector?.('.refresh-seconds');
                if (el) el.textContent = `${elapsed}s ago`;
            }
        }, 1000);
    }

    async fetchAllData() {
        try {
            const [healthRes, appsRes, modsRes, entitiesRes, auditRes] = await Promise.allSettled([
                this.api('diagnostics_health'),
                this.api('list_apps'),
                this.api('list_modules'),
                this.api('list_entities', { appname: 'default' }),
                this.api('list_audit_logs')
            ]);

            const healthRaw = healthRes.status === 'fulfilled' && healthRes.value.success
                ? healthRes.value.data
                : null;
            // Health data may be at .data.health (LiveAction) or directly in .data (sendResponse)
            const health = healthRaw?.health || healthRaw || null;

            const appCount = appsRes.status === 'fulfilled' && appsRes.value.success
                ? (appsRes.value.data.apps || []).length
                : 0;

            const moduleCount = modsRes.status === 'fulfilled' && modsRes.value.success
                ? (modsRes.value.data.modules || []).length
                : 0;

            const entityCount = entitiesRes.status === 'fulfilled' && entitiesRes.value.success
                ? (entitiesRes.value.data.entities || []).length
                : 0;

            const auditLogs = auditRes.status === 'fulfilled' && auditRes.value.success
                ? (auditRes.value.data.logs || []).slice(0, 10)
                : [];

            this.setState({
                loading: false,
                health,
                appCount,
                moduleCount,
                entityCount,
                auditLogs,
                lastRefreshed: Date.now(),
                secondsAgo: 0
            });
        } catch (err) {
            console.error('Dashboard fetch error:', err);
            this.setState({ loading: false });
        }
    }

    getStatusColor(status) {
        if (!status) return 'var(--text-dim)';
        const s = status.toUpperCase();
        if (s === 'UP' || s === 'OK') return 'var(--success, #22c55e)';
        if (s === 'DEGRADED' || s === 'WARN') return 'var(--warning, #f59e0b)';
        return 'var(--error, #ef4444)';
    }

    getStatusLabel(status) {
        if (!status) return 'UNKNOWN';
        return status.toUpperCase();
    }

    getComponentIcon(name) {
        const icons = {
            database: '💾',
            redis: '⚡',
            fs_var: '📁',
            fs_logs: '📋',
            system: '🖥️'
        };
        return icons[name] || '🔧';
    }

    getComponentLabel(name) {
        const labels = {
            database: 'Database',
            redis: 'Redis Cache',
            fs_var: 'Var Storage',
            fs_logs: 'Log Storage',
            system: 'System'
        };
        return labels[name] || name;
    }

    render() {
        const {
            loading, health, appCount, moduleCount, entityCount,
            auditLogs, username
        } = this.state;
        const secondsAgo = 0; // Initial value; ticked via DOM directly

        if (loading) {
            return html`
                <div class="loading-state" style="min-height: 60vh; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 1rem;">
                    <div class="sppux-spinner"></div>
                    <div style="color: var(--text-dim); font-size: 0.9rem;">Initializing dashboard metrics…</div>
                </div>
            `;
        }

        const overallStatus = health?.status || 'UNKNOWN';
        const statusColor = this.getStatusColor(overallStatus);
        const components = health?.components || {};

        // Extract PHP version and memory from system component
        const sysComp = health?.components?.system || {};
        const phpVersion = sysComp.php_version || health?.php_version || '—';
        const memoryUsage = sysComp.memory_usage || health?.memory_usage || '—';

        return html`
            <style>
                .dash-container {
                    animation: fadeIn 0.5s ease-out;
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 0 0.5rem;
                }

                /* ── Hero Banner ── */
                .dash-hero {
                    background: linear-gradient(145deg, rgba(30,32,40,0.85) 0%, rgba(15,17,26,0.95) 100%);
                    border: 1px solid rgba(255,255,255,0.08);
                    border-radius: 24px;
                    padding: 2.5rem 2.5rem 2rem 2.5rem;
                    position: relative;
                    overflow: hidden;
                    margin-bottom: 1.5rem;
                }
                .dash-hero::before {
                    content: '';
                    position: absolute;
                    top: -60%;
                    left: -15%;
                    width: 55%;
                    height: 220%;
                    background: radial-gradient(circle, rgba(56, 189, 248, 0.08) 0%, transparent 70%);
                    transform: rotate(30deg);
                    pointer-events: none;
                }
                .dash-hero::after {
                    content: '';
                    position: absolute;
                    bottom: -60%;
                    right: -15%;
                    width: 55%;
                    height: 220%;
                    background: radial-gradient(circle, rgba(244, 63, 94, 0.08) 0%, transparent 70%);
                    transform: rotate(-30deg);
                    pointer-events: none;
                }
                .dash-hero-inner {
                    position: relative;
                    z-index: 1;
                }
                .dash-hero h1 {
                    font-size: 2.4rem;
                    margin: 0 0 0.3rem 0;
                    font-family: 'Outfit', sans-serif;
                }
                .dash-hero-subtitle {
                    color: rgba(255, 255, 255, 0.75);
                    font-size: 1.05rem;
                    margin: 0;
                }
                .dash-hero-meta {
                    display: flex;
                    align-items: center;
                    gap: 1.5rem;
                    margin-top: 1.2rem;
                    flex-wrap: wrap;
                }

                /* ── Health Pulse Bar ── */
                .health-pulse {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    background: rgba(0,0,0,0.25);
                    padding: 8px 18px;
                    border-radius: 40px;
                    border: 1px solid rgba(255,255,255,0.06);
                }
                .health-dot {
                    width: 12px;
                    height: 12px;
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
                    letter-spacing: 0.1em;
                    font-weight: 600;
                }

                /* ── Refreshed Indicator ── */
                .refresh-indicator {
                    font-size: 0.75rem;
                    color: rgba(255, 255, 255, 0.6);
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }
                .refresh-indicator button {
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.6);
                    cursor: pointer;
                    font-size: 0.9rem;
                    padding: 2px;
                    transition: transform 0.3s;
                }
                .refresh-indicator button:hover {
                    transform: rotate(180deg);
                    color: #fff;
                }

                /* ── Stats Cards ── */
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 1.25rem;
                    margin-bottom: 1.5rem;
                }
                @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 580px) { .stats-grid { grid-template-columns: 1fr; } }

                .stat-card {
                    background: var(--panel-bg, rgba(255,255,255,0.03));
                    border: 1px solid var(--glass-border);
                    border-radius: 18px;
                    padding: 1.5rem 1.5rem 1.25rem 1.5rem;
                    transition: all 0.35s cubic-bezier(.4,0,.2,1);
                    position: relative;
                    overflow: hidden;
                }
                .stat-card::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    border-radius: 18px;
                    opacity: 0;
                    transition: opacity 0.35s;
                    pointer-events: none;
                }
                .stat-card:hover {
                    border-color: var(--primary-color, #6366f1);
                    transform: translateY(-4px);
                    box-shadow: 0 12px 32px rgba(0,0,0,0.25);
                }
                .stat-card:hover::after {
                    opacity: 1;
                }
                .stat-card-top {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.75rem;
                }
                .stat-card-label {
                    font-size: 0.78rem;
                    color: var(--text-dim);
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    font-weight: 500;
                }
                .stat-card-icon {
                    font-size: 1.4rem;
                    width: 38px;
                    height: 38px;
                    background: rgba(255,255,255,0.04);
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .stat-card-value {
                    font-size: 2.2rem;
                    font-weight: 800;
                    letter-spacing: -0.03em;
                    line-height: 1.1;
                    animation: countUp 0.6s ease-out;
                }
                @keyframes countUp {
                    from { opacity: 0; transform: translateY(10px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                .stat-card-footer {
                    margin-top: 0.6rem;
                    font-size: 0.75rem;
                    color: var(--text-dim);
                }

                /* ── Health Components Grid ── */
                .components-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                    gap: 0.75rem;
                    margin-top: 1rem;
                }
                .comp-chip {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    background: rgba(0,0,0,0.2);
                    border: 1px solid rgba(255,255,255,0.06);
                    padding: 10px 14px;
                    border-radius: 12px;
                    transition: all 0.25s;
                }
                .comp-chip:hover {
                    background: rgba(255,255,255,0.04);
                }
                .comp-dot {
                    width: 9px;
                    height: 9px;
                    border-radius: 50%;
                    flex-shrink: 0;
                }
                .comp-name {
                    font-size: 0.8rem;
                    font-weight: 500;
                    color: rgba(255, 255, 255, 0.9);
                }
                .comp-status {
                    font-size: 0.65rem;
                    text-transform: uppercase;
                    margin-left: auto;
                    font-weight: 600;
                    letter-spacing: 0.05em;
                }

                /* ── Quick Actions ── */
                .quick-actions {
                    display: flex;
                    gap: 0.75rem;
                    margin-bottom: 1.5rem;
                    flex-wrap: wrap;
                }
                .quick-action-btn {
                    background: var(--panel-bg, rgba(255,255,255,0.03));
                    border: 1px solid var(--glass-border);
                    border-radius: 12px;
                    padding: 12px 20px;
                    color: var(--text);
                    font-size: 0.88rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.25s;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    white-space: nowrap;
                }
                .quick-action-btn:hover {
                    background: rgba(99, 102, 241, 0.12);
                    border-color: var(--primary-color, #6366f1);
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
                }

                /* ── Section Titles ── */
                .dash-section-title {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin: 0 0 1rem 0;
                }
                .dash-section-title h3 {
                    margin: 0;
                    font-size: 1.1rem;
                    font-weight: 600;
                    letter-spacing: -0.01em;
                }
                .dash-section-line {
                    flex: 1;
                    height: 1px;
                    background: linear-gradient(to right, var(--glass-border), transparent);
                }

                /* ── Audit Table ── */
                .audit-panel {
                    border-radius: 18px;
                    overflow: hidden;
                }
                .audit-panel table {
                    margin: 0;
                }
                .audit-empty {
                    text-align: center;
                    padding: 3rem 2rem;
                    color: var(--text-dim);
                }
                .audit-empty-icon {
                    font-size: 2.5rem;
                    margin-bottom: 0.5rem;
                    opacity: 0.5;
                }

                /* ── Fade-in stagger ── */
                .stagger-1 { animation: fadeIn 0.5s ease-out 0.05s both; }
                .stagger-2 { animation: fadeIn 0.5s ease-out 0.10s both; }
                .stagger-3 { animation: fadeIn 0.5s ease-out 0.15s both; }
                .stagger-4 { animation: fadeIn 0.5s ease-out 0.20s both; }
                .stagger-5 { animation: fadeIn 0.5s ease-out 0.25s both; }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(12px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            </style>

            <div class="dash-container">

                <!-- ═══════════════ Hero Banner ═══════════════ -->
                <div class="dash-hero stagger-1">
                    <div class="dash-hero-inner">
                        <h1 class="gradient-text">Welcome back, ${username}</h1>
                        <p class="dash-hero-subtitle">
                            Your SPP command center — monitor, build, and ship from one place.
                        </p>

                        <div class="dash-hero-meta">
                            <!-- Health Pulse -->
                            <div class="health-pulse">
                                <div class="health-dot" style="background: ${statusColor}; box-shadow: 0 0 12px ${statusColor};"></div>
                                <span class="health-label" style="color: ${statusColor};">System ${this.getStatusLabel(overallStatus)}</span>
                            </div>

                            <!-- Component Health Chips -->
                            ${Object.keys(components).length > 0 ? html`
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    ${Object.entries(components).map(([name, comp]) => {
                                        const cStatus = typeof comp === 'string' ? comp : (comp?.status || 'UNKNOWN');
                                        const cColor = this.getStatusColor(cStatus);
                                        return html`
                                            <div class="comp-chip">
                                                <div class="comp-dot" style="background: ${cColor}; box-shadow: 0 0 6px ${cColor};"></div>
                                                <span class="comp-name">${this.getComponentLabel(name)}</span>
                                                <span class="comp-status" style="color: ${cColor};">${this.getStatusLabel(cStatus)}</span>
                                            </div>
                                        `;
                                    })}
                                </div>
                            ` : ''}

                            <!-- Refresh Indicator -->
                            <div class="refresh-indicator" style="margin-left: auto;">
                                <span class="refresh-seconds">🕐 ${secondsAgo}s ago</span>
                                <button @click=${() => this.fetchAllData()} title="Refresh now">🔄</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════ Stats Cards ═══════════════ -->
                <div class="stats-grid stagger-2">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <span class="stat-card-label">Total Apps</span>
                            <div class="stat-card-icon" style="color: var(--primary-color, #6366f1);">📱</div>
                        </div>
                        <div class="stat-card-value" style="color: var(--primary-color, #6366f1);">${appCount}</div>
                        <div class="stat-card-footer">Registered application contexts</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <span class="stat-card-label">Total Modules</span>
                            <div class="stat-card-icon" style="color: var(--accent, #f472b6);">📦</div>
                        </div>
                        <div class="stat-card-value" style="color: var(--accent, #f472b6);">${moduleCount}</div>
                        <div class="stat-card-footer">Core + application modules</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <span class="stat-card-label">Total Entities</span>
                            <div class="stat-card-icon" style="color: var(--success, #22c55e);">🏗️</div>
                        </div>
                        <div class="stat-card-value" style="color: var(--success, #22c55e);">${entityCount}</div>
                        <div class="stat-card-footer">Data models in default app</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <span class="stat-card-label">Runtime</span>
                            <div class="stat-card-icon" style="color: var(--info, #38bdf8);">🐘</div>
                        </div>
                        <div class="stat-card-value" style="font-size: 1.4rem; color: var(--info, #38bdf8);">
                            ${phpVersion}
                        </div>
                        <div class="stat-card-footer">${memoryUsage !== '—' ? `Memory: ${memoryUsage}` : 'PHP Runtime'}</div>
                    </div>
                </div>

                <!-- ═══════════════ Quick Actions ═══════════════ -->
                <div class="dash-section-title stagger-3">
                    <h3>Quick Actions</h3>
                    <div class="dash-section-line"></div>
                </div>

                <div class="quick-actions stagger-3">
                    <button class="quick-action-btn" @click=${() => location.hash = 'apps'}>
                        🚀 App Studio
                    </button>
                    <button class="quick-action-btn" @click=${() => location.hash = 'entities'}>
                        🏗️ Entities
                    </button>
                    <button class="quick-action-btn" @click=${() => location.hash = 'parikshak'}>
                        🧪 Run Tests
                    </button>
                    <button class="quick-action-btn" @click=${() => location.hash = 'copilot'}>
                        🤖 AI Copilot
                    </button>
                    <button class="quick-action-btn" @click=${() => location.hash = 'commands'}>
                        ⚡ CLI
                    </button>
                </div>

                <!-- ═══════════════ Recent Audit Log ═══════════════ -->
                <div class="dash-section-title stagger-4">
                    <h3>Recent Activity</h3>
                    <div class="dash-section-line"></div>
                    <span style="font-size: 0.75rem; color: var(--text-dim);">Last 10 actions</span>
                </div>

                <div class="glass-panel audit-panel stagger-5" style="padding: 0;">
                    ${auditLogs.length > 0 ? html`
                        <table class="data-table" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 22%;">Action</th>
                                    <th style="width: 15%;">User</th>
                                    <th style="width: 20%;">Timestamp</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${auditLogs.map(log => {
                                    // Parse new_values JSON for extra details
                                    let details = {};
                                    try { details = log.new_values ? JSON.parse(log.new_values) : {}; } catch(e) {}
                                    const user = details.user || log.user_id || '—';
                                    const ip = details.ip || log.ip_address || '';
                                    return html`
                                    <tr>
                                        <td>
                                            <span class="tag info-tag" style="font-size: 0.75rem;">${log.entity_id || log.action || '—'}</span>
                                        </td>
                                        <td style="font-weight: 500;">${user}</td>
                                        <td style="color: var(--text-dim); font-size: 0.85rem;">
                                            ${log.created_at || '—'}
                                        </td>
                                        <td style="font-size: 0.85rem; color: var(--text-dim); max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            ${ip ? `📡 ${ip}` : '—'}
                                        </td>
                                    </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    ` : html`
                        <div class="audit-empty">
                            <div class="audit-empty-icon">📭</div>
                            <h4 style="margin: 0 0 0.3rem 0; color: var(--text);">No activity recorded yet</h4>
                            <p style="margin: 0; font-size: 0.85rem;">Admin actions will appear here as you use the platform.</p>
                        </div>
                    `}
                </div>

                <!-- ═══════════════ Footer Spacer ═══════════════ -->
                <div style="height: 2rem;"></div>
            </div>
        `;
    }
}
