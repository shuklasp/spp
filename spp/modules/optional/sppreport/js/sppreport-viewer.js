export default class SPPReportViewer extends BaseComponent {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.state = {
            schema: {},
            selectedTable: '',
            columns: [], 
            filters: { logic: 'AND', conditions: [] },
            groupBy: [],
            orderBy: { field: '', direction: 'ASC' },
            limit: 100,
            previewData: null,
            sql: '',
            loading: true,
            error: null,
            reportName: this.props.reportName || '',
            templateHtml: '<div style="text-align:center;"><h2>{{ report_title }}</h2><p>Generated on {{ current_date }}</p></div><br>{{ data_table }}',
            showPreviewModal: false,
            previewHtml: '',
            themePrimary: '',
            themeFont: '',
            themeLogo: '',
            autoRefresh: 0,
            refreshIntervalId: null,
            showPivot: false,
            aiInsights: null,
            loadingInsights: false
        };
        this.props.apiEndpoint = this.props.apiEndpoint || '/spp/admin/api.php?action=report_api&modname=sppreport';
    }

    async onInit() {
        try {
            const res = await fetch(`${this.props.apiEndpoint}&report_action=schema`);
            const data = await res.json();
            if (data.status === 'success') {
                this.state.schema = data.schema;
                if (this.state.reportName) {
                    await this.loadReport(this.state.reportName);
                } else {
                    this.state.loading = false;
                    this.state.error = "No report name provided to viewer.";
                }
            } else {
                this.state.error = data.message;
            }
        } catch (err) {
            this.state.error = err.message;
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
                this.state.filters = cfg.filters || { logic: 'AND', conditions: [] };
                this.state.groupBy = cfg.group_by || [];
                this.state.orderBy = cfg.order_by || { field: '', direction: 'ASC' };
                this.state.limit = cfg.limit || 100;
                this.state.themePrimary = cfg.theme_primary || '';
                this.state.themeFont = cfg.theme_font || '';
                this.state.themeLogo = cfg.theme_logo || '';
                this.state.autoRefresh = parseInt(cfg.auto_refresh) || 0;
                this.state.reportName = name;
                this.state.error = null;
                
                // Set White-Labeling Themes
                if (this.state.themePrimary) this.container.style.setProperty('--sppux-primary', this.state.themePrimary);
                if (this.state.themeFont) this.container.style.setProperty('--sppux-font', this.state.themeFont);

                // Auto-refresh setup
                if (this.state.autoRefresh > 0 && !this.state.refreshIntervalId) {
                    this.state.refreshIntervalId = setInterval(() => this.runReport(true), this.state.autoRefresh * 1000);
                }

                // Automatically run the report
                this.runReport();
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

    async runReport(silent = false) {
        if (!silent) {
            this.state.loading = true;
            this.update();
        }
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
            if (!silent) {
                this.state.loading = false;
            }
            this.update();
        }
    }

    async fetchAIInsights() {
        this.state.loadingInsights = true;
        this.update();
        try {
            const payload = JSON.stringify(this.getConfigPayload());
            const res = await fetch(`${this.props.apiEndpoint}&report_action=ai_analyze`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload
            });
            const data = await res.json();
            if (data.status === 'success') {
                this.state.aiInsights = data.analysis;
            } else {
                alert("AI Insights failed: " + data.message);
            }
        } catch (err) {
            alert("AI Error: " + err.message);
        } finally {
            this.state.loadingInsights = false;
            this.update();
        }
    }

    getConfigPayload() {
        return {
            table: this.state.selectedTable,
            columns: this.state.columns,
            filters: this.state.filters,
            group_by: this.state.groupBy,
            order_by: this.state.orderBy,
            limit: this.state.limit
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
        html = html.replace(/{{ report_title }}/g, this.state.reportName || 'SPP Report');
        html = html.replace(/{{ current_date }}/g, new Date().toLocaleString());
        return html;
    }

    printReport() {
        const printArea = document.getElementById('spp-viewer-print-area');
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

    renderFilters(filterGroup, parentPath = []) {
        const availableCols = this.state.schema[this.state.selectedTable] || [];
        return html`
            <div class="spp-filter-group" style="border: 1px solid var(--sppux-glass-border); padding: 10px; margin: 10px 0; border-radius: 8px; background: var(--sppux-glass-bg);">
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                    <select class="spp-input" style="width: 80px;" @change=${(e) => { filterGroup.logic = e.target.value; this.update(); }}>
                        <option value="AND" ${filterGroup.logic === 'AND' ? 'selected' : ''}>AND</option>
                        <option value="OR" ${filterGroup.logic === 'OR' ? 'selected' : ''}>OR</option>
                    </select>
                    <button class="btn primary outline small" @click=${() => { filterGroup.conditions.push({ field: availableCols[0] || '', operator: '=', value: '' }); this.update(); }}>+ Add Override</button>
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

    render() {
        if (this.state.loading && !this.state.schema) {
            return html`<div class="sppux-spinner"></div>`;
        }
        
        return html`
            <div class="spp-report-viewer no-print" style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Report Header & Controls -->
                <div class="spp-panel" style="background: var(--sppux-panel); border-radius: var(--sppux-radius-lg); padding: 20px; border: 1px solid var(--sppux-glass-border);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <h2 style="margin:0 0 10px 0;">${this.state.reportName || 'Loading Report...'}</h2>
                            <p style="margin:0; color:var(--sppux-text-dim);">Adjust filters to narrow down your results, then click Run.</p>
                        </div>
                        <div style="display:flex; gap:10px;">
                            ${this.state.themeLogo ? html`<img src="${this.state.themeLogo}" style="height:35px; border-radius:4px;" />` : Fragment}
                            <button class="btn primary outline" @click=${() => this.runReport()}>Run Report</button>
                            <button class="btn secondary outline" @click=${() => this.fetchAIInsights()} disabled=${this.state.loadingInsights}>
                                ${this.state.loadingInsights ? 'Thinking...' : '✨ AI Insights'}
                            </button>
                            <button class="btn secondary outline" @click=${() => { this.state.showPivot = !this.state.showPivot; this.update(); }}>${this.state.showPivot ? 'Grid View' : 'Pivot View'}</button>
                            <button class="btn secondary" @click=${() => this.exportReport('csv')}>CSV</button>
                            <button class="btn primary" @click=${() => this.printReport()}>Print Native</button>
                        </div>
                    </div>
                    
                    ${this.state.selectedTable ? html`
                    <div style="margin-top: 20px;">
                        <h4>Runtime Filters</h4>
                        ${this.renderFilters(this.state.filters)}
                    </div>
                    ` : Fragment}
                </div>

                <!-- Data Display -->
                <div class="spp-panel" style="background: var(--sppux-panel); border-radius: var(--sppux-radius-lg); padding: 20px; border: 1px solid var(--sppux-glass-border);">
                    ${this.state.error ? html`<div style="color:var(--sppux-danger); padding:10px; border:1px solid var(--sppux-danger); border-radius:5px; margin-bottom:15px;">${this.state.error}</div>` : Fragment}
                    
                    ${this.state.aiInsights ? html`
                        <div style="background: var(--sppux-glass-bg); padding: 15px; border-radius: 8px; border: 1px dashed var(--sppux-primary); margin-bottom: 20px;">
                            <h4 style="margin-top:0; color:var(--sppux-primary);">✨ Executive Summary</h4>
                            <div style="white-space: pre-wrap; font-family: var(--sppux-font);">${this.state.aiInsights}</div>
                            <button class="btn secondary small" style="margin-top:10px;" @click=${() => { this.state.aiInsights = null; this.update(); }}>Dismiss</button>
                        </div>
                    ` : Fragment}

                    ${this.state.loading ? html`<div class="sppux-spinner"></div>` : 
                        (this.state.previewData && this.state.previewData.length > 0) ? html`
                        
                        ${this.state.showPivot ? html`
                            <div data-spp-type="ux" data-spp-path="/spp/modules/spp/sppreport/js/sppreport-pivot.js" data-spp-props='${JSON.stringify({data: this.state.previewData})}'></div>
                        ` : html`
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
                        `}
                        ` : html`<div style="padding:40px; text-align:center; color:var(--sppux-text-dim); border:1px dashed var(--sppux-glass-border); border-radius:8px;">No data to display. Run report.</div>`
                    }
                </div>
            </div>

            <!-- Print Only View -->
            <div class="print-only" id="spp-viewer-print-area" style="display:none; padding:20px; font-family:sans-serif; background:white; color:black;">
                <!-- Generated Print HTML goes here -->
            </div>

            <!-- Preview Modal -->
            ${this.state.showPreviewModal ? html`
                <div class="spp-modal-overlay" style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); z-index:9999; display:flex; align-items:center; justify-content:center; padding: 20px;">
                    <div class="spp-modal-content" style="background:white; color:black; padding:40px; border-radius:8px; width:900px; max-width:95vw; height:100%; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.5);">
                        <div style="display:flex; justify-content:flex-end; margin-bottom:20px;" class="no-print">
                            <button class="btn primary" style="margin-right:10px;" @click=${() => this.printReport()}>Print Now</button>
                            <button class="btn danger" @click=${() => { this.state.showPreviewModal = false; this.update(); }}>Close</button>
                        </div>
                        <div dangerouslySetInnerHTML=${{__html: this.state.previewHtml}}></div>
                    </div>
                </div>
            ` : Fragment}

            <style>
                @media print {
                    body * { visibility: hidden !important; }
                    .spp-report-viewer { display: none !important; }
                    .spp-modal-overlay.no-print { display: none !important; }
                    #spp-viewer-print-area, #spp-viewer-print-area * { visibility: visible !important; }
                    #spp-viewer-print-area { display: block !important; position: absolute; left: 0; top: 0; width: 100%; padding: 0 !important; }
                }
            </style>
        `;
    }
}
