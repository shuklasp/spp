export default class SPPReportBuilder extends BaseComponent {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.state = {
            schema: {},
            selectedTable: '',
            columns: [], // {field: '', aggregate: '', alias: ''}
            joins: [], // {table: '', type: 'LEFT JOIN', on: ''}
            allowedRoles: '',
            cronSchedule: '',
            cronEmail: '',
            cronFormat: 'html',
            webhookUrl: '',
            webhookCondition: '',
            filters: { logic: 'AND', conditions: [] },
            groupBy: [],
            orderBy: { field: '', direction: 'ASC' },
            limit: 100,
            previewData: null,
            sql: '',
            loading: true,
            error: null,
            savedReports: [],
            reportName: '',
            savedTemplates: [],
            selectedTemplate: '',
            templateName: '',
            templateHtml: '<div style="text-align:center;"><h2>{{ report_title }}</h2><p>Generated on {{ current_date }}</p></div><br>{{ data_table }}',
            showTemplateModal: false,
            showSettings: false,
            showTemplates: false,
            showSettingsModal: false,
            showPreviewModal: false,
            showVersions: false,
            versions: [],
            previewHtml: ''
        };
    }

    async onInit() {
        await this.fetchSchema();
        await this.fetchSavedReports();
        await this.fetchSavedTemplates();
    }

    async fetchSchema(force = false) {
        if (force) {
            this.state.loading = true;
            this.update();
        }
        try {
            let url = `${this.props.apiEndpoint}&report_action=schema`;
            if (this.state.externalDsn) {
                url += `&external_dsn=${encodeURIComponent(this.state.externalDsn)}`;
                if (this.state.externalUser) url += `&external_user=${encodeURIComponent(this.state.externalUser)}`;
                if (this.state.externalPass) url += `&external_pass=${encodeURIComponent(this.state.externalPass)}`;
            }
            const res = await fetch(url);
            const data = await res.json();
            if (data.status === 'success') {
                this.state.schema = data.schema;
                if (!this.state.selectedTable || force) {
                    this.state.selectedTable = Object.keys(data.schema)[0] || '';
                }
                this.state.error = null;
            } else {
                this.state.error = data.message;
            }
        } catch (err) {
            this.state.error = err.message;
        } finally {
            this.state.loading = false;
            this.update();
        }
    }

    async fetchSavedReports() {
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=list`);
            const data = await res.json();
            if (data.status === 'success') {
                this.state.savedReports = data.reports || [];
                this.update();
            }
        } catch (e) {
            console.error('Failed to fetch reports list', e);
        }
    }

    async fetchSavedTemplates() {
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=list_templates`);
            const data = await res.json();
            if (data.status === 'success') {
                this.state.savedTemplates = data.templates || [];
                this.update();
            }
        } catch (e) {
            console.error('Failed to fetch templates list', e);
        }
    }

    async loadReport(name) {
        if (!name) return;
        this.state.loading = true;
        this.update();
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=load&name=${encodeURIComponent(name)}`);
            const data = await res.json();
            if (data.status === 'success') {
                const cfg = data.config;
                this.state.selectedTable = cfg.table;
                this.state.columns = cfg.columns || [];
                this.state.joins = cfg.joins || [];
                this.state.allowedRoles = cfg.allowed_roles || '';
                this.state.cronSchedule = cfg.cron_schedule || '';
                this.state.cronEmail = cfg.cron_email || '';
                this.state.cronFormat = cfg.cron_format || 'html';
                this.state.webhookUrl = cfg.webhook_url || '';
                this.state.webhookCondition = cfg.webhook_condition || '';
                this.state.filters = cfg.filters || { logic: 'AND', conditions: [] };
                this.state.groupBy = cfg.group_by || [];
                this.state.orderBy = cfg.order_by || { field: '', direction: 'ASC' };
                this.state.limit = cfg.limit || 100;
                this.state.externalDsn = cfg.external_dsn || '';
                this.state.externalUser = cfg.external_user || '';
                this.state.externalPass = cfg.external_pass || '';
                this.state.themePrimary = cfg.theme_primary || '#0078d7';
                this.state.themeFont = cfg.theme_font || '';
                this.state.themeLogo = cfg.theme_logo || '';
                this.state.autoRefresh = cfg.auto_refresh || 0;
                this.state.reportName = name;
                this.state.error = null;
                // If using external DSN, refresh schema
                if (this.state.externalDsn) await this.fetchSchema(true);
                // Automatically preview
                this.runPreview();
            } else {
                this.state.error = data.message;
            }
        } catch (e) {
            this.state.error = e.message;
        } finally {
            this.state.loading = false;
            this.update();
        }
    }

    async saveReport() {
        if (!this.state.reportName) {
            this.state.error = "Please enter a report name to save.";
            this.update();
            return;
        }
        try {
            const payload = this.getConfigPayload();
            payload.report_name = this.state.reportName;
            
            const res = await fetch(`${this.props.apiEndpoint}&report_action=save`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert(data.message);
                await this.fetchSavedReports();
            } else {
                this.state.error = data.message;
                this.update();
            }
        } catch (e) {
            this.state.error = e.message;
            this.update();
        }
    }

    async loadTemplate(name) {
        if (!name) return;
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=load_template&name=${encodeURIComponent(name)}`);
            const data = await res.json();
            if (data.status === 'success') {
                this.state.templateHtml = data.html;
                this.state.templateName = name;
                this.state.selectedTemplate = name;
                this.update();
            }
        } catch (e) {
            console.error('Failed to load template', e);
        }
    }

    async saveTemplate() {
        if (!this.state.templateName) {
            alert("Please enter a template name.");
            return;
        }
        try {
            const payload = {
                template_name: this.state.templateName,
                html: this.state.templateHtml
            };
            const res = await fetch(`${this.props.apiEndpoint}&report_action=save_template`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert(data.message);
                this.state.selectedTemplate = this.state.templateName;
                await this.fetchSavedTemplates();
            } else {
                alert(data.message);
            }
        } catch (e) {
            console.error('Save template failed', e);
        }
    }

    async askAI(query) {
        if (!query) return;
        this.state.aiLoading = true;
        this.update();
        try {
            const payload = { query: query };
            const res = await fetch(`${this.props.apiEndpoint}&report_action=ai_build`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.status === 'success' && data.config) {
                this.state.selectedTable = data.config.table || this.state.selectedTable;
                this.state.columns = data.config.columns || [];
                this.state.joins = data.config.joins || [];
                // Reset filters since AI might not have set them perfectly yet
                this.state.filters = { logic: 'AND', conditions: [] };
                this.state.error = null;
                alert("AI successfully generated the query structure! Please review before running.");
            } else {
                alert(data.message || "Failed to generate AI report.");
            }
        } catch (e) {
            console.error('AI build failed', e);
            alert("AI Error: " + e.message);
        } finally {
            this.state.aiLoading = false;
            this.update();
        }
    }

    async runPreview() {
        this.state.loading = true;
        this.update();
        try {
            const payload = JSON.stringify(this.getConfigPayload());
            const res = await fetch(`${this.props.apiEndpoint}&report_action=preview`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload
            });
            const data = await res.json();
            if (data.status === 'success') {
                this.state.previewData = data.data;
                this.state.sql = data.sql;
                this.state.error = null;
            } else {
                this.state.error = data.message;
            }
        } catch (err) {
            this.state.error = err.message;
        } finally {
            this.state.loading = false;
            this.update();
        }
    }

    getConfigPayload() {
        return {
            table: this.state.selectedTable,
            joins: this.state.joins,
            columns: this.state.columns,
            filters: this.state.filters,
            group_by: this.state.groupBy,
            order_by: this.state.orderBy,
            limit: this.state.limit,
            allowed_roles: this.state.allowedRoles,
            cron_schedule: this.state.cronSchedule,
            cron_email: this.state.cronEmail,
            cron_format: this.state.cronFormat,
            webhook_url: this.state.webhookUrl,
            webhook_condition: this.state.webhookCondition,
            external_dsn: this.state.externalDsn,
            external_user: this.state.externalUser,
            external_pass: this.state.externalPass,
            theme_primary: this.state.themePrimary,
            theme_font: this.state.themeFont,
            theme_logo: this.state.themeLogo,
            auto_refresh: this.state.autoRefresh
        };
    }

    exportReport(format) {
        const payload = encodeURIComponent(JSON.stringify(this.getConfigPayload()));
        window.location.href = `${this.props.apiEndpoint}&report_action=export_${format}&payload=${payload}`;
    }

    generatePrintHTML() {
        let tableHtml = '';
        if (this.state.previewData && this.state.previewData.length > 0) {
            const headers = Object.keys(this.state.previewData[0]).map(k => `<th style="padding:8px; border:1px solid #ddd; background:#f4f4f4;">${k}</th>`).join('');
            const rows = this.state.previewData.map(row => {
                return '<tr>' + Object.values(row).map(v => `<td style="padding:6px 8px; border:1px solid #eee;">${v}</td>`).join('') + '</tr>';
            }).join('');
            tableHtml = `<table style="width:100%; border-collapse:collapse; font-family:sans-serif; text-align:left;"><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table>`;
        }

        let html = this.state.templateHtml;
        html = html.replace(/{{ data_table }}/g, tableHtml);
        html = html.replace(/{{ report_title }}/g, this.state.reportName || this.state.selectedTable || 'SPP Report');
        html = html.replace(/{{ current_date }}/g, new Date().toLocaleString());
        return html;
    }

    printReport() {
        const printArea = document.getElementById('spp-print-area');
        if (printArea) {
            printArea.innerHTML = this.generatePrintHTML();
        }
        window.print();
    }

    showPreview() {
        this.state.previewHtml = this.generatePrintHTML();
        this.state.showPreviewModal = true;
        this.update();
    }

    async fetchVersions() {
        if (!this.state.reportName) return;
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=list_versions&name=` + encodeURIComponent(this.state.reportName));
            const data = await res.json();
            if (data.status === 'success') {
                this.state.versions = data.versions;
            }
        } catch (e) {
            console.error("Failed to fetch versions", e);
        }
        this.update();
    }

    async restoreVersion(versionFile) {
        if (!confirm(`Are you sure you want to restore ${versionFile}? The current version will be backed up.`)) return;
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=restore_version`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    report_name: this.state.reportName,
                    version_file: versionFile
                })
            });
            const data = await res.json();
            if (data.status === 'success') {
                this.state.showVersions = false;
                await this.loadReport(this.state.reportName);
                alert("Version restored successfully.");
            } else {
                alert("Restore failed: " + data.message);
            }
        } catch (e) {
            alert("Error restoring version: " + e.message);
        }
    }

    // --- UI Renderers ---

    renderFilters(filterGroup, parentPath = []) {
        const availableCols = this.state.schema[this.state.selectedTable] || [];
        return html`
            <div class="spp-filter-group" style="border: 1px solid var(--sppux-glass-border); padding: 10px; margin: 10px 0; border-radius: 8px; background: var(--sppux-glass-bg);">
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                    <select class="spp-input" style="width: 80px;" @change=${(e) => { filterGroup.logic = e.target.value; this.update(); }}>
                        <option value="AND" ${filterGroup.logic === 'AND' ? 'selected' : ''}>AND</option>
                        <option value="OR" ${filterGroup.logic === 'OR' ? 'selected' : ''}>OR</option>
                    </select>
                    <button class="btn primary outline small" @click=${() => { filterGroup.conditions.push({ field: availableCols[0] || '', operator: '=', value: '' }); this.update(); }}>+ Condition</button>
                    <button class="btn secondary outline small" @click=${() => { filterGroup.conditions.push({ logic: 'AND', conditions: [] }); this.update(); }}>+ Group</button>
                </div>
                <div style="padding-left: 20px; border-left: 2px solid var(--sppux-primary);">
                    ${filterGroup.conditions.map((cond, i) => {
                        if (cond.logic) {
                            return html`
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <div style="flex:1;">${this.renderFilters(cond, [...parentPath, i])}</div>
                                    <button class="btn danger icon small" @click=${() => { filterGroup.conditions.splice(i, 1); this.update(); }}>×</button>
                                </div>
                            `;
                        } else {
                            return html`
                                <div style="display: flex; gap: 10px; margin-bottom: 5px; align-items: center;">
                                    <select class="spp-input" @change=${(e) => { cond.field = e.target.value; this.update(); }}>
                                        ${availableCols.map(col => html`<option value="${col}" ${cond.field === col ? 'selected' : ''}>${col}</option>`)}
                                    </select>
                                    <select class="spp-input" style="width:120px;" @change=${(e) => { cond.operator = e.target.value; this.update(); }}>
                                        ${['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN'].map(op => html`<option value="${op}" ${cond.operator === op ? 'selected' : ''}>${op}</option>`)}
                                    </select>
                                    <input type="text" class="spp-input" placeholder="Value" value="${cond.value}" @input=${(e) => { cond.value = e.target.value; this.update(); }}>
                                    <button class="btn danger icon small" @click=${() => { filterGroup.conditions.splice(i, 1); this.update(); }}>×</button>
                                </div>
                            `;
                        }
                    })}
                </div>
            </div>
        `;
    }

    renderColumns() {
        const availableCols = this.state.schema[this.state.selectedTable] || [];
        return html`
            <div style="margin-bottom: 20px;">
                <h4>Selected Columns</h4>
                ${this.state.columns.map((col, i) => html`
                    <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        ${col.aggregate === 'CUSTOM' ? html`
                            <input type="text" class="spp-input" style="flex:1;" placeholder="Custom Formula (e.g. price * qty)" value="${col.field}" @input=${(e) => { col.field = e.target.value; this.update(); }}>
                        ` : html`
                            <select class="spp-input" style="flex:1;" @change=${(e) => { col.field = e.target.value; this.update(); }}>
                                ${availableCols.map(c => html`<option value="${c}" ${col.field === c ? 'selected' : ''}>${c}</option>`)}
                            </select>
                        `}
                        <select class="spp-input" @change=${(e) => { col.aggregate = e.target.value; this.update(); }}>
                            <option value="">(No Aggregate)</option>
                            ${['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'CUSTOM'].map(agg => html`<option value="${agg}" ${col.aggregate === agg ? 'selected' : ''}>${agg}</option>`)}
                        </select>
                        <input type="text" class="spp-input" placeholder="Alias (Optional)" value="${col.alias}" @input=${(e) => { col.alias = e.target.value; this.update(); }}>
                        <button class="btn danger icon small" @click=${() => { this.state.columns.splice(i, 1); this.update(); }}>×</button>
                    </div>
                `)}
                <button class="btn primary small" @click=${() => { this.state.columns.push({field: availableCols[0] || '*', aggregate: '', alias: ''}); this.update(); }}>+ Add Column</button>
            </div>
        `;
    }

    render() {
        if (this.state.loading && !this.state.schema) {
            return html`<div class="sppux-spinner"></div>`;
        }
        
        return html`
            <div class="spp-report-builder no-print" style="display: grid; grid-template-columns: 350px 1fr; gap: 20px;">
                <!-- Builder Sidebar -->
                <div class="spp-panel" style="background: var(--sppux-panel); border-radius: var(--sppux-radius-lg); padding: 20px; border: 1px solid var(--sppux-glass-border);">
                    <h3>Report Configurator</h3>
                    <hr style="border-color: var(--sppux-glass-border); margin: 15px 0;">

                    <div style="margin-bottom: 20px; display:flex; gap:10px; align-items:center; background: var(--sppux-glass-bg); padding: 10px; border-radius: 8px; border: 1px dashed var(--sppux-primary);">
                        <span style="font-size: 20px;">✨</span>
                        <input type="text" class="spp-input" style="flex:1; border: none; background: transparent; outline: none;" placeholder="Ask AI: Show me sales by region..." id="spp-ai-prompt" @keydown=${(e) => { if(e.key === 'Enter') this.askAI(e.target.value); }}>
                        <button class="btn primary" @click=${() => this.askAI(document.getElementById('spp-ai-prompt').value)} disabled=${this.state.aiLoading}>
                            ${this.state.aiLoading ? 'Thinking...' : 'Build'}
                        </button>
                    </div>
                    
                    <div style="margin-bottom: 20px; display:flex; gap:10px;">
                        <input type="text" class="spp-input" style="flex:1;" placeholder="Report Name" value="${this.state.reportName}" @input=${(e) => { this.state.reportName = e.target.value; this.update(); }}>
                        <button class="btn secondary outline" @click=${() => this.saveReport()}>Save .yml</button>
                    </div>

                    ${this.state.savedReports.length > 0 ? html`
                    <div style="margin-bottom: 20px; display:flex; gap:10px; align-items:center;">
                        <select class="spp-input" style="flex:1;" id="spp-load-report-select">
                            <option value="">-- Load Saved Report --</option>
                            ${this.state.savedReports.map(r => html`<option value="${r}">${r}</option>`)}
                        </select>
                        <button class="btn secondary outline" @click=${() => this.loadReport(document.getElementById('spp-load-report-select').value)}>Load</button>
                    </div>
                    ` : ''}
                    
                    <div style="margin-bottom: 20px;">
                        <label class="spp-label" style="display:block;margin-bottom:5px;font-weight:bold;">Base Table</label>
                        <select class="spp-input" style="width:100%; margin-bottom: 15px;" @change=${(e) => { 
                            this.state.selectedTable = e.target.value; 
                            this.state.columns = [{field: this.state.schema[e.target.value]?.[0] || '*', aggregate: '', alias: ''}];
                            this.state.joins = [];
                            this.state.filters = {logic:'AND', conditions:[]};
                            this.update(); 
                        }}>
                            <option value="">-- Select Table --</option>
                            ${Object.keys(this.state.schema).map(t => html`<option value="${t}" ${this.state.selectedTable === t ? 'selected' : ''}>${t}</option>`)}
                        </select>
                    </div>

                    <div style="margin-bottom: 15px; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 8px;">
                        <label style="display:block;margin-bottom:5px;font-weight:bold;">Table Joins</label>
                        ${this.state.joins.map((j, i) => html`
                            <div style="display:flex; flex-direction:column; gap:5px; margin-bottom:10px; padding:10px; border:1px solid var(--sppux-glass-border); border-radius:5px;">
                                <div style="display:flex; gap:10px;">
                                    <select class="spp-input" style="width:100px;" @change=${(e) => { j.type = e.target.value; this.update(); }}>
                                        <option value="LEFT JOIN" ${j.type==='LEFT JOIN'?'selected':''}>LEFT JOIN</option>
                                        <option value="INNER JOIN" ${j.type==='INNER JOIN'?'selected':''}>INNER JOIN</option>
                                    </select>
                                    <select class="spp-input" style="flex:1;" @change=${(e) => { j.table = e.target.value; this.update(); }}>
                                        <option value="">Select Table...</option>
                                        ${Object.keys(this.state.schema).map(t => html`<option value="${t}" ${j.table === t ? 'selected' : ''}>${t}</option>`)}
                                    </select>
                                    <button class="btn danger icon small" @click=${() => { this.state.joins.splice(i, 1); this.update(); }}>×</button>
                                </div>
                                <input type="text" class="spp-input" placeholder="ON clause (e.g. users.id = orders.user_id)" value="${j.on}" @input=${(e) => { j.on = e.target.value; this.update(); }}>
                            </div>
                        `)}
                        <button class="btn secondary small" @click=${() => { this.state.joins.push({table:'', type:'LEFT JOIN', on:''}); this.update(); }}>+ Add Join</button>
                    </div>

                    ${this.renderColumns()}
                    
                    <h4>Filters</h4>
                    ${this.renderFilters(this.state.filters)}

                    <div style="margin-top:20px; display:flex; gap:10px;">
                        <button class="btn primary" style="flex:1;" @click=${() => this.runPreview()}>Run Report</button>
                        <button class="btn secondary outline icon" title="Settings & Access" @click=${() => { this.state.showSettingsModal = true; this.update(); }}>⚙️</button>
                        ${this.state.reportName ? html`<button class="btn secondary outline icon" title="History" @click=${() => { this.state.showVersions = true; this.fetchVersions(); }}>🕒</button>` : ''}
                    </div>
                </div>

                <!-- Preview Area -->
                <div class="spp-panel" style="display:flex; flex-direction:column; gap:15px;">
                    ${this.state.error ? html`<div style="color:var(--sppux-danger); padding:10px; border:1px solid var(--sppux-danger); border-radius:5px;">${this.state.error}</div>` : ''}
                    
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Report Preview</h3>
                        <div style="display:flex; gap:10px;">
                            <button class="btn secondary" @click=${() => this.exportReport('csv')}>Export CSV</button>
                            <button class="btn secondary" @click=${() => this.exportReport('xls')}>Export Excel</button>
                            <button class="btn secondary" @click=${() => this.exportReport('pdf')} title="Requires server-side PDF plugin">Export PDF (Server)</button>
                            <button class="btn secondary" @click=${() => this.showPreview()}>Print Preview</button>
                            <button class="btn primary" @click=${() => this.printReport()}>Print PDF (Native)</button>
                        </div>
                    </div>
                    
                    ${this.state.sql ? html`<pre style="background:var(--sppux-code-bg); color:var(--sppux-code-text); padding:10px; border-radius:5px; font-size:12px; overflow-x:auto;">${this.state.sql}</pre>` : ''}

                    <div style="background:var(--sppux-panel); border:1px solid var(--sppux-glass-border); padding:15px; border-radius:var(--sppux-radius-lg); display:flex; gap:10px; align-items:center;">
                        <label><strong>Print Layout:</strong></label>
                        <select class="spp-input" style="width:200px;" @change=${(e) => {
                            if (e.target.value) {
                                this.loadTemplate(e.target.value);
                            }
                        }}>
                            <option value="">-- Generic Template --</option>
                            ${this.state.savedTemplates.map(t => html`<option value="${t}" ${this.state.selectedTemplate === t ? 'selected' : ''}>${t}</option>`)}
                        </select>
                        <button class="btn secondary outline small" @click=${() => { this.state.showTemplateModal = true; this.update(); }}>⚙️ Manage Templates</button>
                    </div>

                    ${this.state.loading ? html`<div class="sppux-spinner"></div>` : 
                        (this.state.previewData && this.state.previewData.length > 0) ? html`
                        <div class="spp-report-grid-container" style="background:var(--sppux-card-bg); border-radius:var(--sppux-radius-lg); border:1px solid var(--sppux-glass-border); overflow-x:auto;">
                            <table style="width:100%; border-collapse: collapse; text-align: left;">
                                <thead>
                                    <tr style="background:var(--sppux-glass-bg); border-bottom:1px solid var(--sppux-glass-border);">
                                        ${Object.keys(this.state.previewData[0]).map(k => html`<th style="padding:12px; font-weight:600;">${k}</th>`)}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${this.state.previewData.map(row => html`
                                        <tr style="border-bottom:1px solid var(--sppux-glass-border);">
                                            ${Object.values(row).map(val => html`<td style="padding:10px 12px; color:var(--sppux-text-dim);">${val}</td>`)}
                                        </tr>
                                    `)}
                                </tbody>
                            </table>
                        </div>
                        ` : html`<div style="padding:40px; text-align:center; color:var(--sppux-text-dim); border:1px dashed var(--sppux-glass-border); border-radius:8px;">No data or run report to preview.</div>`
                    }
                </div>
            </div>

            <!-- Print Only View -->
            <div class="print-only" id="spp-print-area" style="display:none; padding:20px; font-family:sans-serif; background:white; color:black;">
                <!-- Generated Print HTML goes here -->
            </div>

            <!-- Modals -->
            ${this.state.showTemplateModal ? html`
                <div class="spp-modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
                    <div class="spp-modal-content" style="background:var(--sppux-bg, #ffffff); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); padding:20px; border-radius:var(--sppux-radius-lg); width:800px; max-width:90vw; max-height:90vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.5); border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.1));">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h2 style="margin:0;">Layout Template Editor (WYSIWYG)</h2>
                            <button class="btn danger icon" @click=${() => { this.state.showTemplateModal = false; this.update(); }}>×</button>
                        </div>
                        <p style="color:var(--sppux-text-dim); margin-top:0;">Use variables: <code>{{ company_name }}</code>, <code>{{ report_title }}</code>, <code>{{ current_date }}</code>, <code>{{ data_table }}</code></p>
                        
                        <div style="margin-bottom:15px; display:flex; gap:10px;">
                            <input type="text" class="spp-input" style="flex:1;" placeholder="Template Name (e.g. Corporate_Header)" value="${this.state.templateName}" @input=${(e) => { this.state.templateName = e.target.value; this.update(); }}>
                            <button class="btn primary" @click=${() => this.saveTemplate()}>Save Template</button>
                        </div>

                        ${SPPUX.RichText ? SPPUX.RichText.render(this.state.templateHtml, (val) => { this.state.templateHtml = val; }, this.state.templateHtml) : html`<textarea class="spp-input" style="width:100%; height:300px; font-family:monospace;" @input=${(e) => { this.state.templateHtml = e.target.value; }}>${this.state.templateHtml}</textarea>`}
                    </div>
                </div>
            ` : ''}

            ${this.state.showPreviewModal ? html`
                <div class="spp-modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); z-index:9999; display:flex; align-items:center; justify-content:center; padding: 20px;">
                    <div class="spp-modal-content" style="background:white; color:black; padding:40px; border-radius:8px; width:900px; max-width:95vw; height:100%; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.5);">
                        <div style="display:flex; justify-content:flex-end; margin-bottom:20px;" class="no-print">
                            <button class="btn primary" style="margin-right:10px;" @click=${() => this.printReport()}>Print Now</button>
                            <button class="btn danger" @click=${() => { this.state.showPreviewModal = false; this.update(); }}>Close Preview</button>
                        </div>
                        <div dangerouslySetInnerHTML=${{__html: this.state.previewHtml}}></div>
                    </div>
                </div>
            ` : ''}

            ${this.state.showSettingsModal ? html`
                <div class="spp-modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
                    <div class="spp-modal-content" style="background:var(--sppux-bg, #ffffff); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); padding:20px; border-radius:var(--sppux-radius-lg); width:500px; max-width:90vw; max-height:90vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.5); border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.1));">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h2 style="margin:0;">Report Settings & Access</h2>
                            <button class="btn danger icon" @click=${() => { this.state.showSettingsModal = false; this.update(); }}>×</button>
                        </div>

                        <h4>Federated BI (External Data)</h4>
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">External PDO DSN (e.g. mysql:host=localhost;dbname=otherdb). Leave blank for local SPP database.</label>
                        <input type="text" class="spp-input" style="width:100%; margin-bottom:10px;" placeholder="mysql:host=localhost;dbname=otherdb" value="${this.state.externalDsn || ''}" @input=${(e) => { this.state.externalDsn = e.target.value; this.update(); }}>
                        <div style="display:flex; gap:10px; margin-bottom:20px;">
                            <input type="text" class="spp-input" style="flex:1;" placeholder="DB Username" value="${this.state.externalUser || ''}" @input=${(e) => { this.state.externalUser = e.target.value; this.update(); }}>
                            <input type="password" class="spp-input" style="flex:1;" placeholder="DB Password" value="${this.state.externalPass || ''}" @input=${(e) => { this.state.externalPass = e.target.value; this.update(); }}>
                            <button class="btn secondary" @click=${() => this.fetchSchema(true)}>Refresh Schema</button>
                        </div>
                        
                        <h4>Row-Level Security (RBAC)</h4>
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Allowed Roles (comma separated, e.g. admin,manager). Leave blank for all.</label>
                        <input type="text" class="spp-input" style="width:100%; margin-bottom:20px;" placeholder="admin,manager" value="${this.state.allowedRoles}" @input=${(e) => { this.state.allowedRoles = e.target.value; this.update(); }}>
                        
                        <h4>Cron Scheduling (Automated Email)</h4>
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Cron Schedule Expression (e.g. 0 8 * * * for 8 AM daily). Leave blank to disable.</label>
                        <input type="text" class="spp-input" style="width:100%; margin-bottom:10px;" placeholder="* * * * *" value="${this.state.cronSchedule}" @input=${(e) => { this.state.cronSchedule = e.target.value; this.update(); }}>
                        
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Recipient Email Addresses (comma separated)</label>
                        <input type="email" class="spp-input" style="width:100%; margin-bottom:10px;" placeholder="ceo@company.com" value="${this.state.cronEmail}" @input=${(e) => { this.state.cronEmail = e.target.value; this.update(); }}>
                        
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Email Format</label>
                        <select class="spp-input" style="width:100%; margin-bottom:20px;" @change=${(e) => { this.state.cronFormat = e.target.value; this.update(); }}>
                            <option value="html" ${this.state.cronFormat === 'html' ? 'selected' : ''}>Rich HTML Body + CSV Attachment</option>
                            <option value="pdf" ${this.state.cronFormat === 'pdf' ? 'selected' : ''}>PDF Attachment (Requires TCPDF)</option>
                        </select>

                        <h4>Threshold Alerts (Webhooks)</h4>
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Webhook URL (POST triggered during Cron run if condition is met)</label>
                        <input type="url" class="spp-input" style="width:100%; margin-bottom:10px;" placeholder="https://hooks.slack.com/..." value="${this.state.webhookUrl}" @input=${(e) => { this.state.webhookUrl = e.target.value; this.update(); }}>
                        
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Alert Condition Expression (e.g. Total Sales < 1000). Uses row[0] values.</label>
                        <input type="text" class="spp-input" style="width:100%; margin-bottom:20px;" placeholder="Total Sales < 1000" value="${this.state.webhookCondition}" @input=${(e) => { this.state.webhookCondition = e.target.value; this.update(); }}>

                        <h4>White-Labeling & Live Data</h4>
                        <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Auto-Refresh Interval (seconds). Set to 0 to disable.</label>
                        <input type="number" class="spp-input" style="width:100%; margin-bottom:10px;" placeholder="0" value="${this.state.autoRefresh || 0}" @input=${(e) => { this.state.autoRefresh = e.target.value; this.update(); }}>
                        
                        <div style="display:flex; gap:10px; margin-bottom:20px;">
                            <div style="flex:1;">
                                <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Primary Color (HEX)</label>
                                <input type="color" class="spp-input" style="width:100%; padding:0;" value="${this.state.themePrimary || '#0078d7'}" @input=${(e) => { this.state.themePrimary = e.target.value; this.update(); }}>
                            </div>
                            <div style="flex:1;">
                                <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Google Font Family</label>
                                <input type="text" class="spp-input" style="width:100%;" placeholder="Inter" value="${this.state.themeFont || ''}" @input=${(e) => { this.state.themeFont = e.target.value; this.update(); }}>
                            </div>
                            <div style="flex:1;">
                                <label style="display:block;margin-bottom:5px;font-size:12px;color:var(--sppux-text-dim);">Logo URL</label>
                                <input type="text" class="spp-input" style="width:100%;" placeholder="https://" value="${this.state.themeLogo || ''}" @input=${(e) => { this.state.themeLogo = e.target.value; this.update(); }}>
                            </div>
                        </div>
                        
                        <div style="display:flex; justify-content:flex-end;">
                            <button class="btn primary" @click=${() => { this.state.showSettingsModal = false; this.saveReport(); }}>Save & Close</button>
                        </div>
                    </div>
                </div>
            ` : ''}

            <!-- Versions Modal -->
            ${this.state.showVersions ? html`
                <div class="spp-modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
                    <div class="spp-modal-content" style="background:var(--sppux-bg, white); padding:20px; border-radius:var(--sppux-radius-lg, 8px); width:600px; max-width:90vw;">
                        <h3 style="margin-top:0;">Version History: ${this.state.reportName}</h3>
                        <p style="color:var(--sppux-text-dim);">Restoring a previous version will create a backup of the current state.</p>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            ${this.state.versions.length === 0 ? html`<p>No backups found.</p>` : html`
                                <table style="width:100%; border-collapse:collapse; text-align:left;">
                                    <thead>
                                        <tr style="background:var(--sppux-glass-bg);"><th style="padding:10px;">Timestamp</th><th style="padding:10px;">Action</th></tr>
                                    </thead>
                                    <tbody>
                                        ${this.state.versions.map(v => html`
                                            <tr style="border-bottom:1px solid var(--sppux-glass-border);">
                                                <td style="padding:10px;">${v.timestamp}</td>
                                                <td style="padding:10px;">
                                                    <button class="btn primary small" @click=${() => this.restoreVersion(v.file)}>Restore</button>
                                                </td>
                                            </tr>
                                        `)}
                                    </tbody>
                                </table>
                            `}
                        </div>
                        
                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                            <button class="btn secondary outline" @click=${() => { this.state.showVersions = false; this.update(); }}>Close</button>
                        </div>
                    </div>
                </div>
            ` : ''}

            <style>
                @media print {
                    body * { visibility: hidden !important; }
                    .spp-report-builder { display: none !important; }
                    .spp-modal-overlay.no-print { display: none !important; }
                    #spp-print-area, #spp-print-area * { visibility: visible !important; }
                    #spp-print-area { display: block !important; position: absolute; left: 0; top: 0; width: 100%; padding: 0 !important; }
                }
            </style>
        `;
    }
}
