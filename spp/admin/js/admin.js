/**
 * SPP Admin SPA Frontend Controller
 * 
 * Manages view routing, API synchronization, authentication state, 
 * and UI interactions for the developer workbench.
 * Now refactored to a Standard Script with Global SPP-UX support.
 */

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
        
        // Initialize Global Root Store
        window.spp_root_store = new SPPStore({
            user: null,
            selectedApp: this.selectedApp,
            theme: this.theme
        });
        
        this.currentView = 'dashboard';
        this.viewIcons = {
            'dashboard': '👋',
            'system': '🖥️',
            'apps': '📱',
            'modules': '📦',
            'entities': '🏗️',
            'forms': '📝',
            'identity': '🛡️',
            'groups': '👥',
            'services': '🔌',
            'routing': '🛤️',
            'interdb': '🕸️',
            'xdb': '🗄️',
            'ai': '🧠',
            'parikshak': '🧪',
            'spplang': '💬',
            'trace': '🐛',
            'lifecycle': '🔄',
            'commands': '⚡',
            'reports': '📊'
        };
        this.viewTitles = {
            'dashboard': 'Welcome Dashboard',
            'system': 'System & Diagnostics',
            'apps': 'App Studio',
            'modules': 'Module Marketplace',
            'entities': 'Database & Entities',
            'forms': 'Modern Form Engine',
            'identity': 'Identity & Security',
            'groups': 'Group Dynamics',
            'services': 'Services (DI & AJAX)',
            'routing': 'Routing & Middleware',
            'xdb': 'XML Database',
            'interdb': 'InterDB Mesh',
            'parikshak': 'Parikshak Evaluator',
            'spplang': 'Translation Workbench',
            'mobile': 'Mobile Studio',
            'trace': 'Event Tracing',
            'lifecycle': 'Lifecycle & Deployment',
            'commands': 'CLI Workbench',
            'reports': 'Report Builder'
        };
        this.availableApps = [];
        this.selectedApp = localStorage.getItem('spp_admin_selected_app') || 'default';
        this.searchTimeout = null;
        this.theme = localStorage.getItem('spp_admin_theme') || 'night';
        
        // Define base configuration for components
        const p = window.location.pathname;
        const idx = p.indexOf('/sppadmin') !== -1 ? p.indexOf('/sppadmin') : p.indexOf('/spp/admin');
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
        this.bindEvents();
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

            // Modal elements
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

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeModal();
            });

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
                window.spp_root_store.set({ user: this.user });
                this.showWorkspace();
                
                // Update Sidebar Profile
                const profileRes = await this.api('get_profile');
                if (profileRes.success) {
                    this.updateUserDisplay(profileRes.data);
                }
            } else {
                this.showLogin();
            }
        } catch (e) {
            this.showLogin();
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
                    window.spp_root_store.set({ user: this.user });
                    location.hash = 'dashboard';
                    this.showWorkspace();
                    this.notify(`Welcome back, ${this.user.username}`, 'success');

                    // Update Sidebar Profile
                    const profileRes = await this.api('get_profile');
                    if (profileRes.success) {
                        this.updateUserDisplay(profileRes.data);
                    }
                }
            } else {
                this.handleApiErrors(res);
                this.notify(res.message || 'Invalid credentials.', 'error');
                if (this.mfaChallengeToken) {
                    mfaCodeEl.value = '';
                    mfaCodeEl.focus();
                }
            }
        } catch (err) {
            console.error("Login Error:", err);
            this.notify('Connection error. Is the server running?', 'error');
        }

        if (btn) {
            btn.textContent = origText;
            btn.disabled = false;
        }
    }

    async handleLogout() {
        await this.api('logout');
        this.user = null;
        window.spp_root_store.set({ user: null });
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
        this.notify(`Theme switched to ${theme} mode.`, 'success');
    }

    applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        
        // Sync SPPUX Global Variable Theme if library is loaded
        if (window.SPPUX && SPPUX.Theme) {
            SPPUX.Theme.set(theme);
        }

        // Special cosmetic tweaks for body backgrounds if needed
        if (theme === 'day') {
            document.body.style.backgroundImage = 'none';
        } else {
            document.body.style.backgroundImage = '';
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
        let hash = location.hash.replace('#', '') || 'dashboard';
        
        // Backward-compatible redirects for merged modules
        const redirects = {
            'events': 'trace',
            'middleware': 'routing',
            'access': 'identity',
            'groups': 'identity',
            'config': 'system',
            'copilot': 'ai',
            'sppai': 'ai',
            'ajax': 'services',
            'queue': 'system',
            'polyglot': 'system'
        };
        if (redirects[hash]) {
            hash = redirects[hash];
            location.hash = hash;
            return;
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
        
        document.getElementById('view-title').innerHTML =
            `<span class="view-icon">${icon}</span> ${title}`;

        const app = this.availableApps.find(a => a.name === hash);
        if (app && app.has_admin && this.selectedApp !== hash) {
            this.onAppContextChange(hash);
            return; // onAppContextChange will trigger loadView again
        }

        if (this.user) {
            this.loadView(hash);
        }
    }

    // =============================================
    // VIEW MANAGEMENT
    // =============================================
    
    /**
     * RemoteView Component
     * Loads and renders a PHP-based view from the server.
     */
    static RemoteView = class extends BaseComponent {
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

    async loadView(view) {
        this.activeView = view;
        const mainContainer = document.getElementById('view-container');
        document.getElementById('header-actions').innerHTML = '';
        const params = {}; // Reserved for future deep-linking parameters
        
        // Initialize View Cache
        this.viewWrappers = this.viewWrappers || {};
        this.viewInstances = this.viewInstances || {};

        if (!this.viewCacheInitialized) {
            mainContainer.innerHTML = ''; // Clear initial static loading state
            this.viewCacheInitialized = true;
        }

        // Hide all existing view wrappers
        for (let k in this.viewWrappers) {
            this.viewWrappers[k].style.display = 'none';
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
        } else {
            container.style.display = 'block';
            if (this.viewInstances[view]) {
                // View already loaded and cached, instant switch!
                this.updateViewTitle(view);
                this.viewInstances[view].update(); // trigger a fast re-render without re-fetching
                return;
            }
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
                const rootPath = jsDir.includes('spp/admin/js/') ? jsDir.split('spp/admin/js/')[0] : '../../';
                let appPath = `${rootPath}src/${this.selectedApp}/comp/${view}.js?v=${ts}`;
                
                // If the view name matches an app name, try to load that app's 'manage.js' or '<appname>.js'
                const appMeta = this.availableApps.find(a => a.name === view);
                if (appMeta) {
                    const srcPath = appMeta.src_path || `src/${view}`;
                    appPath = `${rootPath}${srcPath}/comp/${view}.js?v=${ts}`;
                }

                let module;
                // List of views that are strictly core framework views and should not be requested from apps
                const coreOnlyViews = ['apps', 'commands', 'dashboard', 'entities', 'forms', 'identity', 'interdb', 'lifecycle', 'modules', 'parikshak', 'routing', 'services', 'system', 'xdb', 'ai', 'trace', 'reports', 'api_keys', 'mobile', 'docs'];
                const isCoreView = coreOnlyViews.includes(view);
                
                // If a specific app is selected (not 'default' or '__sppadmin__'), and it's not a strict core view, try app-side first
                const useAppFirst = !isCoreView && this.selectedApp !== 'default' && this.selectedApp !== '__sppadmin__';

                if (useAppFirst) {
                    try {
                        module = await import(appPath);
                        console.log(`Loaded app-side component (Priority): ${view}`);
                    } catch (e) {
                        console.warn(`App-side component not found, trying core: ${corePath}`);
                        try {
                            module = await import(corePath);
                            console.log(`Loaded core component (Fallback): ${view}`);
                        } catch (e2) {
                             console.error(`Core component also failed: ${corePath}`, e2);
                             // Try manage.js fallback for apps
                             if (appMeta) {
                                try {
                                    const srcPath = appMeta.src_path || `src/${view}`;
                                    const managePath = `${rootPath}${srcPath}/comp/manage.js?v=${ts}`;
                                    module = await import(managePath);
                                    console.log(`Loaded app-side component: manage.js for ${view}`);
                                } catch (e3) {
                                    throw new Error(`Component "${view}" failed to load from app or core.\nApp: ${appPath} -> ${e.message}\nCore: ${corePath} -> ${e2.message}`);
                                }
                             } else {
                                throw new Error(`Component "${view}" failed to load from app or core.\nApp: ${appPath} -> ${e.message}\nCore: ${corePath} -> ${e2.message}`);
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
                        try {
                            module = await import(appPath);
                            console.log(`Loaded app-side component: ${view}`);
                        } catch (e2) {
                            throw e2;
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

                // 3. Render SPP-UX Component
                if (module.default) {
                    this.viewInstances[view] = new module.default(this, container, params);
                    await this.viewInstances[view].onInit();
                    this.viewInstances[view].update();
                }
            } catch (err) {
                console.error('View load error:', err);
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Failed to Load</h3>
                    <p>An error occurred while loading "${this.escapeHtml(view)}".</p>
                    <div class="error-detail" style="font-family: monospace; font-size: 0.8rem; background: var(--danger-bg); padding: 10px; border-radius: 4px; margin-top: 10px; color: var(--danger);">
                        ${this.escapeHtml(err.message || String(err))}
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

    onAppContextChange(appname) {
        this.selectedApp = appname;
        localStorage.setItem('spp_admin_selected_app', appname);
        this.notify(`App context switched to "${appname}".`, 'success');
        
        // Dynamically update the app-specific sidebar menu
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
        
        this.loadView(this.currentView);
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

    async api(action, params = {}, options = { lock: true }) {
        if (options.lock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.start();
        try {
            params.appname = this.selectedApp;
            // Unify with SPPUX.api which handles LiveAction instructions automatically
            const res = await SPPUX.api(action, params);
            if (res.errors_html) this.handleApiErrors(res);
            return res;
        } finally {
            if (options.lock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.stop();
        }
    }

    async apiPost(actionOrFormData, params = {}, options = { lock: true }) {
        if (options.lock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.start();
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
            if (options.lock && window.SPPUX && SPPUX.Busy) SPPUX.Busy.stop();
        }
    }

    // =============================================
    // UI STATE
    // =============================================

    showLogin() {
        document.getElementById('login-layer').classList.add('active');
        document.getElementById('workspace-layer').classList.remove('active');
        // Focus username field
        setTimeout(() => {
            const unField = document.getElementById('username');
            if (unField) unField.focus();
        }, 600);
    }

    async showWorkspace() {
        document.getElementById('login-layer').classList.remove('active');
        document.getElementById('workspace-layer').classList.add('active');

        // Update user info in sidebar
        if (this.user) {
            const username = this.user.username || this.user;
            const avatarEl = document.getElementById('user-avatar');
            const nameEl = document.getElementById('user-display-name');
            if (avatarEl) avatarEl.textContent = username.charAt(0).toUpperCase();
            if (nameEl) nameEl.textContent = username;
        }


        // Discovery & Resource Sync
        await this.loadApps();

        // Load admin RBAC permissions and gate sidebar
        await this.loadAdminPermissions();

        this.handleRouting();
    }

    /**
     * Fetch the current user's admin scopes and hide sidebar items
     * they don't have access to.
     */
    async loadAdminPermissions() {
        try {
            const res = await this.api('get_admin_permissions');
            if (res.success && res.data) {
                this.adminScopes = res.data.scopes || [];
                this.adminScopeMap = res.data.scope_map || {};
                this.applyAdminScopeGating();
            }
        } catch (e) {
            console.warn('[RBAC] Could not load admin permissions, showing all:', e);
            this.adminScopes = []; // Fail-open: show everything
        }
    }

    applyAdminScopeGating() {
        if (!this.adminScopes || this.adminScopes.length === 0) return;

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
        if (this._activeModal) {
            this._activeModal.props.title = title;
            this._activeModal.props.content = content;
            if (actions) this._activeModal.props.actions = actions;
            this._activeModal.update();
            if (this.viewInstance) this.viewInstance._registerGlobalHandlers();
        } else {
            this.openModal(title, content, actions || []);
        }
    }

    closeModal() {
        if (this._activeModal) {
            this._activeModal.close();
            this._activeModal = null;
        }
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
