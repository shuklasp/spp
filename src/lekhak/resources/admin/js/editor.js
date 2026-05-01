export class FullscreenEditor {
    constructor(container, apiEndpoint) {
        this.container = container;
        this.apiEndpoint = apiEndpoint;
        this.onClose = null;
        this.documentId = null;
    }

    async init(id = null) {
        this.documentId = id;
        this.renderLayout();
        
        if (id) {
            await this.loadDocument(id);
        }
        
        this.setupEventListeners();
    }

    renderLayout() {
        this.container.innerHTML = `
            <div class="editor-shell" style="background: #0f172a; color: #f8fafc; height: 100vh; display: flex; flex-direction: column;">
                <div class="editor-toolbar" style="height: 60px; border-bottom: 1px solid #334155; display: flex; align-items: center; justify-content: space-between; padding: 0 2rem;">
                    <div class="toolbar-left" style="display: flex; gap: 1rem; align-items: center;">
                        <button id="btn-editor-back" style="background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem;"><i class="fas fa-arrow-left"></i></button>
                        <span id="editor-status" style="color: #94a3b8; font-size: 0.875rem;">Draft</span>
                    </div>
                    <div class="toolbar-right" style="display: flex; gap: 1rem;">
                        <button id="btn-editor-save" style="background: #334155; border: none; color: white; padding: 0.5rem 1.5rem; border-radius: 0.25rem; cursor: pointer;">Save</button>
                        <button id="btn-editor-publish" style="background: #6366f1; border: none; color: white; padding: 0.5rem 1.5rem; border-radius: 0.25rem; cursor: pointer;">Publish</button>
                    </div>
                </div>
                <div class="editor-content" style="flex-grow: 1; padding: 4rem 20%; overflow-y: auto;">
                    <h1 id="editor-title" contenteditable="true" style="font-family: 'Outfit', sans-serif; font-size: 3.5rem; border: none; outline: none; margin-bottom: 2rem; color: white;">Untitled Document</h1>
                    <div id="editor-body" contenteditable="true" style="font-size: 1.25rem; line-height: 1.8; color: #cbd5e1; outline: none; min-height: 400px;">
                        Start writing your story here...
                    </div>
                </div>
            </div>
        `;
    }

    setupEventListeners() {
        document.getElementById('btn-editor-back').addEventListener('click', () => {
            if (this.onClose) this.onClose();
        });

        document.getElementById('btn-editor-save').addEventListener('click', () => this.handleSave('draft'));
        document.getElementById('btn-editor-publish').addEventListener('click', () => this.handleSave('published'));
    }

    async loadDocument(id) {
        const response = await fetch(`${this.apiEndpoint}?action=get_node&id=${id}`);
        const data = await response.json();
        if (data.success) {
            document.getElementById('editor-title').textContent = data.node.title || 'Untitled Document';
            document.getElementById('editor-body').innerHTML = data.node.body || '';
            document.getElementById('editor-status').textContent = data.node.status || 'Draft';
        }
    }

    async handleSave(status) {
        const title = document.getElementById('editor-title').textContent;
        const body = document.getElementById('editor-body').innerHTML;
        
        const btn = (status === 'published') ? document.getElementById('btn-editor-publish') : document.getElementById('btn-editor-save');
        const originalText = btn.textContent;
        btn.textContent = 'Saving...';
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'save_node');
            formData.append('id', this.documentId || '');
            formData.append('title', title);
            formData.append('body', body);
            formData.append('status', status);

            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                this.documentId = data.data.id;
                document.getElementById('editor-status').textContent = status;
                alert(data.message);
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Fatal error: ' + err.message);
        } finally {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }
}
