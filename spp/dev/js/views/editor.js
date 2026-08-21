/**
 * EditorView - Integrated Code Editor for SPP Dev
 */
export default class EditorView extends BaseComponent {
    constructor(app, container, props = {}) {
        super(app, container, props);
        this.state = {
            currentPath: '',
            items: [],
            openFile: null,
            openFileContent: '',
            loading: false,
            saving: false,
            editorReady: false,
            useLsp: localStorage.getItem('spp_editor_use_lsp') === 'true',
            lspUrl: localStorage.getItem('spp_editor_lsp_url') || 'ws://localhost:3000',
            completionsLoaded: false
        };
        this.editor = null;
    }

    async onInit() {
        if (!window.require) {
            await this.loadMonacoLoader();
        }
        if (!window.monaco) {
            await this.initMonaco();
        } else {
            this.setState({ editorReady: true });
        }
        await this.loadPath('');
    }

    loadMonacoLoader() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs/loader.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    initMonaco() {
        return new Promise((resolve) => {
            require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs' }});
            require(['vs/editor/editor.main'], async () => {
                await this.registerProviders();
                this.setState({ editorReady: true });
                resolve();
            });
        });
    }

    async registerProviders() {
        if (this.state.completionsLoaded) return;
        
        // 1. Static Snippets
        monaco.languages.registerCompletionItemProvider('php', {
            provideCompletionItems: (model, position) => {
                const word = model.getWordUntilPosition(position);
                const range = {
                    startLineNumber: position.lineNumber,
                    endLineNumber: position.lineNumber,
                    startColumn: word.startColumn,
                    endColumn: word.endColumn
                };
                return {
                    suggestions: [
                        {
                            label: 'sppctrl',
                            kind: monaco.languages.CompletionItemKind.Snippet,
                            insertText: 'class ${1:MyController} extends \\SPP\\ViewController {\n\tpublic function index() {\n\t\t$this->render(\'${2:view}\');\n\t}\n}',
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            documentation: 'SPP Controller Boilerplate',
                            range: range
                        }
                    ]
                };
            }
        });

        // 2. Dynamic Reflection (from SPP API)
        try {
            const res = await this.apiPost('editor_get_completions');
            if (res.success && res.data.completions) {
                monaco.languages.registerCompletionItemProvider('php', {
                    provideCompletionItems: (model, position) => {
                        const word = model.getWordUntilPosition(position);
                        const range = {
                            startLineNumber: position.lineNumber,
                            endLineNumber: position.lineNumber,
                            startColumn: word.startColumn,
                            endColumn: word.endColumn
                        };
                        const suggestions = res.data.completions.map(c => ({
                            label: c.label,
                            kind: c.kind,
                            insertText: c.insertText,
                            insertTextRules: c.insertTextRules || 0,
                            detail: c.detail,
                            range: range
                        }));
                        return { suggestions };
                    }
                });
            }
        } catch (e) {
            console.warn("Could not load dynamic completions from SPP API", e);
        }

        // 3. LSP Client Hook
        if (this.state.useLsp && this.state.lspUrl) {
            this.initLspClient();
        }
        
        this.setState({ completionsLoaded: true });
    }

    initLspClient() {
        console.log(`[LSP] Attempting to connect to ${this.state.lspUrl}...`);
        this.lspSocket = new WebSocket(this.state.lspUrl);
        this.lspMessageId = 1;
        this.lspPendingRequests = {};
        
        this.lspSocket.onopen = () => {
            console.log('[LSP] Connected to Language Server.');
            this.notify('LSP Connected', 'success');
            
            this.sendLspRequest('initialize', {
                processId: null,
                rootUri: null,
                capabilities: {
                    textDocument: {
                        completion: {
                            completionItem: { snippetSupport: true }
                        }
                    }
                }
            }).then(() => {
                this.sendLspNotification('initialized', {});
                this.registerLspMonacoProvider();
            });
        };
        
        this.lspSocket.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.id && this.lspPendingRequests[msg.id]) {
                    if (msg.error) {
                        this.lspPendingRequests[msg.id].reject(msg.error);
                    } else {
                        this.lspPendingRequests[msg.id].resolve(msg.result);
                    }
                    delete this.lspPendingRequests[msg.id];
                }
            } catch (e) {
                console.warn('[LSP] Error parsing message', e);
            }
        };

        this.lspSocket.onerror = (e) => {
            console.warn('[LSP] Connection failed. Using fallback SPP completion.');
            if (this.state.useLsp) {
                this.notify('LSP Connection failed. Using SPP Reflection fallback.', 'warning');
            }
        };
    }

    sendLspRequest(method, params) {
        return new Promise((resolve, reject) => {
            const id = this.lspMessageId++;
            this.lspPendingRequests[id] = { resolve, reject };
            this.lspSocket.send(JSON.stringify({
                jsonrpc: '2.0',
                id,
                method,
                params
            }));
        });
    }

    sendLspNotification(method, params) {
        if (this.lspSocket.readyState === WebSocket.OPEN) {
            this.lspSocket.send(JSON.stringify({
                jsonrpc: '2.0',
                method,
                params
            }));
        }
    }

    registerLspMonacoProvider() {
        if (this._lspProviderRegistered) return;
        this._lspProviderRegistered = true;
        
        monaco.editor.onDidCreateModel((model) => {
            this.sendLspNotification('textDocument/didOpen', {
                textDocument: {
                    uri: model.uri.toString(),
                    languageId: model.getLanguageId(),
                    version: model.getVersionId(),
                    text: model.getValue()
                }
            });
            
            model.onDidChangeContent(() => {
                this.sendLspNotification('textDocument/didChange', {
                    textDocument: {
                        uri: model.uri.toString(),
                        version: model.getVersionId()
                    },
                    contentChanges: [
                        { text: model.getValue() }
                    ]
                });
            });
        });

        monaco.languages.registerCompletionItemProvider('php', {
            provideCompletionItems: async (model, position) => {
                try {
                    const result = await this.sendLspRequest('textDocument/completion', {
                        textDocument: { uri: model.uri.toString() },
                        position: {
                            line: position.lineNumber - 1,
                            character: position.column - 1
                        }
                    });
                    
                    if (!result) return { suggestions: [] };
                    
                    const items = Array.isArray(result) ? result : (result.items || []);
                    const word = model.getWordUntilPosition(position);
                    const range = {
                        startLineNumber: position.lineNumber,
                        endLineNumber: position.lineNumber,
                        startColumn: word.startColumn,
                        endColumn: word.endColumn
                    };
                    
                    const suggestions = items.map(item => ({
                        label: item.label,
                        kind: item.kind || 1,
                        insertText: item.insertText || item.label,
                        insertTextRules: item.insertTextFormat === 2 ? 4 : 0, 
                        detail: item.detail,
                        documentation: typeof item.documentation === 'string' ? item.documentation : item.documentation?.value,
                        range: range
                    }));
                    
                    return { suggestions };
                } catch (e) {
                    console.error('[LSP] Completion error:', e);
                    return { suggestions: [] };
                }
            }
        });
    }

    openLspSettings() {
        this.openModal('Language Server Setup', html`
            <div class="form-grid">
                <div class="input-group full-width">
                    <label class="toggle-switch">
                        <input type="checkbox" id="lsp-enable" ?checked=${this.state.useLsp}>
                        <span class="toggle-slider"></span>
                        Enable PHP Language Server Protocol (LSP)
                    </label>
                    <small class="text-dim mt-2 block">If enabled, the editor will attempt to connect to a WebSocket-based Language Server (like Intelephense) for full IDE intelligence.</small>
                </div>
                <div class="input-group full-width mt-3">
                    <label>LSP WebSocket URL</label>
                    <input type="text" id="lsp-url" value="${this.state.lspUrl}" placeholder="ws://localhost:3000">
                </div>
            </div>
        `, [
            { label: 'Cancel', type: 'secondary', fn: () => this.closeModal() },
            { label: 'Save', type: 'primary', fn: () => {
                const useLsp = document.getElementById('lsp-enable').checked;
                const lspUrl = document.getElementById('lsp-url').value;
                localStorage.setItem('spp_editor_use_lsp', useLsp);
                localStorage.setItem('spp_editor_lsp_url', lspUrl);
                this.setState({ useLsp, lspUrl });
                this.closeModal();
                if (useLsp) this.initLspClient();
            }}
        ]);
    }

    async loadPath(path) {
        this.setState({ loading: true });
        try {
            const res = await this.apiPost('editor_list_files', { path: path });
            if (res.success) {
                this.setState({
                    items: res.data.items || [],
                    currentPath: res.data.currentPath || '',
                    loading: false
                });
            } else {
                this.notify(res.message || 'Failed to load directory', 'error');
                this.setState({ loading: false });
            }
        } catch (e) {
            this.notify('Error loading directory', 'error');
            this.setState({ loading: false });
        }
    }

    async openFile(path) {
        this.setState({ loading: true });
        try {
            const res = await this.apiPost('editor_read_file', { path: path });
            if (res.success) {
                this.setState({
                    openFile: path,
                    openFileContent: res.data.content || '',
                    loading: false
                });
                this.updateEditorContent();
            } else {
                this.notify(res.message || 'Failed to open file', 'error');
                this.setState({ loading: false });
            }
        } catch (e) {
            this.notify('Error opening file', 'error');
            this.setState({ loading: false });
        }
    }

    async saveFile() {
        if (!this.state.openFile || !this.editor) return;
        
        const content = this.editor.getValue();
        this.setState({ saving: true });
        
        try {
            const res = await this.apiPost('editor_write_file', { 
                path: this.state.openFile,
                content: content
            });
            if (res.success) {
                this.notify('File saved successfully', 'success');
            } else {
                this.notify(res.message || 'Failed to save file', 'error');
            }
        } catch (e) {
            this.notify('Error saving file', 'error');
        } finally {
            this.setState({ saving: false });
        }
    }

    getLanguage(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const map = {
            'php': 'php',
            'js': 'javascript',
            'ts': 'typescript',
            'json': 'json',
            'html': 'html',
            'css': 'css',
            'sql': 'sql',
            'md': 'markdown',
            'yaml': 'yaml',
            'yml': 'yaml'
        };
        return map[ext] || 'plaintext';
    }

    updateEditorContent() {
        setTimeout(() => {
            const container = document.getElementById('monaco-container');
            if (container && window.monaco && !this.editor) {
                this.editor = monaco.editor.create(container, {
                    value: this.state.openFileContent,
                    language: this.getLanguage(this.state.openFile || ''),
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: { enabled: false }
                });
                
                // Add save command (Ctrl+S / Cmd+S)
                this.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
                    this.saveFile();
                });
            } else if (this.editor) {
                this.editor.setValue(this.state.openFileContent);
                monaco.editor.setModelLanguage(this.editor.getModel(), this.getLanguage(this.state.openFile || ''));
            }
        }, 100);
    }

    navigateUp() {
        if (!this.state.currentPath) return;
        let parts = this.state.currentPath.split('/');
        parts.pop();
        this.loadPath(parts.join('/'));
    }

    onDestroy() {
        if (this.editor) {
            this.editor.dispose();
            this.editor = null;
        }
    }

    render() {
        const { currentPath, items, openFile, loading, saving, editorReady } = this.state;

        return html`
            <div class="editor-view" style="display: flex; height: calc(100vh - 140px); gap: 15px; overflow: hidden;">
                
                <!-- Sidebar (File Tree) -->
                <div class="glass-panel fade-in" style="width: 250px; display: flex; flex-direction: column; overflow: hidden; padding: 0;">
                    <div class="panel-header" style="padding: 15px; border-bottom: 1px solid var(--glass-border);">
                        <h4 style="margin: 0; font-size: 1rem;">File Explorer</h4>
                    </div>
                    <div style="padding: 10px; background: rgba(0,0,0,0.2); font-size: 0.8rem; word-break: break-all;">
                        Workspace / ${currentPath || ''}
                    </div>
                    <div class="file-tree" style="flex: 1; overflow-y: auto; padding: 10px;">
                        ${currentPath ? html`
                            <div class="file-item" style="cursor: pointer; padding: 5px 10px; border-radius: 4px; display: flex; align-items: center; gap: 8px;" @click=${() => this.navigateUp()}>
                                <span>📁</span> <span>.. (Up)</span>
                            </div>
                        ` : ''}
                        
                        ${items.map(item => html`
                            <div class="file-item" 
                                style="cursor: pointer; padding: 5px 10px; border-radius: 4px; display: flex; align-items: center; gap: 8px; ${openFile && openFile.endsWith(item.name) ? 'background: var(--primary-color-alpha);' : ''}"
                                @click=${() => item.isDir ? this.loadPath((currentPath ? currentPath + '/' : '') + item.name) : this.openFile((currentPath ? currentPath + '/' : '') + item.name)}>
                                <span>${item.isDir ? '📁' : '📄'}</span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.name}</span>
                            </div>
                        `)}
                        
                        ${items.length === 0 && !loading ? html`<div style="padding: 10px; opacity: 0.5;">No items found.</div>` : ''}
                        ${loading ? html`<div style="padding: 10px; opacity: 0.5;">Loading...</div>` : ''}
                    </div>
                </div>

                <!-- Editor Main Area -->
                <div class="glass-panel fade-in" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 0;">
                    <div class="panel-header" style="padding: 10px 15px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-family: monospace; font-size: 0.9rem; opacity: 0.8;">
                            ${openFile ? openFile : 'No file selected'}
                        </div>
                        <div class="actions" style="display: flex; gap: 10px;">
                            <button class="btn ghost-btn btn-sm" @click=${() => this.openLspSettings()} title="LSP Settings">⚙️ LSP</button>
                            ${openFile ? html`
                                <button class="btn primary-btn btn-sm" @click=${() => this.saveFile()} ?disabled=${saving}>
                                    ${saving ? 'Saving...' : '💾 Save (Ctrl+S)'}
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div style="flex: 1; position: relative;">
                        ${!editorReady ? html`
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center;">
                                <div>Loading Editor Engine...</div>
                            </div>
                        ` : ''}
                        
                        ${!openFile && editorReady ? html`
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.5;">
                                <div style="font-size: 4rem; margin-bottom: 20px;">⌨️</div>
                                <h2>SPP Dev Code Editor</h2>
                                <p>Select a file from the explorer to begin editing.</p>
                            </div>
                        ` : ''}
                        
                        <div id="monaco-container" style="width: 100%; height: 100%; display: ${openFile ? 'block' : 'none'};"></div>
                    </div>
                </div>
                
            </div>
            
            <style>
                .file-item:hover {
                    background: rgba(255,255,255,0.05);
                }
            </style>
        `;
    }
}
