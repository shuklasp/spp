/**
 * XdbView Component - Enterprise Edition
 * 
 * Enhanced with Schema Designer, Query Console, and Multi-Segment support.
 */

export default class XdbView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            databases: [],
            selectedDb: 'default',
            tables: [],
            selectedTable: null,
            data: [],
            query: '',
            queryResults: null,
            view: 'browse', // 'browse' | 'query' | 'audit' | 'schema'
            loadingTables: false,
            loadingData: false,
            columns: [],
            designerColumns: [{ name: 'id', type: 'int' }]
        };
        await this.fetchDatabases();
    }

    async fetchDatabases() {
        try {
            const res = await this.api.listXdbDatabases();
            const databases = res.success ? (res.data.databases || res.data) : (Array.isArray(res) ? res : []);
            this.setState({ databases: Array.isArray(databases) ? databases : [] });
            if (this.state.databases.length > 0 && !this.state.databases.includes(this.state.selectedDb)) {
                this.state.selectedDb = this.state.databases[0];
            }
            await this.selectDatabase(this.state.selectedDb);
        } catch (err) {
            this.notify('Failed to load databases', 'error');
        } finally {
            this.setState({ loading: false });
        }
    }

    async createDatabase() {
        const name = await window.prompt('Enter database name:');
        if (!name) return;
        try {
            await this.api.createXdbDatabase({ dbname: name });
            this.notify('Database created', 'success');
            await this.fetchDatabases();
        } catch (err) {
            this.notify(err.message, 'error');
        }
    }

    async selectDatabase(db) {
        this.setState({ selectedDb: db, loadingTables: true, selectedTable: null, data: [], queryResults: null });
        try {
            const res = await this.api.listXdbTables({ dbname: db });
            const tables = res.success ? (res.data.tables || res.data) : (Array.isArray(res) ? res : []);
            this.setState({ tables: Array.isArray(tables) ? tables : [] });
        } catch (err) {
            this.notify('Failed to load tables', 'error');
        } finally {
            this.setState({ loadingTables: false });
        }
    }

    async selectTable(table) {
        this.setState({ selectedTable: table, loadingData: true, view: 'browse', queryResults: null });
        try {
            // Fetch columns first for empty table display
            const resCols = await this.api.getXdbTableColumns({ dbname: this.state.selectedDb, table: table });
            const columns = resCols.success ? (resCols.data.columns || resCols.data) : (Array.isArray(resCols) ? resCols : []);
            this.setState({ columns: Array.isArray(columns) ? columns : [] });

            const resRows = await this.api.getXdbTableData({ 
                dbname: this.state.selectedDb, 
                table: table 
            });
            const rows = resRows.success ? (resRows.data.rows || resRows.data) : (Array.isArray(resRows) ? resRows : []);
            this.setState({ data: Array.isArray(rows) ? rows : [] });
        } catch (err) {
            this.notify('Failed to load table data', 'error');
        } finally {
            this.setState({ loadingData: false });
        }
    }

    async runQuery() {
        if (!this.state.query) return;
        this.setState({ loadingData: true });
        try {
            const res = await this.api.runXdbQuery({ 
                dbname: this.state.selectedDb, 
                sql: this.state.query 
            });
            const results = res.success ? (res.data.results || res.data) : (Array.isArray(res) ? res : []);
            this.setState({ queryResults: Array.isArray(results) ? results : [] });
            this.notify('Query executed successfully', 'success');
        } catch (err) {
            this.notify(err.message, 'error');
        } finally {
            this.setState({ loadingData: false });
        }
    }

    async showAudit() {
        this.setState({ view: 'audit', loadingData: true, selectedTable: null, queryResults: null });
        try {
            const res = await this.api.getXdbTableData({ 
                dbname: this.state.selectedDb, 
                table: '_audit' 
            });
            const rows = res.success ? (res.data.rows || res.data) : (Array.isArray(res) ? res : []);
            this.setState({ data: Array.isArray(rows) ? rows : [] });
        } catch (err) {
            this.notify('Audit explorer unavailable or no logs found.', 'info');
            this.setState({ data: [] });
        } finally {
            this.setState({ loadingData: false });
        }
    }

    openSchemaDesigner() {
        this.setState({ 
            designerTableName: '',
            designerColumns: [{ name: 'id', type: 'int', primary: true, notNull: true, unique: true, default: '' }],
            designerConstraints: []
        });
        
        const renderDesigner = () => {
            const cols = this.state.designerColumns;
            const constraints = this.state.designerConstraints;

            return html`
                <div class="designer-form" style="padding: 10px;">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">Table Name</label>
                        <input type="text" class="spp-element" id="designer-table-name" value="${this.state.designerTableName || ''}" @input=${(e) => this.state.designerTableName = e.target.value} placeholder="e.g. users" style="width: 100%; padding: 10px; font-size: 1.1rem;">
                    </div>
                    
                    <div class="section-title" style="margin: 20px 0 10px; font-weight: bold; border-bottom: 1px solid var(--glass-border); padding-bottom: 5px;">Columns</div>

                    <div class="columns-header" style="display: flex; gap: 8px; margin-bottom: 10px; padding: 0 5px; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: bold;">
                        <div style="flex: 2;">Name</div>
                        <div style="flex: 1.2;">Type</div>
                        <div style="flex: 0.6;">Size</div>
                        <div style="flex: 0.8; text-align: center;" title="Primary Key">PK</div>
                        <div style="flex: 0.8; text-align: center;" title="Not Null">NN</div>
                        <div style="flex: 0.8; text-align: center;" title="Unique">UQ</div>
                        <div style="flex: 1.5;">Default</div>
                        <div style="flex: 1.5;">Constraint (Check)</div>
                        <div style="width: 32px;"></div>
                    </div>

                    <div id="designer-columns-container" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                        ${cols.map((c, i) => html`
                            <div class="column-row glass-panel" style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.03);">
                                <input type="text" class="spp-element" value="${c.name}" @change=${(e) => { c.name = e.target.value; SPPUX.updateSubEditor(renderDesigner()); }} placeholder="Name" style="flex: 2;">
                                
                                <select class="spp-element" @change=${(e) => { c.type = e.target.value }} style="flex: 1.2;">
                                    <option value="int" ?selected=${c.type === 'int'}>INT</option>
                                    <option value="varchar" ?selected=${c.type === 'varchar'}>VARCHAR</option>
                                    <option value="text" ?selected=${c.type === 'text'}>TEXT</option>
                                    <option value="float" ?selected=${c.type === 'float'}>FLOAT</option>
                                    <option value="boolean" ?selected=${c.type === 'boolean'}>BOOL</option>
                                    <option value="datetime" ?selected=${c.type === 'datetime'}>DATETIME</option>
                                </select>

                                <input type="text" class="spp-element" value="${c.size || ''}" @change=${(e) => { c.size = e.target.value }} placeholder="Size" style="flex: 0.6; padding: 10px 5px; text-align: center;">

                                <div style="flex: 0.8; display: flex; justify-content: center;">
                                    <input type="checkbox" ?checked=${c.primary} @change=${(e) => { c.primary = e.target.checked; if(c.primary) { c.notNull = true; c.unique = true; SPPUX.updateSubEditor(renderDesigner()); } }}>
                                </div>
                                <div style="flex: 0.8; display: flex; justify-content: center;">
                                    <input type="checkbox" ?checked=${c.notNull} @change=${(e) => { c.notNull = e.target.checked }}>
                                </div>
                                <div style="flex: 0.8; display: flex; justify-content: center;">
                                    <input type="checkbox" ?checked=${c.unique} @change=${(e) => { c.unique = e.target.checked }}>
                                </div>

                                <input type="text" class="spp-element" value="${c.default || ''}" @change=${(e) => { c.default = e.target.value }} placeholder="Default" style="flex: 1.5;">
                                <input type="text" class="spp-element" value="${c.check || ''}" @change=${(e) => { c.check = e.target.value }} placeholder="e.g. . > 18" style="flex: 1.5;">

                                <button class="btn-icon danger" @click=${() => { 
                                    this.state.designerColumns.splice(i, 1);
                                    SPPUX.updateSubEditor(renderDesigner());
                                    this._registerGlobalHandlers();
                                }}>🗑️</button>
                            </div>
                        `)}
                    </div>
                    
                    <button type="button" class="btn ghost-btn btn-sm" style="margin-top: 10px;" @click=${(e) => {
                        e.preventDefault();
                        const newCols = [...this.state.designerColumns, { name: '', type: 'varchar', size: '', primary: false, notNull: false, unique: false, default: '' }];
                        this.setState({ designerColumns: newCols });
                        SPPUX.updateSubEditor(renderDesigner());
                        this._registerGlobalHandlers();
                    }}>➕ Add New Column</button>

                    <div class="section-title" style="margin: 30px 0 10px; font-weight: bold; border-bottom: 1px solid var(--glass-border); padding-bottom: 5px;">Composite Constraints</div>
                    
                    <div id="designer-constraints-container">
                        ${constraints.map((con, i) => html`
                            <div class="constraint-row glass-panel" style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.03);">
                                <select class="spp-element" style="flex: 1;" @change=${(e) => con.type = e.target.value}>
                                    <option value="unique" ?selected=${con.type === 'unique'}>UNIQUE</option>
                                    <option value="primary" ?selected=${con.type === 'primary'}>PRIMARY</option>
                                </select>
                                <div style="flex: 3; display: flex; flex-wrap: wrap; gap: 5px;">
                                    ${cols.filter(c => c.name).map(c => html`
                                        <label style="font-size: 0.8rem; display: flex; align-items: center; gap: 3px; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px;">
                                            <input type="checkbox" ?checked=${con.columns.includes(c.name)} @change=${(e) => {
                                                if (e.target.checked) con.columns.push(c.name);
                                                else con.columns = con.columns.filter(name => name !== c.name);
                                            }}> ${c.name}
                                        </label>
                                    `)}
                                </div>
                                <button class="btn-icon danger" @click=${() => { 
                                    this.state.designerConstraints.splice(i, 1);
                                    SPPUX.updateSubEditor(renderDesigner());
                                    this._registerGlobalHandlers();
                                }}>🗑️</button>
                            </div>
                        `)}
                    </div>

                    <button type="button" class="btn ghost-btn btn-sm" @click=${(e) => {
                        e.preventDefault();
                        const newCons = [...this.state.designerConstraints, { type: 'unique', columns: [] }];
                        this.setState({ designerConstraints: newCons });
                        SPPUX.updateSubEditor(renderDesigner());
                        this._registerGlobalHandlers();
                    }}>➕ Add Composite Constraint</button>
                </div>
            `;
        };

        SPPUX.openSubEditor('Visual Table Designer', renderDesigner(), {}, async (result) => {
            const name = this.state.designerTableName;
            if (!name) {
                this.notify('Table name required', 'error');
                return;
            }
            const schema = {};
            this.state.designerColumns.forEach(c => {
                if (c.name) {
                    let fullType = c.type;
                    if (c.size && (c.type === 'varchar' || c.type === 'int')) {
                        fullType += `(${c.size})`;
                    }
                    schema[c.name] = {
                        type: fullType,
                        primary: c.primary,
                        notNull: c.notNull,
                        unique: c.unique,
                        check: c.check === '' ? null : c.check,
                        default: c.default === '' ? null : c.default
                    };
                }
            });

            if (this.state.designerConstraints.length > 0) {
                schema['_constraints'] = this.state.designerConstraints.filter(con => con.columns.length > 0);
            }

            try {
                await this.api.createXdbTable({ 
                    dbname: this.state.selectedDb,
                    table: name,
                    schema: schema
                });
                this.notify('Table created successfully', 'success');
                await this.selectDatabase(this.state.selectedDb);
            } catch (err) {
                this.notify(err.message, 'error');
            }
        });
        
        this._registerGlobalHandlers();
    }

    render() {
        const { loading, databases, selectedDb, tables, selectedTable, data, view, loadingTables, loadingData, query, queryResults } = this.state;

        if (loading) return html`<div class="loading-state">Initializing Enterprise XDB...</div>`;

        return html`
            <div class="xdb-layout" style="display: flex; height: calc(100vh - 160px); gap: 20px;">
                <!-- Sidebar -->
                <div class="xdb-sidebar glass-panel" style="width: 280px; display: flex; flex-direction: column; overflow: hidden;">
                    <div class="sidebar-header" style="padding: 15px; border-bottom: 1px solid var(--glass-border); display: flex; gap: 8px;">
                        <select class="spp-element" style="flex: 1;" @change=${(e) => this.selectDatabase(e.target.value)}>
                            ${databases.map(db => html`<option value="${db}" ?selected=${db === selectedDb}>${db}</option>`)}
                        </select>
                        <button class="btn-icon" title="New Database" @click=${() => this.createDatabase()}>➕</button>
                    </div>
                    
                    <div class="table-list" style="flex: 1; overflow-y: auto; padding: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 10px;">
                            <label style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim);">Tables</label>
                            <div style="display: flex; gap: 5px;">
                                <button class="btn-icon" title="SQL Console" @click=${() => this.setState({ view: 'query', selectedTable: null, queryResults: null })}>💻</button>
                                <button class="btn-icon" title="Audit Log" @click=${() => this.showAudit()}>📜</button>
                            </div>
                        </div>
                        ${loadingTables ? html`<div class="loading-sm">...</div>` : ''}
                        ${tables.map(table => html`
                            <div class="table-item ${selectedTable === table ? 'active' : ''}" @click=${() => this.selectTable(table)}>
                                <span class="icon">📊</span> ${table}
                            </div>
                        `)}
                    </div>

                    <div class="sidebar-footer" style="padding: 15px; border-top: 1px solid var(--glass-border);">
                        <button class="btn primary-btn btn-sm" style="width: 100%;" @click=${() => this.openSchemaDesigner()}>✨ New Table</button>
                    </div>
                </div>

                <!-- Main -->
                <div class="xdb-main" style="flex: 1; display: flex; flex-direction: column; gap: 20px;">
                    ${view === 'query' ? this.renderQueryConsole() : html`
                        <div class="data-view glass-panel" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                            <div class="view-header" style="padding: 15px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                                <h4 style="margin: 0;">
                                    ${view === 'audit' ? 'Compliance Audit Trail' : (selectedTable ? `Table: ${selectedTable}` : 'Database Engine Explorer')}
                                </h4>
                                <div class="actions" style="display: flex; gap: 8px;">
                                    ${selectedTable ? html`
                                        <button class="btn ghost-btn btn-sm" @click=${() => this.selectTable(selectedTable)}>🔄 Refresh</button>
                                    ` : ''}
                                    ${view === 'audit' ? html`<button class="btn ghost-btn btn-sm" @click=${() => this.showAudit()}>🔄 Refresh Logs</button>` : ''}
                                </div>
                            </div>

                            <div class="grid-container" style="flex: 1; overflow: auto; background: rgba(0,0,0,0.2);">
                                ${loadingData ? html`<div class="loading-state">Syncing data...</div>` : ''}
                                ${!loadingData && data.length === 0 && !selectedTable && view !== 'audit' ? html`
                                    <div class="empty-state" style="padding: 60px; text-align: center;">
                                        <div style="font-size: 3rem; margin-bottom: 20px;">🛸</div>
                                        <h3>Select a table or open the SQL console to begin.</h3>
                                    </div>
                                ` : ''}
                                ${!loadingData && data.length === 0 && (selectedTable || view === 'audit') ? html`<div class="empty-state" style="padding: 40px;">No records found.</div>` : ''}
                                ${!loadingData && (data.length > 0 || (selectedTable && this.state.columns.length > 0)) ? this.renderGrid(data) : ''}
                            </div>
                        </div>
                    `}
                </div>
            </div>

            <style>
                .table-item { padding: 10px; border-radius: 8px; cursor: pointer; transition: all 0.2s; color: var(--text-dim); display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
                .table-item:hover { background: rgba(255,255,255,0.05); color: var(--text-bright); }
                .table-item.active { background: var(--primary-faded); color: var(--primary); font-weight: 500; }
                .xdb-grid { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
                .xdb-grid th { text-align: left; padding: 12px; background: rgba(255,255,255,0.05); position: sticky; top: 0; color: var(--text-bright); }
                .xdb-grid td { padding: 10px 12px; border-bottom: 1px solid var(--glass-border); color: var(--text-dim); }
                .btn-icon { background: none; border: none; cursor: pointer; font-size: 1rem; opacity: 0.6; transition: opacity 0.2s; }
                .btn-icon:hover { opacity: 1; }
                .query-editor { width: 100%; height: 120px; background: #111; color: #0f0; font-family: monospace; border: 1px solid var(--glass-border); padding: 15px; border-radius: 8px; resize: none; }
            </style>
        `;
    }

    renderQueryConsole() {
        const { query, loadingData, queryResults } = this.state;
        return html`
            <div class="query-console glass-panel" style="flex: 1; display: flex; flex-direction: column; gap: 15px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0;">🚀 SQL / XPath Query Console</h4>
                    <button class="btn primary-btn btn-sm" ?disabled=${loadingData} @click=${() => this.runQuery()}>
                        ${loadingData ? '⏳ Executing...' : '▶️ Execute Query'}
                    </button>
                </div>
                <textarea class="query-editor" 
                    placeholder="SELECT * FROM table / OR / //row[@id=1]..." 
                    @input=${(e) => this.setState({ query: e.target.value })}
                    @keydown=${(e) => {
                        if (e.ctrlKey && e.key === 'Enter') {
                            e.preventDefault();
                            this.runQuery();
                        }
                    }}>${query}</textarea>
                
                <div class="results-viewer" style="flex: 1; overflow: auto; border-top: 1px solid var(--glass-border); padding-top: 15px;">
                    <label style="font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 10px; display: block;">Query Results</label>
                    ${this.state.queryResults ? this.renderGrid(this.state.queryResults) : html`<div style="color: var(--text-dim); font-style: italic;">No results to display.</div>`}
                </div>
            </div>
        `;
    }

    renderGrid(data) {
        // Use either data keys or fetched columns
        let headers = [];
        if (data && data.length > 0) {
            headers = Object.keys(data[0]);
        } else if (this.state.columns && this.state.columns.length > 0) {
            headers = this.state.columns;
        }

        if (headers.length === 0) return '';

        return html`
            <table class="xdb-grid">
                <thead>
                    <tr>${headers.map(h => html`<th>${h}</th>`)}</tr>
                </thead>
                <tbody>
                    ${data.map(row => html`
                        <tr>${headers.map(h => html`<td>${row[h] !== undefined ? row[h] : ''}</td>`)}</tr>
                    `)}
                </tbody>
            </table>
        `;
    }
}
