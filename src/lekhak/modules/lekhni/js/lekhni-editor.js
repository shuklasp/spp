import BaseComponent from '../../../../../spp/modules/spp/sppux/js/BaseComponent.js';

/**
 * Lekhni - Modular Professional Editor
 * Portable WYSIWYG component for SPP Framework.
 */
export default class LekhniEditor extends BaseComponent {
    async onInit(params) {
        this.state = {
            id: params.id || null,
            title: '',
            body: '',
            status: 'draft',
            alias: '',
            tags: '',
            category: 'General',
            saving: false,
            lastSaved: null,
            isDirty: false,
            manualAlias: false,
            mediaLoading: false
        };
    }

    async onMount() {
        if (this.state.id) {
            await this.loadNode();
        }
        this.setupEditorSync();
    }

    async loadNode() {
        this.setState({ saving: true });
        try {
            const res = await this.admin.api('get_node', { id: this.state.id });
            if (res.success) {
                const node = res.node;
                this.setState({
                    title: node.title || '',
                    body: node.body || '',
                    status: node.status || 'draft',
                    alias: node.alias || '',
                    saving: false,
                    manualAlias: !!node.alias
                });
                
                const editor = this.container.querySelector('#lekhni-body');
                if (editor) editor.innerHTML = this.state.body;
            }
        } catch (e) {
            this.admin.notify('Failed to load document', 'error');
            this.setState({ saving: false });
        }
    }

    setupEditorSync() {
        const editor = this.container.querySelector('#lekhni-body');
        if (!editor) return;

        editor.addEventListener('input', () => {
            this.state.body = editor.innerHTML;
            this.state.isDirty = true;
            this.autoSave();
        });

        // Handle placeholder
        editor.addEventListener('focus', () => {
            if (editor.innerHTML === '<p><br></p>' || editor.innerHTML === 'Start writing...') {
                editor.innerHTML = '';
            }
        });

        // Handle Paste Events (Copy-Paste Images)
        editor.addEventListener('paste', (e) => this.handlePaste(e));
    }

    async handlePaste(e) {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                const file = item.getAsFile();
                await this.performDirectUpload(file, 'image');
            }
        }
    }

    async performDirectUpload(file, type = 'image') {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'lekhni_upload_media');

        this.setState({ mediaLoading: true });
        try {
            const res = await fetch(this.admin.config.apiBase + '?action=lekhni_upload_media', {
                method: 'POST',
                body: formData
            }).then(r => r.json());

            if (res.success) {
                if (type === 'image') {
                    this.format('insertImage', res.url);
                } else {
                    const link = `<a href="${res.url}" target="_blank">${res.name}</a>`;
                    this.format('insertHTML', link);
                }
                this.admin.notify('Pasted image uploaded.', 'success');
            }
        } catch (err) {
            this.admin.notify('Paste upload failed.', 'error');
        } finally {
            this.setState({ mediaLoading: false });
        }
    }

    format(cmd, val = null) {
        document.execCommand(cmd, false, val);
        this.container.querySelector('#lekhni-body').focus();
    }

    handleTitleInput(e) {
        const val = e.target.value;
        this.state.title = val;
        this.state.isDirty = true;

        if (!this.state.manualAlias) {
            this.suggestAlias(val);
        }
        this.autoSave();
    }

    suggestAlias(title) {
        const slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        
        this.setState({ alias: slug });
    }

    async uploadMedia(type = 'image') {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = type === 'image' ? 'image/*' : 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            await this.performDirectUpload(file, type);
        };
        input.click();
    }

    async autoSave() {
        if (this.saveTimer) clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => this.save(false), 2000);
    }

    async save(showNotify = true) {
        if (!this.state.isDirty && this.state.id) return;

        this.setState({ saving: true });
        try {
            const res = await this.admin.api('save_node', {
                id: this.state.id,
                title: this.state.title,
                body: this.state.body,
                status: this.state.status,
                alias: this.state.alias
            });

            if (res.success) {
                this.setState({ 
                    id: res.id, 
                    saving: false, 
                    lastSaved: new Date().toLocaleTimeString(),
                    isDirty: false
                });
                if (showNotify) this.admin.notify(res.message, 'success');
            }
        } catch (e) {
            this.setState({ saving: false });
            if (showNotify) this.admin.notify('Save failure', 'error');
        }
    }

    async publish() {
        this.state.status = 'published';
        this.state.isDirty = true;
        await this.save(true);
    }

    render() {
        const { title, status, saving, lastSaved, alias, tags, category, mediaLoading } = this.state;

        return html`
            <div class="lekhni-editor-root">
                <nav class="lekhni-nav">
                    <div class="nav-left">
                        <button class="btn-icon" @click="${() => location.hash = 'content'}" title="Back">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <span class="save-status">${saving ? 'Saving...' : (lastSaved ? `Saved at ${lastSaved}` : 'Draft')}</span>
                    </div>
                    <div class="nav-actions">
                        <button class="btn-secondary" @click="${() => this.save(true)}">Save Draft</button>
                        <button class="btn-primary" @click="${() => this.publish()}">
                            ${status === 'published' ? 'Update' : 'Publish'}
                        </button>
                    </div>
                </nav>

                <div class="lekhni-workspace">
                    <main class="lekhni-main">
                        <div class="lekhni-toolbar">
                            <div class="toolbar-group">
                                <button @click="${() => this.format('formatBlock', 'h1')}">H1</button>
                                <button @click="${() => this.format('formatBlock', 'h2')}">H2</button>
                                <button @click="${() => this.format('formatBlock', 'p')}">P</button>
                            </div>
                            <div class="divider"></div>
                            <div class="toolbar-group">
                                <button @click="${() => this.format('bold')}"><b>B</b></button>
                                <button @click="${() => this.format('italic')}"><i>I</i></button>
                                <button @click="${() => this.format('underline')}"><u>U</u></button>
                            </div>
                            <div class="divider"></div>
                            <div class="toolbar-group">
                                <button @click="${() => this.format('insertUnorderedList')}">• List</button>
                                <button @click="${() => this.uploadMedia('image')}" ?disabled="${mediaLoading}">🖼️ Image</button>
                                <button @click="${() => this.uploadMedia('file')}" ?disabled="${mediaLoading}">📎 File</button>
                            </div>
                        </div>

                        <div class="lekhni-canvas">
                            <input type="text" class="lekhni-title-input" placeholder="Document Title" 
                                .value="${title}" @input="${(e) => this.handleTitleInput(e)}">
                            
                            <div id="lekhni-body" class="lekhni-content-area" contenteditable="true">
                                <p>Start writing...</p>
                            </div>
                        </div>
                    </main>

                    <aside class="lekhni-sidebar">
                        <div class="sidebar-section">
                            <h4>Publishing</h4>
                            <div class="field">
                                <label>URL Alias</label>
                                <input type="text" .value="${alias}" 
                                    @input="${(e) => { this.state.alias = e.target.value; this.state.manualAlias = true; this.state.isDirty = true; }}">
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <select .value="${status}" @change="${(e) => { this.state.status = e.target.value; this.state.isDirty = true; }}">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="private">Private</option>
                                </select>
                            </div>
                        </div>

                        <div class="sidebar-section">
                            <h4>Classification</h4>
                            <div class="field">
                                <label>Category</label>
                                <select .value="${category}" @change="${(e) => this.setState({ category: e.target.value })}">
                                    <option>General</option>
                                    <option>News</option>
                                    <option>Tutorial</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Tags</label>
                                <input type="text" placeholder="Add tags..." .value="${tags}" @input="${(e) => this.setState({ tags: e.target.value })}">
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <style>
                .lekhni-editor-root {
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: #0f172a; z-index: 2000;
                    display: flex; flex-direction: column;
                    color: #f1f5f9; font-family: 'Inter', sans-serif;
                }

                .lekhni-nav {
                    height: 60px; background: #1e293b; border-bottom: 1px solid #334155;
                    display: flex; justify-content: space-between; align-items: center; padding: 0 1.5rem;
                }
                .nav-left { display: flex; align-items: center; gap: 1rem; }
                .save-status { font-size: 0.8rem; color: #94a3b8; }
                .nav-actions { display: flex; gap: 0.75rem; }

                .lekhni-workspace { display: flex; flex-grow: 1; overflow: hidden; }
                .lekhni-main { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
                
                .lekhni-toolbar {
                    position: sticky; top: 0; z-index: 10;
                    background: #1e293b; padding: 0.5rem 2rem;
                    display: flex; gap: 0.5rem; align-items: center;
                    border-bottom: 1px solid #334155;
                }
                .lekhni-toolbar button {
                    background: transparent; border: none; color: #94a3b8; padding: 6px 12px; border-radius: 4px;
                    cursor: pointer; font-size: 0.85rem; transition: all 0.2s;
                }
                .lekhni-toolbar button:hover { background: #334155; color: white; }
                .lekhni-toolbar button:disabled { opacity: 0.5; cursor: not-allowed; }
                .divider { width: 1px; height: 20px; background: #334155; margin: 0 0.5rem; }

                .lekhni-canvas { max-width: 800px; margin: 0 auto; width: 100%; padding: 4rem 2rem; }
                .lekhni-title-input {
                    width: 100%; background: transparent; border: none;
                    font-size: 3rem; font-weight: 800; font-family: 'Outfit';
                    color: white; margin-bottom: 2rem; outline: none;
                }
                .lekhni-content-area { font-size: 1.25rem; line-height: 1.8; color: #cbd5e1; outline: none; min-height: 500px; }
                .lekhni-content-area img { max-width: 100%; border-radius: 8px; margin: 1.5rem 0; }

                .lekhni-sidebar { width: 300px; background: #1e293b; border-left: 1px solid #334155; padding: 1.5rem; }
                .sidebar-section { margin-bottom: 2rem; }
                .sidebar-section h4 { font-size: 0.75rem; text-transform: uppercase; color: #64748b; margin-bottom: 1rem; letter-spacing: 0.05em; }
                .field { margin-bottom: 1rem; }
                .field label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.4rem; }
                .field input, .field select {
                    width: 100%; background: #0f172a; border: 1px solid #334155;
                    padding: 0.6rem; border-radius: 6px; color: white; font-size: 0.9rem;
                }

                .btn-primary { background: #6366f1; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
                .btn-secondary { background: transparent; border: 1px solid #334155; color: white; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
                .btn-icon { background: transparent; border: none; color: #94a3b8; cursor: pointer; display: flex; }

                /* Hide global admin UI when editor is open */
                body:has(.lekhni-editor-root) .sidebar,
                body:has(.lekhni-editor-root) .content-header {
                    display: none !important;
                }
                body:has(.lekhni-editor-root) .main-wrapper {
                    margin-left: 0 !important;
                }
            </style>
        `;
    }
}
