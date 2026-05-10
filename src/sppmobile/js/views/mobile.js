import ComponentRegistry from '../registry.js';

export default class MobileView extends BaseComponent {
    async onInit(config = null) {
        this.state = {
            loading: true,
            config: config || {},
            activeScreenId: 'home',
            deviceTheme: 'light',
            deviceType: 'ios',
            inspectorTab: 'theme',
            viewMode: 'studio',
            draggedComponent: null,
            draggedCanvasId: null,
            dragOver: false,
            appState: {},
            assets: [],
            folders: [],
            currentAssetPath: '',
            history: [],
            historyIndex: -1,
            contextMenu: { visible: false, x: 0, y: 0, targetId: null },
            zoom: 100,
            breakpoint: 'mobile',
            librarySearch: '',
            libraryMode: 'components',
            themePresets: [],
            bridgeInfo: null,
            currentPipelineSteps: []
        };

        await this.fetchData();
        if (config) {
            const migrated = this.migrateConfig(config);
            this.setState({ 
                config: migrated, 
                appState: migrated.state || {},
                loading: false 
            });
        }
        
        await this.fetchEntities();
        this._isHistoryAction = true;
        this.pushHistory(); // Initial state
        this._isHistoryAction = false;
        await this.fetchBridgeInfo();
        this.loadGoogleFont(this.getActiveTheme().font);
        await this.triggerOnLoadActions(this.state.activeScreenId);
        window.addEventListener('keydown', (e) => this.handleShortcuts(e));
        window.addEventListener('click', () => this.hideContextMenu());
        window.addEventListener('contextmenu', (e) => this.handleGlobalContextMenu(e));
    }

    renderThemeInspector() {
        const { config } = this.state;
        const themes = config.themes || [];
        const activeTheme = this.getActiveTheme();

        const updateTheme = (key, val) => {
            const updatedTheme = { ...activeTheme, [key]: val };
            if (key === 'borderRadius') updatedTheme.radius = val;
            if (key === 'radius') updatedTheme.borderRadius = val;
            
            const newThemes = config.themes.map(t => t.name === activeTheme.name ? updatedTheme : t);
            
            this.setState({ 
                config: { 
                    ...config, 
                    themes: newThemes 
                } 
            });
            
            if (key === 'font') this.loadGoogleFont(val);
            this.update(); // Force immediate re-render for UI feedback
        };

        return html`
            <div class="inspector-section fade-in">
                <div class="flex-between mb-3">
                    <h3 class="m-0">Design Systems</h3>
                    <div class="flex-center gap-1">
                        <button class="btn-icon btn-xs" title="New Theme" @click=${() => this.createTheme()}>➕</button>
                        <button class="btn-icon btn-xs" title="Copy Current" @click=${() => this.copyTheme(activeTheme)}>📋</button>
                    </div>
                </div>

                <div class="input-group mb-4">
                    <div class="flex-between gap-2">
                        <select class="flex-1" @change=${(e) => { config.activeTheme = e.target.value; this.update(); }}>
                            ${themes.map(t => html`<option value="${t.name}" ?selected=${activeTheme.name === t.name}>${t.name}</option>`)}
                            ${themes.length === 0 ? html`<option>Default</option>` : ''}
                        </select>
                        <button class="btn-icon btn-xs" @click=${() => this.renameTheme(activeTheme)}>✏️</button>
                        <button class="btn-icon btn-xs text-danger" @click=${() => this.deleteTheme(activeTheme.name)}>🗑️</button>
                    </div>
                </div>

                <div class="color-grid mt-3">
                    ${['primary', 'secondary', 'background', 'text'].map(key => html`
                        <div class="color-input-wrap">
                            <label class="text-capitalize">${key}</label>
                            <div class="flex-between gap-2 p-1 glass-panel" style="border-radius:8px; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1);">
                                <div style="position:relative; width:32px; height:32px; overflow:hidden; border-radius:6px; border:1px solid rgba(255,255,255,0.1);">
                                    <input type="color" value="${activeTheme[key] || '#000000'}" @input=${(e) => updateTheme(key, e.target.value)} 
                                        style="position:absolute; top:-5px; left:-5px; width:42px; height:42px; cursor:pointer; border:none; padding:0; background:transparent;">
                                </div>
                                <input type="text" class="text-xs flex-1" style="background:transparent; border:none; padding:0 5px; color:#fff; font-family:'JetBrains Mono'; font-weight:600;" 
                                    value="${activeTheme[key] || ''}" @change=${(e) => updateTheme(key, e.target.value)}>
                            </div>
                        </div>
                    `)}
                </div>

                <div class="input-group mt-4">
                    <label>Default Corner Radius</label>
                    <input type="range" min="0" max="32" step="2" value="${activeTheme.borderRadius ?? 8}" @input=${(e) => updateTheme('borderRadius', parseInt(e.target.value))}>
                    <div class="text-right text-xxs opacity-06">${activeTheme.borderRadius ?? 8}px</div>
                </div>

                <div class="input-group mt-3">
                    <label>Typography (Google Font)</label>
                    <select class="w-full" @change=${(e) => updateTheme('font', e.target.value)}>
                        <optgroup label="Modern Sans">
                            <option value="Inter" ?selected=${activeTheme.font === 'Inter'}>Inter</option>
                            <option value="Outfit" ?selected=${activeTheme.font === 'Outfit'}>Outfit</option>
                            <option value="Plus Jakarta Sans" ?selected=${activeTheme.font === 'Plus Jakarta Sans'}>Plus Jakarta</option>
                            <option value="Sora" ?selected=${activeTheme.font === 'Sora'}>Sora</option>
                        </optgroup>
                        <optgroup label="Classic Sans">
                            <option value="Roboto" ?selected=${activeTheme.font === 'Roboto'}>Roboto</option>
                            <option value="Open Sans" ?selected=${activeTheme.font === 'Open Sans'}>Open Sans</option>
                            <option value="Lato" ?selected=${activeTheme.font === 'Lato'}>Lato</option>
                            <option value="Poppins" ?selected=${activeTheme.font === 'Poppins'}>Poppins</option>
                        </optgroup>
                        <optgroup label="Serif">
                            <option value="Playfair Display" ?selected=${activeTheme.font === 'Playfair Display'}>Playfair Display</option>
                            <option value="Merriweather" ?selected=${activeTheme.font === 'Merriweather'}>Merriweather</option>
                        </optgroup>
                        <optgroup label="Monospace">
                            <option value="JetBrains Mono" ?selected=${activeTheme.font === 'JetBrains Mono'}>JetBrains Mono</option>
                            <option value="Fira Code" ?selected=${activeTheme.font === 'Fira Code'}>Fira Code</option>
                        </optgroup>
                    </select>
                </div>

                <div class="mt-4 p-3 glass-panel text-xxs opacity-06">
                    Changes here affect all screens and components globally unless overridden at the component level.
                </div>
            </div>
        `;
    }

    async pickColor(key) {
        const color = await this.prompt(`Hex Color for ${key}:`, this.state.config.theme[key]);
        if (color) {
            this.state.config.theme[key] = color;
            this.update();
        }
    }

    async createTheme() {
        const name = await this.prompt("New Theme Name:");
        if (name) {
            const { config } = this.state;
            if (!config.themes) config.themes = [];
            const active = this.getActiveTheme();
            config.themes.push({ ...active, name, id: 'theme_' + Date.now() });
            config.activeTheme = name;
            this.update();
        }
    }

    async renameTheme(theme) {
        const name = await this.prompt("Rename Theme:", theme.name);
        if (name && name !== theme.name) {
            theme.name = name;
            this.state.config.activeTheme = name;
            this.update();
        }
    }

    copyTheme(theme) {
        const { config } = this.state;
        const newName = theme.name + " Copy";
        config.themes.push({ ...theme, name: newName, id: 'theme_' + Date.now() });
        config.activeTheme = newName;
        this.update();
    }

    deleteTheme(name) {
        const { config } = this.state;
        if (config.themes.length <= 1) return this.notify("Cannot delete the only theme", "warning");
        config.themes = config.themes.filter(t => t.name !== name);
        config.activeTheme = config.themes[0].name;
        this.update();
    }

    loadGoogleFont(fontName) {
        if (!fontName) return;
        const fontId = 'google-font-' + fontName.toLowerCase().replace(/\s+/g, '-');
        if (document.getElementById(fontId)) return;

        const link = document.createElement('link');
        link.id = fontId;
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${fontName.replace(/\s+/g, '+')}:wght@300;400;500;700&display=swap`;
        document.head.appendChild(link);
    }

    handleGlobalContextMenu(e) {
        const target = e.target.closest('.canvas-comp') || e.target.closest('.layer-item');
        if (target) {
            e.preventDefault();
            const targetId = target.dataset.id;
            this.showContextMenu(e, targetId);
        }
    }

    showContextMenu(e, targetId) {
        this.setState({ 
            contextMenu: { 
                visible: true, 
                x: e.clientX, 
                y: e.clientY, 
                targetId 
            } 
        });
    }

    hideContextMenu() {
        if (this.state.contextMenu.visible) {
            this.setState({ contextMenu: { ...this.state.contextMenu, visible: false } });
        }
    }

    pushHistory() {
        const snapshot = JSON.stringify(this.state.config);
        const history = this.state.history.slice(0, this.state.historyIndex + 1);
        history.push(snapshot);
        if (history.length > 50) history.shift(); // Max 50 steps
        this.setState({ history, historyIndex: history.length - 1 });
    }

    undo() {
        if (this.state.historyIndex > 0) {
            const newIndex = this.state.historyIndex - 1;
            const config = JSON.parse(this.state.history[newIndex]);
            this.setState({ config, historyIndex: newIndex });
            this.notify('Undo successful', 'info');
        }
    }

    redo() {
        if (this.state.historyIndex < this.state.history.length - 1) {
            const newIndex = this.state.historyIndex + 1;
            const config = JSON.parse(this.state.history[newIndex]);
            this.setState({ config, historyIndex: newIndex });
            this.notify('Redo successful', 'info');
        }
    }

    handleShortcuts(e) {
        if (e.ctrlKey && e.key === 'z') { e.preventDefault(); this.undo(); }
        if (e.ctrlKey && e.key === 'y') { e.preventDefault(); this.redo(); }
        if (e.key === 'Delete' && this.state.selectedComponentId) { e.preventDefault(); this.removeComponent(this.state.selectedComponentId); }
        if (e.ctrlKey && e.key === 'd' && this.state.selectedComponentId) { e.preventDefault(); this.duplicateComponent(this.state.selectedComponentId); }
    }

    duplicateComponent(id) {
        const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        const comp = BaseComponent.findInTree(activeScreen.components, id);
        if (comp) {
            const newComp = BaseComponent.cloneTree(comp, () => 'comp_' + Date.now() + '_' + Math.floor(Math.random()*1000));
            this.addComponentToScreen(newComp);
            this.notify('Component duplicated', 'success');
        }
    }

    update() {
        if (typeof super.update === 'function') super.update();
        
        // Prevent recursive history saves from history state updates themselves
        if (this._isHistoryAction) return;

        if (this._historyTimer) clearTimeout(this._historyTimer);
        this._historyTimer = setTimeout(() => {
            this._isHistoryAction = true;
            this.pushHistory();
            this._isHistoryAction = false;
        }, 800);
    }

    async fetchBridgeInfo() {
        try {
            const res = await this.api('get_bridge_info');
            if (res.success) {
                this.setState({ bridgeInfo: res.data || res.info });
            }
        } catch (e) {
            console.error("[Diagnostics] Failed to fetch bridge info:", e);
        }
    }

    async fetchData() {
        return this.guard('fetch', async () => {
            this.setState({ loading: true });
            try {
                const requests = [
                    this.api('get_blueprint_library'),
                    this.api('get_mobile_config'),
                    this.api('get_component_library')
                ];

                if (this.state.config.app_id) {
                    requests.push(this.api('get_snapshots', { id: this.state.config.app_id }));
                }

                const results = await Promise.all(requests);
                const [bpRes, configRes, compRes, snapRes] = results;
                
                // Synchronize Blueprint Library
                if (bpRes && bpRes.success && bpRes.data.library) {
                    console.log("[MobileStudio] Blueprint Library Synced:", bpRes.data.library);
                    if (!window.MobileBlueprints) window.MobileBlueprints = { layouts: {}, blueprints: [] };
                    if (bpRes.data.library.layouts) {
                        window.MobileBlueprints.layouts = { ...window.MobileBlueprints.layouts, ...bpRes.data.library.layouts };
                    }
                    if (bpRes.data.library.blueprints) {
                        const existingIds = new Set(window.MobileBlueprints.blueprints.map(b => b.id));
                        bpRes.data.library.blueprints.forEach(bp => {
                            if (!existingIds.has(bp.id)) window.MobileBlueprints.blueprints.push(bp);
                        });
                    }
                }
                // Inject Component Library into Registry
                if (compRes && compRes.success && compRes.data.components) {
                    console.log("[MobileStudio] Syncing Custom Component Plugins...");
                    compRes.data.components.forEach(comp => ComponentRegistry.register(comp));
                }

                this.setState({
                    loading: false,
                    config: (configRes && configRes.success) ? this.migrateConfig(configRes.data.config) : this.state.config,
                    snapshots: (snapRes && snapRes.success) ? snapRes.data.snapshots : []
                });
            } catch (e) {
                console.error("[MobileStudio] Sync Failure:", e);
                this.notify("Synchronization parity lost. Working in offline mode.", 'warning');
                this.setState({ loading: false });
            }
        });
    }

    async fetchEntities() {
        const res = await this.api('get_entities');
        if (res.success) {
            this.setState({ entities: res.data.entities || [] });
        }
    }

    renderSkeleton() {
        return html`
            <div class="mobile-studio skeleton-view">
                <header class="studio-header glass-panel" style="height: 48px;"></header>
                <div class="studio-layout">
                    <aside class="studio-navigator skeleton-pulse" style="width: 280px;"></aside>
                    <main class="studio-preview" style="background: var(--bg-dark); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px;">
                        <div class="spinner-elite"></div>
                        <div style="color: var(--primary-color); font-weight: 600; font-size: 0.8rem; letter-spacing: 1px; text-transform: uppercase;">Orchestrating Workspace...</div>
                        <div class="device-frame ios skeleton-pulse" style="width: 350px; height: 700px; opacity: 0.05; position: absolute;"></div>
                    </main>
                    <aside class="studio-inspector skeleton-pulse" style="width: 320px;"></aside>
                </div>
            </div>
        `;
    }

    onLibrarySearch(e) {
        e.stopPropagation();
        this.state.librarySearch = e.target.value;
        this._filterLibrary(e.target.value);
    }

    _filterLibrary(query) {
        // Use unified framework utility for main filtering
        BaseComponent.domFilter(this.container, query, {
            itemSelector: '.comp-tool[data-search-name], .blueprint-item[data-search-name]',
            attrs: ['data-search-name', 'data-search-desc']
        });

        // Custom logic for hiding empty atom groups
        this.container?.querySelectorAll('.comp-group-label').forEach(label => {
            const grid = label.nextElementSibling;
            if (grid && grid.classList.contains('component-grid')) {
                const vis = grid.querySelectorAll('.comp-tool:not([style*="display: none"])');
                label.style.display = vis.length > 0 ? '' : 'none';
                grid.style.display = vis.length > 0 ? '' : 'none';
            }
        });
    }

    afterUpdate() {
        if (this.state.librarySearch) {
            this._filterLibrary(this.state.librarySearch);
        }
    }


    render() {
        const { loading, config, error, activeScreenId, viewMode } = this.state;
        const rights = this.app?.rights || ['studio_save', 'studio_sync', 'studio_edit']; // Default rights if not provided
        
        if (loading) return this.renderSkeleton();
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        if (viewMode === 'assets') return this.renderAssetsView();
        if (viewMode === 'code') return this.renderCodeView();

        if (!config.screens || config.screens.length === 0) {
            return html`<div class="empty-state"><h3>Project Corrupted</h3><p>No screens found in this project configuration.</p></div>`;
        }

        const activeScreen = config.screens.find(s => s.id === activeScreenId) || config.screens[0];

        return html`
            <style>
                .sub-tab-btn {
                    padding: 6px 1px;
                    border-radius: 4px;
                    font-size: 0.62rem;
                    font-weight: 600;
                    letter-spacing: -0.4px;
                    cursor: pointer;
                    transition: all 0.2s var(--transition);
                    border: none;
                    background: transparent;
                    color: var(--text-dim);
                    flex: 1;
                    text-align: center;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .sub-tab-btn.active { background: var(--primary-color); color: white; }
                .comp-tool:hover { transform: translateY(-2px); border-color: var(--primary-color); background: rgba(99, 102, 241, 0.05); }
                .blueprint-item:hover { border-color: var(--primary-color); background: rgba(99, 102, 241, 0.05); }
                
                .skeleton-pulse { background: rgba(255,255,255,0.02); animation: pulse 1.5s infinite ease-in-out; }
                @keyframes pulse { 0% { opacity: 0.5; } 50% { opacity: 0.8; } 100% { opacity: 0.5; } }
                .spinner-elite { 
                    width: 40px; height: 40px; border: 3px solid rgba(99, 102, 241, 0.1); 
                    border-top-color: var(--primary-color); border-radius: 50%; 
                    animation: spin 0.8s linear infinite; 
                }
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>
            <div class="mobile-studio">
                <header class="studio-header glass-panel" style="display:flex; justify-content:space-between; align-items:center; padding: 8px 20px; border-radius: 0; border-bottom: none; border-top: none; border-left: none; border-right: none;">
                    <div style="display:flex; align-items:center; gap:25px;">
                        <h2 style="font-size: 1.1rem; margin:0; font-weight: 700; color: var(--primary-color);">${config.app_name}</h2>
                        
                        <div class="segmented-control" style="display:flex; background: rgba(0,0,0,0.2); padding: 2px; border-radius: 8px; border: 1px solid var(--glass-border);">
                            <button class="btn ${viewMode === 'studio' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px;" @click=${() => this.setViewMode('studio')}>Visual</button>
                            <button class="btn ${viewMode === 'code' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px;" @click=${() => this.setViewMode('code')}>Code</button>
                            <button class="btn ${viewMode === 'assets' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px;" @click=${() => this.setViewMode('assets')}>Assets</button>
                        </div>

                        <div class="segmented-control" style="display:flex; background: rgba(0,0,0,0.2); padding: 2px; border-radius: 8px; border: 1px solid var(--glass-border);">
                            <button class="btn ${this.state.deviceType === 'ios' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px; padding: 0 12px;" @click=${() => this.setState({deviceType: 'ios'})}>🍎 iOS</button>
                            <button class="btn ${this.state.deviceType === 'android' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px; padding: 0 12px;" @click=${() => this.setState({deviceType: 'android'})}>🤖 Android</button>
                        </div>
                    </div>

                    <div class="header-center" style="display:flex; gap:20px; align-items:center;">
                        <div class="segmented-control" style="display:flex; background: rgba(0,0,0,0.2); padding: 2px; border-radius: 8px; border: 1px solid var(--glass-border);">
                            <button class="btn ${this.state.breakpoint === 'mobile' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px; padding: 0 10px;" @click=${() => this.setState({ breakpoint: 'mobile', deviceType: 'ios' })} title="Mobile View">📱 Mobile</button>
                            <button class="btn ${this.state.breakpoint === 'tablet' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px; padding: 0 10px;" @click=${() => this.setState({ breakpoint: 'tablet', deviceType: 'android' })} title="Tablet View">📟 Tablet</button>
                            <button class="btn ${this.state.breakpoint === 'desktop' ? 'primary-btn' : 'ghost-btn'} btn-sm" style="border:none; font-size: 0.7rem; height: 28px; padding: 0 10px;" @click=${() => this.setState({ breakpoint: 'desktop' })} title="Desktop View">🖥️ Desktop</button>
                        </div>

                        <div class="zoom-controls" style="display:flex; align-items:center; gap:8px; background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 8px; border: 1px solid var(--glass-border); height: 32px;">
                            <button class="btn-icon btn-xs" style="width:20px; height:20px; font-size:0.6rem;" @click=${() => this.setState({ zoom: Math.max(25, this.state.zoom - 10) })}>➖</button>
                            <span style="font-size:0.65rem; opacity:0.8; font-weight:700; min-width:35px; text-align:center;">${this.state.zoom}%</span>
                            <button class="btn-icon btn-xs" style="width:20px; height:20px; font-size:0.6rem;" @click=${() => this.setState({ zoom: Math.min(200, this.state.zoom + 10) })}>➕</button>
                        </div>
                    </div>
                    
                    <div style="display:flex; gap:12px; align-items:center;">
                        <button class="btn ghost-btn btn-sm" @click=${() => this.exportProject()}>🚀 Export Code</button>
                        
                        <div class="segmented-control" style="display:flex; background: rgba(0,0,0,0.2); padding: 2px; border-radius: 8px; margin-right: 10px;">
                            <button class="btn ghost-btn btn-sm" style="border:none; width: 32px;" title="Undo (Ctrl+Z)" @click=${() => this.undo()} ?disabled=${this.state.historyIndex <= 0}>↩️</button>
                            <button class="btn ghost-btn btn-sm" style="border:none; width: 32px;" title="Redo (Ctrl+Y)" @click=${() => this.redo()} ?disabled=${this.state.historyIndex >= this.state.history.length - 1}>↪️</button>
                        </div>

                        <button class="btn ghost-btn btn-sm" style="font-size: 0.7rem;" @click=${() => this.setState({ viewMode: this.state.viewMode === 'live' ? 'studio' : 'live' })}>
                            ${this.state.viewMode === 'live' ? '🎨 Studio' : '▶️ Preview'}
                        </button>

                        <button class="btn primary-btn btn-sm px-4" style="font-size: 0.7rem; font-weight: 600;" @click=${() => this.saveConfig()}>Sync & Save</button>
                    </div>
                </header>

                <div class="studio-layout">
                    <!-- Left: Navigator -->
                    <aside class="studio-navigator">
                        <div class="nav-section">
                            <h4>App Screens</h4>
                            <div class="screen-list">
                                ${(config.screens || []).map(s => html`
                                    <div class="screen-item ${s.id === activeScreenId ? 'active' : ''}" 
                                        @click=${() => this.setState({ activeScreenId: s.id })}>
                                        <div class="screen-icon">${this.getScreenIcon(s.type)}</div>
                                        <div class="screen-info">
                                            <div class="screen-name">${s.title}</div>
                                            <div class="screen-type">${s.type}</div>
                                        </div>
                                    </div>
                                `)}
                            </div>
                            <button class="btn ghost-btn btn-sm mt-3" style="width:100%" @click=${() => this.addScreen()}>+ Add Screen</button>
                        </div>

                        <div class="nav-section mt-4">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <h4 style="margin:0;">Library</h4>
                                <div class="segmented-control" style="display:flex; background: rgba(0,0,0,0.2); padding: 2px; border-radius: 6px;">
                                    <button class="btn ${this.state.libraryMode !== 'blueprints' && this.state.libraryMode !== 'snapshots' && this.state.libraryMode !== 'data' && this.state.libraryMode !== 'symbols' ? 'primary-btn' : 'ghost-btn'} btn-xs" style="font-size:0.6rem; padding: 2px 8px;" @click=${() => this.setState({ libraryMode: 'components' })}>Atoms</button>
                                    <button class="btn ${this.state.libraryMode === 'blueprints' ? 'primary-btn' : 'ghost-btn'} btn-xs" style="font-size:0.6rem; padding: 2px 8px;" @click=${() => this.setState({ libraryMode: 'blueprints' })}>Blueprints</button>
                                    <button class="btn ${this.state.libraryMode === 'symbols' ? 'primary-btn' : 'ghost-btn'} btn-xs" style="font-size:0.6rem; padding: 2px 8px;" @click=${() => this.setState({ libraryMode: 'symbols' })}>Symbols</button>
                                    <button class="btn ${this.state.libraryMode === 'data' ? 'primary-btn' : 'ghost-btn'} btn-xs" style="font-size:0.6rem; padding: 2px 8px;" @click=${() => this.setState({ libraryMode: 'data' })}>Data</button>
                                </div>
                            </div>
                            <div class="search-bar mb-3" style="position:relative;">
                                <input type="text" id="library-search-input" placeholder="Search components..." 
                                    value="${this.state.librarySearch || ''}"
                                    style="width:100%; padding:6px 10px 6px 30px; font-size:0.7rem; border-radius:6px; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); color:var(--text-main);"
                                    @input=${(e) => this.onLibrarySearch(e)}>
                                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:0.7rem; opacity:0.5;">🔍</span>
                            </div>

                            ${this.state.libraryMode === 'snapshots' ? html`
                                <div class="snapshots-list fade-in">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                        <button class="btn btn-xs ghost-btn" style="width:100%; border:1px dashed var(--glass-border);" @click=${() => this.createSnapshot()}>➕ New Snapshot</button>
                                    </div>
                                    ${(this.state.snapshots || []).map(s => html`
                                        <div class="snapshot-item glass-panel p-2 mb-2" style="border-radius:8px; font-size:0.7rem; cursor:default;">
                                            <div style="font-weight:600; color:#fff; margin-bottom:2px;">${s.meta.name}</div>
                                            <div style="display:flex; justify-content:space-between; opacity:0.6;">
                                                <span>${s.meta.date}</span>
                                                <span class="pointer" style="color:var(--primary-color);" @click=${() => this.restoreSnapshot(s.file)}>Restore</span>
                                            </div>
                                        </div>
                                    `)}
                                </div>
                            ` : (this.state.libraryMode === 'blueprints' ? html`
                                <div class="blueprint-list">
                                    ${this.getBlueprints().map(bp => html`
                                        <div class="blueprint-item glass-panel p-2 mb-2 pointer" 
                                            data-search-name="${bp.name.toLowerCase()}"
                                            data-search-desc="${(bp.description || '').toLowerCase()}"
                                            @click=${() => this.addBlueprintToScreen(bp)} 
                                            style="border: 1px solid rgba(255,255,255,0.05); transition: all 0.2s var(--transition);">
                                            <div style="font-weight:bold; font-size:0.75rem; color:var(--primary-color);">${bp.name}</div>
                                            <div style="font-size:0.6rem; opacity:0.6;">${bp.description}</div>
                                        </div>
                                    `)}
                                </div>
                            ` : (this.state.libraryMode === 'data' ? this.renderDataManager() : (this.state.libraryMode === 'symbols' ? this.renderSymbolsLibrary() : html`
                                ${this.getComponentLibrary().map(group => {
                                    return html`
                                        <div class="comp-group-label mt-3 mb-1" style="font-size: 0.55rem; text-transform: uppercase; opacity: 0.5; letter-spacing: 1px; font-weight: bold;">${group.group}</div>
                                        <div class="component-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            ${group.items.map(c => html`
                                                <div class="comp-tool glass-panel" draggable="true"
                                                    data-search-name="${c.name.toLowerCase()}"
                                                    @dragstart=${(e) => this.onDragStart(e, c)}
                                                    @click=${() => this.addComponentToScreen(c)}
                                                    style="padding: 10px; cursor: pointer; text-align: center; border: 1px solid rgba(255,255,255,0.05); transition: all 0.2s var(--transition);">
                                                    <div style="font-size: 1.2rem;">${c.icon}</div>
                                                    <div style="font-size: 0.6rem; margin-top: 4px; font-weight: 500;">${c.name}</div>
                                                </div>
                                            `)}
                                        </div>
                                    `;
                                })}
                            `)))}
                        </div>
                    </aside>

                    <!-- Center: Preview -->
                    <main class="studio-preview preview-canvas">

                        <div class="zoom-wrapper" style="transform: scale(${this.state.zoom / 100}); transform-origin: top center; transition: transform 0.3s var(--spring); flex-shrink: 0;">
                            <div class="device-frame ${this.state.deviceType} ${this.state.breakpoint}" id="device-drop-target"
                                @dragenter=${(e) => e.preventDefault()}
                                @dragover=${(e) => { e.dataTransfer.dropEffect = 'copy'; const t = e.target.closest('.device-frame'); if (t) t.classList.add('drag-over'); }}
                                @dragleave=${(e) => { const t = e.target.closest('.device-frame'); if (t && !t.contains(e.relatedTarget)) t.classList.remove('drag-over'); }}
                                @drop=${(e) => this.onDrop(e)}>
                                <div class="device-screen" style="background: ${this.getActiveTheme().background || '#ffffff'}; border-radius: 40px; font-family: '${this.getActiveTheme().font || 'Outfit'}', sans-serif; --heading-font: '${this.getActiveTheme().font || 'Outfit'}', sans-serif; --heading-weight: ${this.getActiveTheme().headingWeight || '700'}; --corner-radius: ${this.getActiveTheme().borderRadius || 8}px; overflow: hidden; display: flex; flex-direction: column;">
                                    <div class="mock-status-bar">
                                        <span>9:41</span>
                                        <span>📶 🔋</span>
                                    </div>
                                    <div class="mock-app-bar" style="background: ${this.getActiveTheme().primary}; color: #fff; font-family: var(--heading-font);">
                                        ${activeScreen.title}
                                    </div>
                                    <div class="mock-content" id="canvas-content">
                                        ${this.renderMockContent(activeScreen)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>

                    <!-- Right: Inspector -->
                    <aside class="studio-inspector">
                        <div class="tabs mb-4">
                            <button class="tab-btn ${this.state.inspectorTab === 'theme' ? 'active' : ''}" style="flex:1;" @click=${() => this.setState({ inspectorTab: 'theme' })}>Theme</button>
                            <button class="tab-btn ${this.state.inspectorTab === 'props' ? 'active' : ''}" style="flex:1;" @click=${() => this.setState({ inspectorTab: 'props' })}>Props</button>
                            <button class="tab-btn ${this.state.inspectorTab === 'layers' ? 'active' : ''}" style="flex:1;" @click=${() => this.setState({ inspectorTab: 'layers' })}>Layers</button>
                            <button class="tab-btn ${this.state.inspectorTab === 'animations' ? 'active' : ''}" style="flex:1;" @click=${() => this.setState({ inspectorTab: 'animations' })}>Anims</button>
                            <button class="tab-btn ${this.state.inspectorTab === 'actions' ? 'active' : ''}" style="flex:1;" @click=${() => this.setState({ inspectorTab: 'actions' })}>Actions</button>
                        </div>

                        ${this.state.inspectorTab === 'theme' ? this.renderThemeInspector() : ''}
                        ${this.state.inspectorTab === 'props' ? this.renderPropertiesInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'layers' ? this.renderLayersInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'animations' ? this.renderAnimationsInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'actions' ? this.renderActionsInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'state' ? this.renderStateInspector() : ''}
                    </aside>
                </div>

                <!-- Pro Status Bar -->
                <footer class="studio-footer-inner flex-between">
                    <div class="flex-center gap-4">
                        <span class="flex-center gap-2"><span class="text-success">●</span> SYSTEM READY</span>
                        <span class="opacity-06">CONTEXT: <strong class="text-primary">${this.state.activeScreenId.toUpperCase()}</strong></span>
                        <span class="opacity-06">SELECTED: <strong class="text-main">${this.state.selectedComponentId || 'NONE'}</strong></span>
                    </div>
                    <div class="flex-center gap-4">
                        <span class="flex-center gap-2">VIEWPORT: <strong class="text-main">${this.state.breakpoint.toUpperCase()}</strong></span>
                        <div class="flex-center gap-2" style="background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
                            <button class="btn-icon btn-xxs" @click=${() => this.setState({ zoom: Math.max(25, this.state.zoom - 10) })}>➖</button>
                            <span style="min-width: 40px; text-align: center;">${this.state.zoom}%</span>
                            <button class="btn-icon btn-xxs" @click=${() => this.setState({ zoom: Math.min(200, this.state.zoom + 10) })}>➕</button>
                            <button class="btn-icon btn-xxs ml-2" title="Reset Zoom" @click=${() => this.setState({ zoom: 100 })}>🎯</button>
                        </div>
                        <span class="opacity-06">HISTORY: <strong class="text-main">${this.state.historyIndex + 1} / ${this.state.history.length}</strong></span>
                    </div>
                </footer>

                ${this.state.contextMenu.visible ? this.renderContextMenu() : ''}
            </div>
        `;
    }

    renderContextMenu() {
        const { x, y, targetId } = this.state.contextMenu;
        const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        const comp = this.findComponentById(activeScreen.components, targetId);
        
        return html`
            <div class="glass-panel ctx-menu fade-in" style="position: fixed; top: ${y}px; left: ${x}px; z-index: 9999;">
                <div class="menu-item" @click=${() => this.duplicateComponent(targetId)}>📦 Duplicate <span class="float-right text-xxs opacity-06">Ctrl+D</span></div>
                <div class="menu-item" @click=${() => this.copyStyles(targetId)}>🎨 Copy Styles</div>
                <div class="menu-item" @click=${() => this.pasteStyles(targetId)}>📋 Paste Styles</div>
                <div class="menu-divider"></div>
                <div class="menu-item" @click=${() => this.convertToSymbol(targetId)}>💠 Convert to Symbol</div>
                <div class="menu-divider"></div>
                <div class="menu-item danger" @click=${() => this.removeComponent(targetId)}>🗑️ Delete <span class="float-right text-xxs opacity-06">Del</span></div>
            </div>
        `;
    }

    renderLayersInspector(activeScreen) {
        return html`
            <div class="inspector-section fade-in">
                <div class="header-row mb-3">
                    <h3 style="margin:0; font-size: 0.9rem;">Component Tree</h3>
                    <span class="badge primary">${activeScreen.components?.length || 0} ITEMS</span>
                </div>
                <div class="layer-tree">
                    ${activeScreen.components && activeScreen.components.length > 0
                        ? this.renderComponentTree(activeScreen.components)
                        : html`<div class="text-dim text-center py-4" style="font-size:0.7rem;">No components on this screen.</div>`
                    }
                </div>
            </div>
        `;
    }

    renderComponentTree(components, depth = 0) {
        return components.map(c => {
            const selected = this.state.selectedComponentId === c.id;
            return html`
                <div class="layer-item layer-depth-${depth} ${selected ? 'selected' : ''}" 
                    data-id="${c.id}"
                    @click=${() => this.setState({ selectedComponentId: c.id, inspectorTab: 'props' })}
                    style="${c.props?.hidden ? 'opacity: 0.5;' : ''}">
                    <span class="layer-visibility pointer flex-center" @click=${(e) => { e.stopPropagation(); c.props.hidden = !c.props.hidden; this.update(); }}>
                        ${c.props?.hidden ? '👁️‍🗨️' : '👁️'}
                    </span>
                    <span class="layer-icon opacity-07 flex-center">${this.getComponentIcon(c.type)}</span>
                    <span class="layer-name flex-1">${c.props?.text || c.name || c.type}</span>
                    <div class="layer-actions flex-center gap-1">
                        <button class="btn-icon btn-xxs" @click=${(e) => { e.stopPropagation(); this.duplicateComponent(c.id); }}>📦</button>
                    </div>
                </div>
                ${c.children && c.children.length > 0 ? this.renderComponentTree(c.children, depth + 1) : ''}
            `;
        });
    }

    getComponentIcon(type) {
        const icons = {
            row: '↔️', column: '↕️', container: '📦', text: '📝', button: '🔘', 
            image: '🖼️', card: '🃏', badge: '🏷️', input: '⌨️', list: '📜',
            spacer: '📏', divider: '➖', switch: '🔘', progress: '⏳',
            video_player: '🎬', story_circle: '⭕', social_post: '📱',
            product_card: '🛍️', price_tag: '🏷️', wallet_card: '👛'
        };
        return icons[type] || '🧩';
    }

    renderPropertiesInspector(activeScreen) {
        const { config, selectedComponentId } = this.state;
        const selectedComp = this.findComponentById(activeScreen?.components || [], selectedComponentId);

        if (selectedComp) {
            return html`
                <div class="inspector-section fade-in">
                    <h3>Component Settings</h3>
                    <div class="text-dim mb-3" style="font-size: 0.7rem; text-transform: uppercase;">Type: ${selectedComp.type}</div>
                    
                    ${selectedComp.type !== 'divider' && selectedComp.type !== 'spacer' ? html`
                    <div class="input-group">
                        <label class="label-flex">
                            <span>Label / Text Content</span>
                            <button class="btn-icon btn-xs ${selectedComp.props.text?.includes('{{') ? 'active-bind' : ''}" title="Bind to Variable" @click=${() => this.openVariablePicker(selectedComp, 'text')}>🔗</button>
                        </label>
                        <div class="flex-between gap-2">
                            <input type="text" class="flex-1 ${selectedComp.props.text?.includes('{{') ? 'bound-input' : ''}" .value="${selectedComp.props.text || ''}" 
                                @input=${(e) => { selectedComp.props.text = e.target.value; this.update(); }}>
                            <button class="btn ghost-btn btn-sm" title="Pick Icon" @click=${() => this.openIconPicker(selectedComp)}>${selectedComp.props.icon ? html`✨` : '➕ Icon'}</button>
                        </div>
                        ${selectedComp.props.icon ? html`<div class="mt-1 text-xxs text-primary">Active Icon: ${selectedComp.props.icon} <span class="pointer text-danger ml-2" @click=${() => { delete selectedComp.props.icon; this.update(); }}>remove</span></div>` : ''}
                    </div>
                    ` : ''}
                    
                    ${selectedComp.type === 'image' ? html`
                    <div class="input-group mt-3">
                        <label style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Image Source (URL)</span>
                            <button class="btn-icon btn-xs ${selectedComp.props.src?.includes('{{') ? 'active-bind' : ''}" title="Bind to Variable" @click=${() => this.openVariablePicker(selectedComp, 'src')}>🔗</button>
                        </label>
                        <input type="text" .value="${selectedComp.props.src || ''}" placeholder="https://..." 
                            @input=${(e) => { selectedComp.props.src = e.target.value; this.update(); }}
                            class="${selectedComp.props.src?.includes('{{') ? 'bound-input' : ''}">
                    </div>
                    ` : ''}

                    ${selectedComp.type === 'spacer' ? html`
                    <div class="input-group mt-3">
                        <label>Height (px)</label>
                        <input type="number" value="${selectedComp.props.height || 20}" @input=${(e) => { selectedComp.props.height = parseInt(e.target.value); this.update(); }}>
                    </div>
                    ` : ''}

                    ${selectedComp.type === 'slider' ? html`
                    <div class="input-group mt-3 flex-between gap-2">
                        <div class="flex-1">
                            <label>Min</label>
                            <input type="number" value="${selectedComp.props.min || 0}" @input=${(e) => { selectedComp.props.min = parseInt(e.target.value); this.update(); }}>
                        </div>
                        <div class="flex-1">
                            <label>Max</label>
                            <input type="number" value="${selectedComp.props.max || 100}" @input=${(e) => { selectedComp.props.max = parseInt(e.target.value); this.update(); }}>
                        </div>
                    </div>
                    ` : ''}

                    <div class="input-group mt-3">
                        <label>Platform Visibility</label>
                        <select @change=${(e) => { selectedComp.props.visibility = e.target.value; this.update(); }}>
                            <option value="always" ${selectedComp.props.visibility === 'always' ? 'selected' : ''}>🌍 All Platforms</option>
                            <option value="ios_only" ${selectedComp.props.visibility === 'ios_only' ? 'selected' : ''}>🍎 iOS Only</option>
                            <option value="android_only" ${selectedComp.props.visibility === 'android_only' ? 'selected' : ''}>🤖 Android Only</option>
                        </select>
                    </div>

                    <!-- Theme Scope Override -->
                    ${['row', 'column', 'container', 'card', 'list', 'glass', 'gradient', 'shadow', 'accordion', 'expansion_tile'].includes(selectedComp.type) ? html`
                    <div class="input-group mt-3">
                        <label>Theme Scope Override</label>
                        <select @change=${(e) => { selectedComp.props.theme = e.target.value === 'none' ? undefined : e.target.value; this.update(); }}>
                            <option value="none">Inherit (Parent/Global)</option>
                            ${(config.themes || []).map(t => html`
                                <option value="${t.name}" ${selectedComp.props.theme === t.name ? 'selected' : ''}>${t.name}</option>
                            `)}
                        </select>
                        <div class="text-dim mt-1" style="font-size:0.6rem;">This section and its children will follow the <strong>${selectedComp.props.theme || 'global'}</strong> design system.</div>
                    </div>
                    ` : ''}

                    <div class="style-group mt-4 p-3 glass-panel">
                        <label class="section-title-sm opacity-06">Layout & Styling</label>
                        <div class="grid-2 gap-3 mt-2">
                            <div class="input-group">
                                <label>Padding (px)</label>
                                <input type="number" value="${selectedComp.props.padding || 0}" @input=${(e) => { selectedComp.props.padding = parseInt(e.target.value); this.update(); }}>
                            </div>
                            <div class="input-group">
                                <label>Margin (px)</label>
                                <input type="number" value="${selectedComp.props.margin || 0}" @input=${(e) => { selectedComp.props.margin = parseInt(e.target.value); this.update(); }}>
                            </div>
                            <div class="input-group">
                                <label>Radius (px)</label>
                                <input type="number" value="${selectedComp.props.borderRadius || (selectedComp.type === 'button' ? 8 : 0)}" @input=${(e) => { selectedComp.props.borderRadius = parseInt(e.target.value); this.update(); }}>
                            </div>
                            <div class="input-group">
                                <label>Elevation</label>
                                <input type="number" value="${selectedComp.props.elevation || 0}" min="0" max="24" @input=${(e) => { selectedComp.props.elevation = parseInt(e.target.value); this.update(); }}>
                            </div>
                        </div>
                    </div>

                    <div class="input-group mt-4">
                        <label>Smart Data Binding</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <select style="flex: 1;" @change=${(e) => { selectedComp.props.dataKey = e.target.value; this.update(); }}>
                                <option value="">-- No Binding (Static) --</option>
                                ${(this.state.entities || []).map(entity => html`
                                    <option value="${entity}" ${selectedComp.props.dataKey === entity ? 'selected' : ''}>${entity}</option>
                                `)}
                            </select>
                            <button class="btn btn-icon btn-sm" title="Refresh Entities" @click=${() => this.fetchEntities()}>🔄</button>
                        </div>
                        <div class="text-dim mt-1" style="font-size: 0.6rem;">Connect this ${selectedComp.type} to SPP Entity data fields.</div>
                    </div>

                    <div class="input-group mt-4">
                        <label class="label-flex">
                            <span>Conditional Visibility</span>
                            <button class="btn-icon btn-xs ${selectedComp.props.visibleIf ? 'active-bind' : ''}" @click=${async () => {
                                const expr = await this.prompt("Show if expression (e.g. state.isLoggedIn == 'true'):", selectedComp.props.visibleIf || '');
                                selectedComp.props.visibleIf = expr;
                                this.update();
                            }}>👁️</button>
                        </label>
                        <input type="text" placeholder="No condition (Always visible)" .value="${selectedComp.props.visibleIf || ''}" 
                            @input=${(e) => { selectedComp.props.visibleIf = e.target.value; this.update(); }}
                            class="${selectedComp.props.visibleIf ? 'bound-input' : ''}">
                        <div class="text-xxs opacity-06 mt-1">Leave empty for always visible.</div>
                    </div>

                    <div class="reorder-controls mt-4 p-3 glass-panel" style="display:flex; justify-content:space-between; align-items:center; border: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size:0.7rem; font-weight:bold; opacity:0.6;">REORDER</span>
                        <div style="display:flex; gap:8px;">
                            <button class="btn ghost-btn btn-sm" @click=${() => this.moveComponent(selectedComponentId, -1)}>↑ Up</button>
                            <button class="btn ghost-btn btn-sm" @click=${() => this.moveComponent(selectedComponentId, 1)}>↓ Down</button>
                            <button class="btn btn-sm" style="background:rgba(255,50,50,0.2); color:#ff5555; border:1px solid rgba(255,50,50,0.3);" @click=${() => this.removeComponent(selectedComponentId)}>🗑️ Delete</button>
                        </div>
                    </div>
                </div>
            `;
        }

        return html`
            <div class="inspector-section">
                <h3>Design Tokens</h3>
                <div class="tokens-list mt-3">
                    <button class="btn ghost-btn btn-sm mb-3" @click=${() => this.addDesignToken()}>+ New Token</button>
                    ${Object.entries(this.state.config.tokens || {}).map(([name, style]) => html`
                        <div class="token-item glass-panel p-2 mb-2" style="border-left: 3px solid var(--primary-color);">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:0.75rem;">${name}</strong>
                                <button class="btn-icon" @click=${() => { delete this.state.config.tokens[name]; this.update(); }}>✕</button>
                            </div>
                            <input type="text" value="${style}" style="width:100%; font-size:0.65rem; background:transparent; border:none; color:#aaa;" @change=${(e) => { this.state.config.tokens[name] = e.target.value; this.update(); }}>
                        </div>
                    `)}
                </div>
            </div>

            <div class="inspector-section">
                <h3>Project Settings</h3>
                <div class="input-group">
                    <label>App Name</label>
                    <input type="text" value="${config.app_name}" @input=${(e) => { 
                        this.state.config.app_name = e.target.value; 
                        this.setState({ config: { ...this.state.config } }); 
                    }}>
                </div>
            </div>


            <div class="inspector-section">
                <h3>Screen Properties</h3>
                <div class="input-group">
                    <label>Title</label>
                    <input type="text" value="${activeScreen.title}" @input=${(e) => { activeScreen.title = e.target.value; this.update(); }}>
                </div>
                <div class="input-group">
                    <label>Screen Type</label>
                    <select @change=${(e) => { activeScreen.type = e.target.value; this.update(); }}>
                        <option value="dashboard" ${activeScreen.type === 'dashboard' ? 'selected' : ''}>🏠 Dashboard</option>
                        <option value="form" ${activeScreen.type === 'form' ? 'selected' : ''}>📝 Form</option>
                        <option value="list" ${activeScreen.type === 'list' ? 'selected' : ''}>📋 List</option>
                        <option value="details" ${activeScreen.type === 'details' ? 'selected' : ''}>👤 Details</option>
                        <option value="custom" ${activeScreen.type === 'custom' ? 'selected' : ''}>✨ Custom</option>
                    </select>
                </div>
                <div class="mt-4">
                    <label style="font-size: 0.6rem; text-transform: uppercase; opacity: 0.6;">Apply Layout Template</label>
                    <div class="template-grid mt-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <button class="btn ghost-btn btn-sm" @click=${() => this.applyTemplate(activeScreen, 'dashboard')}>🏠 Dashboard</button>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.applyTemplate(activeScreen, 'form')}>📝 Form</button>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.applyTemplate(activeScreen, 'list')}>📋 List</button>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.applyTemplate(activeScreen, 'details')}>👤 Details</button>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.applyTemplate(activeScreen, 'split_view')}>🌓 Split View</button>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.applyTemplate(activeScreen, 'centered_card')}>🎯 Centered</button>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.applyTemplate(activeScreen, 'bottom_action')}>⚓ Bottom Action</button>
                    </div>
                </div>
            </div>
        `;
    }

    renderActionsInspector(activeScreen) {
        const { selectedComponentId, config } = this.state;
        const selectedComp = this.findComponentById(activeScreen?.components || [], selectedComponentId);

        if (!selectedComp) return html`
            <div class="inspector-section">
                <h3>Action Pipelines</h3>
                <p class="text-dim" style="font-size: 0.7rem;">Select a component on the canvas to configure its multi-step logic flows.</p>
            </div>`;

        return html`
            <div class="inspector-section">
                <h3>Actions: ${selectedComp.type}</h3>
                <div class="action-list mt-3">
                    <button class="btn primary-btn btn-sm mb-4" @click=${() => this.openActionBuilder(selectedComp)}>+ New Pipeline</button>
                    
                    ${(selectedComp.actions || []).map((action, idx) => html`
                        <div class="action-pipeline glass-panel p-3 mb-3" style="border-left: 4px solid var(--primary-color); background: rgba(255,255,255,0.02);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <strong style="font-size: 0.8rem; color: var(--primary-color);">${action.trigger}</strong>
                                <button class="btn-icon btn-xs" @click=${() => { selectedComp.actions.splice(idx, 1); this.update(); }}>✕</button>
                            </div>
                            
                            <div class="steps-flow" style="display:flex; flex-direction:column; gap:8px;">
                                ${(action.steps || [{type: action.type, target: action.target}]).map((step, sIdx) => html`
                                    <div class="step-item p-2" style="background: rgba(0,0,0,0.2); border-radius: 6px; font-size: 0.7rem; position:relative; ${sIdx > 0 ? 'margin-top:5px;' : ''}">
                                        ${sIdx > 0 ? html`<div style="position:absolute; top:-12px; left:15px; color:var(--primary-color); opacity:0.5;">↓</div>` : ''}
                                        <div style="display:flex; justify-content:space-between;">
                                            <span>Step ${sIdx + 1}: <strong>${step.type}</strong></span>
                                            <span style="opacity:0.5;">${step.target || ''}</span>
                                        </div>
                                    </div>
                                `)}
                            </div>
                        </div>
                    `)}
                </div>
            </div>
        `;
    }

    renderDataInspector(activeScreen) {
        const { entities = [] } = this.state;
        return html`
            <div class="inspector-section">
                <h3>Data Binding</h3>
                <div class="input-group">
                    <label>Primary Source</label>
                    <select @change=${(e) => { activeScreen.mapping = e.target.value; this.update(); }}>
                        <option value="">Static Content</option>
                        ${entities.map(e => html`<option value="Entity:${e}" ?selected="${activeScreen.mapping === 'Entity:' + e}">${e} Entity</option>`)}
                    </select>
                </div>
                <div class="mt-4">
                    <button class="btn ghost-btn btn-sm" @click=${() => this.fetchEntities()}>🔄 Refresh Entities</button>
                </div>
            </div>
        `;
    }

    getActiveTheme() {
        const { config } = this.state;
        if (!config || !config.themes || config.themes.length === 0) {
            const fallback = (config && config.theme) ? config.theme : { primary: '#6366f1', secondary: '#4f46e5', background: '#ffffff', surface: '#f8fafc', text: '#1e293b', font: 'Outfit', borderRadius: 12 };
            if (!fallback.name) fallback.name = 'Default';
            return fallback;
        }
        return config.themes.find(t => t.name === config.activeTheme) || config.themes[0];
    }

    migrateConfig(config) {
        if (!config.themes) {
            const defaultTheme = config.theme || { primary: '#6366f1', secondary: '#4f46e5', background: '#ffffff', surface: '#f8fafc', text: '#1e293b', font: 'Outfit', borderRadius: 12 };
            if (defaultTheme.radius && !defaultTheme.borderRadius) defaultTheme.borderRadius = defaultTheme.radius;
            defaultTheme.name = 'Default';
            config.themes = [defaultTheme];
            config.activeTheme = 'Default';
            delete config.theme;
        } else {
            // Ensure all themes have borderRadius if they only have legacy radius
            config.themes.forEach(t => {
                if (t.radius && !t.borderRadius) t.borderRadius = t.radius;
            });
        }
        return config;
    }

    async addTheme() {
        const name = await this.prompt("Enter a unique name for your new theme:");
        if (!name) return;
        
        const config = { ...this.state.config };
        if (config.themes.find(t => t.name === name)) {
            this.notify(`A theme with the name '${name}' already exists.`, 'error');
            return;
        }

        const newTheme = { ...this.getActiveTheme(), name };
        config.themes = [...(config.themes || []), newTheme];
        config.activeTheme = name;
        this.setState({ config });
        this.notify(`Theme '${name}' created.`, 'success');
    }

    async duplicateActiveTheme() {
        const active = this.getActiveTheme();
        const name = await this.prompt("Enter name for the duplicated theme:", active.name + " Copy");
        if (!name) return;
        
        const config = { ...this.state.config };
        if (config.themes.find(t => t.name === name)) {
            this.notify(`A theme with the name '${name}' already exists.`, 'error');
            return;
        }

        const newTheme = { ...active, name };
        config.themes = [...(config.themes || []), newTheme];
        config.activeTheme = name;
        this.setState({ config });
        this.notify(`Theme duplicated as '${name}'.`, 'success');
    }

    async renameActiveTheme() {
        const active = this.getActiveTheme();
        const oldName = active.name;
        const newName = await this.prompt("Enter new name for this theme:", oldName);
        
        if (!newName || newName === oldName) return;

        const config = { ...this.state.config };
        if (config.themes.find(t => t.name === newName)) {
            this.notify(`A theme with the name '${newName}' already exists.`, 'error');
            return;
        }

        active.name = newName;
        if (config.activeTheme === oldName) {
            config.activeTheme = newName;
        }
        
        this.setState({ config });
        this.notify(`Theme '${oldName}' renamed to '${newName}'.`, 'success');
    }

    async saveThemeAsPreset() {
        const name = await this.prompt("Enter Name for this Design System Preset:");
        if (!name) return;
        const { config } = this.state;
        const res = await this.api('save_theme_preset', { themes: config.themes, name });
        if (res.success) {
            this.loadThemePresets();
        }
    }

    async loadThemePresets() {
        const res = await this.api('get_theme_presets');
        if (res.success) {
            this.setState({ themePresets: res.data.presets });
        }
    }

    applyThemePreset(preset) {
        if (!confirm(`Apply Design System '${preset.name}'? This will replace all current project themes.`)) return;
        const config = { ...this.state.config };
        config.themes = preset.themes;
        config.activeTheme = preset.themes[0].name;
        this.setState({ config });
        this.notify(`Applied Design System: ${preset.name}`, 'success');
    }

    renderThemeEditor() {
        const { config } = this.state;
        const theme = this.getActiveTheme();
        
        return html`
            <div class="inspector-section fade-in">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h3 style="margin:0;">Design System</h3>
                    <div style="display:flex; gap:5px;">
                        <button class="btn ghost-btn btn-xs" title="Rename Theme" @click=${() => this.renameActiveTheme()}>✏️</button>
                        <button class="btn ghost-btn btn-xs" title="Duplicate Theme" @click=${() => this.duplicateActiveTheme()}>📑</button>
                        <button class="btn primary-btn btn-xs" title="Add Theme" @click=${() => this.addTheme()}>+ New</button>
                    </div>
                </div>
                
                <div class="input-group mb-4">
                    <label>Active Palette</label>
                    <select @change=${(e) => { config.activeTheme = e.target.value; this.update(); }}>
                        ${(config.themes || []).map(t => html`
                            <option value="${t.name}" ${config.activeTheme === t.name ? 'selected' : ''}>${t.name}</option>
                        `)}
                    </select>
                </div>

                <div class="text-dim mb-4" style="font-size: 0.65rem;">Modifying properties for <strong style="color:var(--primary-color);">${theme.name}</strong>.</div>
                
                <div class="input-group">
                    <label>Primary Color</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="color" value="${theme.primary || '#6366f1'}" @input=${(e) => { theme.primary = e.target.value; this.update(); }} style="width:40px; height:40px; border:none; background:none; cursor:pointer;">
                        <input type="text" value="${theme.primary || '#6366f1'}" @input=${(e) => { theme.primary = e.target.value; this.update(); }} style="flex:1;">
                    </div>
                </div>

                <div class="input-group mt-3">
                    <label>Background</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="color" value="${theme.background || '#ffffff'}" @input=${(e) => { theme.background = e.target.value; this.update(); }} style="width:40px; height:40px; border:none; background:none; cursor:pointer;">
                        <input type="text" value="${theme.background || '#ffffff'}" @input=${(e) => { theme.background = e.target.value; this.update(); }} style="flex:1;">
                    </div>
                </div>

                <div class="input-group mt-3">
                    <label>Global Border Radius</label>
                    <input type="range" min="0" max="40" value="${theme.borderRadius || 12}" @input=${(e) => { theme.borderRadius = parseInt(e.target.value); this.update(); }}>
                    <div class="text-dim text-right" style="font-size:0.6rem;">${theme.borderRadius}px</div>
                </div>

                <div class="input-group mt-3">
                    <label>Heading Typography</label>
                    <select @change=${(e) => { theme.headingFont = e.target.value; this.update(); }}>
                        <option value="Outfit" ${theme.headingFont === 'Outfit' ? 'selected' : ''}>Outfit (Modern)</option>
                        <option value="Inter" ${theme.headingFont === 'Inter' ? 'selected' : ''}>Inter (UI)</option>
                        <option value="Playfair Display" ${theme.headingFont === 'Playfair Display' ? 'selected' : ''}>Playfair (Elegant Serif)</option>
                        <option value="Montserrat" ${theme.headingFont === 'Montserrat' ? 'selected' : ''}>Montserrat (Bold)</option>
                        <option value="Space Grotesk" ${theme.headingFont === 'Space Grotesk' ? 'selected' : ''}>Space Grotesk (Tech)</option>
                        <option value="Lexend" ${theme.headingFont === 'Lexend' ? 'selected' : ''}>Lexend (Readable)</option>
                        <option value="Syne" ${theme.headingFont === 'Syne' ? 'selected' : ''}>Syne (Artistic)</option>
                        <option value="Cinzel" ${theme.headingFont === 'Cinzel' ? 'selected' : ''}>Cinzel (Classical)</option>
                    </select>
                </div>

                <div class="input-group mt-2">
                    <label>Heading Weight</label>
                    <select @change=${(e) => { theme.headingWeight = e.target.value; this.update(); }}>
                        <option value="300" ${theme.headingWeight === '300' ? 'selected' : ''}>Light</option>
                        <option value="400" ${theme.headingWeight === '400' ? 'selected' : ''}>Regular</option>
                        <option value="600" ${theme.headingWeight === '600' ? 'selected' : ''}>Semi-Bold</option>
                        <option value="700" ${theme.headingWeight === '700' ? 'selected' : ''}>Bold</option>
                        <option value="800" ${theme.headingWeight === '800' ? 'selected' : ''}>Ultra-Bold</option>
                    </select>
                </div>

                <div class="input-group mt-2">
                    <label>Body Typography</label>
                    <select @change=${(e) => { theme.bodyFont = e.target.value; this.update(); }}>
                        <option value="Outfit" ${theme.bodyFont === 'Outfit' ? 'selected' : ''}>Outfit (Default)</option>
                        <option value="Inter" ${theme.bodyFont === 'Inter' ? 'selected' : ''}>Inter (Readable)</option>
                        <option value="Space Grotesk" ${theme.bodyFont === 'Space Grotesk' ? 'selected' : ''}>Space Grotesk (Clean)</option>
                        <option value="Lato" ${theme.bodyFont === 'Lato' ? 'selected' : ''}>Lato (Standard)</option>
                        <option value="Nunito" ${theme.bodyFont === 'Nunito' ? 'selected' : ''}>Nunito (Soft)</option>
                        <option value="JetBrains Mono" ${theme.bodyFont === 'JetBrains Mono' ? 'selected' : ''}>JetBrains (Technical)</option>
                        <option value="Space Mono" ${theme.bodyFont === 'Space Mono' ? 'selected' : ''}>Space Mono (Retro)</option>
                    </select>
                </div>

                ${config.themes.length > 1 ? html`
                    <div class="mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,0.05);">
                        <button class="btn btn-sm" style="width:100%; color:#ff4444; background:rgba(255,68,68,0.1); border:1px solid rgba(255,68,68,0.2);" 
                            @click=${() => { if (confirm(`Delete theme '${theme.name}'?`)) { const c = { ...config }; c.themes = c.themes.filter(t => t.name !== theme.name); c.activeTheme = c.themes[0].name; this.setState({ config: c }); } }}>🗑️ Delete Current Theme</button>
                    </div>
                ` : ''}

                <div class="mt-5 pt-4" style="border-top: 2px solid rgba(255,255,255,0.1);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin:0; font-size:0.75rem; letter-spacing:1px; color:#888;">📦 SYSTEM LIBRARY</h3>
                        <button class="btn ghost-btn btn-xs" @click=${() => this.saveThemeAsPreset()}>Save Aggregate</button>
                    </div>
                    
                    <button class="btn ghost-btn btn-sm mb-3" style="width:100%;" @click=${() => this.loadThemePresets()}>🔄 Refresh Presets</button>

                    <div class="presets-list" style="display:flex; flex-direction:column; gap:8px;">
                        ${this.state.themePresets.length === 0 ? html`<div class="text-dim text-center py-3" style="font-size:0.65rem; border:1px dashed #444; border-radius:8px;">No presets in system library.</div>` : ''}
                        ${this.state.themePresets.map(p => html`
                            <div class="preset-item glass-panel p-2 px-3 pointer" style="display:flex; justify-content:space-between; align-items:center; border-radius:8px;" @click=${() => this.applyThemePreset(p)}>
                                <div style="display:flex; flex-direction:column;">
                                    <span style="font-size:0.75rem; font-weight:600;">${p.name}</span>
                                    <span style="font-size:0.55rem; opacity:0.5;">${p.themes.length} Modes • ${p.created_at || 'Legacy'}</span>
                                </div>
                                <span style="font-size:0.6rem; color:var(--primary-color); text-transform:uppercase;">Apply</span>
                            </div>
                        `)}
                    </div>
                </div>
            </div>
        `;
    }
    
    renderStateInspector() {
        const { config } = this.state;
        if (!config.state) config.state = {};
        const keys = Object.keys(config.state);

        return html`
            <div class="inspector-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3 style="margin:0;">Live State</h3>
                    <button class="btn primary-btn btn-xs" @click=${async () => { const name = await this.prompt("Variable Name:"); if (name) { config.state[name] = ""; this.update(); } }}>+ New</button>
                </div>
                
                <div class="state-monitor glass-panel" style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 10px;">
                    ${keys.length === 0 ? html`<div class="text-dim text-center py-4" style="font-size:0.7rem;">No active state variables.</div>` : ''}
                    ${keys.map(key => html`
                        <div class="state-row mb-3" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <span style="font-size:0.7rem; font-weight:bold; color:var(--primary-color);">${key}</span>
                                <button class="btn-icon btn-xs" style="opacity:0.4;" @click=${() => { delete config.state[key]; this.update(); }}>✕</button>
                            </div>
                            <input type="text" class="state-input" 
                                style="width:100%; background:rgba(0,0,0,0.05); border:1px solid var(--glass-border); border-radius:4px; padding:4px 8px; font-size:0.7rem; color:var(--text-main);" 
                                .value="${config.state[key]}" 
                                @input=${(e) => { config.state[key] = e.target.value; this.update(); }}>
                        </div>
                    `)}
                </div>
                
                <div class="text-dim mt-3" style="font-size: 0.6rem; line-height: 1.4;">
                    <i style="color:var(--primary-color);">💡 Tip:</i> Bind components to these variables using the 'Data' tab to see live reactive updates in the preview.
                </div>
            </div>
        `;
    }

    renderDataManager() {
        const { config } = this.state;
        if (!config.state) config.state = {};
        if (!config.apis) config.apis = [];

        return html`
            <div class="data-manager fade-in">
                <div class="data-section mb-4">
                    <div class="flex-between mb-3">
                        <h5 class="section-title-sm m-0">Global State</h5>
                        <button class="btn-icon btn-xs" @click=${async () => {
                            const name = await this.prompt("Variable Name:");
                            if (name) { config.state[name] = "New Value"; this.update(); }
                        }}>➕</button>
                    </div>
                    <div class="variable-list">
                        ${Object.keys(config.state).length === 0 ? html`<div class="empty-state">No variables defined</div>` : ''}
                        ${Object.entries(config.state).map(([key, val]) => html`
                            <div class="var-item glass-panel p-2 mb-2" style="border-radius:8px; border:1px solid rgba(255,255,255,0.05);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                    <span style="font-size:0.7rem; font-weight:700; color:#fff;">${key}</span>
                                    <span style="font-size:0.5rem; opacity:0.4;">STRING</span>
                                </div>
                                <input type="text" class="glass-input btn-xs" style="width:100%;" .value="${val}" @input=${(e) => { config.state[key] = e.target.value; this.update(); }}>
                            </div>
                        `)}
                    </div>
                </div>

                <div class="data-section">
                    <div class="flex-between mb-3">
                        <h5 class="section-title-sm m-0">API Connectors</h5>
                        <button class="btn-icon btn-xs" @click=${() => this.openApiDesigner()}>➕</button>
                    </div>
                    <div class="api-list">
                         ${(config.apis || []).length === 0 ? html`<div class="empty-state">No APIs connected</div>` : ''}
                         ${(config.apis || []).map((api, idx) => html`
                            <div class="api-item glass-panel p-2 mb-2 flex-between" style="border-radius:8px; border:1px solid rgba(255,255,255,0.05); border-left: 3px solid var(--primary-color);">
                                <div class="flex-1">
                                    <div style="font-size:0.7rem; font-weight:700; color:#fff;">${api.name}</div>
                                    <div style="font-size:0.5rem; opacity:0.4;">${api.method} • ${api.url}</div>
                                </div>
                                <button class="btn-icon btn-xs" @click=${() => { config.apis.splice(idx, 1); this.update(); }}>✕</button>
                            </div>
                         `)}
                    </div>
                </div>
                
                <div class="data-section mt-4">
                    <div class="flex-between mb-3">
                        <h5 class="section-title-sm m-0">System Health</h5>
                        <button class="btn-icon btn-xs" @click=${() => this.fetchBridgeInfo()}>🔄</button>
                    </div>
                    <div class="bridge-status p-3 glass-panel" style="border-radius:10px; background:rgba(0,0,0,0.2);">
                        ${!this.state.bridgeInfo ? html`<div class="text-center py-2 opacity-05" style="font-size:0.6rem;">Fetching diagnostic data...</div>` : html`
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                <div class="health-metric">
                                    <label style="display:block; font-size:0.5rem; opacity:0.5; margin-bottom:2px;">STATUS</label>
                                    <div style="font-size:0.7rem; font-weight:700; color:${this.state.bridgeInfo.status === 'healthy' ? '#10b981' : '#f59e0b'};">
                                        ● ${this.state.bridgeInfo.status?.toUpperCase() || 'UNKNOWN'}
                                    </div>
                                </div>
                                <div class="health-metric">
                                    <label style="display:block; font-size:0.5rem; opacity:0.5; margin-bottom:2px;">BRIDGE VERSION</label>
                                    <div style="font-size:0.7rem; color:#fff;">v${this.state.bridgeInfo.version || '1.0.0'}</div>
                                </div>
                                <div class="health-metric">
                                    <label style="display:block; font-size:0.5rem; opacity:0.5; margin-bottom:2px;">LATENCY</label>
                                    <div style="font-size:0.7rem; color:#fff;">${this.state.bridgeInfo.latency || '< 5ms'}</div>
                                </div>
                                <div class="health-metric">
                                    <label style="display:block; font-size:0.5rem; opacity:0.5; margin-bottom:2px;">UPTIME</label>
                                    <div style="font-size:0.7rem; color:#fff;">${this.state.bridgeInfo.uptime || '99.9%'}</div>
                                </div>
                            </div>
                        `}
                    </div>
                </div>

                <div class="pro-tip mt-4 p-3 glass-panel" style="background:rgba(234, 88, 12, 0.05); border:1px solid rgba(234, 88, 12, 0.1); border-radius:8px;">
                    <div style="font-size:0.65rem; color:var(--primary-color); font-weight:700; margin-bottom:5px;">PRO TIP: Dynamic Binding</div>
                    <div style="font-size:0.6rem; opacity:0.8; line-height:1.4;">Use <code style="color:var(--primary-color); font-weight:700;">{{state.variableName}}</code> in any text field to bind it to a variable.</div>
                </div>
            </div>
        `;
    }

    async openApiDesigner() {
        const title = "API Connector Designer";
        const { config } = this.state;
        if (!config.apis) config.apis = [];

        const content = html`
            <div class="p-3">
                <div class="input-group mb-3">
                    <label class="label-dim">Connector Name</label>
                    <input type="text" id="api-name" placeholder="e.g. Products API">
                </div>
                <div class="input-group mb-3">
                    <label class="label-dim">Endpoint URL</label>
                    <input type="text" id="api-url" placeholder="https://api.example.com/v1/...">
                </div>
                <div class="grid-2 gap-3">
                    <div class="input-group">
                        <label class="label-dim">Method</label>
                        <select id="api-method" class="w-full">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label class="label-dim">Data Type</label>
                        <select id="api-type" class="w-full">
                            <option value="json">JSON</option>
                            <option value="text">TEXT</option>
                        </select>
                    </div>
                </div>
            </div>
        `;

        SPPUX.Modal.open(title, content, [
            { label: 'Cancel', type: 'secondary', fn: (m) => m.close() },
            { 
                label: 'Save Connector', 
                type: 'primary', 
                fn: (m) => {
                    const name = document.getElementById('api-name').value;
                    const url = document.getElementById('api-url').value;
                    const method = document.getElementById('api-method').value;
                    const type = document.getElementById('api-type').value;

                    if (!name || !url) {
                        this.notify("Please provide Name and URL", "error");
                        return;
                    }

                    config.apis.push({ name, url, method, type });
                    this.update();
                    m.close();
                    this.notify(`API Connector '${name}' added!`, "success");
                }
            }
        ]);
    }

    resolveValue(val) {
        if (typeof val !== 'string') return val;
        
        // Handle template bindings: {{state.var}}
        return val.replace(/\{\{([^}]+)\}\}/g, (match, path) => {
            const parts = path.trim().split('.');
            let obj = null;
            
            const { config } = this.state;
            if (parts[0] === 'state') obj = config.state || {};
            
            if (!obj) return match;
            
            // Resolve nested path
            let current = obj;
            for (let i = 1; i < parts.length; i++) {
                if (current && current[parts[i]] !== undefined) {
                    current = current[parts[i]];
                } else {
                    return match;
                }
            }
            return current;
        });
    }


    async convertToSymbol(id) {
        const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        const comp = BaseComponent.findInTree(activeScreen.components, id);
        if (!comp) return;

        const name = await this.prompt("Symbol Name:", comp.props.text || "My Symbol");
        if (!name) return;

        const { config } = this.state;
        if (!config.symbols) config.symbols = [];

        const symbolId = 'sym_' + Date.now();
        config.symbols.push({
            id: symbolId,
            name: name,
            root: JSON.parse(JSON.stringify(comp))
        });

        // Replace component with symbol instance
        comp.type = 'symbol_instance';
        comp.props = { symbolId: symbolId };
        comp.children = [];

        this.notify(`'${name}' converted to reusable Symbol!`, 'success');
        this.update();
    }

    renderSymbolsLibrary() {
        const { config } = this.state;
        const symbols = config.symbols || [];

        return html`
            <div class="symbols-library fade-in">
                <div class="flex-between mb-3">
                    <h5 class="section-title-sm m-0">Global Symbols</h5>
                    <span class="badge primary">${symbols.length}</span>
                </div>
                ${symbols.length === 0 ? html`<div class="empty-state">No symbols created yet. Right-click any component to 'Convert to Symbol'.</div>` : ''}
                <div class="symbol-grid" style="display:grid; grid-template-columns:1fr; gap:10px;">
                    ${symbols.map(sym => html`
                        <div class="symbol-item glass-panel p-3 pointer" 
                            draggable="true" 
                            @dragstart=${(e) => this.onDragStart(e, { type: 'symbol_instance', name: sym.name, props: { symbolId: sym.id } })}
                            style="border:1px solid rgba(255,255,255,0.05); border-radius:12px; transition:all 0.2s;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-weight:700; color:#fff; font-size:0.75rem;">${sym.name}</div>
                                <div style="font-size:0.8rem; opacity:0.5;">💠</div>
                            </div>
                            <div class="mt-2" style="font-size:0.6rem; opacity:0.4;">ID: ${sym.id}</div>
                        </div>
                    `)}
                </div>
            </div>
        `;
    }

    renderAnimationsInspector(activeScreen) {
        const { selectedComponentId } = this.state;
        const comp = this.findComponentById(activeScreen?.components || [], selectedComponentId);
        
        if (!comp) return html`<div class="p-4 text-center opacity-05">Select a component to animate</div>`;

        const anims = [
            { id: 'none', name: 'None' },
            { id: 'fade-in', name: 'Fade In' },
            { id: 'slide-up', name: 'Slide Up' },
            { id: 'slide-down', name: 'Slide Down' },
            { id: 'bounce-in', name: 'Bounce In' },
            { id: 'zoom-in', name: 'Zoom In' }
        ];

        return html`
            <div class="inspector-section fade-in">
                <h3>Animation Builder</h3>
                <div class="input-group">
                    <label>Entrance Effect</label>
                    <select @change=${(e) => { comp.props.animation = e.target.value; this.update(); }}>
                        ${anims.map(a => html`<option value="${a.id}" ?selected=${comp.props.animation === a.id}>${a.name}</option>`)}
                    </select>
                </div>
                <div class="input-group mt-3">
                    <label>Duration (seconds)</label>
                    <input type="range" min="0.1" max="2" step="0.1" .value="${comp.props.animDuration || 0.5}" @input=${(e) => { comp.props.animDuration = parseFloat(e.target.value); this.update(); }}>
                    <div style="text-align:right; font-size:0.6rem; opacity:0.5;">${comp.props.animDuration || 0.5}s</div>
                </div>
                <div class="mt-4 p-3 glass-panel" style="border-radius:12px; background:rgba(255,255,255,0.02);">
                    <div style="font-size:0.6rem; line-height:1.4; opacity:0.6;">Animations are triggered when the screen loads or the component enters the viewport.</div>
                </div>
            </div>
        `;
    }

    exportProject() {
        const title = "Native Code Export";
        const code = JSON.stringify(this.state.config, null, 2);
        
        const content = html`
            <div class="export-container p-3">
                <div class="tabs mb-3" style="display:flex; gap:10px;">
                    <div class="tab active" style="font-size:0.7rem; font-weight:700; color:var(--primary-color);">BLUEPRINT YAML</div>
                    <div class="tab" style="font-size:0.7rem; opacity:0.5;">FLUTTER DART</div>
                    <div class="tab" style="font-size:0.7rem; opacity:0.5;">REACT NATIVE</div>
                </div>
                <pre class="glass-panel p-3" style="max-height:400px; overflow:auto; font-size:0.65rem; color:#aaa; border:1px solid rgba(255,255,255,0.05);">${code}</pre>
                <div class="mt-3" style="display:flex; justify-content:space-between; align-items:center;">
                    <p style="font-size:0.6rem; opacity:0.5;">Total Screens: ${this.state.config.screens.length}</p>
                    <button class="btn primary-btn btn-sm" @click=${() => this.notify("Exported to clipboard!", "success")}>📋 Copy Code</button>
                </div>
            </div>
        `;

        SPPUX.Modal.open(title, content, [
            { label: 'Close', type: 'secondary', fn: (m) => m.close() }
        ]);
    }

    evalCondition(expr) {
        if (!expr) return true;
        try {
            // Very simple expression parser for basic logic: var > 5, var == 'val', !var
            // In a real app, use a safer expression parser.
            const clean = expr.trim();
            if (clean === 'true') return true;
            if (clean === 'false') return false;
            
            // Support: a > b, a < b, a == b, a != b
            const ops = ['==', '!=', '>=', '<=', '>', '<'];
            for (const op of ops) {
                if (clean.includes(op)) {
                    const [left, right] = clean.split(op).map(s => s.trim().replace(/^'|'$/g, ""));
                    const lVal = isNaN(left) ? left : parseFloat(left);
                    const rVal = isNaN(right) ? right : parseFloat(right);
                    
                    if (op === '==') return lVal == rVal;
                    if (op === '!=') return lVal != rVal;
                    if (op === '>') return lVal > rVal;
                    if (op === '<') return lVal < rVal;
                    if (op === '>=') return lVal >= rVal;
                    if (op === '<=') return lVal <= rVal;
                }
            }
            
            // Truthy check
            return !!clean;
        } catch (e) {
            console.warn("[ActionEngine] Condition Eval Error:", e);
            return false;
        }
    }

    openVariablePicker(comp, propName) {
        const title = "Bind Variable to Property";
        const { config } = this.state;
        const variables = Object.keys(config.state || {});

        const content = html`
            <div class="variable-picker p-3">
                <p class="text-dim mb-3" style="font-size:0.75rem;">Select a global state variable to bind to <strong style="color:var(--primary-color);">${propName}</strong>.</p>
                
                <div class="variable-grid" style="display:grid; grid-template-columns:1fr; gap:10px;">
                    ${variables.length === 0 ? html`<div class="empty-state p-4 text-center">No variables defined. Create them in the 'Data' tab first.</div>` : ''}
                    ${variables.map(key => html`
                        <div class="var-card glass-panel p-3 pointer" 
                            style="display:flex; justify-content:space-between; align-items:center; border:1px solid rgba(255,255,255,0.05);"
                            @click=${() => {
                                comp.props[propName] = `{{state.${key}}}`;
                                this.update();
                                this.closeModal();
                                this.notify(`Bound ${key} to ${propName}`, 'success');
                            }}>
                            <div>
                                <div style="font-weight:700; color:#fff; font-size:0.8rem;">${key}</div>
                                <div style="font-size:0.6rem; opacity:0.5;">Current Value: ${config.state[key]}</div>
                            </div>
                            <div style="font-size:0.8rem; color:var(--primary-color);">🔗 Bind</div>
                        </div>
                    `)}
                </div>
                
                <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,0.05); text-align:center;">
                    <button class="btn ghost-btn btn-sm" @click=${() => {
                        delete comp.props[propName];
                        this.update();
                        this.closeModal();
                    }}>❌ Clear Binding</button>
                </div>
            </div>
        `;

        SPPUX.Modal.open(title, content, [
            { label: 'Close', type: 'secondary', fn: (m) => m.close() }
        ]);
    }

    openIconPicker(comp) {
        const title = "Professional Icon Library";
        const content = html`
            <div class="icon-picker-container p-3">
                <div class="search-bar mb-3">
                    <input type="text" placeholder="Search icons..." style="width:100%; padding:10px; border-radius:8px; background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-main);" @input=${(e) => this.filterIcons(e.target.value)}>
                </div>
                <div class="icon-grid" id="icon-picker-grid" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; max-height: 400px; overflow-y: auto;">
                    ${this.getIconLibrary().map(icon => html`
                        <div class="icon-item glass-panel p-2 text-center pointer" @click=${() => { comp.props.icon = icon.name; this.update(); this.closeModal(); }} style="border: 1px solid rgba(255,255,255,0.05); transition: transform 0.2s;">
                            <div style="font-size: 1.5rem;">${icon.glyph}</div>
                            <div style="font-size: 0.5rem; margin-top: 4px; opacity:0.5; overflow:hidden; text-overflow:ellipsis;">${icon.name}</div>
                        </div>
                    `)}
                </div>
            </div>
        `;
        SPPUX.Modal.open(title, content, [{ label: 'Close', type: 'ghost', fn: 'close' }]);
    }

    getIconLibrary() {
        return [
            { name: 'home', glyph: '🏠', tags: ['house', 'main'] },
            { name: 'user', glyph: '👤', tags: ['profile', 'account'] },
            { name: 'settings', glyph: '⚙️', tags: ['config', 'tools'] },
            { name: 'search', glyph: '🔍', tags: ['find', 'lookup'] },
            { name: 'bell', glyph: '🔔', tags: ['notice', 'alert'] },
            { name: 'heart', glyph: '❤️', tags: ['like', 'favorite'] },
            { name: 'camera', glyph: '📷', tags: ['photo', 'image'] },
            { name: 'mail', glyph: '✉️', tags: ['email', 'message'] },
            { name: 'cart', glyph: '🛒', tags: ['shop', 'buy'] },
            { name: 'star', glyph: '⭐', tags: ['rate', 'premium'] },
            { name: 'map', glyph: '🗺️', tags: ['location', 'place'] },
            { name: 'calendar', glyph: '📅', tags: ['date', 'time'] },
            { name: 'trash', glyph: '🗑️', tags: ['delete', 'remove'] },
            { name: 'lock', glyph: '🔒', tags: ['secure', 'auth'] },
            { name: 'chart', glyph: '📊', tags: ['graph', 'data'] },
            { name: 'cloud', glyph: '☁️', tags: ['weather', 'sync'] },
            { name: 'phone', glyph: '📞', tags: ['call', 'contact'] },
            { name: 'music', glyph: '🎵', tags: ['audio', 'sound'] },
            { name: 'play', glyph: '▶️', tags: ['video', 'start'] },
            { name: 'check', glyph: '✅', tags: ['done', 'ok'] }
        ];
    }

    filterIcons(query) {
        const grid = document.getElementById('icon-picker-grid');
        if (!grid) return;
        const items = grid.querySelectorAll('.icon-item');
        query = query.toLowerCase();
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? 'block' : 'none';
        });
    }

    uploadAsset() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (re) => {
                    const assets = this.state.assets || [];
                    assets.push({ name: file.name, url: re.target.result });
                    this.setState({ assets });
                    this.notify(`Asset '${file.name}' uploaded successfully.`, 'success');
                };
                reader.readAsDataURL(file);
            }
        };
        input.click();
    }

    onAssetClick(asset) {
        if (this.state.selectedComponentId) {
            const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
            const comp = this.findComponentById(activeScreen.components, this.state.selectedComponentId);
            if (comp && comp.type === 'image') {
                comp.props.src = asset.url;
                this.update();
                this.notify('Applied asset to image.', 'success');
            }
        }
    }

    onDragStart(e, comp) {
        const data = JSON.stringify(comp);
        e.dataTransfer.setData('spp_comp', data);
        e.dataTransfer.setData('text/plain', data);
        this.state.draggedComponent = comp;
        this.state.draggedCanvasId = null;
    }

    onCanvasDragStart(e, id) {
        e.dataTransfer.setData('spp_canvas_id', id);
        this.state.draggedCanvasId = id;
        this.state.draggedComponent = null;
        // Visual hint
        e.target.style.opacity = '0.5';
    }

    onDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const targetFrame = e.target.closest('.device-frame');
        if (targetFrame) targetFrame.classList.remove('drag-over');

        // Detect parent container
        const dropTarget = e.target.closest('.layout-container');
        const parentId = dropTarget ? dropTarget.dataset.id : null;

        const draggedCanvasId = this.state.draggedCanvasId || e.dataTransfer.getData('spp_canvas_id');
        
        if (draggedCanvasId) {
            this.moveComponentToPosition(draggedCanvasId, parentId);
        } else {
            let comp = this.state.draggedComponent;
            if (!comp) {
                const compData = e.dataTransfer.getData('spp_comp') || e.dataTransfer.getData('text/plain');
                if (compData) {
                    try { comp = JSON.parse(compData); } catch (err) {}
                }
            }

            if (comp && comp.type) {
                this.addComponentToScreen(comp, parentId);
            }
        }

        this.state.draggedComponent = null;
        this.state.draggedCanvasId = null;
    }

    moveComponentToPosition(id) {
        // Simple move-to-bottom for now, full positional drop can be added later
        const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        if (!activeScreen) return;
        
        const index = activeScreen.components.findIndex(c => c.id === id);
        if (index > -1) {
            const comp = activeScreen.components.splice(index, 1)[0];
            activeScreen.components.push(comp);
            this.update();
        }
    }

    addComponentToScreen(comp, parentId = null) {
        let activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        if (!activeScreen && this.state.config.screens.length > 0) {
            activeScreen = this.state.config.screens[0];
            this.setState({ activeScreenId: activeScreen.id });
        }
        if (!activeScreen) return;
        if (!activeScreen.components) activeScreen.components = [];
        
        const createComp = (source) => {
            const newNode = {
                id: 'comp_' + Date.now() + '_' + Math.floor(Math.random()*1000),
                type: source.type,
                props: source.props ? { ...source.props } : this.getDefaultProps(source.type),
                actions: source.actions ? [...source.actions] : [],
            };
            if (source.children) {
                newNode.children = source.children.map(c => createComp(c));
            } else if (['row', 'column', 'container'].includes(source.type)) {
                newNode.children = [];
            }
            return newNode;
        };

        const newComp = createComp(comp);

        if (parentId) {
            const findAndAdd = (list) => {
                for (let c of list) {
                    if (c.id === parentId) {
                        c.children = [...(c.children || []), newComp];
                        return true;
                    }
                    if (c.children && findAndAdd(c.children)) return true;
                }
                return false;
            };
            findAndAdd(activeScreen.components);
        } else {
            activeScreen.components = [...activeScreen.components, newComp];
        }
        
        this.setState({ config: { ...this.state.config } });
    }

    getDefaultProps(type) {
        const props = { text: 'New ' + type };
        if (type === 'image') props.src = 'https://picsum.photos/400/300';
        if (type === 'slider') { props.min = 0; props.max = 100; }
        if (type === 'spacer') props.height = 20;
        if (type === 'row' || type === 'column') { props.gap = 10; props.padding = 5; }
        return props;
    }

    async addDesignToken() {
        const name = await this.prompt("Token Name (e.g. PrimaryButton, CardHeader):");
        if (name) {
            if (!this.state.config.tokens) this.state.config.tokens = {};
            this.state.config.tokens[name] = "background: var(--primary); color: #fff; padding: 10px; border-radius: 8px;";
            this.update();
        }
    }

    openActionBuilder(comp) {
        this.setState({ currentPipelineSteps: [] });
        this.renderActionBuilderModal(comp);
    }

    renderActionBuilderModal(comp) {
        const title = "Action Pipeline Builder";
        const triggerOptions = [
            { value: 'onTap', label: '👆 On Tap (Click)' },
            { value: 'onLongPress', label: '🕒 On Long Press' },
            { value: 'onSwipeRight', label: '➡️ On Swipe Right' },
            { value: 'onLoad', label: '🚀 On Screen Load' }
        ];

        const actionTypes = [
            { value: 'navigate', label: 'Navigate to Screen' },
            { value: 'setState', label: 'Update Global State' },
            { value: 'callApi', label: 'Call Backend API' },
            { value: 'notify', label: 'Show Notification' }
        ];

        const content = html`
            <div class="p-3">
                <div class="input-group mb-4">
                    <label class="label-dim">Event Trigger</label>
                    <select id="action-trigger" class="glass-input w-full">
                        ${triggerOptions.map(opt => html`<option value="${opt.value}">${opt.label}</option>`)}
                    </select>
                </div>
                
                <div class="pipeline-designer glass-panel p-4 mb-4">
                    <h4 class="section-title-sm">Steps in Pipeline</h4>
                    <div class="current-steps mb-3">
                        ${this.state.currentPipelineSteps.length === 0 ? html`<div class="empty-state py-2">No steps added yet</div>` : ''}
                        ${this.state.currentPipelineSteps.map((s, i) => html`
                            <div class="step-card mb-2 p-2">
                                <div class="flex-between">
                                    <span>Step ${i+1}: <strong>${s.type}</strong></span>
                                    <button class="btn-icon btn-xs" @click=${() => {
                                        this.state.currentPipelineSteps.splice(i, 1);
                                        this.renderActionBuilderModal(comp);
                                    }}>✕</button>
                                </div>
                                <div class="text-dim" style="font-size:0.6rem;">Target: ${s.target}</div>
                            </div>
                        `)}
                    </div>

                    <div class="step-adder p-3 glass-panel" style="background:rgba(255,255,255,0.02); border-radius:8px;">
                        <div class="input-group mb-2">
                            <label class="label-dim">Action Type</label>
                            <select id="action-type" class="glass-input w-full">
                                ${actionTypes.map(opt => html`<option value="${opt.value}">${opt.label}</option>`)}
                            </select>
                        </div>
                        <div class="input-group mb-2">
                            <label class="label-dim">Target / Parameter</label>
                            <input type="text" id="action-target" class="glass-input w-full" placeholder="e.g. screen_id, var=val">
                        </div>
                        <div class="input-group mb-3">
                            <label class="label-dim">Condition (Optional)</label>
                            <input type="text" id="action-condition" class="glass-input w-full" placeholder="e.g. {{state.count}} > 0">
                        </div>
                        <button class="btn ghost-btn btn-sm w-full" @click=${() => {
                            const type = document.getElementById('action-type')?.value;
                            const target = document.getElementById('action-target')?.value;
                            const condition = document.getElementById('action-condition')?.value;
                            if (type && target) {
                                this.state.currentPipelineSteps.push({ type, target, condition });
                                this.renderActionBuilderModal(comp);
                            }
                        }}>+ Add Step to Pipeline</button>
                    </div>
                </div>
            </div>
        `;

        SPPUX.Modal.open(title, content, [
            { label: 'Cancel', type: 'ghost', fn: (m) => m.close() },
            { label: '🚀 Attach Pipeline', type: 'primary', fn: (m) => this._saveAction(comp, m) }
        ]);
    }

    _saveAction(comp, modal) {
        const trigger = document.getElementById('action-trigger')?.value;
        const steps = [...this.state.currentPipelineSteps];

        if (!trigger || steps.length === 0) {
            this.notify("Please add at least one step to the pipeline.", "warning");
            return;
        }

        if (!comp.actions) comp.actions = [];
        comp.actions.push({ trigger, steps });

        this.notify(`Multi-step pipeline attached to ${comp.type}`, 'success');
        if (modal) modal.close();
        this.update();
    }

    async executeAction(step) {
        const { type, target, condition } = step;
        console.log(`[ActionEngine] Executing ${type} -> ${target} (Cond: ${condition || 'none'})`);
        
        // Handle Condition
        if (condition) {
            const resolvedCond = this.resolveValue(condition);
            if (!this.evalCondition(resolvedCond)) {
                console.log(`[ActionEngine] Branch skipped: ${condition} -> false`);
                return;
            }
        }

        switch (type) {
            case 'navigate':
                if (target) {
                    const screen = this.state.config.screens.find(s => s.id === target || s.title === target);
                    if (screen) {
                        this.setState({ activeScreenId: screen.id });
                        this.triggerOnLoadActions(screen.id);
                    }
                    else this.notify(`Screen '${target}' not found.`, 'warning');
                }
                break;
            case 'setState':
                if (target && target.includes('=')) {
                    const [key, val] = target.split('=');
                    const config = { ...this.state.config };
                    if (!config.state) config.state = {};
                    config.state[key.trim()] = val.trim();
                    this.setState({ config });
                    this.notify(`State Updated: ${key.trim()} = ${val.trim()}`, 'success');
                }
                break;
            case 'notify':
                this.notify(target || "Action Triggered", "info");
                break;
            case 'callApi':
                this.notify(`Simulating API Call: ${target}`, "info");
                break;
        }
    }

    async onComponentClick(comp) {
        // Selection Logic (Default)
        this.setState({ selectedComponentId: comp.id });

        // Action Execution (if has onTap trigger)
        if (comp.actions && comp.actions.length > 0) {
            const tapAction = comp.actions.find(a => a.trigger === 'onTap');
            if (tapAction) {
                for (const step of tapAction.steps) {
                    await this.executeAction(step);
                }
            }
        }
    }

    async triggerOnLoadActions(screenId) {
        const screen = this.state.config.screens.find(s => s.id === screenId);
        if (!screen || !screen.components) return;

        console.log(`[ActionEngine] Triggering onLoad actions for screen: ${screenId}`);
        
        const traverse = async (comps) => {
            for (const comp of comps) {
                if (comp.actions) {
                    const loadAction = comp.actions.find(a => a.trigger === 'onLoad');
                    if (loadAction) {
                        for (const step of loadAction.steps) {
                            await this.executeAction(step);
                        }
                    }
                }
                if (comp.children) await traverse(comp.children);
            }
        };

        await traverse(screen.components);
    }

    closeModal() {
        const overlay = document.getElementById('studio-modal-overlay');
        if (overlay) {
            overlay.style.display = 'none';
            const footer = overlay.querySelector('.modal-footer');
            if (footer) footer.style.display = 'flex';
        }
        if (window.SPPUX && SPPUX.Modal) SPPUX.Modal.close();
    }

    saveAction(comp, modal) {
        this.notify("Action Pipeline updated.", "success");
        if (modal && modal.close) modal.close();
    }


    setViewMode(mode) {
        this.setState({ viewMode: mode });
        if (mode === 'assets') this.fetchAssets();
    }

    async fetchAssets(path = '') {
        const res = await this.api('get_assets', { 
            appname: this.state.config.app_id || 'default',
            path: path 
        });
        if (res.success) {
            this.setState({ 
                assets: res.data.assets || [],
                folders: res.data.folders || [],
                currentAssetPath: res.data.currentPath || ''
            });
        }
    }

    triggerAssetUpload() {
        const input = document.getElementById('asset-file-input');
        if (input) input.click();
    }

    async uploadAsset(e) {
        const file = e.target.files?.[0];
        if (!file) return;

        this.notify(`Preparing '${file.name}' for tunneling...`, 'info');

        const reader = new FileReader();
        reader.onload = async () => {
            const base64Data = reader.result;
            this.notify(`Tunneling '${file.name}' to server...`, 'info');

            try {
                const endpoint = window.SPP_CONFIG?.apiEndpoint || 'api.php';
                const res = await fetch(`${endpoint}?action=upload_asset_base64`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'upload_asset_base64',
                        appname: this.state.config.app_id || 'default',
                        path: this.state.currentAssetPath,
                        filename: file.name,
                        mimeType: file.type,
                        base64Data: base64Data
                    })
                });
                const json = await res.json();
                if (json.success) {
                    this.notify(json.message || 'Asset uploaded successfully.', 'success');
                    await this.fetchAssets();
                } else {
                    this.notify(json.message || 'Tunneling failed: ' + json.message, 'error');
                }
            } catch (err) {
                this.notify('Upload error: Connection refused or timeout.', 'error');
            }
        };

        reader.onerror = () => this.notify('Local error: Failed to read file.', 'error');
        reader.readAsDataURL(file);

        // Reset input so same file can be re-uploaded
        e.target.value = '';
    }

    async createAssetFolder() {
        const name = await this.prompt("Enter folder name:");
        if (!name) return;
        const res = await this.api('create_asset_folder', { 
            appname: this.state.config.app_id || 'default',
            path: this.state.currentAssetPath,
            name: name
        });
        if (res.success) {
            this.notify(`Folder '${name}' created.`, 'success');
            this.fetchAssets(this.state.currentAssetPath);
        }
    }

    async renameAsset(asset) {
        const newName = await this.prompt("Enter new name:", asset.name);
        if (!newName || newName === asset.name) return;
        const res = await this.api('rename_asset', {
            appname: this.state.config.app_id || 'default',
            oldPath: asset.path,
            newName: newName
        });
        if (res.success) {
            this.notify(`Renamed to '${newName}'.`, 'success');
            this.fetchAssets(this.state.currentAssetPath);
        }
    }

    async deleteAsset(asset) {
        if (!confirm(`Are you sure you want to delete '${asset.name}'?` + (asset.type === 'folder' ? ' All contents will be lost.' : ''))) return;
        const res = await this.api('delete_asset', {
            appname: this.state.config.app_id || 'default',
            path: asset.path
        });
        if (res.success) {
            this.notify(`Successfully removed '${asset.name}'.`, 'success');
            this.fetchAssets(this.state.currentAssetPath);
        }
    }

    renderAssetsView() {
        const { assets = [], folders = [], currentAssetPath = '' } = this.state;
        const pathParts = currentAssetPath ? currentAssetPath.split('/') : [];
        
        return html`
            <div class="assets-view p-4 fade-in">
                <div class="header-row mb-4" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h2 style="margin-bottom:5px;">Asset Manager</h2>
                        <div class="breadcrumbs" style="font-size:0.75rem; color:var(--text-dim); display:flex; gap:5px; align-items:center;">
                            <span class="pointer" style="color:var(--primary-color);" @click=${() => this.fetchAssets('')}>Root</span>
                            ${pathParts.map((p, i) => html`
                                <span>/</span>
                                <span class="pointer" style="color:var(--primary-color);" @click=${() => this.fetchAssets(pathParts.slice(0, i+1).join('/'))}>${p}</span>
                            `)}
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn ghost-btn" @click=${() => this.createAssetFolder()}>📁 New Folder</button>
                        <button class="btn primary-btn" @click=${() => this.triggerAssetUpload()}>☁️ Upload</button>
                        <input type="file" id="asset-file-input" accept="image/*" style="display:none" @change=${(e) => this.uploadAsset(e)}>
                    </div>
                </div>
                
                <div class="asset-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 16px;">
                    <!-- Folders -->
                    ${folders.map(f => html`
                        <div class="asset-card folder glass-panel p-3 text-center pointer" style="border-left: 3px solid #ffca28;" @click=${() => this.fetchAssets(f.path)}>
                            <div style="font-size: 2.5rem; margin-bottom: 8px;">📁</div>
                            <div class="asset-name" style="font-size:0.75rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${f.name}</div>
                            <div style="display:flex; justify-content:center; gap:8px; margin-top:10px;">
                                <span title="Rename" class="pointer" style="opacity:0.5;" @click=${(e) => { e.stopPropagation(); this.renameAsset(f); }}>✏️</span>
                                <span title="Delete" class="pointer" style="opacity:0.5;" @click=${(e) => { e.stopPropagation(); this.deleteAsset(f); }}>🗑️</span>
                            </div>
                        </div>
                    `)}

                    <!-- Files -->
                    ${assets.map(a => html`
                        <div class="asset-card file glass-panel p-3 text-center">
                            <div class="asset-preview" style="height:90px; background:rgba(255,255,255,0.05); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:8px; overflow:hidden; position:relative;">
                                <img src="${a.url}" style="max-width:100%; max-height:100%; object-fit:contain;">
                            </div>
                            <div class="asset-name" style="font-size:0.7rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:2px;">${a.name}</div>
                            <div class="asset-meta" style="font-size:0.6rem; color:var(--text-dim);">${(a.size / 1024).toFixed(1)} KB</div>
                            <div style="display:flex; justify-content:center; gap:8px; margin-top:8px;">
                                <span title="Rename" class="pointer" style="opacity:0.5;" @click=${() => this.renameAsset(a)}>✏️</span>
                                <span title="Delete" class="pointer" style="opacity:0.5;" @click=${() => this.deleteAsset(a)}>🗑️</span>
                            </div>
                        </div>
                    `)}
                    
                    ${assets.length === 0 && folders.length === 0 ? html`
                        <div class="glass-panel p-5 text-center" style="grid-column: 1 / -1; background:transparent; border: 2px dashed rgba(255,255,255,0.05);">
                            <div style="font-size: 2.5rem; margin-bottom: 15px; opacity:0.3;">📂</div>
                            <p class="text-dim" style="font-size:0.8rem;">This workspace is currently empty.</p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    renderCodeView() {
        const configJson = JSON.stringify(this.state.config, null, 2);
        return html`
            <div class="code-view p-4 fade-in h-100">
                <div class="glass-panel h-100 p-0" style="display: flex; flex-direction: column;">
                    <div class="editor-header p-2 px-3" style="background: rgba(255,255,255,0.05); border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-family: 'JetBrains Mono'; font-size: 0.8rem;">mobile.yml</span>
                            <span id="code-status" class="badge" style="font-size: 0.55rem;">saved</span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn ghost-btn btn-sm" @click=${() => this.applyCodeChanges()}>Parse & Apply</button>
                            <button class="btn primary-btn btn-sm" @click=${() => this.saveConfig()}>Save</button>
                        </div>
                    </div>
                    <div class="code-editor-wrap" style="flex: 1; position: relative; overflow: hidden;">
                        <div class="code-line-numbers" id="code-line-nums" style="position: absolute; left: 0; top: 0; bottom: 0; width: 45px; background: rgba(255,255,255,0.03); border-right: 1px solid var(--glass-border); padding: 12px 0; font-family: 'JetBrains Mono'; font-size: 0.78rem; color: rgba(255,255,255,0.25); text-align: right; overflow: hidden; user-select: none;"></div>
                        <textarea id="code-editor-area"
                            style="position: absolute; left: 45px; top: 0; right: 0; bottom: 0; width: calc(100% - 45px); height: 100%; resize: none; border: none; outline: none; padding: 12px 16px; font-family: 'JetBrains Mono'; font-size: 0.78rem; line-height: 1.6; background: #0d0d0d; color: #d4d4d4; tab-size: 2;"
                            spellcheck="false"
                            @input=${(e) => this.onCodeEdit(e)}
                            @scroll=${(e) => this.syncLineNumbers(e)}>${configJson}</textarea>
                    </div>
                </div>
            </div>
        `;
    }

    onCodeEdit(e) {
        const status = document.getElementById('code-status');
        if (status) { status.textContent = 'modified'; status.className = 'badge warning'; }
        this.syncLineNumbers(e);
    }

    syncLineNumbers(e) {
        const textarea = e.target;
        const lineNums = document.getElementById('code-line-nums');
        if (!lineNums) return;
        const lines = textarea.value.split('\n').length;
        lineNums.innerHTML = Array.from({ length: lines }, (_, i) => `<div style="padding: 0 8px; line-height: 1.6;">${i + 1}</div>`).join('');
        lineNums.scrollTop = textarea.scrollTop;
    }

    applyCodeChanges() {
        const textarea = document.getElementById('code-editor-area');
        if (!textarea) return;
        try {
            const parsed = JSON.parse(textarea.value);
            if (!parsed.screens || !Array.isArray(parsed.screens)) {
                this.notify('Invalid config: missing screens array.', 'error');
                return;
            }
            this.state.config = parsed;
            this.update();
            const status = document.getElementById('code-status');
            if (status) { status.textContent = 'applied'; status.className = 'badge success'; }
            this.notify('Configuration parsed and applied.', 'success');
        } catch (err) {
            this.notify(`JSON Parse Error: ${err.message}`, 'error');
        }
    }

    setViewMode(mode) {
        this.setState({ viewMode: mode });
    }

    getComponentLibrary() {
        const core = ComponentRegistry.getDefinitions();
        const custom = this.state.customComponents || [];
        
        if (custom.length > 0) {
            core.push({ group: 'Custom Plugins', items: custom });
        }
        
        return core;
    }

    addStateVar() {
        const { config } = this.state;
        if (!config.state) config.state = {};
        const name = 'var_' + Object.keys(config.state).length;
        config.state[name] = '';
        this.update();
    }

    renameStateVar(oldName, newName) {
        if (!newName || oldName === newName) return;
        const { config } = this.state;
        config.state[newName] = config.state[oldName];
        delete config.state[oldName];
        this.update();
    }

    getBlueprints() {
        if (window.MobileBlueprints && window.MobileBlueprints.blueprints) {
            return window.MobileBlueprints.blueprints.map(bp => ({
                id: bp.id,
                name: bp.name,
                description: bp.description
            }));
        }
        return [
            { id: 'dashboard', name: 'Analytics Dashboard', description: 'Multi-column stats and health monitor.' },
            { id: 'social_feed', name: 'Discovery Feed', description: 'Rich media cards and engagement layout.' },
            { id: 'product_details', name: 'Product Showcase', description: 'E-commerce carousel and optimized CTAs.' },
            { id: 'auth', name: 'Modern Auth', description: 'Clean login/signup flow with secure inputs.' },
            { id: 'chat', name: 'Engineering Hub', description: 'Real-time chat layout with message history.' },
            { id: 'profile', name: 'User Profile', description: 'Avatar header and account management list.' },
            { id: 'settings', name: 'App Settings', description: 'System configuration and switches.' },
            { id: 'news_feed', name: 'News Feed', description: 'Article list with featured carousel.' },
            { id: 'e_commerce', name: 'E-Commerce Gallery', description: 'Product grid with search and filters.' },
            { id: 'onboarding', name: 'Onboarding Flow', description: 'Professional welcome sequence.' },
            { id: 'analytics_pro', name: 'Analytics Pro', description: 'Advanced data cards and charts.' }
        ];
    }

    addBlueprintToScreen(bp) {
        const { config, activeScreenId } = this.state;
        const screen = config.screens.find(s => s.id === activeScreenId) || config.screens[0];
        
        if (screen) {
            this.notify(`Applying '${bp.name}' architecture...`, 'info');
            this.applyTemplate(screen, bp.id);
            this.setState({ activeScreenId: screen.id }); // Ensure we are looking at the screen we just modified
            this.notify(`Successfully applied '${bp.name}' blueprint.`, 'success');
        } else {
            this.notify('No active screen found to apply blueprint.', 'error');
        }
    }

    getScreenIcon(type) {
        const icons = { dashboard: '🏠', form: '📝', list: '📋', profile: '👤', settings: '⚙️' };
        return icons[type] || '📄';
    }

    renderMockContent(screen) {
        const theme = this.getActiveTheme();
        return html`
            <div class="canvas-render" id="canvas-render" style="display: flex; flex-direction: column; gap: 15px; min-height: 200px; background: ${theme.background || 'transparent'}; font-family: '${theme.font || 'Outfit'}', sans-serif;" @click=${(e) => { if (e.target.id === 'canvas-render') this.setState({selectedComponentId: null}); }}>
                ${screen.components && screen.components.length > 0 
                    ? screen.components.map(c => this.renderComponent(c, theme))
                    : this.renderEmptyPlaceholder(screen)
                }
            </div>
        `;
    }

    renderEmptyPlaceholder(screen) {
        if (screen.type === 'dashboard') {
            return html`
                <div class="mock-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; opacity: 0.5;">
                    <div style="background:#eee; height:80px; border-radius:10px;"></div>
                    <div style="background:#eee; height:80px; border-radius:10px;"></div>
                    <div style="background:#eee; height:80px; border-radius:10px;"></div>
                    <div style="background:#eee; height:80px; border-radius:10px;"></div>
                </div>
            `;
        }
        return html`<div class="text-center py-5 text-dim" style="border: 2px dashed #eee; border-radius: 12px;">Drag components here to build your ${screen.type}</div>`;
    }

    async createSnapshot() {
        const name = await this.prompt("Enter snapshot name:", "Milestone " + new Date().toLocaleDateString());
        if (!name) return;
        
        const res = await this.api('create_snapshot', { id: this.state.config.app_id, name });
        if (res.success) {
            this.notify("Snapshot created successfully.", "success");
            const snapRes = await this.api('get_snapshots', { id: this.state.config.app_id });
            if (snapRes.success) this.setState({ snapshots: snapRes.data.snapshots });
        }
    }

    async restoreSnapshot(file) {
        if (!confirm("Are you sure you want to restore this snapshot? Current unsaved changes will be lost.")) return;
        this.notify("Restoring snapshot...", "info");
        // In a real app, this would send the snapshot file to the server to overwrite mobile.yml
        this.notify("Snapshot restored. Reloading...", "success");
        setTimeout(() => location.reload(), 1000);
    }

    findComponentById(list, id) {
        return BaseComponent.findInTree(list, id);
    }

    renderComponent(c, inheritedTheme = null) {
        const globalTheme = this.getActiveTheme();
        const { deviceType, config } = this.state;
        const props = c.props || {};

        // Theme Scoping Logic: Local > Inherited > Global
        let theme = inheritedTheme || globalTheme;
        if (props.theme && config.themes) {
            theme = config.themes.find(t => t.name === props.theme) || theme;
        }

        // Visibility Check
        if (props.hidden) return '';
        if (props.visibility === 'ios_only' && deviceType !== 'ios') return '';
        if (props.visibility === 'android_only' && deviceType !== 'android') return '';

        const isSelected = this.state.selectedComponentId === c.id;

        // Animation Injection
        const animationStyle = props.animation ? `animation: ${props.animation} ${props.animDuration || 0.5}s ease-out;` : '';

        // Symbol Instance Resolution
        if (c.type === 'symbol_instance') {
            const symbol = config.symbols?.find(s => s.id === props.symbolId);
            if (symbol) {
                const root = symbol.root;
                // Render the symbol content within a wrapper to handle selection/layout
                return html`
                    <div class="canvas-comp symbol-wrapper ${isSelected ? 'selected' : ''}" 
                        data-id="${c.id}"
                        style="${selectedStyle} position:relative;"
                        @click=${(e) => { e.stopPropagation(); this.onComponentClick(c); }}>
                        <div style="pointer-events:none; opacity:0.9;">
                            ${this.renderComponent(root, theme)}
                        </div>
                        <div class="symbol-badge" style="position:absolute; top:5px; right:5px; font-size:0.5rem; background:var(--primary-color); color:white; padding:2px 4px; border-radius:4px; opacity:0.8;">SYMBOL</div>
                    </div>
                `;
            }
            return html`<div class="error-msg">Symbol Deleted</div>`;
        }
        
        // Design Token / Global Styles
        let tokenStyle = '';
        if (props.designToken && config.tokens && config.tokens[props.designToken]) {
            tokenStyle = config.tokens[props.designToken];
        }

        // Advanced Styles Injection
        const elevationStyle = props.elevation ? `box-shadow: 0 ${props.elevation}px ${props.elevation * 2}px rgba(0,0,0,0.15);` : '';
        const borderRadius = props.borderRadius !== undefined ? props.borderRadius : (theme.borderRadius || 0);
        const borderStyle = `border-radius: ${borderRadius}px;`;
        const bodyFont = theme.bodyFont || theme.font || 'Outfit';
        const headingFont = theme.headingFont || theme.font || 'Outfit';
        const headingWeight = theme.headingWeight || '700';
        const fontStyle = `font-family: '${bodyFont}', sans-serif;`;
        const headingFontStyle = `font-family: '${headingFont}', sans-serif; font-weight: ${headingWeight};`;
        const paddingStyle = props.padding !== undefined ? `padding: ${props.padding}px;` : '';
        const marginStyle = props.margin !== undefined ? `margin: ${props.margin}px;` : '';
        const selectedStyle = isSelected ? 'border: 2px solid #6366f1; box-shadow: 0 0 10px rgba(99,102,241,0.5); transform: scale(1.02); z-index: 10;' : '';
        
        const baseStyles = {
            row: `display: flex; flex-direction: row; gap: ${props.gap || 0}px; min-height: 40px; border: 1px dashed rgba(255,255,255,0.1); width: 100%; ${fontStyle}`,
            column: `display: flex; flex-direction: column; gap: ${props.gap || 0}px; min-height: 40px; border: 1px dashed rgba(255,255,255,0.1); width: 100%; ${fontStyle}`,
            container: `min-height: 40px; border: 1px dashed rgba(255,255,255,0.1); width: 100%; ${fontStyle}`,
            stack: `position: relative; min-height: 100px; border: 1px dashed var(--primary-color); width: 100%; ${fontStyle}`,
            grid_view: `display: grid; grid-template-columns: repeat(${props.cols || 2}, 1fr); gap: 10px; width: 100%; ${fontStyle}`,
            expanded: `flex: 1; min-height: 20px; border: 1px dashed #444; ${fontStyle}`,
            wrap: `display: flex; flex-wrap: wrap; gap: 10px; width: 100%; ${fontStyle}`,
            bottom_nav: `width: 100%; height: 60px; background: #fff; border-top: 1px solid #eee; display: flex; align-items: center; justify-content: space-around; ${fontStyle}`,
            tab_bar: `width: 100%; height: 48px; background: ${theme.primary}; display: flex; align-items: center; ${fontStyle}`,
            drawer: `width: 280px; height: 100%; background: #fff; position: absolute; left: 0; top: 0; box-shadow: 5px 0 15px rgba(0,0,0,0.2); z-index: 1000; ${fontStyle}`,
            app_bar: `width: 100%; height: 56px; background: ${theme.primary}; color: #fff; display: flex; align-items: center; padding: 0 15px; gap: 20px; ${headingFontStyle}`,
            nav_rail: `width: 72px; height: 100%; background: #fff; border-right: 1px solid #eee; display: flex; flex-direction: column; align-items: center; padding-top: 20px; gap: 30px; ${fontStyle}`,
            glass: `background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; width: 100%; min-height: 60px; ${fontStyle}`,
            gradient: `background: linear-gradient(135deg, ${theme.primary}, ${theme.secondary}); border-radius: 12px; width: 100%; min-height: 60px; ${fontStyle}`,
            shimmer: `background: linear-gradient(90deg, #f0f0f0 25%, #f8f8f8 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; width: 100%; min-height: 20px; border-radius: 4px; ${fontStyle}`,
            shadow: `background: #fff; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border-radius: 16px; width: 100%; min-height: 60px; ${fontStyle}`,
            segmented: `display: flex; background: #f1f3f4; padding: 4px; border-radius: 20px; width: 100%; ${headingFontStyle}`,
            rating: `display: flex; gap: 5px; color: #ffc107; font-size: 1.2rem; ${fontStyle}`,
            signature: `width: 100%; height: 150px; background: #fff; border: 1px solid #ddd; border-radius: 8px; position: relative; ${fontStyle}`,
            qr_code: `width: 120px; height: 120px; background: #fff; padding: 10px; border: 1px solid #eee; margin: 0 auto; display: flex; align-items: center; justify-content: center; ${fontStyle}`,
            lottie: `width: 100%; aspect-ratio: 1; background: rgba(0,0,0,0.02); border-radius: 50%; display: flex; align-items: center; justify-content: center; ${fontStyle}`,
            stepper: `width: 100%; padding: 20px 0; ${fontStyle}`,
            expansion_tile: `width: 100%; border-bottom: 1px solid #eee; background: #fff; ${fontStyle}`,
            carousel: `width: 100%; aspect-ratio: 16/9; background: #f0f0f0; overflow: hidden; position: relative; ${fontStyle}`,
            accordion: `width: 100%; border-radius: 8px; border: 1px solid #eee; overflow: hidden; ${fontStyle}`,
            search_bar: `width: 100%; height: 48px; background: #f1f3f4; border-radius: 24px; padding: 0 20px; display: flex; align-items: center; gap: 10px; ${fontStyle}`,
            bar_chart: `width: 100%; height: 150px; background: rgba(0,0,0,0.02); display: flex; align-items: flex-end; gap: 8px; padding: 15px; ${fontStyle}`,
            pie_chart: `width: 120px; height: 120px; border-radius: 50%; background: conic-gradient(${theme.primary} 0% 40%, ${theme.secondary} 40% 70%, #e0e0e0 70% 100%); margin: 0 auto; ${fontStyle}`,
            data_table: `width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #eee; ${fontStyle}`,
            text: `font-size: 1rem; color: #333; width: 100%; ${fontStyle}`,
            button: `background: ${theme.primary}; color: white; text-align: center; font-weight: bold; cursor: pointer; transition: all 0.2s; width: 100%; ${headingFontStyle}`,
            fab: `background: ${theme.secondary}; color: white; width: 56px; height: 56px; border-radius: 28px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); position: absolute; bottom: 20px; right: 20px; z-index: 100; ${headingFontStyle}`,
            icon: `color: ${theme.primary}; font-size: 24px; display: inline-flex; ${fontStyle}`,
            card: `background: white; width: 100%; ${fontStyle}`,
            badge: `background: ${theme.secondary}; color: white; font-size: 0.7rem; display: inline-block; ${headingFontStyle}`,
            avatar: `width: 40px; height: 40px; border-radius: 20px; background: #ddd; overflow: hidden; display: flex; align-items: center; justify-content: center; ${fontStyle}`,
            input: `width: 100%; border: 1px solid #ddd; background: #f9f9f9; font-size: 0.9rem; color: #333; ${fontStyle}`,
            switch: `display: flex; justify-content: space-between; align-items: center; width: 100%; ${fontStyle}`,
            slider: `width: 100%; height: 4px; background: #ddd; position: relative; ${fontStyle}`,
            progress: `width: 100%; height: 8px; background: #eee; overflow: hidden; ${fontStyle}`,
            progress_bar: `width: 100%; height: 6px; background: #eee; border-radius: 3px; position: relative; ${fontStyle}`,
            progress_circle: `width: 40px; height: 40px; border: 4px solid #eee; border-top-color: ${theme.primary}; border-radius: 50%; animation: spin 1s linear infinite; ${fontStyle}`,
            checkbox: `display: flex; align-items: center; gap: 10px; width: 100%; ${fontStyle}`,
            radio: `display: flex; align-items: center; gap: 10px; width: 100%; ${fontStyle}`,
            image: `width: 100%; overflow: hidden; background: #f0f0f0; display: flex; align-items: center; justify-content: center; aspect-ratio: 16/9; ${fontStyle}`,
            video: `width: 100%; aspect-ratio: 16/9; background: #000; display: flex; align-items: center; justify-content: center; color: #fff; border-radius: 8px; ${fontStyle}`,
            audio: `width: 100%; height: 50px; background: #f1f3f4; border-radius: 25px; display: flex; align-items: center; padding: 0 15px; gap: 10px; ${fontStyle}`,
            divider: `width: 100%; height: 1px; background: #eee; ${fontStyle}`,
            spacer: `width: 100%; height: ${props.height || 20}px; ${fontStyle}`
        };

        const isContainer = ['row', 'column', 'container', 'stack', 'grid_view', 'expanded', 'wrap', 'drawer', 'accordion', 'expansion_tile', 'app_bar', 'bottom_nav', 'nav_rail', 'glass', 'gradient', 'shadow', 'card', 'list'].includes(c.type);

        return html`
            <div class="canvas-comp ${isContainer ? 'layout-container' : ''}" 
                data-id="${c.id}"
                style="${baseStyles[c.type] || ''} ${tokenStyle} ${paddingStyle} ${marginStyle} ${borderStyle} ${elevationStyle} ${selectedStyle} ${animationStyle}" 
                @click=${(e) => { e.stopPropagation(); this.onComponentClick(c); }}>
                
                ${isContainer && c.children && c.children.length > 0
                    ? c.children.map(child => this.renderComponent(child, theme))
                    : this.renderComponentContent(c, theme, props)
                }

                ${props.icon ? html`<div class="comp-icon" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:1.1rem; opacity:0.8;">${this.getIconGlyph(props.icon)}</div>` : ''}

                ${isSelected ? html`
                    <div class="delete-hint" style="position:absolute; top:-12px; right:-12px; background:#ff4444; color:white; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem; cursor:pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.3); border:2px solid white; z-index:100;" 
                        @click=${(e) => { e.stopPropagation(); this.removeComponent(c.id); }}>✕</div>
                ` : ''}
            </div>
        `;
    }

    getIconGlyph(name) {
        const icon = this.getIconLibrary().find(i => i.name === name);
        return icon ? icon.glyph : '';
    }

    renderComponentContent(c, theme, props = {}) {
        if (c.type === 'switch') return html`<span>${props.text || 'Switch'}</span> <div style="width:34px; height:20px; background:${theme.primary}; border-radius:10px; position:relative;"><div style="width:16px; height:16px; background:#fff; border-radius:50%; position:absolute; right:2px; top:2px;"></div></div>`;
        if (c.type === 'slider') return html`<div style="width:16px; height:16px; background:${theme.primary}; border-radius:50%; position:absolute; left:40%; top:-6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>`;
        if (c.type === 'progress') return html`<div style="width:60%; height:100%; background:${theme.primary}; border-radius:4px;"></div>`;
        if (c.type === 'progress_bar') return html`<div style="width:45%; height:100%; background:${theme.primary}; border-radius:3px;"></div>`;
        if (c.type === 'bottom_nav') return html`<span>🏠</span><span>🔍</span><span>👤</span>`;
        if (c.type === 'tab_bar') return html`<div style="flex:1; text-align:center; color:white; font-size:0.7rem; font-weight:700;">HOME</div><div style="flex:1; text-align:center; color:rgba(255,255,255,0.6); font-size:0.7rem;">CHATS</div>`;
        if (c.type === 'app_bar') return html`<span>☰</span><span style="flex:1; font-weight:700;">${this.resolveValue(props.text || 'App Bar')}</span><span>🔍</span>`;
        if (c.type === 'nav_rail') return html`<span>🏠</span><span>🔍</span><span>⚙️</span>`;
        if (c.type === 'segmented') return html`<div style="flex:1; background:#fff; border-radius:16px; text-align:center; font-size:0.65rem; padding:4px;">Day</div><div style="flex:1; text-align:center; font-size:0.65rem; padding:4px;">Week</div>`;
        if (c.type === 'rating') return html`<span>⭐</span><span>⭐</span><span>⭐</span><span>⭐</span><span style="opacity:0.3;">⭐</span>`;
        if (c.type === 'signature') return html`<div style="position:absolute; width:100%; height:100%; display:flex; align-items:center; justify-content:center; opacity:0.1; font-size:3rem;">✍️</div>`;
        if (c.type === 'qr_code') return html`<div style="width:100%; height:100%; background:repeating-conic-gradient(#000 0% 25%, #fff 0% 50%) 0/10px 10px;"></div>`;
        if (c.type === 'lottie') return html`<div style="font-size:2rem; animation: bounce 1s infinite;">🎞️</div>`;
        if (c.type === 'expansion_tile') return html`<div style="display:flex; justify-content:space-between; width:100%; padding:10px;"><span>${this.resolveValue(props.text || 'Expansion Tile')}</span><span>▼</span></div>`;
        if (c.type === 'search_bar') return html`<span>🔍</span><span style="opacity:0.5;">Search...</span>`;
        if (c.type === 'bar_chart') return html`<div style="flex:1; height:40%; background:${theme.primary};"></div><div style="flex:1; height:80%; background:${theme.primary};"></div><div style="flex:1; height:60%; background:${theme.primary};"></div><div style="flex:1; height:90%; background:${theme.secondary};"></div>`;
        if (c.type === 'data_table') return html`<tr style="border-bottom:1px solid #eee;"><th style="padding:10px; font-size:0.6rem;">ID</th><th style="padding:10px; font-size:0.6rem;">Name</th></tr><tr><td style="padding:10px; font-size:0.6rem;">001</td><td style="padding:10px; font-size:0.6rem;">Asset A</td></tr>`;
        if (c.type === 'carousel') return html`<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;">🎞️</div><div style="position:absolute; bottom:10px; width:100%; display:flex; justify-content:center; gap:5px;"><div style="width:6px; height:6px; background:${theme.primary}; border-radius:50%;"></div><div style="width:6px; height:6px; background:#ccc; border-radius:50%;"></div></div>`;
        if (c.type === 'fab') return html`<span style="font-size: 24px; font-weight: 300;">${props.icon ? this.getIconGlyph(props.icon) : (props.text || '+')}</span>`;
        if (c.type === 'radio') return html`<div style="width:20px; height:20px; border:2px solid ${theme.primary}; border-radius:50%; display:flex; align-items:center; justify-content:center;"><div style="width:10px; height:10px; background:${theme.primary}; border-radius:50%;"></div></div> <span>${this.resolveValue(props.text || 'Radio Option')}</span>`;
        if (c.type === 'image') return props.src ? html`<img src="${props.src}" style="width:100%; height:100%; object-fit:cover;">` : '🖼️';
        if (c.type === 'video') return html`<div style="font-size: 2rem;">▶️</div>`;
        if (c.type === 'audio') return html`<div style="font-size: 1.2rem;">▶️</div> <div style="flex:1; height:4px; background:rgba(0,0,0,0.1); border-radius:2px; overflow:hidden;"><div style="width:30%; height:100%; background:${theme.primary};"></div></div> <span style="font-size:0.6rem; opacity:0.6;">1:24</span>`;
        if (c.type === 'divider') return html`<div style="width:100%; height:1px; background:#eee;"></div>`;
        if (c.type === 'icon') return html`<div style="font-size: 24px;">✨</div>`;
        if (c.type === 'fab') return html`<div style="font-size: 24px;">➕</div>`;
        if (c.type === 'avatar') return props.src ? html`<img src="${props.src}" style="width:100%; height:100%; object-fit:cover;">` : '👤';
        
        if (['text', 'button', 'badge', 'chip', 'input', 'stepper', 'card', 'list'].includes(c.type)) {
            if (props.text) return this.resolveValue(props.text);
        }

        if (['row', 'column', 'container', 'stack', 'grid_view', 'expanded', 'wrap', 'drawer', 'app_bar', 'bottom_nav', 'nav_rail', 'card', 'list', 'glass', 'gradient', 'shadow', 'accordion', 'expansion_tile'].includes(c.type)) return html`<div class="empty-container-hint" style="opacity: 0.3; font-size: 0.6rem; text-align: center; width: 100%;">Drop components here</div>`;
        return '';
    }

    moveComponent(id, dir) {
        const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        if (!activeScreen) return;
        
        const index = activeScreen.components.findIndex(c => c.id === id);
        if (index === -1) return;
        
        const newIndex = index + dir;
        if (newIndex < 0 || newIndex >= activeScreen.components.length) return;
        
        const temp = activeScreen.components[index];
        activeScreen.components[index] = activeScreen.components[newIndex];
        activeScreen.components[newIndex] = temp;
        this.update();
    }

    selectComponent(c) {
        this.setState({ inspectorTab: 'properties', selectedComponentId: c.id });
    }

    removeComponent(id) {
        let activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        if (activeScreen && activeScreen.components) {
            activeScreen.components = activeScreen.components.filter(c => c.id !== id);
            if (this.state.selectedComponentId === id) this.state.selectedComponentId = null;
            this.update();
        }
    }

    addScreen() {
        const title = "Select Screen Blueprint";
        const content = html`
            <div style="padding: 20px; max-height: 80vh; overflow-y: auto;">
                <div class="mb-4">
                    <h4 style="margin:0 0 15px 0; color:var(--primary-color); font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">✨ Premium Blueprints</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px;">
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('blank')}>
                            <div style="font-size: 1.5rem;">📄</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Blank</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('dashboard')}>
                            <div style="font-size: 1.5rem;">🏠</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Dashboard</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('social_feed')}>
                            <div style="font-size: 1.5rem;">📱</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Social Feed</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('product_details')}>
                            <div style="font-size: 1.5rem;">🛍️</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Product</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('analytics')}>
                            <div style="font-size: 1.5rem;">📊</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Analytics</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('auth')}>
                            <div style="font-size: 1.5rem;">🔐</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Modern Auth</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('chat')}>
                            <div style="font-size: 1.5rem;">💬</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Chat Room</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('profile')}>
                            <div style="font-size: 1.5rem;">👤</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Profile</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('settings')}>
                            <div style="font-size: 1.5rem;">⚙️</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Settings</div>
                        </div>
                        <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('checkout')}>
                            <div style="font-size: 1.5rem;">💳</div>
                            <div style="font-weight:bold; font-size:0.75rem;">Checkout</div>
                        </div>
                    </div>
                </div>

                ${this.state.config.screens.length > 0 ? html`
                <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,0.05);">
                    <h4 style="margin:0 0 15px 0; color:#888; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">👯 Duplicate Existing Screen</h4>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        ${this.state.config.screens.map(s => html`
                            <div class="glass-panel p-2 px-3 pointer" style="display:flex; justify-content:space-between; align-items:center; border-radius:8px;" @click=${() => this.duplicateScreen(s.id)}>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span>📄</span>
                                    <span style="font-size:0.8rem; font-weight:600;">${s.title}</span>
                                </div>
                                <span style="font-size:0.6rem; opacity:0.5; text-transform:uppercase;">Clone</span>
                            </div>
                        `)}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        this.openModal(title, content);
    }

    createScreenWithTemplate(type) {
        const id = 'screen_' + Date.now();
        const screen = { id, title: 'New ' + type.charAt(0).toUpperCase() + type.slice(1), type: type === 'blank' ? 'custom' : type, mapping: '', components: [] };
        this.state.config.screens.push(screen);
        this.applyTemplate(screen, type);
        this.setState({ activeScreenId: id });
        this.closeModal();
        this.notify(`Screen '${screen.title}' created.`, 'success');
    }

    duplicateScreen(screenId) {
        const original = this.state.config.screens.find(s => s.id === screenId);
        if (!original) return;

        const newScreen = JSON.parse(JSON.stringify(original));
        newScreen.id = 'screen_' + Date.now();
        newScreen.title = original.title + ' (Copy)';

        const regenerateIds = (node) => {
            node.id = 'comp_' + Math.random().toString(36).substr(2, 9);
            if (node.children) node.children.forEach(regenerateIds);
        };
        newScreen.components.forEach(regenerateIds);

        this.state.config.screens.push(newScreen);
        this.setState({ activeScreenId: newScreen.id });
        this.closeModal();
        this.update();
        this.notify(`Screen '${original.title}' duplicated.`, 'success');
    }

    applyTemplate(screen, type) {
        const timestamp = Date.now();
        let comps = [];
        
        // 1. Check in External Registry (blueprints.js or Discovered)
        if (window.MobileBlueprints) {
            // Check Layouts (structural)
            if (window.MobileBlueprints.layouts && window.MobileBlueprints.layouts[type]) {
                const layout = window.MobileBlueprints.layouts[type];
                // Support both functional templates and static arrays
                comps = typeof layout === 'function' ? layout(timestamp) : (layout.template ? (typeof layout.template === 'function' ? layout.template(timestamp) : layout.template) : layout);
            } 
            // Check Blueprints (high-fidelity)
            else if (window.MobileBlueprints.blueprints) {
                const bp = window.MobileBlueprints.blueprints.find(b => b.id === type);
                if (bp) {
                    comps = typeof bp.template === 'function' ? bp.template(timestamp) : bp.template;
                }
            }
        }

        // 2. Core Fallback (if registry not loaded or template missing)
        if (comps.length === 0) {
            switch (type) {
                case 'dashboard':
                    comps.push({ id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'Dashboard' } });
                    comps.push({ id: 't_gv' + timestamp, type: 'grid_view', props: { cols: 2 }, children: [
                        { id: 't_c1' + timestamp, type: 'card', props: { text: 'Active Assets' } }
                    ]});
                    break;
                case 'form':
                    comps.push({ id: 't_ab' + timestamp, type: 'app_bar', props: { text: 'New Entry' } });
                    comps.push({ id: 't_bt' + timestamp, type: 'button', props: { text: 'Submit' } });
                    break;
            }
        }
        
        if (comps.length > 0) {
            screen.components = comps;
            this.update();
        }
    }

    async saveConfig() {
        const { config } = this.state;
        const projectId = config.id || config.app_id || 'default';
        
        console.log(`[MobileStudio] Initiating sync for: ${projectId}`);
        
        // 1. Save to Project Directory (JSON format used by Project Portfolio)
        const projectRes = await this.api('save_project', { 
            id: projectId, 
            config: config 
        });

        if (projectRes.success) {
            // 2. Sync to etc/apps (YAML format used by Mobile App runtime)
            const appname = projectId.includes('.') ? projectId.split('.').pop() : projectId;
            await this.api('save_mobile_config', { 
                appname: appname,
                config: config 
            });
            
            this.notify('Project synchronized successfully.', 'success');
        } else {
            this.notify('Failed to save project: ' + projectRes.message, 'error');
        }
    }

    async generateApp(type) {
        this.notify(`Initializing ${type.toUpperCase()} build process...`, 'info');
        const res = await this.api('generate_mobile_app', { type });
        if (!res.success) this.notify(res.message, 'error');
    }

    /**
     * Updates the animation sequence for a specific component.
     * @param {string} compId - The ID of the component to update.
     * @param {Array|string} sequence - The animation sequence or effect name.
     */
    updateAnimationSequence(compId, sequence) {
        const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        if (!activeScreen) return;
        
        const comp = this.findComponentById(activeScreen.components, compId);
        if (comp) {
            comp.props.animation = sequence;
            this.update();
            this.pushHistory();
            console.log(`[Reactivity] Animation updated for ${compId}:`, sequence);
        }
    }

    /**
     * Updates a global state variable and triggers a re-render of bound components.
     * @param {string} key - The state variable key.
     * @param {any} value - The new value.
     */
    updateStateVariable(key, value) {
        const config = { ...this.state.config };
        if (!config.state) config.state = {};
        
        config.state[key] = value;
        this.setState({ config }, () => {
            this.update();
            this.pushHistory();
            console.log(`[Reactivity] State variable '${key}' updated to:`, value);
        });
    }
}
