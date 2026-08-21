/**
 * AppsView - App Studio Console
 * 
 * Comprehensive management hub for Applications, Modules, Scaffolding,
 * and Shared Resource Groups. Merges formerly separate App/Module boundaries.
 */

export default class AppsView extends BaseComponent {
    constructor(app, container, props = {}) {
        super(app, container, props);
        
        const savedToggles = localStorage.getItem('spp_admin_apps_expanded');
        const expandedArray = savedToggles ? JSON.parse(savedToggles) : [];

        this.state = {
            loading: true,
            apps: [],
            modules: [],
            sharedGroups: {},
            activeTab: localStorage.getItem('spp_admin_apps_tab') || 'apps',
            expandedPaths: new Set(expandedArray),
            modFilter: localStorage.getItem('spp_admin_mod_filter') || 'all',
            scaffoldOutput: '',
            scaffolding: false
        };
    }

    async onInit() {
        await this.loadData();
    }

    async loadData(retryCount = 0) {
        this.setState({ loading: true, error: null });
        try {
            const [appsRes, modsRes] = await Promise.all([
                this.api.listApps(),
                this.api('list_modules')
            ]);
            
            if (appsRes.success && modsRes.success) {
                this.setState({
                    apps: appsRes.data.apps || [],
                    sharedGroups: appsRes.data.shared_groups || {},
                    modules: modsRes.data.modules || [],
                    loading: false
                });
            } else {
                throw new Error((appsRes.message || '') + " " + (modsRes.message || ''));
            }
        } catch (e) {
            if (retryCount < 2) {
                console.warn(`Registry load failed, retrying (${retryCount + 1}/2)...`, e);
                setTimeout(() => this.loadData(retryCount + 1), 500);
            } else {
                this.setState({ 
                    loading: false, 
                    error: `Failed to load registry data: ${e.message}` 
                });
            }
        }
    }

    setTab(tab) {
        localStorage.setItem('spp_admin_apps_tab', tab);
        this.setState({ activeTab: tab });
        this.renderHeader(); // clear or set header actions based on tab
    }

    setModFilter(filter) {
        localStorage.setItem('spp_admin_mod_filter', filter);
        this.setState({ modFilter: filter });
    }

    togglePath(appName) {
        const expanded = new Set(this.state.expandedPaths);
        if (expanded.has(appName)) expanded.delete(appName);
        else expanded.add(appName);
        
        localStorage.setItem('spp_admin_apps_expanded', JSON.stringify(Array.from(expanded)));
        this.setState({ expandedPaths: expanded });
    }

    render() {
        const { loading, activeTab, error } = this.state;

        if (loading && this.state.apps.length === 0) {
            return html`<div class="loading-state">Synchronizing App Studio registry...</div>`;
        }

        if (error) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Error</h3>
                    <p>${error}</p>
                    <button class="btn primary-btn btn-sm mt-4" @click=${() => this.loadData()}>🔄 Retry Connection</button>
                </div>`;
        }

        // Ensure header actions are appropriate for the active tab
        this.renderHeader();

        return html`
            <div class="apps-view">
                <div class="tab-bar-secondary">
                    <button class="sub-tab-btn ${activeTab === 'apps' ? 'active' : ''}" 
                        @click=${() => this.setTab('apps')}>📱 Applications</button>
                    <button class="sub-tab-btn ${activeTab === 'modules' ? 'active' : ''}" 
                        @click=${() => this.setTab('modules')}>📦 Modules Registry</button>
                    <button class="sub-tab-btn ${activeTab === 'builder' ? 'active' : ''}" 
                        @click=${() => this.setTab('builder')}>🛠️ App Builder (CLI)</button>
                    <button class="sub-tab-btn ${activeTab === 'groups' ? 'active' : ''}" 
                        @click=${() => this.setTab('groups')}>👥 Shared Groups</button>
                    <button class="sub-tab-btn ${activeTab === 'logs' ? 'active' : ''}" 
                        @click=${() => this.setTab('logs')}>📡 Server Logs</button>
                </div>

                <div class="apps-content">
                    ${activeTab === 'apps' ? this.renderAppsTable() : ''}
                    ${activeTab === 'modules' ? this.renderModulesTable() : ''}
                    ${activeTab === 'builder' ? this.renderAppBuilder() : ''}
                    ${activeTab === 'groups' ? this.renderGroupsTable() : ''}
                    ${activeTab === 'logs' ? this.renderServerLogs() : ''}
                </div>
            </div>
        `;
    }

    // --- Tab: Applications ---
    renderAppsTable() {
        const { apps, expandedPaths } = this.state;
        return html`
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header">
                    <div class="header-main">
                        <h3 class="gradient-text">Registered Applications</h3>
                        <span class="count-badge">${apps.length}</span>
                        <button class="btn primary-btn btn-sm" @click=${() => this.openTemplateHub()} style="margin-left: 20px;">🏗️ Template Hub</button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Application</th>
                                <th style="width: 25%;">Base URL</th>
                                <th style="width: 15%;">Prefix</th>
                                <th class="text-right" style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${apps.map(app => {
                                const isExpanded = expandedPaths.has(app.name);
                                return html`
                                    <tr class="${isExpanded ? 'expanded-row' : ''}">
                                        <td>
                                            <div class="app-identity" style="display: flex; align-items: center; gap: 12px;">
                                                <label class="icon-radio-wrap" title="Set as Primary Base Application" style="margin-bottom: 0;">
                                                    <input type="radio" name="base-app-selector" value="${app.name}" 
                                                        ?checked="${app.is_base_app}" 
                                                        @change=${() => this.setBaseApp(app.name)}>
                                                    <div class="app-icon ${app.is_base_app ? 'active' : ''}">🚀</div>
                                                </label>
                                                <div class="app-name-wrap">
                                                    <div class="entity-name">${app.name}</div>
                                                    <div class="app-status-row">
                                                        ${app.is_base_app ? html`<span class="status-indicator active"></span> <small>Primary</small>` : ''}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><div class="url-cell"><code class="code-badge primary">${app.base_url || '/'}</code></div></td>
                                        <td><code class="code-badge warning">${app.table_prefix || '(none)'}</code></td>
                                        <td class="text-right">
                                            <div class="btn-group-horizontal" style="justify-content: flex-end;">
                                                <button class="btn ghost-btn btn-sm" @click=${() => this.cloneApp(app.name)} title="Clone Application">👯 Clone</button>
                                                <button class="btn ghost-btn btn-sm" @click=${() => this.openDeploySync(app.name)} title="Export & Deploy">🚀 Deploy</button>
                                                ${app.has_admin ? html`
                                                    <button class="btn primary-btn btn-sm" @click=${() => this.manageApp(app.name)} title="Manage Application">🛠️ Manage</button>
                                                ` : ''}
                                                <button class="btn ghost-btn btn-sm" @click=${() => this.showAppInfo(app)} title="View Configuration">👁️ Show</button>
                                                <button class="btn ghost-btn btn-sm" @click=${() => this.openAppEditor(app.name)} title="Configure Application">⚙️ Configure</button>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            })}
                            ${apps.length === 0 ? html`<tr><td colspan="4" class="empty-row">No applications detected.</td></tr>` : ''}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // --- Tab: Modules ---
    renderModulesTable() {
        const { modules, modFilter, modGraphMode, modSearch } = this.state;
        let filtered = modules;
        
        if (modFilter !== 'all') {
            if (modFilter === 'core') filtered = modules.filter(m => m.type === 'system');
            else if (modFilter === 'app') filtered = modules.filter(m => m.type === 'user');
            else filtered = modules.filter(m => (m.module_category || (m.type === 'system' ? 'Core Optional' : 'App Modules')) === modFilter);
        }

        if (modSearch) {
            const term = modSearch.toLowerCase();
            filtered = filtered.filter(m => 
                (m.name || '').toLowerCase().includes(term) || 
                (m.public_name || '').toLowerCase().includes(term) || 
                (m.description || '').toLowerCase().includes(term)
            );
        }

        if (modGraphMode) {
            return this.renderModuleGraph(filtered);
        }

        const groups = {};
        filtered.forEach(mod => {
            const g = mod.module_category || (mod.type === 'system' ? 'Core Optional' : 'App Modules');
            if (!groups[g]) groups[g] = [];
            groups[g].push(mod);
        });

        const order = ['Core Required', 'Core Optional', 'App Modules'];
        const groupNames = Object.keys(groups).sort((a, b) => {
            const idxA = order.indexOf(a);
            const idxB = order.indexOf(b);
            if (idxA !== -1 && idxB !== -1) return idxA - idxB;
            if (idxA !== -1) return -1;
            if (idxB !== -1) return 1;
            return a.localeCompare(b);
        });

        if (filtered.length === 0) {
            return html`
                <div class="view-content-wrapper fade-in">
                    <div style="margin-bottom: 20px;">
                        <input type="text" 
                               placeholder="🔍 Search modules by name or description..." 
                               value="${modSearch || ''}" 
                               @input=${(e) => this.setState({modSearch: e.target.value})}
                               style="width: 100%; max-width: 400px; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text); font-size: 0.9rem;">
                    </div>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>No Modules found</h3>
                        <p>No modules match the current filter in this context.</p>
                    </div>
                </div>
            `;
        }

        return html`
            <div class="view-content-wrapper fade-in">
                <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <input type="text" 
                           placeholder="🔍 Search modules by name or description..." 
                           value="${modSearch || ''}" 
                           @input=${(e) => this.setState({modSearch: e.target.value})}
                           style="flex: 1; max-width: 400px; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text); font-size: 0.9rem;">
                    
                    <button class="btn secondary-btn" @click=${() => this.app.installAllActiveModules()}>📦 Install All Active</button>
                </div>
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
                                            <small title="${mod.path}">${this.app.truncatePath ? this.app.truncatePath(mod.path, 40) : mod.path}</small>
                                            <div class="card-actions">
                                                <button type="button" class="btn primary-btn btn-sm" @click=${() => this.app.installModule(mod.name)}>📦 Install</button>
                                                <button type="button" class="btn danger-ghost-btn btn-sm" @click=${() => this.app.uninstallModule(mod.name)}>🗑️ Uninstall</button>
                                                ${mod.has_config ? html`<button type="button" class="btn ghost-btn btn-sm" @click=${() => this.app.openModuleSettings(mod.name, mod.public_name || mod.name)}>⚙️ Setup</button>` : ''}
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

    renderModuleGraph(modules) {
        const spacingX = 220;
        const spacingY = 150;
        let cols = 4;
        
        let nodes = [];
        let edgesHtml = [];
        
        // Find dependencies
        const positions = {};
        
        modules.forEach((mod, i) => {
            const x = (i % cols) * spacingX + 50;
            const y = Math.floor(i / cols) * spacingY + 50;
            positions[mod.name] = { x: x + 100, y: y + 40 }; // center approx
            
            nodes.push(html`
                <div class="spp-card ${mod.active ? 'border-primary' : 'border-muted'}" style="position: absolute; left: ${x}px; top: ${y}px; width: 200px; padding: 15px; z-index: 10; cursor: move; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <h5 style="margin: 0 0 5px 0;">${mod.name}</h5>
                    <span style="font-size: 0.7rem; color: var(--text-dim);">${mod.type === 'system' ? 'Core' : 'App'} Module</span>
                    ${!mod.active ? html`<div style="font-size: 0.7rem; color: var(--danger);">Inactive</div>` : ''}
                </div>
            `);
        });

        // Draw simple SVG lines for dependencies
        modules.forEach(mod => {
            if (mod.dependencies && mod.dependencies.length > 0) {
                mod.dependencies.forEach(dep => {
                    if (positions[mod.name] && positions[dep]) {
                        const from = positions[mod.name];
                        const to = positions[dep];
                        edgesHtml.push(html`
                            <line x1="${from.x}" y1="${from.y}" x2="${to.x}" y2="${to.y}" stroke="var(--primary-color)" stroke-width="2" marker-end="url(#arrowhead)" opacity="0.6" stroke-dasharray="5,5" />
                        `);
                    }
                });
            }
        });

        return html`
            <div class="spp-card" style="position: relative; height: 600px; overflow: auto; background: var(--surface-1); margin-top: 20px;">
                <svg style="position: absolute; top: 0; left: 0; width: 2000px; height: 2000px; z-index: 1;">
                    <defs>
                        <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                            <polygon points="0 0, 10 3.5, 0 7" fill="var(--primary-color)" />
                        </marker>
                    </defs>
                    ${edgesHtml}
                </svg>
                ${nodes}
            </div>
        `;
    }

    async toggleModule(modname, active) {
        const newStatus = active ? 'active' : 'inactive';
        try {
            const res = await this.apiPost('toggle_module', { 
                modname, 
                status: newStatus,
                appname: this.app.selectedApp
            });
            if (res.success) {
                this.notify(res.message, 'success');
                const modules = this.state.modules.map(m => m.name === modname ? { ...m, active } : m);
                this.setState({ modules });
            } else {
                this.notify(res.message, 'error');
                this.update();
            }
        } catch (err) {
            this.notify('Error toggling module.', 'error');
            this.update();
        }
    }

    async rebuildRegistry() {
        this.notify('Rebuilding module registry cache...', 'info');
        try {
            const res = await this.apiPost('compile_registry');
            if (res.success) {
                this.notify(res.message, 'success');
                await this.loadData();
            } else {
                this.notify(res.message, 'error');
            }
        } catch (err) {
            this.notify('Compilation request failed.', 'error');
        }
    }

    async runMigrations() {
        this.notify('Running pending migrations...', 'info');
        try {
            const res = await this.apiPost('run_migrations');
            if (res.success) {
                this.notify(res.message, 'success');
                console.log('Migration Log:', res.data.log);
            } else {
                this.notify(res.message, 'error');
            }
        } catch (err) {
            this.notify('Migration request failed.', 'error');
        }
    }

    // --- Tab: App Builder (Scaffolding) ---
    renderAppBuilder() {
        const { scaffolding, scaffoldOutput, selectedScaffoldCmd } = this.state;
        const currentCmd = selectedScaffoldCmd || 'make:app';
        const cmdHelp = {
            'make:app': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--api</code>: Scaffolds a lightweight, API-only application without frontend views or UI routing.</li>
                        <li><code>--no-db</code>: Skips database configuration and connection bootstrapping.</li>
                        <li><code>--enterprise</code>: Enables Enterprise Mode (configures Redis for sessions and caching, enables strict typing).</li>
                    </ul>
                </div>
            `,
            'make:module': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--force</code>: Overwrites an existing module with the same name if one is already present. USE WITH CAUTION.</li>
                    </ul>
                </div>
            `,
            'make:entity': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--table=name</code>: Maps the entity to a specific custom table name instead of auto-deriving it.</li>
                        <li><code>--no-migration</code>: Creates the entity definition but skips pushing the schema changes to the database.</li>
                        <li><code>--force</code>: Overwrites existing entity definitions if they conflict.</li>
                    </ul>
                </div>
            `,
            'make:controller': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--resource</code>: Automatically generates standard CRUD methods (index, create, store, show, edit, update, destroy).</li>
                        <li><code>--api</code>: Excludes web views and restricts controller responses exclusively to JSON APIs.</li>
                    </ul>
                </div>
            `,
            'make:scaffold': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--api</code>: Scaffolds an API-only stack (Controller + Entity + DB schema), skipping any visual UI or views.</li>
                        <li><code>--force</code>: Forcefully overwrites any existing scaffold files that share the target name.</li>
                    </ul>
                </div>
            `,
            'make:service': html`
                <div style="margin-top: 8px;">
                    <strong>Available Options:</strong>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        <li><code>--singleton</code>: Registers the service as a shared singleton in the Dependency Injection container.</li>
                        <li><code>--force</code>: Overwrites the service class if it already exists.</li>
                    </ul>
                </div>
            `,
            'make:blade': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Creates a new Drishyam Blade view.</div>`,
            'make:twig': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Creates a new Drishyam Twig view.</div>`,
            'make:sppview': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Creates a new native AST-based SPPView.</div>`,
            'make:mixed-paradigm': html`<div style="margin-top: 8px;"><strong>Usage:</strong> Scaffolds a Kitchen Sink example blending SPPView, Blade/Twig, and SPPUX.</div>`
        };

        return html`
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header">
                    <div class="header-main">
                        <h3 class="gradient-text">App Builder (CLI)</h3>
                        <span class="tag info-tag">spp.php CLI Interop</span>
                    </div>
                </div>
                
                <div style="margin: 20px 20px 0 20px; display: flex; gap: 10px; align-items: center; background: var(--surface-2); padding: 15px; border-radius: 8px; border: 1px solid var(--primary-color);">
                    <span style="font-size: 1.2rem;">🤖</span>
                    <input type="text" id="ai-cli-prompt" placeholder="e.g. Create a new REST API application called billing and a Stripe module..." style="flex-grow: 1; padding: 10px; border-radius: 4px; border: 1px solid var(--glass-border); background: var(--surface-1); color: var(--text);">
                    <button type="button" class="btn primary-btn" @click=${() => this.runAiScaffold()} ?disabled="${scaffolding}">
                        ${scaffolding ? '⏳' : '✨ Generate & Execute'}
                    </button>
                </div>
                
                <div class="form-grid" style="padding: 20px; align-items: end;">
                    <div class="input-group">
                        <label>Command Type</label>
                        <select id="scaffold-command" @change=${(e) => this.setState({selectedScaffoldCmd: e.target.value})}>
                            <option value="make:app" ?selected=${currentCmd === 'make:app'}>make:app - New Application Context</option>
                            <option value="make:module" ?selected=${currentCmd === 'make:module'}>make:module - New SPP Module</option>
                            <option value="make:entity" ?selected=${currentCmd === 'make:entity'}>make:entity - Data Entity definition</option>
                            <option value="make:controller" ?selected=${currentCmd === 'make:controller'}>make:controller - Scaffold Controller</option>
                            <option value="make:scaffold" ?selected=${currentCmd === 'make:scaffold'}>make:scaffold - Full Entity/View Stack</option>
                            <option value="make:service" ?selected=${currentCmd === 'make:service'}>make:service - Dependency Injected Service</option>
                            <option value="make:blade" ?selected=${currentCmd === 'make:blade'}>make:blade - Scaffold Blade View</option>
                            <option value="make:twig" ?selected=${currentCmd === 'make:twig'}>make:twig - Scaffold Twig View</option>
                            <option value="make:sppview" ?selected=${currentCmd === 'make:sppview'}>make:sppview - Scaffold Native AST View</option>
                            <option value="make:mixed-paradigm" ?selected=${currentCmd === 'make:mixed-paradigm'}>make:mixed-paradigm - Scaffold Kitchen Sink View</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Target Name</label>
                        <input type="text" id="scaffold-name" placeholder="e.g. AuthModule or Product">
                    </div>
                    ${currentCmd === 'make:app' ? html`
                    <div class="input-group">
                        <label>App Type</label>
                        <select id="scaffold-apptype">
                            <option value="native">native (Default)</option>
                            <option value="blade">blade (Laravel Style)</option>
                            <option value="react">react (React frontend)</option>
                            <option value="vue">vue (Vue frontend)</option>
                            <option value="drupal">drupal (Drupal bridge)</option>
                            <option value="sppux">sppux (Native UI)</option>
                            <option value="dropin">dropin</option>
                        </select>
                    </div>
                    ` : ''}
                    <div class="input-group full-width" style="grid-column: 1 / -1;">
                        <label>Command Options / Flags <span class="help-tag" title="Command line flags and options">(?)</span></label>
                        <input type="text" id="scaffold-options" placeholder="--force --api">
                        <div class="help-text" style="font-size: 0.8rem; color: var(--text-dim); margin-top: 5px;">
                            💡 ${cmdHelp[currentCmd]}
                        </div>
                    </div>
                    <div class="input-group full-width" style="display: flex; align-items: flex-end; justify-content: flex-end; grid-column: 1 / -1;">
                        <button class="btn primary-btn shine-effect" ?disabled="${scaffolding}" @click=${() => this.runScaffold()}>
                            ${scaffolding ? '⏳ Processing...' : '🚀 Execute Scaffold'}
                        </button>
                    </div>
                </div>

                <div class="code-terminal" style="margin: 0 20px 20px 20px; background: #0f111a; border-radius: 8px; padding: 15px; min-height: 200px; max-height: 400px; overflow-y: auto; font-family: monospace; color: #a6accd; border: 1px solid rgba(255,255,255,0.1);">
                    ${scaffoldOutput ? html`<pre style="margin:0; white-space: pre-wrap;">${scaffoldOutput}</pre>` : html`<div style="opacity:0.5; font-style:italic;">Terminal Output...</div>`}
                </div>
            </div>
        `;
    }

    async runAiScaffold() {
        const prompt = document.getElementById('ai-cli-prompt')?.value;
        if (!prompt) return this.notify('Please enter a natural language prompt.', 'error');
        
        this.setState({ scaffolding: true, scaffoldOutput: '🤖 Analyzing prompt and building command sequence...\n' });
        
        try {
            const res = await this.apiPost('ai_parse_scaffold', { prompt });
            if (res.success && res.data && res.data.commands) {
                let output = this.state.scaffoldOutput;
                for (const cmdObj of res.data.commands) {
                    output += `\n> Executing: php spp.php ${cmdObj.cmd} ${cmdObj.target} ${cmdObj.opts}\n`;
                    this.setState({ scaffoldOutput: output });
                    
                    const cmdRes = await this.apiPost('execute_scaffold', { 
                        command: cmdObj.cmd, 
                        target: cmdObj.target, 
                        options: cmdObj.opts 
                    });
                    
                    output += (cmdRes.data?.output || cmdRes.message) + "\n";
                    this.setState({ scaffoldOutput: output });
                }
                this.notify('AI Scaffold completed.', 'success');
                this.loadData();
            } else {
                this.notify(res.message || 'Failed to parse prompt', 'error');
                this.setState({ scaffoldOutput: this.state.scaffoldOutput + '\n' + (res.message || 'Parse error') });
            }
        } catch (e) {
            this.setState({ scaffoldOutput: this.state.scaffoldOutput + `\nNetwork Error: ${e.message}` });
        } finally {
            this.setState({ scaffolding: false });
        }
    }

    async runScaffold() {
        const cmd = document.getElementById('scaffold-command').value;
        const target = document.getElementById('scaffold-name').value.trim();
        let opts = document.getElementById('scaffold-options').value.trim();
        
        if (cmd === 'make:app') {
            const appType = document.getElementById('scaffold-apptype')?.value;
            if (appType) {
                opts = `${appType} ${opts}`.trim();
            }
        }
        
        if (!target) {
            return this.notify('Please provide a target name.', 'error');
        }

        this.setState({ scaffolding: true, scaffoldOutput: `Executing: php spp.php ${cmd} ${target} ...\n` });
        
        try {
            const res = await this.apiPost('execute_scaffold', { command: cmd, target: target, options: opts });
            this.setState({ 
                scaffolding: false, 
                scaffoldOutput: this.state.scaffoldOutput + (res.data?.output || res.message) 
            });
            if (res.success) {
                this.notify('Scaffold completed successfully.', 'success');
                this.loadData(); // refresh modules/apps list
            } else {
                this.notify('Scaffold execution returned an error.', 'error');
            }
        } catch (e) {
            this.setState({ scaffolding: false, scaffoldOutput: this.state.scaffoldOutput + `\nNetwork/Execution Error: ${e.message}` });
        }
    }


    // --- Tab: Shared Groups ---
    renderGroupsTable() {
        const { sharedGroups } = this.state;
        const groupEntries = Object.entries(sharedGroups);

        return html`
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header">
                    <div class="header-main">
                        <h3 class="gradient-text">Shared Resource Groups</h3>
                        <button class="btn primary-btn btn-sm" @click=${() => this.openGroupEditor()}>+ New Group</button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table elegant-table">
                        <thead>
                            <tr>
                                <th style="width: 200px;">Group Name</th>
                                <th style="width: 180px;">Inheritance</th>
                                <th style="width: 120px;">Table Prefix</th>
                                <th>Shared Entities</th>
                                <th class="actions-head" style="width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${groupEntries.map(([name, group]) => html`
                                <tr>
                                    <td><div class="entity-name">${name}</div></td>
                                    <td>
                                        ${group.extends ? html`<span class="tag link-tag primary">Inherits: ${group.extends}</span>` : html`<span class="tag muted-tag">Root Group</span>`}
                                    </td>
                                    <td><code class="code-badge success">${group.table_prefix || ''}</code></td>
                                    <td>
                                        <div class="entity-list-chipset">
                                            ${(group.entities || []).map(ent => html`<span class="chip">${ent}</span>`)}
                                        </div>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="btn-group-horizontal">
                                            <button class="btn ghost-btn btn-sm" @click=${() => this.openGroupEditor(name)}>✏️ Edit</button>
                                            <button class="btn danger-ghost-btn btn-sm" @click=${() => this.deleteGroup(name)}>🗑️ Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            `)}
                            ${groupEntries.length === 0 ? html`<tr><td colspan="5" class="empty-row">No shared groups defined.</td></tr>` : ''}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // --- Dialogs and App Configurations ---
    async manageApp(appName) {
        window.admin.onAppContextChange(appName);
        const app = this.state.apps.find(a => a.name === appName);
        location.hash = (app && app.has_admin) ? appName : 'manage';
        this.notify(`Switched to ${appName} Management`, 'success');
    }

    async setBaseApp(appName) {
        try {
            const formData = new FormData();
            formData.append('action', 'set_base_app');
            formData.append('target_app', appName);
            
            const res = await this.apiPost(formData);
            if (res.success) {
                this.notify(`Base application set to "${appName}".`, 'success');
                await this.loadData();
            } else {
                this.notify(res.message || 'Operation failed', 'error');
            }
        } catch (err) {
            this.notify(`Error changing base app: ${err.message}`, 'error');
        }
    }

    async openAppEditor(appName) {
        const app = this.state.apps.find(a => a.name === appName);
        if (!app) return;

        this.openModal(`Configure: ${appName}`, html`
            <div class="form-grid">
                <div class="input-group">
                    <label>Base URL Prefix <span class="help-tag" title="The URL path where this application is mounted (e.g., /admin). Leave empty for the root domain.">(?)</span></label>
                    <input type="text" id="app-base-url" value="${app.base_url || ''}" placeholder="/myapp">
                </div>
                <div class="input-group">
                    <label>Table Name Prefix <span class="help-tag" title="Prefixes all database tables for this app to prevent collisions (e.g., app1_users, app2_users).">(?)</span></label>
                    <input type="text" id="app-table-prefix" value="${app.table_prefix || ''}" placeholder="prefix_">
                </div>
                <div class="input-group">
                    <label>Shared Resource Group</label>
                    <select id="app-shared-group">
                        <option value="">None (Isolated)</option>
                        ${Object.keys(this.state.sharedGroups).map(g => html`<option value="${g}" ?selected="${app.shared_group === g}">${g}</option>`)}
                    </select>
                </div>
                <div class="input-group">
                    <label>Custom Etc Directory (Optional)</label>
                    <input type="text" id="app-etc-path" value="${app.etc_path || ''}" placeholder="/etc or relative/to/src">
                </div>
                <div class="input-group">
                    <label>Custom Src Directory (Optional)</label>
                    <input type="text" id="app-src-path" value="${app.src_path || ''}" placeholder="/src/custom">
                </div>
                <div class="input-group full-width" style="grid-column: 1 / -1;">
                    <label>Advanced Options (YAML) <span class="help-tag" title="Custom configuration options for this application. Available options:\n- cache_enabled: true/false\n- auth_provider: local/sso\n- custom_theme: dark/light\n- timezone: UTC\nFormat must be standard YAML.">(?)</span></label>
                    <textarea id="app-options" rows="3" placeholder="cache_enabled: true\nauth_provider: local">${app.options_yaml || ''}</textarea>
                </div>
            </div>
            
            <details class="advanced-settings mt-4">
                <summary class="text-dim" style="cursor:pointer">Database Override (Optional)</summary>
                <div class="form-grid" style="padding-top: 10px;">
                    <div class="input-group"><label>DB Host</label><input type="text" id="app-dbhost" value="${app.db_config?.dbhost || ''}"></div>
                    <div class="input-group"><label>DB Name</label><input type="text" id="app-dbname" value="${app.db_config?.dbname || ''}"></div>
                    <div class="input-group"><label>DB User</label><input type="text" id="app-dbuser" value="${app.db_config?.dbuser || ''}"></div>
                    <div class="input-group"><label>DB Password</label><input type="password" id="app-dbpasswd" value="${app.db_config?.dbpasswd || ''}"></div>
                </div>
            </details>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Save Changes', type: 'primary', fn: async () => {
                const config = {
                    base_url: document.getElementById('app-base-url').value.trim(),
                    table_prefix: document.getElementById('app-table-prefix').value.trim(),
                    shared_group: document.getElementById('app-shared-group').value,
                    etc_path: document.getElementById('app-etc-path').value.trim(),
                    src_path: document.getElementById('app-src-path').value.trim(),
                    options_yaml: document.getElementById('app-options').value.trim()
                };
                const dbname = document.getElementById('app-dbname').value.trim();
                if (dbname) {
                    config.db_config = {
                        dbhost: document.getElementById('app-dbhost').value.trim() || 'localhost',
                        dbname: dbname,
                        dbuser: document.getElementById('app-dbuser').value.trim(),
                        dbpasswd: document.getElementById('app-dbpasswd').value
                    };
                }
                const fd = new FormData();
                fd.append('target_app', appName);
                fd.append('config', JSON.stringify(config));

                const res = await this.api.saveAppConfig(fd);
                if (res.success) {
                    this.notify('Application configuration updated.', 'success');
                    this.closeModal();
                    await this.loadData();
                } else this.notify(res.message || 'Update failed', 'error');
            }}
        ]);
    }

    showAppInfo(app) {
        this.openModal(`Application Details: ${app.name}`, html`
            <div class="app-info-modal">
                <div class="info-section">
                    <h4>Overview</h4>
                    <div class="info-grid">
                        <div class="info-item"><strong>Name:</strong> <span>${app.name}</span></div>
                        <div class="info-item"><strong>Type:</strong> <span>${app.is_base_app ? 'Primary / Base Application' : 'Standard Module'}</span></div>
                        <div class="info-item"><strong>Base URL:</strong> <code class="code-badge">${app.base_url || '/'}</code></div>
                        <div class="info-item"><strong>Table Prefix:</strong> <code class="code-badge warning">${app.table_prefix || '(none)'}</code></div>
                    </div>
                </div>
                <div class="info-section mt-4">
                    <h4>System Paths</h4>
                    <div class="path-card glass-panel p-3">
                        <div class="path-row mb-2"><strong>ETC:</strong> <code class="text-xs">${app.etc_path}</code></div>
                        <div class="path-row"><strong>SRC:</strong> <code class="text-xs">${app.src_path}</code></div>
                    </div>
                </div>
            </div>
            <style>
                .app-info-modal h4 { color: var(--primary-color); margin-bottom: 10px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: rgba(0,0,0,0.05); padding: 15px; border-radius: 8px; }
                .info-item { display: flex; flex-direction: column; gap: 4px; }
                .info-item strong { font-size: 0.75rem; color: var(--text-dim); }
                .path-card code { display: block; word-break: break-all; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; }
            </style>
        `, [
            { label: 'Configure', type: 'ghost', fn: () => { this.closeModal(); this.openAppEditor(app.name); } },
            { label: 'Close', type: 'secondary', fn: () => this.closeModal() }
        ]);
    }

    async openGroupEditor(groupName = null) {
        const group = groupName ? this.state.sharedGroups[groupName] : { extends: '', table_prefix: '', entities: [] };
        
        this.openModal(groupName ? `Edit Group: ${groupName}` : 'New Shared Group', html`
            <div class="form-grid">
                <div class="input-group">
                    <label>Group Name</label>
                    <input type="text" id="group-name" value="${groupName || ''}" ?disabled="${!!groupName}">
                </div>
                <div class="input-group">
                    <label>Inherits From <span class="help-tag" title="Inherit tables and code from an existing base group to extend its functionality.">(?)</span></label>
                    <select id="group-extends">
                        <option value="">None</option>
                        ${Object.keys(this.state.sharedGroups).filter(g => g !== groupName).map(g => html`<option value="${g}" ?selected="${group.extends === g}">${g}</option>`)}
                    </select>
                </div>
                <div class="input-group">
                    <label>Group Table Prefix <span class="help-tag" title="A shared prefix for all entities within this group. Ensures isolation from other groups.">(?)</span></label>
                    <input type="text" id="group-prefix" value="${group.table_prefix || ''}">
                </div>
                <div class="input-group full-width">
                    <label>Shared Entities (Comma separated names)</label>
                    <textarea id="group-entities" rows="3">${(group.entities || []).join(', ')}</textarea>
                </div>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Save Group', type: 'primary', fn: async () => {
                const name = document.getElementById('group-name').value.trim();
                if (!name) return this.notify('Group name is required.', 'error');

                const updatedGroups = { ...this.state.sharedGroups };
                updatedGroups[name] = {
                    extends: document.getElementById('group-extends').value,
                    table_prefix: document.getElementById('group-prefix').value.trim(),
                    entities: document.getElementById('group-entities').value.split(',').map(s => s.trim()).filter(s => s)
                };

                const fullSettings = await this.api.getGlobalSettings();
                if (fullSettings.success) {
                    const settings = fullSettings.data;
                    settings.shared_groups = updatedGroups;
                    const fd = new FormData();
                    fd.append('settings', JSON.stringify(settings));

                    const res = await this.api.saveGlobalSettings(fd);
                    if (res.success) {
                        this.notify('Shared group saved.', 'success');
                        this.closeModal();
                        await this.loadData();
                    } else this.notify(res.message || 'Save failed', 'error');
                }
            }}
        ]);
    }

    async deleteGroup(name) {
        if (!confirm(`Delete shared group "${name}"?`)) return;
        const updatedGroups = { ...this.state.sharedGroups };
        delete updatedGroups[name];

        const fullSettings = await this.api.getGlobalSettings();
        if (fullSettings.success) {
            const settings = fullSettings.data;
            settings.shared_groups = updatedGroups;
            const fd = new FormData();
            fd.append('settings', JSON.stringify(settings));

            const res = await this.api.saveGlobalSettings(fd);
            if (res.success) {
                this.notify('Shared group deleted.', 'success');
                await this.loadData();
            } else this.notify(res.message || 'Delete failed', 'error');
        }
    }

    async cloneApp(appName) {
        const newName = prompt(`Enter new application name for cloned ${appName}:`);
        if (!newName) return;
        
        SPPUX.notify(`Cloning ${appName} to ${newName}...`, 'info');
        const res = await this.apiPost('clone_app', { source: appName, target: newName });
        if (res.success) {
            SPPUX.notify('App cloned successfully!', 'success');
            this.loadData();
        } else {
            SPPUX.notify(res.message || 'Clone failed', 'error');
        }
    }

    openTemplateHub() {
        this.openModal('SPP Template Hub', html`
            <div class="card-grid mb-4">
                <div class="item-card active p-3 text-center" style="cursor: pointer;" @click=${() => this.scaffoldTemplate('saas')}>
                    <h4>🏢 SaaS Starter</h4>
                    <p class="text-xs text-muted">Billing, Tenancy, Auth</p>
                </div>
                <div class="item-card active p-3 text-center" style="cursor: pointer;" @click=${() => this.scaffoldTemplate('crm')}>
                    <h4>👥 CRM Base</h4>
                    <p class="text-xs text-muted">Leads, Contacts, Notes</p>
                </div>
                <div class="item-card active p-3 text-center" style="cursor: pointer;" @click=${() => this.scaffoldTemplate('blog')}>
                    <h4>📝 Blog/CMS</h4>
                    <p class="text-xs text-muted">Posts, Categories, SEO</p>
                </div>
            </div>
        `, [{ label: 'Close', type: 'secondary', fn: () => this.closeModal() }]);
    }

    async scaffoldTemplate(tplName) {
        SPPUX.notify(`Scaffolding ${tplName} template...`, 'info');
        const res = await this.apiPost('scaffold_template', { template: tplName });
        if (res.success) {
            SPPUX.notify('Template generated successfully!', 'success');
            this.closeModal();
            this.loadData();
        } else {
            SPPUX.notify(res.message || 'Template failed', 'error');
        }
    }

    // --- Tab: Server Logs ---
    renderServerLogs() {
        const { logLines, logFilter, logPolling } = this.state;
        const lines = logLines || [];
        const filter = logFilter || 'all';

        const filtered = filter === 'all' ? lines : lines.filter(l => {
            if (filter === 'error') return /error|fatal|exception/i.test(l);
            if (filter === 'warn') return /warn/i.test(l);
            if (filter === 'info') return /info|notice/i.test(l);
            return true;
        });

        return html`
            <div class="glass-panel registry-panel fade-in">
                <div class="panel-header">
                    <div class="header-main">
                        <h3 class="gradient-text">Live Server Logs</h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select class="btn ghost-btn btn-sm" style="background: var(--bg-card-glass);" @change=${(e) => this.setState({logFilter: e.target.value})}>
                                <option value="all">📋 All Levels</option>
                                <option value="error">🔴 Errors Only</option>
                                <option value="warn">🟡 Warnings</option>
                                <option value="info">🔵 Info</option>
                            </select>
                            <button type="button" class="btn ${logPolling ? 'danger-ghost-btn' : 'primary-btn'} btn-sm" @click=${() => this.toggleLogPolling()}>
                                ${logPolling ? '⏹️ Stop Tail' : '▶️ Start Live Tail'}
                            </button>
                            <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.fetchLogs()}>🔄 Refresh</button>
                        </div>
                    </div>
                </div>
                <div class="code-terminal" style="margin: 0 20px 20px 20px; background: #0f111a; border-radius: 8px; padding: 15px; min-height: 350px; max-height: 550px; overflow-y: auto; font-family: 'Fira Code', 'Cascadia Code', monospace; font-size: 0.8rem; color: #a6accd; border: 1px solid rgba(255,255,255,0.1);">
                    ${filtered.length === 0
                        ? html`<div style="opacity:0.5; font-style:italic;">No log entries. Click "Start Live Tail" or "Refresh" to fetch logs.</div>`
                        : filtered.map(line => {
                            let color = '#a6accd';
                            if (/error|fatal|exception/i.test(line)) color = '#ff5370';
                            else if (/warn/i.test(line)) color = '#ffcb6b';
                            else if (/info|notice/i.test(line)) color = '#82aaff';
                            return html`<div style="color: ${color}; line-height: 1.6; border-bottom: 1px solid rgba(255,255,255,0.03); padding: 2px 0;">${line}</div>`;
                        })
                    }
                </div>
            </div>
        `;
    }

    async fetchLogs() {
        try {
            const res = await this.apiPost('tail_logs', {});
            if (res.success && res.data && res.data.lines) {
                this.setState({ logLines: res.data.lines });
            } else {
                this.notify(res.message || 'Failed to fetch logs', 'error');
            }
        } catch (e) {
            this.notify('Log fetch error: ' + e.message, 'error');
        }
    }

    toggleLogPolling() {
        if (this.state.logPolling) {
            clearInterval(this._logPollTimer);
            this._logPollTimer = null;
            this.setState({ logPolling: false });
            this.notify('Log tailing stopped.', 'info');
        } else {
            this.fetchLogs();
            this._logPollTimer = setInterval(() => this.fetchLogs(), 5000);
            this.setState({ logPolling: true });
            this.notify('Live tail started (5s interval).', 'success');
        }
    }

    // --- Deploy Sync ---
    openDeploySync(appName) {
        this.openModal(`Deploy / Sync: ${appName}`, html`
            <div class="form-grid">
                <div class="input-group full-width">
                    <label>Target Environment URL <span class="help-tag" title="The URL of the remote SPP Admin API on the target server.">(?)</span></label>
                    <input type="text" id="deploy-target-url" placeholder="https://staging.example.com/admin/api.php">
                </div>
                <div class="input-group full-width">
                    <label>Deploy Token / Secret</label>
                    <input type="password" id="deploy-token" placeholder="Bearer token or shared secret">
                </div>
            </div>
            <div class="spp-card" style="margin-top: 15px; padding: 15px; background: var(--surface-2);">
                <h5 style="margin-top: 0;">📦 What gets packaged:</h5>
                <ul style="font-size: 0.85rem; color: var(--text-dim); margin: 5px 0 0 15px;">
                    <li>Application configuration (base URL, prefixes, options)</li>
                    <li>All entity YAMLs and PHP logic files</li>
                    <li>Module registry state</li>
                    <li>Shared group definitions</li>
                </ul>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: '📦 Export Package Only', type: 'ghost', fn: () => this.exportAppPackage(appName) },
            { label: '🚀 Package & Deploy', type: 'primary', fn: () => this.deployToTarget(appName) }
        ]);
    }

    async exportAppPackage(appName) {
        SPPUX.notify('Packaging application...', 'info');
        try {
            const res = await this.apiPost('export_app_package', { app: appName });
            if (res.success) {
                SPPUX.notify('Package exported! Check console for payload.', 'success');
                console.log('[SPP Deploy] Exported Package:', res.data);
            } else {
                SPPUX.notify(res.message || 'Export failed', 'error');
            }
        } catch (e) {
            SPPUX.notify('Export error: ' + e.message, 'error');
        }
    }

    async deployToTarget(appName) {
        const targetUrl = document.getElementById('deploy-target-url')?.value;
        const token = document.getElementById('deploy-token')?.value;
        if (!targetUrl) return SPPUX.notify('Target URL is required.', 'error');

        SPPUX.notify('Packaging and deploying...', 'info');
        try {
            const res = await this.apiPost('export_app_package', { app: appName });
            if (res.success) {
                // In production this would POST the payload to targetUrl.
                // For dev safety, we log and simulate success.
                console.log('[SPP Deploy] Would POST to:', targetUrl, 'with token:', token ? '***' : '(none)');
                console.log('[SPP Deploy] Payload:', res.data);
                SPPUX.notify('Deploy simulated successfully! Payload logged to console.', 'success');
                this.closeModal();
            } else {
                SPPUX.notify(res.message || 'Packaging failed', 'error');
            }
        } catch (e) {
            SPPUX.notify('Deploy error: ' + e.message, 'error');
        }
    }

    // --- Header Injection ---
    renderHeader() {
        const headerActions = document.getElementById('header-actions');
        if (!headerActions) return;

        if (this.state.activeTab === 'modules') {
            const { modules, modFilter } = this.state;
            const activeCount = modules.filter(m => m.active).length;
            const categories = Array.from(new Set(modules.map(m => m.module_category || (m.type === 'system' ? 'Core Optional' : 'App Modules'))));
            
            const headerHtml = html`
                <div class="header-filters" style="display: flex; gap: 12px; align-items: center;">
                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.setState({modGraphMode: !this.state.modGraphMode})}>
                        ${this.state.modGraphMode ? '📋 List View' : '🕸️ Graph View'}
                    </button>
                    <select class="btn ghost-btn btn-sm" style="background: var(--bg-card-glass);" 
                        @change=${(e) => this.setModFilter(e.target.value)}>
                        <option value="all" ?selected="${modFilter === 'all'}">📦 All Modules</option>
                        ${categories.map(c => html`<option value="${c}" ?selected="${modFilter === c}">📁 ${c}</option>`)}
                    </select>
                    <div class="btn-group" style="display: flex; gap: 8px;">
                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.rebuildRegistry()}>⚡ Compile</button>
                        <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.runMigrations()}>🚀 Migrate</button>
                    </div>
                </div>
                <span style="font-size: 0.8rem; color: var(--text-dim); white-space: nowrap;">${activeCount}/${modules.length} active</span>
            `;
            headerActions.innerHTML = headerHtml.toString();
            
            if (!headerActions._hasSppListener) {
                ['click', 'change'].forEach(type => headerActions.addEventListener(type, (e) => this._onEvent(e)));
                headerActions._hasSppListener = true;
            }
        } else {
            headerActions.innerHTML = '';
        }
    }
}
