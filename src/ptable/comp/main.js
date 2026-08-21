/**
 * ============================================================================
 * Main Dashboard Component — ptable
 * ============================================================================
 *
 * This is the root SPP-UX component for your application. It demonstrates
 * ALL major SPP-UX features in one working example.
 *
 * SPP-UX COMPONENT LIFECYCLE:
 *   1. constructor()  — Component instantiated (don't override directly)
 *   2. onInit()       — Set initial state, register stores (async)
 *   3. render()       — Return HTML via html`` tagged template
 *   4. onMount()      — DOM is ready, fetch data, start timers (async)
 *   5. afterUpdate()  — Called after every re-render (state change)
 *   6. onDestroy()    — Cleanup: unsubscribe stores, clear timers
 *
 * STATE MANAGEMENT:
 *   this.state         — Current state object (read-only outside setState)
 *   this.setState({})  — Merge new state → triggers re-render
 *   this.props         — Read-only props from parent or data-spp-props
 *
 * API CALLS:
 *   this.service('name', params)          — Call a registered PHP service (works everywhere)
 *   this.serv['service.name'](params)     — Proxy shorthand for service calls
 *   this.api('action', data)              — API call via SPPUX.api()
 *   this.apiPost(formData)                — POST API call with FormData
 *
 * UI HELPERS:
 *   this.notify('msg', 'success')       — Toast notification
 *   this.confirm('Are you sure?')       — Confirmation dialog (returns Promise)
 *   this.prompt('Enter value:')         — Input prompt (returns Promise)
 *   SPPUX.Modal.open('Title', content)  — Open modal
 *   SPPUX.Theme.set('midnight')         — Switch theme
 *   SPPUX.Notify.show('msg', 'info')    — Global notification
 *
 * HOW TO MODIFY:
 *   - Edit render() to change the UI layout
 *   - Edit onInit() to change initial state
 *   - Add new methods for business logic
 *   - Import sub-components by adding more data-spp-component divs in render()
 * ============================================================================
 */
export default class Main extends BaseComponent {

    /**
     * Called once before first render. Set up initial state here.
     * This is async — you can await API calls.
     */
    async onInit() {
        this.setState({
            appName: this.props.appName || 'ptable',
            activeTab: 'welcome',
            items: [],
            loading: false,
            theme: 'midnight',
            counter: 0,
            stats: [
                { label: 'Components', value: '5', icon: '🧩' },
                { label: 'Routes', value: '12', icon: '🗺️' },
                { label: 'Services', value: '3', icon: '⚡' }
            ]
        });
    }

    /**
     * Called after the first render when DOM is available.
     * Fetch initial data, set up event listeners, etc.
     */
    async onMount() {
        // Example: Load items from the API service
        // Uncomment when you have a working backend:
        // await this.loadItems();
    }

    /**
     * Called when this component is removed from the DOM.
     * Clean up timers, event listeners, store subscriptions here.
     */
    onDestroy() {
        // Example: clearInterval(this.timer);
    }

    // ── Business Logic Methods ──────────────────────────────────

    async loadItems() {
        this.setState({ loading: true });
        try {
            // Call a registered PHP service (defined in etc/services.yml)
            // this.service() works in ALL contexts (admin panel + standalone)
            // Alternative shorthand: this.serv['task.create']({...})
            const result = await this.service('task.create', {
                taskTitle: 'Sample Item',
                taskPriority: 'High'
            });
            this.notify('Service called successfully!', 'success');
            this.setState({ loading: false });
        } catch (e) {
            this.notify('Failed to load: ' + e.message, 'error');
            this.setState({ loading: false });
        }
    }

    async showModal() {
        // SPPUX.Modal — built-in modal dialog
        SPPUX.Modal.open('Framework Info', `
            <div style="padding: 1rem;">
                <h3>SPP-UX Component System</h3>
                <p>This modal was opened from a component method using:</p>
                <pre>SPPUX.Modal.open('Title', content, actions)</pre>
                <p>You can pass action buttons as the 3rd argument.</p>
            </div>
        `, [
            { label: 'Got it!', type: 'primary', fn: (m) => m.close() }
        ]);
    }

    async showConfirm() {
        // this.confirm() — returns a Promise<boolean>
        const confirmed = await this.confirm('Do you want to proceed?');
        this.notify(confirmed ? 'You confirmed!' : 'You cancelled.', confirmed ? 'success' : 'info');
    }

    async showPrompt() {
        // this.prompt() — returns a Promise<string|null>
        const name = await this.prompt('What is your name?', 'Developer');
        if (name) {
            this.notify(`Hello, ${name}! 👋`, 'success');
        }
    }

    switchTheme(name) {
        // SPPUX.Theme.set() — switch between 7 built-in themes
        // Available: midnight, emerald, royal, cyberpunk, ocean, saffron, day
        SPPUX.Theme.set(name);
        this.setState({ theme: name });
        this.notify(`Theme switched to ${name}`, 'success');
    }

    increment() {
        this.setState({ counter: this.state.counter + 1 });
    }

    switchTab(tab) {
        this.setState({ activeTab: tab });
    }

    // ── Render Method ───────────────────────────────────────────
    // Must return html`` tagged template literal.
    // Use ${expression} for dynamic values.
    // Use @click, @input, @change for event binding.

    render() {
        return html`
            <div style="min-height: 100vh; padding: 2rem;">
                <!-- Navigation Bar -->
                <nav style="display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem; padding:1rem 1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.8)); border-radius:16px; backdrop-filter:blur(20px);">
                    <div style="display:flex; align-items:center; gap:0.8rem;">
                        <span style="font-size:1.5rem;">🚀</span>
                        <span style="font-weight:700; font-size:1.2rem;">${this.state.appName}</span>
                        <span style="font-size:0.75rem; opacity:0.5; padding:2px 8px; background:rgba(99,102,241,0.2); border-radius:8px;">SPP-UX</span>
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        ${['welcome', 'features', 'themes', 'api'].map(tab => html`
                            <button @click="${() => this.switchTab(tab)}"
                                    style="padding:0.5rem 1rem; border:none; border-radius:8px; cursor:pointer; font-family:inherit; font-weight:${this.state.activeTab === tab ? '600' : '400'}; background:${this.state.activeTab === tab ? 'var(--sppux-primary, #6366f1)' : 'transparent'}; color:${this.state.activeTab === tab ? '#fff' : 'inherit'};">
                                ${tab.charAt(0).toUpperCase() + tab.slice(1)}
                            </button>
                        `)}
                    </div>
                </nav>

                <!-- Stats Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:2rem;">
                    ${this.state.stats.map(s => html`
                        <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:14px; backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.05);">
                            <span style="font-size:1.5rem;">${s.icon}</span>
                            <div style="font-size:0.8rem; opacity:0.6; margin-top:0.5rem;">${s.label}</div>
                            <div style="font-size:1.4rem; font-weight:700;">${s.value}</div>
                        </div>
                    `)}
                </div>

                <!-- Tab Content -->
                <div style="background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:20px; padding:2rem; backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.05);">
                    ${this.state.activeTab === 'welcome' ? this.renderWelcome() : ''}
                    ${this.state.activeTab === 'features' ? this.renderFeatures() : ''}
                    ${this.state.activeTab === 'themes' ? this.renderThemes() : ''}
                    ${this.state.activeTab === 'api' ? this.renderApi() : ''}
                </div>

                <!-- Footer -->
                <footer style="text-align:center; margin-top:2rem; opacity:0.4; font-size:0.85rem;">
                    &copy; ${new Date().getFullYear()} ${this.state.appName} • Powered by SPP-UX Framework
                </footer>
            </div>
        `;
    }

    renderWelcome() {
        return html`
            <div>
                <h2 style="margin-top:0;">👋 Welcome to ${this.state.appName}</h2>
                <p style="opacity:0.7; line-height:1.7;">
                    This is your SPP-UX application scaffold. It's a <b>live, interactive tutorial</b>
                    that demonstrates every feature of the SPP-UX component system.
                </p>
                <p style="opacity:0.7; line-height:1.7;">
                    <b>What you can do:</b>
                </p>
                <ul style="opacity:0.7; line-height:2;">
                    <li>Edit <code>comp/main.js</code> to modify this component</li>
                    <li>Create new components in <code>comp/</code> directory</li>
                    <li>Mount sub-components using <code>data-spp-component</code> divs in render()</li>
                    <li>Call PHP services via <code>this.service('name', params)</code></li>
                    <li>Use 7 built-in themes via <code>SPPUX.Theme.set()</code></li>
                </ul>

                <h3>🧮 Interactive Counter Demo</h3>
                <p style="opacity:0.5; font-size:0.9rem;">
                    Demonstrates <code>this.setState()</code> → automatic re-render
                </p>
                <div style="display:flex; align-items:center; gap:1rem; margin-top:1rem;">
                    <button @click="${() => this.increment()}"
                            style="padding:0.7rem 1.5rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600;">
                        Count: ${this.state.counter}
                    </button>
                    <button @click="${() => this.showModal()}"
                            style="padding:0.7rem 1.5rem; background:rgba(99,102,241,0.15); color:var(--sppux-primary, #6366f1); border:1px solid var(--sppux-primary, #6366f1); border-radius:10px; cursor:pointer; font-weight:600;">
                        Open Modal
                    </button>
                    <button @click="${() => this.showConfirm()}"
                            style="padding:0.7rem 1.5rem; background:rgba(16,185,129,0.15); color:#10b981; border:1px solid #10b981; border-radius:10px; cursor:pointer; font-weight:600;">
                        Confirm Dialog
                    </button>
                    <button @click="${() => this.showPrompt()}"
                            style="padding:0.7rem 1.5rem; background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid #f59e0b; border-radius:10px; cursor:pointer; font-weight:600;">
                        Prompt Input
                    </button>
                </div>
            </div>
        `;
    }

    renderFeatures() {
        const features = [
            { icon: '⚡', title: 'Reactive State', desc: 'this.setState({key: value}) triggers automatic re-renders.' },
            { icon: '🎨', title: 'Tagged Templates', desc: 'html`` literal for safe, efficient DOM rendering.' },
            { icon: '🔄', title: 'Lifecycle Hooks', desc: 'onInit → render → onMount → afterUpdate → onDestroy' },
            { icon: '📦', title: 'Component Composition', desc: 'Mount sub-components via data-spp-component divs.' },
            { icon: '🌐', title: 'Service Bridge', desc: 'this.serv[name]() calls PHP services from JavaScript.' },
            { icon: '💬', title: 'UI Helpers', desc: 'Modal, Toast, Confirm, Prompt, Drawer, Spotlight built-in.' },
            { icon: '🎭', title: '7 Themes', desc: 'midnight, emerald, royal, cyberpunk, ocean, saffron, day.' },
            { icon: '📊', title: 'SPPStore', desc: 'Shared state across components with subscribe/notify.' },
        ];
        return html`
            <div>
                <h2 style="margin-top:0;">🚀 SPP-UX Features</h2>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:1rem;">
                    ${features.map(f => html`
                        <div style="padding:1.2rem; background:rgba(255,255,255,0.03); border-radius:12px; border:1px solid rgba(255,255,255,0.06);">
                            <span style="font-size:1.5rem;">${f.icon}</span>
                            <h4 style="margin:0.5rem 0 0.3rem;">${f.title}</h4>
                            <p style="margin:0; font-size:0.85rem; opacity:0.6;">${f.desc}</p>
                        </div>
                    `)}
                </div>
            </div>
        `;
    }

    renderThemes() {
        const themes = ['midnight', 'emerald', 'royal', 'cyberpunk', 'ocean', 'saffron', 'day'];
        return html`
            <div>
                <h2 style="margin-top:0;">🎨 Theme Switcher</h2>
                <p style="opacity:0.6;">Click a theme to switch instantly. Themes use CSS variables — override them in your app CSS for custom branding.</p>
                <div style="display:flex; flex-wrap:wrap; gap:0.8rem; margin-top:1rem;">
                    ${themes.map(t => html`
                        <button @click="${() => this.switchTheme(t)}"
                                style="padding:0.8rem 1.5rem; border:2px solid ${this.state.theme === t ? 'var(--sppux-primary, #6366f1)' : 'rgba(255,255,255,0.1)'}; background:${this.state.theme === t ? 'var(--sppux-primary, #6366f1)' : 'rgba(255,255,255,0.05)'}; color:${this.state.theme === t ? '#fff' : 'inherit'}; border-radius:10px; cursor:pointer; font-weight:600; font-family:inherit; text-transform:capitalize;">
                            ${t}
                        </button>
                    `)}
                </div>
                <div style="margin-top:1.5rem; padding:1rem; background:rgba(255,255,255,0.03); border-radius:10px; font-family:monospace; font-size:0.85rem;">
                    <span style="opacity:0.5;">// Switch theme from JavaScript:</span><br>
                    SPPUX.Theme.set('${this.state.theme}');
                </div>
            </div>
        `;
    }

    renderApi() {
        return html`
            <div>
                <h2 style="margin-top:0;">🌐 Service & API Integration</h2>
                <p style="opacity:0.6; line-height:1.7;">
                    SPP-UX components can call PHP services and REST APIs. Services are defined in
                    <code>etc/services.yml</code> and called via the bridge.
                </p>

                <h3>Service Call Example</h3>
                <div style="padding:1rem; background:rgba(255,255,255,0.03); border-radius:10px; font-family:monospace; font-size:0.85rem; line-height:1.8; overflow-x:auto;">
                    <span style="opacity:0.5;">// Call a PHP service from JavaScript</span><br>
                    <span style="color:#c084fc;">const</span> result = <span style="color:#c084fc;">await</span> this.service(<span style="color:#a5f3fc;">'task.create'</span>, {<br>
                    &nbsp;&nbsp;taskTitle: <span style="color:#a5f3fc;">'My Task'</span>,<br>
                    &nbsp;&nbsp;taskPriority: <span style="color:#a5f3fc;">'High'</span><br>
                    });<br><br>
                    <span style="opacity:0.5;">// The PHP service is at: src/ptable/serv/task_create.php</span><br>
                    <span style="opacity:0.5;">// Registered in: src/ptable/etc/services.yml</span>
                </div>

                <div style="margin-top:1.5rem;">
                    <button @click="${() => this.loadItems()}"
                            style="padding:0.7rem 1.5rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600;">
                        ⚡ Try Service Call
                    </button>
                </div>
            </div>
        `;
    }
}