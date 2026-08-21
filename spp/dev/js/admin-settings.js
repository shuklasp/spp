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
    if (typeof window.SPPAdmin === 'undefined') {
        console.warn('admin-settings.js: SPPAdmin not found, skipping enhancement.');
        return;
    }

    /**
     * Override openModuleSettings to use server-rendered LiveAction flow.
     */
    SPPAdmin.prototype.openModuleSettings = async function (modname, publicName) {
        console.log('[admin-settings.js] LiveAction openModuleSettings active. modname=', modname);
        
        try {
            // Call the new LiveAction service that handles modal creation server-side
            const res = await this.api('open_module_settings', { 
                modname, 
                public_name: publicName,
                appname: this.selectedApp 
            });

            if (!res.success) {
                this.notify('Failed to open settings: ' + res.message, 'error');
                return;
            }

            // The modal is opened automatically by SPPUX.api instruction processing.
            // We just need to initialize SPPForm for reactivity if it's the interactive tab.
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
            this.notify('Error opening module settings: ' + err.message, 'error');
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
            if (!modname) modname = document.getElementById('setup-modname')?.value;
            if (!appname) appname = document.getElementById('setup-appname')?.value;

            if (!modname) throw new Error("Module name not found.");

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
