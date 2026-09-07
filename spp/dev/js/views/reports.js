/**
 * Reports Studio View Component (SPPReport V5)
 * 
 * Comprehensive, interactive visual reporting and business intelligence studio.
 * Features Visual Query Designer, Saved Report Runner, Multi-Format Exporters,
 * OLAP Pivot Cross-Tabulation, and Executive BI KPI Dashboard.
 */

export default class ReportsView extends BaseComponent {
    async onInit() {
        const hash = location.hash;
        let initialTab = 'builder';
        if (hash.includes('tab=viewer') || hash.includes('#viewer')) initialTab = 'viewer';
        else if (hash.includes('tab=pivot') || hash.includes('#pivot')) initialTab = 'pivot';
        else if (hash.includes('tab=dashboard') || hash.includes('#dashboard')) initialTab = 'dashboard';

        this.state = {
            activeTab: initialTab,
            loading: true,
            schema: {},
            tables: [],
            selectedTable: '',
            
            // Builder state
            reportName: '',
            reportTitle: '',
            reportDescription: '',
            selectedColumns: [], // [{ field, aggregate, alias }]
            joins: [],           // [{ table, type, on }]
            filters: [],         // [{ field, operator, value }]
            filterLogic: 'AND',
            groupBy: '',
            orderByField: '',
            orderByDirection: 'ASC',
            limit: 50,
            templateHtml: '<div style="text-align:center; padding:1rem;"><h2>{{ report_title }}</h2><p>Generated on {{ current_date }}</p></div><br>{{ data_table }}',
            cronSchedule: '',
            cronEmail: '',
            webhookUrl: '',

            // Preview & Execution state
            previewExecuting: false,
            previewResults: null,
            previewError: null,
            generatedSql: '',

            // Saved Reports state
            savedReports: [],
            activeReport: null,

            // Pivot state
            pivotRowField: '',
            pivotColField: '',
            pivotValField: '',
            pivotAgg: 'COUNT',
            pivotMatrix: null
        };

        await this.loadSchema();
        await this.loadSavedReports();
    }

    async switchTab(tab) {
        if (this.state.activeTab === tab) return;
        this.setState({ activeTab: tab });
        if (tab === 'viewer' && this.state.savedReports.length === 0) {
            await this.loadSavedReports();
        }
    }

    // =========================================================================
    //  DATA FETCHING & SCHEMA
    // =========================================================================

    async loadSchema() {
        try {
            const res = await this.api('report_schema');
            if (res.success && res.schema) {
                const tables = Object.keys(res.schema);
                const firstTable = tables[0] || '';
                const defaultCols = (res.schema[firstTable] || []).slice(0, 4).map(f => ({
                    field: f,
                    aggregate: '',
                    alias: ''
                }));

                this.setState({
                    schema: res.schema,
                    tables: tables,
                    selectedTable: firstTable,
                    selectedColumns: defaultCols,
                    loading: false
                });
            } else {
                this.setState({ loading: false, error: res.error || 'Failed to inspect schema' });
            }
        } catch (e) {
            this.setState({ loading: false, error: e.message });
        }
    }

    async loadSavedReports() {
        try {
            const res = await this.api('list_reports');
            if (res.success) {
                this.setState({ savedReports: res.reports || [] });
            }
        } catch (e) {
            console.error('Failed to load reports:', e);
        }
    }

    // =========================================================================
    //  BUILDER ACTIONS
    // =========================================================================

    selectTable(table) {
        const cols = (this.state.schema[table] || []).slice(0, 4).map(f => ({
            field: f,
            aggregate: '',
            alias: ''
        }));
        this.setState({
            selectedTable: table,
            selectedColumns: cols,
            joins: [],
            filters: [],
            groupBy: '',
            orderByField: '',
            previewResults: null
        });
    }

    addColumn(fieldName = '') {
        const available = this.state.schema[this.state.selectedTable] || [];
        const field = fieldName || available[0] || 'id';
        const cols = [...this.state.selectedColumns, { field, aggregate: '', alias: '' }];
        this.setState({ selectedColumns: cols });
    }

    updateColumn(index, key, val) {
        const cols = [...this.state.selectedColumns];
        if (cols[index]) {
            cols[index][key] = val;
            this.setState({ selectedColumns: cols });
        }
    }

    removeColumn(index) {
        const cols = this.state.selectedColumns.filter((_, i) => i !== index);
        this.setState({ selectedColumns: cols });
    }

    addJoin() {
        const otherTables = this.state.tables.filter(t => t !== this.state.selectedTable);
        const joinTable = otherTables[0] || '';
        const newJoin = {
            table: joinTable,
            type: 'LEFT JOIN',
            on: `${this.state.selectedTable}.id = ${joinTable}.user_id`
        };
        this.setState({ joins: [...this.state.joins, newJoin] });
    }

    updateJoin(index, key, val) {
        const joins = [...this.state.joins];
        if (joins[index]) {
            joins[index][key] = val;
            this.setState({ joins });
        }
    }

    removeJoin(index) {
        const joins = this.state.joins.filter((_, i) => i !== index);
        this.setState({ joins });
    }

    addFilter() {
        const available = this.state.schema[this.state.selectedTable] || [];
        const newFilter = {
            field: available[0] || 'id',
            operator: '=',
            value: ''
        };
        this.setState({ filters: [...this.state.filters, newFilter] });
    }

    updateFilter(index, key, val) {
        const filters = [...this.state.filters];
        if (filters[index]) {
            filters[index][key] = val;
            this.setState({ filters });
        }
    }

    removeFilter(index) {
        const filters = this.state.filters.filter((_, i) => i !== index);
        this.setState({ filters });
    }

    buildQuerySql() {
        const { selectedTable, selectedColumns, joins, filters, filterLogic, groupBy, orderByField, orderByDirection, limit } = this.state;
        if (!selectedTable) return '';

        let colsPart = '*';
        if (selectedColumns.length > 0) {
            colsPart = selectedColumns.map(c => {
                const agg = c.aggregate ? `${c.aggregate}(${c.field})` : c.field;
                return c.alias ? `${agg} AS "${c.alias}"` : agg;
            }).join(', ');
        }

        let sql = `SELECT ${colsPart} FROM ${selectedTable}`;

        // Joins
        if (joins.length > 0) {
            const joinClauses = joins.map(j => `${j.type} ${j.table} ON ${j.on}`).join(' ');
            sql += ` ${joinClauses}`;
        }

        // Filters
        if (filters.length > 0) {
            const conds = filters.map(f => {
                const val = isNaN(f.value) || f.value === '' ? `'${f.value.replace(/'/g, "''")}'` : f.value;
                if (f.operator === 'LIKE') return `${f.field} LIKE '%${f.value.replace(/'/g, "''")}%'`;
                if (f.operator === 'IN') return `${f.field} IN (${f.value})`;
                return `${f.field} ${f.operator} ${val}`;
            }).join(` ${filterLogic} `);
            sql += ` WHERE ${conds}`;
        }

        // Group By
        if (groupBy) {
            sql += ` GROUP BY ${groupBy}`;
        }

        // Order By
        if (orderByField) {
            sql += ` ORDER BY ${orderByField} ${orderByDirection}`;
        }

        // Limit
        sql += ` LIMIT ${limit || 50}`;

        return sql;
    }

    async runPreview() {
        const sql = this.buildQuerySql();
        if (!sql) return;

        this.setState({ previewExecuting: true, previewError: null, generatedSql: sql });

        try {
            const res = await this.api('preview_report', { sql });
            if (res.success) {
                this.setState({
                    previewResults: res,
                    previewExecuting: false,
                    previewError: null
                });
            } else {
                this.setState({
                    previewError: res.error || 'Query execution failed',
                    previewResults: null,
                    previewExecuting: false
                });
            }
        } catch (e) {
            this.setState({ previewError: e.message, previewResults: null, previewExecuting: false });
        }
    }

    async saveReportManifest() {
        let name = this.state.reportName.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '_');
        if (!name) {
            name = (this.state.selectedTable + '_report_' + Date.now()).toLowerCase();
            this.setState({ reportName: name });
        }

        const reportConfig = {
            report: {
                id: name,
                name: this.state.reportTitle || `${this.state.selectedTable.toUpperCase()} Analysis`,
                description: this.state.reportDescription || 'Custom interactive report generated via SPP Report Studio',
                group: 'Analytics',
                table: this.state.selectedTable,
                data_source: {
                    external_dsn: 'default',
                    sql_query: this.buildQuerySql()
                },
                columns: this.state.selectedColumns,
                joins: this.state.joins,
                filters: {
                    logic: this.state.filterLogic,
                    conditions: this.state.filters
                },
                group_by: this.state.groupBy,
                order_by: { field: this.state.orderByField, direction: this.state.orderByDirection },
                limit: this.state.limit,
                view: {
                    template: this.state.templateHtml
                },
                automation: {
                    cron_schedule: this.state.cronSchedule || '',
                    notification_email: this.state.cronEmail || '',
                    webhook_url: this.state.webhookUrl || ''
                }
            }
        };

        try {
            const res = await this.api('save_report', {
                name: name,
                config: reportConfig
            });

            if (res.success) {
                if (this.admin?.notify) this.admin.notify(`Report '${name}' saved successfully.`, 'success');
                await this.loadSavedReports();
                this.setState({ activeTab: 'viewer' });
            } else {
                if (this.admin?.notify) this.admin.notify('Failed to save report: ' + res.error, 'error');
            }
        } catch (e) {
            if (this.admin?.notify) this.admin.notify('Error saving report: ' + e.message, 'error');
        }
    }

    // =========================================================================
    //  VIEWER & EXECUTION
    // =========================================================================

    async loadAndRunReport(repName) {
        this.setState({ loading: true });
        try {
            const res = await this.api('load_report', { name: repName });
            if (res.success && res.config) {
                const rep = res.config.report || res.config;
                const sql = rep.data_source?.sql_query || `SELECT * FROM ${rep.table || 'users'} LIMIT 50`;
                
                const execRes = await this.api('preview_report', { sql });
                this.setState({
                    activeReport: rep,
                    previewResults: execRes.success ? execRes : null,
                    previewError: execRes.success ? null : execRes.error,
                    generatedSql: sql,
                    loading: false
                });
            } else {
                this.setState({ loading: false });
                if (this.admin?.notify) this.admin.notify('Failed to load report: ' + res.error, 'error');
            }
        } catch (e) {
            this.setState({ loading: false });
            if (this.admin?.notify) this.admin.notify('Error loading report: ' + e.message, 'error');
        }
    }

    exportCsv() {
        if (!this.state.previewResults || !this.state.previewResults.rows?.length) return;
        const rows = this.state.previewResults.rows;
        const headers = Object.keys(rows[0]);
        const csvContent = [
            headers.join(','),
            ...rows.map(r => headers.map(h => `"${String(r[h] ?? '').replace(/"/g, '""')}"`).join(','))
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.state.reportName || 'report'}_export_${Date.now()}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    }

    exportJson() {
        if (!this.state.previewResults || !this.state.previewResults.rows?.length) return;
        const blob = new Blob([JSON.stringify(this.state.previewResults.rows, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.state.reportName || 'report'}_export_${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    exportExcel() {
        if (!this.state.previewResults || !this.state.previewResults.rows?.length) return;
        const rows = this.state.previewResults.rows;
        const headers = Object.keys(rows[0]);
        
        let htmlTable = '<table border="1"><thead><tr>';
        headers.forEach(h => htmlTable += `<th style="background:#f1f5f9; font-weight:bold;">${h}</th>`);
        htmlTable += '</tr></thead><tbody>';
        rows.forEach(r => {
            htmlTable += '<tr>';
            headers.forEach(h => htmlTable += `<td>${String(r[h] ?? '')}</td>`);
            htmlTable += '</tr>';
        });
        htmlTable += '</tbody></table>';

        const blob = new Blob([htmlTable], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.state.reportName || 'report'}_export_${Date.now()}.xls`;
        a.click();
        URL.revokeObjectURL(url);
    }

    // =========================================================================
    //  OLAP PIVOT MATRIX
    // =========================================================================

    generatePivotMatrix() {
        const { previewResults, pivotRowField, pivotColField, pivotValField, pivotAgg } = this.state;
        if (!previewResults || !previewResults.rows?.length || !pivotRowField || !pivotColField) {
            return;
        }

        const rows = previewResults.rows;
        const rowKeys = [...new Set(rows.map(r => String(r[pivotRowField] ?? '—')))].sort();
        const colKeys = [...new Set(rows.map(r => String(r[pivotColField] ?? '—')))].sort();

        const matrix = {};
        rowKeys.forEach(r => {
            matrix[r] = {};
            colKeys.forEach(c => matrix[r][c] = { sum: 0, count: 0, min: Infinity, max: -Infinity });
        });

        rows.forEach(r => {
            const rk = String(r[pivotRowField] ?? '—');
            const ck = String(r[pivotColField] ?? '—');
            const val = parseFloat(r[pivotValField]) || 0;
            const cell = matrix[rk][ck];
            cell.count++;
            cell.sum += val;
            if (val < cell.min) cell.min = val;
            if (val > cell.max) cell.max = val;
        });

        // Compute aggregated values
        const formatted = {};
        rowKeys.forEach(r => {
            formatted[r] = {};
            colKeys.forEach(c => {
                const cell = matrix[r][c];
                if (cell.count === 0) {
                    formatted[r][c] = '—';
                } else if (pivotAgg === 'COUNT') {
                    formatted[r][c] = cell.count;
                } else if (pivotAgg === 'SUM') {
                    formatted[r][c] = Number(cell.sum.toFixed(2));
                } else if (pivotAgg === 'AVG') {
                    formatted[r][c] = Number((cell.sum / cell.count).toFixed(2));
                } else if (pivotAgg === 'MIN') {
                    formatted[r][c] = cell.min === Infinity ? 0 : cell.min;
                } else if (pivotAgg === 'MAX') {
                    formatted[r][c] = cell.max === -Infinity ? 0 : cell.max;
                }
            });
        });

        this.setState({
            pivotMatrix: { rowKeys, colKeys, data: formatted }
        });
    }

    // =========================================================================
    //  RENDER METHODS
    // =========================================================================

    render() {
        const { activeTab, loading, error } = this.state;

        // Update Header Tab Navigation
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            headerActions.innerHTML = '';
            const headerTabs = html`
                <div class="spp-tabs" style="display: inline-flex; gap: 8px;">
                    <button type="button" class="tab-btn ${activeTab === 'builder' ? 'active' : ''}" 
                            @click=${() => this.switchTab('builder')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'builder' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'builder' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'builder' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>🛠️ Visual Designer</span>
                    </button>
                    <button type="button" class="tab-btn ${activeTab === 'viewer' ? 'active' : ''}" 
                            @click=${() => this.switchTab('viewer')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'viewer' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'viewer' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'viewer' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>👁️ Saved Reports (${this.state.savedReports.length})</span>
                    </button>
                    <button type="button" class="tab-btn ${activeTab === 'pivot' ? 'active' : ''}" 
                            @click=${() => this.switchTab('pivot')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'pivot' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'pivot' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'pivot' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>📊 OLAP Pivot Matrix</span>
                    </button>
                    <button type="button" class="tab-btn ${activeTab === 'dashboard' ? 'active' : ''}" 
                            @click=${() => this.switchTab('dashboard')}
                            style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid ${activeTab === 'dashboard' ? 'var(--primary)' : 'transparent'}; background: ${activeTab === 'dashboard' ? 'var(--primary-subtle)' : 'transparent'}; color: ${activeTab === 'dashboard' ? 'var(--text-bright)' : 'var(--text-secondary)'}; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                        <span>📈 BI Dashboard</span>
                    </button>
                </div>
            `;
            if (headerTabs.render) headerTabs.render(headerActions);
            else headerActions.innerHTML = headerTabs.toString();
        }

        if (loading) {
            return html`
                <div class="loading-state" style="min-height: 50vh; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 1rem;">
                    <div class="sppux-spinner"></div>
                    <div style="color: var(--text-dim); font-size: 0.9rem;">Connecting to Report Engine & Database Schemas...</div>
                </div>
            `;
        }

        if (error) {
            return html`
                <div class="alert error-alert" style="margin: 2rem;">
                    <strong>Report Studio Error:</strong> ${error}
                </div>
            `;
        }

        return html`
            <div class="reports-studio-container" style="max-width: 1440px; margin: 0 auto; padding: 1.5rem;">
                ${activeTab === 'builder' ? this.renderBuilder() : ''}
                ${activeTab === 'viewer' ? this.renderViewer() : ''}
                ${activeTab === 'pivot' ? this.renderPivot() : ''}
                ${activeTab === 'dashboard' ? this.renderDashboard() : ''}
            </div>
        `;
    }

    // =========================================================================
    //  RENDER: Visual Designer (Tab 1)
    // =========================================================================

    renderBuilder() {
        const { tables, selectedTable, schema, selectedColumns, joins, filters, filterLogic, groupBy, orderByField, orderByDirection, limit, previewExecuting, previewResults, previewError, generatedSql } = this.state;
        const availableCols = schema[selectedTable] || [];

        return html`
            <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start;">
                
                <!-- Left Sidebar: Tables & Report Meta -->
                <div class="glass-panel" style="padding: 1.25rem;">
                    <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                        <span>🗄️</span> Select Data Source Table
                    </h4>

                    <div style="max-height: 380px; overflow-y: auto; border: 1px solid var(--glass-border); border-radius: 6px; padding: 4px; background: rgba(0,0,0,0.15);">
                        ${tables.map(tbl => html`
                            <div class="table-item ${tbl === selectedTable ? 'active' : ''}" 
                                 @click=${() => this.selectTable(tbl)}
                                 style="padding: 8px 10px; border-radius: 4px; cursor: pointer; font-size: 0.82rem; font-family: 'JetBrains Mono', monospace; display: flex; justify-content: space-between; background: ${tbl === selectedTable ? 'var(--primary-subtle)' : 'transparent'}; color: ${tbl === selectedTable ? 'var(--text-bright)' : 'var(--text-dim)'};">
                                <span>${tbl}</span>
                                <span style="font-size: 0.7rem; opacity: 0.7;">${(schema[tbl] || []).length} cols</span>
                            </div>
                        `)}
                    </div>

                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--glass-border); padding-top: 1.25rem;">
                        <h4 style="margin: 0 0 0.85rem 0; font-size: 0.95rem; color: var(--text-bright);">Report Metadata</h4>
                        
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.75rem; color: var(--text-dim);">Identifier (slug)</label>
                            <input type="text" class="spp-element" placeholder="e.g. monthly_sales" 
                                   .value=${this.state.reportName} 
                                   @input=${(e) => this.setState({ reportName: e.target.value })}
                                   style="width: 100%; padding: 6px 10px; font-size: 0.8rem; border-radius: 6px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.75rem; color: var(--text-dim);">Display Title</label>
                            <input type="text" class="spp-element" placeholder="e.g. Monthly Revenue Summary" 
                                   .value=${this.state.reportTitle} 
                                   @input=${(e) => this.setState({ reportTitle: e.target.value })}
                                   style="width: 100%; padding: 6px 10px; font-size: 0.8rem; border-radius: 6px;">
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim);">Cron Schedule (optional)</label>
                            <input type="text" class="spp-element" placeholder="e.g. 0 2 1 * *" 
                                   .value=${this.state.cronSchedule} 
                                   @input=${(e) => this.setState({ cronSchedule: e.target.value })}
                                   style="width: 100%; padding: 6px 10px; font-size: 0.8rem; border-radius: 6px;">
                        </div>
                    </div>
                </div>

                <!-- Right Canvas: Visual Query & Output Builder -->
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    <!-- Section 1: Columns & Aggregates -->
                    <div class="glass-panel" style="padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h4 style="margin: 0; font-size: 0.95rem; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                                <span>📋</span> Selected Columns & Aggregations
                            </h4>
                            <button class="btn ghost-btn btn-sm" @click=${() => this.addColumn()}>+ Add Column</button>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            ${selectedColumns.map((col, idx) => html`
                                <div style="display: grid; grid-template-columns: 1fr 140px 140px 36px; gap: 8px; align-items: center;">
                                    <select class="spp-element" style="padding: 6px 8px; font-size: 0.8rem;"
                                            .value=${col.field}
                                            @change=${(e) => this.updateColumn(idx, 'field', e.target.value)}>
                                        ${availableCols.map(c => html`<option value="${c}" ?selected=${c === col.field}>${c}</option>`)}
                                    </select>

                                    <select class="spp-element" style="padding: 6px 8px; font-size: 0.8rem;"
                                            .value=${col.aggregate}
                                            @change=${(e) => this.updateColumn(idx, 'aggregate', e.target.value)}>
                                        <option value="">None (Raw)</option>
                                        <option value="COUNT">COUNT()</option>
                                        <option value="SUM">SUM()</option>
                                        <option value="AVG">AVG()</option>
                                        <option value="MIN">MIN()</option>
                                        <option value="MAX">MAX()</option>
                                    </select>

                                    <input type="text" class="spp-element" placeholder="Alias..." 
                                           style="padding: 6px 8px; font-size: 0.8rem;"
                                           .value=${col.alias}
                                           @input=${(e) => this.updateColumn(idx, 'alias', e.target.value)}>

                                    <button class="btn ghost-btn btn-xs" style="color: var(--error);" @click=${() => this.removeColumn(idx)}>✕</button>
                                </div>
                            `)}
                        </div>
                    </div>

                    <!-- Section 2: Joins, Filters, and Ordering -->
                    <div class="glass-panel" style="padding: 1.25rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            
                            <!-- Filters -->
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                    <h4 style="margin: 0; font-size: 0.9rem; color: var(--text-bright);">Filters (WHERE)</h4>
                                    <div style="display: flex; gap: 6px;">
                                        <select class="spp-element" style="padding: 2px 6px; font-size: 0.75rem;"
                                                .value=${filterLogic}
                                                @change=${(e) => this.setState({ filterLogic: e.target.value })}>
                                            <option value="AND">AND</option>
                                            <option value="OR">OR</option>
                                        </select>
                                        <button class="btn ghost-btn btn-xs" @click=${() => this.addFilter()}>+ Filter</button>
                                    </div>
                                </div>

                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    ${filters.map((f, idx) => html`
                                        <div style="display: grid; grid-template-columns: 1fr 80px 1fr 28px; gap: 6px; align-items: center;">
                                            <select class="spp-element" style="padding: 4px; font-size: 0.75rem;"
                                                    .value=${f.field}
                                                    @change=${(e) => this.updateFilter(idx, 'field', e.target.value)}>
                                                ${availableCols.map(c => html`<option value="${c}" ?selected=${c === f.field}>${c}</option>`)}
                                            </select>
                                            <select class="spp-element" style="padding: 4px; font-size: 0.75rem;"
                                                    .value=${f.operator}
                                                    @change=${(e) => this.updateFilter(idx, 'operator', e.target.value)}>
                                                <option value="=">=</option>
                                                <option value="!=">!=</option>
                                                <option value=">">&gt;</option>
                                                <option value="<">&lt;</option>
                                                <option value="LIKE">LIKE</option>
                                                <option value="IN">IN</option>
                                            </select>
                                            <input type="text" class="spp-element" placeholder="val" style="padding: 4px 6px; font-size: 0.75rem;"
                                                   .value=${f.value}
                                                   @input=${(e) => this.updateFilter(idx, 'value', e.target.value)}>
                                            <button class="btn ghost-btn btn-xs" style="color: var(--error); padding: 2px;" @click=${() => this.removeFilter(idx)}>✕</button>
                                        </div>
                                    `)}
                                    ${filters.length === 0 ? html`<div style="font-size: 0.75rem; color: var(--text-dim);">No filters applied (scans full table).</div>` : ''}
                                </div>
                            </div>

                            <!-- Sorting & Limits -->
                            <div>
                                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.9rem; color: var(--text-bright);">Grouping & Ordering</h4>
                                
                                <div class="form-group" style="margin-bottom: 0.5rem;">
                                    <label style="font-size: 0.75rem; color: var(--text-dim);">Group By (for aggregates)</label>
                                    <select class="spp-element" style="width: 100%; padding: 4px 8px; font-size: 0.75rem;"
                                            .value=${groupBy}
                                            @change=${(e) => this.setState({ groupBy: e.target.value })}>
                                        <option value="">None</option>
                                        ${availableCols.map(c => html`<option value="${c}" ?selected=${c === groupBy}>${c}</option>`)}
                                    </select>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 100px; gap: 8px; margin-bottom: 0.5rem;">
                                    <div>
                                        <label style="font-size: 0.75rem; color: var(--text-dim);">Order By</label>
                                        <select class="spp-element" style="width: 100%; padding: 4px 8px; font-size: 0.75rem;"
                                                .value=${orderByField}
                                                @change=${(e) => this.setState({ orderByField: e.target.value })}>
                                            <option value="">None</option>
                                            ${availableCols.map(c => html`<option value="${c}" ?selected=${c === orderByField}>${c}</option>`)}
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 0.75rem; color: var(--text-dim);">Direction</label>
                                        <select class="spp-element" style="width: 100%; padding: 4px 8px; font-size: 0.75rem;"
                                                .value=${orderByDirection}
                                                @change=${(e) => this.setState({ orderByDirection: e.target.value })}>
                                            <option value="ASC">ASC</option>
                                            <option value="DESC">DESC</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label style="font-size: 0.75rem; color: var(--text-dim);">Max Rows Limit</label>
                                    <input type="number" class="spp-element" style="width: 100%; padding: 4px 8px; font-size: 0.75rem;" 
                                           .value=${limit}
                                           @input=${(e) => this.setState({ limit: parseInt(e.target.value) || 50 })}>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0;">
                        <div style="font-size: 0.8rem; color: var(--text-dim); font-family: 'JetBrains Mono', monospace; max-width: 65%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${this.buildQuerySql()}
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn secondary-btn" @click=${() => this.runPreview()} ?disabled=${previewExecuting}>
                                ${previewExecuting ? '⏳ Running...' : '⚡ Test Run / Preview'}
                            </button>
                            <button class="btn primary-btn shine-effect" @click=${() => this.saveReportManifest()}>
                                💾 Save Report
                            </button>
                        </div>
                    </div>

                    <!-- Preview Results Panel -->
                    ${previewError ? html`
                        <div class="alert error-alert" style="margin-top: 0.5rem;">
                            <strong>Execution Error:</strong> ${previewError}
                        </div>
                    ` : ''}

                    ${previewResults ? html`
                        <div class="glass-panel" style="padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 0.95rem; color: var(--success); display: flex; align-items: center; gap: 6px;">
                                        <span>✓</span> Query Results (${previewResults.count} records · ${previewResults.duration_ms}ms)
                                    </h4>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn ghost-btn btn-xs" @click=${() => this.exportCsv()}>📥 Export CSV</button>
                                    <button class="btn ghost-btn btn-xs" @click=${() => this.exportExcel()}>📊 Export Excel</button>
                                    <button class="btn ghost-btn btn-xs" @click=${() => this.exportJson()}>📋 Export JSON</button>
                                </div>
                            </div>

                            <div style="overflow-x: auto; max-height: 450px; border-radius: 6px; border: 1px solid var(--glass-border);">
                                <table class="data-table" style="margin: 0; font-size: 0.82rem;">
                                    <thead>
                                        <tr>
                                            ${(previewResults.columns || []).map(col => html`<th style="position: sticky; top: 0; background: var(--surface); z-index: 2;">${col}</th>`)}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${(previewResults.rows || []).map(row => html`
                                            <tr>
                                                ${(previewResults.columns || []).map(col => html`<td>${row[col] !== null && row[col] !== undefined ? String(row[col]) : '—'}</td>`)}
                                            </tr>
                                        `)}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ` : ''}

                </div>
            </div>
        `;
    }

    // =========================================================================
    //  RENDER: Saved Reports & Viewer (Tab 2)
    // =========================================================================

    renderViewer() {
        const { savedReports, activeReport, previewResults, previewError, loading } = this.state;

        return html`
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- Report Picker Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                    ${savedReports.map(rep => html`
                        <div class="glass-panel report-card ${activeReport?.id === rep.name ? 'active-report' : ''}" 
                             @click=${() => this.loadAndRunReport(rep.name)}
                             style="padding: 1.25rem; cursor: pointer; border: 1px solid ${activeReport?.id === rep.name ? 'var(--primary)' : 'var(--glass-border)'}; border-radius: 10px; transition: all 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <h4 style="margin: 0; font-size: 1rem; color: var(--text-bright);">${rep.title}</h4>
                                <span class="badge info" style="font-size: 0.7rem;">${rep.table}</span>
                            </div>
                            <p style="margin: 0 0 0.85rem 0; font-size: 0.8rem; color: var(--text-dim); line-height: 1.4;">
                                ${rep.description || 'Enterprise report definition'}
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--primary);">
                                <span>⚡ Click to Execute</span>
                                <span style="font-family: 'JetBrains Mono', monospace;">${rep.name}.yml</span>
                            </div>
                        </div>
                    `)}
                    ${savedReports.length === 0 ? html`
                        <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-dim);">
                            No saved reports found. Use the <strong>Visual Designer</strong> to craft and save your first report!
                        </div>
                    ` : ''}
                </div>

                <!-- Active Report Output Table -->
                ${activeReport ? html`
                    <div class="glass-panel" style="padding: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.2rem; color: var(--text-bright);">${activeReport.name}</h3>
                                <div style="font-size: 0.8rem; color: var(--text-dim); margin-top: 4px;">
                                    Data Source: <code>${activeReport.table}</code> · Format: HTML / Streaming Table
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn ghost-btn btn-sm" @click=${() => this.exportCsv()}>📥 Download CSV</button>
                                <button class="btn ghost-btn btn-sm" @click=${() => this.exportExcel()}>📊 Download Excel</button>
                                <button class="btn ghost-btn btn-sm" @click=${() => this.exportJson()}>📋 Download JSON</button>
                                <button class="btn secondary-btn btn-sm" @click=${() => window.print()}>📄 Print</button>
                            </div>
                        </div>

                        ${previewError ? html`<div class="alert error-alert">${previewError}</div>` : ''}

                        ${previewResults ? html`
                            <div style="overflow-x: auto; max-height: 500px; border-radius: 8px; border: 1px solid var(--glass-border);">
                                <table class="data-table" style="margin: 0; font-size: 0.85rem;">
                                    <thead>
                                        <tr>
                                            ${(previewResults.columns || []).map(col => html`<th style="position: sticky; top: 0; background: var(--surface);">${col}</th>`)}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${(previewResults.rows || []).map(row => html`
                                            <tr>
                                                ${(previewResults.columns || []).map(col => html`<td>${row[col] !== null && row[col] !== undefined ? String(row[col]) : '—'}</td>`)}
                                            </tr>
                                        `)}
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-dim); display: flex; justify-content: space-between;">
                                <span>Total rows: <strong>${previewResults.count}</strong></span>
                                <span>Execution latency: <strong>${previewResults.duration_ms} ms</strong></span>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}

            </div>
        `;
    }

    // =========================================================================
    //  RENDER: OLAP Pivot Matrix (Tab 3)
    // =========================================================================

    renderPivot() {
        const { previewResults, pivotRowField, pivotColField, pivotValField, pivotAgg, pivotMatrix } = this.state;
        const cols = previewResults?.columns || [];

        return html`
            <div class="glass-panel" style="padding: 1.5rem;">
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: var(--text-bright); display: flex; align-items: center; gap: 8px;">
                    <span>📊</span> Multi-Dimensional OLAP Pivot Matrix
                </h3>
                <p style="margin: 0 0 1.5rem 0; font-size: 0.85rem; color: var(--text-dim);">
                    Perform cross-tabulation and dimensional aggregation on your active report dataset.
                </p>

                ${!previewResults ? html`
                    <div class="alert info-alert">
                        No active dataset loaded. Execute a query in <strong>Visual Designer</strong> or select a <strong>Saved Report</strong> first.
                    </div>
                ` : html`
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr) 140px; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim);">Row Dimension</label>
                            <select class="spp-element" style="width: 100%; padding: 6px 10px;"
                                    .value=${pivotRowField}
                                    @change=${(e) => this.setState({ pivotRowField: e.target.value })}>
                                <option value="">Select Row...</option>
                                ${cols.map(c => html`<option value="${c}" ?selected=${c === pivotRowField}>${c}</option>`)}
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim);">Column Dimension</label>
                            <select class="spp-element" style="width: 100%; padding: 6px 10px;"
                                    .value=${pivotColField}
                                    @change=${(e) => this.setState({ pivotColField: e.target.value })}>
                                <option value="">Select Column...</option>
                                ${cols.map(c => html`<option value="${c}" ?selected=${c === pivotColField}>${c}</option>`)}
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim);">Value Metric</label>
                            <select class="spp-element" style="width: 100%; padding: 6px 10px;"
                                    .value=${pivotValField}
                                    @change=${(e) => this.setState({ pivotValField: e.target.value })}>
                                <option value="">Select Metric...</option>
                                ${cols.map(c => html`<option value="${c}" ?selected=${c === pivotValField}>${c}</option>`)}
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim);">Aggregation</label>
                            <select class="spp-element" style="width: 100%; padding: 6px 10px;"
                                    .value=${pivotAgg}
                                    @change=${(e) => this.setState({ pivotAgg: e.target.value })}>
                                <option value="COUNT">COUNT</option>
                                <option value="SUM">SUM</option>
                                <option value="AVG">AVG</option>
                                <option value="MIN">MIN</option>
                                <option value="MAX">MAX</option>
                            </select>
                        </div>

                        <button class="btn primary-btn shine-effect" style="height: 38px;" @click=${() => this.generatePivotMatrix()}>
                            Generate Matrix
                        </button>
                    </div>

                    ${pivotMatrix ? html`
                        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--glass-border); max-height: 500px;">
                            <table class="data-table" style="margin: 0; font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th style="background: var(--primary-subtle); color: var(--primary);">${pivotRowField} / ${pivotColField}</th>
                                        ${pivotMatrix.colKeys.map(ck => html`<th style="text-align: right;">${ck}</th>`)}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${pivotMatrix.rowKeys.map(rk => html`
                                        <tr>
                                            <td style="font-weight: 600; color: var(--text-bright);">${rk}</td>
                                            ${pivotMatrix.colKeys.map(ck => html`
                                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace;">
                                                    ${pivotMatrix.data[rk][ck]}
                                                </td>
                                            `)}
                                        </tr>
                                    `)}
                                </tbody>
                            </table>
                        </div>
                    ` : ''}
                `}
            </div>
        `;
    }

    // =========================================================================
    //  RENDER: Executive BI Dashboard (Tab 4)
    // =========================================================================

    renderDashboard() {
        const { tables, savedReports, previewResults } = this.state;

        return html`
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- KPI Stat Cards -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div class="glass-panel" style="padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 4px;">Available Database Tables</div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary);">${tables.length}</div>
                        <div style="font-size: 0.75rem; color: var(--success); margin-top: 4px;">Schema introspection online</div>
                    </div>

                    <div class="glass-panel" style="padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 4px;">Saved Reports</div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--accent-light);">${savedReports.length}</div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">YAML Definitions in etc/</div>
                    </div>

                    <div class="glass-panel" style="padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 4px;">Export Drivers</div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-bright);">4</div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">CSV, Excel, PDF, JSON</div>
                    </div>

                    <div class="glass-panel" style="padding: 1.25rem;">
                        <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 4px;">Last Query Latency</div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: var(--success);">${previewResults?.duration_ms ? `${previewResults.duration_ms}ms` : '—'}</div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Direct PDO engine execution</div>
                    </div>
                </div>

                <!-- Feature Guide Banner -->
                <div class="glass-panel" style="padding: 1.5rem; background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1), transparent);">
                    <h3 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: var(--text-bright);">SPPReport V5 High-Throughput Engine</h3>
                    <p style="margin: 0 0 1rem 0; font-size: 0.85rem; color: var(--text-dim); line-height: 1.5;">
                        SPP Report Studio empowers developers to model complex analytical queries with visual drag-and-drop joins, nested filters, aggregate functions, and streaming exports. Reports are persisted as clean declarative YAML manifests for automated cron dispatch or REST API embedding.
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn primary-btn btn-sm" @click=${() => this.switchTab('builder')}>+ Build New Report</button>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.switchTab('viewer')}>Browse Saved Reports</button>
                    </div>
                </div>

            </div>
        `;
    }
}
