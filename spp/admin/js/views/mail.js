/**
 * SPP e-Vaarta (ई-वार्ता) Mail Server Administration Console
 *
 * Sovereign Mail Server & Gateway Controller:
 * - 100% Fixed-Viewport (Zero outer scrolling; scrollable data tables only)
 * - Native SPPAdmin Theme Adaptation (Night, Day, Saffron) with High Contrast
 * - Virtual Mailbox & Domain Provisioning (Quotas, Passwords, Status, Forwarders)
 * - Spool & Queue Management (Active, Deferred, Bounced, Frozen, Flush, Purge)
 * - Inbound & Outbound Mail Routing Rules & Filter Engine
 * - Screener, Quarantine & Spam Scoring Manager
 * - RFC 6376 DKIM Key Generator, SPF, DMARC, DNS Alignment & Deliverability Tester
 * - Pure-PHP SMTP/IMAP Server Listener Bindings, TLS, Ports & Gateway Settings
 * - Seamless 1-Click Launch to Dedicated Webmail Client (/sppmail)
 */

const BaseClass = window.BaseComponent || (window.SPPUX && window.SPPUX.BaseComponent) || class {
    constructor(admin, container, props) {
        this.admin = admin;
        this.container = container;
        this.props = props || {};
    }

    update() {
        if (this.container && typeof this.render === 'function') {
            this.render(this.container);
        }
    }

    notify(message, type = 'info') {
        if (!message) return;
        if (this.admin && typeof this.admin.notify === 'function') {
            this.admin.notify(message, type);
        } else if (window.SPPUX && window.SPPUX.Notify) {
            window.SPPUX.Notify.show(message, type);
        } else if (window.admin && typeof window.admin.notify === 'function') {
            window.admin.notify(message, type);
        } else if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }
};

if (typeof window.showToast !== 'function') {
    window.showToast = (msg, type = 'info') => {
        if (window.SPPUX && window.SPPUX.Notify) {
            window.SPPUX.Notify.show(msg, type);
        } else if (window.admin && typeof window.admin.notify === 'function') {
            window.admin.notify(msg, type);
        } else if (window.mailServerInstance && typeof window.mailServerInstance.notify === 'function') {
            window.mailServerInstance.notify(msg, type);
        } else {
            console.log(`[Toast ${type}] ${msg}`);
        }
    };
}

const roundTo = (val, decimals = 1) => Number(val || 0).toFixed(decimals);

export default class MailServerView extends BaseClass {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
        this.container = container;
        this.props = props || {};
        this.initState();
        window.mailServerInstance = this;
        window.mailViewInstance = this;

        this._themeListener = () => {
            if (typeof this.update === 'function') this.update();
        };
        window.addEventListener('spp:theme-change', this._themeListener);
    }

    onThemeChange(theme) {
        this.update();
    }

    update() {
        if (this.admin && this.admin.activeView && this.admin.activeView !== 'mail') return;
        if (this.container && (this.container.style.display === 'none' || this.container.classList.contains('view-wrapper-hidden'))) return;
        if (this.container && typeof this.render === 'function') {
            this.render(this.container);
        }
    }

    initState() {
        const existing = this.state || {};
        this.state = Object.assign({
            activeTab: 'accounts', // 'accounts', 'queue', 'rules', 'screener', 'deliverability', 'diagnostics'
            loading: false,

            // Local Server Mailboxes (Hosted Domains)
            serverMailboxes: [],
            serverDomains: [],
            serverForwarders: [],
            selectedServerDomain: 'all',
            serverMailSearch: '',
            activeAccountSubtab: 'server', // 'server', 'forwarders'
            mailboxModalOpen: false,
            passwordModalOpen: false,
            quotaModalOpen: false,
            forwarderModalOpen: false,
            editingMailbox: null,
            targetMailboxEmail: '',
            targetMailboxQuota: 1024,
            newMailboxForm: {
                username: '',
                domain: '',
                password: '',
                quota_mb: 1024,
                sync_client_account: true,
                comment: ''
            },
            newForwarderForm: {
                source_email: '',
                destination_email: '',
                is_active: 1
            },
            newPasswordInput: '',
            showPasswordText: false,
            serverActionLoading: false,

            // Gateway & Queue State
            queueStatus: 'all',
            queueItems: [],
            queueMetrics: { total: 0, pending: 0, sent: 0, failed: 0 },
            loadingQueue: false,

            // Automation Rules State
            rules: [],
            loadingRules: false,
            ruleModalOpen: false,
            editingRule: null,
            ruleTestInput: { from: 'alerts@github.com', subject: '[URGENT] Build failure in pipeline', body: 'The production build has failed.' },
            ruleTestResult: null,

            // Screener & Quarantine State
            screeners: [],
            loadingScreeners: false,
            screenerModalOpen: false,

            // Deliverability & DKIM State
            deliverabilityDomain: (window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com',
            deliverabilitySelector: 'default',
            dkimBits: 2048,
            dkimResult: null,
            generatingDkim: false,
            auditResult: null,
            auditing: false,
            aligningDns: false,
            alignDnsResult: null,

            // SMTP Server Settings State
            smtpSettings: {
                host: '127.0.0.1',
                port: 587,
                encryption: 'tls',
                username: '',
                password: '',
                from_email: 'admin@' + ((window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com'),
                from_name: 'SPP e-Vaarta Mail Server',
                dkim_enabled: true,
                default_selector: 'default'
            },
            savingSettings: false,
            testRecipient: 'admin@' + ((window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com'),
            testSubject: 'SPP e-Vaarta Mail Server Diagnostic Test',
            testBody: 'Diagnostic verification message from SPP e-Vaarta Mail Server.',
            testDryRun: true,
            sendingTest: false,
            testResult: null
        }, existing);
    }

    setState(partial) {
        this.state = Object.assign({}, this.state, partial);
    }

    async callApi(action, params = {}, options = {}) {
        let res = null;
        const apiOpts = Object.assign({ lock: false }, options);
        if (this.admin && typeof this.admin.api === 'function') {
            res = await this.admin.api(action, params, apiOpts);
        } else if (this.admin && typeof this.admin.callApi === 'function') {
            res = await this.admin.callApi(action, params, apiOpts);
        } else if (window.SPPUX && typeof window.SPPUX.api === 'function') {
            res = await window.SPPUX.api(action, params);
        } else if (typeof window.sppCall === 'function') {
            res = await window.sppCall(action, params);
        } else {
            const endpoint = window.SPP_API_ENDPOINT || './api.php';
            try {
                const response = await fetch(`${endpoint}?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(params)
                });
                res = await response.json();
            } catch (err) {
                console.error("MailServerView API error:", err);
                return { success: false, error: err.message };
            }
        }

        if (res && typeof res === 'object' && res.data && typeof res.data === 'object' && !Array.isArray(res.data)) {
            res = Object.assign({}, res, res.data);
        }
        return res;
    }

    async onInit() {
        this.initState();
        await Promise.all([
            this.fetchServerMailboxes(),
            this.fetchServerDomains(),
            this.fetchServerForwarders(),
            this.fetchQueue(),
            this.fetchRules(),
            this.fetchScreeners(),
            this.fetchSettings()
        ]);
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
        this.update();
    }

    escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================================
    // SERVER MAILBOXES & FORWARDERS METHODS
    // =========================================================================

    async fetchServerMailboxes() {
        const res = await this.callApi('server_mailboxes_list', {}, { lock: false });
        if (res && res.success) {
            const updateState = { serverMailboxes: res.mailboxes || [] };
            if (res.domains && Array.isArray(res.domains) && res.domains.length > 0) {
                updateState.serverDomains = res.domains;
            }
            this.setState(updateState);
            if (this.state.activeTab === 'accounts') this.update();
        }
    }

    async fetchServerDomains() {
        const res = await this.callApi('server_domains_list', {}, { lock: false });
        if (res && res.success) {
            const domains = res.domains || [];
            const updateState = { serverDomains: domains };
            if (domains.length > 0 && (!this.state.deliverabilityDomain || this.state.deliverabilityDomain === 'spp.local' || this.state.deliverabilityDomain === 'school.local')) {
                updateState.deliverabilityDomain = domains[0];
            }
            this.setState(updateState);
            if (this.state.activeTab === 'accounts' || this.state.activeTab === 'deliverability') this.update();
        }
    }

    async fetchServerForwarders() {
        const res = await this.callApi('server_forwarders_list', {}, { lock: false });
        if (res && res.success) {
            this.setState({ serverForwarders: res.forwarders || [] });
            if (this.state.activeTab === 'accounts') this.update();
        }
    }

    setAccountSubtab(tab) {
        this.setState({ activeAccountSubtab: tab });
        this.update();
    }

    filterServerDomain(domain) {
        this.setState({ selectedServerDomain: domain });
        this.update();
    }

    searchServerMailboxes(query) {
        this.setState({ serverMailSearch: query });
        this.update();
    }

    openCreateMailboxModal() {
        const defDomain = (this.state.serverDomains && this.state.serverDomains[0]) || ((window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com');
        this.setState({
            mailboxModalOpen: true,
            newMailboxForm: {
                username: '',
                domain: defDomain,
                password: this.generateRandomPassword(),
                quota_mb: 1024,
                sync_client_account: true,
                comment: ''
            },
            showPasswordText: true
        });
        this.update();
    }

    closeCreateMailboxModal() {
        this.setState({ mailboxModalOpen: false });
        this.update();
    }

    generateRandomPassword() {
        const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%^&*';
        let pass = '';
        for (let i = 0; i < 14; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return pass;
    }

    async saveCreateMailbox() {
        const form = this.state.newMailboxForm;
        if (!form.username || !form.username.trim()) {
            alert("Username cannot be empty.");
            return;
        }
        if (!form.domain || !form.domain.trim()) {
            alert("Domain cannot be empty.");
            return;
        }
        if (!form.password || form.password.length < 8) {
            alert("Password must be at least 8 characters.");
            return;
        }

        this.setState({ serverActionLoading: true });
        this.update();

        const res = await this.callApi('server_mailbox_save', form);
        this.setState({ serverActionLoading: false });

        if (res && res.success) {
            this.notify(`Mailbox ${form.username}@${form.domain} provisioned successfully!`, "success");
            this.closeCreateMailboxModal();
            await this.fetchServerMailboxes();
        } else {
            alert("Failed to provision mailbox: " + (res.error || "Unknown error"));
            this.update();
        }
    }

    openPasswordModal(email) {
        this.setState({
            passwordModalOpen: true,
            targetMailboxEmail: email,
            newPasswordInput: this.generateRandomPassword(),
            showPasswordText: true
        });
        this.update();
    }

    closePasswordModal() {
        this.setState({ passwordModalOpen: false, targetMailboxEmail: '', newPasswordInput: '' });
        this.update();
    }

    async savePassword() {
        if (!this.state.newPasswordInput || this.state.newPasswordInput.length < 8) {
            alert("Password must be at least 8 characters.");
            return;
        }

        this.setState({ serverActionLoading: true });
        this.update();

        const res = await this.callApi('server_mailbox_password', {
            email: this.state.targetMailboxEmail,
            password: this.state.newPasswordInput
        });

        this.setState({ serverActionLoading: false });

        if (res && res.success) {
            this.notify("Password updated successfully.", "success");
            this.closePasswordModal();
        } else {
            alert("Failed to update password: " + (res.error || ""));
            this.update();
        }
    }

    openQuotaModal(email, currentQuotaMb) {
        this.setState({
            quotaModalOpen: true,
            targetMailboxEmail: email,
            targetMailboxQuota: currentQuotaMb || 1024
        });
        this.update();
    }

    closeQuotaModal() {
        this.setState({ quotaModalOpen: false, targetMailboxEmail: '' });
        this.update();
    }

    async saveQuota() {
        this.setState({ serverActionLoading: true });
        this.update();

        const res = await this.callApi('server_mailbox_quota', {
            email: this.state.targetMailboxEmail,
            quota_mb: parseInt(this.state.targetMailboxQuota) || 1024
        });

        this.setState({ serverActionLoading: false });

        if (res && res.success) {
            this.notify("Storage quota updated.", "success");
            this.closeQuotaModal();
            await this.fetchServerMailboxes();
        } else {
            alert("Failed to update quota: " + (res.error || ""));
            this.update();
        }
    }

    async toggleMailboxStatus(email, currentStatus) {
        const newStatus = (currentStatus === 'active') ? 'suspended' : 'active';
        const res = await this.callApi('server_mailbox_status', {
            email: email,
            status: newStatus
        });
        if (res && res.success) {
            this.notify(`Mailbox ${email} is now ${newStatus}.`, "info");
            await this.fetchServerMailboxes();
        } else {
            alert("Failed to update mailbox status: " + (res.error || ""));
        }
    }

    async deleteServerMailbox(email) {
        if (!confirm(`Are you sure you want to delete mailbox ${email}?\n\nThis will remove the virtual account from the database and archive its Maildir spool directory.`)) {
            return;
        }
        const res = await this.callApi('server_mailbox_delete', { email: email });
        if (res && res.success) {
            this.notify(`Mailbox ${email} removed.`, "info");
            await this.fetchServerMailboxes();
        } else {
            alert("Failed to delete mailbox: " + (res.error || ""));
        }
    }

    openDeviceConnectModal(email) {
        this.setState({
            deviceConnectModalOpen: true,
            deviceConnectEmail: email,
            deviceConnectTab: 'apple'
        });
        this.update();
    }

    closeDeviceConnectModal() {
        this.setState({ deviceConnectModalOpen: false, deviceConnectEmail: '' });
        this.update();
    }

    async downloadDeviceConfig(email, format = 'mobileconfig') {
        try {
            const res = await this.callApi('mail_device_config', { email: email, format: format });
            if (res && res.success && res.content) {
                const blob = new Blob([res.content], { type: res.mime_type || 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = res.filename || `${email}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.notify(`Downloaded ${res.filename}`, "success");
            } else {
                alert("Failed to generate device config: " + (res.error || "Unknown error"));
            }
        } catch (e) {
            alert("Error downloading configuration: " + e.message);
        }
    }

    openForwarderModal() {
        const defDomain = (this.state.serverDomains && this.state.serverDomains[0]) || ((window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com');
        this.setState({
            forwarderModalOpen: true,
            newForwarderForm: {
                source_email: '@' + defDomain,
                destination_email: '',
                is_active: 1
            }
        });
        this.update();
    }

    closeForwarderModal() {
        this.setState({ forwarderModalOpen: false });
        this.update();
    }

    async saveForwarder() {
        const form = this.state.newForwarderForm;
        if (!form.source_email || !form.source_email.includes('@')) {
            alert("Please specify a valid source address (e.g. contact@domain.com).");
            return;
        }
        if (!form.destination_email || !form.destination_email.includes('@')) {
            alert("Please specify a valid destination email address.");
            return;
        }

        this.setState({ serverActionLoading: true });
        this.update();

        const res = await this.callApi('server_forwarder_save', form);
        this.setState({ serverActionLoading: false });

        if (res && res.success) {
            this.notify("Forwarder created successfully.", "success");
            this.closeForwarderModal();
            await this.fetchServerForwarders();
            await this.fetchServerMailboxes();
        } else {
            alert("Failed to create forwarder: " + (res.error || ""));
            this.update();
        }
    }

    async deleteForwarder(sourceEmail) {
        if (!confirm(`Remove forwarder for ${sourceEmail}?`)) return;
        const res = await this.callApi('server_forwarder_delete', { source_email: sourceEmail });
        if (res && res.success) {
            this.notify("Forwarder removed.", "info");
            await this.fetchServerForwarders();
            await this.fetchServerMailboxes();
        } else {
            alert("Failed to remove forwarder: " + (res.error || ""));
        }
    }

    openWebmailFor(email) {
        const url = '../sppmail/?account=' + encodeURIComponent(email);
        window.open(url, '_blank');
    }

    // =========================================================================
    // SPOOL & GATEWAY METHODS
    // =========================================================================

    async fetchSettings() {
        const res = await this.callApi('mail_settings_get');
        const settings = res && (res.settings || (res.data && res.data.settings));
        if (res && res.success && settings) {
            this.setState({ smtpSettings: Object.assign({}, this.state.smtpSettings, settings) });
            if (this.state.activeTab === 'diagnostics') this.update();
        }
    }

    async saveSettings() {
        this.setState({ savingSettings: true });
        this.update();
        const res = await this.callApi('mail_settings_save', { settings: this.state.smtpSettings });
        this.setState({ savingSettings: false });
        if (res && res.success) {
            this.notify("Server settings updated successfully.", "success");
        } else {
            alert("Failed to save settings: " + (res.error || ""));
        }
        this.update();
    }

    async fetchQueue(filterStatus = 'all') {
        this.setState({ loadingQueue: true, queueStatus: filterStatus });
        if (this.state.activeTab === 'queue') this.update();
        const res = await this.callApi('mail_queue_list', { status: filterStatus !== 'all' ? filterStatus : null }, { lock: false });
        if (res && res.success) {
            this.setState({
                queueItems: res.items || [],
                queueMetrics: res.metrics || this.state.queueMetrics,
                loadingQueue: false
            });
        } else {
            this.setState({ loadingQueue: false });
        }
        if (this.state.activeTab === 'queue') this.update();
    }

    async flushQueue() {
        this.notify("Flushing outbound mail spool...", "info");
        const res = await this.callApi('mail_queue_flush');
        if (res && res.success) {
            this.notify(`Spool flushed! Dispatched ${res.dispatched || 0} messages.`, "success");
            await this.fetchQueue(this.state.queueStatus);
        } else {
            alert("Failed to flush queue: " + (res.error || ""));
        }
    }

    async retryQueueItem(id) {
        const res = await this.callApi('mail_queue_retry', { id: id });
        if (res && res.success) {
            this.notify("Message re-queued for immediate delivery.", "info");
            await this.fetchQueue(this.state.queueStatus);
        } else {
            alert("Failed to retry message: " + (res.error || ""));
        }
    }

    async purgeQueue() {
        if (!confirm("Are you sure you want to purge all failed messages from the spool?")) return;
        const res = await this.callApi('mail_queue_purge', { status: 'failed' });
        if (res && res.success) {
            this.notify("Failed messages purged from spool.", "info");
            await this.fetchQueue(this.state.queueStatus);
        } else {
            alert("Failed to purge queue: " + (res.error || ""));
        }
    }

    // =========================================================================
    // DELIVERABILITY & DKIM METHODS
    // =========================================================================

    async generateDkim() {
        this.setState({ generatingDkim: true, dkimResult: null });
        this.update();
        const res = await this.callApi('mail_deliverability_generate_dkim', {
            domain: this.state.deliverabilityDomain,
            selector: this.state.deliverabilitySelector,
            bits: this.state.dkimBits
        });
        this.setState({ generatingDkim: false, dkimResult: res });
        if (res && res.success) {
            this.notify(`2048-bit DKIM Keypair generated for ${this.state.deliverabilityDomain}!`, "success");
        }
        this.update();
    }

    async runAudit() {
        this.setState({ auditing: true, auditResult: null });
        this.update();
        const res = await this.callApi('mail_deliverability_audit', { domain: this.state.deliverabilityDomain });
        this.setState({ auditing: false, auditResult: res });
        if (res && res.success) {
            this.notify("DNS deliverability audit complete.", "info");
        }
        this.update();
    }

    async alignDnsRecords() {
        this.setState({ aligningDns: true, alignDnsResult: null });
        this.update();
        const res = await this.callApi('mail_deliverability_align_dns', {
            domain: this.state.deliverabilityDomain,
            selector: this.state.deliverabilitySelector
        });
        this.setState({ aligningDns: false, alignDnsResult: res });
        if (res && res.success) {
            this.notify(`DNS records aligned to BIND zone for ${this.state.deliverabilityDomain}!`, "success");
            await this.runAudit();
        } else {
            alert("Auto-alignment failed: " + (res.error || ""));
        }
        this.update();
    }

    async sendDiagnosticTest() {
        this.setState({ sendingTest: true, testResult: null });
        this.update();
        const res = await this.callApi('mail_deliverability_send_test', {
            to: this.state.testRecipient,
            subject: this.state.testSubject,
            body: this.state.testBody,
            dry_run: this.state.testDryRun
        });
        this.setState({ sendingTest: false, testResult: res });
        if (res && res.success) {
            this.notify("Diagnostic test email dispatched successfully!", "success");
        }
        this.update();
    }

    // =========================================================================
    // AUTOMATION RULES METHODS
    // =========================================================================

    async fetchRules() {
        this.setState({ loadingRules: true });
        const res = await this.callApi('rules_list', {}, { lock: false });
        if (res && res.success) {
            this.setState({ rules: res.rules || [], loadingRules: false });
        } else {
            this.setState({ loadingRules: false });
        }
        if (this.state.activeTab === 'rules') this.update();
    }

    openRuleModal(rule = null) {
        this.setState({
            ruleModalOpen: true,
            editingRule: rule ? JSON.parse(JSON.stringify(rule)) : {
                id: null,
                name: '',
                is_active: true,
                priority: 10,
                rule: {
                    conditions: [{ field: 'subject', operator: 'contains', value: '' }],
                    condition_match: 'all',
                    actions: [{ type: 'add_tag', value: 'flagged' }]
                }
            },
            ruleTestResult: null
        });
        this.update();
    }

    closeRuleModal() {
        this.setState({ ruleModalOpen: false, editingRule: null });
        this.update();
    }

    async saveEditingRule() {
        const rule = this.state.editingRule;
        if (!rule.name || !rule.name.trim()) {
            alert("Rule name is required.");
            return;
        }
        const res = await this.callApi('rule_save', { rule: rule });
        if (res && res.success) {
            this.notify("Automation rule saved.", "success");
            this.closeRuleModal();
            await this.fetchRules();
        } else {
            alert("Failed to save rule: " + (res.error || ""));
        }
    }

    async deleteRule(id) {
        if (!confirm("Are you sure you want to delete this rule?")) return;
        const res = await this.callApi('rule_delete', { id: id });
        if (res && res.success) {
            this.notify("Rule deleted.", "info");
            await this.fetchRules();
        }
    }

    async toggleRule(id, currentStatus) {
        const res = await this.callApi('rule_toggle', { id: id, is_active: !currentStatus });
        if (res && res.success) {
            await this.fetchRules();
        }
    }

    async testRuleSimulation() {
        const res = await this.callApi('rule_test', {
            rule: this.state.editingRule ? this.state.editingRule.rule : null,
            test_message: this.state.ruleTestInput
        });
        this.setState({ ruleTestResult: res });
        this.update();
    }

    // =========================================================================
    // SCREENER & VIP SENDER METHODS
    // =========================================================================

    async fetchScreeners() {
        this.setState({ loadingScreeners: true });
        const res = await this.callApi('screener_list', {}, { lock: false });
        if (res && res.success) {
            this.setState({ screeners: res.screeners || [], loadingScreeners: false });
        } else {
            this.setState({ loadingScreeners: false });
        }
        if (this.state.activeTab === 'screener') this.update();
    }

    async screenSender(senderEmail, action) {
        const res = await this.callApi('screener_action', { sender_email: senderEmail, action: action });
        if (res && res.success) {
            this.notify(`Sender ${senderEmail} set to ${action}.`, "info");
            await this.fetchScreeners();
        } else {
            alert("Failed to update screener: " + (res.error || ""));
        }
    }

    // =========================================================================
    // RENDERING LOGIC - FIXED-VIEWPORT, ZERO SCROLLING, HIGH CONTRAST
    // =========================================================================

    render(targetContainer) {
        const container = targetContainer || this.container;
        if (!container) return;
        this.container = container;
        this.initState();

        const currentTheme = (typeof document !== 'undefined' && document.body && document.body.getAttribute('data-theme')) || (typeof localStorage !== 'undefined' && localStorage.getItem('spp_admin_theme')) || 'night';

        let content = `
            <style id="evaarta-server-styles">
                /* Base Reset & Zero-Scroll Container */
                .evaarta-server-root {
                    height: 100%;
                    width: 100%;
                    max-height: 100%;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden !important;
                    font-family: 'Outfit', sans-serif;
                    box-sizing: border-box;
                    background: var(--bg-surface, #0d0f18);
                    color: var(--text-main, #e2e8f0);
                }

                /* =============================================================
                   THEME & CONTRAST TOKENS (Synced with SPPAdmin)
                   ============================================================= */

                /* Night Theme (Default Dark) */
                :root, [data-theme="night"] .evaarta-server-root, .evaarta-server-root[data-theme="night"] {
                    --es-bg: #0d0f18;
                    --es-panel: #111528;
                    --es-panel-alt: #161b33;
                    --es-border: rgba(255, 255, 255, 0.09);
                    --es-border-strong: rgba(255, 255, 255, 0.18);
                    --es-text-bright: #ffffff;
                    --es-text-main: #f1f5f9;
                    --es-text-dim: #94a3b8;
                    --es-text-muted: #64748b;
                    --es-accent: #6366f1;
                    --es-accent-hover: #818cf8;
                    --es-accent-glow: rgba(99, 102, 241, 0.35);
                    --es-input-bg: #090c16;
                    --es-input-border: rgba(255, 255, 255, 0.14);
                    --es-row-hover: rgba(255, 255, 255, 0.04);
                    --es-badge-green-bg: rgba(52, 211, 153, 0.16);
                    --es-badge-green-text: #34d399;
                    --es-badge-yellow-bg: rgba(251, 191, 36, 0.16);
                    --es-badge-yellow-text: #fbbf24;
                    --es-badge-red-bg: rgba(248, 113, 113, 0.16);
                    --es-badge-red-text: #f87171;
                    --es-badge-blue-bg: rgba(99, 102, 241, 0.16);
                    --es-badge-blue-text: #a5b4fc;
                }

                /* Day Theme (Crisp Light Mode - Razor-sharp slate 900 text) */
                [data-theme="day"] .evaarta-server-root, .evaarta-server-root[data-theme="day"] {
                    --es-bg: #f8fafc;
                    --es-panel: #ffffff;
                    --es-panel-alt: #f1f5f9;
                    --es-border: #cbd5e1;
                    --es-border-strong: #94a3b8;
                    --es-text-bright: #020617;
                    --es-text-main: #0f172a;
                    --es-text-dim: #334155;
                    --es-text-muted: #475569;
                    --es-accent: #2563eb;
                    --es-accent-hover: #1d4ed8;
                    --es-accent-glow: rgba(37, 99, 235, 0.25);
                    --es-input-bg: #ffffff;
                    --es-input-border: #94a3b8;
                    --es-row-hover: #f1f5f9;
                    --es-badge-green-bg: #dcfce7;
                    --es-badge-green-text: #14532d;
                    --es-badge-yellow-bg: #fef3c7;
                    --es-badge-yellow-text: #78350f;
                    --es-badge-red-bg: #fee2e2;
                    --es-badge-red-text: #7f1d1d;
                    --es-badge-blue-bg: #dbeafe;
                    --es-badge-blue-text: #1e3a8a;
                }

                /* Saffron Theme (Warm Light Saffron - Rich deep dark brown text) */
                [data-theme="saffron"] .evaarta-server-root, .evaarta-server-root[data-theme="saffron"] {
                    --es-bg: #fff4e6;
                    --es-panel: #ffffff;
                    --es-panel-alt: #fff8f0;
                    --es-border: rgba(124, 45, 18, 0.22);
                    --es-border-strong: rgba(124, 45, 18, 0.4);
                    --es-text-bright: #1f0702;
                    --es-text-main: #2d0a03;
                    --es-text-dim: #5c1d06;
                    --es-text-muted: #7c2d12;
                    --es-accent: #ea580c;
                    --es-accent-hover: #c2410c;
                    --es-accent-glow: rgba(234, 88, 12, 0.35);
                    --es-input-bg: #fffefc;
                    --es-input-border: rgba(124, 45, 18, 0.3);
                    --es-row-hover: #fff8f0;
                    --es-badge-green-bg: #dcfce7;
                    --es-badge-green-text: #14532d;
                    --es-badge-yellow-bg: #ffedd5;
                    --es-badge-yellow-text: #7c2d12;
                    --es-badge-red-bg: #fee2e2;
                    --es-badge-red-text: #7f1d1d;
                    --es-badge-blue-bg: #ffedd5;
                    --es-badge-blue-text: #9a3412;
                }

                /* Pinned Top Navigation Bar - 100% Non-scrolling & Fluid */
                .es-top-toolbar {
                    min-height: 48px;
                    background: var(--es-panel);
                    border-bottom: 1px solid var(--es-border);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0 14px;
                    flex-shrink: 0;
                    gap: 10px;
                    z-index: 10;
                    box-sizing: border-box;
                    overflow: hidden;
                }
                .es-tab-pills {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    flex: 1 1 auto;
                    min-width: 0;
                    overflow: hidden;
                }
                .es-tab-btn {
                    flex: 1 1 auto;
                    min-width: 0;
                    padding: 6px 10px;
                    border-radius: 7px;
                    font-weight: 600;
                    font-size: 0.8rem;
                    border: none;
                    cursor: pointer;
                    background: transparent;
                    color: var(--es-text-dim);
                    transition: all 0.18s ease;
                    white-space: nowrap;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                }
                .es-tab-btn:hover {
                    color: var(--es-text-bright);
                    background: var(--es-row-hover, rgba(125, 125, 125, 0.08));
                }
                .es-tab-btn.active {
                    background: var(--es-accent) !important;
                    color: #ffffff !important;
                    box-shadow: 0 2px 8px var(--es-accent-glow);
                }
                .es-tab-badge {
                    font-size: 0.68rem;
                    font-weight: 700;
                    padding: 1px 6px;
                    border-radius: 9999px;
                    background: var(--es-panel-alt);
                    color: var(--es-text-dim);
                    line-height: 1.2;
                    flex-shrink: 0;
                }
                .es-tab-btn.active .es-tab-badge {
                    background: rgba(255, 255, 255, 0.25);
                    color: #ffffff;
                }
                .es-toolbar-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-shrink: 0;
                }
                .es-smtp-badge {
                    padding: 4px 8px;
                    font-size: 0.72rem;
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    white-space: nowrap;
                }
                .es-status-dot {
                    display: inline-block;
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                    background: currentColor;
                    flex-shrink: 0;
                }
                .es-webmail-btn {
                    padding: 6px 12px;
                    font-size: 0.78rem;
                    white-space: nowrap;
                    flex-shrink: 0;
                }
                .es-tab-label {
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .es-tab-icon {
                    flex-shrink: 0;
                    font-size: 0.95rem;
                }

                /* Responsive Scaling for All Screen Sizes (No Scrolling Ever) */
                @media (max-width: 1250px) {
                    .es-tab-btn {
                        padding: 5px 8px;
                        font-size: 0.76rem;
                        gap: 4px;
                    }
                    .es-webmail-btn {
                        padding: 5px 10px;
                        font-size: 0.75rem;
                    }
                }

                @media (max-width: 1050px) {
                    .es-top-toolbar {
                        height: auto;
                        min-height: 48px;
                        padding: 6px 10px;
                        flex-wrap: wrap;
                        gap: 6px;
                    }
                    .es-tab-pills {
                        width: 100%;
                        order: 1;
                        display: grid;
                        grid-template-columns: repeat(6, 1fr);
                        gap: 3px;
                    }
                    .es-tab-btn {
                        padding: 6px 4px;
                        font-size: 0.74rem;
                    }
                    .es-toolbar-actions {
                        order: 2;
                        width: 100%;
                        justify-content: space-between;
                        padding-top: 4px;
                        border-top: 1px solid var(--es-border);
                    }
                }

                @media (max-width: 768px) {
                    .es-tab-pills {
                        grid-template-columns: repeat(3, 1fr);
                        gap: 4px;
                    }
                    .es-tab-btn {
                        padding: 5px 4px;
                        font-size: 0.72rem;
                    }
                    .es-hud-ribbon {
                        grid-template-columns: repeat(2, 1fr);
                        gap: 6px;
                    }
                }

                @media (max-width: 480px) {
                    .es-tab-pills {
                        grid-template-columns: repeat(3, 1fr);
                    }
                    .es-tab-badge {
                        display: none;
                    }
                }

                /* Workspace Fixed Viewport Deck */
                .es-deck-viewport {
                    flex: 1;
                    min-height: 0;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    padding: 12px 18px;
                    gap: 10px;
                    box-sizing: border-box;
                }

                /* HUD Statistics Ribbon */
                .es-hud-ribbon {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 10px;
                    flex-shrink: 0;
                }
                .es-hud-chip {
                    background: var(--es-panel);
                    border: 1px solid var(--es-border);
                    border-radius: 8px;
                    padding: 8px 14px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                .es-hud-label {
                    font-size: 0.73rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: var(--es-text-dim);
                }
                .es-hud-val {
                    font-size: 1.25rem;
                    font-weight: 800;
                    color: var(--es-text-bright);
                    font-variant-numeric: tabular-nums;
                }

                /* Compact Action Toolbar */
                .es-action-bar {
                    background: var(--es-panel);
                    border: 1px solid var(--es-border);
                    border-radius: 8px;
                    padding: 6px 12px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-shrink: 0;
                    gap: 10px;
                }

                /* Scrollable Data Table Container */
                .es-table-card {
                    flex: 1;
                    min-height: 0;
                    background: var(--es-panel);
                    border: 1px solid var(--es-border);
                    border-radius: 8px;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                }
                .es-table-scroll {
                    flex: 1;
                    min-height: 0;
                    overflow-y: auto;
                    overflow-x: auto;
                    scrollbar-width: thin;
                    scrollbar-color: var(--es-border) transparent;
                }
                .es-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 0.83rem;
                    color: var(--es-text-main);
                    text-align: left;
                }
                .es-table th {
                    position: sticky;
                    top: 0;
                    background: var(--es-panel);
                    padding: 9px 12px;
                    font-size: 0.72rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: var(--es-text-dim);
                    border-bottom: 2px solid var(--es-border);
                    z-index: 2;
                }
                .es-table td {
                    padding: 8px 12px;
                    border-bottom: 1px solid var(--es-border);
                    vertical-align: middle;
                }
                .es-table tr:hover td {
                    background: var(--es-row-hover);
                }

                /* High-Contrast Interactive Controls */
                .es-btn-primary {
                    background: var(--es-accent);
                    color: #ffffff !important;
                    border: none;
                    padding: 6px 14px;
                    border-radius: 6px;
                    font-weight: 600;
                    font-size: 0.8rem;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    text-decoration: none;
                    transition: all 0.15s ease;
                    box-shadow: 0 1px 4px var(--es-accent-glow);
                }
                .es-btn-primary:hover {
                    opacity: 0.92;
                    transform: translateY(-1px);
                }
                .es-btn-ghost {
                    background: transparent;
                    color: var(--es-text-main);
                    border: 1px solid var(--es-border);
                    padding: 5px 10px;
                    border-radius: 6px;
                    font-size: 0.78rem;
                    font-weight: 600;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    text-decoration: none;
                }
                .es-btn-ghost:hover {
                    background: var(--es-row-hover);
                    border-color: var(--es-border-strong);
                }
                .es-input {
                    background: var(--es-input-bg);
                    border: 1px solid var(--es-input-border);
                    border-radius: 6px;
                    padding: 6px 10px;
                    color: var(--es-text-bright);
                    font-size: 0.82rem;
                    box-sizing: border-box;
                }
                .es-input:focus {
                    outline: 2px solid var(--es-accent);
                    outline-offset: 1px;
                }
                .es-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 2px 7px;
                    border-radius: 9999px;
                    font-size: 0.72rem;
                    font-weight: 700;
                    letter-spacing: 0.02em;
                }
                .es-badge.green { background: var(--es-badge-green-bg); color: var(--es-badge-green-text); }
                .es-badge.yellow { background: var(--es-badge-yellow-bg); color: var(--es-badge-yellow-text); }
                .es-badge.red { background: var(--es-badge-red-bg); color: var(--es-badge-red-text); }
                .es-badge.blue { background: var(--es-badge-blue-bg); color: var(--es-badge-blue-text); }
            </style>

            <div class="evaarta-server-root" data-theme="${currentTheme}">
                <!-- Pinned Top Control Bar (Always visible by default without scrolling on all screen sizes) -->
                <header class="es-top-toolbar">
                    <nav class="es-tab-pills" aria-label="Mail Server Navigation">
                        <button class="es-tab-btn ${this.state.activeTab === 'accounts' ? 'active' : ''}" onclick="window.mailServerInstance.switchTab('accounts')" title="Virtual Mailboxes & Hosted Domains">
                            <span class="es-tab-icon">👥</span>
                            <span class="es-tab-label">Mailboxes</span>
                            <span class="es-tab-badge">${(this.state.serverMailboxes || []).length}</span>
                        </button>
                        <button class="es-tab-btn ${this.state.activeTab === 'queue' ? 'active' : ''}" onclick="window.mailServerInstance.switchTab('queue')" title="Spool & Outbound Queue">
                            <span class="es-tab-icon">📬</span>
                            <span class="es-tab-label">Queue</span>
                            <span class="es-tab-badge">${(this.state.queueMetrics?.pending) ?? 0}</span>
                        </button>
                        <button class="es-tab-btn ${this.state.activeTab === 'rules' ? 'active' : ''}" onclick="window.mailServerInstance.switchTab('rules')" title="Inbound Routing Rules & Filters">
                            <span class="es-tab-icon">⚡</span>
                            <span class="es-tab-label">Rules</span>
                            <span class="es-tab-badge">${(this.state.rules || []).length}</span>
                        </button>
                        <button class="es-tab-btn ${this.state.activeTab === 'screener' ? 'active' : ''}" onclick="window.mailServerInstance.switchTab('screener')" title="Screener & Quarantine Triage">
                            <span class="es-tab-icon">🛡️</span>
                            <span class="es-tab-label">Screener</span>
                            <span class="es-tab-badge">${(this.state.screeners || []).length}</span>
                        </button>
                        <button class="es-tab-btn ${this.state.activeTab === 'deliverability' ? 'active' : ''}" onclick="window.mailServerInstance.switchTab('deliverability')" title="RFC 6376 DKIM Keys & DNS Deliverability">
                            <span class="es-tab-icon">🌐</span>
                            <span class="es-tab-label">Deliverability</span>
                        </button>
                        <button class="es-tab-btn ${this.state.activeTab === 'diagnostics' ? 'active' : ''}" onclick="window.mailServerInstance.switchTab('diagnostics')" title="Server Socket Bindings & Health Configuration">
                            <span class="es-tab-icon">⚙️</span>
                            <span class="es-tab-label">Configuration</span>
                        </button>
                    </nav>

                    <div class="es-toolbar-actions">
                        <!-- Server Status Badge -->
                        <div class="es-badge green es-smtp-badge" title="Pure-PHP RFC 5321 SMTP Listener running on port ${this.state.smtpSettings?.port || 587}">
                            <span class="es-status-dot"></span>
                            <span class="es-smtp-text">SMTP :${this.state.smtpSettings?.port || 587}</span>
                        </div>

                        <!-- 1-Click Launch Webmail Client -->
                        <a href="../sppmail/" target="_blank" class="es-btn-primary es-webmail-btn" title="Launch e-Vaarta Standalone Webmail Client (/sppmail)">
                            <span>🌐</span>
                            <span class="es-webmail-label">Webmail Client</span>
                            <span class="es-webmail-arrow">↗</span>
                        </a>
                    </div>
                </header>

                <!-- Fixed-Height Workspace Deck -->
                <main class="es-deck-viewport">
        `;

        if (this.state.activeTab === 'accounts') {
            content += this.renderAccounts();
        } else if (this.state.activeTab === 'queue') {
            content += this.renderQueueSpool();
        } else if (this.state.activeTab === 'rules') {
            content += this.renderRulesView();
        } else if (this.state.activeTab === 'screener') {
            content += this.renderScreenerView();
        } else if (this.state.activeTab === 'deliverability') {
            content += this.renderDeliverability();
        } else if (this.state.activeTab === 'diagnostics') {
            content += this.renderDiagnostics();
        }

        content += `
                </main>

                <!-- Modals -->
                ${this.state.ruleModalOpen ? this.renderRuleModal() : ''}
                ${this.state.mailboxModalOpen ? this.renderServerMailboxModal() : ''}
                ${this.state.passwordModalOpen ? this.renderServerPasswordModal() : ''}
                ${this.state.quotaModalOpen ? this.renderServerQuotaModal() : ''}
                ${this.state.forwarderModalOpen ? this.renderServerForwarderModal() : ''}
                ${this.state.deviceConnectModalOpen ? this.renderDeviceConnectModal() : ''}
            </div>
        `;

        container.innerHTML = content;
    }

    // =========================================================================
    // TAB 1: MAILBOXES & DOMAINS (Zero-Scroll Layout)
    // =========================================================================

    renderAccounts() {
        const subtab = this.state.activeAccountSubtab || 'server';
        const serverBoxes = this.state.serverMailboxes || [];
        const serverDomains = this.state.serverDomains || [];
        const forwarders = this.state.serverForwarders || [];
        const selDomain = this.state.selectedServerDomain || 'all';
        const searchQ = (this.state.serverMailSearch || '').toLowerCase().trim();

        // Aggregated Metrics
        const totalUsedMb = serverBoxes.reduce((acc, b) => acc + (b.used_mb || 0), 0);
        const totalQuotaMb = serverBoxes.reduce((acc, b) => acc + (b.quota_mb || 0), 0);

        // Filtered Mailboxes
        const filteredBoxes = serverBoxes.filter(box => {
            if (selDomain !== 'all' && box.domain.toLowerCase() !== selDomain.toLowerCase()) return false;
            if (searchQ && !box.email.toLowerCase().includes(searchQ) && !(box.comment || '').toLowerCase().includes(searchQ)) return false;
            return true;
        });

        // Filtered Forwarders
        const filteredForwarders = forwarders.filter(f => {
            if (selDomain !== 'all') {
                const parts = (f.source_email || '').split('@');
                if (parts[1] && parts[1].toLowerCase() !== selDomain.toLowerCase()) return false;
            }
            if (searchQ && !f.source_email.toLowerCase().includes(searchQ) && !f.destination_email.toLowerCase().includes(searchQ)) return false;
            return true;
        });

        return `
            <!-- Ribbon HUD Metrics (Height: ~44px) -->
            <div class="es-hud-ribbon">
                <div class="es-hud-chip">
                    <span class="es-hud-label">🏢 Total Mailboxes</span>
                    <span class="es-hud-val">${serverBoxes.length}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">🌐 Hosted Domains</span>
                    <span class="es-hud-val">${serverDomains.length}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">💾 Storage Allocated</span>
                    <span class="es-hud-val">${totalUsedMb} <small style="font-size:0.75rem; font-weight:600; color:var(--es-text-dim);">/ ${totalQuotaMb} MB</small></span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">🔀 Active Forwarders</span>
                    <span class="es-hud-val">${forwarders.length}</span>
                </div>
            </div>

            <!-- Action Toolbar (Height: ~38px) -->
            <div class="es-action-bar">
                <div style="display:flex; align-items:center; gap:8px;">
                    <!-- Sub-tab pill switcher -->
                    <div style="display:flex; background:var(--es-panel-alt); padding:2px; border-radius:6px; border:1px solid var(--es-border);">
                        <button onclick="window.mailServerInstance.setAccountSubtab('server')" style="background:${subtab === 'server' ? 'var(--es-accent)' : 'transparent'}; color:${subtab === 'server' ? '#ffffff' : 'var(--es-text-dim)'}; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            🏢 Mailboxes (${filteredBoxes.length})
                        </button>
                        <button onclick="window.mailServerInstance.setAccountSubtab('forwarders')" style="background:${subtab === 'forwarders' ? 'var(--es-accent)' : 'transparent'}; color:${subtab === 'forwarders' ? '#ffffff' : 'var(--es-text-dim)'}; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            🔀 Forwarders (${filteredForwarders.length})
                        </button>
                    </div>

                    <!-- Domain selector dropdown -->
                    <select class="es-input" style="padding:4px 8px; font-size:0.8rem;" onchange="window.mailServerInstance.filterServerDomain(this.value)">
                        <option value="all" ${selDomain === 'all' ? 'selected' : ''}>All Hosted Domains (${serverDomains.length})</option>
                        ${serverDomains.map(d => `<option value="${d}" ${selDomain === d ? 'selected' : ''}>${d}</option>`).join('')}
                    </select>

                    <!-- Live search filter -->
                    <input type="text" class="es-input" placeholder="🔍 Filter accounts..." value="${this.escapeHtml(this.state.serverMailSearch || '')}" oninput="window.mailServerInstance.searchServerMailboxes(this.value)" style="width:200px;">
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    ${subtab === 'server' ? `
                        <button onclick="window.mailServerInstance.openCreateMailboxModal()" class="es-btn-primary">
                            <span>➕</span> Create Mailbox
                        </button>
                    ` : `
                        <button onclick="window.mailServerInstance.openForwarderModal()" class="es-btn-primary">
                            <span>🔀</span> Add Forwarder
                        </button>
                    `}
                </div>
            </div>

            <!-- Table Container (Takes 100% remaining space, scrolls internally only) -->
            <div class="es-table-card">
                <div class="es-table-scroll">
                    ${subtab === 'server' ? this.renderServerMailboxesTable(filteredBoxes) : this.renderForwardersTable(filteredForwarders)}
                </div>
            </div>
        `;
    }

    renderServerMailboxesTable(boxes) {
        if (!boxes || boxes.length === 0) {
            return `
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; text-align:center;">
                    <span style="font-size:2.5rem; margin-bottom:8px;">📬</span>
                    <h3 style="margin:0 0 4px 0; color:var(--es-text-bright); font-size:1.1rem;">No Mailboxes Found</h3>
                    <p style="margin:0 0 16px 0; color:var(--es-text-dim); font-size:0.82rem;">Create your first sovereign virtual mailbox to start hosting emails locally.</p>
                    <button onclick="window.mailServerInstance.openCreateMailboxModal()" class="es-btn-primary">
                        <span>➕</span> Create Mailbox
                    </button>
                </div>
            `;
        }

        return `
            <table class="es-table">
                <thead>
                    <tr>
                        <th style="width:30%;">Mailbox Address</th>
                        <th style="width:16%;">Domain</th>
                        <th style="width:22%;">Storage & Quota</th>
                        <th style="width:12%;">Status</th>
                        <th style="width:20%; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${boxes.map(b => {
                        const used = b.used_mb || 0;
                        const quota = b.quota_mb || 1024;
                        const pct = Math.min(100, Math.round((used / quota) * 100));
                        const isActive = b.status === 'active';

                        return `
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="font-size:1.1rem;">✉️</span>
                                        <div>
                                            <div style="font-weight:700; color:var(--es-text-bright); font-size:0.88rem;">${b.email}</div>
                                            ${b.comment ? `<div style="font-size:0.75rem; color:var(--es-text-muted);">${this.escapeHtml(b.comment)}</div>` : ''}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="es-badge blue">${b.domain}</span>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:3px;">
                                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:600;">
                                            <span style="color:var(--es-text-dim);">${used} MB / ${quota} MB</span>
                                            <span style="color:${pct > 80 ? 'var(--es-badge-red-text)' : 'var(--es-text-dim)'};">${pct}%</span>
                                        </div>
                                        <div style="width:100%; height:5px; background:var(--es-panel-alt); border-radius:3px; overflow:hidden;">
                                            <div style="width:${pct}%; height:100%; background:${pct > 85 ? '#ef4444' : pct > 65 ? '#f59e0b' : 'var(--es-accent)'}; border-radius:3px;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="es-badge ${isActive ? 'green' : 'red'}">
                                        ${isActive ? 'Active' : 'Suspended'}
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; align-items:center; gap:5px;">
                                        <button onclick="window.mailServerInstance.openWebmailFor('${b.email}')" class="es-btn-ghost" title="Open mailbox directly in Webmail Client">
                                            <span>🌐</span> Webmail
                                        </button>
                                        <button onclick="window.mailServerInstance.openDeviceConnectModal('${b.email}')" class="es-btn-ghost" title="Connect Apple, Outlook or Android Devices">
                                            <span>📱</span>
                                        </button>
                                        <button onclick="window.mailServerInstance.openPasswordModal('${b.email}')" class="es-btn-ghost" title="Reset mailbox password">
                                            <span>🔑</span>
                                        </button>
                                        <button onclick="window.mailServerInstance.openQuotaModal('${b.email}', ${quota})" class="es-btn-ghost" title="Adjust storage quota">
                                            <span>💾</span>
                                        </button>
                                        <button onclick="window.mailServerInstance.toggleMailboxStatus('${b.email}', '${b.status}')" class="es-btn-ghost" title="${isActive ? 'Suspend' : 'Activate'} mailbox">
                                            <span>${isActive ? '⏸️' : '▶️'}</span>
                                        </button>
                                        <button onclick="window.mailServerInstance.deleteServerMailbox('${b.email}')" class="es-btn-ghost" style="color:var(--es-badge-red-text);" title="Delete mailbox">
                                            <span>🗑️</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
    }

    renderForwardersTable(forwarders) {
        if (!forwarders || forwarders.length === 0) {
            return `
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; text-align:center;">
                    <span style="font-size:2.5rem; margin-bottom:8px;">🔀</span>
                    <h3 style="margin:0 0 4px 0; color:var(--es-text-bright); font-size:1.1rem;">No Forwarders Configured</h3>
                    <p style="margin:0 0 16px 0; color:var(--es-text-dim); font-size:0.82rem;">Create email forwarders to seamlessly redirect inbound messages to external addresses.</p>
                    <button onclick="window.mailServerInstance.openForwarderModal()" class="es-btn-primary">
                        <span>🔀</span> Add Forwarder
                    </button>
                </div>
            `;
        }

        return `
            <table class="es-table">
                <thead>
                    <tr>
                        <th style="width:40%;">Inbound Source Address</th>
                        <th style="width:40%;">Destination Relay Address</th>
                        <th style="width:10%;">Status</th>
                        <th style="width:10%; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${forwarders.map(f => `
                        <tr>
                            <td>
                                <strong style="color:var(--es-text-bright); font-size:0.88rem;">${f.source_email}</strong>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span style="color:var(--es-text-muted);">➔</span>
                                    <span style="color:var(--es-text-main); font-family:monospace; font-size:0.85rem;">${f.destination_email}</span>
                                </div>
                            </td>
                            <td>
                                <span class="es-badge green">Active</span>
                            </td>
                            <td style="text-align:right;">
                                <button onclick="window.mailServerInstance.deleteForwarder('${f.source_email}')" class="es-btn-ghost" style="color:var(--es-badge-red-text);" title="Remove forwarder">
                                    <span>🗑️</span> Delete
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    // =========================================================================
    // TAB 2: SPOOL & QUEUE (Zero-Scroll Layout)
    // =========================================================================

    renderQueueSpool() {
        const metrics = this.state.queueMetrics || {};
        const items = this.state.queueItems || [];
        const status = this.state.queueStatus || 'all';

        return `
            <!-- Ribbon HUD Metrics -->
            <div class="es-hud-ribbon">
                <div class="es-hud-chip">
                    <span class="es-hud-label">Total Outbound</span>
                    <span class="es-hud-val">${metrics.total || 0}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">Pending Dispatch</span>
                    <span class="es-hud-val" style="color:var(--es-badge-yellow-text);">${metrics.pending || 0}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">Delivered Sent</span>
                    <span class="es-hud-val" style="color:var(--es-badge-green-text);">${metrics.sent || 0}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">Failed Deliveries</span>
                    <span class="es-hud-val" style="color:var(--es-badge-red-text);">${metrics.failed || 0}</span>
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="es-action-bar">
                <div style="display:flex; align-items:center; gap:6px;">
                    <div style="display:flex; background:var(--es-panel-alt); padding:2px; border-radius:6px; border:1px solid var(--es-border);">
                        <button onclick="window.mailServerInstance.fetchQueue('all')" style="background:${status === 'all' ? 'var(--es-accent)' : 'transparent'}; color:${status === 'all' ? '#ffffff' : 'var(--es-text-dim)'}; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            All Spool
                        </button>
                        <button onclick="window.mailServerInstance.fetchQueue('pending')" style="background:${status === 'pending' ? 'var(--es-accent)' : 'transparent'}; color:${status === 'pending' ? '#ffffff' : 'var(--es-text-dim)'}; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            Pending (${metrics.pending || 0})
                        </button>
                        <button onclick="window.mailServerInstance.fetchQueue('sent')" style="background:${status === 'sent' ? 'var(--es-accent)' : 'transparent'}; color:${status === 'sent' ? '#ffffff' : 'var(--es-text-dim)'}; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            Sent (${metrics.sent || 0})
                        </button>
                        <button onclick="window.mailServerInstance.fetchQueue('failed')" style="background:${status === 'failed' ? 'var(--es-accent)' : 'transparent'}; color:${status === 'failed' ? '#ffffff' : 'var(--es-text-dim)'}; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                            Failed (${metrics.failed || 0})
                        </button>
                    </div>

                    <button onclick="window.mailServerInstance.fetchQueue('${status}')" class="es-btn-ghost" title="Refresh Spool">
                        <span>🔄</span> Refresh
                    </button>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <button onclick="window.mailServerInstance.flushQueue()" class="es-btn-primary" style="background:#10b981;">
                        <span>⚡</span> Flush Spool Now
                    </button>
                    ${(metrics.failed || 0) > 0 ? `
                        <button onclick="window.mailServerInstance.purgeQueue()" class="es-btn-ghost" style="color:var(--es-badge-red-text);">
                            <span>🧹</span> Purge Failed
                        </button>
                    ` : ''}
                </div>
            </div>

            <!-- Table Container -->
            <div class="es-table-card">
                <div class="es-table-scroll">
                    ${items.length === 0 ? `
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; text-align:center;">
                            <span style="font-size:2.5rem; margin-bottom:8px;">📬</span>
                            <h3 style="margin:0 0 4px 0; color:var(--es-text-bright); font-size:1.1rem;">Spool Queue is Clean</h3>
                            <p style="margin:0; color:var(--es-text-dim); font-size:0.82rem;">No messages currently queued for delivery.</p>
                        </div>
                    ` : `
                        <table class="es-table">
                            <thead>
                                <tr>
                                    <th style="width:8%;">ID</th>
                                    <th style="width:25%;">Recipient</th>
                                    <th style="width:30%;">Subject</th>
                                    <th style="width:12%;">Status</th>
                                    <th style="width:10%;">Attempts</th>
                                    <th style="width:15%; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.map(m => `
                                    <tr>
                                        <td style="font-family:monospace; font-size:0.78rem; color:var(--es-text-dim);">#${m.id}</td>
                                        <td><strong style="color:var(--es-text-bright);">${this.escapeHtml(m.recipient || '')}</strong></td>
                                        <td><span style="color:var(--es-text-main);">${this.escapeHtml(m.subject || '(no subject)')}</span></td>
                                        <td>
                                            <span class="es-badge ${m.status === 'sent' ? 'green' : m.status === 'pending' ? 'yellow' : 'red'}">
                                                ${m.status}
                                            </span>
                                        </td>
                                        <td><span style="font-size:0.8rem; color:var(--es-text-dim);">${m.attempts || 0} / 5</span></td>
                                        <td style="text-align:right;">
                                            ${m.status !== 'sent' ? `
                                                <button onclick="window.mailServerInstance.retryQueueItem(${m.id})" class="es-btn-ghost" title="Retry delivery now">
                                                    <span>🔄</span> Retry
                                                </button>
                                            ` : ''}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `}
                </div>
            </div>
        `;
    }

    // =========================================================================
    // TAB 3: ROUTING RULES (Zero-Scroll Layout)
    // =========================================================================

    renderRulesView() {
        const rules = this.state.rules || [];

        return `
            <!-- Ribbon HUD Metrics -->
            <div class="es-hud-ribbon">
                <div class="es-hud-chip">
                    <span class="es-hud-label">⚡ Automation Rules</span>
                    <span class="es-hud-val">${rules.length}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">Active Enabled</span>
                    <span class="es-hud-val" style="color:var(--es-badge-green-text);">${rules.filter(r => r.is_active).length}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">Matching Engine</span>
                    <span class="es-hud-val" style="font-size:0.95rem; font-weight:700;">Deterministic (&lt;1ms)</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">System Defaults</span>
                    <span class="es-hud-val" style="font-size:0.95rem; font-weight:700; color:var(--es-badge-blue-text);">Active</span>
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="es-action-bar">
                <span style="font-size:0.82rem; font-weight:600; color:var(--es-text-dim);">Inbound message inspection & routing rules</span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <button onclick="window.mailServerInstance.openRuleModal()" class="es-btn-primary">
                        <span>➕</span> Add New Rule
                    </button>
                </div>
            </div>

            <!-- Rules List Card -->
            <div class="es-table-card">
                <div class="es-table-scroll">
                    ${rules.length === 0 ? `
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; text-align:center;">
                            <span style="font-size:2.5rem; margin-bottom:8px;">⚡</span>
                            <h3 style="margin:0 0 4px 0; color:var(--es-text-bright); font-size:1.1rem;">No Custom Rules Configured</h3>
                            <p style="margin:0 0 16px 0; color:var(--es-text-dim); font-size:0.82rem;">Built-in heuristics (Urgent, Billing, Dev, Feed, Support) are active. Create custom rules to tailor inbound routing.</p>
                            <button onclick="window.mailServerInstance.openRuleModal()" class="es-btn-primary">
                                <span>➕</span> Create First Rule
                            </button>
                        </div>
                    ` : `
                        <table class="es-table">
                            <thead>
                                <tr>
                                    <th style="width:25%;">Rule Name</th>
                                    <th style="width:35%;">Conditions</th>
                                    <th style="width:20%;">Actions</th>
                                    <th style="width:10%;">Status</th>
                                    <th style="width:10%; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rules.map(r => {
                                    const b = r.rule || {};
                                    return `
                                        <tr>
                                            <td>
                                                <strong style="color:var(--es-text-bright); font-size:0.88rem;">${this.escapeHtml(r.name)}</strong>
                                                <div style="font-size:0.75rem; color:var(--es-text-dim);">Priority: ${r.priority || 10}</div>
                                            </td>
                                            <td>
                                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                                    ${(b.conditions || []).map(c => `
                                                        <span class="es-badge blue">${c.field} ${c.operator} "${this.escapeHtml(c.value)}"</span>
                                                    `).join('')}
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                                    ${(b.actions || []).map(a => `
                                                        <span class="es-badge yellow">${a.type}: ${this.escapeHtml(a.value || '')}</span>
                                                    `).join('')}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="es-badge ${r.is_active ? 'green' : 'red'}">
                                                    ${r.is_active ? 'Active' : 'Disabled'}
                                                </span>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:inline-flex; align-items:center; gap:5px;">
                                                    <button onclick="window.mailServerInstance.openRuleModal(${JSON.stringify(r).replace(/"/g, '&quot;')})" class="es-btn-ghost" title="Edit rule">
                                                        <span>✏️</span>
                                                    </button>
                                                    <button onclick="window.mailServerInstance.toggleRule(${r.id}, ${r.is_active})" class="es-btn-ghost" title="${r.is_active ? 'Disable' : 'Enable'} rule">
                                                        <span>${r.is_active ? '⏸️' : '▶️'}</span>
                                                    </button>
                                                    <button onclick="window.mailServerInstance.deleteRule(${r.id})" class="es-btn-ghost" style="color:var(--es-badge-red-text);" title="Delete rule">
                                                        <span>🗑️</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    `}
                </div>
            </div>
        `;
    }

    // =========================================================================
    // TAB 4: SCREENER & QUARANTINE (Zero-Scroll Layout)
    // =========================================================================

    renderScreenerView() {
        const screeners = this.state.screeners || [];

        return `
            <!-- Ribbon HUD Metrics -->
            <div class="es-hud-ribbon">
                <div class="es-hud-chip">
                    <span class="es-hud-label">🛡️ Screened Senders</span>
                    <span class="es-hud-val">${screeners.length}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">VIP Allowed</span>
                    <span class="es-hud-val" style="color:var(--es-badge-green-text);">${screeners.filter(s => s.action === 'accepted').length}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">Spam Blocked</span>
                    <span class="es-hud-val" style="color:var(--es-badge-red-text);">${screeners.filter(s => s.action === 'blocked').length}</span>
                </div>
                <div class="es-hud-chip">
                    <span class="es-hud-label">Pending Triage</span>
                    <span class="es-hud-val" style="color:var(--es-badge-yellow-text);">${screeners.filter(s => s.action === 'pending').length}</span>
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="es-action-bar">
                <span style="font-size:0.82rem; font-weight:600; color:var(--es-text-dim);">First-time sender triage & quarantine rules</span>
                <button onclick="const e = prompt('Enter sender email address to screen/whitelist/block:'); if(e) window.mailServerInstance.screenSender(e, 'accepted');" class="es-btn-primary">
                    <span>➕</span> Screen New Sender
                </button>
            </div>

            <!-- Table Container -->
            <div class="es-table-card">
                <div class="es-table-scroll">
                    ${screeners.length === 0 ? `
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; text-align:center;">
                            <span style="font-size:2.5rem; margin-bottom:8px;">🛡️</span>
                            <h3 style="margin:0 0 4px 0; color:var(--es-text-bright); font-size:1.1rem;">No Screened Senders</h3>
                            <p style="margin:0; color:var(--es-text-dim); font-size:0.82rem;">Unvetted inbound senders automatically appear here for approval or blocking.</p>
                        </div>
                    ` : `
                        <table class="es-table">
                            <thead>
                                <tr>
                                    <th style="width:35%;">Sender Email</th>
                                    <th style="width:25%;">Domain</th>
                                    <th style="width:20%;">Routing Decision</th>
                                    <th style="width:20%; text-align:right;">Change Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${screeners.map(s => `
                                    <tr>
                                        <td><strong style="color:var(--es-text-bright); font-size:0.88rem;">${this.escapeHtml(s.sender_email)}</strong></td>
                                        <td><span class="es-badge blue">${this.escapeHtml(s.domain || '')}</span></td>
                                        <td>
                                            <span class="es-badge ${s.action === 'accepted' ? 'green' : s.action === 'blocked' ? 'red' : 'yellow'}">
                                                ${s.action}
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <div style="display:inline-flex; align-items:center; gap:5px;">
                                                <button onclick="window.mailServerInstance.screenSender('${s.sender_email}', 'accepted')" class="es-btn-ghost" title="Allow sender to primary inbox">
                                                    <span>✅</span> Allow
                                                </button>
                                                <button onclick="window.mailServerInstance.screenSender('${s.sender_email}', 'blocked')" class="es-btn-ghost" style="color:var(--es-badge-red-text);" title="Block sender">
                                                    <span>🚫</span> Block
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `}
                </div>
            </div>
        `;
    }

    // =========================================================================
    // TAB 5: DELIVERABILITY & DKIM (Zero-Scroll 2-Column Split)
    // =========================================================================

    renderDeliverability() {
        return `
            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:12px; overflow:hidden;">
                <!-- Column 1: DKIM 2048-bit Keypair Generator -->
                <div class="es-table-card" style="padding:16px; overflow-y:auto;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span style="font-size:1.3rem;">🔑</span>
                        <div>
                            <h3 style="margin:0; font-size:1rem; font-weight:700; color:var(--es-text-bright);">RFC 6376 DKIM Signer</h3>
                            <div style="font-size:0.75rem; color:var(--es-text-dim);">Pure-PHP 2048-bit RSA Keypair Generator</div>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Hosted Domain</label>
                            <div style="display:flex; gap:6px;">
                                <select class="es-input" style="flex:1;" onchange="window.mailServerInstance.setState({deliverabilityDomain:this.value}); window.mailServerInstance.update();">
                                    ${(this.state.serverDomains || []).map(d => `<option value="${d}" ${this.state.deliverabilityDomain === d ? 'selected' : ''}>${d}</option>`).join('')}
                                </select>
                                <input type="text" class="es-input" style="flex:1;" placeholder="Custom domain..." value="${this.state.deliverabilityDomain}" oninput="window.mailServerInstance.setState({deliverabilityDomain:this.value})">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Selector</label>
                                <input type="text" class="es-input" style="width:100%;" value="${this.state.deliverabilitySelector}" oninput="window.mailServerInstance.setState({deliverabilitySelector:this.value})">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Key Length</label>
                                <select class="es-input" style="width:100%;" onchange="window.mailServerInstance.setState({dkimBits:parseInt(this.value)})">
                                    <option value="2048" ${this.state.dkimBits === 2048 ? 'selected' : ''}>2048-bit (Standard)</option>
                                    <option value="1024" ${this.state.dkimBits === 1024 ? 'selected' : ''}>1024-bit (Legacy)</option>
                                </select>
                            </div>
                        </div>
                        <button onclick="window.mailServerInstance.generateDkim()" class="es-btn-primary" style="justify-content:center;">
                            ${this.state.generatingDkim ? '⏳ Generating RSA Keypair...' : '🔑 Generate 2048-bit Keypair'}
                        </button>
                    </div>

                    ${this.state.dkimResult ? `
                        <div style="background:var(--es-panel-alt); padding:12px; border-radius:6px; border:1px solid var(--es-border);">
                            <div style="font-size:0.8rem; font-weight:700; color:var(--es-badge-green-text); margin-bottom:4px;">✅ Keypair Generated in var/mail/dkim/</div>
                            <div style="font-size:0.75rem; color:var(--es-text-dim); margin-bottom:6px;">DNS TXT Name: <code>${this.state.dkimResult.dns_record?.name || ''}</code></div>
                            <textarea readonly class="es-input" style="width:100%; font-family:monospace; font-size:0.72rem; height:80px;" rows="3">${this.state.dkimResult.dns_record?.value || ''}</textarea>
                        </div>
                    ` : ''}
                </div>

                <!-- Column 2: Live DNS Deliverability Auditor & Diagnostic Dispatcher -->
                <div class="es-table-card" style="padding:16px; overflow-y:auto; display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:1.3rem;">📡</span>
                                <h3 style="margin:0; font-size:1rem; font-weight:700; color:var(--es-text-bright);">DNS Alignment Inspector</h3>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <button onclick="window.mailServerInstance.runAudit()" class="es-btn-ghost" ${this.state.auditing ? 'disabled' : ''}>
                                    ${this.state.auditing ? '⏳ Auditing...' : '🔍 Audit'}
                                </button>
                                <button onclick="window.mailServerInstance.alignDnsRecords()" class="es-btn-primary" style="background:#10b981;" ${this.state.aligningDns ? 'disabled' : ''}>
                                    ${this.state.aligningDns ? '⏳ Aligning...' : '✨ 1-Click Align DNS'}
                                </button>
                            </div>
                        </div>
                        ${this.state.alignDnsResult ? `
                            <div style="padding:8px 10px; border-radius:6px; font-size:0.78rem; font-weight:600; background:${this.state.alignDnsResult.success ? 'var(--es-badge-green-bg)' : 'var(--es-badge-red-bg)'}; color:${this.state.alignDnsResult.success ? 'var(--es-badge-green-text)' : 'var(--es-badge-red-text)'}; border:1px solid currentColor;">
                                ${this.state.alignDnsResult.success ? '✅ DNS Records successfully written to local BIND zone!' : '❌ ' + (this.state.alignDnsResult.error || 'Alignment failed')}
                            </div>
                        ` : ''}
                    </div>

                    <!-- Diagnostic Email Dispatcher -->
                    <div style="border-top:1px solid var(--es-border); padding-top:12px;">
                        <h4 style="margin:0 0 8px 0; font-size:0.88rem; color:var(--es-text-bright);">✉️ Outbound Socket Delivery Probe</h4>
                        <div style="display:flex; gap:8px; margin-bottom:8px;">
                            <input type="email" class="es-input" style="flex:1;" placeholder="Test recipient email" value="${this.state.testRecipient}" oninput="window.mailServerInstance.setState({testRecipient:this.value})">
                            <button onclick="window.mailServerInstance.sendDiagnosticTest()" class="es-btn-primary" ${this.state.sendingTest ? 'disabled' : ''}>
                                ${this.state.sendingTest ? '⏳ Dispatching...' : '🚀 Test Send'}
                            </button>
                        </div>
                        ${this.state.testResult ? `
                            <div style="padding:8px 10px; border-radius:6px; font-size:0.75rem; background:var(--es-panel-alt); border:1px solid var(--es-border);">
                                <span style="font-weight:700; color:${this.state.testResult.success ? 'var(--es-badge-green-text)' : 'var(--es-badge-red-text)'};">
                                    ${this.state.testResult.success ? '✅ Dispatch Success!' : '❌ Dispatch Failed'}
                                </span>
                                <div style="font-family:monospace; margin-top:4px; color:var(--es-text-dim);">${JSON.stringify(this.state.testResult)}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    // =========================================================================
    // TAB 6: SERVER CONFIGURATION (Zero-Scroll 2-Column Split)
    // =========================================================================

    renderDiagnostics() {
        const s = this.state.smtpSettings || {};

        return `
            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:12px; overflow:hidden;">
                <!-- Column 1: SMTP & IMAP Listener Bindings -->
                <div class="es-table-card" style="padding:16px; overflow-y:auto;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span style="font-size:1.3rem;">⚙️</span>
                        <div>
                            <h3 style="margin:0; font-size:1rem; font-weight:700; color:var(--es-text-bright);">Socket Listener Settings</h3>
                            <div style="font-size:0.75rem; color:var(--es-text-dim);">Pure-PHP RFC 5321 SMTP & RFC 3501 IMAP4rev1</div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.73rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">SMTP Host</label>
                            <input type="text" class="es-input" style="width:100%;" value="${s.host || ''}" oninput="window.mailServerInstance.state.smtpSettings.host=this.value">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.73rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">SMTP Port</label>
                            <input type="number" class="es-input" style="width:100%;" value="${s.port || 587}" oninput="window.mailServerInstance.state.smtpSettings.port=parseInt(this.value)">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.73rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">From Email</label>
                            <input type="email" class="es-input" style="width:100%;" value="${s.from_email || ''}" oninput="window.mailServerInstance.state.smtpSettings.from_email=this.value">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.73rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">From Name</label>
                            <input type="text" class="es-input" style="width:100%;" value="${s.from_name || ''}" oninput="window.mailServerInstance.state.smtpSettings.from_name=this.value">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.73rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">SMTP Username</label>
                            <input type="text" class="es-input" style="width:100%;" value="${s.username || ''}" oninput="window.mailServerInstance.state.smtpSettings.username=this.value">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.73rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">SMTP Password</label>
                            <input type="password" class="es-input" style="width:100%;" value="${s.password || ''}" oninput="window.mailServerInstance.state.smtpSettings.password=this.value">
                        </div>
                    </div>

                    <button onclick="window.mailServerInstance.saveSettings()" class="es-btn-primary" style="justify-content:center; width:100%;">
                        ${this.state.savingSettings ? '⏳ Saving Server Configuration...' : '💾 Save Server Configuration'}
                    </button>
                </div>

                <!-- Column 2: Daemon Process Status & Health Probes -->
                <div class="es-table-card" style="padding:16px; overflow-y:auto; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:1.3rem;">🩺</span>
                        <h3 style="margin:0; font-size:1rem; font-weight:700; color:var(--es-text-bright);">Active Server Health Probes</h3>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div style="background:var(--es-panel-alt); padding:10px 12px; border-radius:6px; border:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong style="font-size:0.83rem; color:var(--es-text-bright);">RFC 5321 Socket Server</strong>
                                <div style="font-size:0.72rem; color:var(--es-text-dim);">Pure-PHP CLI daemon: php spp.php evaarta:listen</div>
                            </div>
                            <span class="es-badge green">Ready</span>
                        </div>
                        <div style="background:var(--es-panel-alt); padding:10px 12px; border-radius:6px; border:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong style="font-size:0.83rem; color:var(--es-text-bright);">Snooze Escalation Daemon</strong>
                                <div style="font-size:0.72rem; color:var(--es-text-dim);">php spp.php evaarta:process-snooze</div>
                            </div>
                            <span class="es-badge green">Operational</span>
                        </div>
                        <div style="background:var(--es-panel-alt); padding:10px 12px; border-radius:6px; border:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong style="font-size:0.83rem; color:var(--es-text-bright);">Local Maildir Storage Hierarchy</strong>
                                <div style="font-size:0.72rem; color:var(--es-text-dim);">var/mail/vhosts/ (cur, new, tmp)</div>
                            </div>
                            <span class="es-badge blue">Mounted</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // =========================================================================
    // MODAL DIALOGS
    // =========================================================================

    renderServerMailboxModal() {
        const form = this.state.newMailboxForm;
        const domains = this.state.serverDomains || [];

        return `
            <div style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; z-index:9999; padding:20px;">
                <div style="width:100%; max-width:480px; background:var(--es-panel); border:1px solid var(--es-border); border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,0.5); overflow:hidden;">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--es-text-bright);">➕ Provision Virtual Mailbox</h3>
                        <button onclick="window.mailServerInstance.closeCreateMailboxModal()" style="background:transparent; border:none; color:var(--es-text-dim); font-size:1.2rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:18px; display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Mailbox Address</label>
                            <div style="display:flex; gap:6px;">
                                <input type="text" class="es-input" style="flex:1;" placeholder="username" value="${this.escapeHtml(form.username)}" oninput="window.mailServerInstance.state.newMailboxForm.username=this.value">
                                <span style="display:flex; align-items:center; color:var(--es-text-dim);">@</span>
                                <select class="es-input" style="flex:1;" onchange="window.mailServerInstance.state.newMailboxForm.domain=this.value">
                                    ${domains.map(d => `<option value="${d}" ${form.domain === d ? 'selected' : ''}>${d}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Password</label>
                            <div style="display:flex; gap:6px;">
                                <input type="${this.state.showPasswordText ? 'text' : 'password'}" class="es-input" style="flex:1; font-family:monospace;" value="${this.escapeHtml(form.password)}" oninput="window.mailServerInstance.state.newMailboxForm.password=this.value">
                                <button type="button" class="es-btn-ghost" onclick="window.mailServerInstance.state.newMailboxForm.password=window.mailServerInstance.generateRandomPassword(); window.mailServerInstance.update();">
                                    🎲 Random
                                </button>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Quota (MB)</label>
                            <input type="number" class="es-input" style="width:100%;" value="${form.quota_mb}" oninput="window.mailServerInstance.state.newMailboxForm.quota_mb=parseInt(this.value)">
                        </div>
                    </div>
                    <div style="padding:12px 18px; border-top:1px solid var(--es-border); background:var(--es-panel-alt); display:flex; justify-content:flex-end; gap:8px;">
                        <button onclick="window.mailServerInstance.closeCreateMailboxModal()" class="es-btn-ghost">Cancel</button>
                        <button onclick="window.mailServerInstance.saveCreateMailbox()" class="es-btn-primary" ${this.state.serverActionLoading ? 'disabled' : ''}>
                            ${this.state.serverActionLoading ? 'Provisioning...' : 'Provision Mailbox'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderServerPasswordModal() {
        return `
            <div style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; z-index:9999; padding:20px;">
                <div style="width:100%; max-width:420px; background:var(--es-panel); border:1px solid var(--es-border); border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,0.5); overflow:hidden;">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--es-text-bright);">🔑 Reset Password</h3>
                        <button onclick="window.mailServerInstance.closePasswordModal()" style="background:transparent; border:none; color:var(--es-text-dim); font-size:1.2rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:18px; display:flex; flex-direction:column; gap:12px;">
                        <div style="font-size:0.83rem; color:var(--es-text-main);">Target Mailbox: <strong>${this.escapeHtml(this.state.targetMailboxEmail)}</strong></div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">New Password</label>
                            <div style="display:flex; gap:6px;">
                                <input type="${this.state.showPasswordText ? 'text' : 'password'}" class="es-input" style="flex:1; font-family:monospace;" value="${this.escapeHtml(this.state.newPasswordInput)}" oninput="window.mailServerInstance.setState({newPasswordInput:this.value})">
                                <button type="button" class="es-btn-ghost" onclick="window.mailServerInstance.setState({newPasswordInput:window.mailServerInstance.generateRandomPassword()}); window.mailServerInstance.update();">
                                    🎲 Random
                                </button>
                            </div>
                        </div>
                    </div>
                    <div style="padding:12px 18px; border-top:1px solid var(--es-border); background:var(--es-panel-alt); display:flex; justify-content:flex-end; gap:8px;">
                        <button onclick="window.mailServerInstance.closePasswordModal()" class="es-btn-ghost">Cancel</button>
                        <button onclick="window.mailServerInstance.savePassword()" class="es-btn-primary" ${this.state.serverActionLoading ? 'disabled' : ''}>
                            ${this.state.serverActionLoading ? 'Updating...' : 'Update Password'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderServerQuotaModal() {
        return `
            <div style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; z-index:9999; padding:20px;">
                <div style="width:100%; max-width:420px; background:var(--es-panel); border:1px solid var(--es-border); border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,0.5); overflow:hidden;">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--es-text-bright);">💾 Adjust Storage Quota</h3>
                        <button onclick="window.mailServerInstance.closeQuotaModal()" style="background:transparent; border:none; color:var(--es-text-dim); font-size:1.2rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:18px; display:flex; flex-direction:column; gap:12px;">
                        <div style="font-size:0.83rem; color:var(--es-text-main);">Target Mailbox: <strong>${this.escapeHtml(this.state.targetMailboxEmail)}</strong></div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Storage Quota (MB)</label>
                            <input type="number" class="es-input" style="width:100%;" value="${this.state.targetMailboxQuota}" oninput="window.mailServerInstance.setState({targetMailboxQuota:parseInt(this.value)})">
                        </div>
                    </div>
                    <div style="padding:12px 18px; border-top:1px solid var(--es-border); background:var(--es-panel-alt); display:flex; justify-content:flex-end; gap:8px;">
                        <button onclick="window.mailServerInstance.closeQuotaModal()" class="es-btn-ghost">Cancel</button>
                        <button onclick="window.mailServerInstance.saveQuota()" class="es-btn-primary" ${this.state.serverActionLoading ? 'disabled' : ''}>
                            ${this.state.serverActionLoading ? 'Saving...' : 'Update Quota'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderServerForwarderModal() {
        const form = this.state.newForwarderForm;

        return `
            <div style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; z-index:9999; padding:20px;">
                <div style="width:100%; max-width:440px; background:var(--es-panel); border:1px solid var(--es-border); border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,0.5); overflow:hidden;">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--es-text-bright);">🔀 Add Email Forwarder</h3>
                        <button onclick="window.mailServerInstance.closeForwarderModal()" style="background:transparent; border:none; color:var(--es-text-dim); font-size:1.2rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:18px; display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Source Email</label>
                            <input type="text" class="es-input" style="width:100%;" placeholder="e.g. info@domain.com" value="${this.escapeHtml(form.source_email || '')}" oninput="window.mailServerInstance.state.newForwarderForm.source_email=this.value">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Forward Destination</label>
                            <input type="email" class="es-input" style="width:100%;" placeholder="e.g. external.team@gmail.com" value="${this.escapeHtml(form.destination_email || '')}" oninput="window.mailServerInstance.state.newForwarderForm.destination_email=this.value">
                        </div>
                    </div>
                    <div style="padding:12px 18px; border-top:1px solid var(--es-border); background:var(--es-panel-alt); display:flex; justify-content:flex-end; gap:8px;">
                        <button onclick="window.mailServerInstance.closeForwarderModal()" class="es-btn-ghost">Cancel</button>
                        <button onclick="window.mailServerInstance.saveForwarder()" class="es-btn-primary" ${this.state.serverActionLoading ? 'disabled' : ''}>
                            ${this.state.serverActionLoading ? 'Creating...' : 'Create Forwarder'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderRuleModal() {
        const rule = this.state.editingRule;
        if (!rule) return '';

        return `
            <div style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; z-index:9999; padding:20px;">
                <div style="width:100%; max-width:540px; background:var(--es-panel); border:1px solid var(--es-border); border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,0.5); overflow:hidden;">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--es-text-bright);">⚡ ${rule.id ? 'Edit Automation Rule' : 'New Automation Rule'}</h3>
                        <button onclick="window.mailServerInstance.closeRuleModal()" style="background:transparent; border:none; color:var(--es-text-dim); font-size:1.2rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:18px; display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Rule Name</label>
                            <input type="text" class="es-input" style="width:100%;" value="${this.escapeHtml(rule.name)}" oninput="window.mailServerInstance.state.editingRule.name=this.value">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Condition: If Subject Contains</label>
                            <input type="text" class="es-input" style="width:100%;" value="${this.escapeHtml((rule.rule?.conditions && rule.rule?.conditions[0]?.value) || '')}" oninput="if(!window.mailServerInstance.state.editingRule.rule.conditions) window.mailServerInstance.state.editingRule.rule.conditions=[{field:'subject', operator:'contains', value:''}]; window.mailServerInstance.state.editingRule.rule.conditions[0].value=this.value">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--es-text-dim); margin-bottom:4px; text-transform:uppercase;">Action: Auto Tag</label>
                            <input type="text" class="es-input" style="width:100%;" value="${this.escapeHtml((rule.rule?.actions && rule.rule?.actions[0]?.value) || 'urgent')}" oninput="if(!window.mailServerInstance.state.editingRule.rule.actions) window.mailServerInstance.state.editingRule.rule.actions=[{type:'add_tag', value:''}]; window.mailServerInstance.state.editingRule.rule.actions[0].value=this.value">
                        </div>
                    </div>
                    <div style="padding:12px 18px; border-top:1px solid var(--es-border); background:var(--es-panel-alt); display:flex; justify-content:flex-end; gap:8px;">
                        <button onclick="window.mailServerInstance.closeRuleModal()" class="es-btn-ghost">Cancel</button>
                        <button onclick="window.mailServerInstance.saveEditingRule()" class="es-btn-primary">Save Rule</button>
                    </div>
                </div>
            </div>
        `;
    }

    renderDeviceConnectModal() {
        const email = this.state.deviceConnectEmail || '';
        const parts = email.split('@');
        const domain = parts[1] || ((this.state.serverDomains && this.state.serverDomains[0]) || ((window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com'));
        const mailHost = `mail.${domain}`;
        const activeTab = this.state.deviceConnectTab || 'apple';

        return `
            <div style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; z-index:9999; padding:20px;">
                <div style="width:100%; max-width:540px; background:var(--es-panel); border:1px solid var(--es-border); border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,0.5); overflow:hidden;">
                    <div style="padding:14px 18px; border-bottom:1px solid var(--es-border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--es-text-bright); display:flex; align-items:center; gap:8px;">
                            <span>📱</span> Connect Client Devices
                        </h3>
                        <button onclick="window.mailServerInstance.closeDeviceConnectModal()" style="background:transparent; border:none; color:var(--es-text-dim); font-size:1.2rem; cursor:pointer;">✕</button>
                    </div>

                    <!-- Subtabs: Apple / Outlook / Manual -->
                    <div style="display:flex; border-bottom:1px solid var(--es-border); background:var(--es-panel-alt); padding:0 14px;">
                        <button onclick="window.mailServerInstance.setState({deviceConnectTab:'apple'}); window.mailServerInstance.update();" 
                            style="padding:10px 14px; background:transparent; border:none; border-bottom:2px solid ${activeTab === 'apple' ? 'var(--es-accent)' : 'transparent'}; color:${activeTab === 'apple' ? 'var(--es-text-bright)' : 'var(--es-text-dim)'}; font-weight:700; font-size:0.82rem; cursor:pointer;">
                            🍏 Apple (iOS &amp; macOS)
                        </button>
                        <button onclick="window.mailServerInstance.setState({deviceConnectTab:'outlook'}); window.mailServerInstance.update();" 
                            style="padding:10px 14px; background:transparent; border:none; border-bottom:2px solid ${activeTab === 'outlook' ? 'var(--es-accent)' : 'transparent'}; color:${activeTab === 'outlook' ? 'var(--es-text-bright)' : 'var(--es-text-dim)'}; font-weight:700; font-size:0.82rem; cursor:pointer;">
                            📧 Outlook &amp; Thunderbird
                        </button>
                        <button onclick="window.mailServerInstance.setState({deviceConnectTab:'manual'}); window.mailServerInstance.update();" 
                            style="padding:10px 14px; background:transparent; border:none; border-bottom:2px solid ${activeTab === 'manual' ? 'var(--es-accent)' : 'transparent'}; color:${activeTab === 'manual' ? 'var(--es-text-bright)' : 'var(--es-text-dim)'}; font-weight:700; font-size:0.82rem; cursor:pointer;">
                            📋 Manual Settings
                        </button>
                    </div>

                    <div style="padding:18px;">
                        <div style="margin-bottom:12px; font-size:0.85rem; color:var(--es-text-main);">
                            Configuring: <strong style="color:var(--es-accent);">${this.escapeHtml(email)}</strong>
                        </div>

                        ${activeTab === 'apple' ? `
                            <div style="background:rgba(56,189,248,0.06); border:1px solid rgba(56,189,248,0.2); border-radius:8px; padding:14px; margin-bottom:14px;">
                                <div style="font-weight:700; color:#38bdf8; font-size:0.9rem; margin-bottom:6px;">1-Tap Auto-Configuration Profile</div>
                                <p style="font-size:0.8rem; color:var(--es-text-dim); margin:0 0 12px 0; line-height:1.5;">
                                    Download this verified Apple Configuration Profile (<code>.mobileconfig</code>) directly to your iPhone, iPad, or Mac. Open <strong>Settings &rarr; Profile Downloaded</strong> to instantly bind Mail with IMAP SSL &amp; SMTP TLS.
                                </p>
                                <button onclick="window.mailServerInstance.downloadDeviceConfig('${this.escapeHtml(email)}', 'mobileconfig')" class="es-btn-primary" style="width:100%; justify-content:center; padding:10px;">
                                    <span>📥</span> Download Apple .mobileconfig Profile
                                </button>
                            </div>
                        ` : ''}

                        ${activeTab === 'outlook' ? `
                            <div style="display:flex; flex-direction:column; gap:10px;">
                                <div style="background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.2); border-radius:8px; padding:12px;">
                                    <div style="font-weight:700; color:#34d399; font-size:0.85rem; margin-bottom:4px;">Microsoft Outlook Autodiscover XML</div>
                                    <p style="font-size:0.78rem; color:var(--es-text-dim); margin:0 0 8px 0;">Standard Exchange/Outlook autodiscover response schema for quick mailbox binding.</p>
                                    <button onclick="window.mailServerInstance.downloadDeviceConfig('${this.escapeHtml(email)}', 'outlook')" class="es-btn-ghost" style="width:100%; justify-content:center;">
                                        <span>📥</span> Download Outlook Autodiscover.xml
                                    </button>
                                </div>
                                <div style="background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.2); border-radius:8px; padding:12px;">
                                    <div style="font-weight:700; color:#818cf8; font-size:0.85rem; margin-bottom:4px;">Mozilla Thunderbird AutoConfig XML</div>
                                    <p style="font-size:0.78rem; color:var(--es-text-dim); margin:0 0 8px 0;">Thunderbird client configuration spec (v1.1) with encrypted TLS endpoints.</p>
                                    <button onclick="window.mailServerInstance.downloadDeviceConfig('${this.escapeHtml(email)}', 'thunderbird')" class="es-btn-ghost" style="width:100%; justify-content:center;">
                                        <span>📥</span> Download Thunderbird Config.xml
                                    </button>
                                </div>
                            </div>
                        ` : ''}

                        ${activeTab === 'manual' ? `
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.8rem;">
                                <div style="background:var(--es-panel-alt); border:1px solid var(--es-border); border-radius:8px; padding:10px;">
                                    <div style="font-weight:700; color:var(--es-text-bright); margin-bottom:6px;">📥 Incoming (IMAP)</div>
                                    <div style="color:var(--es-text-dim); line-height:1.6;">
                                        Server: <strong style="color:var(--es-text-bright);">${this.escapeHtml(mailHost)}</strong><br>
                                        Port: <strong style="color:var(--es-text-bright);">993</strong><br>
                                        Security: <strong>SSL / TLS</strong><br>
                                        Username: <strong>${this.escapeHtml(email)}</strong>
                                    </div>
                                </div>
                                <div style="background:var(--es-panel-alt); border:1px solid var(--es-border); border-radius:8px; padding:10px;">
                                    <div style="font-weight:700; color:var(--es-text-bright); margin-bottom:6px;">📤 Outgoing (SMTP)</div>
                                    <div style="color:var(--es-text-dim); line-height:1.6;">
                                        Server: <strong style="color:var(--es-text-bright);">${this.escapeHtml(mailHost)}</strong><br>
                                        Port: <strong style="color:var(--es-text-bright);">587</strong><br>
                                        Security: <strong>STARTTLS</strong><br>
                                        Username: <strong>${this.escapeHtml(email)}</strong>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>

                    <div style="padding:12px 18px; border-top:1px solid var(--es-border); background:var(--es-panel-alt); display:flex; justify-content:flex-end;">
                        <button onclick="window.mailServerInstance.closeDeviceConnectModal()" class="es-btn-ghost">Done</button>
                    </div>
                </div>
            </div>
        `;
    }
}
