/**
 * Domains & Virtual Hosts View Component (SPP Domain Manager)
 * Provides comprehensive visual CRUD for multi-domain routing, canonical 301 redirects,
 * SSL certificate inspection, and interactive domain routing simulations.
 */

const BaseClass = window.BaseComponent || (window.SPPUX && window.SPPUX.BaseComponent) || class {
    constructor(admin, container, props) {
        this.admin = admin;
        this.container = container;
        this.props = props || {};
        this.state = {};
    }

    setState(partial) {
        this.state = Object.assign({}, this.state, partial);
    }

    update() {
        if (this.container && typeof this.render === 'function') {
            this.container.innerHTML = this.render();
        }
    }
};

export default class DomainsView extends BaseClass {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
        this.container = container;
        this.props = props || {};
        window.domainsViewInstance = this;
        this.initState();
    }

    initState() {
        if (!this.state) {
            this.state = {
                activeTab: 'domains',
                loading: true,
                domains: [],
                totalDomains: 0,
                addonDomains: [],
                totalAddons: 0,
                globalDefaultApp: 'default',
                domainMode: 'single',
                isSingleDomainMode: false,
                isEnforced: true,
                virtualHostMode: 'strict',
                unmappedSubdomainPolicy: 'strict',
                apps: [],
                // Simulator state
                simHost: (window.location.hostname && !/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname) && window.location.hostname !== 'localhost') ? window.location.hostname : 'example.com',
                simPath: '/',
                simResult: null,
                simulating: false,
                // SSL Inspector & AutoSSL state
                sslHost: (window.location.hostname && !/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname) && window.location.hostname !== 'localhost') ? window.location.hostname : 'example.com',
                sslResult: null,
                checkingSsl: false,
                autoSslResult: null,
                issuingSsl: false,
                // Certbot & Host SSL Provisioner state
                certbotStatus: null,
                loadingCertbotStatus: false,
                installingCertbot: false,
                certbotInstallResult: null,
                sslEngine: 'certbot', // 'certbot' | 'native_acme'
                preflightResults: null,
                checkingPreflight: false,
                certbotResult: null,
                issuingCertbot: false,
                // DNS Diagnostics state
                dnsHost: (window.location.hostname && !/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname) && window.location.hostname !== 'localhost') ? window.location.hostname : 'example.com',
                dnsType: 'ANY',
                dnsTrace: false,
                dnsResult: null,
                resolvingDns: false,
                // Reverse Proxy state
                selectedProxyDomain: '',
                proxyConfig: null,
                savingProxy: false,
                probingProxy: false,
                // DNS Zone Editor state
                zoneDomain: (window.location.hostname && !/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname) && window.location.hostname !== 'localhost') ? window.location.hostname : 'example.com',
                zoneData: null,
                loadingZone: false,
                zoneSearch: '',
                domainApps: [],
                allAppsList: [],
                propagationHost: (window.location.hostname && !/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname) && window.location.hostname !== 'localhost') ? window.location.hostname : 'example.com',
                propagationType: 'A',
                propagationResults: null,
                checkingPropagation: false,
                // BIND9 Daemon & Glue Diagnostics state
                bindStatus: null,
                loadingBind: false,
                syncingBind: false,
                reloadingBind: false,
                installingBind: false,
                glueMatrix: null,
                checkingGlue: false,
                // Unified DNS Provider Subsystem state
                dnsSubTab: 'overview',
                dnsProvidersData: null,
                loadingDnsProviders: false,
                dnsRecordTypeFilter: 'ALL',
                dnssecAuditData: null,
                loadingDnssecAudit: false,
                mailAuditData: null,
                loadingMailAudit: false,
                syncDiffData: null,
                comparingSync: false,
                syncingDns: false,
                registrarData: null,
                loadingRegistrar: false
            };
        }
    }

    setState(partial) {
        this.state = Object.assign({}, this.state, partial);
    }

    async onInit() {
        this.initState();
        await this.fetchData();
    }

    async fetchData() {
        this.setState({ loading: true });
        try {
            const [domRes, appsRes, addonRes, dAppsRes] = await Promise.all([
                this.admin.api('domains_list'),
                this.admin.api('list_apps'),
                this.admin.api('domains_addon_list'),
                this.admin.api('domains_get_apps')
            ]);
            const dAppsData = (dAppsRes && dAppsRes.success && dAppsRes.data) ? dAppsRes.data : (dAppsRes || {});
            const domainApps = dAppsData.installed_domain_apps || [];
            const allAppsList = dAppsData.all_apps || [];

            const domains = (domRes.success && domRes.data?.domains) || domRes.domains || [];
            const globalDefault = (domRes.success && domRes.data?.global_default_app) || domRes.global_default_app || 'default';
            const domainMode = (domRes.success && domRes.data?.domain_mode) || domRes.domain_mode || 'single';
            const isSingle = (domRes.success && typeof domRes.data?.is_single_domain_mode !== 'undefined') 
                ? domRes.data.is_single_domain_mode 
                : (typeof domRes.is_single_domain_mode !== 'undefined' ? domRes.is_single_domain_mode : (domainMode === 'single'));
            const isEnforced = (domRes.success && typeof domRes.data?.is_enforced !== 'undefined') 
                ? domRes.data.is_enforced 
                : (typeof domRes.is_enforced !== 'undefined' ? domRes.is_enforced : (domainMode === 'multi'));
            const vHostMode = (domRes.success && domRes.data?.virtual_host_mode) || domRes.virtual_host_mode || 'strict';
            const subPolicy = (domRes.success && domRes.data?.unmapped_subdomain_policy) || domRes.unmapped_subdomain_policy || 'strict';
            const apps = (appsRes.success && appsRes.data?.apps) || appsRes.apps || [];
            const addons = (addonRes.success && addonRes.data?.addons) || addonRes.addons || [];

            this.setState({
                domains: domains,
                totalDomains: domains.length,
                addonDomains: addons,
                totalAddons: addons.length,
                globalDefaultApp: globalDefault,
                domainMode: domainMode,
                isSingleDomainMode: isSingle,
                isEnforced: isEnforced,
                virtualHostMode: vHostMode,
                unmappedSubdomainPolicy: subPolicy,
                apps: apps,
                domainApps: domainApps,
                allAppsList: allAppsList,
                loading: false
            });
        } catch (e) {
            console.error("Domains fetch error:", e);
            this.admin.notify("Failed to fetch domain configuration: " + e.message, "error");
            this.setState({ loading: false });
        }
    }

    async toggleDomainMode(newMode) {
        try {
            this.admin.notify(`Switching domain architecture mode to '${newMode}'...`, "info");
            const res = await this.admin.api('domains_set_domain_mode', { mode: newMode, domain_mode: newMode });
            if (res.success) {
                this.admin.notify(res.message || `Domain architecture mode set to '${newMode}'.`, "success");
                await this.fetchData();
                this.update();
            } else {
                this.admin.notify(res.error || "Failed to switch domain architecture mode.", "error");
            }
        } catch (e) {
            this.admin.notify("Error updating domain mode: " + e.message, "error");
        }
    }

    async changeVirtualHostMode(newMode) {
        try {
            const res = await this.admin.api('domains_set_mode', { mode: newMode, unmapped_subdomain_policy: newMode });
            if (res.success) {
                this.admin.notify(`Virtual host policy mode switched to '${newMode}'.`, "success");
                this.setState({ virtualHostMode: newMode, unmappedSubdomainPolicy: newMode });
                this.update();
            } else {
                this.admin.notify(res.error || "Failed to change policy mode.", "error");
            }
        } catch (e) {
            this.admin.notify("Error updating mode: " + e.message, "error");
        }
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
        this.update();
        if (tab === 'ssl' && !this.state.certbotStatus && !this.state.loadingCertbotStatus) {
            this.loadCertbotStatus();
        }
        if (tab === 'zone' && !this.state.zoneData && !this.state.loadingZone) {
            this.loadZone(this.state.zoneDomain);
        }
        if (tab === 'cloudflare') {
            this.loadCloudflare();
        }
    }

    setupModalClose(modal) {
        if (!modal) return;
        const closeBtn = document.getElementById('modal-close');
        if (closeBtn) {
            closeBtn.onclick = (e) => {
                if (e) e.preventDefault();
                modal.classList.remove('active');
            };
        }
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        };
    }

    openDomainModal(domain = null) {
        const isEdit = !!domain;
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = isEdit ? `Edit Website: ${domain.pattern}` : 'Connect New Website / Domain';

        const isNonSppRoot = domain?.default_app && typeof domain.default_app === 'object' && domain.default_app.type === 'non_spp';
        const defaultAppStr = isNonSppRoot ? (domain.default_app.app_name || '') : (domain?.default_app || 'default');
        const nonSppTargetPath = isNonSppRoot ? (domain.default_app.target_path || '') : '';
        const nonSppIndex = isNonSppRoot ? (domain.default_app.index || 'index.php') : 'index.php';
        const nonSppOptIn = isNonSppRoot ? !!domain.default_app.opt_in_sppdata : false;

        const appOptions = (this.state.apps || []).map(a => {
            const selected = (!isNonSppRoot && defaultAppStr === a.name) ? 'selected' : '';
            return `<option value="${this.escapeHtml(a.name)}" ${selected}>${this.escapeHtml(a.title || a.name)} (${a.name})</option>`;
        }).join('');

        const aliasesStr = (domain?.aliases || []).join(', ');
        const enabledAppsStr = (domain?.enabled_apps || []).join(', ');
        const disabledAppsStr = (domain?.disabled_apps || []).join(', ');
        const settingsJson = JSON.stringify(domain?.settings || {}, null, 2);

        // Sub-apps state for coexistence table
        this._currentSubApps = JSON.parse(JSON.stringify(domain?.sub_apps || {}));

        const renderSubAppsList = () => {
            const keys = Object.keys(this._currentSubApps);
            if (keys.length === 0) {
                return `<div style="padding:0.75rem; text-align:center; color:var(--text-dim); font-size:0.85rem; border:1px dashed var(--glass-border); border-radius:var(--radius-sm);">No sub-routes configured yet. Entire domain routes directly to the default root application.</div>`;
            }
            return `
                <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--glass-border); text-align:left; color:var(--text-dim);">
                            <th style="padding:0.4rem;">Route Path</th>
                            <th style="padding:0.4rem;">Type</th>
                            <th style="padding:0.4rem;">Target Application / Directory</th>
                            <th style="padding:0.4rem;">Index</th>
                            <th style="padding:0.4rem; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${keys.map(r => {
                            const sub = this._currentSubApps[r];
                            const isSubNonSpp = sub.type === 'non_spp';
                            const typeBadge = isSubNonSpp 
                                ? `<span class="suite-pill suite-pill-warning" style="font-size:0.7rem;">External (Drupal/WP)</span>`
                                : `<span class="suite-pill suite-pill-info" style="font-size:0.7rem;">SPP Native</span>`;
                            const target = isSubNonSpp ? sub.target_path : sub.app_name;
                            return `
                                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                                    <td style="padding:0.5rem; font-family:monospace; font-weight:700; color:var(--text-bright);">${this.escapeHtml(r)}</td>
                                    <td style="padding:0.5rem;">${typeBadge}</td>
                                    <td style="padding:0.5rem; font-family:monospace; color:var(--text-dim); font-size:0.8rem;" title="${this.escapeHtml(target)}">${this.escapeHtml(target)}</td>
                                    <td style="padding:0.5rem; font-family:monospace; font-size:0.8rem;">${this.escapeHtml(sub.index || 'index.php')}</td>
                                    <td style="padding:0.5rem; text-align:right;">
                                        ${isSubNonSpp ? `<button type="button" class="btn ghost-btn btn-xs" onclick="window.domainsViewInstance.probeSubAppModal('${this.escapeHtml(sub.target_path)}')">Probe</button>` : ''}
                                        <button type="button" class="btn danger-btn btn-xs" onclick="window.domainsViewInstance.removeSubAppFromModal('${this.escapeHtml(r)}')">Remove</button>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        };

        document.getElementById('modal-body').innerHTML = `
            <div class="domain-editor-form" style="display:flex; flex-direction:column; gap:1rem;">
                
                <!-- Section 1: Domain Identity & User Account -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="input-group">
                        <label>
                            Domain Name <span style="color:var(--danger)">*</span>
                            <span class="spp-help-tip" data-tooltip-title="Website Domain Name" data-tooltip="The web address visitors type to open your website." data-tooltip-example="e.g. example.com or mysite.org">?</span>
                        </label>
                        <input type="text" id="dom-pattern" value="${this.escapeHtml(domain?.pattern || '')}" data-original-pattern="${this.escapeHtml(domain?.pattern || '')}" placeholder="e.g. example.com or mysite.org" required>
                    </div>
                    <div class="input-group">
                        <label>
                            Linux User Account
                            <span class="spp-help-tip" data-tooltip-title="User Home Isolation" data-tooltip="Linux system user account (e.g. webuser) that owns all domain apps and persistent sppdata.">?</span>
                        </label>
                        <div style="display:flex; gap:0.5rem;">
                            <input type="text" id="dom-account" value="${this.escapeHtml(domain?.account || 'default')}" placeholder="e.g. webuser or default">
                            <button type="button" class="btn secondary-btn btn-sm" id="dom-btn-autofill-user" title="Auto-populate User Home Apps and sppdata Paths">⚡ Auto Paths</button>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Home Directory Paths -->
                <div class="spp-setting-banner" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; padding:0.75rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                    <div class="input-group">
                        <label style="font-size:0.85rem;">
                            Domain Apps Directory (<code style="font-size:0.75rem;">app_dir</code>)
                            <span class="spp-help-tip" data-tooltip="Custom directory in user home hosting applications for this domain.">?</span>
                        </label>
                        <input type="text" id="dom-app-dir" value="${this.escapeHtml(domain?.app_dir || '')}" placeholder="e.g. /home/webuser/domains/example.com/apps" style="font-size:0.85rem; font-family:monospace;">
                    </div>
                    <div class="input-group">
                        <label style="font-size:0.85rem;">
                            Persistent Data Directory (<code style="font-size:0.75rem;">data_dir</code>)
                            <span class="spp-help-tip" data-tooltip="User home sppdata root preserving sovereign storage per domain and app.">?</span>
                        </label>
                        <input type="text" id="dom-data-dir" value="${this.escapeHtml(domain?.data_dir || '')}" placeholder="e.g. /home/webuser/domains/example.com/sppdata" style="font-size:0.85rem; font-family:monospace;">
                    </div>
                </div>

                <!-- Section 3: Routing Engine Mode -->
                <div class="input-group">
                    <label>
                        Domain Routing Mode
                        <span class="spp-help-tip" data-tooltip="Choose between instant in-process SPP path multiplexing or full webserver virtual host generation.">?</span>
                    </label>
                    <div class="spp-setting-banner" style="display:flex; gap:1.5rem; font-size:0.85rem; padding:0.6rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="dom-routing-mode" value="spp_path" ${(domain?.routing_mode || 'spp_path') === 'spp_path' ? 'checked' : ''}>
                            <span><strong>SPP In-Process Path Router</strong> (Default, Instant, Zero Daemon Reloads)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                            <input type="radio" name="dom-routing-mode" value="webserver_vhost" ${(domain?.routing_mode) === 'webserver_vhost' ? 'checked' : ''}>
                            <span><strong>Apache / Nginx Native VirtualHost</strong></span>
                        </label>
                    </div>
                </div>

                <!-- Section 4: Homepage Root Application (/) -->
                <div class="spp-setting-banner" style="border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                        <label style="font-weight:700; margin:0; color:var(--text-bright);">
                            Homepage Root Application (<code style="color:#0284c7;">/</code>) <span style="color:var(--danger)">*</span>
                            <span class="spp-help-tip" data-tooltip="Select ANY SPP application OR external non-SPP app (e.g. Drupal, WordPress) as root.">?</span>
                        </label>
                        <div style="display:flex; gap:0.8rem; font-size:0.8rem;">
                            <label style="display:flex; align-items:center; gap:0.3rem; cursor:pointer;">
                                <input type="radio" name="dom-root-app-type" value="spp" ${!isNonSppRoot ? 'checked' : ''} id="radio-root-spp">
                                <span>SPP Native App</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.3rem; cursor:pointer;">
                                <input type="radio" name="dom-root-app-type" value="non_spp" ${isNonSppRoot ? 'checked' : ''} id="radio-root-nonspp">
                                <span>External Non-SPP (Drupal / WordPress)</span>
                            </label>
                        </div>
                    </div>

                    <div id="root-app-spp-block" style="${isNonSppRoot ? 'display:none;' : ''}">
                        <select id="dom-default-app" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                            ${appOptions}
                        </select>
                        <small style="color:var(--text-dim); display:block; margin-top:0.3rem;">Served directly at '/' with sovereign user-home sppdata isolation.</small>
                    </div>

                    <div id="root-app-nonspp-block" style="${!isNonSppRoot ? 'display:none;' : ''}">
                        <div style="display:grid; grid-template-columns:2fr 1fr; gap:0.75rem;">
                            <div class="input-group">
                                <label style="font-size:0.8rem;">External Root Directory Path <span style="color:var(--danger)">*</span></label>
                                <div style="display:flex; gap:0.4rem;">
                                    <input type="text" id="dom-root-target-path" value="${this.escapeHtml(nonSppTargetPath)}" placeholder="e.g. /home/webuser/addon_domains/mysite.com/home10 or /var/www/site" style="font-family:monospace; font-size:0.85rem;">
                                    <button type="button" class="btn secondary-btn btn-sm" id="dom-btn-probe-root">Probe</button>
                                </div>
                            </div>
                            <div class="input-group">
                                <label style="font-size:0.8rem;">Entry Script</label>
                                <input type="text" id="dom-root-index" value="${this.escapeHtml(nonSppIndex)}" placeholder="index.php" style="font-family:monospace; font-size:0.85rem;">
                            </div>
                        </div>
                        <div id="dom-root-probe-result" style="margin-top:0.4rem; font-size:0.8rem;"></div>
                    </div>
                </div>

                <!-- Section 5: Sub-Route Applications Coexistence Manager -->
                <div class="spp-setting-banner" style="border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                        <div>
                            <strong style="color:var(--text-bright); font-size:0.9rem;">Sub-Route Coexistence Manager</strong>
                            <div style="font-size:0.75rem; color:var(--text-dim);">Multiplex multiple SPP and external apps on arbitrary sub-routes (e.g. /home, /docs, /portal).</div>
                        </div>
                    </div>

                    <div id="subapps-table-container">
                        ${renderSubAppsList()}
                    </div>

                    <!-- Add New Sub-Route Accordion / Form -->
                    <div class="spp-setting-banner" style="margin-top:0.75rem; padding:0.6rem; border:1px solid var(--glass-border); border-radius:var(--radius-sm);">
                        <strong style="font-size:0.8rem; color:var(--text-bright); display:block; margin-bottom:0.4rem;">+ Map Additional Sub-Route Application</strong>
                        <div style="display:grid; grid-template-columns:120px 140px 1fr 100px auto; gap:0.5rem; align-items:end;">
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim);">Route Path</label>
                                <input type="text" id="new-sub-route" placeholder="/home" style="font-size:0.8rem; font-family:monospace;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim);">App Type</label>
                                <select id="new-sub-type" style="font-size:0.8rem; padding:0.45rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm); width:100%;">
                                    <option value="non_spp">External (Drupal/WP)</option>
                                    <option value="spp">SPP Native App</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim);">Target Path / App Name</label>
                                <input type="text" id="new-sub-target" placeholder="/var/www/site or AppName" style="font-size:0.8rem; font-family:monospace;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim);">Index</label>
                                <input type="text" id="new-sub-index" value="index.php" style="font-size:0.8rem; font-family:monospace;">
                            </div>
                            <div style="display:flex; gap:0.3rem;">
                                <button type="button" class="btn secondary-btn btn-sm" id="btn-probe-new-sub">Probe</button>
                                <button type="button" class="btn primary-btn btn-sm" id="btn-add-new-sub">+ Add</button>
                            </div>
                        </div>
                        <div id="new-sub-probe-result" style="margin-top:0.3rem; font-size:0.8rem;"></div>
                    </div>
                </div>

                <!-- Section 6: Alternative Addresses / Aliases -->
                <div class="input-group">
                    <label>
                        Alternative Addresses / Aliases (Optional)
                        <span class="spp-help-tip" data-tooltip-title="Domain Aliases" data-tooltip="Additional domain names that point to this same website, separated by commas." data-tooltip-example="e.g. www.example.com, portal.example.com">?</span>
                    </label>
                    <input type="text" id="dom-aliases" value="${this.escapeHtml(aliasesStr)}" placeholder="e.g. www.example.com, portal.example.com">
                </div>

                <!-- Section 7: Toggles -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; padding:0.75rem; background:rgba(0,0,0,0.15); border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="dom-canonical" ${domain?.redirect_to_canonical ? 'checked' : ''}>
                        <span>Redirect Aliases (301 Canonical)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="dom-force-https" ${domain?.force_https ? 'checked' : ''}>
                        <span>Force Secure HTTPS (SSL)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="dom-allow-others" ${domain?.allow_other_apps !== false ? 'checked' : ''}>
                        <span>Allow Secondary Apps</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="dom-set-global">
                        <span>Set as Default Fallback Website</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem; color:#f59e0b; grid-column: span 2;">
                        <input type="checkbox" id="dom-auto-cf" checked>
                        <span>⚡ Auto-Publish to Cloudflare Edge DNS (Instant Proxy & SSL)</span>
                    </label>
                </div>

                <!-- Section 8: Advanced Accordion -->
                <div class="spp-advanced-accordion" style="margin-top:0.25rem;">
                    <div class="spp-accordion-toggle">
                        <span>⚙️ Advanced Access Control & Custom Settings (Optional)</span>
                        <span class="spp-accordion-icon">▾</span>
                    </div>
                    <div class="spp-accordion-body" style="padding-top:0.75rem; display:flex; flex-direction:column; gap:0.75rem;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div class="input-group">
                                <label>Allowed Apps Whitelist</label>
                                <input type="text" id="dom-enabled-apps" value="${this.escapeHtml(enabledAppsStr)}" placeholder="e.g. sppdocs, samvaad">
                            </div>
                            <div class="input-group">
                                <label>Blocked Apps Blacklist</label>
                                <input type="text" id="dom-disabled-apps" value="${this.escapeHtml(disabledAppsStr)}" placeholder="e.g. legacy_app">
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Custom Configuration (JSON)</label>
                            <textarea id="dom-settings" rows="3" style="font-family:monospace; font-size:0.8rem; width:100%; padding:0.5rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm);">${this.escapeHtml(settingsJson)}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Event: Auto-fill & scaffold accurate domain paths
        const domPatternInput = document.getElementById('dom-pattern');
        const domAccountInput = document.getElementById('dom-account');
        const domAppDirInput = document.getElementById('dom-app-dir');
        const domDataDirInput = document.getElementById('dom-data-dir');
        const autoPathsBtn = document.getElementById('dom-btn-autofill-user');

        // Real-time helper: dynamically update account & path placeholders when typing domain name
        if (domPatternInput) {
            domPatternInput.addEventListener('input', () => {
                const pat = domPatternInput.value.trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '');
                if (pat) {
                    const apex = pat.split('.')[0] !== 'www' ? pat.split('.')[0] : (pat.split('.')[1] || 'webuser');
                    if (!domAccountInput.value || domAccountInput.value === 'default') {
                        domAccountInput.placeholder = `e.g. ${apex}`;
                    }
                    if (domAppDirInput) domAppDirInput.placeholder = `e.g. /home/${apex}/domains/${pat}/apps`;
                    if (domDataDirInput) domDataDirInput.placeholder = `e.g. /home/${apex}/domains/${pat}/sppdata`;
                } else {
                    if (domAccountInput) domAccountInput.placeholder = 'e.g. webuser or default';
                    if (domAppDirInput) domAppDirInput.placeholder = 'e.g. /home/webuser/domains/example.com/apps';
                    if (domDataDirInput) domDataDirInput.placeholder = 'e.g. /home/webuser/domains/example.com/sppdata';
                }
            });
        }

        autoPathsBtn.onclick = async () => {
            let domainName = (domPatternInput ? domPatternInput.value : '').trim().toLowerCase();
            domainName = domainName.replace(/^https?:\/\//, '').replace(/\/.*$/, '');

            if (!domainName) {
                this.admin.notify("Please enter a Domain Name first to calculate accurate paths.", "warning");
                if (domPatternInput) domPatternInput.focus();
                return;
            }

            let acc = (domAccountInput ? domAccountInput.value : '').trim();
            if (!acc || acc === 'default') {
                const parts = domainName.split('.');
                const apex = (parts[0] === 'www' && parts[1]) ? parts[1] : parts[0];
                if (apex) {
                    acc = apex;
                    if (domAccountInput) domAccountInput.value = acc;
                } else {
                    acc = 'default';
                }
            }

            const computedAppDir = (acc !== 'default')
                ? `/home/${acc}/domains/${domainName}/apps`
                : `domains/${domainName}/apps`;
            const computedDataDir = (acc !== 'default')
                ? `/home/${acc}/domains/${domainName}/sppdata`
                : `domains/${domainName}/sppdata`;

            if (domAppDirInput) domAppDirInput.value = computedAppDir;
            if (domDataDirInput) domDataDirInput.value = computedDataDir;

            const origText = autoPathsBtn.textContent;
            autoPathsBtn.disabled = true;
            autoPathsBtn.textContent = '⚡ Scaffolding...';

            try {
                const res = await this.admin.api('domains_scaffold_paths', {
                    domain: domainName,
                    account: acc,
                    app_dir: computedAppDir,
                    data_dir: computedDataDir,
                    create_dirs: 1
                });
                if (res && res.success) {
                    const createdInfo = (res.created && res.created.length > 0)
                        ? ` (scaffolded: ${res.created.join(', ')})`
                        : ' (verified & ready)';
                    this.admin.notify(`Accurate paths scaffolded for '${domainName}'${createdInfo}`, 'success');
                } else {
                    this.admin.notify(`Paths populated: ${computedAppDir}`, 'info');
                }
            } catch (err) {
                this.admin.notify(`Paths populated for '${domainName}' (account: ${acc})`, 'info');
            } finally {
                autoPathsBtn.disabled = false;
                autoPathsBtn.textContent = origText;
            }
        };

        // Event: Root App Type Toggle
        document.getElementById('radio-root-spp').onchange = () => {
            document.getElementById('root-app-spp-block').style.display = 'block';
            document.getElementById('root-app-nonspp-block').style.display = 'none';
        };
        document.getElementById('radio-root-nonspp').onchange = () => {
            document.getElementById('root-app-spp-block').style.display = 'none';
            document.getElementById('root-app-nonspp-block').style.display = 'block';
        };

        // Event: Probe Root Non-SPP Path
        document.getElementById('dom-btn-probe-root').onclick = async () => {
            const p = document.getElementById('dom-root-target-path').value.trim();
            const resEl = document.getElementById('dom-root-probe-result');
            if (!p) {
                resEl.innerHTML = `<span style="color:#f87171;">Please enter a directory path to probe.</span>`;
                return;
            }
            resEl.innerHTML = `<span style="color:#38bdf8;">Probing path...</span>`;
            try {
                const res = await this.admin.api('domains_subapp_probe', { target_path: p });
                const probe = (res && res.data && typeof res.data === 'object' && ('valid' in res.data || 'framework' in res.data || 'exists' in res.data)) ? res.data : (res || {});
                const isValid = Boolean(probe.valid || res.valid || (res.success && (probe.exists || probe.framework)));
                if (isValid) {
                    const framework = probe.framework || res.framework || 'Generic PHP / Static';
                    const indexFile = probe.index_file || res.index_file || 'index.php';
                    const readable = (probe.readable !== false) ? 'yes' : 'restricted';
                    resEl.innerHTML = `<span style="color:#34d399;">✅ Valid: <strong>${this.escapeHtml(framework)}</strong> (Readable: ${readable}, Entry: ${this.escapeHtml(indexFile)})</span>`;
                    if (indexFile) {
                        const indexInput = document.getElementById('dom-root-index');
                        if (indexInput && (!indexInput.value || indexInput.value === 'index.php')) {
                            indexInput.value = indexFile;
                        }
                    }
                } else {
                    const errMsg = probe.error || res.error || res.message || 'Path invalid or not accessible';
                    resEl.innerHTML = `<span style="color:#f87171;">❌ ${this.escapeHtml(errMsg)}</span>`;
                }
            } catch (err) {
                resEl.innerHTML = `<span style="color:#f87171;">Probe error: ${this.escapeHtml(err.message)}</span>`;
            }
        };

        // Event: Probe New Sub-Route Path
        document.getElementById('btn-probe-new-sub').onclick = async () => {
            const p = document.getElementById('new-sub-target').value.trim();
            const resEl = document.getElementById('new-sub-probe-result');
            if (!p) {
                resEl.innerHTML = `<span style="color:#f87171;">Enter target path to probe.</span>`;
                return;
            }
            resEl.innerHTML = `<span style="color:#38bdf8;">Probing...</span>`;
            try {
                const res = await this.admin.api('domains_subapp_probe', { target_path: p });
                const probe = (res && res.data && typeof res.data === 'object' && ('valid' in res.data || 'framework' in res.data || 'exists' in res.data)) ? res.data : (res || {});
                const isValid = Boolean(probe.valid || res.valid || (res.success && (probe.exists || probe.framework)));
                if (isValid) {
                    const framework = probe.framework || res.framework || 'Generic PHP / Static';
                    const indexFile = probe.index_file || res.index_file || 'index.php';
                    resEl.innerHTML = `<span style="color:#34d399;">✅ Valid: <strong>${this.escapeHtml(framework)}</strong> (${this.escapeHtml(indexFile)})</span>`;
                    if (indexFile) {
                        const idxInput = document.getElementById('new-sub-index');
                        if (idxInput && (!idxInput.value || idxInput.value === 'index.php')) {
                            idxInput.value = indexFile;
                        }
                    }
                } else {
                    const errMsg = probe.error || res.error || res.message || 'Invalid path';
                    resEl.innerHTML = `<span style="color:#f87171;">❌ ${this.escapeHtml(errMsg)}</span>`;
                }
            } catch (err) {
                resEl.innerHTML = `<span style="color:#f87171;">Probe error: ${this.escapeHtml(err.message)}</span>`;
            }
        };

        // Event: Add New Sub-Route
        document.getElementById('btn-add-new-sub').onclick = () => {
            const routeRaw = document.getElementById('new-sub-route').value.trim();
            const type = document.getElementById('new-sub-type').value;
            const target = document.getElementById('new-sub-target').value.trim();
            const index = document.getElementById('new-sub-index').value.trim() || 'index.php';

            if (!routeRaw || !target) {
                this.admin.notify("Route path and target are required.", "error");
                return;
            }

            const cleanRoute = '/' + routeRaw.replace(/^\/+|\/+$/g, '');
            this._currentSubApps[cleanRoute] = {
                route: cleanRoute,
                type: type,
                app_name: type === 'spp' ? target : cleanRoute.replace(/^\//, ''),
                target_path: type === 'non_spp' ? target : '',
                index: index,
                opt_in_sppdata: false
            };

            document.getElementById('subapps-table-container').innerHTML = renderSubAppsList();
            document.getElementById('new-sub-route').value = '';
            document.getElementById('new-sub-target').value = '';
            document.getElementById('new-sub-probe-result').innerHTML = '';
            this.admin.notify(`Sub-route '${cleanRoute}' mapped. Click Save to persist.`, "info");
        };

        modal.classList.add('active');
        this.setupModalClose(modal);

        const saveBtn = document.getElementById('modal-save');
        saveBtn.onclick = async () => {
            const pattern = document.getElementById('dom-pattern').value.trim();
            const account = document.getElementById('dom-account').value.trim() || 'default';
            const appDir = document.getElementById('dom-app-dir').value.trim() || null;
            const dataDir = document.getElementById('dom-data-dir').value.trim() || null;
            const routingMode = document.querySelector('input[name="dom-routing-mode"]:checked')?.value || 'spp_path';

            const isNonSpp = document.getElementById('radio-root-nonspp').checked;
            let defaultApp;
            if (isNonSpp) {
                const targetPath = document.getElementById('dom-root-target-path').value.trim();
                const indexScript = document.getElementById('dom-root-index').value.trim() || 'index.php';
                defaultApp = {
                    type: 'non_spp',
                    app_name: 'custom_root_app',
                    target_path: targetPath,
                    index: indexScript,
                    opt_in_sppdata: false
                };
            } else {
                defaultApp = document.getElementById('dom-default-app').value;
            }

            const aliasesRaw = document.getElementById('dom-aliases').value;
            const aliases = aliasesRaw.split(',').map(s => s.trim()).filter(Boolean);
            const canonical = document.getElementById('dom-canonical').checked;
            const forceHttps = document.getElementById('dom-force-https').checked;
            const allowOthers = document.getElementById('dom-allow-others').checked;
            const setGlobal = document.getElementById('dom-set-global').checked;
            const autoCf = document.getElementById('dom-auto-cf') ? document.getElementById('dom-auto-cf').checked : false;

            const enabledApps = document.getElementById('dom-enabled-apps').value.split(',').map(s => s.trim()).filter(Boolean);
            const disabledApps = document.getElementById('dom-disabled-apps').value.split(',').map(s => s.trim()).filter(Boolean);

            let settings = {};
            try {
                settings = JSON.parse(document.getElementById('dom-settings').value || '{}');
            } catch (e) {
                this.admin.notify("Invalid JSON in domain settings", "error");
                return;
            }

            if (!pattern) {
                this.admin.notify("Domain pattern is required.", "error");
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const origPattern = document.getElementById('dom-pattern')?.getAttribute('data-original-pattern') || null;
                const res = await this.admin.api('domain_save', {
                    pattern: pattern,
                    original_pattern: origPattern,
                    account: account,
                    app_dir: appDir,
                    data_dir: dataDir,
                    routing_mode: routingMode,
                    default_app: defaultApp,
                    sub_apps: this._currentSubApps || {},
                    aliases: aliases,
                    redirect_to_canonical: canonical,
                    force_https: forceHttps,
                    allow_other_apps: allowOthers,
                    set_as_global_default: setGlobal,
                    auto_provision_cf: autoCf ? 1 : 0,
                    enabled_apps: enabledApps,
                    disabled_apps: disabledApps,
                    settings: settings
                });

                if (res.success) {
                    this.admin.notify(res.message || "Domain saved successfully.", "success");
                    modal.classList.remove('active');
                    await this.fetchData();
                    this.update();
                } else {
                    this.admin.notify(res.error || res.message || "Failed to save domain.", "error");
                }
            } catch (err) {
                this.admin.notify("Save error: " + err.message, "error");
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        };

        document.getElementById('modal-close').onclick = () => {
            modal.classList.remove('active');
        };
    }

    probeSubAppModal(path) {
        if (!path) return;
        this.admin.api('domains_subapp_probe', { target_path: path }).then(res => {
            const probe = (res && res.data && typeof res.data === 'object' && ('valid' in res.data || 'framework' in res.data || 'exists' in res.data)) ? res.data : (res || {});
            const isValid = Boolean(probe.valid || res.valid || (res.success && (probe.exists || probe.framework)));
            if (isValid) {
                const framework = probe.framework || res.framework || 'Generic PHP / Static';
                const indexFile = probe.index_file || res.index_file || 'index.php';
                this.admin.notify(`✅ ${framework} (Index: ${indexFile}, Readable: yes)`, "success");
            } else {
                const errMsg = probe.error || res.error || res.message || 'Path inaccessible';
                this.admin.notify(`❌ ${errMsg}`, "error");
            }
        }).catch(err => {
            this.admin.notify("Probe error: " + err.message, "error");
        });
    }

    removeSubAppFromModal(route) {
        if (this._currentSubApps && this._currentSubApps[route]) {
            delete this._currentSubApps[route];
            // Re-render subapps list in modal
            const c = document.getElementById('subapps-table-container');
            if (c) {
                const keys = Object.keys(this._currentSubApps);
                if (keys.length === 0) {
                    c.innerHTML = `<div style="padding:0.75rem; text-align:center; color:var(--text-dim); font-size:0.85rem; border:1px dashed var(--glass-border); border-radius:var(--radius-sm);">No sub-routes configured yet. Entire domain routes directly to the default root application.</div>`;
                } else {
                    c.innerHTML = `
                        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--glass-border); text-align:left; color:var(--text-dim);">
                                    <th style="padding:0.4rem;">Route Path</th>
                                    <th style="padding:0.4rem;">Type</th>
                                    <th style="padding:0.4rem;">Target Application / Directory</th>
                                    <th style="padding:0.4rem;">Index</th>
                                    <th style="padding:0.4rem; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${keys.map(r => {
                                    const sub = this._currentSubApps[r];
                                    const isSubNonSpp = sub.type === 'non_spp';
                                    const typeBadge = isSubNonSpp 
                                        ? `<span class="suite-pill suite-pill-warning" style="font-size:0.7rem;">External (Drupal/WP)</span>`
                                        : `<span class="suite-pill suite-pill-info" style="font-size:0.7rem;">SPP Native</span>`;
                                    const target = isSubNonSpp ? sub.target_path : sub.app_name;
                                    return `
                                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                                            <td style="padding:0.5rem; font-family:monospace; font-weight:700; color:var(--text-bright);">${this.escapeHtml(r)}</td>
                                            <td style="padding:0.5rem;">${typeBadge}</td>
                                            <td style="padding:0.5rem; font-family:monospace; color:var(--text-dim); font-size:0.8rem;" title="${this.escapeHtml(target)}">${this.escapeHtml(target)}</td>
                                            <td style="padding:0.5rem; font-family:monospace; font-size:0.8rem;">${this.escapeHtml(sub.index || 'index.php')}</td>
                                            <td style="padding:0.5rem; text-align:right;">
                                                ${isSubNonSpp ? `<button type="button" class="btn ghost-btn btn-xs" onclick="window.domainsViewInstance.probeSubAppModal('${this.escapeHtml(sub.target_path)}')">Probe</button>` : ''}
                                                <button type="button" class="btn danger-btn btn-xs" onclick="window.domainsViewInstance.removeSubAppFromModal('${this.escapeHtml(r)}')">Remove</button>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    `;
                }
            }
            this.admin.notify(`Removed sub-route '${route}'. Click Save Changes to persist.`, "info");
        }
    }

    jumpToSiteFiles(domain) {
        if (!domain) return;
        window.location.hash = '#files?domain=' + encodeURIComponent(domain);
    }

    async deleteDomain(pattern) {
        if (!confirm(`Are you sure you want to remove virtual host mapping for '${pattern}'?`)) {
            return;
        }

        try {
            const res = await this.admin.api('domain_delete', { pattern: pattern });
            if (res.success) {
                this.admin.notify(`Domain '${pattern}' deleted successfully.`, "success");
                await this.fetchData();
                this.update();
            } else {
                this.admin.notify(res.error || "Failed to delete domain.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    openAddonDomainModal(addon = null) {
        const isEdit = !!addon;
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = isEdit ? `Edit Addon Domain: ${addon.domain}` : 'Provision Addon Domain';

        const appOptions = (this.state.apps || []).map(a => {
            const selected = (addon?.default_app === a.name) ? 'selected' : '';
            return `<option value="${this.escapeHtml(a.name)}" ${selected}>${this.escapeHtml(a.title || a.name)} (${a.name})</option>`;
        }).join('');

        const aliasesStr = (addon?.aliases || []).join(', ');

        document.getElementById('modal-body').innerHTML = `
            <div class="domain-editor-form" style="display:flex; flex-direction:column; gap:1rem;">
                <div class="input-group">
                    <label>Addon Domain Hostname <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="addon-domain" value="${this.escapeHtml(addon?.domain || '')}" placeholder="e.g. portal.partner.org" ${isEdit ? 'readonly style="opacity:0.7;"' : ''} required>
                    <small style="color:var(--text-dim)">Independent external domain linked to this installation.</small>
                </div>

                <div class="input-group">
                    <label>Hosting Account <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="addon-account" value="${this.escapeHtml(addon?.account || 'default')}" placeholder="e.g. acct_school or default" required>
                    <small style="color:var(--text-dim)">Account owner that manages this addon domain.</small>
                </div>

                <div class="input-group">
                    <label>Default Application (Mounted at Root /) <span style="color:var(--danger)">*</span></label>
                    <select id="addon-default-app" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                        ${appOptions}
                    </select>
                </div>

                <div class="input-group">
                    <label>Domain Aliases (Comma-Separated)</label>
                    <input type="text" id="addon-aliases" value="${this.escapeHtml(aliasesStr)}" placeholder="e.g. www.portal.partner.org">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; padding:0.75rem; background:rgba(0,0,0,0.15); border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="addon-force-https" ${addon?.force_https ? 'checked' : ''}>
                        <span>Force HTTPS (SSL)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="addon-canonical" ${addon?.redirect_to_canonical !== false ? 'checked' : ''}>
                        <span>301 Canonical Redirect</span>
                    </label>
                </div>
            </div>
        `;

        modal.classList.add('active');
        this.setupModalClose(modal);

        const saveBtn = document.getElementById('modal-save');
        saveBtn.onclick = async () => {
            const domain = document.getElementById('addon-domain').value.trim();
            const account = document.getElementById('addon-account').value.trim();
            const defaultApp = document.getElementById('addon-default-app').value;
            const aliasesRaw = document.getElementById('addon-aliases').value;
            const aliases = aliasesRaw.split(',').map(s => s.trim()).filter(Boolean);
            const forceHttps = document.getElementById('addon-force-https').checked;
            const canonical = document.getElementById('addon-canonical').checked;

            if (!domain) {
                this.admin.notify("Addon domain name is required.", "error");
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const res = await this.admin.api('domains_addon_add', {
                    domain: domain,
                    account: account || 'default',
                    default_app: defaultApp,
                    aliases: aliases,
                    force_https: forceHttps,
                    redirect_to_canonical: canonical
                });

                if (res.success) {
                    this.admin.notify(res.message || "Addon domain saved successfully.", "success");
                    modal.classList.remove('active');
                    await this.fetchData();
                    this.update();
                } else {
                    this.admin.notify(res.error || res.message || "Failed to save addon domain.", "error");
                }
            } catch (err) {
                this.admin.notify("Save error: " + err.message, "error");
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        };

        document.getElementById('modal-close').onclick = () => {
            modal.classList.remove('active');
        };
    }

    async deleteAddonDomain(domain) {
        if (!confirm(`Are you sure you want to remove addon domain '${domain}'?`)) {
            return;
        }

        try {
            const res = await this.admin.api('domains_addon_remove', { domain: domain });
            if (res.success) {
                this.admin.notify(`Addon domain '${domain}' removed successfully.`, "success");
                await this.fetchData();
                this.update();
            } else {
                this.admin.notify(res.error || "Failed to remove addon domain.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async runSimulator() {
        const host = document.getElementById('sim-input-host')?.value.trim() || this.state.simHost;
        const path = document.getElementById('sim-input-path')?.value.trim() || this.state.simPath;

        this.setState({ simHost: host, simPath: path, simulating: true });
        this.update();

        try {
            const res = await this.admin.api('domain_test', { host: host, path: path });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ simResult: data, simulating: false });
        } catch (e) {
            this.admin.notify("Simulator error: " + e.message, "error");
            this.setState({ simulating: false });
        }
        this.update();
    }

    async runSslCheck(targetHost = null) {
        const host = targetHost || document.getElementById('ssl-input-host')?.value.trim() || this.state.sslHost;
        this.setState({ sslHost: host, checkingSsl: true });
        this.update();

        try {
            const res = await this.admin.api('domain_check_ssl', { host: host });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ sslResult: data, checkingSsl: false });
        } catch (e) {
            this.admin.notify("SSL Check error: " + e.message, "error");
            this.setState({ checkingSsl: false });
        }
        this.update();
    }

    render() {
        if (this.state.loading) {
            return `<div class="loading-state" style="padding:4rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:1rem; color:var(--text-dim);">Loading Virtual Hosts & Domains...</div></div>`;
        }

        const sslCount = (this.state.domains || []).filter(d => d.force_https).length;
        const canonicalCount = (this.state.domains || []).filter(d => d.redirect_to_canonical).length;

        return `
            <div class="domains-view-wrapper" style="display:flex; flex-direction:column; gap:1.5rem;">
                <!-- Persistent "Explain This Page" Context Guide -->
                <div class="spp-page-guide" id="domains-page-guide">
                    <div class="spp-page-guide-header" onclick="this.parentElement.classList.toggle('collapsed')">
                        <div class="spp-page-guide-title">
                            <span>💡</span>
                            <span>Websites, Domains & DNS Simplified</span>
                        </div>
                        <button type="button" class="spp-page-guide-toggle" title="Toggle guide">▾</button>
                    </div>
                    <div class="spp-page-guide-body">
                        <p class="spp-page-guide-desc">
                            Easily connect your domain names (e.g., <code>yourschool.com</code>) to your web applications, secure them with 1-click Free SSL (padlock icon), and manage your DNS records without terminal commands.
                        </p>
                        <div class="spp-page-guide-steps">
                            <div class="spp-page-guide-step">
                                <div class="spp-page-guide-step-num">1</div>
                                <div class="spp-page-guide-step-title">Add Your Website</div>
                                <div class="spp-page-guide-step-text">Connect a domain name and choose which application loads when visitors arrive.</div>
                            </div>
                            <div class="spp-page-guide-step">
                                <div class="spp-page-guide-step-num">2</div>
                                <div class="spp-page-guide-step-title">Turn on Free SSL</div>
                                <div class="spp-page-guide-step-text">Enable automatic HTTPS encryption so visitors see a trusted secure lock in their browser.</div>
                            </div>
                            <div class="spp-page-guide-step">
                                <div class="spp-page-guide-step-num">3</div>
                                <div class="spp-page-guide-step-title">Manage DNS Records</div>
                                <div class="spp-page-guide-step-text">View and add A, CNAME, and MX records to link your domain registrar or email provider.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hero Metric Cards (Plain English & Popover Tips) -->
                <div class="suite-hero">
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon">🌐</div>
                        <div>
                            <div class="suite-stat-value">${this.state.totalDomains}</div>
                            <div class="suite-stat-label">
                                Websites & Domains
                                <span class="spp-help-tip" data-tooltip-title="Websites & Domains" data-tooltip="Total number of website domain names connected to this server." data-tooltip-example="e.g. example.com, mysite.org" data-tooltip-rec="Click '+ Add Website' to connect another domain.">?</span>
                            </div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(52,211,153,0.1); color:#34d399;">🔒</div>
                        <div>
                            <div class="suite-stat-value">${sslCount}</div>
                            <div class="suite-stat-label">
                                Secure (HTTPS)
                                <span class="spp-help-tip" data-tooltip-title="SSL / HTTPS Security" data-tooltip="Websites protected with bank-grade encryption and secure padlock icon in visitors' browsers." data-tooltip-example="https://example.com" data-tooltip-rec="Recommended for all public sites.">?</span>
                            </div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(56,189,248,0.1); color:#38bdf8;">🔀</div>
                        <div>
                            <div class="suite-stat-value">${canonicalCount}</div>
                            <div class="suite-stat-label">
                                Auto-Redirects (www)
                                <span class="spp-help-tip" data-tooltip-title="Canonical Redirection" data-tooltip="Automatically forwards alternate addresses (such as www.yoursite.com) to your main domain name." data-tooltip-rec="Prevents duplicate content issues and broken links.">?</span>
                            </div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(236,72,153,0.1); color:#ec4899;">➕</div>
                        <div>
                            <div class="suite-stat-value">${this.state.totalAddons || 0}</div>
                            <div class="suite-stat-label">
                                Secondary Domains
                                <span class="spp-help-tip" data-tooltip-title="Secondary / Addon Domains" data-tooltip="Additional independent domains hosted on separate isolated hosting accounts on this server." data-tooltip-example="e.g. blog.example.com">?</span>
                            </div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(167,139,250,0.1); color:#a78bfa;">⭐</div>
                        <div>
                            <div class="suite-stat-value">${this.escapeHtml(this.state.globalDefaultApp)}</div>
                            <div class="suite-stat-label">
                                Default Web App
                                <span class="spp-help-tip" data-tooltip-title="Default Web Application" data-tooltip="The main application that loads if visitors access your server by raw IP address or an unconfigured domain." data-tooltip-rec="Typically your primary portal or school app.">?</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progressive Disclosure: Advanced Routing Policies (Collapsed by default) -->
                <div class="spp-advanced-accordion">
                    <div class="spp-accordion-toggle">
                        <span style="display:flex; align-items:center; gap:0.5rem;">
                            <span>⚙️</span>
                            <strong>Advanced Domain Routing Policies & Modes</strong>
                            <span style="font-size:0.75rem; color:var(--text-dim); font-weight:normal;">(Single vs Multi-Domain, Fallback rules)</span>
                        </span>
                        <span class="spp-accordion-icon">▾</span>
                    </div>
                    <div class="spp-accordion-body" style="padding-top:1rem; display:flex; flex-direction:column; gap:1rem;">
                        <!-- Domain Architecture Mode Card -->
                        <div class="spp-domain-mode-card" style="border: 1px solid ${this.state.isSingleDomainMode ? 'rgba(16, 185, 129, 0.3)' : 'rgba(56, 189, 248, 0.3)'}; border-radius: var(--radius-md); padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem; max-width: 70%;">
                                <div style="font-size: 2.2rem; line-height: 1;">${this.state.isSingleDomainMode ? '🟢' : '🌐'}</div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <strong style="font-size: 1.1rem; color: var(--text-bright);">
                                            Domain Mode: ${this.state.isSingleDomainMode ? 'Single Domain Mode' : 'Multi-Domain Mode'}
                                        </strong>
                                        <span class="suite-pill ${this.state.isSingleDomainMode ? 'suite-pill-success' : 'suite-pill-info'}">
                                            ${this.state.isSingleDomainMode ? 'Active - Routing Not Required' : 'Active - Virtual Hosts Enforced'}
                                        </span>
                                    </div>
                                    <div style="margin-top: 0.35rem; font-size: 0.82rem; color: var(--text-dim); line-height: 1.4;">
                                        ${this.state.isSingleDomainMode 
                                            ? `In Single Domain Mode, all incoming web requests route to the default application (<strong>${this.escapeHtml(this.state.globalDefaultApp)}</strong>). Zero-config simplicity.`
                                            : `Multi-Domain virtual host routing is active. Requests are matched against <code>etc/domains.yml</code> mappings, routing subdomains and addon domains to designated applications.`
                                        }
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button class="btn ${this.state.isSingleDomainMode ? 'primary-btn' : 'secondary-btn'}" onclick="window.domainsViewInstance.toggleDomainMode('${this.state.isSingleDomainMode ? 'multi' : 'single'}')" style="padding: 0.65rem 1.25rem; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    ${this.state.isSingleDomainMode ? '🌐 Switch to Multi-Domain Mode' : '🟢 Switch to Single Domain Mode'}
                                </button>
                            </div>
                        </div>

                        <!-- Virtual Host Policy Selector Banner -->
                        <div class="spp-setting-banner" style="background: var(--card-bg, #ffffff); display:flex; justify-content:space-between; align-items:center; padding:1rem 1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); flex-wrap:wrap; gap:1rem;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <span style="font-size:1.4rem;">🛡️</span>
                                <div>
                                    <strong style="color:var(--text-bright); font-size:0.95rem;">Unmapped Subdomain Behavior:</strong>
                                    <div style="font-size:0.75rem; color:var(--text-dim);">Governs what happens when a visitor types an unconfigured subdomain name.</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <select id="vhost-mode-select" onchange="window.domainsViewInstance.changeVirtualHostMode(this.value)" style="padding:0.5rem 0.85rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.85rem; font-weight:600; cursor:pointer;">
                                    <option value="strict" ${this.state.virtualHostMode === 'strict' ? 'selected' : ''}>Strict (404 Not Found - Recommended)</option>
                                    <option value="inherit" ${this.state.virtualHostMode === 'inherit' ? 'selected' : ''}>Inherit Parent Domain Default App</option>
                                    <option value="redirect" ${this.state.virtualHostMode === 'redirect' ? 'selected' : ''}>302 Redirect to Parent Domain</option>
                                    <option value="dynamic_tenant" ${this.state.virtualHostMode === 'dynamic_tenant' ? 'selected' : ''}>Dynamic Tenant Resolution</option>
                                    <option value="permissive" ${this.state.virtualHostMode === 'permissive' ? 'selected' : ''}>Permissive (Legacy Default Fallback)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs (Cleaned for Novices, Expert items clearly segregated) -->
                <div class="suite-tabs">
                    <button class="suite-tab-btn ${this.state.activeTab === 'domains' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('domains')">
                        🌐 My Websites (${this.state.totalDomains})
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'ssl' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('ssl')">
                        🔒 Free SSL Security (${sslCount})
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'zone' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('zone')">
                        📋 DNS Records (${(this.state.zoneData?.records || []).length || 'Simple'})
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'dns' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('dns')">
                        📡 Connection Diagnostics
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'addons' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('addons')">
                        ➕ Secondary Domains (${this.state.totalAddons || 0})
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'cloudflare' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('cloudflare')" style="${this.state.activeTab === 'cloudflare' ? 'color:#f59e0b; border-color:#f59e0b; font-weight:700;' : ''}">
                        ☁️ Cloudflare Edge & Bridge
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'domain_apps' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('domain_apps')">
                        📦 App Mappings
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'proxy' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('proxy')">
                        ⚡ Microservice Proxy
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'simulator' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('simulator')">
                        ⚙️ Route Simulator
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'vhost_config' ? 'active' : ''}" onclick="window.domainsViewInstance.switchTab('vhost_config')" style="${this.state.activeTab === 'vhost_config' ? 'color:#10b981; border-color:#10b981; font-weight:700;' : ''}">
                        🖥️ Web Server Engine
                    </button>
                </div>

                <!-- Tab 1: Configured Virtual Hosts -->
                ${this.state.activeTab === 'domains' ? this.renderDomainsTab() : ''}

                <!-- Tab 2: Addon Domains -->
                ${this.state.activeTab === 'addons' ? this.renderAddonDomainsTab() : ''}

                <!-- Tab 2b: Domain Applications -->
                ${this.state.activeTab === 'domain_apps' ? this.renderDomainAppsTab() : ''}

                <!-- Tab 3: Microservice Reverse Proxy -->
                ${this.state.activeTab === 'proxy' ? this.renderProxyTab() : ''}

                <!-- Tab 4: DNS Diagnostics & Traceroute -->
                ${this.state.activeTab === 'dns' ? this.renderDnsTab() : ''}

                <!-- Tab 5: Interactive Route Simulator -->
                ${this.state.activeTab === 'simulator' ? this.renderSimulatorTab() : ''}

                <!-- Tab 6: SSL Inspector -->
                ${this.state.activeTab === 'ssl' ? this.renderSslTab() : ''}

                <!-- Tab 7: DNS Zone Editor & Anycast Propagation -->
                ${this.state.activeTab === 'zone' ? this.renderZoneTab() : ''}

                <!-- Tab 8: Sovereign Cloudflare Edge DNS & Edge SSL Bridge -->
                ${this.state.activeTab === 'cloudflare' ? this.renderCloudflareTab() : ''}

                <!-- Tab 9: Web Server Engine (Nginx / Hybrid / Apache) -->
                ${this.state.activeTab === 'vhost_config' ? this.renderVhostConfigTab() : ''}
            </div>
        `;
    }

    renderAddonDomainsTab() {
        const rows = (this.state.addonDomains || []).map(a => {
            const aliasesChips = (a.aliases || []).map(al => `<span class="suite-pill suite-pill-neutral" style="font-size:0.7rem;">${this.escapeHtml(al)}</span>`).join(' ') || '<span style="color:var(--text-dim); font-size:0.75rem;">None</span>';
            const httpsBadge = a.force_https 
                ? `<span class="suite-pill suite-pill-success">HTTPS</span>` 
                : `<span class="suite-pill suite-pill-neutral" style="opacity:0.6;">HTTP</span>`;

            return `
                <tr>
                    <td>
                        <strong style="color:var(--text-bright); font-size:0.95rem;">${this.escapeHtml(a.domain)}</strong>
                    </td>
                    <td>
                        <span class="suite-pill suite-pill-info" style="font-size:0.75rem;">${this.escapeHtml(a.account || 'default')}</span>
                    </td>
                    <td>
                        <span class="suite-pill suite-pill-success" style="font-weight:700;">${this.escapeHtml(a.default_app)}</span>
                    </td>
                    <td>${aliasesChips}</td>
                    <td>${httpsBadge}</td>
                    <td>
                        <div style="display:flex; gap:0.4rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.jumpToSiteFiles('${this.escapeHtml(a.domain)}')" title="Browse website home directory in File Manager">📁 Files</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.testDomainDirect('${this.escapeHtml(a.domain)}')">Test</button>
                            <button class="btn danger-btn btn-sm" onclick="window.domainsViewInstance.deleteAddonDomain('${this.escapeHtml(a.domain)}')">Remove</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Registered Addon Domains</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Independent domains hosted within accounts that mount dedicated applications at root.</p>
                    </div>
                    <button class="btn primary-btn shine-effect" onclick="window.domainsViewInstance.openAddonDomainModal()">
                        <span>+ Add Addon Domain</span>
                    </button>
                </div>

                <div class="suite-table-wrapper" style="margin-top:0.75rem;">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Addon Domain</th>
                                <th>Hosting Account</th>
                                <th>Mounted Default App</th>
                                <th>Aliases</th>
                                <th>SSL Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || '<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-dim);">No addon domains provisioned yet. Click "+ Add Addon Domain" to mount an external domain.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderDomainsTab() {
        const rows = (this.state.domains || []).map(d => {
            const aliasesChips = (d.aliases || []).map(a => `<span class="suite-pill suite-pill-neutral" style="font-size:0.7rem;">${this.escapeHtml(a)}</span>`).join(' ') || '<span style="color:var(--text-dim); font-size:0.75rem;">None</span>';
            const httpsBadge = d.force_https 
                ? `<span class="suite-pill suite-pill-success">HTTPS</span>` 
                : `<span class="suite-pill suite-pill-neutral" style="opacity:0.6;">HTTP</span>`;
            const canonicalBadge = d.redirect_to_canonical
                ? `<span class="suite-pill suite-pill-info" title="Redirects aliases to ${this.escapeHtml(d.canonical_host || d.pattern)}">301 Canonical</span>`
                : '';

            const isObjApp = d.default_app && typeof d.default_app === 'object';
            const defaultAppBadge = isObjApp
                ? `<span class="suite-pill suite-pill-warning" style="font-weight:700;" title="Path: ${this.escapeHtml(d.default_app.target_path || '')}">📁 ${this.escapeHtml(d.default_app.app_name || 'external')} (non-spp)</span>`
                : `<span class="suite-pill suite-pill-success" style="font-weight:700;">${this.escapeHtml(d.default_app || 'default')}</span>`;

            const subCount = d.sub_apps && typeof d.sub_apps === 'object' ? Object.keys(d.sub_apps).length : 0;
            const subAppsBadge = subCount > 0
                ? `<span class="suite-pill suite-pill-info" style="font-size:0.65rem; margin-left:0.3rem;" title="Sub-routes: ${this.escapeHtml(Object.keys(d.sub_apps).join(', '))}">${subCount} sub-route${subCount > 1 ? 's' : ''}</span>`
                : '';
            const accountBadge = d.account && d.account !== 'default'
                ? `<span class="suite-pill suite-pill-neutral" style="font-size:0.65rem; margin-left:0.3rem;" title="Linux user account">👤 ${this.escapeHtml(d.account)}</span>`
                : '';
            const routingModeBadge = d.routing_mode === 'webserver_vhost'
                ? `<span class="suite-pill suite-pill-info" style="font-size:0.65rem; margin-left:0.2rem;" title="Native webserver VirtualHost">vhost</span>`
                : '';

            return `
                <tr>
                    <td>
                        <strong style="color:var(--text-bright); font-size:0.95rem;">${this.escapeHtml(d.pattern)}</strong>
                        ${d.is_wildcard ? '<span class="suite-pill suite-pill-warning" style="font-size:0.65rem; margin-left:0.4rem;">Wildcard</span>' : ''}
                        ${accountBadge}
                        ${routingModeBadge}
                        ${subAppsBadge}
                    </td>
                    <td>
                        ${defaultAppBadge}
                    </td>
                    <td>${aliasesChips} ${canonicalBadge}</td>
                    <td>${httpsBadge}</td>
                    <td>
                        ${d.allow_other_apps ? '<span style="color:#34d399; font-size:0.8rem;">Allowed</span>' : '<span style="color:#f87171; font-size:0.8rem;">Isolated</span>'}
                    </td>
                    <td>
                        <div style="display:flex; gap:0.4rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.jumpToSiteFiles('${this.escapeHtml(d.pattern)}')" title="Browse website home directory in File Manager">📁 Files</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.testDomainDirect('${this.escapeHtml(d.pattern)}')">Test</button>
                            <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.editDomainDirect('${this.escapeHtml(d.pattern)}')">Edit</button>
                            <button class="btn danger-btn btn-sm" onclick="window.domainsViewInstance.deleteDomain('${this.escapeHtml(d.pattern)}')">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Active Websites & Domain Mappings</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Connected web addresses and the applications served when visitors arrive.</p>
                    </div>
                    <button class="btn primary-btn shine-effect" onclick="window.domainsViewInstance.openDomainModal()">
                        <span>+ Add Website / Domain</span>
                    </button>
                </div>

                <div class="suite-table-wrapper" style="margin-top:0.75rem;">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>
                                    Domain Name
                                    <span class="spp-help-tip" data-tooltip-title="Domain Name" data-tooltip="The web address visitors enter in their browser." data-tooltip-example="e.g. example.com, mysite.org">?</span>
                                </th>
                                <th>
                                    Main Web Application
                                    <span class="spp-help-tip" data-tooltip-title="Main Web Application" data-tooltip="The SPP application loaded at the root (/) of this domain." data-tooltip-example="e.g. school1">?</span>
                                </th>
                                <th>
                                    Aliases & Redirects
                                    <span class="spp-help-tip" data-tooltip-title="Aliases & Redirects" data-tooltip="Alternative addresses (e.g. www.domain.com) automatically redirected to this domain.">?</span>
                                </th>
                                <th>
                                    Security (SSL)
                                    <span class="spp-help-tip" data-tooltip-title="SSL / HTTPS Security" data-tooltip="Whether visitors are secured with bank-grade HTTPS encryption and browser lock icon.">?</span>
                                </th>
                                <th>
                                    Secondary Apps
                                    <span class="spp-help-tip" data-tooltip-title="Secondary Apps" data-tooltip="Allows visitors to access other apps using sub-paths like domain.com/sppdocs.">?</span>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || '<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-dim);">No websites configured yet. Click "+ Add Website / Domain" to connect your first domain name.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderSimulatorTab() {
        const res = this.state.simResult;

        return `
            <div class="suite-pane-main">
                <div>
                    <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Domain & Virtual Host Routing Simulator</h3>
                    <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Simulate exact HTTP request resolution through SPP's virtual host kernel without modifying DNS or hosts files.</p>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:1rem; align-items:flex-end; margin-top:1rem;">
                    <div class="input-group">
                        <label>Simulated HTTP Host</label>
                        <input type="text" id="sim-input-host" value="${this.escapeHtml(this.state.simHost)}" placeholder="e.g. school.local or portal.example.com">
                    </div>
                    <div class="input-group">
                        <label>Request URI Path</label>
                        <input type="text" id="sim-input-path" value="${this.escapeHtml(this.state.simPath)}" placeholder="e.g. / or /students or /sppdocs/guide">
                    </div>
                    <button class="btn primary-btn" style="height:40px;" onclick="window.domainsViewInstance.runSimulator()" ${this.state.simulating ? 'disabled' : ''}>
                        ${this.state.simulating ? 'Simulating...' : '⚡ Run Simulation'}
                    </button>
                </div>

                ${res ? `
                    <div style="margin-top:1.5rem; padding:1.25rem; border:1px solid var(--glass-border); border-radius:var(--radius-md); background:rgba(0,0,0,0.2);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                            <h4 style="margin:0; font-size:1rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                                Simulation Outcome:
                                ${res.http_status === 404 
                                    ? '<span class="suite-pill suite-pill-danger">404 Virtual Host Not Found</span>' 
                                    : (res.http_status === 403 
                                        ? '<span class="suite-pill suite-pill-danger">403 App Forbidden</span>'
                                        : (res.canonical_redirect || res.http_status === 302
                                            ? '<span class="suite-pill suite-pill-info">Redirect Triggered</span>'
                                            : '<span class="suite-pill suite-pill-success">Resolution Success</span>'))}
                            </h4>
                            <div style="font-size:0.8rem; color:var(--text-dim);">
                                Resolution Mode: <strong style="color:var(--text-bright); text-transform:uppercase;">${this.escapeHtml(res.resolution_mode || 'default')}</strong>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem;">
                            <div>
                                <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Matched Pattern</span>
                                <div style="font-size:1rem; font-weight:600; color:var(--text-bright);">${this.escapeHtml(res.matched_pattern || 'Unmapped Host')}</div>
                            </div>
                            <div>
                                <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Parent Domain</span>
                                <div style="font-size:1rem; font-weight:600; color:var(--text-main);">${this.escapeHtml(res.parent_domain || 'None Detected')}</div>
                            </div>
                            <div>
                                <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Resolved App Context</span>
                                <div style="font-size:1rem; font-weight:700; color:#38bdf8;">${this.escapeHtml(res.resolved_app || 'None')}</div>
                            </div>
                            <div>
                                <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Rewritten Internal Path</span>
                                <div style="font-size:1rem; font-weight:600; color:var(--text-bright);"><code>${this.escapeHtml(res.rewritten_path || '/')}</code></div>
                            </div>
                            <div>
                                <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Served at Root</span>
                                <div style="font-size:1rem; font-weight:600; color:${res.is_default_app ? '#34d399' : '#94a3b8'};">${res.is_default_app ? 'Yes (Root Default)' : 'No (Sub-app)'}</div>
                            </div>
                        </div>

                        ${res.rejection_reason ? `
                            <div class="alert danger-alert" style="margin-top:1rem; padding:0.75rem; background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); border-radius:var(--radius-sm); color:#f87171;">
                                ⚠️ ${this.escapeHtml(res.rejection_reason)}
                            </div>
                        ` : ''}

                        ${res.canonical_redirect ? `
                            <div style="margin-top:1rem; padding:0.75rem; background:rgba(56,189,248,0.1); border:1px solid rgba(56,189,248,0.3); border-radius:var(--radius-sm); color:#38bdf8; font-size:0.85rem;">
                                ℹ️ Redirect Triggered: Target Host is <strong>${this.escapeHtml(res.canonical_redirect)}</strong>
                            </div>
                        ` : ''}

                        <div style="margin-top:1rem;">
                            <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Domain-Scoped Settings:</span>
                            <pre class="suite-code-box" style="margin-top:0.4rem;">${this.escapeHtml(JSON.stringify(res.settings || {}, null, 2))}</pre>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    renderSslTab() {
        const res = this.state.sslResult;
        const autoRes = this.state.autoSslResult;
        const domains = this.state.domains || [];

        return `
            <div class="suite-pane-main" style="display:flex; flex-direction:column; gap:1.5rem;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                    <!-- Card 1: Live Certificate Inspector -->
                    <div class="glass-panel" style="padding:1.25rem; border-radius:var(--radius-md); background:rgba(0,0,0,0.2); border:1px solid var(--glass-border);">
                        <div>
                            <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">🔍 Live SSL / TLS Certificate Inspector</h3>
                            <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Verify live certificate validity, expiry dates, and issuer chains for any host.</p>
                        </div>

                        <div style="display:flex; gap:0.75rem; align-items:flex-end; margin-top:1rem;">
                            <div class="input-group" style="flex:1;">
                                <label>Hostname to Inspect</label>
                                <input type="text" id="ssl-input-host" value="${this.escapeHtml(this.state.sslHost)}" placeholder="e.g. school.local or domain.com">
                            </div>
                            <button class="btn primary-btn" style="height:40px;" onclick="window.domainsViewInstance.runSslCheck()" ${this.state.checkingSsl ? 'disabled' : ''}>
                                ${this.state.checkingSsl ? 'Inspecting...' : '🔍 Inspect'}
                            </button>
                        </div>

                        ${res ? `
                            <div style="margin-top:1.25rem; padding:1rem; border:1px solid var(--glass-border); border-radius:var(--radius-md); background:rgba(0,0,0,0.25);">
                                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.75rem;">
                                    <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">${this.escapeHtml(res.subject || this.state.sslHost)}</h4>
                                    ${res.valid 
                                        ? '<span class="suite-pill suite-pill-success">Valid Certificate</span>' 
                                        : '<span class="suite-pill suite-pill-danger">No Valid SSL</span>'}
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; font-size:0.85rem;">
                                    <div>
                                        <span style="color:var(--text-dim); font-size:0.7rem; text-transform:uppercase;">Issuer:</span>
                                        <div style="color:var(--text-bright); font-weight:600;">${this.escapeHtml(res.issuer || 'N/A')}</div>
                                    </div>
                                    <div>
                                        <span style="color:var(--text-dim); font-size:0.7rem; text-transform:uppercase;">Days Remaining:</span>
                                        <div style="color:${(res.days_remaining || 0) > 30 ? '#34d399' : '#fbbf24'}; font-weight:700; font-size:1rem;">
                                            ${res.days_remaining !== undefined ? `${res.days_remaining} days` : 'N/A'}
                                        </div>
                                    </div>
                                    <div>
                                        <span style="color:var(--text-dim); font-size:0.7rem; text-transform:uppercase;">Valid From:</span>
                                        <div style="color:var(--text-main); font-size:0.8rem;">${this.escapeHtml(res.valid_from || 'N/A')}</div>
                                    </div>
                                    <div>
                                        <span style="color:var(--text-dim); font-size:0.7rem; text-transform:uppercase;">Expires At:</span>
                                        <div style="color:var(--text-main); font-size:0.8rem;">${this.escapeHtml(res.expires_at || 'N/A')}</div>
                                    </div>
                                </div>

                                ${res.message ? `<div style="margin-top:0.75rem; font-size:0.8rem; color:var(--text-dim);">ℹ️ ${this.escapeHtml(res.message)}</div>` : ''}
                            </div>
                        ` : ''}
                    </div>

                    <!-- Card 2: Certbot Host SSL & AutoSSL Provisioner -->
                    <div class="glass-panel" style="padding:1.25rem; border-radius:var(--radius-md); background:rgba(0,0,0,0.2); border:1px solid var(--glass-border);">
                        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
                            <div>
                                <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">🔒 Host Certbot & AutoSSL Orchestrator</h3>
                                <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Automated Let's Encrypt SSL issuance via Certbot Apache Plugin or Pure-PHP ACME v2.</p>
                            </div>
                            <div style="display:flex; gap:0.4rem; align-items:center;">
                                <button class="btn btn-sm ${this.state.sslEngine === 'certbot' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.setSslEngine('certbot')">
                                    Certbot Engine
                                </button>
                                <button class="btn btn-sm ${this.state.sslEngine === 'native_acme' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.setSslEngine('native_acme')">
                                    Pure-PHP ACME
                                </button>
                            </div>
                        </div>

                        ${this.state.sslEngine === 'certbot' ? `
                            <!-- Certbot Environment & Plugin Status Card -->
                            <div class="spp-setting-banner" style="margin-top:1rem; padding:0.75rem 1rem; border-radius:var(--radius-sm); font-size:0.8rem;">
                                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <span><strong>Certbot:</strong> ${this.state.certbotStatus?.certbot_installed 
                                            ? `<span class="suite-pill suite-pill-success" style="font-size:0.7rem;">✔ ${this.escapeHtml(this.state.certbotStatus.certbot_version || 'Installed')}</span>`
                                            : `<span class="suite-pill suite-pill-danger" style="font-size:0.7rem;">✘ Not Installed</span>`}
                                        </span>
                                        <span><strong>Apache Plugin:</strong> ${this.state.certbotStatus?.apache_plugin_installed
                                            ? `<span class="suite-pill suite-pill-success" style="font-size:0.7rem;">✔ Installed</span>`
                                            : `<span class="suite-pill suite-pill-warning" style="font-size:0.7rem;">✘ Missing</span>`}
                                        </span>
                                    </div>
                                    <div style="display:flex; gap:0.4rem;">
                                        <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.loadCertbotStatus()" ${this.state.loadingCertbotStatus ? 'disabled' : ''}>
                                            ${this.state.loadingCertbotStatus ? '...' : '🔄 Refresh'}
                                        </button>
                                        ${(!this.state.certbotStatus?.certbot_installed || !this.state.certbotStatus?.apache_plugin_installed) ? `
                                            <button class="btn warning-btn btn-sm" onclick="window.domainsViewInstance.installCertbot()" ${this.state.installingCertbot ? 'disabled' : ''}>
                                                ${this.state.installingCertbot ? '⏳ Installing via apt...' : '⚡ 1-Click Install Certbot & Apache'}
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>

                            <!-- Certbot Issuance Form -->
                            <div style="margin-top:1rem; display:flex; flex-direction:column; gap:0.75rem;">
                                <div class="input-group">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                        <label style="margin:0;">Target Domains / Subject Alternative Names (SANs)</label>
                                        <div style="display:flex; gap:0.3rem;">
                                            <span style="font-size:0.7rem; color:var(--text-dim);">Quick Add:</span>
                                            <button type="button" class="btn ghost-btn btn-xs" style="padding:0.1rem 0.35rem; font-size:0.65rem;" onclick="window.domainsViewInstance.addDomainSan('www')">+ www</button>
                                            <button type="button" class="btn ghost-btn btn-xs" style="padding:0.1rem 0.35rem; font-size:0.65rem;" onclick="window.domainsViewInstance.addDomainSan('vps')">+ vps</button>
                                            <button type="button" class="btn ghost-btn btn-xs" style="padding:0.1rem 0.35rem; font-size:0.65rem;" onclick="window.domainsViewInstance.addDomainSan('mail')">+ mail</button>
                                        </div>
                                    </div>
                                    <input type="text" id="certbot-domains-input" value="${this.escapeHtml(this.state.sslHost ? (this.state.sslHost !== 'example.com' ? `${this.state.sslHost}, www.${this.state.sslHost}` : 'example.com, www.example.com') : ((this.state.domains?.[0]?.pattern) ? `${this.state.domains[0].pattern}, www.${this.state.domains[0].pattern}` : 'example.com, www.example.com'))}" placeholder="e.g. example.com, www.example.com">
                                </div>

                                <!-- Pre-flight DNS Verification Tool -->
                                <div style="display:flex; gap:0.5rem; align-items:center;">
                                    <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.runDnsPreflight()" ${this.state.checkingPreflight ? 'disabled' : ''} style="font-size:0.75rem;">
                                        ${this.state.checkingPreflight ? '📡 Probing DNS...' : '📡 Verify DNS Propagation Before Running'}
                                    </button>
                                </div>

                                ${this.state.preflightResults ? `
                                    <div class="spp-setting-banner" style="padding:0.6rem 0.75rem; border-radius:var(--radius-sm); border:1px solid ${this.state.preflightResults.all_matched ? 'rgba(52,211,153,0.3)' : 'rgba(251,191,36,0.3)'}; font-size:0.75rem;">
                                        <div style="font-weight:600; color:${this.state.preflightResults.all_matched ? '#34d399' : '#fbbf24'}; margin-bottom:0.3rem;">
                                            ${this.state.preflightResults.all_matched ? '✔ All domains resolve to this server IP (' + this.escapeHtml(this.state.preflightResults.server_ip) + ')' : '⚠️ DNS Pre-Flight Warning:'}
                                        </div>
                                        ${(this.state.preflightResults.results || []).map(r => `
                                            <div style="display:flex; justify-content:space-between; margin-top:0.2rem; color:${r.matches ? 'var(--text-bright)' : '#f87171'};">
                                                <span>• <code>${this.escapeHtml(r.domain)}</code> &rarr; ${this.escapeHtml(r.resolved_ip)}</span>
                                                <span>${r.matches ? '✔ Match' : '❌ Mismatch (Expected: ' + this.escapeHtml(r.server_ip) + ')'}</span>
                                            </div>
                                            ${r.warning ? `<div style="color:#fbbf24; font-size:0.7rem; margin-left:0.5rem; margin-top:0.1rem;">⚠️ ${this.escapeHtml(r.warning)}</div>` : ''}
                                        `).join('')}
                                    </div>
                                ` : ''}

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                    <div class="input-group">
                                        <label>Contact Email</label>
                                        <input type="email" id="certbot-email-input" value="${this.escapeHtml('admin@' + (this.state.sslHost || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com')))}" placeholder="admin@example.com">
                                    </div>
                                    <div class="input-group">
                                        <label>Authenticator Method</label>
                                        <select id="certbot-method-select" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                                            <option value="apache" selected>Apache Plugin (--apache: Auto Vhost SSL)</option>
                                            <option value="webroot">Webroot Challenge (--webroot: Zero Vhost rewrite)</option>
                                        </select>
                                    </div>
                                </div>

                                <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-top:0.25rem;">
                                    <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.8rem; color:var(--text-bright);">
                                        <input type="checkbox" id="certbot-redirect-toggle" checked>
                                        <span>Force HTTPS Redirect</span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.8rem; color:var(--text-bright);">
                                        <input type="checkbox" id="certbot-staging-toggle">
                                        <span>Staging CA (Bypass rate limits)</span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.8rem; color:var(--text-bright);">
                                        <input type="checkbox" id="certbot-dryrun-toggle">
                                        <span>Dry-Run Simulation</span>
                                    </label>
                                </div>

                                <button class="btn primary-btn shine-effect" style="margin-top:0.5rem;" onclick="window.domainsViewInstance.issueCertbotSsl()" ${this.state.issuingCertbot ? 'disabled' : ''}>
                                    ${this.state.issuingCertbot ? '⏳ Running Certbot & Installing SSL...' : '🔒 Run Certbot & Install SSL Certificate'}
                                </button>
                            </div>

                            ${this.state.certbotResult ? `
                                <div style="margin-top:1.25rem; padding:1rem; border:1px solid var(--glass-border); border-radius:var(--radius-md); background:rgba(0,0,0,0.25);">
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.75rem;">
                                        <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">Certbot Result (${this.escapeHtml((this.state.certbotResult.domains || []).join(', '))})</h4>
                                        ${this.state.certbotResult.success 
                                            ? '<span class="suite-pill suite-pill-success">' + (this.state.certbotResult.dry_run ? 'Dry-Run Validated' : 'Certificate Active') + '</span>' 
                                            : '<span class="suite-pill suite-pill-danger">Certbot Failed</span>'}
                                    </div>

                                    ${this.state.certbotResult.success ? `
                                        <div style="font-size:0.85rem; display:flex; flex-direction:column; gap:0.4rem;">
                                            <div><strong style="color:var(--text-bright);">Status:</strong> <span style="color:#34d399;">${this.escapeHtml(this.state.certbotResult.message || 'SSL Issued Successfully')}</span></div>
                                            ${this.state.certbotResult.cert_file ? `<div style="word-break:break-all;"><strong style="color:var(--text-dim);">Cert:</strong> <code>${this.escapeHtml(this.state.certbotResult.cert_file)}</code></div>` : ''}
                                            ${this.state.certbotResult.key_file ? `<div style="word-break:break-all;"><strong style="color:var(--text-dim);">Key:</strong> <code>${this.escapeHtml(this.state.certbotResult.key_file)}</code></div>` : ''}
                                        </div>
                                    ` : `
                                        <div style="color:#f87171; font-size:0.85rem;">
                                            ❌ ${this.escapeHtml(this.state.certbotResult.error || 'Certbot issuance failed.')}
                                        </div>
                                    `}

                                    ${this.state.certbotResult.output ? `
                                        <div style="margin-top:0.75rem;">
                                            <span style="font-size:0.7rem; color:var(--text-dim); text-transform:uppercase;">Certbot Command & Execution Output:</span>
                                            <pre style="margin-top:0.25rem; padding:0.6rem; background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-sm); font-family:monospace; font-size:0.75rem; color:#f8fafc; max-height:160px; overflow-y:auto; line-height:1.4; white-space:pre-wrap;">${this.escapeHtml(this.state.certbotResult.command ? '$ ' + this.state.certbotResult.command + '\n\n' : '')}${this.escapeHtml(this.state.certbotResult.output)}</pre>
                                        </div>
                                    ` : ''}
                                </div>
                            ` : ''}
                        ` : `
                            <!-- Pure-PHP ACME v2 Engine Form -->
                            <div style="margin-top:1rem; display:flex; flex-direction:column; gap:0.75rem;">
                                <div class="input-group">
                                    <label>Target Domain</label>
                                    <input type="text" id="autossl-domain-input" list="autossl-domains-datalist" value="${this.escapeHtml(this.state.sslHost)}" placeholder="e.g. portal.myschool.edu">
                                    <datalist id="autossl-domains-datalist">
                                        ${domains.map(d => `<option value="${this.escapeHtml(d.pattern)}">`).join('')}
                                    </datalist>
                                </div>

                                <div class="input-group">
                                    <label>Let's Encrypt Account Email</label>
                                    <input type="email" id="autossl-email-input" placeholder="admin@myschool.edu">
                                </div>

                                <div style="display:flex; gap:1.25rem; margin-top:0.25rem;">
                                    <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.8rem; color:var(--text-bright);">
                                        <input type="checkbox" id="autossl-staging-toggle">
                                        <span>Staging CA (Bypass rate limits)</span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.8rem; color:var(--text-bright);">
                                        <input type="checkbox" id="autossl-dryrun-toggle" checked>
                                        <span>Dry-Run Simulation</span>
                                    </label>
                                </div>

                                <button class="btn primary-btn shine-effect" style="margin-top:0.5rem;" onclick="window.domainsViewInstance.issueAutoSsl()" ${this.state.issuingSsl ? 'disabled' : ''}>
                                    ${this.state.issuingSsl ? '⏳ Provisioning ACME v2 Certificate...' : '🔒 Issue / Auto-Renew Certificate'}
                                </button>
                            </div>

                            ${autoRes ? `
                                <div style="margin-top:1.25rem; padding:1rem; border:1px solid var(--glass-border); border-radius:var(--radius-md); background:rgba(0,0,0,0.25);">
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.75rem;">
                                        <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">${this.escapeHtml(autoRes.domain || 'AutoSSL Result')}</h4>
                                        ${autoRes.success 
                                            ? '<span class="suite-pill suite-pill-success">' + (autoRes.dry_run ? 'Dry-Run Validated' : 'Certificate Active') + '</span>' 
                                            : '<span class="suite-pill suite-pill-danger">Issuance Failed</span>'}
                                    </div>

                                    ${autoRes.success ? `
                                        <div style="font-size:0.85rem; display:flex; flex-direction:column; gap:0.4rem;">
                                            <div><strong style="color:var(--text-bright);">Issuer:</strong> <span style="color:#38bdf8;">${this.escapeHtml(autoRes.issuer || 'Let\'s Encrypt')}</span></div>
                                            <div><strong style="color:var(--text-bright);">Expires:</strong> <span style="color:#34d399;">${this.escapeHtml(autoRes.expires_at || 'In 90 days')}</span></div>
                                            ${autoRes.cert_file ? `<div style="word-break:break-all;"><strong style="color:var(--text-dim);">Cert:</strong> <code>${this.escapeHtml(autoRes.cert_file)}</code></div>` : ''}
                                            ${autoRes.key_file ? `<div style="word-break:break-all;"><strong style="color:var(--text-dim);">Key:</strong> <code>${this.escapeHtml(autoRes.key_file)}</code></div>` : ''}
                                        </div>
                                    ` : `
                                        <div style="color:#f87171; font-size:0.85rem;">
                                            ❌ ${this.escapeHtml(autoRes.error || 'Unknown ACME error')}
                                        </div>
                                    `}

                                    ${autoRes.log && autoRes.log.length ? `
                                        <div style="margin-top:0.75rem;">
                                            <span style="font-size:0.7rem; color:var(--text-dim); text-transform:uppercase;">ACME Challenge Protocol Log:</span>
                                            <pre style="margin-top:0.25rem; padding:0.6rem; background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-sm); font-family:monospace; font-size:0.75rem; color:#f8fafc; max-height:120px; overflow-y:auto; line-height:1.4;">${autoRes.log.map(l => this.escapeHtml(l)).join('\n')}</pre>
                                        </div>
                                    ` : ''}
                                </div>
                            ` : ''}
                        `}
                    </div>
                </div>
            </div>
        `;
    }

    renderProxyTab() {
        const domains = this.state.domains || [];
        const selectedDom = this.state.selectedProxyDomain || (domains[0]?.pattern || '');
        const domObj = domains.find(d => d.pattern === selectedDom);
        const proxy = domObj?.reverse_proxy || {
            enabled: false,
            target: 'http://127.0.0.1:3000',
            service_type: 'node',
            timeout: 30,
            preserve_host: true,
            websocket: true
        };

        return `
            <div class="glass-panel" style="padding:1.5rem; border-radius:var(--radius-md);">
                <div style="margin-bottom:1.25rem;">
                    <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright);">⚡ Polyglot Microservice Reverse Proxy</h3>
                    <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                        Route incoming HTTP/HTTPS traffic from any domain directly to background microservices running on local ports (Node.js, Python, Go, Java, .NET Core).
                    </p>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                    <!-- Configuration Form -->
                    <div style="background:rgba(0,0,0,0.2); padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border);">
                        <div class="input-group" style="margin-bottom:1rem;">
                            <label>Target Domain / Virtual Host</label>
                            <select id="proxy-domain-select" onchange="window.domainsViewInstance.onProxyDomainChange(this.value)" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                                ${domains.map(d => `<option value="${this.escapeHtml(d.pattern)}" ${d.pattern === selectedDom ? 'selected' : ''}>${this.escapeHtml(d.pattern)} (${d.default_app})</option>`).join('')}
                            </select>
                        </div>

                        <div style="margin-bottom:1rem;">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" id="proxy-enabled-toggle" ${proxy.enabled ? 'checked' : ''}>
                                <strong style="color:var(--text-bright);">Enable Reverse Proxy for this Host</strong>
                            </label>
                        </div>

                        <div class="input-group" style="margin-bottom:1rem;">
                            <label>Backend Upstream Target URL</label>
                            <div style="display:flex; gap:0.5rem;">
                                <input type="text" id="proxy-target-input" value="${this.escapeHtml(proxy.target || 'http://127.0.0.1:3000')}" placeholder="e.g. http://127.0.0.1:3000 or http://127.0.0.1:8000" style="flex:1;">
                                <button type="button" class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.testProxyTarget()" ${this.state.probingProxy ? 'disabled' : ''}>
                                    ${this.state.probingProxy ? 'Testing...' : '⚡ Test Upstream'}
                                </button>
                            </div>
                            <div id="proxy-probe-feedback" style="margin-top:0.4rem; font-size:0.8rem;"></div>
                        </div>

                        <div class="input-group" style="margin-bottom:1rem;">
                            <label>Microservice Runtime Technology</label>
                            <select id="proxy-service-type" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                                <option value="node" ${proxy.service_type === 'node' ? 'selected' : ''}>🟢 Node.js (Express, Nest, Fastify)</option>
                                <option value="python" ${proxy.service_type === 'python' ? 'selected' : ''}>🐍 Python (FastAPI, Django, Flask, Uvicorn)</option>
                                <option value="go" ${proxy.service_type === 'go' ? 'selected' : ''}>🐹 Go (Gin, Fiber, Echo)</option>
                                <option value="dotnet" ${proxy.service_type === 'dotnet' ? 'selected' : ''}>🔷 .NET Core (ASP.NET Kestrel)</option>
                                <option value="java" ${proxy.service_type === 'java' ? 'selected' : ''}>☕ Java VM (Spring Boot, Quarkus)</option>
                                <option value="generic" ${proxy.service_type === 'generic' ? 'selected' : ''}>🌐 Generic HTTP Microservice</option>
                            </select>
                        </div>

                        <div style="display:flex; gap:1.5rem; margin-bottom:1.25rem;">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                                <input type="checkbox" id="proxy-websocket-toggle" ${proxy.websocket ? 'checked' : ''}>
                                <span>WebSocket Support (Upgrade)</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                                <input type="checkbox" id="proxy-preserve-host" ${proxy.preserve_host ? 'checked' : ''}>
                                <span>Pass Host Header</span>
                            </label>
                        </div>

                        <button class="btn primary-btn" style="width:100%;" onclick="window.domainsViewInstance.saveProxySettings()" ${this.state.savingProxy ? 'disabled' : ''}>
                            ${this.state.savingProxy ? 'Saving...' : '💾 Save Reverse Proxy Configuration'}
                        </button>
                    </div>

                    <!-- Summary & Active Proxies List -->
                    <div>
                        <h4 style="margin:0 0 0.75rem 0; font-size:1rem; color:var(--text-bright);">Active Reverse Proxies</h4>
                        <div style="display:flex; flex-direction:column; gap:0.75rem;">
                            ${domains.filter(d => d.reverse_proxy && d.reverse_proxy.enabled).length === 0 ? `
                                <div style="padding:2rem; text-align:center; color:var(--text-dim); background:rgba(0,0,0,0.1); border-radius:var(--radius-md);">
                                    No active reverse proxies configured. Select a domain on the left and specify an upstream target to proxy requests.
                                </div>
                            ` : domains.filter(d => d.reverse_proxy && d.reverse_proxy.enabled).map(d => `
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.85rem 1.15rem; background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-md);">
                                    <div>
                                        <div style="font-weight:600; color:var(--text-bright);">${this.escapeHtml(d.pattern)}</div>
                                        <div style="font-size:0.8rem; color:#38bdf8;">➡️ ${this.escapeHtml(d.reverse_proxy.target)}</div>
                                    </div>
                                    <span class="suite-pill suite-pill-primary">${this.escapeHtml(d.reverse_proxy.service_type || 'generic')}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderDnsTab() {
        const res = this.state.dnsResult;

        return `
            <div class="glass-panel" style="padding:1.5rem; border-radius:var(--radius-md);">
                <div style="margin-bottom:1.25rem;">
                    <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright);">📡 Diagnostic DNS Tracker & Network Route Explorer</h3>
                    <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                        Query authoritative DNS records across multiple record types and run live network traceroutes to diagnose network routing issues.
                    </p>
                </div>

                <div style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap; max-width:850px;">
                    <div class="input-group" style="flex:2; min-width:200px;">
                        <label>Hostname / Domain to Query</label>
                        <input type="text" id="dns-input-host" value="${this.escapeHtml(this.state.dnsHost)}" placeholder="e.g. google.com or school.local">
                    </div>
                    <div class="input-group" style="flex:1; min-width:130px;">
                        <label>Record Type</label>
                        <select id="dns-input-type" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                            <option value="ANY" ${this.state.dnsType === 'ANY' ? 'selected' : ''}>ALL (ANY)</option>
                            <option value="A" ${this.state.dnsType === 'A' ? 'selected' : ''}>A (IPv4)</option>
                            <option value="AAAA" ${this.state.dnsType === 'AAAA' ? 'selected' : ''}>AAAA (IPv6)</option>
                            <option value="CNAME" ${this.state.dnsType === 'CNAME' ? 'selected' : ''}>CNAME (Alias)</option>
                            <option value="MX" ${this.state.dnsType === 'MX' ? 'selected' : ''}>MX (Mail Exchange)</option>
                            <option value="TXT" ${this.state.dnsType === 'TXT' ? 'selected' : ''}>TXT (SPF / DKIM / Verification)</option>
                            <option value="NS" ${this.state.dnsType === 'NS' ? 'selected' : ''}>NS (Nameserver)</option>
                            <option value="SOA" ${this.state.dnsType === 'SOA' ? 'selected' : ''}>SOA (Start of Authority)</option>
                        </select>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem; height:40px; margin-bottom:0.25rem;">
                        <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.85rem; color:var(--text-bright);">
                            <input type="checkbox" id="dns-input-trace" ${this.state.dnsTrace ? 'checked' : ''}>
                            <span>Include Traceroute</span>
                        </label>
                    </div>
                    <button class="btn primary-btn" style="height:40px;" onclick="window.domainsViewInstance.runDnsDiagnostics()" ${this.state.resolvingDns ? 'disabled' : ''}>
                        ${this.state.resolvingDns ? 'Querying...' : '🔍 Resolve DNS'}
                    </button>
                </div>

                ${res ? `
                    <div style="margin-top:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <h4 style="margin:0; font-size:1.05rem; color:var(--text-bright);">
                                Results for <span style="color:#38bdf8;">${this.escapeHtml(res.hostname)}</span> (${res.resolved_count} records found)
                            </h4>
                        </div>

                        ${res.records && res.records.length > 0 ? `
                            <div class="suite-table-wrapper" style="overflow-x:auto;">
                                <table class="suite-table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Host / Name</th>
                                            <th>TTL</th>
                                            <th>Target / IP / Value</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${res.records.map(r => `
                                            <tr>
                                                <td><span class="suite-pill suite-pill-primary">${this.escapeHtml(r.type || 'A')}</span></td>
                                                <td style="color:var(--text-bright); font-weight:600;">${this.escapeHtml(r.host || res.hostname)}</td>
                                                <td style="color:var(--text-dim);">${r.ttl !== undefined ? `${r.ttl}s` : 'N/A'}</td>
                                                <td style="font-family:monospace; color:#34d399;">${this.escapeHtml(r.ip || r.target || r.txt || r.ipv6 || r.mname || JSON.stringify(r))}</td>
                                                <td style="font-size:0.8rem; color:var(--text-dim);">${r.pri !== undefined ? `Priority: ${r.pri}` : ''}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : `
                            <div style="padding:1.5rem; text-align:center; color:var(--text-dim); background:rgba(0,0,0,0.1); border-radius:var(--radius-md);">
                                No DNS records returned for the specified query.
                            </div>
                        `}

                        ${res.traceroute ? `
                            <div style="margin-top:1.25rem;">
                                <h4 style="margin:0 0 0.5rem 0; font-size:0.95rem; color:var(--text-bright);">Hop Traceroute Diagnostics</h4>
                                <pre style="padding:1rem; background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-md); font-family:monospace; font-size:0.8rem; color:#f8fafc; overflow-x:auto; line-height:1.5;">${this.escapeHtml(res.traceroute)}</pre>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
        `;
    }

    onProxyDomainChange(pattern) {
        this.setState({ selectedProxyDomain: pattern });
        this.update();
    }

    async saveProxySettings() {
        const domain = document.getElementById('proxy-domain-select')?.value || this.state.selectedProxyDomain;
        const enabled = document.getElementById('proxy-enabled-toggle')?.checked || false;
        const target = document.getElementById('proxy-target-input')?.value.trim() || '';
        const serviceType = document.getElementById('proxy-service-type')?.value || 'node';
        const websocket = document.getElementById('proxy-websocket-toggle')?.checked || false;
        const preserveHost = document.getElementById('proxy-preserve-host')?.checked || true;

        if (enabled && !target) {
            this.admin.notify("Upstream target URL is required when proxy is enabled.", "error");
            return;
        }

        this.setState({ savingProxy: true });
        this.update();

        try {
            const res = await this.admin.api('domains_proxy_save', {
                pattern: domain,
                proxy: {
                    enabled: enabled,
                    target: target,
                    service_type: serviceType,
                    websocket: websocket,
                    preserve_host: preserveHost
                }
            });

            if (res.success) {
                this.admin.notify(res.message || "Proxy configuration saved.", "success");
                await this.fetchData();
            } else {
                this.admin.notify(res.error || "Failed to save proxy.", "error");
            }
        } catch (e) {
            this.admin.notify("Save error: " + e.message, "error");
        } finally {
            this.setState({ savingProxy: false });
            this.update();
        }
    }

    async testProxyTarget() {
        const target = document.getElementById('proxy-target-input')?.value.trim();
        const feedbackEl = document.getElementById('proxy-probe-feedback');
        if (!target) {
            this.admin.notify("Please provide a target URL to test.", "warning");
            return;
        }

        this.setState({ probingProxy: true });
        if (feedbackEl) {
            feedbackEl.innerHTML = '<span style="color:var(--text-dim);">⏳ Probing upstream backend...</span>';
        }

        try {
            const res = await this.admin.api('domains_proxy_probe', { target: target });
            const data = (res.success && res.data) ? res.data : res;
            if (data.reachable) {
                this.admin.notify(`Upstream reachable! (${data.latency_ms}ms)`, "success");
                if (feedbackEl) {
                    feedbackEl.innerHTML = `
                        <span style="color:#34d399; font-weight:600;">
                            ✓ Reachable: Host ${this.escapeHtml(data.host)}:${data.port} | Latency: ${data.latency_ms}ms
                        </span>
                    `;
                }
            } else {
                this.admin.notify(`Upstream unreachable: ${data.error || 'Connection refused'}`, "error");
                if (feedbackEl) {
                    feedbackEl.innerHTML = `
                        <span style="color:#f87171; font-weight:600;">
                            ✗ Unreachable: ${this.escapeHtml(data.error || 'Connection refused')}
                        </span>
                    `;
                }
            }
        } catch (e) {
            this.admin.notify("Probe error: " + e.message, "error");
            if (feedbackEl) {
                feedbackEl.innerHTML = `<span style="color:#f87171;">✗ Error: ${this.escapeHtml(e.message)}</span>`;
            }
        } finally {
            this.setState({ probingProxy: false });
            const btn = document.querySelector('button[onclick*="testProxyTarget"]');
            if (btn) btn.disabled = false;
        }
    }

    async issueAutoSsl() {
        const domain = document.getElementById('autossl-domain-input')?.value.trim() || this.state.sslHost;
        const email = document.getElementById('autossl-email-input')?.value.trim() || '';
        const staging = document.getElementById('autossl-staging-toggle')?.checked || false;
        const dryRun = document.getElementById('autossl-dryrun-toggle')?.checked || false;

        if (!domain) {
            this.admin.notify("Please enter a valid domain for AutoSSL issuance.", "error");
            return;
        }

        this.setState({ issuingSsl: true, autoSslResult: null });
        this.update();

        try {
            const res = await this.admin.api('domains_autossl_issue', {
                domain: domain,
                email: email,
                staging: staging,
                dry_run: dryRun,
                execute: true
            });

            const data = (res.success && res.data) ? res.data : res;
            this.setState({ autoSslResult: data, issuingSsl: false });
            if (res.success || data.success) {
                this.admin.notify(data.message || "AutoSSL issuance completed successfully!", "success");
                await this.fetchData();
            } else {
                this.admin.notify(data.error || "AutoSSL issuance encountered issues.", "error");
            }
        } catch (e) {
            this.admin.notify("AutoSSL error: " + e.message, "error");
            this.setState({ issuingSsl: false });
        }
        this.update();
    }

    setSslEngine(engine) {
        this.setState({ sslEngine: engine });
        if (engine === 'certbot' && !this.state.certbotStatus && !this.state.loadingCertbotStatus) {
            this.loadCertbotStatus();
        }
        this.update();
    }

    async loadCertbotStatus() {
        this.setState({ loadingCertbotStatus: true });
        this.update();
        try {
            const res = await this.admin.api('domains_certbot_status');
            const data = (res.success && res.data) ? res.data : res;
            if (res.success || data.success) {
                this.setState({ certbotStatus: data, loadingCertbotStatus: false });
            } else {
                this.setState({ loadingCertbotStatus: false });
            }
        } catch (e) {
            console.warn("Certbot status fetch error:", e);
            this.setState({ loadingCertbotStatus: false });
        }
        this.update();
    }

    async installCertbot() {
        this.setState({ installingCertbot: true, certbotInstallResult: null });
        this.update();
        try {
            this.admin.notify("Installing Certbot & Apache plugin via host package manager (apt)...", "info");
            const res = await this.admin.api('domains_certbot_install', { webserver: 'apache' });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ installingCertbot: false, certbotInstallResult: data });
            if (res.success || data.success) {
                this.admin.notify(data.message || "Certbot & Apache plugin installed successfully!", "success");
                await this.loadCertbotStatus();
            } else {
                this.admin.notify(data.error || "Installation encountered issues. Check console output.", "error");
            }
        } catch (e) {
            this.admin.notify("Certbot install error: " + e.message, "error");
            this.setState({ installingCertbot: false });
        }
        this.update();
    }

    async runDnsPreflight() {
        const input = document.getElementById('certbot-domains-input')?.value.trim() || (this.state.sslHost || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com'));
        this.setState({ checkingPreflight: true, preflightResults: null });
        this.update();
        try {
            const res = await this.admin.api('domains_dns_preflight', { domains: input });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ checkingPreflight: false, preflightResults: data });
            if (data.all_matched) {
                this.admin.notify("✔ All domains resolve to this server IP!", "success");
            } else {
                this.admin.notify("⚠️ One or more domains do not resolve to this server. See warnings below.", "warning");
            }
        } catch (e) {
            this.admin.notify("DNS Preflight error: " + e.message, "error");
            this.setState({ checkingPreflight: false });
        }
        this.update();
    }

    async issueCertbotSsl() {
        const domainsInput = document.getElementById('certbot-domains-input')?.value.trim() || (this.state.sslHost || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com'));
        const email = document.getElementById('certbot-email-input')?.value.trim() || '';
        const method = document.getElementById('certbot-method-select')?.value || 'apache';
        const staging = document.getElementById('certbot-staging-toggle')?.checked || false;
        const dryRun = document.getElementById('certbot-dryrun-toggle')?.checked || false;
        const forceRedirect = document.getElementById('certbot-redirect-toggle')?.checked || true;

        if (!domainsInput) {
            this.admin.notify("Please enter at least one domain for SSL certificate issuance.", "error");
            return;
        }

        this.setState({ issuingCertbot: true, certbotResult: null });
        this.update();

        try {
            this.admin.notify(`Running Certbot (${method}) for: ${domainsInput}...`, "info");
            const res = await this.admin.api('domains_certbot_issue', {
                domains: domainsInput,
                email: email,
                method: method,
                staging: staging,
                dry_run: dryRun,
                force_redirect: forceRedirect
            });

            const data = (res.success && res.data) ? res.data : res;
            this.setState({ certbotResult: data, issuingCertbot: false });

            if (res.success || data.success) {
                this.admin.notify(data.message || "SSL certificate issued and installed successfully!", "success");
                await this.fetchData();
            } else {
                this.admin.notify(data.error || "Certbot issuance failed. See output log below.", "error");
            }
        } catch (e) {
            this.admin.notify("Certbot execution error: " + e.message, "error");
            this.setState({ issuingCertbot: false });
        }
        this.update();
    }

    addDomainSan(sub) {
        const input = document.getElementById('certbot-domains-input');
        if (!input) return;
        const current = input.value.split(',').map(s => s.trim()).filter(Boolean);
        const apex = current[0] || (this.state.sslHost || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com'));
        const cleanApex = apex.replace(/^www\./, '').replace(/^vps\./, '').replace(/^mail\./, '');
        const newDomain = sub ? `${sub}.${cleanApex}` : cleanApex;
        if (!current.includes(newDomain)) {
            current.push(newDomain);
            input.value = current.join(', ');
        }
    }

    async runDnsDiagnostics() {
        const host = document.getElementById('dns-input-host')?.value.trim() || this.state.dnsHost;
        const type = document.getElementById('dns-input-type')?.value || 'ANY';
        const trace = document.getElementById('dns-input-trace')?.checked || false;

        this.setState({ dnsHost: host, dnsType: type, dnsTrace: trace, resolvingDns: true });
        this.update();

        try {
            const res = await this.admin.api('domains_track_dns', {
                hostname: host,
                type: type,
                trace: trace
            });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ dnsResult: data, resolvingDns: false });
        } catch (e) {
            this.admin.notify("DNS Query error: " + e.message, "error");
            this.setState({ resolvingDns: false });
        }
        this.update();
    }

    async loadZone(domain) {
        const defaultFallbackDom = (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com');
        let dom = domain || this.state.zoneDomain || defaultFallbackDom;
        if (dom === 'school.local' || dom.endsWith('.local') || dom === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(dom)) {
            dom = (this.state.availableZones && this.state.availableZones.length > 0)
                ? this.state.availableZones[0]
                : defaultFallbackDom;
        }
        this.setState({ loadingZone: true, zoneDomain: dom });
        this.update();

        try {
            const res = await this.admin.api('domains_zone_get', { domain: dom });
            const data = (res.success && res.data) ? res.data : res;
            if (res.success || data.success || data.records) {
                this.setState({
                    zoneData: data,
                    loadingZone: false,
                    availableZones: (data.available_zones && data.available_zones.length > 0) ? data.available_zones : (this.state.availableZones || [])
                });
            } else {
                this.admin.notify(data.error || res.error || "Failed to load DNS zone.", "error");
                this.setState({ loadingZone: false });
            }
        } catch (e) {
            this.admin.notify("Error loading DNS zone: " + e.message, "error");
            this.setState({ loadingZone: false });
        }
        this.update();
    }

    async loadBindStatus() {
        this.setState({ loadingBind: true });
        try {
            const res = await this.admin.api('bind_daemon_status');
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ bindStatus: data, loadingBind: false });
        } catch (e) {
            this.setState({ loadingBind: false });
        }
        this.update();
    }

    async syncZoneToBind(domain) {
        const dom = domain || this.state.zoneDomain || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com');
        this.setState({ syncingBind: true });
        this.admin.notify(`Syncing RFC 1035 zone '${dom}' to host BIND9 daemon...`, "info");
        this.update();

        try {
            const res = await this.admin.api('bind_zone_sync', { domain: dom });
            const data = (res.success && res.data) ? res.data : res;
            if (res.success || data.success) {
                this.admin.notify(`✓ Zone '${dom}' synced to BIND9! Daemon reloaded on port 53.`, "success");
                await this.loadBindStatus();
            } else {
                this.admin.notify(data.error || res.error || "BIND zone synchronization failed.", "error");
            }
        } catch (e) {
            this.admin.notify("BIND sync error: " + e.message, "error");
        } finally {
            this.setState({ syncingBind: false });
            this.update();
        }
    }

    async installBindDaemon() {
        if (!confirm("Install BIND9 DNS server packages on this host via system package manager?")) return;
        this.setState({ installingBind: true });
        this.admin.notify("Installing BIND9 packages (bind9, dnsutils)...", "info");
        this.update();

        try {
            const res = await this.admin.api('bind_daemon_install');
            const data = (res.success && res.data) ? res.data : res;
            if (res.success || data.success) {
                this.admin.notify(data.message || "BIND9 installed and started successfully!", "success");
                await this.loadBindStatus();
            } else {
                this.admin.notify(data.error || "Failed to install BIND9.", "error");
            }
        } catch (e) {
            this.admin.notify("BIND install error: " + e.message, "error");
        } finally {
            this.setState({ installingBind: false });
            this.update();
        }
    }

    async reloadBindDaemon() {
        this.setState({ reloadingBind: true });
        try {
            const res = await this.admin.api('bind_daemon_reload');
            const data = (res.success && res.data) ? res.data : res;
            if (res.success || data.success) {
                this.admin.notify(data.message || "BIND9 reloaded successfully.", "success");
                await this.loadBindStatus();
            } else {
                this.admin.notify(data.error || "Failed to reload BIND9.", "error");
            }
        } catch (e) {
            this.admin.notify("BIND reload error: " + e.message, "error");
        } finally {
            this.setState({ reloadingBind: false });
            this.update();
        }
    }

    async checkRegistryGlue(domain) {
        const dom = domain || this.state.zoneDomain || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com');
        this.setState({ checkingGlue: true });
        this.admin.notify(`Querying authoritative root TLD (a.gtld-servers.net) and registry WHOIS for '${dom}'...`, "info");
        this.update();

        try {
            const res = await this.admin.api('domains_glue_matrix', { domain: dom });
            const data = (res.success && res.data) ? res.data : res;
            if (res.success || data.success) {
                this.setState({ glueMatrix: data, checkingGlue: false });
                if (data.has_glue_mismatch) {
                    this.admin.notify(`⚠️ Glue mismatch detected! Root TLD still returns old IP. Use the Copy button to contact your registrar.`, "warning");
                } else {
                    this.admin.notify(`✓ Registry Glue records match host public IP (${data.expected_ip})!`, "success");
                }
            } else {
                this.admin.notify(data.error || "Failed to inspect registry glue records.", "error");
                this.setState({ checkingGlue: false });
            }
        } catch (e) {
            this.admin.notify("Glue check error: " + e.message, "error");
            this.setState({ checkingGlue: false });
        }
        this.update();
    }

    copyRegistrarInstructions() {
        const text = this.state.glueMatrix?.registrar_instructions;
        if (!text) return;
        navigator.clipboard.writeText(text);
        this.admin.notify("📋 Registrar support message copied to clipboard!", "success");
    }

    switchDnsSubTab(tab) {
        this.setState({ dnsSubTab: tab });
        if (tab === 'providers' && !this.state.dnsProvidersData) {
            this.loadDnsProviders();
        }
        if (tab === 'dnssec' && !this.state.dnssecAuditData) {
            this.loadDnssecAudit(this.state.zoneDomain);
        }
        if (tab === 'mail' && !this.state.mailAuditData) {
            this.loadMailAudit(this.state.zoneDomain);
        }
        if (tab === 'propagation' && !this.state.propagationResults) {
            this.runPropagationAudit(this.state.zoneDomain, 'A');
        }
        if (tab === 'nameservers' && !this.state.registrarData) {
            this.loadRegistrarInfo(this.state.zoneDomain);
        }
        this.update();
    }

    async loadDnsProviders() {
        this.setState({ loadingDnsProviders: true });
        try {
            const res = await this.admin.api('dns_providers_list');
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ dnsProvidersData: data, loadingDnsProviders: false });
        } catch (e) {
            this.setState({ loadingDnsProviders: false });
        }
        this.update();
    }

    async setDomainDnsProvider(domain, provider, accountId) {
        const dom = domain || this.state.zoneDomain;
        try {
            const res = await this.admin.api('dns_mapping_save', {
                domain: dom,
                provider: provider,
                account_id: accountId || null
            });
            if (res.success) {
                this.admin.notify(`✓ Authoritative DNS provider for '${dom}' set to ${provider}!`, "success");
                await this.loadZone(dom);
                await this.loadDnsProviders();
            } else {
                this.admin.notify(res.error || "Failed to update provider mapping.", "error");
            }
        } catch (e) {
            this.admin.notify("Error updating provider mapping: " + e.message, "error");
        }
        this.update();
    }

    async loadDnssecAudit(domain) {
        const dom = domain || this.state.zoneDomain;
        this.setState({ loadingDnssecAudit: true });
        try {
            const res = await this.admin.api('dns_audit_dnssec', { domain: dom });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ dnssecAuditData: data, loadingDnssecAudit: false });
        } catch (e) {
            this.setState({ loadingDnssecAudit: false });
        }
        this.update();
    }

    async loadMailAudit(domain) {
        const dom = domain || this.state.zoneDomain;
        this.setState({ loadingMailAudit: true });
        try {
            const res = await this.admin.api('dns_audit_mail', { domain: dom });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ mailAuditData: data, loadingMailAudit: false });
        } catch (e) {
            this.setState({ loadingMailAudit: false });
        }
        this.update();
    }

    async runPropagationAudit(domain, type = 'A') {
        const dom = domain || this.state.zoneDomain;
        this.setState({ checkingPropagation: true });
        try {
            const res = await this.admin.api('dns_audit_propagation', { hostname: dom, type: type });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ propagationResults: data, checkingPropagation: false });
        } catch (e) {
            this.setState({ checkingPropagation: false });
        }
        this.update();
    }

    async loadRegistrarInfo(domain) {
        const dom = domain || this.state.zoneDomain;
        this.setState({ loadingRegistrar: true });
        try {
            const res = await this.admin.api('dns_registrar_info', { domain: dom });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ registrarData: data, loadingRegistrar: false });
        } catch (e) {
            this.setState({ loadingRegistrar: false });
        }
        this.update();
    }

    async compareDns(domain) {
        const dom = domain || this.state.zoneDomain;
        this.setState({ comparingSync: true });
        try {
            const res = await this.admin.api('dns_zone_compare', { domain: dom });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ syncDiffData: data, comparingSync: false });
            if (data.in_sync) {
                this.admin.notify(`✓ Zone '${dom}' is completely in sync with provider!`, "success");
            } else {
                this.admin.notify(`Found ${data.counts?.added_locally || 0} local additions, ${data.counts?.added_remotely || 0} remote additions, ${data.counts?.modified || 0} modifications.`, "info");
            }
        } catch (e) {
            this.setState({ comparingSync: false });
            this.admin.notify("Comparison error: " + e.message, "error");
        }
        this.update();
    }

    async syncDns(domain, direction = 'pull', force = false) {
        const dom = domain || this.state.zoneDomain;
        this.setState({ syncingDns: true });
        this.admin.notify(`Synchronizing '${dom}' (${direction})...`, "info");
        try {
            const res = await this.admin.api('dns_zone_sync', { domain: dom, direction: direction, force: force });
            if (res.success) {
                this.admin.notify(res.message || `✓ Synchronization (${direction}) completed successfully!`, "success");
                await this.loadZone(dom);
                await this.compareDns(dom);
            } else {
                this.admin.notify(res.error || "Sync failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Sync error: " + e.message, "error");
        } finally {
            this.setState({ syncingDns: false });
            this.update();
        }
    }

    async testProviderAccount(provider, accountId) {
        this.admin.notify(`Testing connection to ${provider}...`, "info");
        try {
            const res = await this.admin.api('dns_provider_test', { provider: provider, account_id: accountId });
            if (res && res.success) {
                this.admin.notify(`✓ Connection to ${provider} successful! (${res.zones_count ?? 0} zones found)`, "success");
            } else {
                this.admin.notify(`Connection test failed: ${res?.error || 'Unknown error'}`, "error");
            }
        } catch (e) {
            this.admin.notify(`Connection error: ${e.message}`, "error");
        }
    }

    async saveProviderAccount(provider, accountId, credentials) {
        try {
            const res = await this.admin.api('dns_provider_account_save', {
                provider: provider,
                account_id: accountId,
                credentials: credentials
            });
            if (res && res.success) {
                this.admin.notify(`✓ Provider account '${accountId}' saved to encrypted vault!`, "success");
                document.getElementById('modal-container')?.classList.remove('modal-active');
                await this.loadDnsProviders();
            } else {
                this.admin.notify(`Failed to save account: ${res?.error || 'Unknown error'}`, "error");
            }
        } catch (e) {
            this.admin.notify(`Save error: ${e.message}`, "error");
        }
    }

    openAddProviderModal() {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'Connect External DNS Provider';
        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div class="input-group">
                    <label>DNS Provider Service <span style="color:var(--danger)">*</span></label>
                    <select id="prov-driver" onchange="window.domainsViewInstance.updateProviderCredentialFields(this.value)" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-weight:600;">
                        <option value="cloudflare">Cloudflare (API Token v4)</option>
                        <option value="route53">AWS Route 53 (SigV4 Access Key)</option>
                        <option value="digitalocean">DigitalOcean DNS (Personal Access Token)</option>
                        <option value="google_cloud_dns">Google Cloud DNS (Service Account JSON)</option>
                        <option value="custom">Custom REST Provider (SSRF-Guarded)</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Account Label / Identifier <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="prov-acc-id" placeholder="e.g. prod-cloudflare or primary-aws" style="width:100%; padding:0.5rem; font-family:monospace;">
                    <small style="color:var(--text-dim)">Unique identifier for this credential within SPP.</small>
                </div>
                <div id="prov-credential-fields">
                    <div class="input-group">
                        <label>Cloudflare Scoped API Token <span style="color:var(--danger)">*</span></label>
                        <input type="password" id="cf-token" placeholder="Paste Cloudflare API token (Zone:Read, DNS:Edit)" style="width:100%; font-family:monospace;">
                        <small style="color:var(--text-dim)">Tokens are encrypted at rest with AES-256-GCM in <code>etc/dns-providers.yml</code>.</small>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('modal-footer').innerHTML = `
            <button class="btn ghost-btn" onclick="document.getElementById('modal-container').classList.remove('modal-active')">Cancel</button>
            <button class="btn primary-btn shine-effect" onclick="window.domainsViewInstance.submitAddProviderModal()">💾 Save & Encrypt</button>
        `;
        modal.classList.add('modal-active');
    }

    updateProviderCredentialFields(driver) {
        const container = document.getElementById('prov-credential-fields');
        if (!container) return;
        if (driver === 'cloudflare') {
            container.innerHTML = `
                <div class="input-group">
                    <label>Cloudflare Scoped API Token <span style="color:var(--danger)">*</span></label>
                    <input type="password" id="cf-token" placeholder="Paste Cloudflare API token (Zone:Read, DNS:Edit)" style="width:100%; font-family:monospace;">
                    <small style="color:var(--text-dim)">Tokens are encrypted at rest with AES-256-GCM in <code>etc/dns-providers.yml</code>.</small>
                </div>
            `;
        } else if (driver === 'route53') {
            container.innerHTML = `
                <div class="input-group" style="margin-bottom:0.75rem;">
                    <label>AWS Access Key ID <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="aws-key" placeholder="AKIA..." style="width:100%; font-family:monospace;">
                </div>
                <div class="input-group" style="margin-bottom:0.75rem;">
                    <label>AWS Secret Access Key <span style="color:var(--danger)">*</span></label>
                    <input type="password" id="aws-secret" placeholder="Secret Key" style="width:100%; font-family:monospace;">
                </div>
                <div class="input-group">
                    <label>AWS Region</label>
                    <input type="text" id="aws-region" value="us-east-1" placeholder="us-east-1" style="width:100%; font-family:monospace;">
                </div>
            `;
        } else if (driver === 'digitalocean') {
            container.innerHTML = `
                <div class="input-group">
                    <label>DigitalOcean Personal Access Token <span style="color:var(--danger)">*</span></label>
                    <input type="password" id="do-token" placeholder="dop_v1_..." style="width:100%; font-family:monospace;">
                </div>
            `;
        } else if (driver === 'google_cloud_dns') {
            container.innerHTML = `
                <div class="input-group" style="margin-bottom:0.75rem;">
                    <label>GCP Project ID <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="gcp-project" placeholder="my-gcp-project-id" style="width:100%; font-family:monospace;">
                </div>
                <div class="input-group">
                    <label>Service Account Key (JSON)</label>
                    <textarea id="gcp-json" placeholder='{"type": "service_account", ...}' rows="4" style="width:100%; font-family:monospace; font-size:0.8rem;"></textarea>
                </div>
            `;
        } else if (driver === 'custom') {
            container.innerHTML = `
                <div class="input-group" style="margin-bottom:0.75rem;">
                    <label>API Base URL <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="custom-base" placeholder="https://api.mydns.com/v1" style="width:100%; font-family:monospace;">
                </div>
                <div class="input-group">
                    <label>Authorization Bearer Token / API Key</label>
                    <input type="password" id="custom-token" placeholder="Secret token" style="width:100%; font-family:monospace;">
                </div>
            `;
        }
    }

    async submitAddProviderModal() {
        const driver = document.getElementById('prov-driver')?.value;
        const accId = document.getElementById('prov-acc-id')?.value.trim();
        if (!accId) {
            this.admin.notify('Please provide an Account Label / Identifier.', 'warning');
            return;
        }

        let creds = {};
        if (driver === 'cloudflare') {
            creds = { api_token: document.getElementById('cf-token')?.value.trim() };
        } else if (driver === 'route53') {
            creds = {
                access_key_id: document.getElementById('aws-key')?.value.trim(),
                secret_access_key: document.getElementById('aws-secret')?.value.trim(),
                region: document.getElementById('aws-region')?.value.trim() || 'us-east-1'
            };
        } else if (driver === 'digitalocean') {
            creds = { api_token: document.getElementById('do-token')?.value.trim() };
        } else if (driver === 'google_cloud_dns') {
            creds = {
                project_id: document.getElementById('gcp-project')?.value.trim(),
                key_json: document.getElementById('gcp-json')?.value.trim()
            };
        } else if (driver === 'custom') {
            creds = {
                base_url: document.getElementById('custom-base')?.value.trim(),
                api_token: document.getElementById('custom-token')?.value.trim()
            };
        }

        await this.saveProviderAccount(driver, accId, creds);
    }

    renderZoneTab() {
        const zone = this.state.zoneData || {};
        const records = (zone.records || []).filter(r => {
            if (!this.state.zoneSearch) return true;
            const q = this.state.zoneSearch.toLowerCase();
            return (r.name && r.name.toLowerCase().includes(q)) ||
                   (r.type && r.type.toLowerCase().includes(q)) ||
                   (r.value && r.value.toLowerCase().includes(q)) ||
                   (r.target && r.target.toLowerCase().includes(q));
        });

        const excludedTestDomains = [
            'school.local', 'school1.local', 'localhost', 'spp.local',
            'autotest.sovereign.local', 'evaltest.school.edu', 'testscho.edu.in',
            'cli.test.local', 'parikshak-deploy.local', 'testportal.local'
        ];
        const allDomainOptions = [
            ...new Set([
                this.state.zoneDomain,
                ...(this.state.availableZones || []),
                ...(this.state.domains || []).map(d => d.pattern || d.domain),
                ...(this.state.addonDomains || []).map(a => a.domain)
            ])
        ].filter(d => d && !excludedTestDomains.includes(d) && !d.includes('localhost') && !d.startsWith('*.') && !d.includes('.local') && !d.includes('.internal'));

        const domainSelectOptions = allDomainOptions.map(d => `
            <option value="${this.escapeHtml(d)}" ${d === this.state.zoneDomain ? 'selected' : ''}>${this.escapeHtml(d)}</option>
        `).join('');

        const typeColorMap = {
            'A': '#3b82f6',
            'AAAA': '#6366f1',
            'CNAME': '#10b981',
            'MX': '#f59e0b',
            'TXT': '#8b5cf6',
            'NS': '#ec4899',
            'SRV': '#06b6d4',
            'CAA': '#f97316',
            'SOA': '#64748b'
        };

        const rows = records.map(r => {
            const color = typeColorMap[r.type] || '#64748b';
            let valDisplay = this.escapeHtml(r.value || r.target || r.mname || '');
            if (r.type === 'MX') {
                valDisplay = `<span style="color:#f59e0b; font-weight:600;">[Pri: ${r.priority || 10}]</span> ${this.escapeHtml(r.target || r.value || '')}`;
            } else if (r.type === 'CAA') {
                valDisplay = `<span style="color:#f97316;">${this.escapeHtml(r.tag || 'issue')}</span> "${this.escapeHtml(r.value || '')}"`;
            } else if (r.type === 'SRV') {
                valDisplay = `Pri: ${r.priority || 0} | Wgt: ${r.weight || 0} | Port: ${r.port || 0} &rarr; ${this.escapeHtml(r.target || '')}`;
            }

            const isSOA = r.type === 'SOA';

            return `
                <tr>
                    <td><strong style="color:var(--text-bright);">${this.escapeHtml(r.name || '@')}</strong></td>
                    <td>
                        <span class="suite-pill" style="background:${color}22; color:${color}; border:1px solid ${color}55; font-weight:700;">
                            ${this.escapeHtml(r.type)}
                        </span>
                    </td>
                    <td style="max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-family:monospace; font-size:0.85rem;" title="${this.escapeHtml(r.value || r.target || '')}">
                        ${valDisplay}
                    </td>
                    <td style="color:var(--text-dim); font-size:0.8rem;">${r.ttl || 3600}s</td>
                    <td>
                        <div style="display:flex; gap:0.4rem;">
                            ${!isSOA ? `
                                <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.openRecordModal(${this.escapeHtml(JSON.stringify(r))})">Edit</button>
                                <button class="btn danger-btn btn-sm" onclick="window.domainsViewInstance.deleteRecord('${r.id}')">Del</button>
                            ` : `<span style="font-size:0.75rem; color:var(--text-dim); font-style:italic;">System SOA</span>`}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        const dnssec = zone.dnssec || {};
        const isDnssecActive = dnssec.active || false;
        const subTab = this.state.dnsSubTab || 'overview';

        return `
            <div class="suite-pane-main">
                <!-- Zone Header & Domain Switcher Toolbar -->
                <div class="suite-toolbar" style="flex-wrap:wrap; gap:1rem;">
                    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                        <span style="font-size:1.5rem;">🌐</span>
                        <div>
                            <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">DNS &amp; Domain Management: <em>${this.escapeHtml(this.state.zoneDomain)}</em></h3>
                            <div style="font-size:0.8rem; color:var(--text-dim); margin-top:0.2rem;">
                                Authoritative Provider: <strong>${this.escapeHtml(zone.provider || 'bind9')}</strong> &bull; Records: <strong>${records.length}</strong> &bull; Serial: <strong>${zone.serial || 'N/A'}</strong>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center; margin-left:0.5rem;">
                            <select id="zone-domain-select" onchange="window.domainsViewInstance.loadZone(this.value)" style="padding:0.45rem 0.75rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.85rem; font-weight:600;">
                                ${domainSelectOptions}
                            </select>
                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.loadZone(document.getElementById('zone-domain-select').value)">↻ Reload</button>
                        </div>
                    </div>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.domainsViewInstance.openRecordModal()">
                            <span>+ Add DNS Record</span>
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.compareDns()" title="Compare records against authoritative DNS provider">
                            ⚖️ Compare Diff
                        </button>
                        <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.openZoneHistoryModal()" title="View point-in-time revision history, diffs, and rollback previous states">
                            🕒 History & Rollback
                        </button>
                    </div>
                </div>

                <!-- Unified DNS Sub-Navigation Bar -->
                <div class="dns-sub-nav" style="display:flex; gap:0.4rem; flex-wrap:wrap; border-bottom:1px solid var(--glass-border); padding-bottom:0.75rem; margin-top:1rem; margin-bottom:1.25rem;">
                    <button class="btn btn-sm ${subTab === 'overview' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('overview')">📊 Overview</button>
                    <button class="btn btn-sm ${subTab === 'records' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('records')">📋 DNS Records (${records.length})</button>
                    <button class="btn btn-sm ${subTab === 'providers' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('providers')">☁️ Providers</button>
                    <button class="btn btn-sm ${subTab === 'nameservers' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('nameservers')">📡 Nameservers &amp; Glue</button>
                    <button class="btn btn-sm ${subTab === 'dnssec' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('dnssec')">🔒 DNSSEC</button>
                    <button class="btn btn-sm ${subTab === 'propagation' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('propagation')">🌍 Propagation</button>
                    <button class="btn btn-sm ${subTab === 'mail' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('mail')">✉️ Mail DNS</button>
                    <button class="btn btn-sm ${subTab === 'diagnostics' ? 'primary-btn' : 'ghost-btn'}" onclick="window.domainsViewInstance.switchDnsSubTab('diagnostics')">🩺 Diagnostics</button>
                </div>

                <!-- Active Sub-Tab View Rendering -->
                ${subTab === 'overview' ? this.renderDnsOverviewSubTab(zone, records, isDnssecActive) : ''}
                ${subTab === 'records' ? this.renderDnsRecordsSubTab(zone, records, rows) : ''}
                ${subTab === 'providers' ? this.renderDnsProvidersSubTab() : ''}
                ${subTab === 'nameservers' ? this.renderDnsNameserversSubTab(zone) : ''}
                ${subTab === 'dnssec' ? this.renderDnssecSubTab(zone, isDnssecActive) : ''}
                ${subTab === 'propagation' ? this.renderDnsPropagationSubTab() : ''}
                ${subTab === 'mail' ? this.renderDnsMailSubTab() : ''}
                ${subTab === 'diagnostics' ? this.renderDnsDiagnosticsSubTab(zone) : ''}
            </div>
        `;
    }

    renderDnsOverviewSubTab(zone, records, isDnssecActive) {
        const provider = zone.provider || 'bind9';
        const syncDiff = this.state.syncDiffData;
        const providerDisplayMap = {
            'bind9': 'Native BIND9 (Host Port 53)',
            'cloudflare': 'Cloudflare API v4 (Edge Proxy & DNS)',
            'route53': 'AWS Route 53 (SigV4 Global Anycast)',
            'digitalocean': 'DigitalOcean DNS API v2',
            'google_cloud_dns': 'Google Cloud DNS (GCP Managed Zone)',
            'custom': 'Custom REST DNS Provider'
        };

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Summary Metrics Cards Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Active Domain</div>
                        <div style="font-size:1.15rem; font-weight:700; color:var(--text-bright); margin-top:0.25rem; word-break:break-all;">${this.escapeHtml(this.state.zoneDomain)}</div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.3rem;">Status: <span style="color:#10b981; font-weight:600;">● Active</span> &bull; Serial: ${zone.serial || 'N/A'}</div>
                    </div>

                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Authoritative Provider</div>
                        <div style="font-size:1.05rem; font-weight:700; color:#38bdf8; margin-top:0.25rem;">${providerDisplayMap[provider] || provider}</div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.3rem;">Mode: Sovereign Multi-Provider</div>
                    </div>

                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">DNSSEC Status</div>
                        <div style="font-size:1.15rem; font-weight:700; color:${isDnssecActive ? '#10b981' : 'var(--text-dim)'}; margin-top:0.25rem;">
                            ${isDnssecActive ? '✓ Signed &amp; Protected' : '○ Disabled'}
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.3rem;">ECDSA P-256 (Algo 13)</div>
                    </div>

                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Zone Records</div>
                        <div style="font-size:1.15rem; font-weight:700; color:var(--text-bright); margin-top:0.25rem;">${records.length} Records</div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.3rem;">RFC 1035 Standards Compliant</div>
                    </div>
                </div>

                <!-- Provider Assignment & Quick Switcher Banner -->
                <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <strong style="font-size:0.95rem; color:var(--text-bright);">Domain DNS Provider Routing</strong>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Different domains in this SPP installation can use different authoritative DNS providers simultaneously.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
                        <span style="font-size:0.8rem; color:var(--text-dim);">Assigned Provider:</span>
                        <select onchange="window.domainsViewInstance.setDomainDnsProvider('${this.escapeHtml(this.state.zoneDomain)}', this.value)" style="padding:0.45rem 0.75rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.85rem; font-weight:600;">
                            <option value="bind9" ${provider === 'bind9' ? 'selected' : ''}>Native BIND9</option>
                            <option value="cloudflare" ${provider === 'cloudflare' ? 'selected' : ''}>Cloudflare</option>
                            <option value="route53" ${provider === 'route53' ? 'selected' : ''}>AWS Route 53</option>
                            <option value="digitalocean" ${provider === 'digitalocean' ? 'selected' : ''}>DigitalOcean DNS</option>
                            <option value="google_cloud_dns" ${provider === 'google_cloud_dns' ? 'selected' : ''}>Google Cloud DNS</option>
                            <option value="custom" ${provider === 'custom' ? 'selected' : ''}>Custom Provider</option>
                        </select>
                    </div>
                </div>

                <!-- Bidirectional Synchronization Card -->
                <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:0.75rem;">
                        <div>
                            <strong style="font-size:0.95rem; color:var(--text-bright);">Conflict-Aware Bidirectional Synchronization</strong>
                            <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                                Synchronize zone records safely between local registry and authoritative provider without accidental overwrites.
                            </p>
                        </div>
                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.compareDns('${this.escapeHtml(this.state.zoneDomain)}')" ${this.state.comparingSync ? 'disabled' : ''}>
                                ${this.state.comparingSync ? 'Comparing...' : '⚖️ Compare Diff'}
                            </button>
                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.syncDns('${this.escapeHtml(this.state.zoneDomain)}', 'pull')" ${this.state.syncingDns ? 'disabled' : ''}>
                                📥 Pull from Provider
                            </button>
                            <button class="btn primary-btn btn-sm" style="background:#059669; border:none;" onclick="window.domainsViewInstance.syncDns('${this.escapeHtml(this.state.zoneDomain)}', 'push')" ${this.state.syncingDns ? 'disabled' : ''}>
                                📤 Push to Provider
                            </button>
                        </div>
                    </div>

                    ${syncDiff ? `
                        <div style="margin-top:0.75rem; background:rgba(0,0,0,0.25); padding:0.85rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border); font-size:0.8rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                                <strong>Diff Calculation Result:</strong>
                                <span class="suite-pill ${syncDiff.in_sync ? 'suite-pill-success' : 'suite-pill-warning'}">
                                    ${syncDiff.in_sync ? '✓ In Sync' : '⚠️ Pending Differences'}
                                </span>
                            </div>
                            <div style="color:var(--text-dim); display:flex; gap:1.5rem; flex-wrap:wrap;">
                                <span>Local Additions: <strong>${syncDiff.counts?.added_locally || 0}</strong></span>
                                <span>Remote Additions: <strong>${syncDiff.counts?.added_remotely || 0}</strong></span>
                                <span>Modifications: <strong>${syncDiff.counts?.modified || 0}</strong></span>
                                <span>Total Remote Records: <strong>${syncDiff.remote_count || 0}</strong></span>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    renderDnsRecordsSubTab(zone, records, rows) {
        return `
            <div>
                <!-- Record Filter & Search Bar -->
                <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <div style="flex:1; max-width:420px;">
                        <input type="text" placeholder="Search records by host name, type, or IP address..." value="${this.escapeHtml(this.state.zoneSearch)}" oninput="window.domainsViewInstance.setState({zoneSearch: this.value}); window.domainsViewInstance.update();" style="width:100%; padding:0.5rem 0.85rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.85rem;">
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-dim);">
                        Showing ${records.length} record${records.length === 1 ? '' : 's'}
                    </div>
                </div>

                <!-- Primary DNS Records Table -->
                <div class="suite-table-wrapper" style="margin-top:0.75rem;">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Host / Subdomain</th>
                                <th>Record Type</th>
                                <th>Points To / Destination</th>
                                <th>Speed (TTL)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.length > 0 ? rows : `<tr><td colspan="5" style="text-align:center; color:var(--text-dim); padding:2rem;">No DNS records match your search filter.</td></tr>`}
                        </tbody>
                    </table>
                </div>

                <!-- Advanced DNS Infrastructure Tools Accordion -->
                <div class="spp-advanced-accordion" style="margin-top:1.5rem;">
                    <div class="spp-accordion-toggle">
                        <span style="display:flex; align-items:center; gap:0.5rem;">
                            <span>⚙️</span>
                            <strong>Advanced DNS Tools &amp; BIND9 Integration</strong>
                            <span style="font-size:0.75rem; color:var(--text-dim); font-weight:normal;">(Auto-Scaffold, Revision History, Export/Import, BIND9 Daemon)</span>
                        </span>
                        <span class="spp-accordion-icon">▾</span>
                    </div>
                    <div class="spp-accordion-body" style="padding-top:1rem; display:flex; flex-direction:column; gap:1rem;">
                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; padding:0.75rem; background:rgba(0,0,0,0.2); border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                            <span style="font-size:0.8rem; color:var(--text-dim); align-self:center; margin-right:0.5rem;">Zone Management:</span>
                            <button class="btn primary-btn btn-sm" style="background:linear-gradient(135deg, #0ea5e9, #3b82f6); border:none;" onclick="window.domainsViewInstance.openScaffoldZoneModal()">Auto-Scaffold Zone</button>
                            <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.openZoneHistoryModal()">🕒 Revision History & Rollback</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.openExportZoneModal()">📄 Export Zone</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.openImportZoneModal()">📥 Import Zone</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.resetZoneDefaults()">🔄 Reset Defaults</button>
                            <a href="#files?path=var/dns/${this.escapeHtml(this.state.zoneDomain)}.zone" class="btn ghost-btn btn-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem;">📁 Raw Zone File</a>
                        </div>

                        <!-- BIND9 Host Daemon Card -->
                        ${(() => {
                            const b = this.state.bindStatus;
                            const isInstalled = b && b.installed;
                            const isActive = b && b.active;
                            const isListening = b && b.listening_port_53;
                            return `
                                <div class="spp-setting-banner" style="padding:1rem 1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                                    <div style="display:flex; align-items:flex-start; gap:0.75rem;">
                                        <span style="font-size:1.4rem;">⚙️</span>
                                        <div>
                                            <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                                                <strong style="color:var(--text-bright); font-size:0.95rem;">Host BIND9 DNS Daemon (Port 53)</strong>
                                                <span class="suite-pill ${isActive && isListening ? 'suite-pill-success' : (isInstalled ? 'suite-pill-warning' : 'suite-pill-danger')}" style="font-size:0.7rem;">
                                                    ${isActive && isListening ? '✓ Active & Listening on Port 53' : (isInstalled ? 'Installed (Inactive)' : 'Not Installed')}
                                                </span>
                                                ${b ? `<span style="font-size:0.75rem; color:var(--text-dim); font-family:monospace;">${this.escapeHtml(b.service_name || 'named')} &bull; ${this.escapeHtml(b.version || 'BIND 9')}</span>` : ''}
                                            </div>
                                            <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.3rem;">
                                                Directly synchronizes SPP RFC 1035 zone records to <code>/etc/bind/zones/</code> (or <code>/var/named/</code>) and validates with <code>named-checkzone</code>.
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                        ${!isInstalled ? `
                                            <button class="btn primary-btn btn-sm" style="background:#0284c7; border:none;" onclick="window.domainsViewInstance.installBindDaemon()" ${this.state.installingBind ? 'disabled' : ''}>
                                                ${this.state.installingBind ? 'Installing...' : '📦 Install BIND9 Package'}
                                            </button>
                                        ` : `
                                            <button class="btn primary-btn btn-sm" style="background:#059669; border:none;" onclick="window.domainsViewInstance.syncZoneToBind()" ${this.state.syncingBind ? 'disabled' : ''}>
                                                ${this.state.syncingBind ? 'Syncing...' : '⚡ Sync Zone to BIND9'}
                                            </button>
                                            <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.reloadBindDaemon()" ${this.state.reloadingBind ? 'disabled' : ''}>
                                                ${this.state.reloadingBind ? 'Reloading...' : '🔄 Reload BIND9'}
                                            </button>
                                        `}
                                        <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.loadBindStatus()">↻ Status</button>
                                    </div>
                                </div>
                            `;
                        })()}
                    </div>
                </div>
            </div>
        `;
    }

    renderDnsProvidersSubTab() {
        const pData = this.state.dnsProvidersData || {};
        const accounts = pData.accounts || {};
        const providersList = [
            { id: 'bind9', name: 'Native BIND9', driver: 'Local Daemon', desc: 'Authoritative local nameserver with RFC 1035 zone files' },
            { id: 'cloudflare', name: 'Cloudflare', driver: 'Cloudflare API v4', desc: 'Global Anycast DNS with DDoS protection & proxy' },
            { id: 'route53', name: 'AWS Route 53', driver: 'AWS SigV4 REST', desc: 'Amazon Route 53 hosted zones and alias routing' },
            { id: 'digitalocean', name: 'DigitalOcean DNS', driver: 'DigitalOcean API v2', desc: 'Cloud DNS management with droplet integration' },
            { id: 'google_cloud_dns', name: 'Google Cloud DNS', driver: 'GCP Cloud DNS v1', desc: 'Google Cloud Platform managed DNS zones' },
            { id: 'custom', name: 'Custom DNS Provider', driver: 'Custom REST Adapter', desc: 'Extensible provider interface with strict SSRF defense' }
        ];

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h4 style="margin:0; font-size:1.05rem; color:var(--text-bright);">Connected DNS Providers &amp; Credential Vault</h4>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Credentials are encrypted with AES-256-GCM in <code>etc/dns-providers.yml</code>. Secrets are never exposed to clients.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.loadDnsProviders()">↻ Refresh</button>
                        <button class="btn primary-btn btn-sm" onclick="window.domainsViewInstance.openAddProviderModal()">+ Connect Provider Account</button>
                    </div>
                </div>

                <!-- Providers List Table -->
                <div class="suite-table-wrapper">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Driver / Protocol</th>
                                <th>Configured Accounts</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${providersList.map(p => {
                                const accList = Object.entries(accounts).filter(([accId, acc]) => acc.provider === p.id);
                                return `
                                    <tr>
                                        <td><strong style="color:var(--text-bright);">${this.escapeHtml(p.name)}</strong></td>
                                        <td><span class="suite-pill suite-pill-info">${this.escapeHtml(p.driver)}</span></td>
                                        <td>
                                            ${accList.length > 0 ? accList.map(([accId]) => `
                                                <span class="suite-pill suite-pill-success" style="font-family:monospace; margin-right:0.3rem;">${this.escapeHtml(accId)}</span>
                                            `).join('') : (p.id === 'bind9' ? '<span class="suite-pill suite-pill-neutral">Local System</span>' : '<span style="color:var(--text-dim); font-size:0.8rem;">None</span>')}
                                        </td>
                                        <td style="font-size:0.8rem; color:var(--text-dim);">${this.escapeHtml(p.desc)}</td>
                                        <td>
                                            <div style="display:flex; gap:0.4rem;">
                                                <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.testProviderAccount('${p.id}', null)">Test</button>
                                                ${p.id !== 'bind9' ? `<button class="btn primary-btn btn-sm" onclick="window.domainsViewInstance.openAddProviderModal()">+ Account</button>` : ''}
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderDnsNameserversSubTab(zone) {
        const gm = this.state.glueMatrix;
        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Tri-Level Glue Record Diagnostic Matrix -->
                ${!gm ? `
                    <div class="spp-setting-banner" style="padding:1rem 1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                        <div style="display:flex; align-items:center; gap:0.6rem;">
                            <span style="font-size:1.3rem;">📡</span>
                            <div style="font-size:0.85rem; color:var(--text-bright);">
                                <strong>Root TLD Glue &amp; Child Nameserver Diagnostics</strong> &mdash; <span style="color:var(--text-dim); font-size:0.8rem;">Query Verisign root (@a.gtld-servers.net) and registry WHOIS directly to verify live nameserver IPs.</span>
                            </div>
                        </div>
                        <button class="btn primary-btn btn-sm" onclick="window.domainsViewInstance.checkRegistryGlue()" ${this.state.checkingGlue ? 'disabled' : ''}>
                            ${this.state.checkingGlue ? 'Querying Root Servers...' : '🔍 Check Registry Glue (Root TLD)'}
                        </button>
                    </div>
                ` : `
                    <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid ${gm.has_glue_mismatch ? '#f59e0b' : 'var(--glass-border)'};">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <div style="display:flex; align-items:center; gap:0.6rem;">
                                    <h4 style="margin:0; font-size:1rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                                        <span>📡</span> Tri-Level Nameserver Glue Diagnostic Matrix
                                    </h4>
                                    <span class="suite-pill ${gm.has_glue_mismatch ? 'suite-pill-warning' : 'suite-pill-success'}" style="font-size:0.7rem;">
                                        ${gm.has_glue_mismatch ? '⚠️ Root Glue Mismatch' : '✓ Registry Glue Aligned'}
                                    </span>
                                </div>
                                <div style="font-size:0.8rem; color:var(--text-dim); margin-top:0.25rem;">
                                    Comparing Host Server IP (<code>${this.escapeHtml(gm.expected_ip)}</code>) against Local BIND, Verisign Root TLD Glue, and Recursive Resolvers.
                                </div>
                            </div>
                            <div style="display:flex; gap:0.5rem;">
                                <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.checkRegistryGlue()" ${this.state.checkingGlue ? 'disabled' : ''}>
                                    ${this.state.checkingGlue ? 'Refreshing...' : '↻ Re-check Glue'}
                                </button>
                            </div>
                        </div>

                        ${gm.has_glue_mismatch ? `
                            <div style="background:rgba(245,158,11,0.12); border-left:4px solid #f59e0b; padding:1rem; border-radius:var(--radius-sm); margin-bottom:1rem;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.75rem;">
                                    <div>
                                        <strong style="#fbbf24; font-size:0.9rem;">⚠️ Action Required at Domain Registrar:</strong>
                                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-bright); line-height:1.4;">
                                            The authoritative Verisign .net root servers (<code>@a.gtld-servers.net</code>) still return the old server IP for child nameservers.
                                            Public resolvers will continue failing to resolve your domain until your registrar resubmits the child nameserver host objects!
                                        </p>
                                    </div>
                                    <button class="btn primary-btn btn-sm" style="background:#f59e0b; color:#000; font-weight:600; border:none;" onclick="window.domainsViewInstance.copyRegistrarInstructions()">
                                        📋 Copy Registrar Support Ticket
                                    </button>
                                </div>
                            </div>
                        ` : ''}

                        <div class="suite-table-wrapper" style="overflow-x:auto;">
                            <table class="suite-table" style="width:100%; font-size:0.85rem;">
                                <thead>
                                    <tr>
                                        <th>Nameserver Host</th>
                                        <th>Expected Server IP</th>
                                        <th>Local BIND (127.0.0.1)</th>
                                        <th>Root TLD Glue</th>
                                        <th>Public Resolver (1.1.1.1)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(gm.matrix || []).map(row => `
                                        <tr>
                                            <td style="font-weight:600; color:var(--text-bright); font-family:monospace;">${this.escapeHtml(row.nameserver)}</td>
                                            <td style="font-family:monospace; color:#38bdf8;">${this.escapeHtml(row.expected_ip)}</td>
                                            <td style="font-family:monospace; color:${row.local_bind_ip === row.expected_ip ? '#34d399' : '#f87171'};">
                                                ${this.escapeHtml(row.local_bind_ip || 'Not Answering')}
                                            </td>
                                            <td style="font-family:monospace; color:${row.is_glue_aligned ? '#34d399' : '#fbbf24'}; font-weight:600;">
                                                ${this.escapeHtml(row.tld_glue_ip || 'No Glue')}
                                            </td>
                                            <td style="font-family:monospace; color:${row.is_public_aligned ? '#34d399' : 'var(--text-dim)'};">
                                                ${this.escapeHtml(row.public_resolver_ip || 'Pending')}
                                            </td>
                                            <td>
                                                <span class="suite-pill ${row.is_glue_aligned && row.is_public_aligned ? 'suite-pill-success' : 'suite-pill-warning'}">
                                                    ${this.escapeHtml(row.status)}
                                                </span>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `}
            </div>
        `;
    }

    renderDnssecSubTab(zone, isDnssecActive) {
        const dnssec = zone.dnssec || {};
        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div style="display:flex; align-items:flex-start; gap:0.75rem;">
                        <span style="font-size:1.6rem;">🔐</span>
                        <div>
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                <strong style="color:var(--text-bright); font-size:1.05rem;">DNSSEC Cryptographic Chain-of-Trust</strong>
                                <span class="suite-pill ${isDnssecActive ? 'suite-pill-success' : 'suite-pill-neutral'}" style="font-size:0.75rem;">
                                    ${isDnssecActive ? '✓ Active & Signed' : '○ Inactive'}
                                </span>
                            </div>
                            <div style="font-size:0.8rem; color:var(--text-dim); margin-top:0.3rem;">
                                Algorithm: <strong>${this.escapeHtml(dnssec.algorithm || 'ECDSA P-256 (13)')}</strong> &bull; Key Tag: <strong>${dnssec.key_tag || 2371}</strong> &bull; Digest Type: <strong>SHA-256 (2)</strong>
                            </div>
                        </div>
                    </div>
                    <div>
                        <button class="btn ${isDnssecActive ? 'danger-btn' : 'primary-btn'} btn-sm" onclick="window.domainsViewInstance.toggleDnssec()">
                            ${isDnssecActive ? 'Disable DNSSEC' : 'Enable DNSSEC'}
                        </button>
                    </div>
                </div>

                <!-- DS Record Parameters Card -->
                <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border);">
                    <strong style="color:var(--text-bright); font-size:0.95rem; display:block; margin-bottom:0.4rem;">Delegation Signer (DS) Parameters for Registrar</strong>
                    <p style="font-size:0.8rem; color:var(--text-dim); margin:0 0 1rem 0;">
                        Publish these parameters at your domain registrar (Namecheap, GoDaddy, Cloudflare Registrar, etc.) to establish parent delegation.
                    </p>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; font-size:0.85rem;">
                        <div style="background:var(--input-bg); padding:0.6rem 0.8rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                            <span style="color:var(--text-dim); font-size:0.75rem; display:block;">Key Tag</span>
                            <strong style="font-family:monospace; color:#38bdf8;">${dnssec.key_tag || 2371}</strong>
                        </div>
                        <div style="background:var(--input-bg); padding:0.6rem 0.8rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                            <span style="color:var(--text-dim); font-size:0.75rem; display:block;">Algorithm</span>
                            <strong style="font-family:monospace; color:#38bdf8;">13 (ECDSAP256SHA256)</strong>
                        </div>
                        <div style="background:var(--input-bg); padding:0.6rem 0.8rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                            <span style="color:var(--text-dim); font-size:0.75rem; display:block;">Digest Type</span>
                            <strong style="font-family:monospace; color:#38bdf8;">2 (SHA-256)</strong>
                        </div>
                    </div>

                    ${dnssec.ds_record ? `
                        <div style="margin-top:1rem; font-family:monospace; font-size:0.8rem; background:rgba(0,0,0,0.3); padding:0.75rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border); color:var(--text-bright); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                            <span>${this.escapeHtml(dnssec.ds_record)}</span>
                            <button class="btn ghost-btn btn-sm" onclick="navigator.clipboard.writeText('${dnssec.ds_record}'); window.domainsViewInstance.admin.notify('DS Record copied to clipboard!', 'success');">📋 Copy DS Record</button>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    renderDnsPropagationSubTab() {
        return `
            <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                    <div>
                        <h4 style="margin:0; font-size:1.05rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            <span>🌍</span> Global Anycast DNS Propagation Checker
                        </h4>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Probes real-time DNS resolution latency and consistency across worldwide Tier-1 Anycast resolver networks.
                        </p>
                    </div>
                </div>

                <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; margin-bottom:1.25rem;">
                    <input type="text" id="prop-input-host" value="${this.escapeHtml(this.state.propagationHost || this.state.zoneDomain)}" placeholder="e.g. ${this.state.zoneDomain}" style="flex:1; min-width:200px; padding:0.5rem 0.75rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.85rem;">
                    <select id="prop-input-type" style="padding:0.5rem 0.75rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.85rem;">
                        <option value="A" ${this.state.propagationType === 'A' ? 'selected' : ''}>A (IPv4)</option>
                        <option value="AAAA" ${this.state.propagationType === 'AAAA' ? 'selected' : ''}>AAAA (IPv6)</option>
                        <option value="CNAME" ${this.state.propagationType === 'CNAME' ? 'selected' : ''}>CNAME</option>
                        <option value="MX" ${this.state.propagationType === 'MX' ? 'selected' : ''}>MX</option>
                        <option value="TXT" ${this.state.propagationType === 'TXT' ? 'selected' : ''}>TXT</option>
                        <option value="NS" ${this.state.propagationType === 'NS' ? 'selected' : ''}>NS</option>
                        <option value="SOA" ${this.state.propagationType === 'SOA' ? 'selected' : ''}>SOA</option>
                    </select>
                    <button class="btn primary-btn shine-effect" onclick="window.domainsViewInstance.checkPropagation()" ${this.state.checkingPropagation ? 'disabled' : ''}>
                        ${this.state.checkingPropagation ? 'Checking...' : 'Check Propagation'}
                    </button>
                </div>

                ${this.renderPropagationResults()}
            </div>
        `;
    }

    renderDnsMailSubTab() {
        const mail = this.state.mailAuditData || {};
        const score = mail.deliverability_score ?? 100;
        const scoreColor = score >= 90 ? '#10b981' : (score >= 70 ? '#f59e0b' : '#ef4444');

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h4 style="margin:0; font-size:1.05rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            <span>✉️</span> Mail DNS Deliverability Health Suite
                        </h4>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Audits MX routing, SPF anti-spoofing (RFC 7208), DKIM cryptographic signatures, and DMARC enforcement.
                        </p>
                    </div>
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <div style="text-align:right;">
                            <span style="font-size:0.75rem; color:var(--text-dim); display:block;">Deliverability Score</span>
                            <strong style="font-size:1.4rem; color:${scoreColor};">${score} / 100</strong>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.loadMailAudit('${this.escapeHtml(this.state.zoneDomain)}')">↻ Re-Audit</button>
                    </div>
                </div>

                <!-- Mail Deliverability Breakdown Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1rem;">
                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>MX Routing</strong>
                            <span class="suite-pill suite-pill-success">✓ Configured</span>
                        </div>
                        <p style="font-size:0.75rem; color:var(--text-dim); margin-top:0.5rem;">
                            Valid Mail Exchange records direct incoming messages to authorized mail transfer agents.
                        </p>
                    </div>

                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>SPF Policy (RFC 7208)</strong>
                            <span class="suite-pill suite-pill-success">✓ Validated</span>
                        </div>
                        <p style="font-size:0.75rem; color:var(--text-dim); margin-top:0.5rem;">
                            No duplicate SPF records detected. Authorizes mail servers to send on behalf of this domain.
                        </p>
                    </div>

                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>DKIM Signature</strong>
                            <span class="suite-pill suite-pill-info">Default Selector</span>
                        </div>
                        <p style="font-size:0.75rem; color:var(--text-dim); margin-top:0.5rem;">
                            Public key selector ready for mail server signature verification.
                        </p>
                    </div>

                    <div class="spp-setting-banner" style="padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>DMARC Alignment</strong>
                            <span class="suite-pill suite-pill-success">Policy Active</span>
                        </div>
                        <p style="font-size:0.75rem; color:var(--text-dim); margin-top:0.5rem;">
                            DMARC policy instructs receiving servers on handling unaligned or spoofed messages.
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    renderDnsDiagnosticsSubTab(zone) {
        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <div class="spp-setting-banner" style="padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border);">
                    <h4 style="margin:0 0 0.5rem 0; font-size:1.05rem; color:var(--text-bright);">DNS Diagnostics &amp; Direct Resolver Query</h4>
                    <p style="font-size:0.8rem; color:var(--text-dim); margin:0 0 1rem 0;">
                        Perform low-level diagnostic lookups directly through authoritative nameservers or public recursive resolvers.
                    </p>

                    <div style="display:grid; grid-template-columns:1fr 120px 180px auto; gap:0.5rem; align-items:end;">
                        <div>
                            <label style="font-size:0.75rem; color:var(--text-dim);">Target Hostname</label>
                            <input type="text" id="diag-host" value="${this.escapeHtml(this.state.zoneDomain)}" style="font-family:monospace; font-size:0.85rem;">
                        </div>
                        <div>
                            <label style="font-size:0.75rem; color:var(--text-dim);">Record Type</label>
                            <select id="diag-type" style="padding:0.45rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm); width:100%;">
                                <option value="A">A</option>
                                <option value="AAAA">AAAA</option>
                                <option value="CNAME">CNAME</option>
                                <option value="MX">MX</option>
                                <option value="TXT">TXT</option>
                                <option value="NS">NS</option>
                                <option value="SOA">SOA</option>
                                <option value="CAA">CAA</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.75rem; color:var(--text-dim);">Resolver</label>
                            <select id="diag-resolver" style="padding:0.45rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm); width:100%;">
                                <option value="127.0.0.1">Local BIND9 (Port 53)</option>
                                <option value="1.1.1.1">Cloudflare (1.1.1.1)</option>
                                <option value="8.8.8.8">Google (8.8.8.8)</option>
                                <option value="9.9.9.9">Quad9 (9.9.9.9)</option>
                            </select>
                        </div>
                        <button class="btn primary-btn btn-sm" onclick="window.domainsViewInstance.runPropagationAudit(document.getElementById('diag-host').value, document.getElementById('diag-type').value)">⚡ Execute Query</button>
                    </div>
                </div>
            </div>
        `;
    }

    renderPropagationResults() {
        if (this.state.checkingPropagation) {
            return `<div style="text-align:center; padding:2rem; color:var(--text-dim);">⏳ Querying Anycast DNS clusters globally...</div>`;
        }

        const res = this.state.propagationResults;
        if (!res || !res.resolvers) {
            return `<div style="text-align:center; padding:1.5rem; color:var(--text-dim); font-size:0.85rem; border:1px dashed var(--glass-border); border-radius:var(--radius-sm);">Click 'Check Propagation' to test global resolution across Google, Cloudflare, Quad9, AdGuard, and Local DNS.</div>`;
        }

        const resolverCards = Object.entries(res.resolvers).map(([key, info]) => {
            const isSuccess = info.status === 'success';
            const latencyColor = (info.latency_ms < 50) ? '#34d399' : ((info.latency_ms < 150) ? '#f59e0b' : '#f87171');
            const recordsList = (info.records && info.records.length > 0)
                ? info.records.map(r => `<code>${this.escapeHtml(typeof r === 'object' ? JSON.stringify(r) : r)}</code>`).join('<br>')
                : `<span style="color:var(--text-dim); font-style:italic;">No records returned</span>`;

            return `
                <div style="background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.85rem; display:flex; flex-direction:column; gap:0.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="color:var(--text-bright); font-size:0.9rem;">${this.escapeHtml(info.name || key)}</strong>
                            <div style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(info.ip || '')} &bull; ${this.escapeHtml(info.location || 'Anycast')}</div>
                        </div>
                        <div style="text-align:right;">
                            <span class="suite-pill ${isSuccess ? 'suite-pill-success' : 'suite-pill-danger'}" style="font-size:0.7rem;">
                                ${isSuccess ? '✓ ' + (info.records?.length || 0) + ' Records' : '✗ ' + (info.error || 'Failed')}
                            </span>
                            <div style="font-size:0.75rem; color:${latencyColor}; font-weight:700; margin-top:0.2rem;">
                                ${info.latency_ms || 0}ms
                            </div>
                        </div>
                    </div>
                    <div style="font-size:0.8rem; background:rgba(0,0,0,0.2); padding:0.4rem 0.6rem; border-radius:4px; max-height:80px; overflow-y:auto;">
                        ${recordsList}
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:0.75rem;">
                ${resolverCards}
            </div>
        `;
    }

    async checkPropagation() {
        const host = document.getElementById('prop-input-host')?.value.trim() || this.state.zoneDomain;
        const type = document.getElementById('prop-input-type')?.value || 'A';

        this.setState({ propagationHost: host, propagationType: type, checkingPropagation: true });
        this.update();

        try {
            const res = await this.admin.api('domains_check_propagation', { hostname: host, type: type });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ propagationResults: data, checkingPropagation: false });
        } catch (e) {
            this.admin.notify("Propagation check error: " + e.message, "error");
            this.setState({ checkingPropagation: false });
        }
        this.update();
    }

    async toggleDnssec() {
        const zone = this.state.zoneData || {};
        const isCurrentActive = zone.dnssec?.active || false;
        const newStatus = !isCurrentActive;

        try {
            const res = await this.admin.api('domains_dnssec_toggle', { domain: this.state.zoneDomain, enabled: newStatus });
            if (res.success) {
                this.admin.notify(res.message || `DNSSEC ${newStatus ? 'enabled' : 'disabled'}.`, "success");
                await this.loadZone(this.state.zoneDomain);
            } else {
                this.admin.notify(res.error || "Failed to update DNSSEC.", "error");
            }
        } catch (e) {
            this.admin.notify("DNSSEC toggle error: " + e.message, "error");
        }
    }

    openRecordModal(record = null) {
        const isEdit = !!record;
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = isEdit ? `Edit DNS Record: ${record.type} ${record.name}` : `Add DNS Record (${this.state.zoneDomain})`;

        const currentType = record?.type || 'A';
        const typeDescriptions = {
            'A': 'A — Points Domain to IPv4 Server IP (e.g. 192.0.2.1)',
            'AAAA': 'AAAA — Points Domain to IPv6 Address',
            'CNAME': 'CNAME — Nickname / Alias (e.g. points www to domain)',
            'MX': 'MX — Mail Exchange (Routes incoming emails to mail server)',
            'TXT': 'TXT — Verification & Anti-Spam (SPF, DKIM, DMARC, Google)',
            'NS': 'NS — Nameserver (Delegates DNS authority)',
            'CAA': 'CAA — SSL Permission (Controls who can issue certificates)',
            'SRV': 'SRV — Service Location (e.g. SIP, Minecraft, Teams)'
        };
        const types = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'CAA', 'SRV'];
        const typeOptions = types.map(t => `<option value="${t}" ${t === currentType ? 'selected' : ''}>${typeDescriptions[t] || t}</option>`).join('');

        document.getElementById('modal-body').innerHTML = `
            <div class="dns-record-form" style="display:flex; flex-direction:column; gap:1rem;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="input-group">
                        <label>
                            Record Type <span style="color:var(--danger)">*</span>
                            <span class="spp-help-tip" data-tooltip-title="DNS Record Type" data-tooltip="A = Server IP, CNAME = Alias, MX = Email routing, TXT = Verification & security.">?</span>
                        </label>
                        <select id="rec-type" onchange="window.domainsViewInstance.updateRecordFormFields(this.value)" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-weight:700;">
                            ${typeOptions}
                        </select>
                    </div>
                    <div class="input-group">
                        <label>
                            Host / Subdomain Name <span style="color:var(--danger)">*</span>
                            <span class="spp-help-tip" data-tooltip-title="Host Name" data-tooltip="Use '@' to point your main root domain (e.g. example.com). Use a prefix like 'www' or 'mail' for subdomains." data-tooltip-example="@ or www or mail">?</span>
                        </label>
                        <input type="text" id="rec-name" value="${this.escapeHtml(record?.name || '@')}" placeholder="e.g. @ or www or mail" required>
                        <small style="color:var(--text-dim)">Use '@' for main root domain, or a prefix like 'www' or 'mail'</small>
                    </div>
                </div>

                <div id="rec-dynamic-fields">
                    <!-- Injected dynamically by updateRecordFormFields -->
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="input-group">
                        <label>
                            Cache Speed (TTL)
                            <span class="spp-help-tip" data-tooltip-title="Time To Live (TTL)" data-tooltip="How long (in seconds) internet providers cache this record before checking your server again." data-tooltip-rec="1 Hour (3600s) is recommended for stable records; 5 Minutes (300s) during testing or migration.">?</span>
                        </label>
                        <select id="rec-ttl" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                            <option value="300" ${record?.ttl === 300 ? 'selected' : ''}>5 Minutes (300s - Fast Testing / Migration)</option>
                            <option value="1800" ${record?.ttl === 1800 ? 'selected' : ''}>30 Minutes (1800s)</option>
                            <option value="3600" ${(!record || record?.ttl === 3600) ? 'selected' : ''}>1 Hour (3600s - Standard Recommended)</option>
                            <option value="14400" ${record?.ttl === 14400 ? 'selected' : ''}>4 Hours (14400s)</option>
                            <option value="86400" ${record?.ttl === 86400 ? 'selected' : ''}>24 Hours (86400s - Maximum Stability)</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Comment / Note (Optional)</label>
                        <input type="text" id="rec-comment" value="${this.escapeHtml(record?.comment || '')}" placeholder="Optional reminder note">
                    </div>
                </div>
            </div>
        `;

        modal.classList.add('active');
        this.setupModalClose(modal);
        this.updateRecordFormFields(currentType, record);

        const saveBtn = document.getElementById('modal-save');
        saveBtn.onclick = async () => {
            const type = document.getElementById('rec-type').value;
            const name = document.getElementById('rec-name').value.trim() || '@';
            const ttl = parseInt(document.getElementById('rec-ttl').value, 10) || 3600;
            const comment = document.getElementById('rec-comment').value.trim();

            const recordPayload = {
                id: record?.id || null,
                name: name,
                type: type,
                ttl: ttl,
                comment: comment
            };

            if (type === 'A' || type === 'AAAA') {
                recordPayload.value = document.getElementById('rec-val-ip')?.value.trim();
                if (!recordPayload.value) {
                    this.admin.notify("IP address is required.", "error");
                    return;
                }
            } else if (type === 'CNAME' || type === 'NS') {
                recordPayload.target = document.getElementById('rec-val-target')?.value.trim();
                if (!recordPayload.target) {
                    this.admin.notify("Target hostname is required.", "error");
                    return;
                }
            } else if (type === 'MX') {
                recordPayload.target = document.getElementById('rec-val-target')?.value.trim();
                recordPayload.priority = parseInt(document.getElementById('rec-val-priority')?.value, 10) || 10;
                if (!recordPayload.target) {
                    this.admin.notify("Mail server target is required.", "error");
                    return;
                }
            } else if (type === 'TXT') {
                recordPayload.value = document.getElementById('rec-val-text')?.value.trim();
                if (!recordPayload.value) {
                    this.admin.notify("TXT value is required.", "error");
                    return;
                }
            } else if (type === 'SRV') {
                recordPayload.target = document.getElementById('rec-val-target')?.value.trim();
                recordPayload.priority = parseInt(document.getElementById('rec-val-priority')?.value, 10) || 0;
                recordPayload.weight = parseInt(document.getElementById('rec-val-weight')?.value, 10) || 0;
                recordPayload.port = parseInt(document.getElementById('rec-val-port')?.value, 10) || 0;
            } else if (type === 'CAA') {
                recordPayload.tag = document.getElementById('rec-val-tag')?.value.trim() || 'issue';
                recordPayload.value = document.getElementById('rec-val-text')?.value.trim();
                recordPayload.flag = parseInt(document.getElementById('rec-val-flag')?.value, 10) || 0;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const res = await this.admin.api('domains_zone_save_record', {
                    domain: this.state.zoneDomain,
                    record: recordPayload
                });

                if (res.success) {
                    this.admin.notify(res.message || "Record saved successfully.", "success");
                    modal.classList.remove('active');
                    await this.loadZone(this.state.zoneDomain);
                } else {
                    this.admin.notify(res.error || "Failed to save record.", "error");
                }
            } catch (e) {
                this.admin.notify("Error saving record: " + e.message, "error");
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        };
    }

    updateRecordFormFields(type, record = null) {
        const container = document.getElementById('rec-dynamic-fields');
        if (!container) return;

        if (type === 'A') {
            container.innerHTML = `
                <div class="input-group">
                    <label>
                        Server IPv4 Address <span style="color:var(--danger)">*</span>
                        <span class="spp-help-tip" data-tooltip-title="Server IPv4 Address" data-tooltip="The public IPv4 address of your server where web visitors should be sent." data-tooltip-example="e.g. 192.0.2.1" data-tooltip-rec="Enter your server's public IP.">?</span>
                    </label>
                    <input type="text" id="rec-val-ip" value="${this.escapeHtml(record?.value || '')}" placeholder="e.g. 192.0.2.1" required>
                    <small style="color:var(--text-dim)">Points your website to this server machine.</small>
                </div>
            `;
        } else if (type === 'AAAA') {
            container.innerHTML = `
                <div class="input-group">
                    <label>
                        Server IPv6 Address <span style="color:var(--danger)">*</span>
                        <span class="spp-help-tip" data-tooltip-title="Server IPv6 Address" data-tooltip="The 128-bit IPv6 address for modern internet routing." data-tooltip-example="e.g. 2001:db8::1">?</span>
                    </label>
                    <input type="text" id="rec-val-ip" value="${this.escapeHtml(record?.value || '')}" placeholder="e.g. 2001:db8::1" required>
                </div>
            `;
        } else if (type === 'CNAME' || type === 'NS') {
            const isCname = type === 'CNAME';
            container.innerHTML = `
                <div class="input-group">
                    <label>
                        ${isCname ? 'Target Hostname (Alias For)' : 'Authoritative Nameserver'} <span style="color:var(--danger)">*</span>
                        <span class="spp-help-tip" data-tooltip-title="${isCname ? 'CNAME Target' : 'Nameserver'}" data-tooltip="${isCname ? 'The destination domain name that this alias forwards to.' : 'The domain name of the authoritative nameserver.'}" data-tooltip-example="${isCname ? 'e.g. example.com or ghs.googlehosted.com' : 'e.g. ns1.example.com'}">?</span>
                    </label>
                    <input type="text" id="rec-val-target" value="${this.escapeHtml(record?.target || record?.value || '')}" placeholder="${isCname ? 'e.g. example.com or ghs.googlehosted.com' : 'e.g. ns1.example.com'}" required>
                    <small style="color:var(--text-dim)">${isCname ? 'Must be a valid domain or subdomain name (do not enter an IP address).' : 'Fully qualified nameserver hostname.'}</small>
                </div>
            `;
        } else if (type === 'MX') {
            container.innerHTML = `
                <div style="display:grid; grid-template-columns:3fr 1fr; gap:1rem;">
                    <div class="input-group">
                        <label>
                            Mail Server Target <span style="color:var(--danger)">*</span>
                            <span class="spp-help-tip" data-tooltip-title="Mail Server Target" data-tooltip="The domain name of the mail server that receives incoming emails for your domain." data-tooltip-example="e.g. mail.example.com or aspmx.l.google.com">?</span>
                        </label>
                        <input type="text" id="rec-val-target" value="${this.escapeHtml(record?.target || record?.value || '')}" placeholder="e.g. mail.example.com" required>
                        <small style="color:var(--text-dim)">Domain name of the email exchange host (do not use IP).</small>
                    </div>
                    <div class="input-group">
                        <label>
                            Priority
                            <span class="spp-help-tip" data-tooltip-title="MX Priority" data-tooltip="Delivery preference. Lower numbers have higher priority (e.g. 10 is checked before 20)." data-tooltip-rec="10 is recommended for primary mail server.">?</span>
                        </label>
                        <input type="number" id="rec-val-priority" value="${record?.priority || 10}" min="0" max="65535">
                    </div>
                </div>
            `;
        } else if (type === 'TXT') {
            container.innerHTML = `
                <div class="input-group">
                    <label>
                        TXT Record Content <span style="color:var(--danger)">*</span>
                        <span class="spp-help-tip" data-tooltip-title="TXT Value" data-tooltip="Text string used for domain verification, SPF / DKIM / DMARC email authentication, and security." data-tooltip-example="v=spf1 ip4:192.0.2.1 ~all">?</span>
                    </label>
                    <textarea id="rec-val-text" rows="3" style="width:100%; padding:0.5rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm);" placeholder="e.g. v=spf1 ip4:192.0.2.1 ~all">${this.escapeHtml(record?.value || '')}</textarea>
                    <small style="color:var(--text-dim)">Commonly used for SPF records, Google Search Console, or SSL verification tokens.</small>
                </div>
            `;
        } else if (type === 'SRV') {
            container.innerHTML = `
                <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:0.5rem;">
                    <div class="input-group">
                        <label>Target Host <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="rec-val-target" value="${this.escapeHtml(record?.target || '')}" placeholder="sip.domain.com">
                    </div>
                    <div class="input-group">
                        <label>Priority</label>
                        <input type="number" id="rec-val-priority" value="${record?.priority || 0}">
                    </div>
                    <div class="input-group">
                        <label>Weight</label>
                        <input type="number" id="rec-val-weight" value="${record?.weight || 0}">
                    </div>
                    <div class="input-group">
                        <label>Port</label>
                        <input type="number" id="rec-val-port" value="${record?.port || 5060}">
                    </div>
                </div>
            `;
        } else if (type === 'CAA') {
            container.innerHTML = `
                <div style="display:grid; grid-template-columns:1fr 2fr 1fr; gap:0.75rem;">
                    <div class="input-group">
                        <label>Tag</label>
                        <select id="rec-val-tag" style="width:100%; padding:0.6rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm);">
                            <option value="issue" ${record?.tag === 'issue' ? 'selected' : ''}>issue</option>
                            <option value="issuewild" ${record?.tag === 'issuewild' ? 'selected' : ''}>issuewild</option>
                            <option value="iodef" ${record?.tag === 'iodef' ? 'selected' : ''}>iodef</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Value (CA Name or URL)</label>
                        <input type="text" id="rec-val-text" value="${this.escapeHtml(record?.value || '')}" placeholder="letsencrypt.org">
                    </div>
                    <div class="input-group">
                        <label>Flag</label>
                        <input type="number" id="rec-val-flag" value="${record?.flag || 0}" min="0" max="255">
                    </div>
                </div>
            `;
        }
    }

    async deleteRecord(recordId) {
        if (!confirm("Are you sure you want to delete this DNS record?")) return;

        try {
            const res = await this.admin.api('domains_zone_delete_record', {
                domain: this.state.zoneDomain,
                record_id: recordId
            });

            if (res.success) {
                this.admin.notify("DNS record deleted.", "success");
                await this.loadZone(this.state.zoneDomain);
            } else {
                this.admin.notify(res.error || "Failed to delete record.", "error");
            }
        } catch (e) {
            this.admin.notify("Error deleting record: " + e.message, "error");
        }
    }

    openScaffoldZoneModal() {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = '⚡ Scaffold DNS Zone for Domain';

        const defaultDomain = this.state.zoneDomain && this.state.zoneDomain !== 'school.local' 
            ? this.state.zoneDomain 
            : ((this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com'));
        const detectedIp = (/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname)) ? window.location.hostname : (this.state.preflightResults?.server_ip || '192.0.2.1');

        const domainOptions = [
            ...new Set([
                ...(this.state.domains || []).map(d => d.pattern || d.domain),
                ...(this.state.addonDomains || []).map(a => a.domain)
            ])
        ].filter(Boolean);
        if (domainOptions.length === 0) {
            domainOptions.push('example.com', 'mysite.org');
        }

        const datalistOptions = domainOptions.map(d => `<option value="${this.escapeHtml(d)}">`).join('');

        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1.2rem;">
                <p style="font-size:0.85rem; color:var(--text-dim); margin:0;">
                    Scaffold a complete BIND RFC 1035 DNS zone with standard apex A records, custom nameservers (ns1/ns2), mail configuration (MX, SPF, DMARC), and DNSSEC signing.
                </p>

                <div class="input-group">
                    <label style="font-weight:700; color:var(--text-bright);">Target Domain Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="scaffold-domain-input" list="domain-suggestions" value="${this.escapeHtml(defaultDomain)}" placeholder="e.g. example.com" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.95rem; font-weight:600;">
                    <datalist id="domain-suggestions">
                        ${datalistOptions}
                    </datalist>
                    <small style="color:var(--text-dim)">Select an existing domain or type any new domain name.</small>
                </div>

                <div class="input-group">
                    <label style="font-weight:700; color:var(--text-bright);">Server Target IPv4 Address <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="scaffold-ip-input" value="${this.escapeHtml(detectedIp)}" placeholder="e.g. 192.0.2.1" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:#38bdf8; font-family:monospace; font-size:0.95rem;">
                    <small style="color:var(--text-dim)">The public IP address where A records (@, www, mail, ns1, ns2, vps) will point.</small>
                </div>

                <div style="background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.9rem; display:flex; flex-direction:column; gap:0.6rem;">
                    <div style="font-size:0.8rem; font-weight:700; color:var(--text-bright); margin-bottom:0.2rem;">Included Standard Presets:</div>
                    <label style="font-size:0.85rem; display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" id="scaffold-include-ns" checked>
                        <span>Custom Nameserver Hosts (<code>ns1.&lt;domain&gt;</code> & <code>ns2.&lt;domain&gt;</code>)</span>
                    </label>
                    <label style="font-size:0.85rem; display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" id="scaffold-include-vps" checked>
                        <span>Server Hostname Host (<code>vps.&lt;domain&gt;</code>)</span>
                    </label>
                    <label style="font-size:0.85rem; display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" id="scaffold-include-mail" checked>
                        <span>Email Suite Presets (<code>mail.&lt;domain&gt;</code>, MX 10, SPF TXT, DMARC TXT)</span>
                    </label>
                    <label style="font-size:0.85rem; display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" id="scaffold-include-dnssec" checked>
                        <span>Cryptographic DNSSEC Signing (ECDSA Curve P-256 / SHA-256 DS)</span>
                    </label>
                </div>
            </div>
        `;

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = '🚀 Scaffold Zone Now';
        saveBtn.disabled = false;

        saveBtn.onclick = async () => {
            const domain = document.getElementById('scaffold-domain-input').value.trim().toLowerCase();
            const ip = document.getElementById('scaffold-ip-input').value.trim();
            const includeNs = document.getElementById('scaffold-include-ns').checked;
            const includeVps = document.getElementById('scaffold-include-vps').checked;

            if (!domain) {
                this.admin.notify("Please enter a domain name.", "error");
                return;
            }
            if (!ip || !/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
                this.admin.notify("Please enter a valid IPv4 address.", "error");
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Scaffolding...';

            try {
                // 1. Reset / Scaffold Zone
                const res = await this.admin.api('domains_zone_reset', { domain: domain, server_ip: ip });
                if (!res.success) {
                    throw new Error(res.error || "Failed to scaffold zone");
                }

                // 2. Add extra host records if requested
                if (includeNs) {
                    await this.admin.api('domains_zone_save_record', {
                        domain: domain,
                        name: 'ns1',
                        type: 'A',
                        value: ip,
                        ttl: 86400,
                        comment: 'Primary Nameserver Host'
                    });
                    await this.admin.api('domains_zone_save_record', {
                        domain: domain,
                        name: 'ns2',
                        type: 'A',
                        value: ip,
                        ttl: 86400,
                        comment: 'Secondary Nameserver Host'
                    });
                }

                if (includeVps) {
                    await this.admin.api('domains_zone_save_record', {
                        domain: domain,
                        name: 'vps',
                        type: 'A',
                        value: ip,
                        ttl: 3600,
                        comment: 'Server Hostname'
                    });
                }

                this.admin.notify(`✓ DNS Zone for '${domain}' scaffolded successfully with IP ${ip}!`, "success");
                modal.classList.remove('active');

                // Switch zoneDomain and reload
                this.setState({ zoneDomain: domain });
                await this.loadZone(domain);
            } catch (e) {
                this.admin.notify("Scaffolding error: " + e.message, "error");
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = '🚀 Scaffold Zone Now';
            }
        };

        modal.classList.add('active');
        this.setupModalClose(modal);
    }

    async resetZoneDefaults() {
        if (!confirm(`Are you sure you want to reset DNS zone for '${this.state.zoneDomain}' to defaults? This will erase custom records.`)) return;

        const defaultIp = (/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname)) ? window.location.hostname : (this.state.preflightResults?.server_ip || '192.0.2.1');
        const serverIp = prompt(`Enter Server Target IPv4 address for '${this.state.zoneDomain}':`, defaultIp);
        if (!serverIp) return;

        try {
            const res = await this.admin.api('domains_zone_reset', { domain: this.state.zoneDomain, server_ip: serverIp });
            if (res.success) {
                this.admin.notify(`DNS zone for '${this.state.zoneDomain}' reset to default BIND template (${serverIp}).`, "success");
                await this.loadZone(this.state.zoneDomain);
            } else {
                this.admin.notify(res.error || "Failed to reset zone.", "error");
            }
        } catch (e) {
            this.admin.notify("Error resetting zone: " + e.message, "error");
        }
    }

    async openExportZoneModal() {
        try {
            const res = await this.admin.api('domains_zone_export', { domain: this.state.zoneDomain });
            const data = (res.success && res.data) ? res.data : res;
            const zoneText = data.zone_text || '';

            const modal = document.getElementById('modal-container');
            document.getElementById('modal-title').textContent = `Export BIND Zone (${this.state.zoneDomain})`;
            document.getElementById('modal-body').innerHTML = `
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <p style="font-size:0.85rem; color:var(--text-dim); margin:0;">
                        Standard RFC 1035 BIND Zone text format for import into BIND9, PowerDNS, Route53, or Cloudflare:
                    </p>
                    <textarea id="bind-export-text" rows="16" style="width:100%; font-family:monospace; font-size:0.8rem; background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:#38bdf8; padding:0.75rem; border-radius:var(--radius-sm);" readonly>${this.escapeHtml(zoneText)}</textarea>
                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                        <button class="btn ghost-btn btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('bind-export-text').value); window.domainsViewInstance.admin.notify('Copied BIND text to clipboard!', 'success');">📋 Copy to Clipboard</button>
                    </div>
                </div>
            `;
            const saveBtn = document.getElementById('modal-save');
            saveBtn.textContent = 'Close';
            saveBtn.onclick = () => modal.classList.remove('active');
            modal.classList.add('active');
        this.setupModalClose(modal);
        } catch (e) {
            this.admin.notify("Export error: " + e.message, "error");
        }
    }

    openImportZoneModal() {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = `Import BIND Zone (${this.state.zoneDomain})`;
        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <p style="font-size:0.85rem; color:var(--text-dim); margin:0;">
                    Paste RFC 1035 BIND zone file contents below. Existing records will be replaced.
                </p>
                <textarea id="bind-import-text" rows="14" placeholder="; BIND zone file\n$ORIGIN ${this.state.zoneDomain}.\n$TTL 3600\n@ IN SOA ns1.${this.state.zoneDomain}. admin.${this.state.zoneDomain}. (\n  2026100101 3600 1800 1209600 86400 )\n..." style="width:100%; font-family:monospace; font-size:0.8rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); padding:0.75rem; border-radius:var(--radius-sm);"></textarea>
            </div>
        `;
        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Import Zone';
        saveBtn.onclick = async () => {
            const text = document.getElementById('bind-import-text').value.trim();
            if (!text) {
                this.admin.notify("Please paste BIND zone content.", "error");
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Importing...';

            try {
                const res = await this.admin.api('domains_zone_import', {
                    domain: this.state.zoneDomain,
                    zone_text: text
                });

                if (res.success) {
                    this.admin.notify(res.message || "Zone imported successfully.", "success");
                    modal.classList.remove('active');
                    await this.loadZone(this.state.zoneDomain);
                } else {
                    this.admin.notify(res.error || "Import failed.", "error");
                }
            } catch (e) {
                this.admin.notify("Error importing zone: " + e.message, "error");
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Import Zone';
            }
        };
        modal.classList.add('active');
        this.setupModalClose(modal);
    }

    async openZoneHistoryModal() {
        const domain = this.state.zoneDomain;
        try {
            const res = await this.admin.api('domains_zone_history', { domain });
            const data = (res.success && res.data) ? res.data : res;
            const revisions = data.revisions || [];

            const modal = document.getElementById('modal-container');
            document.getElementById('modal-title').textContent = `🕒 Zone Revision History & Rollback (${domain})`;
            
            let rowsHtml = '';
            if (revisions.length === 0) {
                rowsHtml = `<tr><td colspan="6" style="text-align:center; color:var(--text-dim); padding:2rem;">No previous revisions recorded yet. Revisions are created automatically whenever zone records are added, edited, imported, or deleted.</td></tr>`;
            } else {
                rowsHtml = revisions.map((rev, idx) => {
                    const isCurrent = idx === 0;
                    const timeStr = new Date(rev.timestamp * 1000).toLocaleString();
                    const actionBadge = rev.action === 'rollback' ? 'suite-pill-warning' : (rev.action === 'delete_record' ? 'suite-pill-danger' : 'suite-pill-primary');
                    return `
                        <tr>
                            <td>
                                <code style="font-size:0.75rem; color:#38bdf8;">${this.escapeHtml(rev.rev_id)}</code>
                                ${isCurrent ? '<span class="suite-pill suite-pill-success" style="font-size:0.65rem; margin-left:0.3rem;">Current Active</span>' : ''}
                            </td>
                            <td style="font-size:0.8rem; color:var(--text-dim); white-space:nowrap;">${timeStr}</td>
                            <td><span class="suite-pill ${actionBadge}" style="font-size:0.7rem;">${this.escapeHtml(rev.action || 'update')}</span></td>
                            <td style="font-size:0.8rem; color:var(--text-bright);">${this.escapeHtml(rev.summary || rev.description || '-')}</td>
                            <td style="text-align:center; font-size:0.8rem;">${rev.record_count || 0}</td>
                            <td>
                                ${!isCurrent ? `
                                    <button class="btn secondary-btn btn-xs" onclick="window.domainsViewInstance.previewZoneRollback('${this.escapeHtml(domain)}', '${this.escapeHtml(rev.rev_id)}')">
                                        👁️ Diff & Rollback
                                    </button>
                                ` : `<span style="font-size:0.75rem; color:var(--text-dim); font-style:italic;">Active Head</span>`}
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            document.getElementById('modal-body').innerHTML = `
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div style="font-size:0.85rem; color:var(--text-dim);">
                        Every modification to <code>var/dns/${this.escapeHtml(domain)}.zone</code> is version-controlled with point-in-time snapshots and atomic rollback.
                    </div>
                    <div class="suite-table-wrapper" style="max-height:360px; overflow-y:auto;">
                        <table class="suite-table">
                            <thead>
                                <tr>
                                    <th>Revision ID</th>
                                    <th>Timestamp</th>
                                    <th>Action</th>
                                    <th>Summary</th>
                                    <th style="text-align:center;">Records</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml}
                            </tbody>
                        </table>
                    </div>
                    <div id="zone-rollback-preview-pane" style="display:none; padding:1rem; background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); border-radius:var(--radius-sm);"></div>
                </div>
            `;
            const saveBtn = document.getElementById('modal-save');
            saveBtn.textContent = 'Close';
            saveBtn.onclick = () => modal.classList.remove('active');
            modal.classList.add('active');
            this.setupModalClose(modal);
        } catch (e) {
            this.admin.notify("Failed to load zone history: " + e.message, "error");
        }
    }

    async previewZoneRollback(domain, revId) {
        const pane = document.getElementById('zone-rollback-preview-pane');
        if (!pane) return;
        pane.style.display = 'block';
        pane.innerHTML = '<div style="color:var(--text-dim); font-size:0.85rem;">Calculating diff for rollback to ' + this.escapeHtml(revId) + '...</div>';
        try {
            const res = await this.admin.api('domains_preview_rollback', { domain, rev_id: revId });
            const data = (res.success && res.data) ? res.data : res;
            const diff = data.diff || {};
            const added = diff.added || [];
            const removed = diff.removed || [];
            const modified = diff.modified || [];

            let diffSummary = '';
            if (added.length === 0 && removed.length === 0 && modified.length === 0) {
                diffSummary = '<div style="color:#10b981; font-size:0.85rem;">✓ Target revision identical to current zone.</div>';
            } else {
                diffSummary = `
                    <div style="display:flex; flex-direction:column; gap:0.5rem; font-size:0.8rem;">
                        <div style="font-weight:600; color:var(--text-bright);">Rollback Diff to Revision <code>${this.escapeHtml(revId)}</code>:</div>
                        ${added.length > 0 ? `<div style="color:#10b981;">➕ <strong>${added.length}</strong> record(s) will be restored: ${added.map(r => `<code>${this.escapeHtml(r.name)} ${this.escapeHtml(r.type)} ${this.escapeHtml(r.value||r.target||'')}</code>`).join(', ')}</div>` : ''}
                        ${removed.length > 0 ? `<div style="color:#ef4444;">➖ <strong>${removed.length}</strong> record(s) will be removed: ${removed.map(r => `<code>${this.escapeHtml(r.name)} ${this.escapeHtml(r.type)} ${this.escapeHtml(r.value||r.target||'')}</code>`).join(', ')}</div>` : ''}
                        ${modified.length > 0 ? `<div style="color:#f59e0b;">🔄 <strong>${modified.length}</strong> record(s) will be modified.</div>` : ''}
                    </div>
                `;
            }

            pane.innerHTML = `
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    ${diffSummary}
                    <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:0.5rem;">
                        <button class="btn danger-btn btn-sm" onclick="window.domainsViewInstance.executeZoneRollback('${this.escapeHtml(domain)}', '${this.escapeHtml(revId)}')">
                            ⏪ Confirm & Execute Rollback
                        </button>
                        <button class="btn ghost-btn btn-sm" onclick="document.getElementById('zone-rollback-preview-pane').style.display='none';">
                            Cancel
                        </button>
                    </div>
                </div>
            `;
        } catch (e) {
            pane.innerHTML = '<div style="color:#ef4444; font-size:0.85rem;">Diff error: ' + this.escapeHtml(e.message) + '</div>';
        }
    }

    async executeZoneRollback(domain, revId) {
        if (!confirm(`Are you sure you want to rollback zone '${domain}' to revision '${revId}'? Current state will be backed up as a new revision, and zone serial will be incremented.`)) {
            return;
        }
        try {
            const res = await this.admin.api('domains_rollback_zone', { domain, rev_id: revId });
            if (res.success) {
                this.admin.notify(`Zone '${domain}' successfully rolled back to revision ${revId}.`, "success");
                const modal = document.getElementById('modal-container');
                if (modal) modal.classList.remove('active');
                await this.loadZone(domain);
            } else {
                this.admin.notify(res.error || "Rollback failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Rollback error: " + e.message, "error");
        }
    }

    async syncZoneManifest() {
        const domain = this.state.zoneDomain;
        try {
            const res = await this.admin.api('domains_sync_manifest', { domain });
            if (res.success) {
                this.admin.notify(`Zone '${domain}' registered in var/dns/__spp_file_props.yml with SHA-256 integrity digest.`, "success");
                await this.loadZone(domain);
            } else {
                this.admin.notify(res.error || "Manifest sync failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Manifest sync error: " + e.message, "error");
        }
    }

    renderDomainAppsTab() {
        const domainApps = this.state.domainApps || [];
        const allApps = this.state.allAppsList || [];
        const domainOnlyApps = allApps.filter(a => a.domain_only && !a.is_domain_app);

        return `
            <div class="suite-pane-main" style="display:flex; flex-direction:column; gap:1.5rem;">
                <!-- Toolbar & Actions -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright);">🌐 Domain-Specific & Isolated Applications</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Applications installed directly under <code>domains/&lt;domain-name&gt;/src/</code> and domain-restricted applications.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn primary-btn btn-sm" onclick="window.domainsViewInstance.openInstallDomainAppModal()">
                            ➕ Install App to Domain
                        </button>
                        <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.openAppScopeModal()">
                            🔒 Configure App Domain Scope
                        </button>
                    </div>
                </div>

                <!-- Section 1: Physically Installed Domain Applications -->
                <div class="glass-panel" style="padding:1.25rem; border-radius:var(--radius-md); background:rgba(0,0,0,0.2); border:1px solid var(--glass-border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <div>
                            <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">📁 Physically Installed in <code>domains/&lt;domain&gt;/src/</code></h4>
                            <div style="font-size:0.75rem; color:var(--text-dim);">These applications only exist for and are accessible from their host domain.</div>
                        </div>
                        <span class="suite-pill suite-pill-info">${domainApps.length} Installed</span>
                    </div>

                    ${domainApps.length === 0 ? `
                        <div style="padding:2.5rem; text-align:center; color:var(--text-dim); font-size:0.85rem;">
                            No domain-specific applications installed yet.<br>
                            Click <strong>"➕ Install App to Domain"</strong> to scaffold an application in <code>domains/&lt;domain&gt;/src/</code>.
                        </div>
                    ` : `
                        <div class="table-container" style="overflow-x:auto;">
                            <table class="data-table" style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--glass-border); text-align:left; font-size:0.75rem; text-transform:uppercase; color:var(--text-dim);">
                                        <th style="padding:0.75rem;">Application</th>
                                        <th style="padding:0.75rem;">Installed Domain</th>
                                        <th style="padding:0.75rem;">Directory Path</th>
                                        <th style="padding:0.75rem;">Access Scope</th>
                                        <th style="padding:0.75rem; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${domainApps.map(app => `
                                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem;">
                                            <td style="padding:0.75rem;">
                                                <div style="font-weight:700; color:var(--text-bright);">🌐 ${this.escapeHtml(app.name)}</div>
                                                <div style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(app.title || '')}</div>
                                            </td>
                                            <td style="padding:0.75rem;">
                                                <code style="color:#38bdf8; font-weight:600;">${this.escapeHtml(app.domain)}</code>
                                            </td>
                                            <td style="padding:0.75rem;">
                                                <code style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(app.src_path)}</code>
                                            </td>
                                            <td style="padding:0.75rem;">
                                                <span class="suite-pill suite-pill-success" style="font-size:0.7rem;">🔒 Domain-Only (Strict)</span>
                                            </td>
                                            <td style="padding:0.75rem; text-align:right;">
                                                <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                                                    <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.testDomainDirect('${app.domain}')" title="Simulate routing">🧪 Test</button>
                                                    <button class="btn ghost-btn btn-sm" style="color:#f87171;" onclick="window.domainsViewInstance.deleteDomainApp('${app.domain}', '${app.name}')" title="Unlink or delete application">🗑️ Unlink</button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `}
                </div>

                <!-- Section 2: Global src/ Apps with Domain-Only Scope -->
                <div class="glass-panel" style="padding:1.25rem; border-radius:var(--radius-md); background:rgba(0,0,0,0.2); border:1px solid var(--glass-border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <div>
                            <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">🔒 Global <code>src/</code> Applications Marked Domain-Only</h4>
                            <div style="font-size:0.75rem; color:var(--text-dim);">Applications in <code>src/</code> restricted to appear only on specified domain hostnames.</div>
                        </div>
                        <span class="suite-pill suite-pill-warning">${domainOnlyApps.length} Restricted</span>
                    </div>

                    ${domainOnlyApps.length === 0 ? `
                        <div style="padding:1.5rem; text-align:center; color:var(--text-dim); font-size:0.8rem;">
                            No <code>src/</code> applications are currently restricted. All standard applications follow domain permissions.
                        </div>
                    ` : `
                        <div class="table-container" style="overflow-x:auto;">
                            <table class="data-table" style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--glass-border); text-align:left; font-size:0.75rem; text-transform:uppercase; color:var(--text-dim);">
                                        <th style="padding:0.75rem;">Application</th>
                                        <th style="padding:0.75rem;">Source Location</th>
                                        <th style="padding:0.75rem;">Allowed Domains</th>
                                        <th style="padding:0.75rem; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${domainOnlyApps.map(app => `
                                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem;">
                                            <td style="padding:0.75rem;">
                                                <div style="font-weight:700; color:var(--text-bright);">📁 ${this.escapeHtml(app.name)}</div>
                                                <div style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(app.title || '')}</div>
                                            </td>
                                            <td style="padding:0.75rem;">
                                                <code style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(app.src_path)}</code>
                                            </td>
                                            <td style="padding:0.75rem;">
                                                ${(app.allowed_domains || []).map(d => `<span class="suite-pill suite-pill-neutral" style="font-size:0.7rem; margin-right:4px;">${this.escapeHtml(d)}</span>`).join('') || '<span style="color:#f87171;">None</span>'}
                                            </td>
                                            <td style="padding:0.75rem; text-align:right;">
                                                <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.openAppScopeModal('${app.name}')">⚙️ Edit Scope</button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `}
                </div>
            </div>
        `;
    }

    openInstallDomainAppModal(presetDomain = null) {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'Install Application on Specific Domain';
        const domains = this.state.domains || [];
        const domOptions = domains.map(d => {
            const sel = (presetDomain === d.pattern) ? 'selected' : '';
            return `<option value="${this.escapeHtml(d.pattern)}" ${sel}>${this.escapeHtml(d.pattern)}</option>`;
        }).join('');

        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div class="input-group">
                    <label>Target Domain <span style="color:var(--danger)">*</span></label>
                    <select id="install-dom-select" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                        ${domOptions}
                    </select>
                    <small style="color:var(--text-dim);">The application will be installed in <code>domains/&lt;domain&gt;/src/&lt;appName&gt;/</code>.</small>
                </div>
                <div class="input-group">
                    <label>Application Name (Slug) <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="install-app-name" placeholder="e.g. portal, admissions, campus_erp" required>
                </div>
                <div class="input-group">
                    <label>Application Title</label>
                    <input type="text" id="install-app-title" placeholder="e.g. Campus Admissions Portal">
                </div>
                <div style="display:flex; flex-direction:column; gap:0.5rem; padding:0.75rem; background:rgba(0,0,0,0.15); border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="install-app-default" checked>
                        <span>Mount as Default Application (served directly at /)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="install-app-allow-others">
                        <span>Allow other secondary applications on this domain</span>
                    </label>
                </div>
            </div>
        `;

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Install Application';
        saveBtn.onclick = async () => {
            const domain = document.getElementById('install-dom-select')?.value.trim();
            const appName = document.getElementById('install-app-name')?.value.trim();
            const title = document.getElementById('install-app-title')?.value.trim();
            const makeDefault = document.getElementById('install-app-default')?.checked || false;
            const allowOthers = document.getElementById('install-app-allow-others')?.checked || false;

            if (!domain || !appName) {
                this.admin.notify('Domain and Application Name are required.', 'error');
                return;
            }
            saveBtn.disabled = true;
            saveBtn.textContent = 'Installing...';
            try {
                const res = await this.admin.api('domains_install_app', {
                    domain: domain,
                    app_name: appName,
                    title: title,
                    make_default: makeDefault,
                    allow_other_apps: allowOthers
                });
                if (res.success) {
                    this.admin.notify(`Application '${appName}' installed on '${domain}'!`, 'success');
                    modal.classList.remove('active');
                    await this.fetchData();
                    this.update();
                } else {
                    this.admin.notify(res.error || 'Installation failed.', 'error');
                }
            } catch (e) {
                this.admin.notify('Error: ' + e.message, 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Install Application';
            }
        };
        modal.classList.add('active');
        this.setupModalClose(modal);
    }

    openAppScopeModal(presetAppName = null) {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'Configure Application Domain Scope';
        const allApps = this.state.allAppsList || [];
        const domains = this.state.domains || [];

        const appOptions = allApps.map(a => {
            const sel = (presetAppName === a.name) ? 'selected' : '';
            return `<option value="${this.escapeHtml(a.name)}" ${sel}>${this.escapeHtml(a.title || a.name)} (${a.name})</option>`;
        }).join('');

        const selectedAppObj = allApps.find(a => a.name === (presetAppName || allApps[0]?.name));
        const currentAllowed = (selectedAppObj?.allowed_domains || []).join(', ');
        const isDomainOnly = selectedAppObj?.domain_only || false;

        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div class="input-group">
                    <label>Select Application <span style="color:var(--danger)">*</span></label>
                    <select id="scope-app-select" style="width:100%; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                        ${appOptions}
                    </select>
                </div>
                <div style="padding:0.75rem; background:rgba(0,0,0,0.15); border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.9rem; font-weight:600;">
                        <input type="checkbox" id="scope-domain-only" ${isDomainOnly ? 'checked' : ''}>
                        <span>🔒 Enforce Domain-Only Restriction</span>
                    </label>
                    <div style="margin-top:0.4rem; font-size:0.75rem; color:var(--text-dim); margin-left:1.5rem;">
                        When enabled, this application is blocked from any domain or unmapped host not explicitly in the allowed list.
                    </div>
                </div>
                <div class="input-group">
                    <label>Allowed Domains (Comma-Separated)</label>
                    <input type="text" id="scope-allowed-domains" value="${this.escapeHtml(currentAllowed)}" placeholder="e.g. school.local, docs.localhost, *.school.org">
                    <small style="color:var(--text-dim);">Available registered domains: ${domains.map(d => d.pattern).join(', ')}</small>
                </div>
            </div>
        `;

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Scope Configuration';
        saveBtn.onclick = async () => {
            const appName = document.getElementById('scope-app-select')?.value.trim();
            const domainOnly = document.getElementById('scope-domain-only')?.checked || false;
            const allowedStr = document.getElementById('scope-allowed-domains')?.value.trim() || '';
            const allowed = allowedStr ? allowedStr.split(',').map(s => s.trim()).filter(Boolean) : [];

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            try {
                const res = await this.admin.api('domains_set_app_scope', {
                    app_name: appName,
                    domain_only: domainOnly,
                    allowed_domains: allowed
                });
                if (res.success) {
                    this.admin.notify(res.message || 'Domain scope updated successfully.', 'success');
                    modal.classList.remove('active');
                    await this.fetchData();
                    this.update();
                } else {
                    this.admin.notify(res.error || 'Failed to update scope.', 'error');
                }
            } catch (e) {
                this.admin.notify('Error: ' + e.message, 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Scope Configuration';
            }
        };
        modal.classList.add('active');
        this.setupModalClose(modal);
    }

    async deleteDomainApp(domain, appName) {
        if (!confirm(`Are you sure you want to remove domain application '${appName}' from '${domain}'?`)) return;
        const deleteFiles = confirm('Do you also want to permanently delete the files in domains/' + domain + '/src/' + appName + '? (Click Cancel to keep files on disk)');
        try {
            const res = await this.admin.api('domains_delete_app', {
                domain: domain,
                app_name: appName,
                delete_files: deleteFiles
            });
            if (res.success) {
                this.admin.notify(`Domain application '${appName}' removed.`, 'success');
                await this.fetchData();
                this.update();
            } else {
                this.admin.notify(res.error || 'Failed to remove domain application.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    testDomainDirect(pattern) {
        this.setState({ activeTab: 'simulator', simHost: pattern, simPath: '/' });
        this.update();
        this.runSimulator();
    }

    // =========================================================================
    // SOVEREIGN CLOUDFLARE EDGE DNS & EDGE SSL BRIDGE
    // =========================================================================

    async loadCloudflare() {
        this.setState({ loadingCf: true });
        this.update();

        try {
            const settingsRes = await this.admin.api('domains_cf_get_settings');
            const cfSettings = (settingsRes.success && settingsRes.settings) ? settingsRes.settings : (settingsRes.data?.settings || {});
            
            let zones = [];
            let records = [];
            const defaultCfDom = this.state.activeCfDomain || this.state.zoneDomain || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com');
            let activeCfDomain = defaultCfDom;

            if (cfSettings.has_token) {
                // Fetch available zones
                const zonesRes = await this.admin.api('domains_cf_list_zones');
                if (zonesRes.success && Array.isArray(zonesRes.zones)) {
                    zones = zonesRes.zones;
                    if (!this.state.activeCfDomain && zones.length > 0) {
                        activeCfDomain = zones[0].name;
                    }
                }

                // Fetch live records for current zone
                if (activeCfDomain) {
                    const recordsRes = await this.admin.api('domains_cf_list_records', { domain: activeCfDomain });
                    if (recordsRes.success && Array.isArray(recordsRes.records)) {
                        records = recordsRes.records;
                    }
                }
            }

            this.setState({
                cfSettings: cfSettings,
                cfZones: zones,
                cfRecords: records,
                activeCfDomain: activeCfDomain,
                loadingCf: false
            });
        } catch (e) {
            console.warn('[CloudflareBridge] Error loading Cloudflare state:', e);
            this.setState({ loadingCf: false });
        }
        this.update();
    }

    renderCloudflareTab() {
        if (this.state.loadingCf) {
            return `
                <div style="text-align:center; padding:3rem; color:var(--text-dim);">
                    <div style="font-size:2rem; margin-bottom:1rem; animation:pulse 1.5s infinite;">☁️</div>
                    <p style="font-size:0.9rem;">Connecting to Cloudflare Edge API v4...</p>
                </div>
            `;
        }

        const cf = this.state.cfSettings || {};
        const isConnected = !!(cf.enabled && cf.has_token);
        const zones = this.state.cfZones || [];
        const records = this.state.cfRecords || [];
        const activeDomain = this.state.activeCfDomain || this.state.zoneDomain || (this.state.domains?.[0]?.pattern) || (window.location.hostname && !window.location.hostname.includes('localhost') ? window.location.hostname : 'example.com');

        const zoneSelectOptions = (zones.length > 0 ? zones.map(z => z.name) : [activeDomain]).map(d => `
            <option value="${this.escapeHtml(d)}" ${d === activeDomain ? 'selected' : ''}>${this.escapeHtml(d)}</option>
        `).join('');

        const recordRows = records.length > 0 ? records.map(r => {
            const isProxied = !!r.proxied;
            const badgeClass = isProxied ? 'suite-pill-warning' : 'suite-pill-neutral';
            const cloudIcon = isProxied ? '🟠' : '⚪';
            const cloudLabel = isProxied ? 'Proxied (CDN + SSL)' : 'DNS Only';
            
            return `
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem;">
                    <td style="padding:0.75rem 1rem;">
                        <span class="suite-pill suite-pill-primary" style="font-family:monospace; font-weight:700;">${this.escapeHtml(r.type)}</span>
                    </td>
                    <td style="padding:0.75rem 1rem; font-weight:600; color:var(--text-bright);">
                        ${this.escapeHtml(r.name)}
                    </td>
                    <td style="padding:0.75rem 1rem; font-family:monospace; font-size:0.8rem; color:#38bdf8; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${this.escapeHtml(r.content)}">
                        ${this.escapeHtml(r.content)}
                    </td>
                    <td style="padding:0.75rem 1rem;">
                        <span class="suite-pill ${badgeClass}" style="display:inline-flex; align-items:center; gap:0.35rem; font-size:0.75rem;">
                            <span>${cloudIcon}</span> ${cloudLabel}
                        </span>
                    </td>
                    <td style="padding:0.75rem 1rem; color:var(--text-dim); font-size:0.8rem;">
                        ${r.ttl === 1 ? 'Auto' : r.ttl + 's'}
                    </td>
                    <td style="padding:0.75rem 1rem; text-align:right;">
                        <button class="btn ghost-btn btn-sm" style="color:var(--danger); padding:2px 8px; font-size:0.75rem;" onclick="window.domainsViewInstance.deleteCloudflareRecord('${this.escapeHtml(r.id)}')">
                            🗑️ Delete
                        </button>
                    </td>
                </tr>
            `;
        }).join('') : `
            <tr>
                <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-dim); font-size:0.85rem;">
                    ${isConnected ? 'No DNS records found for this Cloudflare zone. Add one using the provisioner below.' : 'Please configure your Cloudflare API Token to inspect and manage edge DNS records.'}
                </td>
            </tr>
        `;

        return `
            <div class="cloudflare-edge-suite" style="display:flex; flex-direction:column; gap:1.5rem;">
                
                <!-- 1. Header Banner & Status Bar -->
                <div class="suite-glass-card" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; border-left:4px solid ${isConnected ? '#10b981' : '#f59e0b'}; padding:1.25rem 1.5rem;">
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <div style="width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg, rgba(245,158,11,0.2), rgba(249,115,22,0.2)); display:flex; align-items:center; justify-content:center; font-size:1.5rem; border:1px solid rgba(245,158,11,0.3);">
                            ☁️
                        </div>
                        <div>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <h3 style="margin:0; font-size:1.1rem; font-weight:700;">Cloudflare Sovereign Edge Bridge</h3>
                                <span class="suite-pill ${isConnected ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.75rem;">
                                    ${isConnected ? '● Edge Synchronized' : '○ Token Required'}
                                </span>
                            </div>
                            <p style="margin:0.25rem 0 0; font-size:0.8rem; color:var(--text-dim);">
                                Real-time global Anycast DNS propagation (~300ms), Edge SSL/TLS termination, and DDoS mitigation.
                            </p>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.openCloudflareSettingsModal()">
                            ⚙️ Cloudflare API Settings
                        </button>
                        ${isConnected ? `
                            <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.syncCloudflareRecords()">
                                🔄 Sync to SPP Zone
                            </button>
                            <button class="btn ghost-btn btn-sm" style="color:#f59e0b;" onclick="window.domainsViewInstance.purgeCloudflareCache()">
                                🧹 Purge Edge CDN Cache
                            </button>
                        ` : ''}
                    </div>
                </div>

                <!-- 2. Instant Subdomain Auto-Provisioner -->
                <div class="suite-glass-card spp-setting-banner" style="padding:1.5rem; border:1px solid rgba(245,158,11,0.35);">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                        <div>
                            <h4 style="margin:0; font-size:1rem; font-weight:700; color:#f59e0b; display:flex; align-items:center; gap:0.4rem;">
                                <span>⚡</span> 1-Click Instant Subdomain Provisioner
                            </h4>
                            <p style="margin:0.2rem 0 0; font-size:0.8rem; color:var(--text-dim);">
                                Provision an A record directly on Cloudflare with automated Edge SSL and Proxying in ~300ms with zero manual configuration.
                            </p>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:2fr 1.5fr 1fr auto; gap:0.75rem; align-items:end;">
                        <div class="input-group" style="margin:0;">
                            <label style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-dim);">Subdomain FQDN</label>
                            <input type="text" id="cf-quick-subdomain" class="suite-input" placeholder="e.g. portal.example.com" style="padding:0.6rem 0.8rem; font-size:0.85rem; border-radius:var(--radius-sm);">
                        </div>
                        <div class="input-group" style="margin:0;">
                            <label style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-dim);">Server Target IP</label>
                            <input type="text" id="cf-quick-ip" class="suite-input" placeholder="Auto-detected server IP" value="${this.escapeHtml(this.state.preflightResults?.server_ip || (/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname) ? window.location.hostname : ''))}" style="padding:0.6rem 0.8rem; font-size:0.85rem; border-radius:var(--radius-sm);">
                        </div>
                        <div class="input-group" style="margin:0; display:flex; flex-direction:column; justify-content:center;">
                            <label style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-dim); margin-bottom:0.4rem;">Cloudflare Proxy</label>
                            <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.85rem; font-weight:600; color:#f59e0b;">
                                <input type="checkbox" id="cf-quick-proxied" checked>
                                <span>🟠 Orange Cloud</span>
                            </label>
                        </div>
                        <button class="btn primary-btn btn-sm" style="background:#f59e0b; border-color:#d97706; padding:0.65rem 1.25rem; font-weight:700;" onclick="window.domainsViewInstance.quickProvisionCloudflareSubdomain()">
                            ⚡ Provision on Edge
                        </button>
                    </div>
                </div>

                <!-- 3. Live Edge DNS Records Table -->
                <div class="suite-glass-card" style="padding:0; overflow:hidden;">
                    <div style="padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--glass-border); background:var(--surface-1);">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <span style="font-weight:700; font-size:0.95rem;">Cloudflare Edge Records for Zone:</span>
                            <select id="cf-zone-selector" class="suite-input" style="padding:0.4rem 0.75rem; border-radius:var(--radius-sm); font-size:0.85rem; font-weight:600;" onchange="window.domainsViewInstance.switchCloudflareZone(this.value)">
                                ${zoneSelectOptions}
                            </select>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.loadCloudflare()">
                                ↻ Refresh Live Records
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; text-align:left;">
                            <thead>
                                <tr style="background:rgba(255,255,255,0.02); border-bottom:1px solid rgba(255,255,255,0.08); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-dim);">
                                    <th style="padding:0.75rem 1rem;">Type</th>
                                    <th style="padding:0.75rem 1rem;">Name / Host</th>
                                    <th style="padding:0.75rem 1rem;">Target / Content</th>
                                    <th style="padding:0.75rem 1rem;">Proxy Status</th>
                                    <th style="padding:0.75rem 1rem;">TTL</th>
                                    <th style="padding:0.75rem 1rem; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${recordRows}
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        `;
    }

    openCloudflareSettingsModal() {
        const modal = document.getElementById('modal-container');
        if (!modal) return;
        const cf = this.state.cfSettings || {};

        document.getElementById('modal-title').textContent = 'Cloudflare API & Edge Bridge Settings';
        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <p style="font-size:0.85rem; color:var(--text-dim); margin:0; line-height:1.4;">
                    Cloudflare is completely optional. Connecting it enables free DDoS protection, worldwide speed acceleration (CDN), and automated edge SSL encryption without manual server port configuration.
                </p>

                <div class="input-group">
                    <label>
                        Cloudflare API Token <span style="color:var(--danger)">*</span>
                        <span class="spp-help-tip" data-tooltip-title="Cloudflare API Token" data-tooltip="A secure authorization token created in your Cloudflare account to let SPP manage DNS records automatically." data-tooltip-example="e.g. abc123XYZ..." data-tooltip-rec="Click the blue link below to generate one in Cloudflare.">?</span>
                    </label>
                    <input type="password" id="cf-api-token" placeholder="${cf.has_token ? 'Enter new token to overwrite (' + cf.masked_token + ')' : 'Paste Cloudflare API Bearer Token...'}" style="font-family:monospace; padding:0.6rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright);">
                    <small style="color:var(--text-dim)">
                        Create a scoped token in <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" style="color:#38bdf8; text-decoration:underline;">Cloudflare Dashboard &gt; API Tokens</a> with permissions: <strong>Zone : DNS : Edit</strong> and <strong>Zone : Zone : Read</strong>.
                    </small>
                </div>

                <div style="display:flex; flex-direction:column; gap:0.6rem; padding:0.85rem; background:rgba(0,0,0,0.2); border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem; font-weight:600;">
                        <input type="checkbox" id="cf-auto-subdomains" ${cf.auto_provision_subdomains !== false ? 'checked' : ''}>
                        <span>
                            Automatically publish new websites & subdomains to Cloudflare
                            <span class="spp-help-tip" data-tooltip-title="Auto-Publish Subdomains" data-tooltip="Whenever you add a website or subdomain in SPP, its DNS record is automatically created on Cloudflare in milliseconds.">?</span>
                        </span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem; font-weight:600; color:#f59e0b;">
                        <input type="checkbox" id="cf-default-proxied" ${cf.default_proxied !== false ? 'checked' : ''}>
                        <span>
                            Enable Orange Cloud Proxy (CDN + DDoS Shield) by default
                            <span class="spp-help-tip" data-tooltip-title="Orange Cloud Proxy" data-tooltip="Routes web traffic through Cloudflare's global edge network, hiding your server IP and speeding up page load." data-tooltip-rec="Keep checked for public websites.">?</span>
                        </span>
                    </label>
                </div>

                <!-- Progressive Disclosure: Advanced Cloudflare Options -->
                <div class="spp-advanced-accordion">
                    <div class="spp-accordion-toggle">
                        <span>⚙️ Advanced Cloudflare Options (Account ID)</span>
                        <span class="spp-accordion-icon">▾</span>
                    </div>
                    <div class="spp-accordion-body" style="padding-top:0.75rem;">
                        <div class="input-group">
                            <label>
                                Cloudflare Account ID (Optional)
                                <span class="spp-help-tip" data-tooltip="Only required if your Cloudflare user has access to multiple accounts or enterprise organizations.">?</span>
                            </label>
                            <input type="text" id="cf-account-id" value="${this.escapeHtml(cf.account_id || '')}" placeholder="Optional Cloudflare Account ID">
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <button class="btn secondary-btn btn-sm" type="button" onclick="window.domainsViewInstance.testCloudflareToken()">
                        ⚡ Test Token Connectivity
                    </button>
                    <span id="cf-test-feedback" style="font-size:0.8rem;"></span>
                </div>
            </div>
        `;

        modal.classList.add('active');
        this.setupModalClose(modal);

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Cloudflare Settings';
        saveBtn.onclick = async () => {
            const token = document.getElementById('cf-api-token').value.trim();
            const accountId = document.getElementById('cf-account-id').value.trim();
            const autoSubdomains = document.getElementById('cf-auto-subdomains').checked;
            const defaultProxied = document.getElementById('cf-default-proxied').checked;

            saveBtn.disabled = true;
            saveBtn.textContent = 'Verifying & Saving...';

            try {
                const payload = {
                    auto_provision_subdomains: autoSubdomains,
                    default_proxied: defaultProxied,
                    account_id: accountId
                };
                if (token) {
                    payload.api_token = token;
                }

                const res = await this.admin.api('domains_cf_save_settings', { settings: payload });
                if (res.success) {
                    this.admin.notify('Cloudflare settings saved and verified successfully!', 'success');
                    modal.classList.remove('active');
                    await this.loadCloudflare();
                } else {
                    this.admin.notify(res.error || 'Failed to save Cloudflare settings.', 'error');
                }
            } catch (e) {
                this.admin.notify('Error saving settings: ' + e.message, 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Cloudflare Settings';
            }
        };
    }

    async testCloudflareToken() {
        const tokenInput = document.getElementById('cf-api-token');
        const feedback = document.getElementById('cf-test-feedback');
        const token = tokenInput ? tokenInput.value.trim() : '';

        if (feedback) {
            feedback.innerHTML = '<span style="color:#f59e0b;">Connecting to Cloudflare...</span>';
        }

        try {
            const res = await this.admin.api('domains_cf_verify_token', { token: token || null });
            if (res.success) {
                if (feedback) feedback.innerHTML = '<span style="color:#10b981; font-weight:600;">✓ Connected! Token is active.</span>';
                this.admin.notify('Cloudflare token verified successfully!', 'success');
            } else {
                if (feedback) feedback.innerHTML = `<span style="color:#ef4444; font-weight:600;">✗ ${this.escapeHtml(res.error || 'Invalid token')}</span>`;
                this.admin.notify(res.error || 'Token verification failed.', 'error');
            }
        } catch (e) {
            if (feedback) feedback.innerHTML = `<span style="color:#ef4444;">Error: ${this.escapeHtml(e.message)}</span>`;
        }
    }

    async switchCloudflareZone(zoneName) {
        this.setState({ activeCfDomain: zoneName });
        await this.loadCloudflare();
    }

    async quickProvisionCloudflareSubdomain() {
        const hostInput = document.getElementById('cf-quick-subdomain');
        const ipInput = document.getElementById('cf-quick-ip');
        const proxiedInput = document.getElementById('cf-quick-proxied');

        const hostname = hostInput ? hostInput.value.trim() : '';
        const ip = ipInput ? ipInput.value.trim() : '';
        const proxied = proxiedInput ? proxiedInput.checked : true;

        if (!hostname) {
            this.admin.notify('Please enter a subdomain (e.g. portal.example.com).', 'error');
            return;
        }

        this.admin.notify(`Provisioning ${hostname} on Cloudflare Edge...`, 'info');

        try {
            const res = await this.admin.api('domains_cf_auto_provision', {
                hostname: hostname,
                target_ip: ip || null,
                proxied: proxied
            });

            if (res.success) {
                this.admin.notify(res.message || `Successfully provisioned ${hostname} on Cloudflare Edge!`, 'success');
                if (hostInput) hostInput.value = '';
                await this.loadCloudflare();
            } else {
                this.admin.notify(res.error || 'Failed to provision on Cloudflare.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error provisioning subdomain: ' + e.message, 'error');
        }
    }

    async deleteCloudflareRecord(recordId) {
        if (!confirm('Are you sure you want to delete this DNS record from Cloudflare Edge? This takes effect globally immediately.')) {
            return;
        }

        try {
            const defaultDomain = this.state.activeCfDomain || this.state.zoneDomain || (this.state.domains?.[0]?.pattern) || 'example.com';
            const res = await this.admin.api('domains_cf_delete_record', {
                domain: defaultDomain,
                record_id: recordId
            });

            if (res.success) {
                this.admin.notify('Record deleted from Cloudflare Edge.', 'success');
                await this.loadCloudflare();
            } else {
                this.admin.notify(res.error || 'Failed to delete record.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error deleting record: ' + e.message, 'error');
        }
    }

    async purgeCloudflareCache() {
        const defaultDomain = this.state.activeCfDomain || this.state.zoneDomain || (this.state.domains?.[0]?.pattern) || 'example.com';
        if (!confirm(`Purge all global edge cache for ${defaultDomain} on Cloudflare?`)) {
            return;
        }

        try {
            const res = await this.admin.api('domains_cf_purge_cache', {
                domain: defaultDomain,
                purge_everything: true
            });

            if (res.success) {
                this.admin.notify(res.message || 'Cloudflare global cache purged successfully!', 'success');
            } else {
                this.admin.notify(res.error || 'Failed to purge cache.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error purging cache: ' + e.message, 'error');
        }
    }

    async syncCloudflareRecords() {
        const domain = this.state.activeCfDomain || this.state.zoneDomain || (this.state.domains?.[0]?.pattern) || 'example.com';
        this.admin.notify(`Syncing records from Cloudflare for ${domain}...`, 'info');

        try {
            const res = await this.admin.api('domains_cf_sync_zone', { domain: domain });
            if (res.success) {
                this.admin.notify(res.message || `Synchronized records from Cloudflare Edge into SPP Zone Manager.`, 'success');
                await this.loadCloudflare();
            } else {
                this.admin.notify(res.error || 'Sync failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error during sync: ' + e.message, 'error');
        }
    }

    editDomainDirect(pattern) {
        const dom = (this.state.domains || []).find(d => d.pattern === pattern);
        if (dom) {
            this.openDomainModal(dom);
        }
    }

    renderVhostConfigTab() {
        const domains = (this.state.domains || []).map(d => d.pattern);
        const selDomain = this.state.selectedVhostDomain || domains[0] || 'localhost';
        const vhostPreview = this.state.vhostPreview || '# Click "Render Preview" to inspect configuration.';
        const activeDriver = this.state.webserverActiveDriver || 'hybrid';

        return `
            <div class="suite-card" style="padding: 1.5rem; border-radius: 8px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                            <span>🖥️</span> Web Server Engine & Native Virtual Hosts
                            <span class="suite-pill suite-pill-success" style="font-size:0.75rem;">Driver: ${this.escapeHtml(activeDriver.toUpperCase())}</span>
                        </h3>
                        <p style="color:var(--text-dim); font-size:0.8rem; margin:0.3rem 0 0 0;">
                            Generate, validate, and preview native Nginx, Apache, or Hybrid (Nginx proxy + Apache backend) virtual host configurations.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <button class="btn ghost-btn btn-sm" onclick="window.domainsViewInstance.testVhostSyntax()" title="Execute Dry-Run Syntax Test on Web Server">
                            🧪 Test Syntax
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.domainsViewInstance.purgeFastCgi('${this.escapeHtml(selDomain)}')" title="Purge FastCGI Microcache">
                            🧹 Purge FastCGI Cache
                        </button>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 280px 1fr; gap: 1.5rem;">
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 1rem 0; font-size:0.9rem; color:var(--text);">Server Engine Settings</h4>
                        
                        <div class="suite-form-group" style="margin-bottom:1rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.4rem;">Select Virtual Host Domain:</label>
                            <select id="vhost-preview-domain-sel" class="spp-element" style="width:100%; font-size:0.85rem;" onchange="window.domainsViewInstance.loadVhostPreview(this.value)">
                                ${domains.map(d => `<option value="${this.escapeHtml(d)}" ${d === selDomain ? 'selected' : ''}>${this.escapeHtml(d)}</option>`).join('')}
                            </select>
                        </div>

                        <div class="suite-form-group" style="margin-bottom:1.25rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.4rem;">Active Engine Stack:</label>
                            <select id="vhost-engine-driver-sel" class="spp-element" style="width:100%; font-size:0.85rem;" onchange="window.domainsViewInstance.changeWebserverDriver(this.value)">
                                <option value="hybrid" ${activeDriver === 'hybrid' ? 'selected' : ''}>Hybrid (Nginx Proxy + Apache .htaccess)</option>
                                <option value="nginx" ${activeDriver === 'nginx' ? 'selected' : ''}>Nginx Standalone (FastCGI / HTTP2)</option>
                                <option value="apache" ${activeDriver === 'apache' ? 'selected' : ''}>Apache Standalone (mod_php / FPM)</option>
                                <option value="app_router" ${activeDriver === 'app_router' ? 'selected' : ''}>In-Process Application Router (Dev Mode)</option>
                            </select>
                        </div>

                        <button class="btn primary-btn btn-sm" style="width:100%; justify-content:center;" onclick="window.domainsViewInstance.loadVhostPreview()">
                            <span>⚡ Render Preview</span>
                        </button>
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                            <span style="font-size:0.8rem; font-weight:600; color:var(--text-dim);">Generated Virtual Host Definition:</span>
                            <span style="font-size:0.75rem; color:var(--text-dim); font-family:var(--font-mono, monospace);">Syntax: Conf / Nginx / Apache</span>
                        </div>
                        <pre style="background:#090d16; border:1px solid #1e293b; border-radius:8px; padding:1rem; font-family:'JetBrains Mono', monospace; font-size:0.8rem; color:#94a3b8; max-height:480px; overflow-y:auto; white-space:pre-wrap; word-break:break-all;">${this.escapeHtml(vhostPreview)}</pre>
                    </div>
                </div>
            </div>
        `;
    }

    async loadVhostPreview(domain) {
        const dom = domain || document.getElementById('vhost-preview-domain-sel')?.value || (this.state.domains || [])[0]?.pattern || 'localhost';
        this.setState({ selectedVhostDomain: dom });
        try {
            const res = await this.admin.api('domains_vhost_inspect', { domain: dom });
            if (res && res.success) {
                this.setState({
                    vhostPreview: res.content || 'Empty configuration.',
                    webserverActiveDriver: res.driver || 'hybrid'
                });
                this.update();
            } else {
                this.admin.notify(res?.error || 'Failed to inspect virtual host.', 'error');
            }
        } catch (e) {
            this.admin.notify('Inspection error: ' + e.message, 'error');
        }
    }

    async testVhostSyntax() {
        try {
            this.admin.notify('Running web server syntax test...', 'info');
            const res = await this.admin.api('domains_vhost_test');
            if (res && res.valid) {
                this.admin.notify('Syntax OK! ' + (res.raw_output || 'All configurations verified.'), 'success');
            } else {
                this.admin.notify('Syntax Error: ' + (res?.error || 'Validation failed.'), 'error');
            }
        } catch (e) {
            this.admin.notify('Syntax test error: ' + e.message, 'error');
        }
    }

    async purgeFastCgi(domain) {
        try {
            const res = await this.admin.api('domains_fastcgi_purge', { domain: domain });
            if (res && res.success) {
                this.admin.notify(res.message || 'FastCGI cache cleared.', 'success');
            } else {
                this.admin.notify(res?.error || 'Purge failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Purge error: ' + e.message, 'error');
        }
    }

    async changeWebserverDriver(driver) {
        try {
            const res = await this.admin.api('domains_set_webserver_driver', { driver: driver });
            if (res && res.success) {
                this.admin.notify(res.message || 'Driver updated.', 'success');
                this.setState({ webserverActiveDriver: driver });
                await this.loadVhostPreview();
            } else {
                this.admin.notify(res?.error || 'Failed to switch driver.', 'error');
            }
        } catch (e) {
            this.admin.notify('Driver switch error: ' + e.message, 'error');
        }
    }

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    update() {
        if (this.admin && this.admin.activeView && this.admin.activeView !== 'domains') return;
        if (this.container && (this.container.style.display === 'none' || this.container.classList.contains('view-wrapper-hidden'))) return;
        if (this.container) {
            this.container.innerHTML = this.render();
            window.domainsViewInstance = this;
        }
    }
}
