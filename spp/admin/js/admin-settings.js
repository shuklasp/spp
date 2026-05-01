/**
 * SPP Admin - Module Settings Enhancement
 * 
 * Extends the core SPPAdmin class with schema-based module settings.
 * Uses settings definitions from module.yml to render type-aware forms
 * via server-side ViewFormBuilder and client-side SPPForm reactivity.
 * 
 * Loaded AFTER admin.js - overrides openModuleSettings() only.
 */

(function () {
    // Wait for SPPAdmin to be available
    if (typeof SPPAdmin === 'undefined') {
        console.warn('admin-settings.js: SPPAdmin not found, skipping enhancement.');
        return;
    }

    /**
     * Override openModuleSettings to use server-rendered form generation.
     */
    SPPAdmin.prototype.openModuleSettings = async function (modname, publicName) {
        console.log('[admin-settings.js] openModuleSettings override active. modname=', modname);
        this.openModal(`⚙️ Setup: ${publicName}`, html`<div class="loader">Loading configuration modes...</div>`);

        try {
            // Fetch both KV and Raw data simultaneously
            const [kvRes, rawRes] = await Promise.all([
                this.api('get_module_config', { modname, appname: this.selectedApp }),
                this.api('get_module_config_raw', { modname, appname: this.selectedApp })
            ]);

            if (!kvRes.success) {
                this.updateModal('Setup Failed', html`<div class="alert error">${this.escapeHtml(kvRes.message)}</div>`);
                return;
            }

            const config = kvRes.data.variables || {};
            const raw = rawRes.success ? rawRes.data : { content: '', format: 'yml' };
            const formHtml = kvRes.data.settings_form_html || '';
            const appname = this.selectedApp;

            let contentHtml = `
                <!-- Effective Path Indicator -->
                <div class="effective-path-info" style="margin-bottom: 20px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 6px; border: 1px solid var(--glass-border); display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 1.1rem;">📂</span>
                    <div style="overflow: hidden; flex: 1;">
                        <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; font-weight: 600;">Effective Config Source</div>
                        <div style="font-family: 'JetBrains Mono', 'Cascadia Code', monospace; font-size: 0.8rem; color: var(--accent-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${this.escapeAttr(kvRes.data.source)}">
                            ${this.escapeHtml(this.truncatePath(kvRes.data.source, 90))}
                        </div>
                    </div>
                </div>

                <div class="tabs-toolbar" style="margin-bottom: 20px; border-bottom: 1px solid var(--glass-border); display: flex; gap: 4px;">
                    <button class="tab-btn active" onclick="admin.switchSetupTab('interactive')" id="tab-interactive">🏠 Interactive Editor</button>
                    <button class="tab-btn" onclick="admin.switchSetupTab('yaml')" id="tab-yaml">📄 Advanced YAML</button>
                </div>
                
                <div id="setup-pane-interactive" class="setup-pane active">
                    <div class="settings-form" style="max-height: 450px; overflow-y: auto; padding-right: 10px;">
            `;

            if (formHtml) {
                contentHtml += formHtml;
            } else if (Object.keys(config).length > 0 || Object.keys(kvRes.data.settings_definition || {}).length > 0) {
                const settingsDef = kvRes.data.settings_definition || {};
                const allKeys = new Set([...Object.keys(settingsDef), ...Object.keys(config)]);

                for (const key of allKeys) {
                    const val = config[key] !== undefined ? config[key] : '';
                    const def = settingsDef[key] || {};
                    const label = def.label || key;
                    const help = def.help || '';
                    const type = def.type || 'text';
                    const dependsOn = def.depends_on ? JSON.stringify(def.depends_on) : '';

                    contentHtml += `
                        <div class="input-group" style="margin-bottom: 20px;" ${dependsOn ? `data-depends-on='${this.escapeAttr(dependsOn)}'` : ''}>
                            <label style="display: block; margin-bottom: 5px; font-size: 0.85rem; font-weight: 500; color: var(--text-main);">${this.escapeHtml(label)}</label>
                    `;

                    if (type === 'boolean' || type === 'checkbox' || type === 'toggle' || typeof val === 'boolean') {
                        const isChecked = (val === true || val === 'true' || val === 1 || val === '1' || val === 'on');
                        contentHtml += `
                            <label class="toggle-switch">
                                <input type="checkbox" class="setting-input spp-element" name="${this.escapeAttr(key)}" data-key="${this.escapeAttr(key)}" ${isChecked ? 'checked' : ''}>
                                <span class="toggle-slider"></span>
                            </label>
                        `;
                    } else if (type === 'select' && def.options) {
                        contentHtml += `
                            <select class="setting-input spp-element" name="${this.escapeAttr(key)}" data-key="${this.escapeAttr(key)}" 
                                style="width: 100%; padding: 10px; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 6px; color: var(--text-main); font-size: 0.9rem;">
                        `;
                        for (const [optVal, optLabel] of Object.entries(def.options)) {
                            contentHtml += `<option value="${this.escapeAttr(optVal)}" ${optVal == val ? 'selected' : ''}>${this.escapeHtml(optLabel)}</option>`;
                        }
                        contentHtml += `</select>`;
                    } else {
                        contentHtml += `
                            <input type="${type === 'number' ? 'number' : (type === 'password' ? 'password' : 'text')}" 
                                class="setting-input spp-element" name="${this.escapeAttr(key)}" data-key="${this.escapeAttr(key)}" value="${this.escapeAttr(val)}" 
                                style="width: 100%; padding: 10px; background: var(--input-bg); border: 1px solid var(--glass-border); border-radius: 6px; color: var(--text-main); font-size: 0.9rem;">
                        `;
                    }

                    if (help) {
                        contentHtml += `
                            <div class="setting-help" style="margin-top: 6px; display: flex; gap: 6px; align-items: flex-start;">
                                <span style="font-size: 0.8rem; opacity: 0.6;">💡</span>
                                <small style="font-size: 0.75rem; color: var(--text-dim); line-height: 1.4; opacity: 0.85;">${this.escapeHtml(help)}</small>
                            </div>
                        `;
                    }
                    contentHtml += `</div>`;
                }
            } else {
                contentHtml += `<div class="empty-state"><p>No standard settings discovered for "${modname}". Use the YAML tab for direct overrides.</p></div>`;
            }

            contentHtml += `
                    </div>
                </div>
                
                <div id="setup-pane-yaml" class="setup-pane" style="display: none;">
                    <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 10px;">Direct YAML manipulation for "${modname}" in application context "${appname}".</p>
                    <textarea id="raw-config-editor" style="width: 100%; height: 400px; background: #1e1e1e; color: #d4d4d4; font-family: 'Cascadia Code', Consolas, monospace; padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border); line-height: 1.5; outline: none; resize: vertical;">${this.escapeHtml(raw.content || '')}</textarea>
                    <input type="hidden" id="raw-config-format" value="${this.escapeAttr(raw.format || 'yml')}">
                </div>
            `;

            this.updateModal(`Setup: ${publicName}`, html`${new TrustedHTML(contentHtml)}`, [
                { label: 'Cancel', type: 'secondary', fn: (m) => m.close() },
                { label: 'Save Changes', type: 'primary', fn: (m) => this.saveModuleSettings(modname, appname, m) }
            ]);
            this.activeSetupTab = 'interactive';

            // Initialize SPPForm for reactivity
            if (window.SPPForm) {
                setTimeout(() => {
                    const formContainer = document.querySelector('#setup-pane-interactive');
                    if (formContainer) {
                        this.activeSettingsForm = new SPPForm(this, formContainer);
                        this.activeSettingsForm.onMount();
                    }
                }, 150);
            }

        } catch (err) {
            this.updateModal('Error', err.message);
        }
    };

    /**
     * Legacy helper - kept for compatibility but SPPForm handles this now.
     */
    SPPAdmin.prototype.refreshSettingDependencies = function () {
        if (this.activeSettingsForm) {
            this.activeSettingsForm.refreshDependencies();
        }
    };

    /**
     * Override saveModuleSettings to handle all input types.
     * Enhanced Save Logic with Modal Awareness and robust element resolution
     */
    SPPAdmin.prototype.saveModuleSettings = async function (modname, appname, modal = null) {
        try {
            console.log(`[admin-settings.js] saveModuleSettings tab=${this.activeSetupTab} mod=${modname} app=${appname}`);
            
            let res;
            const action = (this.activeSetupTab === 'yaml') ? 'save_module_config_raw' : 'save_module_config';
            
            if (this.activeSetupTab === 'interactive') {
                const config = {};
                const container = modal?.container || document.querySelector('.modal-box') || document;
                
                // Use FormData for robust serialization
                const formElem = container.querySelector('form');
                if (formElem) {
                    const formData = new FormData(formElem);
                    formData.forEach((value, key) => {
                        if (key.endsWith('[]')) {
                            const cleanKey = key.slice(0, -2);
                            if (!config[cleanKey]) config[cleanKey] = [];
                            config[cleanKey].push(value);
                        } else {
                            config[key] = value;
                        }
                    });
                } else {
                    // Fallback for manual loops
                    const inputs = container.querySelectorAll('.setting-input, .spp-element');
                    inputs.forEach(inp => {
                        if (!['INPUT', 'SELECT', 'TEXTAREA'].includes(inp.tagName)) return;
                        const key = inp.name || inp.getAttribute('data-key');
                        if (!key) return;
                        if (inp.type === 'checkbox') config[key] = inp.checked;
                        else config[key] = inp.value;
                    });
                }
                
                console.log('[admin-settings.js] Interactive Data:', config);
                res = await this.apiPost('save_module_config', { 
                    modname, 
                    appname, 
                    config: JSON.stringify(config) 
                });
            } else {
                // Fallback to global IDs for YAML editor
                const contentEl = document.getElementById('raw-config-editor');
                const formatEl = document.getElementById('raw-config-format');
                
                if (!contentEl) {
                    throw new Error("Unable to locate YAML editor elements in the current modal.");
                }
                
                const content = contentEl.value;
                const format = formatEl ? formatEl.value : 'yml';

                console.log(`[admin-settings.js] Raw Save (format=${format})`);
                res = await this.apiPost('save_module_config_raw', { 
                    modname, 
                    appname, 
                    content,
                    format
                });
            }

            if (res && res.success) {
                this.notify(res.message || 'Configuration saved successfully.', 'success');
                if (modal) modal.close();
                else this.closeModal();
            } else {
                const errorMsg = res ? (res.message || 'Unknown API error') : 'No response from server';
                this.notify('Save Failed: ' + errorMsg, 'error');
                console.error('[admin-settings.js] Save Error Details:', res);
            }
        } catch (err) {
            console.error('[admin-settings.js] saveModuleSettings Exception:', err);
            this.notify('Critical Error: ' + err.message, 'error');
        }
    };

    console.log('SPP Admin Settings Enhancement loaded (v2 - Server Rendered).');
})();
