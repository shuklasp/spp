export default class DocsView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            error: null,
            codebaseData: null,
            activeClass: null,
            expandedCategories: {},
            searchQuery: ''
        };
        await this.fetchData();
    }

    async fetchData() {
        try {
            const res = await this.app.api('get_codebase_structure', {}, { lock: false });
            
            if (res.status === 'success' || res.status === 'ok') {
                this.setState({
                    codebaseData: res.data,
                    loading: false
                });
            } else {
                this.setState({ error: res.message || 'Failed to load codebase structure', loading: false });
            }
        } catch (err) {
            this.setState({ error: err.message, loading: false });
        }
    }

    linkType(typeStr) {
        if (!typeStr || typeof typeStr !== 'string') return html`<span class="param-type">${typeStr}</span>`;
        
        let targetClass = null;
        let shortName = typeStr;
        if (typeStr.includes('\\')) {
            shortName = typeStr.split('\\').pop();
        }

        // Search for the class
        for (const [cat, classes] of Object.entries(this.state.codebaseData || {})) {
            for (const [clsName, clsData] of Object.entries(classes)) {
                if (clsName === typeStr || clsData.name === shortName || '\\' + clsName === typeStr) {
                    targetClass = clsData;
                    break;
                }
            }
            if (targetClass) break;
        }

        if (targetClass) {
            return html`<a @click=${() => this.setState({ activeClass: targetClass })} style="color: #38bdf8; text-decoration: underline; cursor: pointer;" title="${typeStr}">${shortName}</a>`;
        }
        return html`<span class="param-type">${shortName}</span>`;
    }

    buildNestedTree() {
        if (!this.state.codebaseData) return {};
        const tree = { name: 'root', children: {}, classes: [] };
        
        for (const [namespace, classes] of Object.entries(this.state.codebaseData)) {
            // Skip injected HTML or non-object categories (e.g. from warnings caught by LiveAction)
            if (namespace === 'html' || typeof classes !== 'object' || classes === null || Array.isArray(classes)) continue;

            const parts = namespace.split('\\').filter(Boolean);
            
            // Build path
            let current = tree;
            for (const part of parts) {
                if (!current.children[part]) {
                    current.children[part] = { name: part, children: {}, classes: [] };
                }
                current = current.children[part];
            }
            
            // Add classes
            for (const [className, classData] of Object.entries(classes)) {
                classData._full_name = className;
                if (this.state.searchQuery) {
                    const q = this.state.searchQuery;
                    if (!className.toLowerCase().includes(q) && !classData.name.toLowerCase().includes(q)) {
                        continue;
                    }
                }
                current.classes.push(classData);
            }
        }
        
        // Remove empty branches
        const trimEmpty = (node) => {
            let hasContent = node.classes.length > 0;
            for (const childName of Object.keys(node.children)) {
                const childHasContent = trimEmpty(node.children[childName]);
                if (!childHasContent) {
                    delete node.children[childName];
                } else {
                    hasContent = true;
                }
            }
            return hasContent;
        };
        trimEmpty(tree);
        
        return tree;
    }

    toggleNode(path) {
        let expanded = { ...this.state.expandedCategories };
        const isCurrentlyExpanded = expanded[path];
        
        const parts = path.split('\\');
        const parentPath = parts.slice(0, -1).join('\\');
        
        if (!isCurrentlyExpanded) {
            // Collapse siblings
            for (const key of Object.keys(expanded)) {
                if (!expanded[key]) continue;
                const keyParts = key.split('\\');
                const keyParentPath = keyParts.slice(0, -1).join('\\');
                if (keyParentPath === parentPath && key !== path) {
                    delete expanded[key];
                    for (const subKey of Object.keys(expanded)) {
                        if (subKey.startsWith(key + '\\')) delete expanded[subKey];
                    }
                }
            }
            expanded[path] = true;
        } else {
            // Collapse self and children
            delete expanded[path];
            for (const subKey of Object.keys(expanded)) {
                if (subKey.startsWith(path + '\\')) delete expanded[subKey];
            }
        }
        
        this.setState({ expandedCategories: expanded });
    }

    renderTreeNode(node, currentPath = '') {
        let result = [];
        
        // Sub-folders
        const sortedChildren = Object.keys(node.children).sort();
        sortedChildren.forEach(childName => {
            const childNode = node.children[childName];
            const childPath = currentPath ? currentPath + '\\' + childName : childName;
            
            const isExpanded = this.state.searchQuery ? true : (this.state.expandedCategories[childPath] || false);
            
            result.push(html`
                <div class="tree-category" @click=${() => this.toggleNode(childPath)}>
                    <i class="icon" style="margin-right: 5px; font-size: 0.8em; transition: transform 0.2s; transform: ${isExpanded ? 'rotate(90deg)' : 'rotate(0deg)'}">▶</i>
                    <i class="icon" style="margin-right: 5px; color: #facc15;">📁</i>
                    ${childName}
                </div>
                ${isExpanded ? html`
                    <div class="tree-children" style="padding-left: 12px; border-left: 1px solid rgba(255,255,255,0.05); margin-left: 10px;">
                        ${this.renderTreeNode(childNode, childPath)}
                    </div>
                ` : ''}
            `);
        });

        // Classes and Configs
        const sortedClasses = node.classes.sort((a, b) => a.name.localeCompare(b.name));
        sortedClasses.forEach(c => {
            const isActive = this.state.activeClass && this.state.activeClass._full_name === c._full_name;
            const icon = c.type === 'config' ? '⚙️' : '📄';
            result.push(html`
                <div class="tree-item ${isActive ? 'active' : ''}" data-id="${c._full_name}" @click=${() => this.setState({ activeClass: c })}>
                    <i class="icon" style="margin-right: 5px; opacity: 0.7;">${icon}</i> ${c.name}
                </div>
            `);
        });
        
        return result;
    }

    renderTree() {
        if (!this.state.codebaseData) return '';
        const tree = this.buildNestedTree();
        
        return html`
            <div style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <input type="text" placeholder="Search codebase..." .value=${this.state.searchQuery} @input=${e => {
                    this.setState({ searchQuery: e.target.value.toLowerCase() });
                }} style="width: 100%; padding: 10px 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; outline: none; transition: border-color 0.2s;" />
            </div>
            <div style="padding: 10px 0;">
                ${this.renderTreeNode(tree)}
            </div>
        `;
    }

    renderClassViewer() {
        if (!this.state.activeClass) {
            return html`<div style="color: var(--text-secondary); text-align: center; margin-top: 50px;">
                Select a class or configuration from the sidebar to view its details.
            </div>`;
        }

        const c = this.state.activeClass;
        
        if (c.type === 'config') {
            if (!c.content && !c._loadingContent) {
                c._loadingContent = true;
                this.app.api('get_file_content', { file: c.file }, { lock: false }).then(res => {
                    c.content = res.data.content;
                    c._loadingContent = false;
                    this.update();
                });
            }
            return html`
                <h1 style="margin: 0 0 5px 0; font-size: 2.5rem; color: var(--text-primary);">
                    <span style="color: #94a3b8; font-size: 1.2rem; font-weight: normal; vertical-align: middle;">Configuration</span> 
                    ${c.name}
                </h1>
                <div style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px;">
                    <i class="icon">⚙️</i> Configuration File
                    <div style="margin-top: 5px;">File: ${c.file}</div>
                </div>
                <div class="doc-block" style="border-left-color: #f59e0b; font-family: 'JetBrains Mono', monospace; font-size: 0.95rem; line-height: 1.5; background: #0f172a; color: #e2e8f0; overflow-x: auto;">${this.escapeHtml(c.content) || 'Loading content...'}</div>
            `;
        }
        
        return html`
            <div style="color: var(--text-secondary); font-family: monospace; margin-bottom: 5px;">${c.namespace}</div>
            <h1 style="margin: 0 0 5px 0; font-size: 2.5rem; color: var(--text-primary);">
                <span style="color: #94a3b8; font-size: 1.2rem; font-weight: normal; vertical-align: middle;">${c.is_final ? 'final ' : ''}${c.type}</span> 
                ${c.name}
            </h1>
            
            ${c.parent || (c.interfaces && c.interfaces.length) || (c.traits && c.traits.length) ? html`
                <div style="font-family: monospace; font-size: 0.9rem; margin-bottom: 15px; color: #cbd5e1;">
                    ${c.parent ? html`<div><span style="color: #f43f5e;">extends</span> ${this.linkType(c.parent)}</div>` : ''}
                    ${c.interfaces && c.interfaces.length ? html`<div><span style="color: #f43f5e;">implements</span> ${c.interfaces.map((i, idx) => html`${this.linkType(i)}${idx < c.interfaces.length - 1 ? ', ' : ''}`)}</div>` : ''}
                    ${c.traits && c.traits.length ? html`<div><span style="color: #f43f5e;">uses</span> ${c.traits.map((t, idx) => html`${this.linkType(t)}${idx < c.traits.length - 1 ? ', ' : ''}`)}</div>` : ''}
                </div>
            ` : ''}

            <div style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px;">
                <i class="icon">📁</i> ${c.file}
            </div>

            ${c.docblock ? html`<div class="doc-block">${c.docblock}</div>` : ''}

            ${(c.constants && c.constants.length > 0) ? html`
                <h2 style="margin-top: 40px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">Constants</h2>
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 20px;">
                    ${c.constants.map(constObj => html`
                        <div style="margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                            <div style="font-family: monospace; font-size: 1.1rem; color: var(--text-primary);">
                                <span class="badge badge-${constObj.visibility}">${constObj.visibility}</span>
                                ${constObj.inherited_from ? html`<span class="badge badge-inherited" title="Inherited from ${constObj.inherited_from}">inherited</span>` : ''}
                                <span style="color: #38bdf8;">${constObj.name}</span> = <span style="color: #a3e635;">${constObj.value}</span>;
                            </div>
                            ${constObj.docblock ? html`<div class="doc-block" style="margin: 10px 0 0 0; padding: 10px; background: rgba(0,0,0,0.1); border-left-color: var(--text-secondary);">${constObj.docblock}</div>` : ''}
                        </div>
                    `)}
                </div>
            ` : ''}

            ${(c.properties && c.properties.length > 0) ? html`
                <h2 style="margin-top: 40px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">Properties</h2>
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 20px;">
                    ${c.properties.map(prop => html`
                        <div style="margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                            <div style="font-family: monospace; font-size: 1.1rem; color: var(--text-primary);">
                                <span class="badge badge-${prop.visibility}">${prop.visibility}</span>
                                ${prop.static ? html`<span class="badge badge-static">static</span>` : ''}
                                ${prop.inherited_from ? html`<span class="badge badge-inherited" title="Inherited from ${prop.inherited_from}">inherited</span>` : ''}
                                ${this.linkType(prop.type)} <span style="color: #38bdf8;">$${prop.name}</span>
                            </div>
                            ${prop.docblock ? html`<div class="doc-block" style="margin: 10px 0 0 0; padding: 10px; background: rgba(0,0,0,0.1); border-left-color: var(--text-secondary);">${prop.docblock}</div>` : ''}
                        </div>
                    `)}
                </div>
            ` : ''}

            ${(c.methods && c.methods.length > 0) ? html`
                <h2 style="margin-top: 40px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">Methods</h2>
                ${c.methods.map(method => html`
                    <div class="method-card">
                        <h3>
                            <span class="badge badge-${method.visibility}">${method.visibility}</span>
                            ${method.static ? html`<span class="badge badge-static">static</span>` : ''}
                            ${method.inherited_from ? html`<span class="badge badge-inherited" title="Inherited from ${method.inherited_from}">inherited</span>` : ''}
                            
                            ${method.name}(${method.parameters.map((p, idx) => html`${p.type ? html`${this.linkType(p.type)} ` : ''}$${p.name}${p.optional ? ' = null' : ''}${idx < method.parameters.length - 1 ? ', ' : ''}`)}): ${this.linkType(method.return_type)}
                        </h3>
                        ${method.docblock ? html`
                            <div class="doc-block" style="margin: 10px 0 0 0; padding: 10px; background: rgba(0,0,0,0.1); border-left-color: var(--text-secondary);">
                                ${method.docblock}
                            </div>
                        ` : ''}
                    </div>
                `)}
            ` : ''}
        `;
    }

    render() {
        if (this.state.loading && !this.state.codebaseData) {
            return this.renderLoading('Scanning Codebase & Building Documentation...');
        }

        return html`
            <div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="margin:0; font-weight:700;">Code Explorer</h1>
                <button class="btn primary-btn shine-effect" @click=${() => this.fetchData()}>
                    <i class="icon">🔄</i> Refresh Codebase
                </button>
            </div>
            
            <div id="explorer-container" style="display: flex; gap: 20px; height: calc(100vh - 150px);">
                <!-- Sidebar (Tree) -->
                <div class="glass-panel" style="width: 300px; overflow-y: auto; display: flex; flex-direction: column;">
                    ${this.state.error 
                        ? html`<div style="color:var(--danger); padding:20px;">${this.state.error}</div>`
                        : this.renderTree()
                    }
                </div>
                
                <!-- Main Content (Class Details) -->
                <div class="glass-panel" id="class-viewer" style="flex: 1; overflow-y: auto; padding: 30px;">
                    ${this.renderClassViewer()}
                </div>
            </div>

            <style>
                /* Premium Glassmorphism UI */
                .glass-panel {
                    background: rgba(15, 23, 42, 0.4);
                    backdrop-filter: blur(16px);
                    -webkit-backdrop-filter: blur(16px);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
                    border-radius: 12px;
                }
                .glass-panel::-webkit-scrollbar { width: 6px; }
                .glass-panel::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
                .glass-panel::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

                /* Tree Styles */
                .tree-category { font-weight: 600; padding: 10px 12px; color: var(--text-primary); font-size: 0.9rem; letter-spacing: 0.02em; border-bottom: 1px solid rgba(255,255,255,0.03); margin-top: 5px; cursor: pointer; transition: background 0.2s; border-radius: 6px; }
                .tree-category:hover { background: rgba(255,255,255,0.05); }
                .tree-item { padding: 8px 12px; cursor: pointer; color: var(--text-secondary); font-size: 0.9rem; border-left: 3px solid transparent; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 0 6px 6px 0; margin: 2px 0; }
                .tree-item:hover { background: rgba(255,255,255,0.03); color: var(--text-primary); transform: translateX(2px); }
                .tree-item.active { background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.15) 0%, transparent 100%); border-left-color: var(--primary); font-weight: 600; color: #fff; }
                
                /* Details Styles */
                .doc-block { background: rgba(0,0,0,0.2); padding: 20px; border-left: 4px solid var(--primary); margin: 20px 0; border-radius: 8px; font-family: 'JetBrains Mono', monospace; white-space: pre-wrap; color: var(--text-secondary); box-shadow: inset 0 2px 10px rgba(0,0,0,0.1); }
                .method-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); padding: 24px; border-radius: 12px; margin-bottom: 20px; transition: transform 0.2s, box-shadow 0.2s; }
                .method-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); }
                .method-card h3 { margin: 0 0 12px 0; font-family: 'JetBrains Mono', monospace; color: var(--text-primary); font-size: 1.1rem; }
                
                /* Badges */
                .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; margin-right: 10px; letter-spacing: 0.05em; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .badge-public { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
                .badge-protected { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
                .badge-static { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; }
                .badge-inherited { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
                .badge-mixed { background: linear-gradient(135deg, #64748b 0%, #475569 100%); color: white; }
                .param-type { color: var(--primary-light); font-weight: 500; }
            </style>
        `;
    }

    escapeHtml(unsafe) {
        return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
}
