/**
 * SPP Developer Workbench IDE Engine
 */

class SPPIde {
    constructor() {
        this.currentPath = '';
        this.openFiles = new Map(); // path -> { content, model, viewState, tab }
        this.activeFile = null;
        this.editor = null;

        this.initEditor();
        this.bindEvents();
        this.loadFileSystem();
    }

    initEditor() {
        require(['vs/editor/editor.main'], () => {
            const container = document.getElementById('monaco-container');
            // Remove welcome screen when editor is created
            
            this.editor = monaco.editor.create(container, {
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
                minimap: { enabled: false },
                wordWrap: 'on'
            });

            // Save shortcut
            this.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
                this.saveActiveFile();
            });

            // Hide editor initially, show welcome
            this.editor.getContainerDomNode().style.display = 'none';
        });
    }

    bindEvents() {
        // Activity Bar Navigation
        document.querySelectorAll('.activity-item[data-panel]').forEach(item => {
            item.addEventListener('click', (e) => {
                document.querySelectorAll('.activity-item').forEach(i => i.classList.remove('active'));
                e.currentTarget.classList.add('active');
                
                const panel = e.currentTarget.getAttribute('data-panel');
                document.getElementById('sidebar-title').innerText = panel.toUpperCase();
                
                if (panel === 'explorer') {
                    document.getElementById('sidebar-explorer').style.display = 'block';
                    document.getElementById('sidebar-dynamic').style.display = 'none';
                } else if (panel === 'terminal' || panel === 'diagnostics') {
                    // Open these in the bottom panel (placeholder for now)
                    document.getElementById('bottom-panel').classList.remove('collapsed');
                } else {
                    document.getElementById('sidebar-explorer').style.display = 'none';
                    // Open complex tools as tabs in the main editor area
                    this.openToolTab(panel);
                }
            });
        });

        // Bottom Panel Toggles
        document.getElementById('btn-close-bottom').addEventListener('click', () => {
            document.getElementById('bottom-panel').classList.toggle('collapsed');
        });

        document.getElementById('btn-refresh-fs').addEventListener('click', () => {
            this.loadFileSystem(this.currentPath);
        });
    }

    async loadFileSystem(path = '') {
        const treeContainer = document.getElementById('file-tree');
        treeContainer.innerHTML = '<div style="padding:1rem; color:var(--text-dim); text-align:center;">Loading...</div>';

        try {
            const formData = new FormData();
            formData.append('action', 'editor_list_files');
            if (path) formData.append('path', path);

            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 
                    'X-CSRF-Token': window.SPP_CSRF_TOKEN || '',
                    'X-SPP-Ajax': '1' 
                },
                body: formData
            });

            const json = await res.json();
            if (json.success) {
                this.currentPath = json.data.currentPath || '';
                this.renderFileTree(json.data.items);
            } else {
                treeContainer.innerHTML = `<div style="color:var(--danger); padding:1rem;">Error: ${json.message}</div>`;
            }
        } catch (e) {
            console.error(e);
            treeContainer.innerHTML = `<div style="color:var(--danger); padding:1rem;">Failed to load filesystem.</div>`;
        }
    }

    renderFileTree(items) {
        const treeContainer = document.getElementById('file-tree');
        treeContainer.innerHTML = '';

        if (this.currentPath && this.currentPath !== '/' && this.currentPath !== '.') {
            // Add "Up" directory
            const up = document.createElement('div');
            up.className = 'tree-item';
            up.innerHTML = `<span>📁</span> <span>..</span>`;
            up.onclick = () => {
                let parts = this.currentPath.split('/');
                if(parts.length > 0) parts.pop();
                const newPath = parts.length > 0 ? parts.join('/') : '';
                this.loadFileSystem(newPath);
            };
            treeContainer.appendChild(up);
        }

        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'tree-item';
            const icon = item.isDir ? '📁' : '📄';
            div.innerHTML = `<span>${icon}</span> <span>${item.name}</span>`;
            
            div.onclick = () => {
                if (item.isDir) {
                    this.loadFileSystem(item.path);
                } else {
                    this.openFile(item.path, item.name);
                }
            };
            
            treeContainer.appendChild(div);
        });
    }

    openToolTab(panel) {
        const path = 'tool://' + panel;
        const name = panel.toUpperCase();
        
        if (this.openFiles.has(path)) {
            this.activateFile(path);
            return;
        }

        const tab = document.createElement('div');
        tab.className = 'editor-tab';
        tab.innerHTML = `🛠️ ${name} <span class="close-tab">&times;</span>`;
        tab.onclick = (e) => {
            if (e.target.classList.contains('close-tab')) {
                this.closeFile(path);
            } else {
                this.activateFile(path);
            }
        };
        
        document.getElementById('editor-tabs').appendChild(tab);
        this.openFiles.set(path, { isTool: true, panel: panel, tab: tab, name: name });
        
        document.getElementById('welcome-screen').style.display = 'none';
        this.activateFile(path);
    }

    async openFile(path, name) {
        if (this.openFiles.has(path)) {
            this.activateFile(path);
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'editor_read_file');
            formData.append('path', path);

            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 
                    'X-CSRF-Token': window.SPP_CSRF_TOKEN || '',
                    'X-SPP-Ajax': '1' 
                },
                body: formData
            });

            const json = await res.json();
            if (json.success) {
                const content = json.data.content;
                const language = this.getLanguageFromExtension(name);
                
                // create monaco model
                const model = monaco.editor.createModel(content, language);
                
                // create tab
                const tab = document.createElement('div');
                tab.className = 'editor-tab';
                tab.innerHTML = `${name} <span class="close-tab">&times;</span>`;
                tab.onclick = (e) => {
                    if (e.target.classList.contains('close-tab')) {
                        this.closeFile(path);
                    } else {
                        this.activateFile(path);
                    }
                };
                
                document.getElementById('editor-tabs').appendChild(tab);

                this.openFiles.set(path, { content, model, viewState: null, tab, name });
                
                // hide welcome
                document.getElementById('welcome-screen').style.display = 'none';
                if (this.editor) this.editor.getContainerDomNode().style.display = 'block';

                this.activateFile(path);
            } else {
                alert(`Error reading file: ${json.message}`);
            }
        } catch (e) {
            console.error(e);
            alert("Failed to load file.");
        }
    }

    activateFile(path) {
        if (!this.openFiles.has(path)) return;
        
        // save previous state
        if (this.activeFile && this.openFiles.has(this.activeFile)) {
            const prev = this.openFiles.get(this.activeFile);
            if (!prev.isTool) {
                prev.viewState = this.editor.saveViewState();
            }
            prev.tab.classList.remove('active');
        }

        this.activeFile = path;
        const current = this.openFiles.get(path);
        
        current.tab.classList.add('active');
        
        if (current.isTool) {
            if (this.editor) this.editor.getContainerDomNode().style.display = 'none';
            document.getElementById('ide-tools-container').style.display = 'block';
            
            // Route through legacy SPP Admin logic
            if (window.admin && admin.loadView) {
                admin.loadView(current.panel);
            }
        } else {
            document.getElementById('ide-tools-container').style.display = 'none';
            if (this.editor) {
                this.editor.getContainerDomNode().style.display = 'block';
                this.editor.setModel(current.model);
                if (current.viewState) {
                    this.editor.restoreViewState(current.viewState);
                }
                this.editor.focus();
            }
        }
    }

    closeFile(path) {
        if (!this.openFiles.has(path)) return;

        const fileData = this.openFiles.get(path);
        fileData.tab.remove();
        if (!fileData.isTool) {
            fileData.model.dispose();
        }
        this.openFiles.delete(path);

        if (this.activeFile === path) {
            this.activeFile = null;
            if (this.openFiles.size > 0) {
                // activate last open
                const last = Array.from(this.openFiles.keys()).pop();
                this.activateFile(last);
            } else {
                this.editor.setModel(null);
                this.editor.getContainerDomNode().style.display = 'none';
                document.getElementById('welcome-screen').style.display = 'flex';
            }
        }
    }

    async saveActiveFile() {
        if (!this.activeFile) return;
        
        const fileData = this.openFiles.get(this.activeFile);
        const content = fileData.model.getValue();

        try {
            const formData = new FormData();
            formData.append('action', 'editor_write_file');
            formData.append('path', this.activeFile);
            formData.append('content', content);

            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 
                    'X-CSRF-Token': window.SPP_CSRF_TOKEN || '',
                    'X-SPP-Ajax': '1' 
                },
                body: formData
            });

            const json = await res.json();
            if (json.success) {
                if (window.SPPUX && window.SPPUX.Toast) {
                    SPPUX.Toast.success(`Saved ${fileData.name}`);
                } else {
                    console.log(`Saved ${fileData.name}`);
                }
            } else {
                alert(`Error saving file: ${json.message}`);
            }
        } catch (e) {
            console.error(e);
            alert("Failed to save file.");
        }
    }

    getLanguageFromExtension(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        switch (ext) {
            case 'php': return 'php';
            case 'js': return 'javascript';
            case 'ts': return 'typescript';
            case 'css': return 'css';
            case 'html': return 'html';
            case 'json': return 'json';
            case 'xml': return 'xml';
            case 'yaml': case 'yml': return 'yaml';
            case 'md': return 'markdown';
            case 'sql': return 'sql';
            default: return 'plaintext';
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.ide = new SPPIde();
});
