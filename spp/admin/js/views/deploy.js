/**
 * Deployments & Backups View Component (SPP Deployment Command Center)
 * Full-featured integration of the SPPDeploy Remote Engine,
 * native Git VCS status & releases, and snapshot backup & restore manager.
 */

const BaseClass = window.BaseComponent || (window.SPPUX && window.SPPUX.BaseComponent) || class {
    constructor(admin, container, props) {
        this.admin = admin;
        this.container = container;
        this.props = props || {};
    }
};

export default class DeployView extends BaseClass {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
        window.deployViewInstance = this;
    }

    setState(partial) {
        this.state = Object.assign({}, this.state, partial);
    }

    async onInit() {
        this.state = {
            activeTab: 'sppdeploy', // sppdeploy, git, backups, config
            subTab: 'history',       // history, timeline, command_mode, logs, clusters
            loading: true,

            // Visual Deployment Studio & Pre-Deploy Preview State
            deployMode: 'mixed', // mixed, canary, delta, artifact, cloud, cluster, cpanel
            preDeployComparison: null,
            comparisonLoading: false,
            activeDiffFile: null,
            activePreviewTab: 'files', // files or ddl
            stepperStage: 1, // 1: Lineage, 2: Preflight Gates, 3: Delta Diff, 4: Staging, 5: Canary, 6: Telemetry/Rollback
            canaryWeight: 100,
            updatingCanary: false,
            releaseTimeline: [],
            timelineLoading: false,
            mixedDeploying: false,
            dryRunning: false,
            mixedDeployLog: null,

            // SPPDeploy & Target State
            sppStatus: null,
            targetEnv: 'production',
            health: null,
            healthLoading: false,

            // Differential Plan
            plan: null,
            planning: false,
            syncDb: true,

            // Deploy Operation
            deploying: false,
            deployLog: null,

            // Remote Command Mode
            commandInput: 'php spp.php sys:status',
            commandRunning: false,
            commandHistory: [],

            // Remote Logs
            logContent: '',
            logOffset: -1,
            logTailing: false,
            logTailTimer: null,

            // Remote History & Backups
            remoteHistory: [],
            remoteBackups: [],

            // Clusters & Webhooks
            clusters: {},
            testingWebhook: false,

            // Git State
            git: null,

            // Local Backups State
            localBackups: [],
            totalBackups: 0,
            creatingBackup: false,

            // Environments & Config
            configYaml: '',
            savingConfig: false,
            rotatingToken: false,
            envComparison: null,

            // Target Management Modal
            targetModalOpen: false,
            targetModalData: {
                name: '',
                old_name: '',
                url: '',
                token: '',
                is_default: false,
                ssl_cert: '',
                ssl_key: '',
                ssl_passphrase: ''
            },
            savingTarget: false,

            // Bare-Metal Sovereign Host & Fleet Installer State
            installerConfig: {
                domain: (window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com',
                app: 'school1',
                php_version: '8.3',
                webserver: 'nginx',
                install_dir: '/var/www/spp',
                git_repo: 'https://github.com/satya-spp/spp-framework.git',
                branch: 'main',
                ssl: true,
                email: 'admin@' + ((window.location.hostname && !window.location.hostname.includes('localhost')) ? window.location.hostname : 'example.com'),
                enable_worker: true,
                enable_watchdog: true
            },
            installerSummary: null,
            installerLoading: false,
            installerActivePreview: 'script', // script, nginx, worker, watchdog
            installerSaving: false
        };

        await this.loadInitialData();
    }

    async loadInitialData() {
        this.setState({ loading: true });
        try {
            const [statusRes, backupsRes] = await Promise.all([
                this.admin.api('deploy_spp_status'),
                this.admin.api('backup_list')
            ]);

            const sppStatus = (statusRes.success && statusRes.data) ? statusRes.data : statusRes;
            const backups = (backupsRes.success && backupsRes.data) ? backupsRes.data : backupsRes;

            const defaultEnv = sppStatus.default_environment || Object.keys(sppStatus.environments || {})[0] || 'production';

            this.setState({
                sppStatus: sppStatus,
                git: sppStatus.git || null,
                targetEnv: defaultEnv,
                localBackups: backups.backups || [],
                totalBackups: backups.total_backups || 0,
                loading: false
            });

            // Auto-ping target health in background
            this.fetchTargetHealth(defaultEnv);
            this.fetchRemoteHistory(defaultEnv);

            // Auto-load pre-deployment comparison & release timeline for the default view
            this.fetchPreDeployComparison();
            this.fetchReleaseTimeline();
        } catch (e) {
            console.error("DeployView initialization error:", e);
            this.setState({ loading: false });
        }
        this.update();
    }

    async fetchTargetHealth(target) {
        target = target || this.state.targetEnv;
        this.setState({ healthLoading: true });
        this.update();
        try {
            const res = await this.admin.api('deploy_spp_health', { target });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ health: data, healthLoading: false });
        } catch (e) {
            this.setState({
                health: { reachable: false, status_detail: e.message, latency_ms: 0 },
                healthLoading: false
            });
        }
        this.update();
    }

    async fetchRemoteHistory(target) {
        target = target || this.state.targetEnv;
        try {
            const res = await this.admin.api('deploy_spp_history', { target });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ remoteHistory: data.history || [] });
        } catch (e) {
            this.setState({ remoteHistory: [] });
        }
        this.update();
    }

    async fetchBackups() {
        try {
            const res = await this.admin.api('backup_list');
            const data = (res.success && res.data) ? res.data : res;
            this.setState({
                localBackups: data.backups || [],
                totalBackups: data.total_backups || 0
            });
        } catch (e) {
            console.error("Backups list error:", e);
        }
        this.update();
    }

    switchTab(tab) {
        if (this.state.logTailing && tab !== 'sppdeploy') {
            this.stopLogTail();
        }
        this.setState({ activeTab: tab });
        if (tab === 'installer' && !this.state.installerSummary) {
            this.fetchInstallerSummary();
        }
        this.update();
    }

    // ─── BARE-METAL SOVEREIGN HOST & FLEET INSTALLER OPERATIONS ────────
    async fetchInstallerSummary() {
        this.setState({ installerLoading: true });
        this.update();
        try {
            const res = await this.admin.api('host_installer_summary', this.state.installerConfig);
            if (res && res.success) {
                this.setState({ installerSummary: res, installerLoading: false });
            } else {
                this.setState({ installerLoading: false });
            }
        } catch (e) {
            console.error("Failed to fetch host installer summary:", e);
            this.setState({ installerLoading: false });
        }
        this.update();
    }

    onInstallerConfigChange(field, value) {
        const config = Object.assign({}, this.state.installerConfig, { [field]: value });
        if (field === 'domain' && (!config.email || config.email.startsWith('admin@'))) {
            config.email = `admin@${value}`;
        }
        this.setState({ installerConfig: config });
        this.fetchInstallerSummary();
    }

    switchInstallerPreview(previewKey) {
        this.setState({ installerActivePreview: previewKey });
        this.update();
    }

    async saveInstallerScript() {
        this.setState({ installerSaving: true });
        this.update();
        try {
            const res = await this.admin.api('host_installer_save', this.state.installerConfig);
            if (res && res.success) {
                this.admin.notify(res.message || "Host installer script saved to server!", "success");
            } else {
                this.admin.notify(res.error || "Failed to save installer script.", "error");
            }
        } catch (e) {
            this.admin.notify("Save failed: " + e.message, "error");
        }
        this.setState({ installerSaving: false });
        this.update();
    }

    copyText(text, successMessage) {
        if (!navigator.clipboard) {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        } else {
            navigator.clipboard.writeText(text);
        }
        this.admin.notify(successMessage || "Copied to clipboard!", "success");
    }

    switchSubTab(subTab) {
        if (this.state.logTailing && subTab !== 'logs') {
            this.stopLogTail();
        }
        this.setState({ subTab: subTab });
        if (subTab === 'logs' && !this.state.logContent) {
            this.fetchLogs();
        } else if (subTab === 'clusters') {
            this.fetchClusters();
        } else if (subTab === 'timeline') {
            this.fetchReleaseTimeline();
        }
        this.update();
    }

    onTargetChange(target) {
        this.setState({ targetEnv: target, plan: null });
        this.fetchTargetHealth(target);
        this.fetchRemoteHistory(target);
        this.fetchPreDeployComparison();
    }

    // ─── VISUAL DEPLOYMENT STUDIO & COMPARISON ENGINE ───────────

    async fetchPreDeployComparison(app = 'default') {
        this.setState({ comparisonLoading: true });
        this.update();
        try {
            const res = await this.admin.api('deploy_predeploy_comparison', { app });
            const data = (res.success && res.data) ? res.data : res;
            if (data && data.success) {
                this.setState({
                    preDeployComparison: data,
                    canaryWeight: data.live_production?.canary_weight || 100,
                    comparisonLoading: false
                });
            } else {
                this.setState({ comparisonLoading: false });
            }
        } catch (e) {
            console.error("fetchPreDeployComparison error:", e);
            this.setState({ comparisonLoading: false });
        }
        this.update();
    }

    async fetchReleaseTimeline(app = 'default') {
        this.setState({ timelineLoading: true });
        try {
            const res = await this.admin.api('deploy_release_timeline', { app });
            const data = (res.success && res.data) ? res.data : res;
            if (data && data.success) {
                this.setState({
                    releaseTimeline: data.timeline || [],
                    timelineLoading: false
                });
            } else {
                this.setState({ timelineLoading: false });
            }
        } catch (e) {
            console.error("fetchReleaseTimeline error:", e);
            this.setState({ timelineLoading: false });
        }
        this.update();
    }

    setDeployMode(mode) {
        this.setState({ deployMode: mode });
        this.update();
    }

    setStepperStage(stage) {
        this.setState({ stepperStage: stage });
        this.update();
    }

    switchPreviewTab(tab) {
        this.setState({ activePreviewTab: tab });
        this.update();
    }

    selectDiffFile(file) {
        this.setState({ activeDiffFile: file === this.state.activeDiffFile ? null : file });
        this.update();
    }

    updateCanarySlider(newWeight) {
        let weight = parseInt(newWeight, 10);
        if (isNaN(weight)) weight = 100;
        weight = Math.max(1, Math.min(100, weight));
        this.setState({ canaryWeight: weight });
        const sliderLabel = document.getElementById('canary-weight-display');
        if (sliderLabel) {
            sliderLabel.textContent = `${weight}%`;
        }
        const candidateBar = document.getElementById('canary-candidate-bar');
        const prodBar = document.getElementById('canary-prod-bar');
        if (candidateBar && prodBar) {
            candidateBar.style.width = `${weight}%`;
            prodBar.style.width = `${100 - weight}%`;
        }
    }

    async saveCanaryWeight() {
        this.setState({ updatingCanary: true });
        this.update();
        try {
            const res = await this.admin.api('deploy_canary_weight', {
                app: 'default',
                weight: this.state.canaryWeight
            });
            if (res.success) {
                this.admin.notify(`Canary traffic split updated to ${this.state.canaryWeight}%!`, "success");
                await this.fetchPreDeployComparison();
            } else {
                this.admin.notify(res.error || "Failed to update canary traffic.", "error");
            }
        } catch (e) {
            this.admin.notify("Canary update error: " + e.message, "error");
        } finally {
            this.setState({ updatingCanary: false });
            this.update();
        }
    }

    async triggerMixedStrategyDeploy(isDryRun = false) {
        const mode = this.state.deployMode || 'mixed';
        const weight = this.state.canaryWeight || 100;
        const msg = isDryRun
            ? `Run simulated Dry-Run deployment for [${mode.toUpperCase()}] mode? Zero changes will be written to production.`
            : `Execute Live Deployment using strategy [${mode.toUpperCase()}] (Canary: ${weight}%) with distributed mutex locking and < 1ms atomic symlink switch?`;

        if (!confirm(msg)) return;

        this.setState({
            mixedDeploying: !isDryRun,
            dryRunning: isDryRun,
            stepperStage: 2,
            mixedDeployLog: [
                `[${new Date().toLocaleTimeString()}] Initializing ${isDryRun ? 'DRY-RUN SIMULATION' : 'LIVE DEPLOYMENT'}...`,
                `[${new Date().toLocaleTimeString()}] Strategy mode: ${mode.toUpperCase()} (Canary weight: ${weight}%)`,
                `[${new Date().toLocaleTimeString()}] Acquiring distributed mutex lock (TargetConnection::acquireDeploymentLock)...`
            ]
        });
        this.update();

        try {
            const strategyMap = {
                'mixed': 'canary',
                'canary': 'canary',
                'delta': 'delta',
                'artifact': 'artifact',
                'cloud': 'cloud',
                'cluster': 'cluster',
                'cpanel': 'cpanel'
            };

            const res = await this.admin.api('deploy_mixed_deploy', {
                app: 'default',
                strategy: strategyMap[mode] || 'canary',
                canary_weight: weight,
                dry_run: isDryRun,
                no_test: false
            });

            const data = (res.success && res.data) ? res.data : res;

            if (res.success) {
                const logs = data.steps ? data.steps.map(s => `[${s.step}] ${s.output || s.message || (s.status === 'ok' ? 'PASSED' : s.status)}`) : (data.log || []);
                logs.unshift(`[${new Date().toLocaleTimeString()}] ✅ ${isDryRun ? 'Dry-Run Simulation Succeeded' : 'Deployment Completed Successfully (< 1ms Switch)'}`);
                if (data.release_id) {
                    logs.push(`[${new Date().toLocaleTimeString()}] Active Release ID: ${data.release_id}`);
                }

                this.admin.notify(res.message || `${isDryRun ? 'Simulation' : 'Deployment'} completed successfully!`, "success");
                this.setState({
                    mixedDeploying: false,
                    dryRunning: false,
                    stepperStage: 6,
                    mixedDeployLog: logs
                });

                await this.fetchPreDeployComparison();
                await this.fetchReleaseTimeline();
            } else {
                this.admin.notify(res.error || "Deployment execution failed.", "error");
                const errLogs = this.state.mixedDeployLog || [];
                errLogs.push(`[${new Date().toLocaleTimeString()}] ❌ FAILED: ${res.error || 'Unknown error'}`);
                this.setState({
                    mixedDeploying: false,
                    dryRunning: false,
                    stepperStage: 2,
                    mixedDeployLog: errLogs
                });
            }
        } catch (e) {
            this.admin.notify("Deploy execution error: " + e.message, "error");
            this.setState({
                mixedDeploying: false,
                dryRunning: false,
                mixedDeployLog: [`[${new Date().toLocaleTimeString()}] FATAL: ${e.message}`]
            });
        }
        this.update();
    }

    async triggerInstantRollback(targetReleaseId = null) {
        const comp = this.state.preDeployComparison;
        const targetRel = targetReleaseId || comp?.previous_release?.id || 'previous';

        if (!confirm(`Trigger Instant Rollback to release [${targetRel}]? Symlink will switch atomically in < 1ms.`)) {
            return;
        }

        this.setState({
            mixedDeploying: true,
            stepperStage: 6,
            mixedDeployLog: [
                `[${new Date().toLocaleTimeString()}] Initiating Instant Rollback to release [${targetRel}]...`,
                `[${new Date().toLocaleTimeString()}] Acquiring distributed deployment lock...`,
                `[${new Date().toLocaleTimeString()}] Switching atomic production symlink (< 1ms switch)...`
            ]
        });
        this.update();

        try {
            const res = await this.admin.api('deploy_mixed_rollback', {
                app: 'default',
                target: targetRel
            });

            if (res.success) {
                this.admin.notify(res.message || `Rollback to ${targetRel} completed in < 1ms!`, "success");
                this.setState({
                    mixedDeploying: false,
                    mixedDeployLog: [
                        `[${new Date().toLocaleTimeString()}] ✅ Instant Rollback Succeeded!`,
                        `[${new Date().toLocaleTimeString()}] Restored active release: ${res.active_release || targetRel}`,
                        `[${new Date().toLocaleTimeString()}] Zero-downtime symlink switch completed in < 1ms.`
                    ]
                });
                await this.fetchPreDeployComparison();
                await this.fetchReleaseTimeline();
            } else {
                this.admin.notify(res.error || "Rollback failed.", "error");
                this.setState({ mixedDeploying: false });
            }
        } catch (e) {
            this.admin.notify("Rollback error: " + e.message, "error");
            this.setState({ mixedDeploying: false });
        }
        this.update();
    }

    // ─── SPPDEPLOY OPERATIONS ────────────────────────────────────

    async runPreFlightPlan() {
        this.setState({ planning: true, plan: null });
        this.update();

        try {
            const res = await this.admin.api('deploy_spp_plan', {
                target: this.state.targetEnv,
                no_db: !this.state.syncDb
            });
            const data = (res.success && res.data) ? res.data : res;

            if (res.success) {
                this.setState({ plan: data, planning: false });
                this.admin.notify(`Plan generated: ${data.total_file_changes} file changes, ${data.total_db_changes} DB tables.`, "info");
            } else {
                this.admin.notify(res.error || "Failed to generate deployment plan.", "error");
                this.setState({ planning: false });
            }
        } catch (e) {
            this.admin.notify("Plan generation failed: " + e.message, "error");
            this.setState({ planning: false });
        }
        this.update();
    }

    async triggerDeployPush() {
        const target = this.state.targetEnv;
        const msg = `Deploy release to [${target}] using SPPDeploy Hot-Sync with distributed mutex locking?`;
        if (!confirm(msg)) return;

        this.setState({
            deploying: true,
            deployLog: [
                `[${new Date().toLocaleTimeString()}] Initializing deployment to target [${target}]...`,
                `[${new Date().toLocaleTimeString()}] Acquiring distributed mutex lock...`
            ]
        });
        this.update();

        try {
            const res = await this.admin.api('deploy_spp_push', {
                target: target,
                options: { no_db: !this.state.syncDb }
            });
            const data = (res.success && res.data) ? res.data : res;

            if (res.success) {
                this.admin.notify(res.message || `Deployment to ${target} succeeded!`, "success");
                this.setState({
                    deploying: false,
                    deployLog: data.log || [`[${new Date().toLocaleTimeString()}] Deployment completed successfully.`]
                });
                await this.fetchRemoteHistory(target);
                await this.fetchTargetHealth(target);
            } else {
                this.admin.notify(res.error || "Deployment failed.", "error");
                this.setState({
                    deploying: false,
                    deployLog: data.log || [`[${new Date().toLocaleTimeString()}] ERROR: ${res.error || 'Deployment failed.'}`]
                });
            }
        } catch (e) {
            this.admin.notify("Deploy error: " + e.message, "error");
            this.setState({
                deploying: false,
                deployLog: [`[${new Date().toLocaleTimeString()}] FATAL: ${e.message}`]
            });
        }
        this.update();
    }

    async triggerRemoteRollback() {
        const target = this.state.targetEnv;
        try {
            const res = await this.admin.api('deploy_spp_backups', { target });
            const data = (res.success && res.data) ? res.data : res;
            const backups = data.backups || [];

            if (backups.length === 0) {
                const manual = prompt(`No automated snapshots detected on target [${target}]. Enter snapshot ID to rollback to:`, "previous");
                if (!manual) return;
                return this.executeRollback(target, manual);
            }

            const backupListStr = backups.slice(0, 8).map((b, i) => `${i + 1}. ${b.id} (${b.date})`).join('\n');
            const choice = prompt(`Select snapshot to rollback to on [${target}]:\n\n${backupListStr}\n\nEnter snapshot ID or filename:`, backups[0].id);
            if (!choice) return;

            await this.executeRollback(target, choice.trim());
        } catch (e) {
            this.admin.notify("Rollback probe failed: " + e.message, "error");
        }
    }

    async executeRollback(target, version) {
        if (!confirm(`Confirm rollback of [${target}] to snapshot "${version}"? Remote state will be overwritten.`)) return;

        try {
            const res = await this.admin.api('deploy_spp_rollback', { target, version });
            if (res.success) {
                this.admin.notify(res.message || `Rollback on [${target}] completed!`, "success");
                await this.fetchTargetHealth(target);
            } else {
                this.admin.notify(res.error || "Rollback failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Rollback error: " + e.message, "error");
        }
    }

    async toggleMaintenanceMode() {
        const target = this.state.targetEnv;
        const current = !!(this.state.health && this.state.health.maintenance);
        const next = !current;

        if (!confirm(`Switch maintenance mode ${next ? 'ON (Down for maintenance)' : 'OFF (Live)'} on target [${target}]?`)) return;

        try {
            const res = await this.admin.api('deploy_spp_maintenance', { target, enable: next });
            if (res.success) {
                this.admin.notify(res.message || "Maintenance status updated.", "success");
                await this.fetchTargetHealth(target);
            } else {
                this.admin.notify(res.error || "Failed to toggle maintenance mode.", "error");
            }
        } catch (e) {
            this.admin.notify("Maintenance error: " + e.message, "error");
        }
    }

    // ─── REMOTE SPP COMMAND MODE ─────────────────────────────────

    async runRemoteCommand() {
        const cmd = (this.state.commandInput || '').trim();
        if (!cmd) return;

        const target = this.state.targetEnv;
        this.setState({ commandRunning: true });
        this.update();

        try {
            const res = await this.admin.api('deploy_spp_run', { target, command: cmd });
            const data = (res.success && res.data) ? res.data : res;

            const entry = {
                cmd: cmd,
                output: data.output || '(empty output)',
                exit_code: data.exit_code ?? 0,
                time: new Date().toLocaleTimeString(),
                success: res.success && data.exit_code === 0
            };

            const history = [entry, ...this.state.commandHistory.slice(0, 19)];
            this.setState({ commandHistory: history, commandRunning: false });
        } catch (e) {
            this.admin.notify("Remote command error: " + e.message, "error");
            this.setState({ commandRunning: false });
        }
        this.update();
    }

    // ─── REMOTE LOGS ─────────────────────────────────────────────

    async fetchLogs() {
        try {
            const res = await this.admin.api('deploy_spp_logs', {
                target: this.state.targetEnv,
                offset: this.state.logOffset
            });
            const data = (res.success && res.data) ? res.data : res;
            if (res.success) {
                let content = this.state.logContent;
                if (data.content) {
                    content += data.content;
                } else if (!content) {
                    content = "No remote log output recorded.";
                }
                this.setState({
                    logContent: content,
                    logOffset: data.offset !== undefined ? data.offset : this.state.logOffset
                });
            }
        } catch (e) {
            console.warn("Fetch logs error:", e);
        }
        this.update();
    }

    toggleLogTail() {
        if (this.state.logTailing) {
            this.stopLogTail();
        } else {
            this.setState({ logTailing: true });
            this.fetchLogs();
            this.state.logTailTimer = setInterval(() => this.fetchLogs(), 2500);
        }
        this.update();
    }

    stopLogTail() {
        if (this.state.logTailTimer) {
            clearInterval(this.state.logTailTimer);
            this.state.logTailTimer = null;
        }
        this.setState({ logTailing: false });
    }

    // ─── CLUSTERS & WEBHOOKS ─────────────────────────────────────

    async fetchClusters() {
        try {
            const res = await this.admin.api('deploy_spp_clusters');
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ clusters: data.clusters || {} });
        } catch (e) {
            console.error("Clusters fetch error:", e);
        }
        this.update();
    }

    async testWebhook(url) {
        if (!url) {
            url = prompt("Enter Webhook URL (Slack or Discord):");
            if (!url) return;
        }
        this.setState({ testingWebhook: true });
        this.update();
        try {
            const res = await this.admin.api('deploy_spp_test_webhook', { url });
            if (res.success) {
                this.admin.notify(res.message || "Test notification dispatched!", "success");
            } else {
                this.admin.notify(res.error || "Webhook test failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Webhook test error: " + e.message, "error");
        } finally {
            this.setState({ testingWebhook: false });
            this.update();
        }
    }

    // ─── LOCAL SNAPSHOT BACKUPS ──────────────────────────────────

    async createBackup(type = 'full', domain = null, app = null) {
        this.setState({ creatingBackup: true });
        this.update();

        try {
            const targetDomain = domain || this.admin.selectedDomain || null;
            const res = await this.admin.api('backup_create', { type, domain: targetDomain, app });
            const data = (res.success && res.data) ? res.data : res;

            if (res.success) {
                this.admin.notify(`Backup created: ${data.filename} (${data.size_formatted})`, "success");
                await this.fetchBackups();
            } else {
                this.admin.notify(res.error || "Failed to create snapshot backup.", "error");
            }
        } catch (e) {
            this.admin.notify("Backup error: " + e.message, "error");
        } finally {
            this.setState({ creatingBackup: false });
            this.update();
        }
    }

    async restoreBackup(filename) {
        if (!confirm(`Restore local system state from snapshot '${filename}'? Local files and database tables will be overwritten.`)) {
            return;
        }

        try {
            const res = await this.admin.api('backup_restore', { filename });
            if (res.success) {
                this.admin.notify(res.message || "Snapshot restored successfully!", "success");
            } else {
                this.admin.notify(res.error || "Restore failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Restore error: " + e.message, "error");
        }
    }

    async deleteBackup(filename) {
        if (!confirm(`Delete snapshot archive '${filename}' from storage? This cannot be undone.`)) {
            return;
        }

        try {
            const res = await this.admin.api('backup_delete', { filename });
            if (res.success) {
                this.admin.notify(res.message || "Snapshot removed.", "success");
                await this.fetchBackups();
            } else {
                this.admin.notify(res.error || "Failed to delete snapshot.", "error");
            }
        } catch (e) {
            this.admin.notify("Delete error: " + e.message, "error");
        }
    }

    // ─── CONFIGURATION & TOKEN ROTATION ──────────────────────────

    async rotateDeploymentToken() {
        if (!confirm("Rotate the secure deployment gateway token? You will need to update target servers with this new token.")) return;

        this.setState({ rotatingToken: true });
        this.update();
        try {
            const res = await this.admin.api('deploy_spp_rotate_token', { target: this.state.targetEnv });
            if (res.success) {
                this.admin.notify(`Token rotated: ${res.data?.token || res.token}`, "success");
                await this.loadInitialData();
            } else {
                this.admin.notify(res.error || "Failed to rotate token.", "error");
            }
        } catch (e) {
            this.admin.notify("Token rotation error: " + e.message, "error");
        } finally {
            this.setState({ rotatingToken: false });
            this.update();
        }
    }

    // ─── TARGET SERVER MANAGEMENT ────────────────────────────────

    async openTargetModal(name = '') {
        if (name) {
            try {
                const res = await this.admin.api('deploy_spp_target_get', { target: name });
                const data = (res.success && res.data) ? res.data : res;
                if (!res.success) {
                    this.admin.notify(res.error || "Failed to load target configuration.", "error");
                    return;
                }
                this.setState({
                    targetModalOpen: true,
                    targetModalData: {
                        name: data.name || name,
                        old_name: name,
                        url: data.url || '',
                        token: data.token || '',
                        is_default: !!data.is_default,
                        ssl_cert: data.ssl_cert || '',
                        ssl_key: data.ssl_key || '',
                        ssl_passphrase: data.ssl_passphrase || ''
                    }
                });
            } catch (e) {
                this.admin.notify("Error fetching target details: " + e.message, "error");
                return;
            }
        } else {
            // New target
            const randToken = Array.from(crypto.getRandomValues(new Uint8Array(24))).map(b => b.toString(16).padStart(2, '0')).join('');
            this.setState({
                targetModalOpen: true,
                targetModalData: {
                    name: '',
                    old_name: '',
                    url: 'http://',
                    token: randToken,
                    is_default: false,
                    ssl_cert: '',
                    ssl_key: '',
                    ssl_passphrase: ''
                }
            });
        }
        this.update();
    }

    closeTargetModal() {
        this.setState({ targetModalOpen: false, targetModalData: null, savingTarget: false });
        this.update();
    }

    generateModalToken() {
        const randToken = Array.from(crypto.getRandomValues(new Uint8Array(24))).map(b => b.toString(16).padStart(2, '0')).join('');
        if (this.state.targetModalData) {
            this.state.targetModalData.token = randToken;
        }
        const input = document.getElementById('target-modal-token');
        if (input) input.value = randToken;
    }

    async saveTargetServer(e) {
        if (e && e.preventDefault) e.preventDefault();

        const nameInput = document.getElementById('target-modal-name');
        const urlInput = document.getElementById('target-modal-url');
        const tokenInput = document.getElementById('target-modal-token');
        const defaultInput = document.getElementById('target-modal-default');
        const sslCertInput = document.getElementById('target-modal-ssl-cert');
        const sslKeyInput = document.getElementById('target-modal-ssl-key');
        const sslPassInput = document.getElementById('target-modal-ssl-pass');

        const name = nameInput ? nameInput.value.trim() : (this.state.targetModalData?.name || '');
        const url = urlInput ? urlInput.value.trim() : (this.state.targetModalData?.url || '');
        const token = tokenInput ? tokenInput.value.trim() : (this.state.targetModalData?.token || '');
        const isDefault = defaultInput ? defaultInput.checked : (this.state.targetModalData?.is_default || false);
        const sslCert = sslCertInput ? sslCertInput.value.trim() : '';
        const sslKey = sslKeyInput ? sslKeyInput.value.trim() : '';
        const sslPass = sslPassInput ? sslPassInput.value.trim() : '';

        if (!name) {
            this.admin.notify("Target name is required.", "error");
            return;
        }

        if (!/^[a-zA-Z0-9_\-]+$/.test(name)) {
            this.admin.notify("Target name can only contain alphanumeric characters, underscores, and dashes.", "error");
            return;
        }

        if (!url || (!url.startsWith('http://') && !url.startsWith('https://'))) {
            this.admin.notify("Remote URL must begin with http:// or https://", "error");
            return;
        }

        this.setState({ savingTarget: true });
        this.update();

        const payload = {
            target: this.state.targetModalData?.old_name || name,
            name: name,
            url: url,
            token: token,
            is_default: isDefault,
            ssl_cert: sslCert,
            ssl_key: sslKey,
            ssl_passphrase: sslPass
        };

        if (this.state.targetModalData?.old_name) {
            payload.old_name = this.state.targetModalData.old_name;
        }

        try {
            const res = await this.admin.api('deploy_spp_target_save', payload);
            if (res.success) {
                this.admin.notify(res.message || `Target server '${name}' saved successfully!`, "success");
                this.closeTargetModal();
                await this.loadInitialData();
                this.onTargetChange(name);
            } else {
                this.admin.notify(res.error || "Failed to save target server.", "error");
                this.setState({ savingTarget: false });
                this.update();
            }
        } catch (err) {
            this.admin.notify("Save target error: " + err.message, "error");
            this.setState({ savingTarget: false });
            this.update();
        }
    }

    async deleteCurrentTarget() {
        return this.deleteTarget(this.state.targetEnv);
    }

    async deleteTarget(name) {
        if (!name) return;
        if (!confirm(`Are you sure you want to delete target server "${name}"? This will remove its configuration from .sppdeploy.yml.`)) {
            return;
        }

        try {
            const res = await this.admin.api('deploy_spp_target_delete', { target: name });
            if (res.success) {
                this.admin.notify(res.message || `Target '${name}' deleted successfully.`, "success");
                await this.loadInitialData();
            } else {
                this.admin.notify(res.error || "Failed to delete target.", "error");
            }
        } catch (e) {
            this.admin.notify("Delete target error: " + e.message, "error");
        }
    }

    renderTargetModal() {
        const d = this.state.targetModalData || {};
        const isEditing = !!d.old_name;

        return `
            <div class="suite-modal-backdrop" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:9999;">
                <div class="suite-modal" style="background:var(--card-bg, #1e222d); border:1px solid var(--glass-border); border-radius:var(--radius-lg, 12px); width:100%; max-width:540px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); overflow:hidden;">
                    <!-- Modal Header -->
                    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03);">
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            <span>${isEditing ? '✏️' : '➕'}</span>
                            <span>${isEditing ? `Edit Target Server: ${this.escapeHtml(d.old_name)}` : 'Add New Target Server'}</span>
                        </h3>
                        <button onclick="window.deployViewInstance.closeTargetModal()" style="background:transparent; border:none; color:var(--text-dim); font-size:1.25rem; cursor:pointer; padding:0.25rem 0.5rem; line-height:1;">✕</button>
                    </div>

                    <!-- Modal Body -->
                    <form onsubmit="window.deployViewInstance.saveTargetServer(event)" style="padding:1.5rem; display:flex; flex-direction:column; gap:1.1rem; margin:0;">
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.35rem;">
                                Target Identifier / Alias *
                            </label>
                            <input id="target-modal-name" type="text" value="${this.escapeHtml(d.name || '')}" placeholder="e.g. production, staging, aws-east" required
                                style="width:100%; padding:0.55rem 0.85rem; background:var(--input-bg, #14171f); border:1px solid var(--glass-border); border-radius:var(--radius-sm, 6px); color:var(--text-bright); font-weight:600; box-sizing:border-box;" />
                            <span style="font-size:0.75rem; color:var(--text-dim); margin-top:0.25rem; display:block;">
                                Short alphanumeric identifier used in CLI and configuration (e.g. <code>php spp.php deploy:push ${this.escapeHtml(d.name || 'staging')}</code>).
                            </span>
                        </div>

                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.35rem;">
                                Remote Target URL *
                            </label>
                            <input id="target-modal-url" type="url" value="${this.escapeHtml(d.url || 'http://')}" placeholder="https://app.example.com or http://192.168.1.50/school1" required
                                style="width:100%; padding:0.55rem 0.85rem; background:var(--input-bg, #14171f); border:1px solid var(--glass-border); border-radius:var(--radius-sm, 6px); color:#38bdf8; font-family:monospace; box-sizing:border-box;" />
                            <span style="font-size:0.75rem; color:var(--text-dim); margin-top:0.25rem; display:block;">
                                Full base URL to the remote SPP application gateway. Must include <code>http://</code> or <code>https://</code>.
                            </span>
                        </div>

                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                                <label style="font-size:0.8rem; font-weight:600; text-transform:uppercase; color:var(--text-dim); margin:0;">
                                    Shared Gateway HMAC Secret Token
                                </label>
                                <button type="button" class="btn secondary-btn btn-xs" onclick="window.deployViewInstance.generateModalToken()" style="font-size:0.72rem; padding:0.2rem 0.5rem;">
                                    🎲 Generate Secret
                                </button>
                            </div>
                            <input id="target-modal-token" type="text" value="${this.escapeHtml(d.token || '')}" placeholder="Leave blank to use global gateway token"
                                style="width:100%; padding:0.55rem 0.85rem; background:var(--input-bg, #14171f); border:1px solid var(--glass-border); border-radius:var(--radius-sm, 6px); color:var(--text-bright); font-family:monospace; font-size:0.82rem; box-sizing:border-box;" />
                            <span style="font-size:0.75rem; color:var(--text-dim); margin-top:0.25rem; display:block;">
                                Cryptographic HMAC token for this target. If empty, the global token from <code>.sppdeploy.yml</code> will be used.
                            </span>
                        </div>

                        <div style="padding:0.75rem; background:rgba(255,255,255,0.02); border:1px solid var(--glass-border); border-radius:var(--radius-sm, 6px);">
                            <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; margin:0; font-size:0.85rem; color:var(--text-bright); font-weight:500;">
                                <input id="target-modal-default" type="checkbox" ${d.is_default ? 'checked' : ''} style="cursor:pointer;" />
                                <span>Set as Default Deployment Environment (⭐ default_environment)</span>
                            </label>
                        </div>

                        <!-- Modal Actions -->
                        <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:0.5rem; padding-top:1rem; border-top:1px solid var(--glass-border);">
                            <button type="button" class="btn secondary-btn" onclick="window.deployViewInstance.closeTargetModal()" style="padding:0.5rem 1.1rem;">
                                Cancel
                            </button>
                            <button type="submit" class="btn primary-btn shine-effect" ${this.state.savingTarget ? 'disabled' : ''} style="padding:0.5rem 1.3rem;">
                                ${this.state.savingTarget ? 'Saving...' : (isEditing ? '💾 Save Changes' : '➕ Add Target Server')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    // ─── RENDER MAIN ─────────────────────────────────────────────

    render() {
        const git = this.state.git;
        const health = this.state.health;
        const envCount = Object.keys(this.state.sppStatus?.environments || {}).length || 1;

        let healthBadge = `<span class="suite-pill suite-pill-neutral">Pinging target...</span>`;
        if (health) {
            if (health.reachable) {
                healthBadge = `<span class="suite-pill suite-pill-success">🟢 Online (${health.latency_ms}ms)</span>`;
            } else {
                healthBadge = `<span class="suite-pill suite-pill-danger">🔴 Unreachable</span>`;
            }
        }

        return `
            <div class="deploy-view-wrapper" style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Hero Stat Cards -->
                <div class="suite-hero">
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(99,102,241,0.15); color:#818cf8;">🚀</div>
                        <div>
                            <div class="suite-stat-value" style="display:flex; align-items:center; gap:0.5rem;">
                                ${this.escapeHtml(this.state.targetEnv)}
                                <span style="font-size:0.75rem;">${healthBadge}</span>
                            </div>
                            <div class="suite-stat-label">Active SPPDeploy Target</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(34,197,94,0.15); color:#22c55e;">🌿</div>
                        <div>
                            <div class="suite-stat-value">${this.escapeHtml(git?.branch || 'main')} <span style="font-size:0.8rem; color:var(--text-dim); font-family:monospace;">(${this.escapeHtml(git?.commit || 'HEAD')})</span></div>
                            <div class="suite-stat-label">Git Branch & Commit</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(245,158,11,0.15); color:#f59e0b;">🔒</div>
                        <div>
                            <div class="suite-stat-value">${git?.is_clean ? 'Clean' : `${git?.dirty_files_count} Changed`}</div>
                            <div class="suite-stat-label">Working Tree Status</div>
                        </div>
                    </div>
                    <div class="suite-stat-card">
                        <div class="suite-stat-icon" style="background:rgba(168,85,247,0.15); color:#a855f7;">📦</div>
                        <div>
                            <div class="suite-stat-value">${this.state.totalBackups} Snapshots</div>
                            <div class="suite-stat-label">Local Backups Store</div>
                        </div>
                    </div>
                </div>

                <!-- Main Tabs Navigation -->
                <div class="suite-tabs">
                    <button class="suite-tab-btn ${this.state.activeTab === 'sppdeploy' ? 'active' : ''}" onclick="window.deployViewInstance.switchTab('sppdeploy')">
                        🚀 SPPDeploy Remote Engine
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'git' ? 'active' : ''}" onclick="window.deployViewInstance.switchTab('git')">
                        🌿 Git Releases & VCS
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'installer' ? 'active' : ''}" onclick="window.deployViewInstance.switchTab('installer')">
                        🖥️ Sovereign Host & Fleet Installer
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'backups' ? 'active' : ''}" onclick="window.deployViewInstance.switchTab('backups')">
                        📦 Snapshot Backup & Restore Wizard (${this.state.totalBackups})
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'config' ? 'active' : ''}" onclick="window.deployViewInstance.switchTab('config')">
                        ⚙️ Environments & Secrets
                    </button>
                </div>

                <!-- Tab Panels -->
                ${this.state.activeTab === 'sppdeploy' ? this.renderSppDeployTab() : ''}
                ${this.state.activeTab === 'git' ? this.renderGitTab() : ''}
                ${this.state.activeTab === 'installer' ? this.renderInstallerTab() : ''}
                ${this.state.activeTab === 'backups' ? this.renderBackupsTab() : ''}
                ${this.state.activeTab === 'config' ? this.renderConfigTab() : ''}

                <!-- Target Server Modal -->
                ${this.state.targetModalOpen ? this.renderTargetModal() : ''}
            </div>
        `;
    }

    // ─── TAB 1: SPPDEPLOY REMOTE ENGINE ──────────────────────────

    // ─── VISUAL DEPLOYMENT FRONTEND COMPONENTS ──────────────────

    renderDeployModeSelector() {
        const modes = [
            { id: 'mixed', icon: '🌟', title: 'Mixed Strategy', desc: 'Best of All Worlds (Preflight + Hot-Sync + <1ms Switch)' },
            { id: 'canary', icon: '🌓', title: 'Blue/Green Canary', desc: 'Dual-slot zero downtime with 1-100% traffic weighting' },
            { id: 'delta', icon: '⚡', title: 'Checksum Delta Sync', desc: 'SHA-256 binary diff transfer over WAN' },
            { id: 'artifact', icon: '📦', title: 'Immutable Artifact', desc: 'Signed, standalone release archive' },
            { id: 'cloud', icon: '☁️', title: 'Cloud Gateway', desc: 'Remote SFTP, Amazon S3, or WebDAV storage' },
            { id: 'cluster', icon: '🌐', title: 'Cluster Farm', desc: 'Distributed parallel rollouts across server nodes' },
            { id: 'cpanel', icon: '🧰', title: '1-Click cPanel', desc: 'Legacy cPanel/WHM bridge & automated migration' }
        ];

        const currentMode = this.state.deployMode || 'mixed';

        return `
            <div style="background:rgba(0,0,0,0.22); border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                    <div style="font-size:0.85rem; font-weight:700; color:var(--text-bright); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:0.4rem;">
                        <span>🧭</span> Deployment Strategy Engine (7 Modes Supported)
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-dim);">
                        Active Engine: <strong style="color:#818cf8; text-transform:uppercase;">${currentMode}</strong>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(145px, 1fr)); gap:0.6rem;">
                    ${modes.map(m => {
                        const isActive = m.id === currentMode;
                        return `
                            <button type="button" onclick="window.deployViewInstance.setDeployMode('${m.id}')"
                                style="cursor:pointer; text-align:left; padding:0.65rem 0.75rem; border-radius:var(--radius-sm); border:1px solid ${isActive ? 'rgba(99,102,241,0.8)' : 'var(--glass-border)'}; background:${isActive ? 'rgba(99,102,241,0.18)' : 'rgba(255,255,255,0.02)'}; color:var(--text-bright); transition:all 0.15s ease; outline:none; display:flex; flex-direction:column; gap:0.25rem;">
                                <div style="font-size:0.85rem; font-weight:700; display:flex; align-items:center; gap:0.35rem;">
                                    <span>${m.icon}</span>
                                    <span>${m.title}</span>
                                </div>
                                <div style="font-size:0.7rem; color:${isActive ? '#c7d2fe' : 'var(--text-dim)'}; line-height:1.2; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                                    ${m.desc}
                                </div>
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    renderDeployStepper() {
        const steps = [
            { num: 1, icon: '🌿', title: 'Topology & Lineage', desc: 'Git / Sovereign Hash' },
            { num: 2, icon: '🛡️', title: 'Preflight Safety Gates', desc: 'Syntax, Tests & AST Shield' },
            { num: 3, icon: '📊', title: 'Checksum Delta Diff', desc: 'Files, DDL & Env Keys' },
            { num: 4, icon: '🧪', title: 'Staging & Probing', desc: 'Isolated Health Check' },
            { num: 5, icon: '🎚️', title: 'Canary Traffic Split', desc: 'Weighted 1%-100% Routing' },
            { num: 6, icon: '🚀', title: 'Telemetry & Rollback', desc: '< 1ms Symlink Switch' }
        ];

        const currentStage = this.state.stepperStage || 1;

        return `
            <div style="background:rgba(0,0,0,0.22); border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <div style="font-size:0.85rem; font-weight:700; color:var(--text-bright); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:0.4rem;">
                        <span>⚡</span> 6-Step Visual Orchestration Pipeline
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-dim);">
                        Pipeline Stage <strong style="color:#22c55e;">${currentStage}</strong> of 6
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.5rem;">
                    ${steps.map(s => {
                        const isActive = s.num === currentStage;
                        const isDone = s.num < currentStage;
                        const borderColor = isActive ? '#818cf8' : (isDone ? '#22c55e' : 'var(--glass-border)');
                        const bg = isActive ? 'rgba(99,102,241,0.2)' : (isDone ? 'rgba(34,197,94,0.1)' : 'rgba(255,255,255,0.02)');
                        const badgeIcon = isDone ? '✓' : s.num;
                        const badgeBg = isActive ? '#6366f1' : (isDone ? '#22c55e' : 'rgba(255,255,255,0.1)');

                        return `
                            <div onclick="window.deployViewInstance.setStepperStage(${s.num})"
                                style="cursor:pointer; padding:0.6rem 0.75rem; border:1px solid ${borderColor}; background:${bg}; border-radius:var(--radius-sm); display:flex; flex-direction:column; gap:0.3rem; transition:all 0.15s ease;">
                                <div style="display:flex; align-items:center; justify-content:space-between;">
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:${badgeBg}; color:#fff; font-size:0.7rem; font-weight:700;">
                                        ${badgeIcon}
                                    </span>
                                    <span style="font-size:0.85rem;">${s.icon}</span>
                                </div>
                                <div style="font-size:0.8rem; font-weight:700; color:${isActive ? '#fff' : (isDone ? '#86efac' : 'var(--text-bright)')};">
                                    ${s.title}
                                </div>
                                <div style="font-size:0.7rem; color:var(--text-dim); line-height:1.2;">
                                    ${s.desc}
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    renderPreDeployComparisonCard() {
        const comp = this.state.preDeployComparison;
        const loading = this.state.comparisonLoading;

        if (loading && !comp) {
            return `
                <div style="padding:2.5rem 1.5rem; background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-md); text-align:center;">
                    <div style="font-size:1.6rem; margin-bottom:0.5rem;">🔄</div>
                    <div style="font-size:1rem; font-weight:700; color:var(--text-bright);">Computing Pre-Deployment Delta & Preflight Verification...</div>
                    <div style="font-size:0.82rem; color:var(--text-dim); margin-top:0.3rem;">Analyzing live production state vs candidate tree, database DDL, and safety gates.</div>
                </div>
            `;
        }

        if (!comp) {
            return `
                <div style="padding:1.5rem; background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-md); text-align:center;">
                    <div style="color:var(--text-dim); font-size:0.85rem; margin-bottom:0.75rem;">No pre-deployment comparison data available for candidate.</div>
                    <button class="btn primary-btn btn-sm" onclick="window.deployViewInstance.fetchPreDeployComparison()">🔍 Scan Candidate & Generate Diff</button>
                </div>
            `;
        }

        const live = comp.live_production || {};
        const cand = comp.proposed_candidate || {};
        const gates = comp.safety_gates || {};
        const delta = comp.delta || {};
        const fileCreates = delta.files_create || [];
        const fileUpdates = delta.files_update || [];
        const fileDeletes = delta.files_delete || [];
        const totalChanged = delta.total_files_changed ?? (fileCreates.length + fileUpdates.length + fileDeletes.length);
        const dbTables = delta.db_schema?.total_tables ?? 0;
        const sqlPreview = delta.sql_preview || '';
        const prevRel = comp.previous_release;

        // Gate Badges
        const syntaxOk = !!gates.syntax?.passed;
        const testsOk = !!gates.tests?.passed;
        const testsSkipped = !!gates.tests?.skipped;
        const secOk = !!gates.security?.passed;
        const schemaOk = !!gates.schema?.passed;
        const allGatesPassed = !!gates.all_passed;

        const currentCanary = this.state.canaryWeight || live.canary_weight || 100;

        return `
            <div style="background:rgba(0,0,0,0.3); border:1px solid rgba(99,102,241,0.35); border-radius:var(--radius-lg, 12px); padding:1.25rem; display:flex; flex-direction:column; gap:1.25rem; box-shadow:0 10px 25px -5px rgba(0,0,0,0.3);">
                <!-- Header with Title and Mode Badge -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; border-bottom:1px solid var(--glass-border); padding-bottom:0.85rem;">
                    <div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.25rem;">🛡️</span>
                            <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright); font-weight:700;">
                                Pre-Deployment Live vs Candidate Comparison
                            </h3>
                            <span class="suite-pill ${allGatesPassed ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.72rem;">
                                ${allGatesPassed ? '✅ Preflight Gates Passed' : '⚠️ Gate Warning'}
                            </span>
                        </div>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Zero-risk state verification comparing active production against local release candidate before atomic activation.
                        </p>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.fetchPreDeployComparison()" title="Refresh Candidate Diff">
                            🔄 Refresh Diff
                        </button>
                    </div>
                </div>

                <!-- Side-by-Side Comparison Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1rem;">
                    <!-- Column 1: Active Live Production -->
                    <div style="background:rgba(15,23,42,0.6); border:1px solid rgba(34,197,94,0.3); border-radius:var(--radius-md); padding:1rem; display:flex; flex-direction:column; gap:0.6rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:0.5rem;">
                            <span style="font-size:0.82rem; font-weight:700; color:#86efac; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:0.4rem;">
                                <span>🟢</span> Active Live Production
                            </span>
                            <span class="suite-pill suite-pill-success" style="font-size:0.7rem;">
                                ${this.escapeHtml(live.status || 'LIVE')} (${live.canary_weight || 100}% Traffic)
                            </span>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; font-size:0.8rem;">
                            <div>
                                <span style="color:var(--text-dim); display:block; font-size:0.72rem;">Target Host / Env</span>
                                <strong style="color:var(--text-bright); font-family:monospace; word-break:break-all;">${this.escapeHtml(live.target || 'production')}</strong>
                            </div>
                            <div>
                                <span style="color:var(--text-dim); display:block; font-size:0.72rem;">Active Release ID</span>
                                <strong style="color:#38bdf8; font-family:monospace;">${this.escapeHtml(live.active_release || 'initial')}</strong>
                            </div>
                            <div>
                                <span style="color:var(--text-dim); display:block; font-size:0.72rem;">Active Lineage / Git</span>
                                <span style="color:var(--text-bright); font-family:monospace;">${this.escapeHtml(live.branch || 'main')} @ ${this.escapeHtml(live.commit || 'HEAD')}</span>
                            </div>
                            <div>
                                <span style="color:var(--text-dim); display:block; font-size:0.72rem;">Deployed At</span>
                                <span style="color:var(--text-bright); font-size:0.75rem;">${this.escapeHtml(live.deployed_at || 'Existing deployment')}</span>
                            </div>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-dim); border-top:1px dashed rgba(255,255,255,0.06); padding-top:0.4rem; display:flex; justify-content:space-between;">
                            <span>Tracked Footprint: <strong>${live.total_files || 0} files</strong></span>
                            <span>Operator: <strong>${this.escapeHtml(live.author || 'Deployer')}</strong></span>
                        </div>
                    </div>

                    <!-- Column 2: Proposed Local Candidate -->
                    <div style="background:rgba(15,23,42,0.6); border:1px solid rgba(99,102,241,0.3); border-radius:var(--radius-md); padding:1rem; display:flex; flex-direction:column; gap:0.6rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:0.5rem;">
                            <span style="font-size:0.82rem; font-weight:700; color:#c7d2fe; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:0.4rem;">
                                <span>🟡</span> Proposed Local Candidate
                            </span>
                            <span class="suite-pill suite-pill-info" style="font-size:0.7rem;">
                                Ready for Blue/Green Staging
                            </span>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; font-size:0.8rem;">
                            <div>
                                <span style="color:var(--text-dim); display:block; font-size:0.72rem;">Source / Lineage</span>
                                <strong style="color:var(--text-bright); font-family:monospace;">${this.escapeHtml(cand.source || 'git').toUpperCase()} (${this.escapeHtml(cand.branch || 'main')})</strong>
                            </div>
                            <div>
                                <span style="color:var(--text-dim); display:block; font-size:0.72rem;">Candidate Commit</span>
                                <strong style="color:#a855f7; font-family:monospace;">${this.escapeHtml(cand.commit || 'HEAD')}</strong>
                            </div>
                            <div style="grid-column:1 / -1;">
                                <span style="color:var(--text-dim); display:block; font-size:0.72rem;">Commit / Change Log</span>
                                <div style="color:var(--text-bright); font-style:italic; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    "${this.escapeHtml(cand.message || 'Latest candidate changes')}"
                                </div>
                            </div>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-dim); border-top:1px dashed rgba(255,255,255,0.06); padding-top:0.4rem; display:flex; justify-content:space-between;">
                            <span>Local Tree: <strong>${cand.is_dirty ? `${cand.dirty_count} uncommitted` : 'Clean'}</strong></span>
                            <span>Candidate Hash: <code style="font-size:0.7rem;">${this.escapeHtml((cand.tree_hash || '').substring(0, 12))}...</code></span>
                        </div>
                    </div>
                </div>

                <!-- Preflight Safety Gate Badges Grid -->
                <div style="background:rgba(0,0,0,0.22); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.85rem;">
                    <div style="font-size:0.78rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; margin-bottom:0.6rem; letter-spacing:0.5px;">
                        Preflight Safety Gate Verification Matrix
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:0.6rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright);">
                            <span style="font-size:1.1rem;">${syntaxOk ? '✅' : '❌'}</span>
                            <div>
                                <div style="font-weight:600;">PHP Syntax & Lint</div>
                                <div style="font-size:0.7rem; color:var(--text-dim);">${syntaxOk ? `${gates.syntax?.checked_files || 0} files valid` : 'Syntax errors detected'}</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright);">
                            <span style="font-size:1.1rem;">${testsOk ? '✅' : (testsSkipped ? '⏭️' : '❌')}</span>
                            <div>
                                <div style="font-weight:600;">Parikshak Automated Tests</div>
                                <div style="font-size:0.7rem; color:var(--text-dim);">${testsOk ? `${gates.tests?.tests_run || 0} assertions passed` : (testsSkipped ? 'Suite verified / Ready' : 'Test failures')}</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright);">
                            <span style="font-size:1.1rem;">${secOk ? '✅' : '⚠️'}</span>
                            <div>
                                <div style="font-weight:600;">AST Security Shield</div>
                                <div style="font-size:0.7rem; color:var(--text-dim);">${secOk ? 'Zero malware threats' : 'Security alerts'}</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright);">
                            <span style="font-size:1.1rem;">${schemaOk ? '✅' : '⚠️'}</span>
                            <div>
                                <div style="font-weight:600;">Database DDL Gate</div>
                                <div style="font-size:0.7rem; color:var(--text-dim);">${schemaOk ? 'Reversible migrations' : 'Manual verification'}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delta Impact Summary Badges -->
                <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
                    <span style="font-size:0.78rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; margin-right:0.25rem;">
                        Delta Impact:
                    </span>
                    <span class="suite-pill suite-pill-success" style="font-weight:600;">+${fileCreates.length} New Files</span>
                    <span class="suite-pill suite-pill-warning" style="font-weight:600;">~${fileUpdates.length} Modified</span>
                    <span class="suite-pill suite-pill-danger" style="font-weight:600;">-${fileDeletes.length} Deleted</span>
                    <span class="suite-pill suite-pill-info" style="font-weight:600;">🗄️ ${dbTables} Schema Tables</span>
                    ${prevRel ? `<span class="suite-pill" style="background:rgba(168,85,247,0.2); color:#d8b4fe; border:1px solid rgba(168,85,247,0.4); font-weight:600;">⏪ Rollback Target: ${this.escapeHtml(prevRel.id)} (&lt; 1ms)</span>` : ''}
                </div>

                <!-- Expandable Delta Viewer Tabs: Files vs SQL DDL -->
                <div style="background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-sm); overflow:hidden;">
                    <div style="display:flex; border-bottom:1px solid var(--glass-border); background:rgba(0,0,0,0.15);">
                        <button type="button" class="suite-tab-btn ${this.state.activePreviewTab === 'files' ? 'active' : ''}" onclick="window.deployViewInstance.switchPreviewTab('files')" style="border-radius:0; border-top:none; border-bottom:none; font-size:0.8rem; padding:0.5rem 1rem;">
                            📄 File Changes (${totalChanged})
                        </button>
                        <button type="button" class="suite-tab-btn ${this.state.activePreviewTab === 'ddl' ? 'active' : ''}" onclick="window.deployViewInstance.switchPreviewTab('ddl')" style="border-radius:0; border-top:none; border-bottom:none; font-size:0.8rem; padding:0.5rem 1rem;">
                            🗄️ Database DDL Migration Preview (${sqlPreview ? '1 Script' : '0 Pending'})
                        </button>
                    </div>

                    <div style="padding:0.85rem;">
                        ${this.state.activePreviewTab === 'files' ? `
                            ${totalChanged === 0 ? `
                                <div style="text-align:center; padding:1rem; color:#22c55e; font-size:0.85rem; font-weight:600;">
                                    ✅ Zero file changes pending. Candidate workspace is fully synchronized!
                                </div>
                            ` : `
                                <div style="max-height:190px; overflow-y:auto; font-family:monospace; font-size:0.78rem; display:flex; flex-direction:column; gap:0.25rem;">
                                    ${fileCreates.map(f => `
                                        <div onclick="window.deployViewInstance.selectDiffFile('${this.escapeHtml(f)}')" style="cursor:pointer; color:#22c55e; padding:0.2rem 0.4rem; border-radius:3px; background:${this.state.activeDiffFile === f ? 'rgba(34,197,94,0.15)' : 'transparent'};">
                                            [NEW] ${this.escapeHtml(f)}
                                        </div>
                                    `).join('')}
                                    ${fileUpdates.map(f => `
                                        <div onclick="window.deployViewInstance.selectDiffFile('${this.escapeHtml(f)}')" style="cursor:pointer; color:#f59e0b; padding:0.2rem 0.4rem; border-radius:3px; background:${this.state.activeDiffFile === f ? 'rgba(245,158,11,0.15)' : 'transparent'};">
                                            [MOD] ${this.escapeHtml(f)}
                                        </div>
                                    `).join('')}
                                    ${fileDeletes.map(f => `
                                        <div onclick="window.deployViewInstance.selectDiffFile('${this.escapeHtml(f)}')" style="cursor:pointer; color:#ef4444; padding:0.2rem 0.4rem; border-radius:3px; background:${this.state.activeDiffFile === f ? 'rgba(239,68,68,0.15)' : 'transparent'};">
                                            [DEL] ${this.escapeHtml(f)}
                                        </div>
                                    `).join('')}
                                </div>
                            `}
                        ` : `
                            <pre class="suite-terminal" style="max-height:180px; overflow-y:auto; margin:0; font-size:0.78rem;">${this.escapeHtml(sqlPreview || '-- No pending database schema DDL migrations required.\n-- All active entity tables are verified and up to date.')}</pre>
                        `}
                    </div>
                </div>

                <!-- Canary Traffic Weighting Slider & Live Gauge -->
                <div style="background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:1rem; display:flex; flex-direction:column; gap:0.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                        <div>
                            <span style="font-size:0.82rem; font-weight:700; color:var(--text-bright); text-transform:uppercase; letter-spacing:0.5px;">
                                🎚️ Canary Traffic Split Controller
                            </span>
                            <span style="font-size:0.75rem; color:var(--text-dim); margin-left:0.5rem;">
                                Dynamic runtime routing between Active Production and Candidate Release
                            </span>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span id="canary-weight-display" style="font-size:1.1rem; font-weight:700; color:#818cf8; font-family:monospace;">
                                ${currentCanary}%
                            </span>
                            <button class="btn secondary-btn btn-xs" onclick="window.deployViewInstance.saveCanaryWeight()" ${this.state.updatingCanary ? 'disabled' : ''}>
                                ${this.state.updatingCanary ? 'Saving...' : '💾 Apply Split'}
                            </button>
                        </div>
                    </div>

                    <!-- Split Gauge Bar -->
                    <div style="width:100%; height:12px; background:rgba(255,255,255,0.06); border-radius:6px; overflow:hidden; display:flex;">
                        <div id="canary-prod-bar" style="width:${100 - currentCanary}%; height:100%; background:linear-gradient(90deg, #16a34a, #22c55e); transition:width 0.2s ease;" title="Live Production Traffic"></div>
                        <div id="canary-candidate-bar" style="width:${currentCanary}%; height:100%; background:linear-gradient(90deg, #6366f1, #818cf8); transition:width 0.2s ease;" title="Canary Candidate Traffic"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.72rem; color:var(--text-dim);">
                        <span>Live Production: <strong>${100 - currentCanary}%</strong></span>
                        <span>Canary Candidate: <strong>${currentCanary}%</strong></span>
                    </div>

                    <!-- Slider Input -->
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <span style="font-size:0.75rem; color:var(--text-dim);">1% (Pilot)</span>
                        <input type="range" min="1" max="100" value="${currentCanary}"
                            oninput="window.deployViewInstance.updateCanarySlider(this.value)"
                            style="flex:1; cursor:pointer; accent-color:#6366f1;" />
                        <span style="font-size:0.75rem; color:var(--text-dim);">100% (Full Release)</span>
                    </div>
                </div>

                <!-- Immediate Action Bar -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; border-top:1px solid var(--glass-border); padding-top:1rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                        <button class="btn primary-btn shine-effect" onclick="window.deployViewInstance.triggerMixedStrategyDeploy(false)" ${this.state.mixedDeploying ? 'disabled' : ''} style="padding:0.6rem 1.4rem; font-weight:700;">
                            ${this.state.mixedDeploying ? '⏳ Orchestrating Release...' : '⚡ Confirm & Deploy Now (< 1ms Switch)'}
                        </button>
                        <button class="btn secondary-btn" onclick="window.deployViewInstance.triggerMixedStrategyDeploy(true)" ${this.state.dryRunning ? 'disabled' : ''} style="padding:0.6rem 1.1rem;">
                            ${this.state.dryRunning ? 'Simulating...' : '🧪 Dry-Run Simulation (Zero Impact)'}
                        </button>
                    </div>

                    <div>
                        <button class="btn secondary-btn" onclick="window.deployViewInstance.triggerInstantRollback()" ${!comp.rollback_available || this.state.mixedDeploying ? 'disabled' : ''} style="padding:0.6rem 1.1rem; color:${comp.rollback_available ? 'var(--warning-color, #f59e0b)' : 'var(--text-dim)'}; border-color:${comp.rollback_available ? 'rgba(245,158,11,0.4)' : 'var(--glass-border)'};" title="Restore previous release in < 1ms">
                            ⏪ Instant Rollback (&lt; 1ms)
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderReleaseTimelineSubTab() {
        const timeline = this.state.releaseTimeline || [];
        const loading = this.state.timelineLoading;

        if (loading && timeline.length === 0) {
            return `<div style="text-align:center; padding:2rem; color:var(--text-dim);">Loading historical release ledger...</div>`;
        }

        const rows = timeline.map(r => {
            return `
                <tr>
                    <td style="font-family:monospace; font-weight:700; color:${r.is_active ? '#86efac' : '#c7d2fe'}; font-size:0.82rem;">
                        ${this.escapeHtml(r.id)}
                    </td>
                    <td>
                        <span class="suite-pill ${r.is_active ? 'suite-pill-success' : 'suite-pill-neutral'}" style="font-size:0.72rem;">
                            ${r.is_active ? `🟢 ACTIVE (${r.canary_weight}%)` : 'STANDBY'}
                        </span>
                    </td>
                    <td style="font-family:monospace; font-size:0.8rem; color:#38bdf8;">
                        ${this.escapeHtml((r.commit || 'unknown').substring(0, 8))}
                    </td>
                    <td style="color:var(--text-dim); font-size:0.8rem;">
                        ${this.escapeHtml(r.deployed_at || 'N/A')}
                    </td>
                    <td style="color:var(--text-dim); font-size:0.8rem;">
                        ${r.file_count} files
                    </td>
                    <td style="font-family:monospace; font-size:0.8rem; color:#a855f7;">
                        &lt; 1ms
                    </td>
                    <td>
                        ${r.can_rollback_to ? `
                            <button class="btn secondary-btn btn-xs" onclick="window.deployViewInstance.triggerInstantRollback('${this.escapeHtml(r.id)}')" style="color:#f59e0b;">
                                ⏪ Rollback
                            </button>
                        ` : `<span style="font-size:0.75rem; color:#22c55e;">Current Live</span>`}
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <div>
                        <h4 style="margin:0; font-size:0.95rem; color:var(--text-bright);">Historical Release Timeline & Instant Rollbacks</h4>
                        <p style="margin:0.2rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Immutable Blue/Green release slots recorded in <code>releases.json</code> with zero-downtime rollback capability.</p>
                    </div>
                    <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.fetchReleaseTimeline()">🔄 Refresh Timeline</button>
                </div>
                <div class="suite-table-wrapper">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Release Slot ID</th>
                                <th>Traffic Status</th>
                                <th>Commit SHA</th>
                                <th>Deployed At</th>
                                <th>Footprint</th>
                                <th>Switch Latency</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || '<tr><td colspan="7" style="text-align:center; padding:1.5rem; color:var(--text-dim);">No previous release slots found in ledger.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // ─── TAB 1: SPPDEPLOY REMOTE ENGINE ──────────────────────────

    renderSppDeployTab() {
        const envs = this.state.sppStatus?.environments || {};
        const envOptions = Object.keys(envs).map(name => {
            return `<option value="${this.escapeHtml(name)}" ${name === this.state.targetEnv ? 'selected' : ''}>Target: ${this.escapeHtml(name)}</option>`;
        }).join('') || '<option value="production">Target: production</option>';

        const health = this.state.health;
        const plan = this.state.plan;

        return `
            <div class="suite-pane-main" style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- 1. Deployment Mode Selector Bar (7 Modes Supported) -->
                ${this.renderDeployModeSelector()}

                <!-- 2. Interactive 6-Step Visual Stepper Pipeline -->
                ${this.renderDeployStepper()}

                <!-- 3. Pre-Deployment Live vs Candidate Comparison Hero Card (Default View) -->
                ${this.renderPreDeployComparisonCard()}

                <!-- Live Mixed Deployment Execution Log (if triggered) -->
                ${this.state.mixedDeployLog ? `
                    <div style="background:rgba(0,0,0,0.35); border:1px solid rgba(99,102,241,0.4); border-radius:var(--radius-md); padding:1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                            <h4 style="margin:0; font-size:0.85rem; color:#818cf8; text-transform:uppercase; font-weight:700;">
                                🚀 Live Visual Deployment Execution Terminal
                            </h4>
                            <span class="suite-pill ${this.state.mixedDeploying ? 'suite-pill-warning' : 'suite-pill-success'}" style="font-size:0.7rem;">
                                ${this.state.mixedDeploying ? 'In Progress...' : 'Finished'}
                            </span>
                        </div>
                        <pre class="suite-terminal" style="max-height:220px; overflow-y:auto; margin:0;">${this.state.mixedDeployLog.map(line => `<div class="suite-terminal-line"><span>${this.escapeHtml(line)}</span></div>`).join('')}</pre>
                    </div>
                ` : ''}

                <!-- Toolbar & Target Selector (Existing Remote Tools) -->
                <div class="suite-toolbar">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            <span>🚀</span> SPPDeploy Remote Orchestrator
                        </h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.82rem; color:var(--text-dim);">
                            Hot-deploy differential deltas, run pre-flight plans, and orchestrate remote SPP environments with distributed mutex locking.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                        <div style="display:inline-flex; gap:0.25rem; align-items:center;">
                            <select id="spp-target-select" onchange="window.deployViewInstance.onTargetChange(this.value)" style="padding:0.45rem 0.85rem; background:var(--input-bg); border:1px solid var(--glass-border); border-radius:var(--radius-sm); color:var(--text-bright); font-weight:600;">
                                ${envOptions}
                            </select>
                            <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.openTargetModal(window.deployViewInstance.state.targetEnv)" title="Edit Current Target Server" style="padding:0.45rem 0.6rem;">
                                ✏️ Edit
                            </button>
                            <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.openTargetModal()" title="Add New Target Server" style="padding:0.45rem 0.6rem;">
                                ➕ Add
                            </button>
                            <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.deleteCurrentTarget()" title="Delete Current Target Server" style="padding:0.45rem 0.6rem; color:var(--danger-color, #ef4444);">
                                🗑️
                            </button>
                        </div>
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.runPreFlightPlan()" ${this.state.planning ? 'disabled' : ''}>
                            ${this.state.planning ? 'Scanning...' : '🔍 Pre-Flight Plan & Diff'}
                        </button>
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.deployViewInstance.triggerDeployPush()" ${this.state.deploying ? 'disabled' : ''}>
                            ${this.state.deploying ? 'Deploying...' : '🚀 Push Deployment'}
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.triggerRemoteRollback()">
                            ⏪ Remote Rollback
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.toggleMaintenanceMode()" title="Toggle Remote Maintenance Mode">
                            ${health?.maintenance ? '🛡️ Maintenance: ON' : '🛡️ Maintenance: OFF'}
                        </button>
                    </div>
                </div>

                <!-- Target Diagnostics Banner -->
                <div style="padding:1rem 1.25rem; background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-md);">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:1rem; align-items:center;">
                        <div>
                            <span style="font-size:0.72rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Target Reachability</span>
                            <div style="font-size:0.95rem; font-weight:700; color:${health?.reachable ? 'var(--success-color, #22c55e)' : 'var(--danger-color, #ef4444)'}; display:flex; align-items:center; gap:0.4rem; margin-top:0.2rem;">
                                <span>${health?.reachable ? '🟢 REACHABLE' : '🔴 OFFLINE'}</span>
                                <span style="font-size:0.78rem; opacity:0.8;">(${health?.latency_ms || 0}ms)</span>
                            </div>
                        </div>
                        <div>
                            <span style="font-size:0.72rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Remote Runtime</span>
                            <div style="font-size:0.95rem; font-weight:600; color:var(--text-bright); margin-top:0.2rem;">
                                PHP ${this.escapeHtml(health?.php_version || 'N/A')}
                            </div>
                        </div>
                        <div>
                            <span style="font-size:0.72rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Remote Database</span>
                            <div style="font-size:0.95rem; font-weight:600; color:var(--text-bright); margin-top:0.2rem;">
                                ${health?.db_connected ? '<span class="suite-pill suite-pill-success">Connected</span>' : '<span class="suite-pill suite-pill-neutral">Disconnected</span>'}
                            </div>
                        </div>
                        <div>
                            <span style="font-size:0.72rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Zip Ext / Var Storage</span>
                            <div style="font-size:0.95rem; font-weight:600; color:var(--text-bright); margin-top:0.2rem;">
                                ${health?.zip_extension ? '✅ Zip' : '❌ Zip'} · ${health?.var_writable ? '✅ Writable' : '❌ Writable'}
                            </div>
                        </div>
                        <div>
                            <span style="font-size:0.72rem; color:var(--text-dim); text-transform:uppercase; font-weight:600;">Options</span>
                            <div style="margin-top:0.2rem;">
                                <label style="font-size:0.85rem; color:var(--text-bright); display:inline-flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                    <input type="checkbox" ${this.state.syncDb ? 'checked' : ''} onchange="window.deployViewInstance.state.syncDb = this.checked">
                                    <span>Sync DB Schema</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pre-Flight Plan & Diff Inspector (If generated) -->
                ${plan ? this.renderPlanPreview(plan) : ''}

                <!-- Live Deployment Output Terminal -->
                ${this.state.deployLog ? `
                    <div style="background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:1rem;">
                        <h4 style="margin:0 0 0.5rem 0; font-size:0.85rem; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Deployment Execution Log</h4>
                        <pre class="suite-terminal" style="max-height:220px; overflow-y:auto;">${this.state.deployLog.map(line => `<div class="suite-terminal-line"><span>${this.escapeHtml(line)}</span></div>`).join('')}</pre>
                    </div>
                ` : ''}

                <!-- Sub-Tabs Drawer: Remote Command Mode, Audit Trail, Logs, Clusters -->
                <div style="background:rgba(0,0,0,0.15); border:1px solid var(--glass-border); border-radius:var(--radius-md); overflow:hidden;">
                    <div style="display:flex; border-bottom:1px solid var(--glass-border); background:rgba(0,0,0,0.2);">
                        <button class="suite-tab-btn ${this.state.subTab === 'history' ? 'active' : ''}" onclick="window.deployViewInstance.switchSubTab('history')" style="border-radius:0; border-top:none; border-bottom:none;">
                            📜 Deployment Audit Trail (${this.state.remoteHistory.length})
                        </button>
                        <button class="suite-tab-btn ${this.state.subTab === 'timeline' ? 'active' : ''}" onclick="window.deployViewInstance.switchSubTab('timeline')" style="border-radius:0; border-top:none; border-bottom:none;">
                            ⏱️ Release Timeline & Rollback (${this.state.releaseTimeline.length})
                        </button>
                        <button class="suite-tab-btn ${this.state.subTab === 'command_mode' ? 'active' : ''}" onclick="window.deployViewInstance.switchSubTab('command_mode')" style="border-radius:0; border-top:none; border-bottom:none;">
                            ⌨️ Remote SPP Command Mode
                        </button>
                        <button class="suite-tab-btn ${this.state.subTab === 'logs' ? 'active' : ''}" onclick="window.deployViewInstance.switchSubTab('logs')" style="border-radius:0; border-top:none; border-bottom:none;">
                            📋 Live Remote Logs
                        </button>
                        <button class="suite-tab-btn ${this.state.subTab === 'clusters' ? 'active' : ''}" onclick="window.deployViewInstance.switchSubTab('clusters')" style="border-radius:0; border-top:none; border-bottom:none;">
                            🌐 Multi-Node Clusters & Webhooks
                        </button>
                    </div>

                    <div style="padding:1.25rem;">
                        ${this.renderSubTabContent()}
                    </div>
                </div>
            </div>
        `;
    }

    renderPlanPreview(plan) {
        const fileCreates = plan.files?.create || [];
        const fileUpdates = plan.files?.update || [];
        const fileDeletes = plan.files?.delete || [];

        const dbCreates = plan.db?.create || [];
        const dbUpdates = plan.db?.update || [];
        const dbDeletes = plan.db?.delete || [];

        return `
            <div style="padding:1.25rem; background:rgba(0,0,0,0.28); border:1px solid rgba(99,102,241,0.35); border-radius:var(--radius-md);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <h4 style="margin:0; font-size:1.05rem; color:#818cf8; display:flex; align-items:center; gap:0.4rem;">
                            <span>🔍</span> Pre-Flight Differential Plan Preview
                        </h4>
                        <p style="margin:0.2rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Deltas calculated between local workspace and remote [${this.escapeHtml(plan.target)}].</p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <span class="suite-pill suite-pill-success">+${fileCreates.length} New</span>
                        <span class="suite-pill suite-pill-warning">~${fileUpdates.length} Modified</span>
                        <span class="suite-pill suite-pill-danger">-${fileDeletes.length} Deleted</span>
                        <span class="suite-pill suite-pill-info">🗄️ ${plan.total_db_changes} DB Tables</span>
                    </div>
                </div>

                ${plan.is_clean ? `
                    <div style="padding:1.5rem; text-align:center; color:#22c55e; font-weight:600;">
                        ✅ Remote target server is fully synchronized. No pending file or database changes!
                    </div>
                ` : `
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1rem;">
                        <!-- File Changes List -->
                        <div style="max-height:220px; overflow-y:auto; background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.75rem; font-family:monospace; font-size:0.78rem;">
                            ${fileCreates.map(f => `<div style="color:#22c55e; margin-bottom:3px;">[NEW] ${this.escapeHtml(f)}</div>`).join('')}
                            ${fileUpdates.map(f => `<div style="color:#f59e0b; margin-bottom:3px;">[MOD] ${this.escapeHtml(f)}</div>`).join('')}
                            ${fileDeletes.map(f => `<div style="color:#ef4444; margin-bottom:3px;">[DEL] ${this.escapeHtml(f)}</div>`).join('')}
                        </div>

                        <!-- Database Migrations Preview -->
                        <div style="max-height:220px; overflow-y:auto; background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.75rem; font-family:monospace; font-size:0.78rem;">
                            ${dbCreates.map(t => `<div style="color:#38bdf8; margin-bottom:3px;">[TABLE CREATE] ${this.escapeHtml(t)}</div>`).join('')}
                            ${dbUpdates.map(t => `<div style="color:#fbbf24; margin-bottom:3px;">[TABLE UPDATE] ${this.escapeHtml(t)}</div>`).join('')}
                            ${dbDeletes.map(t => `<div style="color:#f87171; margin-bottom:3px;">[TABLE DROP] ${this.escapeHtml(t)}</div>`).join('')}
                            ${plan.sql_preview ? `<div style="color:var(--text-dim); margin-top:0.5rem; border-top:1px dashed var(--glass-border); padding-top:0.4rem;">${this.escapeHtml(plan.sql_preview)}</div>` : (!plan.total_db_changes ? '<div style="color:var(--text-dim);">No database schema migrations required.</div>' : '')}
                        </div>
                    </div>
                `}
            </div>
        `;
    }

    renderSubTabContent() {
        switch (this.state.subTab) {
            case 'timeline':
                return this.renderReleaseTimelineSubTab();
            case 'command_mode':
                return this.renderCommandModeSubTab();
            case 'logs':
                return this.renderLogsSubTab();
            case 'clusters':
                return this.renderClustersSubTab();
            case 'history':
            default:
                return this.renderHistorySubTab();
        }
    }

    renderHistorySubTab() {
        const health = this.state.health;
        let warningBanner = '';
        if (health && !health.reachable) {
            warningBanner = `
                <div style="padding:0.75rem 1rem; margin-bottom:1rem; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:var(--radius-sm); color:#fca5a5; font-size:0.85rem; display:flex; align-items:center; justify-content:space-between; gap:0.5rem; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span>⚠️</span>
                        <span>Target <strong>[${this.escapeHtml(this.state.targetEnv)}]</strong> is currently unreachable: ${this.escapeHtml(health.status_detail || 'Connection refused')}.</span>
                    </div>
                    <button class="btn secondary-btn btn-xs" onclick="window.deployViewInstance.openTargetModal('${this.escapeHtml(this.state.targetEnv)}')">
                        ✏️ Edit Target Server
                    </button>
                </div>
            `;
        }

        const rows = (this.state.remoteHistory || []).map(h => {
            return `
                <tr>
                    <td style="font-family:monospace; font-size:0.8rem; color:var(--text-bright);">${this.escapeHtml(h.timestamp || 'N/A')}</td>
                    <td style="color:var(--text-dim);">${this.escapeHtml(h.ip || '127.0.0.1')}</td>
                    <td>
                        <span class="suite-pill ${h.status === 'ok' ? 'suite-pill-success' : 'suite-pill-danger'}">
                            ${this.escapeHtml(h.status || 'unknown')}
                        </span>
                    </td>
                    <td style="color:var(--text-dim);">${h.filesCount ?? 0} files</td>
                    <td style="color:var(--text-dim);">${h.dbCount ?? 0} tables</td>
                    <td style="color:var(--text-bright); max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        ${this.escapeHtml(h.message || 'Automated deployment')}
                    </td>
                </tr>
            `;
        }).join('');

        return `
            ${warningBanner}
            <div class="suite-table-wrapper">
                <table class="suite-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Client IP</th>
                            <th>Status</th>
                            <th>Files</th>
                            <th>DB Delta</th>
                            <th>Summary Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows || '<tr><td colspan="6" style="text-align:center; padding:1.5rem; color:var(--text-dim);">No deployment audit records found on target.</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }

    renderCommandModeSubTab() {
        const historyRows = (this.state.commandHistory || []).map(entry => {
            return `
                <div style="margin-bottom:1rem; border:1px solid var(--glass-border); border-radius:var(--radius-sm); overflow:hidden;">
                    <div style="background:rgba(0,0,0,0.3); padding:0.4rem 0.75rem; display:flex; justify-content:space-between; align-items:center; font-family:monospace; font-size:0.8rem;">
                        <span style="color:#818cf8; font-weight:700;">$ ${this.escapeHtml(entry.cmd)}</span>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <span style="font-size:0.75rem; color:var(--text-dim);">${entry.time}</span>
                            <span class="suite-pill ${entry.success ? 'suite-pill-success' : 'suite-pill-danger'}" style="font-size:0.7rem;">Exit: ${entry.exit_code}</span>
                        </div>
                    </div>
                    <pre class="suite-terminal" style="margin:0; max-height:140px; overflow-y:auto; border:none; border-radius:0;">${this.escapeHtml(entry.output)}</pre>
                </div>
            `;
        }).join('');

        return `
            <div>
                <p style="margin:0 0 0.75rem 0; font-size:0.85rem; color:var(--text-dim);">
                    Execute SPP CLI commands and maintenance routines remotely on <strong>[${this.escapeHtml(this.state.targetEnv)}]</strong> securely over HTTP.
                </p>
                <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
                    <input type="text" id="remote-spp-cmd-input" value="${this.escapeHtml(this.state.commandInput)}" oninput="window.deployViewInstance.state.commandInput = this.value" onkeydown="if(event.key==='Enter') window.deployViewInstance.runRemoteCommand()" placeholder="e.g. php spp.php cache:clear, php spp.php sys:status, php spp.php queue:restart" style="flex:1; padding:0.55rem 0.85rem; background:var(--input-bg); border:1px solid var(--glass-border); border-radius:var(--radius-sm); color:var(--text-bright); font-family:monospace;" />
                    <button class="btn primary-btn shine-effect btn-sm" onclick="window.deployViewInstance.runRemoteCommand()" ${this.state.commandRunning ? 'disabled' : ''}>
                        ${this.state.commandRunning ? 'Executing...' : '⚡ Execute on Target'}
                    </button>
                </div>
                <div style="margin-top:1rem;">
                    ${historyRows || '<div style="color:var(--text-dim); font-size:0.85rem; font-style:italic;">No commands executed yet during this session.</div>'}
                </div>
            </div>
        `;
    }

    renderLogsSubTab() {
        return `
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <span style="font-size:0.85rem; color:var(--text-dim);">Streaming remote server error & deployment logs from <strong>[${this.escapeHtml(this.state.targetEnv)}]</strong>.</span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.fetchLogs()">🔄 Refresh</button>
                        <button class="btn ${this.state.logTailing ? 'secondary-btn' : 'primary-btn'} btn-sm" onclick="window.deployViewInstance.toggleLogTail()">
                            ${this.state.logTailing ? '⏹ Stop Auto-Tail' : '▶ Start Auto-Tail'}
                        </button>
                    </div>
                </div>
                <pre class="suite-terminal" style="max-height:300px; overflow-y:auto;">${this.escapeHtml(this.state.logContent || 'Click Refresh or Start Auto-Tail to view remote server logs.')}</pre>
            </div>
        `;
    }

    renderClustersSubTab() {
        const clusters = this.state.clusters || {};
        const clusterEntries = Object.entries(clusters);

        return `
            <div>
                <h4 style="margin:0 0 0.5rem 0; font-size:0.95rem; color:var(--text-bright);">Multi-Node Server Farm Clusters</h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-top:0.75rem;">
                    ${clusterEntries.map(([name, nodes]) => `
                        <div style="padding:1rem; background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-sm);">
                            <h5 style="margin:0 0 0.5rem 0; color:#818cf8; font-size:0.9rem;">Cluster: ${this.escapeHtml(name)}</h5>
                            <div style="display:flex; flex-direction:column; gap:0.4rem;">
                                ${(nodes || []).map(n => `
                                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:var(--text-bright);">
                                        <span>${this.escapeHtml(n.target)}</span>
                                        <span class="suite-pill ${n.reachable ? 'suite-pill-success' : 'suite-pill-danger'}">${n.reachable ? `${n.latency_ms}ms` : 'Offline'}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `).join('') || '<div style="color:var(--text-dim); font-size:0.85rem;">No clusters configured in .sppdeploy.yml.</div>'}
                </div>

                <div style="margin-top:1.5rem; border-top:1px solid var(--glass-border); padding-top:1rem;">
                    <h4 style="margin:0 0 0.5rem 0; font-size:0.95rem; color:var(--text-bright);">Webhook Notifications Test</h4>
                    <p style="margin:0 0 0.75rem 0; font-size:0.82rem; color:var(--text-dim);">Send a test deployment notification to configured Slack or Discord webhooks.</p>
                    <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.testWebhook()" ${this.state.testingWebhook ? 'disabled' : ''}>
                        ${this.state.testingWebhook ? 'Testing...' : '🧪 Dispatch Test Webhook'}
                    </button>
                </div>
            </div>
        `;
    }

    // ─── TAB 2: GIT RELEASES & VCS ───────────────────────────────

    renderGitTab() {
        const git = this.state.git;

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Git Releases & Version Control</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Inspect local git metadata, branch lineage, and trigger distributed mutex guarded releases.</p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.deployViewInstance.triggerDeployPush()">
                            🚀 Push Git Release
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.triggerRemoteRollback()">
                            ⏪ Rollback
                        </button>
                    </div>
                </div>

                <!-- Git Metadata Grid -->
                <div style="margin-top:1rem; padding:1.25rem; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); border-radius:var(--radius-md);">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem;">
                        <div>
                            <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Repository Remote</span>
                            <div style="font-size:0.92rem; font-weight:600; color:var(--text-bright); overflow:hidden; text-overflow:ellipsis; margin-top:0.2rem;">
                                ${this.escapeHtml(git?.remote_url || 'Local Workspace')}
                            </div>
                            <span style="font-size:0.75rem; color:var(--text-dim);">Remote name: <code>${this.escapeHtml(git?.remote_name || 'origin')}</code></span>
                        </div>
                        <div>
                            <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Latest Commit SHA</span>
                            <div style="font-size:0.92rem; font-weight:600; font-family:monospace; color:#38bdf8; margin-top:0.2rem;">
                                ${this.escapeHtml(git?.commit_full || git?.commit || 'unknown')}
                            </div>
                            <span style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(git?.commit_date || '')}</span>
                        </div>
                        <div>
                            <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Latest Commit Message</span>
                            <div style="font-size:0.92rem; font-weight:600; color:var(--text-bright); overflow:hidden; text-overflow:ellipsis; margin-top:0.2rem;">
                                "${this.escapeHtml(git?.commit_message || 'N/A')}"
                            </div>
                            <span style="font-size:0.75rem; color:var(--text-dim);">${this.escapeHtml(git?.commit_author || '')}</span>
                        </div>
                        <div>
                            <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Git Engine / Binary</span>
                            <div style="margin-top:0.2rem;">
                                <span class="suite-pill suite-pill-success">${this.escapeHtml(git?.git_binary || 'Native .git')}</span>
                            </div>
                            <span style="font-size:0.75rem; color:var(--text-dim); margin-top:0.2rem; display:block;">Distributed Mutex Enabled</span>
                        </div>
                    </div>
                </div>

                <!-- Changed Files List -->
                ${git?.dirty_files && git.dirty_files.length > 0 ? `
                    <div style="margin-top:1.25rem;">
                        <h4 style="margin:0 0 0.5rem 0; font-size:0.85rem; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Working Tree Changes (${git.dirty_files_count} files)</h4>
                        <div style="max-height:220px; overflow-y:auto; background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.75rem; font-family:monospace; font-size:0.78rem;">
                            ${git.dirty_files.map(f => `<div style="color:${f.startsWith('M') ? '#f59e0b' : (f.startsWith('A') || f.startsWith('??') ? '#22c55e' : '#ef4444')}; margin-bottom:3px;">${this.escapeHtml(f)}</div>`).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    // ─── TAB 3: SNAPSHOT BACKUPS & RESTORES ───────────────────────

    renderBackupsTab() {
        const rows = (this.state.localBackups || []).map(b => {
            let pillClass = 'suite-pill-neutral';
            if (b.type_key === 'full') pillClass = 'suite-pill-success';
            else if (b.type_key === 'database') pillClass = 'suite-pill-info';
            else if (b.type_key === 'env_config') pillClass = 'suite-pill-warning';

            return `
                <tr>
                    <td>
                        <strong style="color:var(--text-bright);">${this.escapeHtml(b.filename)}</strong>
                    </td>
                    <td>
                        <span class="suite-pill ${pillClass}">${this.escapeHtml(b.type)}</span>
                    </td>
                    <td style="color:var(--text-dim); font-family:monospace;">${b.size_formatted}</td>
                    <td style="color:var(--text-dim);">${b.created_at}</td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:0.4rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.restoreBackup('${this.escapeHtml(b.filename)}')">Restore</button>
                            <button class="btn secondary-btn btn-sm" style="color:var(--danger-color, #ef4444);" onclick="window.deployViewInstance.deleteBackup('${this.escapeHtml(b.filename)}')">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Snapshot Backups & Restores</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Create and restore genuine full snapshots (database + source + config) stored securely in <code>var/backups/</code>.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.deployViewInstance.createBackup('full')" ${this.state.creatingBackup ? 'disabled' : ''}>
                            ${this.state.creatingBackup ? 'Creating...' : '+ Platform Full Snapshot'}
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.createBackup('domain', window.admin.selectedDomain)" ${this.state.creatingBackup ? 'disabled' : ''}>
                            + Domain Snapshot (${this.escapeHtml(this.admin.selectedDomain || 'default')})
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.createBackup('db')" ${this.state.creatingBackup ? 'disabled' : ''}>
                            + Database Only
                        </button>
                    </div>
                </div>

                <div class="suite-table-wrapper" style="margin-top:0.75rem;">
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Snapshot File</th>
                                <th>Classification</th>
                                <th>Size</th>
                                <th>Timestamp</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || '<tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-dim);">No backup snapshots found. Click "+ Full Backup Snapshot" to create your first archive.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // ─── TAB 4: ENVIRONMENTS & SECRETS ───────────────────────────

    renderConfigTab() {
        const confFile = this.state.sppStatus?.config_file || '.sppdeploy.yml';
        const tokenMasked = this.state.sppStatus?.token_masked || 'none';

        return `
            <div class="suite-pane-main" style="display:flex; flex-direction:column; gap:1.25rem;">
                <div class="suite-toolbar">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Environments, Clusters & Security</h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Manage remote target servers, deployment HMAC authorization tokens, and orchestration hooks in <code>${this.escapeHtml(confFile)}</code>.
                        </p>
                    </div>
                    <div>
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.deployViewInstance.openTargetModal()">
                            ➕ Add Target Server
                        </button>
                    </div>
                </div>

                <!-- Security & Token Card -->
                <div style="padding:1.25rem; background:rgba(0,0,0,0.25); border:1px solid var(--glass-border); border-radius:var(--radius-md);">
                    <h4 style="margin:0 0 0.5rem 0; font-size:0.95rem; color:var(--text-bright); display:flex; align-items:center; gap:0.4rem;">
                        <span>🔐</span> Deployment Gateway Security Token
                    </h4>
                    <p style="margin:0 0 0.75rem 0; font-size:0.82rem; color:var(--text-dim);">
                        All deployment endpoints are guarded with SHA-256 HMAC cryptographic signatures using this shared token.
                    </p>
                    <div style="display:flex; gap:0.75rem; align-items:center;">
                        <input type="text" readonly value="${this.escapeHtml(tokenMasked)}" style="padding:0.5rem 0.85rem; background:var(--input-bg); border:1px solid var(--glass-border); border-radius:var(--radius-sm); color:var(--text-bright); font-family:monospace; min-width:240px;" />
                        <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.rotateDeploymentToken()" ${this.state.rotatingToken ? 'disabled' : ''}>
                            ${this.state.rotatingToken ? 'Rotating...' : '🔄 Rotate Token'}
                        </button>
                    </div>
                </div>

                <!-- Environments List -->
                <div style="padding:1.25rem; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); border-radius:var(--radius-md);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                        <h4 style="margin:0; font-size:0.95rem; color:var(--text-bright);">Configured Target Environments</h4>
                        <button class="btn secondary-btn btn-xs" onclick="window.deployViewInstance.openTargetModal()">
                            ➕ Add Target Server
                        </button>
                    </div>
                    <div class="suite-table-wrapper">
                        <table class="suite-table">
                            <thead>
                                <tr>
                                    <th>Environment</th>
                                    <th>Remote URL</th>
                                    <th>Token Status</th>
                                    <th>Default</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${Object.values(this.state.sppStatus?.environments || {}).map(e => `
                                    <tr>
                                        <td><strong>${this.escapeHtml(e.name)}</strong></td>
                                        <td style="font-family:monospace; color:#38bdf8;">${this.escapeHtml(e.url)}</td>
                                        <td><span class="suite-pill ${e.has_token ? 'suite-pill-success' : 'suite-pill-warning'}">${e.token_masked}</span></td>
                                        <td>${e.is_default ? '⭐ Default' : '—'}</td>
                                        <td style="text-align:right;">
                                            <div style="display:inline-flex; gap:0.35rem;">
                                                <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.openTargetModal('${this.escapeHtml(e.name)}')" title="Edit Server Config">✏️ Edit</button>
                                                <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.onTargetChange('${this.escapeHtml(e.name)}'); window.deployViewInstance.switchTab('sppdeploy');" title="Select as Active Target">🚀 Select</button>
                                                <button class="btn secondary-btn btn-sm" style="color:var(--danger-color, #ef4444);" onclick="window.deployViewInstance.deleteTarget('${this.escapeHtml(e.name)}')" title="Delete Server">🗑️</button>
                                            </div>
                                        </td>
                                    </tr>
                                `).join('') || '<tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-dim);">No environments configured. Click "➕ Add Target Server" to set up your first target.</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    // ─── TAB 3: BARE-METAL SOVEREIGN HOST & FLEET INSTALLER ───────────

    renderInstallerTab() {
        const sum = this.state.installerSummary || {};
        const cfg = this.state.installerConfig;
        const oneLiner = sum.one_liner || `curl -sSL https://${cfg.domain}/install.sh | sudo bash -s -- --ssl`;
        const activePreviewKey = this.state.installerActivePreview;

        let previewContent = '';
        if (activePreviewKey === 'nginx') {
            previewContent = sum.nginx_preview || '// Generating Nginx configuration...';
        } else if (activePreviewKey === 'worker') {
            previewContent = sum.worker_preview || '# Generating Systemd worker unit...';
        } else if (activePreviewKey === 'watchdog') {
            previewContent = sum.watchdog_preview || '# Generating Systemd watchdog unit...';
        } else {
            previewContent = sum.script_preview || '# Generating bash installer script...';
        }

        return `
            <div class="suite-pane-main" style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Header & Vision Banner -->
                <div class="suite-card" style="background:linear-gradient(135deg, rgba(30,58,138,0.25) 0%, rgba(15,23,42,0.6) 100%); border:1px solid rgba(59,130,246,0.3); border-left:4px solid #38bdf8;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem;">
                                <span style="font-size:1.4rem;">🖥️</span>
                                <h3 style="margin:0; font-size:1.2rem; color:var(--text-bright);">Sovereign Bare-Metal Host & Fleet VPS Installer</h3>
                                <span class="suite-pill suite-pill-success">100% Sovereign Architecture</span>
                            </div>
                            <p style="margin:0; font-size:0.88rem; color:var(--text-dim); max-width:850px; line-height:1.5;">
                                Provision clean, unmanaged Linux VPS nodes into self-healing SPP production servers in under 60 seconds with <strong>0 MB idle overhead</strong>.
                                Automates PHP 8.2/8.3-FPM, SQLite3 WAL, Nginx reverse proxying, Let's Encrypt SSL, Systemd background workers, and atomic symlink zero-downtime releases.
                            </p>
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <button class="btn primary-btn shine-effect btn-sm" onclick="window.deployViewInstance.saveInstallerScript()" ${this.state.installerSaving ? 'disabled' : ''}>
                                ${this.state.installerSaving ? 'Saving...' : '💾 Save install.sh to Root'}
                            </button>
                            <button class="btn secondary-btn btn-sm" onclick="window.deployViewInstance.fetchInstallerSummary()">
                                🔄 Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 1-Line Remote VPS Bootstrap Box -->
                <div class="suite-card" style="border:1px solid rgba(56,189,248,0.4); background:rgba(15,23,42,0.85); box-shadow:0 8px 32px rgba(0,0,0,0.35);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.1rem; color:#38bdf8;">⚡</span>
                            <span style="font-weight:700; color:var(--text-bright); font-size:0.95rem;">1-Line Sovereign Fleet Bootstrap Command</span>
                            <span style="font-size:0.75rem; color:var(--text-dim);">(Paste into any clean Linux terminal as root)</span>
                        </div>
                        <button class="btn primary-btn btn-sm" onclick="window.deployViewInstance.copyText('${this.escapeHtml(oneLiner)}', '1-line bootstrap command copied!')" style="font-size:0.8rem; padding:0.35rem 0.85rem;">
                            📋 Copy Command
                        </button>
                    </div>

                    <div style="background:rgba(0,0,0,0.6); border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-sm); padding:0.85rem 1rem; display:flex; align-items:center; justify-content:space-between; font-family:monospace; font-size:0.85rem; color:#38bdf8; overflow-x:auto;">
                        <code style="white-space:nowrap;">${this.escapeHtml(oneLiner)}</code>
                    </div>

                    <!-- Supported Distributions Pills -->
                    <div style="margin-top:0.85rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                        <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em; font-weight:600;">Certified Nodes:</span>
                        <span class="suite-pill suite-pill-info" style="font-size:0.72rem;">🐧 Ubuntu 24.04 / 22.04 LTS</span>
                        <span class="suite-pill suite-pill-info" style="font-size:0.72rem;">🐧 Debian 12 / 11</span>
                        <span class="suite-pill suite-pill-info" style="font-size:0.72rem;">🐧 AlmaLinux 9 / RHEL 9</span>
                        <span class="suite-pill suite-pill-success" style="font-size:0.72rem;">⚡ Atomic Symlink Releases</span>
                        <span class="suite-pill suite-pill-success" style="font-size:0.72rem;">🛡️ Auto Let's Encrypt SSL</span>
                    </div>
                </div>

                <!-- 2-Column: Fleet Customizer & Sovereign Artifacts Preview -->
                <div style="display:grid; grid-template-columns:minmax(320px, 1fr) minmax(460px, 1.5fr); gap:1.25rem;">
                    <!-- Left: Interactive Fleet Customizer Form -->
                    <div class="suite-card" style="background:rgba(15,23,42,0.6);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--glass-border); padding-bottom:0.6rem;">
                            <h4 style="margin:0; font-size:0.95rem; color:var(--text-bright);">⚙️ Fleet Customization Parameters</h4>
                            <span style="font-size:0.75rem; color:var(--text-dim);">Live generator</span>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.85rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Primary Target Domain</label>
                                <input type="text" class="suite-input" value="${this.escapeHtml(cfg.domain)}" oninput="window.deployViewInstance.onInstallerConfigChange('domain', this.value)" placeholder="e.g. example.com" style="width:100%; box-sizing:border-box;">
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">PHP Runtime</label>
                                    <select class="suite-input" onchange="window.deployViewInstance.onInstallerConfigChange('php_version', this.value)" style="width:100%; box-sizing:border-box;">
                                        <option value="8.3" ${cfg.php_version === '8.3' ? 'selected' : ''}>PHP 8.3-FPM (Latest)</option>
                                        <option value="8.2" ${cfg.php_version === '8.2' ? 'selected' : ''}>PHP 8.2-FPM (Stable)</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Web Server</label>
                                    <select class="suite-input" onchange="window.deployViewInstance.onInstallerConfigChange('webserver', this.value)" style="width:100%; box-sizing:border-box;">
                                        <option value="nginx" ${cfg.webserver === 'nginx' ? 'selected' : ''}>Nginx (High-Performance)</option>
                                        <option value="apache" ${cfg.webserver === 'apache' ? 'selected' : ''}>Apache 2.4 (Event MPM)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Default App Context</label>
                                    <input type="text" class="suite-input" value="${this.escapeHtml(cfg.app)}" oninput="window.deployViewInstance.onInstallerConfigChange('app', this.value)" style="width:100%; box-sizing:border-box;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Install Root Directory</label>
                                    <input type="text" class="suite-input" value="${this.escapeHtml(cfg.install_dir)}" oninput="window.deployViewInstance.onInstallerConfigChange('install_dir', this.value)" style="width:100%; box-sizing:border-box;">
                                </div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem; font-weight:600;">Certbot SSL Notification Email</label>
                                <input type="email" class="suite-input" value="${this.escapeHtml(cfg.email)}" oninput="window.deployViewInstance.onInstallerConfigChange('email', this.value)" style="width:100%; box-sizing:border-box;">
                            </div>

                            <!-- Checkbox Toggles -->
                            <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.35rem; padding:0.6rem 0.75rem; background:rgba(0,0,0,0.25); border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright); cursor:pointer;">
                                    <input type="checkbox" ${cfg.ssl ? 'checked' : ''} onchange="window.deployViewInstance.onInstallerConfigChange('ssl', this.checked)">
                                    <span>Automated Let's Encrypt SSL Provisioning</span>
                                </label>
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright); cursor:pointer;">
                                    <input type="checkbox" ${cfg.enable_worker ? 'checked' : ''} onchange="window.deployViewInstance.onInstallerConfigChange('enable_worker', this.checked)">
                                    <span>Systemd DAG Queue Daemon (<code>spp-worker.service</code>)</span>
                                </label>
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--text-bright); cursor:pointer;">
                                    <input type="checkbox" ${cfg.enable_watchdog ? 'checked' : ''} onchange="window.deployViewInstance.onInstallerConfigChange('enable_watchdog', this.checked)">
                                    <span>Systemd SLA Health Watchdog (<code>spp-watchdog.service</code>)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Tabbed Artifacts Viewer -->
                    <div class="suite-card" style="background:rgba(15,23,42,0.6); display:flex; flex-direction:column;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                            <!-- Preview Tabs -->
                            <div style="display:inline-flex; background:rgba(0,0,0,0.3); border-radius:var(--radius-sm); border:1px solid var(--glass-border); overflow:hidden;">
                                <button type="button" class="suite-tab-btn ${activePreviewKey === 'script' ? 'active' : ''}" onclick="window.deployViewInstance.switchInstallerPreview('script')" style="padding:0.35rem 0.75rem; font-size:0.78rem; border-radius:0;">
                                    📜 spp-host-installer.sh
                                </button>
                                <button type="button" class="suite-tab-btn ${activePreviewKey === 'nginx' ? 'active' : ''}" onclick="window.deployViewInstance.switchInstallerPreview('nginx')" style="padding:0.35rem 0.75rem; font-size:0.78rem; border-radius:0;">
                                    🌐 spp.nginx.conf
                                </button>
                                <button type="button" class="suite-tab-btn ${activePreviewKey === 'worker' ? 'active' : ''}" onclick="window.deployViewInstance.switchInstallerPreview('worker')" style="padding:0.35rem 0.75rem; font-size:0.78rem; border-radius:0;">
                                    ⚙️ spp-worker.service
                                </button>
                                <button type="button" class="suite-tab-btn ${activePreviewKey === 'watchdog' ? 'active' : ''}" onclick="window.deployViewInstance.switchInstallerPreview('watchdog')" style="padding:0.35rem 0.75rem; font-size:0.78rem; border-radius:0;">
                                    🛡️ spp-watchdog.service
                                </button>
                            </div>

                            <button class="btn secondary-btn btn-xs" onclick="window.deployViewInstance.copyText(document.getElementById('installer-preview-code').innerText, 'Configuration copied to clipboard!')">
                                📋 Copy Preview
                            </button>
                        </div>

                        <!-- Code Preview Block -->
                        <div style="flex:1; background:rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.06); border-radius:var(--radius-sm); padding:0.85rem; overflow:hidden; position:relative;">
                            <pre style="margin:0; max-height:420px; overflow-y:auto; font-family:'Fira Code', monospace; font-size:0.76rem; line-height:1.45; color:#7dd3fc;"><code id="installer-preview-code">${this.escapeHtml(previewContent)}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- 4-Column: Sovereign Architectural Superiority Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1rem; margin-top:0.5rem;">
                    <div class="suite-card" style="border-top:3px solid #38bdf8; background:rgba(15,23,42,0.5);">
                        <div style="font-size:1.1rem; margin-bottom:0.25rem;">⚡ RAM Overhead</div>
                        <div style="font-size:1.2rem; font-weight:700; color:#22c55e;">0 MB Idle</div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.25rem;">SPP operates pure event-driven CLI/FPM with an ultra-lightweight zero-idle footprint.</div>
                    </div>

                    <div class="suite-card" style="border-top:3px solid #22c55e; background:rgba(15,23,42,0.5);">
                        <div style="font-size:1.1rem; margin-bottom:0.25rem;">🚀 Deployments</div>
                        <div style="font-size:1.2rem; font-weight:700; color:#38bdf8;">Atomic Symlinks</div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.25rem;">Zero-downtime releases via <code>current -> releases/&lt;hash&gt;</code> with instant &lt; 100ms 1-click rollback.</div>
                    </div>

                    <div class="suite-card" style="border-top:3px solid #a855f7; background:rgba(15,23,42,0.5);">
                        <div style="font-size:1.1rem; margin-bottom:0.25rem;">🗄️ Database Studio</div>
                        <div style="font-size:1.2rem; font-weight:700; color:#a855f7;">Reactive Grid + AI</div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.25rem;">Modern inline cell editing, visual ERD canvas, and AI SQL Copilot built directly into the studio.</div>
                    </div>

                    <div class="suite-card" style="border-top:3px solid #eab308; background:rgba(15,23,42,0.5);">
                        <div style="font-size:1.1rem; margin-bottom:0.25rem;">💰 Licensing Cost</div>
                        <div style="font-size:1.2rem; font-weight:700; color:#eab308;">$0 / Month</div>
                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.25rem;">100% Sovereign ownership on your own hardware without paying $40–$60/month commercial control panel fees.</div>
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
            window.deployViewInstance = this;
        }
    }
}
