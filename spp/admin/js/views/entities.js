/**
 * EntitiesView Component
 */

/**
 * EntitiesView Component
 * 
 * Manages framework entities and provides a visual YAML builder.
 */
export default class EntitiesView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            entities: [],
            activeFormTab: 'builder',
            currentEntityName: '',
            currentEntitySource: '',
            currentEntityConfig: { table: '', attributes: {}, relations: [] }
        };
        await this.fetchData();
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
        const { loading, entities, error } = this.state;

        if (loading) return html`<div class="loading-state">Cataloging application entities...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            const defaultSource = 'table: my_table\nid_field: id\nattributes:\n  name:\n    type: varchar\n    length: 255';
            const headerHtml = html`
                <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openEditor('', defaultSource)}>+ New Entity</button>
            `;
            headerActions.innerHTML = headerHtml.toString();
        }

        if (entities.length === 0) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">🏗️</div>
                    <h3>No Entities Defined</h3>
                    <p>Applications in SPP use YAML-defined entities for decoupled data management.</p>
                    <button type="button" class="btn primary-btn" @click=${() => this.openEditor('', 'table: my_table\nid_field: id\nattributes:\n  name:\n    type: varchar\n    length: 255')}>+ Create Entity</button>
                </div>
            `;
        }

        return html`
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
                                    <div class="card-meta">${metaInfo || 'Entity Definition'}</div>
                                </div>
                                <span class="type-badge yml">YML</span>
                            </div>
                            <div class="card-footer">
                                <small>${ent.size ? Math.round(ent.size / 1024 * 100) / 100 + ' KB' : ''}</small>
                                <div class="card-actions">
                                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openEditor(ent.name, ent.content)}>Edit</button>
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

    async openEditor(name, content) {
        this.state.activeFormTab = 'builder';
        this.state.currentEntityName = name || '';
        this.state.currentEntitySource = content || '';
        this.state.currentEntityConfig = { table: '', attributes: {}, relations: [] };

        if (content) {
            const fd = new FormData();
            fd.append('action', 'parse_entity_yaml');
            fd.append('yaml', content);
            const res = await this.apiPost(fd);
            if (res.success) {
                this.state.currentEntityConfig = this._normalizeConfig(res.data.config);
            }
        }

        this.openModal(name ? `Entity: ${name}.yml` : 'Create New Entity', this.getModalHtml(), [
            { label: name ? 'Save Changes' : 'Create Entity', type: 'primary', fn: () => this.save() }
        ]);
    }

    getModalHtml() {
        const { activeFormTab } = this.state;
        return html`
            <div class="tab-bar">
                <button type="button" class="tab-btn ${activeFormTab === 'builder' ? 'active' : ''}" 
                    @click=${() => this.switchTab('builder')}>Visual Builder</button>
                <button type="button" class="tab-btn ${activeFormTab === 'source' ? 'active' : ''}" 
                    @click=${() => this.switchTab('source')}>Source (YAML)</button>
            </div>
            <div id="entity-editor-content" class="tab-content active" style="margin-top: 1.5rem;">
                ${activeFormTab === 'builder' ? this.getBuilderHtml() : this.getSourceHtml()}
            </div>
        `;
    }

    async switchTab(tab) {
        const prevTab = this.state.activeFormTab;
        if (tab === prevTab) return;
        
        this.state.activeFormTab = tab;

        if (tab === 'builder' && prevTab === 'source') {
            const source = document.getElementById('editor-content')?.value;
            if (source && source !== this.state.currentEntitySource) {
                await this.syncSourceToBuilder(source);
            }
        } else if (tab === 'source' && prevTab === 'builder') {
            this.state.currentEntitySource = await this.generateYaml();
        }

        this.refreshModal();
    }

    getBuilderHtml() {
        const config = this.state.currentEntityConfig;
        const attrs = config.attributes || {};
        const relations = config.relations || [];

        return html`
            <div class="builder-layout">
                <div class="builder-sidebar glass-panel">
                    <h4>Entity Settings</h4>
                    <div class="input-group">
                        <label>Class Name</label>
                        <input type="text" value="${this.state.currentEntityName}" 
                            @change=${(e) => { this.state.currentEntityName = e.target.value; }} 
                            placeholder="e.g. Staff" ${this.state.currentEntityName ? 'disabled' : ''}>
                    </div>
                    <div class="input-group">
                        <label>Database Table</label>
                        <input type="text" value="${config.table || ''}" 
                            @change=${(e) => { config.table = e.target.value; }} placeholder="e.g. staffs">
                    </div>
                    <div class="input-group">
                        <label>Extends (Parent)</label>
                        <input type="text" value="${config.extends || ''}" 
                            @change=${(e) => { config.extends = e.target.value; }} placeholder="e.g. \\SPPMod\\SPPAuth\\SPPUser">
                    </div>
                    <div class="input-group checkbox-group">
                        <label><input type="checkbox" ?checked="${config.login_enabled}" 
                            @change=${(e) => { config.login_enabled = e.target.checked; }}> Login Enabled</label>
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

    getSourceHtml() {
        return html`
            <div class="input-group" style="margin-top: 0;">
                <label>YAML Definition</label>
                <textarea id="editor-content" spellcheck="false" style="min-height:400px; font-family:monospace;">${this.state.currentEntitySource}</textarea>
            </div>`;
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
                    <input type="text" value="${rel.child_entity || ''}" 
                        @change=${(e) => { this.state.currentEntityConfig.relations[idx].child_entity = e.target.value; }} placeholder="e.g. \\App\\Entities\\Course">
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
        const body = document.querySelector('.modal-body');
        if (body) {
            body.innerHTML = this.getModalHtml().toString();
            
            // Re-claim handlers for the new DOM elements
            body.querySelectorAll('[data-spp-evt]').forEach(el => {
                const id = el.getAttribute('data-spp-evt');
                if (window.__spp_handlers && window.__spp_handlers[id]) {
                    this._handlers.set(id, window.__spp_handlers[id]);
                }
            });
        }
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

    async generateYaml() {
        const fd = new FormData();
        fd.append('action', 'dump_entity_yaml');
        fd.append('config', JSON.stringify(this.state.currentEntityConfig));
        const res = await this.apiPost(fd);
        return res.success ? res.data.yaml : '# Dump failed';
    }

    async syncSourceToBuilder(source) {
        const fd = new FormData();
        fd.append('action', 'parse_entity_yaml');
        fd.append('yaml', source);
        const res = await this.apiPost(fd);
        if (res.success) {
            this.state.currentEntityConfig = this._normalizeConfig(res.data.config);
            this.state.currentEntitySource = source;
        }
    }

    async save() {
        const name = this.state.currentEntityName.trim();
        if (!name) return this.notify('Entity name is required.', 'error');

        if (this.state.activeFormTab === 'source') {
            const source = document.getElementById('editor-content').value;
            await this.syncSourceToBuilder(source);
        }

        const fd = new FormData();
        fd.append('action', 'save_entity_config');
        fd.append('name', name);
        fd.append('config', JSON.stringify(this.state.currentEntityConfig));
        
        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify('Entity saved successfully.', 'success');
            this.closeModal();
            this.fetchData(); // Refresh list
        } else {
            this.handleApiErrors(res);
        }
    }
}
