/**
 * EntitiesView Component
 * 
 * Manages framework entities, visual schema builder, and Magic DB viewer.
 * (Formerly imported SchemaBuilderView and MagicDBView, now fully inlined.)
 */

export default class EntitiesView extends BaseComponent {
    constructor(app, container, props) {
        super(app, container, props);
    }

    async onInit() {
        this.state = {
            loading: true,
            entities: [],
            activeFormTab: 'builder',
            activeMainTab: 'list',
            currentEntityName: '',
            currentEntitySource: '',
            currentEntityConfig: { table: '', attributes: {}, relations: [] },
            availableClasses: ['\\SPPMod\\SPPDB\\SPPEntity', '\\SPPMod\\SPPAuth\\SPPUser'],
            // --- Inlined SchemaBuilder state ---
            sbEntityName: 'NewEntity',
            sbTableName: '',
            sbExtendsClass: '',
            sbExtendsSelection: '',
            sbLoginEnabled: false,
            sbColumns: [
                { name: 'name', type: 'varchar(255)' },
                { name: 'created_at', type: 'timestamp' }
            ],
            sbRelations: [],
            sbSaving: false,
            sbSavePath: ''
        };
        await this.fetchData();
        await this.fetchBuilderContext();
    }

    async fetchBuilderContext() {
        const fd = new FormData();
        fd.append('action', 'get_builder_context');
        try {
            const res = await this.apiPost(fd);
            if (res.success) {
                this.setState({ availableClasses: res.data.classes });
            }
        } catch (e) {
            console.error("Failed to fetch builder context", e);
        }
    }

    async fetchData() {
        try {
            const res = await this.api('list_entities');
            if (res.success) {
                this.setState({
                    entities: res.data.entities || [],
                    loading: false
                });
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }


    render() {
        const { loading, entities, error, activeMainTab } = this.state;

        if (loading) return html`<div class="loading-state">Cataloging application entities...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        // Update Header Actions
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            if (activeMainTab === 'list') {
                const defaultSource = 'table: my_table\nid_field: id\nattributes:\n  name:\n    type: varchar\n    length: 255';
                const headerHtml = html`
                    <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openEditor('', defaultSource)}>+ New Entity</button>
                `;
                headerActions.innerHTML = headerHtml.toString();
            } else {
                headerActions.innerHTML = '';
            }
        }

        const tabsHtml = html`
            <div class="view-header">
                <h2>Data Entities</h2>
                <p>Manage Data Models, Schema, and Magic Database</p>
            </div>
            
            <div class="tab-bar-secondary" style="margin-bottom: 20px; display: flex; align-items: center; gap: 5px;">
                <button class="sub-tab-btn ${activeMainTab === 'list' ? 'active' : ''}" @click=${() => this.setState({ activeMainTab: 'list' })}>📋 Entity List</button>
                <button class="sub-tab-btn ${activeMainTab === 'builder' ? 'active' : ''}" @click=${() => this.setState({ activeMainTab: 'builder' })}>🏗️ Visual Builder</button>
                <button class="sub-tab-btn ${activeMainTab === 'magicdb' ? 'active' : ''}" @click=${() => this.setState({ activeMainTab: 'magicdb' })}>✨ Magic DB</button>
                <button class="sub-tab-btn ${activeMainTab === 'erd' ? 'active' : ''}" @click=${() => this.setState({ activeMainTab: 'erd' })}>🕸️ ERD Canvas</button>
                
                <div style="margin-left: auto; display: flex; gap: 5px;">
                    <button class="sub-tab-btn" style="color: var(--success);" @click=${() => this.generateSdk()}>📦 Export SDK</button>
                    <button class="sub-tab-btn" style="color: var(--info);" @click=${() => this.generateDocker()}>🐳 Docker Deploy</button>
                    <button class="sub-tab-btn" style="color: var(--warning);" @click=${() => this.scaffoldAuth()}>🔐 Scaffold Auth System</button>
                </div>
            </div>
        `;

        if (activeMainTab === 'builder') {
            return html`
                ${tabsHtml}
                <div id="builder-container">
                    ${this.renderSchemaBuilder()}
                </div>
            `;
        }

        if (activeMainTab === 'magicdb') {
            return html`
                ${tabsHtml}
                <div id="magicdb-container">
                    ${this.renderMagicDB()}
                </div>
            `;
        }

        if (activeMainTab === 'erd') {
            return html`
                ${tabsHtml}
                <div class="spp-card" style="position: relative; height: 600px; overflow: auto; background: var(--surface-1); padding: 20px;">
                    ${this.renderERD(entities)}
                </div>
            `;
        }

        if (entities.length === 0) {
            return html`
                ${tabsHtml}
                <div class="empty-state">
                    <div class="empty-icon">🏗️</div>
                    <h3>No Entities Defined</h3>
                    <p>Applications in SPP use YAML-defined entities for decoupled data management.</p>
                    <button type="button" class="btn primary-btn" @click=${() => this.openEditor('', 'table: my_table\nid_field: id\nattributes:\n  name:\n    type: varchar\n    length: 255')}>+ Create Entity</button>
                </div>
            `;
        }

        return html`
            ${tabsHtml}
            <div class="card-grid">
                ${entities.map((ent, i) => {
            const metaInfo = [
                ent.table ? `Table: ${ent.table}` : null,
                ent.extends ? `Extends: ${ent.extends.split('\\').pop()}` : null,
                ent.login_enabled ? '🔑 Auth' : null
            ].filter(x => x).join(' · ');
            return html`
                        <div class="item-card" style="animation-delay: ${i * 0.05}s">
                            <div class="card-header">
                                <div>
                                    <h3>${ent.name}</h3>
                                    <p class="text-dim" style="margin-bottom: 0.5rem; font-size: 0.8rem;">
                                        ${ent.yaml_path ? html`<span class="badge" style="background: var(--primary-bg); color: var(--primary); padding: 2px 6px; border-radius: 4px; margin-right: 5px;">YAML</span>` : ''}
                                        ${ent.php_path ? html`<span class="badge" style="background: var(--success-bg); color: var(--success); padding: 2px 6px; border-radius: 4px;">PHP</span>` : ''}
                                    </p>
                                </div>
                            </div>
                            <div class="card-footer">
                                <small>${ent.size ? Math.round(ent.size / 1024 * 100) / 100 + ' KB' : ''}</small>
                                <div class="card-actions">
                                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openEditor(ent.name, ent.yaml_content, ent.php_content)}>Edit</button>
                                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.scaffoldDashboard(ent.name)}>📊 Dash</button>
                                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.generateTests(ent.name)}>🧪 Tests</button>
                                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.generateForm(ent.name, ent.yaml_content)}>Form</button>
                                    <button type="button" class="btn danger-btn btn-sm" @click=${() => this.confirmDelete('entity', ent.name)}>Delete</button>
                                </div>
                            </div>
                        </div>
                    `;
        })}
            </div>
        `;
    }

    // =============================================
    // EDITOR LOGIC
    // =============================================

    afterUpdate() {
        if (this.state.activeMainTab === 'erd' && window.SPPUX && SPPUX.Draggable) {
            const savedPositions = JSON.parse(localStorage.getItem('spp_erd_positions') || '{}');
            document.querySelectorAll('.erd-node').forEach(node => {
                if (!node.__draggable) {
                    node.__draggable = new SPPUX.Draggable(node, {
                        onDragEnd: (el, x, y) => {
                            const name = el.getAttribute('data-entity-name');
                            if (name) {
                                savedPositions[name] = { x, y };
                                localStorage.setItem('spp_erd_positions', JSON.stringify(savedPositions));
                            }
                        }
                    });
                }
            });
        }
    }

    renderERD(entities) {
        // Simple grid layout for nodes
        const spacingX = 250;
        const spacingY = 200;
        const savedPositions = JSON.parse(localStorage.getItem('spp_erd_positions') || '{}');
        let cols = 3;

        let nodes = [];
        entities.forEach((ent, i) => {
            let x = (i % cols) * spacingX + 50;
            let y = Math.floor(i / cols) * spacingY + 50;

            if (savedPositions[ent.name]) {
                x = savedPositions[ent.name].x;
                y = savedPositions[ent.name].y;
            }

            let attrs = [];
            if (ent.yaml_content) {
                try {
                    const parsed = jsyaml.load(ent.yaml_content);
                    if (parsed && parsed.attributes) {
                        attrs = Object.keys(parsed.attributes);
                    }
                } catch (e) { }
            }

            nodes.push(html`
                <div class="erd-node spp-card" data-entity-name="${ent.name}" style="position: absolute; left: ${x}px; top: ${y}px; width: 200px; padding: 10px; z-index: 10; border-top: 4px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.1); cursor: move;">
                    <h4 style="margin: 0 0 10px 0; text-align: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 5px;">${ent.name}</h4>
                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.8rem; color: var(--text-dim);">
                        ${attrs.map(a => html`<li style="padding: 2px 0;">▫️ ${a}</li>`)}
                    </ul>
                </div>
            `);
        });

        return html`
            <div style="position: relative; width: 100%; height: 100%;">
                ${nodes}
            </div>
        `;
    }

    async generateForm(name, yamlContent) {
        if (!yamlContent) {
            SPPUX.notify('No YAML config found to scaffold form.', 'error');
            return;
        }

        try {
            const parseRes = await this.apiPost('parse_entity_yaml', { yaml: yamlContent });
            if (parseRes && parseRes.success) {
                const config = parseRes.data.config;
                const scaffoldRes = await this.apiPost('scaffold_form', { entityName: name, config: JSON.stringify(config) });
                if (scaffoldRes && scaffoldRes.success) {
                    const formConfig = scaffoldRes.data.formConfig;
                    const saveRes = await this.apiPost('save_form_config', { name: formConfig.form.name, config: JSON.stringify(formConfig) });
                    if (saveRes && saveRes.success) {
                        SPPUX.notify('Form generated and saved successfully! Switch to Forms module to view it.', 'success');
                    } else {
                        SPPUX.notify('Error saving generated form.', 'error');
                    }
                } else {
                    SPPUX.notify(scaffoldRes?.message || 'Error scaffolding form.', 'error');
                }
            }
        } catch (e) {
            console.error(e);
            SPPUX.notify('Error generating form', 'error');
        }
    }

    async scaffoldDashboard(entityName) {
        if (!confirm(`Generate a dynamic data-grid dashboard for ${entityName}?`)) return;
        const fd = new FormData();
        fd.append('action', 'scaffold_dashboard');
        fd.append('entityName', entityName);
        const res = await this.apiPost(fd);
        if (res && res.success) {
            SPPUX.notify(res.message, 'success');
        } else {
            SPPUX.notify(res?.message || 'Error scaffolding dashboard', 'error');
        }
    }

    async generateTests(entityName) {
        const fd = new FormData();
        fd.append('action', 'scaffold_test');
        fd.append('entityName', entityName);
        SPPUX.notify('Generating tests...', 'info');
        const res = await this.apiPost(fd);
        if (res && res.success) {
            SPPUX.notify('Tests generated successfully in tests/!', 'success');
        } else {
            SPPUX.notify(res?.message || 'Error generating tests', 'error');
        }
    }

    async generateSdk() {
        const fd = new FormData();
        fd.append('action', 'generate_sdk');
        SPPUX.notify('Generating API SDK...', 'info');
        const res = await this.apiPost(fd);
        if (res && res.success) {
            SPPUX.notify('SDK generated in src/spp_sdk.js!', 'success');
        } else {
            SPPUX.notify(res?.message || 'Error generating SDK', 'error');
        }
    }

    async generateDocker() {
        const fd = new FormData();
        fd.append('action', 'generate_docker');
        SPPUX.notify('Generating Docker config...', 'info');
        const res = await this.apiPost(fd);
        if (res && res.success) {
            SPPUX.notify('Dockerfile and docker-compose.yml generated!', 'success');
        } else {
            SPPUX.notify(res?.message || 'Error generating Docker config', 'error');
        }
    }

    async scaffoldAuth() {
        if (!confirm('This will scaffold User and Role entities, and basic auth logic. Proceed?')) return;
        const fd = new FormData();
        fd.append('action', 'scaffold_auth');
        const res = await this.apiPost(fd);
        if (res && res.success) {
            SPPUX.notify('Auth System scaffolded successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            SPPUX.notify(res?.message || 'Failed to scaffold Auth', 'error');
        }
    }

    async openEditor(name, yamlContent, phpContent) {
        this.state.activeFormTab = yamlContent ? 'builder' : 'php';
        this.state.currentEntityName = name || '';
        this.state.currentEntityYaml = yamlContent || '';
        this.state.currentEntityPhp = phpContent || '';
        this.state.currentEntityConfig = { table: '', attributes: {}, relations: [] };

        if (yamlContent) {
            const fd = new FormData();
            fd.append('action', 'parse_entity_yaml');
            fd.append('yaml', yamlContent);
            const res = await this.apiPost(fd);
            if (res.success) {
                this.state.currentEntityConfig = this._normalizeConfig(res.data.config);
            }
        }

        if (name) {
            const revRes = await this.apiPost('list_revisions', { name: name });
            if (revRes && revRes.success) {
                this.state.revisions = revRes.data.revisions || [];
            } else {
                this.state.revisions = [];
            }
        } else {
            this.state.revisions = [];
        }

        this.openModal(name ? `Entity: ${name}` : 'Create New Entity', this.getModalHtml(), [
            { label: name ? 'Save Changes' : 'Create Entity', type: 'primary', fn: () => this.save() }
        ]);
    }

    getModalHtml() {
        const { activeFormTab } = this.state;
        return html`
            <div class="tab-bar">
                <div style="display: flex; gap: 5px;">
                    <button type="button" class="tab-btn ${activeFormTab === 'builder' ? 'active' : ''}" 
                        @click=${() => this.switchTab('builder')}>Visual Builder</button>
                    <button type="button" class="tab-btn ${activeFormTab === 'yaml' ? 'active' : ''}" 
                        @click=${() => this.switchTab('yaml')}>Source (YAML)</button>
                    <button type="button" class="tab-btn ${activeFormTab === 'php' ? 'active' : ''}" 
                        @click=${() => this.switchTab('php')}>PHP Logic</button>
                    <button type="button" class="tab-btn ${activeFormTab === 'workflow' ? 'active' : ''}" 
                        @click=${() => this.switchTab('workflow')}>🔗 Workflow</button>
                </div>
                ${this.state.currentEntityName ? html`
                    <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 0.8rem; color: var(--text-dim);">Time Travel:</span>
                        <select @change=${(e) => this.restoreRevision(e.target.value)} style="padding: 2px 8px; font-size: 0.8rem; border-radius: 4px; background: var(--surface-3); border: 1px solid var(--glass-border); color: var(--text);">
                            <option value="">Current Version</option>
                            ${(this.state.revisions || []).map(r => html`<option value="${r.timestamp}">${r.date}</option>`)}
                        </select>
                    </div>
                ` : ''}
            </div>
            <div id="entity-editor-content" class="tab-content active" style="margin-top: 1.5rem;">
                ${activeFormTab === 'builder' ? this.getVisualBuilderHtml() : ''}
                ${activeFormTab === 'yaml' ? this.getYamlHtml() : ''}
                ${activeFormTab === 'php' ? this.getPhpHtml() : ''}
                ${activeFormTab === 'workflow' ? this.getWorkflowHtml() : ''}
            </div>
        `;
    }

    getWorkflowHtml() {
        return html`
            <div class="spp-card">
                <h4>Visual Workflow Builder</h4>
                <p class="text-muted">Chain actions to automatically execute on entity events.</p>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <select id="wf-trigger" class="form-control" style="width: auto;">
                        <option value="before_save">On Before Save</option>
                        <option value="after_save">On After Save</option>
                        <option value="after_delete">On After Delete</option>
                    </select>
                    <span>➔</span>
                    <select id="wf-action" class="form-control" style="width: auto;">
                        <option value="email">Send Notification Email</option>
                        <option value="log">Audit Log Event</option>
                        <option value="validate">Run Strict Validation</option>
                        <option value="webhook">Trigger Webhook</option>
                    </select>
                    <button type="button" class="btn primary-btn btn-sm" @click=${() => this.addWorkflowNode()}>+ Add to Pipeline</button>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; border: 1px dashed var(--glass-border); border-radius: 4px; background: var(--surface-2);">
                    <h5 style="margin-top:0;">Pipeline Preview</h5>
                    <p style="font-size: 0.85rem;">Actions added here will be automatically compiled into your PHP Logic tab.</p>
                </div>
            </div>
        `;
    }

    async addWorkflowNode() {
        const trigger = document.getElementById('wf-trigger')?.value;
        const action = document.getElementById('wf-action')?.value;
        if (!trigger || !action) return;

        const fd = new FormData();
        fd.append('action', 'compile_workflow');
        fd.append('trigger', trigger);
        fd.append('task', action);

        SPPUX.notify('Compiling workflow to PHP...', 'info');
        const res = await this.apiPost(fd);
        if (res && res.success) {
            let content = this.state.currentEntityPhp || '';
            const lastBraceIdx = content.lastIndexOf('}');
            if (lastBraceIdx !== -1) {
                content = content.substring(0, lastBraceIdx) + res.data.code + "\n" + content.substring(lastBraceIdx);
                this.state.currentEntityPhp = content;
                SPPUX.notify('Pipeline compiled! Check PHP tab.', 'success');
                this.refreshModal();
            }
        } else {
            SPPUX.notify('Error compiling workflow', 'error');
        }
    }

    async restoreRevision(timestamp) {
        if (!timestamp) return;
        if (!confirm('Are you sure you want to restore this previous version? Your current changes will be overwritten (but backed up as a new revision).')) return;

        const res = await this.apiPost('restore_revision', { name: this.state.currentEntityName, timestamp: timestamp });
        if (res && res.success) {
            SPPUX.notify(res.message, 'success');
            // Re-open editor with restored contents
            await this.openEditor(this.state.currentEntityName, res.data.yaml, res.data.php);
        } else {
            SPPUX.notify(res?.message || 'Failed to restore revision', 'error');
        }
    }

    async previewMigration() {
        const res = await this.apiPost('preview_migration', {
            name: this.state.currentEntityName,
            yaml: this.generateYamlSync()
        });
        if (res && res.success) {
            const sql = res.data.sql;
            if (sql.length === 0) {
                SPPUX.notify('Schema is up to date. No migrations needed.', 'success');
            } else {
                alert("The following SQL will be executed on next save:\n\n" + sql.join(";\n") + ";");
            }
        } else {
            SPPUX.notify(res?.message || 'Error generating migration preview', 'error');
        }
    }

    async generateSchemaFromPrompt() {
        const prompt = document.getElementById('ai-schema-prompt')?.value;
        if (!prompt) return;

        const fd = new FormData();
        fd.append('action', 'magic_generate_schema');
        fd.append('prompt', prompt);

        SPPUX.notify('Analyzing prompt...', 'info');
        const res = await this.apiPost(fd);
        if (res && res.success) {
            SPPUX.notify('Schema updated!', 'success');
            // Merge into current config
            const newConfig = res.data.config;
            this.state.currentEntityConfig = {
                ...this.state.currentEntityConfig,
                attributes: {
                    ...(this.state.currentEntityConfig.attributes || {}),
                    ...(newConfig.attributes || {})
                }
            };
            this.refreshModal();
        } else {
            SPPUX.notify(res?.message || 'Failed to parse', 'error');
        }
    }

    switchTab(tab) {
        if (this.state.activeFormTab === tab) return;
        const prevTab = this.state.activeFormTab;

        // Grab current value before switching
        if (prevTab === 'yaml') {
            this.state.currentEntityYaml = document.getElementById('editor-yaml-content')?.value || '';
        } else if (prevTab === 'php') {
            this.state.currentEntityPhp = document.getElementById('editor-php-content')?.value || '';
        }

        this.state.activeFormTab = tab;

        // Propagate YAML changes
        if (prevTab === 'yaml') {
            this.syncSourceToBuilderSync(this.state.currentEntityYaml);
            this.syncBuilderToPhpSync();
        }

        // Propagate PHP changes
        if (prevTab === 'php') {
            this.syncPhpToBuilderSync(this.state.currentEntityPhp);
            this.state.currentEntityYaml = this.generateYamlSync();
        }

        // Propagate Builder changes
        if (prevTab === 'builder') {
            this.state.currentEntityYaml = this.generateYamlSync();
            this.syncBuilderToPhpSync();
        }

        this.refreshModal();
    }

    getVisualBuilderHtml() {
        const config = this.state.currentEntityConfig;
        const attrs = config.attributes || {};
        const relations = config.relations || [];

        const currentExtends = config.extends || '';
        const isExtendsCustom = currentExtends && !this.state.availableClasses.includes(currentExtends);
        const isExtendsOther = this.state._extendsOther || isExtendsCustom;

        return html`
            <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn btn-sm secondary-btn" @click=${() => this.importFromDB()}>Import from DB Table</button>
                <button type="button" class="btn btn-sm secondary-btn" @click=${() => this.previewMigration()}>🔍 Preview Migration (SQL)</button>
                
                <div style="margin-left: auto; display: flex; gap: 5px;">
                    <input type="text" id="ai-schema-prompt" placeholder="e.g. A user has a name and dob..." style="padding: 4px 8px; border-radius: 4px; border: 1px solid var(--glass-border); background: var(--surface-2); color: var(--text); font-size: 0.8rem; width: 250px;">
                    <button type="button" class="btn btn-sm primary-btn" @click=${() => this.generateSchemaFromPrompt()}>✨ Magic Generate</button>
                </div>
            </div>
            <datalist id="available-classes-list-edit">
                ${this.state.availableClasses.map(c => html`<option value="${c}"></option>`)}
            </datalist>
            
            <div class="builder-layout">
                <div class="builder-sidebar glass-panel">
                    <h4>Entity Settings</h4>
                    <div class="input-group">
                        <label title="The PHP class name for your entity. Must be in PascalCase (e.g. StudentProfile).">Class Name ℹ️</label>
                        <input type="text" value="${this.state.currentEntityName}" 
                            @change=${(e) => { this.state.currentEntityName = e.target.value; }} 
                            placeholder="e.g. Staff" ${this.state.currentEntityName ? 'disabled' : ''}>
                    </div>
                    <div class="input-group">
                        <label title="The physical database table name where this entity's records will be stored. Usually plural lowercase (e.g. student_profiles).">Database Table ℹ️</label>
                        <input type="text" value="${config.table || ''}" 
                            @change=${(e) => { config.table = e.target.value; }} placeholder="e.g. staffs">
                    </div>
                    <div class="input-group">
                        <label title="The parent PHP class this entity inherits from. Use 'Person' for users, or 'BaseEntity' for standard objects.">Extends (Parent) ℹ️</label>
                        <select @change=${(e) => {
                if (e.target.value === '__other__') {
                    this.state._extendsOther = true;
                } else {
                    this.state._extendsOther = false;
                    config.extends = e.target.value;
                }
                this.refreshModal();
            }} style="margin-bottom: ${isExtendsOther ? '0.5rem' : '0'};">
                            <option value="">(None)</option>
                            ${this.state.availableClasses.map(c => html`<option value="${c}" ?selected="${currentExtends === c && !this.state._extendsOther}">${c}</option>`)}
                            <option value="__other__" ?selected="${isExtendsOther}">Other...</option>
                        </select>
                        ${isExtendsOther ? html`
                            <input type="text" value="${config.extends || ''}" @change=${(e) => { config.extends = e.target.value; }} placeholder="Enter custom class">
                        ` : ''}
                    </div>
                    <div class="input-group checkbox-group">
                        <label title="Enable this if instances of this entity should be able to log into the application (requires the entity to extend Person or implement Auth interfaces).">
                            <input type="checkbox" ?checked="${config.login_enabled}" 
                            @change=${(e) => { config.login_enabled = e.target.checked; }}> Login Enabled ℹ️
                        </label>
                    </div>
                    <div class="input-group checkbox-group">
                        <label title="Automatically generate standard RESTful endpoints for this entity at /api/v1/{EntityName}">
                            <input type="checkbox" ?checked="${config.enable_api}" 
                            @change=${(e) => { config.enable_api = e.target.checked; }}> Enable REST API ⚡
                        </label>
                    </div>
                </div>
                <div class="builder-main">
                    <div class="section-card attributes-section">
                        <div class="section-header">
                            <h4>Attributes</h4>
                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.addAttribute()}>+ Add Attribute</button>
                        </div>
                        <div class="attribute-list">
                            ${Object.entries(attrs).map(([name, type]) => this.getAttributeRowHtml(name, type))}
                        </div>
                    </div>
                    
                    <div class="section-card relations-section">
                        <div class="section-header">
                            <h4>Relationships</h4>
                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.addRelation()}>+ Add Relation</button>
                        </div>
                        <div class="relation-list">
                            ${relations.map((rel, idx) => this.getRelationRowHtml(rel, idx))}
                        </div>
                    </div>
                </div>
            </div>`;
    }

    getYamlHtml() {
        return html`
            <div class="form-group">
                <textarea id="editor-yaml-content" spellcheck="false" style="min-height:400px; font-family:monospace;">${this.state.currentEntityYaml}</textarea>
            </div>
        `;
    }

    getPhpHtml() {
        return html`
            <div style="margin-bottom: 15px; display: flex; gap: 5px; align-items: center; background: var(--surface-2); padding: 10px; border-radius: 4px; border: 1px solid var(--glass-border);">
                <span>🤖 Copilot:</span>
                <input type="text" id="ai-logic-prompt" placeholder="e.g. email the user after save" style="padding: 6px; border-radius: 4px; border: 1px solid var(--glass-border); background: var(--surface-3); color: var(--text); flex-grow: 1;">
                <button type="button" class="btn btn-sm primary-btn" @click=${() => this.generateLogicFromPrompt()}>✨ Write Code</button>
            </div>
            <div class="php-snippet-toolbar" style="margin-bottom: 10px; display: flex; gap: 8px;">
                <button type="button" class="btn btn-sm secondary-btn" @click=${() => this.insertPhpSnippet('before_save')}>+ before_save()</button>
                <button type="button" class="btn btn-sm secondary-btn" @click=${() => this.insertPhpSnippet('after_save')}>+ after_save()</button>
                <button type="button" class="btn btn-sm secondary-btn" @click=${() => this.insertPhpSnippet('rules')}>+ rules()</button>
                <button type="button" class="btn btn-sm secondary-btn" @click=${() => this.insertPhpSnippet('scope')}>+ custom scope</button>
            </div>
            <div class="form-group">
                <textarea id="editor-php-content" spellcheck="false" style="min-height:400px; font-family:monospace; width: 100%; tab-size: 4;"
                    @keydown=${(e) => this.handlePhpCodeCompletion(e)}>${this.state.currentEntityPhp}</textarea>
            </div>
        `;
    }

    async generateLogicFromPrompt() {
        const prompt = document.getElementById('ai-logic-prompt')?.value;
        if (!prompt) return;

        const fd = new FormData();
        fd.append('action', 'ai_generate_logic');
        fd.append('prompt', prompt);

        SPPUX.notify('AI is writing code...', 'info');
        const res = await this.apiPost(fd);
        if (res && res.success) {
            const textarea = document.getElementById('editor-php-content');
            if (textarea) {
                let content = textarea.value;
                const lastBraceIdx = content.lastIndexOf('}');
                if (lastBraceIdx !== -1) {
                    content = content.substring(0, lastBraceIdx) + res.data.code + "\n" + content.substring(lastBraceIdx);
                    textarea.value = content;
                    this.state.currentEntityPhp = content;
                    SPPUX.notify('Code injected successfully!', 'success');
                }
            }
        } else {
            SPPUX.notify(res?.message || 'Failed to generate code', 'error');
        }
    }

    insertPhpSnippet(type) {
        const textarea = document.getElementById('editor-php-content');
        if (!textarea) return;
        let snippet = '';
        if (type === 'before_save') snippet = `\n    public function before_save() {\n        // Your logic here\n        return parent::before_save();\n    }\n`;
        else if (type === 'after_save') snippet = `\n    public function after_save() {\n        // Your logic here\n        return parent::after_save();\n    }\n`;
        else if (type === 'rules') snippet = `\n    public function rules() {\n        return [\n            // 'email' => 'required|email'\n        ];\n    }\n`;
        else if (type === 'scope') snippet = `\n    public function scopeCustom($query) {\n        $query->where('status', '=', 'active');\n    }\n`;

        let content = textarea.value;
        const lastBraceIdx = content.lastIndexOf('}');
        if (lastBraceIdx !== -1) {
            content = content.substring(0, lastBraceIdx) + snippet + content.substring(lastBraceIdx);
        } else {
            content += snippet;
        }
        textarea.value = content;
        this.state.currentEntityPhp = content;

        // Basic sync back to visual if possible (though snippets don't affect visual config)
    }

    handlePhpCodeCompletion(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = e.target.selectionStart;
            const end = e.target.selectionEnd;
            e.target.value = e.target.value.substring(0, start) + "    " + e.target.value.substring(end);
            e.target.selectionStart = e.target.selectionEnd = start + 4;
            this.state.currentEntityPhp = e.target.value;
        }
    }

    async importFromDB() {
        const tableName = prompt('Enter the name of the database table to import schema from:');
        if (!tableName) return;

        const res = await this.apiPost('introspect_table', { table: tableName });
        if (res && res.success && res.data.config) {
            this.state.currentEntityConfig.table = res.data.config.table;
            this.state.currentEntityConfig.attributes = { ...this.state.currentEntityConfig.attributes, ...res.data.config.attributes };
            this.refreshModal();
            SPPUX.notify('Schema imported successfully!', 'success');
        }
    }

    getAttributeRowHtml(name, type) {
        const typeStr = String(type);
        return html`
            <div class="attribute-row">
                <input type="text" value="${name}" @change=${(e) => this.updateAttributeName(name, e.target.value)} placeholder="Field name">
                <select @change=${(e) => { this.state.currentEntityConfig.attributes[name] = e.target.value; }}>
                    <option value="varchar(255)" ?selected="${typeStr.includes('varchar')}">Varchar</option>
                    <option value="int" ?selected="${typeStr === 'int'}">Integer</option>
                    <option value="bigint" ?selected="${typeStr === 'bigint'}">BigInt</option>
                    <option value="text" ?selected="${typeStr === 'text'}">Text</option>
                    <option value="datetime" ?selected="${typeStr === 'datetime'}">DateTime</option>
                    <option value="decimal(10,2)" ?selected="${typeStr.includes('decimal')}">Decimal</option>
                </select>
                <button type="button" class="btn btn-icon danger" @click=${() => this.removeAttribute(name)}>✕</button>
            </div>`;
    }

    getRelationRowHtml(rel, idx) {
        return html`
            <div class="relation-row card">
                <div class="rel-meta">
                    <select @change=${(e) => { this.state.currentEntityConfig.relations[idx].relation_type = e.target.value; this.refreshModal(); }}>
                        <option value="OneToMany" ?selected="${rel.relation_type === 'OneToMany'}">One-to-Many</option>
                        <option value="ManyToMany" ?selected="${rel.relation_type === 'ManyToMany'}">Many-to-Many</option>
                    </select>
                    <span>Target:</span>
                    <select @change=${(e) => { this.state.currentEntityConfig.relations[idx].child_entity = e.target.value; this.refreshModal(); }}>
                        <option value="">-- Select Target Entity --</option>
                        ${this.state.entities.map(ent => html`<option value="${ent.name}" ?selected="${rel.child_entity === ent.name}">${ent.name}</option>`)}
                    </select>
                </div>
                <div class="rel-fields">
                    <input type="text" value="${rel.child_entity_field || ''}" 
                        @change=${(e) => { this.state.currentEntityConfig.relations[idx].child_entity_field = e.target.value; }} placeholder="FK Field">
                    ${rel.relation_type === 'ManyToMany' ? html`
                        <input type="text" value="${rel.pivot_table || ''}" 
                            @change=${(e) => { this.state.currentEntityConfig.relations[idx].pivot_table = e.target.value; }} placeholder="Pivot Table">
                    ` : ''}
                </div>
                <button type="button" class="btn btn-icon danger" @click=${() => this.removeRelation(idx)}>✕</button>
            </div>`;
    }

    // Helper to refresh modal content without closing it
    refreshModal() {
        const title = this.state.currentEntityName ? `Entity: ${this.state.currentEntityName}` : 'Create New Entity';
        this.updateModal(title, this.getModalHtml(), [
            { label: this.state.currentEntityName ? 'Save Changes' : 'Create Entity', type: 'primary', fn: () => this.save() }
        ]);
    }

    addAttribute() {
        const nextId = Object.keys(this.state.currentEntityConfig.attributes).length + 1;
        this.state.currentEntityConfig.attributes['new_attr_' + nextId] = 'varchar(255)';
        this.refreshModal();
    }

    removeAttribute(name) {
        delete this.state.currentEntityConfig.attributes[name];
        this.refreshModal();
    }

    updateAttributeName(oldName, newName) {
        if (!newName || oldName === newName) return;
        const type = this.state.currentEntityConfig.attributes[oldName];
        delete this.state.currentEntityConfig.attributes[oldName];
        this.state.currentEntityConfig.attributes[newName] = type;
        this.refreshModal();
    }

    addRelation() {
        this.state.currentEntityConfig.relations.push({ child_entity: '', child_entity_field: '', relation_type: 'OneToMany' });
        this.refreshModal();
    }

    removeRelation(idx) {
        this.state.currentEntityConfig.relations.splice(idx, 1);
        this.refreshModal();
    }

    _normalizeConfig(config) {
        if (!config) return { table: '', attributes: {}, relations: [] };
        if (!config.attributes || Array.isArray(config.attributes)) config.attributes = {};
        for (let key in config.attributes) {
            let attr = config.attributes[key];
            if (attr && typeof attr === 'object') {
                let type = attr.type || 'varchar';
                let len = attr.length || attr.size;
                config.attributes[key] = len ? `${type}(${len})` : type;
            }
        }
        if (!config.relations) config.relations = [];
        return config;
    }

    generateYamlSync() {
        const c = this.state.currentEntityConfig;
        let yaml = [];
        if (c.table) yaml.push(`table: ${c.table}`);
        if (c.extends) yaml.push(`extends: ${c.extends}`);
        if (c.login_enabled) yaml.push(`login_enabled: true`);
        if (c.enable_api) yaml.push(`enable_api: true`);
        if (c.id_field) yaml.push(`id_field: ${c.id_field}`);

        if (Object.keys(c.attributes || {}).length > 0) {
            yaml.push(`attributes:`);
            for (let k in c.attributes) {
                yaml.push(`  ${k}: ${c.attributes[k]}`);
            }
        }

        if ((c.relations || []).length > 0) {
            yaml.push(`relations:`);
            for (let r of c.relations) {
                yaml.push(`  - child_entity: '${r.child_entity}'`);
                yaml.push(`    relation_type: '${r.relation_type}'`);
                yaml.push(`    child_entity_field: '${r.child_entity_field}'`);
                if (r.pivot_table) yaml.push(`    pivot_table: '${r.pivot_table}'`);
            }
        }
        return yaml.join('\n');
    }

    syncSourceToBuilderSync(yaml) {
        const config = { table: '', attributes: {}, relations: [] };
        const lines = yaml.split('\n');
        let currentSection = null;
        let currentRelation = null;

        for (let line of lines) {
            const trimmed = line.trim();
            if (!trimmed || trimmed.startsWith('#')) continue;

            const isIndent = line.startsWith('  ');

            if (!isIndent) {
                if (trimmed.startsWith('attributes:')) {
                    currentSection = 'attributes';
                } else if (trimmed.startsWith('relations:')) {
                    currentSection = 'relations';
                } else {
                    const parts = trimmed.split(':');
                    if (parts.length >= 2) {
                        const key = parts[0].trim();
                        let val = parts.slice(1).join(':').trim();
                        if (val.startsWith("'") || val.startsWith('"')) val = val.substring(1, val.length - 1);
                        if (key === 'login_enabled') val = val === 'true';
                        config[key] = val;
                    }
                    currentSection = null;
                }
            } else {
                if (currentSection === 'attributes') {
                    const parts = trimmed.split(':');
                    if (parts.length >= 2) {
                        const key = parts[0].trim();
                        let val = parts.slice(1).join(':').trim();
                        if (val.startsWith("'") || val.startsWith('"')) val = val.substring(1, val.length - 1);
                        config.attributes[key] = val;
                    }
                } else if (currentSection === 'relations') {
                    if (trimmed.startsWith('-')) {
                        currentRelation = {};
                        config.relations.push(currentRelation);
                        const parts = trimmed.substring(1).split(':');
                        if (parts.length >= 2) {
                            let val = parts.slice(1).join(':').trim();
                            if (val.startsWith("'") || val.startsWith('"')) val = val.substring(1, val.length - 1);
                            currentRelation[parts[0].trim()] = val;
                        }
                    } else if (currentRelation) {
                        const parts = trimmed.split(':');
                        if (parts.length >= 2) {
                            let val = parts.slice(1).join(':').trim();
                            if (val.startsWith("'") || val.startsWith('"')) val = val.substring(1, val.length - 1);
                            currentRelation[parts[0].trim()] = val;
                        }
                    }
                }
            }
        }
        this.state.currentEntityConfig = this._normalizeConfig(config);
        this.state.currentEntityYaml = yaml;
    }

    syncPhpToBuilderSync(phpSource) {
        const config = this.state.currentEntityConfig || { table: '', attributes: {}, relations: [] };

        const extendsMatch = phpSource.match(/class\s+\w+\s+extends\s+([\w\\]+)/i);
        if (extendsMatch) {
            let cls = extendsMatch[1].trim();
            if (cls !== 'SPPEntity' && cls !== '\\SPPMod\\SPPDB\\SPPEntity') {
                config.extends = cls.startsWith('\\') ? cls : '\\\\' + cls;
            } else {
                delete config.extends;
            }
        }

        const tableMatch = phpSource.match(/public\s+function\s+getTable\s*\(\)\s*\{\s*return\s*['"]([^'"]+)['"]/i);
        if (tableMatch) {
            config.table = tableMatch[1];
        }

        const attrMatch = phpSource.match(/public\s+function\s+define_attributes\s*\(\)\s*\{[\s\S]*?return\s*\[([\s\S]*?)\];\s*\}/i);
        if (attrMatch) {
            const attrBody = attrMatch[1];
            const attributes = {};
            const regex = /['"]([^'"]+)['"]\s*=>\s*['"]([^'"]+)['"]/g;
            let match;
            while ((match = regex.exec(attrBody)) !== null) {
                attributes[match[1]] = match[2];
            }
            config.attributes = attributes;
        }

        this.state.currentEntityConfig = this._normalizeConfig(config);
        this.state.currentEntityPhp = phpSource;
    }

    syncBuilderToPhpSync() {
        let php = this.state.currentEntityPhp;
        if (!php) return;

        const config = this.state.currentEntityConfig || {};

        if (config.extends) {
            php = php.replace(/class\s+(\w+)\s+extends\s+[\w\\]+/i, `class $1 extends ${config.extends}`);
        } else {
            php = php.replace(/class\s+(\w+)\s+extends\s+[\w\\]+/i, `class $1 extends \\SPPMod\\SPPDB\\SPPEntity`);
        }

        if (config.table) {
            const tableMethod = `public function getTable() { return '${config.table}'; }`;
            if (php.includes('function getTable()')) {
                php = php.replace(/public\s+function\s+getTable\s*\(\)\s*\{[\s\S]*?return\s*['"][^'"]+['"];\s*\}/i, tableMethod);
            } else {
                php = php.replace(/class\s+\w+\s+extends\s+[\w\\]+\s*\{/i, `\$&\n    ${tableMethod}\n`);
            }
        }

        const attrs = config.attributes || {};
        if (Object.keys(attrs).length > 0) {
            const attrStr = Object.entries(attrs).map(([k, v]) => `            '${k}' => '${v}'`).join(",\n");
            const newAttrMethod = `public function define_attributes()\n    {\n        return [\n${attrStr}\n        ];\n    }`;

            if (php.includes('define_attributes')) {
                php = php.replace(/public\s+function\s+define_attributes\s*\(\)\s*\{[\s\S]*?return\s*\[[\s\S]*?\];\s*\}/i, newAttrMethod);
            } else {
                php = php.replace(/(\n\s*\}\s*)$/, `\n    ${newAttrMethod}\n$1`);
            }
        }

        this.state.currentEntityPhp = php;
    }

    async save() {
        const name = this.state.currentEntityName.trim();
        if (!name) return this.notify('Entity name is required.', 'error');

        // Capture YAML if active
        if (this.state.activeFormTab === 'yaml') {
            const yaml = document.getElementById('editor-yaml-content')?.value || '';
            await this.syncSourceToBuilder(yaml);
        }

        // Capture PHP if active
        let phpSource = this.state.currentEntityPhp;
        if (this.state.activeFormTab === 'php') {
            phpSource = document.getElementById('editor-php-content')?.value || '';
        }

        const fdConfig = new FormData();
        fdConfig.append('action', 'save_entity_config');
        fdConfig.append('name', name);
        fdConfig.append('config', JSON.stringify(this.state.currentEntityConfig));

        const resConfig = await this.apiPost(fdConfig);
        if (!resConfig.success) {
            return this.handleApiErrors(resConfig);
        }

        if (phpSource) {
            const fdPhp = new FormData();
            fdPhp.append('action', 'save_entity_source');
            fdPhp.append('name', name);
            fdPhp.append('source', phpSource);
            const resPhp = await this.apiPost(fdPhp);
            if (!resPhp.success) {
                return this.handleApiErrors(resPhp);
            }
        }

        this.notify('Entity saved successfully.', 'success');
        this.closeModal();
        this.fetchData(); // Refresh list
    }

    // =========================================================================
    //  INLINED: Schema Builder (formerly schemabuilder.js)
    // =========================================================================

    sbAddColumn() {
        this.setState(s => ({
            sbColumns: [...s.sbColumns, { name: 'new_column', type: 'varchar(255)' }]
        }));
    }

    sbUpdateColumn(index, field, value) {
        const cols = [...this.state.sbColumns];
        cols[index][field] = value;
        this.setState({ sbColumns: cols });
    }

    sbRemoveColumn(index) {
        const cols = [...this.state.sbColumns];
        cols.splice(index, 1);
        this.setState({ sbColumns: cols });
    }

    sbAddRelation() {
        this.setState(s => ({
            sbRelations: [...s.sbRelations, { child_entity: '', relation_type: 'OneToMany', child_entity_field: '', pivot_table: '' }]
        }));
    }

    sbUpdateRelation(index, field, value) {
        const rels = [...this.state.sbRelations];
        rels[index][field] = value;
        this.setState({ sbRelations: rels });
    }

    sbRemoveRelation(index) {
        const rels = [...this.state.sbRelations];
        rels.splice(index, 1);
        this.setState({ sbRelations: rels });
    }

    async sbSaveEntity() {
        if (!this.state.sbEntityName) {
            this.notify('Entity Name is required', 'error');
            return;
        }
        this.setState({ sbSaving: true });

        const config = {
            table: this.state.sbTableName || (this.state.sbEntityName.toLowerCase() + 's'),
            extends: this.state.sbExtendsClass || '',
            login_enabled: this.state.sbLoginEnabled,
            attributes: {},
            relations: this.state.sbRelations.filter(r => r.child_entity)
        };

        for (const col of this.state.sbColumns) {
            if (col.name) config.attributes[col.name] = col.type;
        }

        const fd = new FormData();
        fd.append('action', 'save_entity_config');
        fd.append('name', this.state.sbEntityName);
        fd.append('config', JSON.stringify(config));

        try {
            const res = await this.apiPost(fd);
            if (res.success) {
                this.notify('Entity YAML generated successfully!', 'success');
                this.fetchData();
                this.setState({ activeMainTab: 'list' });
            } else {
                this.notify('Failed to save entity: ' + res.message, 'error');
            }
        } catch (e) {
            this.notify('Failed to save entity: ' + e.message, 'error');
        } finally {
            this.setState({ sbSaving: false });
        }
    }

    renderSchemaBuilder() {
        const { sbEntityName, sbTableName, sbExtendsClass, sbExtendsSelection, sbLoginEnabled, sbColumns, sbRelations, sbSaving, sbSavePath, availableClasses } = this.state;

        return html`
            <div class="spp-card" style="padding: 2rem;">
                <h3 style="color: #f43f5e; margin-bottom: 1rem;">🖌️ Visual Schema Builder</h3>
                <p style="color: var(--text-dim); margin-bottom: 0.5rem;">
                    Design your Entity structure visually. The framework will automatically write the underlying YAML configurations.
                </p>
                ${sbSavePath ? html`
                    <p style="color: var(--primary); font-size: 0.9rem; margin-bottom: 2rem;">
                        📁 Saving to: <code>${sbSavePath}</code>
                    </p>
                ` : ''}

                <datalist id="available-classes-list">
                    ${availableClasses.map(c => html`<option value="${c}"></option>`)}
                </datalist>

                <div style="margin-bottom: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #38bdf8; font-weight: bold;">Entity Name</label>
                        <input type="text" class="spp-element" value="${sbEntityName}" @input="${e => this.setState({ sbEntityName: e.target.value })}" style="font-size: 1.2rem; padding: 0.5rem; width: 100%;" />
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #38bdf8; font-weight: bold;">Table Name (Optional)</label>
                        <input type="text" class="spp-element" value="${sbTableName}" @input="${e => this.setState({ sbTableName: e.target.value })}" placeholder="Defaults to plural entity name" style="font-size: 1.2rem; padding: 0.5rem; width: 100%;" />
                    </div>
                </div>

                <div style="margin-bottom: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: #38bdf8; font-weight: bold;">Extends Class (Optional)</label>
                        <select class="spp-element" style="padding: 0.5rem; width: 100%;" @change="${e => {
                const val = e.target.value;
                this.setState({ sbExtendsSelection: val });
                if (val !== '__other__') this.setState({ sbExtendsClass: val });
            }}">
                            <option value="">(None)</option>
                            ${availableClasses.map(c => html`<option value="${c}" ?selected="${sbExtendsSelection === c || (!sbExtendsSelection && sbExtendsClass === c)}">${c}</option>`)}
                            <option value="__other__" ?selected="${sbExtendsSelection === '__other__'}">Other...</option>
                        </select>
                        ${sbExtendsSelection === '__other__' ? html`
                            <input type="text" class="spp-element" value="${sbExtendsClass === '__other__' ? '' : sbExtendsClass}" @input="${e => this.setState({ sbExtendsClass: e.target.value })}" placeholder="e.g. \\MyApp\\BaseEntity" style="padding: 0.5rem; width: 100%; margin-top: 0.5rem;" />
                        ` : ''}
                    </div>
                    <div style="display: flex; align-items: center; padding-top: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-normal);">
                            <input type="checkbox" ?checked="${sbLoginEnabled}" @change="${e => this.setState({ sbLoginEnabled: e.target.checked })}" style="width: 1.2rem; height: 1.2rem;" />
                            <strong>Enable Login Support</strong>
                        </label>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <strong style="color: var(--text-dim);">Columns</strong>
                </div>

                <div class="schema-columns" style="display: flex; flex-direction: column; gap: 1rem;">
                    ${sbColumns.map((col, i) => html`
                        <div style="display: flex; gap: 1rem; align-items: center; background: var(--surface-2); padding: 1rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                            <input type="text" class="spp-element" value="${col.name}" @input="${e => this.sbUpdateColumn(i, 'name', e.target.value)}" placeholder="Column name" style="flex: 1;" />
                            <select class="spp-element" @change="${e => this.sbUpdateColumn(i, 'type', e.target.value)}" style="flex: 1;">
                                <option value="varchar(255)" ?selected="${col.type === 'varchar(255)'}">String (varchar)</option>
                                <option value="text" ?selected="${col.type === 'text'}">Long Text</option>
                                <option value="int" ?selected="${col.type === 'int'}">Integer</option>
                                <option value="decimal(10,2)" ?selected="${col.type === 'decimal(10,2)'}">Decimal</option>
                                <option value="timestamp" ?selected="${col.type === 'timestamp'}">Timestamp</option>
                                <option value="boolean" ?selected="${col.type === 'boolean'}">Boolean</option>
                            </select>
                            <button class="btn ghost-btn btn-sm" @click="${() => this.sbRemoveColumn(i)}" style="color: #f43f5e;">❌</button>
                        </div>
                    `)}
                </div>
                
                <div style="margin-top: 1rem; margin-bottom: 2rem;">
                    <button class="btn secondary-btn btn-sm" @click="${() => this.sbAddColumn()}">+ Add Column</button>
                </div>

                <div style="margin-bottom: 1rem;">
                    <strong style="color: var(--text-dim);">Relationships</strong>
                </div>

                <div class="schema-relations" style="display: flex; flex-direction: column; gap: 1rem;">
                    ${sbRelations.map((rel, i) => html`
                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; background: var(--surface-2); padding: 1rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                            <input type="text" list="available-classes-list" class="spp-element" value="${rel.child_entity}" @input="${e => this.sbUpdateRelation(i, 'child_entity', e.target.value)}" placeholder="Target Entity" style="flex: 2; min-width: 200px;" />
                            <select class="spp-element" @change="${e => this.sbUpdateRelation(i, 'relation_type', e.target.value)}" style="flex: 1; min-width: 120px;">
                                <option value="OneToMany" ?selected="${rel.relation_type === 'OneToMany'}">One To Many</option>
                                <option value="ManyToMany" ?selected="${rel.relation_type === 'ManyToMany'}">Many To Many</option>
                                <option value="ManyToOne" ?selected="${rel.relation_type === 'ManyToOne'}">Many To One</option>
                                <option value="OneToOne" ?selected="${rel.relation_type === 'OneToOne'}">One To One</option>
                            </select>
                            <input type="text" class="spp-element" value="${rel.child_entity_field}" @input="${e => this.sbUpdateRelation(i, 'child_entity_field', e.target.value)}" placeholder="Foreign Key (Optional)" style="flex: 1; min-width: 150px;" />
                            ${rel.relation_type === 'ManyToMany' ? html`
                                <input type="text" class="spp-element" value="${rel.pivot_table}" @input="${e => this.sbUpdateRelation(i, 'pivot_table', e.target.value)}" placeholder="Pivot Table (Optional)" style="flex: 1; min-width: 150px;" />
                            ` : ''}
                            <button class="btn ghost-btn btn-sm" @click="${() => this.sbRemoveRelation(i)}" style="color: #f43f5e;">❌</button>
                        </div>
                    `)}
                </div>
                
                <div style="margin-top: 1rem; margin-bottom: 2rem;">
                    <button class="btn secondary-btn btn-sm" @click="${() => this.sbAddRelation()}">+ Add Relationship</button>
                </div>

                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--glass-border); display: flex; gap: 1rem;">
                    <button class="btn primary-btn shine-effect" @click="${() => this.sbSaveEntity()}" ?disabled="${sbSaving}">
                        ${sbSaving ? 'Generating...' : 'Generate Entity'}
                    </button>
                </div>
            </div>
        `;
    }

    // =========================================================================
    //  INLINED: Magic DB Viewer (formerly magicdb.js)
    // =========================================================================

    async seedMockData(entityName) {
        const count = prompt('How many mock records to generate and insert?', '50');
        if (!count) return;

        try {
            const fd = new FormData();
            fd.append('action', 'seed_entity');
            fd.append('entityName', entityName);
            fd.append('count', parseInt(count));
            const res = await this.apiPost(fd);
            if (res && res.success) {
                this.notify(`Seeded ${res.data.inserted} records for ${entityName}`, 'success');
            } else {
                this.notify(res?.message || 'Error seeding data', 'error');
            }
        } catch (err) {
            this.notify('Seed error: ' + err.message, 'error');
        }
    }

    renderMagicDB() {
        const { entities } = this.state;

        return html`
            <div class="spp-card" style="padding: 2rem;">
                <h3 style="color: #f43f5e; margin-bottom: 1rem;">✨ Magic DB Viewer</h3>
                <p style="color: var(--text-dim); margin-bottom: 2rem;">
                    In Developer Heaven mode, you don't need to run migrations. Just save entities and the DB alters itself. 
                    Below are the currently autogenerated tables.
                </p>

                <div class="table-list">
                    ${entities.map(ent => html`
                        <div style="background: var(--surface-2); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="color: #38bdf8;">${ent.table || ent.name}</strong>
                                <div style="font-size: 0.8rem; color: var(--text-dim); margin-top: 0.5rem;">Entity: ${ent.name}</div>
                            </div>
                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.seedMockData(ent.name)}>Seed Mock Data</button>
                        </div>
                    `)}
                </div>
            </div>
        `;
    }
}
