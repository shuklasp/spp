/**
 * Mobile Studio Application Core
 */
import MobileView from './views/mobile.js';

class MobileStudioApp {
    constructor() {
        this.container = document.getElementById('view-container');
        this.init();
    }

    async init() {
        console.log("[MobileStudio] Initializing IDE...");
        
        // Load the Mobile Studio View directly as the main application
        this.view = new MobileView(this, this.container);
        await this.view.onInit();
        this.view.update();
        
        // Show workspace layer
        document.getElementById('workspace-layer')?.classList.add('active');

        // Bind sidebar navigation
        this.bindNavigation();

        // Bind global actions
        document.getElementById('save-project-btn')?.addEventListener('click', () => {
            this.view.saveConfig();
        });
    }

    bindNavigation() {
        const links = {
            'nav-studio': 'studio',
            'nav-assets': 'assets',
            'nav-code': 'code'
        };

        Object.entries(links).forEach(([id, mode]) => {
            document.getElementById(id)?.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchView(mode, id);
            });
        });
    }

    switchView(mode, activeId) {
        // Update UI state
        document.querySelectorAll('.mini-sidebar a').forEach(a => a.classList.remove('active'));
        document.getElementById(activeId)?.classList.add('active');

        // Update View Mode
        if (this.view && this.view.setViewMode) {
            this.view.setViewMode(mode);
        }
    }

    // Proxy notify to SPPUX
    notify(msg, type) {
        if (window.SPPUX && SPPUX.Notify) SPPUX.Notify.show(msg, type);
        else console.log(`[${type}] ${msg}`);
    }

    // Proxy API calls
    async api(action, data = {}) {
        const endpoint = 'api.php';
        const res = await fetch(`${endpoint}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await res.json();
    }
}

window.mobileStudio = new MobileStudioApp();
