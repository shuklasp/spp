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
    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    escapeAttr(str) {
        return this.escapeHtml(str);
    }
};

export default class DatabaseView extends BaseClass {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
    }

    async onInit() {
        this.state = {
            activeTab: 'data', // data, sql, dumps
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
            selectedEngineFilter: 'all',
            selectedRowIds: new Set(),
            indexes: [],
            foreignKeys: [],
            tableStatus: null,
            migTable: '',
            migColumns: [
                { name: 'id', type: 'INTEGER', primary: true, nullable: false, default: '' },
                { name: 'title', type: 'VARCHAR(255)', primary: false, nullable: false, default: '' },
                { name: 'created_at', type: 'DATETIME', primary: false, nullable: true, default: 'CURRENT_TIMESTAMP' }
            ],
            generatingMigration: false,
            lastGeneratedMigration: null,
            // Connection error state
            connectionError: null,
            provisionedDatabases: [],
            provisionedUsers: [],
            remoteHosts: []
        };

        await this.fetchDatabases();
        this.fetchProvisioningData().catch(() => {});
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
                indexes: descData.indexes || [],
                foreignKeys: descData.foreign_keys || [],
                tableStatus: descData.status || null,
                rows: queryData.rows || [],
                totalRows: queryData.total_rows || 0,
                totalPages: queryData.total_pages || 1,
                loadingRows: false
            });
            if (this.state.selectedRowIds) {
                this.state.selectedRowIds.clear();
            }
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
        a.download = `${this.state.activeTable || 'table'}_export_${new Date().toISOString().slice(0, 10)}.csv`;
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
        a.download = `${this.state.activeTable || 'table'}_export_${new Date().toISOString().slice(0, 10)}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        this.admin.notify(`Exported ${this.state.rows.length} rows to JSON.`, "success");
    }

    openRowModal(row = null, isClone = false) {
        const isEdit = !!row && !isClone;
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = isClone
            ? `Duplicate / Clone Row into ${this.state.activeTable}`
            : (isEdit ? `Edit Row in ${this.state.activeTable}` : `Insert New Row into ${this.state.activeTable}`);

        const pk = this.state.primaryKey;
        const pkVal = isEdit ? row[pk] : null;

        const inputsHtml = (this.state.columns || []).map(col => {
            const isPkCol = col.name === pk;
            const currentVal = row && (!isPkCol || !isClone) ? (row[col.name] !== null && row[col.name] !== undefined ? String(row[col.name]) : '') : '';

            return `
                <div class="input-group" style="margin-bottom:0.75rem;">
                    <label style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="font-weight:600; font-size:0.85rem;">${this.escapeHtml(col.name)} ${isPkCol ? '<strong style="color:var(--primary)">(Primary Key)</strong>' : ''}</span>
                        <small style="color:var(--text-dim); font-size:0.75rem;">${this.escapeHtml(col.type || 'TEXT')} ${col.nullable ? '(nullable)' : '(required)'}</small>
                    </label>
                    <input type="text" id="row-col-${this.escapeHtml(col.name)}" 
                        value="${this.escapeHtml(currentVal)}" 
                        placeholder="${isPkCol ? (isClone ? '(Auto-generated on save)' : '(Auto)') : (col.default !== null && col.default !== undefined ? 'Default: ' + this.escapeHtml(col.default) : (col.nullable ? 'NULL' : ''))}"
                        ${isPkCol && isEdit ? 'readonly style="opacity:0.7;"' : ''}
                        class="spp-element" style="width:100%; font-size:0.85rem;">
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

    cloneRow(row) {
        if (!row) return;
        this.openRowModal(row, true);
    }

    exportSelectedCsv() {
        if (!this.state.selectedRowIds || this.state.selectedRowIds.size === 0) return;
        const pk = this.state.primaryKey || 'id';
        const selected = (this.state.rows || []).filter(r => this.state.selectedRowIds.has(String(r[pk])));
        if (selected.length === 0) return;

        const cols = this.state.columns.map(c => c.name);
        let csv = cols.map(c => `"${c.replace(/"/g, '""')}"`).join(',') + '\r\n';
        selected.forEach(r => {
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
        a.download = `${this.state.activeTable || 'table'}_selected_${selected.length}_rows.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        this.admin.notify(`Exported ${selected.length} selected row(s) to CSV.`, "success");
    }

    exportSelectedJson() {
        if (!this.state.selectedRowIds || this.state.selectedRowIds.size === 0) return;
        const pk = this.state.primaryKey || 'id';
        const selected = (this.state.rows || []).filter(r => this.state.selectedRowIds.has(String(r[pk])));
        if (selected.length === 0) return;

        const jsonStr = JSON.stringify(selected, null, 2);
        const blob = new Blob([jsonStr], { type: 'application/json;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.state.activeTable || 'table'}_selected_${selected.length}_rows.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        this.admin.notify(`Exported ${selected.length} selected row(s) to JSON.`, "success");
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

        this.saveQueryToHistory(sql);
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

    explainSql() {
        const sqlInput = document.getElementById('sql-console-input');
        const sql = sqlInput?.value?.trim() || this.state.sqlQuery;
        if (!sql) return this.admin.notify("Please enter a SQL statement first.", "warning");
        const prefix = (this.state.driver === 'sqlite') ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
        const fullExplain = sql.toUpperCase().startsWith('EXPLAIN') ? sql : (prefix + sql);
        if (sqlInput) sqlInput.value = fullExplain;
        this.executeSql();
    }

    saveQueryToHistory(sql) {
        if (!sql || sql.length < 5) return;
        try {
            let history = JSON.parse(localStorage.getItem('spp_db_query_history') || '[]');
            history = history.filter(q => q !== sql);
            history.unshift(sql);
            if (history.length > 25) history = history.slice(0, 25);
            localStorage.setItem('spp_db_query_history', JSON.stringify(history));
        } catch (e) { }
    }

    getQueryHistory() {
        try {
            return JSON.parse(localStorage.getItem('spp_db_query_history') || '[]');
        } catch (e) {
            return [];
        }
    }

    async exportDump(format = 'sql', scope = 'active') {
        this.setState({ exporting: true, dumpFormat: format, dumpOutput: '' });
        this.update();

        const tables = (scope === 'all') ? [] : (this.state.activeTable ? [this.state.activeTable] : []);

        try {
            const res = await this.admin.api('db_export_dump', {
                tables: tables,
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
            this.admin.notify(`Export ready: ${data.filename || 'dump'}`, 'success');
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

    handleImportFile(event) {
        const file = event.target?.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            const content = e.target.result;
            const textarea = document.getElementById('import-script-text');
            if (textarea) textarea.value = content;
            this.admin.notify(`Loaded ${file.name} (${Math.round(file.size / 1024)} KB) into import buffer.`, 'info');
        };
        reader.readAsText(file);
    }

    async runImport() {
        const text = document.getElementById('import-script-text')?.value?.trim();
        if (!text) return this.admin.notify('Please upload a file or paste a script to import.', 'warning');

        if (!confirm(`Are you sure you want to execute and import this script into '${this.state.selectedDatabase}'?`)) return;

        this.setState({ importing: true });
        this.admin.notify(`Executing import on '${this.state.selectedDatabase}'...`, 'info');

        try {
            const res = await this.admin.api('db_import_dump', {
                content: text,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });

            this.setState({ importing: false });
            if (res && res.success) {
                this.admin.notify(res.message || 'Import executed successfully!', 'success');
                const textarea = document.getElementById('import-script-text');
                if (textarea) textarea.value = '';
                const fileInput = document.getElementById('import-file-input');
                if (fileInput) fileInput.value = '';
                await this.fetchTables();
            } else {
                this.admin.notify(res?.error || 'Import failed.', 'error');
            }
        } catch (e) {
            this.setState({ importing: false });
            this.admin.notify('Import error: ' + e.message, 'error');
        }
    }

    async runDatabaseSearch() {
        const input = document.getElementById('db-search-input')?.value?.trim();
        if (!input) return this.admin.notify('Please enter a search keyword.', 'warning');

        this.setState({ searchingDb: true, searchTerm: input, searchedTerm: input });
        this.update();

        try {
            const res = await this.admin.api('db_search_database', {
                term: input,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });

            if (res && res.success) {
                this.setState({
                    searchingDb: false,
                    searchResults: res.results || [],
                    searchTotalMatches: res.total_matches || 0,
                    searchedTerm: res.term || input
                });
            } else {
                this.setState({ searchingDb: false, searchResults: [], searchTotalMatches: 0 });
                this.admin.notify(res?.error || 'Search failed.', 'error');
            }
        } catch (e) {
            this.setState({ searchingDb: false, searchResults: [], searchTotalMatches: 0 });
            this.admin.notify('Search failed: ' + e.message, 'error');
        }
        this.update();
    }

    async browseSearchResults(tableName, term) {
        this.state.activeTab = 'data';
        this.state.search = term;
        await this.selectTable(tableName);
        this.update();
    }

    filterByEngine(engine) {
        this.setState({ selectedEngineFilter: engine });
        this.update();
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
        } else if (tab === 'provisioning') {
            await this.fetchProvisioningData();
        }
    }

    renderProvisioningTab() {
        const allDatabases = (this.state.provisionedDatabases && this.state.provisionedDatabases.length > 0)
            ? this.state.provisionedDatabases
            : (this.state.databases || []);
        const users = this.state.provisionedUsers || [];
        const grantableDbs = allDatabases.filter(d => {
            const eng = (d.driver || d.engine || '').toLowerCase();
            return ['mysql', 'mariadb', 'pgsql', 'postgres', 'postgresql'].includes(eng);
        });

        return `
            <div class="suite-card" style="padding:1.5rem; border-radius:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                            <span>🗄️</span> Multi-Engine Database &amp; User Provisioner
                        </h3>
                        <p style="color:var(--text-dim); font-size:0.8rem; margin:0.3rem 0 0 0;">
                            Create, maintain, and delete databases across SQLite, MySQL, PostgreSQL, and SPP Native XDB. Full DDL &amp; DML management independent of active selection.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.fetchDatabases(); window.databaseViewInstance.fetchProvisioningData();">🔄 Refresh All</button>
                    </div>
                </div>

                ${this.state.provisioningConnected === false ? `
                    <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:8px; padding:0.9rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.8rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <span style="font-size:1.5rem;">⚙️</span>
                            <div>
                                <strong style="color:#f59e0b; font-size:0.9rem;">MySQL Engine Administrative Profile Setup</strong>
                                <div style="font-size:0.78rem; color:var(--text-dim); margin-top:2px;">
                                    SQLite &amp; XDB engines are fully operational. To provision real MySQL databases, users, and grant privileges, configure your administrative profile once.
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.openQuickDbSetupModal('mysql')">
                                🚀 Setup MySQL Engine
                            </button>
                        </div>
                    </div>
                ` : ''}

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
                    <!-- Create Database Box (Multi-Engine) -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 1rem 0; font-size:0.95rem; color:var(--text); display:flex; align-items:center; gap:0.4rem;">
                            <span>➕</span> Create New Database
                        </h4>
                        
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Database Type / Engine:</label>
                            <select id="prov-db-engine" class="spp-element" onchange="window.databaseViewInstance.onProvEngineChange(this.value)" style="width:100%; font-size:0.85rem; font-weight:600; padding:6px 10px;">
                                <option value="sqlite">SQLite (Serverless File DB - Recommended)</option>
                                <option value="mysql">MySQL / MariaDB (Relational Server)</option>
                                <option value="pgsql">PostgreSQL (Relational Server)</option>
                                <option value="xdb">SPP Native XDB (NoSQL Key-Doc Store)</option>
                            </select>
                        </div>

                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Database Name:</label>
                            <input type="text" id="prov-db-name" class="spp-element" placeholder="e.g. app_portal, finance_db" style="width:100%; font-size:0.85rem;">
                        </div>

                        <!-- Engine Options: SQLite -->
                        <div id="prov-engine-sqlite-opts" style="display:block; margin-bottom:1rem; font-size:0.75rem; color:var(--text-dim); background:rgba(255,255,255,0.02); padding:8px 10px; border-radius:6px; border:1px solid var(--border);">
                            📁 Storage: <code>var/db/&lt;name&gt;.sqlite</code> &bull; Journal: <code>WAL</code> (Zero configuration, high-speed transactional ACID storage).
                        </div>

                        <!-- Engine Options: MySQL -->
                        <div id="prov-engine-mysql-opts" style="display:none; margin-bottom:1rem;">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem;">
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Charset:</label>
                                    <select id="prov-db-charset" class="spp-element" style="width:100%; font-size:0.8rem;">
                                        <option value="utf8mb4">utf8mb4 (Recommended)</option>
                                        <option value="utf8">utf8</option>
                                        <option value="latin1">latin1</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Collation:</label>
                                    <select id="prov-db-collation" class="spp-element" style="width:100%; font-size:0.8rem;">
                                        <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                                        <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Engine Options: PostgreSQL -->
                        <div id="prov-engine-pgsql-opts" style="display:none; margin-bottom:1rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Encoding:</label>
                            <select id="prov-db-encoding" class="spp-element" style="width:100%; font-size:0.8rem;">
                                <option value="UTF8">UTF8 (Default)</option>
                                <option value="SQL_ASCII">SQL_ASCII</option>
                            </select>
                        </div>

                        <!-- Engine Options: XDB -->
                        <div id="prov-engine-xdb-opts" style="display:none; margin-bottom:1rem; font-size:0.75rem; color:var(--text-dim); background:rgba(255,255,255,0.02); padding:8px 10px; border-radius:6px; border:1px solid var(--border);">
                            📁 Storage: <code>sppxdb/data/&lt;name&gt;</code> &bull; Native XML document &amp; high-performance binary indexed NoSQL collection.
                        </div>

                        <button class="btn primary-btn btn-sm" style="width:100%; justify-content:center;" onclick="window.databaseViewInstance.createDatabase()">
                            <span>➕ Create Database</span>
                        </button>
                    </div>

                    <!-- Create User Box -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 1rem 0; font-size:0.95rem; color:var(--text); display:flex; align-items:center; gap:0.4rem;">
                            <span>👤</span> Create Database User
                        </h4>
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Database Engine:</label>
                            <select id="prov-user-engine" class="spp-element" style="width:100%; font-size:0.85rem;">
                                <option value="mysql">MySQL / MariaDB</option>
                                <option value="pgsql">PostgreSQL</option>
                            </select>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Username:</label>
                                <input type="text" id="prov-user-name" class="spp-element" placeholder="e.g. db_user" style="width:100%; font-size:0.85rem;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Host:</label>
                                <input type="text" id="prov-user-host" class="spp-element" value="localhost" placeholder="localhost or %" style="width:100%; font-size:0.85rem;">
                            </div>
                        </div>
                        <div class="suite-form-group" style="margin-bottom:1rem;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
                                <label style="font-size:0.75rem; color:var(--text-dim);">Password:</label>
                                <a href="javascript:void(0)" style="font-size:0.75rem; color:var(--primary);" onclick="document.getElementById('prov-user-pass').value = 'DbPass!' + Math.random().toString(36).slice(2, 10)">🎲 Generate</a>
                            </div>
                            <input type="text" id="prov-user-pass" class="spp-element" placeholder="Strong password..." style="width:100%; font-size:0.85rem;">
                        </div>
                        <button class="btn primary-btn btn-sm" style="width:100%; justify-content:center;" onclick="window.databaseViewInstance.createDbUser()">
                            <span>👤 Create User</span>
                        </button>
                    </div>

                    <!-- Assign User to DB -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 1rem 0; font-size:0.95rem; color:var(--text); display:flex; align-items:center; gap:0.4rem;">
                            <span>🛡️</span> Assign User to Database
                        </h4>
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">User:</label>
                            <select id="prov-assign-user" class="spp-element" style="width:100%; font-size:0.85rem;">
                                ${users.length > 0 
                                    ? users.map(u => `<option value="${this.escapeHtml(u.user)}" data-host="${this.escapeHtml(u.host || 'localhost')}">${this.escapeHtml(u.user)}@${this.escapeHtml(u.host || 'localhost')}</option>`).join('')
                                    : '<option value="">No provisioned users found</option>'}
                            </select>
                        </div>
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Database:</label>
                            <select id="prov-assign-db" class="spp-element" style="width:100%; font-size:0.85rem;">
                                ${grantableDbs.length > 0
                                    ? grantableDbs.map(d => {
                                        const rawName = d.database || d.dbname || (d.id && d.id.startsWith('app:') ? d.id.substring(4) : (d.name || '').replace(/\s*\(.*?\)/, '').trim());
                                        const eng = (d.driver || d.engine || 'mysql').toUpperCase();
                                        return `<option value="${this.escapeHtml(rawName)}">${this.escapeHtml(rawName)} (${this.escapeHtml(eng)})</option>`;
                                    }).join('')
                                    : '<option value="">No relational databases available</option>'}
                            </select>
                        </div>
                        <div style="margin-bottom:1rem; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:6px; padding:8px 10px;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                <input type="checkbox" id="prov-priv-all" checked onchange="document.querySelectorAll('.prov-priv-cb').forEach(cb => cb.checked = this.checked)">
                                <strong style="color:var(--text-bright);">ALL PRIVILEGES</strong>
                            </label>
                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:4px 6px; margin-top:6px; font-size:0.72rem; color:var(--text-dim);">
                                <label><input type="checkbox" class="prov-priv-cb" value="SELECT" checked> SELECT</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="INSERT" checked> INSERT</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="UPDATE" checked> UPDATE</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="DELETE" checked> DELETE</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="CREATE" checked> CREATE</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="DROP" checked> DROP</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="ALTER" checked> ALTER</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="INDEX" checked> INDEX</label>
                                <label><input type="checkbox" class="prov-priv-cb" value="REFERENCES" checked> REFS</label>
                            </div>
                        </div>
                        <button class="btn secondary-btn btn-sm" style="width:100%; justify-content:center;" onclick="window.databaseViewInstance.grantPrivileges()">
                            <span>🛡️ Grant Privileges</span>
                        </button>
                    </div>
                </div>

                <!-- SPPAdmin Global Database Maintenance Suite -->
                <div style="background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.25); border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <strong style="color:#10b981; font-size:0.95rem;">🛠️ SPPAdmin Global Database Maintenance &amp; Optimization</strong>
                        <div style="color:var(--text-dim); font-size:0.8rem; margin-top:2px;">Select any discovered database to check integrity, defragment, and vacuum storage independent of active selection.</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                        <select id="global-maint-db-select" class="spp-element" style="font-size:0.8rem; padding:4px 8px; max-width:240px;">
                            ${allDatabases.map(d => `<option value="${this.escapeHtml(d.id || d.name)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)}</option>`).join('')}
                        </select>
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.checkActiveDatabase(document.getElementById('global-maint-db-select').value)">
                            🔍 Check Integrity
                        </button>
                        <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.repairActiveDatabase(document.getElementById('global-maint-db-select').value)">
                            🧹 Repair &amp; Optimize
                        </button>
                    </div>
                </div>

                <!-- Discovered & Managed Databases Table (ALL Databases Across All Engines) -->
                <div style="margin-bottom:2rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.8rem; flex-wrap:wrap; gap:0.5rem;">
                        <h4 style="margin:0; font-size:1rem; display:flex; align-items:center; gap:0.5rem;">
                            <span>Discovered &amp; Managed Databases (${allDatabases.length})</span>
                        </h4>
                        <span style="font-size:0.75rem; color:var(--text-dim);">Real-time multi-engine database registry</span>
                    </div>

                    <div class="suite-table-wrapper" style="overflow-x:auto;">
                        <table class="suite-table" style="width:100%; font-size:0.8rem;">
                            <thead>
                                <tr>
                                    <th>Database Name</th>
                                    <th>Engine</th>
                                    <th>Scope / Context</th>
                                    <th>Disk Size</th>
                                    <th style="width:280px; text-align:right;">Actions &amp; Maintenance</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${allDatabases.length === 0 ? `
                                    <tr><td colspan="5" style="text-align:center; padding:1.5rem; color:var(--text-dim);">No databases discovered. Use 'Create New Database' above to create one.</td></tr>
                                ` : allDatabases.map(d => {
                                    const engine = d.driver || d.engine || 'sqlite';
                                    const isCurrent = d.id === this.state.selectedDatabase;
                                    const isProtected = ['default', 'default.sqlite', 'spp.sqlite', 'spp', 'sys', 'mysql', 'information_schema'].includes((d.name || '').toLowerCase());
                                    return `
                                        <tr style="${isCurrent ? 'background:rgba(56,189,248,0.05);' : ''}">
                                            <td>
                                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                                    <strong style="color:var(--text-bright);">${this.escapeHtml(d.name)}</strong>
                                                    ${isCurrent ? '<span class="suite-pill suite-pill-primary" style="font-size:0.6rem;">ACTIVE</span>' : ''}
                                                </div>
                                                ${d.path ? `<div style="font-size:0.7rem; color:var(--text-dim); font-family:'JetBrains Mono',monospace;">${this.escapeHtml(d.path)}</div>` : ''}
                                            </td>
                                            <td>
                                                <span class="suite-pill ${engine === 'sqlite' ? 'suite-pill-info' : (engine === 'mysql' ? 'suite-pill-warning' : (engine === 'pgsql' ? 'suite-pill-primary' : 'suite-pill-neutral'))}" style="font-size:0.68rem; text-transform:uppercase; font-weight:700;">
                                                    ${this.escapeHtml(engine)}
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size:0.75rem; color:var(--text-dim);">
                                                    ${d.app ? `App: <strong>${this.escapeHtml(d.app)}</strong>` : (d.is_default ? 'Framework Default' : 'Global')}
                                                </span>
                                            </td>
                                            <td>
                                                <strong style="color:var(--primary); font-size:0.8rem;">
                                                    ${d.size ? (typeof d.size === 'number' ? (d.size > 1048576 ? (d.size / 1048576).toFixed(1) + ' MB' : (d.size / 1024).toFixed(1) + ' KB') : d.size) : '-'}
                                                </strong>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:inline-flex; gap:0.3rem;">
                                                    <button class="btn secondary-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.selectDatabase('${this.escapeAttr(d.id)}').then(() => window.databaseViewInstance.switchTab('data'))" title="Open in Data Browser">
                                                        ⚡ Open
                                                    </button>
                                                    <button class="btn ghost-btn btn-sm" style="padding:2px 6px; font-size:0.75rem;" onclick="window.databaseViewInstance.checkActiveDatabase('${this.escapeAttr(d.id)}')" title="Check Database Integrity">
                                                        🔍 Check
                                                    </button>
                                                    <button class="btn ghost-btn btn-sm" style="padding:2px 6px; font-size:0.75rem;" onclick="window.databaseViewInstance.repairActiveDatabase('${this.escapeAttr(d.id)}')" title="Vacuum & Optimize Storage">
                                                        🧹 Optimize
                                                    </button>
                                                    ${!isProtected ? `
                                                        <button class="btn ghost-btn btn-sm" style="color:var(--danger); padding:2px 6px; font-size:0.75rem;" onclick="window.databaseViewInstance.deleteDatabase('${this.escapeAttr(d.database || d.dbname || d.name)}', '${this.escapeAttr(d.id)}', '${this.escapeAttr(engine)}')" title="Drop Database">
                                                            🗑️ Drop
                                                        </button>
                                                    ` : '<span style="font-size:0.68rem; color:var(--text-dim); padding:2px 4px;">Protected</span>'}
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>

                ${users.length > 0 ? `
                    <!-- Provisioned Users Table -->
                    <div>
                        <h4 style="margin:0 0 0.8rem 0; font-size:0.95rem; color:var(--text-bright);">Server Database Users (${users.length})</h4>
                        <table class="suite-table" style="width:100%; font-size:0.8rem;">
                            <thead>
                                <tr><th>User</th><th>Host</th><th style="width:180px; text-align:right;">Actions</th></tr>
                            </thead>
                            <tbody>
                                ${users.map(u => `
                                    <tr>
                                        <td><strong>${this.escapeHtml(u.user)}</strong></td>
                                        <td><code>${this.escapeHtml(u.host || 'localhost')}</code></td>
                                        <td style="text-align:right;">
                                            <div style="display:inline-flex; gap:0.3rem;">
                                                <button class="btn secondary-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.changeDbUserPassword('${this.escapeHtml(u.user)}', '${this.escapeHtml(u.host || 'localhost')}')">🔑 Password</button>
                                                <button class="btn ghost-btn btn-sm" style="color:var(--danger); padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.deleteDbUser('${this.escapeHtml(u.user)}', '${this.escapeHtml(u.host || 'localhost')}')">🗑️ Drop</button>
                                            </div>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : ''}
            </div>
        `;
    }

    renderRemoteAccessTab() {
        const hosts = this.state.remoteHosts || ['127.0.0.1', '192.168.1.%'];
        return `
            <div class="suite-card" style="padding:1.5rem; border-radius:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                            <span>🌐</span> Remote Database Access Hosts
                        </h3>
                        <p style="color:var(--text-dim); font-size:0.8rem; margin:0.3rem 0 0 0;">
                            Authorize external client IP addresses or wildcards to connect to MySQL (port 3306).
                        </p>
                    </div>
                </div>

                <div style="display:flex; gap:0.8rem; margin-bottom:1.5rem; max-width:500px;">
                    <input type="text" id="remote-host-ip-input" class="spp-element" placeholder="IP address (e.g. 203.0.113.50) or %" style="flex:1; font-size:0.85rem;">
                    <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.addRemoteHost()">➕ Add Host</button>
                </div>

                <table class="suite-table" style="width:100%; max-width:600px; font-size:0.8rem;">
                    <thead><tr><th>Allowed Host / IP</th><th style="width:100px;">Action</th></tr></thead>
                    <tbody>
                        ${hosts.map(h => `
                            <tr>
                                <td><code>${this.escapeHtml(h)}</code></td>
                                <td><button class="btn ghost-btn btn-sm" style="color:var(--danger);" onclick="window.databaseViewInstance.removeRemoteHost('${this.escapeHtml(h)}')">🗑️ Remove</button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    renderRedisTab() {
        const redis = this.state.redisStatus || {
            version: '7.2', uptime: '14 days', used_memory_human: '24.5M', peak_memory_human: '42.1M', total_keys: 342, hit_rate: '94.2%', connected_clients: 3
        };
        const keys = this.state.redisKeys || [
            { key: 'spp:session:sess_a8b7c6', type: 'string' },
            { key: 'spp:cache:routes_manifest', type: 'hash' },
            { key: 'spp:queue:pending_jobs', type: 'list' }
        ];

        return `
            <div class="suite-card" style="padding:1.5rem; border-radius:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                            <span>⚡</span> Redis In-Memory Cache Studio
                            <span class="suite-pill suite-pill-success" style="font-size:0.75rem;">v${this.escapeHtml(redis.version)}</span>
                        </h3>
                        <p style="color:var(--text-dim); font-size:0.8rem; margin:0.3rem 0 0 0;">
                            Inspect memory footprints, search cache keys, monitor hit rates, and flush databases with 1 click.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.flushRedis(false)">🧹 Flush DB</button>
                        <button class="btn ghost-btn btn-sm" style="color:var(--danger);" onclick="window.databaseViewInstance.flushRedis(true)">💥 Flush All</button>
                    </div>
                </div>

                <!-- Metrics Grid -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1rem; text-align:center;">
                        <div style="font-size:0.75rem; color:var(--text-dim);">Used Memory</div>
                        <div style="font-size:1.3rem; font-weight:700; color:var(--info);">${this.escapeHtml(redis.used_memory_human)}</div>
                    </div>
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1rem; text-align:center;">
                        <div style="font-size:0.75rem; color:var(--text-dim);">Peak Memory</div>
                        <div style="font-size:1.3rem; font-weight:700; color:var(--text);">${this.escapeHtml(redis.peak_memory_human)}</div>
                    </div>
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1rem; text-align:center;">
                        <div style="font-size:0.75rem; color:var(--text-dim);">Total Keys</div>
                        <div style="font-size:1.3rem; font-weight:700; color:var(--primary);">${this.escapeHtml(redis.total_keys)}</div>
                    </div>
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1rem; text-align:center;">
                        <div style="font-size:0.75rem; color:var(--text-dim);">Hit Rate</div>
                        <div style="font-size:1.3rem; font-weight:700; color:var(--success);">${this.escapeHtml(redis.hit_rate)}</div>
                    </div>
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1rem; text-align:center;">
                        <div style="font-size:0.75rem; color:var(--text-dim);">Connected Clients</div>
                        <div style="font-size:1.3rem; font-weight:700; color:var(--warning);">${this.escapeHtml(redis.connected_clients)}</div>
                    </div>
                </div>

                <!-- Key Browser -->
                <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h4 style="margin:0; font-size:0.9rem;">Cache Key Browser</h4>
                        <div style="display:flex; gap:0.5rem;">
                            <input type="text" id="redis-key-pattern" class="spp-element" placeholder="Pattern (e.g. spp:*)" style="font-size:0.8rem; padding:4px 8px;">
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.searchRedisKeys()">🔍 Search</button>
                        </div>
                    </div>
                    <table class="suite-table" style="width:100%; font-size:0.8rem;">
                        <thead><tr><th>Key</th><th>Type</th></tr></thead>
                        <tbody>
                            ${keys.map(k => `
                                <tr>
                                    <td><code>${this.escapeHtml(k.key)}</code></td>
                                    <td><span class="suite-pill suite-pill-neutral" style="font-size:0.7rem;">${this.escapeHtml(k.type || 'string')}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    onProvEngineChange(engine) {
        const sqliteBox = document.getElementById('prov-engine-sqlite-opts');
        const mysqlBox = document.getElementById('prov-engine-mysql-opts');
        const pgsqlBox = document.getElementById('prov-engine-pgsql-opts');
        const xdbBox = document.getElementById('prov-engine-xdb-opts');

        if (sqliteBox) sqliteBox.style.display = (engine === 'sqlite') ? 'block' : 'none';
        if (mysqlBox) mysqlBox.style.display = (engine === 'mysql') ? 'block' : 'none';
        if (pgsqlBox) pgsqlBox.style.display = (engine === 'pgsql') ? 'block' : 'none';
        if (xdbBox) xdbBox.style.display = (engine === 'xdb') ? 'block' : 'none';
    }

    async createDatabase() {
        const engine = document.getElementById('prov-db-engine')?.value || 'sqlite';
        const name = document.getElementById('prov-db-name')?.value?.trim();
        const charset = document.getElementById('prov-db-charset')?.value || 'utf8mb4';
        const collation = document.getElementById('prov-db-collation')?.value || 'utf8mb4_unicode_ci';

        if (!name) return this.admin.notify('Database name cannot be empty.', 'error');
        try {
            const res = await this.admin.api('db_create_database', {
                name: name,
                driver: engine,
                charset: charset,
                collation: collation
            });

            if (res && res.success) {
                this.admin.notify(res.message || `Database '${name}' created successfully.`, 'success');
                const nameInput = document.getElementById('prov-db-name');
                if (nameInput) nameInput.value = '';
                await this.fetchDatabases();
                await this.fetchProvisioningData();
            } else {
                this.admin.notify(res?.error || res?.message || 'Failed to create database.', 'error');
            }
        } catch (e) {
            this.admin.notify('Create database error: ' + e.message, 'error');
        }
    }

    async quickCreateMissingDatabase(name, engine = 'mysql') {
        if (!name) return;
        try {
            const res = await this.admin.api('db_create_database', {
                name: name,
                driver: engine,
                charset: 'utf8mb4',
                collation: 'utf8mb4_unicode_ci'
            });
            const data = (res && res.data) ? res.data : res;
            if (res && (res.success || data.success)) {
                this.admin.notify(`Database '${name}' created successfully on ${engine.toUpperCase()}.`, 'success');
                await this.fetchDatabases();
                await this.fetchProvisioningData();
                if (this.state.selectedDatabase) {
                    await this.selectDatabase(this.state.selectedDatabase);
                }
            } else {
                this.admin.notify(res?.error || res?.message || data?.error || 'Failed to create database.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async createDbUser() {
        const username = document.getElementById('prov-user-name')?.value?.trim();
        const password = document.getElementById('prov-user-pass')?.value?.trim();
        const host = document.getElementById('prov-user-host')?.value?.trim() || 'localhost';
        const engine = document.getElementById('prov-user-engine')?.value || 'mysql';

        if (!username || !password) return this.admin.notify('Username and password required.', 'error');
        try {
            const res = await this.admin.api('db_provision_create_user', { username, password, host, engine });
            const data = (res && res.data) ? res.data : res;
            if (res && (res.success || data.success)) {
                this.admin.notify(res.message || data.message || `User '${username}' created successfully.`, 'success');
                const uInput = document.getElementById('prov-user-name');
                const pInput = document.getElementById('prov-user-pass');
                if (uInput) uInput.value = '';
                if (pInput) pInput.value = '';
                await this.fetchProvisioningData();
                await this.fetchDatabases();
            } else {
                this.admin.notify(res?.error || res?.message || data?.error || 'Failed to create user.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async grantPrivileges() {
        const userSelect = document.getElementById('prov-assign-user');
        const user = userSelect?.value;
        const host = userSelect?.options[userSelect.selectedIndex]?.getAttribute('data-host') || 'localhost';
        const db = document.getElementById('prov-assign-db')?.value;
        if (!user || !db) return this.admin.notify('User and database must be selected.', 'error');

        let privileges = ['ALL'];
        const allCb = document.getElementById('prov-priv-all');
        if (allCb && !allCb.checked) {
            const checked = Array.from(document.querySelectorAll('.prov-priv-cb:checked')).map(cb => cb.value);
            if (checked.length > 0) {
                privileges = checked;
            }
        }

        try {
            const res = await this.admin.api('db_provision_grant', { username: user, database: db, privileges: privileges, host: host });
            const data = (res && res.data) ? res.data : res;
            if (res && (res.success || data.success)) {
                this.admin.notify(res.message || data.message || `Granted privileges on ${db} to ${user}.`, 'success');
            } else {
                this.admin.notify(res?.error || res?.message || data?.error || 'Grant failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async changeDbUserPassword(username, host = 'localhost') {
        const newPass = prompt(`Enter new password for database user '${username}'@'${host}':`);
        if (!newPass) return;
        try {
            const res = await this.admin.api('db_provision_change_password', { username, password: newPass, host });
            if (res && res.success) {
                this.admin.notify(res.message || 'Password changed successfully.', 'success');
            } else {
                this.admin.notify(res?.error || 'Failed to change password.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async deleteDatabase(name, dbId = null, engine = null) {
        if (!name) return;
        const targetId = dbId || name;
        const protectedDbs = ['default', 'default.sqlite', 'spp.sqlite', 'spp', 'sys', 'mysql', 'information_schema', 'performance_schema'];
        const cleanName = name.replace(/\s*\(.*\)/, '').trim().toLowerCase();
        if (protectedDbs.includes(cleanName) || protectedDbs.includes(name.toLowerCase())) {
            return this.admin.notify(`Safety Violation: '${name}' is a protected system database and cannot be deleted.`, 'error');
        }

        if (!confirm(`CRITICAL WARNING: Are you sure you want to permanently drop database '${name}'? This will completely delete the database and all its tables.`)) return;

        try {
            const res = await this.admin.api('db_delete_database', {
                name: name,
                connection: targetId,
                driver: engine
            });

            const data = (res && res.data) ? res.data : res;
            if (res && (res.success || data.success)) {
                this.admin.notify(res.message || data.message || `Database '${name}' deleted successfully.`, 'success');
                await this.fetchDatabases();
                await this.fetchProvisioningData();
                if (this.state.selectedDatabase === targetId || this.state.selectedDatabase === name) {
                    await this.selectDatabase('default');
                }
            } else {
                this.admin.notify(res?.error || res?.message || data?.error || 'Delete database failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Delete database error: ' + e.message, 'error');
        }
    }

    async dropTableDirect(table) {
        if (!table) return;
        if (!confirm(`CRITICAL WARNING: Dropping table '${table}' will permanently delete the table schema and all records. Are you sure you want to proceed?`)) return;

        try {
            const res = await this.admin.api('db_drop_table', {
                table: table,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });

            if (res && res.success) {
                this.admin.notify(`Table '${table}' dropped successfully.`, 'success');
                await this.fetchTables();
            } else {
                this.admin.notify(res?.error || res?.message || 'Drop table failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Drop table error: ' + e.message, 'error');
        }
    }

    async deleteDbUser(username, host) {
        if (!confirm(`Are you sure you want to drop database user '${username}'@'${host}'?`)) return;
        try {
            const res = await this.admin.api('db_provision_delete_user', { username, host });
            if (res && res.success) {
                this.admin.notify(res.message || 'User deleted.', 'success');
                await this.fetchProvisioningData();
            } else {
                this.admin.notify(res?.error || 'Delete failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async fetchProvisioningData() {
        try {
            const [pResRaw, rResRaw, redResRaw] = await Promise.all([
                this.admin.api('db_provision_list'),
                this.admin.api('db_provision_remote_hosts_get'),
                this.admin.api('redis_status')
            ]);
            const pRes = (pResRaw && pResRaw.data) ? pResRaw.data : (pResRaw || {});
            const rRes = (rResRaw && rResRaw.data) ? rResRaw.data : (rResRaw || {});
            const redRes = (redResRaw && redResRaw.data) ? redResRaw.data : (redResRaw || {});

            this.setState({
                provisionedDatabases: pRes.databases || [],
                provisionedUsers: pRes.users || [],
                remoteHosts: rRes.hosts || [],
                redisStatus: redRes || null,
                provisioningConnected: pRes.connected !== false,
                provisioningEngine: pRes.engine || 'mysql'
            });
            this.update();
        } catch (e) {
            console.error('Fetch provisioning error:', e);
        }
    }

    openQuickDbSetupModal(engine = 'mysql') {
        const isPg = (engine === 'pgsql' || engine === 'postgres');
        const defaultPort = isPg ? 5432 : 3306;
        const defaultUser = isPg ? 'postgres' : 'root';
        const title = isPg ? 'PostgreSQL Administrative Engine Setup' : 'MySQL / MariaDB Administrative Engine Setup';

        const existingModal = document.getElementById('quick-db-setup-modal');
        if (existingModal) existingModal.remove();

        const modalHtml = `
            <div id="quick-db-setup-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:10000; padding:1rem; backdrop-filter:blur(4px);">
                <div style="background:var(--bg-panel, #0f172a); border:1px solid var(--border); border-radius:12px; width:100%; max-width:480px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); overflow:hidden;">
                    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:0.5rem; color:var(--text-bright);">
                            <span>🚀</span> ${title}
                        </h3>
                        <button class="btn ghost-btn btn-sm" onclick="document.getElementById('quick-db-setup-modal').remove()" style="font-size:1.1rem; padding:2px 8px;">✕</button>
                    </div>
                    <div style="padding:1.5rem;">
                        <p style="font-size:0.82rem; color:var(--text-dim); margin:0 0 1.25rem 0; line-height:1.45;">
                            Configure your server's administrative credentials once to enable cPanel-style database creation, user provisioning, and granular privilege management.
                        </p>
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Host:</label>
                            <input type="text" id="quick-db-host" class="spp-element" value="127.0.0.1" style="width:100%; font-size:0.85rem;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Port:</label>
                                <input type="number" id="quick-db-port" class="spp-element" value="${defaultPort}" style="width:100%; font-size:0.85rem;">
                            </div>
                            <div>
                                <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Username:</label>
                                <input type="text" id="quick-db-user" class="spp-element" value="${defaultUser}" style="width:100%; font-size:0.85rem;">
                            </div>
                        </div>
                        <div class="suite-form-group" style="margin-bottom:1.25rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Administrative Password:</label>
                            <input type="password" id="quick-db-pass" class="spp-element" placeholder="Enter root or admin password..." style="width:100%; font-size:0.85rem;">
                        </div>
                        <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                            <button class="btn ghost-btn btn-sm" onclick="document.getElementById('quick-db-setup-modal').remove()">Cancel</button>
                            <button class="btn primary-btn btn-sm" id="btn-save-quick-db-setup" onclick="window.databaseViewInstance.saveQuickDbSetup('${engine}')">
                                🔌 Test &amp; Save Connection
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    async saveQuickDbSetup(engine = 'mysql') {
        const host = document.getElementById('quick-db-host')?.value?.trim() || '127.0.0.1';
        const port = parseInt(document.getElementById('quick-db-port')?.value || '3306', 10);
        const username = document.getElementById('quick-db-user')?.value?.trim() || 'root';
        const password = document.getElementById('quick-db-pass')?.value || '';
        const btn = document.getElementById('btn-save-quick-db-setup');

        if (btn) btn.innerText = 'Testing...';

        try {
            const conf = {
                driver: engine,
                host: host,
                port: port,
                username: username,
                password: password,
                database: (engine === 'pgsql' || engine === 'postgres') ? 'postgres' : 'mysql'
            };

            const testRes = await this.admin.api('db_test_connection', { config: conf });
            if (!testRes || !testRes.success) {
                if (btn) btn.innerText = '🔌 Test & Save Connection';
                return this.admin.notify('Connection failed: ' + (testRes?.error || 'Access denied or server offline.'), 'error');
            }

            const saveRes = await this.admin.api('db_save_config', {
                name: engine,
                config: conf
            });

            if (saveRes && saveRes.success) {
                this.admin.notify(`✅ ${engine.toUpperCase()} administrative profile verified and saved to etc/db.yml!`, 'success');
                document.getElementById('quick-db-setup-modal')?.remove();
                await this.fetchDatabases();
                await this.fetchProvisioningData();
            } else {
                this.admin.notify('Failed to save profile: ' + (saveRes?.error || 'Unknown error'), 'error');
                if (btn) btn.innerText = '🔌 Test & Save Connection';
            }
        } catch (e) {
            this.admin.notify('Setup error: ' + e.message, 'error');
            if (btn) btn.innerText = '🔌 Test & Save Connection';
        }
    }

    async addRemoteHost() {
        const ip = document.getElementById('remote-host-ip-input')?.value?.trim();
        if (!ip) return this.admin.notify('IP address cannot be empty.', 'error');
        try {
            const res = await this.admin.api('db_provision_remote_host_add', { ip });
            if (res && res.success) {
                this.admin.notify(res.message || 'Remote host added.', 'success');
                await this.fetchProvisioningData();
            } else {
                this.admin.notify(res?.error || 'Failed to add host.', 'error');
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async removeRemoteHost(ip) {
        try {
            const res = await this.admin.api('db_provision_remote_host_remove', { ip });
            if (res && res.success) {
                this.admin.notify(res.message || 'Remote host removed.', 'success');
                await this.fetchProvisioningData();
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async flushRedis(all) {
        const action = all ? 'redis_flush_all' : 'redis_flush_db';
        if (!confirm(`Are you sure you want to ${all ? 'FLUSH ALL Redis databases' : 'FLUSH the active Redis database'}?`)) return;
        try {
            const res = await this.admin.api(action);
            if (res && res.success) {
                this.admin.notify(res.message || 'Redis flushed.', 'success');
                await this.fetchProvisioningData();
            }
        } catch (e) {
            this.admin.notify('Error: ' + e.message, 'error');
        }
    }

    async searchRedisKeys() {
        const pattern = document.getElementById('redis-key-pattern')?.value?.trim() || '*';
        try {
            const res = await this.admin.api('redis_keys', { pattern });
            if (res && res.success) {
                this.setState({ redisKeys: res.keys || [] });
                this.update();
            }
        } catch (e) {
            this.admin.notify('Search error: ' + e.message, 'error');
        }
    }

    render() {
        if (this.state.loading) {
            return `<div class="loading-state" style="padding:4rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:1rem; color:var(--text-dim);">Connecting to Database Engine...</div></div>`;
        }

        const currentApp = this.admin.selectedApp || 'default';
        const allDbs = this.state.databases || [];
        const sqliteDbs = allDbs.filter(d => (d.driver || d.engine || '').toLowerCase() === 'sqlite');
        const mysqlDbs = allDbs.filter(d => ['mysql', 'mariadb'].includes((d.driver || d.engine || '').toLowerCase()));
        const pgsqlDbs = allDbs.filter(d => ['pgsql', 'postgres', 'postgresql'].includes((d.driver || d.engine || '').toLowerCase()));
        const xdbDbs = allDbs.filter(d => (d.driver || d.engine || '').toLowerCase() === 'xdb' || d.type === 'nosql');

        const activeFilter = this.state.selectedEngineFilter || 'all';
        let filteredDbs = allDbs;
        if (activeFilter === 'sqlite') filteredDbs = sqliteDbs;
        else if (activeFilter === 'mysql') filteredDbs = mysqlDbs;
        else if (activeFilter === 'pgsql') filteredDbs = pgsqlDbs;
        else if (activeFilter === 'xdb') filteredDbs = xdbDbs;

        const contextDbs = filteredDbs.filter(d => d.is_context);
        const appDbs = filteredDbs.filter(d => d.is_app && !d.is_context);
        const relationalDbs = filteredDbs.filter(d => d.type === 'relational' && !d.is_context && !d.is_app);
        const nosqlDbs = filteredDbs.filter(d => (d.type === 'nosql' || d.driver === 'xdb') && !d.is_context && !d.is_app);
        const currentDbObj = allDbs.find(d => d.id === this.state.selectedDatabase) || null;

        let selectorOptionsHtml = '';
        if (contextDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="⭐ Active Context Databases (${this.escapeHtml(currentApp)})">`;
            contextDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml((d.driver || d.engine || 'sqlite').toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }
        if (appDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="📱 Application Database Configurations">`;
            appDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml((d.driver || d.engine || 'sqlite').toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }
        if (relationalDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="🗄️ System & Local Relational Databases">`;
            relationalDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml((d.driver || d.engine || 'sqlite').toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }
        if (nosqlDbs.length > 0) {
            selectorOptionsHtml += `<optgroup label="⚡ Native XDB NoSQL Collections">`;
            nosqlDbs.forEach(d => {
                selectorOptionsHtml += `<option value="${this.escapeHtml(d.id)}" ${d.id === this.state.selectedDatabase ? 'selected' : ''}>${this.escapeHtml(d.name)} [${this.escapeHtml((d.driver || d.engine || 'xdb').toUpperCase())}]</option>`;
            });
            selectorOptionsHtml += `</optgroup>`;
        }

        return `
            <div class="database-view-wrapper" style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Multi-Engine Database Selector Bar with Type Selector -->
                <div class="glass-panel" style="padding:1rem 1.25rem; display:flex; flex-direction:column; gap:0.9rem; border-radius:var(--radius-md); border:1px solid var(--glass-border); background:linear-gradient(135deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%);">
                    
                    <!-- Engine Type Switcher Buttons -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                        <div style="display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;">
                            <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:700; margin-right:0.25rem;">Database Engine:</span>
                            <button class="btn ${activeFilter === 'all' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="padding:3px 10px; font-size:0.78rem;" onclick="window.databaseViewInstance.filterByEngine('all')">
                                🌐 All Engines (${allDbs.length})
                            </button>
                            <button class="btn ${activeFilter === 'sqlite' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="padding:3px 10px; font-size:0.78rem;" onclick="window.databaseViewInstance.filterByEngine('sqlite')">
                                🗄️ SQLite (${sqliteDbs.length})
                            </button>
                            <button class="btn ${activeFilter === 'mysql' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="padding:3px 10px; font-size:0.78rem;" onclick="window.databaseViewInstance.filterByEngine('mysql')">
                                🐬 MySQL / MariaDB (${mysqlDbs.length})
                            </button>
                            <button class="btn ${activeFilter === 'pgsql' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="padding:3px 10px; font-size:0.78rem;" onclick="window.databaseViewInstance.filterByEngine('pgsql')">
                                🐘 PostgreSQL (${pgsqlDbs.length})
                            </button>
                            <button class="btn ${activeFilter === 'xdb' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="padding:3px 10px; font-size:0.78rem;" onclick="window.databaseViewInstance.filterByEngine('xdb')">
                                ⚡ SPP Native XDB (${xdbDbs.length})
                            </button>
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.fetchDatabases()" title="Refresh database discovery">
                                🔄 Refresh
                            </button>
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.openConfigModal()" title="Configure database connection profiles">
                                ⚙️ Database Profiles
                            </button>
                        </div>
                    </div>

                    <!-- Active Connection Dropdown & Status -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; padding-top:0.75rem; border-top:1px solid rgba(255,255,255,0.06);">
                        <div style="display:flex; align-items:center; gap:0.9rem; flex:1; min-width:320px;">
                            <div style="font-size:1.4rem; background:var(--primary-subtle); border:1px solid var(--primary); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                                ${this.state.driver === 'xdb' ? '⚡' : (['mysql', 'mariadb'].includes(this.state.driver) ? '🐬' : (['pgsql', 'postgres'].includes(this.state.driver) ? '🐘' : '🗄️'))}
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                                    <label style="font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-dim);">
                                        Active Database Connection:
                                    </label>
                                    <span class="suite-pill ${this.state.driver === 'xdb' ? 'suite-pill-warning' : 'suite-pill-success'}" style="font-size:0.65rem; padding:1px 6px;">
                                        ${this.escapeHtml((this.state.driver || 'sqlite').toUpperCase())}
                                    </span>
                                    ${currentDbObj && currentDbObj.is_context ? `<span class="suite-pill suite-pill-primary" style="font-size:0.65rem; padding:1px 6px;">App Context: ${this.escapeHtml(currentApp)}</span>` : ''}
                                </div>
                                <select class="app-context-select suite-input" onchange="window.databaseViewInstance.selectDatabase(this.value)" style="width:100%; max-width:620px; font-weight:600; font-size:0.875rem; padding:6px 10px; border-radius:var(--radius-sm);">
                                    ${selectorOptionsHtml}
                                </select>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.checkActiveDatabase()" title="Check integrity of current database">
                                🔍 Check DB
                            </button>
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.repairActiveDatabase()" title="Vacuum and defragment storage">
                                🧹 Optimize DB
                            </button>
                        </div>
                    </div>
                </div>

                ${this.state.connectionError ? (() => {
                    const isUnknownDb = this.state.connectionError.includes('1049') || this.state.connectionError.toLowerCase().includes('unknown database');
                    const dbMatch = this.state.connectionError.match(/unknown database '([^']+)'/i);
                    const missingDbName = dbMatch ? dbMatch[1] : (currentDbObj?.database || (this.state.selectedDatabase?.startsWith('app:') ? this.state.selectedDatabase.substring(4) : null));
                    const engine = currentDbObj?.driver || this.state.driver || 'mysql';

                    return `
                    <!-- Non-blocking Diagnostic Banner -->
                    <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:8px; padding:0.75rem 1.25rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
                        <div style="display:flex; align-items:center; gap:0.6rem;">
                            <span style="font-size:1.4rem;">${isUnknownDb ? '🗄️' : '⚠️'}</span>
                            <div>
                                <strong style="color:#f87171; font-size:0.875rem;">${isUnknownDb ? 'Database Not Found:' : 'Connection Warning:'}</strong>
                                <span style="color:var(--text); font-size:0.825rem; margin-left:0.3rem;">
                                    ${isUnknownDb ? `Database <strong>'${this.escapeHtml(missingDbName)}'</strong> does not exist yet on MySQL.` : `Could not connect to <strong>${this.escapeHtml(currentDbObj ? currentDbObj.name : this.state.selectedDatabase)}</strong>.`}
                                </span>
                                <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px; font-family:'JetBrains Mono',monospace;">
                                    ${this.escapeHtml(this.state.connectionError)}
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                            ${isUnknownDb && missingDbName ? `
                                <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.quickCreateMissingDatabase('${this.escapeAttr(missingDbName)}', '${this.escapeAttr(engine)}')">
                                    ➕ Create Database '${this.escapeHtml(missingDbName)}'
                                </button>
                            ` : ''}
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.selectDatabase('default')">⚡ Switch to Default DB</button>
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.openConfigModal()">⚙️ Edit Profile</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.setState({ connectionError: null }); window.databaseViewInstance.update();">✕ Dismiss</button>
                        </div>
                    </div>
                    `;
                })() : ''}

                <!-- Studio Navigation Tabs (Always Accessible) -->
                <div class="suite-tabs">
                    <button class="suite-tab-btn ${this.state.activeTab === 'data' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('data')">
                        📑 Data Browser & Rows
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'structure' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('structure')">
                        📐 Structure
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'operations' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('operations')">
                        ⚙️ Operations
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'search' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('search')">
                        🔍 Search DB
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'sql' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('sql')">
                        💻 ${this.state.driver === 'xdb' ? 'XPath / SQL Query Console' : 'SQL Query Console'}
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'erd' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('erd')">
                        🗺️ Visual ERD
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'snapshots' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('snapshots')">
                        ⏱️ Snapshots & Time Travel
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'migrations' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('migrations')">
                        ⚡ Migration Generator
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'dumps' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('dumps')">
                        📦 Export &amp; Import
                    </button>
                    <button class="suite-tab-btn ${this.state.activeTab === 'provisioning' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('provisioning')" style="${this.state.activeTab === 'provisioning' ? 'color:#10b981; border-color:#10b981; font-weight:700;' : ''}">
                        🗄️ Databases & Users
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'remote_access' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('remote_access')">
                        🌐 Remote Access
                    </button>
                    <button data-mode="expert" class="suite-tab-btn ${this.state.activeTab === 'redis' ? 'active' : ''}" onclick="window.databaseViewInstance.switchTab('redis')" style="${this.state.activeTab === 'redis' ? 'color:#ef4444; border-color:#ef4444; font-weight:700;' : ''}">
                        ⚡ Redis Cache
                    </button>
                </div>

                <!-- Tab 1: Data Browser Split Pane -->
                ${this.state.activeTab === 'data' ? this.renderDataBrowser() : ''}

                <!-- Tab 1b: Structure & Schema -->
                ${this.state.activeTab === 'structure' ? this.renderStructureTab() : ''}

                <!-- Tab 1c: Operations & Maintenance -->
                ${this.state.activeTab === 'operations' ? this.renderOperationsTab() : ''}

                <!-- Tab 1d: Database-wide Search -->
                ${this.state.activeTab === 'search' ? this.renderSearchTab() : ''}

                <!-- Tab 2: SQL Console -->
                ${this.state.activeTab === 'sql' ? this.renderSqlConsole() : ''}

                <!-- Tab 3: Visual ERD -->
                ${this.state.activeTab === 'erd' ? this.renderErdTab() : ''}

                <!-- Tab 4: Snapshots & Time Travel -->
                ${this.state.activeTab === 'snapshots' ? this.renderSnapshotsTab() : ''}

                <!-- Tab 5: Migration Generator -->
                ${this.state.activeTab === 'migrations' ? this.renderMigrationsTab() : ''}

                <!-- Tab 6: Dumps -->
                ${this.state.activeTab === 'dumps' ? this.renderDumpsTab() : ''}

                <!-- Tab 7: Database & User Provisioning -->
                ${this.state.activeTab === 'provisioning' ? this.renderProvisioningTab() : ''}

                <!-- Tab 8: Remote Access -->
                ${this.state.activeTab === 'remote_access' ? this.renderRemoteAccessTab() : ''}

                <!-- Tab 9: Redis Workstation -->
                ${this.state.activeTab === 'redis' ? this.renderRedisTab() : ''}
            </div>
        `;
    }

    renderDataBrowser() {
        if (this.state.connectionError && (!this.state.tables || this.state.tables.length === 0)) {
            const isAuthError = this.state.connectionError.includes('1698') || this.state.connectionError.toLowerCase().includes('access denied');
            const isUnknownDb = this.state.connectionError.includes('1049') || this.state.connectionError.toLowerCase().includes('unknown database');
            const dbMatch = this.state.connectionError.match(/unknown database '([^']+)'/i);
            const appName = this.state.selectedDatabase && this.state.selectedDatabase.startsWith('app:') ? this.state.selectedDatabase.substring(4) : null;
            const missingDbName = dbMatch ? dbMatch[1] : (appName || (this.state.selectedDatabase ? this.state.selectedDatabase.replace(/^app:/, '') : null));
            const engine = this.state.driver || 'mysql';

            return `
                <div class="suite-card" style="padding:3rem 2rem; text-align:center; margin-top:1rem; border:1px solid rgba(239,68,68,0.3); background:radial-gradient(ellipse at top, rgba(239,68,68,0.08) 0%, rgba(15,23,42,0.6) 80%); border-radius:8px;">
                    <div style="font-size:3rem; margin-bottom:0.75rem;">${isUnknownDb ? '🗄️' : '⚠️'}</div>
                    <h3 style="color:#f87171; margin:0 0 0.5rem 0; font-size:1.3rem; font-weight:700;">
                        ${isUnknownDb ? `Database '${this.escapeHtml(missingDbName)}' Does Not Exist Yet` : (isAuthError ? 'Database Authentication Failed' : 'Database Connection Offline')}
                    </h3>
                    <p style="color:var(--text-dim); margin:0 auto 0.75rem auto; font-size:0.875rem; max-width:640px;">
                        ${isUnknownDb 
                            ? `The MySQL database server is online, but database schema <strong style="color:var(--text-bright);">${this.escapeHtml(missingDbName)}</strong> has not been created yet.`
                            : `Unable to connect to <strong style="color:var(--text-bright);">${this.escapeHtml(this.state.selectedDatabase)}</strong> to browse tables and rows.`}
                        <br><span style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; color:#f87171; display:inline-block; margin-top:0.5rem; background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:4px;">${this.escapeHtml(this.state.connectionError)}</span>
                    </p>
                    ${isAuthError ? `
                        <p style="color:var(--text-dim); font-size:0.8rem; max-width:600px; margin:0 auto 1.5rem auto;">
                            The database server rejected the username/password configured for <strong>${this.escapeHtml(this.state.selectedDatabase)}</strong>. On Linux servers, MySQL root requires socket authentication or a dedicated database user.
                        </p>
                    ` : '<div style="margin-bottom:1.5rem;"></div>'}
                    <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
                        ${isUnknownDb && missingDbName ? `
                            <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.quickCreateMissingDatabase('${this.escapeAttr(missingDbName)}', '${this.escapeAttr(engine)}')">
                                🚀 Create Database '${this.escapeHtml(missingDbName)}' Now
                            </button>
                        ` : ''}
                        <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.openConfigModal('${this.escapeAttr(this.state.selectedDatabase)}')">
                            ⚙️ Edit Connection Settings
                        </button>
                        ${appName ? `
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.quickSwitchAppToSqlite('${this.escapeAttr(appName)}')">
                                📁 Switch ${this.escapeHtml(appName)} to SQLite
                            </button>
                        ` : ''}
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.selectDatabase('default')">
                            ⚡ Switch to Default SQLite
                        </button>
                        <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.switchTab('provisioning')">
                            🗄️ Databases &amp; Users
                        </button>
                    </div>
                </div>
            `;
        }

        const tableListItems = (this.state.tables || []).map(t => {
            const isActive = t.name === this.state.activeTable;
            return `
                <div onclick="window.databaseViewInstance.selectTable('${this.escapeHtml(t.name)}')" 
                    style="padding:0.5rem 0.75rem; border-radius:var(--radius-sm); cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:${isActive ? 'var(--primary-subtle)' : 'transparent'}; border:1px solid ${isActive ? 'var(--primary)' : 'transparent'};">
                    <span style="font-weight:${isActive ? '700' : '500'}; color:${isActive ? 'var(--primary-hover)' : 'var(--text-main)'}; font-size:0.875rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:130px;" title="${this.escapeAttr(t.name)}">
                        ${this.escapeHtml(t.name)}
                    </span>
                    <div style="display:inline-flex; align-items:center; gap:0.25rem;">
                        <span class="suite-pill suite-pill-neutral" style="font-size:0.65rem;">
                            ${t.rows >= 0 ? t.rows : '-'}
                        </span>
                        <button class="btn ghost-btn btn-sm" style="padding:1px 4px; font-size:0.7rem; color:var(--danger); opacity:0.7;" onclick="event.stopPropagation(); window.databaseViewInstance.dropTableDirect('${this.escapeHtml(t.name)}')" title="Drop Table">🗑️</button>
                    </div>
                </div>
            `;
        }).join('');

        const allSelected = (this.state.rows || []).length > 0 && (this.state.rows || []).every(r => this.state.selectedRowIds && this.state.selectedRowIds.has(String(r[this.state.primaryKey || 'id'])));
        const selectHeader = `
            <th style="width:36px; text-align:center;">
                <input type="checkbox" onchange="window.databaseViewInstance.toggleSelectAll(this.checked)" ${allSelected ? 'checked' : ''} title="Select All Rows">
            </th>
        `;

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
            const isSelected = this.state.selectedRowIds && this.state.selectedRowIds.has(String(pkVal));

            const selectCell = `
                <td style="text-align:center;">
                    <input type="checkbox" onchange="window.databaseViewInstance.toggleSelectRow('${this.escapeAttr(pkVal)}', this.checked)" ${isSelected ? 'checked' : ''}>
                </td>
            `;

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
                <tr style="${isSelected ? 'background:rgba(59,130,246,0.08);' : ''}">
                    ${selectCell}
                    ${cells}
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:0.3rem;">
                            <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.openRowModal(${rowJson})" title="Open full row editor">Edit</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.cloneRow(${rowJson})" title="Clone / Duplicate this row">📋 Copy</button>
                            <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.deleteRow(${rowJson})" title="Delete row">✕</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        const selCount = this.state.selectedRowIds ? this.state.selectedRowIds.size : 0;

        return `
            <div class="suite-split-pane">
                <!-- Sidebar: Tables List -->
                <div class="suite-pane-sidebar">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                        <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Tables (${this.state.tables.length})</span>
                        <button class="btn primary-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.openCreateTableModal()" title="Create New Table">➕ New</button>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.25rem;">
                        ${tableListItems}
                    </div>
                </div>

                <!-- Main: Table Rows & Schema -->
                <div class="suite-pane-main">
                    <div class="suite-toolbar" style="flex-wrap:wrap; gap:0.75rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                            <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">${this.escapeHtml(this.state.activeTable || 'Select Table')}</h3>
                            <span class="suite-pill suite-pill-neutral">${this.state.totalRows} Total Records</span>
                            <span style="font-size:0.75rem; color:var(--text-dim); display:inline-flex; align-items:center; gap:0.35rem; background:rgba(255,255,255,0.03); padding:3px 8px; border-radius:12px; border:1px solid var(--glass-border);">
                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#10b981;"></span>
                                Double-click any cell to edit inline
                            </span>
                            ${selCount > 0 ? `
                                <div style="display:inline-flex; align-items:center; gap:0.4rem; background:rgba(59,130,246,0.12); border:1px solid #3b82f6; padding:3px 10px; border-radius:6px; font-size:0.8rem; color:#60a5fa;">
                                    <span>Selected <strong>${selCount}</strong> row(s)</span>
                                    <button class="btn danger-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.deleteSelectedBatch()">🗑️ Delete</button>
                                    <button class="btn secondary-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.exportSelectedCsv()" title="Export selected rows to CSV">📥 CSV</button>
                                    <button class="btn secondary-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.exportSelectedJson()" title="Export selected rows to JSON">📥 JSON</button>
                                    <button class="btn ghost-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.clearSelection()">✕ Cancel</button>
                                </div>
                            ` : ''}
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
                                        ${selectHeader}
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
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.75rem; font-size:0.85rem; color:var(--text-dim); flex-wrap:wrap; gap:0.5rem;">
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <span>Showing page <strong>${this.state.page}</strong> of <strong>${this.state.totalPages}</strong></span>
                                <div style="display:inline-flex; align-items:center; gap:0.35rem; font-size:0.8rem;">
                                    <span>Rows per page:</span>
                                    <select class="spp-element" onchange="window.databaseViewInstance.changePageSize(this.value)" style="padding:2px 6px; font-size:0.78rem;">
                                        <option value="25" ${this.state.pageSize === 25 ? 'selected' : ''}>25</option>
                                        <option value="50" ${this.state.pageSize === 50 ? 'selected' : ''}>50</option>
                                        <option value="100" ${this.state.pageSize === 100 ? 'selected' : ''}>100</option>
                                        <option value="250" ${this.state.pageSize === 250 ? 'selected' : ''}>250</option>
                                        <option value="500" ${this.state.pageSize === 500 ? 'selected' : ''}>500</option>
                                    </select>
                                </div>
                            </div>
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
        const queryHistory = this.getQueryHistory();

        return `
            <div class="suite-pane-main">
                <div class="suite-toolbar" style="flex-wrap:wrap; gap:0.75rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.1rem; color:var(--text-bright);">
                            ${isXdb ? '⚡ XDB NoSQL Query & XPath Console' : 'Interactive SQL Query Console'}
                        </h3>
                        <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                            ${isXdb
                ? 'Execute SQL statements or XPath expressions against native XDB NoSQL collections.'
                : 'Execute raw SELECT queries, DML updates, or DDL migrations with live execution timing and explain plans.'}
                            ${isXdb ? '<span style="color:#fbbf24; font-weight:600; display:block; margin-top:2px;">💡 Tip: Supports SQL (<code>SELECT * FROM students</code>) and XPath (<code>/school/students</code>)</span>' : ''}
                        </p>
                    </div>
                    <div style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap;">
                        ${queryHistory.length > 0 ? `
                            <select class="spp-element" onchange="if(this.value){document.getElementById('sql-console-input').value = this.value; this.value='';}" style="max-width:180px; font-size:0.75rem; padding:3px 6px;">
                                <option value="">📜 History (${queryHistory.length})...</option>
                                ${queryHistory.map(h => `<option value="${this.escapeAttr(h)}">${this.escapeHtml(h.substring(0, 45))}${h.length > 45 ? '...' : ''}</option>`).join('')}
                            </select>
                        ` : ''}
                        ${isXdb ? `
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = 'SELECT * FROM students LIMIT 25;'">Sample SQL</button>
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = '/school/students'">XPath Query</button>
                        ` : `
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = 'SELECT * FROM spp_users LIMIT 25;'">Users</button>
                            <button class="btn secondary-btn btn-sm" onclick="document.getElementById('sql-console-input').value = 'SELECT * FROM spp_migrations;'">Migrations</button>
                            <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.explainSql()" title="Run EXPLAIN QUERY PLAN">🔍 Explain Plan</button>
                        `}
                        <button class="btn ghost-btn btn-sm" onclick="document.getElementById('sql-console-input').value = '';" title="Clear SQL text">✕ Clear</button>
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
                <div style="margin-bottom:1.5rem;">
                    <h3 style="margin:0; font-size:1.15rem; color:var(--text-bright); display:flex; align-items:center; gap:0.5rem;">
                        <span>📦</span> Database Backup, Export &amp; Import Workstation
                    </h3>
                    <p style="margin:0.25rem 0 0 0; font-size:0.8rem; color:var(--text-dim);">
                        SPPAdmin data mobility: generate full database dumps or restore and execute multi-query SQL/JSON scripts into <code style="color:var(--primary); font-family:'JetBrains Mono',monospace;">${this.escapeHtml(this.state.selectedDatabase)}</code>.
                    </p>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
                    <!-- Box 1: Export / Dump -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <h4 style="margin:0 0 0.8rem 0; font-size:0.95rem; color:var(--text-bright);">📤 Export Database / Tables</h4>
                            <div class="suite-form-group" style="margin-bottom:0.8rem;">
                                <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Export Scope:</label>
                                <select id="export-scope-select" class="spp-element" style="width:100%; font-size:0.85rem;">
                                    <option value="all">Entire Database (${this.state.tables.length} tables/collections)</option>
                                    ${this.state.activeTable ? `<option value="active" selected>Active Table: ${this.escapeHtml(this.state.activeTable)}</option>` : ''}
                                </select>
                            </div>
                            <div class="suite-form-group" style="margin-bottom:1.2rem;">
                                <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Format:</label>
                                <div style="display:flex; gap:1.2rem; font-size:0.85rem; color:var(--text-bright);">
                                    <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                        <input type="radio" name="export-format-choice" value="sql" checked> SQL Script (.sql)
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                        <input type="radio" name="export-format-choice" value="json"> Structured JSON (.json)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.6rem;">
                            <button class="btn primary-btn shine-effect btn-sm" style="flex:1; justify-content:center;" onclick="
                                const scope = document.getElementById('export-scope-select')?.value || 'active';
                                const format = document.querySelector('input[name=export-format-choice]:checked')?.value || 'sql';
                                window.databaseViewInstance.exportDump(format, scope);
                            " ${this.state.exporting ? 'disabled' : ''}>
                                ${this.state.exporting ? 'Generating Dump...' : '📥 Generate &amp; Download'}
                            </button>
                        </div>
                    </div>

                    <!-- Box 2: Import / Restore -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 0.8rem 0; font-size:0.95rem; color:var(--text-bright);">📥 Import &amp; Restore</h4>
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Choose SQL or JSON File:</label>
                            <input type="file" id="import-file-input" accept=".sql,.json,.txt" class="spp-element" style="width:100%; font-size:0.8rem;" onchange="window.databaseViewInstance.handleImportFile(event)">
                        </div>
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Or Paste SQL Statements / JSON:</label>
                            <textarea id="import-script-text" rows="4" class="spp-element" placeholder="CREATE TABLE ... or INSERT INTO ... (semi-colon separated)" style="width:100%; font-family:'JetBrains Mono',monospace; font-size:0.8rem; box-sizing:border-box;"></textarea>
                        </div>
                        <button class="btn primary-btn btn-sm" style="width:100%; justify-content:center;" onclick="window.databaseViewInstance.runImport()" ${this.state.importing ? 'disabled' : ''}>
                            ${this.state.importing ? 'Executing Import...' : '⚡ Execute &amp; Import'}
                        </button>
                    </div>
                </div>

                ${this.state.dumpOutput ? `
                    <div style="margin-top:1.5rem; display:flex; flex-direction:column; gap:0.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>Dump Preview (${this.escapeHtml(this.state.dumpFilename)}):</strong>
                            <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.downloadDump()">⬇ Download File</button>
                        </div>
                        <pre class="suite-code-box" style="max-height:400px; overflow-y:auto;">${this.escapeHtml(this.state.dumpOutput.substring(0, 5000))}${this.state.dumpOutput.length > 5000 ? '\n\n-- [Output truncated for preview. Click Download File to get complete dump.]' : ''}</pre>
                    </div>
                ` : ''}
            </div>
        `;
    }

    renderSearchTab() {
        const results = this.state.searchResults || [];
        const term = this.state.searchTerm || '';

        return `
            <div class="suite-card" style="padding:1.5rem; border-radius:8px;">
                <div style="margin-bottom:1.5rem;">
                    <h3 style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🔍</span> Database-wide Search
                    </h3>
                    <p style="color:var(--text-dim); font-size:0.8rem; margin:0.3rem 0 0 0;">
                        Global search: scan across every table and column in <code style="color:var(--primary); font-family:'JetBrains Mono',monospace;">${this.escapeHtml(this.state.selectedDatabase)}</code> for matching text.
                    </p>
                </div>

                <div style="display:flex; gap:0.8rem; margin-bottom:1.5rem; max-width:600px;">
                    <input type="text" id="db-search-input" class="spp-element" value="${this.escapeAttr(term)}" placeholder="Search keyword or phrase across all tables..." style="flex:1; font-size:0.85rem;" onkeydown="if(event.key==='Enter') window.databaseViewInstance.runDatabaseSearch()">
                    <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.runDatabaseSearch()">
                        🔍 Search Entire DB
                    </button>
                </div>

                ${this.state.searchingDb ? `
                    <div style="padding:3rem; text-align:center;"><div class="sppux-spinner"></div><div style="margin-top:0.75rem; color:var(--text-dim); font-size:0.85rem;">Searching across all tables in ${this.escapeHtml(this.state.selectedDatabase)}...</div></div>
                ` : (this.state.searchedTerm ? `
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h4 style="margin:0; font-size:0.95rem; color:var(--text-bright);">
                                Found <strong>${this.state.searchTotalMatches || 0}</strong> match(es) for &ldquo;<span style="color:var(--primary);">${this.escapeHtml(this.state.searchedTerm)}</span>&rdquo; across <strong>${results.length}</strong> table(s):
                            </h4>
                        </div>

                        ${results.length === 0 ? `
                            <div style="padding:2rem; text-align:center; color:var(--text-dim); background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:8px;">
                                No matches found in any table.
                            </div>
                        ` : `
                            <div class="suite-table-wrapper">
                                <table class="suite-table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Table Name</th>
                                            <th>Total Matches</th>
                                            <th style="width:160px; text-align:right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${results.map(r => `
                                            <tr>
                                                <td>
                                                    <strong style="color:var(--text-bright); font-size:0.9rem;">${this.escapeHtml(r.table)}</strong>
                                                </td>
                                                <td>
                                                    <span class="suite-pill suite-pill-primary" style="font-size:0.75rem;">
                                                        ${r.matches_count} row(s) match
                                                    </span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <button class="btn primary-btn btn-sm" style="padding:2px 10px; font-size:0.78rem;" onclick="window.databaseViewInstance.browseSearchResults('${this.escapeHtml(r.table)}', '${this.escapeHtml(this.state.searchedTerm)}')">
                                                        👁️ Browse Matches
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `}
                    </div>
                ` : '')}
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
                                                class="suite-input"
                                                style="border-radius:4px; padding:4px 8px; font-family:'JetBrains Mono',monospace; width:100%; max-width:180px;">
                                        </td>
                                        <td>
                                            <select onchange="window.databaseViewInstance.updateMigColumn(${idx}, 'type', this.value)"
                                                class="suite-input"
                                                style="border-radius:4px; padding:4px 8px; font-size:0.8rem;">
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
                                                class="suite-input"
                                                style="border-radius:4px; padding:4px 8px; font-size:0.8rem; width:100%; max-width:140px;">
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

    closeConfigModal() {
        const modal = document.getElementById('modal-container');
        if (modal) {
            modal.classList.remove('active');
            const modalBox = modal.querySelector('.modal-box');
            if (modalBox) modalBox.style.maxWidth = '';
            const modalFooter = document.getElementById('modal-footer');
            if (modalFooter) modalFooter.style.display = '';
        }
    }

    renderStructureTab() {
        if (this.state.connectionError && (!this.state.tables || this.state.tables.length === 0)) {
            return `
                <div class="suite-card" style="padding:3rem 2rem; text-align:center; margin-top:1rem; border:1px solid rgba(239,68,68,0.3); background:radial-gradient(ellipse at top, rgba(239,68,68,0.08) 0%, rgba(15,23,42,0.6) 80%); border-radius:8px;">
                    <div style="font-size:3rem; margin-bottom:0.75rem;">⚠️</div>
                    <h3 style="color:#f87171; margin:0 0 0.5rem 0; font-size:1.3rem; font-weight:700;">Database Connection Offline</h3>
                    <p style="color:var(--text-dim); margin:0 auto 1.5rem auto; font-size:0.875rem; max-width:600px;">
                        Unable to connect to <strong style="color:var(--text-bright);">${this.escapeHtml(this.state.selectedDatabase)}</strong> to inspect structure.
                        <br><span style="font-family:'JetBrains Mono',monospace; font-size:0.8rem; color:#f87171; display:inline-block; margin-top:0.5rem; background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:4px;">${this.escapeHtml(this.state.connectionError)}</span>
                    </p>
                    <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
                        <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.selectDatabase('default')">⚡ Switch to Default SQLite</button>
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.switchTab('provisioning')">🗄️ Manage Databases & Users</button>
                    </div>
                </div>
            `;
        }

        if (!this.state.activeTable) {
            return `<div class="suite-card" style="padding:2rem; text-align:center; color:var(--text-dim);">Please select a table to view its structure.</div>`;
        }
        const cols = this.state.columns || [];
        const indexes = this.state.indexes || [];
        const fks = this.state.foreignKeys || [];

        return `
            <div class="suite-card" style="padding:1.5rem; border-radius:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                            <span>📐</span> Structure of Table: <code style="color:var(--primary); font-family:'JetBrains Mono',monospace;">${this.escapeHtml(this.state.activeTable)}</code>
                        </h3>
                        <p style="color:var(--text-dim); font-size:0.8rem; margin:0.3rem 0 0 0;">
                            Table schema inspector: columns, data types, primary keys, nullability, defaults, secondary indexes, and foreign keys.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.openAddColumnModal()">
                            ➕ Add Column
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.openAddIndexModal()">
                            ⚡ Add Index
                        </button>
                        <button class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.truncateActiveTable()" title="Truncate / empty all rows in this table">
                            🧹 Empty (Truncate)
                        </button>
                        <button class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.dropActiveTable()" title="Permanently drop this table">
                            💥 Drop Table
                        </button>
                        <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.selectTable('${this.escapeHtml(this.state.activeTable)}')">
                            🔄 Refresh
                        </button>
                    </div>
                </div>

                <!-- Columns Table -->
                <div style="margin-bottom:2rem;">
                    <h4 style="margin:0 0 0.8rem 0; font-size:0.95rem; color:var(--text-bright);">Columns (${cols.length})</h4>
                    <div class="suite-table-wrapper">
                        <table class="suite-table" style="width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Column Name</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Default</th>
                                    <th>Key</th>
                                    <th>Extra</th>
                                    <th style="width:100px; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${cols.map((col, idx) => {
            const isPk = col.pk || col.primary || col.name === this.state.primaryKey;
            const isNull = col.null === 'YES' || col.nullable === true || col.notnull === 0 || col.notnull === false;
            return `
                                        <tr>
                                            <td style="color:var(--text-dim); font-size:0.75rem;">${idx + 1}</td>
                                            <td>
                                                <strong style="color:var(--text-bright);">${this.escapeHtml(col.name)}</strong>
                                                ${isPk ? '<span style="margin-left:0.4rem;" title="Primary Key">🔑</span>' : ''}
                                            </td>
                                            <td><code style="font-family:'JetBrains Mono',monospace; color:var(--primary); font-size:0.82rem;">${this.escapeHtml(col.type || 'TEXT')}</code></td>
                                            <td>
                                                <span class="suite-pill ${isNull ? 'suite-pill-neutral' : 'suite-pill-warning'}" style="font-size:0.7rem;">
                                                    ${isNull ? 'YES' : 'NO'}
                                                </span>
                                            </td>
                                            <td style="font-size:0.8rem; color:var(--text-dim);">
                                                ${col.default !== null && col.default !== undefined ? `<code>${this.escapeHtml(col.default)}</code>` : '<em style="opacity:0.5;">None</em>'}
                                            </td>
                                            <td>
                                                ${isPk ? '<span class="suite-pill suite-pill-primary" style="font-size:0.7rem;">PRIMARY</span>' : ''}
                                            </td>
                                            <td style="font-size:0.8rem; color:var(--text-dim);">
                                                ${col.extra ? this.escapeHtml(col.extra) : '-'}
                                            </td>
                                            <td style="text-align:right;">
                                                ${!isPk ? `
                                                    <button class="btn danger-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.dropColumn('${this.escapeHtml(col.name)}')">
                                                        Drop
                                                    </button>
                                                ` : '<span style="font-size:0.75rem; color:var(--text-dim);">Locked</span>'}
                                            </td>
                                        </tr>
                                    `;
        }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Indexes Table -->
                <div style="margin-bottom:2rem;">
                    <h4 style="margin:0 0 0.8rem 0; font-size:0.95rem; color:var(--text-bright);">Indexes (${indexes.length})</h4>
                    ${indexes.length === 0 ? `
                        <div style="padding:1rem; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:6px; font-size:0.85rem; color:var(--text-dim);">
                            No secondary indexes found on this table.
                        </div>
                    ` : `
                        <div class="suite-table-wrapper">
                            <table class="suite-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Index Name</th>
                                        <th>Type</th>
                                        <th>Indexed Columns</th>
                                        <th style="width:100px; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${indexes.map(idx => `
                                        <tr>
                                            <td><strong>${this.escapeHtml(idx.name)}</strong></td>
                                            <td>
                                                <span class="suite-pill ${idx.unique ? 'suite-pill-primary' : 'suite-pill-neutral'}" style="font-size:0.7rem;">
                                                    ${idx.unique ? 'UNIQUE' : 'INDEX'}
                                                </span>
                                            </td>
                                            <td><code>${this.escapeHtml(Array.isArray(idx.columns) ? idx.columns.join(', ') : idx.columns)}</code></td>
                                            <td style="text-align:right;">
                                                <button class="btn danger-btn btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.databaseViewInstance.dropIndex('${this.escapeHtml(idx.name)}')">
                                                    Drop
                                                </button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `}
                </div>

                <!-- Foreign Keys Table -->
                <div>
                    <h4 style="margin:0 0 0.8rem 0; font-size:0.95rem; color:var(--text-bright);">Foreign Key Constraints (${fks.length})</h4>
                    ${fks.length === 0 ? `
                        <div style="padding:1rem; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:6px; font-size:0.85rem; color:var(--text-dim);">
                            No foreign key constraints defined for this table.
                        </div>
                    ` : `
                        <div class="suite-table-wrapper">
                            <table class="suite-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Local Column</th>
                                        <th>Target Table</th>
                                        <th>Target Column</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${fks.map(fk => `
                                        <tr>
                                            <td>${fk.id !== undefined ? fk.id : '-'}</td>
                                            <td><code>${this.escapeHtml(fk.from || fk.column || '')}</code></td>
                                            <td><strong>${this.escapeHtml(fk.table || fk.to_table || '')}</strong></td>
                                            <td><code>${this.escapeHtml(fk.to || fk.to_column || '')}</code></td>
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

    renderOperationsTab() {
        if (!this.state.activeTable) {
            return `<div class="suite-card" style="padding:2rem; text-align:center; color:var(--text-dim);">Please select a table to perform operations.</div>`;
        }
        const table = this.state.activeTable;

        return `
            <div class="suite-card" style="padding:1.5rem; border-radius:8px;">
                <div style="margin-bottom:1.5rem;">
                    <h3 style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>⚙️</span> Table Operations: <code style="color:var(--primary); font-family:'JetBrains Mono',monospace;">${this.escapeHtml(table)}</code>
                    </h3>
                    <p style="color:var(--text-dim); font-size:0.8rem; margin:0.3rem 0 0 0;">
                        SPPAdmin table maintenance: integrity checks, storage optimization / vacuum, renaming, duplicating, truncating, and dropping.
                    </p>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
                    <!-- Box 1: Table Maintenance -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <h4 style="margin:0 0 0.6rem 0; font-size:0.95rem; color:var(--text-bright);">🧹 Table Maintenance</h4>
                            <p style="font-size:0.8rem; color:var(--text-dim); margin:0 0 1rem 0;">
                                Check table integrity and optimize / vacuum storage to defragment data and reclaim disk space.
                            </p>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:0.6rem;">
                            <button class="btn secondary-btn btn-sm" style="justify-content:center;" onclick="window.databaseViewInstance.checkActiveTable()">
                                🔍 Check Table Integrity
                            </button>
                            <button class="btn primary-btn btn-sm" style="justify-content:center;" onclick="window.databaseViewInstance.optimizeActiveTable()">
                                🧹 Optimize / Vacuum Table
                            </button>
                        </div>
                    </div>

                    <!-- Box 2: Rename Table -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 0.6rem 0; font-size:0.95rem; color:var(--text-bright);">✏️ Rename Table</h4>
                        <p style="font-size:0.8rem; color:var(--text-dim); margin:0 0 0.8rem 0;">
                            Rename this table across all database schemas.
                        </p>
                        <div class="suite-form-group" style="margin-bottom:0.8rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">New Table Name:</label>
                            <input type="text" id="op-rename-input" class="spp-element" value="${this.escapeAttr(table)}" style="width:100%; font-size:0.85rem;">
                        </div>
                        <button class="btn primary-btn btn-sm" style="width:100%; justify-content:center;" onclick="window.databaseViewInstance.renameActiveTable()">
                            💾 Rename Table
                        </button>
                    </div>

                    <!-- Box 3: Duplicate / Copy Table -->
                    <div style="background:var(--card-bg, rgba(255,255,255,0.03)); border:1px solid var(--border); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 0.6rem 0; font-size:0.95rem; color:var(--text-bright);">📑 Copy / Duplicate Table</h4>
                        <p style="font-size:0.8rem; color:var(--text-dim); margin:0 0 0.8rem 0;">
                            Clone table structure and optionally duplicate all existing records.
                        </p>
                        <div class="suite-form-group" style="margin-bottom:0.6rem;">
                            <label style="font-size:0.75rem; color:var(--text-dim); display:block; margin-bottom:0.3rem;">Target Table Name:</label>
                            <input type="text" id="op-copy-input" class="spp-element" placeholder="${this.escapeAttr(table)}_copy" style="width:100%; font-size:0.85rem;">
                        </div>
                        <div style="display:flex; gap:1rem; margin-bottom:0.8rem; font-size:0.8rem; color:var(--text-dim);">
                            <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                <input type="radio" name="copy-data-mode" value="data" checked> Structure and Data
                            </label>
                            <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                <input type="radio" name="copy-data-mode" value="structure"> Structure Only
                            </label>
                        </div>
                        <button class="btn primary-btn btn-sm" style="width:100%; justify-content:center;" onclick="window.databaseViewInstance.duplicateActiveTable()">
                            📋 Duplicate Table
                        </button>
                    </div>

                    <!-- Box 4: Danger Zone -->
                    <div style="background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.3); border-radius:8px; padding:1.25rem;">
                        <h4 style="margin:0 0 0.6rem 0; font-size:0.95rem; color:#f87171;">⚠️ Danger Zone</h4>
                        <p style="font-size:0.8rem; color:var(--text-dim); margin:0 0 1rem 0;">
                            Irreversible operations. Ensure you have exported backups before proceeding.
                        </p>
                        <div style="display:flex; flex-direction:column; gap:0.6rem;">
                            <button class="btn danger-btn btn-sm" style="justify-content:center;" onclick="window.databaseViewInstance.truncateActiveTable()">
                                🗑️ Empty the Table (TRUNCATE)
                            </button>
                            <button class="btn danger-btn btn-sm" style="justify-content:center; background:#b91c1c;" onclick="window.databaseViewInstance.dropActiveTable()">
                                💥 Drop the Table (DROP)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async checkActiveTable() {
        if (!this.state.activeTable) return;
        this.admin.notify(`Checking table '${this.state.activeTable}'...`, 'info');
        try {
            const res = await this.admin.api('db_check_database', {
                table: this.state.activeTable,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Table '${this.state.activeTable}': ${res.message || 'Integrity OK'}`, 'success');
            } else {
                this.admin.notify(`Table '${this.state.activeTable}' warning: ${res?.error || 'Check failed'}`, 'warning');
            }
        } catch (e) {
            this.admin.notify('Check failed: ' + e.message, 'error');
        }
    }

    async optimizeActiveTable() {
        if (!this.state.activeTable) return;
        this.admin.notify(`Optimizing table '${this.state.activeTable}'...`, 'info');
        try {
            const res = await this.admin.api('db_repair_database', {
                table: this.state.activeTable,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Table '${this.state.activeTable}' optimized successfully.`, 'success');
            } else {
                this.admin.notify(`Optimize warning: ${res?.error || 'Failed'}`, 'warning');
            }
        } catch (e) {
            this.admin.notify('Optimize failed: ' + e.message, 'error');
        }
    }

    async renameActiveTable() {
        const oldName = this.state.activeTable;
        const newName = document.getElementById('op-rename-input')?.value?.trim();
        if (!newName || newName === oldName) {
            return this.admin.notify('Please provide a new table name different from current.', 'error');
        }
        if (!confirm(`Are you sure you want to rename table '${oldName}' to '${newName}'?`)) return;
        try {
            const res = await this.admin.api('db_rename_table', {
                table: oldName,
                new_name: newName,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Table renamed to '${newName}' successfully.`, 'success');
                await this.fetchTables();
                await this.selectTable(newName);
            } else {
                this.admin.notify(res?.error || 'Rename failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Rename failed: ' + e.message, 'error');
        }
    }

    async duplicateActiveTable() {
        const oldName = this.state.activeTable;
        const newName = document.getElementById('op-copy-input')?.value?.trim();
        const mode = document.querySelector('input[name="copy-data-mode"]:checked')?.value || 'data';
        const withData = (mode === 'data');
        if (!newName) {
            return this.admin.notify('Please enter a target table name.', 'error');
        }
        try {
            const res = await this.admin.api('db_duplicate_table', {
                table: oldName,
                new_table: newName,
                with_data: withData,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(res.message || `Table duplicated to '${newName}'.`, 'success');
                await this.fetchTables();
                await this.selectTable(newName);
            } else {
                this.admin.notify(res?.error || 'Duplication failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Duplication failed: ' + e.message, 'error');
        }
    }

    async truncateActiveTable() {
        const table = this.state.activeTable;
        if (!confirm(`WARNING: Truncating table '${table}' will delete ALL records permanently. Are you sure?`)) return;
        try {
            const res = await this.admin.api('db_truncate_table', {
                table: table,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(res.message || `Table '${table}' truncated.`, 'success');
                await this.selectTable(table);
            } else {
                this.admin.notify(res?.error || 'Truncate failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Truncate failed: ' + e.message, 'error');
        }
    }

    async dropActiveTable() {
        const table = this.state.activeTable;
        if (!confirm(`CRITICAL WARNING: Dropping table '${table}' will permanently delete the table schema and all data. Are you absolutely certain?`)) return;
        try {
            const res = await this.admin.api('db_drop_table', {
                table: table,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Table '${table}' dropped successfully.`, 'success');
                await this.fetchTables();
            } else {
                this.admin.notify(res?.error || 'Drop failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Drop failed: ' + e.message, 'error');
        }
    }

    async checkActiveDatabase(dbName = null) {
        const targetDb = dbName || this.state.selectedDatabase || 'default';
        this.admin.notify(`Running integrity check on '${targetDb}'...`, 'info');
        try {
            const res = await this.admin.api('db_check_database', {
                database: targetDb,
                connection: targetDb,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                const type = (res.data?.status === 'WARNING' || res.status === 'WARNING') ? 'warning' : 'success';
                this.admin.notify(`[${targetDb}] Integrity Check: ${res.message || 'OK'}`, type);
            } else {
                const errMsg = res?.message || res?.error || 'Check failed';
                const hint = res?.hint ? ` (${res.hint})` : '';
                this.admin.notify(`[${targetDb}] Integrity Check: ${errMsg}${hint}`, 'error');
            }
        } catch (e) {
            this.admin.notify('Integrity check failed: ' + e.message, 'error');
        }
    }

    async repairActiveDatabase(dbName = null) {
        const targetDb = dbName || this.state.selectedDatabase || 'default';
        this.admin.notify(`Optimizing & vacuuming '${targetDb}'...`, 'info');
        try {
            const res = await this.admin.api('db_repair_database', {
                database: targetDb,
                connection: targetDb,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`[${targetDb}] Optimization Complete: ${res.message || 'Storage vacuumed & defragmented.'}`, 'success');
                await this.fetchProvisioningData();
                await this.fetchDatabases();
            } else {
                this.admin.notify(`[${targetDb}] Optimization error: ${res?.message || res?.error || 'Failed'}`, 'error');
            }
        } catch (e) {
            this.admin.notify('Optimization failed: ' + e.message, 'error');
        }
    }

    openAddColumnModal() {
        const modal = document.getElementById('modal-container');
        if (!modal) return;
        const body = `
            <div style="padding:1rem;">
                <h4 style="margin:0 0 1rem 0;">Add Column to <code>${this.escapeHtml(this.state.activeTable)}</code></h4>
                <div class="suite-form-group" style="margin-bottom:0.8rem;">
                    <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Column Name:</label>
                    <input type="text" id="add-col-name" class="spp-element" placeholder="e.g. status, bio, count" style="width:100%;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
                    <div>
                        <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Type:</label>
                        <select id="add-col-type" class="spp-element" style="width:100%;">
                            <option value="VARCHAR(255)">VARCHAR(255)</option>
                            <option value="TEXT">TEXT</option>
                            <option value="INTEGER">INTEGER</option>
                            <option value="BOOLEAN">BOOLEAN</option>
                            <option value="DATETIME">DATETIME</option>
                            <option value="DECIMAL(10,2)">DECIMAL(10,2)</option>
                            <option value="BLOB">BLOB</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Nullability:</label>
                        <select id="add-col-null" class="spp-element" style="width:100%;">
                            <option value="1">Allow NULL</option>
                            <option value="0">NOT NULL</option>
                        </select>
                    </div>
                </div>
                <div class="suite-form-group" style="margin-bottom:1rem;">
                    <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Default Value (optional):</label>
                    <input type="text" id="add-col-default" class="spp-element" placeholder="e.g. NULL, 'active', 0" style="width:100%;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.closeConfigModal()">Cancel</button>
                    <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.submitAddColumn()">➕ Add Column</button>
                </div>
            </div>
        `;
        document.getElementById('modal-title').textContent = 'Add Column';
        document.getElementById('modal-body').innerHTML = body;
        const footer = document.getElementById('modal-footer');
        if (footer) footer.style.display = 'none';
        modal.classList.add('active');
    }

    async submitAddColumn() {
        const colName = document.getElementById('add-col-name')?.value?.trim();
        const colType = document.getElementById('add-col-type')?.value;
        const isNull = document.getElementById('add-col-null')?.value === '1';
        const defVal = document.getElementById('add-col-default')?.value?.trim();
        if (!colName) return this.admin.notify('Column name is required.', 'error');

        try {
            const res = await this.admin.api('db_add_column', {
                table: this.state.activeTable,
                column_name: colName,
                column_type: colType,
                is_null: isNull,
                default_value: defVal || null,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Column '${colName}' added successfully.`, 'success');
                this.closeConfigModal();
                await this.selectTable(this.state.activeTable);
            } else {
                this.admin.notify(res?.error || 'Failed to add column.', 'error');
            }
        } catch (e) {
            this.admin.notify('Add column failed: ' + e.message, 'error');
        }
    }

    async dropColumn(colName) {
        if (!confirm(`Are you sure you want to drop column '${colName}' from '${this.state.activeTable}'?`)) return;
        try {
            const res = await this.admin.api('db_drop_column', {
                table: this.state.activeTable,
                column_name: colName,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Column '${colName}' dropped successfully.`, 'success');
                await this.selectTable(this.state.activeTable);
            } else {
                this.admin.notify(res?.error || 'Drop column failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Drop column failed: ' + e.message, 'error');
        }
    }

    openAddIndexModal() {
        const modal = document.getElementById('modal-container');
        if (!modal) return;
        const cols = this.state.columns || [];
        const body = `
            <div style="padding:1rem;">
                <h4 style="margin:0 0 1rem 0;">Add Index to <code>${this.escapeHtml(this.state.activeTable)}</code></h4>
                <div class="suite-form-group" style="margin-bottom:0.8rem;">
                    <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Index Name:</label>
                    <input type="text" id="add-idx-name" class="spp-element" placeholder="e.g. idx_${this.escapeAttr(this.state.activeTable)}_col" style="width:100%;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem;">
                    <div>
                        <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Target Column:</label>
                        <select id="add-idx-col" class="spp-element" style="width:100%;">
                            ${cols.map(c => `<option value="${this.escapeHtml(c.name)}">${this.escapeHtml(c.name)}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Index Type:</label>
                        <select id="add-idx-unique" class="spp-element" style="width:100%;">
                            <option value="0">INDEX (Standard)</option>
                            <option value="1">UNIQUE</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                    <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.closeConfigModal()">Cancel</button>
                    <button class="btn primary-btn btn-sm" onclick="window.databaseViewInstance.submitAddIndex()">⚡ Create Index</button>
                </div>
            </div>
        `;
        document.getElementById('modal-title').textContent = 'Add Index';
        document.getElementById('modal-body').innerHTML = body;
        const footer = document.getElementById('modal-footer');
        if (footer) footer.style.display = 'none';
        modal.classList.add('active');
    }

    async submitAddIndex() {
        const idxName = document.getElementById('add-idx-name')?.value?.trim();
        const colName = document.getElementById('add-idx-col')?.value;
        const isUnique = document.getElementById('add-idx-unique')?.value === '1';
        if (!idxName || !colName) return this.admin.notify('Index name and column are required.', 'error');

        try {
            const res = await this.admin.api('db_add_index', {
                table: this.state.activeTable,
                index_name: idxName,
                columns: [colName],
                is_unique: isUnique,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Index '${idxName}' created successfully.`, 'success');
                this.closeConfigModal();
                await this.selectTable(this.state.activeTable);
            } else {
                this.admin.notify(res?.error || 'Failed to create index.', 'error');
            }
        } catch (e) {
            this.admin.notify('Create index failed: ' + e.message, 'error');
        }
    }

    async dropIndex(idxName) {
        if (!confirm(`Are you sure you want to drop index '${idxName}'?`)) return;
        try {
            const res = await this.admin.api('db_drop_index', {
                index_name: idxName,
                table: this.state.activeTable,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Index '${idxName}' dropped successfully.`, 'success');
                await this.selectTable(this.state.activeTable);
            } else {
                this.admin.notify(res?.error || 'Drop index failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Drop index failed: ' + e.message, 'error');
        }
    }

    openCreateTableModal() {
        const modal = document.getElementById('modal-container');
        if (!modal) return;
        this.createTableCols = [
            { name: 'id', type: 'INTEGER', primary: true, nullable: false, default: '' },
            { name: 'title', type: 'VARCHAR(255)', primary: false, nullable: false, default: '' },
            { name: 'status', type: 'VARCHAR(50)', primary: false, nullable: true, default: "'active'" },
            { name: 'created_at', type: 'DATETIME', primary: false, nullable: true, default: 'CURRENT_TIMESTAMP' }
        ];

        const renderColsList = () => {
            return this.createTableCols.map((c, i) => `
                <div style="display:grid; grid-template-columns:2fr 2fr 1fr 1fr 1fr 40px; gap:0.5rem; align-items:center; margin-bottom:0.4rem;">
                    <input type="text" class="spp-element" value="${this.escapeAttr(c.name)}" placeholder="col_name" onchange="window.databaseViewInstance.createTableCols[${i}].name = this.value.trim()" style="font-size:0.8rem;">
                    <select class="spp-element" onchange="window.databaseViewInstance.createTableCols[${i}].type = this.value" style="font-size:0.8rem;">
                        <option value="INTEGER" ${c.type === 'INTEGER' ? 'selected' : ''}>INTEGER</option>
                        <option value="VARCHAR(255)" ${c.type === 'VARCHAR(255)' ? 'selected' : ''}>VARCHAR(255)</option>
                        <option value="TEXT" ${c.type === 'TEXT' ? 'selected' : ''}>TEXT</option>
                        <option value="DATETIME" ${c.type === 'DATETIME' ? 'selected' : ''}>DATETIME</option>
                        <option value="BOOLEAN" ${c.type === 'BOOLEAN' ? 'selected' : ''}>BOOLEAN</option>
                        <option value="DECIMAL(10,2)" ${c.type === 'DECIMAL(10,2)' ? 'selected' : ''}>DECIMAL(10,2)</option>
                    </select>
                    <label style="font-size:0.75rem; display:flex; align-items:center; gap:0.25rem;">
                        <input type="checkbox" ${c.primary ? 'checked' : ''} onchange="window.databaseViewInstance.createTableCols[${i}].primary = this.checked"> PK
                    </label>
                    <label style="font-size:0.75rem; display:flex; align-items:center; gap:0.25rem;">
                        <input type="checkbox" ${c.nullable ? 'checked' : ''} onchange="window.databaseViewInstance.createTableCols[${i}].nullable = this.checked"> Null
                    </label>
                    <input type="text" class="spp-element" value="${this.escapeAttr(c.default || '')}" placeholder="Default" onchange="window.databaseViewInstance.createTableCols[${i}].default = this.value.trim()" style="font-size:0.75rem;">
                    <button type="button" class="btn danger-btn btn-sm" onclick="window.databaseViewInstance.removeWizardCol(${i})" style="padding:2px 6px;">✕</button>
                </div>
            `).join('');
        };

        window.databaseViewInstance.refreshWizardCols = () => {
            const el = document.getElementById('wizard-cols-container');
            if (el) el.innerHTML = renderColsList();
        };
        window.databaseViewInstance.addWizardCol = () => {
            this.createTableCols.push({ name: 'field_' + (this.createTableCols.length + 1), type: 'VARCHAR(255)', primary: false, nullable: true, default: '' });
            window.databaseViewInstance.refreshWizardCols();
        };
        window.databaseViewInstance.removeWizardCol = (idx) => {
            if (this.createTableCols.length <= 1) return;
            this.createTableCols.splice(idx, 1);
            window.databaseViewInstance.refreshWizardCols();
        };

        const body = `
            <div style="padding:1rem;">
                <h4 style="margin:0 0 1rem 0;">Create New Database Table</h4>
                <div class="suite-form-group" style="margin-bottom:1rem;">
                    <label style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">Table Name:</label>
                    <input type="text" id="wizard-table-name" class="spp-element" placeholder="e.g. students, invoices, courses" style="width:100%; font-size:0.85rem;">
                </div>
                <div style="margin-bottom:0.8rem; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.8rem; font-weight:600;">Columns Specification:</span>
                    <button type="button" class="btn secondary-btn btn-sm" onclick="window.databaseViewInstance.addWizardCol()">➕ Add Column</button>
                </div>
                <div style="display:grid; grid-template-columns:2fr 2fr 1fr 1fr 1fr 40px; gap:0.5rem; font-size:0.75rem; color:var(--text-dim); margin-bottom:0.4rem; padding-bottom:0.2rem; border-bottom:1px solid var(--border);">
                    <span>Name</span><span>Type</span><span>PK</span><span>Null</span><span>Default</span><span></span>
                </div>
                <div id="wizard-cols-container" style="max-height:220px; overflow-y:auto; margin-bottom:1rem;">
                    ${renderColsList()}
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.5rem; border-top:1px solid var(--border); padding-top:1rem;">
                    <button class="btn ghost-btn btn-sm" onclick="window.databaseViewInstance.closeConfigModal()">Cancel</button>
                    <button class="btn primary-btn shine-effect btn-sm" onclick="window.databaseViewInstance.submitCreateTable()">🚀 Create Table</button>
                </div>
            </div>
        `;

        document.getElementById('modal-title').textContent = 'Create Table Wizard';
        document.getElementById('modal-body').innerHTML = body;
        const footer = document.getElementById('modal-footer');
        if (footer) footer.style.display = 'none';
        modal.classList.add('active');
    }

    async submitCreateTable() {
        const tableName = document.getElementById('wizard-table-name')?.value?.trim();
        if (!tableName) return this.admin.notify('Table name is required.', 'error');
        if (!this.createTableCols || this.createTableCols.length === 0) return this.admin.notify('At least one column is required.', 'error');

        try {
            const res = await this.admin.api('db_create_table', {
                table: tableName,
                columns: this.createTableCols,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(res.message || `Table '${tableName}' created successfully.`, 'success');
                this.closeConfigModal();
                await this.fetchTables();
                await this.selectTable(tableName);
            } else {
                this.admin.notify(res?.error || 'Failed to create table.', 'error');
            }
        } catch (e) {
            this.admin.notify('Create table failed: ' + e.message, 'error');
        }
    }

    toggleSelectAll(checked) {
        if (!this.state.selectedRowIds) this.state.selectedRowIds = new Set();
        const pk = this.state.primaryKey || 'id';
        if (checked) {
            (this.state.rows || []).forEach(r => {
                if (r[pk] !== undefined && r[pk] !== null) {
                    this.state.selectedRowIds.add(String(r[pk]));
                }
            });
        } else {
            this.state.selectedRowIds.clear();
        }
        this.update();
    }

    toggleSelectRow(pkVal, checked) {
        if (!this.state.selectedRowIds) this.state.selectedRowIds = new Set();
        const strVal = String(pkVal);
        if (checked) {
            this.state.selectedRowIds.add(strVal);
        } else {
            this.state.selectedRowIds.delete(strVal);
        }
        this.update();
    }

    clearSelection() {
        if (this.state.selectedRowIds) this.state.selectedRowIds.clear();
        this.update();
    }

    async deleteSelectedBatch() {
        if (!this.state.selectedRowIds || this.state.selectedRowIds.size === 0) return;
        const count = this.state.selectedRowIds.size;
        const pk = this.state.primaryKey || 'id';
        if (!confirm(`Are you sure you want to delete ${count} selected record(s) from '${this.state.activeTable}'?`)) return;

        try {
            const keys = Array.from(this.state.selectedRowIds);
            const res = await this.admin.api('db_delete_rows_batch', {
                table: this.state.activeTable,
                primary_key: pk,
                keys: keys,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            if (res && res.success) {
                this.admin.notify(`Deleted ${res.deleted_count || count} row(s) successfully.`, 'success');
                this.state.selectedRowIds.clear();
                await this.selectTable(this.state.activeTable);
            } else {
                this.admin.notify(res?.error || 'Batch deletion failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Batch deletion error: ' + e.message, 'error');
        }
    }

    async changePageSize(newSize) {
        const size = parseInt(newSize, 10) || 25;
        this.setState({ pageSize: size, page: 1, loadingRows: true });
        this.update();
        try {
            const res = await this.admin.api('db_query_table', {
                table: this.state.activeTable,
                page: 1,
                page_size: size,
                sort_col: this.state.sortCol,
                sort_dir: this.state.sortDir,
                search: this.state.search,
                connection: this.state.selectedDatabase,
                context: this.admin.selectedApp
            });
            const queryData = (res.success && res.data) ? res.data : res;
            this.setState({
                rows: queryData.rows || [],
                totalRows: queryData.total_rows || 0,
                totalPages: queryData.total_pages || 1,
                loadingRows: false
            });
        } catch (e) {
            this.admin.notify('Failed to update page size: ' + e.message, 'error');
            this.setState({ loadingRows: false });
        }
        this.update();
    }

    async quickSwitchAppToSqlite(appName) {
        if (!confirm(`Switch '${appName}' database driver to local SQLite (var/db/${appName}.sqlite)?\n\nThis will allow '${appName}' to run immediately with zero configuration while avoiding MySQL root authentication issues.`)) return;
        try {
            const res = await this.admin.api('db_save_config', {
                name: 'app:' + appName,
                app: appName,
                scope: 'app',
                config: {
                    driver: 'sqlite',
                    database: `var/db/${appName}.sqlite`,
                    sqlite_path: `var/db/${appName}.sqlite`,
                    table_prefix: appName + '_'
                }
            });
            if (res && res.success) {
                this.admin.notify(`'${appName}' switched to SQLite successfully!`, 'success');
                await this.fetchDatabases();
                await this.selectDatabase('app:' + appName);
            } else {
                this.admin.notify(res?.error || 'Switch failed.', 'error');
            }
        } catch (e) {
            this.admin.notify('Switch error: ' + e.message, 'error');
        }
    }

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    escapeAttr(str) {
        return this.escapeHtml(str);
    }

    update() {
        if (this.container) {
            this.container.innerHTML = this.render();
            window.databaseViewInstance = this;
        }
    }
}
