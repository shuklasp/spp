/**
 * FormsView Component
 */

/**
 * FormsView Component
 * 
 * Manages form manifests and features a drag-and-drop Visual Form Builder.
 */
export default class FormsView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            forms: [],
            activeFormTab: 'builder',
            currentFormName: '',
            currentFormType: 'yml',
            currentFormSource: '',
            currentFormConfig: { form: { name: '', type: 'single' }, fields: [] }
        };
        await this.fetchData();
    }

    async fetchData() {
        try {
            const res = await this.api('list_forms');
            if (res.success) {
                this.setState({
                    forms: res.data.forms || [],
                    loading: false
                });
                this.existingFormNames = (res.data.forms || []).map(f => f.name);
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    render() {
        const { loading, forms, error } = this.state;

        if (loading) return html`<div class="loading-state">Syncing form manifests...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        // Update Header
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            const defaultSource = 'form:\n  name: my_form\n  service: save_data\n\nfields:\n  - name: title\n    type: input\n    label: Title';
            const headerHtml = html`
                <button type="button" class="btn primary-btn btn-sm" @click=${() => this.openEditor('', 'yml', defaultSource)}>+ New Form</button>
            `;
            headerActions.innerHTML = headerHtml.toString();
            
            // Ensure buttons in the header also trigger events for this component
            headerActions.querySelectorAll('[data-spp-evt]').forEach(el => {
                const id = el.getAttribute('data-spp-evt');
                if (window.__spp_handlers && window.__spp_handlers[id]) {
                    this._handlers.set(id, window.__spp_handlers[id]);
                }
            });
            
            if (!headerActions._hasSppListener) {
                ['click', 'change', 'input'].forEach(type => {
                    headerActions.addEventListener(type, (e) => this._onEvent(e));
                });
                headerActions._hasSppListener = true;
            }
        }

        if (forms.length === 0) {
            return html`
                <div class="empty-state" style="padding: 4rem 2rem; max-width: 600px; margin: 0 auto;">
                    <div class="empty-icon" style="font-size: 4rem; margin-bottom: 1rem;">📝</div>
                    <h3 style="font-size: 1.8rem; margin-bottom: 0.5rem; color: var(--primary);">Zero to Hero: Forms</h3>
                    <p style="color: var(--text-dim); margin-bottom: 1.5rem; line-height: 1.6;">
                        <strong>What are Forms?</strong><br>
                        Forms are the UI interfaces for your Entities. By defining a Form manifest, SPP automatically generates accessible, responsive, and secure HTML forms. You don't need to write repetitive HTML or validation logic ever again!
                    </p>
                    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px dashed var(--glass-border);">
                        <code style="color: #38bdf8;">form: my_form<br>fields:<br>&nbsp;&nbsp;- name: email<br>&nbsp;&nbsp;&nbsp;&nbsp;type: email</code>
                    </div>
                    <button type="button" class="btn primary-btn shine-effect" style="font-size: 1.1rem; padding: 12px 24px;" @click=${() => this.openEditor('', 'yml', '')}>+ Create Your First Form</button>
                </div>
            `;
        }

        return html`
            <div class="card-grid">
                ${forms.map((form, i) => {
                    const lineCount = (String(form.content || '').match(/\n/g) || []).length + 1;
                    return html`
                        <div class="item-card" style="animation-delay: ${i * 0.05}s">
                            <div class="card-header">
                                <div>
                                    <h3>${form.name}</h3>
                                    <div class="card-meta">${lineCount} lines · ${form.modified || 'Just now'}</div>
                                </div>
                                <span class="type-badge ${form.type.toLowerCase()}">${form.type}</span>
                            </div>
                            <div class="card-footer">
                                <small>${form.size ? Math.round(form.size / 1024 * 100) / 100 + ' KB' : ''}</small>
                                <div class="card-actions">
                                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.openEditor(form.name, form.type, form.content)}>Edit</button>
                                    <button type="button" class="btn danger-btn btn-sm" @click=${() => this.confirmDelete('form', form.name)}>Delete</button>
                                </div>
                            </div>
                        </div>
                    `;
                })}
            </div>
        `;
    }

    // =============================================
    // FORM BUILDER LOGIC
    // =============================================

    async openEditor(name, type, content) {
        this.state.activeFormTab = 'builder';
        this.state.currentFormName = name || '';
        this.state.currentFormType = type || 'yml';
        this.state.currentFormSource = content || '';
        
        const isNew = !name;
        const defaultName = isNew ? this._getNextAvailableName('my_form', this.existingFormNames || []) : name;
        let config = { form: { name: defaultName, type: 'single' }, fields: [], isNew: isNew };

        if (content) {
            const fd = new FormData();
            fd.append('action', 'parse_form_yaml');
            fd.append('yaml', content);
            const res = await this.apiPost(fd);
            if (res.success) {
                config = Object.assign(config, res.data.config);
            }
        }

        this.state.currentFormConfig = this._normalizeConfig(config);
        this.openModal(name ? `Form: ${name}.${type.toLowerCase()}` : 'Create New Form', this.getModalHtml(), [
            { label: 'Save Form', type: 'primary', fn: (m) => this.save() }
        ]);
    }

    getModalHtml() {
        const { activeFormTab } = this.state;
        return html`
            <div class="tab-bar">
                <button type="button" class="tab-btn ${activeFormTab === 'builder' ? 'active' : ''}" @click=${() => this.switchTab('builder')}>Visual Builder</button>
                <button type="button" class="tab-btn ${activeFormTab === 'source' ? 'active' : ''}" @click=${() => this.switchTab('source')}>Source (YAML)</button>
                <button type="button" class="tab-btn ${activeFormTab === 'preview' ? 'active' : ''}" @click=${() => this.switchTab('preview')}>Live Preview</button>
            </div>
            <div id="form-editor-content" class="tab-content active">
                ${this.getTabContent(activeFormTab)}
            </div>
        `;
    }

    getTabContent(tab) {
        if (tab === 'builder') return this.getBuilderHtml();
        if (tab === 'source') return this.getSourceHtml();
        if (tab === 'preview') return html`<div class="preview-loading"><div class="loader"></div><p>Rendering framework preview...</p></div>`;
        return '';
    }

    async switchTab(tab) {
        const prevTab = this.state.activeFormTab;
        this.state.activeFormTab = tab;

        if (prevTab === 'source') {
            const source = document.getElementById('editor-content')?.value;
            if (source && source !== this.state.currentFormSource) {
                if (tab === 'builder') {
                    await this.syncSourceToBuilder(source);
                } else {
                    this.state.currentFormSource = source;
                }
            }
        } else if (prevTab === 'builder' && (tab === 'source' || tab === 'preview')) {
            this.state.currentFormSource = await this.generateYaml();
        }

        this.refreshModal();

        if (tab === 'builder') this.attachBuilderEvents();
        if (tab === 'preview') this.renderPreview();
    }

    refreshModal() {
        const title = this.state.currentFormName ? `Form: ${this.state.currentFormName}.${this.state.currentFormType.toLowerCase()}` : 'Create New Form';
        this.updateModal(title, this.getModalHtml(), [
            { label: 'Save Form', type: 'primary', fn: (m) => this.save() }
        ]);
        this.attachBuilderEvents();
    }

    getBuilderHtml() {
        const c = this.state.currentFormConfig;
        const isWizard = c.form.type === 'wizard';
        
        return html`
            <div class="builder-layout">
                <div class="builder-sidebar glass-panel">
                    <h4>Form Metadata</h4>
                    <div class="input-group">
                        <label title="The unique system identifier for this form (e.g. user_registration). Cannot contain spaces.">Name ℹ️</label>
                        <input type="text" @change=${(e) => { c.form.name = e.target.value; }} value="${c.form.name}">
                    </div>
                    <div class="input-group">
                        <label title="Determines whether this form renders on a single page or as a multi-step wizard.">Type ℹ️</label>
                        <select @change=${(e) => this.toggleFormType(e.target.value)}>
                            <option value="single" ?selected="${!isWizard}">Single Step</option>
                            <option value="wizard" ?selected="${isWizard}">Multi-step Wizard</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label title="The backend API service endpoint this form submits data to.">Service (API) ℹ️</label>
                        <input type="text" @change=${(e) => { c.form.service = e.target.value; }} value="${c.form.service || ''}" placeholder="e.g. save_user">
                    </div>
                    
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid var(--glass-border);">
                    
                    <h4>Intelligence & Resilience</h4>
                    <div class="input-group-row" style="display: flex; flex-direction: column; gap: 8px;">
                        <label title="If enabled, form submissions will be queued locally if the user loses internet connection and automatically sync when reconnected." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; text-transform: none; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.form.offline}" @change=${(e) => { c.form.offline = e.target.checked; }}>
                            Offline Sync Support ℹ️
                        </label>
                        <label title="Tracks user engagement metrics such as time-to-completion, drop-off fields, and interaction heatmaps for this form." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; text-transform: none; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.form.telemetry}" @change=${(e) => { c.form.telemetry = e.target.checked; }}>
                            Engagement Telemetry ℹ️
                        </label>
                        <label title="Automatically saves the user's progress locally as they type, preventing data loss on accidental reload or navigation." style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; text-transform: none; cursor: pointer;">
                            <input type="checkbox" ?checked="${!!c.form.autosave}" @change=${(e) => { c.form.autosave = e.target.checked; }}>
                            Local Auto-save ℹ️
                        </label>
                    </div>
                </div>
                <div class="builder-main">
                    ${isWizard ? this.getWizardStepListHtml() : this.getFieldListHtml(c.fields || [])}
                </div>
            </div>`;
    }

    getFieldListHtml(fields, stepIdx = null) {
        return html`
            <div class="builder-section-header">
                <h4>Fields</h4>
                <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.addField(stepIdx)}>+ Add Field</button>
            </div>
            <div class="field-list">
                ${fields.map((f, i) => html`
                    <div class="field-item draggable" draggable="true" 
                        data-index="${i}" data-step="${stepIdx !== null ? stepIdx : ''}"
                        @dragstart=${(e) => this.onDragStart(e)}
                        @dragover=${(e) => this.onDragOver(e)}
                        @dragleave=${(e) => this.onDragLeave(e)}
                        @drop=${(e) => this.onDrop(e)}
                        @dragend=${(e) => this.onDragEnd(e)}>
                        <div class="field-drag-handle">⋮</div>
                        <div class="field-info">
                            <strong>${f.name || 'unnamed'}</strong>
                            <span class="badge">${f.type || 'text'}</span>
                            ${f.voice ? html`<span class="badge tiny info" title="Voice-to-Text enabled">🎙️</span>` : ''}
                            ${f.telemetry ? html`<span class="badge tiny warning" title="Telemetry tracking active">📊</span>` : ''}
                            ${f.computed ? html`<span class="badge tiny success" title="Computed field">🧮</span>` : ''}
                            <div class="field-label-preview">${f.label || ''}</div>
                        </div>
                        <div class="field-actions">
                            <button type="button" class="btn btn-icon" @click=${() => this.editField(i, stepIdx)}>⚙️</button>
                            <button type="button" class="btn btn-icon danger" @click=${() => this.removeField(i, stepIdx)}>✕</button>
                        </div>
                    </div>
                `)}
            </div>`;
    }

    getWizardStepListHtml() {
        const steps = this.state.currentFormConfig.steps || [];
        return html`
            <div class="builder-section-header">
                <h4>Wizard Steps</h4>
                <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.addStep()}>+ Add Step</button>
            </div>
            <div class="steps-container">
                ${steps.map((s, idx) => html`
                    <div class="step-panel glass-panel">
                        <div class="step-header">
                            <h5>Step ${idx + 1}: ${s.title || 'Untitled'}</h5>
                            <div class="step-actions">
                                <button type="button" class="btn btn-icon" @click=${() => this.editStep(idx)}>⚙️</button>
                                <button type="button" class="btn btn-icon danger" @click=${() => this.removeStep(idx)}>✕</button>
                            </div>
                        </div>
                        <div class="step-field-list">
                            ${this.getFieldListHtml(s.fields || [], idx)}
                        </div>
                    </div>
                `)}
            </div>`;
    }

    getSourceHtml() {
        return html`
            <div class="input-group">
                <textarea id="editor-content" spellcheck="false" style="min-height: 400px; font-family: monospace;">${this.state.currentFormSource}</textarea>
            </div>`;
    }

    // =============================================
    // INTERACTORS
    // =============================================

    toggleFormType(type) {
        const c = this.state.currentFormConfig;
        c.form.type = type;
        if (type === 'wizard' && !c.steps) {
            c.steps = [{ title: 'Step 1', fields: c.fields || [] }];
            delete c.fields;
        } else if (type === 'single' && c.steps) {
            c.fields = c.steps[0].fields || [];
            delete c.steps;
        }
        this.refreshModal();
        this.attachBuilderEvents();
    }

    addStep() {
        const c = this.state.currentFormConfig;
        if (!c.steps) c.steps = [];
        c.steps.push({ title: 'New Step', fields: [] });
        this.refreshModal();
        this.attachBuilderEvents();
    }

    async editStep(idx) {
        const step = this.state.currentFormConfig.steps[idx];
        const res = await this.api('get_form_html&type=step_editor');
        if (res.success) {
            this.openSubEditor('Edit Step Properties', res.data.html, step, (newData) => {
                Object.assign(this.state.currentFormConfig.steps[idx], newData);
                this.refreshModal();
                this.attachBuilderEvents();
            });
        }
    }

    removeStep(idx) {
        this.state.currentFormConfig.steps.splice(idx, 1);
        this.refreshModal();
        this.attachBuilderEvents();
    }

    addField(stepIdx) {
        const fields = stepIdx !== null ? this.state.currentFormConfig.steps[stepIdx].fields : this.state.currentFormConfig.fields;
        const name = this._getNextAvailableName('new_field', fields.map(f => f.name));
        fields.push({ name, type: 'text', label: name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' ') });
        this.refreshModal();
        this.attachBuilderEvents();
    }

    async editField(idx, stepIdx) {
        const fields = stepIdx !== null ? this.state.currentFormConfig.steps[stepIdx].fields : this.state.currentFormConfig.fields;
        const field = fields[idx];
        const res = await this.api('get_form_html&type=field_editor');
        if (res.success) {
            this.openSubEditor('Edit Field Properties', res.data.html, field, (newData) => {
                Object.assign(fields[idx], newData);
                this.refreshModal();
                this.attachBuilderEvents();
            });
        }
    }

    removeField(idx, stepIdx) {
        const fields = stepIdx !== null ? this.state.currentFormConfig.steps[stepIdx].fields : this.state.currentFormConfig.fields;
        fields.splice(idx, 1);
        this.refreshModal();
        this.attachBuilderEvents();
    }

    // Drag & Drop
    onDragStart(e) {
        const item = e.target.closest('.field-item');
        item.classList.add('dragging');
        e.dataTransfer.setData('fieldIndex', item.getAttribute('data-index'));
        e.dataTransfer.setData('fromStep', item.getAttribute('data-step'));
    }

    onDragOver(e) {
        e.preventDefault();
        const target = e.target.closest('.field-item') || e.target.closest('.step-panel');
        if (target) target.classList.add('drag-over');
    }

    onDragLeave(e) {
        const target = e.target.closest('.field-item') || e.target.closest('.step-panel');
        if (target) target.classList.remove('drag-over');
    }

    onDragEnd(e) {
        const item = e.target.closest('.field-item');
        if (item) item.classList.remove('dragging');
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
    }

    onDrop(e) {
        e.preventDefault();
        const fromIdx = parseInt(e.dataTransfer.getData('fieldIndex'));
        const fromStepStr = e.dataTransfer.getData('fromStep');
        const fromStep = fromStepStr === '' ? null : parseInt(fromStepStr);

        const targetField = e.target.closest('.field-item');
        const targetStepPanel = e.target.closest('.step-panel');
        
        let toIdx = 0;
        let toStep = null;

        if (targetField) {
            toIdx = parseInt(targetField.getAttribute('data-index'));
            const s = targetField.getAttribute('data-step');
            toStep = s === '' ? null : parseInt(s);
        } else if (targetStepPanel) {
            toStep = parseInt(targetStepPanel.querySelector('.step-field-list').getAttribute('data-step-index'));
            toIdx = 999; // append
        }

        this.moveField(fromIdx, fromStep, toIdx, toStep);
    }

    moveField(fromIdx, fromStep, toIdx, toStep) {
        const c = this.state.currentFormConfig;
        let field;
        
        if (fromStep !== null) {
            field = c.steps[fromStep].fields.splice(fromIdx, 1)[0];
        } else {
            field = c.fields.splice(fromIdx, 1)[0];
        }

        const targetList = toStep !== null ? c.steps[toStep].fields : c.fields;
        if (toIdx > targetList.length) toIdx = targetList.length;
        targetList.splice(toIdx, 0, field);
        
        this.refreshModal();
        this.attachBuilderEvents();
    }

    // Helpers
    async syncSourceToBuilder(source) {
        const fd = new FormData();
        fd.append('action', 'parse_form_yaml');
        fd.append('yaml', source);
        const res = await this.apiPost(fd, {}, { lock: false });
        if (res.success) {
            this.state.currentFormConfig = this._normalizeConfig(res.data.config);
            this.state.currentFormSource = source;
            this.refreshModal();
            this.attachBuilderEvents();
        }
    }

    async generateYaml() {
        const fd = new FormData();
        fd.append('action', 'dump_form_yaml');
        fd.append('config', JSON.stringify(this.state.currentFormConfig));
        const res = await this.apiPost(fd, {}, { lock: false });
        return res.success ? res.data.yaml : '# Dump failed';
    }

    async renderPreview() {
        const container = document.getElementById('form-editor-content');
        const yaml = await this.generateYaml();
        const fd = new FormData();
        fd.append('action', 'get_form_html');
        fd.append('form', yaml);
        const res = await this.apiPost(fd);
        if (res.success) {
            // Load required component assets before rendering HTML
            if (res.data.assets) {
                await this.loadAssets(res.data.assets);
            }
            
            container.innerHTML = `
                <div class="preview-container glass-panel">
                    <div class="preview-header"><span class="preview-badge">Live Preview</span></div>
                    <div class="preview-content">${res.data.html}</div>
                </div>`;
        }
    }

    async save() {
        const { currentFormConfig, activeFormTab } = this.state;
        const fd = new FormData();
        fd.append('action', activeFormTab === 'builder' ? 'save_form_config' : 'save_form');
        fd.append('name', currentFormConfig.form.name);
        
        if (activeFormTab === 'builder') {
            fd.append('config', JSON.stringify(currentFormConfig));
        } else {
            fd.append('content', document.getElementById('editor-content').value);
            fd.append('type', 'yml');
        }

        const res = await this.apiPost(fd);
        if (res.success) {
            this.notify('Form saved successfully.', 'success');
            this.closeModal();
            this.fetchData();
        }
    }

    _normalizeConfig(config) {
        if (!config.form) config.form = { name: 'unnamed', type: 'single' };
        if (!config.fields) config.fields = [];
        return config;
    }

    _getNextAvailableName(base, existing) {
        let name = base;
        let i = 1;
        while (existing.includes(name)) {
            name = base + i;
            i++;
        }
        return name;
    }

    attachBuilderEvents() {
        // Drag events are handled by SPP-UX template bindings now
    }
}
