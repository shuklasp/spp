/**
 * SPP Admin SPA Frontend Controller
 * 
 * Manages view routing, API synchronization, authentication state, 
 * and UI interactions for the developer workbench.
 * Now refactored to a Standard Script with Global SPP-UX support.
 */

// Ensure global BaseComponent fallback for all view modules
if (typeof window !== 'undefined' && !window.BaseComponent) {
    window.BaseComponent = class BaseComponent {
        constructor(admin, container, props) {
            this.admin = admin || window.admin;
            this.container = container;
            this.props = props || {};
            this.state = {};
        }

        setState(partial) {
            this.state = Object.assign({}, this.state, partial);
        }

        update() {
            if (this.container && typeof this.render === 'function') {
                this.container.innerHTML = this.render();
            }
        }

        openSubEditor(title, content, data = {}, onSave = null) {
            if (typeof SPPUX !== 'undefined' && typeof SPPUX.openSubEditor === 'function') {
                return SPPUX.openSubEditor(title, content, data, onSave);
            }
            if (this.admin && typeof this.admin.openSubEditor === 'function') {
                return this.admin.openSubEditor(title, content, data, onSave);
            }
            if (typeof window !== 'undefined' && window.admin && typeof window.admin.openSubEditor === 'function') {
                return window.admin.openSubEditor(title, content, data, onSave);
            }
            console.warn('SPPUX.openSubEditor not available');
        }

        updateSubEditor(content) {
            if (typeof SPPUX !== 'undefined' && typeof SPPUX.updateSubEditor === 'function') {
                return SPPUX.updateSubEditor(content);
            }
            console.warn('SPPUX.updateSubEditor not available');
        }

        escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        escapeAttr(str) {
            return this.escapeHtml(str);
        }

        notify(msg, type = 'info') {
            if (this.admin && typeof this.admin.notify === 'function') {
                return this.admin.notify(msg, type);
            }
            if (typeof window !== 'undefined' && window.admin && typeof window.admin.notify === 'function') {
                return window.admin.notify(msg, type);
            }
            if (typeof SPPUX !== 'undefined' && typeof SPPUX.notify === 'function') {
                return SPPUX.notify(msg, type);
            }
            console.log(`[${type.toUpperCase()}] ${msg}`);
        }

        openModal(title, content, onSave = null) {
            if (this.admin && typeof this.admin.openModal === 'function') {
                return this.admin.openModal(title, content, onSave);
            }
            if (typeof window !== 'undefined' && window.admin && typeof window.admin.openModal === 'function') {
                return window.admin.openModal(title, content, onSave);
            }
        }

        closeModal() {
            if (this.admin && typeof this.admin.closeModal === 'function') {
                return this.admin.closeModal();
            }
            if (typeof window !== 'undefined' && window.admin && typeof window.admin.closeModal === 'function') {
                return window.admin.closeModal();
            }
        }
    };
}
if (typeof window !== 'undefined' && window.SPPUX) {
    if (!window.SPPUX.BaseComponent) {
        window.SPPUX.BaseComponent = window.BaseComponent;
    } else {
        if (!window.SPPUX.BaseComponent.prototype.escapeHtml) {
            window.SPPUX.BaseComponent.prototype.escapeHtml = window.BaseComponent.prototype.escapeHtml;
        }
        if (!window.SPPUX.BaseComponent.prototype.escapeAttr) {
            window.SPPUX.BaseComponent.prototype.escapeAttr = window.BaseComponent.prototype.escapeAttr;
        }
    }
    if (!window.SPPUX.callAction) window.SPPUX.callAction = (action, data = {}) => window.SPPUX.api(action, data);
}

class SPPAdmin {
    constructor() {
        console.log("SPP Admin Workbench v1.1 Loaded");
        this.apiEndpoint = 'api.php';
        
        // i18n Configuration
        window.SPP_I18N = window.SPP_I18N || {};
        window.__ = function(key, params = {}) {
            let str = window.SPP_I18N[key] || key;
            for (let k in params) {
                str = str.replace(new RegExp('\\{' + k + '\\}', 'g'), params[k]);
            }
            return str;
        };
        this.__ = window.__;

        // Ensure local aliases for namespaced SPPUX utilities
        if (typeof SPPUX !== 'undefined') {
            window.html = SPPUX.html;
            window.TrustedHTML = SPPUX.TrustedHTML;
            this.escapeHtml = SPPUX.utils.escapeHtml;
            this.escapeAttr = SPPUX.utils.escapeAttr;
            this.truncatePath = SPPUX.utils.truncatePath;
        }
        this.user = null;
        this.theme = localStorage.getItem('spp_admin_theme') || 'night';
        this.selectedApp = localStorage.getItem('spp_admin_selected_app') || 'default';
        this.uiMode = localStorage.getItem('spp_admin_ui_mode') || 'normal';
        
        // Initialize Global Root Store with proper context values
        const StoreClass = window.SPPStore || (window.SPPUX && window.SPPUX.SPPStore);
        if (StoreClass) {
            window.spp_root_store = new StoreClass({
                user: null,
                selectedApp: this.selectedApp,
                theme: this.theme
            });
        }
        
        this.currentView = 'dashboard';
        this.viewIcons = {
            'dashboard': '👋',
            'studio': '💻',
            'coder': '💻',
            'workflows': '🔄',
            'entities': '🏗️',
            'forms': '📝',
            'routing': '🛤️',
            'reports': '📊',
            'ai': '🤖',
            'tinker': '💬',
            'mesh': '🕸️',
            'testing': '🧪',
            'pages': '📐',
            'trace': '📈',
            'commands': '⚡',
            // Legacy aliases
            'editor': '💻',
            'apps': '📱',
            'docs': '📚',
            'xdb': '🗄️',
            'interdb': '🕸️',
            'database': '🏗️',
            'spplang': '💬',
            'mobile': '📱',
            'services': '🔌',
            'parikshak': '🧪',
            'mail': '📬',
            'fleet': '🌐',
            'apm': '🔥',
            'webhooks': '⚡',
            'apistudio': '🚀',
            'terminal': '⬛',
            'files': '📁',
            'manifests': '🛡️',
            'cron': '⏱️',
            'system': '🖥️',
            'domains': '🌐',
            'deploy': '🚀',
            'apptoolkit': '📦',
            'shield': '🛡️',
            'server': '🖥️',
            'runtime': '⚙️',
            'metrics': '📊',
            'reseller': '👥'
        };
        this.viewTitles = {
            'dashboard': 'Developer Studio Hub',
            'studio': 'Code Studio & Scaffolder',
            'coder': 'Code Studio & Scaffolder',
            'workflows': 'Workflows & State Machines',
            'entities': 'Entities & Schemas',
            'forms': 'Forms & Locales',
            'routing': 'Routing & Endpoints',
            'reports': 'Reports & Analytics',
            'ai': 'AI Copilot & Playground',
            'tinker': 'Web Tinker REPL',
            'mesh': 'Integration Mesh',
            'testing': 'Parikshak Test Studio',
            'pages': 'SPP Studio',
            'trace': 'Distributed Tracing Waterfall',
            'commands': 'CLI Command Center',
            // Legacy aliases & imported views
            'editor': 'Code Editor',
            'apps': 'App Studio',
            'docs': 'Code Explorer & Docs',
            'xdb': 'XML Database (XDB)',
            'interdb': 'InterDB Data Mesh',
            'database': 'Unified Developer Data Plane',
            'spplang': 'Translations & Locales',
            'mobile': 'Mobile Studio',
            'services': 'Services & Polyglot API',
            'parikshak': 'Parikshak Test Studio',
            'mail': 'Mail & Gateway',
            'fleet': 'Multi-Server Fleet & DNS Cluster',
            'apm': 'Native In-Panel APM & Flame Graph Profiler',
            'webhooks': 'Sovereign Webhook Relay & Inspector',
            'apistudio': 'Interactive API Studio & OpenAPI 3.1',
            'terminal': 'Interactive Web Terminal',
            'files': 'File Manager & Storage Quotas',
            'manifests': 'Manifest OS & Dependency Graph',
            'cron': 'Background Tasks, Queue & DAG Jobs',
            'system': 'Observability, OPcache & Live Logs',
            'domains': 'Virtual Hosts & Domains',
            'deploy': 'Deployments & Backups',
            'apptoolkit': 'CMS & App Marketplace',
            'shield': 'Security & Firewall',
            'server': 'Server Workstation & Docker Containers',
            'runtime': 'PHP Runtimes, Extensions & OPcache',
            'metrics': 'Metrics & Real-Time Analytics',
            'reseller': 'Resellers & Multi-Tenancy'
        };
        this.availableApps = [];
        this.selectedApp = localStorage.getItem('spp_admin_selected_app') || 'default';
        this.searchTimeout = null;
        this.theme = localStorage.getItem('spp_admin_theme') || 'night';
        this._loadCounter = 0;
        
        // Define base configuration for components
        const p = window.location.pathname;
        const idx = p.indexOf('/sppdev') !== -1 ? p.indexOf('/sppdev') : p.indexOf('/spp/dev');
        this.config = {
            baseUrl: window.location.origin + (idx !== -1 ? p.substring(0, idx) : ''),
            apiBase: 'api.php'
        };

        this.init();
    }

    // =============================================
    // INITIALIZATION
    // =============================================

    async init() {
        this.applyTheme(this.theme);
        this.applyUIMode(this.uiMode);
        this.restoreSidebarState();
        this.bindEvents();

        // 1. Fast Path: Server-validated authentication state (Instant load on refresh)
        const initialAuth = window.SPP_AUTH_STATE;
        if (initialAuth && initialAuth.authenticated) {
            this.user = initialAuth.user || { username: 'Administrator' };
            if (window.spp_root_store) {
                if (window.spp_root_store?.set) {
                    window.spp_root_store.set({ user: this.user });
                }
            }
            this.updateUserDisplay(this.user);

            // Ensure DOM layers reflect authenticated state instantly (no flash of login screen)
            const loginLayer = document.getElementById('login-layer');
            if (loginLayer) loginLayer.classList.remove('active');
            const workspace = document.getElementById('ide-workspace') || document.getElementById('workspace-layer');
            if (workspace) workspace.classList.add('active');

            // Render current route immediately on first paint
            this.handleRouting();

            // Run non-critical background synchronization in parallel without blocking the UI
            Promise.all([
                this.loadRoutes(),
                this.loadApps(),
                this.loadAdminPermissions()
            ]).catch(e => console.warn("Background sync warning:", e));
            return;
        }

        // 2. Unauthenticated Path: load routes and check auth/magic link
        await this.loadRoutes();
        await this.checkAuth();

    }

    async loadRoutes() {
        try {
            const res = await fetch('routes.json');
            if (res.ok) {
                const customRoutes = await res.json();
                for (const [view, config] of Object.entries(customRoutes)) {
                    if (config.icon) this.viewIcons[view] = config.icon;
                    if (config.title) this.viewTitles[view] = config.title;
                    // Note: If component overrides are needed, we can map config.component
                    // However, our current dynamic import assumes `views/${view}.js`
                    // or `src/${app}/comp/${view}.js`. This aligns perfectly with declarative definitions.
                }
                console.log("Declarative routes loaded from routes.json");
            }
        } catch (e) {
            console.warn("No routes.json found or failed to parse, proceeding with default routes.", e);
        }
    }

    bindEvents() {
        try {
            // Login form
            const loginForm = document.getElementById('login-form');
            if (loginForm) {
                loginForm.addEventListener('submit', (e) => this.handleLogin(e));
            }

            // Navigation
            document.querySelectorAll('.nav-item').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const view = e.currentTarget.getAttribute('data-view');
                    location.hash = view;
                });
            });

            // Sidebar Search
            const sidebarSearch = document.getElementById('sidebar-search');
            if (sidebarSearch) {
                sidebarSearch.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase();
                    const navItems = document.querySelectorAll('#sidebar-nav li');
                    navItems.forEach(li => {
                        const text = li.textContent.toLowerCase();
                        const aTag = li.querySelector('a');
                        const keywords = aTag ? (aTag.getAttribute('data-keywords') || '').toLowerCase() : '';
                        
                        if (text.includes(term) || keywords.includes(term)) {
                            li.style.display = '';
                        } else {
                            li.style.display = 'none';
                        }
                    });
                    
                    // Hide section titles if all items under them are hidden
                    const sections = document.querySelectorAll('.sidebar-section-title');
                    sections.forEach(sec => {
                        let next = sec.nextElementSibling;
                        let hasVisible = false;
                        while (next && !next.classList.contains('sidebar-divider') && !next.classList.contains('sidebar-section-title')) {
                            if (next.tagName === 'LI' && next.style.display !== 'none') {
                                hasVisible = true;
                            }
                            next = next.nextElementSibling;
                        }
                        sec.style.display = (hasVisible || !term) ? '' : 'none';
                    });
                    
                    const dividers = document.querySelectorAll('.sidebar-divider');
                    dividers.forEach(div => div.style.display = term ? 'none' : '');
                });
            }

            // Hash change for routing
            window.addEventListener('hashchange', () => this.handleRouting());

            // Delegated member search results click
            document.addEventListener('click', (e) => {
                const searchItem = e.target.closest('.search-item');
                if (searchItem) {
                    const entityClass = searchItem.getAttribute('data-class');
                    const entityId = searchItem.getAttribute('data-id');
                    const name = searchItem.getAttribute('data-name');
                    if (entityClass && entityId && name) {
                        this.promptAddMember(entityClass, entityId, name);
                    }
                }
            });

            // Logout
            const logoutBtn = document.getElementById('logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => this.handleLogout());
            }

            // Profile Editor
            const userInfo = document.querySelector('.user-info');
            if (userInfo) {
                userInfo.addEventListener('click', () => this.openProfileEditor());
            }

            // Modal elements with resilient capture-phase dismissal & escape key
            const modalClose = document.getElementById('modal-close');
            if (modalClose) {
                modalClose.addEventListener('click', () => this.closeModal());
            }

            const modalContainer = document.getElementById('modal-container');
            if (modalContainer) {
                modalContainer.addEventListener('click', (e) => {
                    if (e.target.id === 'modal-container') this.closeModal();
                });
            }

            document.addEventListener('click', (e) => {
                const isCloseBtn = e.target && (e.target.closest('#modal-close') || e.target.closest('.modal-close') || e.target.closest('[data-dismiss="modal"]'));
                if (isCloseBtn) {
                    this.closeModal();
                } else if (e.target && e.target.id === 'modal-container') {
                    this.closeModal();
                }
            }, true);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' || e.code === 'Escape') {
                    const container = document.getElementById('modal-container');
                    if (container && container.classList.contains('active')) {
                        this.closeModal();
                    }
                }
            }, true);

            // Universal Help Tooltips & Progressive Disclosure Accordions
            this.initGlobalTooltips();
            this.initAccordions();

            // Keyboard shortcuts (Capture-phase for Omnibox suppression & universal palette)
            const handlePaletteShortcuts = (e) => {
                const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;
                const isK = (e.key && e.key.toLowerCase() === 'k') || e.code === 'KeyK' || e.keyCode === 75 || e.which === 75;
                const isShiftP = (e.ctrlKey || e.metaKey) && e.shiftKey && (e.key?.toLowerCase() === 'p' || e.code === 'KeyP');
                const isF1 = e.key === 'F1' && !e.ctrlKey && !e.metaKey && !e.altKey;

                if ((isCtrlOrCmd && isK) || isShiftP || isF1) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === 'function') {
                        e.stopImmediatePropagation();
                    }
                    this.toggleCommandPalette();
                    return false;
                }

                if (isCtrlOrCmd && (e.key === '/' || e.code === 'Slash')) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === 'function') {
                        e.stopImmediatePropagation();
                    }
                    this.toggleShortcutsCheatsheet();
                    return false;
                }

                if (isCtrlOrCmd && (e.key?.toLowerCase() === 'h' || e.code === 'KeyH')) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.openJargonBuster();
                    return false;
                }

                if (e.key === 'Escape') {
                    if (window.SPPCommandPalette) {
                        window.SPPCommandPalette.close();
                        if (typeof window.SPPCommandPalette.closeCheatsheet === 'function') {
                            window.SPPCommandPalette.closeCheatsheet();
                        }
                    }
                    this.closeModal();
                }
            };

            window.addEventListener('keydown', handlePaletteShortcuts, true);
            window.addEventListener('keyup', (e) => {
                const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;
                const isK = (e.key && e.key.toLowerCase() === 'k') || e.code === 'KeyK' || e.keyCode === 75 || e.which === 75;
                if (isCtrlOrCmd && isK) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true);

            // Close portal suggestions on outside click
            document.addEventListener('click', (e) => {
                const portal = document.getElementById('global-suggestions');
                const isSearchInput = e.target.classList.contains('spp-element') && 
                                    e.target.placeholder && 
                                    e.target.placeholder.includes('Search');
                if (portal && portal.classList.contains('active') && !portal.contains(e.target) && !isSearchInput) {
                    this.hidePortalSuggestions();
                }
            });
        } catch (err) {
            console.error("Critical error in SPPAdmin.bindEvents:", err);
        }
    }

    // =============================================
    // AUTHENTICATION
    // =============================================

    async checkAuth() {
        if (this.isAuthenticating) return;
        // Auto-consume magic link if present
        const urlParams = new URLSearchParams(window.location.search);
        const magicToken = urlParams.get('magic_token');
        if (magicToken) {
            try {
                const formData = new FormData();
                formData.append('action', 'ConsumeMagicLink');
                formData.append('token', magicToken);
                const res = await this.apiPost(formData);
                if (res.success) {
                    window.history.replaceState({}, document.title, window.location.pathname); // clear token from URL
                    this.notify('Magic link login successful', 'success');
                    // proceed to normal checkAuth logic
                } else {
                    this.notify(res.message || 'Invalid magic link', 'error');
                }
            } catch (e) {
                console.error("Magic link error:", e);
            }
        }

        try {
            const res = await this.api('check_auth');
            if (res.success) {
                this.user = res.data;
                if (window.spp_root_store?.set) {
                    window.spp_root_store.set({ user: this.user });
                }
                await this.showWorkspace();
                
                // Update Sidebar Profile
                const profileRes = await this.api('get_profile');
                if (profileRes.success) {
                    this.updateUserDisplay(profileRes.data);
                }
            } else {
                if (!this.user && !this.isAuthenticating) {
                    this.showLogin();
                }
            }
        } catch (e) {
            if (!this.user && !this.isAuthenticating) {
                this.showLogin();
            }
        }
    }

    async sendMagicLink() {
        const email = document.getElementById('magic_email').value.trim();
        if (!email) {
            this.notify('Please enter your email address.', 'error');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'SendMagicLink');
            formData.append('email', email);
            const res = await this.apiPost(formData);
            if (res.success) {
                this.notify('Magic link sent if the account exists.', 'success');
                document.getElementById('magic_email').value = '';
                document.getElementById('magic-email-group').style.display = 'none';
                document.getElementById('btn-show-magic').style.display = 'block';
            } else {
                this.notify(res.message || 'Failed to send link', 'error');
            }
        } catch (e) {
            this.notify('Connection error', 'error');
        }
    }

    updateUserDisplay(profile) {
        const nameDisplay = document.getElementById('user-display-name');
        if (nameDisplay) nameDisplay.textContent = profile.username || 'System';
        
        const avatarDisplay = document.getElementById('user-avatar');
        if (avatarDisplay) avatarDisplay.textContent = (profile.username || 'S').charAt(0).toUpperCase();

        const roleDisplay = document.getElementById('user-display-role');
        if (roleDisplay) roleDisplay.textContent = profile.role || 'Developer';
    }

    async handleLogin(e) {
        e.preventDefault();
        console.log("Login form submit intercepted.");
        
        const btn = e.target.querySelector('button[type="submit"]');
        const origText = btn ? btn.textContent : 'Authenticate';
        
        if (btn) {
            btn.textContent = 'Authenticating...';
            btn.disabled = true;
        }

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const mfaCodeEl = document.getElementById('mfa_code');
        const mfaCode = mfaCodeEl ? mfaCodeEl.value.trim() : '';

        if (!username || !password) {
            this.notify('Please enter both username and password.', 'error');
            if (btn) {
                btn.textContent = origText;
                btn.disabled = false;
            }
            return;
        }

        const formData = new FormData();
        
        if (this.mfaChallengeToken) {
            formData.append('action', 'VerifyMFA');
            formData.append('challenge_token', this.mfaChallengeToken);
            formData.append('code', mfaCode);
            if (!mfaCode) {
                this.notify('Please enter your authenticator code.', 'error');
                if (btn) { btn.textContent = origText; btn.disabled = false; }
                return;
            }
        } else {
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);
        }

        console.log(`Attempting login for user: ${username}`);
        this.isAuthenticating = true;
        try {
            const res = await this.apiPost(formData);
            console.log("Login API response:", res);
            
            if (res.success) {
                if (res.data && res.data.mfa_challenge) {
                    this.mfaChallengeToken = res.data.mfa_challenge;
                    document.getElementById('mfa-section').style.display = 'block';
                    document.getElementById('username').readOnly = true;
                    document.getElementById('password').readOnly = true;
                    mfaCodeEl.focus();
                    if (btn) btn.textContent = 'Verify Code';
                    this.notify('Multi-Factor Authentication required.', 'info');
                } else {
                    this.mfaChallengeToken = null;
                    document.getElementById('mfa-section').style.display = 'none';
                    this.user = { username: res.data.user || username };
                    if (window.spp_root_store?.set) {
                    window.spp_root_store.set({ user: this.user });
                }
                    
                    // Immediately transition DOM to workspace to prevent any flash or redirect back to login
                    const loginLayer = document.getElementById('login-layer');
                    if (loginLayer) loginLayer.classList.remove('active');
                    const workspace = document.getElementById('ide-workspace') || document.getElementById('workspace-layer');
                    if (workspace) workspace.classList.add('active');

                    const currentHash = location.hash ? location.hash.replace('#', '').split('?')[0].trim() : '';
                    const targetHash = (currentHash && currentHash !== 'dashboard') ? currentHash : 'dashboard';
                    if (location.hash.replace('#', '').split('?')[0] !== targetHash) {
                        location.hash = targetHash;
                    }
                    await this.showWorkspace();
                    this.notify(`Welcome back, ${this.user.username}`, 'success');

                    // Update Sidebar Profile
                    const profileRes = await this.api('get_profile');
                    if (profileRes.success) {
                        this.updateUserDisplay(profileRes.data);
                    }
                }
            } else {
                this.handleApiErrors(res);

                if (this.mfaChallengeToken) {
                    mfaCodeEl.value = '';
                    mfaCodeEl.focus();
                }
            }
        } catch (err) {
            console.error("Login Error:", err);
            this.notify('Connection error. Is the server running?', 'error');
        } finally {
            this.isAuthenticating = false;
        }

        if (btn) {
            btn.textContent = origText;
            btn.disabled = false;
        }
    }

    async handleLogout() {
        await this.api('logout');
        this.user = null;
        if (window.spp_root_store?.set) {
            window.spp_root_store.set({ user: null });
        }
        this.showLogin();
        this.notify('Successfully logged out.');
    }

    // =============================================
    // THEME MANAGEMENT
    // =============================================

    setTheme(theme) {
        this.theme = theme;
        localStorage.setItem('spp_admin_theme', theme);
        this.applyTheme(theme);

    }

    applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        
        // Sync SPPUX Global Variable Theme if library is loaded
        if (window.SPPUX && window.SPPUX.Theme) {
            window.SPPUX.Theme.set ? window.SPPUX.Theme.set(theme) : (window.SPPUX.Theme.current = theme);
        }

        // Special cosmetic tweaks for body backgrounds if needed
        if (theme === 'day') {
            document.body.style.backgroundImage = 'none';
        } else {
            document.body.style.backgroundImage = '';
        }

        // Dispatch global theme change event
        window.dispatchEvent(new CustomEvent('spp:theme-change', { detail: { theme } }));

        // Re-render active view if it implements onThemeChange or update
        if (this.viewInstances && this.currentView && this.viewInstances[this.currentView]) {
            const inst = this.viewInstances[this.currentView];
            if (typeof inst.onThemeChange === 'function') {
                inst.onThemeChange(theme);
            } else if (typeof inst.update === 'function') {
                inst.update();
            }
        }
    }


    getUIMode() {
        return this.uiMode || localStorage.getItem('spp_admin_ui_mode') || 'normal';
    }

    setUIMode(mode) {
        if (mode !== 'normal' && mode !== 'expert') mode = 'normal';
        this.uiMode = mode;
        try {
            localStorage.setItem('spp_admin_ui_mode', mode);
        } catch (e) {}
        this.applyUIMode(mode);
        if (typeof this.notify === 'function') {
            this.notify(mode === 'normal' 
                ? '🌐 Switched to Normal Mode (Site & App Management)'
                : '⚡ Switched to Expert Mode (Full Sovereign Server Operations)', 
                'info'
            );
        }
    }

    applyUIMode(mode) {
        if (!mode) mode = this.getUIMode();
        document.body.setAttribute('data-ui-mode', mode);
        document.documentElement.setAttribute('data-ui-mode', mode);

        // Update sidebar switcher buttons
        const btnNormal = document.getElementById('btn-mode-normal');
        const btnExpert = document.getElementById('btn-mode-expert');
        if (btnNormal) btnNormal.classList.toggle('active-mode', mode === 'normal');
        if (btnExpert) btnExpert.classList.toggle('active-mode', mode === 'expert');

        // Update top header mode button
        const headerPill = document.getElementById('header-ui-mode-pill');
        if (headerPill) {
            headerPill.innerHTML = mode === 'normal'
                ? '<span>🌐</span> Normal Mode'
                : '<span>⚡</span> Expert Mode';
            headerPill.title = mode === 'normal'
                ? 'Current: Normal Mode (Click to switch to Expert Mode)'
                : 'Current: Expert Mode (Click to switch to Normal Mode)';
        }

        // Dispatch global mode change event
        window.dispatchEvent(new CustomEvent('spp:ui-mode-change', { detail: { mode } }));

        // Re-render active view if it supports mode change
        if (this.viewInstances && this.currentView && this.viewInstances[this.currentView]) {
            const inst = this.viewInstances[this.currentView];
            if (typeof inst.onUIModeChange === 'function') {
                inst.onUIModeChange(mode);
            } else if (typeof inst.update === 'function') {
                inst.update();
            }
        }
    }

    async openProfileEditor() {
        const res = await this.api('get_profile');
        if (!res.success) {
            this.notify(res.message || 'Failed to fetch profile', 'error');
            return;
        }

        const profile = res.data;
        const modal = document.getElementById('modal-container');
        document.getElementById('modal-title').textContent = 'My Profile';
        
        document.getElementById('modal-body').innerHTML = `
            <div class="profile-editor">
                <div class="input-group">
                    <label>Username</label>
                    <input type="text" id="prof-username" value="${this.escapeHtml(profile.username)}" placeholder="Username">
                </div>
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" id="prof-email" value="${this.escapeHtml(profile.email || '')}" placeholder="email@example.com">
                </div>
                <div class="input-group">
                    <label>New Password (Leave blank to keep current)</label>
                    <input type="password" id="prof-password" placeholder="••••••••">
                </div>
                
                <div class="mfa-settings" style="margin-top: 1.5rem; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 8px;">
                    <h4>Multi-Factor Authentication (MFA)</h4>
                    <p style="font-size: 0.85rem; color: var(--text-dim);">Protect your account with a TOTP Authenticator app.</p>
                    <button id="btn-setup-mfa" class="btn secondary-btn" style="margin-top: 0.5rem;">Configure 2FA</button>
                    
                    <div id="mfa-setup-area" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                        <div id="mfa-qr-container" style="text-align: center; margin-bottom: 1rem;"></div>
                        <div class="input-group">
                            <label>Secret Key (Manual Entry)</label>
                            <code id="mfa-manual-code" style="display: block; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 4px; text-align: center; letter-spacing: 2px;"></code>
                        </div>
                        <div class="input-group">
                            <label>Verify 6-digit Code</label>
                            <input type="text" id="mfa-verify-code" placeholder="123456" maxlength="6" pattern="[0-9]*">
                        </div>
                        <button id="btn-enable-mfa" class="btn primary-btn" style="width: 100%;">Verify & Enable 2FA</button>
                    </div>
                </div>

                <div class="alert info-alert" style="margin-top: 1rem;">
                    <span class="view-icon">ℹ️</span> Changes to identity may require you to log back in.
                </div>
            </div>`;

        setTimeout(() => {
            const btnSetupMfa = document.getElementById('btn-setup-mfa');
            const btnEnableMfa = document.getElementById('btn-enable-mfa');
            
            if (btnSetupMfa) btnSetupMfa.onclick = () => this.setupMFA();
            if (btnEnableMfa) btnEnableMfa.onclick = () => this.enableMFA();
        }, 100);

        const saveBtn = document.getElementById('modal-save');
        saveBtn.textContent = 'Save Profile';
        saveBtn.onclick = async () => {
            const username = document.getElementById('prof-username').value.trim();
            const email = document.getElementById('prof-email').value.trim();
            const password = document.getElementById('prof-password').value;

            if (!username) {
                this.notify('Username is required.', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'save_user');
            fd.append('id', profile.id);
            fd.append('username', username);
            fd.append('email', email);
            if (password) fd.append('password', password);

            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            const saveRes = await this.apiPost(fd);
            if (saveRes.success) {
                this.notify('Profile updated successfully.', 'success');
                this.closeModal();
                // Update Sidebar Display
                const nameDisplay = document.getElementById('user-display-name');
                if (nameDisplay) nameDisplay.textContent = username;
                const avatarDisplay = document.getElementById('user-avatar');
                if (avatarDisplay) avatarDisplay.textContent = username.charAt(0).toUpperCase();
            } else {
                this.notify(saveRes.message || 'Update failed', 'error');
            }
            saveBtn.textContent = 'Save Profile';
            saveBtn.disabled = false;
        };
        
        modal.classList.add('active');
    }

    async setupMFA() {
        const btn = document.getElementById('btn-setup-mfa');
        btn.textContent = 'Generating...';
        btn.disabled = true;
        
        try {
            const res = await SPPAPI.call('Auth.GenerateMFASecret');
            if (res) {
                document.getElementById('mfa-setup-area').style.display = 'block';
                document.getElementById('mfa-qr-container').innerHTML = `<img src="${res.qr_code_url}" alt="QR Code" style="border-radius: 8px; border: 4px solid white;">`;
                document.getElementById('mfa-manual-code').textContent = res.manual_code;
                btn.style.display = 'none';
            }
        } catch (e) {
            this.notify(e.message || 'Failed to generate MFA secret', 'error');
            btn.textContent = 'Configure 2FA';
            btn.disabled = false;
        }
    }

    async enableMFA() {
        const codeInput = document.getElementById('mfa-verify-code');
        const code = codeInput.value.trim();
        if (!code) {
            this.notify('Enter the 6-digit code', 'error');
            return;
        }
        
        const btn = document.getElementById('btn-enable-mfa');
        btn.textContent = 'Verifying...';
        btn.disabled = true;
        
        try {
            await SPPAPI.call('Auth.EnableMFA', { code });
            // Success! 
            document.getElementById('mfa-setup-area').innerHTML = `<div class="alert success-alert" style="text-align:center;">✅ Multi-Factor Authentication is currently ENABLED.</div>`;
        } catch (e) {
            this.notify(e.message || 'Invalid code', 'error');
            btn.textContent = 'Verify & Enable 2FA';
            btn.disabled = false;
        }
    }

    // =============================================
    // ROUTING
    // =============================================

    handleRouting() {
        // Clean redundant trailing '?' from URL if present
        if (window.location.search === '?') {
            try {
                window.history.replaceState(null, '', window.location.pathname + window.location.hash);
            } catch (e) {}
        }

        let rawHash = location.hash.replace('#', '').trim() || 'dashboard';
        let baseHash = rawHash.split('?')[0];
        let queryString = rawHash.includes('?') ? rawHash.split('?')[1] : '';
        let params = {};
        if (queryString) {
            try { params = Object.fromEntries(new URLSearchParams(queryString)); } catch (e) {}
        }
        if (window.location.search && window.location.search.length > 1) {
            try {
                const searchParams = Object.fromEntries(new URLSearchParams(window.location.search));
                params = { ...searchParams, ...params };
            } catch (e) {}
        }
        
        // Backward-compatible redirects for legacy URLs
        const redirects = {
            'editor': 'coder',
            'xdb': 'entities?tab=xdb',
            'interdb': 'entities?tab=interdb',
            'spplang': 'forms?tab=spplang',
            'mobile': 'forms?tab=mobile',
            'services': 'routing?tab=services',
            'middleware': 'routing?tab=middleware',
            'parikshak': 'testing?tab=parikshak',
            'events': 'trace',
            'access': 'identity',
            'groups': 'identity',
            'copilot': 'ai',
            'sppai': 'ai'
        };

        if (redirects[baseHash]) {
            location.hash = redirects[baseHash];
            return;
        }

        let hash = baseHash;
        const expertViews = ['fleet', 'terminal', 'identity', 'shield', 'server', 'runtime', 'cron', 'metrics', 'system', 'reseller'];
        if (expertViews.includes(hash) && this.getUIMode() === 'normal') {
            this.setUIMode('expert');
        }
        this.currentView = hash;

        // Update Nav UI
        document.querySelectorAll('.nav-item').forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-view') === hash);
        });

        let icon = this.viewIcons[hash] || '📄';
        let title = this.viewTitles[hash] || 'Unknown';
        
        if (this.selectedApp && this.selectedApp !== 'default') {
            const app = this.availableApps.find(a => a.name === this.selectedApp);
            if (app && app.admin_menu) {
                const menuItem = app.admin_menu.find(m => m.id === hash);
                if (menuItem) {
                    icon = menuItem.icon || icon;
                    title = menuItem.title || title;
                }
            }
        }
        
        const viewTitle = document.getElementById('view-title');
        if (viewTitle) {
            viewTitle.innerHTML = `<span class="view-icon">${icon}</span> ${title}`;
        }

        const app = this.availableApps.find(a => a.name === hash);
        if (app && app.has_admin && this.selectedApp !== hash) {
            this.onAppContextChange(hash);
            return; // onAppContextChange will trigger loadView again
        }

        if (this.user) {
            this.loadView(hash, params);
        }
    }

    /**
     * Programmatic Navigation API for SPPAdmin / SPPDev
     * Supports navigating to any view with optional query parameters.
     */
    navigate(view, params = {}) {
        let hash = view;
        const q = new URLSearchParams(params).toString();
        if (q) hash += `?${q}`;
        window.location.hash = `#${hash}`;
        if (this.loadView) {
            this.loadView(view, params);
        }
    }

    // =============================================
    // VIEW MANAGEMENT
    // =============================================
    
    /**
     * RemoteView Component
     * Loads and renders a PHP-based view from the server.
     */
    static RemoteView = class extends (window.BaseComponent || class {}) {
        async onInit() {
            this.state = { loading: true, html: '' };
            await this.fetchView();
        }
        async fetchView() {
            console.log(`[RemoteView] Fetching ${this.props.viewName}...`);
            try {
                const res = await this.api('load_view', { view: this.props.viewName });
                console.log(`[RemoteView] Response for ${this.props.viewName}:`, res);
                if (res.success) {
                    const html = res.html || (res.data && res.data.html) || '';
                    this.setState({ loading: false, html: html });
                } else {
                    this.setState({ loading: false, error: res.message });
                }
            } catch (err) {
                console.error(`[RemoteView] Error loading ${this.props.viewName}:`, err);
                this.setState({ loading: false, error: 'Failed to load remote view.' });
            }
        }
        render() {
            if (this.state.loading) {
                return SPPUX.html`<div style="padding: 4rem; text-align: center;"><div class="sppux-spinner" style="width: 40px; height: 40px; margin: 0 auto 1.5rem auto;"></div><div style="opacity: 0.5;">Loading ${this.props.viewName}...</div></div>`;
            }
            if (this.state.error) {
                return SPPUX.html`<div class="alert error">${this.state.error}</div>`;
            }
            return new TrustedHTML(this.state.html);
        }
    }

    async loadView(view, params = {}) {
        const loadId = ++this._loadCounter;
        this.activeView = view;
        const mainContainer = document.getElementById('view-container') || document.getElementById('ide-tools-container');
        if (!mainContainer) return;
        
        const headerActions = document.getElementById('header-actions');
        if (headerActions) headerActions.innerHTML = '';
        
        params = params || {};
        
        // Initialize View Cache
        this.viewWrappers = this.viewWrappers || {};
        this.viewInstances = this.viewInstances || {};

        if (!this.viewCacheInitialized) {
            mainContainer.innerHTML = ''; // Clear initial static loading state
            this.viewCacheInitialized = true;
        }

        // Deactivate previous active view if switching away
        if (this.previousActiveView && this.previousActiveView !== view && this.viewInstances[this.previousActiveView]) {
            const prevInstance = this.viewInstances[this.previousActiveView];
            if (typeof prevInstance.onDeactivate === 'function') {
                try {
                    prevInstance.onDeactivate();
                } catch (e) {
                    console.warn(`Error during onDeactivate for ${this.previousActiveView}:`, e);
                }
            }
        }
        this.previousActiveView = view;

        // Hide all other view wrappers and any container children
        for (let k in this.viewWrappers) {
            if (k !== view && this.viewWrappers[k]) {
                this.viewWrappers[k].style.setProperty('display', 'none', 'important');
                this.viewWrappers[k].classList.add('view-wrapper-hidden');
            }
        }
        if (mainContainer.children) {
            Array.from(mainContainer.children).forEach(child => {
                if (child.id !== 'view-wrapper-' + view) {
                    child.style.setProperty('display', 'none', 'important');
                    child.classList.add('view-wrapper-hidden');
                }
            });
        }

        let container = this.viewWrappers[view];
        if (!container) {
            container = document.createElement('div');
            container.id = 'view-wrapper-' + view;
            container.style.height = '100%';
            mainContainer.appendChild(container);
            this.viewWrappers[view] = container;
            
            container.innerHTML = '<div class="loading-state">Loading section...</div>';
            this.showSkeleton(container);
        }
        container.classList.remove('view-wrapper-hidden');
        container.style.removeProperty('display');
        container.style.display = 'block';

        // Update nav sidebar active class immediately
        document.querySelectorAll('#sidebar-nav .nav-item').forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-view') === view);
        });

        if (this.viewInstances[view]) {
            // View already loaded and cached, instant switch!
            this.updateViewTitle(view);
            if (typeof this.viewInstances[view].onActivate === 'function') {
                try {
                    this.viewInstances[view].onActivate(params);
                } catch (e) {
                    console.warn(`Error during onActivate for ${view}:`, e);
                }
            }
            if (typeof this.viewInstances[view].update === 'function') {
                this.viewInstances[view].update(); // trigger a fast re-render without re-fetching
            }
            container.classList.remove('view-wrapper-hidden');
            container.style.removeProperty('display');
            container.style.display = 'block';
            return;
        }

        // List of views that should be loaded as PHP templates
        const phpHybridViews = []; // All views migrated to SPP-UX components
        
        if (phpHybridViews.includes(view)) {
            console.log(`[SPPAdmin] Loading PHP-Hybrid view: ${view}`);
            this.viewInstances[view] = new SPPAdmin.RemoteView(this, container, { viewName: view });
            await this.viewInstances[view].onInit();
            this.viewInstances[view].update();
            this.updateViewTitle(view);
            return;
        }

        try {
                const ts = Date.now(); // Dynamic version to force cache busting
                // 1. Script-Relative Component paths
                const scripts = document.getElementsByTagName('script');
                let adminJsUrl = '';
                for (let s of scripts) {
                    if (s.src && s.src.includes('admin.js')) {
                        adminJsUrl = s.src;
                        break;
                    }
                }
                const jsDir = adminJsUrl ? adminJsUrl.substring(0, adminJsUrl.lastIndexOf('/') + 1) : 'js/';
                const corePath = `${jsDir}views/${view}.js?v=${ts}`;
                
                // 2. Resolve App-Side Component Path
                const rootPath = jsDir.includes('spp/dev/js/') ? jsDir.split('spp/dev/js/')[0] : '../../';
                let appPath = `${rootPath}src/${this.selectedApp}/comp/${view}.js?v=${ts}`;
                
                // If the view name matches an app name, try to load that app's 'manage.js' or '<appname>.js'
                const appMeta = this.availableApps.find(a => a.name === view);
                if (appMeta) {
                    const srcPath = appMeta.src_path || `src/${view}`;
                    appPath = `${rootPath}${srcPath}/comp/${view}.js?v=${ts}`;
                }

                let module;
                // List of views that are strictly core framework views and should not be requested from apps
                const coreOnlyViews = ['apps', 'apptoolkit', 'commands', 'dashboard', 'entities', 'workflows', 'forms', 'identity', 'interdb', 'lifecycle', 'modules', 'parikshak', 'pages', 'routing', 'services', 'system', 'xdb', 'ai', 'trace', 'reports', 'api_keys', 'mobile', 'docs', 'editor', 'tinker', 'mesh', 'testing', 'coder', 'studio', 'domains', 'files', 'terminal', 'deploy', 'cron', 'manifests', 'mail', 'fleet', 'apm', 'webhooks', 'apistudio', 'polyglot', 'database', 'server', 'spplang', 'reseller', 'runtime', 'shield', 'metrics'];
                const isCoreView = coreOnlyViews.includes(view);
                
                // Helper to detect JavaScript syntax/parsing defects vs 404 network misses
                const isSyntaxError = (err) => {
                    if (!err) return false;
                    if (err instanceof SyntaxError) return true;
                    const msg = (err.message || String(err)).toLowerCase();
                    return msg.includes('unexpected token') || 
                           msg.includes('syntaxerror') || 
                           msg.includes('invalid or unexpected') || 
                           msg.includes('identifier') ||
                           msg.includes('missing ) after argument list');
                };

                // If a specific app is selected (not 'default' or '__sppadmin__'), and it's not a strict core view, try app-side first
                const useAppFirst = !isCoreView && this.selectedApp !== 'default' && this.selectedApp !== '__sppadmin__';

                if (useAppFirst) {
                    try {
                        module = await import(appPath);
                        console.log(`Loaded app-side component (Priority): ${view}`);
                    } catch (e) {
                        if (isSyntaxError(e)) {
                            console.error(`Syntax error in app component "${view}":`, e);
                            throw new Error(`Syntax Error in App Component "${view}":\n${e.message}\nFile: ${appPath}`);
                        }
                        console.warn(`App-side component not found, trying core: ${corePath}`);
                        try {
                            module = await import(corePath);
                            console.log(`Loaded core component (Fallback): ${view}`);
                        } catch (e2) {
                            if (isSyntaxError(e2)) {
                                console.error(`Syntax error in core component "${view}":`, e2);
                                throw new Error(`Syntax Error in Core Component "${view}":\n${e2.message}\nFile: ${corePath}`);
                            }
                            console.error(`Core component also failed: ${corePath}`, e2);
                            // Try manage.js fallback for apps
                            if (appMeta) {
                                try {
                                    const srcPath = appMeta.src_path || `src/${view}`;
                                    const managePath = `${rootPath}${srcPath}/comp/manage.js?v=${ts}`;
                                    module = await import(managePath);
                                    console.log(`Loaded app-side component: manage.js for ${view}`);
                                } catch (e3) {
                                    throw new Error(`Component "${view}" could not be loaded from app or core.\nApp: ${appPath} -> ${e.message}\nCore: ${corePath} -> ${e2.message}`);
                                }
                            } else {
                                throw new Error(`Component "${view}" could not be loaded from app or core.\nApp: ${appPath} -> ${e.message}\nCore: ${corePath} -> ${e2.message}`);
                            }
                        }
                    }
                } else {
                    try {
                        // Try to load core component first
                        module = await import(corePath);
                        console.log(`Loaded core component: ${view}`);
                    } catch (e) {
                        console.error(`Failed to load core component: ${corePath}`, e);
                        // If core component has a syntax error, do NOT failover to appPath!
                        if (isSyntaxError(e)) {
                            throw new Error(`Syntax Error in Core Component "${view}":\n${e.message}\nFile: ${corePath}`);
                        }
                        try {
                            module = await import(appPath);
                            console.log(`Loaded app-side component: ${view}`);
                        } catch (e2) {
                            if (isSyntaxError(e2)) {
                                throw new Error(`Syntax Error in App Component "${view}":\n${e2.message}\nFile: ${appPath}`);
                            }
                            throw new Error(`Component "${view}" failed to load.\nCore: ${corePath} -> ${e.message}\nApp: ${appPath} -> ${e2.message}`);
                        }
                    }
                }
                
                if (!module) {
                    // 3. Fallback to Legacy Hardcoded Methods
                        const legacyMethod = 'render' + view.charAt(0).toUpperCase() + view.slice(1);
                    if (typeof this[legacyMethod] === 'function') {
                        console.log(`Falling back to legacy method: ${legacyMethod}`);
                        
                        // Handle legacy data fetching if needed (Logic duplicated from old switch)
                        await this.executeLegacyViewLogic(view);
                        return;
                    }
                    throw new Error(`Component or Legacy View "${view}" not found.`);
                }

                if (loadId !== this._loadCounter) return;

                // 3. Render SPP-UX Component
                if (module.default) {
                    this.viewInstances[view] = new module.default(this, container, params);
                    if (typeof this.viewInstances[view].onInit === 'function') {
                        await this.viewInstances[view].onInit();
                    }
                    if (loadId !== this._loadCounter) {
                        console.log(`[SPPAdmin] loadView('${view}') superseded after onInit.`);
                        return;
                    }
                    if (typeof this.viewInstances[view].update === 'function') {
                        this.viewInstances[view].update();
                    }
                    if (typeof this.viewInstances[view].onActivate === 'function') {
                        try {
                            await this.viewInstances[view].onActivate(params);
                        } catch (e) {
                            console.warn(`Error during onActivate for ${view}:`, e);
                        }
                    }

                    // Enforce that the active view wrapper is visible and all others are hidden
                    container.classList.remove('view-wrapper-hidden');
                    container.style.removeProperty('display');
                    container.style.display = 'block';

                    for (let k in this.viewWrappers) {
                        if (k !== view && this.viewWrappers[k]) {
                            this.viewWrappers[k].style.setProperty('display', 'none', 'important');
                            this.viewWrappers[k].classList.add('view-wrapper-hidden');
                        }
                    }

                    this.updateViewTitle(view);
                    document.querySelectorAll('#sidebar-nav .nav-item').forEach(link => {
                        link.classList.toggle('active', link.getAttribute('data-view') === view);
                    });
                }
            } catch (err) {
                if (loadId !== this._loadCounter) return;
                console.error('[SPPAdmin] View load error:', err);
                delete this.viewInstances[view];

                // Intelligent Error Categorization & Diagnosis
                const rawMsg = err.message || String(err);
                const isSyntax = err instanceof SyntaxError || 
                    rawMsg.includes('Unexpected token') || 
                    rawMsg.includes('SyntaxError') || 
                    rawMsg.includes('Syntax Error') ||
                    rawMsg.includes('Invalid or unexpected');
                const isNotFound = rawMsg.includes('Failed to fetch dynamically imported module') || 
                    rawMsg.includes('404') || 
                    rawMsg.includes('not found') ||
                    rawMsg.includes('Cannot find module');

                let errorBadge = 'Runtime Exception';
                let badgeColor = '#d29922'; // Warning gold
                let categoryTitle = 'Component Execution Error';
                let plainExplanation = `An uncaught exception occurred while loading or initializing the <strong>${this.escapeHtml(view)}</strong> component.`;
                let diagnosticTips = [
                    'Open the browser developer console (<kbd style="background:#21262d; padding:1px 5px; border-radius:3px;">F12</kbd> &rarr; Console) to view the full execution callstack.',
                    'Check for null property access, invalid configuration parameters, or unhandled promise rejections.'
                ];

                if (isSyntax) {
                    errorBadge = 'Syntax Error';
                    badgeColor = '#f85149'; // Error red
                    categoryTitle = 'JavaScript Parsing / Syntax Error';
                    plainExplanation = `The browser JavaScript runtime could not parse the component module for <strong>${this.escapeHtml(view)}</strong> due to invalid syntax.`;
                    diagnosticTips = [
                        'A syntax error indicates malformed code, such as an unexpected token, an unclosed brace <code>{ }</code>, mismatched parentheses <code>( )</code>, or a misplaced <code>else</code>/<code>catch</code> statement.',
                        'Inspect the file and line reported below, or run a quick syntax check in your terminal: <code>node --check &lt;file&gt;</code>.'
                    ];
                } else if (isNotFound) {
                    errorBadge = 'Module Not Found';
                    badgeColor = '#f0883e'; // Orange
                    categoryTitle = 'Component Module Missing or Unreachable';
                    plainExplanation = `Neither the active application component nor the core framework view could be loaded for <strong>${this.escapeHtml(view)}</strong>.`;
                    diagnosticTips = [
                        `Verify that the component file exists at <code>spp/dev/js/views/${this.escapeHtml(view)}.js</code> or <code>src/${this.escapeHtml(this.selectedApp)}/comp/${this.escapeHtml(view)}.js</code>.`,
                        'Ensure your web server (Apache/Nginx) has read permissions for the file.'
                    ];
                }

                const retryFn = `window.admin ? window.admin.loadView('${this.escapeHtml(view)}') : window.location.reload()`;
                const copyData = {
                    view,
                    app: this.selectedApp,
                    error: rawMsg,
                    timestamp: new Date().toISOString(),
                    url: window.location.href,
                    userAgent: navigator.userAgent
                };

                container.innerHTML = `
                <div style="max-width:880px; margin:40px auto; background:#0d1117; border:1px solid #30363d; border-radius:12px; box-shadow:0 12px 32px rgba(0,0,0,0.5); overflow:hidden; font-family:Inter,system-ui,sans-serif;">
                    <!-- Card Header -->
                    <div style="background:#161b22; padding:18px 24px; border-bottom:1px solid #30363d; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.6rem;">⚠️</span>
                            <div>
                                <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:#f0f6fc; display:flex; align-items:center; gap:10px;">
                                    ${categoryTitle}
                                    <span style="background:${badgeColor}22; color:${badgeColor}; border:1px solid ${badgeColor}66; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:12px; text-transform:uppercase; letter-spacing:0.5px;">
                                        ${errorBadge}
                                    </span>
                                </h3>
                                <p style="margin:4px 0 0; font-size:0.8rem; color:#8b949e;">Component: <code style="color:#58a6ff; background:#1f242c; padding:1px 6px; border-radius:4px;">${this.escapeHtml(view)}</code> &bull; Context: <strong style="color:#f0f6fc;">${this.escapeHtml(this.selectedApp)}</strong></p>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="btn primary-btn btn-sm" onclick="${retryFn}" style="display:flex; align-items:center; gap:6px; padding:6px 14px; font-size:0.8rem; border-radius:6px; cursor:pointer;">
                                <span>🔄</span> Retry Loading
                            </button>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div style="padding:24px;">
                        <p style="color:#c9d1d9; font-size:0.9rem; line-height:1.5; margin-top:0;">
                            ${plainExplanation}
                        </p>

                        <!-- Error Message Console Box -->
                        <div style="margin:16px 0; background:#090d13; border:1px solid rgba(248,81,73,0.35); border-left:4px solid ${badgeColor}; border-radius:6px; padding:14px; overflow-x:auto;">
                            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; color:${badgeColor}; margin-bottom:8px;">Diagnostic Log Output</div>
                            <pre style="margin:0; font-family:'Fira Code',Consolas,monospace; font-size:0.82rem; color:#f0f6fc; white-space:pre-wrap; word-break:break-word; line-height:1.5;">${this.escapeHtml(rawMsg)}</pre>
                        </div>

                        <!-- Actionable Suggestions -->
                        <div style="background:#161b22; border:1px solid #30363d; border-radius:8px; padding:16px; margin-top:20px;">
                            <h4 style="margin:0 0 10px; font-size:0.85rem; font-weight:600; color:#58a6ff; display:flex; align-items:center; gap:6px;">
                                <span>💡</span> Troubleshooting & Remediation
                            </h4>
                            <ul style="margin:0; padding-left:20px; font-size:0.82rem; color:#8b949e; line-height:1.6;">
                                ${diagnosticTips.map(tip => `<li>${tip}</li>`).join('')}
                            </ul>
                        </div>

                        <!-- Quick Actions Bar -->
                        <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding-top:16px; border-top:1px solid #21262d;">
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="btn ghost-btn btn-sm" onclick="navigator.clipboard.writeText(${this.escapeHtml(JSON.stringify(JSON.stringify(copyData)))}).then(() => alert('Diagnostic details copied to clipboard!')).catch(() => prompt('Copy diagnostics:', ${this.escapeHtml(JSON.stringify(JSON.stringify(copyData)))}))" style="font-size:0.78rem; padding:5px 12px; border:1px solid #30363d; border-radius:6px; color:#c9d1d9; cursor:pointer;">
                                    📋 Copy Diagnostics
                                </button>
                                <button type="button" class="btn ghost-btn btn-sm" onclick="window.location.hash='#editor?file=' + encodeURIComponent('spp/dev/js/views/${this.escapeHtml(view)}.js')" style="font-size:0.78rem; padding:5px 12px; border:1px solid #30363d; border-radius:6px; color:#58a6ff; cursor:pointer;">
                                    💻 Open in Code Studio
                                </button>
                            </div>
                            <span style="font-size:0.75rem; color:#484f58;">Press <kbd style="background:#21262d; padding:2px 5px; border-radius:4px; color:#8b949e;">F12</kbd> for browser console stack trace</span>
                        </div>
                    </div>
                </div>`;
            }
    }

    async executeLegacyViewLogic(view) {
        // This bridge handles legacy data fetching for views not yet fully componentized.
        switch (view) {
            case 'system':
                const [sysRes, bridgeRes] = await Promise.all([this.api('get_system_info'), this.api('get_bridge_info')]);
                if (sysRes.success) this.renderSystem(sysRes.data, bridgeRes.data || null);
                break;
            default:
                console.warn(`Legacy logic for view "${view}" has been deprecated or removed.`);
        }
    }

    /**
     * callAppService
     * Standardized Bridge to PHP logic in src/<appname>/serv/
     */
    async callAppService(serviceName, params = {}) {
        const formData = new FormData();
        formData.append('action', 'call_service');
        formData.append('appname', this.selectedApp);
        formData.append('service', serviceName);
        formData.append('params', JSON.stringify(params));
        
        const res = await this.apiPost(formData);
        if (res.success) return res.data;
        throw new Error(res.message || `Service ${serviceName} failed.`);
    }

    showSkeleton(container) {
        let cards = '';
        for (let i = 0; i < 6; i++) {
            cards += `<div class="skeleton-card">
                <div class="skeleton-line"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line"></div>
            </div>`;
        }
        container.innerHTML = `<div class="skeleton-grid">${cards}</div>`;
    }

    updateViewTitle(view) {
        const titleEl = document.getElementById('view-title');
        if (titleEl) {
            let icon = this.viewIcons[view] || '📦';
            let title = this.viewTitles[view] || view;
            
            // Check if it's an app-specific view
            if (this.selectedApp && this.selectedApp !== 'default') {
                const app = this.availableApps.find(a => a.name === this.selectedApp);
                if (app && app.admin_menu) {
                    const menuItem = app.admin_menu.find(m => m.id === view);
                    if (menuItem) {
                        icon = menuItem.icon || icon;
                        title = menuItem.title || title;
                    }
                }
            }
            
            titleEl.innerHTML = `<span class="view-icon">${icon}</span> ${title}`;
        }
    }

    // =============================================
    // APP CONTEXT MANAGEMENT
    // =============================================

    async loadApps() {
        try {
            const res = await this.api('list_apps');
            if (res.success && res.data.apps) {
                if (Array.isArray(res.data.apps)) {
                    this.availableApps = res.data.apps;
                } else {
                    this.availableApps = Object.entries(res.data.apps).map(([name, cfg]) => {
                        return typeof cfg === 'object' ? { ...cfg, name } : { name, title: cfg };
                    });
                }
                this.renderAppSelector();
            }
        } catch (err) {
            console.error('Failed to load apps:', err);
        }
    }

    renderAppSelector() {
        const container = document.getElementById('app-selector-container');
        if (!container) return;
        
        if (!Array.isArray(this.availableApps) || this.availableApps.length === 0) {
            this.loadApps();
            return;
        }

        const options = this.availableApps.map(app => `
            <option value="${app.name}" ${app.name === this.selectedApp ? 'selected' : ''}>
                ${app.icon || '🛠️'} ${app.title || app.name}
            </option>
        `).join('');
        
        container.innerHTML = `
            <div class="sidebar-selector-wrap" style="padding: 0 1rem; margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.1em; padding-left: 2px;">Active Context</label>
                <select class="app-context-select" onchange="admin.onAppContextChange(this.value)" style="width: 100%; font-size: 0.75rem;">
                    ${options}
                </select>
            </div>
        `;
    }

    onModuleFilterChange(val) {
        localStorage.setItem('spp_admin_mod_filter', val);
        this.loadView('modules');
    }

    async switchApp(appname) {
        return await this.onAppContextChange(appname);
    }

    async onAppContextChange(appname) {
        this.selectedApp = appname;
        localStorage.setItem('spp_admin_selected_app', appname);

        // 1. Synchronize Global Root Store so all components read the new context
        if (window.spp_root_store && typeof window.spp_root_store.set === 'function') {
            window.spp_root_store.set({ selectedApp: appname });
        }

        // 2. Synchronize Left Sidebar Active Context selector
        this.renderAppSelector();
        const sidebarSelect = document.querySelector('.app-context-select');
        if (sidebarSelect && sidebarSelect.value !== appname) {
            sidebarSelect.value = appname;
        }

        // 3. Synchronize SPP Studio app dropdown if present in DOM
        const studioSelect = document.querySelector('.studio-app-select');
        if (studioSelect && studioSelect.value !== appname) {
            studioSelect.value = appname;
        }

        // 4. Notify backend to set session context
        try {
            await this.api('switch_context', { appname });
        } catch (e) {
            console.warn('Backend context update failed:', e);
        }


        
        // 3. Dynamically update the app-specific sidebar menu
        const container = document.getElementById('app-specific-menu-container');
        if (container) {
            container.innerHTML = '';
            const app = this.availableApps.find(a => a.name === appname);
            if (app && app.admin_menu && app.admin_menu.length > 0) {
                let html = '<ul style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">';
                html += '<li style="padding: 0 20px 10px; font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.1em;">App Modules</li>';
                app.admin_menu.forEach(item => {
                    html += `<li><a href="#${item.id}" class="nav-item" data-view="${item.id}">`;
                    html += `<span class="icon">${item.icon || '📦'}</span> ${item.title}`;
                    html += `</a></li>`;
                });
                html += '</ul>';
                container.innerHTML = html;
                
                // Re-attach active state logic
                document.querySelectorAll('.nav-item').forEach(link => {
                    link.classList.toggle('active', link.getAttribute('data-view') === this.currentView);
                });
            }
        }
        
        // 4. Invalidate all cached view instances and DOM wrappers so every page re-renders fresh for the new context
        for (let k in this.viewInstances) {
            if (this.viewInstances[k] && typeof this.viewInstances[k].dispose === 'function') {
                try { this.viewInstances[k].dispose(); } catch (e) {}
            }
        }
        this.viewInstances = {};
        for (let k in this.viewWrappers) {
            if (this.viewWrappers[k] && this.viewWrappers[k].parentNode) {
                this.viewWrappers[k].parentNode.removeChild(this.viewWrappers[k]);
            }
        }
        this.viewWrappers = {};

        // 5. Broadcast global context change event
        window.dispatchEvent(new CustomEvent('spp:context-change', { detail: { appname } }));

        // 6. Re-load current view with the new context data
        await this.loadView(this.currentView);
    }

    // =============================================
    // COMPONENT HELPERS
    // =============================================

    openSubEditor(title, html, data, onSave) {
        return SPPUX.openSubEditor(title, html, data, onSave);
    }

    updateSubEditor(html) {
        return SPPUX.updateSubEditor(html);
    }

    prompt(title, message, callback) {
        if (window.SPPUX && SPPUX.Prompt) {
            SPPUX.Prompt.show(title, message, callback);
        } else {
            const val = window.prompt(message);
            if (val !== null) callback(val);
        }
    }

    confirm(title, message, callback) {
        if (window.SPPUX && SPPUX.Confirm) {
            SPPUX.Confirm.show(title, message, callback);
        } else {
            if (window.confirm(message)) callback();
        }
    }

    // =============================================
    // API HELPERS
    // =============================================

    async api(action, params = {}, options = { lock: false }) {
        const shouldLock = Boolean(options && options.lock === true);
        if (shouldLock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.start();
        try {
            params.appname = this.selectedApp;
            // Unify with SPPUX.api which handles LiveAction instructions automatically
            const res = await SPPUX.api(action, params);
            if (res.errors_html) this.handleApiErrors(res);
            return res;
        } finally {
            if (shouldLock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.stop();
        }
    }

    async apiPost(actionOrFormData, params = {}, options = { lock: false }) {
        const shouldLock = Boolean(options && options.lock === true);
        if (shouldLock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.start();
        try {
            let res;
            if (actionOrFormData instanceof FormData) {
                if (!actionOrFormData.has('appname')) {
                    actionOrFormData.append('appname', this.selectedApp);
                }
                res = await SPPUX.apiPost(actionOrFormData);
            } else {
                params.appname = this.selectedApp;
                res = await SPPUX.api(actionOrFormData, params);
            }
            if (res.errors_html) this.handleApiErrors(res);
            return res;
        } finally {
            if (shouldLock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.stop();
        }
    }

    // =============================================
    // UI STATE
    // =============================================

    showLogin() {
        if (this.user || this.isAuthenticating || window.spp_root_store?.get?.()?.user) return;
        const loginLayer = document.getElementById('login-layer');
        if (loginLayer) loginLayer.classList.add('active');
        
        const workspace = document.getElementById('ide-workspace') || document.getElementById('workspace-layer');
        if (workspace) workspace.classList.remove('active');
        // Focus username field
        setTimeout(() => {
            const unField = document.getElementById('username');
            if (unField) unField.focus();
        }, 600);
    }

    async showWorkspace() {
        const loginLayer = document.getElementById('login-layer');
        if (loginLayer) loginLayer.classList.remove('active');
        
        const workspace = document.getElementById('ide-workspace') || document.getElementById('workspace-layer');
        if (workspace) workspace.classList.add('active');

        // Update user info in sidebar
        if (this.user) {
            const username = this.user.username || this.user;
            const avatarEl = document.getElementById('user-avatar');
            const nameEl = document.getElementById('user-display-name');
            if (avatarEl) avatarEl.textContent = username.charAt(0).toUpperCase();
            if (nameEl) nameEl.textContent = username;
        }


        this.restoreSidebarState();
        this.handleRouting();

        // Non-blocking background sync for apps and permissions
        Promise.all([
            this.loadApps(),
            this.loadAdminPermissions()
        ]).catch(e => console.warn("Workspace background sync error:", e));
    }

    restoreSidebarState() {
        const savedCollapsed = localStorage.getItem('spp_sidebar_collapsed') === 'true';
        const workspace = document.getElementById('ide-workspace') || document.getElementById('workspace-layer');
        if (workspace) {
            workspace.classList.toggle('sidebar-collapsed', savedCollapsed);
        }
        const toggleBtn = document.getElementById('global-sidebar-toggle-btn');
        if (toggleBtn) {
            toggleBtn.title = savedCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
            toggleBtn.classList.toggle('active', savedCollapsed);
        }
    }

    toggleSidebar(forceState) {
        const workspace = document.getElementById('ide-workspace') || document.getElementById('workspace-layer');
        if (!workspace) return false;
        const isCurrentlyCollapsed = workspace.classList.contains('sidebar-collapsed');
        const nextState = typeof forceState === 'boolean' ? forceState : !isCurrentlyCollapsed;
        workspace.classList.toggle('sidebar-collapsed', nextState);
        localStorage.setItem('spp_sidebar_collapsed', String(nextState));

        const toggleBtn = document.getElementById('global-sidebar-toggle-btn');
        if (toggleBtn) {
            toggleBtn.title = nextState ? 'Expand Sidebar' : 'Collapse Sidebar';
            toggleBtn.classList.toggle('active', nextState);
        }

        window.dispatchEvent(new CustomEvent('spp:sidebar-toggle', { detail: { collapsed: nextState } }));
        return nextState;
    }

    /**
     * Fetch the current user's admin scopes and hide sidebar items
     * they don't have access to.
     */
    async loadAdminPermissions() {
        try {
            const res = await this.api('get_admin_permissions');
            if (res.success && res.data) {
                const rawScopes = res.data.scopes;
                this.adminScopes = Array.isArray(rawScopes)
                    ? rawScopes
                    : (rawScopes && typeof rawScopes === 'object' ? Object.values(rawScopes) : []);
                this.adminScopeMap = res.data.scope_map || {};
                this.applyAdminScopeGating();
            }
        } catch (e) {
            console.warn('[RBAC] Could not load admin permissions, showing all:', e);
            this.adminScopes = []; // Fail-open: show everything
        }
    }

    applyAdminScopeGating() {
        if (!this.adminScopes || !Array.isArray(this.adminScopes) || this.adminScopes.length === 0) return;

        document.querySelectorAll('#sidebar-nav .nav-item').forEach(link => {
            const view = link.getAttribute('data-view');
            if (!view) return;
            const requiredScope = this.adminScopeMap[view];
            if (requiredScope && !this.adminScopes.includes(requiredScope) && !this.adminScopes.includes('admin.*')) {
                const li = link.closest('li');
                if (li) li.style.display = 'none';
            }
        });
    }

    notify(message, type = 'info') {
        if (!message) return;
        if (window.SPPUX && SPPUX.Notify) SPPUX.Notify.show(message, type);
        else console.log(`[${type}] ${message}`);
    }

    handleApiErrors(res) {
        if (res.errors_html && res.errors_html.trim()) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast error';
            toast.innerHTML = res.errors_html;
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('removing');
                setTimeout(() => toast.remove(), 300);
            }, 6000);
        } else if (res.message) {
            this.notify(res.message, 'error');
        }
    }

    openModal(title, content = '', actions = []) {
        this._activeModal = SPPUX.openModal(title, content, actions.length ? actions : [
            { label: 'Cancel', type: 'secondary', fn: (m) => m.close() },
            { label: 'Save Changes', type: 'primary', fn: () => this.saveCurrentModal() }
        ]);
        if (this.viewInstance) this.viewInstance._registerGlobalHandlers();
        return this._activeModal;
    }

    updateModal(title, content, actions = null) {
        if (window.SPPUX && typeof SPPUX.updateModal === 'function') {
            this._activeModal = SPPUX.updateModal(title, content, actions || []);
            if (this.viewInstance) this.viewInstance._registerGlobalHandlers();
            return this._activeModal;
        }
        if (this._activeModal) {
            this._activeModal.props.title = title;
            this._activeModal.props.content = content;
            if (actions) this._activeModal.props.actions = actions;
            if (typeof this._activeModal.setState === 'function') {
                this._activeModal.setState({ title, content, actions: actions || this._activeModal.props.actions });
            } else {
                this._activeModal.update();
            }
            if (this.viewInstance) this.viewInstance._registerGlobalHandlers();
        } else {
            this.openModal(title, content, actions || []);
        }
    }

    closeModal() {
        if (this._activeModal) {
            try { this._activeModal.close(); } catch (_) {}
            this._activeModal = null;
        }
        const modalContainer = document.getElementById('modal-container');
        if (modalContainer) {
            modalContainer.classList.remove('active');
        }
    }

    openCommandPalette() {
        if (window.SPPCommandPalette && typeof window.SPPCommandPalette.open === 'function') {
            window.SPPCommandPalette.open();
        }
    }

    closeCommandPalette() {
        if (window.SPPCommandPalette && typeof window.SPPCommandPalette.close === 'function') {
            window.SPPCommandPalette.close();
        }
    }

    toggleCommandPalette() {
        if (window.SPPCommandPalette && typeof window.SPPCommandPalette.toggle === 'function') {
            window.SPPCommandPalette.toggle();
        }
    }

    openShortcutsCheatsheet() {
        if (window.SPPCommandPalette && typeof window.SPPCommandPalette.openCheatsheet === 'function') {
            window.SPPCommandPalette.openCheatsheet();
        }
    }

    closeShortcutsCheatsheet() {
        if (window.SPPCommandPalette && typeof window.SPPCommandPalette.closeCheatsheet === 'function') {
            window.SPPCommandPalette.closeCheatsheet();
        }
    }

    toggleShortcutsCheatsheet() {
        if (window.SPPCommandPalette && typeof window.SPPCommandPalette.toggleCheatsheet === 'function') {
            window.SPPCommandPalette.toggleCheatsheet();
        }
    }

    initGlobalTooltips() {
        let popover = document.getElementById('spp-tooltip-popover');
        if (!popover) {
            popover = document.createElement('div');
            popover.id = 'spp-tooltip-popover';
            popover.className = 'spp-tooltip-popover';
            document.body.appendChild(popover);
        }

        const showTip = (target) => {
            const def = target.getAttribute('data-tooltip') || target.getAttribute('data-tip') || target.getAttribute('title');
            if (!def) return;
            const title = target.getAttribute('data-tooltip-title') || target.getAttribute('data-title') || 'What is this?';
            const example = target.getAttribute('data-tooltip-example') || target.getAttribute('data-example') || '';
            const rec = target.getAttribute('data-tooltip-rec') || target.getAttribute('data-rec') || '';

            popover.innerHTML = `
                <div class="spp-tooltip-title">💡 ${this.escapeHtml(title)}</div>
                <div class="spp-tooltip-def">${this.escapeHtml(def)}</div>
                ${example ? `<div class="spp-tooltip-example"><strong>Example:</strong> ${this.escapeHtml(example)}</div>` : ''}
                ${rec ? `<div class="spp-tooltip-rec">⭐ ${this.escapeHtml(rec)}</div>` : ''}
            `;

            const rect = target.getBoundingClientRect();
            let top = rect.bottom + 8;
            let left = rect.left - 20;

            if (left + 310 > window.innerWidth) {
                left = window.innerWidth - 320;
            }
            if (left < 16) left = 16;
            if (top + 160 > window.innerHeight) {
                top = rect.top - 140;
            }

            popover.style.top = `${Math.max(10, top)}px`;
            popover.style.left = `${Math.max(10, left)}px`;
            popover.classList.add('active');
        };

        const hideTip = () => {
            if (popover) popover.classList.remove('active');
        };

        document.addEventListener('mouseover', (e) => {
            const tipEl = e.target.closest('.spp-help-tip, [data-tooltip], [data-tip]');
            if (tipEl) {
                showTip(tipEl);
            }
        });

        document.addEventListener('mouseout', (e) => {
            const tipEl = e.target.closest('.spp-help-tip, [data-tooltip], [data-tip]');
            if (tipEl) {
                hideTip();
            }
        });

        document.addEventListener('click', (e) => {
            const tipEl = e.target.closest('.spp-help-tip');
            if (tipEl) {
                e.stopPropagation();
                if (popover.classList.contains('active')) {
                    hideTip();
                } else {
                    showTip(tipEl);
                }
            } else if (!e.target.closest('.spp-tooltip-popover')) {
                hideTip();
            }
        });
    }

    initAccordions() {
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('.spp-accordion-toggle');
            if (toggle) {
                const accordion = toggle.closest('.spp-advanced-accordion');
                if (accordion) {
                    accordion.classList.toggle('open');
                }
            }
            const guideToggle = e.target.closest('.spp-page-guide-header');
            if (guideToggle) {
                const guide = guideToggle.closest('.spp-page-guide');
                if (guide) {
                    guide.classList.toggle('collapsed');
                    const guideId = guide.getAttribute('data-guide-id') || 'default';
                    try {
                        localStorage.setItem(`spp_guide_${guideId}_collapsed`, guide.classList.contains('collapsed') ? '1' : '0');
                    } catch (_) {}
                }
            }
        });
    }

    getJargonGlossary() {
        return [
            {
                badge: 'DNS',
                term: 'DNS (Domain Name System)',
                name: 'Internet Phonebook',
                desc: 'Translates human-readable domain names (like example.com) into numerical server IP addresses (like 198.51.100.24) so browsers can find your site.',
                example: 'Without DNS, visitors would have to type http://198.51.100.24 into their browser.'
            },
            {
                badge: 'A',
                term: 'A Record (Address Record)',
                name: 'Point Domain to Server IP',
                desc: 'The most important DNS record. Connects your domain or subdomain directly to an IPv4 server address.',
                example: 'example.com ➔ 198.51.100.24'
            },
            {
                badge: 'CNAME',
                term: 'CNAME Record (Canonical Name)',
                name: 'Domain Alias / Nickname',
                desc: 'Makes one domain name point to another domain name instead of a direct numerical IP address.',
                example: 'www.example.com ➔ points to example.com'
            },
            {
                badge: 'MX',
                term: 'MX Record (Mail Exchange)',
                name: 'Email Delivery Server',
                desc: 'Tells mail services (Gmail, Outlook) which server handles incoming emails sent to @yourdomain.com.',
                example: 'mail.example.com with priority 10'
            },
            {
                badge: 'TXT',
                term: 'TXT Record (Text Record)',
                name: 'Anti-Spam & Domain Verification',
                desc: 'Carries arbitrary text for proving domain ownership (Google, Microsoft) and preventing email spoofing via SPF, DKIM, and DMARC.',
                example: 'v=spf1 ip4:198.51.100.24 ~all'
            },
            {
                badge: 'TTL',
                term: 'TTL (Time To Live)',
                name: 'Cache Speed / Refresh Time',
                desc: 'How many seconds worldwide DNS servers keep a cached copy of your record before asking your server for updates.',
                example: 'Recommended: 3600 (1 Hour). Use 300 (5 Mins) during migrations.'
            },
            {
                badge: 'SSL',
                term: 'SSL / TLS Certificate',
                name: 'Security Padlock (HTTPS)',
                desc: 'Encrypts communication between visitors and your website, stopping eavesdropping and removing browser "Not Secure" warnings.',
                example: 'SPP provisions free auto-renewing Let\'s Encrypt certificates with 1 click.'
            },
            {
                badge: 'GLUE',
                term: 'Glue Record',
                name: 'Nameserver Registry Registration',
                desc: 'Tells the top-level domain registry (like Verisign for .net) the IP address of your custom child nameservers (ns1/ns2).',
                example: 'ns1.example.com ➔ registered as 198.51.100.24 at registry.'
            },
            {
                badge: 'VHOST',
                term: 'Virtual Host (VHost)',
                name: 'Independent Website Mapping',
                desc: 'An Apache or Nginx configuration that routes traffic for a specific domain name to its dedicated folder on the server.',
                example: 'Hosting example.com and blog.example.com on the same single VPS.'
            },
            {
                badge: 'DOCROOT',
                term: 'DocumentRoot',
                name: 'Website Files Folder',
                desc: 'The physical directory on the server containing your index.html, WordPress, or PHP application files.',
                example: '/var/www/example.com or /home/username/public_html'
            },
            {
                badge: 'ACME',
                term: 'ACME Protocol (RFC 8555)',
                name: 'Automated SSL Engine',
                desc: 'The open standard used by Let\'s Encrypt and Certbot to verify domain ownership and issue free SSL certificates automatically.',
                example: 'HTTP-01 challenge creates a temporary file under /.well-known/acme-challenge/'
            },
            {
                badge: 'PROXY',
                term: 'Orange Cloud (Cloudflare Proxy)',
                name: 'Speed & DDoS Shield',
                desc: 'When enabled, incoming traffic passes through Cloudflare\'s worldwide network before reaching your server, hiding your real IP.',
                example: 'Orange Cloud = Speed & DDoS Shield ON. Grey Cloud = Direct DNS bypass.'
            },
            {
                badge: 'BIND9',
                term: 'BIND9 (named)',
                name: 'Authoritative DNS Server Daemon',
                desc: 'The Linux system daemon that runs on Port 53 and answers worldwide DNS lookups for your hosted zones.',
                example: 'systemctl status named (listens on 0.0.0.0:53 UDP/TCP)'
            },
            {
                badge: 'UFW',
                term: 'UFW (Uncomplicated Firewall)',
                name: 'Server Port Gatekeeper',
                desc: 'Controls which ports (like HTTP 80, HTTPS 443, DNS 53, SSH 9632) are open to the internet or blocked from attackers.',
                example: 'ufw allow 80/tcp permits web traffic to reach your websites.'
            },
            {
                badge: 'SAPI',
                term: 'SAPI (Server API)',
                name: 'PHP Execution Method',
                desc: 'Specifies how the web server communicates with PHP (e.g. Apache module, FastCGI / PHP-FPM, or CLI).',
                example: 'PHP-FPM runs PHP as independent isolated worker processes.'
            },
            {
                badge: 'WAL',
                term: 'SQLite WAL (Write-Ahead Logging)',
                name: 'High-Concurrency Database Mode',
                desc: 'Allows multiple web visitors to read from the database at the same time another visitor is saving data without locking.',
                example: 'PRAGMA journal_mode=WAL accelerates database speed up to 10x.'
            },
            {
                badge: 'SWAP',
                term: 'Swap Memory',
                name: 'Virtual RAM Buffer',
                desc: 'Disk space used as emergency RAM to keep your server running stably when physical RAM is full, preventing sudden crashes.',
                example: 'A 2GB swapfile protects low-memory VPS instances from Out-Of-Memory (OOM) kills.'
            }
        ];
    }

    openJargonBuster(initialQuery = '') {
        const modal = document.getElementById('modal-container');
        if (!modal) return;
        const glossary = this.getJargonGlossary();

        document.getElementById('modal-title').innerHTML = '❓ Jargon Buster &amp; Plain-English Hosting Glossary';
        document.getElementById('modal-body').innerHTML = `
            <div>
                <p style="margin: 0 0 1rem 0; font-size: 0.85rem; color: var(--text-dim);">
                    Search any web hosting, DNS, SSL, or server abbreviation to get a 1-sentence plain English explanation with real-world examples.
                </p>
                <input type="text" id="jargon-search" class="spp-glossary-search-input" placeholder="Search abbreviation or term (e.g. A, MX, TTL, SSL, BIND, Glue, VHost, SAPI)..." value="${this.escapeHtml(initialQuery)}" autofocus>
                <div id="jargon-results" class="spp-glossary-grid"></div>
            </div>
        `;

        const renderResults = (query = '') => {
            const q = query.toLowerCase().trim();
            const filtered = glossary.filter(item => {
                if (!q) return true;
                return item.term.toLowerCase().includes(q) ||
                       item.badge.toLowerCase().includes(q) ||
                       item.name.toLowerCase().includes(q) ||
                       item.desc.toLowerCase().includes(q);
            });

            const resultsEl = document.getElementById('jargon-results');
            if (!resultsEl) return;

            if (filtered.length === 0) {
                resultsEl.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; color: var(--text-dim); padding: 2rem;">No matching terms found for "${this.escapeHtml(query)}". Try searching for "DNS", "SSL", "A", or "TTL".</div>`;
                return;
            }

            resultsEl.innerHTML = filtered.map(item => `
                <div class="spp-glossary-card">
                    <div class="spp-glossary-term">
                        <span class="spp-glossary-badge">${this.escapeHtml(item.badge)}</span>
                        <span class="spp-glossary-name">${this.escapeHtml(item.name)}</span>
                    </div>
                    <div style="font-size: 0.75rem; color: #38bdf8; font-weight: 600; margin-bottom: 0.35rem;">
                        ${this.escapeHtml(item.term)}
                    </div>
                    <div class="spp-glossary-desc">
                        ${this.escapeHtml(item.desc)}
                    </div>
                    ${item.example ? `<div class="spp-glossary-example"><strong>💡 Example:</strong> ${this.escapeHtml(item.example)}</div>` : ''}
                </div>
            `).join('');
        };

        renderResults(initialQuery);

        modal.classList.add('active');
        const closeBtn = document.getElementById('modal-close');
        if (closeBtn) {
            closeBtn.textContent = 'Close';
            closeBtn.onclick = () => modal.classList.remove('active');
        }
        const saveBtn = document.getElementById('modal-save');
        if (saveBtn) {
            saveBtn.style.display = 'none';
        }

        setTimeout(() => {
            const input = document.getElementById('jargon-search');
            if (input) {
                input.focus();
                input.oninput = (e) => renderResults(e.target.value);
            }
        }, 50);
    }

    handleModalAction(name, modal, instruction, context = null) {
        console.log(`[SPPAdmin] handleModalAction: ${name}`, { instruction, context });
        
        // Use provided context or fall back to last stored API context
        const ctx = context || this._lastApiContext || {};
        
        if (name === 'close') {
            modal.close();
            if (this._activeModal === modal) this._activeModal = null;
            return;
        } 
        
        if (name === 'save') {
            if (ctx.modname && ctx.appname) {
                this.saveModuleSettings(ctx.modname, ctx.appname);
            } else {
                console.warn('Save action triggered without context:', ctx);
                const form = document.querySelector('.modal-body form');
                if (form) form.submit();
            }
            return;
        }

        if (name === 'admin.applySystemUpdate()') {
            this.applySystemUpdate();
            modal.close();
            return;
        }

        // Generic evaluation for other admin methods
        if (name.startsWith('admin.')) {
            const method = name.substring(6).replace('()', '');
            if (typeof this[method] === 'function') {
                this[method]();
                modal.close();
                return;
            }
        }

        console.warn(`Unhandled modal action: ${name}`);
    }

    async confirm(message) {
        return await SPPUX.Confirm(message);
    }

    saveCurrentModal() {
        // Compatibility helper for legacy save buttons
        const saveBtn = document.getElementById('modal-save');
        if (saveBtn && saveBtn.onclick) {
            saveBtn.onclick();
        }
    }

    renderSystem(data, bridge) {
        const container = document.getElementById('view-container');

        let bridgeHtml = '';
        if (bridge) {
            let runtimesHtml = '';
            for (const [key, r] of Object.entries(bridge.runtimes)) {
                const statusClass = r.path ? 'active' : 'inactive';
                const statusText = r.path ? 'Ready' : 'Not Found';
                const versionInfo = r.version && r.version !== 'N/A' ? `(${r.version})` : '';
                
                runtimesHtml += `
                    <tr>
                        <td><strong>${this.escapeHtml(r.name)}</strong></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="status-indicator ${statusClass}"></span>
                                <code>${r.path ? this.escapeHtml(this.truncatePath(r.path, 50)) : 'N/A'}</code>
                            </div>
                        </td>
                        <td>${this.escapeHtml(statusText)} ${this.escapeHtml(versionInfo)}</td>
                    </tr>`;
            }

            bridgeHtml = `
                <div class="details-section glass-panel mt-4">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3><span class="view-icon">🌉</span> Polyglot Resource Bridge</h3>
                        <button class="btn ghost-btn btn-sm" onclick="admin.refreshBridge()" id="refresh-bridge-btn">🔄 Refresh Bridge</button>
                    </div>
                    <div class="stat-summary mb-3" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
                        <div class="small-stat">
                            <label>Shared Directory</label>
                            <code class="path-label">${this.escapeHtml(this.truncatePath(bridge.shared_dir, 60))}</code>
                        </div>
                        <div class="small-stat">
                            <label>Config Status</label>
                            <span class="badge ${bridge.config_exists ? 'success' : 'danger'}">${bridge.config_exists ? 'Generated' : 'Missing'}</span>
                        </div>
                        <div class="small-stat">
                            <label>Last Sync</label>
                            <strong>${this.escapeHtml(bridge.last_sync || 'Never')}</strong>
                        </div>
                    </div>
                    <table class="data-table">
                        <tr><th>Engine</th><th>Binary Path</th><th>Status / Version</th></tr>
                        ${runtimesHtml}
                    </table>
                </div>
            `;
        }

        let html = `
            <div class="dashboard-grid">
                <!-- Status Cards -->
                <div class="info-card">
                    <div class="card-icon">⚡</div>
                    <div class="card-content">
                        <h3>Framework Status</h3>
                        <div class="status-badge active">Online</div>
                        <p>Version: <strong>${this.escapeHtml(data.spp_version)}</strong></p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="card-icon">📁</div>
                    <div class="card-content">
                        <h3>Resources</h3>
                        <div class="stat-row"><span>Apps:</span> <strong>${data.stats.apps}</strong></div>
                        <div class="stat-row"><span>Modules:</span> <strong>${data.stats.modules}</strong></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-icon">🛠️</div>
                    <div class="card-content">
                        <h3>Configuration</h3>
                        <div class="stat-row"><span>Entities:</span> <strong>${data.stats.entities}</strong></div>
                        <div class="stat-row"><span>Forms:</span> <strong>${data.stats.forms}</strong></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-icon">💾</div>
                    <div class="card-content">
                        <h3>Database</h3>
                        <div class="status-badge ${data.db_status === 'Connected' ? 'active' : (data.db_status === 'Disconnected' ? 'danger' : 'warning')}">${this.escapeHtml(data.db_status)}</div>
                        <p>Runtime: <strong>PHP ${this.escapeHtml(data.php_version)}</strong></p>
                    </div>
                </div>
            </div>

            <div class="details-section glass-panel">
                <h3><span class="icon">🔍</span> System Environment</h3>
                <table class="data-table">
                    <tr><th>Parameter</th><th>Value</th></tr>
                    <tr><td>Operating System</td><td>${this.escapeHtml(data.os)}</td></tr>
                    <tr><td>Server Software</td><td>${this.escapeHtml(data.server_software)}</td></tr>
                    <tr><td>Framework Path</td><td><code class="path-label">${this.escapeHtml(data.spp_base)}</code></td></tr>
                    <tr><td>Application Path</td><td><code class="path-label">${this.escapeHtml(data.app_root)}</code></td></tr>
                </table>
            </div>

            ${bridgeHtml}

            <div class="action-banner glass-panel" style="margin-top: 2rem;">
                <div class="banner-content">
                    <h4>SPP Developer Workbench</h4>
                    <p>Developer workbench is configured for application context: <strong>${this.escapeHtml(this.selectedApp)}</strong></p>
                </div>
                <div style="display:flex; gap:12px;">
                    <button class="btn accent-btn" onclick="location.hash = 'apps'" style="background: var(--accent-gradient); color: white; border: none;">📱 Manage Applications</button>
                    <button class="btn primary-btn" onclick="admin.runSystemUpdate()">🚀 Update System</button>
                </div>
            </div>
        `;
        container.innerHTML = html;
    }

    async refreshBridge() {
        const btn = document.getElementById('refresh-bridge-btn');
        const origText = btn.innerHTML;
        btn.innerHTML = '🔄 Syncing...';
        btn.disabled = true;

        try {
            const res = await this.api('setup_bridge');
            if (res.success) {
                this.notify('Polyglot Bridge environment refreshed.', 'success');
                this.loadView('dashboard');
            } else {
                this.notify(res.message || 'Bridge refresh failed.', 'error');
                btn.innerHTML = origText;
                btn.disabled = false;
            }
        } catch (e) {
            this.notify('Network error during bridge refresh.', 'error');
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    }
    // LEGACY IAM & GROUPS REMOVED - MIGRATED TO SPP-UX AccessView & GroupsView



    // =============================================
    // SYSTEM UPDATE LOGIC
    // =============================================

    async runSystemUpdate() {
        console.log('[SPPAdmin] runSystemUpdate triggered');
        this.openModal('🔍 Scanning System for Updates...', 
            SPPUX.html`<div style="padding: 2rem; text-align: center;"><div class="sppux-spinner" style="width: 40px; height: 40px; margin: 0 auto 1.5rem auto;"></div><div style="font-size: 1.1rem; opacity: 0.8;">Analyzing module manifests and entity schemas...</div></div>`);
        
        try {
            const res = await this.api('system_update_list');
            console.log('[SPPAdmin] system_update_list response:', res);
            // The modal is updated automatically via LiveAction instructions in res
        } catch (err) {
            console.error('[SPPAdmin] runSystemUpdate error:', err);
            this.updateModal('Error', SPPUX.html`<div class="alert error-alert">${err.message}</div>`);
        }
    }

    async applySystemUpdate() {
        console.log('[SPPAdmin] applySystemUpdate triggered');
        this.updateModal('🚀 Updating System...', 
            SPPUX.html`<div style="padding: 2rem; text-align: center;"><div class="sppux-spinner" style="width: 40px; height: 40px; margin: 0 auto 1.5rem auto;"></div><div style="font-size: 1.1rem; opacity: 0.8;">Executing migration routines and synchronizing schemas...</div><div style="font-size: 0.8rem; opacity: 0.4; margin-top: 1rem;">Please do not close this window.</div></div>`);
        
        try {
            const res = await this.api('system_update_run');
            console.log('[SPPAdmin] system_update_run response:', res);
            // The result modal is handled server-side via LiveAction
        } catch (err) {
            console.error('[SPPAdmin] applySystemUpdate error:', err);
            this.updateModal('Error', SPPUX.html`<div class="alert error-alert">${err.message}</div>`);
        }
    }
    // =============================================
    // MODULE MANAGEMENT LOGIC
    // =============================================

    async openModuleMaintenance(modname, publicName) {
        this.openModal(`🏗️ Maintenance: ${publicName}`, SPPUX.html`
            <div class="glass-panel" style="padding: 3rem; text-align: center; background: rgba(0,0,0,0.2);">
                <div class="sppux-spinner" style="width: 45px; height: 45px; margin: 0 auto 1.5rem auto;"></div>
                <div style="font-size: 1.1rem; opacity: 0.8; letter-spacing: 0.5px;">Scanning module for changes...</div>
            </div>
        `);
        
        try {
            const res = await this.apiPost('scan_module', { modname });
            // The modal is updated automatically via LiveAction instructions in res
        } catch (err) {
            this.updateModal('Error', SPPUX.html`<div class="alert error-alert">${err.message}</div>`);
        }
    }

    async installModule(modname) {
        this.updateModal('🚀 Installing Module...', 
            SPPUX.html`<div style="padding: 2rem; text-align: center;"><div class="sppux-spinner" style="width: 40px; height: 40px; margin: 0 auto 1.5rem auto;"></div><div style="font-size: 1.1rem; opacity: 0.8;">Installing module schema and dependencies...</div></div>`);
        
        try {
            const res = await this.apiPost('setup_module', { modname });
            if (res.success) {
                this.updateModal('Success', SPPUX.html`<div class="alert success">${res.message}</div>`);
            }
        } catch (err) {
            this.updateModal('Error', SPPUX.html`<div class="alert error-alert">${err.message}</div>`);
        }
    }

    async installAllActiveModules() {
        if (!await this.confirm(`Are you sure you want to install all active modules? This will create tables and execute seeders.`)) return;
        
        this.updateModal('📦 Bulk Installing Modules...', 
            SPPUX.html`<div style="padding: 2rem; text-align: center;"><div class="sppux-spinner" style="width: 40px; height: 40px; margin: 0 auto 1.5rem auto;"></div><div style="font-size: 1.1rem; opacity: 0.8;">Installing all active modules...</div></div>`);
        
        try {
            const res = await this.apiPost('install_all_active');
            if (res.success) {
                this.updateModal('Success', SPPUX.html`<div class="alert success">${res.message}</div>`);
            }
        } catch (err) {
            this.updateModal('Error', SPPUX.html`<div class="alert error-alert">${err.message}</div>`);
        }
    }

    async uninstallModule(modname) {
        if (!await this.confirm(`Are you sure you want to uninstall ${modname}? This will remove tracking, but retain data.`)) return;
        
        this.updateModal('🗑️ Uninstalling Module...', 
            SPPUX.html`<div style="padding: 2rem; text-align: center;"><div class="sppux-spinner" style="width: 40px; height: 40px; margin: 0 auto 1.5rem auto;"></div><div style="font-size: 1.1rem; opacity: 0.8;">Uninstalling...</div></div>`);
        
        try {
            const res = await this.apiPost('uninstall_module', { modname });
            if (res.success) {
                this.updateModal('Success', SPPUX.html`<div class="alert success">${res.message}</div>`);
            }
        } catch (err) {
            this.updateModal('Error', SPPUX.html`<div class="alert error-alert">${err.message}</div>`);
        }
    }

    async openModuleSettings(modname, publicName) {
        // This is now handled entirely via LiveAction from the server
        const res = await this.apiPost('open_module_settings', { 
            modname, 
            public_name: publicName,
            appname: this.selectedApp 
        });
        
        if (res.success) {
            this._lastApiContext = res.data;
            this.activeSetupTab = 'interactive';
            // The modal is automatically opened by SPPUX.applyInstructions called within apiPost
            
            // Allow small delay for DOM to render before initializing dependencies
            setTimeout(() => {
                if (window.SPPDependencies) SPPDependencies.init();
            }, 100);
        } else {
            this.notify(res.message || 'Failed to open settings', 'error');
        }
    }

    async testRuntime(id) {
        this.notify(`Testing ${id} bridge connectivity...`, 'info');
        try {
            const res = await this.api('get_bridge_info', { test: id });
            if (res.success) {
                this.notify(`${id} Bridge is operational!`, 'success');
                if (window.SPPUX && SPPUX.Celebrate) SPPUX.Celebrate.burst();
            } else {
                this.notify(`Bridge test failed: ${res.message}`, 'error');
            }
        } catch (err) {
            this.notify('Test failed due to network error.', 'error');
        }
    }

    async saveSyncConfig() {
        const yaml = document.getElementById('sync-config-raw')?.value;
        if (!yaml) return this.notify('Configuration cannot be empty.', 'error');
        await this.api('lifecycle_save_target', { yaml });
    }

    async syncDeploymentToken() {
        this.notify('Synchronizing deployment secrets...', 'info');
        try {
            const res = await this.api('lifecycle_receive', { 
                action: 'sync_token',
                token: '' // Will be prompted or retrieved if needed
            });
            // Result handled by LiveAction
        } catch (err) {
            this.notify('Sync failed.', 'error');
        }
    }

    switchSetupTab(tabId) {
        // Sync Interactive -> YAML before switching to YAML tab
        if (tabId === 'yaml' && this.activeSetupTab === 'interactive') {
            const config = this.getInteractiveConfig();
            const editor = document.getElementById('raw-config-editor');
            if (editor) {
                editor.value = "variables:\n" + Object.entries(config)
                    .map(([k, v]) => `    ${k}: ${JSON.stringify(v)}`)
                    .join("\n");
            }
        }

        this.activeSetupTab = tabId;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.setup-pane').forEach(p => p.style.display = 'none');
        
        const tabBtn = document.getElementById(`tab-${tabId}`);
        const pane = document.getElementById(`setup-pane-${tabId}`);
        if (tabBtn) tabBtn.classList.add('active');
        if (pane) pane.style.display = 'block';

        if (tabId === 'interactive' && window.SPPDependencies) {
            SPPDependencies.init();
        }
    }

    getInteractiveConfig() {
        const container = document.querySelector('.modal-body') || document;
        const inputs = container.querySelectorAll('input:not([type="hidden"]), select, textarea:not(.code-editor)');
        
        const config = {};
        inputs.forEach(inp => {
            const key = inp.id || inp.name || inp.getAttribute('data-key');
            if (!key || key.startsWith('setup-') || key === '__spp_form' || key === '_csrf_token') return;
            
            let val;
            if (inp.type === 'checkbox' || inp.type === 'radio') {
                val = inp.checked ? (inp.value || true) : false;
            } else {
                val = inp.value;
            }
            
            const cleanKey = key.replace(/^spp-/, '');
            config[cleanKey] = val;
        });
        return config;
    }

    async saveModuleSettings(modname, appname) {
        if (!modname) modname = document.getElementById('setup-modname')?.value;
        if (!appname) appname = document.getElementById('setup-appname')?.value;

        if (!modname) {
            this.notify('Module name not found.', 'error');
            return;
        }

        console.log(`[SPPAdmin] Saving settings for ${modname} in ${appname}...`);
        
        try {
            let res;
            if (this.activeSetupTab === 'interactive') {
                const config = this.getInteractiveConfig();
                console.log('[SPPAdmin] Saving Interactive Config:', config);
                
                res = await this.apiPost('save_module_config', { 
                    modname, 
                    appname, 
                    config: JSON.stringify(config) 
                });
            } else {
                const editor = document.getElementById('raw-config-editor');
                if (!editor) {
                    this.notify('Editor element not found.', 'error');
                    return;
                }

                const content = editor.value;
                res = await this.apiPost('save_module_config_raw', { 
                    modname, 
                    appname, 
                    content,
                    format: 'yml'
                });
            }

            if (res.success) {
                this.notify('Module configuration updated successfully.', 'success');
                this.closeModal();
            } else {
                this.updateModal('Save Failed', SPPUX.html`<div class="alert error">${this.escapeHtml(res.message)}</div>`);
            }
        } catch (err) {
            this.updateModal('Error', SPPUX.html`<div class="alert error">${err.message}</div>`);
        }
    }
    /**
     * Dynamic Asset Loader
     * Ensures required CSS/JS files are present in the page context.
     */
    async loadAssets(assets) {
        if (!assets) return;
        
        // Load CSS
        if (assets.css) {
            assets.css.forEach(href => {
                const path = href.startsWith('http') ? href : (href.startsWith('/') ? href : href);
                if (!document.querySelector(`link[href*="${path}"]`)) {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = href;
                    document.head.appendChild(link);
                }
            });
        }
        
        // Load JS
        if (assets.js) {
            const promises = assets.js.map(src => {
                const path = src.startsWith('http') ? src : (src.startsWith('/') ? src : src);
                if (!document.querySelector(`script[src*="${path}"]`)) {
                    return new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = src;
                        script.async = true;
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                return Promise.resolve();
            });
            await Promise.all(promises);
        }
    }

    /**
     * Copy the active SPP serving directory path to the user's clipboard.
     * Useful when multiple copies of SPP exist on the server.
     */
    copyServingDir() {
        const dir = window.SPP_SYSTEM_ENV?.serving_dir || '';
        if (!dir) {
            this.notify('Serving directory information unavailable.', 'warning');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(dir).then(() => {
                this.notify(`Serving directory copied: ${dir}`, 'success');
            }).catch(() => {
                this.promptCopyFallback(dir);
            });
        } else {
            this.promptCopyFallback(dir);
        }
    }

    promptCopyFallback(text) {
        window.prompt('SPP Serving Directory (Press Ctrl+C to copy):', text);
    }

}

// Add shake animation for login failures (injected dynamically)
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-12px); }
    40% { transform: translateX(10px); }
    60% { transform: translateX(-8px); }
    80% { transform: translateX(6px); }
}`;
document.head.appendChild(shakeStyle);

// Global instance initialization
const admin = new SPPAdmin();
window.admin = admin;

window.SPPAdmin = SPPAdmin;

// Phase 14: Sovereign 1-Click Multi-Portal Single Sign-On (SSO) Handover
window.switchPortalWithSso = async function(targetPortal) {
    try {
        if (window.admin && typeof window.admin.api === 'function') {
            const res = await window.admin.api('auth_sso_generate', { target: targetPortal });
            if (res && res.success && res.url) {
                window.location.href = res.url;
                return;
            }
        }
    } catch (e) {
        console.warn('SSO token handover fallback:', e);
    }
    window.location.href = targetPortal === 'sppdev' ? '../sppdev/#dashboard' : '../sppadmin/#dashboard';
};

