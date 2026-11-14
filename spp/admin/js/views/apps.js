/**
 * AppsView - App Studio Console
 * 
 * Comprehensive management hub for Applications, Modules, Scaffolding,
 * and Shared Resource Groups. Merges formerly separate App/Module boundaries.
 */

export default class AppsView extends BaseComponent {
    constructor(app, container, props = {}) {
        super(app, container, props);
        
        const savedToggles = localStorage.getItem('spp_admin_apps_expanded');
        const expandedArray = savedToggles ? JSON.parse(savedToggles) : [];

        const savedHelp = localStorage.getItem('spp_admin_open_help');
        let openHelpArray = [];
        try {
            openHelpArray = savedHelp ? JSON.parse(savedHelp) : [];
        } catch (e) {
            openHelpArray = [];
        }

        // Detect tab from hash query param if provided (e.g., #apps?tab=lifecycle or legacy #lifecycle)
        const hash = location.hash;
        let initialTab = localStorage.getItem('spp_admin_apps_tab') || 'apps';
        if (hash.includes('tab=lifecycle') || hash.includes('#lifecycle')) initialTab = 'lifecycle';
        else if (hash.includes('tab=modules') || hash.includes('#modules')) initialTab = 'modules';
        else if (hash.includes('tab=mesh') || hash.includes('#mesh')) initialTab = 'mesh';

        this.state = {
            loading: true,
            apps: [],
            devMode: true,
            profile: 'dev',
            appFilterType: localStorage.getItem('spp_admin_apps_filter') || 'all',
            modules: [],
            sharedGroups: {},
            foreignApps: [],
            foreignServices: [],
            activeTab: initialTab,
            expandedPaths: new Set(expandedArray),
            modFilter: localStorage.getItem('spp_admin_mod_filter') || 'all',
            scaffoldOutput: '',
            scaffolding: false,
            // Multi-Language Service Mesh & Supervisor state
            meshOperating: false,
            meshLogApp: null,
            meshLogLines: '',
            meshLogLoading: false,
            meshRpcApp: '',
            meshRpcEndpoint: '',
            meshRpcMethod: 'POST',
            meshRpcPayload: '{\n  \n}',
            meshRpcResult: null,
            meshRpcExecuting: false,
            meshFilterTerm: '',
            meshEventName: '',
            meshEventPayload: '{\n  "action": "test",\n  "timestamp": 1234567890\n}',
            meshEventResult: null,
            meshEventDispatching: false,
            // cPanel Importer state
            cpanelArchives: [],
            cpanelLoadingArchives: false,
            cpanelArchivePath: '',
            cpanelTargetApp: '',
            cpanelOverwrite: false,
            cpanelSkipDb: false,
            cpanelSkipEmail: false,
            cpanelSkipFiles: false,
            cpanelSkipDns: false,
            cpanelInspection: null,
            cpanelInspecting: false,
            cpanelImporting: false,
            cpanelImportResult: null,
            // App Uninstaller & Backups state
            backups: [],
            backupInventory: null,
            loadingBackups: false,
            // Domain-scoped application state
            domains: [],
            selectedDomain: localStorage.getItem('spp_admin_selected_domain') || 'all',
            domainAppsData: null,
            loadingDomainApps: false,
            // Domain Activation Matrix state
            matrixData: null,
            loadingMatrix: false,
            matrixFilter: '',
            matrixSelectedApps: new Set(),
            // UI Search & Filters
            domainSearchTerm: '',
            appSearchTerm: '',
            appStatusFilter: 'all',
            spotlightExpanded: false,
            backupFilter: 'all',
            backupSearchTerm: '',
            restoreWizard: null,
            openHelpSections: new Set(openHelpArray)
        };
        window.appsViewInstance = this;
    }

    async onInit() {
        await this.loadData();
    }

    async onContextChange(newApp) {
        await this.loadData();
        this.update();
    }

    async loadData(retryCount = 0) {
        this.setState({ loading: true, error: null });
        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const [appsRes, modsRes, domsRes] = await Promise.all([
                apiCall('list_apps'),
                apiCall('list_modules'),
                apiCall('domains_list').catch(() => ({ success: false, domains: [] }))
            ]);
            
            if (appsRes.success && modsRes.success) {
                const appsData = appsRes.data || appsRes;
                const modsData = modsRes.data || modsRes;
                const domsData = domsRes.data || domsRes;
                const domains = domsData.domains || [];

                this.setState({
                    apps: appsData.apps || [],
                    domains: domains,
                    devMode: appsData.dev_mode !== false,
                    profile: appsData.profile || 'dev',
                    sharedGroups: appsData.shared_groups || {},
                    foreignApps: appsData.foreign_apps || [],
                    foreignServices: appsData.foreign_services || [],
                    modules: modsData.modules || [],
                    loading: false
                });

                if (this.state.selectedDomain && this.state.selectedDomain !== 'all') {
                    await this.loadDomainApps(this.state.selectedDomain);
                }
            } else {
                throw new Error((appsRes.message || appsRes.error || '') + " " + (modsRes.message || modsRes.error || ''));
            }
        } catch (e) {
            if (retryCount < 2) {
                console.warn(`Registry load failed, retrying (${retryCount + 1}/2)...`, e);
                setTimeout(() => this.loadData(retryCount + 1), 500);
            } else {
                this.setState({ 
                    loading: false, 
                    error: `Failed to load registry data: ${e.message}` 
                });
            }
        }
    }

    async selectDomain(domPattern) {
        localStorage.setItem('spp_admin_selected_domain', domPattern);
        this.setState({ selectedDomain: domPattern });
        if (domPattern !== 'all') {
            await this.loadDomainApps(domPattern);
        } else {
            this.setState({ domainAppsData: null });
        }
    }

    async loadDomainApps(domain) {
        this.setState({ loadingDomainApps: true });
        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const res = await apiCall('domains_get_domain_apps', { domain });
            if (res.success) {
                this.setState({ domainAppsData: res, loadingDomainApps: false });
            } else {
                this.setState({ loadingDomainApps: false });
                this.notify(res.error || 'Failed to load domain applications.', 'error');
            }
        } catch (e) {
            this.setState({ loadingDomainApps: false });
            this.notify(e.message, 'error');
        }
    }

    setTab(tab) {
        localStorage.setItem('spp_admin_apps_tab', tab);
        this.setState({ activeTab: tab });
        if (tab === 'matrix') {
            this.loadActivationMatrix();
        } else if (tab === 'cpanel') {
            this.loadCpanelArchives();
        } else if (tab === 'backups') {
            this.loadBackups();
        } else if (tab === 'mesh') {
            this.refreshMeshStatus();
        }
        this.renderHeader(); // clear or set header actions based on tab
    }

    setModFilter(filter) {
        localStorage.setItem('spp_admin_mod_filter', filter);
        this.setState({ modFilter: filter });
    }

    setAppFilter(filter) {
        localStorage.setItem('spp_admin_apps_filter', filter);
        this.setState({ appFilterType: filter });
    }

    async toggleDevMode() {
        const isDev = this.state.devMode !== false;
        const nextMode = isDev ? 'prod' : 'dev';
        const msg = isDev
            ? "Switch to Production profile? This will disable developer and administration portals (sppadmin, sppdev) for security. You can re-enable anytime via CLI: php spp.php env:mode dev"
            : "Switch to Developer profile? This enables developer and administration portals (sppadmin, sppdev).";
        if (!confirm(msg)) return;

        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const res = await apiCall('toggle_dev_mode', { mode: nextMode });
            if (res.success) {
                (this.admin || window.admin).notify(res.message || `Switched to ${nextMode} mode.`, 'success');
                await this.loadData();
                this.update();
            } else {
                (this.admin || window.admin).notify(res.error || res.message || 'Failed to toggle dev mode', 'error');
            }
        } catch (e) {
            (this.admin || window.admin).notify('Error toggling dev mode: ' + e.message, 'error');
        }
    }

    togglePath(appName) {
        const expanded = new Set(this.state.expandedPaths);
        if (expanded.has(appName)) expanded.delete(appName);
        else expanded.add(appName);
        
        localStorage.setItem('spp_admin_apps_expanded', JSON.stringify(Array.from(expanded)));
        this.setState({ expandedPaths: expanded });
    }

    toggleSectionHelp(sectionKey) {
        const open = new Set(this.state.openHelpSections || []);
        if (open.has(sectionKey)) {
            open.delete(sectionKey);
        } else {
            open.add(sectionKey);
        }
        try {
            localStorage.setItem('spp_admin_open_help', JSON.stringify(Array.from(open)));
        } catch (e) {}
        this.setState({ openHelpSections: open });
    }

    renderHelpButton(sectionKey) {
        const isOpen = this.state.openHelpSections && this.state.openHelpSections.has(sectionKey);
        return html`
            <button type="button" class="btn ghost-btn btn-sm help-toggle-btn"
                style="border-radius: 20px; font-size: 0.75rem; padding: 4px 10px; display: inline-flex; align-items: center; gap: 5px; border: 1px dashed var(--glass-border); color: #93c5fd; background: rgba(99,102,241,0.08); transition: all 0.2s ease;"
                @click=${() => this.toggleSectionHelp(sectionKey)}
                title="Toggle operational guide and architectural tips">
                <span>💡 Help</span>
                <span style="font-size: 0.65rem;">${isOpen ? '▲' : '▼'}</span>
            </button>
        `;
    }

    renderHelpBanner(sectionKey, title, summary, details = {}) {
        const isOpen = this.state.openHelpSections && this.state.openHelpSections.has(sectionKey);
        if (!isOpen) return '';
        return html`
            <div class="glass-panel fade-in section-help-banner" style="margin-bottom: 1.25rem; padding: 1.1rem 1.35rem; border-radius: 12px; background: linear-gradient(135deg, rgba(15,23,42,0.85) 0%, rgba(30,41,59,0.75) 100%); border: 1px solid rgba(99,102,241,0.3); border-left: 4px solid #6366f1; box-shadow: 0 8px 24px rgba(0,0,0,0.35);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.15rem;">💡</span>
                        <strong style="color: #c7d2fe; font-size: 0.92rem; letter-spacing: 0.3px;">${title}</strong>
                    </div>
                    <button type="button" class="btn ghost-btn btn-sm" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 12px; color: var(--text-dim);" @click=${() => this.toggleSectionHelp(sectionKey)}>Hide Guide ▲</button>
                </div>
                <p style="margin: 0 0 12px 0; font-size: 0.82rem; color: #cbd5e1; line-height: 1.5;">${summary}</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; font-size: 0.79rem;">
                    ${details.steps ? html`
                        <div style="background: rgba(0,0,0,0.35); padding: 10px 12px; border-radius: 8px; border-top: 2px solid #38bdf8;">
                            <div style="font-weight: 700; color: #38bdf8; text-transform: uppercase; font-size: 0.7rem; margin-bottom: 6px; display: flex; align-items: center; gap: 5px;">
                                <span>🚀</span><span>How to Use</span>
                            </div>
                            <div style="color: var(--text-dim); line-height: 1.45;">${details.steps}</div>
                        </div>
                    ` : ''}
                    ${details.concepts ? html`
                        <div style="background: rgba(0,0,0,0.35); padding: 10px 12px; border-radius: 8px; border-top: 2px solid #a78bfa;">
                            <div style="font-weight: 700; color: #a78bfa; text-transform: uppercase; font-size: 0.7rem; margin-bottom: 6px; display: flex; align-items: center; gap: 5px;">
                                <span>🧠</span><span>Key Architecture</span>
                            </div>
                            <div style="color: var(--text-dim); line-height: 1.45;">${details.concepts}</div>
                        </div>
                    ` : ''}
                    ${details.proTip ? html`
                        <div style="background: rgba(0,0,0,0.35); padding: 10px 12px; border-radius: 8px; border-top: 2px solid #34d399;">
                            <div style="font-weight: 700; color: #34d399; text-transform: uppercase; font-size: 0.7rem; margin-bottom: 6px; display: flex; align-items: center; gap: 5px;">
                                <span>✨</span><span>Pro Tip &amp; Security</span>
                            </div>
                            <div style="color: var(--text-dim); line-height: 1.45;">${details.proTip}</div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    render() {
        const { loading, activeTab, error } = this.state;

        if (loading && this.state.apps.length === 0) {
            return html`<div class="loading-state">Synchronizing App Studio registry...</div>`;
        }

        if (error) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Error</h3>
                    <p>${error}</p>
                    <button class="btn primary-btn btn-sm mt-4" @click=${() => this.loadData()}>🔄 Retry Connection</button>
                </div>`;
        }

        // Ensure header actions are appropriate for the active tab
        this.renderHeader();

        const groups = this.getNavGroups();
        const activeGroup = this.getActiveNavGroup();
        const currentGroupDef = groups.find(g => g.id === activeGroup) || groups[0];

        return html`
            <div class="apps-view">
                <!-- Grouped Navigation Container (Section 7.1) -->
                <div class="grouped-nav-container" style="margin-bottom: 1.25rem;">
                    <!-- Level 1: Four Main Navigation Groups -->
                    <div class="nav-groups-bar" style="display: flex; gap: 8px; flex-wrap: wrap; background: rgba(0,0,0,0.22); border: 1px solid var(--glass-border); border-radius: 12px; padding: 6px 10px; margin-bottom: 8px;">
                        ${groups.map(g => {
                            const isGroupActive = (activeGroup === g.id);
                            return html`
                                <button type="button"
                                    class="btn btn-sm ${isGroupActive ? 'primary-btn' : 'ghost-btn'}"
                                    style="border-radius: 8px; font-size: 0.82rem; font-weight: ${isGroupActive ? '700' : '500'}; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px; ${isGroupActive ? 'box-shadow: 0 0 10px rgba(99,102,241,0.3);' : ''}"
                                    @click=${() => this.setNavGroup(g.id)}>
                                    <span>${g.icon}</span>
                                    <span>${g.label}</span>
                                </button>
                            `;
                        })}
                    </div>

                    <!-- Level 2: Secondary Tabs for the Active Group -->
                    <div class="tab-bar-secondary" style="display: flex; gap: 6px; flex-wrap: wrap; padding: 4px 6px;">
                        ${currentGroupDef.tabs.map(t => {
                            const isTabActive = (activeTab === t.id);
                            return html`
                                <button type="button"
                                    class="sub-tab-btn ${isTabActive ? 'active' : ''}"
                                    style="border-radius: 8px; font-size: 0.8rem; padding: 5px 12px; display: inline-flex; align-items: center; gap: 6px;"
                                    @click=${() => this.setTab(t.id)}>
                                    <span>${t.icon}</span>
                                    <span>${t.label}</span>
                                </button>
                            `;
                        })}
                    </div>
                </div>

                <div class="apps-content">
                    ${activeTab === 'apps' ? this.renderAppsTable() : ''}
                    ${activeTab === 'matrix' ? this.renderActivationMatrix() : ''}
                    ${activeTab === 'mesh' ? this.renderMeshTab() : ''}
                    ${activeTab === 'modules' ? this.renderModulesTable() : ''}
                    ${activeTab === 'lifecycle' ? this.renderLifecycle() : ''}
                    ${activeTab === 'builder' ? this.renderAppBuilder() : ''}
                    ${activeTab === 'groups' ? this.renderGroupsTable() : ''}
                    ${activeTab === 'logs' ? this.renderServerLogs() : ''}
                    ${activeTab === 'cpanel' ? this.renderCpanelImporter() : ''}
                    ${activeTab === 'backups' ? this.renderBackupsTable() : ''}
                </div>
            </div>
        `;
    }

    getNavGroups() {
        return [
            {
                id: 'applications',
                label: 'Applications',
                icon: '📱',
                tabs: [
                    { id: 'apps', label: 'Applications', icon: '📱' },
                    { id: 'matrix', label: 'Activation Matrix', icon: '📊' },
                    { id: 'groups', label: 'Shared Groups', icon: '👥' },
                ]
            },
            {
                id: 'development',
                label: 'Development',
                icon: '🛠️',
                tabs: [
                    { id: 'modules', label: 'Modules Registry', icon: '📦' },
                    { id: 'builder', label: 'App Builder (CLI)', icon: '🛠️' },
                ]
            },
            {
                id: 'operations',
                label: 'Operations',
                icon: '⚙️',
                tabs: [
                    { id: 'mesh', label: 'Service Mesh & Daemons', icon: '🕸️' },
                    { id: 'lifecycle', label: 'Deployments & Lifecycle', icon: '🔄' },
                    { id: 'logs', label: 'Server Logs', icon: '📡' },
                ]
            },
            {
                id: 'recovery',
                label: 'Migration & Recovery',
                icon: '🛡️',
                tabs: [
                    { id: 'cpanel', label: 'cPanel Importer', icon: '📥' },
                    { id: 'backups', label: 'Backups & Restore', icon: '💾' },
                ]
            }
        ];
    }

    getActiveNavGroup() {
        const groups = this.getNavGroups();
        for (const g of groups) {
            if (g.tabs.some(t => t.id === this.state.activeTab)) {
                return g.id;
            }
        }
        return 'applications';
    }

    setNavGroup(groupId) {
        const groups = this.getNavGroups();
        const grp = groups.find(g => g.id === groupId);
        if (grp && grp.tabs.length > 0) {
            if (!grp.tabs.some(t => t.id === this.state.activeTab)) {
                this.setTab(grp.tabs[0].id);
            }
        }
    }

    async loadActivationMatrix() {
        this.setState({ loadingMatrix: true });
        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const res = await apiCall('apps_activation_matrix');
            if (res.success && res.data) {
                this.setState({ matrixData: res.data, loadingMatrix: false });
            } else {
                throw new Error(res.error || 'Failed to load activation matrix');
            }
        } catch (e) {
            console.error('Activation matrix error:', e);
            (this.admin || window.admin).notify('Error loading activation matrix: ' + e.message, 'error');
            this.setState({ loadingMatrix: false });
        }
    }

    async toggleAppDomainActivation(appId, domainId, currentEnabled) {
        const nextEnabled = !currentEnabled;
        const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
        try {
            const res = await apiCall('apps_set_activation', {
                app_id: appId,
                domain_id: domainId,
                enabled: nextEnabled
            });
            if (res.success) {
                (this.admin || window.admin).notify(`Application '${appId}' ${nextEnabled ? 'enabled' : 'disabled'} on '${domainId}'.`, 'success');
                if (this.state.matrixData && this.state.matrixData.matrix && this.state.matrixData.matrix[appId]) {
                    const nextMatrix = { ...this.state.matrixData.matrix };
                    nextMatrix[appId] = { ...nextMatrix[appId], [domainId]: nextEnabled };
                    this.setState({ matrixData: { ...this.state.matrixData, matrix: nextMatrix } });
                } else {
                    await this.loadActivationMatrix();
                }
            } else {
                throw new Error(res.message || res.error || 'Operation failed');
            }
        } catch (e) {
            (this.admin || window.admin).notify('Failed to update activation: ' + e.message, 'error');
        }
    }

    async openAppConfigModal(appId, domainId = null) {
        const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
        try {
            const [cfgRes, diffRes] = await Promise.all([
                apiCall('apps_config_get', { app_id: appId, domain_id: domainId }),
                domainId ? apiCall('apps_config_diff', { app_id: appId, domain_id: domainId }).catch(() => ({ success: false })) : Promise.resolve({ success: false })
            ]);

            const config = (cfgRes.success && cfgRes.data) ? cfgRes.data : {};
            const diff = diffRes.success ? diffRes.data : null;
            const title = domainId ? `Domain Config: ${appId} @ ${domainId}` : `Global Config: ${appId}`;
            const jsonText = JSON.stringify(config, null, 2);

            (this.admin || window.admin).openModal(
                `⚙️ ${title}`,
                `
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <p style="font-size:0.85rem; color:var(--text-dim); margin:0;">
                        ${domainId 
                            ? `Overrides in <code>domains/${domainId}/etc/apps/${appId}.yml</code> take precedence over platform defaults.`
                            : `Platform-wide defaults in <code>etc/apps/${appId}.yml</code> apply to all domains unless overridden.`}
                    </p>
                    ${diff && Object.keys(diff.overrides || {}).length > 0 ? `
                        <div style="background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.3); border-radius:6px; padding:8px 12px; font-size:0.8rem;">
                            <strong>⚡ Active Overrides on this Domain:</strong>
                            <pre style="margin:4px 0 0; font-size:0.75rem; color:#818cf8; overflow-x:auto;">${JSON.stringify(diff.overrides, null, 2)}</pre>
                        </div>
                    ` : ''}
                    <textarea id="app-config-editor" class="suite-input" style="width:100%; height:280px; font-family:'JetBrains Mono',monospace; font-size:0.85rem; padding:10px; border-radius:6px; background:#0d1117; color:#c9d1d9;">${jsonText}</textarea>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        ${domainId ? `
                            <button type="button" class="btn ghost-btn btn-sm" onclick="window.appsViewInstance.resetDomainConfig('${appId}', '${domainId}')" style="color:var(--danger, #f87171);">
                                🗑️ Reset to Global Defaults
                            </button>
                        ` : '<span></span>'}
                        <span style="font-size:0.75rem; color:var(--text-dim);">JSON format</span>
                    </div>
                </div>
                `,
                async () => {
                    const text = document.getElementById('app-config-editor').value;
                    let parsed;
                    try {
                        parsed = JSON.parse(text);
                    } catch (je) {
                        (this.admin || window.admin).notify('Invalid JSON syntax: ' + je.message, 'error');
                        return;
                    }
                    const saveAction = domainId ? 'apps_config_save_domain' : 'apps_config_save_global';
                    const sRes = await apiCall(saveAction, {
                        app_id: appId,
                        domain_id: domainId,
                        config: parsed
                    });
                    if (sRes.success) {
                        (this.admin || window.admin).notify('Configuration saved successfully.', 'success');
                        (this.admin || window.admin).closeModal();
                    } else {
                        (this.admin || window.admin).notify('Failed to save config: ' + (sRes.message || sRes.error), 'error');
                    }
                }
            );
        } catch (e) {
            (this.admin || window.admin).notify('Error loading config: ' + e.message, 'error');
        }
    }

    async resetDomainConfig(appId, domainId) {
        if (!confirm(`Are you sure you want to reset configuration overrides for '${appId}' on '${domainId}'? It will revert to global defaults.`)) {
            return;
        }
        const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
        const res = await apiCall('apps_config_reset', { app_id: appId, domain_id: domainId });
        if (res.success) {
            (this.admin || window.admin).notify(res.message, 'success');
            (this.admin || window.admin).closeModal();
        } else {
            (this.admin || window.admin).notify('Failed to reset config: ' + (res.message || res.error), 'error');
        }
    }

    async bulkSetActivation(action, targetDomainId = null) {
        const { matrixData, matrixFilter } = this.state;
        if (!matrixData || !matrixData.global_apps) return;
        const domains = matrixData.domains || [];
        const globalApps = matrixData.global_apps || [];
        const filterLower = (matrixFilter || '').toLowerCase().trim();
        const targetApps = globalApps.filter(a => {
            if (!filterLower) return true;
            return (a.id || '').toLowerCase().includes(filterLower) || (a.title || '').toLowerCase().includes(filterLower);
        });

        const isEnable = (action === 'enable');
        const domainText = targetDomainId ? `domain '${targetDomainId}'` : 'ALL registered domains';
        const msg = `Are you sure you want to ${isEnable ? 'ENABLE' : 'DISABLE'} ${targetApps.length} application(s) on ${domainText}?\n\nThis will update multi-tenant routing and domain execution contexts.`;
        if (!confirm(msg)) return;

        const targetDomains = targetDomainId ? domains.filter(d => d.id === targetDomainId) : domains;
        const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
        let successCount = 0;
        let failCount = 0;

        (this.admin || window.admin).notify(`Processing bulk ${isEnable ? 'activation' : 'deactivation'}...`, 'info');
        for (const app of targetApps) {
            for (const dom of targetDomains) {
                try {
                    const res = await apiCall('apps_set_activation', {
                        app_id: app.id,
                        domain_id: dom.id,
                        enabled: isEnable
                    });
                    if (res.success) successCount++;
                    else failCount++;
                } catch (e) {
                    failCount++;
                }
            }
        }

        (this.admin || window.admin).notify(`Bulk operation completed: ${successCount} updated, ${failCount} failed.`, successCount > 0 ? 'success' : 'error');
        await this.loadActivationMatrix();
    }

    renderActivationMatrix() {
        const { matrixData, loadingMatrix, matrixFilter = '' } = this.state;
        if (loadingMatrix && !matrixData) {
            return html`
                <div class="glass-panel" style="padding: 3rem; text-align: center;">
                    <div class="sppux-spinner"></div>
                    <div style="margin-top: 1rem; color: var(--text-dim);">Loading Domain Activation Matrix...</div>
                </div>
            `;
        }

        const data = matrixData || { global_apps: [], domains: [], matrix: {} };
        const globalApps = data.global_apps || [];
        const domains = data.domains || [];
        const matrix = data.matrix || {};

        const filterLower = (matrixFilter || '').toLowerCase().trim();
        const filteredApps = globalApps.filter(a => {
            if (!filterLower) return true;
            return (a.id || '').toLowerCase().includes(filterLower) ||
                   (a.title || '').toLowerCase().includes(filterLower) ||
                   (a.description || '').toLowerCase().includes(filterLower);
        });

        // Compute stats
        let totalActiveActivations = 0;
        let totalPossibleActivations = globalApps.length * domains.length;
        Object.values(matrix).forEach(domMap => {
            if (domMap && typeof domMap === 'object') {
                Object.values(domMap).forEach(v => {
                    if (v) totalActiveActivations++;
                });
            }
        });

        return html`
            <div class="activation-matrix-view fade-in">
                <!-- Header & Stats Banner -->
                <div class="glass-panel" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary, #6366f1);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                        <div>
                            <h2 style="margin:0 0 6px 0; font-size:1.35rem; color:var(--text-bright); display:flex; align-items:center; gap:8px;">
                                <span>📊 Domain Activation Matrix</span>
                                <span class="badge" style="background:rgba(99,102,241,0.2); color:#a5b4fc; font-size:0.75rem; padding:2px 8px; border-radius:12px;">Multi-Tenant Governance</span>
                            </h2>
                            <p style="margin:0; font-size:0.85rem; color:var(--text-dim); max-width:820px;">
                                Globally installed applications exist in shared storage (<code>apps/</code> or <code>src/</code>) but are executed strictly in the isolated context of enabled domains. Global installation never implies automatic activation.
                            </p>
                        </div>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" placeholder="Filter apps..." 
                                     class="suite-input" 
                                    value="${matrixFilter}" 
                                    @input=${(e) => this.setState({ matrixFilter: e.target.value })} 
                                    style="padding:6px 12px; font-size:0.8rem; border-radius:6px; min-width:180px;">
                                ${this.renderHelpButton('matrix')}
                                <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.loadActivationMatrix()} title="Refresh Matrix">
                                    🔄 Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Badges -->
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-top:1.25rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.06);">
                        <div style="background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.06); padding:10px 14px; border-radius:8px;">
                            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-dim); font-weight:700;">Global Applications</div>
                            <div style="font-size:1.4rem; font-weight:700; color:#38bdf8; margin-top:2px;">${globalApps.length}</div>
                        </div>
                        <div style="background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.06); padding:10px 14px; border-radius:8px;">
                            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-dim); font-weight:700;">Registered Domains</div>
                            <div style="font-size:1.4rem; font-weight:700; color:#818cf8; margin-top:2px;">${domains.length}</div>
                        </div>
                        <div style="background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.06); padding:10px 14px; border-radius:8px;">
                            <div style="font-size:0.72rem; text-transform:uppercase; color:var(--text-dim); font-weight:700;">Active Activations</div>
                            <div style="font-size:1.4rem; font-weight:700; color:#34d399; margin-top:2px;">${totalActiveActivations} <span style="font-size:0.8rem; font-weight:500; color:var(--text-dim);">/ ${totalPossibleActivations}</span></div>
                        </div>
                    </div>
                </div>

                ${this.renderHelpBanner('matrix', 'Domain-Application Activation Matrix Guide',
                    'Multi-tenant governance matrix controlling which global and domain applications are active for each configured domain pattern.',
                    {
                        steps: 'Click any cell badge (Active / Inactive) to toggle single-app activation for a domain. Use the Bulk Governance toolbar to activate or deactivate multiple checked apps across all domains in a single click.',
                        concepts: 'Activation creates an entry in the domain application registry (etc/domains/<domain>/apps.yml). If deactivated, requests to that app route namespace return 404 or fall back to the domain router.',
                        proTip: 'Always verify database bindings before activating an app on a live domain. Domain-specific overrides in etc/domains/<domain>/ take precedence over global settings.'
                    }
                )}

                <!-- Bulk Operations Governance Toolbar (Section 7.3.3) -->
                <div class="glass-panel" style="padding: 10px 16px; margin-bottom: 1rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: rgba(0,0,0,0.2);">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase;">
                            ⚡ Bulk Activation Governance:
                        </span>
                        <select id="bulk-matrix-domain-target" class="suite-input" style="padding: 4px 10px; font-size: 0.8rem; border-radius: 6px;">
                            <option value="">-- Apply to All Domains --</option>
                            ${domains.map(d => html`<option value="${d.id}">🌐 ${d.id}</option>`)}
                        </select>
                        <button type="button" class="btn btn-sm btn-success" 
                            style="font-size: 0.78rem; padding: 4px 12px; border-radius: 6px;"
                            @click=${() => {
                                const sel = document.getElementById('bulk-matrix-domain-target');
                                this.bulkSetActivation('enable', sel ? sel.value : null);
                            }}>
                            ⚡ Enable Filtered (${filteredApps.length})
                        </button>
                        <button type="button" class="btn btn-sm danger-btn" 
                            style="font-size: 0.78rem; padding: 4px 12px; border-radius: 6px;"
                            @click=${() => {
                                const sel = document.getElementById('bulk-matrix-domain-target');
                                this.bulkSetActivation('disable', sel ? sel.value : null);
                            }}>
                            🛑 Disable Filtered (${filteredApps.length})
                        </button>
                    </div>
                    <small style="font-size: 0.72rem; color: var(--text-dim);">
                        Enforces multi-tenant isolation and routing boundaries.
                    </small>
                </div>

                <!-- Matrix Table -->
                <div class="glass-panel" style="padding:0; overflow:hidden; border-radius:8px; margin-bottom:1.5rem;">
                    <div style="overflow-x:auto;">
                        <table class="data-table" style="width:100%; border-collapse:collapse; text-align:left;">
                            <thead>
                                <tr style="background:rgba(0,0,0,0.3); border-bottom:1px solid rgba(255,255,255,0.1);">
                                    <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em; min-width:240px; position:sticky; left:0; background:rgba(20,24,33,0.95); z-index:2;">
                                        Application (Global)
                                    </th>
                                    <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em; min-width:140px;">
                                        Storage Model
                                    </th>
                                    ${domains.map(dom => html`
                                        <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-bright); text-align:center; min-width:160px;">
                                            <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                                                <span style="font-weight:600;">🌐 ${dom.id}</span>
                                                <span style="font-size:0.65rem; color:${dom.enabled !== false ? '#34d399' : '#f87171'}; text-transform:uppercase; font-weight:700;">
                                                    ${dom.enabled !== false ? 'ACTIVE' : 'DISABLED'}
                                                </span>
                                            </div>
                                        </th>
                                    `)}
                                    <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em; text-align:right; min-width:140px;">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                ${filteredApps.length === 0 ? html`
                                    <tr>
                                        <td colspan="${domains.length + 3}" style="text-align:center; padding:3rem; color:var(--text-dim);">
                                            No global applications found matching search criteria.
                                        </td>
                                    </tr>
                                ` : filteredApps.map((app, idx) => {
                                    const storageModel = app.storage_model || 'domain_isolated';
                                    return html`
                                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05); background:${idx % 2 === 0 ? 'rgba(255,255,255,0.01)' : 'transparent'};">
                                            <!-- Application Identity (Sticky Col) -->
                                            <td style="padding:12px 16px; position:sticky; left:0; background:rgba(20,24,33,0.95); z-index:1;">
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <span style="font-size:1.3rem;">📱</span>
                                                    <div>
                                                        <div style="font-weight:600; color:var(--text-bright);">${app.title || app.id}</div>
                                                        <div style="font-size:0.75rem; color:var(--text-dim); font-family:monospace;">${app.id}</div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Storage Model -->
                                            <td style="padding:12px 16px;">
                                                <span class="suite-pill ${storageModel === 'shared_platform' ? 'suite-pill-warning' : 'suite-pill-info'}" style="font-size:0.7rem;">
                                                    ${storageModel === 'shared_platform' ? 'Shared Platform DB' : 'Domain-Isolated DB'}
                                                </span>
                                            </td>

                                            <!-- Domain Activations -->
                                            ${domains.map(dom => {
                                                const isEnabled = Boolean(matrix[app.id] && matrix[app.id][dom.id]);
                                                return html`
                                                    <td style="padding:12px 16px; text-align:center;">
                                                        <div style="display:inline-flex; align-items:center; gap:6px;">
                                                            <button type="button" 
                                                                class="btn btn-sm ${isEnabled ? 'primary-btn' : 'ghost-btn'}"
                                                                style="border-radius:20px; font-size:0.75rem; padding:3px 12px; font-weight:600; min-width:88px; transition:all 0.2s ease; ${isEnabled ? 'background:#059669; border-color:#10b981;' : 'opacity:0.6;'}"
                                                                @click=${() => this.toggleAppDomainActivation(app.id, dom.id, isEnabled)}
                                                                title="Click to ${isEnabled ? 'disable' : 'enable'} on ${dom.id}">
                                                                ${isEnabled ? '✔ Enabled' : '✖ Disabled'}
                                                            </button>
                                                            <button type="button" 
                                                                class="btn ghost-btn btn-sm" 
                                                                style="padding:3px 6px; font-size:0.75rem;" 
                                                                @click=${() => this.openAppConfigModal(app.id, dom.id)} 
                                                                title="Configure domain override for ${app.id} on ${dom.id}">
                                                                ⚙️
                                                            </button>
                                                        </div>
                                                    </td>
                                                `;
                                            })}

                                            <!-- Global Actions -->
                                            <td style="padding:12px 16px; text-align:right;">
                                                <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openAppConfigModal(app.id, null)} title="Configure Global Platform Defaults">
                                                    ⚙️ Global Config
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Novice Architectural Guide -->
                <div class="glass-panel" style="padding:1.25rem 1.5rem; background:rgba(0,0,0,0.2); border-radius:8px;">
                    <h4 style="margin:0 0 6px 0; font-size:0.95rem; color:var(--text-bright); display:flex; align-items:center; gap:6px;">
                        <span>💡 How Activation & Configuration Isolation Works</span>
                    </h4>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-top:10px; font-size:0.8rem; color:var(--text-dim); line-height:1.5;">
                        <div>
                            <strong style="color:var(--text-bright);">1. Independent Lifecycle</strong><br>
                            An application can be installed globally once by platform administrators. Individual domain administrators can enable or disable the application independently without touching the global code repository.
                        </div>
                        <div>
                            <strong style="color:var(--text-bright);">2. 4-Tier Configuration Cascading</strong><br>
                            Settings cascade automatically: <code>defaults.yml</code> (Codebase) &rarr; <code>etc/apps/*.yml</code> (Platform Defaults) &rarr; <code>domains/&lt;domain&gt;/etc/apps/*.yml</code> (Domain Overrides) &rarr; Runtime environment variables.
                        </div>
                        <div>
                            <strong style="color:var(--text-bright);">3. Strict Database Context Isolation</strong><br>
                            When <code>storage_model: domain_isolated</code> is declared, queries are bound exclusively to <code>domains/&lt;domain&gt;/var/data/</code> or tenant database connections, completely isolating customer data.
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderLifecycle() {
        setTimeout(async () => {
            const mount = document.getElementById('apps-lifecycle-mount');
            if (mount) {
                if (!this.lifecycleInstance) {
                    try {
                        const ts = window.SPP_CONFIG?.version || Date.now();
                        const mod = await import(`./lifecycle.js?v=${ts}`);
                        const LifecycleView = mod.default;
                        this.lifecycleInstance = new LifecycleView(this.app || this.admin, mount, { app: (this.app || this.admin).selectedApp });
                        if (this.lifecycleInstance.onInit) await this.lifecycleInstance.onInit();
                        await this.lifecycleInstance.update();
                    } catch (err) {
                        console.error('Failed to load LifecycleView:', err);
                        mount.innerHTML = `<div class="alert error" style="margin: 1.5rem;">Failed to load Deployments & Lifecycle: ${err.message}</div>`;
                    }
                } else {
                    this.lifecycleInstance.container = mount;
                    await this.lifecycleInstance.update();
                }
            }
        }, 10);

        return html`
            <div class="fade-in">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 0.75rem;">
                    ${this.renderHelpButton('lifecycle')}
                </div>
                ${this.renderHelpBanner('lifecycle', 'Deployments & Application Lifecycle Guide',
                    'Automated staging, canary releases, zero-downtime rollbacks, and atomic directory swapping across multi-server topologies.',
                    {
                        steps: 'Select target deployment profile and branch/release tag. Review git commit diffs and automated pre-flight checks, then click "Deploy Release". If an issue occurs, click "Rollback" to restore the previous release instantly.',
                        concepts: 'SPP Deploy uses symlink-based atomic releases (current -> releases/<timestamp>), distributed mutex locking, and automatic post-deploy cache priming and migration runs.',
                        proTip: 'Always run automated Parikshak tests in CI before triggering production deployments. Use Canary mode to test on 10% of traffic before full rollout.'
                    }
                )}
                <div id="apps-lifecycle-mount" style="min-height: 500px;">
                    <div class="loading-state" style="padding: 2rem; text-align: center;"><div class="sppux-spinner"></div> Loading Deployment Command Center...</div>
                </div>
            </div>
        `;
    }

    // --- Helper: Package Card ---
    renderPackageCard(pkg) {
        return html`
            <div class="package-item-card" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.07); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 4px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <strong style="color: var(--text-bright); font-size: 12px;">${pkg.name || pkg.id}</strong>
                    <code class="code-badge" style="font-size: 10px;">v${pkg.version || '1.0.0'}</code>
                </div>
                <div style="font-size: 11px; color: var(--accent); font-weight: 500;">
                    ${pkg.category || 'Package'}
                </div>
                <div style="font-size: 11px; color: var(--text-dim); line-height: 1.35; margin-top: 2px; ${pkg.description ? '' : 'display: none;'}">
                    ${pkg.description || ''}
                </div>
            </div>
        `;
    }

    // --- Tab: Applications ---
    renderAppsTable() {
        const {
            apps = [],
            domains = [],
            selectedDomain = 'all',
            expandedPaths = new Set(),
            appFilterType = 'all',
            appStatusFilter = 'all',
            appSearchTerm = '',
            domainSearchTerm = '',
            spotlightExpanded = false,
            devMode = true
        } = this.state;

        const systemAppNames = ['sppadmin', 'sppdev', 'default', 'admin', 'system'];
        const isSystemApp = (app) => app.is_system || systemAppNames.includes((app.name || '').toLowerCase()) || (app.src_path && app.src_path.startsWith('spp/'));

        const userApps = apps.filter(a => !isSystemApp(a));
        const systemApps = apps.filter(a => isSystemApp(a));
        const globalApps = apps.filter(a => !a.is_domain_app && !isSystemApp(a));
        const domainApps = apps.filter(a => a.is_domain_app);
        const activeApps = apps.filter(a => a.is_installed !== false && a.enabled !== false);
        const inactiveApps = apps.filter(a => a.is_installed === false || a.enabled === false);
        const attentionApps = apps.filter(a => a.is_installed === false || a.broken || a.has_errors);

        // Identify Primary / Root Base Application for Active Domain Context
        let baseApp = null;
        if (selectedDomain !== 'all') {
            const activeDomObj = domains.find(d => d.pattern === selectedDomain || d.id === selectedDomain);
            const defaultAppName = activeDomObj && activeDomObj.default_app 
                ? (typeof activeDomObj.default_app === 'object' ? (activeDomObj.default_app.app_name || activeDomObj.default_app.name) : activeDomObj.default_app)
                : null;
            if (defaultAppName) {
                baseApp = apps.find(a => a.name === defaultAppName);
            }
        }
        if (!baseApp) {
            baseApp = apps.find(a => a.is_base_app) || userApps[0] || apps[0];
        }

        // Secondary apps are non-base apps
        let secondaryApps = apps.filter(a => !baseApp || a.name !== baseApp.name);

        // Filter secondary apps by type
        if (appFilterType === 'user') secondaryApps = secondaryApps.filter(a => !isSystemApp(a));
        else if (appFilterType === 'system') secondaryApps = secondaryApps.filter(a => isSystemApp(a));
        else if (appFilterType === 'global') secondaryApps = secondaryApps.filter(a => !a.is_domain_app && !isSystemApp(a));
        else if (appFilterType === 'domain') secondaryApps = secondaryApps.filter(a => a.is_domain_app);

        // Filter secondary apps by status
        if (appStatusFilter === 'active') secondaryApps = secondaryApps.filter(a => a.is_installed !== false && a.enabled !== false);
        else if (appStatusFilter === 'inactive') secondaryApps = secondaryApps.filter(a => a.is_installed === false || a.enabled === false);

        // Filter secondary apps by search term
        if (appSearchTerm) {
            const s = appSearchTerm.toLowerCase().trim();
            secondaryApps = secondaryApps.filter(a => 
                (a.name || '').toLowerCase().includes(s) ||
                (a.title || '').toLowerCase().includes(s) ||
                (a.base_url || '').toLowerCase().includes(s) ||
                (a.src_path || '').toLowerCase().includes(s)
            );
        }

        // Searchable Domain Switcher
        const filteredDomains = (domains || []).filter(d => 
            !domainSearchTerm || (d.pattern || d.id || '').toLowerCase().includes(domainSearchTerm.toLowerCase())
        );

        const domainSwitcherHtml = html`
            <div class="glass-panel" style="padding: 0.85rem 1.25rem; margin-bottom: 1.25rem; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">
                            🌐 Domain Context:
                        </span>
                        <select class="suite-input" 
                            style="padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; min-width: 200px; font-weight: 600;"
                            .value=${selectedDomain}
                            @change=${(e) => this.selectDomain(e.target.value)}>
                            <option value="all">🌍 All Applications (${apps.length})</option>
                            <option value="platform">🏛️ Platform Scope</option>
                            ${domains.map(d => {
                                const defApp = typeof d.default_app === 'object' ? (d.default_app.app_name || d.default_app.name) : d.default_app;
                                return html`<option value="${d.pattern}">🌐 ${d.pattern} ${defApp ? `[${defApp}]` : ''}</option>`;
                            })}
                        </select>
                    </div>

                    ${domains.length > 4 ? html`
                        <input type="text" placeholder="Filter domains..." 
                            class="suite-input" 
                            value="${domainSearchTerm}" 
                            @input=${(e) => this.setState({ domainSearchTerm: e.target.value })}
                            style="padding: 5px 10px; font-size: 0.8rem; border-radius: 6px; width: 140px;">
                    ` : ''}

                    <div style="display: flex; gap: 6px;">
                        <button type="button" 
                            class="btn btn-sm ${selectedDomain === 'all' ? 'primary-btn' : 'ghost-btn'}"
                            style="border-radius: 8px; font-size: 0.78rem; padding: 4px 10px;"
                            @click=${() => this.selectDomain('all')}>
                            All
                        </button>
                        ${filteredDomains.slice(0, 3).map(dom => html`
                            <button type="button"
                                class="btn btn-sm ${selectedDomain === dom.pattern ? 'primary-btn' : 'ghost-btn'}"
                                style="border-radius: 8px; font-size: 0.78rem; padding: 4px 10px;"
                                @click=${() => this.selectDomain(dom.pattern)}>
                                ${dom.pattern}
                            </button>
                        `)}
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    ${this.renderHelpButton('apps')}
                    <button class="btn primary-btn btn-sm" @click=${() => this.openTemplateHub()}>🏗️ Template Hub</button>
                    <button class="btn secondary-btn btn-sm" @click=${() => this.openInstallToDomainModal(selectedDomain !== 'all' ? selectedDomain : null)}>🌐 Install to Domain</button>
                    <button class="btn btn-sm ${devMode ? 'btn-success' : 'ghost-btn'}" 
                        @click=${() => this.toggleDevMode()} 
                        style="border-radius: 20px; font-size: 11px; padding: 4px 12px; font-weight: 600;" 
                        title="Dev Mode enables/disables administration and dev portals (sppadmin, sppdev)">
                        ${devMode ? '🟢 Dev Mode' : '⚪ Prod Mode'}
                    </button>
                </div>
            </div>
        `;

        const appsHelpBanner = this.renderHelpBanner('apps', 'Applications & Domain Scopes Guide',
            'Central operational view of all applications discovered across global, domain, and external storage topologies. Inspect app health, switch between active domains, and review installation status.',
            {
                steps: 'Use the Domain Context dropdown to filter apps for a specific virtual host or view all. Click "Inspect Tech" on the Primary Spotlight app to reveal routes, storage paths, and active configs. Toggle status filters or use the live search bar to quickly find secondary applications.',
                concepts: 'SPP decouples global application code from domain activation. An app can be physically installed once under global storage or an external drive, yet independently activated or configured per domain.',
                proTip: 'Primary Base App represents the host system app. For multi-tenant setups, check the Activation Matrix tab to govern which secondary apps are exposed to public visitors.'
            }
        );

        if (selectedDomain !== 'all' && selectedDomain !== 'platform') {
            return html`
                ${domainSwitcherHtml}
                ${appsHelpBanner}
                ${this.renderDomainAppsView(selectedDomain)}
            `;
        }

        return html`
            ${domainSwitcherHtml}
            ${appsHelpBanner}

            <!-- Summary KPI Metrics Cards (Section 7.2.2) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 1.25rem;">
                <div class="glass-panel" style="padding: 12px 16px; border-radius: 10px; border-left: 3px solid #6366f1;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Total Applications</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #818cf8; margin-top: 2px;">${apps.length}</div>
                </div>
                <div class="glass-panel" style="padding: 12px 16px; border-radius: 10px; border-left: 3px solid #38bdf8;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Global Applications</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #38bdf8; margin-top: 2px;">${globalApps.length}</div>
                </div>
                <div class="glass-panel" style="padding: 12px 16px; border-radius: 10px; border-left: 3px solid #a855f7;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Domain Applications</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #c084fc; margin-top: 2px;">${domainApps.length}</div>
                </div>
                <div class="glass-panel" style="padding: 12px 16px; border-radius: 10px; border-left: 3px solid #34d399;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Active Applications</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #34d399; margin-top: 2px;">${activeApps.length}</div>
                </div>
                <div class="glass-panel" style="padding: 12px 16px; border-radius: 10px; border-left: 3px solid #f59e0b;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Inactive / Dormant</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #fbbf24; margin-top: 2px;">${inactiveApps.length}</div>
                </div>
                <div class="glass-panel" style="padding: 12px 16px; border-radius: 10px; border-left: 3px solid ${attentionApps.length > 0 ? '#ef4444' : '#10b981'};">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Requires Attention</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: ${attentionApps.length > 0 ? '#f87171' : '#34d399'}; margin-top: 2px;">
                        ${attentionApps.length}
                    </div>
                </div>
            </div>

            <!-- Base / Root Application Spotlight Card (Section 7.2.3) -->
            ${baseApp ? html`
                <div class="glass-panel" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; border-radius: 12px; border-left: 4px solid #10b981; background: linear-gradient(135deg, rgba(16,185,129,0.06), rgba(0,0,0,0.2));">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.4); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                🚀
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="badge" style="background: rgba(16,185,129,0.25); color: #6ee7b7; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; letter-spacing: 0.5px;">
                                        PRIMARY BASE APPLICATION
                                    </span>
                                    <span class="badge" style="background: rgba(56,189,248,0.2); color: #7dd3fc; font-size: 0.7rem; font-weight: 600; padding: 2px 8px; border-radius: 12px;">
                                        ${baseApp.is_domain_app ? '🌐 DOMAIN' : '🌍 GLOBAL'}
                                    </span>
                                </div>
                                <h3 style="margin: 4px 0 2px 0; font-size: 1.25rem; color: var(--text-bright);">
                                    ${baseApp.title || baseApp.name} <code style="font-size: 0.85rem; color: #a7f3d0; font-family: monospace;">(${baseApp.name})</code>
                                </h3>
                                <p style="margin: 0; font-size: 0.82rem; color: var(--text-dim);">
                                    ${baseApp.description || 'Core base application serving requests for the default context.'}
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" class="btn ghost-btn btn-sm" 
                                style="border-radius: 8px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;"
                                @click=${() => this.setState({ spotlightExpanded: !spotlightExpanded })}>
                                <span>🔬 Technical Inspector</span>
                                <span>${spotlightExpanded ? '▲' : '▼'}</span>
                            </button>
                            <button class="btn primary-btn btn-sm" @click=${() => this.openAppEditor(baseApp.name)}>⚙️ Configure</button>
                            <button class="btn secondary-btn btn-sm" @click=${() => this.showAppInfo(baseApp)}>👁️ Inspect</button>
                        </div>
                    </div>

                    <!-- Spotlight Quick Specs Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.06);">
                        <div>
                            <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 600;">Mounting Point / Base URL</div>
                            <code class="code-badge primary" style="font-size: 0.82rem; margin-top: 2px; display: inline-block;">${baseApp.base_url || '/'}</code>
                        </div>
                        <div>
                            <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 600;">Physical Source Path</div>
                            <code class="code-badge" style="font-size: 0.8rem; color: #cbd5e1; margin-top: 2px; display: inline-block;">${baseApp.src_path || 'src/' + baseApp.name}</code>
                        </div>
                        <div>
                            <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 600;">Active Database Connection</div>
                            <span style="font-size: 0.82rem; color: #a5b4fc; font-weight: 600;">🗄️ ${baseApp.database || 'sqlite:default'}</span>
                        </div>
                        <div>
                            <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 600;">Quick Health Status</div>
                            <span style="font-size: 0.82rem; color: #34d399; font-weight: 600;">🟢 Operational (Zero Faults)</span>
                        </div>
                    </div>

                    <!-- Collapsible Technical Inspector Accordion -->
                    ${spotlightExpanded ? html`
                        <div style="margin-top: 1rem; padding: 1rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); font-size: 0.82rem;" class="fade-in">
                            <div style="font-weight: 700; color: #93c5fd; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                                <span>🔬 Technical Architecture &amp; Mount Telemetry</span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px;">
                                <div><strong>Framework Architecture:</strong> SPP 2.0.4 Enterprise (Zero-Lock)</div>
                                <div><strong>PHP Compatibility:</strong> PHP 8.2+ (Strict Types, JIT)</div>
                                <div><strong>Configuration Source:</strong> <code>etc/apps/${baseApp.name}.yml</code></div>
                                <div><strong>Data Storage Mount:</strong> <code>sppdata/apps/${baseApp.name}</code></div>
                                <div><strong>Runtime Cache Mount:</strong> <code>var/cache/apps/${baseApp.name}</code></div>
                                <div><strong>Runtime Logs Mount:</strong> <code>var/logs/${baseApp.name}.log</code></div>
                            </div>
                            ${baseApp.packages && baseApp.packages.length > 0 ? html`
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(255,255,255,0.08);">
                                    <strong>Loaded Packages:</strong> ${(baseApp.packages || []).map(p => p.id || p.name).join(', ')}
                                </div>
                            ` : ''}
                        </div>
                    ` : ''}
                </div>
            ` : ''}

            <!-- Secondary / Mounted Applications List (Section 7.2.4) -->
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
                    <div class="header-main" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <h3 class="gradient-text" style="margin: 0;">Secondary &amp; Mounted Applications</h3>
                        <span class="count-badge">${secondaryApps.length}</span>
                        
                        <div class="filter-tabs" style="display: inline-flex; background: rgba(0,0,0,0.25); border-radius: 8px; padding: 2px; border: 1px solid rgba(255,255,255,0.08);">
                            <button class="btn btn-sm ${appFilterType === 'all' ? 'primary-btn' : 'ghost-btn'}" 
                                style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                                @click=${() => this.setAppFilter('all')}>
                                All (${apps.filter(a => !baseApp || a.name !== baseApp.name).length})
                            </button>
                            <button class="btn btn-sm ${appFilterType === 'user' ? 'primary-btn' : 'ghost-btn'}" 
                                style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                                @click=${() => this.setAppFilter('user')}>
                                🚀 User Apps
                            </button>
                            <button class="btn btn-sm ${appFilterType === 'global' ? 'primary-btn' : 'ghost-btn'}" 
                                style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                                @click=${() => this.setAppFilter('global')}>
                                🌍 Global
                            </button>
                            <button class="btn btn-sm ${appFilterType === 'domain' ? 'primary-btn' : 'ghost-btn'}" 
                                style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                                @click=${() => this.setAppFilter('domain')}>
                                🌐 Domain
                            </button>
                            <button class="btn btn-sm ${appFilterType === 'system' ? 'primary-btn' : 'ghost-btn'}" 
                                style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                                @click=${() => this.setAppFilter('system')}>
                                🛡️ System
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="text" placeholder="Search applications..." 
                            class="suite-input" 
                            value="${appSearchTerm}" 
                            @input=${(e) => this.setState({ appSearchTerm: e.target.value })} 
                            style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; width: 180px;">

                        <select class="suite-input" 
                            style="padding: 6px 10px; font-size: 0.8rem; border-radius: 6px;"
                            .value=${appStatusFilter}
                            @change=${(e) => this.setState({ appStatusFilter: e.target.value })}>
                            <option value="all">All Statuses</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive / Dormant</option>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 26%;">Application</th>
                                <th style="width: 18%;">Domain Scope</th>
                                <th style="width: 18%;">Base URL</th>
                                <th style="width: 14%;">Table Prefix</th>
                                <th class="text-right" style="width: 240px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${secondaryApps.map(app => {
                                const isExpanded = expandedPaths.has(app.name);
                                const isSystem = isSystemApp(app);
                                return html`
                                    <tr class="${isExpanded ? 'expanded-row' : ''} ${isSystem ? 'system-app-row' : ''}">
                                        <td>
                                            <div class="app-identity" style="display: flex; align-items: center; gap: 12px;">
                                                ${isSystem ? html`
                                                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 8px;" title="Protected System Application">🛡️</div>
                                                ` : (app.is_installed !== false ? html`
                                                    <label class="icon-radio-wrap" title="Set as Primary Base Application" style="margin-bottom: 0;">
                                                        <input type="radio" name="base-app-selector" value="${app.name}" 
                                                            ?checked="${app.is_base_app}" 
                                                            @change=${() => this.setBaseApp(app.name)}>
                                                        <div class="app-icon ${app.is_base_app ? 'active' : ''}">🚀</div>
                                                    </label>
                                                ` : html`
                                                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; background: var(--warning-bg); border-radius: 8px;">📦</div>
                                                `)}
                                                <div class="app-name-wrap">
                                                    <div class="entity-name" style="${app.is_installed === false ? 'color: var(--text-dim);' : ''}">
                                                        ${app.name}
                                                        ${isSystem ? html`
                                                            <span class="badge" style="margin-left: 8px; font-size: 10px; padding: 2px 7px; border-radius: 10px; background: rgba(139, 92, 246, 0.2); color: #c084fc; border: 1px solid rgba(139, 92, 246, 0.35); font-weight: 600;">
                                                                SYSTEM
                                                            </span>
                                                        ` : ''}
                                                        ${app.packages && app.packages.length > 0 ? html`
                                                            <span class="badge-packages" style="cursor: pointer; margin-left: 8px; font-size: 11px; padding: 2px 8px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); font-weight: 500;" @click=${() => this.togglePath(app.name)} title="Click to view packages">
                                                                📦 ${app.packages.length} Packages ${isExpanded ? '▲' : '▼'}
                                                            </span>
                                                        ` : ''}
                                                        ${app.is_installed === false ? html`
                                                            <span class="badge-dormant">Dormant Code</span>
                                                        ` : ''}
                                                    </div>
                                                    <div class="app-status-row">
                                                        ${isSystem ? (app.dev_mode_dependent || ['sppadmin', 'sppdev'].includes((app.name || '').toLowerCase()) ? html`
                                                            ${devMode ? html`
                                                                <span style="color: #4ade80; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                                                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #4ade80; display: inline-block;"></span>
                                                                    Enabled (Dev Mode)
                                                                </span>
                                                            ` : html`
                                                                <span style="color: var(--text-dim); font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                                                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #94a3b8; display: inline-block;"></span>
                                                                    Disabled (Prod Mode)
                                                                </span>
                                                            `}
                                                            <small style="color: var(--text-dim); margin-left: 8px;">Cannot be uninstalled</small>
                                                        ` : html`
                                                            ${app.is_base_app ? html`<span class="status-indicator active"></span> <small>Primary Framework Base</small>` : html`<small style="color: var(--text-dim);">Core System App</small>`}
                                                        `) : html`
                                                            ${app.is_base_app ? html`<span class="status-indicator active"></span> <small>Primary</small>` : ''}
                                                            ${app.packages && app.packages.length > 0 && !isExpanded ? html`
                                                                <small style="color: var(--text-dim); margin-left: 6px;">Includes: ${app.packages.map(p => p.id || p.name).slice(0, 4).join(', ')}${app.packages.length > 4 ? '...' : ''}</small>
                                                            ` : ''}
                                                        `}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            ${isSystem ? html`
                                                <span class="code-badge" style="background: rgba(139, 92, 246, 0.15); color: #c084fc; font-size: 11px;" title="Internal System Portal">
                                                    🛡️ System Core
                                                </span>
                                            ` : (app.is_installed === false ? html`
                                                <span class="badge-config-uninstalled" title="Source code is preserved in ${app.src_path}, but configuration is uninstalled.">
                                                    🟡 Config Uninstalled
                                                </span>
                                            ` : app.is_domain_app ? html`
                                                <span class="code-badge primary" title="Installed at ${app.src_path}" style="font-size: 11px;">
                                                    🌐 ${app.installed_domain}
                                                </span>
                                            ` : app.domain_only ? html`
                                                <span class="code-badge warning" title="Restricted to: ${(app.allowed_domains || []).join(', ')}" style="font-size: 11px;">
                                                    🔒 Domain-Only (${(app.allowed_domains || []).length})
                                                </span>
                                            ` : html`
                                                <span class="code-badge success" title="Global (Accessible across all domains)" style="font-size: 11px;">
                                                    🌍 Global
                                                </span>
                                            `)}
                                        </td>
                                        <td><div class="url-cell"><code class="code-badge primary">${app.base_url || '/'}</code></div></td>
                                        <td><code class="code-badge warning">${app.table_prefix || '(none)'}</code></td>
                                        <td class="text-right">
                                            <div class="btn-group-horizontal" style="justify-content: flex-end;">
                                                ${isSystem ? html`
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.showAppInfo(app)} title="View Configuration">👁️ Show</button>
                                                    ${app.dev_mode_dependent || ['sppadmin', 'sppdev'].includes((app.name || '').toLowerCase()) ? html`
                                                        <button class="btn ${devMode ? 'ghost-btn' : 'primary-btn'} btn-sm" @click=${() => this.toggleDevMode()} title="Toggle Dev Mode (Enables/Disables sppadmin and sppdev)">
                                                            ⚡ ${devMode ? 'Dev Mode' : 'Enable Dev Mode'}
                                                        </button>
                                                    ` : ''}
                                                    <span class="badge" style="font-size: 10px; color: var(--text-dim); padding: 4px 6px;" title="System applications cannot be uninstalled">🛡️ Protected</span>
                                                ` : (app.is_installed === false ? html`
                                                    <button class="btn btn-reinstall btn-sm" @click=${() => this.reinstallApp(app.name)} title="Re-install and Activate Application">📥 Re-Install</button>
                                                    <button class="btn btn-full-delete btn-sm" @click=${() => this.purgeDormantCode(app.name)} title="Permanently Delete Remaining Source Code from Disk">🗑️ Full Delete</button>
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.showAppInfo(app)} title="View Preserved Code Info">👁️ Show</button>
                                                ` : html`
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.cloneApp(app.name)} title="Clone Application">👯 Clone</button>
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.openDeploySync(app.name)} title="Export & Deploy">🚀 Deploy</button>
                                                    ${app.has_admin ? html`
                                                        <button class="btn primary-btn btn-sm" @click=${() => this.manageApp(app.name)} title="Manage Application">🛠️ Manage</button>
                                                    ` : ''}
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.showAppInfo(app)} title="View Configuration">👁️ Show</button>
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.openDomainScopeModal(app)} title="Configure Domain Scope">🌐 Scope</button>
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.openAppEditor(app.name)} title="Configure Application">⚙️ Configure</button>
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.openCreateBackupModal(app.name)} title="Backup Application">💾 Backup</button>
                                                    ${!['default', 'admin', 'spp', 'core', 'system', 'sppadmin', 'sppdev'].includes((app.name || '').toLowerCase()) && !app.is_system ? html`
                                                        <button class="btn danger-btn btn-sm" @click=${() => this.openUninstallModal(app)} title="Uninstall / Decommission Application">🗑️ Uninstall</button>
                                                    ` : ''}
                                                `)}
                                            </div>
                                        </td>
                                    </tr>
                                    ${isExpanded && app.packages && app.packages.length > 0 ? html`
                                        <tr class="packages-expansion-row">
                                            <td colspan="5" style="background: rgba(15, 23, 42, 0.45); padding: 14px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                                    <span style="font-size: 12px; font-weight: 600; color: #93c5fd; text-transform: uppercase; letter-spacing: 0.5px;">
                                                        📦 Integrated Packages for ${app.title || app.name} (${app.packages.length})
                                                    </span>
                                                    <span style="font-size: 11px; color: var(--text-dim);">Treated as internal modules of ${app.name}</span>
                                                </div>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px;">
                                                    ${app.packages.map(pkg => this.renderPackageCard(pkg))}
                                                </div>
                                            </td>
                                        </tr>
                                    ` : ''}
                                `;
                            })}
                            ${secondaryApps.length === 0 ? html`<tr><td colspan="5" class="empty-row" style="text-align: center; padding: 2.5rem; color: var(--text-dim);">No secondary applications matching filters.</td></tr>` : ''}
                        </tbody>
                    </table>
                </div>
            </div>

            ${this.renderForeignAppsTable()}
        `;
    }

    // --- Sub-Section: Multi-Language Guest Applications & Microservices ---
    renderForeignAppsTable() {
        const { foreignApps, foreignServices, meshOperating } = this.state;
        const guestApps = (foreignApps || []).filter(a => (a.type || '').toLowerCase() !== 'spp');
        const totalCount = guestApps.length;
        const runningCount = guestApps.filter(a => a.running).length;

        const getLangBadge = (type) => {
            switch ((type || '').toLowerCase()) {
                case 'python': return html`<span class="badge-lang-python">🐍 Python</span>`;
                case 'node':
                case 'nodejs': return html`<span class="badge-lang-node">🟢 Node.js</span>`;
                case 'php':
                case 'php-fpm': return html`<span class="badge-lang-php">🐘 PHP-FPM</span>`;
                case 'go':
                case 'golang': return html`<span class="badge-lang-go">🐹 Go</span>`;
                case 'static': return html`<span class="badge-lang-static">📄 Static SPA</span>`;
                default: return html`<span class="badge-lang-default">🌐 ${type}</span>`;
            }
        };

        return html`
            <div class="glass-panel registry-panel fade-in" style="margin-top: 2rem;">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div class="header-main" style="display: flex; align-items: center; gap: 10px;">
                        <h3 class="gradient-text" style="margin: 0;">🌐 Multi-Language Applications & Microservices</h3>
                        <span class="count-badge">${totalCount}</span>
                        <span class="badge ${runningCount > 0 ? 'badge-success' : 'badge-neutral'}" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 999px;">
                            ${runningCount} / ${totalCount} Running
                        </span>
                    </div>
                    <div class="header-actions-right" style="display: flex; gap: 8px;">
                        <button class="btn ghost-btn btn-sm" ?disabled=${meshOperating} @click=${() => this.refreshMeshStatus()}>
                            🔄 Refresh Daemons
                        </button>
                        <button class="btn secondary-btn btn-sm" @click=${() => this.setTab('mesh')}>
                            🕸️ Service Mesh Studio
                        </button>
                    </div>
                </div>

                <p style="color: var(--text-dim); font-size: 0.85rem; margin: -10px 0 16px 0;">
                    Guest applications running in any language (Python, Node.js, Go, PHP) seamlessly routed under server route directories and subdomains via the SPP Ambient Mesh.
                </p>

                ${totalCount === 0 ? html`
                    <div class="empty-state" style="padding: 2.5rem; text-align: center;">
                        <div class="empty-icon" style="font-size: 2.5rem; margin-bottom: 0.5rem;">🕸️</div>
                        <h4>No Multi-Language Applications Registered</h4>
                        <p style="color: var(--text-dim); max-width: 520px; margin: 0 auto 1.5rem auto;">
                            Declare Python, Node.js, or external PHP apps inside <code>etc/apps.yml</code> to run them natively under SPP subpaths and subdomains.
                        </p>
                        <button class="btn primary-btn btn-sm" @click=${() => this.createSampleForeignApp()}>
                            ✨ Scaffold Example Microservice
                        </button>
                    </div>
                ` : html`
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Application & ID</th>
                                    <th style="width: 15%;">Stack & Runtime</th>
                                    <th style="width: 20%;">Route & Subdomain</th>
                                    <th style="width: 15%;">Supervisor & Port</th>
                                    <th class="text-right" style="width: 25%;">Lifecycle & Mesh Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${guestApps.map(app => {
                                    const appSvcs = (foreignServices || []).filter(s => s.app_id === app.id);
                                    const isRunning = !!app.running;
                                    const isDaemon = !!app.is_daemon;

                                    return html`
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="font-size: 1.5rem;">${app.type === 'python' ? '🐍' : app.type === 'node' ? '🟢' : app.type === 'php-fpm' ? '🐘' : app.type === 'go' ? '🐹' : '🌐'}</div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--text-primary);">${app.name}</div>
                                                        <div style="font-size: 0.75rem; color: var(--text-dim); font-family: monospace;">id: ${app.id}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>${getLangBadge(app.type)}</div>
                                                ${appSvcs.length > 0 ? html`
                                                    <div style="font-size: 0.72rem; color: var(--primary); font-weight: 500; margin-top: 4px;">
                                                        ⚡ ${appSvcs.length} exposed ${appSvcs.length === 1 ? 'service' : 'services'}
                                                    </div>
                                                ` : ''}
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 3px;">
                                                    <div>
                                                        <a href="${app.route}" target="_blank" class="code-badge link" title="Open ${app.route} in new tab" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                            📁 <code>${app.route}</code> ↗
                                                        </a>
                                                    </div>
                                                    ${app.subdomain && app.subdomain !== '-' ? html`
                                                        <div style="font-size: 0.75rem; color: var(--text-dim);">
                                                            🌐 <code>${app.subdomain}</code>
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                                    ${isDaemon ? html`
                                                        <div style="display: flex; align-items: center; gap: 6px;">
                                                            <span class="status-indicator ${isRunning ? 'active' : ''}" style="${!isRunning ? 'background: #ef4444;' : ''}"></span>
                                                            <span style="font-size: 0.8rem; font-weight: 500; color: ${isRunning ? 'var(--success)' : 'var(--danger)'};">
                                                                ${isRunning ? 'Running' : 'Stopped'}
                                                            </span>
                                                        </div>
                                                        ${isRunning ? html`
                                                            <div style="font-size: 0.72rem; color: var(--text-dim); font-family: monospace;">
                                                                PID: ${app.pid} | Port: ${app.port}
                                                            </div>
                                                        ` : ''}
                                                    ` : html`
                                                        <div style="display: flex; align-items: center; gap: 6px;">
                                                            <span class="status-indicator ${app.enabled ? 'active' : ''}" style="${!app.enabled ? 'background: #94a3b8;' : ''}"></span>
                                                            <span style="font-size: 0.8rem; font-weight: 500; color: ${app.enabled ? 'var(--success)' : 'var(--text-dim)'};">
                                                                ${app.state_label || (app.type === 'static' ? 'Ready (Static)' : (app.type === 'php-fpm' ? 'Ready (PHP-FPM)' : 'Ready (PHP Engine)'))}
                                                            </span>
                                                        </div>
                                                        <div style="font-size: 0.72rem; color: var(--text-dim); font-family: monospace;">
                                                            ${app.type === 'static' ? 'Direct Asset Delivery' : 'On-Demand SAPI / Pool'}
                                                        </div>
                                                    `}
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end; flex-wrap: wrap;">
                                                    ${isDaemon ? html`
                                                        ${isRunning ? html`
                                                            <button class="btn ghost-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.stopDaemon(app.id)} title="Stop Daemon" style="color: #f87171;">
                                                                ⏹️ Stop
                                                            </button>
                                                            <button class="btn ghost-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.restartDaemon(app.id)} title="Restart Daemon">
                                                                🔄 Restart
                                                            </button>
                                                        ` : html`
                                                            <button class="btn primary-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.startDaemon(app.id)} title="Start Daemon">
                                                                ▶️ Start
                                                            </button>
                                                        `}
                                                        <button class="btn ghost-btn btn-xs" @click=${() => this.openMeshLogs(app.id)} title="View Output Logs">
                                                            📜 Logs
                                                        </button>
                                                    ` : ''}
                                                    <button class="btn ghost-btn btn-xs" @click=${() => this.openRpcSandboxForApp(app.id)} title="Invoke Service RPC">
                                                        ⚡ RPC
                                                    </button>
                                                    <a href="${app.route}" target="_blank" class="btn secondary-btn btn-xs" style="text-decoration: none;" title="Open in browser">
                                                        🌐 Open
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    </div>
                `}
            </div>
        `;
    }

    // --- Tab: Service Mesh & Daemons ---
    renderMeshTab() {
        const {
            foreignApps,
            foreignServices,
            meshOperating,
            meshRpcApp,
            meshRpcEndpoint,
            meshRpcMethod,
            meshRpcPayload,
            meshRpcResult,
            meshRpcExecuting,
            meshFilterTerm,
            meshEventName,
            meshEventPayload,
            meshEventResult,
            meshEventDispatching
        } = this.state;

        const totalApps = (foreignApps || []).length;
        const runningDaemons = (foreignApps || []).filter(a => a.running).length;
        const totalServices = (foreignServices || []).length;

        const filteredApps = (foreignApps || []).filter(a => {
            if (!meshFilterTerm) return true;
            const term = meshFilterTerm.toLowerCase();
            return (a.id || '').toLowerCase().includes(term) ||
                   (a.name || '').toLowerCase().includes(term) ||
                   (a.type || '').toLowerCase().includes(term) ||
                   (a.route || '').toLowerCase().includes(term);
        });

        return html`
            <div class="mesh-view fade-in">
                <!-- Stat Overview Header -->
                <div class="mesh-stat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="glass-panel" style="padding: 16px; border-radius: 12px; display: flex; align-items: center; gap: 14px;">
                        <div style="font-size: 2.2rem; background: rgba(56, 189, 248, 0.1); width: 52px; height: 52px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">📱</div>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Registered Apps</div>
                            <div style="font-size: 1.6rem; font-weight: 700; color: var(--text-bright);">${totalApps}</div>
                        </div>
                    </div>
                    <div class="glass-panel" style="padding: 16px; border-radius: 12px; display: flex; align-items: center; gap: 14px;">
                        <div style="font-size: 2.2rem; background: rgba(74, 222, 128, 0.1); width: 52px; height: 52px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">🟢</div>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Active Daemons</div>
                            <div style="font-size: 1.6rem; font-weight: 700; color: #4ade80;">${runningDaemons} <span style="font-size: 0.9rem; color: var(--text-dim);">/ ${totalApps}</span></div>
                        </div>
                    </div>
                    <div class="glass-panel" style="padding: 16px; border-radius: 12px; display: flex; align-items: center; gap: 14px;">
                        <div style="font-size: 2.2rem; background: rgba(167, 139, 250, 0.1); width: 52px; height: 52px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">⚡</div>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Mesh Services</div>
                            <div style="font-size: 1.6rem; font-weight: 700; color: #a78bfa;">${totalServices}</div>
                        </div>
                    </div>
                    <div class="glass-panel" style="padding: 16px; border-radius: 12px; display: flex; align-items: center; gap: 14px;">
                        <div style="font-size: 2.2rem; background: rgba(251, 191, 36, 0.1); width: 52px; height: 52px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">🔌</div>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Port Pool</div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #fbbf24; font-family: monospace;">10000 - 20000</div>
                        </div>
                    </div>
                </div>

                ${this.renderHelpBanner('mesh', 'Polyglot Service Mesh & Daemon Supervisor Guide',
                    'Monitor, start, stop, and inspect background worker daemons, scheduled tasks, and multi-language service microprocesses (Python, Node.js, Go, Rust).',
                    {
                        steps: 'Review live process state, PID, memory, and CPU metrics. Click "Start", "Stop", or "Restart" to manage daemon lifecycles. Click "Logs" to open real-time tail streams for any running service.',
                        concepts: 'The SPP Mesh Supervisor orchestrates background workers and DAG jobs with token-bucket flow control and distributed mutex locking (acquireDeploymentLock).',
                        proTip: 'If a daemon fails repeatedly, check its crash count and inspect recent stderr traces via the Log Viewer before restarting.'
                    }
                )}

                <!-- Section 1: Process Supervisor & Microservices Fleet -->
                <div class="glass-panel registry-panel mb-4" style="margin-bottom: 24px;">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div class="header-main" style="display: flex; align-items: center; gap: 12px;">
                            <h3 class="gradient-text" style="margin: 0;">🚀 Process Supervisor &amp; Daemons</h3>
                            <span class="count-badge">${filteredApps.length}</span>
                        </div>
                        <div class="header-actions-right" style="display: flex; gap: 8px; align-items: center;">
                            ${this.renderHelpButton('mesh')}
                            <input type="text" placeholder="Filter microservices..." 
                                value="${meshFilterTerm}" 
                                @input=${(e) => this.setState({ meshFilterTerm: e.target.value })}
                                style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text-bright); width: 180px;">
                            <button class="btn primary-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.startAllDaemons()}>
                                ▶️ Start All
                            </button>
                            <button class="btn ghost-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.stopAllDaemons()} style="color: var(--danger);">
                                ⏹️ Stop All
                            </button>
                            <button class="btn ghost-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.refreshMeshStatus()}>
                                🔄 Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Application & ID</th>
                                    <th style="width: 15%;">Stack</th>
                                    <th style="width: 20%;">Route Prefix</th>
                                    <th style="width: 15%;">PID & Port</th>
                                    <th style="width: 10%;">State</th>
                                    <th class="text-right" style="width: 15%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${filteredApps.length === 0 ? html`
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-dim);">
                                            No microservices match your filter.
                                        </td>
                                    </tr>
                                ` : filteredApps.map(app => {
                                    const isRunning = !!app.running;
                                    const isDaemon = !!app.is_daemon;

                                    return html`
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <span style="font-size: 1.4rem;">${app.type === 'python' ? '🐍' : app.type === 'node' ? '🟢' : app.type === 'php-fpm' ? '🐘' : app.type === 'go' ? '🐹' : '🌐'}</span>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--text-primary);">${app.name}</div>
                                                        <div style="font-size: 0.72rem; color: var(--text-dim); font-family: monospace;">id: ${app.id}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="code-badge" style="font-size: 0.75rem;">${app.type}</code>
                                            </td>
                                            <td>
                                                <a href="${app.route}" target="_blank" class="code-badge link" style="text-decoration: none;">
                                                    ${app.route} ↗
                                                </a>
                                            </td>
                                            <td>
                                                <div style="font-family: monospace; font-size: 0.78rem;">
                                                    ${isDaemon ? html`
                                                        ${isRunning ? html`
                                                            <div>PID: <span style="color: var(--success); font-weight: 600;">${app.pid}</span></div>
                                                            <div style="color: var(--text-dim);">Port: ${app.port}</div>
                                                        ` : html`<span style="color: var(--text-dim);">-</span>`}
                                                    ` : html`<span style="color: var(--text-dim);">${app.type === 'static' ? 'Static (Direct)' : 'SAPI / Pool'}</span>`}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge ${isRunning ? 'badge-success' : (!app.enabled ? 'badge-neutral' : 'badge-neutral')}" style="font-size: 0.75rem; padding: 2px 8px;">
                                                    ${app.state_label || (isRunning ? (isDaemon ? 'Running' : 'Ready') : 'Stopped')}
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end;">
                                                    ${isDaemon ? html`
                                                        ${isRunning ? html`
                                                            <button class="btn ghost-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.stopDaemon(app.id)} style="color: var(--danger);" title="Stop Daemon">⏹️</button>
                                                            <button class="btn ghost-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.restartDaemon(app.id)} title="Restart Daemon">🔄</button>
                                                        ` : html`
                                                            <button class="btn primary-btn btn-xs" ?disabled=${meshOperating} @click=${() => this.startDaemon(app.id)} title="Start Daemon">▶️</button>
                                                        `}
                                                        <button class="btn ghost-btn btn-xs" @click=${() => this.openMeshLogs(app.id)} title="Logs">📜</button>
                                                    ` : ''}
                                                    <button class="btn ghost-btn btn-xs" @click=${() => this.openRpcSandboxForApp(app.id)} title="RPC">⚡</button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 2 & 3: Two-Column Mesh Studio (RPC Sandbox + Event Bus) -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 24px; margin-bottom: 24px;">
                    
                    <!-- Inter-App RPC Sandbox -->
                    <div class="glass-panel" style="padding: 20px; border-radius: 12px; display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 class="gradient-text" style="margin: 0 0 4px 0;">⚡ Inter-App RPC Sandbox</h3>
                                <div style="font-size: 0.8rem; color: var(--text-dim);">Execute live remote calls to guest microservices via \\SPP\\Mesh</div>
                            </div>
                            <span class="badge" style="background: rgba(167, 139, 250, 0.15); color: #a78bfa;">Sovereign Mesh</span>
                        </div>

                        <div style="display: grid; grid-template-columns: 2fr 3fr 1fr; gap: 8px;">
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-dim); display: block; margin-bottom: 4px;">Target App</label>
                                <select id="mesh-rpc-app-select" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text-bright); font-size: 0.82rem;"
                                    @change=${(e) => this.onMeshRpcAppChange(e.target.value)}>
                                    <option value="">-- Select App --</option>
                                    ${(foreignApps || []).map(a => html`
                                        <option value="${a.id}" ?selected="${meshRpcApp === a.id}">${a.name} (${a.id})</option>
                                    `)}
                                </select>
                            </div>

                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-dim); display: block; margin-bottom: 4px;">Service Endpoint</label>
                                <input type="text" id="mesh-rpc-endpoint" placeholder="/api/predict" value="${meshRpcEndpoint}"
                                    @input=${(e) => this.setState({ meshRpcEndpoint: e.target.value })}
                                    style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text-bright); font-size: 0.82rem; font-family: monospace;">
                            </div>

                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-dim); display: block; margin-bottom: 4px;">Method</label>
                                <select id="mesh-rpc-method" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text-bright); font-size: 0.82rem;"
                                    @change=${(e) => this.setState({ meshRpcMethod: e.target.value })}>
                                    <option value="POST" ?selected="${meshRpcMethod === 'POST'}">POST</option>
                                    <option value="GET" ?selected="${meshRpcMethod === 'GET'}">GET</option>
                                    <option value="PUT" ?selected="${meshRpcMethod === 'PUT'}">PUT</option>
                                    <option value="DELETE" ?selected="${meshRpcMethod === 'DELETE'}">DELETE</option>
                                </select>
                            </div>
                        </div>

                        ${foreignServices.filter(s => s.app_id === meshRpcApp).length > 0 ? html`
                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                <span style="font-size: 0.72rem; color: var(--text-dim);">Discovered endpoints:</span>
                                ${foreignServices.filter(s => s.app_id === meshRpcApp).map(svc => html`
                                    <button class="btn ghost-btn btn-xs" style="font-family: monospace; font-size: 0.72rem; padding: 2px 6px;"
                                        @click=${() => this.setState({ meshRpcEndpoint: svc.endpoint, meshRpcMethod: svc.method || 'POST' })}>
                                        ${svc.method} ${svc.endpoint}
                                    </button>
                                `)}
                            </div>
                        ` : ''}

                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <label style="font-size: 0.75rem; color: var(--text-dim);">JSON Payload</label>
                                <button class="btn ghost-btn btn-xs" style="font-size: 0.7rem; padding: 2px 6px;"
                                    @click=${() => this.formatRpcPayload()}>Format JSON</button>
                            </div>
                            <textarea id="mesh-rpc-payload" rows="4" 
                                style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--glass-border); background: #0d1117; color: #58a6ff; font-family: monospace; font-size: 0.82rem; resize: vertical;"
                                @input=${(e) => this.setState({ meshRpcPayload: e.target.value })}>${meshRpcPayload}</textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button class="btn primary-btn btn-sm" ?disabled=${meshRpcExecuting || !meshRpcApp} @click=${() => this.executeMeshRpc()}>
                                ${meshRpcExecuting ? '⏳ Executing Call...' : '🚀 Invoke Mesh Service'}
                            </button>
                        </div>

                        ${meshRpcResult ? html`
                            <div class="mesh-rpc-result-card fade-in" style="background: rgba(0,0,0,0.3); border: 1px solid ${meshRpcResult.success ? 'rgba(74, 222, 128, 0.3)' : 'rgba(239, 68, 68, 0.3)'}; border-radius: 8px; padding: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="badge ${meshRpcResult.success ? 'badge-success' : 'badge-danger'}" style="font-size: 0.75rem;">
                                            ${meshRpcResult.success ? '200 OK' : 'RPC Error'}
                                        </span>
                                        <span style="font-size: 0.75rem; color: var(--text-dim); font-family: monospace;">
                                            ⏱️ ${meshRpcResult.duration_ms || 0} ms
                                        </span>
                                    </div>
                                    <div style="font-size: 0.72rem; color: var(--text-dim); font-family: monospace;">
                                        Target: ${meshRpcResult.app_id || meshRpcApp}${meshRpcResult.endpoint || meshRpcEndpoint}
                                    </div>
                                </div>
                                <pre style="margin: 0; padding: 10px; background: #0d1117; border-radius: 6px; font-size: 0.78rem; color: ${meshRpcResult.success ? '#7ee787' : '#f85149'}; max-height: 200px; overflow-y: auto; font-family: monospace;">${JSON.stringify(meshRpcResult.response || meshRpcResult.error, null, 2)}</pre>
                            </div>
                        ` : ''}
                    </div>

                    <!-- Cross-App Unified Event Bus -->
                    <div class="glass-panel" style="padding: 20px; border-radius: 12px; display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 class="gradient-text" style="margin: 0 0 4px 0;">📡 Inter-App Event Bus</h3>
                                <div style="font-size: 0.8rem; color: var(--text-dim);">Broadcast domain events across native SPP listeners & guest microservices</div>
                            </div>
                            <span class="badge badge-success">Pub/Sub</span>
                        </div>

                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-dim); display: block; margin-bottom: 4px;">Event Name</label>
                            <input type="text" id="mesh-event-name" placeholder="e.g. user.created or workflow.after_transition" value="${meshEventName}"
                                @input=${(e) => this.setState({ meshEventName: e.target.value })}
                                style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text-bright); font-size: 0.82rem; font-family: monospace;">
                        </div>

                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                            <span style="font-size: 0.72rem; color: var(--text-dim);">Common events:</span>
                            <button class="btn ghost-btn btn-xs" style="font-size: 0.7rem; padding: 2px 6px;"
                                @click=${() => this.setState({ meshEventName: 'user.created', meshEventPayload: '{\n  "user_id": 42,\n  "role": "student"\n}' })}>
                                user.created
                            </button>
                            <button class="btn ghost-btn btn-xs" style="font-size: 0.7rem; padding: 2px 6px;"
                                @click=${() => this.setState({ meshEventName: 'workflow.after_transition', meshEventPayload: '{\n  "entity": "Student",\n  "transition": "admit"\n}' })}>
                                workflow.after_transition
                            </button>
                            <button class="btn ghost-btn btn-xs" style="font-size: 0.7rem; padding: 2px 6px;"
                                @click=${() => this.setState({ meshEventName: 'ai.inference_requested', meshEventPayload: '{\n  "prompt": "Evaluate performance"\n}' })}>
                                ai.inference_requested
                            </button>
                        </div>

                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-dim); display: block; margin-bottom: 4px;">Payload (JSON)</label>
                            <textarea id="mesh-event-payload" rows="4" 
                                style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--glass-border); background: #0d1117; color: #a5d6ff; font-family: monospace; font-size: 0.82rem; resize: vertical;"
                                @input=${(e) => this.setState({ meshEventPayload: e.target.value })}>${meshEventPayload}</textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button class="btn primary-btn btn-sm" ?disabled=${meshEventDispatching || !meshEventName} @click=${() => this.dispatchMeshEvent()}>
                                ${meshEventDispatching ? '⏳ Firing Event...' : '🔥 Dispatch Event to Mesh'}
                            </button>
                        </div>

                        ${meshEventResult ? html`
                            <div class="mesh-event-result-card fade-in" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(74, 222, 128, 0.3); border-radius: 8px; padding: 12px;">
                                <div style="font-size: 0.8rem; color: #7ee787; font-weight: 600; margin-bottom: 4px;">
                                    ✔ Event '${meshEventResult.event}' successfully broadcast to bus
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);">
                                    Propagated to native listeners and guest microservices via <code>/spp-mesh/events</code>.
                                </div>
                            </div>
                        ` : ''}
                    </div>

                </div>
            </div>
        `;
    }

    // --- Mesh Controller Helpers ---
    async refreshMeshStatus() {
        this.setState({ meshOperating: true });
        try {
            const res = await (this.admin || window.admin).api('apps_mesh_status');
            const data = res.data || res;
            if (res.success || data.success) {
                this.setState({
                    foreignApps: data.statuses || [],
                    foreignServices: data.services || [],
                    meshOperating: false
                });
                this.notify('Mesh microservices synchronized.', 'success');
            } else {
                this.notify(data.error || 'Failed to refresh mesh status.', 'error');
                this.setState({ meshOperating: false });
            }
        } catch (e) {
            this.notify('Mesh refresh error: ' + e.message, 'error');
            this.setState({ meshOperating: false });
        }
    }

    async startDaemon(appId) {
        this.setState({ meshOperating: true });
        this.notify(`Starting microservice '${appId}'...`, 'info');
        try {
            const res = await (this.admin || window.admin).api('apps_mesh_start', { app_id: appId });
            const data = res.data || res;
            if (data.success) {
                this.notify(`Microservice '${appId}' started successfully on port ${data.port || ''}!`, 'success');
                await this.refreshMeshStatus();
            } else {
                this.notify(data.error || 'Failed to start microservice.', 'error');
                this.setState({ meshOperating: false });
            }
        } catch (e) {
            this.notify('Start error: ' + e.message, 'error');
            this.setState({ meshOperating: false });
        }
    }

    async stopDaemon(appId) {
        this.setState({ meshOperating: true });
        this.notify(`Stopping microservice '${appId}'...`, 'info');
        try {
            const res = await (this.admin || window.admin).api('apps_mesh_stop', { app_id: appId });
            const data = res.data || res;
            if (data.success) {
                this.notify(`Microservice '${appId}' stopped.`, 'success');
                await this.refreshMeshStatus();
            } else {
                this.notify(data.error || 'Failed to stop microservice.', 'error');
                this.setState({ meshOperating: false });
            }
        } catch (e) {
            this.notify('Stop error: ' + e.message, 'error');
            this.setState({ meshOperating: false });
        }
    }

    async restartDaemon(appId) {
        this.setState({ meshOperating: true });
        this.notify(`Restarting microservice '${appId}'...`, 'info');
        try {
            const res = await (this.admin || window.admin).api('apps_mesh_restart', { app_id: appId });
            const data = res.data || res;
            if (data.success) {
                this.notify(`Microservice '${appId}' restarted on port ${data.port || ''}!`, 'success');
                await this.refreshMeshStatus();
            } else {
                this.notify(data.error || 'Failed to restart microservice.', 'error');
                this.setState({ meshOperating: false });
            }
        } catch (e) {
            this.notify('Restart error: ' + e.message, 'error');
            this.setState({ meshOperating: false });
        }
    }

    async startAllDaemons() {
        this.setState({ meshOperating: true });
        const { foreignApps } = this.state;
        const toStart = (foreignApps || []).filter(a => a.is_daemon && !a.running);
        if (toStart.length === 0) {
            this.notify('All microservice daemons are already running.', 'info');
            this.setState({ meshOperating: false });
            return;
        }
        this.notify(`Starting ${toStart.length} microservices...`, 'info');
        for (const app of toStart) {
            await (this.admin || window.admin).api('apps_mesh_start', { app_id: app.id });
        }
        this.notify('All microservices started.', 'success');
        await this.refreshMeshStatus();
    }

    async stopAllDaemons() {
        this.setState({ meshOperating: true });
        const { foreignApps } = this.state;
        const toStop = (foreignApps || []).filter(a => a.is_daemon && a.running);
        if (toStop.length === 0) {
            this.notify('No microservice daemons are currently running.', 'info');
            this.setState({ meshOperating: false });
            return;
        }
        this.notify(`Stopping ${toStop.length} microservices...`, 'info');
        for (const app of toStop) {
            await (this.admin || window.admin).api('apps_mesh_stop', { app_id: app.id });
        }
        this.notify('All microservices stopped.', 'success');
        await this.refreshMeshStatus();
    }

    async openMeshLogs(appId) {
        this.setState({ meshLogApp: appId, meshLogLoading: true, meshLogLines: 'Loading logs...' });
        
        const fetchLogs = async () => {
            try {
                const res = await (this.admin || window.admin).api('apps_mesh_logs', { app_id: appId, lines: 150 });
                const data = res.data || res;
                const logs = data.logs || 'No log output captured yet.';
                const preEl = document.getElementById('mesh-log-terminal');
                if (preEl) {
                    preEl.textContent = logs;
                    preEl.scrollTop = preEl.scrollHeight;
                }
            } catch (e) {
                console.error("Log fetch error", e);
            }
        };

        const modalHtml = html`
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.85rem; color: var(--text-dim);">
                        Supervisor Output: <code>var/run/apps/${appId}.log</code>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn ghost-btn btn-xs" id="mesh-log-refresh-btn">🔄 Refresh</button>
                    </div>
                </div>
                <pre id="mesh-log-terminal" style="margin: 0; padding: 14px; background: #0d1117; color: #58a6ff; font-family: monospace; font-size: 0.8rem; border-radius: 8px; height: 380px; overflow-y: auto; white-space: pre-wrap; line-height: 1.45;">Loading logs for ${appId}...</pre>
            </div>
        `;

        this.openModal(`📜 Logs: ${appId}`, modalHtml);
        setTimeout(async () => {
            await fetchLogs();
            const refBtn = document.getElementById('mesh-log-refresh-btn');
            if (refBtn) {
                refBtn.addEventListener('click', fetchLogs);
            }
        }, 50);
    }

    onMeshRpcAppChange(appId) {
        const { foreignServices } = this.state;
        const appSvcs = (foreignServices || []).filter(s => s.app_id === appId);
        const defaultEp = appSvcs.length > 0 ? appSvcs[0].endpoint : '/';
        const defaultMethod = appSvcs.length > 0 ? (appSvcs[0].method || 'POST') : 'POST';
        this.setState({
            meshRpcApp: appId,
            meshRpcEndpoint: defaultEp,
            meshRpcMethod: defaultMethod,
            meshRpcResult: null
        });
    }

    openRpcSandboxForApp(appId) {
        this.setTab('mesh');
        this.onMeshRpcAppChange(appId);
        setTimeout(() => {
            const el = document.getElementById('mesh-rpc-endpoint');
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        }, 100);
    }

    formatRpcPayload() {
        try {
            const parsed = JSON.parse(this.state.meshRpcPayload || '{}');
            this.setState({ meshRpcPayload: JSON.stringify(parsed, null, 2) });
            const ta = document.getElementById('mesh-rpc-payload');
            if (ta) ta.value = JSON.stringify(parsed, null, 2);
        } catch (e) {
            this.notify('Invalid JSON: ' + e.message, 'error');
        }
    }

    async executeMeshRpc() {
        const { meshRpcApp, meshRpcEndpoint, meshRpcMethod, meshRpcPayload } = this.state;
        if (!meshRpcApp) {
            return this.notify('Please select a target application.', 'error');
        }

        let payload = null;
        if (meshRpcMethod !== 'GET') {
            try {
                const trimmed = (meshRpcPayload || '').trim();
                if (trimmed) payload = JSON.parse(trimmed);
            } catch (e) {
                return this.notify('Invalid JSON payload: ' + e.message, 'error');
            }
        }

        this.setState({ meshRpcExecuting: true, meshRpcResult: null });
        try {
            const res = await (this.admin || window.admin).api('apps_mesh_call', {
                app_id: meshRpcApp,
                endpoint: meshRpcEndpoint,
                method: meshRpcMethod,
                payload: payload
            });
            const data = res.data || res;
            this.setState({
                meshRpcExecuting: false,
                meshRpcResult: data
            });
            if (data.success) {
                this.notify(`Mesh RPC call succeeded in ${data.duration_ms || 0}ms!`, 'success');
            } else {
                this.notify(data.error || 'RPC call failed', 'error');
            }
        } catch (e) {
            this.setState({
                meshRpcExecuting: false,
                meshRpcResult: { success: false, error: e.message }
            });
            this.notify('RPC Execution error: ' + e.message, 'error');
        }
    }

    async dispatchMeshEvent() {
        const { meshEventName, meshEventPayload } = this.state;
        if (!meshEventName) {
            return this.notify('Please provide an event name.', 'error');
        }

        let payload = {};
        try {
            const trimmed = (meshEventPayload || '').trim();
            if (trimmed) payload = JSON.parse(trimmed);
        } catch (e) {
            return this.notify('Invalid JSON payload: ' + e.message, 'error');
        }

        this.setState({ meshEventDispatching: true, meshEventResult: null });
        try {
            const res = await (this.admin || window.admin).api('apps_mesh_publish_event', {
                event: meshEventName,
                payload: payload
            });
            const data = res.data || res;
            this.setState({
                meshEventDispatching: false,
                meshEventResult: data
            });
            this.notify(`Dispatched event '${meshEventName}' to Mesh!`, 'success');
        } catch (e) {
            this.setState({ meshEventDispatching: false });
            this.notify('Dispatch error: ' + e.message, 'error');
        }
    }

    createSampleForeignApp() {
        this.openModal('Register Multi-Language Microservice', html`
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <p style="font-size: 0.85rem; color: var(--text-dim); margin: 0;">
                    To add an application, add an entry to <code>etc/apps.yml</code>:
                </p>
                <pre style="margin: 0; padding: 12px; background: #0d1117; color: #7ee787; border-radius: 6px; font-size: 0.8rem; font-family: monospace;">apps:
  ai_engine:
    name: "AI Inference Engine"
    type: python
    route: /ai
    subdomain: ai.school.local
    root_dir: apps/ai_engine
    command: "python -m uvicorn main:app --host 127.0.0.1 --port {PORT}"
    health_check: /health
    enabled: true</pre>
                <div style="font-size: 0.8rem; color: var(--text-dim);">
                    SPP will automatically supervise the daemon process, assign a free port from the dynamic pool, and route <code>/ai/*</code> transparently without broken assets!
                </div>
            </div>
        `);
    }

    notify(msg, type = 'info') {
        if (typeof SPPUX !== 'undefined' && SPPUX.notify) {
            return SPPUX.notify(msg, type);
        }
        if (this.admin && typeof this.admin.notify === 'function') {
            return this.admin.notify(msg, type);
        }
        if (window.admin && typeof window.admin.notify === 'function') {
            return window.admin.notify(msg, type);
        }
        console.log(`[${type}] ${msg}`);
    }

    openModal(title, content, actions = []) {
        if (typeof SPPUX !== 'undefined' && SPPUX.openModal) {
            return SPPUX.openModal(title, content, actions);
        }
        if (this.admin && typeof this.admin.openModal === 'function') {
            return this.admin.openModal(title, content, actions);
        }
        if (window.admin && typeof window.admin.openModal === 'function') {
            return window.admin.openModal(title, content, actions);
        }
    }

    closeModal() {
        if (typeof SPPUX !== 'undefined' && typeof SPPUX.closeModal === 'function') {
            return SPPUX.closeModal();
        }
        if (this.admin && typeof this.admin.closeModal === 'function') {
            return this.admin.closeModal();
        }
        if (window.admin && typeof window.admin.closeModal === 'function') {
            return window.admin.closeModal();
        }
        const modal = document.querySelector('.sppux-modal-overlay, .modal-backdrop, .modal');
        if (modal) modal.remove();
    }

    // --- Domain-Scoped Application View ---
    renderDomainAppsView(domainName) {
        const { domainAppsData, loadingDomainApps } = this.state;
        
        if (loadingDomainApps && !domainAppsData) {
            return html`
                <div class="glass-panel" style="padding: 3rem; text-align: center;">
                    <div class="sppux-spinner"></div>
                    <div style="margin-top: 1rem; color: var(--text-dim);">Loading applications for <strong>${domainName}</strong>...</div>
                </div>
            `;
        }

        const data = domainAppsData || {};
        const defaultApp = data.default_app || { name: 'default', title: 'Default', type: 'spp', route: '/', target_path: 'domains/' + domainName + '/src' };
        const subApps = data.sub_apps || [];
        const availableApps = data.available_framework_apps || [];
        const account = data.account || 'default';
        const appDir = data.app_dir || data.suggested_defaults?.app_dir || 'Default Workspace';
        const dataDir = data.data_dir || data.suggested_defaults?.data_dir || 'Default Data Dir';

        return html`
            <div class="domain-apps-view fade-in">
                <!-- Domain Overview Header -->
                <div class="glass-panel" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; border-left: 4px solid var(--accent, #6366f1);">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                            <h2 style="margin: 0; font-size: 1.35rem; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                <span>🌐 ${domainName}</span>
                            </h2>
                            <span class="badge" style="background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.3); font-size: 0.75rem; padding: 2px 8px; border-radius: 12px;">
                                Account: <strong>${account}</strong>
                            </span>
                        </div>
                        <div style="display: flex; gap: 18px; font-size: 0.8rem; color: var(--text-dim); flex-wrap: wrap; margin-top: 6px;">
                            <span>📁 App Directory: <code style="color: var(--text-bright);">${appDir}</code></span>
                            <span>💾 Data Directory: <code style="color: var(--text-bright);">${dataDir}</code></span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <a href="https://${domainName}" target="_blank" class="btn ghost-btn btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                            🔗 Open Site
                        </a>
                        <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openInstallToDomainModal(domainName)} style="display: inline-flex; align-items: center; gap: 6px;">
                            ➕ Install Application on ${domainName}
                        </button>
                    </div>
                </div>

                <!-- 1. Default Application (Served at Root /) -->
                <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(139,92,246,0.05) 100%); border: 1px solid rgba(99,102,241,0.35); box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span class="badge" style="background: #f59e0b; color: #000; font-weight: 800; font-size: 0.7rem; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.5px; text-transform: uppercase;">
                                    ⭐ DEFAULT ROOT APPLICATION
                                </span>
                                <span class="badge" style="background: ${defaultApp.type === 'spp' ? 'rgba(59,130,246,0.2)' : 'rgba(16,185,129,0.2)'}; color: ${defaultApp.type === 'spp' ? '#60a5fa' : '#34d399'}; border: 1px solid ${defaultApp.type === 'spp' ? 'rgba(59,130,246,0.4)' : 'rgba(16,185,129,0.4)'}; font-size: 0.7rem; padding: 2px 8px; border-radius: 6px; font-weight: 600;">
                                    ${defaultApp.type === 'spp' ? '🚀 SPP Native' : '🌐 Non-SPP (' + (defaultApp.index || 'PHP') + ')'}
                                </span>
                            </div>
                            <h3 style="margin: 0; font-size: 1.25rem; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                ${defaultApp.title || defaultApp.name}
                                <code style="font-size: 0.8rem; font-weight: normal; color: var(--text-dim); background: rgba(0,0,0,0.25); padding: 2px 6px; border-radius: 4px;">/</code>
                            </h3>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a href="https://${domainName}/" target="_blank" class="btn ghost-btn btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                🔗 View Root Site
                            </a>
                            ${defaultApp.type === 'spp' ? html`
                                <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openAppEditor(defaultApp.name)}>
                                    ⚙️ Configure
                                </button>
                            ` : ''}
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; background: rgba(0,0,0,0.2); padding: 12px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); font-size: 0.8rem;">
                        <div>
                            <div style="color: var(--text-dim); font-size: 0.72rem; text-transform: uppercase; margin-bottom: 3px;">Application Directory</div>
                            <div style="font-family: monospace; color: var(--text-bright); word-break: break-all;">
                                ${defaultApp.target_path || 'Default Workspace'}
                            </div>
                        </div>
                        <div>
                            <div style="color: var(--text-dim); font-size: 0.72rem; text-transform: uppercase; margin-bottom: 3px;">Data Directory</div>
                            <div style="font-family: monospace; color: var(--text-bright); word-break: break-all;">
                                ${defaultApp.data_dir || '(none)'}
                            </div>
                        </div>
                        <div>
                            <div style="color: var(--text-dim); font-size: 0.72rem; text-transform: uppercase; margin-bottom: 3px;">Entry Script</div>
                            <div style="font-family: monospace; color: var(--text-bright);">
                                ${defaultApp.index || 'index.php'}
                            </div>
                        </div>
                        <div>
                            <div style="color: var(--text-dim); font-size: 0.72rem; text-transform: uppercase; margin-bottom: 3px;">SPP Data Isolation</div>
                            <div style="color: ${defaultApp.opt_in_sppdata ? '#34d399' : 'var(--text-dim)'}; font-weight: 600;">
                                ${defaultApp.opt_in_sppdata ? '✔ Active (Protected SPPData)' : '— Standard'}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Secondary Applications Table (Base Routes) -->
                <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                <span>Secondary Applications &amp; Base Routes</span>
                                <span class="count-badge">${subApps.length}</span>
                            </h3>
                            <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: var(--text-dim);">
                                Applications mounted on sub-routes under <code>${domainName}</code>. Any secondary app can be promoted to the root default application with 1-click.
                            </p>
                        </div>
                        <button type="button" class="btn secondary-btn btn-sm" @click=${() => this.openInstallToDomainModal(domainName)}>
                            ➕ Add Secondary App
                        </button>
                    </div>

                    ${subApps.length === 0 ? html`
                        <div style="padding: 2.5rem 1rem; text-align: center; border: 1px dashed var(--glass-border); border-radius: 8px;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📂</div>
                            <h4 style="margin: 0 0 6px 0; color: var(--text-bright);">No Secondary Applications Mapped</h4>
                            <p style="margin: 0 auto 1rem auto; max-width: 480px; font-size: 0.85rem; color: var(--text-dim);">
                                You can install an SPP app or any external app (WordPress, Laravel, Node.js, static site) to run on a base route (e.g. <code>https://${domainName}/blog</code> or <code>https://${domainName}/store</code>).
                            </p>
                            <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openInstallToDomainModal(domainName)}>
                                ➕ Install First Secondary App
                            </button>
                        </div>
                    ` : html`
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Route</th>
                                        <th style="width: 20%;">Application</th>
                                        <th style="width: 15%;">Type &amp; Stack</th>
                                        <th style="width: 25%;">Directory Path</th>
                                        <th class="text-right" style="width: 20%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${subApps.map(sub => html`
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <code class="code-badge primary" style="font-size: 0.8rem; font-weight: 700;">
                                                        ${sub.route}
                                                    </code>
                                                </div>
                                                <div style="font-size: 0.72rem; color: var(--text-dim); margin-top: 3px;">
                                                    https://${domainName}${sub.route}
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: var(--text-bright);">
                                                    ${sub.title || sub.name}
                                                </div>
                                                <div style="font-size: 0.72rem; color: var(--text-dim); font-family: monospace;">
                                                    ${sub.name}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: ${sub.type === 'spp' ? 'rgba(59,130,246,0.15)' : 'rgba(16,185,129,0.15)'}; color: ${sub.type === 'spp' ? '#60a5fa' : '#34d399'}; border: 1px solid ${sub.type === 'spp' ? 'rgba(59,130,246,0.3)' : 'rgba(16,185,129,0.3)'}; font-size: 0.72rem; padding: 2px 7px; border-radius: 6px;">
                                                    ${sub.type === 'spp' ? '🚀 SPP Native' : '🌐 Non-SPP (' + (sub.index || 'PHP') + ')'}
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-family: monospace; font-size: 0.75rem; color: var(--text-bright); word-break: break-all;">
                                                    ${sub.target_path || '(default)'}
                                                </div>
                                                ${sub.data_dir ? html`
                                                    <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 2px;">
                                                        Data: <code>${sub.data_dir}</code>
                                                    </div>
                                                ` : ''}
                                            </td>
                                            <td class="text-right">
                                                <div class="btn-group-horizontal" style="justify-content: flex-end; gap: 4px;">
                                                    <button type="button" class="btn btn-sm" 
                                                        style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.35); font-size: 0.72rem; padding: 3px 8px; border-radius: 6px;"
                                                        @click=${() => this.makeAppDefault(domainName, sub.name, defaultApp.name)}
                                                        title="Promote this app to become the Default Root Application (/) for ${domainName}">
                                                        ⭐ Make Default
                                                    </button>
                                                    <a href="https://${domainName}${sub.route}" target="_blank" class="btn ghost-btn btn-sm" style="font-size: 0.72rem; padding: 3px 8px;">
                                                        🔗 Open
                                                    </a>
                                                    <button type="button" class="btn danger-btn btn-sm" style="font-size: 0.72rem; padding: 3px 8px;"
                                                        @click=${() => this.removeSubApp(domainName, sub.route, sub.name)}
                                                        title="Unmap this route from ${domainName}">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `)}
                                </tbody>
                            </table>
                        </div>
                    `}
                </div>

                <!-- 3. Available Framework Apps (Quick Attach) -->
                ${availableApps.length > 0 ? html`
                    <div class="glass-panel" style="padding: 1.25rem 1.5rem;">
                        <h4 style="margin: 0 0 10px 0; font-size: 0.95rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">
                            📦 Framework Applications in Workspace
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
                            ${availableApps.filter(a => a.name !== defaultApp.name && !subApps.some(s => s.name === a.name)).map(app => html`
                                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-bright);">${app.title || app.name}</div>
                                        <div style="font-size: 0.72rem; color: var(--text-dim);">${app.src_path || 'src/' + app.name}</div>
                                    </div>
                                    <button type="button" class="btn ghost-btn btn-sm" style="font-size: 0.72rem; padding: 3px 8px;"
                                        @click=${() => this.openInstallToDomainModal(domainName, app.name)}>
                                        ➕ Attach
                                    </button>
                                </div>
                            `)}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    async makeAppDefault(domain, appName, currentDefaultName) {
        const demotedRoute = prompt(
            `Promote '${appName}' to become the primary Default Application (/) for ${domain}?\n\n` +
            `Enter the sub-route to move the current default application ('${currentDefaultName}') to:`,
            `/${currentDefaultName.toLowerCase()}`
        );
        if (demotedRoute === null) return; // User cancelled

        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const res = await apiCall('domains_set_default_app', {
                domain: domain,
                app_name: appName,
                demoted_route: demotedRoute.trim() || null
            });

            if (res.success) {
                (this.admin || window.admin).notify(`🎉 ${appName} is now the Default Application (/) for ${domain}!`, 'success');
                await this.loadData();
                await this.loadDomainApps(domain);
                this.update();
            } else {
                (this.admin || window.admin).notify(res.error || 'Failed to set default application.', 'error');
            }
        } catch (e) {
            (this.admin || window.admin).notify('Error setting default app: ' + e.message, 'error');
        }
    }

    async removeSubApp(domain, route, appName) {
        if (!confirm(`Are you sure you want to remove the route '${route}' (${appName}) from ${domain}?\n\nThis will unmap the route from domains.yml but will NOT delete your files on disk.`)) {
            return;
        }

        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const res = await apiCall('domains_subapp_delete', {
                domain: domain,
                route: route
            });

            if (res.success) {
                (this.admin || window.admin).notify(`Route '${route}' removed from ${domain}.`, 'success');
                await this.loadDomainApps(domain);
                this.update();
            } else {
                (this.admin || window.admin).notify(res.error || 'Failed to remove sub-app route.', 'error');
            }
        } catch (e) {
            (this.admin || window.admin).notify('Error removing sub-app: ' + e.message, 'error');
        }
    }

    openInstallToDomainModal(preselectedDomain = null, preselectedAppName = '') {
        const { domains = [], domainAppsData } = this.state;
        const currentDomain = preselectedDomain || (this.state.selectedDomain !== 'all' ? this.state.selectedDomain : (domains[0]?.pattern || window.location.hostname || 'example.com'));
        
        const targetDom = domains.find(d => d.pattern === currentDomain) || domains[0] || {};
        const account = targetDom.account || 'default';
        const defaultAppPath = account !== 'default' ? `/home/${account}/apps/${preselectedAppName || 'app'}` : `domains/${currentDomain}/src/${preselectedAppName || 'app'}`;
        const defaultDataPath = account !== 'default' ? `/home/${account}/sppdata/${currentDomain}/${preselectedAppName || 'app'}` : `var/data/${currentDomain}/${preselectedAppName || 'app'}`;

        const modalHtml = html`
            <div class="form-grid" style="display: flex; flex-direction: column; gap: 14px; max-height: 72vh; overflow-y: auto; padding-right: 4px;">
                <!-- Domain Selection -->
                <div class="input-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;">Target Domain</label>
                    <select id="inst-domain" class="form-control" style="width: 100%; background: var(--bg-card-glass); border: 1px solid var(--glass-border); border-radius: 6px; padding: 8px 10px; color: var(--text-bright);">
                        ${domains.map(d => html`
                            <option value="${d.pattern}" ?selected="${d.pattern === currentDomain}">
                                🌐 ${d.pattern} (Account: ${d.account || 'default'})
                            </option>
                        `)}
                    </select>
                </div>

                <!-- Application Architecture / Type -->
                <div class="input-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Application Type</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: flex-start; gap: 8px; background: rgba(0,0,0,0.18); border: 1px solid var(--glass-border); border-radius: 8px; padding: 10px; cursor: pointer;">
                            <input type="radio" name="inst-app-type" value="spp" checked style="margin-top: 3px;">
                            <div>
                                <strong style="color: var(--text-bright); font-size: 0.85rem;">🚀 SPP Framework App</strong>
                                <div style="font-size: 0.72rem; color: var(--text-dim); margin-top: 2px;">Native SPPEntity, Controllers, Blade/Twig views &amp; events.</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 8px; background: rgba(0,0,0,0.18); border: 1px solid var(--glass-border); border-radius: 8px; padding: 10px; cursor: pointer;">
                            <input type="radio" name="inst-app-type" value="non_spp" style="margin-top: 3px;">
                            <div>
                                <strong style="color: var(--text-bright); font-size: 0.85rem;">🌐 Non-SPP / External App</strong>
                                <div style="font-size: 0.72rem; color: var(--text-dim); margin-top: 2px;">WordPress, Laravel, Node.js, Python, or Static HTML SPA.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Application Name -->
                <div class="input-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;">Application Name / Identifier</label>
                    <input type="text" id="inst-app-name" value="${preselectedAppName}" placeholder="e.g. blog, store, docs, portal" 
                        style="width: 100%; background: var(--bg-card-glass); border: 1px solid var(--glass-border); border-radius: 6px; padding: 8px 10px; color: var(--text-bright);">
                    <small style="color: var(--text-dim); font-size: 0.72rem;">Letters, numbers, hyphens, and underscores only.</small>
                </div>

                <!-- Application Directory Path (Zero restriction!) -->
                <div class="input-group">
                    <label style="font-weight: 600; display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span>Application Directory Path</span>
                        <span style="font-size: 0.7rem; color: #34d399; font-weight: 500;">Zero directory naming restriction</span>
                    </label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="inst-target-path" value="${preselectedAppName ? defaultAppPath : ''}" placeholder="${defaultAppPath}" 
                            style="flex: 1; font-family: monospace; background: var(--bg-card-glass); border: 1px solid var(--glass-border); border-radius: 6px; padding: 8px 10px; color: var(--text-bright);">
                        <button type="button" id="btn-probe-path" class="btn ghost-btn btn-sm" style="white-space: nowrap;">
                            🔍 Probe Path
                        </button>
                    </div>
                    <div id="probe-feedback" style="display: none; margin-top: 6px; padding: 8px 12px; border-radius: 6px; font-size: 0.75rem;"></div>
                    <small style="color: var(--text-dim); font-size: 0.72rem;">Absolute path (e.g. <code>/home/${account}/apps/myblog</code> or <code>/var/www/wordpress</code>) or relative to SPP workspace.</small>
                </div>

                <!-- Domain Route & Role -->
                <div class="input-group" style="background: rgba(0,0,0,0.15); border: 1px solid var(--glass-border); border-radius: 8px; padding: 12px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Domain Mount &amp; Role</label>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="inst-app-role" value="secondary" checked>
                            <span><strong>🔗 Secondary Application (Base Route)</strong></span>
                        </label>
                        <div id="role-route-wrap" style="margin-left: 24px;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="font-size: 0.85rem; color: var(--text-dim); font-family: monospace;">Route:</span>
                                <input type="text" id="inst-route" value="/${preselectedAppName || 'app'}" placeholder="/blog" 
                                    style="width: 160px; font-family: monospace; background: var(--bg-card-glass); border: 1px solid var(--glass-border); border-radius: 6px; padding: 5px 8px; color: var(--text-bright);">
                            </div>
                            <small style="color: var(--text-dim); font-size: 0.72rem; display: block; margin-top: 4px;">Visitors accessing this sub-path on the domain will be routed to this application.</small>
                        </div>

                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 4px;">
                            <input type="radio" name="inst-app-role" value="default">
                            <span><strong>⭐ Default Root Application (Served at /)</strong></span>
                        </label>
                        <div id="role-default-wrap" style="margin-left: 24px; display: none;">
                            <div style="font-size: 0.75rem; color: #fbbf24; margin-bottom: 4px;">
                                Move current default root app to sub-route (optional):
                            </div>
                            <input type="text" id="inst-demoted-route" placeholder="e.g. /old or /docs" 
                                style="width: 180px; font-family: monospace; background: var(--bg-card-glass); border: 1px solid var(--glass-border); border-radius: 6px; padding: 5px 8px; color: var(--text-bright);">
                        </div>
                    </div>
                </div>

                <!-- Data Directory Path -->
                <div class="input-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;">Data Directory Path</label>
                    <input type="text" id="inst-data-dir" value="${preselectedAppName ? defaultDataPath : ''}" placeholder="${defaultDataPath}" 
                        style="width: 100%; font-family: monospace; background: var(--bg-card-glass); border: 1px solid var(--glass-border); border-radius: 6px; padding: 8px 10px; color: var(--text-bright);">
                    <small style="color: var(--text-dim); font-size: 0.72rem;">Location for application logs, databases, cache, and uploads.</small>
                </div>

                <!-- Manifest & Scaffold Options -->
                <div style="display: flex; flex-direction: column; gap: 8px; background: rgba(0,0,0,0.12); padding: 10px 12px; border-radius: 6px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; cursor: pointer;">
                        <input type="checkbox" id="inst-mark-props" checked>
                        <span>Mark directory as application in <code>__spp_file_props.yml</code> (<code>is_app: true</code>)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; cursor: pointer;">
                        <input type="checkbox" id="inst-scaffold" checked>
                        <span>Scaffold boilerplate structure if directory is empty</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; cursor: pointer;">
                        <input type="checkbox" id="inst-opt-in-sppdata" checked>
                        <span>Enable sovereign SPP Data isolation</span>
                    </label>
                </div>
            </div>
        `;

        this.openModal(`Install Application to Domain`, modalHtml, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: '🚀 Install & Map to Domain', type: 'primary', fn: async () => {
                const domain = document.getElementById('inst-domain')?.value;
                const appName = document.getElementById('inst-app-name')?.value?.trim();
                const targetPath = document.getElementById('inst-target-path')?.value?.trim();
                const dataDir = document.getElementById('inst-data-dir')?.value?.trim();
                const markProps = document.getElementById('inst-mark-props')?.checked;
                const scaffold = document.getElementById('inst-scaffold')?.checked;
                const optInSppdata = document.getElementById('inst-opt-in-sppdata')?.checked;

                let appType = 'spp';
                for (const r of document.getElementsByName('inst-app-type')) {
                    if (r.checked) { appType = r.value; break; }
                }

                let isDefault = false;
                for (const r of document.getElementsByName('inst-app-role')) {
                    if (r.checked && r.value === 'default') { isDefault = true; break; }
                }

                const route = document.getElementById('inst-route')?.value?.trim();
                const demotedRoute = document.getElementById('inst-demoted-route')?.value?.trim() || null;

                if (!appName) {
                    alert('Please enter an Application Name.');
                    return;
                }

                try {
                    const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
                    const res = await apiCall('domains_install_app', {
                        domain: domain,
                        app_name: appName,
                        type: appType,
                        target_path: targetPath,
                        data_dir: dataDir,
                        is_default: isDefault ? 1 : 0,
                        route: route,
                        demoted_route: demotedRoute,
                        scaffold: scaffold ? 1 : 0,
                        opt_in_sppdata: optInSppdata ? 1 : 0
                    });

                    if (res.success) {
                        (this.admin || window.admin).notify(`🎉 Application '${appName}' mapped to ${domain} successfully!`, 'success');
                        this.closeModal();
                        this.setState({ selectedDomain: domain });
                        await this.loadData();
                        await this.loadDomainApps(domain);
                        this.update();
                    } else {
                        alert(res.error || 'Failed to install application to domain.');
                    }
                } catch (e) {
                    alert('Error: ' + e.message);
                }
            }}
        ]);

        // Post-render listeners
        setTimeout(() => {
            const probeBtn = document.getElementById('btn-probe-path');
            const targetPathInput = document.getElementById('inst-target-path');
            const feedbackDiv = document.getElementById('probe-feedback');
            const appNameInput = document.getElementById('inst-app-name');
            const routeInput = document.getElementById('inst-route');
            const domainSelect = document.getElementById('inst-domain');
            const roleRadios = document.getElementsByName('inst-app-role');
            const roleRouteWrap = document.getElementById('role-route-wrap');
            const roleDefaultWrap = document.getElementById('role-default-wrap');

            // Toggle role options
            for (const r of roleRadios) {
                r.addEventListener('change', () => {
                    const isDef = (r.value === 'default' && r.checked);
                    if (roleRouteWrap) roleRouteWrap.style.display = isDef ? 'none' : 'block';
                    if (roleDefaultWrap) roleDefaultWrap.style.display = isDef ? 'block' : 'none';
                });
            }

            // Probe path handler
            if (probeBtn && targetPathInput && feedbackDiv) {
                probeBtn.addEventListener('click', async () => {
                    const path = targetPathInput.value.trim() || targetPathInput.placeholder;
                    if (!path) {
                        feedbackDiv.style.display = 'block';
                        feedbackDiv.style.background = 'rgba(239, 68, 68, 0.15)';
                        feedbackDiv.style.color = '#f87171';
                        feedbackDiv.textContent = 'Please enter a directory path to probe.';
                        return;
                    }

                    feedbackDiv.style.display = 'block';
                    feedbackDiv.style.background = 'rgba(59, 130, 246, 0.15)';
                    feedbackDiv.style.color = '#93c5fd';
                    feedbackDiv.textContent = 'Probing directory path on server...';

                    try {
                        const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
                        const raw = await apiCall('domains_subapp_probe', { target_path: path });
                        const res = (raw && raw.data && typeof raw.data === 'object' && ('valid' in raw.data || 'framework' in raw.data || 'exists' in raw.data)) ? raw.data : (raw || {});
                        const exists = Boolean(res.exists || raw.exists || res.valid || raw.valid || (raw.success && res.framework));
                        if (exists) {
                            feedbackDiv.style.background = 'rgba(16, 185, 129, 0.15)';
                            feedbackDiv.style.color = '#34d399';
                            feedbackDiv.innerHTML = `✅ <strong>Directory Exists</strong> (${res.clean_path || path})<br>` +
                                `Detected Framework: <strong>${res.framework || 'Custom'}</strong> | Entry: <code>${res.index_file || res.suggested_index || 'index.php'}</code> | ` +
                                (res.has_props ? '📄 Has <code>__spp_file_props.yml</code>' : '📄 <code>__spp_file_props.yml</code> will be generated.');
                        } else {
                            feedbackDiv.style.background = 'rgba(59, 130, 246, 0.15)';
                            feedbackDiv.style.color = '#93c5fd';
                            feedbackDiv.innerHTML = `📁 <strong>Directory does not exist yet</strong>.<br>` +
                                `It will be created at <code>${res.clean_path || path}</code> along with <code>__spp_file_props.yml</code>.`;
                        }
                    } catch (err) {
                        feedbackDiv.style.background = 'rgba(239, 68, 68, 0.15)';
                        feedbackDiv.style.color = '#f87171';
                        feedbackDiv.textContent = 'Probe error: ' + err.message;
                    }
                });
            }

            // Sync app name to route and paths if not modified
            if (appNameInput) {
                appNameInput.addEventListener('input', () => {
                    const name = appNameInput.value.trim().toLowerCase().replace(/[^a-z0-9_\-]/g, '');
                    if (routeInput && (!routeInput.dataset.modified || routeInput.dataset.modified === 'false')) {
                        routeInput.value = '/' + name;
                    }
                });
            }
        }, 50);
    }

    // --- Tab: Modules ---
    renderModulesTable() {
        const { modules, modFilter, modGraphMode, modSearch } = this.state;
        let filtered = modules;
        
        if (modFilter !== 'all') {
            if (modFilter === 'core') filtered = modules.filter(m => m.type === 'system');
            else if (modFilter === 'app') filtered = modules.filter(m => m.type === 'user');
            else filtered = modules.filter(m => (m.module_category || (m.type === 'system' ? 'Core Optional' : 'App Modules')) === modFilter);
        }

        if (modSearch) {
            const term = modSearch.toLowerCase();
            filtered = filtered.filter(m => 
                (m.name || '').toLowerCase().includes(term) || 
                (m.public_name || '').toLowerCase().includes(term) || 
                (m.description || '').toLowerCase().includes(term)
            );
        }

        if (modGraphMode) {
            return this.renderModuleGraph(filtered);
        }

        const groups = {};
        filtered.forEach(mod => {
            const g = mod.module_category || (mod.type === 'system' ? 'Core Optional' : 'App Modules');
            if (!groups[g]) groups[g] = [];
            groups[g].push(mod);
        });

        const order = ['Core Required', 'Core Optional', 'App Modules'];
        const groupNames = Object.keys(groups).sort((a, b) => {
            const idxA = order.indexOf(a);
            const idxB = order.indexOf(b);
            if (idxA !== -1 && idxB !== -1) return idxA - idxB;
            if (idxA !== -1) return -1;
            if (idxB !== -1) return 1;
            return a.localeCompare(b);
        });

        if (filtered.length === 0) {
            return html`
                <div class="view-content-wrapper fade-in">
                    <div style="margin-bottom: 20px;">
                        <input type="text" 
                               placeholder="🔍 Search modules by name or description..." 
                               value="${modSearch || ''}" 
                               @input=${(e) => this.setState({modSearch: e.target.value})}
                               style="width: 100%; max-width: 400px; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text); font-size: 0.9rem;">
                    </div>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>No Modules found</h3>
                        <p>No modules match the current filter in this context.</p>
                    </div>
                </div>
            `;
        }

        return html`
            <div class="view-content-wrapper fade-in">
                <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <input type="text" 
                           placeholder="🔍 Search modules by name or description..." 
                           value="${modSearch || ''}" 
                           @input=${(e) => this.setState({modSearch: e.target.value})}
                           style="flex: 1; max-width: 400px; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text); font-size: 0.9rem;">
                    
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${this.renderHelpButton('modules')}
                        <button class="btn secondary-btn" @click=${() => this.app.installAllActiveModules()}>📦 Install All Active</button>
                    </div>
                </div>

                ${this.renderHelpBanner('modules', 'Modules Registry & Dependency Graph Guide',
                    'Audit, toggle, and compile SPP framework modules and optional plugins. View dependency relationships, run database schema migrations, and rebuild the module cache.',
                    {
                        steps: 'Filter by category (Core Optional, App Modules, etc.) or toggle between List View and Dependency Graph. Click "⚡ Compile" to synchronize the module autoload map and "🚀 Migrate" to run pending schema updates.',
                        concepts: 'Modules in SPP follow the Modularity 2.0 standard: declarative metadata in module.yml, database schemas in db.yml, zero-boot events in events.yml, and container integration via ServiceProviderInterface.',
                        proTip: 'Compulsory framework modules cannot be disabled. If adding a new module, run "php spp.php module:doctor <name>" from CLI to verify architectural compliance.'
                    }
                )}

                ${groupNames.map(groupName => {
                    const groupModules = groups[groupName];
                    return html`
                        <div class="module-group-header">
                            <h2>${groupName}</h2>
                            <span class="count-badge">${groupModules.length} Modules</span>
                        </div>
                        <div class="card-grid mb-4">
                            ${groupModules.map((mod, i) => {
                                const typeBadge = html`<span class="module-type-badge ${mod.type}">${mod.type === 'system' ? 'CORE' : 'APP'}</span>`;
                                return html`
                                    <div class="item-card ${mod.active ? 'active' : 'inactive-card'}" style="animation-delay: ${i * 0.05}s">
                                        <div class="card-header">
                                            <div>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <h3>${mod.public_name || mod.name}</h3>
                                                    ${typeBadge}
                                                </div>
                                                <div class="card-meta">${mod.author || 'Unknown'} · v${mod.version}</div>
                                            </div>
                                            <label class="toggle-switch">
                                                <input type="checkbox" ?checked="${mod.active}" 
                                                    @change=${(e) => this.toggleModule(mod.name, e.target.checked)}>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="module-card-body">
                                            ${mod.description ? html`<p class="module-description">${mod.description}</p>` : ''}
                                            <div class="module-deps">
                                                ${this.normalizeDependencies(mod.dependencies).map(d => html`<span class="dep-badge">${d}</span>`)}
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <small title="${mod.path}">${this.app.truncatePath ? this.app.truncatePath(mod.path, 40) : mod.path}</small>
                                            <div class="card-actions">
                                                <button type="button" class="btn primary-btn btn-sm" @click=${() => this.app.installModule(mod.name)}>📦 Install</button>
                                                <button type="button" class="btn danger-ghost-btn btn-sm" @click=${() => this.app.uninstallModule(mod.name)}>🗑️ Uninstall</button>
                                                ${mod.has_config ? html`<button type="button" class="btn ghost-btn btn-sm" @click=${() => this.app.openModuleSettings(mod.name, mod.public_name || mod.name)}>⚙️ Setup</button>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            })}
                        </div>
                    `;
                })}
            </div>
        `;
    }

    normalizeDependencies(deps) {
        if (!deps) return [];
        if (Array.isArray(deps)) return deps;
        if (typeof deps === 'object') {
            const result = [];
            for (const [key, val] of Object.entries(deps)) {
                if (Array.isArray(val)) {
                    val.forEach(v => {
                        if (v !== null && v !== undefined) result.push(typeof v === 'string' ? v : JSON.stringify(v));
                    });
                } else if (typeof val === 'string' || typeof val === 'number') {
                    result.push(key && isNaN(key) ? `${key} (${val})` : String(val));
                } else if (key && isNaN(key)) {
                    result.push(key);
                }
            }
            return result;
        }
        if (typeof deps === 'string') {
            return deps.split(',').map(s => s.trim()).filter(Boolean);
        }
        return [];
    }

    renderModuleGraph(modules) {
        const spacingX = 220;
        const spacingY = 150;
        let cols = 4;
        
        let nodes = [];
        let edgesHtml = [];
        
        // Find dependencies
        const positions = {};
        
        modules.forEach((mod, i) => {
            const x = (i % cols) * spacingX + 50;
            const y = Math.floor(i / cols) * spacingY + 50;
            positions[mod.name] = { x: x + 100, y: y + 40 }; // center approx
            
            nodes.push(html`
                <div class="spp-card ${mod.active ? 'border-primary' : 'border-muted'}" style="position: absolute; left: ${x}px; top: ${y}px; width: 200px; padding: 15px; z-index: 10; cursor: move; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <h5 style="margin: 0 0 5px 0;">${mod.name}</h5>
                    <span style="font-size: 0.7rem; color: var(--text-dim);">${mod.type === 'system' ? 'Core' : 'App'} Module</span>
                    ${!mod.active ? html`<div style="font-size: 0.7rem; color: var(--danger);">Inactive</div>` : ''}
                </div>
            `);
        });

        // Draw simple SVG lines for dependencies
        modules.forEach(mod => {
            const deps = this.normalizeDependencies(mod.dependencies);
            if (deps && deps.length > 0) {
                deps.forEach(dep => {
                    const cleanDep = String(dep).split(' ')[0].split('(')[0].trim();
                    if (positions[mod.name] && positions[cleanDep]) {
                        const from = positions[mod.name];
                        const to = positions[cleanDep];
                        edgesHtml.push(html`
                            <line x1="${from.x}" y1="${from.y}" x2="${to.x}" y2="${to.y}" stroke="var(--primary-color)" stroke-width="2" marker-end="url(#arrowhead)" opacity="0.6" stroke-dasharray="5,5" />
                        `);
                    }
                });
            }
        });

        return html`
            <div class="spp-card" style="position: relative; height: 600px; overflow: auto; background: var(--surface-1); margin-top: 20px;">
                <svg style="position: absolute; top: 0; left: 0; width: 2000px; height: 2000px; z-index: 1;">
                    <defs>
                        <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                            <polygon points="0 0, 10 3.5, 0 7" fill="var(--primary-color)" />
                        </marker>
                    </defs>
                    ${edgesHtml}
                </svg>
                ${nodes}
            </div>
        `;
    }

    async toggleModule(modname, active) {
        const newStatus = active ? 'active' : 'inactive';
        try {
            const res = await this.apiPost('toggle_module', { 
                modname, 
                status: newStatus,
                appname: this.app.selectedApp
            });
            if (res.success) {
                this.notify(res.message, 'success');
                const modules = this.state.modules.map(m => m.name === modname ? { ...m, active } : m);
                this.setState({ modules });
            } else {
                this.notify(res.message, 'error');
                this.update();
            }
        } catch (err) {
            this.notify('Error toggling module.', 'error');
            this.update();
        }
    }

    async rebuildRegistry() {
        this.notify('Rebuilding module registry cache...', 'info');
        try {
            const res = await this.apiPost('compile_registry');
            if (res.success) {
                this.notify(res.message, 'success');
                await this.loadData();
            } else {
                this.notify(res.message, 'error');
            }
        } catch (err) {
            this.notify('Compilation request failed.', 'error');
        }
    }

    async runMigrations() {
        this.notify('Running pending migrations...', 'info');
        try {
            const res = await this.apiPost('run_migrations');
            if (res.success) {
                this.notify(res.message, 'success');
                console.log('Migration Log:', res.data.log);
            } else {
                this.notify(res.message, 'error');
            }
        } catch (err) {
            this.notify('Migration request failed.', 'error');
        }
    }

    // --- Tab: App Builder (Scaffolding) ---
    renderAppBuilder() {
        const { scaffolding, scaffoldOutput, selectedScaffoldCmd } = this.state;
        const currentCmd = selectedScaffoldCmd || 'make:app';
        const cmdHelp = {
            'make:app': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--api</code>: Scaffolds a lightweight, API-only application without frontend views or UI routing.</li>
                        <li><code>--no-db</code>: Skips database configuration and connection bootstrapping.</li>
                        <li><code>--enterprise</code>: Enables Enterprise Mode (configures Redis for sessions and caching, enables strict typing).</li>
                    </ul>
                </div>
            `,
            'make:module': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--force</code>: Overwrites an existing module with the same name if one is already present. USE WITH CAUTION.</li>
                    </ul>
                </div>
            `,
            'make:entity': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--table=name</code>: Maps the entity to a specific custom table name instead of auto-deriving it.</li>
                        <li><code>--no-migration</code>: Creates the entity definition but skips pushing the schema changes to the database.</li>
                        <li><code>--force</code>: Overwrites existing entity definitions if they conflict.</li>
                    </ul>
                </div>
            `,
            'make:controller': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--resource</code>: Automatically generates standard CRUD methods (index, create, store, show, edit, update, destroy).</li>
                        <li><code>--api</code>: Excludes web views and restricts controller responses exclusively to JSON APIs.</li>
                    </ul>
                </div>
            `,
            'make:scaffold': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--api</code>: Scaffolds an API-only stack (Controller + Entity + DB schema), skipping any visual UI or views.</li>
                        <li><code>--force</code>: Forcefully overwrites any existing scaffold files that share the target name.</li>
                    </ul>
                </div>
            `,
            'make:service': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--singleton</code>: Registers the service as a shared singleton in the Dependency Injection container.</li>
                        <li><code>--force</code>: Overwrites the service class if it already exists.</li>
                    </ul>
                </div>
            `,
            'make:blade': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Creates a new Drishyam Blade view.</div>`,
            'make:twig': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Creates a new Drishyam Twig view.</div>`,
            'make:sppview': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Creates a new native AST-based SPPView.</div>`,
            'make:mixed-paradigm': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Scaffolds a Kitchen Sink example blending SPPView, Blade/Twig, and SPPUX.</div>`
        };

        return html`
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div class="header-main">
                        <h3 class="gradient-text">App Builder (CLI)</h3>
                        <span class="tag info-tag">spp.php CLI Interop</span>
                    </div>
                    <div>
                        ${this.renderHelpButton('builder')}
                    </div>
                </div>

                ${this.renderHelpBanner('builder', 'App Builder & Scaffolding Console Guide',
                    'Interactive CLI generator to synthesize new SPP applications, controllers, live components, workflows, and database entities with zero boilerplate.',
                    {
                        steps: 'Select an architecture template (MVC, Reactive LiveComponent, Microservice, or Multi-Language). Enter your app name and namespace, then click "Generate Application Scaffold" or type direct commands.',
                        concepts: 'Scaffolding adheres to SPP Golden Rules: strict CLI SAPI guarding (isCLIOnly()), zero inline HTML literals in PHP, and automatic workflow directory provisioning with YAML definitions.',
                        proTip: 'After generating an app, inspect its etc/workflows/ directory to configure state transitions, or run "php spp.php test:run <app>" to verify the generated test suite.'
                    }
                )}
                
                <div style="margin: 20px 20px 0 20px; display: flex; gap: 10px; align-items: center; background: var(--surface-2); padding: 15px; border-radius: 8px; border: 1px solid var(--primary-color);">
                    <span style="font-size: 1.2rem;">🤖</span>
                    <input type="text" id="ai-cli-prompt" placeholder="e.g. Create a new REST API application called billing and a Stripe module..." style="flex-grow: 1; padding: 10px; border-radius: 4px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text);">
                    <button type="button" class="btn primary-btn" @click=${() => this.runAiScaffold()} ?disabled="${scaffolding}">
                        ${scaffolding ? '⏳' : '✨ Generate & Execute'}
                    </button>
                </div>
                
                <div class="form-grid" style="padding: 20px; align-items: end;">
                    <div class="input-group">
                        <label>Command Type</label>
                        <select id="scaffold-command" @change=${(e) => this.setState({selectedScaffoldCmd: e.target.value})}>
                            <option value="make:app" ?selected=${currentCmd === 'make:app'}>make:app - New Application Context</option>
                            <option value="make:module" ?selected=${currentCmd === 'make:module'}>make:module - New SPP Module</option>
                            <option value="make:entity" ?selected=${currentCmd === 'make:entity'}>make:entity - Data Entity definition</option>
                            <option value="make:controller" ?selected=${currentCmd === 'make:controller'}>make:controller - Scaffold Controller</option>
                            <option value="make:scaffold" ?selected=${currentCmd === 'make:scaffold'}>make:scaffold - Full Entity/View Stack</option>
                            <option value="make:service" ?selected=${currentCmd === 'make:service'}>make:service - Dependency Injected Service</option>
                            <option value="make:blade" ?selected=${currentCmd === 'make:blade'}>make:blade - Scaffold Blade View</option>
                            <option value="make:twig" ?selected=${currentCmd === 'make:twig'}>make:twig - Scaffold Twig View</option>
                            <option value="make:sppview" ?selected=${currentCmd === 'make:sppview'}>make:sppview - Scaffold Native AST View</option>
                            <option value="make:mixed-paradigm" ?selected=${currentCmd === 'make:mixed-paradigm'}>make:mixed-paradigm - Scaffold Kitchen Sink View</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Target Name</label>
                        <input type="text" id="scaffold-name" placeholder="e.g. AuthModule or Product">
                    </div>
                    ${currentCmd === 'make:app' ? html`
                    <div class="input-group">
                        <label>App Type</label>
                        <select id="scaffold-apptype">
                            <option value="native">native (Default)</option>
                            <option value="blade">blade (Laravel Style)</option>
                            <option value="react">react (React frontend)</option>
                            <option value="vue">vue (Vue frontend)</option>
                            <option value="drupal">drupal (Drupal bridge)</option>
                            <option value="sppux">sppux (Native UI)</option>
                            <option value="dropin">dropin</option>
                        </select>
                    </div>
                    ` : ''}
                    <div class="input-group full-width" style="grid-column: 1 / -1;">
                        <label>Command Options / Flags <span class="help-tag" title="Command line flags and options">(?)</span></label>
                        <input type="text" id="scaffold-options" placeholder="--force --api">
                        <div class="help-text" style="font-size: 0.8rem; color: var(--text-dim); margin-top: 5px;">
                            💡 ${cmdHelp[currentCmd]}
                        </div>
                    </div>
                    <div class="input-group full-width" style="display: flex; align-items: flex-end; justify-content: flex-end; grid-column: 1 / -1;">
                        <button class="btn primary-btn shine-effect" ?disabled="${scaffolding}" @click=${() => this.runScaffold()}>
                            ${scaffolding ? '⏳ Processing...' : '🚀 Execute Scaffold'}
                        </button>
                    </div>
                </div>

                <div class="code-terminal" style="margin: 0 20px 20px 20px; background: #0f111a; border-radius: 8px; padding: 15px; min-height: 200px; max-height: 400px; overflow-y: auto; font-family: monospace; color: #a6accd; border: 1px solid rgba(255,255,255,0.1);">
                    ${scaffoldOutput ? html`<pre style="margin:0; white-space: pre-wrap;">${scaffoldOutput}</pre>` : html`<div style="opacity:0.5; font-style:italic;">Terminal Output...</div>`}
                </div>
            </div>
        `;
    }

    async runAiScaffold() {
        const prompt = document.getElementById('ai-cli-prompt')?.value;
        if (!prompt) return this.notify('Please enter a natural language prompt.', 'error');
        
        this.setState({ scaffolding: true, scaffoldOutput: '🤖 Analyzing prompt and building command sequence...\n' });
        
        try {
            const res = await this.apiPost('ai_parse_scaffold', { prompt });
            if (res.success && res.data && res.data.commands) {
                let output = this.state.scaffoldOutput;
                for (const cmdObj of res.data.commands) {
                    output += `\n> Executing: php spp.php ${cmdObj.cmd} ${cmdObj.target} ${cmdObj.opts}\n`;
                    this.setState({ scaffoldOutput: output });
                    
                    const cmdRes = await this.apiPost('execute_scaffold', { 
                        command: cmdObj.cmd, 
                        target: cmdObj.target, 
                        options: cmdObj.opts 
                    });
                    
                    output += (cmdRes.data?.output || cmdRes.message) + "\n";
                    this.setState({ scaffoldOutput: output });
                }
                this.notify('AI Scaffold completed.', 'success');
                this.loadData();
            } else {
                this.notify(res.message || 'Failed to parse prompt', 'error');
                this.setState({ scaffoldOutput: this.state.scaffoldOutput + '\n' + (res.message || 'Parse error') });
            }
        } catch (e) {
            this.setState({ scaffoldOutput: this.state.scaffoldOutput + `\nNetwork Error: ${e.message}` });
        } finally {
            this.setState({ scaffolding: false });
        }
    }

    async runScaffold() {
        const cmd = document.getElementById('scaffold-command').value;
        const target = document.getElementById('scaffold-name').value.trim();
        let opts = document.getElementById('scaffold-options').value.trim();
        
        if (cmd === 'make:app') {
            const appType = document.getElementById('scaffold-apptype')?.value;
            if (appType) {
                opts = `${appType} ${opts}`.trim();
            }
        }
        
        if (!target) {
            return this.notify('Please provide a target name.', 'error');
        }

        this.setState({ scaffolding: true, scaffoldOutput: `Executing: php spp.php ${cmd} ${target} ...\n` });
        
        try {
            const res = await this.apiPost('execute_scaffold', { command: cmd, target: target, options: opts });
            this.setState({ 
                scaffolding: false, 
                scaffoldOutput: this.state.scaffoldOutput + (res.data?.output || res.message) 
            });
            if (res.success) {
                this.notify('Scaffold completed successfully.', 'success');
                this.loadData(); // refresh modules/apps list
            } else {
                this.notify('Scaffold execution returned an error.', 'error');
            }
        } catch (e) {
            this.setState({ scaffolding: false, scaffoldOutput: this.state.scaffoldOutput + `\nNetwork/Execution Error: ${e.message}` });
        }
    }


    // --- Tab: Shared Groups ---
    renderGroupsTable() {
        const { sharedGroups } = this.state;
        const groupEntries = Object.entries(sharedGroups);

        return html`
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div class="header-main" style="display: flex; align-items: center; gap: 12px;">
                        <h3 class="gradient-text" style="margin: 0;">Shared Resource Groups</h3>
                        <button class="btn primary-btn btn-sm" @click=${() => this.openGroupEditor()}>+ New Group</button>
                    </div>
                    <div>
                        ${this.renderHelpButton('groups')}
                    </div>
                </div>

                ${this.renderHelpBanner('groups', 'Shared Resource Groups & Symlinks Guide',
                    'Manage shared libraries, vendor dependencies, and asset symlinks cross-referenced between applications to eliminate redundancy and save disk space.',
                    {
                        steps: 'Review existing shared clusters. Click "Create Shared Group" to bundle shared resources (like uploads, common UI kits, or shared modules) across multiple app namespaces.',
                        concepts: 'Shared groups leverage PathResolver to create canonical junctions or unified read-only lookups across application boundaries while maintaining directory isolation.',
                        proTip: 'Never include write-sensitive cache or session directories in shared groups. Keep sessions strictly per-domain or in dedicated Redis/SQLite session stores.'
                    }
                )}

                <div class="table-container">
                    <table class="data-table elegant-table">
                        <thead>
                            <tr>
                                <th style="width: 200px;">Group Name</th>
                                <th style="width: 180px;">Inheritance</th>
                                <th style="width: 120px;">Table Prefix</th>
                                <th>Shared Entities</th>
                                <th class="actions-head" style="width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${groupEntries.map(([name, group]) => html`
                                <tr>
                                    <td><div class="entity-name">${name}</div></td>
                                    <td>
                                        ${group.extends ? html`<span class="tag link-tag primary">Inherits: ${group.extends}</span>` : html`<span class="tag muted-tag">Root Group</span>`}
                                    </td>
                                    <td><code class="code-badge success">${group.table_prefix || ''}</code></td>
                                    <td>
                                        <div class="entity-list-chipset">
                                            ${(group.entities || []).map(ent => html`<span class="chip">${ent}</span>`)}
                                        </div>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="btn-group-horizontal">
                                            <button class="btn ghost-btn btn-sm" @click=${() => this.openGroupEditor(name)}>✏️ Edit</button>
                                            <button class="btn danger-ghost-btn btn-sm" @click=${() => this.deleteGroup(name)}>🗑️ Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            `)}
                            ${groupEntries.length === 0 ? html`<tr><td colspan="5" class="empty-row">No shared groups defined.</td></tr>` : ''}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // --- Dialogs and App Configurations ---
    async manageApp(appName) {
        window.admin.onAppContextChange(appName);
        const app = this.state.apps.find(a => a.name === appName);
        location.hash = (app && app.has_admin) ? appName : 'manage';
        this.notify(`Switched to ${appName} Management`, 'success');
    }

    async setBaseApp(appName) {
        try {
            const formData = new FormData();
            formData.append('action', 'set_base_app');
            formData.append('target_app', appName);
            
            const res = await this.apiPost(formData);
            if (res.success) {
                this.notify(`Base application set to "${appName}".`, 'success');
                await this.loadData();
            } else {
                this.notify(res.message || 'Operation failed', 'error');
            }
        } catch (err) {
            this.notify(`Error changing base app: ${err.message}`, 'error');
        }
    }

    async openAppEditor(appName) {
        const app = this.state.apps.find(a => a.name === appName);
        if (!app) return;

        this.openModal(`Configure: ${appName}`, html`
            <div class="form-grid">
                <div class="input-group">
                    <label>Base URL Prefix <span class="help-tag" title="The URL path where this application is mounted (e.g., /admin). Leave empty for the root domain.">(?)</span></label>
                    <input type="text" id="app-base-url" value="${app.base_url || ''}" placeholder="/myapp">
                </div>
                <div class="input-group">
                    <label>Table Name Prefix <span class="help-tag" title="Prefixes all database tables for this app to prevent collisions (e.g., app1_users, app2_users).">(?)</span></label>
                    <input type="text" id="app-table-prefix" value="${app.table_prefix || ''}" placeholder="prefix_">
                </div>
                <div class="input-group">
                    <label>Shared Resource Group</label>
                    <select id="app-shared-group">
                        <option value="">None (Isolated)</option>
                        ${Object.keys(this.state.sharedGroups).map(g => html`<option value="${g}" ?selected="${app.shared_group === g}">${g}</option>`)}
                    </select>
                </div>
                <div class="input-group">
                    <label>Custom Etc Directory (Optional)</label>
                    <input type="text" id="app-etc-path" value="${app.etc_path || ''}" placeholder="/etc or relative/to/src">
                </div>
                <div class="input-group">
                    <label>Custom Src Directory (Optional)</label>
                    <input type="text" id="app-src-path" value="${app.src_path || ''}" placeholder="/src/custom">
                </div>
                <div class="input-group full-width" style="grid-column: 1 / -1;">
                    <label>Advanced Options (YAML) <span class="help-tag" title="Custom configuration options for this application. Available options:\n- cache_enabled: true/false\n- auth_provider: local/sso\n- custom_theme: dark/light\n- timezone: UTC\nFormat must be standard YAML.">(?)</span></label>
                    <textarea id="app-options" rows="3" placeholder="cache_enabled: true\nauth_provider: local">${app.options_yaml || ''}</textarea>
                </div>
            </div>
            
            <details class="advanced-settings mt-4">
                <summary class="text-dim" style="cursor:pointer">Database Override (Optional)</summary>
                <div class="form-grid" style="padding-top: 10px;">
                    <div class="input-group"><label>DB Host</label><input type="text" id="app-dbhost" value="${app.db_config?.dbhost || ''}"></div>
                    <div class="input-group"><label>DB Name</label><input type="text" id="app-dbname" value="${app.db_config?.dbname || ''}"></div>
                    <div class="input-group"><label>DB User</label><input type="text" id="app-dbuser" value="${app.db_config?.dbuser || ''}"></div>
                    <div class="input-group"><label>DB Password</label><input type="password" id="app-dbpasswd" value="${app.db_config?.dbpasswd || ''}"></div>
                </div>
            </details>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Save Changes', type: 'primary', fn: async () => {
                const config = {
                    base_url: document.getElementById('app-base-url').value.trim(),
                    table_prefix: document.getElementById('app-table-prefix').value.trim(),
                    shared_group: document.getElementById('app-shared-group').value,
                    etc_path: document.getElementById('app-etc-path').value.trim(),
                    src_path: document.getElementById('app-src-path').value.trim(),
                    options_yaml: document.getElementById('app-options').value.trim()
                };
                const dbname = document.getElementById('app-dbname').value.trim();
                if (dbname) {
                    config.db_config = {
                        dbhost: document.getElementById('app-dbhost').value.trim() || 'localhost',
                        dbname: dbname,
                        dbuser: document.getElementById('app-dbuser').value.trim(),
                        dbpasswd: document.getElementById('app-dbpasswd').value
                    };
                }
                const fd = new FormData();
                fd.append('target_app', appName);
                fd.append('config', JSON.stringify(config));

                const res = await this.api.saveAppConfig(fd);
                if (res.success) {
                    this.notify('Application configuration updated.', 'success');
                    this.closeModal();
                    await this.loadData();
                } else this.notify(res.message || 'Update failed', 'error');
            }}
        ]);
    }

    showAppInfo(app) {
        this.openModal(`Application Details: ${app.name}`, html`
            <div class="app-info-modal">
                <div class="info-section">
                    <h4>Overview</h4>
                    <div class="info-grid">
                        <div class="info-item"><strong>Name:</strong> <span>${app.name}</span></div>
                        <div class="info-item"><strong>Type:</strong> <span>${app.is_base_app ? 'Primary / Base Application' : 'Standard Module'}</span></div>
                        <div class="info-item"><strong>Base URL:</strong> <code class="code-badge">${app.base_url || '/'}</code></div>
                        <div class="info-item"><strong>Table Prefix:</strong> <code class="code-badge warning">${app.table_prefix || '(none)'}</code></div>
                    </div>
                </div>
                <div class="info-section mt-4">
                    <h4>System Paths</h4>
                    <div class="path-card glass-panel p-3">
                        <div class="path-row mb-2"><strong>ETC:</strong> <code class="text-xs">${app.etc_path}</code></div>
                        <div class="path-row"><strong>SRC:</strong> <code class="text-xs">${app.src_path}</code></div>
                    </div>
                </div>
            </div>
            <style>
                .app-info-modal h4 { color: var(--primary-color); margin-bottom: 10px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: rgba(0,0,0,0.05); padding: 15px; border-radius: 8px; }
                .info-item { display: flex; flex-direction: column; gap: 4px; }
                .info-item strong { font-size: 0.75rem; color: var(--text-dim); }
                .path-card code { display: block; word-break: break-all; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; }
            </style>
        `, [
            { label: 'Configure', type: 'ghost', fn: () => { this.closeModal(); this.openAppEditor(app.name); } },
            { label: 'Close', type: 'secondary', fn: () => this.closeModal() }
        ]);
    }

    async openGroupEditor(groupName = null) {
        const group = groupName ? this.state.sharedGroups[groupName] : { extends: '', table_prefix: '', entities: [] };
        
        this.openModal(groupName ? `Edit Group: ${groupName}` : 'New Shared Group', html`
            <div class="form-grid">
                <div class="input-group">
                    <label>Group Name</label>
                    <input type="text" id="group-name" value="${groupName || ''}" ?disabled="${!!groupName}">
                </div>
                <div class="input-group">
                    <label>Inherits From <span class="help-tag" title="Inherit tables and code from an existing base group to extend its functionality.">(?)</span></label>
                    <select id="group-extends">
                        <option value="">None</option>
                        ${Object.keys(this.state.sharedGroups).filter(g => g !== groupName).map(g => html`<option value="${g}" ?selected="${group.extends === g}">${g}</option>`)}
                    </select>
                </div>
                <div class="input-group">
                    <label>Group Table Prefix <span class="help-tag" title="A shared prefix for all entities within this group. Ensures isolation from other groups.">(?)</span></label>
                    <input type="text" id="group-prefix" value="${group.table_prefix || ''}">
                </div>
                <div class="input-group full-width">
                    <label>Shared Entities (Comma separated names)</label>
                    <textarea id="group-entities" rows="3">${(group.entities || []).join(', ')}</textarea>
                </div>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Save Group', type: 'primary', fn: async () => {
                const name = document.getElementById('group-name').value.trim();
                if (!name) return this.notify('Group name is required.', 'error');

                const updatedGroups = { ...this.state.sharedGroups };
                updatedGroups[name] = {
                    extends: document.getElementById('group-extends').value,
                    table_prefix: document.getElementById('group-prefix').value.trim(),
                    entities: document.getElementById('group-entities').value.split(',').map(s => s.trim()).filter(s => s)
                };

                const fullSettings = await this.api.getGlobalSettings();
                if (fullSettings.success) {
                    const settings = fullSettings.data;
                    settings.shared_groups = updatedGroups;
                    const fd = new FormData();
                    fd.append('settings', JSON.stringify(settings));

                    const res = await this.api.saveGlobalSettings(fd);
                    if (res.success) {
                        this.notify('Shared group saved.', 'success');
                        this.closeModal();
                        await this.loadData();
                    } else this.notify(res.message || 'Save failed', 'error');
                }
            }}
        ]);
    }

    async deleteGroup(name) {
        if (!confirm(`Delete shared group "${name}"?`)) return;
        const updatedGroups = { ...this.state.sharedGroups };
        delete updatedGroups[name];

        const fullSettings = await this.api.getGlobalSettings();
        if (fullSettings.success) {
            const settings = fullSettings.data;
            settings.shared_groups = updatedGroups;
            const fd = new FormData();
            fd.append('settings', JSON.stringify(settings));

            const res = await this.api.saveGlobalSettings(fd);
            if (res.success) {
                this.notify('Shared group deleted.', 'success');
                await this.loadData();
            } else this.notify(res.message || 'Delete failed', 'error');
        }
    }

    async cloneApp(appName) {
        const newName = prompt(`Enter new application name for cloned ${appName}:`);
        if (!newName) return;
        
        SPPUX.notify(`Cloning ${appName} to ${newName}...`, 'info');
        const res = await this.apiPost('clone_app', { source: appName, target: newName });
        if (res.success) {
            SPPUX.notify('App cloned successfully!', 'success');
            this.loadData();
        } else {
            SPPUX.notify(res.message || 'Clone failed', 'error');
        }
    }

    openTemplateHub() {
        this.openModal('SPP Template Hub', html`
            <div class="card-grid mb-4">
                <div class="item-card active p-3 text-center" style="cursor: pointer;" @click=${() => this.scaffoldTemplate('saas')}>
                    <h4>🏢 SaaS Starter</h4>
                    <p class="text-xs text-muted">Billing, Tenancy, Auth</p>
                </div>
                <div class="item-card active p-3 text-center" style="cursor: pointer;" @click=${() => this.scaffoldTemplate('crm')}>
                    <h4>👥 CRM Base</h4>
                    <p class="text-xs text-muted">Leads, Contacts, Notes</p>
                </div>
                <div class="item-card active p-3 text-center" style="cursor: pointer;" @click=${() => this.scaffoldTemplate('blog')}>
                    <h4>📝 Blog/CMS</h4>
                    <p class="text-xs text-muted">Posts, Categories, SEO</p>
                </div>
            </div>
        `, [{ label: 'Close', type: 'secondary', fn: () => this.closeModal() }]);
    }

    async scaffoldTemplate(tplName) {
        SPPUX.notify(`Scaffolding ${tplName} template...`, 'info');
        const res = await this.apiPost('scaffold_template', { template: tplName });
        if (res.success) {
            SPPUX.notify('Template generated successfully!', 'success');
            this.closeModal();
            this.loadData();
        } else {
            SPPUX.notify(res.message || 'Template failed', 'error');
        }
    }

    openDomainScopeModal(app) {
        const allowedStr = (app.allowed_domains || []).join(', ');
        this.openModal(`Configure Domain Scope: ${app.name}`, html`
            <div class="form-grid">
                <div class="input-group full-width" style="grid-column: 1 / -1; margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px;">Domain Scope Mode</label>
                    <div style="display: flex; gap: 20px; align-items: center; background: rgba(0,0,0,0.15); padding: 12px; border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="app-scope-mode" value="global" ?checked="${!app.domain_only && !app.is_domain_app}">
                            <span>🌍 Global (Accessible across all domains)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="app-scope-mode" value="domain_only" ?checked="${app.domain_only || app.is_domain_app}">
                            <span>🔒 Domain-Only (Restricted to specific domains)</span>
                        </label>
                    </div>
                </div>

                <div class="input-group full-width" style="grid-column: 1 / -1;">
                    <label>Allowed Domains (Comma-Separated)</label>
                    <input type="text" id="app-allowed-domains" value="${allowedStr}" placeholder="e.g. portal.school.edu, students.school.edu">
                    <small style="color: var(--text-dim); margin-top: 4px; display: block;">If Domain-Only is selected, requests to this application will only be permitted on the listed virtual host domains.</small>
                </div>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Save Domain Scope', type: 'primary', fn: async () => {
                const radios = document.getElementsByName('app-scope-mode');
                let scopeMode = 'global';
                for (const r of radios) {
                    if (r.checked) { scopeMode = r.value; break; }
                }
                const isDomainOnly = (scopeMode === 'domain_only');
                const rawAllowed = document.getElementById('app-allowed-domains').value;
                const allowedDomains = rawAllowed.split(',').map(s => s.trim()).filter(Boolean);

                try {
                    const res = await (this.admin || this.app).api('apps_set_domain_scope', {
                        app_name: app.name,
                        domain_only: isDomainOnly ? 1 : 0,
                        allowed_domains: allowedDomains
                    });
                    if (res.success) {
                        this.notify(res.message || 'Domain scope updated successfully.', 'success');
                        this.closeModal();
                        await this.loadData();
                    } else {
                        this.notify(res.error || res.message || 'Failed to update domain scope.', 'error');
                    }
                } catch (e) {
                    this.notify('Error: ' + e.message, 'error');
                }
            }}
        ]);
    }

    openUninstallModal(app) {
        if (['default', 'admin', 'spp', 'core', 'system'].includes((app.name || '').toLowerCase())) {
            this.notify(`Cannot uninstall protected system application '${app.name}'.`, 'error');
            return;
        }

        this.openModal(`Uninstall / Decommission: ${app.name}`, html`
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 8px; padding: 14px; display: flex; gap: 12px; align-items: flex-start;">
                    <div style="font-size: 1.6rem; line-height: 1;">⚠️</div>
                    <div style="font-size: 0.85rem; color: var(--text-bright); line-height: 1.4;">
                        You are about to decommission <strong>${app.name}</strong> (${app.title || app.name}).
                        Please select your removal preferences below.
                    </div>
                </div>

                <div>
                    <label style="display: block; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 8px;">Removal Mode</label>
                    <div style="display: flex; flex-direction: column; gap: 10px; background: rgba(0,0,0,0.15); border: 1px solid var(--glass-border); border-radius: 8px; padding: 14px;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                            <input type="radio" name="uninstall-mode" value="config" checked style="margin-top: 3px;">
                            <div>
                                <strong style="color: var(--text-bright); font-size: 0.9rem;">Config-Only Removal (Recommended)</strong>
                                <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;">
                                    Unregisters the application from <code>global-settings.yml</code>, unbinds all virtual hosts in <code>etc/domains.yml</code>, and clears routing caches. The source code in <code>src/${app.name}</code> is completely preserved.
                                </div>
                            </div>
                        </label>
                        <div style="height: 1px; background: var(--glass-border);"></div>
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                            <input type="radio" name="uninstall-mode" value="full" style="margin-top: 3px;">
                            <div>
                                <strong style="color: var(--danger); font-size: 0.9rem;">Full Removal (Purge Everything)</strong>
                                <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;">
                                    Completely deletes application source code (<code>src/${app.name}</code>), configuration directories (<code>etc/apps/${app.name}</code>), caches, and domain mappings.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 8px;">Safety &amp; Data Options</label>
                    <div style="display: flex; flex-direction: column; gap: 10px; background: rgba(0,0,0,0.15); border: 1px solid var(--glass-border); border-radius: 8px; padding: 14px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="uninstall-create-backup" checked>
                            <span style="font-size: 0.85rem; color: var(--text-bright);">
                                📦 <strong>Create Safety Backup Archive</strong> in <code>var/backups/apps/</code> before removal (allows 1-click restore later)
                            </span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="uninstall-purge-db">
                            <span style="font-size: 0.85rem; color: var(--text-bright);">
                                🗄️ <strong>Purge SQLite Database</strong> (<code>var/db/${app.name}.sqlite</code>) if present
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Confirm Uninstallation', type: 'danger', fn: async () => {
                const radios = document.getElementsByName('uninstall-mode');
                let mode = 'config';
                for (const r of radios) {
                    if (r.checked) { mode = r.value; break; }
                }
                const createBackup = document.getElementById('uninstall-create-backup').checked;
                const purgeDb = document.getElementById('uninstall-purge-db').checked;

                this.notify(`Decommissioning application '${app.name}'...`, 'info');
                this.closeModal();

                if (mode === 'full') {
                    // Instantly remove from list on full uninstall
                    this.setState({ apps: (this.state.apps || []).filter(a => a.name !== app.name) });
                }

                try {
                    const res = await (this.admin || this.app).api('apps_uninstall', {
                        app: app.name,
                        mode: mode,
                        config_only: (mode === 'config') ? 1 : 0,
                        create_backup: createBackup ? 1 : 0,
                        purge_db: purgeDb ? 1 : 0
                    });

                    if (res.success) {
                        this.notify(res.message || `Application '${app.name}' decommissioned successfully.`, 'success');
                        await this.loadData();
                        if (this.state.activeTab === 'backups') {
                            await this.loadBackups();
                        }
                    } else {
                        this.notify(res.error || res.message || 'Uninstallation failed.', 'error');
                        await this.loadData();
                    }
                } catch (e) {
                    this.notify('Error: ' + e.message, 'error');
                    await this.loadData();
                }
            }}
        ]);
    }

    async reinstallApp(appName) {
        this.notify(`Re-installing and activating application '${appName}'...`, 'info');
        try {
            const res = await (this.admin || this.app).api('apps_reinstall', { app: appName });
            if (res.success) {
                this.notify(res.message || `Application '${appName}' re-installed successfully!`, 'success');
                await this.loadData();
            } else {
                this.notify(res.error || res.message || 'Re-installation failed.', 'error');
            }
        } catch (e) {
            this.notify(`Re-installation failed: ${e.message}`, 'error');
        }
    }

    purgeDormantCode(appName) {
        this.openModal(`Purge Source Code: ${appName}`, html`
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 8px; padding: 14px; display: flex; gap: 12px; align-items: flex-start;">
                    <div style="font-size: 1.6rem; line-height: 1;">⚠️</div>
                    <div style="font-size: 0.85rem; color: var(--text-bright); line-height: 1.4;">
                        Are you sure you want to permanently delete all remaining source code for <strong>${appName}</strong> (<code>src/${appName}</code>)?<br>
                        This will delete the directory from disk and cannot be undone.
                    </div>
                </div>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Permanently Purge', type: 'danger', fn: async () => {
                this.closeModal();
                this.notify(`Purging source code for '${appName}'...`, 'info');
                // Instantly remove from list
                this.setState({ apps: (this.state.apps || []).filter(a => a.name !== appName) });
                try {
                    const res = await (this.admin || this.app).api('apps_uninstall', {
                        app: appName,
                        mode: 'full',
                        config_only: 0
                    });
                    if (res.success) {
                        this.notify(res.message || `Application '${appName}' source code purged.`, 'success');
                    } else {
                        this.notify(res.error || res.message || 'Purge failed.', 'error');
                    }
                    await this.loadData();
                } catch (e) {
                    this.notify(`Purge failed: ${e.message}`, 'error');
                    await this.loadData();
                }
            }}
        ]);
    }

    async loadBackups() {
        this.setState({ loadingBackups: true });
        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            let res = await apiCall('backup_vault_list').catch(() => null);
            if (!res || !res.success) {
                res = await apiCall('apps_list_backups');
            }
            if (res && res.success) {
                const data = res.data || res;
                this.setState({
                    backups: data.backups || [],
                    backupInventory: data.inventory || data.telemetry || null,
                    vaultTelemetry: data.telemetry || null,
                    loadingBackups: false
                });
            } else {
                this.setState({ loadingBackups: false });
                this.notify((res && res.error) || 'Failed to list backup vault archives.', 'error');
            }
        } catch (e) {
            this.setState({ loadingBackups: false });
            this.notify('Error loading backups: ' + e.message, 'error');
        }
    }

    renderBackupsTable() {
        const { backups = [], loadingBackups, vaultTelemetry, backupInventory, backupFilter = 'all', backupSearchTerm = '' } = this.state;
        const telemetry = vaultTelemetry || backupInventory || {};

        // Calculate Overview KPIs
        const fullBackups = backups.filter(b => b.scope === 'full_platform' || b.scope === 'full');
        const domainBackups = backups.filter(b => b.scope === 'domain');
        const appBackups = backups.filter(b => b.scope === 'app' || b.scope === 'application');
        const dbBackups = backups.filter(b => b.scope === 'database' || b.has_db);

        const lastFull = fullBackups.length > 0 ? (fullBackups[0].created_at_formatted || fullBackups[0].created_at) : 'Never';
        const lastDomain = domainBackups.length > 0 ? (domainBackups[0].created_at_formatted || domainBackups[0].created_at) : 'Never';
        const lastApp = appBackups.length > 0 ? (appBackups[0].created_at_formatted || appBackups[0].created_at) : 'Never';

        // Filter archives
        let displayBackups = backups;
        if (backupFilter !== 'all') {
            displayBackups = displayBackups.filter(b => {
                if (backupFilter === 'full') return b.scope === 'full_platform' || b.scope === 'full';
                if (backupFilter === 'domain') return b.scope === 'domain';
                if (backupFilter === 'app') return b.scope === 'app' || b.scope === 'application';
                if (backupFilter === 'database') return b.scope === 'database' || b.has_db;
                return true;
            });
        }
        if (backupSearchTerm) {
            const s = backupSearchTerm.toLowerCase().trim();
            displayBackups = displayBackups.filter(b => 
                (b.filename || '').toLowerCase().includes(s) ||
                (b.target || '').toLowerCase().includes(s) ||
                (b.app_name || '').toLowerCase().includes(s) ||
                (b.backup_id || '').toLowerCase().includes(s)
            );
        }

        return html`
            <div class="glass-panel registry-panel fade-in">
                <!-- Overview Dashboard KPI Metrics Bar (Section 7.4.1) -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 1.5rem;">
                    <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(99,102,241,0.3); border-radius: 10px; padding: 12px 14px; border-left: 3px solid #6366f1;">
                        <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Last Full Platform Backup</div>
                        <div style="font-size: 1.15rem; font-weight: 700; color: #818cf8; margin-top: 3px;">${lastFull}</div>
                        <small style="font-size: 0.72rem; color: var(--text-dim);">${fullBackups.length} snapshots total</small>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(56,189,248,0.3); border-radius: 10px; padding: 12px 14px; border-left: 3px solid #38bdf8;">
                        <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Last Domain Backup</div>
                        <div style="font-size: 1.15rem; font-weight: 700; color: #38bdf8; margin-top: 3px;">${lastDomain}</div>
                        <small style="font-size: 0.72rem; color: var(--text-dim);">${domainBackups.length} domain archives</small>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(168,85,247,0.3); border-radius: 10px; padding: 12px 14px; border-left: 3px solid #a855f7;">
                        <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Last Application Backup</div>
                        <div style="font-size: 1.15rem; font-weight: 700; color: #c084fc; margin-top: 3px;">${lastApp}</div>
                        <small style="font-size: 0.72rem; color: var(--text-dim);">${appBackups.length} app snapshots</small>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(52,211,153,0.3); border-radius: 10px; padding: 12px 14px; border-left: 3px solid #34d399;">
                        <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Vault Storage Consumed</div>
                        <div style="font-size: 1.15rem; font-weight: 700; color: #34d399; margin-top: 3px;">
                            ${telemetry.total_size || telemetry.total_size_formatted || '0 B'}
                        </div>
                        <small style="font-size: 0.72rem; color: var(--text-dim);">Free Space: ${telemetry.free_space_str || 'Available'}</small>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(245,158,11,0.3); border-radius: 10px; padding: 12px 14px; border-left: 3px solid #f59e0b;">
                        <div style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Vault Security &amp; Health</div>
                        <div style="font-size: 1.15rem; font-weight: 700; color: #fbbf24; margin-top: 3px;">AES-256-GCM</div>
                        <small style="font-size: 0.72rem; color: var(--text-dim);">SHA-256 Validated</small>
                    </div>
                </div>

                ${this.renderHelpBanner('backups', 'Unified Backup Vault & Disaster Recovery Guide',
                    'Enterprise backup and disaster recovery engine supporting full platform, domain, application, database, and configuration snapshot scopes.',
                    {
                        steps: 'Click "New Backup Snapshot" to create an instant backup with optional AES-256-GCM encryption. Click "Restore" on any archive to launch the Guided Restore Wizard, supporting Dry-Run simulations and Target Path Remapping.',
                        concepts: 'Backups are self-contained archives containing portable SQLite/SQL dumps, filesystem manifests (manifest.json and manifest.yaml), and SHA-256 checksums. Restoration features ZipSlip protection and creates pre-restore recovery point snapshots.',
                        proTip: 'Before migrating to a new server or topology (e.g. Topology B or C), use Target Path Remapping in the Restore Wizard to relocate files without changing application configuration.'
                    }
                )}

                <!-- Primary Action Buttons (Section 7.4.2) -->
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 1.25rem;">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="btn primary-btn btn-sm" @click=${() => this.openCreateBackupModal('full_platform')}>
                            🛡️ Full Platform Backup
                        </button>
                        <button class="btn secondary-btn btn-sm" @click=${() => this.openCreateBackupModal('domain')}>
                            🌐 Domain Backup
                        </button>
                        <button class="btn secondary-btn btn-sm" @click=${() => this.openCreateBackupModal('app')}>
                            📱 Application Backup
                        </button>
                        <button class="btn secondary-btn btn-sm" @click=${() => this.openCreateBackupModal('database')}>
                            🗄️ Database Backup
                        </button>
                        <button class="btn btn-sm btn-success" @click=${() => this.openRestoreWizard()}>
                            🔄 Guided Restore Wizard
                        </button>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        ${this.renderHelpButton('backups')}
                        <button class="btn ghost-btn btn-sm" @click=${() => this.loadBackups()}>🔄 Refresh</button>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 1rem; padding: 8px 12px; background: rgba(0,0,0,0.2); border-radius: 8px;">
                    <div class="filter-tabs" style="display: inline-flex; background: rgba(0,0,0,0.25); border-radius: 8px; padding: 2px;">
                        <button class="btn btn-sm ${backupFilter === 'all' ? 'primary-btn' : 'ghost-btn'}" 
                            style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                            @click=${() => this.setState({ backupFilter: 'all' })}>
                            All (${backups.length})
                        </button>
                        <button class="btn btn-sm ${backupFilter === 'full' ? 'primary-btn' : 'ghost-btn'}" 
                            style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                            @click=${() => this.setState({ backupFilter: 'full' })}>
                            Full Platform (${fullBackups.length})
                        </button>
                        <button class="btn btn-sm ${backupFilter === 'domain' ? 'primary-btn' : 'ghost-btn'}" 
                            style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                            @click=${() => this.setState({ backupFilter: 'domain' })}>
                            Domains (${domainBackups.length})
                        </button>
                        <button class="btn btn-sm ${backupFilter === 'app' ? 'primary-btn' : 'ghost-btn'}" 
                            style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                            @click=${() => this.setState({ backupFilter: 'app' })}>
                            Apps (${appBackups.length})
                        </button>
                        <button class="btn btn-sm ${backupFilter === 'database' ? 'primary-btn' : 'ghost-btn'}" 
                            style="border-radius: 6px; padding: 3px 10px; font-size: 11px;"
                            @click=${() => this.setState({ backupFilter: 'database' })}>
                            Databases (${dbBackups.length})
                        </button>
                    </div>

                    <input type="text" placeholder="Search archive ID or target..." 
                        class="suite-input" 
                        value="${backupSearchTerm}" 
                        @input=${(e) => this.setState({ backupSearchTerm: e.target.value })} 
                        style="padding: 5px 12px; font-size: 0.8rem; border-radius: 6px; min-width: 220px;">
                </div>

                <!-- Unified Vault Archives Table (Section 7.4.3) -->
                ${loadingBackups ? html`
                    <div class="loading-state" style="padding: 2.5rem; text-align: center;"><div class="sppux-spinner"></div> Synchronizing backup vault telemetry...</div>
                ` : (displayBackups && displayBackups.length > 0) ? html`
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 28%;">Archive Identifier</th>
                                    <th style="width: 16%;">Scope &amp; Target</th>
                                    <th style="width: 12%;">Size</th>
                                    <th style="width: 14%;">Security</th>
                                    <th style="width: 14%;">Integrity</th>
                                    <th style="width: 16%;" class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${displayBackups.map(b => {
                                    const scope = (b.scope || b.backup_type || 'app').toLowerCase();
                                    const isFull = scope === 'full_platform' || scope === 'full';
                                    const isDomain = scope === 'domain';
                                    const isDb = scope === 'database';
                                    const isEncrypted = b.encrypted !== false;
                                    const isVerified = b.verified !== false;

                                    return html`
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <span style="font-size: 1.3rem;">
                                                        ${isFull ? '🛡️' : (isDomain ? '🌐' : (isDb ? '🗄️' : '📦'))}
                                                    </span>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--text-bright); font-size: 0.88rem;">${b.filename}</div>
                                                        <small style="color: var(--text-dim); font-size: 0.72rem;">
                                                            ${b.created_at_formatted || b.created_at || '—'}
                                                            ${b.files_count ? ` • ${b.files_count} files` : ''}
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                                    <span class="code-badge ${isFull ? 'success' : (isDomain ? 'primary' : 'warning')}" style="font-size: 11px; text-transform: uppercase; font-weight: 700; width: fit-content;">
                                                        ${isFull ? 'Full Platform' : (isDomain ? 'Domain' : (isDb ? 'Database' : 'App'))}
                                                    </span>
                                                    <span style="font-size: 0.78rem; color: var(--text-dim); font-weight: 500;">
                                                        ${b.target || b.app_name || (isFull ? 'Entire Platform' : 'Local Instance')}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="code-badge" style="color: #cbd5e1; font-weight: 600;">${b.size_formatted || this.formatBytes(b.size || 0)}</code>
                                            </td>
                                            <td>
                                                ${isEncrypted ? html`
                                                    <span class="badge" style="background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); font-size: 0.72rem; padding: 2px 7px; border-radius: 10px;">
                                                        🔒 AES-256-GCM
                                                    </span>
                                                ` : html`
                                                    <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-dim); font-size: 0.72rem; padding: 2px 7px; border-radius: 10px;">
                                                        🔓 Unencrypted
                                                    </span>
                                                `}
                                            </td>
                                            <td>
                                                ${isVerified ? html`
                                                    <span class="badge" style="background: rgba(56,189,248,0.15); color: #38bdf8; border: 1px solid rgba(56,189,248,0.3); font-size: 0.72rem; padding: 2px 7px; border-radius: 10px;" title="SHA-256 Checksum verified">
                                                        ✅ Validated
                                                    </span>
                                                ` : html`
                                                    <span class="badge" style="background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); font-size: 0.72rem; padding: 2px 7px; border-radius: 10px;" title="Integrity not yet verified">
                                                        ⏳ Unchecked
                                                    </span>
                                                `}
                                            </td>
                                            <td class="text-right">
                                                <div class="btn-group-horizontal" style="justify-content: flex-end; gap: 4px;">
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.inspectBackupArchive(b)} title="Inspect Manifest &amp; Resources">
                                                        📋 Manifest
                                                    </button>
                                                    <button class="btn ghost-btn btn-sm" @click=${() => this.verifyBackupArchive(b)} title="Verify Cryptographic Integrity">
                                                        🔍 Verify
                                                    </button>
                                                    <button class="btn secondary-btn btn-sm" @click=${() => this.openRestoreWizard(b)} title="Restore with Target Path Remapping">
                                                        🔄 Restore
                                                    </button>
                                                    <button class="btn danger-btn btn-sm" @click=${() => this.deleteBackup(b)} title="Delete Archive">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                })}
                            </tbody>
                        </table>
                    </div>
                ` : html`
                    <div class="empty-state" style="padding: 3.5rem 1rem; text-align: center;">
                        <div class="empty-icon" style="font-size: 3.5rem; margin-bottom: 12px;">🛡️</div>
                        <h3>No Backup Snapshots Found</h3>
                        <p style="color: var(--text-dim); max-width: 520px; margin: 0 auto 18px auto; line-height: 1.5;">
                            Create unified platform, domain, application, or database backups. Snapshots capture all code, configuration, databases, and registered storage with authenticated AES-256-GCM encryption.
                        </p>
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <button class="btn primary-btn btn-sm" @click=${() => this.openCreateBackupModal('full_platform')}>
                                🛡️ Create First Full Platform Backup
                            </button>
                        </div>
                    </div>
                `}
            </div>
        `;
    }

    formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }

    openCreateBackupModal(defaultScope = 'full_platform') {
        const { apps = [], domains = [] } = this.state;

        const appOptions = apps.map(a => `<option value="${a.name}">${a.title || a.name} (${a.name})</option>`).join('');
        const domainOptions = domains.map(d => `<option value="${d.pattern}">${d.pattern}</option>`).join('');

        this.openModal('🛡️ Create Unified Backup Snapshot', `
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:700; text-transform:uppercase; color:var(--text-dim); margin-bottom:6px;">Backup Scope</label>
                    <select id="backup-scope-select" class="suite-input" style="width:100%; padding:8px 12px; border-radius:6px; font-weight:600;" onchange="window.appsViewInstance.onBackupScopeChanged(this.value)">
                        <option value="full_platform" ${defaultScope === 'full_platform' ? 'selected' : ''}>🛡️ Full Platform (Everything: Core, Apps, Domains, DBs, Config, Storage)</option>
                        <option value="domain" ${defaultScope === 'domain' ? 'selected' : ''}>🌐 Domain Snapshot (Isolated Domain Code, Config, Data &amp; DB)</option>
                        <option value="app" ${defaultScope === 'app' ? 'selected' : ''}>📱 Application Snapshot (Application Code, Config &amp; Storage)</option>
                        <option value="database" ${defaultScope === 'database' ? 'selected' : ''}>🗄️ Database Snapshot (Zero-Lock SQLite / DB DDL &amp; Data)</option>
                    </select>
                </div>

                <div id="backup-target-app-group" style="display:${defaultScope === 'app' ? 'block' : 'none'};">
                    <label style="display:block; font-size:0.8rem; font-weight:700; text-transform:uppercase; color:var(--text-dim); margin-bottom:6px;">Select Application</label>
                    <select id="backup-target-app" class="suite-input" style="width:100%; padding:8px 12px; border-radius:6px;">
                        ${appOptions}
                    </select>
                </div>

                <div id="backup-target-domain-group" style="display:${defaultScope === 'domain' ? 'block' : 'none'};">
                    <label style="display:block; font-size:0.8rem; font-weight:700; text-transform:uppercase; color:var(--text-dim); margin-bottom:6px;">Select Domain</label>
                    <select id="backup-target-domain" class="suite-input" style="width:100%; padding:8px 12px; border-radius:6px;">
                        ${domainOptions}
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:700; text-transform:uppercase; color:var(--text-dim); margin-bottom:6px;">Description / Memo (Optional)</label>
                    <input type="text" id="backup-description" class="suite-input" placeholder="e.g. Pre-migration snapshot before upgrading to v2.1" style="width:100%; padding:8px 12px; border-radius:6px;">
                </div>

                <div style="background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.06); border-radius:8px; padding:12px;">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" id="backup-encrypt-toggle" checked style="width:16px; height:16px;">
                        <div>
                            <strong style="color:var(--text-bright); font-size:0.88rem;">🔒 Authenticated At-Rest Encryption (AES-256-GCM)</strong>
                            <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">
                                Encrypts the payload with hardware-accelerated AES-256-GCM authenticated cipher and cryptographic auth tag.
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        `, async () => {
            const scope = document.getElementById('backup-scope-select').value;
            const app = document.getElementById('backup-target-app') ? document.getElementById('backup-target-app').value : '';
            const domain = document.getElementById('backup-target-domain') ? document.getElementById('backup-target-domain').value : '';
            const description = document.getElementById('backup-description').value;
            const encrypt = document.getElementById('backup-encrypt-toggle').checked;

            const payload = {
                scope: scope,
                app_name: app,
                domain_id: domain,
                description: description,
                encrypt: encrypt
            };

            (this.admin || window.admin).notify(`Generating ${scope} backup snapshot...`, 'info');
            (this.admin || window.admin).closeModal();

            try {
                const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
                const res = await apiCall('backup_vault_create', payload);
                if (res.success) {
                    (this.admin || window.admin).notify(res.message || 'Backup generated successfully!', 'success');
                    await this.loadBackups();
                } else {
                    (this.admin || window.admin).notify(res.error || res.message || 'Backup generation failed.', 'error');
                }
            } catch (e) {
                (this.admin || window.admin).notify('Backup error: ' + e.message, 'error');
            }
        });
    }

    onBackupScopeChanged(scope) {
        const appGroup = document.getElementById('backup-target-app-group');
        const domGroup = document.getElementById('backup-target-domain-group');
        if (appGroup) appGroup.style.display = (scope === 'app') ? 'block' : 'none';
        if (domGroup) domGroup.style.display = (scope === 'domain') ? 'block' : 'none';
    }

    async inspectBackupArchive(backup) {
        (this.admin || window.admin).notify(`Inspecting manifest for ${backup.filename}...`, 'info');
        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const res = await apiCall('backup_vault_inspect', { filename: backup.filename });
            if (res.success && res.manifest) {
                const m = res.manifest;
                const resources = m.resources || [];
                const databases = m.databases || [];

                (this.admin || window.admin).openModal(`📋 Manifest: ${backup.filename}`, `
                    <div style="display:flex; flex-direction:column; gap:14px; max-height:550px; overflow-y:auto;">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; background:rgba(0,0,0,0.25); padding:10px 14px; border-radius:8px;">
                            <div><strong style="color:var(--text-dim); font-size:0.75rem;">BACKUP ID:</strong><div style="font-family:monospace; color:#818cf8; font-size:0.85rem;">${m.backup_id}</div></div>
                            <div><strong style="color:var(--text-dim); font-size:0.75rem;">SOURCE INSTALLATION:</strong><div style="font-family:monospace; color:#38bdf8; font-size:0.85rem;">${m.installation_id || 'spp-local'}</div></div>
                            <div><strong style="color:var(--text-dim); font-size:0.75rem;">SCOPE:</strong><div style="color:#34d399; font-weight:700; text-transform:uppercase; font-size:0.85rem;">${m.scope || 'Platform'}</div></div>
                            <div><strong style="color:var(--text-dim); font-size:0.75rem;">TIMESTAMP:</strong><div style="font-size:0.85rem;">${m.created_at}</div></div>
                        </div>

                        <div>
                            <h4 style="margin:0 0 6px 0; font-size:0.85rem; color:#93c5fd; text-transform:uppercase;">Packed Storage Resources (${resources.length})</h4>
                            <div style="background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.06); border-radius:6px; max-height:180px; overflow-y:auto;">
                                <table style="width:100%; border-collapse:collapse; font-size:0.78rem;">
                                    <thead>
                                        <tr style="border-bottom:1px solid rgba(255,255,255,0.08); color:var(--text-dim); text-align:left;">
                                            <th style="padding:6px 10px;">Logical Resource</th>
                                            <th style="padding:6px 10px;">Source Path</th>
                                            <th style="padding:6px 10px; text-align:right;">Files</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${resources.map(r => `
                                            <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                                                <td style="padding:6px 10px; font-weight:600; color:#c084fc;">${r.logical_type}</td>
                                                <td style="padding:6px 10px; font-family:monospace; color:#cbd5e1;">${r.original_path}</td>
                                                <td style="padding:6px 10px; text-align:right;">${r.files_count}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        ${databases.length > 0 ? `
                            <div>
                                <h4 style="margin:0 0 6px 0; font-size:0.85rem; color:#93c5fd; text-transform:uppercase;">Included Databases (${databases.length})</h4>
                                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                    ${databases.map(db => `
                                        <span class="code-badge primary" style="font-size:0.8rem; padding:4px 10px;">🗄️ ${db.connection || db.database} (${db.driver || 'sqlite'})</span>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}

                        <div>
                            <h4 style="margin:0 0 6px 0; font-size:0.85rem; color:var(--text-dim); text-transform:uppercase;">Raw JSON Manifest</h4>
                            <pre style="background:#0d1117; color:#c9d1d9; padding:10px; border-radius:6px; font-size:0.75rem; max-height:140px; overflow:auto;">${JSON.stringify(m, null, 2)}</pre>
                        </div>
                    </div>
                `, () => (this.admin || window.admin).closeModal());
            } else {
                (this.admin || window.admin).notify(res.error || 'Failed to inspect manifest.', 'error');
            }
        } catch (e) {
            (this.admin || window.admin).notify('Inspect error: ' + e.message, 'error');
        }
    }

    async verifyBackupArchive(backup) {
        (this.admin || window.admin).notify(`Verifying cryptographic checksums for ${backup.filename}...`, 'info');
        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            const res = await apiCall('backup_vault_verify', { filename: backup.filename });
            if (res.success) {
                (this.admin || window.admin).notify(`Verification Successful: ${res.message || 'All checksums match.'}`, 'success');
                await this.loadBackups();
            } else {
                (this.admin || window.admin).notify(`Verification Failed: ${res.error || 'Archive corrupt or modified.'}`, 'error');
            }
        } catch (e) {
            (this.admin || window.admin).notify('Verification error: ' + e.message, 'error');
        }
    }

    // --- Guided Restore Wizard (Section 7.4.4 & 11) ---
    async openRestoreWizard(initialBackup = null) {
        const { backups = [] } = this.state;
        const selectedFile = initialBackup ? initialBackup.filename : (backups[0] ? backups[0].filename : '');

        if (!selectedFile) {
            this.notify('No backup archives available in vault to restore.', 'warning');
            return;
        }

        // Fetch manifest for the selected archive
        (this.admin || window.admin).notify(`Loading restore wizard for ${selectedFile}...`, 'info');
        const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
        let manifest = null;
        try {
            const inspRes = await apiCall('backup_vault_inspect', { filename: selectedFile });
            if (inspRes.success && inspRes.manifest) {
                manifest = inspRes.manifest;
            }
        } catch (e) {
            console.warn('Could not pre-fetch manifest:', e);
        }

        const resources = (manifest && manifest.resources) ? manifest.resources : [];
        const isEncrypted = initialBackup ? (initialBackup.encrypted !== false) : true;

        (this.admin || window.admin).openModal(`🔄 Guided Restore Wizard: ${selectedFile}`, `
            <div style="display:flex; flex-direction:column; gap:16px; max-height:560px; overflow-y:auto;">
                <!-- Step 1 & 2: Overview & Manifest Preview -->
                <div style="background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.25); border-radius:8px; padding:12px 14px;">
                    <strong style="color:#a5b4fc; font-size:0.85rem;">Step 1 &amp; 2: Archive &amp; Source Manifest Verification</strong>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:8px; margin-top:8px; font-size:0.8rem;">
                        <div><strong>Archive File:</strong> <code style="color:#cbd5e1;">${selectedFile}</code></div>
                        <div><strong>Source Instance:</strong> <code style="color:#38bdf8;">${(manifest && manifest.installation_id) || 'Original Instance'}</code></div>
                        <div><strong>Scope:</strong> <span style="color:#34d399; font-weight:700;">${(manifest && manifest.scope) || 'Platform'}</span></div>
                        <div><strong>Created At:</strong> ${(manifest && manifest.created_at) || '—'}</div>
                    </div>
                </div>

                <!-- Step 3: Target Path Remapping Table (Section 11.2) -->
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <strong style="color:var(--text-bright); font-size:0.85rem;">Step 3: Target Path Remapping</strong>
                        <span style="font-size:0.75rem; color:var(--text-dim);">Map source paths to new physical destinations</span>
                    </div>
                    <div style="background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.06); border-radius:6px; max-height:180px; overflow-y:auto; padding:8px;">
                        ${resources.length > 0 ? `
                            <table style="width:100%; border-collapse:collapse; font-size:0.78rem;">
                                <thead>
                                    <tr style="color:var(--text-dim); text-align:left; border-bottom:1px solid rgba(255,255,255,0.08);">
                                        <th style="padding:4px 8px; width:25%;">Logical Resource</th>
                                        <th style="padding:4px 8px; width:35%;">Original Source Path</th>
                                        <th style="padding:4px 8px; width:40%;">Target Physical Path</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${resources.map(r => `
                                        <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                                            <td style="padding:6px 8px; font-weight:600; color:#c084fc;">${r.logical_type}</td>
                                            <td style="padding:6px 8px; font-family:monospace; color:var(--text-dim); font-size:0.72rem;">${r.original_path}</td>
                                            <td style="padding:4px 8px;">
                                                <input type="text" 
                                                    class="suite-input restore-remapping-input" 
                                                    data-logical="${r.logical_type}" 
                                                    value="${r.original_path}" 
                                                    style="width:100%; padding:3px 6px; font-size:0.75rem; font-family:monospace; border-radius:4px;">
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : `
                            <div style="color:var(--text-dim); font-size:0.8rem; padding:8px; text-align:center;">
                                Target paths will be dynamically mapped to current installation roots.
                            </div>
                        `}
                    </div>
                </div>

                <!-- Step 4: Pre-Restore Safety Options -->
                <div>
                    <strong style="color:var(--text-bright); font-size:0.85rem; display:block; margin-bottom:8px;">Step 4: Safety &amp; Validation Controls</strong>
                    <div style="display:flex; flex-direction:column; gap:8px; background:rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.06); border-radius:8px; padding:12px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="restore-recovery-point" checked style="width:16px; height:16px;">
                            <span style="font-size:0.82rem; color:var(--text-bright); font-weight:600;">
                                📸 Create pre-restore recovery point snapshot before modifying filesystem
                            </span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="restore-dry-run" style="width:16px; height:16px;">
                            <span style="font-size:0.82rem; color:#38bdf8; font-weight:600;">
                                🧪 Dry run validation only (simulate without modifying filesystem or database)
                            </span>
                        </label>
                    </div>
                </div>

                ${isEncrypted ? `
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; text-transform:uppercase; color:var(--text-dim); margin-bottom:4px;">Decryption Key (If different from instance master key)</label>
                        <input type="password" id="restore-decrypt-key" class="suite-input" placeholder="Leave blank to use platform master encryption key" style="width:100%; padding:8px 12px; border-radius:6px; font-size:0.82rem;">
                    </div>
                ` : ''}
            </div>
        `, async () => {
            const dryRun = document.getElementById('restore-dry-run') ? document.getElementById('restore-dry-run').checked : false;
            const recoveryPoint = document.getElementById('restore-recovery-point') ? document.getElementById('restore-recovery-point').checked : true;
            const decryptKeyInput = document.getElementById('restore-decrypt-key');
            const decryptKey = decryptKeyInput ? decryptKeyInput.value.trim() : null;

            // Collect target path remappings
            const targetPaths = {};
            const remapInputs = document.querySelectorAll('.restore-remapping-input');
            remapInputs.forEach(input => {
                const logical = input.getAttribute('data-logical');
                const val = input.value.trim();
                if (logical && val) targetPaths[logical] = val;
            });

            const restorePayload = {
                filename: selectedFile,
                target_paths: targetPaths,
                dry_run: dryRun,
                create_recovery_point: recoveryPoint,
                decryption_key: decryptKey || null
            };

            (this.admin || window.admin).notify(`Executing ${dryRun ? 'dry-run simulation' : 'staged restoration'}...`, 'info');
            (this.admin || window.admin).closeModal();

            try {
                const res = await apiCall('backup_vault_restore', restorePayload);
                if (res.success) {
                    (this.admin || window.admin).notify(
                        dryRun 
                            ? `Simulation Successful: ${res.message || 'All targets verified.'}` 
                            : `Restoration Complete: ${res.message || 'System restored successfully!'}`,
                        'success'
                    );
                    await this.loadData();
                    await this.loadBackups();
                } else {
                    (this.admin || window.admin).notify(`Restoration Failed: ${res.error || res.message}`, 'error');
                }
            } catch (e) {
                (this.admin || window.admin).notify('Restoration error: ' + e.message, 'error');
            }
        });
    }

    async deleteBackup(backup) {
        if (!confirm(`Permanently delete backup archive '${backup.filename}' to reclaim ${backup.size_formatted || 'disk space'}?\n\nThis action cannot be undone.`)) {
            return;
        }

        this.notify(`Deleting backup archive '${backup.filename}'...`, 'info');
        try {
            const apiCall = (this.admin || window.admin).api.bind(this.admin || window.admin);
            let res = await apiCall('backup_vault_delete', { filename: backup.filename }).catch(() => null);
            if (!res || !res.success) {
                res = await apiCall('apps_delete_backup', { file: backup.filename });
            }
            if (res && res.success) {
                this.notify(res.message || `Backup '${backup.filename}' deleted. Disk space reclaimed!`, 'success');
                await this.loadBackups();
            } else {
                this.notify((res && (res.error || res.message)) || 'Failed to delete backup.', 'error');
            }
        } catch (e) {
            this.notify('Error deleting backup: ' + e.message, 'error');
        }
    }

    // --- Tab: Server Logs ---
    renderServerLogs() {
        const { logLines, logFilter, logPolling } = this.state;
        const lines = logLines || [];
        const filter = logFilter || 'all';

        const filtered = filter === 'all' ? lines : lines.filter(l => {
            if (filter === 'error') return /error|fatal|exception/i.test(l);
            if (filter === 'warn') return /warn/i.test(l);
            if (filter === 'info') return /info|notice/i.test(l);
            return true;
        });

        return html`
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header">
                    <div class="header-main" style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 10px;">
                        <h3 class="gradient-text" style="margin: 0;">Live Server Logs</h3>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            ${this.renderHelpButton('logs')}
                            <select class="btn ghost-btn btn-sm" style="background: var(--bg-card-glass);" @change=${(e) => this.setState({logFilter: e.target.value})}>
                                <option value="all">📋 All Levels</option>
                                <option value="error">🔴 Errors Only</option>
                                <option value="warn">🟡 Warnings</option>
                                <option value="info">🔵 Info</option>
                            </select>
                            <button type="button" class="btn ${logPolling ? 'danger-ghost-btn' : 'primary-btn'} btn-sm" @click=${() => this.toggleLogPolling()}>
                                ${logPolling ? '⏹️ Stop Tail' : '▶️ Start Live Tail'}
                            </button>
                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.fetchLogs()}>🔄 Refresh</button>
                        </div>
                    </div>
                </div>

                ${this.renderHelpBanner('logs', 'System & Application Log Inspector Guide',
                    'Real-time log tailing, severity filtering, and error diagnostic inspector for platform errors, access traffic, and framework audit logs.',
                    {
                        steps: 'Choose a log stream (Platform Error, Application Audit, Web Access, or CLI Daemon). Select severity (DEBUG, INFO, WARN, ERROR, CRITICAL) and enter search keywords to filter live entries.',
                        concepts: 'Logs are structured in W3C Trace Context and RFC-5424 formats with unique request correlation IDs (X-Correlation-ID) across distributed requests and background queues.',
                        proTip: 'Use the "Auto-Refresh" toggle during active debugging, but pause it when analyzing complex stack traces to avoid screen jumping.'
                    }
                )}

                <div class="code-terminal" style="margin: 0 20px 20px 20px; background: #0f111a; border-radius: 8px; padding: 15px; min-height: 350px; max-height: 550px; overflow-y: auto; font-family: 'Fira Code', 'Cascadia Code', monospace; font-size: 0.8rem; color: #a6accd; border: 1px solid rgba(255,255,255,0.1);">
                    ${filtered.length === 0
                        ? html`<div style="opacity:0.5; font-style:italic;">No log entries. Click "Start Live Tail" or "Refresh" to fetch logs.</div>`
                        : filtered.map(line => {
                            let color = '#a6accd';
                            if (/error|fatal|exception/i.test(line)) color = '#ff5370';
                            else if (/warn/i.test(line)) color = '#ffcb6b';
                            else if (/info|notice/i.test(line)) color = '#82aaff';
                            return html`<div style="color: ${color}; line-height: 1.6; border-bottom: 1px solid rgba(255,255,255,0.03); padding: 2px 0;">${line}</div>`;
                        })
                    }
                </div>
            </div>
        `;
    }

    async fetchLogs() {
        try {
            const res = await this.apiPost('tail_logs', {});
            if (res.success && res.data && res.data.lines) {
                this.setState({ logLines: res.data.lines });
            } else {
                this.notify(res.message || 'Failed to fetch logs', 'error');
            }
        } catch (e) {
            this.notify('Log fetch error: ' + e.message, 'error');
        }
    }

    toggleLogPolling() {
        if (this.state.logPolling) {
            clearInterval(this._logPollTimer);
            this._logPollTimer = null;
            this.setState({ logPolling: false });
            this.notify('Log tailing stopped.', 'info');
        } else {
            this.fetchLogs();
            this._logPollTimer = setInterval(() => this.fetchLogs(), 5000);
            this.setState({ logPolling: true });
            this.notify('Live tail started (5s interval).', 'success');
        }
    }

    // --- Deploy Sync ---
    openDeploySync(appName) {
        this.openModal(`Deploy / Sync: ${appName}`, html`
            <div class="form-grid">
                <div class="input-group full-width">
                    <label>Target Environment URL <span class="help-tag" title="The URL of the remote SPP Admin API on the target server.">(?)</span></label>
                    <input type="text" id="deploy-target-url" placeholder="https://staging.example.com/admin/api.php">
                </div>
                <div class="input-group full-width">
                    <label>Deploy Token / Secret</label>
                    <input type="password" id="deploy-token" placeholder="Bearer token or shared secret">
                </div>
            </div>
            <div class="spp-card" style="margin-top: 15px; padding: 15px; background: var(--surface-2);">
                <h5 style="margin-top: 0;">📦 What gets packaged:</h5>
                <ul style="font-size: 0.85rem; color: var(--text-dim); margin: 5px 0 0 15px;">
                    <li>Application configuration (base URL, prefixes, options)</li>
                    <li>All entity YAMLs and PHP logic files</li>
                    <li>Module registry state</li>
                    <li>Shared group definitions</li>
                </ul>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: '📦 Export Package Only', type: 'ghost', fn: () => this.exportAppPackage(appName) },
            { label: '🚀 Package & Deploy', type: 'primary', fn: () => this.deployToTarget(appName) }
        ]);
    }

    async exportAppPackage(appName) {
        SPPUX.notify('Packaging application...', 'info');
        try {
            const res = await this.apiPost('export_app_package', { app: appName });
            if (res.success) {
                SPPUX.notify('Package exported! Check console for payload.', 'success');
                console.log('[SPP Deploy] Exported Package:', res.data);
            } else {
                SPPUX.notify(res.message || 'Export failed', 'error');
            }
        } catch (e) {
            SPPUX.notify('Export error: ' + e.message, 'error');
        }
    }

    async deployToTarget(appName) {
        const targetUrl = document.getElementById('deploy-target-url')?.value;
        const token = document.getElementById('deploy-token')?.value;
        if (!targetUrl) return SPPUX.notify('Target URL is required.', 'error');

        SPPUX.notify('Packaging and deploying...', 'info');
        try {
            const res = await this.apiPost('export_app_package', { app: appName });
            if (res.success) {
                // In production this would POST the payload to targetUrl.
                // For dev safety, we log and simulate success.
                console.log('[SPP Deploy] Would POST to:', targetUrl, 'with token:', token ? '***' : '(none)');
                console.log('[SPP Deploy] Payload:', res.data);
                SPPUX.notify('Deploy simulated successfully! Payload logged to console.', 'success');
                this.closeModal();
            } else {
                SPPUX.notify(res.message || 'Packaging failed', 'error');
            }
        } catch (e) {
            SPPUX.notify('Deploy error: ' + e.message, 'error');
        }
    }

    // --- Tab: cPanel Importer ---
    async loadCpanelArchives() {
        this.setState({ cpanelLoadingArchives: true });
        try {
            const res = await this.apiPost('cpanel_import_list_archives', {});
            const list = res.archives || (res.data?.archives) || [];
            this.setState({ cpanelArchives: list, cpanelLoadingArchives: false });
        } catch (e) {
            this.setState({ cpanelLoadingArchives: false });
        }
    }

    async inspectCpanelArchive(archivePath) {
        const path = archivePath || this.state.cpanelArchivePath;
        if (!path) {
            SPPUX.notify('Please specify or select a backup archive path.', 'error');
            return;
        }
        this.setState({ cpanelInspecting: true, cpanelInspection: null, cpanelArchivePath: path });
        try {
            const res = await this.apiPost('cpanel_import_inspect', { archive: path });
            const data = res.manifest ? res : (res.data || {});
            if (data.manifest) {
                this.setState({
                    cpanelInspection: data.manifest,
                    cpanelInspecting: false,
                    cpanelTargetApp: this.state.cpanelTargetApp || data.manifest.username || ''
                });
                SPPUX.notify('cPanel backup archive preflight inspection successful!', 'success');
            } else {
                this.setState({ cpanelInspecting: false });
                SPPUX.notify(data.error || res.message || 'Inspection failed', 'error');
            }
        } catch (e) {
            this.setState({ cpanelInspecting: false });
            SPPUX.notify('Inspection error: ' + e.message, 'error');
        }
    }

    async executeCpanelImport() {
        const path = this.state.cpanelArchivePath;
        if (!path) {
            SPPUX.notify('Please specify or select a backup archive path.', 'error');
            return;
        }
        const app = this.state.cpanelTargetApp || (this.state.cpanelInspection?.username) || 'cpanel_migrated_app';
        if (!confirm(`Import cPanel backup into SPP application '${app}'?\n\nThis will extract public_html to docroot, restore databases, create sovereign mailboxes, and provision BIND DNS.`)) {
            return;
        }

        this.setState({ cpanelImporting: true, cpanelImportResult: null });
        try {
            const res = await this.apiPost('cpanel_import_execute', {
                archive: path,
                app: app,
                overwrite: this.state.cpanelOverwrite,
                skip_db: this.state.cpanelSkipDb,
                skip_email: this.state.cpanelSkipEmail,
                skip_files: this.state.cpanelSkipFiles,
                skip_dns: this.state.cpanelSkipDns
            });
            const data = res.app ? res : (res.data || {});
            if (data.success) {
                this.setState({ cpanelImporting: false, cpanelImportResult: data });
                SPPUX.notify(`cPanel migration completed in ${data.duration_ms || 0}ms!`, 'success');
                this.loadData();
            } else {
                this.setState({ cpanelImporting: false, cpanelImportResult: data });
                SPPUX.notify(data.error || res.message || 'Migration failed', 'error');
            }
        } catch (e) {
            this.setState({ cpanelImporting: false });
            SPPUX.notify('Import error: ' + e.message, 'error');
        }
    }

    renderCpanelImporter() {
        const { 
            cpanelArchives, cpanelLoadingArchives, cpanelArchivePath, 
            cpanelTargetApp, cpanelOverwrite, cpanelSkipDb, cpanelSkipEmail, 
            cpanelSkipFiles, cpanelSkipDns, cpanelInspection, cpanelInspecting, 
            cpanelImporting, cpanelImportResult 
        } = this.state;

        return html`
            <div class="cpanel-importer-container" style="padding: 20px;">
                <div class="header-card" style="margin-bottom: 20px;">
                    <div class="header-main" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h3 class="gradient-text" style="margin: 0; font-size: 1.25rem;">📥 1-Click cPanel / WHM Migration Engine</h3>
                            <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-dim);">
                                Ingest standard cPanel/WHM backups (<code>cpmove-*.tar.gz</code>, <code>.tar</code>, <code>.zip</code>) into sovereign SPP applications. Restores docroot files, databases, virtual mailboxes, Maildir message store, forwarders, and BIND DNS zones.
                            </p>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            ${this.renderHelpButton('cpanel')}
                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.loadCpanelArchives()}>
                                ${cpanelLoadingArchives ? '⏳ Scanning...' : '🔄 Rescan Archives'}
                            </button>
                        </div>
                    </div>
                </div>

                ${this.renderHelpBanner('cpanel', 'cPanel Full Backup Importer Guide',
                    'Automated migration utility to inspect and import legacy cPanel full backup tarballs (cpmove-*.tar.gz, backup-*.tar.gz) into modern SPP domain structures.',
                    {
                        steps: 'Enter the absolute path to a cPanel archive or select from detected files. Click "Inspect Archive" to preview databases, mailboxes, and public_html contents, choose your target SPP app name, and click "Execute Import".',
                        concepts: 'The importer extracts MySQL dumps and converts them or imports them into configured DB drivers, extracts public_html into the app storage path, and maps virtual email accounts.',
                        proTip: 'Use the "Dry Run" inspection mode first to verify table character sets and disk space requirements before performing live database imports.'
                    }
                )}

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Left Column: Archive Selection & Target Configuration -->
                    <div class="glass-panel" style="background: rgba(0,0,0,0.25); border: 1px solid var(--glass-border); border-radius: 8px; padding: 20px;">
                        <h4 style="margin: 0 0 12px 0; color: var(--text-bright);">1. Select cPanel Backup Archive</h4>

                        <!-- Discovered Archives List -->
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px;">
                                Discovered Archives on Server (${cpanelArchives.length})
                            </label>
                            ${cpanelArchives.length === 0 ? html`
                                <div style="font-size: 0.8rem; color: var(--text-dim); font-style: italic; padding: 8px; background: rgba(0,0,0,0.2); border-radius: 6px;">
                                    No backup files found in <code>var/backups</code> or temp directory. Specify absolute path below.
                                </div>
                            ` : html`
                                <div style="display: flex; flex-direction: column; gap: 6px; max-height: 140px; overflow-y: auto;">
                                    ${cpanelArchives.map(a => html`
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: rgba(255,255,255,0.03); border: 1px solid ${cpanelArchivePath === a.path ? 'var(--primary)' : 'rgba(255,255,255,0.06)'}; border-radius: 6px; cursor: pointer;"
                                            @click=${() => this.inspectCpanelArchive(a.path)}>
                                            <div>
                                                <div style="font-weight: 600; font-size: 0.8rem; color: var(--text-bright);">${a.filename}</div>
                                                <div style="font-size: 0.7rem; color: var(--text-dim);">${a.size_human} • ${a.modified_at}</div>
                                            </div>
                                            <button type="button" class="btn primary-btn btn-sm" style="padding: 2px 8px; font-size: 0.72rem;">Inspect</button>
                                        </div>
                                    `)}
                                </div>
                            `}
                        </div>

                        <!-- Manual Path Input -->
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px;">
                                Or Enter Full Archive Path / Directory:
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" class="suite-search-input" style="flex: 1; font-family: monospace; font-size: 0.8rem;"
                                    placeholder="/var/backups/cpmove-user.tar.gz"
                                    .value=${cpanelArchivePath}
                                    @input=${(e) => this.setState({ cpanelArchivePath: e.target.value.trim() })}>
                                <button type="button" class="btn primary-btn btn-sm"
                                    @click=${() => this.inspectCpanelArchive()}
                                    ?disabled=${cpanelInspecting}>
                                    ${cpanelInspecting ? '⏳ Inspecting...' : '🔍 Inspect'}
                                </button>
                            </div>
                        </div>

                        <!-- Target App Name -->
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px;">
                                Target SPP App Name:
                            </label>
                            <input type="text" class="suite-search-input" style="width: 100%; font-family: monospace; font-size: 0.8rem;"
                                placeholder="e.g. myschool (defaults to cPanel username)"
                                .value=${cpanelTargetApp}
                                @input=${(e) => this.setState({ cpanelTargetApp: e.target.value.trim().toLowerCase() })}>
                        </div>

                        <!-- Migration Options Checkboxes -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 8px;">
                                Migration Options:
                            </label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.8rem; color: var(--text-dim);">
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="checkbox" .checked=${cpanelOverwrite} @change=${(e) => this.setState({ cpanelOverwrite: e.target.checked })}>
                                    <span>Overwrite Existing App</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="checkbox" .checked=${cpanelSkipDb} @change=${(e) => this.setState({ cpanelSkipDb: e.target.checked })}>
                                    <span>Skip Databases</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="checkbox" .checked=${cpanelSkipEmail} @change=${(e) => this.setState({ cpanelSkipEmail: e.target.checked })}>
                                    <span>Skip Mailboxes &amp; Maildir</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="checkbox" .checked=${cpanelSkipFiles} @change=${(e) => this.setState({ cpanelSkipFiles: e.target.checked })}>
                                    <span>Skip Docroot Files</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="checkbox" .checked=${cpanelSkipDns} @change=${(e) => this.setState({ cpanelSkipDns: e.target.checked })}>
                                    <span>Skip BIND DNS Zone</span>
                                </label>
                            </div>
                        </div>

                        <button type="button" class="btn primary-btn shine-effect" style="width: 100%; padding: 10px; font-weight: 700; font-size: 0.9rem;"
                            @click=${() => this.executeCpanelImport()}
                            ?disabled=${cpanelImporting || !cpanelArchivePath}>
                            ${cpanelImporting ? '⏳ Executing 1-Click Migration Pipeline...' : '🚀 Execute 1-Click Migration'}
                        </button>
                    </div>

                    <!-- Right Column: Preflight Inspection Breakdown & Execution Output -->
                    <div class="glass-panel" style="background: rgba(0,0,0,0.25); border: 1px solid var(--glass-border); border-radius: 8px; padding: 20px;">
                        <h4 style="margin: 0 0 12px 0; color: var(--text-bright);">2. Preflight Manifest &amp; Results</h4>

                        ${cpanelInspection ? html`
                            <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(99,102,241,0.25); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span style="font-weight: 700; color: #818cf8; font-size: 0.85rem;">📦 Preflight Inspection Manifest</span>
                                    <span class="suite-pill suite-pill-success" style="font-size: 0.7rem;">Verified Compatible</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.8rem; color: var(--text-bright);">
                                    <div><strong>cPanel User:</strong> <code>${cpanelInspection.username || '—'}</code></div>
                                    <div><strong>Primary Domain:</strong> <code>${cpanelInspection.domain || '—'}</code></div>
                                    <div><strong>Archive Format:</strong> ${cpanelInspection.archive_type?.toUpperCase()}</div>
                                    <div><strong>Docroot Files:</strong> ${cpanelInspection.has_public_html ? '✔ public_html found' : '✖ None'}</div>
                                    <div><strong>MySQL Databases:</strong> ${(cpanelInspection.databases || []).length} found</div>
                                    <div><strong>Virtual Mailboxes:</strong> ${(cpanelInspection.email_accounts || []).length} found</div>
                                    <div><strong>Email Forwarders:</strong> ${(cpanelInspection.email_forwarders || []).length} found</div>
                                    <div><strong>BIND DNS Zone:</strong> ${cpanelInspection.has_dns_zone ? '✔ Found' : '✖ None'}</div>
                                </div>

                                ${cpanelInspection.databases?.length > 0 ? html`
                                    <div style="margin-top: 10px; font-size: 0.75rem; color: var(--text-dim);">
                                        <strong>Databases to Import:</strong>
                                        ${cpanelInspection.databases.map(d => html`
                                            <div style="font-family: monospace; color: #a5b4fc;">• ${d.name} (${Math.round((d.size || 0)/1024)} KB)</div>
                                        `)}
                                    </div>
                                ` : ''}

                                ${cpanelInspection.email_accounts?.length > 0 ? html`
                                    <div style="margin-top: 8px; font-size: 0.75rem; color: var(--text-dim);">
                                        <strong>Mailboxes to Provision:</strong>
                                        <div style="font-family: monospace; color: #34d399;">
                                            ${cpanelInspection.email_accounts.map(e => e.email).join(', ')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        ` : html`
                            <div style="padding: 24px; text-align: center; color: var(--text-dim); font-size: 0.85rem; border: 1px dashed var(--glass-border); border-radius: 8px; margin-bottom: 16px;">
                                Select an archive on the left and click <strong>Inspect</strong> to view the preflight inspection manifest.
                            </div>
                        `}

                        ${cpanelImportResult ? html`
                            <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; padding: 14px;">
                                <div style="font-weight: 700; color: #34d399; margin-bottom: 6px; font-size: 0.9rem;">
                                    🎉 Migration Completed Successfully!
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-bright); margin-bottom: 8px;">
                                    Application: <strong>${cpanelImportResult.app}</strong> | Target Directory: <code>${cpanelImportResult.target_dir}</code>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px; font-size: 0.75rem; color: var(--text-dim); font-family: monospace;">
                                    ${(cpanelImportResult.steps || []).map(st => html`
                                        <div>✔ ${st}</div>
                                    `)}
                                </div>
                                <div style="margin-top: 8px; font-size: 0.75rem; color: #a5b4fc;">
                                    Time Elapsed: <strong>${cpanelImportResult.duration_ms}ms</strong>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    // --- Header Injection ---
    renderHeader() {
        const headerActions = document.getElementById('header-actions');
        if (!headerActions) return;

        if (this.state.activeTab === 'modules') {
            const { modules, modFilter } = this.state;
            const activeCount = modules.filter(m => m.active).length;
            const categories = Array.from(new Set(modules.map(m => m.module_category || (m.type === 'system' ? 'Core Optional' : 'App Modules'))));
            
            const headerHtml = html`
                <div class="header-filters" style="display: flex; gap: 8px; align-items: center; flex-shrink: 0; white-space: nowrap;">
                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.setState({modGraphMode: !this.state.modGraphMode})} style="white-space: nowrap;">
                        ${this.state.modGraphMode ? '📋 List View' : '🕸️ Graph View'}
                    </button>
                    <select class="btn ghost-btn btn-sm" style="background: var(--bg-card-glass); max-width: 140px;" 
                        @change=${(e) => this.setModFilter(e.target.value)}>
                        <option value="all" ?selected="${modFilter === 'all'}">📦 All Modules</option>
                        ${categories.map(c => html`<option value="${c}" ?selected="${modFilter === c}">📁 ${c}</option>`)}
                    </select>
                    <div class="btn-group" style="display: flex; gap: 6px; flex-shrink: 0;">
                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.rebuildRegistry()} style="white-space: nowrap;">⚡ Compile</button>
                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.runMigrations()} style="white-space: nowrap;">🚀 Migrate</button>
                    </div>
                </div>
                <span style="font-size: 0.8rem; color: var(--text-dim); white-space: nowrap; flex-shrink: 0;">${activeCount}/${modules.length} active</span>
            `;
            headerActions.innerHTML = headerHtml.toString();
            
            if (!headerActions._hasSppListener) {
                ['click', 'change'].forEach(type => headerActions.addEventListener(type, (e) => this._onEvent(e)));
                headerActions._hasSppListener = true;
            }
        } else {
            headerActions.innerHTML = '';
        }
    }
}
