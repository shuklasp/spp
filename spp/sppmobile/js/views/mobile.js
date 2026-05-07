/**
 * Mobile Studio - Visual Mobile Developer Panel
 */
export default class MobileView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            config: {},
            activeScreenId: 'home',
            deviceTheme: 'light',
            deviceType: 'ios',
            inspectorTab: 'properties',
            viewMode: 'studio',
            draggedComponent: null,
            draggedCanvasId: null,
            dragOver: false,
            appState: {},
            assets: [],
            history: [],
            historyIndex: -1,
            contextMenu: { visible: false, x: 0, y: 0, targetId: null }
        };
        await this.fetchData();
        await this.fetchEntities();
        this.pushHistory(); // Initial state
        window.addEventListener('keydown', (e) => this.handleShortcuts(e));
        window.addEventListener('click', () => this.hideContextMenu());
        window.addEventListener('contextmenu', (e) => this.handleGlobalContextMenu(e));
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
        const comp = this.findComponentById(activeScreen.components, id);
        if (comp) {
            const newComp = JSON.parse(JSON.stringify(comp));
            const regenerateIds = (node) => {
                node.id = 'comp_' + Date.now() + '_' + Math.floor(Math.random()*1000);
                if (node.children) node.children.forEach(regenerateIds);
            };
            regenerateIds(newComp);
            this.addComponentToScreen(newComp);
            this.notify('Component duplicated', 'success');
        }
    }

    update() {
        if (typeof super.update === 'function') super.update();
        if (this._historyTimer) clearTimeout(this._historyTimer);
        this._historyTimer = setTimeout(() => this.pushHistory(), 500);
    }

    async fetchData() {
        this.setState({ loading: true });
        const res = await this.api('get_mobile_config');
        if (res.success) {
            this.setState({ 
                config: res.data.config, 
                appState: res.data.config.state || {},
                loading: false 
            });
        } else {
            this.setState({ loading: false, error: res.message });
        }
    }

    async fetchEntities() {
        const res = await this.api('get_entities');
        if (res.success) {
            this.setState({ entities: res.data.entities || [] });
        }
    }

    render() {
        const { loading, config, error, activeScreenId, viewMode } = this.state;
        if (loading) return html`<div class="loading-state">Initializing Mobile Studio...</div>`;
        if (error) return html`<div class="empty-state"><h3>Error</h3><p>${error}</p></div>`;

        if (viewMode === 'assets') return this.renderAssetsView();
        if (viewMode === 'code') return this.renderCodeView();

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
            </style>
            <div class="mobile-studio fade-in">
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
                    
                    <div style="display:flex; gap:10px; align-items:center;">
                        <div class="segmented-control" style="display:flex; background: rgba(0,0,0,0.2); padding: 2px; border-radius: 8px; margin-right: 10px;">
                            <button class="btn ghost-btn btn-sm" style="border:none; width: 32px;" title="Undo (Ctrl+Z)" @click=${() => this.undo()} ?disabled=${this.state.historyIndex <= 0}>↩️</button>
                            <button class="btn ghost-btn btn-sm" style="border:none; width: 32px;" title="Redo (Ctrl+Y)" @click=${() => this.redo()} ?disabled=${this.state.historyIndex >= this.state.history.length - 1}>↪️</button>
                        </div>
                        <button class="btn ghost-btn btn-sm" style="font-size: 0.7rem;" @click=${() => this.generateApp('pwa')}>Sync PWA</button>
                        <button class="btn ghost-btn btn-sm" style="font-size: 0.7rem;" @click=${() => this.generateApp('flutter')}>Build Flutter</button>
                        <button class="btn primary-btn btn-sm px-4" style="font-size: 0.7rem; font-weight: 600;" @click=${() => this.saveConfig()}>Sync & Save</button>
                    </div>
                </header>

                <div class="studio-layout">
                    <!-- Left: Navigator -->
                    <aside class="studio-navigator">
                        <div class="nav-section">
                            <h4>App Screens</h4>
                            <div class="screen-list">
                                ${config.screens.map(s => html`
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
                                    <button class="btn ${this.state.libraryMode !== 'blueprints' ? 'primary-btn' : 'ghost-btn'} btn-xs" style="font-size:0.6rem; padding: 2px 8px;" @click=${() => this.setState({ libraryMode: 'components' })}>Atoms</button>
                                    <button class="btn ${this.state.libraryMode === 'blueprints' ? 'primary-btn' : 'ghost-btn'} btn-xs" style="font-size:0.6rem; padding: 2px 8px;" @click=${() => this.setState({ libraryMode: 'blueprints' })}>Blueprints</button>
                                </div>
                            </div>
                            
                            <div class="search-bar mb-3" style="position:relative;">
                                <input type="text" placeholder="Search components..." 
                                    style="width:100%; padding:6px 10px 6px 30px; font-size:0.7rem; border-radius:6px; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); color:var(--text-main);"
                                    @input=${(e) => this.setState({ librarySearch: e.target.value.toLowerCase() })}>
                                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:0.7rem; opacity:0.5;">🔍</span>
                            </div>

                            ${this.state.libraryMode === 'blueprints' ? html`
                                <div class="blueprint-list">
                                    ${this.getBlueprints().map(bp => html`
                                        <div class="blueprint-item glass-panel p-2 mb-2 pointer" @click=${() => this.addBlueprintToScreen(bp)} style="border: 1px solid rgba(255,255,255,0.05);">
                                            <div style="font-weight:bold; font-size:0.75rem; color:var(--primary-color);">${bp.name}</div>
                                            <div style="font-size:0.6rem; opacity:0.6;">${bp.description}</div>
                                        </div>
                                    `)}
                                </div>
                            ` : html`
                                ${this.getComponentLibrary().map(group => {
                                    const filteredItems = group.items.filter(c => !this.state.librarySearch || c.name.toLowerCase().includes(this.state.librarySearch));
                                    if (filteredItems.length === 0) return '';
                                    return html`
                                        <div class="comp-group-label mt-3 mb-1" style="font-size: 0.55rem; text-transform: uppercase; opacity: 0.5; letter-spacing: 1px; font-weight: bold;">${group.group}</div>
                                        <div class="component-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            ${filteredItems.map(c => html`
                                                <div class="comp-tool glass-panel" draggable="true"
                                                    @dragstart=${(e) => this.onDragStart(e, c)}
                                                    style="padding: 10px; cursor: grab; text-align: center; border: 1px solid rgba(255,255,255,0.05); transition: transform 0.2s;">
                                                    <div style="font-size: 1.2rem;">${c.icon}</div>
                                                    <div style="font-size: 0.6rem; margin-top: 4px; font-weight: 500;">${c.name}</div>
                                                </div>
                                            `)}
                                        </div>
                                    `;
                                })}
                            `}
                        </div>
                    </aside>

                    <!-- Center: Preview -->
                    <main class="studio-preview" style="border-radius: 0;">
                        
                        <div class="device-frame ${this.state.deviceType}" id="device-drop-target"
                            @dragenter=${(e) => e.preventDefault()}
                            @dragover=${(e) => { e.dataTransfer.dropEffect = 'copy'; const t = e.target.closest('.device-frame'); if (t) t.classList.add('drag-over'); }}
                            @dragleave=${(e) => { const t = e.target.closest('.device-frame'); if (t && !t.contains(e.relatedTarget)) t.classList.remove('drag-over'); }}
                            @drop=${(e) => this.onDrop(e)}>
                            <div class="device-screen">
                                <div class="mock-status-bar">
                                    <span>9:41</span>
                                    <span>📶 🔋</span>
                                </div>
                                <div class="mock-app-bar" style="background: ${config.theme.primary}">
                                    ${activeScreen.title}
                                </div>
                                <div class="mock-content" id="canvas-content">
                                    ${this.renderMockContent(activeScreen)}
                                </div>
                                <div class="mock-fab" style="background: ${config.theme.secondary}" @click=${() => this.onComponentClick({type: 'fab', actions: activeScreen.actions || []})}></div>
                            </div>
                        </div>
                    </main>

                    <!-- Right: Inspector -->
                    <aside class="studio-inspector">
                        <div class="tab-bar-secondary" style="margin-bottom: 15px; display: flex; gap: 0; background: rgba(0,0,0,0.3); padding: 2px; border-radius: 6px;">
                            <button class="sub-tab-btn ${this.state.inspectorTab === 'layers' ? 'active' : ''}" @click=${() => this.setState({ inspectorTab: 'layers' })}>Layers</button>
                            <button class="sub-tab-btn ${this.state.inspectorTab === 'properties' ? 'active' : ''}" @click=${() => this.setState({ inspectorTab: 'properties' })}>Props</button>
                            <button class="sub-tab-btn ${this.state.inspectorTab === 'actions' ? 'active' : ''}" @click=${() => this.setState({ inspectorTab: 'actions' })}>Actions</button>
                            <button class="sub-tab-btn ${this.state.inspectorTab === 'data' ? 'active' : ''}" @click=${() => this.setState({ inspectorTab: 'data' })}>Data</button>
                            <button class="sub-tab-btn ${this.state.inspectorTab === 'state' ? 'active' : ''}" @click=${() => this.setState({ inspectorTab: 'state' })}>State</button>
                        </div>

                        ${this.state.inspectorTab === 'layers' ? this.renderLayersInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'properties' ? this.renderPropertiesInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'actions' ? this.renderActionsInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'data' ? this.renderDataInspector(activeScreen) : ''}
                        ${this.state.inspectorTab === 'state' ? this.renderStateInspector() : ''}
                    </aside>
                </div>

                ${this.state.contextMenu.visible ? this.renderContextMenu() : ''}
            </div>
        `;
    }

    renderContextMenu() {
        const { x, y, targetId } = this.state.contextMenu;
        const activeScreen = this.state.config.screens.find(s => s.id === this.state.activeScreenId);
        const comp = this.findComponentById(activeScreen.components, targetId);
        
        return html`
            <div class="glass-panel context-menu fade-in" style="position: fixed; top: ${y}px; left: ${x}px; z-index: 9999; min-width: 160px; padding: 5px; border-radius: 10px; background: rgba(15, 15, 25, 0.95); box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
                <div class="menu-item" @click=${() => this.duplicateComponent(targetId)}>📑 Duplicate <span style="float:right; opacity:0.5; font-size:0.6rem;">Ctrl+D</span></div>
                <div class="menu-item" @click=${() => { comp.props.hidden = !comp.props.hidden; this.update(); }}>${comp.props?.hidden ? '👁️ Show' : '👁️‍🗨️ Hide'}</div>
                <div class="menu-divider" style="height:1px; background:rgba(255,255,255,0.05); margin:5px 0;"></div>
                <div class="menu-item" @click=${() => this.moveComponent(targetId, -1)}>↑ Move Up</div>
                <div class="menu-item" @click=${() => this.moveComponent(targetId, 1)}>↓ Move Down</div>
                <div class="menu-divider" style="height:1px; background:rgba(255,255,255,0.05); margin:5px 0;"></div>
                <div class="menu-item danger" @click=${() => this.removeComponent(targetId)} style="color: #ff5555;">🗑️ Delete <span style="float:right; opacity:0.5; font-size:0.6rem;">Del</span></div>
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
        return components.map(c => html`
            <div class="layer-item layer-depth-${depth} ${this.state.selectedComponentId === c.id ? 'active' : ''}"
                data-id="${c.id}"
                @click=${(e) => { e.stopPropagation(); this.selectComponent(c); }}
                style="${c.props?.hidden ? 'opacity: 0.5;' : ''} display: flex; align-items: center; gap: 8px;">
                <span class="layer-visibility" @click=${(e) => { e.stopPropagation(); c.props.hidden = !c.props.hidden; this.update(); }} style="cursor:pointer; font-size: 0.8rem; width: 20px; display: flex; justify-content: center;">
                    ${c.props?.hidden ? '👁️‍🗨️' : '👁️'}
                </span>
                <span class="layer-icon" style="opacity:0.7; width: 20px; display: flex; justify-content: center;">${this.getComponentIcon(c.type)}</span>
                <span class="layer-name" style="flex: 1;">${c.props?.text || c.name || c.type}</span>
                <span class="layer-type">${c.type}</span>
            </div>
            ${c.children && c.children.length > 0 ? this.renderComponentTree(c.children, depth + 1) : ''}
        `);
    }

    getComponentIcon(type) {
        const icons = {
            row: '↔️', column: '↕️', container: '📦', text: '📝', button: '🔘', 
            image: '🖼️', card: '🃏', badge: '🏷️', input: '⌨️', list: '📜',
            spacer: '📏', divider: '➖', switch: '🔘', progress: '⏳'
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
                        <label>Label / Text Content</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" style="flex:1" value="${selectedComp.props.text || ''}" @input=${(e) => { selectedComp.props.text = e.target.value; this.update(); }}>
                            <button class="btn ghost-btn btn-sm" title="Pick Icon" @click=${() => this.openIconPicker(selectedComp)}>${selectedComp.props.icon ? html`✨` : '➕ Icon'}</button>
                        </div>
                        ${selectedComp.props.icon ? html`<div class="mt-1" style="font-size:0.6rem; color:var(--primary-color);">Active Icon: ${selectedComp.props.icon} <span class="pointer" style="margin-left:5px; color:#ff4444;" @click=${() => { delete selectedComp.props.icon; this.update(); }}>remove</span></div>` : ''}
                    </div>
                    ` : ''}
                    
                    ${selectedComp.type === 'image' ? html`
                    <div class="input-group mt-3">
                        <label>Image Source (URL)</label>
                        <input type="text" value="${selectedComp.props.src || ''}" placeholder="https://..." @input=${(e) => { selectedComp.props.src = e.target.value; this.update(); }}>
                    </div>
                    ` : ''}

                    ${selectedComp.type === 'spacer' ? html`
                    <div class="input-group mt-3">
                        <label>Height (px)</label>
                        <input type="number" value="${selectedComp.props.height || 20}" @input=${(e) => { selectedComp.props.height = parseInt(e.target.value); this.update(); }}>
                    </div>
                    ` : ''}

                    ${selectedComp.type === 'slider' ? html`
                    <div class="input-group mt-3" style="display:flex; gap:10px;">
                        <div style="flex:1">
                            <label>Min</label>
                            <input type="number" value="${selectedComp.props.min || 0}" @input=${(e) => { selectedComp.props.min = parseInt(e.target.value); this.update(); }}>
                        </div>
                        <div style="flex:1">
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

                    <div class="style-group mt-4 p-3 glass-panel" style="background: rgba(255,255,255,0.02); border-radius: 12px;">
                        <label style="font-size: 0.6rem; text-transform: uppercase; opacity: 0.6; font-weight: bold; letter-spacing: 0.5px;">Layout & Styling</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px;">
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
                <h3>Theme Palette</h3>
                <div class="color-grid">
                    <div class="color-input-wrap">
                        <label>Primary</label>
                        <input type="color" value="${config.theme.primary}" @change=${(e) => { config.theme.primary = e.target.value; this.update(); }}>
                    </div>
                    <div class="color-input-wrap">
                        <label>Secondary</label>
                        <input type="color" value="${config.theme.secondary}" @change=${(e) => { config.theme.secondary = e.target.value; this.update(); }}>
                    </div>
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

    renderStateInspector() {
        const { config } = this.state;
        if (!config.state) config.state = {};
        const keys = Object.keys(config.state);

        return html`
            <div class="inspector-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3 style="margin:0;">Live State</h3>
                    <button class="btn primary-btn btn-xs" @click=${() => { const name = prompt("Variable Name:"); if (name) { config.state[name] = ""; this.update(); } }}>+ New</button>
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

    getBlueprints() {
        return [
            {
                name: 'Modern Hero Header',
                description: 'Full-width image with title, subtitle, and primary action.',
                structure: {
                    type: 'container',
                    props: { padding: 0, borderRadius: 0 },
                    children: [
                        { type: 'image', props: { src: 'https://picsum.photos/800/400', borderRadius: 0 } },
                        { type: 'column', props: { padding: 20, gap: 10 }, children: [
                            { type: 'text', props: { text: 'Explore the Future', fontSize: 24, fontWeight: 'bold' } },
                            { type: 'text', props: { text: 'Experience next-gen mobility with SPP Mobile Pro.', opacity: 0.7 } },
                            { type: 'button', props: { text: 'Get Started Now' } }
                        ]}
                    ]
                }
            },
            {
                name: 'User Profile Section',
                description: 'Circular avatar with user info and stats row.',
                structure: {
                    type: 'card',
                    props: { padding: 20 },
                    children: [
                        { type: 'row', props: { gap: 15, alignItems: 'center' }, children: [
                            { type: 'container', props: { width: 60, height: 60, borderRadius: 30, padding: 0 }, children: [{ type: 'image', props: { src: 'https://i.pravatar.cc/150?u=spp', borderRadius: 30 } }] },
                            { type: 'column', children: [
                                { type: 'text', props: { text: 'Alex Johnson', fontWeight: 'bold' } },
                                { type: 'badge', props: { text: 'PRO MEMBER' } }
                            ]}
                        ]},
                        { type: 'spacer', props: { height: 20 } },
                        { type: 'row', props: { gap: 20, justifyContent: 'space-around' }, children: [
                            { type: 'column', children: [{ type: 'text', props: { text: '1.2k', fontWeight: 'bold' } }, { type: 'text', props: { text: 'Followers', fontSize: 10 } }] },
                            { type: 'column', children: [{ type: 'text', props: { text: '85', fontWeight: 'bold' } }, { type: 'text', props: { text: 'Projects', fontSize: 10 } }] }
                        ]}
                    ]
                }
            },
            {
                name: 'Contact Form Blueprint',
                description: 'Title, Input fields for name/email, and a toggle switch.',
                structure: {
                    type: 'column',
                    props: { padding: 15, gap: 15 },
                    children: [
                        { type: 'text', props: { text: 'Send us a message', fontWeight: 'bold' } },
                        { type: 'input', props: { text: 'Your Name' } },
                        { type: 'input', props: { text: 'Email Address' } },
                        { type: 'switch', props: { text: 'Subscribe to newsletter' } },
                        { type: 'button', props: { text: 'Send Message' } }
                    ]
                }
            }
        ];
    }

    addBlueprintToScreen(blueprint) {
        this.addComponentToScreen(blueprint.structure);
        this.notify(`Applied ${blueprint.name} blueprint.`, 'success');
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

    addDesignToken() {
        const name = prompt("Token Name (e.g. PrimaryButton, CardHeader):");
        if (name) {
            if (!this.state.config.tokens) this.state.config.tokens = {};
            this.state.config.tokens[name] = "background: var(--primary); color: #fff; padding: 10px; border-radius: 8px;";
            this.update();
        }
    }

    openActionBuilder(comp) {
        const title = "Action Pipeline Builder";
        const content = html`
            <div class="p-3">
                <div class="input-group mb-4">
                    <label>Event Trigger</label>
                    <select id="action-trigger">
                        <option value="onTap">👆 On Tap (Click)</option>
                        <option value="onLongPress">🕒 On Long Press</option>
                        <option value="onSwipeRight">➡️ On Swipe Right</option>
                        <option value="onLoad">🚀 On Screen Load</option>
                    </select>
                </div>
                
                <div class="pipeline-designer glass-panel p-3 mb-4" style="background:rgba(0,0,0,0.2);">
                    <h4 style="margin:0 0 10px 0; font-size:0.8rem;">Step 1: Action Type</h4>
                    <select id="action-type" style="width:100%; margin-bottom:15px;">
                        <option value="navigate">Navigate to Screen</option>
                        <option value="setState">Update Global State</option>
                        <option value="callApi">Call Backend API</option>
                        <option value="notify">Show Notification</option>
                    </select>
                    
                    <div class="input-group">
                        <label>Target / Parameter</label>
                        <input type="text" id="action-target" placeholder="e.g. screen_id, var_name=value..." style="width:100%;">
                    </div>
                </div>
                
                <div class="text-dim mb-3" style="font-size:0.65rem;">
                    Advanced Pro Tip: You can chain multiple actions together to build complex business logic.
                </div>
            </div>
        `;
        
        SPPUX.Modal.open(title, content, [
            { label: 'Cancel', type: 'ghost', fn: 'close' },
            { label: 'Save Pipeline', type: 'primary', fn: () => {
                const trigger = document.getElementById('action-trigger').value;
                const type = document.getElementById('action-type').value;
                const target = document.getElementById('action-target').value;
                
                if (!comp.actions) comp.actions = [];
                comp.actions.push({ trigger, steps: [{type, target}] });
                this.update();
                SPPUX.Modal.close();
            }}
        ]);
    }

    onComponentClick(comp) {
        // Live Action Pipeline support
        if (comp.actions && comp.actions.length > 0) {
            const onTapAction = comp.actions.find(a => a.trigger === 'onTap' || a.type === 'navigate');
            if (onTapAction) {
                const steps = onTapAction.steps || [{type: onTapAction.type, target: onTapAction.target}];
                steps.forEach(step => this.executeAction(step));
                return;
            }
        }
        // If not executing actions, select for editing
        if (comp.id) this.setState({ selectedComponentId: comp.id });
    }

    saveAction(comp, modal) {
        this.notify("Action Pipeline updated.", "success");
        if (modal && modal.close) modal.close();
    }

    executeAction(action) {
        if (!action) return;
        switch (action.type) {
            case 'navigate':
                if (action.target) {
                    const targetScreen = this.state.config.screens.find(s => s.id === action.target);
                    if (targetScreen) {
                        this.setState({ activeScreenId: action.target });
                        this.notify(`Navigated to ${targetScreen.title}`, 'info');
                    } else {
                        this.notify(`Screen '${action.target}' not found.`, 'error');
                    }
                }
                break;
            case 'api':
                if (action.target) {
                    this.notify(`Calling LiveService: ${action.target}...`, 'info');
                    this.api(action.target).then(res => {
                        if (res.success) {
                            this.notify(`Service '${action.target}' responded OK.`, 'success');
                        } else {
                            this.notify(`Service error: ${res.message}`, 'error');
                        }
                    });
                }
                break;
            case 'state':
                if (action.target) {
                    try {
                        const [key, val] = action.target.split('=');
                        if (key && this.state.config.state) {
                            this.state.config.state[key.trim()] = (val || '').trim();
                            this.update();
                            this.notify(`State '${key.trim()}' updated.`, 'success');
                        }
                    } catch (e) {
                        this.notify('Invalid state target format. Use key=value.', 'error');
                    }
                }
                break;
            default:
                this.notify(`Unknown action type: ${action.type}`, 'error');
        }
    }

    setViewMode(mode) {
        this.setState({ viewMode: mode });
        if (mode === 'assets') this.fetchAssets();
    }

    async fetchAssets() {
        const res = await this.api('get_assets');
        if (res.success) {
            this.setState({ assets: res.data.assets || [] });
        }
    }

    triggerAssetUpload() {
        const input = document.getElementById('asset-file-input');
        if (input) input.click();
    }

    async uploadAsset(e) {
        const file = e.target.files?.[0];
        if (!file) return;

        this.notify(`Uploading ${file.name}...`, 'info');

        const fd = new FormData();
        fd.append('asset_file', file);
        fd.append('action', 'upload_asset');

        try {
            const res = await fetch('api.php?action=upload_asset', {
                method: 'POST',
                body: fd
            });
            const json = await res.json();
            if (json.success) {
                this.notify(json.message || 'Asset uploaded.', 'success');
                await this.fetchAssets();
            } else {
                this.notify(json.message || 'Upload failed.', 'error');
            }
        } catch (err) {
            this.notify(`Upload error: ${err.message}`, 'error');
        }

        // Reset input so same file can be re-uploaded
        e.target.value = '';
    }

    renderAssetsView() {
        const { assets = [] } = this.state;
        return html`
            <div class="assets-view p-4 fade-in">
                <div class="header-row mb-4" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>Asset Manager</h2>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn ghost-btn" @click=${() => this.fetchAssets()}>🔄 Refresh</button>
                        <button class="btn primary-btn" @click=${() => this.triggerAssetUpload()}>Upload Asset</button>
                        <input type="file" id="asset-file-input" accept="image/*" style="display:none" @change=${(e) => this.uploadAsset(e)}>
                    </div>
                </div>
                
                <div class="asset-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px;">
                    ${assets.map(a => html`
                        <div class="asset-card glass-panel p-3 text-center">
                            <div class="asset-preview" style="height:100px; background:rgba(255,255,255,0.05); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; overflow:hidden;">
                                <img src="${a.url}" style="max-width:100%; max-height:100%; object-fit:contain;">
                            </div>
                            <div class="asset-name" style="font-size:0.75rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${a.name}</div>
                            <div class="asset-meta" style="font-size:0.6rem; color:var(--text-dim);">${(a.size / 1024).toFixed(1)} KB</div>
                        </div>
                    `)}
                    
                    ${assets.length === 0 ? html`
                        <div class="glass-panel p-5 text-center" style="grid-column: 1 / -1;">
                            <div style="font-size: 3rem; margin-bottom: 20px;">📁</div>
                            <p class="text-dim">No assets found for this application.</p>
                            <button class="btn ghost-btn mt-3" @click=${() => this.fetchAssets()}>🔄 Refresh</button>
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
        return [
            { group: 'Layout', items: [
                { name: 'Row', type: 'row', icon: '↔️' },
                { name: 'Column', type: 'column', icon: '↕️' },
                { name: 'Container', type: 'container', icon: '📦' }
            ]},
            { group: 'Basic', items: [
                { name: 'Text', type: 'text', icon: '📝' },
                { name: 'Button', type: 'button', icon: '🔘' },
                { name: 'Image', type: 'image', icon: '🖼️' },
                { name: 'Divider', type: 'divider', icon: '➖' },
                { name: 'Spacer', type: 'spacer', icon: '↕️' }
            ]},
            { group: 'Forms', items: [
                { name: 'Input', type: 'input', icon: '⌨️' },
                { name: 'Switch', type: 'switch', icon: '🔘' },
                { name: 'Slider', type: 'slider', icon: '📏' },
                { name: 'Checkbox', type: 'checkbox', icon: '☑️' }
            ]},
            { group: 'Layout', items: [
                { name: 'Card', type: 'card', icon: '🎴' },
                { name: 'List', type: 'list', icon: '📋' },
                { name: 'Container', type: 'container', icon: '📦' }
            ]},
            { group: 'Feedback', items: [
                { name: 'Progress', type: 'progress', icon: '⏳' },
                { name: 'Badge', type: 'badge', icon: '🔴' },
                { name: 'Chip', type: 'chip', icon: '🏷️' }
            ]}
        ];
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

    getScreenIcon(type) {
        const icons = { dashboard: '🏠', form: '📝', list: '📋', profile: '👤', settings: '⚙️' };
        return icons[type] || '📄';
    }

    renderMockContent(screen) {
        return html`
            <div class="canvas-render" id="canvas-render" style="display: flex; flex-direction: column; gap: 15px; min-height: 200px;" @click=${(e) => { if (e.target.id === 'canvas-render') this.setState({selectedComponentId: null}); }}>
                ${screen.components && screen.components.length > 0 
                    ? screen.components.map(c => this.renderComponent(c))
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

    findComponentById(list, id) {
        if (!list) return null;
        for (let c of list) {
            if (c.id === id) return c;
            if (c.children) {
                const found = this.findComponentById(c.children, id);
                if (found) return found;
            }
        }
        return null;
    }

    renderComponent(c) {
        const theme = this.state.config.theme;
        const { deviceType, config } = this.state;

        // Visibility Check
        if (c.props.hidden) return '';
        if (c.props.visibility === 'ios_only' && deviceType !== 'ios') return '';
        if (c.props.visibility === 'android_only' && deviceType !== 'android') return '';

        const isSelected = this.state.selectedComponentId === c.id;
        
        // Design Token / Global Styles
        let tokenStyle = '';
        if (c.props.designToken && config.tokens && config.tokens[c.props.designToken]) {
            tokenStyle = config.tokens[c.props.designToken];
        }

        // Advanced Styles Injection
        const elevationStyle = c.props.elevation ? `box-shadow: 0 ${c.props.elevation}px ${c.props.elevation * 2}px rgba(0,0,0,0.15);` : '';
        const borderStyle = c.props.borderRadius !== undefined ? `border-radius: ${c.props.borderRadius}px;` : '';
        const paddingStyle = c.props.padding !== undefined ? `padding: ${c.props.padding}px;` : '';
        const marginStyle = c.props.margin !== undefined ? `margin: ${c.props.margin}px;` : '';
        const selectedStyle = isSelected ? 'border: 2px solid #6366f1; box-shadow: 0 0 10px rgba(99,102,241,0.5); transform: scale(1.02); z-index: 10;' : '';
        
        const baseStyles = {
            row: `display: flex; flex-direction: row; gap: ${c.props.gap || 0}px; min-height: 40px; border: 1px dashed rgba(255,255,255,0.1); width: 100%;`,
            column: `display: flex; flex-direction: column; gap: ${c.props.gap || 0}px; min-height: 40px; border: 1px dashed rgba(255,255,255,0.1); width: 100%;`,
            container: `min-height: 40px; border: 1px dashed rgba(255,255,255,0.1); width: 100%;`,
            text: 'font-size: 1rem; color: #333; width: 100%;',
            button: `background: ${theme.primary}; color: var(--text-bright); text-align: center; font-weight: bold; cursor: pointer; transition: all 0.2s; width: 100%;`,
            card: 'background: white; width: 100%;',
            badge: `background: ${theme.secondary}; color: var(--text-bright); font-size: 0.7rem; display: inline-block;`,
            chip: 'background: #e0e0e0; color: #666; font-size: 0.75rem; display: inline-block;',
            input: 'width: 100%; border: 1px solid #ddd; background: #f9f9f9; font-size: 0.9rem; color: #333;',
            list: 'background: white; width: 100%;',
            switch: 'display: flex; justify-content: space-between; align-items: center; width: 100%;',
            slider: 'width: 100%; height: 4px; background: #ddd; position: relative;',
            progress: 'width: 100%; height: 8px; background: #eee; overflow: hidden;',
            checkbox: 'display: flex; align-items: center; gap: 10px; width: 100%;',
            image: 'width: 100%; overflow: hidden; background: #f0f0f0; display: flex; align-items: center; justify-content: center; aspect-ratio: 16/9;',
            divider: 'width: 100%; height: 1px; background: #eee;',
            spacer: `width: 100%; height: ${c.props.height || 20}px;`
        };

        const isContainer = ['row', 'column', 'container'].includes(c.type);

        return html`
            <div class="canvas-comp ${isContainer ? 'layout-container' : ''}" 
                data-id="${c.id}"
                style="${baseStyles[c.type] || ''} ${tokenStyle} ${paddingStyle} ${marginStyle} ${borderStyle} ${elevationStyle} ${selectedStyle}" 
                @click=${(e) => { e.stopPropagation(); this.onComponentClick(c); }}>
                
                ${isContainer && c.children && c.children.length > 0
                    ? c.children.map(child => this.renderComponent(child))
                    : this.renderComponentContent(c, theme)
                }

                ${c.props.icon ? html`<div class="comp-icon" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:1.1rem; opacity:0.8;">${this.getIconGlyph(c.props.icon)}</div>` : ''}

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

    renderComponentContent(c, theme) {
        if (c.type === 'switch') return html`<span>${c.props.text || 'Switch'}</span> <div style="width:34px; height:20px; background:${theme.primary}; border-radius:10px; position:relative;"><div style="width:16px; height:16px; background:#fff; border-radius:50%; position:absolute; right:2px; top:2px;"></div></div>`;
        if (c.type === 'slider') return html`<div style="width:16px; height:16px; background:${theme.primary}; border-radius:50%; position:absolute; left:40%; top:-6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>`;
        if (c.type === 'progress') return html`<div style="width:60%; height:100%; background:${theme.primary}; border-radius:4px;"></div>`;
        if (c.type === 'checkbox') return html`<div style="width:20px; height:20px; border:2px solid ${theme.primary}; border-radius:4px; display:flex; align-items:center; justify-content:center; color:${theme.primary}; font-size:12px; background:rgba(255,255,255,0.1);">✓</div> <span>${c.props.text || 'Checkbox'}</span>`;
        if (c.type === 'image') return c.props.src ? html`<img src="${c.props.src}" style="width:100%; height:100%; object-fit:cover;">` : '🖼️';
        if (c.type === 'divider') return html`<div style="width:100%; height:1px; background:#eee;"></div>`;
        if (['text', 'button', 'card', 'badge', 'chip', 'input', 'list'].includes(c.type)) return c.props.text || c.name || c.type;
        if (['row', 'column', 'container'].includes(c.type)) return html`<div class="empty-container-hint" style="opacity: 0.3; font-size: 0.6rem; text-align: center; width: 100%;">Drop components here</div>`;
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
        const title = "Select Screen Template";
        const content = html`
            <div class="p-3" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('blank')}>
                    <div style="font-size: 2rem;">📄</div>
                    <div class="mt-2" style="font-weight:bold;">Blank Canvas</div>
                    <div class="text-dim" style="font-size:0.6rem;">Pure freedom. Start from scratch.</div>
                </div>
                <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('dashboard')}>
                    <div style="font-size: 2rem;">🏠</div>
                    <div class="mt-2" style="font-weight:bold;">Dashboard</div>
                    <div class="text-dim" style="font-size:0.6rem;">Grid-based analytics or menu layout.</div>
                </div>
                <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('form')}>
                    <div style="font-size: 2rem;">📝</div>
                    <div class="mt-2" style="font-weight:bold;">Input Form</div>
                    <div class="text-dim" style="font-size:0.6rem;">Pre-built fields and submit button.</div>
                </div>
                <div class="template-option glass-panel p-3 text-center pointer" @click=${() => this.createScreenWithTemplate('details')}>
                    <div style="font-size: 2rem;">👤</div>
                    <div class="mt-2" style="font-weight:bold;">Item Details</div>
                    <div class="text-dim" style="font-size:0.6rem;">Image header and info sections.</div>
                </div>
            </div>
        `;
        SPPUX.Modal.open(title, content, [{ label: 'Cancel', type: 'ghost', fn: 'close' }]);
    }

    createScreenWithTemplate(type) {
        const id = 'screen_' + Date.now();
        const screen = { id, title: 'New ' + type.charAt(0).toUpperCase() + type.slice(1), type: type === 'blank' ? 'custom' : type, mapping: '' };
        this.state.config.screens.push(screen);
        this.applyTemplate(screen, type);
        this.setState({ activeScreenId: id });
        this.closeModal();
    }

    applyTemplate(screen, type) {
        const timestamp = Date.now();
        const comps = [];
        
        switch (type) {
            case 'dashboard':
                comps.push({ id: 't_t' + timestamp, type: 'text', props: { text: 'Control Panel' }, actions: [] });
                comps.push({ id: 't_c1' + timestamp, type: 'card', props: { text: 'Active Users' }, actions: [] });
                comps.push({ id: 't_c2' + timestamp, type: 'card', props: { text: 'System Load' }, actions: [] });
                comps.push({ id: 't_p' + timestamp, type: 'progress', props: { text: 'Usage' }, actions: [] });
                break;
            case 'form':
                comps.push({ id: 't_t' + timestamp, type: 'text', props: { text: 'Submit Request' }, actions: [] });
                comps.push({ id: 't_i1' + timestamp, type: 'input', props: { text: 'Full Name' }, actions: [] });
                comps.push({ id: 't_i2' + timestamp, type: 'input', props: { text: 'Description' }, actions: [] });
                comps.push({ id: 't_s' + timestamp, type: 'switch', props: { text: 'Priority' }, actions: [] });
                comps.push({ id: 't_b' + timestamp, type: 'button', props: { text: 'Submit Now' }, actions: [] });
                break;
            case 'list':
                comps.push({ id: 't_i' + timestamp, type: 'input', props: { text: 'Search...' }, actions: [] });
                comps.push({ id: 't_l' + timestamp, type: 'list', props: { text: 'Browse Items' }, actions: [] });
                break;
            case 'details':
                comps.push({ id: 't_im' + timestamp, type: 'image', props: { text: 'Header', src: 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=800' }, actions: [] });
                comps.push({ id: 't_t1' + timestamp, type: 'text', props: { text: 'Main Title' }, actions: [] });
                comps.push({ id: 't_t2' + timestamp, type: 'text', props: { text: 'Detailed description goes here...' }, actions: [] });
                comps.push({ id: 't_b' + timestamp, type: 'button', props: { text: 'Contact Me' }, actions: [] });
                break;
        }
        
        screen.components = comps;
        this.update();
    }

    async saveConfig() {
        const res = await this.api('save_mobile_config', { config: this.state.config });
        if (res.success) {
            this.notify('Mobile project saved successfully.', 'success');
        }
    }

    async generateApp(type) {
        this.notify(`Initializing ${type.toUpperCase()} build process...`, 'info');
        const res = await this.api('generate_mobile_app', { type });
        if (!res.success) this.notify(res.message, 'error');
    }
}
