export class LekhakAdmin {
    constructor() {
        this.viewport = document.getElementById('viewport');
        this.breadcrumb = document.getElementById('breadcrumb');
        this.currentView = 'dashboard';
        this.apiEndpoint = '../admin-api';
    }

    init() {
        this.setupNavigation();
        this.setupEventListeners();
        this.loadHashView();
        
        window.addEventListener('hashchange', () => this.loadHashView());
    }

    setupNavigation() {
        const links = document.querySelectorAll('.nav-link[data-view]');
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                const view = e.currentTarget.getAttribute('data-view');
                location.hash = view;
            });
        });
    }

    setupEventListeners() {
        const btnNew = document.getElementById('btn-new-doc');
        if (btnNew) {
            btnNew.addEventListener('click', () => {
                this.openEditor();
            });
        }
    }

    async loadHashView() {
        const hash = location.hash.replace('#', '') || 'dashboard';
        this.currentView = hash;
        
        // Update active class
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        const activeLink = document.querySelector(`.nav-link[data-view="${hash}"]`);
        if (activeLink) activeLink.classList.add('active');

        this.breadcrumb.textContent = `Lekhak / ${hash.charAt(0).toUpperCase() + hash.slice(1)}`;
        
        await this.renderView(hash);
    }

    async renderView(viewName) {
        this.viewport.innerHTML = '<div class="loader"><div class="spinner"></div></div>';
        
        try {
            // In a real app, we would load dedicated JS modules for each view.
            // For now, we'll implement them inline or as separate files later.
            const content = await this.fetchViewData(viewName);
            this.viewport.innerHTML = content;
            
            // Re-bind view specific events
            this.bindViewEvents(viewName);
        } catch (err) {
            this.viewport.innerHTML = `<div class="error">Failed to load view: ${err.message}</div>`;
        }
    }

    async fetchViewData(viewName) {
        // Here we call the dedicated Lekhak Admin API
        const response = await fetch(`${this.apiEndpoint}?action=get_view&view=${viewName}`);
        const data = await response.json();
        if (data.success) {
            return data.html;
        } else {
            throw new Error(data.message);
        }
    }

    bindViewEvents(viewName) {
        if (viewName === 'content') {
            document.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = e.currentTarget.getAttribute('data-id');
                    this.openEditor(id);
                });
            });
        } else if (viewName === 'canvas') {
            const btnLaunch = document.getElementById('btn-launch-editor');
            if (btnLaunch) {
                btnLaunch.addEventListener('click', () => {
                    this.openEditor();
                });
            }
        }
    }

    async openEditor(id = null) {
        const container = document.getElementById('editor-container');
        container.style.display = 'block';
        
        // Load Editor Module (reuse logic from our previous editor.js but self-contained)
        const { FullscreenEditor } = await import('./editor.js');
        const editor = new FullscreenEditor(container, this.apiEndpoint);
        await editor.init(id);
        
        editor.onClose = () => {
            container.style.display = 'none';
            container.innerHTML = '';
            if (this.currentView === 'content') this.loadHashView();
        };
    }
}
