/**
 * ModulesView Component
 */

/**
 * ModulesView Component
 * 
 * Manages framework and application modules.
 */
export default class ModulesView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            modules: [],
            filter: localStorage.getItem('spp_admin_mod_filter') || 'all',
            search: ''
        };
        await this.fetchData();
    }

    async fetchData() {
        try {
            const res = await this.admin.api('list_modules');
            if (res.success) {
                this.setState({
                    modules: res.data.modules || [],
                    loading: false
                });
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    setFilter(filter) {
        localStorage.setItem('spp_admin_mod_filter', filter);
        this.setState({ filter });
    }

    async toggleModule(modname, active) {
        const newStatus = active ? 'active' : 'inactive';
        try {
            const res = await this.admin.apiPost('toggle_module', { 
                modname, 
                status: newStatus 
            });
            
            if (res.success) {
                this.admin.notify(res.message, 'success');
                // Update local state without full reload
                const modules = this.state.modules.map(m => 
                    m.name === modname ? { ...m, active } : m
                );
                this.setState({ modules });
            } else {
                this.admin.notify(res.message, 'error');
                this.update(); // Revert UI
            }
        } catch (err) {
            this.admin.notify('Error toggling module.', 'error');
            this.update();
        }
    }

    render() {
        const { loading, modules, filter, error } = this.state;

        if (loading) return html`<div class="loading-state">Scanning modules...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        // Filtering
        let filtered = modules;
        if (filter === 'core') filtered = modules.filter(m => m.type === 'system');
        if (filter === 'app') filtered = modules.filter(m => m.type === 'user');

        // Grouping
        const groups = {};
        filtered.forEach(mod => {
            const g = mod.module_group || 'General';
            if (!groups[g]) groups[g] = [];
            groups[g].push(mod);
        });

        const groupNames = Object.keys(groups).sort((a, b) => {
            const coreKeywords = ['spp', 'core', 'system', 'internal'];
            const aIsCore = coreKeywords.some(k => a.toLowerCase().includes(k));
            const bIsCore = coreKeywords.some(k => b.toLowerCase().includes(k));
            if (aIsCore && !bIsCore) return -1;
            if (!aIsCore && bIsCore) return 1;
            return a.localeCompare(b);
        });

        // Update Header Actions
        this.renderHeader();

        if (filtered.length === 0) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <h3>No Modules found</h3>
                    <p>No modules match the current filter in this context.</p>
                </div>
            `;
        }

        return html`
            <div class="view-content-wrapper">
                ${groupNames.map(groupName => {
                    const groupModules = groups[groupName];
                    return html`
                        <div class="module-group-header">
                            <h2>${groupName}</h2>
                            <span class="count-badge">${groupModules.length} Modules</span>
                        </div>
                        <div class="card-grid mb-4">
                            ${groupModules.map((mod, i) => {
                                const typeBadge = html`<span class="module-type-badge ${mod.type}">${mod.type === 'system' ? 'CORE' : 'APP'}</span>`;
                                return html`
                                    <div class="item-card ${mod.active ? 'active' : 'inactive-card'}" style="animation-delay: ${i * 0.05}s">
                                        <div class="card-header">
                                            <div>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <h3>${mod.public_name || mod.name}</h3>
                                                    ${typeBadge}
                                                </div>
                                                <div class="card-meta">${mod.author || 'Unknown'} · v${mod.version}</div>
                                            </div>
                                            <label class="toggle-switch">
                                                <input type="checkbox" ?checked="${mod.active}" 
                                                    @change=${(e) => this.toggleModule(mod.name, e.target.checked)}>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <div class="module-card-body">
                                            ${mod.description ? html`<p class="module-description">${mod.description}</p>` : ''}
                                            <div class="module-deps">
                                                ${(mod.dependencies || []).map(d => html`<span class="dep-badge">${d}</span>`)}
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <small title="${mod.path}">${this.admin.truncatePath(mod.path, 40)}</small>
                                            <div class="card-actions">
                                                <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.admin.openModuleMaintenance(mod.name, mod.public_name || mod.name)}>🏗️ Sync</button>
                                                ${mod.has_config ? html`<button type="button" class="btn ghost-btn btn-sm" @click=${() => this.admin.openModuleSettings(mod.name, mod.public_name || mod.name)}>⚙️ Setup</button>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            })}
                        </div>
                    `;
                })}
            </div>
        `;
    }

    async rebuildRegistry() {
        this.admin.notify('Rebuilding module registry cache...', 'info');
        try {
            const res = await this.admin.apiPost('compile_registry');
            if (res.success) {
                this.admin.notify(res.message, 'success');
                await this.fetchData();
            } else {
                this.admin.notify(res.message, 'error');
            }
        } catch (err) {
            this.admin.notify('Compilation request failed.', 'error');
        }
    }

    async runMigrations() {
        this.admin.notify('Running pending migrations...', 'info');
        try {
            const res = await this.admin.apiPost('run_migrations');
            if (res.success) {
                this.admin.notify(res.message, 'success');
                console.log('Migration Log:', res.data.log);
            } else {
                this.admin.notify(res.message, 'error');
            }
        } catch (err) {
            this.admin.notify('Migration request failed.', 'error');
        }
    }

    renderHeader() {
        const headerActions = document.getElementById('header-actions');
        if (!headerActions) return;

        const { modules, filter } = this.state;
        const activeCount = modules.filter(m => m.active).length;

        const headerHtml = html`
            <div class="header-filters" style="display: flex; gap: 12px; align-items: center;">
                <select id="mod-filter-select" class="btn ghost-btn btn-sm" style="background: var(--bg-card-glass);" 
                    @change=${(e) => this.setFilter(e.target.value)}>
                    <option value="all" ?selected="${filter === 'all'}">📦 All Modules</option>
                    <option value="core" ?selected="${filter === 'core'}">🛡️ Core Modules</option>
                    <option value="app" ?selected="${filter === 'app'}">🚀 App Modules</option>
                </select>
                <div class="btn-group" style="display: flex; gap: 8px; flex-shrink: 0;">
                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.rebuildRegistry()} title="Rebuild High-Performance Cache" style="white-space: nowrap;">⚡ Compile</button>
                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.runMigrations()} title="Run Database/File Migrations" style="white-space: nowrap;">🚀 Migrate</button>
                </div>
            </div>
            <span style="font-size: 0.8rem; color: var(--text-dim); white-space: nowrap;">${activeCount}/${modules.length} active</span>
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
}
