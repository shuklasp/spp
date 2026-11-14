/**
 * Database Studio View Component (SPP Database Studio)
 * Provides comprehensive visual relational data browsing, schema inspection,
 * paginated row CRUD, multi-line SQL execution console, and SQL/JSON dumps.
 */

const BaseClass = window.BaseComponent || (window.SPPUX && window.SPPUX.BaseComponent) || class {
    constructor(admin, container, props) {
        this.admin = admin;
        this.container = container;
        this.props = props || {};
    }
};

export default class DatabaseView extends BaseClass {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
    }

    async onInit() {
        const hash = window.location.hash || '';
        let initialTab = 'data';
        if (hash.includes('tab=entities') || hash.includes('#entities')) initialTab = 'entities';
        else if (hash.includes('tab=xdb') || hash.includes('#xdb')) initialTab = 'xdb';
        else if (hash.includes('tab=interdb') || hash.includes('#interdb')) initialTab = 'interdb';
        else if (hash.includes('tab=sql')) initialTab = 'sql';
        else if (hash.includes('tab=erd')) initialTab = 'erd';
        else if (hash.includes('tab=migrations')) initialTab = 'migrations';
        else if (hash.includes('tab=seeders') || hash.includes('#seeders')) initialTab = 'seeders';

        this.state = {
            activeTab: initialTab, // data, sql, entities, erd, migrations, seeders, xdb, interdb, snapshots, dumps
            loading: true,
            driver: 'sqlite',
            database: '',
            databases: [],
            selectedDatabase: null,
            selectedDatabaseObj: null,
            tables: [],
            activeTable: null,
            tableSchema: null,
            // Data table state
            rows: [],
            columns: [],
            primaryKey: null,
            totalRows: 0,
            page: 1,
            pageSize: 25,
            totalPages: 1,
            sortCol: null,
            sortDir: 'ASC',
            search: '',
            loadingRows: false,
            // SQL Console state
            sqlQuery: 'SELECT * FROM spp_users LIMIT 25;',
            sqlResult: null,
            executingSql: false,
            // Dump export state
            dumpFormat: 'sql',
            dumpOutput: '',
            dumpFilename: '',
            exporting: false,
            // ERD Schema Graph state
            erdData: null,
            erdSvg: '',
            loadingErd: false,
            // Schema Snapshots state
            snapshots: [],
            loadingSnapshots: false,
            selectedSnapshot: null,
            snapshotDiff: null,
            diffLoading: false,
            // Time Machine state
            snapshotMode: 'timemachine',
            tmSnapshots: [],
            loadingTmSnapshots: false,
            selectedTmSnapshot: null,
            tmDiff: null,
            tmDiffLoading: false,
            creatingTmSnapshot: false,
            revertingTmSnapshot: false,
            // Migration Generator state
            migTable: '',
            migColumns: [
                { name: 'id', type: 'INTEGER', primary: true, nullable: false, default: '' },
                { name: 'title', type: 'VARCHAR(255)', primary: false, nullable: false, default: '' },
                { name: 'created_at', type: 'DATETIME', primary: false, nullable: true, default: 'CURRENT_TIMESTAMP' }
            ],
            generatingMigration: false,
            lastGeneratedMigration: null,
            // Seeder & Fixture state
            availableSeeders: [],
            seedingEntity: '',
            seedingCount: 10,
            seedingWorkflowState: '',
            seedingTruncate: false,
            seedingRunning: false,
            lastSeedingResult: null,
            // Connection error state
            connectionError: null
        };

        await this.fetchDatabases();
        this.loadSeeders();
    }

    async onContextChange(newApp) {
        await this.fetchDatabases(newApp);
        this.update();
    }

    async fetchDatabases(context = null) {
        this.setState({ loading: true, connectionError: null });
        try {
            const ctx = context || this.admin.selectedApp || 'default';
            const res = await this.admin.api('db_list_databases', { context: ctx });
            const data = (res.success && res.data) ? res.data : res;
            const databases = data.databases || [];

            // Auto-select:
            // 1. If context was explicitly switched or selectedDatabase is not in new list, pick the context DB
            // 2. If a database is marked is_context: true, pick that
            // 3. Else fallback to first database
            let selectedDb = this.state.selectedDatabase;
            const currentDbExists = databases.some(d => d.id === selectedDb);
            if (context || !selectedDb || !currentDbExists) {
                const contextDb = databases.find(d => d.is_context);
                selectedDb = contextDb ? contextDb.id : (databases.length > 0 ? databases[0].id : null);
            }

            const dbObj = databases.find(d => d.id === selectedDb) || null;

            this.setState({
                databases: databases,
                selectedDatabase: selectedDb,
                selectedDatabaseObj: dbObj,
                activeTable: null,
                connectionError: null
            });

            await this.fetchTables();
        } catch (e) {
            console.error("Database list error:", e);
            this.admin.notify("Failed to list databases: " + e.message, "error");
            this.setState({ loading: false, connectionError: e.message });
        }
    }

    async selectDatabase(dbId) {
        if (this.state.selectedDatabase === dbId) return;
        const dbObj = this.state.databases.find(d => d.id === dbId) || null;
        this.setState({
            selectedDatabase: dbId,
            selectedDatabaseObj: dbObj,
            activeTable: null,
            tableSchema: null,
            rows: [],
            columns: [],
            totalRows: 0,
            page: 1,
            search: '',
            connectionError: null,
            loading: true
        });
        this.update();

        // Update default query template depending on database type
        if (dbObj && dbObj.type === 'nosql') {
            this.state.sqlQuery = 'SELECT * FROM students LIMIT 25;';
        } else {
            this.state.sqlQuery = 'SELECT * FROM spp_users LIMIT 25;';
        }

        await this.fetchTables();
        this.update();
    }

    async fetchTables() {
        this.setState({ loading: true, connectionError: null });
        try {
            const res = await this.admin.api('db_list_tables', {
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });

            if (res && res.success === false) {
                const errMsg = res.message || res.error || "Failed to list tables";
                this.setState({
                    connectionError: errMsg,
                    tables: [],
                    loading: false
                });
                return;
            }

            const data = (res.success && res.data) ? res.data : res;
            const tables = data.tables || [];

            this.setState({
                tables: tables,
                driver: data.driver || 'sqlite',
                database: data.database || 'spp',
                connectionError: null,
                loading: false
            });

            if (tables.length > 0) {
                const stillExists = tables.some(t => t.name === this.state.activeTable);
                await this.selectTable(stillExists ? this.state.activeTable : tables[0].name);
            } else {
                this.setState({
                    activeTable: null,
                    tableSchema: null,
                    rows: [],
                    columns: [],
                    totalRows: 0,
                    loadingRows: false
                });
            }
        } catch (e) {
            console.error("Database fetch error:", e);
            this.setState({
                connectionError: e.message || "Failed to load tables",
                tables: [],
                loading: false
            });
            this.admin.notify("Failed to load tables: " + e.message, "error");
        }
    }

    async selectTable(tableName) {
        this.setState({
            activeTable: tableName,
            page: 1,
            search: '',
            sortCol: null,
            loadingRows: true
        });
        this.update();

        try {
            const [descRes, dataRes] = await Promise.all([
                this.admin.api('db_describe_table', {
                    table: tableName,
                    connection: this.state.selectedDatabase,
                    context: this.admin.selectedApp
                }),
                this.admin.api('db_query_table', {
                    table: tableName,
                    page: 1,
                    page_size: this.state.pageSize,
                    connection: this.state.selectedDatabase,
                    context: this.admin.selectedApp
                })
            ]);

            const descData = (descRes.success && descRes.data) ? descRes.data : descRes;
            const queryData = (dataRes.success && dataRes.data) ? dataRes.data : dataRes;

            this.setState({
                tableSchema: descData.columns || [],
                primaryKey: descData.primary_key || queryData.primary_key,
                columns: descData.columns || [],
                rows: queryData.rows || [],
                totalRows: queryData.total_rows || 0,
                totalPages: queryData.total_pages || 1,
                loadingRows: false
            });
        } catch (e) {
            this.admin.notify("Failed to query table: " + e.message, "error");
            this.setState({ loadingRows: false });
        }
        this.update();
    }

    async changePage(newPage) {
        if (newPage < 1 || newPage > this.state.totalPages) return;
        this.setState({ page: newPage, loadingRows: true });
        this.update();

        try {
            const res = await this.admin.api('db_query_table', {
                table: this.state.activeTable,
                page: newPage,
                page_size: this.state.pageSize,
                sort_col: this.state.sortCol,
                sort_dir: this.state.sortDir,
                search: this.state.search,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({
                rows: data.rows || [],
                totalRows: data.total_rows || 0,
                totalPages: data.total_pages || 1,
                loadingRows: false
            });
        } catch (e) {
            this.admin.notify("Page fetch error: " + e.message, "error");
            this.setState({ loadingRows: false });
        }
        this.update();
    }

    async sortTable(columnName) {
        let dir = 'ASC';
        if (this.state.sortCol === columnName && this.state.sortDir === 'ASC') {
            dir = 'DESC';
        }
        this.setState({ sortCol: columnName, sortDir: dir, loadingRows: true });
        this.update();

        try {
            const res = await this.admin.api('db_query_table', {
                table: this.state.activeTable,
                page: 1,
                page_size: this.state.pageSize,
                sort_col: columnName,
                sort_dir: dir,
                search: this.state.search,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({
                rows: data.rows || [],
                page: 1,
                loadingRows: false
            });
        } catch (e) {
            this.setState({ loadingRows: false });
        }
        this.update();
    }

    async applySearch(e) {
        if (e) e.preventDefault();
        const searchInput = document.getElementById('table-search-input')?.value.trim() || '';
        this.setState({ search: searchInput, page: 1, loadingRows: true });
        this.update();

        try {
            const res = await this.admin.api('db_query_table', {
                table: this.state.activeTable,
                page: 1,
                page_size: this.state.pageSize,
                search: searchInput,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({
                rows: data.rows || [],
                totalRows: data.total_rows || 0,
                totalPages: data.total_pages || 1,
                loadingRows: false
            });
        } catch (e) {
            this.setState({ loadingRows: false });
        }
        this.update();
    }

    editCell(tdElement, colName, rowIdx) {
        if (!tdElement || tdElement.querySelector('.inline-cell-input')) return;

        const row = this.state.rows[rowIdx];
        if (!row) return;

        const originalVal = row[colName];
        const originalHtml = tdElement.innerHTML;
        const currentText = originalVal !== null && originalVal !== undefined ? String(originalVal) : '';

        tdElement.innerHTML = `
            <input type="text" class="inline-cell-input" 
                value="${this.escapeHtml(currentText)}" 
                style="width:100%; box-sizing:border-box; padding:4px 8px; font-size:0.85rem; font-family:inherit; background:#0b132b; border:1px solid #38bdf8; border-radius:4px; color:#f8fafc; outline:none; box-shadow:0 0 8px rgba(56, 189, 248, 0.4);" />
        `;

        const input = tdElement.querySelector('input');
        if (!input) return;

        input.focus();
        input.select();

        let committed = false;

        const cancel = () => {
            if (committed) return;
            committed = true;
            tdElement.innerHTML = originalHtml;
        };

        const commit = async () => {
            if (committed) return;
            committed = true;
            const newVal = input.value.trim();
            if (newVal === (originalVal !== null ? String(originalVal).trim() : '')) {
                tdElement.innerHTML = originalHtml;
                return;
            }

            tdElement.innerHTML = `<span style="font-size:0.75rem; color:#38bdf8;">Saving...</span>`;

            try {
                const pk = this.state.primaryKey;
                const pkVal = pk ? row[pk] : null;

                const res = await this.admin.api('db_save_row', {
                    table: this.state.activeTable,
                    data: { [colName]: newVal === '' ? null : newVal },
                    primary_key: pk,
                    primary_key_value: pkVal,
                    connection: this.state.selectedDatabase,
                    context: this.admin.selectedApp
                });

                if (res && res.success) {
                    this.state.rows[rowIdx][colName] = newVal === '' ? null : newVal;
                    const display = newVal === '' ? '<em style="color:var(--text-dim)">NULL</em>' : this.escapeHtml(newVal);
                    tdElement.innerHTML = `<span class="cell-content">${display}</span>`;
                    tdElement.style.transition = 'background 0.3s ease';
                    tdElement.style.background = 'rgba(16, 185, 129, 0.25)';
                    setTimeout(() => {
                        tdElement.style.background = '';
                    }, 1200);
                    this.admin.notify(`Updated '${colName}' to '${newVal}'`, "success");
                } else {
                    tdElement.innerHTML = originalHtml;
                    this.admin.notify(res.error || "Failed to update cell.", "error");
                }
            } catch (err) {
                tdElement.innerHTML = originalHtml;
                this.admin.notify("Update error: " + err.message, "error");
            }
        };

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                commit();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancel();
            }
        });

        input.addEventListener('blur', () => {
            commit();
        });
    }

    exportCurrentTableCsv() {
        if (!this.state.rows || this.state.rows.length === 0) {
            this.admin.notify("No records available to export.", "warning");
            return;
        }
        const cols = this.state.columns.map(c => c.name);
        let csv = cols.map(c => `"${c.replace(/"/g, '""')}"`).join(',') + '\r\n';
        this.state.rows.forEach(r => {
            csv += cols.map(c => {
                const v = r[c];
                if (v === null || v === undefined) return '""';
                return `"${String(v).replace(/"/g, '""')}"`;
            }).join(',') + '\r\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.state.activeTable || 'table'}_export_${new Date().toISOString().slice(0,10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        this.admin.notify(`Exported ${this.state.rows.length} rows to CSV.`, "success");
    }

    exportCurrentTableJson() {
        if (!this.state.rows || this.state.rows.length === 0) {
            this.admin.notify("No records available to export.", "warning");
            return;
        }
        const jsonStr = JSON.stringify(this.state.rows, null, 2);
        const blob = new Blob([jsonStr], { type: 'application/json;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.state.activeTable || 'table'}_export_${new Date().toISOString().slice(0,10)}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        this.admin.notify(`Exported ${this.state.rows.length} rows to JSON.`, "success");
    }

    openRowModal(row = null) {
        const isEdit = !!row;
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = isEdit ? `Edit Row in ${this.state.activeTable}` : `Insert New Row into ${this.state.activeTable}`;

        const pk = this.state.primaryKey;
        const pkVal = isEdit ? row[pk] : null;

        const inputsHtml = (this.state.columns || []).map(col => {
            const isPkCol = col.name === pk;
            const currentVal = isEdit && row ? (row[col.name] !== null ? String(row[col.name]) : '') : '';

            return `
                <div class="input-group" style="margin-bottom:0.75rem;">
                    <label style="display:flex; justify-content:space-between;">
                        <span>${this.escapeHtml(col.name)} ${isPkCol ? '<strong style="color:var(--primary)">(Primary Key)</strong>' : ''}</span>
                        <small style="color:var(--text-dim)">${this.escapeHtml(col.type)} ${col.nullable ? '(nullable)' : '(required)'}</small>
                    </label>
                    <input type="text" id="row-col-${this.escapeHtml(col.name)}" 
                        value="${this.escapeHtml(currentVal)}" 
                        placeholder="${col.default !== null ? 'Default: ' + this.escapeHtml(col.default) : (col.nullable ? 'NULL' : '')}"
                        ${isPkCol && isEdit ? 'readonly style="opacity:0.7;"' : ''}>
                </div>
            `;
        }).join('');

        document.getElementById('modal-body').innerHTML = `
            <div style="max-height:60vh; overflow-y:auto; padding:0.5rem;">
                ${inputsHtml}
            </div>
        `;

        modal.classList.add('active');

        const saveBtn = document.getElementById('modal-save');
        saveBtn.onclick = async () => {
            const payload = {};
            (this.state.columns || []).forEach(col => {
                const el = document.getElementById(`row-col-${col.name}`);
                if (el) {
                    payload[col.name] = el.value !== '' ? el.value : (col.nullable ? null : '');
                }
            });

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const res = await this.admin.api('db_save_row', {
                    table: this.state.activeTable,
                    data: payload,
                    primary_key: pk,
                    primary_key_value: pkVal,
                    connection: this.state.selectedDatabase,
                    context: this.admin.selectedApp
                });

                if (res.success) {
                    this.admin.notify(res.message || "Row saved successfully.", "success");
                    modal.classList.remove('active');
                    await this.selectTable(this.state.activeTable);
                } else {
                    this.admin.notify(res.error || "Failed to save row.", "error");
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

    async deleteRow(row) {
        const pk = this.state.primaryKey;
        if (!pk || row[pk] === undefined) {
            this.admin.notify("Cannot delete row: Table has no defined primary key.", "error");
            return;
        }

        const pkVal = row[pk];
        if (!confirm(`Delete row where ${pk} = '${pkVal}'? This action cannot be undone.`)) {
            return;
        }

        try {
            const res = await this.admin.api('db_delete_row', {
                table: this.state.activeTable,
                primary_key: pk,
                primary_key_value: pkVal,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });

            if (res.success) {
                this.admin.notify(res.message || "Row deleted.", "success");
                await this.selectTable(this.state.activeTable);
            } else {
                this.admin.notify(res.error || "Failed to delete row.", "error");
            }
        } catch (e) {
            this.admin.notify("Delete error: " + e.message, "error");
        }
    }

    async executeSql() {
        const sql = document.getElementById('sql-console-input')?.value.trim() || this.state.sqlQuery;
        if (!sql) {
            this.admin.notify("Please enter a SQL statement to execute.", "error");
            return;
        }

        this.setState({ sqlQuery: sql, executingSql: true, sqlResult: null });
        this.update();

        try {
            const res = await this.admin.api('db_execute_sql', {
                sql: sql,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ sqlResult: data, executingSql: false });
        } catch (e) {
            this.admin.notify("Execution error: " + e.message, "error");
            this.setState({ executingSql: false });
        }
        this.update();
    }

    async exportDump(format = 'sql') {
        this.setState({ exporting: true, dumpFormat: format, dumpOutput: '' });
        this.update();

        try {
            const res = await this.admin.api('db_export_dump', {
                tables: this.state.activeTable ? [this.state.activeTable] : [],
                format: format,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({
                dumpOutput: data.dump || '',
                dumpFilename: data.filename || 'dump.sql',
                exporting: false
            });
        } catch (e) {
            this.admin.notify("Export error: " + e.message, "error");
            this.setState({ exporting: false });
        }
        this.update();
    }

    downloadDump() {
        if (!this.state.dumpOutput) return;
        const blob = new Blob([this.state.dumpOutput], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = this.state.dumpFilename || 'spp_dump.sql';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    async switchTab(tab) {
        this.setState({ activeTab: tab });
        this.update();
        if (tab === 'erd' && (!this.state.erdData || !this.state.erdSvg)) {
            await this.loadErd();
        } else if (tab === 'snapshots') {
            await this.loadTimeMachineSnapshots();
            if (!this.state.snapshots || this.state.snapshots.length === 0) {
                await this.loadSnapshots();
            }
        } else if (['entities', 'xdb', 'interdb'].includes(tab)) {
            await this.mountSubView(tab);
        }
    }

    async mountSubView(tab) {
        const mount = this.container ? this.container.querySelector('#database-subview-mount') : document.getElementById('database-subview-mount');
        if (!mount) return;
        mount.innerHTML = '<div class="loading-state" style="padding: 2rem; text-align: center;"><div class="sppux-spinner"></div> Loading Workspace...</div>';
        try {
            let mod;
            if (tab === 'entities') mod = await import('./entities.js');
            else if (tab === 'xdb') mod = await import('./xdb.js');
            else if (tab === 'interdb') mod = await import('./interdb.js');
            if (mod && mod.default) {
                mount.innerHTML = '';
                const instance = new mod.default(this.admin, mount, { app: this.admin.selectedApp });
                if (instance.onInit) await instance.onInit();
                if (typeof instance.update === 'function') instance.update();
                else if (typeof instance.render === 'function') mount.innerHTML = instance.render();
            }
        } catch (e) {
            mount.innerHTML = `<div class="error-state" style="padding:2rem; color:#ef4444; text-align:center;">Failed to load ${tab}: ${this.escapeHtml(e.message)}</div>`;
        }
    }

    render() {
        if (this.state.loading) {
            return `<div class="loading-state" style="padding:4rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:1rem; color:var(--text-dim);">Connecting to Database Engine...</div></div>`;
        }

        const currentApp = this.admin.selectedApp || 'default';
        const contextDbs = (this.state.databases || []).filter(d => d.is_context);
        const appDbs = (this.state.databases || []).filter(d => d.is_app && !d.is_context);
        const relationalDbs = (this.state.databases || []).filter(d => d.type === 'relational' && !d.is_context && !d.is_app);
        const xdbDbs = (this.state.databases || []).filter(d => d.type === 'nosql' && !d.is_context && !d.is_app);
        const currentDbObj = (this.state.databases || []).find(d => d.id === this.state.selectedDatabase) || null;

        let selectorOptionsHtml = '';
        if (contextDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="⭐ Active Context Databases (${this.escapeHtml(currentApp)})">`;
            contextDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml(d.engine.toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }
        if (appDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="📱 Application Database Configurations (from app etc/)">`;
            appDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml(d.engine.toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }
        if (relationalDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="🗄️ System &amp; Local Relational Databases (SQLite / Profiles)">`;
            relationalDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml(d.engine.toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }
        if (xdbDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="⚡ Native XDB NoSQL Collections">`;
            xdbDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml(d.engine.toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }

        return `
            <div class="database-view-wrapper" style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Multi-Engine Database Selector Bar -->
                <div class="glass-panel" style="padding:0.9rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); background:linear-gradient(135deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%);">
                    <div style="display:flex; align-items:center; gap:0.9rem; flex:1; min-width:320px;">
                        <div style="font-size:1.4rem; background:var(--primary-subtle); border:1px solid var(--primary); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                            ${this.state.driver === 'xdb' ? '⚡' : '🗄️'}
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                                <label style="font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-dim);">
                                    Active Database Connection
                                </label>
                                <span class="suite-pill ${this.state.driver === 'xdb' ? 'suite-pill-warning' : 'suite-pill-success'}" style="font-size:0.65rem; padding:1px 6px;">
                                    ${this.escapeHtml((this.state.driver || 'sqlite').toUpperCase())}
                                </span>
                                ${currentDbObj && currentDbObj.is_context ? `<span class="suite-pill suite-pill-primary" style="font-size:0.65rem; padding:1px 6px;">Context Active: ${this.escapeHtml(currentApp)}</span>` : ''}
                            </div>
                            <select class="app-context-select" onchange="window.databaseViewInstance.selectDatabase(this.value)" style="width:100%; max-width:550px; font-weight:600; font-size:0.875rem; padding:6px 10px; border-radius:var(--radius-sm); background:rgba(0,0,0,0.3); border:1px solid var(--glass-border); color:var(--text-bright);">
                                ${selectorOptionsHtml}
                            </select>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div style="text-align:right; font-size:0.75rem; color:var(--text-dim);">
                            <div>Available: <strong style="color:var(--text-bright);">${(this.state.databases || []).length} databases</strong></div>
                            <div>Context: <strong style="color:var(--primary);">${this.escapeHtml(currentApp)}</strong></div>
                        </div>
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.fetchDatabases()" title="Refresh database discovery">
                            🔄 Refresh
                        </button>
                        <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.openConfigModal()" title="Configure database connection profiles">
                            ⚙️ Database Settings
                        </button>
                    </div>
                </div>

                ${this.state.connectionError ? `
                    <!-- Connection Failure Graceful Recovery Card -->
                    <div class="glass-panel" style="padding:2.75rem 2rem; text-align:center; border:1px solid rgba(239,68,68,0.35); background:radial-gradient(ellipse at top, rgba(239,68,68,0.1) 0%, rgba(15,23,42,0.6) 80%); border-radius:var(--radius-md); box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                        <div style="font-size:3rem; margin-bottom:0.75rem;">⚠️</div>
                        <h3 style="color:#f87171; margin:0 0 0.5rem 0; font-size:1.3rem; font-weight:700;">Database Connection Failed</h3>
                        <p style="color:var(--text-dim); margin:0 0 1.25rem 0; font-size:0.875rem;">
                            Could not connect to <strong style="color:var(--text-bright);">${this.escapeHtml(currentDbObj ? currentDbObj.name : this.state.selectedDatabase)}</strong>.
                        </p>
                        <div style="max-width:700px; margin:0 auto 1.75rem auto; text-align:left; background:rgba(0,0,0,0.45); border:1px solid rgba(239,68,68,0.3); border-radius:8px; padding:0.9rem 1.25rem; font-family:'JetBrains Mono',monospace; font-size:0.82rem; color:#fca5a5; line-height:1.5; word-break:break-all;">
                            <strong>Diagnostic Error:</strong><br>
                            ${this.escapeHtml(this.state.connectionError)}
                        </div>
                        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                            <button class="btn primary-btn shine-effect" onclick="window.databaseViewInstance.openConfigModal('${this.escapeHtml(this.resolveProfileName(this.state.selectedDatabase) || '')}')">
                                ⚙️ Configure Connection Credentials
                            </button>
                            <button class="btn secondary-btn" onclick="window.databaseViewInstance.fetchTables()">
                                🔄 Retry Connection
                            </button>
                        </div>
                    </div>
                ` : `
                    <!-- Hero Metrics Header -->
                    <div class="suite-hero">
                        <div class="suite-stat-card">
                            <div class="suite-stat-icon">🗄️</div>
                            <div>
                                <div class="suite-stat-value">${this.state.tables.length}</div>
                                <div class="suite-stat-label">Tables / Collections in ${this.escapeHtml(this.state.driver.toUpperCase())}</div>
                            </div>
                        </div>
                        <div class="suite-stat-card">
                            <div class="suite-stat-icon" style="background:rgba(56,189,248,0.1); color:#38bdf8;">📊</div>
                            <div>
                                <div class="suite-stat-value">${this.escapeHtml(this.state.activeTable || 'None')}</div>
                                <div class="suite-stat-label">Selected Table (${this.state.totalRows} rows)</div>
                            </div>
                        </div>
                        <div class="suite-stat-card">
                            <div class="suite-stat-icon" style="background:rgba(52,211,153,0.1); color:#34d399;">⚡</div>
                            <div>
                                <div class="suite-stat-value">${this.escapeHtml(this.state.driver.toUpperCase())}</div>
                                <div class="suite-stat-label">${this.escapeHtml(this.state.database || 'Active Store')}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Studio Navigation Tabs -->
                    <div class="suite-tabs" style="overflow-x:auto; display:flex; gap:6px; flex-wrap:wrap;">
                        <button class="suite-tab-btn ${this.state.activeTab === 'data' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('data')">
                            📑 Data Browser & Rows
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'sql' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('sql')">
                            💻 ${this.state.driver === 'xdb' ? 'XPath / SQL Query Console' : 'SQL Query Console'}
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'entities' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('entities')">
                            🏗️ Entities & Schemas
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'erd' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('erd')">
                            🗺️ Visual ERD
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'migrations' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('migrations')">
                            ⚡ Migration Generator
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'xdb' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('xdb')">
                            📦 NoSQL XDB
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'interdb' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('interdb')">
                            🌐 InterDB Mesh
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'snapshots' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('snapshots')">
                            ⏱️ Snapshots & Time Travel
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'seeders' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('seeders')">
                            🌱 Seeders &amp; Fixtures
                        </button>
                        <button class="suite-tab-btn ${this.state.activeTab === 'dumps' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('dumps')">
                            📦 Export & Dumps
                        </button>
                    </div>

                    <!-- Sub-view mount for entities / xdb / interdb -->
                    ${['entities', 'xdb', 'interdb'].includes(this.state.activeTab) ? `
                        <div id="database-subview-mount" style="min-height:500px; padding:1rem 0;"></div>
                    ` : ''}

                    <!-- Tab 1: Data Browser Split Pane -->
                    ${this.state.activeTab === 'data' ? this.renderDataBrowser() : ''}

                    <!-- Tab 2: SQL Console -->
                    ${this.state.activeTab === 'sql' ? this.renderSqlConsole() : ''}

                    <!-- Tab 3: Visual ERD -->
                    ${this.state.activeTab === 'erd' ? this.renderErdTab() : ''}

                    <!-- Tab 4: Snapshots & Time Travel -->
                    ${this.state.activeTab === 'snapshots' ? this.renderSnapshotsTab() : ''}

                    <!-- Tab 5: Migration Generator -->
                    ${this.state.activeTab === 'migrations' ? this.renderMigrationsTab() : ''}

                    <!-- Tab 6: Seeders & Fixtures -->
                    ${this.state.activeTab === 'seeders' ? this.renderSeedersTab() : ''}

                    <!-- Tab 7: Dumps -->
                    ${this.state.activeTab === 'dumps' ? this.renderDumpsTab() : ''}
                `}
            </div>
        `;
    }

    renderDataBrowser() {
        const tableListItems = (this.state.tables || []).map(t => {
            const isActive = t.name === this.state.activeTable;
            return `
                <div onclick="window.databaseViewInstance.selectTable('${this.escapeHtml(t.name)}')" 
                    style="padding:0.6rem 0.8rem; border-radius:var(--radius-sm); cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:${isActive ? 'var(--primary-subtle)' : 'transparent'}; border:1px solid ${isActive ? 'var(--primary)' : 'transparent'};">
                    <span style="font-weight:${isActive ? '700' : '500'}; color:${isActive ? 'var(--primary-hover)' : 'var(--text-main)'}; font-size:0.875rem;">
                        ${this.escapeHtml(t.name)}
                    </span>
                    <span class="suite-pill suite-pill-neutral" style="font-size:0.65rem;">
                        ${t.rows >= 0 ? t.rows : '-'}
                    </span>
                </div>
            `;
        }).join('');

        const headers = (this.state.columns || []).map(col => {
            const isSort = this.state.sortCol === col.name;
            const sortIcon = isSort ? (this.state.sortDir === 'ASC' ? '▲' : '▼') : '';
            return `
                <th class="sortable" onclick="window.databaseViewInstance.sortTable('${this.escapeHtml(col.name)}')">
                    ${this.escapeHtml(col.name)} <span style="font-size:0.65rem; color:var(--primary);">${sortIcon}</span>
                </th>
            `;
        }).join('');

        const dataRows = (this.state.rows || []).map((row, rowIdx) => {
            const pk = this.state.primaryKey;
            const pkVal = pk ? row[pk] : null;

            const cells = (this.state.columns || []).map(col => {
                const val = row[col.name];
                const display = val === null ? '<em style="color:var(--text-dim)">NULL</em>' : this.escapeHtml(String(val));
                const isPk = col.name === pk;
                const editable = !isPk && pkVal !== null && pkVal !== undefined;

                return `
                    <td class="${editable ? 'editable-cell' : ''}" 
                        ${editable ? `ondblclick="window.databaseViewInstance.editCell(this, '${this.escapeHtml(col.name)}', ${rowIdx})"` : ''}
                        title="${editable ? 'Double-click to edit cell inline' : (isPk ? 'Primary Key (immutable)' : '')}"
                        style="${editable ? 'cursor:cell;' : ''}">
                        <span class="cell-content">${display}</span>
                    </td>
                `;
            }).join('');

            const rowJson = JSON.stringify(row).replace(/"/g, '&quot;');

            return `
                <tr>
                    ${cells}
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:0.3rem;">
                            <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.openRowModal(${rowJson})" title="Open full row editor">Edit</button>
                            <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.deleteRow(${rowJson})" title="Delete row">✕</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <div class="suite-split-pane">
                <!-- Sidebar: Tables List -->
                <div class="suite-pane-sidebar">
                    <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Tables (${this.state.tables.length})</div>
                    <div style="display:flex; flex-direction:column; gap:0.25rem;">
                        ${tableListItems}
                    </div>
                </div>

                <!-- Main: Table Rows & Schema -->
                <div class="suite-pane-main">
                    <div class="suite-toolbar" style="flex-wrap:wrap; gap:0.75rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">${this.escapeHtml(this.state.activeTable || 'Select Table')}</h3>
                            <span class="suite-pill suite-pill-neutral">${this.state.totalRows} Total Records</span>
                            <span style="font-size:0.75rem; color:var(--text-dim); display:inline-flex; align-items:center; gap:0.35rem; background:rgba(255,255,255,0.03); padding:3px 8px; border-radius:12px; border:1px solid var(--glass-border);">
                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#10b981;"></span>
                                Double-click any cell to edit inline
                            </span>
                        </div>
                        <div style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap;">
                            <input type="text" id="table-search-input" class="suite-search-input" value="${this.escapeHtml(this.state.search)}" placeholder="Search across columns..." onkeydown="if(event.key==='Enter') window.databaseViewInstance.applySearch(event)">
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.applySearch(event)">Search</button>
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.exportCurrentTableCsv()" title="Export current records to CSV">📥 CSV</button>
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.exportCurrentTableJson()" title="Export current records to JSON">📥 JSON</button>
                            <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.openRowModal()">+ Insert Row</button>
                        </div>
                    </div>

                    ${this.state.loadingRows ? `
                        <div style="padding:3rem; text-align:center;"><div class="sppux-spinner"></div></div>
                    ` : `
                        <div class="suite-table-wrapper">
                            <table class="suite-table">
                                <thead>
                                    <tr>
                                        ${headers}
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${dataRows || '<tr><td colspan="20" style="text-align:center; padding:2rem; color:var(--text-dim);">No records found in table.</td></tr>'}
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.75rem; font-size:0.85rem; color:var(--text-dim);">
                            <div>Showing page <strong>${this.state.page}</strong> of <strong>${this.state.totalPages}</strong></div>
                            <div style="display:flex; gap:0.5rem;">
                                <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.changePage(${this.state.page - 1})" ${this.state.page <= 1 ? 'disabled' : ''}>← Previous</button>
                                <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.changePage(${this.state.page + 1})" ${this.state.page >= this.state.totalPages ? 'disabled' : ''}>Next →</button>
                            </div>
                        </div>
                    `}
                </div>
            </div>
        `;
    }

    renderSqlConsole() {
        const res = this.state.sqlResult;
        const isXdb = this.state.driver === 'xdb';

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">
                            ${isXdb ? '⚡ XDB NoSQL Query & XPath Console' : 'Interactive SQL Query Console'}
                        </h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            ${isXdb 
                                ? 'Execute SQL statements or XPath expressions against native XDB NoSQL collections.' 
                                : 'Execute raw SELECT queries, DML updates, or DDL migrations with live execution timing.'}
                            ${isXdb ? '<span style="color:#fbbf24; font-weight:600; display:block; margin-top:2px;">💡 Tip: Supports SQL (<code>SELECT * FROM students</code>) and XPath (<code>/school/students</code>)</span>' : ''}
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        ${isXdb ? `
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = 'SELECT * FROM students LIMIT 25;'">Sample SQL</button>
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = '/school/students'">XPath Query</button>
                        ` : `
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = 'SELECT * FROM spp_users LIMIT 25;'">Users</button>
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = 'SELECT * FROM spp_migrations;'">Migrations</button>
                        `}
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.executeSql()" ${this.state.executingSql ? 'disabled' : ''}>
                            ${this.state.executingSql ? 'Executing...' : '▶ Execute (Ctrl+Enter)'}
                        </button>
                    </div>
                </div>

                <textarea id="sql-console-input" rows="6" style="width:100%; font-family:'JetBrains Mono',monospace; font-size:0.875rem; padding:0.75rem; background:#040508; border:1px solid var(--glass-border); border-radius:var(--radius-sm); color:#38bdf8; line-height:1.5;" onkeydown="if((event.ctrlKey||event.metaKey)&&event.key==='Enter') window.databaseViewInstance.executeSql()">${this.escapeHtml(this.state.sqlQuery)}</textarea>

                ${res ? `
                    <div style="margin-top:1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <span style="font-size:0.85rem; color:${res.success ? '#34d399' : '#f87171'}; font-weight:600;">
                                ${res.success ? `✔ Query OK in ${res.duration_ms} ms (${res.total_rows !== undefined ? res.total_rows + ' rows' : (res.affected_rows || 0) + ' affected'})` : `✘ Error: ${this.escapeHtml(res.error || 'Unknown error')}`}
                            </span>
                        </div>

                        ${res.is_select && res.rows ? `
                            <div class="suite-table-wrapper" style="max-height:400px; overflow-y:auto;">
                                <table class="suite-table">
                                    <thead>
                                        <tr>
                                            ${(res.columns || []).map(c => `<th>${this.escapeHtml(c)}</th>`).join('')}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${res.rows.map(r => `
                                            <tr>
                                                ${(res.columns || []).map(c => `<td>${r[c] !== null ? this.escapeHtml(String(r[c])) : '<em style="color:var(--text-dim)">NULL</em>'}</td>`).join('')}
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
        `;
    }

    renderDumpsTab() {
        return `
            <div class="suite-pane-main">
                <div>
                    <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">Database Schema & Data Dump</h3>
                    <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">Generate portable SQL scripts or structured JSON snapshots for offline backup, version control, or migration.</p>
                </div>

                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <button class="btn primary-btn shine-effect" onclick="window.databaseViewInstance.exportDump('sql')" ${this.state.exporting ? 'disabled' : ''}>
                        📥 Export as SQL Script (.sql)
                    </button>
                    <button class="btn secondary-btn" onclick="window.databaseViewInstance.exportDump('json')" ${this.state.exporting ? 'disabled' : ''}>
                        📄 Export as JSON (.json)
                    </button>
                </div>

                ${this.state.dumpOutput ? `
                    <div style="margin-top:1.5rem; display:flex; flex-direction:column; gap:0.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>Preview (${this.escapeHtml(this.state.dumpFilename)}):</strong>
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.downloadDump()">⬇ Download File</button>
                        </div>
                        <pre class="suite-code-box" style="max-height:400px; overflow-y:auto;">${this.escapeHtml(this.state.dumpOutput.substring(0, 5000))}${this.state.dumpOutput.length > 5000 ? '\n\n-- [Output truncated for preview. Click Download File to get complete dump.]' : ''}</pre>
                    </div>
                ` : ''}
            </div>
        `;
    }

    // =========================================================================
    // VISUAL SCHEMA ERD METHODS
    // =========================================================================

    async loadErd() {
        this.setState({ loadingErd: true });
        this.update();
        try {
            const [graphRes, svgRes] = await Promise.all([
                this.admin.api('db_schema_graph', {
                    connection: this.state.selectedDatabase,
                    context: this.admin.selectedApp
                }),
                this.admin.api('db_export_erd_svg', {
                    connection: this.state.selectedDatabase,
                    context: this.admin.selectedApp
                })
            ]);
            const graphData = (graphRes && graphRes.success) ? graphRes : (graphRes.data || null);
            const svgData = (svgRes && svgRes.success) ? svgRes : (svgRes.data || null);
            this.setState({
                erdData: graphData,
                erdSvg: svgData ? (svgData.svg || '') : '',
                loadingErd: false
            });
        } catch (e) {
            this.admin.notify("Failed to load ERD: " + e.message, "error");
            this.setState({ loadingErd: false });
        }
        this.update();
    }

    downloadErdSvg() {
        if (!this.state.erdSvg) {
            this.admin.notify("No ERD diagram generated yet.", "warning");
            return;
        }
        const blob = new Blob([this.state.erdSvg], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const dbName = (this.state.selectedDatabase || 'database').replace(/[^a-zA-Z0-9_\-]/g, '_');
        a.download = `schema_erd_${dbName}.svg`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        this.admin.notify("ERD SVG diagram exported successfully.", "success");
    }

    renderErdTab() {
        if (this.state.loadingErd) {
            return `
                <div class="suite-pane-main" style="padding:4rem; text-align:center;">
                    <div class="sppux-spinner"></div>
                    <div style="margin-top:1rem; color:var(--text-dim);">Generating Schema Relationship Graph &amp; SVG Diagram...</div>
                </div>
            `;
        }

        const graph = this.state.erdData || { tables: [], relations: [] };
        const totalTables = graph.total_tables || (this.state.tables || []).length;
        const totalFks = graph.total_foreign_keys || 0;

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            🗺️ Visual Interactive Schema Designer (ERD)
                        </h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Vectorized Entity-Relationship diagram showing primary keys, foreign key constraints, and relational topology.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <span class="suite-pill suite-pill-primary">${totalTables} Tables</span>
                        <span class="suite-pill suite-pill-success">${totalFks} Foreign Keys</span>
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.loadErd()">🔄 Refresh ERD</button>
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.downloadErdSvg()">📥 Download SVG</button>
                    </div>
                </div>

                <!-- SVG Visual Diagram Canvas -->
                <div class="glass-panel" style="margin-top:1rem; padding:1.25rem; border-radius:var(--radius-md); background:linear-gradient(180deg, rgba(15,23,42,0.85) 0%, rgba(10,15,28,0.95) 100%); border:1px solid var(--glass-border); overflow:auto; max-height:680px; box-shadow:inset 0 2px 10px rgba(0,0,0,0.6);">
                    ${this.state.erdSvg ? `
                        <div style="display:flex; justify-content:center; align-items:center; min-width:800px;">
                            ${this.state.erdSvg}
                        </div>
                    ` : `
                        <div style="padding:4rem 2rem; text-align:center; color:var(--text-dim);">
                            <div style="font-size:2.5rem; margin-bottom:0.75rem;">🗺️</div>
                            <div style="font-size:1rem; font-weight:600; color:var(--text-bright);">No ERD diagram generated yet</div>
                            <p style="margin:0.5rem 0 1.25rem 0; font-size:0.85rem;">Click below to analyze the active schema and build the relational graph.</p>
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.loadErd()">Generate Live ERD</button>
                        </div>
                    `}
                </div>

                <!-- Schema Table Metadata Breakdown -->
                ${graph.tables && graph.tables.length > 0 ? `
                    <div style="margin-top:1.5rem;">
                        <h4 style="margin:0 0 0.75rem 0; font-size:0.95rem; color:var(--text-bright); text-transform:uppercase; letter-spacing:0.05em;">
                            Analyzed Schema Entities (${graph.tables.length})
                        </h4>
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem;">
                            ${graph.tables.map(t => `
                                <div class="glass-panel" style="padding:0.9rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border); background:rgba(255,255,255,0.02);">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                        <strong style="color:var(--primary); font-size:0.9rem;">${this.escapeHtml(t.name)}</strong>
                                        <span class="suite-pill suite-pill-neutral" style="font-size:0.65rem;">${(t.columns || []).length} cols</span>
                                    </div>
                                    <div style="font-size:0.75rem; color:var(--text-dim); display:flex; flex-direction:column; gap:0.2rem;">
                                        <div>Primary Key: <code style="color:#38bdf8;">${this.escapeHtml(t.primary_key || 'None')}</code></div>
                                        <div>Foreign Keys: <strong>${(t.foreign_keys || []).length}</strong></div>
                                        ${(t.foreign_keys || []).map(fk => `
                                            <div style="padding-left:0.5rem; color:#34d399; font-size:0.72rem;">
                                                ↳ ${this.escapeHtml(fk.from)} → ${this.escapeHtml(fk.to_table)}.${this.escapeHtml(fk.to_column)}
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    // =========================================================================
    // POINT-IN-TIME SNAPSHOTS & TIME MACHINE (WAL + GIT + DATA)
    // =========================================================================

    async loadTimeMachineSnapshots() {
        this.setState({ loadingTmSnapshots: true });
        this.update();
        try {
            const res = await this.admin.api('timemachine_list_snapshots', {
                appname: this.admin.selectedApp
            });
            const snapshots = (res && res.success && res.snapshots) ? res.snapshots : [];
            this.setState({ tmSnapshots: snapshots, loadingTmSnapshots: false });
        } catch (e) {
            this.admin.notify("Failed to list Time Machine snapshots: " + e.message, "error");
            this.setState({ loadingTmSnapshots: false });
        }
        this.update();
    }

    async captureTimeMachineSnapshot() {
        const label = prompt("Enter a label for this Time Machine snapshot (e.g. Before Grade Submission):", "Pre-batch operation snapshot");
        if (label === null) return;

        this.setState({ creatingTmSnapshot: true });
        this.update();
        try {
            const res = await this.admin.api('timemachine_create_snapshot', {
                label: label.trim() || 'Manual Snapshot',
                connection: this.state.selectedDatabase,
                appname: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(res.message || "Time Machine snapshot captured!", "success");
                await this.loadTimeMachineSnapshots();
            } else {
                this.admin.notify((res && res.error) || "Failed to create snapshot", "error");
            }
        } catch (e) {
            this.admin.notify("Snapshot error: " + e.message, "error");
        }
        this.setState({ creatingTmSnapshot: false });
        this.update();
    }

    async diffTimeMachineSnapshot(snapshotId) {
        this.setState({ tmDiffLoading: true, selectedTmSnapshot: snapshotId, tmDiff: null });
        this.update();
        try {
            const res = await this.admin.api('timemachine_diff', {
                snapshot_id: snapshotId,
                connection: this.state.selectedDatabase,
                appname: this.admin.selectedApp
            });
            if (res && res.success) {
                this.setState({ tmDiff: res, tmDiffLoading: false });
            } else {
                this.admin.notify((res && res.error) || "Comparison failed", "error");
                this.setState({ tmDiffLoading: false });
            }
        } catch (e) {
            this.admin.notify("Diff error: " + e.message, "error");
            this.setState({ tmDiffLoading: false });
        }
        this.update();
    }

    async rollbackTimeMachineSnapshot(snapshotId, execute = false) {
        if (execute) {
            if (!confirm(`⚠️ ARE YOU SURE you want to instantly restore the database to Time Machine snapshot '${snapshotId}'?\n\nThis will atomically overwrite the active SQLite database with the snapshot state in <50ms.`)) {
                return;
            }
        }

        try {
            const res = await this.admin.api('timemachine_rollback', {
                snapshot_id: snapshotId,
                execute: execute ? 1 : 0,
                connection: this.state.selectedDatabase,
                appname: this.admin.selectedApp
            });
            if (res && res.success) {
                if (execute) {
                    this.admin.notify(res.message || "Instant rollback completed in " + (res.duration_ms || 0) + "ms!", "success");
                    await this.fetchTables();
                    await this.diffTimeMachineSnapshot(snapshotId);
                } else {
                    const preview = `Dry-run rollback verified for '${snapshotId}'.\nDatabase File: ${res.live_db_file}\nRows: ${res.snapshot_rows || 'N/A'}`;
                    alert(preview);
                }
            } else {
                this.admin.notify((res && res.error) || "Rollback failed", "error");
            }
        } catch (e) {
            this.admin.notify("Rollback error: " + e.message, "error");
        }
    }

    async deleteTimeMachineSnapshot(snapshotId) {
        if (!confirm(`Delete Time Machine snapshot '${snapshotId}'? This cannot be undone.`)) {
            return;
        }

        try {
            const res = await this.admin.api('timemachine_delete', {
                snapshot_id: snapshotId
            });
            if (res && res.success) {
                this.admin.notify("Snapshot deleted successfully.", "success");
                if (this.state.selectedTmSnapshot === snapshotId) {
                    this.setState({ selectedTmSnapshot: null, tmDiff: null });
                }
                await this.loadTimeMachineSnapshots();
            } else {
                this.admin.notify((res && res.error) || "Failed to delete snapshot", "error");
            }
        } catch (e) {
            this.admin.notify("Delete error: " + e.message, "error");
        }
    }

    async loadSnapshots() {
        this.setState({ loadingSnapshots: true });
        this.update();
        try {
            const res = await this.admin.api('db_list_snapshots', {
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            const snapshots = (res && res.success && res.snapshots) ? res.snapshots : [];
            this.setState({
                snapshots: snapshots,
                loadingSnapshots: false
            });
        } catch (e) {
            this.admin.notify("Failed to list snapshots: " + e.message, "error");
            this.setState({ loadingSnapshots: false });
        }
        this.update();
    }

    async captureSnapshot() {
        const comment = prompt("Enter a label or description for this schema snapshot:", "Pre-release migration snapshot");
        if (comment === null) return;

        try {
            const res = await this.admin.api('db_create_snapshot', {
                comment: comment.trim() || 'Manual Snapshot',
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(res.message || "Snapshot captured successfully!", "success");
                await this.loadSnapshots();
            } else {
                this.admin.notify((res && res.error) || "Failed to create snapshot", "error");
            }
        } catch (e) {
            this.admin.notify("Snapshot error: " + e.message, "error");
        }
    }

    async diffSnapshot(snapshotName) {
        this.setState({ diffLoading: true, selectedSnapshot: snapshotName, snapshotDiff: null });
        this.update();
        try {
            const res = await this.admin.api('db_compare_snapshot', {
                snapshot: snapshotName,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.setState({ snapshotDiff: res, diffLoading: false });
            } else {
                this.admin.notify((res && res.error) || "Comparison failed", "error");
                this.setState({ diffLoading: false });
            }
        } catch (e) {
            this.admin.notify("Comparison error: " + e.message, "error");
            this.setState({ diffLoading: false });
        }
        this.update();
    }

    async rollbackSnapshot(snapshotName, execute = false) {
        if (execute) {
            if (!confirm(`Are you sure you want to execute a LIVE rollback to snapshot '${snapshotName}'?\nThis will run ALTER/DROP/CREATE DDL statements on the active database.`)) {
                return;
            }
        }

        try {
            const res = await this.admin.api('db_rollback_snapshot', {
                snapshot: snapshotName,
                execute: execute ? 1 : 0,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                if (execute) {
                    this.admin.notify(res.message || "Rollback executed successfully!", "success");
                    await this.fetchTables();
                    await this.diffSnapshot(snapshotName);
                } else {
                    const stmts = res.statements || [];
                    const preview = stmts.length > 0 ? stmts.join("\n") : "No changes required (schema already identical).";
                    alert(`Dry-Run Rollback Plan (${stmts.length} statements):\n\n` + preview);
                }
            } else {
                this.admin.notify((res && res.error) || "Rollback failed", "error");
            }
        } catch (e) {
            this.admin.notify("Rollback error: " + e.message, "error");
        }
    }

    renderSnapshotsTab() {
        const isTm = (this.state.snapshotMode || 'timemachine') === 'timemachine';
        const tmSnapshots = this.state.tmSnapshots || [];
        const tmDiff = this.state.tmDiff;
        const schemaSnapshots = this.state.snapshots || [];
        const schemaDiff = this.state.snapshotDiff;

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-bottom:1px solid var(--glass-border); padding-bottom:0.75rem; margin-bottom:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            ${isTm ? '⏱️ Zero-Lock Database & Codebase Time Machine' : '📐 Point-in-Time Schema DDL Snapshots'}
                        </h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            ${isTm 
                                ? 'Instantaneous point-in-time snapshots of SQLite WAL databases and codebase git revisions with zero-lock atomic checkpoints, &lt;50ms rollbacks, and row-level diff inspection.'
                                : 'Capture immutable schema states, inspect structural DDL diffs against the live database, and execute transactional rollback migrations.'}
                        </p>
                    </div>

                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <div style="display:flex; background:rgba(0,0,0,0.3); padding:2px; border-radius:6px; border:1px solid var(--glass-border); margin-right:0.5rem;">
                            <button onclick="window.databaseViewInstance.setState({snapshotMode:'timemachine'}); window.databaseViewInstance.loadTimeMachineSnapshots();" 
                                style="border:none; padding:4px 10px; border-radius:4px; font-size:0.75rem; font-weight:600; cursor:pointer; background:${isTm ? 'var(--primary, #6366f1)' : 'transparent'}; color:${isTm ? '#fff' : 'var(--text-dim)'};">
                                🚀 Time Machine (WAL + Git)
                            </button>
                            <button onclick="window.databaseViewInstance.setState({snapshotMode:'schema'}); window.databaseViewInstance.loadSnapshots();" 
                                style="border:none; padding:4px 10px; border-radius:4px; font-size:0.75rem; font-weight:600; cursor:pointer; background:${!isTm ? 'var(--primary, #6366f1)' : 'transparent'}; color:${!isTm ? '#fff' : 'var(--text-dim)'};">
                                📐 Schema DDL
                            </button>
                        </div>

                        ${isTm ? `
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.loadTimeMachineSnapshots()">🔄 Refresh</button>
                            <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.captureTimeMachineSnapshot()" ${this.state.creatingTmSnapshot ? 'disabled' : ''}>
                                ${this.state.creatingTmSnapshot ? '⏳ Checkpointing...' : '📸 Capture Time Machine Snapshot'}
                            </button>
                        ` : `
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.loadSnapshots()">🔄 Refresh</button>
                            <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.captureSnapshot()">📸 Capture Schema Snapshot</button>
                        `}
                    </div>
                </div>

                ${isTm ? `
                    <!-- Time Machine Table -->
                    ${this.state.loadingTmSnapshots ? `
                        <div style="padding:3rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:0.5rem; color:var(--text-dim);">Loading Time Machine timeline...</div></div>
                    ` : `
                        <div class="suite-table-wrapper" style="margin-top:0.5rem;">
                            <table class="suite-table">
                                <thead>
                                    <tr>
                                        <th>Snapshot Label &amp; ID</th>
                                        <th>Created At</th>
                                        <th>Git Revision</th>
                                        <th>Driver / Size</th>
                                        <th>Tables / Rows</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tmSnapshots.length > 0 ? tmSnapshots.map(s => {
                                        const isSelected = this.state.selectedTmSnapshot === s.id;
                                        return `
                                            <tr style="${isSelected ? 'background:rgba(99,102,241,0.12);' : ''}">
                                                <td>
                                                    <div style="font-weight:700; color:var(--text-bright);">${this.escapeHtml(s.label || s.id)}</div>
                                                    <code style="font-size:0.72rem; color:var(--text-dim);">${this.escapeHtml(s.id)}</code>
                                                </td>
                                                <td style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; white-space:nowrap;">
                                                    ${this.escapeHtml(s.created_at || '—')}
                                                </td>
                                                <td>
                                                    ${s.git_commit ? `
                                                        <span class="suite-pill suite-pill-neutral" style="font-family:'JetBrains Mono',monospace; font-size:0.75rem;">
                                                            🏷️ ${this.escapeHtml(s.git_branch || 'HEAD')} @ ${this.escapeHtml(s.git_commit)}
                                                        </span>
                                                    ` : `<span style="color:var(--text-dim); font-size:0.75rem;">—</span>`}
                                                </td>
                                                <td>
                                                    <span class="suite-pill suite-pill-primary" style="font-size:0.75rem;">${this.escapeHtml((s.driver || 'sqlite').toUpperCase())}</span>
                                                    <span style="font-size:0.75rem; color:var(--text-dim); margin-left:4px;">${this.escapeHtml(s.db_size_human || '')}</span>
                                                </td>
                                                <td>
                                                    <span style="font-weight:600; color:var(--text-bright);">${s.table_count || 0}</span> tables, 
                                                    <span style="color:var(--primary, #818cf8); font-weight:600;">${(s.total_rows || 0).toLocaleString()}</span> rows
                                                </td>
                                                <td style="text-align:right; white-space:nowrap;">
                                                    <div style="display:inline-flex; gap:0.35rem;">
                                                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.diffTimeMachineSnapshot('${this.escapeHtml(s.id)}')">
                                                            🔍 Row Diff
                                                        </button>
                                                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.rollbackTimeMachineSnapshot('${this.escapeHtml(s.id)}', false)" title="Simulate rollback">
                                                            🛡️ Dry-Run
                                                        </button>
                                                        <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.rollbackTimeMachineSnapshot('${this.escapeHtml(s.id)}', true)" title="Instant &lt;50ms atomic rollback">
                                                            ⚡ Revert
                                                        </button>
                                                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.deleteTimeMachineSnapshot('${this.escapeHtml(s.id)}')" title="Delete snapshot" style="color:#f87171;">
                                                            🗑️
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('') : `
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:2.5rem; color:var(--text-dim);">
                                                No Time Machine snapshots captured yet. Click <strong>Capture Time Machine Snapshot</strong> above to create your first zero-lock baseline!
                                            </td>
                                        </tr>
                                    `}
                                </tbody>
                            </table>
                        </div>
                    `}

                    <!-- Time Machine Diff Panel -->
                    ${this.state.tmDiffLoading ? `
                        <div style="padding:2rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:0.5rem; color:var(--text-dim);">Inspecting table &amp; row-level deltas...</div></div>
                    ` : (tmDiff ? `
                        <div class="glass-panel" style="margin-top:1.5rem; padding:1.25rem; border-radius:var(--radius-md); border:1px solid ${tmDiff.net_row_delta === 0 ? 'rgba(52,211,153,0.3)' : 'rgba(251,191,36,0.3)'}; background:rgba(0,0,0,0.35);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:8px;">
                                <div>
                                    <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">
                                        Time Machine Delta: <span style="color:var(--primary);">${this.escapeHtml(tmDiff.snapshot?.label || tmDiff.snapshot?.id)}</span> vs. Live DB
                                    </h4>
                                    <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">
                                        Snapshot Date: ${this.escapeHtml(tmDiff.snapshot?.created_at || '')} | Engine: ${this.escapeHtml((tmDiff.snapshot?.driver || 'sqlite').toUpperCase())}
                                    </div>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <span class="suite-pill ${tmDiff.net_row_delta === 0 ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.8rem; padding:4px 10px;">
                                        Net Delta: ${tmDiff.net_row_delta > 0 ? '+' : ''}${tmDiff.net_row_delta} rows
                                    </span>
                                    <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.rollbackTimeMachineSnapshot('${this.escapeHtml(tmDiff.snapshot?.id)}', true)">
                                        ⚡ Revert to this State (<50ms)
                                    </button>
                                </div>
                            </div>

                            <div class="suite-table-wrapper" style="margin-top:0.75rem; max-height:280px; overflow-y:auto;">
                                <table class="suite-table">
                                    <thead>
                                        <tr>
                                            <th>Table Name</th>
                                            <th>Snapshot Rows</th>
                                            <th>Live Rows</th>
                                            <th>Row Delta</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${Object.entries(tmDiff.table_deltas || {}).map(([tbl, d]) => `
                                            <tr>
                                                <td><strong style="color:var(--text-bright);">${this.escapeHtml(tbl)}</strong></td>
                                                <td>${d.snapshot_rows}</td>
                                                <td>${d.live_rows}</td>
                                                <td style="font-weight:700; color:${d.delta > 0 ? '#34d399' : (d.delta < 0 ? '#f87171' : 'var(--text-dim)')};">
                                                    ${d.delta > 0 ? '+' : ''}${d.delta}
                                                </td>
                                                <td>
                                                    <span class="suite-pill ${d.status === 'synced' ? 'suite-pill-success' : (d.status === 'modified' ? 'suite-pill-warning' : 'suite-pill-neutral')}" style="font-size:0.7rem;">
                                                        ${d.status.toUpperCase()}
                                                    </span>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ` : '')}
                ` : `
                    <!-- Legacy Schema Snapshots Table -->
                    ${this.state.loadingSnapshots ? `
                        <div style="padding:3rem; text-align:center;"><div class="sppux-spinner"></div></div>
                    ` : `
                        <div class="suite-table-wrapper" style="margin-top:0.5rem;">
                            <table class="suite-table">
                                <thead>
                                    <tr>
                                        <th>Snapshot Name</th>
                                        <th>Label / Comments</th>
                                        <th>Created At</th>
                                        <th>Driver / Conn</th>
                                        <th>Tables</th>
                                        <th>Size</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${schemaSnapshots.length > 0 ? schemaSnapshots.map(s => {
                                        const isSelected = this.state.selectedSnapshot === s.name;
                                        return `
                                            <tr style="${isSelected ? 'background:rgba(56,189,248,0.08);' : ''}">
                                                <td>
                                                    <strong style="color:var(--text-bright);">${this.escapeHtml(s.name)}</strong>
                                                </td>
                                                <td style="color:var(--text-dim); font-size:0.82rem;">${this.escapeHtml(s.comment || '—')}</td>
                                                <td style="font-family:'JetBrains Mono',monospace; font-size:0.8rem;">${this.escapeHtml(s.created_at)}</td>
                                                <td><span class="suite-pill suite-pill-neutral">${this.escapeHtml((s.driver || 'sqlite').toUpperCase())}</span></td>
                                                <td><strong>${s.tables_count}</strong> tables</td>
                                                <td style="font-size:0.8rem; color:var(--text-dim);">${Math.round(s.file_size / 1024)} KB</td>
                                                <td style="text-align:right;">
                                                    <div style="display:inline-flex; gap:0.35rem;">
                                                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.diffSnapshot('${this.escapeHtml(s.name)}')">
                                                            🔍 Compare Diff
                                                        </button>
                                                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.rollbackSnapshot('${this.escapeHtml(s.name)}', false)" title="Simulate rollback DDL without changing database">
                                                            🛡️ Dry-Run
                                                        </button>
                                                        <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.rollbackSnapshot('${this.escapeHtml(s.name)}', true)" title="Execute live rollback to this snapshot">
                                                            ⚡ Revert Live
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('') : `
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding:2.5rem; color:var(--text-dim);">
                                                No schema snapshots captured yet. Click <strong>Capture Schema Snapshot</strong> above to create your first baseline!
                                            </td>
                                        </tr>
                                    `}
                                </tbody>
                            </table>
                        </div>
                    `}

                    <!-- Schema Comparison Diff Panel -->
                    ${this.state.diffLoading ? `
                        <div style="padding:2rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:0.5rem; color:var(--text-dim);">Computing structural schema diff...</div></div>
                    ` : (schemaDiff ? `
                        <div class="glass-panel" style="margin-top:1.5rem; padding:1.25rem; border-radius:var(--radius-md); border:1px solid ${schemaDiff.diff && schemaDiff.diff.is_synchronized ? 'rgba(52,211,153,0.3)' : 'rgba(251,191,36,0.3)'}; background:rgba(0,0,0,0.35);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                                <div>
                                    <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">
                                        Diff Analysis: Live Schema vs. <span style="color:var(--primary);">${this.escapeHtml(schemaDiff.snapshot_name)}</span>
                                    </h4>
                                    <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">Snapshot Date: ${this.escapeHtml(schemaDiff.snapshot_date)}</div>
                                </div>
                                <div>
                                    ${schemaDiff.diff && schemaDiff.diff.is_synchronized ? `
                                        <span class="suite-pill suite-pill-success" style="font-size:0.8rem; padding:4px 10px;">✔ Synchronized (Zero Drift)</span>
                                    ` : `
                                        <span class="suite-pill suite-pill-warning" style="font-size:0.8rem; padding:4px 10px;">⚠️ Schema Drift Detected</span>
                                    `}
                                </div>
                            </div>

                            ${schemaDiff.diff && schemaDiff.diff.is_synchronized ? `
                                <p style="margin:0; font-size:0.875rem; color:#34d399;">
                                    The live database schema is completely identical to this snapshot. All tables, columns, and primary keys match.
                                </p>
                            ` : `
                                <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.85rem;">
                                    ${schemaDiff.diff && schemaDiff.diff.added_tables_in_live && schemaDiff.diff.added_tables_in_live.length > 0 ? `
                                        <div style="padding:0.75rem; border-radius:6px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#fca5a5;">
                                            <strong>Tables added in live (would be dropped on rollback):</strong>
                                            <div style="margin-top:0.25rem; font-family:'JetBrains Mono',monospace;">${schemaDiff.diff.added_tables_in_live.map(t => this.escapeHtml(t)).join(', ')}</div>
                                        </div>
                                    ` : ''}

                                    ${schemaDiff.diff && schemaDiff.diff.removed_tables_in_live && schemaDiff.diff.removed_tables_in_live.length > 0 ? `
                                        <div style="padding:0.75rem; border-radius:6px; background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:#86efac;">
                                            <strong>Tables missing in live (would be recreated on rollback):</strong>
                                            <div style="margin-top:0.25rem; font-family:'JetBrains Mono',monospace;">${schemaDiff.diff.removed_tables_in_live.map(t => this.escapeHtml(t)).join(', ')}</div>
                                        </div>
                                    ` : ''}

                                    ${schemaDiff.diff && schemaDiff.diff.modified_tables && Object.keys(schemaDiff.diff.modified_tables).length > 0 ? `
                                        <div style="padding:0.75rem; border-radius:6px; background:rgba(251,191,36,0.1); border:1px solid rgba(251,191,36,0.3); color:#fde047;">
                                            <strong>Tables with Column Drift:</strong>
                                            ${Object.entries(schemaDiff.diff.modified_tables).map(([tbl, d]) => `
                                                <div style="margin-top:0.35rem; font-family:'JetBrains Mono',monospace; font-size:0.8rem;">
                                                    • <strong>${this.escapeHtml(tbl)}</strong>: 
                                                    ${d.added_columns_in_live && d.added_columns_in_live.length > 0 ? `+Cols: [${d.added_columns_in_live.join(', ')}] ` : ''}
                                                    ${d.missing_columns_in_live && d.missing_columns_in_live.length > 0 ? `-Cols: [${d.missing_columns_in_live.join(', ')}]` : ''}
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : ''}

                                    ${schemaDiff.rollback_plan && schemaDiff.rollback_plan.ddl && schemaDiff.rollback_plan.ddl.length > 0 ? `
                                        <div style="margin-top:0.5rem;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                                                <strong style="color:var(--text-bright);">Generated Rollback DDL (${schemaDiff.rollback_plan.statements_count} statements):</strong>
                                                <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.rollbackSnapshot('${this.escapeHtml(schemaDiff.snapshot_name)}', true)">
                                                    ⚡ Apply Rollback Now
                                                </button>
                                            </div>
                                            <pre class="suite-code-box" style="max-height:220px; overflow-y:auto; font-size:0.8rem;">${this.escapeHtml(schemaDiff.rollback_plan.ddl.join("\n\n"))}</pre>
                                        </div>
                                    ` : ''}
                                </div>
                            `}
                        </div>
                    ` : '')}
                `}
            </div>
        `;
    }

    // =========================================================================
    // REACTIVE SCHEMA MIGRATION GENERATOR
    // =========================================================================

    updateMigTable(val) {
        this.state.migTable = val.trim();
    }

    addMigColumn() {
        const num = this.state.migColumns.length + 1;
        this.state.migColumns.push({
            name: `column_${num}`,
            type: 'VARCHAR(255)',
            primary: false,
            nullable: true,
            default: ''
        });
        this.update();
    }

    removeMigColumn(index) {
        if (this.state.migColumns.length <= 1) {
            this.admin.notify("A table must have at least one column.", "warning");
            return;
        }
        this.state.migColumns.splice(index, 1);
        this.update();
    }

    updateMigColumn(index, field, val) {
        if (this.state.migColumns[index]) {
            this.state.migColumns[index][field] = val;
        }
    }

    async generateMigration() {
        const table = (this.state.migTable || '').trim();
        if (!table) {
            this.admin.notify("Please specify a valid table name.", "warning");
            return;
        }

        this.setState({ generatingMigration: true });
        this.update();

        try {
            const res = await this.admin.api('db_generate_migration', {
                table: table,
                columns: this.state.migColumns,
                context: this.admin.selectedApp
            });

            if (res && res.success) {
                this.setState({
                    lastGeneratedMigration: res,
                    generatingMigration: false
                });
                this.admin.notify(res.message || "Migration class generated successfully!", "success");
            } else {
                this.setState({ generatingMigration: false });
                this.admin.notify((res && res.error) || "Failed to generate migration", "error");
            }
        } catch (e) {
            this.setState({ generatingMigration: false });
            this.admin.notify("Generation error: " + e.message, "error");
        }
        this.update();
    }

    copyMigrationCode() {
        if (!this.state.lastGeneratedMigration || !this.state.lastGeneratedMigration.code) return;
        navigator.clipboard.writeText(this.state.lastGeneratedMigration.code);
        this.admin.notify("Migration PHP code copied to clipboard!", "success");
    }

    renderMigrationsTab() {
        const currentApp = this.admin.selectedApp || 'default';
        const cols = this.state.migColumns || [];
        const lastGen = this.state.lastGeneratedMigration;

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            ⚡ Reactive Schema Migration Generator
                        </h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            Scaffolds enterprise-grade <code>SPPMod\\SPPDB\\Migration</code> PHP classes with DDL identifier sanitization directly into <code>src/${this.escapeHtml(currentApp)}/migrations/</code>.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.addMigColumn()">+ Add Column</button>
                        <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.generateMigration()" ${this.state.generatingMigration ? 'disabled' : ''}>
                            ${this.state.generatingMigration ? 'Generating...' : '⚡ Generate Migration Class'}
                        </button>
                    </div>
                </div>

                <div class="glass-panel" style="margin-top:1rem; padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); background:rgba(0,0,0,0.25);">
                    <div style="margin-bottom:1.25rem; max-width:450px;">
                        <label style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.35rem;">
                            Target Table Name
                        </label>
                        <input type="text" class="suite-search-input" style="width:100%; font-family:'JetBrains Mono',monospace;" 
                            placeholder="e.g. orders, user_profiles, audit_records" 
                            value="${this.escapeHtml(this.state.migTable)}" 
                            oninput="window.databaseViewInstance.updateMigTable(this.value)">
                    </div>

                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.5rem;">
                        Column Definitions (${cols.length})
                    </div>

                    <div class="suite-table-wrapper">
                        <table class="suite-table">
                            <thead>
                                <tr>
                                    <th>Column Name</th>
                                    <th>Data Type</th>
                                    <th style="text-align:center;">Primary Key</th>
                                    <th style="text-align:center;">Nullable</th>
                                    <th>Default Value</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${cols.map((c, idx) => `
                                    <tr>
                                        <td>
                                            <input type="text" value="${this.escapeHtml(c.name)}" 
                                                onchange="window.databaseViewInstance.updateMigColumn(${idx}, 'name', this.value.trim())"
                                                style="background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:4px; padding:4px 8px; font-family:'JetBrains Mono',monospace; width:100%; max-width:180px;">
                                        </td>
                                        <td>
                                            <select onchange="window.databaseViewInstance.updateMigColumn(${idx}, 'type', this.value)"
                                                style="background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:4px; padding:4px 8px; font-size:0.8rem;">
                                                <option value="INTEGER" ${c.type === 'INTEGER' ? 'selected' : ''}>INTEGER</option>
                                                <option value="BIGINT" ${c.type === 'BIGINT' ? 'selected' : ''}>BIGINT</option>
                                                <option value="VARCHAR(255)" ${c.type === 'VARCHAR(255)' ? 'selected' : ''}>VARCHAR(255)</option>
                                                <option value="VARCHAR(100)" ${c.type === 'VARCHAR(100)' ? 'selected' : ''}>VARCHAR(100)</option>
                                                <option value="TEXT" ${c.type === 'TEXT' ? 'selected' : ''}>TEXT</option>
                                                <option value="DATETIME" ${c.type === 'DATETIME' ? 'selected' : ''}>DATETIME</option>
                                                <option value="BOOLEAN" ${c.type === 'BOOLEAN' ? 'selected' : ''}>BOOLEAN</option>
                                                <option value="DECIMAL(10,2)" ${c.type === 'DECIMAL(10,2)' ? 'selected' : ''}>DECIMAL(10,2)</option>
                                                <option value="FLOAT" ${c.type === 'FLOAT' ? 'selected' : ''}>FLOAT</option>
                                            </select>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" ${c.primary ? 'checked' : ''} 
                                                onchange="window.databaseViewInstance.updateMigColumn(${idx}, 'primary', this.checked)">
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" ${c.nullable ? 'checked' : ''} 
                                                onchange="window.databaseViewInstance.updateMigColumn(${idx}, 'nullable', this.checked)">
                                        </td>
                                        <td>
                                            <input type="text" value="${this.escapeHtml(c.default || '')}" placeholder="NULL or value"
                                                onchange="window.databaseViewInstance.updateMigColumn(${idx}, 'default', this.value.trim())"
                                                style="background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:var(--text-bright); border-radius:4px; padding:4px 8px; font-size:0.8rem; width:100%; max-width:140px;">
                                        </td>
                                        <td style="text-align:right;">
                                            <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.removeMigColumn(${idx})">✕</button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>

                ${lastGen ? `
                    <div class="glass-panel" style="margin-top:1.5rem; padding:1.25rem; border-radius:var(--radius-md); border:1px solid rgba(52,211,153,0.35); background:rgba(0,0,0,0.4);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <div>
                                <h4 style="margin:0; font-size:0.95rem; color:#34d399;">
                                    ✔ Generated Migration: <code>${this.escapeHtml(lastGen.class_name)}</code>
                                </h4>
                                <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">
                                    File written to: <code style="color:#38bdf8;">${this.escapeHtml(lastGen.file)}</code>
                                </div>
                            </div>
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.copyMigrationCode()">
                                📋 Copy Migration Code
                            </button>
                        </div>
                        <pre class="suite-code-box" style="max-height:350px; overflow-y:auto; font-size:0.82rem; color:#a7f3d0;">${this.escapeHtml(lastGen.code)}</pre>
                    </div>
                ` : ''}
            </div>
        `;
    }

    resolveProfileName(dbId) {
        if (!dbId) return 'default';
        if (dbId.startsWith('app:')) return dbId;
        if (dbId.startsWith('config:')) return dbId.substring(7);
        if (dbId === 'default') return 'default';
        return null;
    }

    async openConfigModal(targetProfile = null) {
        const modal = document.getElementById('modal-container');
        if (!modal) return;
        const modalBox = modal.querySelector('.modal-box');
        if (modalBox) {
            modalBox.style.maxWidth = '780px';
        }
        document.getElementById('modal-title').innerHTML = `⚙️ Database Connection Settings <span style="font-size:0.8rem; color:var(--text-dim); font-weight:normal;">(Global etc/db.yml &amp; Application etc/)</span>`;

        // Hide default footer buttons since we render custom footer inside
        const modalFooter = document.getElementById('modal-footer');
        if (modalFooter) {
            modalFooter.style.display = 'none';
        }

        document.getElementById('modal-body').innerHTML = `
            <div style="padding:2.5rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:0.75rem; color:var(--text-dim);">Loading connection profiles...</div></div>
        `;
        modal.classList.add('active');

        try {
            const res = await this.admin.api('db_get_configs');
            const profiles = (res && res.success && res.profiles) ? res.profiles : [];
            this.configProfiles = profiles;

            let activeProf = targetProfile;
            if (!activeProf || !profiles.some(p => p.name === activeProf)) {
                if (this.state.selectedDatabase) {
                    const resolved = this.resolveProfileName(this.state.selectedDatabase);
                    if (resolved && profiles.some(p => p.name === resolved)) {
                        activeProf = resolved;
                    }
                }
            }
            if (!activeProf && profiles.length > 0) {
                activeProf = profiles[0].name;
            }

            this.renderConfigModalContent(activeProf || 'default');
        } catch (e) {
            document.getElementById('modal-body').innerHTML = `
                <div style="padding:1.5rem; color:#f87171;">Failed to load profiles: ${this.escapeHtml(e.message)}</div>
                <div style="text-align:right; margin-top:1rem;"><button class="btn secondary-btn" onclick="window.databaseViewInstance.closeConfigModal()">Close</button></div>
            `;
        }
    }

    renderConfigModalContent(activeProfileName, isNew = false) {
        const profiles = this.configProfiles || [];
        const currentProf = isNew ? {
            name: '',
            driver: 'mysql',
            host: '127.0.0.1',
            port: 3306,
            database: '',
            username: 'root',
            password: '',
            charset: 'utf8mb4',
            timeout: 5,
            has_password: false,
            is_default: false,
            scope: 'global'
        } : (profiles.find(p => p.name === activeProfileName) || {
            name: activeProfileName,
            driver: 'sqlite',
            database: ':memory:',
            timeout: 5,
            has_password: false,
            is_default: activeProfileName === 'default',
            scope: (activeProfileName && activeProfileName.startsWith('app:')) ? 'app' : 'global'
        });

        this.currentModalProfile = currentProf;
        const isApp = currentProf.scope === 'app' || (currentProf.name && currentProf.name.startsWith('app:'));
        const appName = currentProf.app || (isApp ? currentProf.name.replace('app:', '') : '');

        // Profiles pills
        let profilePills = profiles.map(p => {
            const isSelected = !isNew && p.name === activeProfileName;
            const isAppP = p.scope === 'app' || (p.name && p.name.startsWith('app:'));
            const icon = p.is_default ? '⭐' : (isAppP ? '📱' : (p.driver === 'xdb' ? '⚡' : '🗄️'));
            const displayName = isAppP ? (p.app || p.name.replace('app:', '')) : p.name;
            return `
                <button type="button" class="btn ${isSelected ? 'primary-btn' : 'secondary-btn'} btn-sm" 
                    onclick="window.databaseViewInstance.renderConfigModalContent('${this.escapeHtml(p.name)}', false)"
                    style="${isSelected ? 'font-weight:700;' : 'opacity:0.85;'}">
                    ${icon} ${this.escapeHtml(displayName)} 
                    <span style="font-size:0.68rem; opacity:0.8; margin-left:4px;">(${this.escapeHtml(p.driver.toUpperCase())}${isAppP ? ' - App' : ''})</span>
                </button>
            `;
        }).join('');

        profilePills += `
            <button type="button" class="btn ${isNew ? 'primary-btn' : 'secondary-btn'} btn-sm" 
                onclick="window.databaseViewInstance.renderConfigModalContent('', true)" 
                style="${isNew ? 'font-weight:700;' : 'border-style:dashed; opacity:0.85;'}">
                ➕ New Profile
            </button>
        `;

        const driver = currentProf.driver || 'sqlite';

        const bodyHtml = `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Profile Switcher Bar -->
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; padding-bottom:0.75rem; border-bottom:1px solid var(--glass-border);">
                    ${profilePills}
                </div>

                <!-- Form Controls -->
                <form id="db-config-form" onsubmit="event.preventDefault(); window.databaseViewInstance.saveCurrentConfig();" style="display:flex; flex-direction:column; gap:1rem;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="input-group">
                            <label style="font-weight:600; font-size:0.8rem; color:var(--text-bright); display:flex; justify-content:space-between; align-items:center;">
                                <span>${isApp ? 'Application Name' : 'Profile Name'}</span>
                                ${currentProf.is_default ? '<small style="color:var(--primary);">(System Default)</small>' : (isApp ? '<span class="suite-pill suite-pill-primary" style="font-size:0.65rem; padding:1px 6px;">App etc/ Config</span>' : '')}
                            </label>
                            <input type="text" id="cfg-name" value="${this.escapeHtml(isApp ? appName : currentProf.name)}" 
                                placeholder="${isApp ? 'e.g. Samvaad, SPPDocs' : 'e.g. production, staging, analytics'}"
                                ${currentProf.is_default || (!isNew && currentProf.name) ? 'readonly style="opacity:0.75; cursor:not-allowed;"' : ''} 
                                required style="width:100%;">
                            ${currentProf.relative_file ? `<div style="font-size:0.75rem; color:var(--text-dim); margin-top:3px;">Configuration Source: <code style="color:var(--primary); font-size:0.72rem;">${this.escapeHtml(currentProf.relative_file)}</code></div>` : ''}
                        </div>

                        <div class="input-group">
                            <label style="font-weight:600; font-size:0.8rem; color:var(--text-bright);">Database Engine Driver</label>
                            <select id="cfg-driver" onchange="window.databaseViewInstance.onConfigDriverChange(this.value)" style="width:100%;" ${currentProf.is_default ? 'disabled style="opacity:0.75;"' : ''}>
                                <option value="mysql" ${driver === 'mysql' || driver === 'mariadb' ? 'selected' : ''}>MySQL / MariaDB</option>
                                <option value="pgsql" ${driver === 'pgsql' || driver === 'postgres' ? 'selected' : ''}>PostgreSQL</option>
                                <option value="sqlite" ${driver === 'sqlite' ? 'selected' : ''}>SQLite Database</option>
                                <option value="xdb" ${driver === 'xdb' ? 'selected' : ''}>SPP Native XDB NoSQL Store</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dynamic Fields Container -->
                    <div id="cfg-dynamic-fields">
                        ${this.renderConfigFields(driver, currentProf)}
                    </div>

                    <!-- Live Test Result Banner -->
                    <div id="cfg-test-result" style="display:none;"></div>

                    <!-- Action Bar -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--glass-border); flex-wrap:wrap; gap:0.75rem;">
                        <div>
                            ${!isNew && !currentProf.is_default ? `
                                <button type="button" class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.deleteConfigProfile('${this.escapeHtml(currentProf.name)}')">
                                    🗑️ Delete Profile
                                </button>
                            ` : ''}
                        </div>
                        <div style="display:flex; gap:0.6rem;">
                            <button type="button" id="cfg-test-btn" class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.testCurrentConfig()">
                                ⚡ Test Connection
                            </button>
                            <button type="button" class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.closeConfigModal()">
                                Cancel
                            </button>
                            <button type="submit" id="cfg-save-btn" class="btn primary-btn shine-effect btn-sm">
                                💾 Save Configuration
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        `;

        document.getElementById('modal-body').innerHTML = bodyHtml;
    }

    renderConfigFields(driver, prof = {}) {
        if (driver === 'mysql' || driver === 'mariadb') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.9rem;">
                    <div style="display:grid; grid-template-columns:3fr 1fr; gap:1rem;">
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Host / Hostname</label>
                            <input type="text" id="cfg-host" value="${this.escapeHtml(prof.host || '127.0.0.1')}" placeholder="127.0.0.1 or db.example.com" required>
                        </div>
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Port</label>
                            <input type="number" id="cfg-port" value="${this.escapeHtml(prof.port || 3306)}" placeholder="3306" required>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem;">
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Database Name</label>
                            <input type="text" id="cfg-database" value="${this.escapeHtml(prof.database || '')}" placeholder="spp_prod" required>
                        </div>
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Charset</label>
                            <input type="text" id="cfg-charset" value="${this.escapeHtml(prof.charset || 'utf8mb4')}" placeholder="utf8mb4">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Username</label>
                            <input type="text" id="cfg-username" value="${this.escapeHtml(prof.username || 'root')}" placeholder="spp_user" required>
                        </div>
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim); display:flex; justify-content:space-between;">
                                <span>Password</span>
                                ${prof.has_password ? '<small style="color:#34d399;">● Stored in etc/db.yml</small>' : ''}
                            </label>
                            <input type="password" id="cfg-password" placeholder="${prof.has_password ? '•••••••• (Leave blank to keep existing)' : 'Enter database password'}">
                        </div>
                    </div>
                </div>
            `;
        }

        if (driver === 'pgsql' || driver === 'postgres') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.9rem;">
                    <div style="display:grid; grid-template-columns:3fr 1fr; gap:1rem;">
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Host / Hostname</label>
                            <input type="text" id="cfg-host" value="${this.escapeHtml(prof.host || '127.0.0.1')}" placeholder="127.0.0.1 or postgres.local" required>
                        </div>
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Port</label>
                            <input type="number" id="cfg-port" value="${this.escapeHtml(prof.port || 5432)}" placeholder="5432" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Database Name</label>
                        <input type="text" id="cfg-database" value="${this.escapeHtml(prof.database || 'postgres')}" placeholder="spp" required>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Username</label>
                            <input type="text" id="cfg-username" value="${this.escapeHtml(prof.username || 'postgres')}" placeholder="postgres" required>
                        </div>
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim); display:flex; justify-content:space-between;">
                                <span>Password</span>
                                ${prof.has_password ? '<small style="color:#34d399;">● Stored in etc/db.yml</small>' : ''}
                            </label>
                            <input type="password" id="cfg-password" placeholder="${prof.has_password ? '•••••••• (Leave blank to keep existing)' : 'Enter database password'}">
                        </div>
                    </div>
                </div>
            `;
        }

        if (driver === 'sqlite') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.9rem;">
                    <div class="input-group">
                        <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Database Path or Target</label>
                        <input type="text" id="cfg-database" value="${this.escapeHtml(prof.database || ':memory:')}" placeholder=":memory: or var/db/app.sqlite" required>
                        <small style="color:var(--text-dim); font-size:0.72rem; margin-top:2px;">Use <code>:memory:</code> for volatile in-memory storage or a workspace relative path (e.g. <code>var/db/app.sqlite</code>).</small>
                    </div>
                    <div class="input-group">
                        <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">Busy Timeout (seconds)</label>
                        <input type="number" id="cfg-timeout" value="${this.escapeHtml(prof.timeout || 5)}" placeholder="5">
                    </div>
                </div>
            `;
        }

        if (driver === 'xdb') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.9rem;">
                    <div class="input-group">
                        <label style="font-size:0.78rem; font-weight:600; color:var(--text-dim);">XDB Collection Store Name</label>
                        <input type="text" id="cfg-database" value="${this.escapeHtml(prof.database || 'default')}" placeholder="e.g. school, default, app" required>
                        <small style="color:var(--text-dim); font-size:0.72rem; margin-top:2px;">SPP Native XML/XPath document-oriented NoSQL database store in <code>data/xdb/</code>.</small>
                    </div>
                </div>
            `;
        }

        return '';
    }

    onConfigDriverChange(newDriver) {
        const dynContainer = document.getElementById('cfg-dynamic-fields');
        if (dynContainer) {
            dynContainer.innerHTML = this.renderConfigFields(newDriver, {});
        }
    }

    readConfigFormValues() {
        const nameEl = document.getElementById('cfg-name');
        const driverEl = document.getElementById('cfg-driver');
        const hostEl = document.getElementById('cfg-host');
        const portEl = document.getElementById('cfg-port');
        const dbEl = document.getElementById('cfg-database');
        const userEl = document.getElementById('cfg-username');
        const passEl = document.getElementById('cfg-password');
        const charsetEl = document.getElementById('cfg-charset');
        const timeoutEl = document.getElementById('cfg-timeout');

        const rawName = nameEl ? nameEl.value.trim() : '';
        const driver = driverEl ? driverEl.value.trim() : 'sqlite';

        const currentActiveProf = this.currentModalProfile || {};
        const isApp = currentActiveProf.scope === 'app' || (currentActiveProf.name && currentActiveProf.name.startsWith('app:'));
        const name = isApp ? (rawName.startsWith('app:') ? rawName : 'app:' + rawName) : rawName;
        const appName = isApp ? rawName.replace('app:', '') : null;

        const config = {
            name: name,
            driver: driver,
            database: dbEl ? dbEl.value.trim() : '',
            scope: isApp ? 'app' : 'global'
        };

        if (isApp) {
            config.app = appName;
        }

        if (hostEl) config.host = hostEl.value.trim();
        if (portEl) config.port = parseInt(portEl.value.trim(), 10) || (driver === 'pgsql' ? 5432 : 3306);
        if (userEl) config.username = userEl.value.trim();
        if (passEl && passEl.value !== '') config.password = passEl.value;
        if (charsetEl) config.charset = charsetEl.value.trim();
        if (timeoutEl) config.timeout = parseInt(timeoutEl.value.trim(), 10) || 5;

        return { name, config, isApp, appName };
    }

    async testCurrentConfig() {
        const { name, config } = this.readConfigFormValues();
        const testBtn = document.getElementById('cfg-test-btn');
        const resBox = document.getElementById('cfg-test-result');
        if (!resBox) return;

        resBox.style.display = 'block';
        resBox.innerHTML = `
            <div style="background:rgba(255,255,255,0.05); border:1px solid var(--glass-border); padding:0.6rem 0.9rem; border-radius:6px; font-size:0.85rem; color:var(--text-dim); display:flex; align-items:center; gap:0.5rem;">
                <div class="sppux-spinner" style="width:14px; height:14px;"></div>
                <span>Testing connection to ${this.escapeHtml(config.driver.toUpperCase())}...</span>
            </div>
        `;

        if (testBtn) {
            testBtn.disabled = true;
            testBtn.textContent = 'Testing...';
        }

        try {
            const res = await this.admin.api('db_test_connection', { config });
            const data = (res && res.success !== undefined) ? res : (res.data || {});

            if (data.success) {
                resBox.innerHTML = `
                    <div style="background:rgba(16,185,129,0.12); border:1px solid #10b981; color:#34d399; padding:0.65rem 0.95rem; border-radius:6px; font-size:0.85rem; display:flex; align-items:center; gap:0.6rem;">
                        <span style="font-size:1.1rem;">✔</span>
                        <div><strong>Connection Successful!</strong><br><span style="font-size:0.8rem; color:rgba(255,255,255,0.85);">${this.escapeHtml(data.message || 'Connected to engine')}</span></div>
                    </div>
                `;
            } else {
                resBox.innerHTML = `
                    <div style="background:rgba(239,68,68,0.12); border:1px solid #ef4444; color:#f87171; padding:0.65rem 0.95rem; border-radius:6px; font-size:0.85rem; display:flex; align-items:flex-start; gap:0.6rem;">
                        <span style="font-size:1.1rem;">✘</span>
                        <div><strong>Connection Failed:</strong><br><span style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; word-break:break-all;">${this.escapeHtml(data.error || data.message || 'Access denied or server unreachable')}</span></div>
                    </div>
                `;
            }
        } catch (e) {
            resBox.innerHTML = `
                <div style="background:rgba(239,68,68,0.12); border:1px solid #ef4444; color:#f87171; padding:0.65rem 0.95rem; border-radius:6px; font-size:0.85rem;">
                    <strong>Error:</strong> ${this.escapeHtml(e.message)}
                </div>
            `;
        } finally {
            if (testBtn) {
                testBtn.disabled = false;
                testBtn.textContent = '⚡ Test Connection';
            }
        }
    }

    async saveCurrentConfig() {
        const { name, config, isApp, appName } = this.readConfigFormValues();
        if (!name) {
            this.admin.notify("Profile name is required.", "error");
            return;
        }

        const saveBtn = document.getElementById('cfg-save-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        try {
            const res = await this.admin.api('db_save_config', { name, config });
            if (res.success) {
                this.admin.notify(res.message || "Database configuration saved successfully.", "success");
                this.closeConfigModal();
                await this.fetchDatabases();
                // Select saved profile if it was saved
                const targetDbId = isApp ? ('app:' + appName) : (name === 'default' ? 'default' : 'config:' + name);
                await this.selectDatabase(targetDbId);
            } else {
                this.admin.notify(res.error || "Failed to save configuration.", "error");
            }
        } catch (e) {
            this.admin.notify("Save error: " + e.message, "error");
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Save Configuration';
            }
        }
    }

    async deleteConfigProfile(name) {
        if (!confirm(`Are you sure you want to delete profile '${name}'? This cannot be undone.`)) {
            return;
        }

        try {
            const res = await this.admin.api('db_delete_config', { name });
            if (res.success) {
                this.admin.notify(res.message || "Profile deleted successfully.", "success");
                await this.fetchDatabases();
                this.openConfigModal('default');
            } else {
                this.admin.notify(res.error || "Failed to delete profile.", "error");
            }
        } catch (e) {
            this.admin.notify("Delete error: " + e.message, "error");
        }
    }

    async loadSeeders() {
        try {
            const ctx = this.admin.selectedApp || 'default';
            const res = await this.admin.api('db_list_seeders', { app: ctx });
            if (res.success && res.data) {
                this.setState({ availableSeeders: res.data.seeders || [] });
            }
        } catch (e) {
            console.error("Failed to load seeders:", e);
        }
    }

    async runSmartSeed() {
        const entityEl = document.getElementById('seeder-target-entity');
        const countEl = document.getElementById('seeder-record-count');
        const stateEl = document.getElementById('seeder-workflow-state');
        const truncEl = document.getElementById('seeder-truncate-box');

        const entity = (entityEl ? entityEl.value.trim() : '') || this.state.seedingEntity || (this.state.tables[0] ? this.state.tables[0].name : '');
        const count = countEl ? parseInt(countEl.value, 10) || 10 : this.state.seedingCount;
        const workflowState = stateEl ? stateEl.value.trim() : this.state.seedingWorkflowState;
        const truncate = truncEl ? truncEl.checked : this.state.seedingTruncate;

        if (!entity) {
            this.admin.notify("Please select or specify a target entity/table.", "warning");
            return;
        }

        this.setState({
            seedingRunning: true,
            seedingEntity: entity,
            seedingCount: count,
            seedingWorkflowState: workflowState,
            seedingTruncate: truncate,
            lastSeedingResult: null
        });
        this.update();

        try {
            const res = await this.admin.api('db_seed_entity', {
                app: this.admin.selectedApp || 'default',
                entity: entity,
                count: count,
                state: workflowState || null,
                truncate: truncate
            });

            if (res.success) {
                const seededCount = res.data?.seeded || count;
                this.admin.notify(res.message || `Successfully seeded ${seededCount} records for ${entity}!`, "success");
                this.setState({
                    seedingRunning: false,
                    lastSeedingResult: {
                        type: 'smart',
                        entity: entity,
                        data: res.data
                    }
                });
                await this.fetchTables();
            } else {
                this.admin.notify(res.error || "Seeding failed.", "error");
                this.setState({ seedingRunning: false });
            }
        } catch (e) {
            this.admin.notify("Seeding error: " + e.message, "error");
            this.setState({ seedingRunning: false });
        }
        this.update();
    }

    async runSeederScript(seederClass) {
        if (!seederClass) return;
        this.setState({ seedingRunning: true, lastSeedingResult: null });
        this.update();

        try {
            const res = await this.admin.api('db_run_seeder', {
                app: this.admin.selectedApp || 'default',
                class: seederClass
            });

            if (res.success) {
                this.admin.notify(res.message || `Seeder ${seederClass} executed successfully!`, "success");
                this.setState({
                    seedingRunning: false,
                    lastSeedingResult: {
                        type: 'script',
                        seeder: seederClass,
                        logs: res.data?.logs || []
                    }
                });
                await this.fetchTables();
            } else {
                this.admin.notify(res.error || "Seeder failed.", "error");
                this.setState({ seedingRunning: false });
            }
        } catch (e) {
            this.admin.notify("Seeder execution error: " + e.message, "error");
            this.setState({ seedingRunning: false });
        }
        this.update();
    }

    renderSeedersTab() {
        const app = this.admin.selectedApp || 'default';
        const tables = this.state.tables || [];
        const seeders = this.state.availableSeeders || [];
        const isRunning = this.state.seedingRunning;
        const result = this.state.lastSeedingResult;

        return `
            <div class="glass-panel" style="padding:1.5rem; display:flex; flex-direction:column; gap:1.5rem; border-radius:var(--radius-md); border:1px solid var(--glass-border);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.25rem; font-weight:700; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            <span>🌱</span> Seeders &amp; Mock Fixtures Studio
                        </h3>
                        <p style="margin:0.25rem 0 0 0; color:var(--text-dim); font-size:0.85rem;">
                            Rapidly generate realistic mock data and workflow state records for <strong>${this.escapeHtml(app)}</strong> without writing SQL manually.
                        </p>
                    </div>
                    <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.loadSeeders(); window.databaseViewInstance.update();" title="Rescan seeders">
                        🔄 Refresh Seeders
                    </button>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:1.5rem;">
                    <!-- Left: Interactive Smart Fixture Generator -->
                    <div class="glass-panel" style="padding:1.25rem; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); border-radius:var(--radius-md);">
                        <h4 style="margin:0 0 1rem 0; font-size:1rem; font-weight:600; color:var(--primary); display:flex; align-items:center; gap:0.5rem;">
                            <span>⚡</span> Smart Fixture Generator
                        </h4>
                        
                        <div style="display:flex; flex-direction:column; gap:0.9rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.35rem; text-transform:uppercase;">
                                    Target Entity / Table
                                </label>
                                <select id="seeder-target-entity" style="width:100%; padding:8px 10px; border-radius:6px; background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.875rem;">
                                    ${tables.map(t => `<option value="${this.escapeHtml(t.name)}" ${t.name === (this.state.seedingEntity || this.state.activeTable) ? 'selected' : ''}>${this.escapeHtml(t.name)}</option>`).join('')}
                                    ${tables.length === 0 ? '<option value="users">users</option><option value="orders">orders</option>' : ''}
                                </select>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.35rem; text-transform:uppercase;">
                                        Count (Records)
                                    </label>
                                    <input type="number" id="seeder-record-count" min="1" max="1000" value="${this.state.seedingCount || 10}" style="width:100%; padding:8px 10px; border-radius:6px; background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.875rem;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.35rem; text-transform:uppercase;">
                                        Workflow State (Opt)
                                    </label>
                                    <input type="text" id="seeder-workflow-state" placeholder="e.g. pending, approved" value="${this.escapeHtml(this.state.seedingWorkflowState || '')}" style="width:100%; padding:8px 10px; border-radius:6px; background:rgba(0,0,0,0.4); border:1px solid var(--glass-border); color:var(--text-bright); font-size:0.875rem;">
                                </div>
                            </div>

                            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                                <input type="checkbox" id="seeder-truncate-box" ${this.state.seedingTruncate ? 'checked' : ''} style="cursor:pointer;">
                                <label for="seeder-truncate-box" style="font-size:0.8rem; color:var(--text-dim); cursor:pointer;">
                                    Truncate existing records before inserting
                                </label>
                            </div>

                            <button class="btn primary-btn" onclick="window.databaseViewInstance.runSmartSeed()" ${isRunning ? 'disabled' : ''} style="margin-top:0.5rem; display:flex; align-items:center; justify-content:center; gap:0.5rem;">
                                ${isRunning ? '<span class="sppux-spinner"></span> Seeding Fixtures...' : '🌱 Generate &amp; Seed Fixtures'}
                            </button>
                        </div>

                        ${result && result.type === 'smart' ? `
                            <div style="margin-top:1.25rem; padding:0.9rem; background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.3); border-radius:8px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                    <span style="font-size:0.8rem; font-weight:700; color:#4ade80;">✅ Successfully Seeded ${result.data?.seeded || 0} Records</span>
                                    <span style="font-size:0.75rem; color:var(--text-dim);">${result.data?.elapsed_ms || 0}ms</span>
                                </div>
                                ${result.data?.sample ? `
                                    <div style="font-size:0.75rem; color:var(--text-dim); margin-bottom:0.25rem;">Sample Record Generated:</div>
                                    <pre style="margin:0; padding:0.5rem; background:rgba(0,0,0,0.5); border-radius:4px; font-size:0.75rem; font-family:'JetBrains Mono',monospace; color:#a7f3d0; overflow-x:auto; max-height:160px;">${this.escapeHtml(JSON.stringify(result.data.sample, null, 2))}</pre>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>

                    <!-- Right: Discovered SPPSeeder Scripts -->
                    <div class="glass-panel" style="padding:1.25rem; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); border-radius:var(--radius-md);">
                        <h4 style="margin:0 0 0.5rem 0; font-size:1rem; font-weight:600; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                            <span>📜</span> Discovered App Seeders
                        </h4>
                        <p style="margin:0 0 1rem 0; font-size:0.8rem; color:var(--text-dim);">
                            Seeders located in <code>src/${this.escapeHtml(app)}/seeders/</code> or framework seeders.
                        </p>

                        <div style="display:flex; flex-direction:column; gap:0.75rem; max-height:280px; overflow-y:auto; padding-right:4px;">
                            ${seeders.map(s => `
                                <div style="padding:0.75rem 0.9rem; background:rgba(255,255,255,0.03); border:1px solid var(--glass-border); border-radius:8px; display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                                    <div style="overflow:hidden;">
                                        <div style="font-weight:600; font-size:0.875rem; color:var(--text-bright);">${this.escapeHtml(s.class || s.name)}</div>
                                        <div style="font-size:0.72rem; color:var(--text-dim); text-overflow:ellipsis; overflow:hidden; white-space:nowrap;" title="${this.escapeHtml(s.file || '')}">
                                            ${this.escapeHtml(s.file || '')}
                                        </div>
                                    </div>
                                    <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.runSeederScript('${this.escapeHtml(s.class || s.name)}')" ${isRunning ? 'disabled' : ''} style="flex-shrink:0;">
                                        ▶ Run
                                    </button>
                                </div>
                            `).join('')}

                            ${seeders.length === 0 ? `
                                <div style="padding:1.5rem; text-align:center; color:var(--text-dim); font-size:0.85rem; border:1px dashed var(--glass-border); border-radius:8px;">
                                    No custom <code>*Seeder.php</code> classes found for this app.<br>
                                    Use the Smart Fixture Generator on the left or create one via <code>php spp.php make:seeder</code>.
                                </div>
                            ` : ''}
                        </div>

                        ${result && result.type === 'script' ? `
                            <div style="margin-top:1.25rem; padding:0.9rem; background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.3); border-radius:8px;">
                                <div style="font-size:0.8rem; font-weight:700; color:#60a5fa; margin-bottom:0.4rem;">
                                    Execution Logs: ${this.escapeHtml(result.seeder)}
                                </div>
                                <div style="max-height:140px; overflow-y:auto; font-size:0.75rem; font-family:'JetBrains Mono',monospace; color:var(--text-dim); display:flex; flex-direction:column; gap:2px;">
                                    ${(result.logs || []).map(l => `<div>› ${this.escapeHtml(l)}</div>`).join('')}
                                    ${(!result.logs || result.logs.length === 0) ? '<div>› Seeder completed with no explicit log output.</div>' : ''}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    closeConfigModal() {
        const modal = document.getElementById('modal-container');
        if (modal) {
            modal.classList.remove('active');
            const modalBox = modal.querySelector('.modal-box');
            if (modalBox) {
                modalBox.style.maxWidth = '';
            }
            const modalFooter = document.getElementById('modal-footer');
            if (modalFooter) {
                modalFooter.style.display = '';
            }
        }
    }

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    update() {
        if (this.container) {
            this.container.innerHTML = this.render();
            window.databaseViewInstance = this;
            if (['entities', 'xdb', 'interdb'].includes(this.state.activeTab)) {
                setTimeout(() => this.mountSubView(this.state.activeTab), 10);
            }
        }
    }
}
