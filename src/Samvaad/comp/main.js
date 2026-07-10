/**
 * ============================================================================
 * Main Dashboard Component — Samvaad
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
            appName: this.props.appName || 'Samvaad',
            activeTab: 'welcome',
            items: [],
            loading: false,
            theme: window.SPPUX?.Theme?.current || 'midnight',
            counter: 0,
            stepperIndex: 1,
            // Showcase Enterprise Features
            sseLogs: [],
            isStreaming: false,
            workflowState: 'DRAFT',
            liveHtml: null,
            // Exhaustive Showcase States
            switchState: true,
            chipsList: ['Enterprise', 'High-Performance', 'SPP-UX'],
            accordionIndex: 0,
            comboboxValue: 'opt1',
            colorValue: '#6366f1',
            ratingValue: 4,
            pageIndex: 1,
            subTabId: 't1',
            richTextContent: '<b>Enterprise Grade</b> Rich Text content with <i>interactive toolbar</i> formatting.',
            chatMessages: [
                { role: 'assistant', content: 'Hello! I am your SPP-UX AI Assistant. How can I help you explore the enterprise framework today?' }
            ],
            isTyping: false,
            kanbanCardsTodo: [
                { id: 'card-1', content: window.SPPUX ? SPPUX.html`<b>Task 1:</b> Configure CQRS Event Store` : 'Task 1: Configure CQRS Event Store' },
                { id: 'card-2', content: window.SPPUX ? SPPUX.html`<b>Task 2:</b> Setup DAG Job Orchestrator` : 'Task 2: Setup DAG Job Orchestrator' }
            ],
            kanbanCardsDone: [
                { id: 'card-3', content: window.SPPUX ? SPPUX.html`<b>Task 3:</b> Boot SPP-UX Runtime` : 'Task 3: Boot SPP-UX Runtime' }
            ],
            kanbanTasks: [ {id:1, title:'Design DB Schema', status:'todo'}, {id:2, title:'Build UI', status:'in-progress'}, {id:3, title:'Write Tests', status:'done'} ],
            stats: [
                { label: 'Components', value: '35+', icon: '🧩' },
                { label: 'Routes', value: '12', icon: '🗺️' },
                { label: 'Services', value: '3', icon: '⚡' }
            ]
        });

        // Fake loading for dramatic effect
        setTimeout(async () => {
            this.setState({ loading: false, items: [1,2,3,4,5] });
            try {
                const liveRes = await this.service('enterprise.live', {});
                if(liveRes && liveRes.status === 'success') {
                    this.setState({ liveHtml: liveRes.html });
                    setTimeout(() => { if(window.SPP && window.SPP.Live) window.SPP.Live.scanComponents(); }, 100);
                }
            } catch(e) {}
        }, 800);
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

    showCelebrate() {
        SPPUX.Celebrate.burst();
        this.notify('🎉 Particle Celebration triggered!', 'success');
    }

    showDrawer() {
        SPPUX.Drawer.open('Enterprise Drawer', `
            <div style="padding: 1rem; color: var(--sppux-text, #fff); font-family: 'Inter', sans-serif;">
                <h4 style="margin-top:0; font-size: 1.2rem;">Sliding Drawer Panel</h4>
                <p style="opacity:0.7; font-size:0.95rem; line-height:1.6;">
                    The drawer slides smoothly from the right edge of the screen. Perfect for deep navigation menus, advanced search filters, or quick entity edits.
                </p>
                <div style="margin-top:1.5rem; padding:1rem; background:rgba(255,255,255,0.05); border-radius:10px; border: 1px solid rgba(255,255,255,0.1);">
                    <b>💡 Tip:</b> Press ESC or click the close icon at the top right to dismiss.
                </div>
            </div>
        `, 'right');
    }

    showContextMenu(e) {
        SPPUX.ContextMenu.open(e, [
            { label: 'Edit Enterprise Item', icon: '✏️', action: () => this.notify('Edit Item selected', 'info') },
            { label: 'Duplicate Node', icon: '📋', action: () => this.notify('Duplicate selected', 'success') },
            { label: 'Delete Entity', icon: '🗑️', action: () => this.notify('Delete selected', 'error') }
        ]);
    }

    showLightbox() {
        SPPUX.Lightbox.open('https://picsum.photos/1200/800', 'Enterprise Sample Image');
    }

    showSpotlight() {
        const items = [
            { title: 'Dashboard', desc: 'Navigate to main dashboard overview', icon: '📊' },
            { title: 'User Management', desc: 'Manage access and organization roles', icon: '👥' },
            { title: 'System Settings', desc: 'Configure environment variables and API keys', icon: '⚙️' },
            { title: 'Billing & Invoicing', desc: 'View enterprise subscription tier details', icon: '💳' },
            { title: 'Security Audit Logs', desc: 'Review compliance and access logs', icon: '🛡️' }
        ];
        SPPUX.Spotlight.open(items, (selected) => {
            this.notify(`Selected action: ${selected.title}`, 'success');
        });
    }

    showSubEditor() {
        SPPUX.openSubEditor('Enterprise Settings', `
            <div style="color: var(--sppux-text, #fff); font-family: 'Inter', sans-serif;">
                <h4 style="margin-top: 0; font-size: 1.2rem;">System Parameter Configurations</h4>
                <p style="opacity: 0.7; font-size: 0.95rem; line-height: 1.6;">
                    Modify your dynamic runtime parameters here. Changes are instantly synchronized across the active cluster.
                </p>
                <div style="margin-top: 1.5rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; opacity: 0.8;">Cluster Instance Name</label>
                    <input type="text" value="Samvaad Production Cluster" style="width: 100%; padding: 0.8rem 1rem; margin-top: 0.5rem; border-radius: 10px; border: 1px solid var(--sppux-primary, #6366f1); background: rgba(0,0,0,0.2); color: #fff; font-family: inherit; font-size: 0.95rem;">
                </div>
                <div style="margin-top: 1.5rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; opacity: 0.8;">Timeout Threshold (ms)</label>
                    <input type="number" value="5000" style="width: 100%; padding: 0.8rem 1rem; margin-top: 0.5rem; border-radius: 10px; border: 1px solid var(--sppux-primary, #6366f1); background: rgba(0,0,0,0.2); color: #fff; font-family: inherit; font-size: 0.95rem;">
                </div>
            </div>
        `);
    }

    showPopover(e) {
        const triggerEl = e.target.closest('button') || e.target;
        SPPUX.Popover.open(triggerEl, 'Enterprise Popover', 'This is a high-performance interactive popover component with automatic outside-click and escape-key dismissal.');
    }

    showMasterGrid() {
        SPPUX.openSubEditor('High-Performance Master Grid Virtualizer', `
            <div id="master-grid-showcase" style="height: 450px; width: 100%; font-family: 'Inter', sans-serif;"></div>
        `);
        setTimeout(() => {
            const container = document.getElementById('master-grid-showcase');
            if (!container) return;
            const grid = new SPPUX.MasterGrid(null, container, {
                columns: [
                    { key: 'id', label: 'ID', width: '80px' },
                    { key: 'component', label: 'Legendary Component', width: '250px' },
                    { key: 'status', label: 'Reactivity State', width: '150px' },
                    { key: 'perf', label: 'Latency', width: '120px' }
                ],
                data: [
                    { id: 'UX-01', component: 'MasterGrid Virtualizer', status: 'Active (60fps)', perf: '0.12ms' },
                    { id: 'UX-02', component: 'Proxy Store Engine', status: 'Synchronized', perf: '0.08ms' },
                    { id: 'UX-03', component: 'Spotlight Palette', status: 'Standby', perf: '0.24ms' },
                    { id: 'UX-04', component: 'Glassmorphic Dialogs', status: 'Active', perf: '0.18ms' },
                    { id: 'UX-05', component: 'Celebrate Particle Engine', status: 'Loaded', perf: '0.35ms' },
                    { id: 'UX-06', component: 'Interactive Stepper', status: 'Subscribed', perf: '0.15ms' },
                    { id: 'UX-07', component: 'Floating Popover', status: 'Ready', perf: '0.22ms' },
                    { id: 'UX-08', component: 'PHP Bridge Syncer', status: 'Polling', perf: '1.45ms' }
                ],
                rowHeight: 45
            });
            if (typeof grid.onInit === 'function') grid.onInit();
            grid.update();
            grid.onMount();
        }, 100);
    }

    showEditorDemo() {
        SPPUX.openSubEditor('Rich Text Editor (Quill)', `
            <div id="demo-editor-container" style="height:100%; width:100%; padding-bottom:50px; color:#000; background:#fff;"></div>
        `, {}, () => {});
        setTimeout(() => {
            const container = document.getElementById('demo-editor-container');
            if (container && window.SPPUX && SPPUX.Editor) {
                const editor = new SPPUX.Editor({ label: 'Document Content', value: '<h2>Hello SPPEXT</h2><p>This is a native quill integration.</p>', height: '400px' }, container);
                editor.onMount();
            } else { SPPUX.notify('SPPEXT Editor component not found', 'error'); }
        }, 100);
    }

    showCodeDemo() {
        SPPUX.openSubEditor('Code Editor (Monaco)', `
            <div id="demo-code-container" style="height:100%; width:100%; overflow:hidden;"></div>
        `, {}, () => {});
        setTimeout(() => {
            const container = document.getElementById('demo-code-container');
            if (container && window.SPPUX && SPPUX.Code) {
                const code = new SPPUX.Code({ label: 'JavaScript Source', value: 'function helloWorld() {\n  console.log("Welcome to SPPEXT Code Engine!");\n}', language: 'javascript', height: '500px' }, container);
                code.onMount();
            } else { SPPUX.notify('SPPEXT Code component not found', 'error'); }
        }, 100);
    }

    showMapDemo() {
        SPPUX.openSubEditor('Geospatial Map (Leaflet)', `
            <div id="demo-map-container" style="height:100%; width:100%;"></div>
        `, {}, () => {});
        setTimeout(() => {
            const container = document.getElementById('demo-map-container');
            if (container && window.SPPUX && SPPUX.Map) {
                const map = new SPPUX.Map({ label: 'Enterprise Locations', center: [37.7749, -122.4194], zoom: 10, markers: [[37.7749, -122.4194, 'San Francisco HQ']], height: '500px' }, container);
                map.onMount();
            } else { SPPUX.notify('SPPEXT Map component not found', 'error'); }
        }, 100);
    }

    showCalendarDemo() {
        SPPUX.openSubEditor('Advanced Calendar (Flatpickr)', `
            <div id="demo-calendar-container" style="height:100%; width:100%; padding:2rem;">
                <p style="margin-bottom:1rem;color:#94a3b8;">Click the input below to launch the Flatpickr calendar interface.</p>
            </div>
        `, {}, () => {});
        setTimeout(() => {
            const container = document.getElementById('demo-calendar-container');
            if (container && window.SPPUX && SPPUX.Calendar) {
                const cal = new SPPUX.Calendar({ label: 'Schedule Date', placeholder: 'Select appointment date...', enableTime: true }, container);
                cal.onMount();
            } else { SPPUX.notify('SPPEXT Calendar component not found', 'error'); }
        }, 100);
    }

    showSortableDemo() {
        SPPUX.openSubEditor('Sortable Lists (SortableJS)', `
            <div id="demo-sortable-container" style="height:100%; width:100%; padding:2rem;"></div>
        `, {}, () => {});
        setTimeout(() => {
            const container = document.getElementById('demo-sortable-container');
            if (container && window.SPPUX && SPPUX.Sortable) {
                const sortable = new SPPUX.Sortable({ 
                    label: 'Drag & Drop Reordering', 
                    items: [
                        { id: 1, text: 'Item 1: Complete Documentation' },
                        { id: 2, text: 'Item 2: Fix CI/CD Pipeline' },
                        { id: 3, text: 'Item 3: Review Pull Requests' },
                        { id: 4, text: 'Item 4: Deploy to Staging' }
                    ] 
                }, container);
                sortable.onMount();
            } else { SPPUX.notify('SPPEXT Sortable component not found', 'error'); }
        }, 100);
    }

    openEcoDemo(compName) {
        SPPUX.openSubEditor(compName + ' Component Viewer', `
            <div id="eco-demo-content" style="padding:2rem;">
                <h2 style="color:var(--sppux-primary, #6366f1); margin-top:0;">${compName}</h2>
                <p style="color:#94a3b8; font-size:1.1rem; margin-bottom:2rem;">
                    This is an isolated sandbox environment for testing the <b>${compName}</b> wrapper.
                </p>
                <div id="eco-${compName}-mount" style="min-height:200px; border:1px dashed rgba(255,255,255,0.1); border-radius:12px; padding:1.5rem; display:flex; align-items:center; justify-content:center; color:#64748b; font-style:italic; background:rgba(0,0,0,0.2);">
                    Component Mount Point: [${compName}]
                </div>
            </div>
        `, {}, () => {});

        setTimeout(() => {
            const mount = document.getElementById('eco-' + compName + '-mount');
            if (!mount) return;
            try {
                if (compName === 'Carousel') {
                    mount.innerHTML = '<div id="test-carousel" style="display:flex; gap:20px; overflow-x:auto;"><div style="padding:4rem;background:#f43f5e;border-radius:12px;min-width:200px;">Slide 1</div><div style="padding:4rem;background:#3b82f6;border-radius:12px;min-width:200px;">Slide 2</div><div style="padding:4rem;background:#10b981;border-radius:12px;min-width:200px;">Slide 3</div></div>';
                } else if (compName === 'DatePicker') {
                    mount.innerHTML = '<input type="text" id="test-date" placeholder="Select Date" style="padding:10px;border-radius:6px;border:none;width:100%;max-width:300px;font-size:1.1rem;" />';
                } else if (compName === 'Select') {
                    mount.innerHTML = '<select id="test-select" style="padding:10px;border-radius:6px;font-size:1.1rem;min-width:200px;"><option>Option A</option><option>Option B</option></select>';
                } else if (compName === 'Markdown') {
                    mount.innerHTML = '<div id="test-md" style="background:#fff;color:#000;padding:1rem;border-radius:8px;width:100%;"></div>';
                    if (window.SPPEX && SPPEX.Markdown) new SPPEX.Markdown('#test-md', '# SPPEX Markdown\\n**Bold text** and *italic*.');
                    return;
                } else if (compName === 'InfiniteScroll') {
                    mount.innerHTML = '<div id="test-scroll" style="height:150px; overflow-y:auto; border:1px solid #334155; width:100%;"><div style="height:300px; padding:1rem;">Scroll down...</div></div>';
                } else if (compName === 'Floating') {
                    mount.innerHTML = '<button id="test-float" style="padding:10px 20px; border-radius:8px; border:none; background:#6366f1; color:#fff;">Hover Me</button><div id="test-tooltip" style="display:none; padding:8px 12px; background:#1e293b; color:#fff; border-radius:6px; font-size:0.9rem;">Floating Tooltip</div>';
                } else if (compName === 'Masonry') {
                    mount.innerHTML = '<div id="test-masonry" style="width:100%; display:grid; grid-template-columns:1fr 1fr; gap:10px;"><div style="height:100px; background:#f43f5e;"></div><div style="height:150px; background:#3b82f6;"></div><div style="height:120px; background:#10b981;"></div></div>';
                } else if (compName === 'RangeSlider') {
                    mount.innerHTML = '<input type="range" id="test-range" min="0" max="100" style="width:100%;" />';
                } else if (compName === 'Highlight') {
                    mount.innerHTML = '<pre style="background:#1e293b;padding:1rem;border-radius:8px;width:100%;"><code id="test-highlight" class="language-js">const x = 42;\nconsole.log(x);</code></pre>';
                } else if (compName === 'AvatarGroup') {
                    mount.innerHTML = '<div id="test-avatars" style="display:flex; gap: -10px;"><div style="width:40px;height:40px;border-radius:50%;background:#f43f5e;border:2px solid #0f172a;"></div><div style="width:40px;height:40px;border-radius:50%;background:#3b82f6;border:2px solid #0f172a;"></div></div>';
                } else if (compName === 'Badge') {
                    mount.innerHTML = '<div id="test-badge" style="position:relative; display:inline-block; padding:10px; background:#334155; border-radius:8px;">Notifications <span style="position:absolute;top:-5px;right:-5px;background:#f43f5e;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:10px;">9+</span></div>';
                } else if (compName === 'Breadcrumbs') {
                    mount.innerHTML = '<div id="test-breadcrumbs" style="color:#6366f1;">Home > Settings > Profile</div>';
                } else if (compName === 'Timeline') {
                    mount.innerHTML = '<div id="test-timeline" style="border-left:2px solid #334155; padding-left:20px; margin-left:20px;"><div style="margin-bottom:20px;"><b>Step 1</b><p>Initialization</p></div><div><b>Step 2</b><p>Processing</p></div></div>';
                } else if (compName === 'CopyToClipboard') {
                    mount.innerHTML = '<button id="test-copy" style="padding:10px 20px; border-radius:8px; border:none; background:#10b981; color:#fff;">Copy to Clipboard</button>';
                } else if (compName === 'Resizable') {
                    mount.innerHTML = '<div id="test-resizable" style="width:200px; height:200px; background:#1e293b; resize:both; overflow:hidden; border:2px dashed #475569;">Resize me</div>';
                } else if (compName === 'ProgressBar') {
                    mount.innerHTML = '<div style="width:100%;background:#334155;border-radius:8px;overflow:hidden;"><div id="test-progress" style="width:75%;height:10px;background:#6366f1;"></div></div>';
                } else if (compName === 'DnD') {
                    mount.innerHTML = '<div id="test-dnd" style="padding:20px; background:#1e293b; border:2px dashed #475569; text-align:center; width:100%; cursor:grab;">Drag area</div>';
                } else {
                    mount.innerHTML = `<div style="text-align:center;">
                        <span style="font-size:2rem;">⚙️</span><br>
                        <b>${compName}</b> is a headless wrapper or utility that injects globally.
                    </div>`;
                }
                
                if (window.SPPEX && SPPEX[compName] && typeof SPPEX[compName] === 'function') {
                    try { new SPPEX[compName]('#test-' + compName.toLowerCase()); } catch(ex){}
                }
            } catch(e) { console.log(e); }
        }, 150);
    }

    advanceStepper() {
        this.setState({ stepperIndex: (this.state.stepperIndex + 1) % 4 });
    }

    handleChatSend(text) {
        this.setState({
            chatMessages: [...this.state.chatMessages, { role: 'user', content: text }],
            isTyping: true
        });
        setTimeout(() => {
            this.setState({
                chatMessages: [...this.state.chatMessages, { role: 'assistant', content: `Acknowledged: "${text}". The SPP-UX AI runtime successfully processed your request.` }],
                isTyping: false
            });
        }, 1200);
    }

    handleKanbanDrag(cardId, colId) {
        let allCards = [...this.state.kanbanCardsTodo, ...this.state.kanbanCardsDone];
        let card = allCards.find(c => c.id === cardId);
        if (!card) return;
        if (colId === 'todo') {
            this.setState({
                kanbanCardsTodo: [...this.state.kanbanCardsTodo.filter(c => c.id !== cardId), card],
                kanbanCardsDone: this.state.kanbanCardsDone.filter(c => c.id !== cardId)
            });
        } else {
            this.setState({
                kanbanCardsDone: [...this.state.kanbanCardsDone.filter(c => c.id !== cardId), card],
                kanbanCardsTodo: this.state.kanbanCardsTodo.filter(c => c.id !== cardId)
            });
        }
        this.notify(`Moved card to ${colId.toUpperCase()}`, 'success');
    }

    // ── Render Method ───────────────────────────────────────────
    // Must return html` tagged template literal.
    // Use ${expression} for dynamic values.
    // Use @click, @input, @change for event binding.

    
    // ── Enterprise Showcase ─────────────────────────────────────
    renderEnterprise() {
        return html`
            <div>
                <h2 style="margin-top:0; font-size:2rem; border-bottom:1px solid var(--sppux-glass-border); padding-bottom:1rem;">
                    🏢 Enterprise Capabilities
                </h2>
                <p style="opacity:0.8; font-size:1.1rem; line-height:1.7; margin-bottom:3rem;">
                    The SPP-UX framework is built for complex, high-scale enterprise applications. Here we demonstrate SPP's advanced backend integrations combined with sophisticated frontend state management.
                </p>

                <!-- 1. KANBAN BOARD -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>📋</span> Interactive Kanban Board
                    </h3>
                    <p style="opacity:0.8; margin-bottom:1.5rem;">A complex component demonstrating array state mutation, optimistic UI rendering, and Drag & Drop integration.</p>
                    
                    <div style="display:flex; gap:1.5rem; overflow-x:auto; padding-bottom:1rem;">
                        ${['todo', 'in-progress', 'done'].map(col => html`
                            <div style="flex:0 0 300px; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:12px; padding:1rem; border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));"
                                 @dragover="${e => e.preventDefault()}"
                                 @drop="${e => {
                                     e.preventDefault();
                                     const taskId = parseInt(e.dataTransfer.getData('text/plain'));
                                     const tasks = [...this.state.kanbanTasks];
                                     const task = tasks.find(t => t.id === taskId);
                                     if(task) { task.status = col; this.setState({ kanbanTasks: tasks }); this.notify('Task moved to ' + col, 'success'); }
                                 }}">
                                <h4 style="margin:0 0 1rem 0; text-transform:uppercase; font-size:0.85rem; opacity:0.7;">${col.replace('-', ' ')}</h4>
                                
                                ${this.state.kanbanTasks.filter(t => t.status === col).map(task => html`
                                    <div draggable="true"
                                         @dragstart="${e => { e.dataTransfer.setData('text/plain', task.id); e.target.style.opacity = '0.5'; }}"
                                         @dragend="${e => e.target.style.opacity = '1'}"
                                         style="background:var(--sppux-card-bg); padding:1rem; border-radius:8px; margin-bottom:0.8rem; cursor:grab; border:1px solid var(--sppux-glass-border);">
                                        <strong>${task.title}</strong>
                                        <div style="font-size:0.8rem; opacity:0.6; margin-top:0.5rem;">ID: ${task.id}</div>
                                    </div>
                                `)}
                            </div>
                        `)}
                    </div>
                </div>

                <!-- 2. AI TOOL CALLING -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🤖</span> AI Agent Tool Calling
                    </h3>
                    <p style="opacity:0.8; margin-bottom:1.5rem;">SPP integrates natively with LLMs via <code>SPPAI::callTool</code>. Test the backend AI service stub.</p>
                    
                    <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:12px; border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <div style="display:flex; gap:1rem;">
                            <input type="text" id="ai-prompt" placeholder="E.g., Generate a summary of our database..." style="flex:1; padding:0.8rem 1.2rem; border-radius:8px; border:1px solid var(--sppux-glass-border); background:var(--sppux-input-bg); color:var(--sppux-text); font-family:inherit;">
                            <button @click="${async () => {
                                const val = this.element.querySelector('#ai-prompt').value;
                                if(!val) return;
                                this.notify('Calling AI Service...', 'info');
                                try {
                                    const res = await this.service('enterprise.ai', { prompt: val });
                                    this.openModal('AI Response', res.message);
                                } catch(e) { this.notify('AI Call Failed', 'error'); }
                            }}" class="sppux-btn sppux-btn-primary">Execute AI</button>
                        </div>
                    </div>
                </div>

                <!-- 3. CQRS EVENT STORE -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>📚</span> CQRS Event Store
                    </h3>
                    <p style="opacity:0.8; margin-bottom:1.5rem;">Demonstrates event sourcing and point-in-time snapshots.</p>
                    
                    <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:12px; border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <button @click="${async () => {
                            const res = await this.service('enterprise.cqrs', { action: 'get_events' });
                            let htmlStr = '<ul style="text-align:left; line-height:1.6;">';
                            res.events.forEach(e => htmlStr += '<li><span style="opacity:0.6;font-size:0.8em">' + e.timestamp + '</span>: <strong>' + e.event + '</strong></li>');
                            htmlStr += '</ul>';
                            this.openModal('CQRS Event Log', htmlStr);
                        }}" class="sppux-btn sppux-btn-secondary">Load Event History</button>
                    </div>
                </div>

                <!-- 4. DAG JOB ORCHESTRATOR -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🕸️</span> DAG Job Orchestration
                    </h3>
                    <p style="opacity:0.8; margin-bottom:1.5rem;">Visualizing token-bucket throttled parallel jobs.</p>
                    
                    <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:12px; border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <button @click="${async () => {
                            const res = await this.service('enterprise.dag', { job: 'rebuild_index' });
                            this.notify(res.message, 'success');
                        }}" class="sppux-btn sppux-btn-secondary">Queue DAG Job</button>
                    </div>
                </div>

                <!-- 5. WORKFLOW ENGINE -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🔀</span> Native Workflow Manager
                    </h3>
                    <p style="opacity:0.8; margin-bottom:1.5rem;">Saga-pattern workflows with parallel state transitions.</p>
                    
                    <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:12px; border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                            <!-- Graph representation -->
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <div style="padding:0.5rem 1rem; border-radius:8px; background:${this.state.workflowState === 'DRAFT' ? 'var(--sppux-primary, #6366f1)' : 'var(--sppux-surface, rgba(0,0,0,0.05))'}; color:${this.state.workflowState === 'DRAFT' ? 'white' : 'inherit'}; font-weight:bold; transition:all 0.3s;">DRAFT</div>
                                <span style="color:var(--sppux-muted);">➔</span>
                                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                    <div style="padding:0.5rem 1rem; border-radius:8px; background:${this.state.workflowState === 'PUBLISHED' ? 'var(--sppux-success, #10b981)' : 'var(--sppux-surface, rgba(0,0,0,0.05))'}; color:${this.state.workflowState === 'PUBLISHED' ? 'white' : 'inherit'}; font-weight:bold; transition:all 0.3s;">PUBLISHED</div>
                                    <div style="padding:0.5rem 1rem; border-radius:8px; background:${this.state.workflowState === 'REJECTED' ? 'var(--sppux-danger, #ef4444)' : 'var(--sppux-surface, rgba(0,0,0,0.05))'}; color:${this.state.workflowState === 'REJECTED' ? 'white' : 'inherit'}; font-weight:bold; transition:all 0.3s;">REJECTED</div>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; gap:1rem;">
                            <button @click="${async () => {
                                const res = await this.service('enterprise.workflow', { transition: 'approve' });
                                this.setState({ workflowState: res.state });
                                this.notify('Transition: ' + res.state, 'success');
                            }}" class="sppux-btn" style="background:var(--sppux-success); color:white; border:none;">Approve</button>
                            
                            <button @click="${async () => {
                                const res = await this.service('enterprise.workflow', { transition: 'reject' });
                                this.setState({ workflowState: res.state });
                                this.notify('Transition: ' + res.state, 'error');
                            }}" class="sppux-btn" style="background:var(--sppux-danger); color:white; border:none;">Reject</button>
                            
                            <button @click="${() => this.setState({ workflowState: 'DRAFT' })}" class="sppux-btn sppux-btn-secondary">Reset</button>
                        </div>
                    </div>
                </div>

                <!-- 6. REAL-TIME CDC STREAM -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>📡</span> Real-Time CDC Stream (SSE)
                    </h3>
                    <p style="opacity:0.8; margin-bottom:1.5rem;">Server-Sent Events streaming simulated database changes in real-time.</p>
                    
                    <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:12px; border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                            <button @click="${() => {
                                if (this.state.isStreaming) return;
                                this.setState({ isStreaming: true, sseLogs: [] });
                                
                                const url = `${window.location.pathname}?__spa=1&__spa_stream=enterprise.stream&t=${Date.now()}`;
                                this.sseSource = new EventSource(url);
                                
                                const handleEvent = (evtType, e) => {
                                    const data = JSON.parse(e.data);
                                    const logEntry = `[${new Date().toLocaleTimeString()}] [${evtType.toUpperCase()}] ${JSON.stringify(data)}`;
                                    this.setState({ sseLogs: [logEntry, ...this.state.sseLogs].slice(0, 15) });
                                    if (evtType === 'complete') {
                                        this.setState({ isStreaming: false });
                                        this.sseSource.close();
                                    }
                                };
                                
                                this.sseSource.addEventListener('start', e => handleEvent('start', e));
                                this.sseSource.addEventListener('progress', e => handleEvent('progress', e));
                                this.sseSource.addEventListener('complete', e => handleEvent('complete', e));
                                
                                this.sseSource.onerror = () => {
                                    this.setState({ isStreaming: false });
                                    this.sseSource.close();
                                    this.notify('Stream Error', 'error');
                                };
                            }}" class="sppux-btn ${this.state.isStreaming ? 'sppux-btn-secondary' : 'sppux-btn-primary'}" ${this.state.isStreaming ? 'disabled' : ''}>Start Stream</button>
                            
                            <button @click="${() => {
                                if (this.sseSource) {
                                    this.sseSource.close();
                                    this.sseSource = null;
                                }
                                this.setState({ isStreaming: false });
                                this.notify('Stream Stopped', 'info');
                            }}" class="sppux-btn sppux-btn-secondary" ${!this.state.isStreaming ? 'disabled' : ''}>Stop Stream</button>
                        </div>
                        
                        <div style="background:#000; color:#0f0; padding:1rem; border-radius:8px; font-family:monospace; height:200px; overflow-y:auto; border:1px solid #333;">
                            ${this.state.sseLogs.length === 0 ? html`<div style="opacity:0.5;">Waiting for stream data...</div>` : ''}
                            ${this.state.sseLogs.map(log => html`<div style="margin-bottom:4px; word-break:break-all;">${log}</div>`)}
                        </div>
                    </div>

                    <!-- 7. HIGH-PERFORMANCE BINARY INDEXING -->
                    <div style="margin-bottom: 2rem; padding: 1.5rem; background: var(--sppux-panel, rgba(30,30,60,0.6)); border-radius: 12px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <h3 style="margin-bottom: 1rem; color: var(--sppux-primary, #6366f1); display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1.5rem;">🔎</span> 7. O(log N) Binary Search Indexing
                        </h3>
                        <p style="margin-bottom: 1rem; opacity: 0.8; line-height: 1.5;">
                            Demonstrates <code>XdbBinaryIndexer</code> which provides ultra-fast in-memory binary search over huge datasets without loading them into memory entirely.
                        </p>
                        <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom: 1rem;">
                            <button @click="${async () => {
                                const res = await this.service('enterprise.indexing', { action: 'build' });
                                if (res && res.status === 'success') {
                                    this.notify(res.message, 'success');
                                }
                            }}" class="sppux-btn sppux-btn-secondary">Build 100K Index</button>
                            <button @click="${async () => {
                                const res = await this.service('enterprise.indexing', { action: 'search', email: 'user50000@example.com' });
                                if (res && res.status === 'success') {
                                    this.notify(res.message, 'success');
                                }
                            }}" class="sppux-btn sppux-btn-primary">Search Index</button>
                        </div>
                    </div>

                    <!-- 8. W3C TRACE CONTEXT -->
                    <div style="margin-bottom: 2rem; padding: 1.5rem; background: var(--sppux-panel, rgba(30,30,60,0.6)); border-radius: 12px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <h3 style="margin-bottom: 1rem; color: var(--sppux-primary, #6366f1); display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1.5rem;">📡</span> 8. W3C Trace Context Telemetry
                        </h3>
                        <p style="margin-bottom: 1rem; opacity: 0.8; line-height: 1.5;">
                            Showcases <code>W3CTraceContext</code> for distributed tracing and context propagation across microservice boundaries.
                        </p>
                        <button @click="${async () => {
                            const res = await this.service('enterprise.tracing', {});
                            if (res && res.status === 'success') {
                                this.notify('Trace generated: ' + res.trace_id, 'info');
                                console.log('Trace details:', res);
                            }
                        }}" class="sppux-btn sppux-btn-secondary">Generate Distributed Trace</button>
                    </div>

                    <!-- 9. VIEW TRANSITIONS & LIVE COMPONENTS -->
                    <div style="margin-bottom: 2rem; padding: 1.5rem; background: var(--sppux-panel, rgba(30,30,60,0.6)); border-radius: 12px; border: 1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                        <h3 style="margin-bottom: 1rem; color: var(--sppux-primary, #6366f1); display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1.5rem;">🪄</span> 9. View Transitions & Live Components
                        </h3>
                        <p style="margin-bottom: 1rem; opacity: 0.8; line-height: 1.5;">
                            Demonstrates SPP's Live Components utilizing HTMX-like server-side reactivity powered by <code>wire:click</code> directives inside an external partial, rendered inside SPPUX via the <code>SPPLive</code> engine.
                        </p>
                        <div id="live-component-container">
                            ${this.state.liveHtml ? new SPPUX.TrustedHTML(this.state.liveHtml) : html`<div style="opacity:0.5; padding:1rem;">Loading Live Component...</div>`}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

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
                        ${['welcome', 'features', 'showcase', 'themes', 'api', 'enterprise'].map(tab => html`
                            <button @click="${() => this.switchTab(tab)}"
                                    style="padding:0.5rem 1rem; border:none; border-radius:8px; cursor:pointer; font-family:inherit; font-weight:${this.state.activeTab === tab ? '600' : '400'}; background:${this.state.activeTab === tab ? 'var(--sppux-primary, #6366f1)' : 'transparent'}; color:${this.state.activeTab === tab ? '#fff' : 'inherit'}; text-transform:capitalize;">
                                ${tab}
                            </button>
                        `)}
                        <div style="width: 1px; background: rgba(255,255,255,0.2); margin: 0 0.5rem;"></div>
                        <a href="${this.props.appRoot || '/'}"
                           style="padding:0.5rem 1rem; border:1px solid rgba(255,255,255,0.2); border-radius:8px; cursor:pointer; font-family:inherit; font-weight:600; text-decoration:none; color:var(--sppux-text, #fff); background:rgba(255,255,255,0.05); display:flex; align-items:center;">
                            Exit
                        </a>
                    </div>
                </nav>

                <!-- Stats Grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:2rem;">
                    ${this.state.stats.map(s => html`
                        <div style="padding:1.5rem; background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:14px; backdrop-filter:blur(10px); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                            <span style="font-size:1.5rem;">${s.icon}</span>
                            <div style="font-size:0.8rem; opacity:0.6; margin-top:0.5rem;">${s.label}</div>
                            <div style="font-size:1.4rem; font-weight:700;">${s.value}</div>
                        </div>
                    `)}
                </div>

                <!-- Tab Content -->
                <div style="background:var(--sppux-panel, rgba(30,30,60,0.6)); border-radius:20px; padding:2rem; backdrop-filter:blur(20px); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
                    ${this.state.activeTab === 'welcome' ? this.renderWelcome() : ''}
                    ${this.state.activeTab === 'features' ? this.renderFeatures() : ''}
                    ${this.state.activeTab === 'showcase' ? this.renderShowcase() : ''}
                    ${this.state.activeTab === 'themes' ? this.renderThemes() : ''}
                    ${this.state.activeTab === 'api' ? this.renderApi() : ''}
                    ${this.state.activeTab === 'enterprise' ? this.renderEnterprise() : ''}
                </div>

                <!-- Footer -->
                <footer style="text-align:center; margin-top:2rem; opacity:0.4; font-size:0.85rem;">
                    &copy; ${new Date().getFullYear()} ${this.state.appName} • Powered by SPP-UX Framework
                </footer>
                
                <!-- Floating Action Button (FAB) -->
                ${this.state.activeTab === 'showcase' ? SPPUX.FAB.render('⚡', [
                    { label: 'Trigger Confetti', icon: '🎉', action: () => this.showCelebrate() },
                    { label: 'Open Spotlight', icon: '🔍', action: () => this.showSpotlight() },
                    { label: 'Switch Theme', icon: '🎨', action: () => this.switchTab('themes') }
                ]) : Fragment}
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
                            class="btn primary" style="padding:0.7rem 1.5rem; border-radius:10px; font-weight:600;">
                        Count: ${this.state.counter}
                    </button>
                    <button @click="${() => this.showModal()}"
                            class="btn primary" style="padding:0.7rem 1.5rem; border-radius:10px; font-weight:600; background:var(--sppux-primary-subtle); color:var(--sppux-primary); border:1px solid var(--sppux-primary);">
                        Open Modal
                    </button>
                    <button @click="${() => this.showConfirm()}"
                            class="btn success" style="padding:0.7rem 1.5rem; border-radius:10px; font-weight:600; border:1px solid var(--sppux-success);">
                        Confirm Dialog
                    </button>
                    <button @click="${() => this.showPrompt()}"
                            class="btn warning" style="padding:0.7rem 1.5rem; border-radius:10px; font-weight:600; border:1px solid var(--sppux-warning);">
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
            { icon: '🛡️', title: 'Error Boundaries', desc: 'Component-level try/catch prevents cascading failures.' },
            { icon: '🔮', title: 'Speculative UI', desc: 'Zero-latency mutations with automatic rollback support.' },
            { icon: '⏳', title: 'Time Travel', desc: 'Rollback layout states exactly to previous snapshots.' },
            { icon: '🪄', title: 'View Transitions', desc: 'Native auto-animations for entity transitions.' },
            { icon: '🔗', title: 'Two-Way Binding', desc: 'spp-model attribute automatically binds to state.' },
            { icon: '🚀', title: 'Turbo Streams', desc: 'Built-in support for real-time partial updates.' },
        ];
        return html`
            <div>
                <h2 style="margin-top:0;">🚀 SPP-UX Features</h2>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:1rem;">
                    ${features.map(f => html`
                        <div style="padding:1.2rem; background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border-radius:12px; border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08));">
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
                <div style="margin-top:1.5rem; padding:1rem; background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border-radius:10px; font-family:monospace; font-size:0.85rem;">
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
                <div style="padding:1rem; background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border-radius:10px; font-family:monospace; font-size:0.85rem; line-height:1.8; overflow-x:auto;">
                    <span style="opacity:0.5;">// Call a PHP service from JavaScript</span><br>
                    <span style="color:var(--sppux-code-keyword);">const</span> result = <span style="color:var(--sppux-code-keyword);">await</span> this.service(<span style="color:var(--sppux-code-text);">'task.create'</span>, {<br>
                    &nbsp;&nbsp;taskTitle: <span style="color:var(--sppux-code-text);">'My Task'</span>,<br>
                    &nbsp;&nbsp;taskPriority: <span style="color:var(--sppux-code-text);">'High'</span><br>
                    });<br><br>
                    <span style="opacity:0.5;">// The PHP service is at: src/Samvaad/serv/task_create.php</span><br>
                    <span style="opacity:0.5;">// Registered in: src/Samvaad/etc/services.yml</span>
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

    renderShowcase() {
        return html`
            <div>
                <h2 style="margin-top:0; font-size:2rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:1rem;">
                    ✨ SPP-UX Exhaustive Enterprise Showcase
                </h2>
                <p style="opacity:0.8; font-size:1.1rem; line-height:1.7; margin-bottom:3rem;">
                    Welcome to the ultimate interactive living design system. Every single SPP-UX component, widget, overlay, and web API is demonstrated below with live controls <b>AND copy-pasteable implementation code</b> designed for developers of all skill levels.
                </p>

                <!-- SECTION 1: INTERACTIVE DIALOGS & OVERLAYS -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🛡️</span> Section 1: Interactive Dialogs & Overlays
                    </h3>
                    
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
                        
                        <!-- Master Grid -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">📊</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Virtualized Master Grid</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A high-performance virtualized data table engineered to handle large datasets smoothly with custom columns and row height rendering.
                                </p>
                                <button @click="${() => this.showMasterGrid()}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Launch Master Grid
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                const grid = new SPPUX.MasterGrid(null, container, {<br>
                                &nbsp;&nbsp;columns: [{ key: 'id', label: 'ID' }],<br>
                                &nbsp;&nbsp;data: [{ id: 'UX-01' }],<br>
                                &nbsp;&nbsp;rowHeight: 45<br>
                                });<br>
                                grid.update();<br>
                                grid.onMount();
                            </div>
                        </div>

                        <!-- Spotlight Palette -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">🔍</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Spotlight Command Palette</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A universal floating search palette supporting global keyboard shortcuts (ESC to close), live fuzzy search filtering, and callback selection.
                                </p>
                                <button @click="${() => this.showSpotlight()}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Open Spotlight (ESC)
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Spotlight.open([<br>
                                &nbsp;&nbsp;{ title: 'Dashboard', desc: 'Main view', icon: '📊' }<br>
                                ], (selected) => {<br>
                                &nbsp;&nbsp;SPPUX.notify(selected.title + ' clicked', 'success');<br>
                                });
                            </div>
                        </div>

                        <!-- Sliding Drawer -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">🚪</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Sliding Drawer Panel</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    An elegant, animated off-canvas side drawer perfect for deep configuration panels, navigation hierarchies, or master-detail inspection.
                                </p>
                                <button @click="${() => this.showDrawer()}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Open Sliding Drawer
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Drawer.open(<br>
                                &nbsp;&nbsp;'Drawer Title',<br>
                                &nbsp;&nbsp;'&lt;p&gt;Drawer HTML content&lt;/p&gt;',<br>
                                &nbsp;&nbsp;'right'<br>
                                );
                            </div>
                        </div>

                        <!-- Sub-Editor Modal -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">📝</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Sub-Editor Overlay</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A spacious glassmorphic modal overlay tailored for complex form editing, workflow configurations, and real-time visual designing.
                                </p>
                                <button @click="${() => this.showSubEditor()}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Open Sub-Editor
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.openSubEditor(<br>
                                &nbsp;&nbsp;'Enterprise Settings',<br>
                                &nbsp;&nbsp;'&lt;div&gt;Form fields...&lt;/div&gt;',<br>
                                &nbsp;&nbsp;{ initialKey: 'val' },<br>
                                &nbsp;&nbsp;async (data) =&gt; save(data)<br>
                                );
                            </div>
                        </div>

                        <!-- Floating Popover -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">💬</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Interactive Popover</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A smart floating popover dialog dynamically positioned next to its triggering element with automatic backdrop dismissal.
                                </p>
                                <button @click="${(e) => this.showPopover(e)}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Toggle Popover
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Popover.open(<br>
                                &nbsp;&nbsp;buttonElement,<br>
                                &nbsp;&nbsp;'Popover Title',<br>
                                &nbsp;&nbsp;'Content body text'<br>
                                );
                            </div>
                        </div>

                        <!-- Context Menu -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">🖱️</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Custom Context Menu</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A high-performance custom context menu spawned exactly at the mouse pointer's coordinates. Click the button below to spawn it.
                                </p>
                                <button @click="${(e) => this.showContextMenu(e)}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Spawn Context Menu
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.ContextMenu.open(e, [<br>
                                &nbsp;&nbsp;{ label: 'Edit', icon: '✏️', action: () =&gt; {} },<br>
                                &nbsp;&nbsp;{ label: 'Delete', icon: '🗑️', action: () =&gt; {} }<br>
                                ]);
                            </div>
                        </div>

                        <!-- Lightbox Modal -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">🖼️</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Lightbox Media Viewer</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    An immersive full-screen image lightbox overlay featuring buttery-smooth entrance scaling and one-click/ESC dismissal.
                                </p>
                                <button @click="${() => this.showLightbox()}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Open Lightbox Viewer
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Lightbox.open(<br>
                                &nbsp;&nbsp;'https://url-to-image.jpg',<br>
                                &nbsp;&nbsp;'Image Alt Description'<br>
                                );
                            </div>
                        </div>

                        <!-- Confetti Particle Engine -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">🎉</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Confetti Particle Physics</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A highly optimized particle generator that rains vibrant confetti across the entire screen. Ideal for celebrating user milestones.
                                </p>
                                <button @click="${() => this.showCelebrate()}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Trigger Confetti Burst
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Celebrate.burst();
                            </div>
                        </div>

                        <!-- Interactive Prompt -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:2rem;">❓</span>
                                    <h4 style="margin:0; font-size:1.2rem;">Universal Prompt Dialog</h4>
                                </div>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A fully customized glassmorphic prompt input dialog that perfectly captures user input via clean JavaScript callback promises.
                                </p>
                                <button @click="${() => this.showPrompt()}" style="width:100%; padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:1rem;">
                                    Open Prompt Dialog
                                </button>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                const name = await this.prompt(<br>
                                &nbsp;&nbsp;'What is your name?',<br>
                                &nbsp;&nbsp;'Default Value'<br>
                                );
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 2: ADVANCED FORM & INPUT CONTROLS -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🎛️</span> Section 2: Advanced Form & Input Controls
                    </h3>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
                        
                        <!-- Switch Toggle -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🔘</span> Switch Toggle Control
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    An accessible, smooth animated binary toggle switch completely integrated into the SPP-UX reactive state cycle.
                                </p>
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem; background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border-radius:12px;">
                                    <span style="font-weight:600;">Current State: ${this.state.switchState ? '🟢 Active' : '⚪ Inactive'}</span>
                                    ${SPPUX.Switch.render(this.state.switchState, (val) => this.setState({ switchState: val }))}
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Switch.render(<br>
                                &nbsp;&nbsp;this.state.switchState,<br>
                                &nbsp;&nbsp;(val) =&gt; this.setState({ switchState: val })<br>
                                );
                            </div>
                        </div>

                        <!-- Chips / Tag Input -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🏷️</span> Dynamic Chips & Tags
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A versatile multi-tag input manager. Type any word and press <b>Enter</b> to add a chip, or click <b>✕</b> to remove one.
                                </p>
                                ${SPPUX.Chips.render(
                                    this.state.chipsList, 
                                    (tag) => this.setState({ chipsList: [...this.state.chipsList, tag] }), 
                                    (idx) => this.setState({ chipsList: this.state.chipsList.filter((_, i) => i !== idx) })
                                )}
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Chips.render(<br>
                                &nbsp;&nbsp;this.state.chipsList,<br>
                                &nbsp;&nbsp;(tag) =&gt; addTag(tag),<br>
                                &nbsp;&nbsp;(idx) =&gt; removeTag(idx)<br>
                                );
                            </div>
                        </div>

                        <!-- Combobox Search -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🗂️</span> Autocomplete Combobox
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A powerful dropdown select with built-in real-time fuzzy typing search to instantly filter through large item catalogues.
                                </p>
                                ${SPPUX.Combobox.render([
                                    { label: '⚡ Next-Gen Telemetry (opt1)', value: 'opt1' },
                                    { label: '🛡️ Advanced W3C Security (opt2)', value: 'opt2' },
                                    { label: '💎 O(log N) Binary Index (opt3)', value: 'opt3' }
                                ], this.state.comboboxValue, (val) => {
                                    this.setState({ comboboxValue: val });
                                    this.notify(`Selected option: ${val}`, 'success');
                                }, "Type to search options...")}
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Combobox.render([<br>
                                &nbsp;&nbsp;{ label: 'Option 1', value: 'opt1' }<br>
                                ], this.state.selectedValue, (val) =&gt; {<br>
                                &nbsp;&nbsp;this.setState({ selectedValue: val });<br>
                                });
                            </div>
                        </div>

                        <!-- Color Picker -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🎨</span> Inline Color Picker
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A sleek color selection box displaying the live hex color code alongside a fully native interactive color wheel.
                                </p>
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem; background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border-radius:12px;">
                                    <span style="font-weight:600; color:${this.state.colorValue}">Selected Color</span>
                                    ${SPPUX.ColorPicker.render(this.state.colorValue, (val) => this.setState({ colorValue: val }))}
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.ColorPicker.render(<br>
                                &nbsp;&nbsp;this.state.colorValue,<br>
                                &nbsp;&nbsp;(val) =&gt; this.setState({ colorValue: val })<br>
                                );
                            </div>
                        </div>

                        <!-- Rating Stars -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>⭐</span> Star Rating Component
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    An interactive 5-star evaluation widget supporting custom max values, hover styles, and instant reactive state selection.
                                </p>
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem; background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border-radius:12px;">
                                    <span style="font-weight:600;">Rating: ${this.state.ratingValue} / 5</span>
                                    ${SPPUX.Rating.render(this.state.ratingValue, 5, (val) => {
                                        this.setState({ ratingValue: val });
                                        this.notify(`Rated ${val} stars! ⭐`, 'success');
                                    })}
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Rating.render(<br>
                                &nbsp;&nbsp;this.state.ratingValue,<br>
                                &nbsp;&nbsp;5, // Max stars<br>
                                &nbsp;&nbsp;(val) =&gt; this.setState({ ratingValue: val })<br>
                                );
                            </div>
                        </div>

                        <!-- Drag & Drop Zone -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>☁️</span> Drag & Drop File Zone
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A beautiful dashed dropzone equipped with automatic dragover highlighting and native file input browsing fallback.
                                </p>
                                ${SPPUX.Dropzone.render((files) => {
                                    const currentFiles = this.state.droppedFiles || [];
                                    const newFiles = [...currentFiles, ...files];
                                    this.setState({ droppedFiles: newFiles });
                                    this.notify(`Successfully queued ${files.length} file(s): ${files[0].name}`, 'success');
                                }, "Drag & Drop enterprise assets here or click to browse", this.state.droppedFiles, (index) => {
                                    const newFiles = [...this.state.droppedFiles];
                                    newFiles.splice(index, 1);
                                    this.setState({ droppedFiles: newFiles });
                                })}
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Dropzone.render((files) =&gt; {<br>
                                &nbsp;&nbsp;console.log(files);<br>
                                }, "Drag & Drop files here");
                            </div>
                        </div>

                        <!-- Rich Text Editor -->
                        <div style="grid-column:1/-1; padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px;">
                            <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                <span>✍️</span> Rich Text WYSIWYG Editor
                            </h4>
                            <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                A complete inline Rich Text editor with an executive formatting toolbar supporting bold, italics, underline, bullet lists, and hyperlinking.
                            </p>
                            ${SPPUX.RichText.render(this.state.richTextContent, (val) => this.setState({ richTextContent: val }))}
                            
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.RichText.render(this.state.htmlContent, (newHtml) =&gt; {<br>
                                &nbsp;&nbsp;this.setState({ htmlContent: newHtml });<br>
                                });
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 3: HIGH-PERFORMANCE NAVIGATION & LAYOUTS -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🚀</span> Section 3: High-Performance Navigation & Layouts
                    </h3>

                    <!-- Interactive Stepper -->
                    <div style="margin-bottom:2rem; padding:2rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px;">
                        <h4 style="margin-top:0; margin-bottom:1.5rem; font-size:1.2rem; display:flex; align-items:center; justify-content:space-between;">
                            <span>🔄 Interactive Stepper Flow</span>
                            <button @click="${() => this.advanceStepper()}" style="padding:0.5rem 1rem; font-size:0.85rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600;">
                                Advance Step ➔
                            </button>
                        </h4>
                        ${SPPUX.Stepper.render(['Initialize SPP', 'Boot SPP-UX Runtime', 'Instantiate Stores', 'Production Ready'], this.state.stepperIndex)}
                        
                        <div class="sppux-code-block" style="margin-top:2rem;">
                            <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                            SPPUX.Stepper.render(['Step 1', 'Step 2', 'Step 3'], this.state.activeIndex);
                        </div>
                    </div>

                    <!-- Kanban Board -->
                    <div style="margin-bottom:2rem; padding:2rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px;">
                        <h4 style="margin-top:0; margin-bottom:0.5rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                            <span>📋</span> Interactive Kanban Task Board
                        </h4>
                        <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                            A fully operational drag-and-drop Kanban board. Drag any task card between the <b>To Do</b> and <b>Done</b> columns to witness seamless reactive transitions.
                        </p>
                        ${SPPUX.Kanban.render([
                            { id: 'todo', title: '🟡 To Do', cards: this.state.kanbanCardsTodo },
                            { id: 'done', title: '🟢 Done', cards: this.state.kanbanCardsDone }
                        ], (cardId, colId) => this.handleKanbanDrag(cardId, colId))}
                        
                        <div class="sppux-code-block" style="margin-top:2rem;">
                            <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                            SPPUX.Kanban.render([<br>
                            &nbsp;&nbsp;{ id: 'todo', title: 'To Do', cards: [{ id: 'c1', content: html\`Task 1\` }] }<br>
                            ], (cardId, targetColId) =&gt; moveCard(cardId, targetColId));
                        </div>
                    </div>

                    <!-- Split Pane -->
                    <div style="margin-bottom:2rem; padding:2rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px;">
                        <h4 style="margin-top:0; margin-bottom:0.5rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                            <span>🗂️</span> Adjustable Split Pane Layout
                        </h4>
                        <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                            A highly versatile resizable dual-pane container. Drag the central vertical divider left or right to dynamically alter panel widths.
                        </p>
                        <div style="height:200px; border:1px solid var(--sppux-glass-border); border-radius:12px; overflow:hidden;">
                            ${SPPUX.SplitPane.render(
                                html`<div style="padding:1.5rem; height:100%; background:rgba(99,102,241,0.05);"><b>👈 Left Viewport</b><p style="opacity:0.7;font-size:0.85rem;">Drag the divider bar to resize this view.</p></div>`,
                                html`<div style="padding:1.5rem; height:100%; background:rgba(16,185,129,0.05);"><b>👉 Right Viewport</b><p style="opacity:0.7;font-size:0.85rem;">Inspect side-by-side component data effortlessly.</p></div>`
                            )}
                        </div>
                        
                        <div class="sppux-code-block" style="margin-top:2rem;">
                            <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                            SPPUX.SplitPane.render(<br>
                            &nbsp;&nbsp;html\`&lt;div&gt;Left pane content&lt;/div&gt;\`,<br>
                            &nbsp;&nbsp;html\`&lt;div&gt;Right pane content&lt;/div&gt;\`<br>
                            );
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
                        
                        <!-- Tree View -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🌳</span> Collapsible Tree View
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A recursive folder hierarchy viewer. Click on any parent node with a ▼ arrow to instantly expand or collapse its child leaves.
                                </p>
                                <div style="padding:1rem; background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border-radius:12px;">
                                    ${SPPUX.TreeView.render([
                                        { label: '📁 src', children: [
                                            { label: '📁 Samvaad', children: [
                                                { label: '📄 main.js (Active Showcase)' },
                                                { label: '📄 sppux.js (Reactivity Engine)' }
                                            ]},
                                            { label: '📄 spp.php (CLI Orchestrator)' }
                                        ]},
                                        { label: '📁 docs', children: [
                                            { label: '📄 AGENTS.md (Enterprise Rules)' }
                                        ]}
                                    ])}
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.TreeView.render([<br>
                                &nbsp;&nbsp;{ label: 'Root Folder', children: [{ label: 'Child File.js' }] }<br>
                                ]);
                            </div>
                        </div>

                        <!-- Accordion -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>📑</span> Animated Accordion
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A beautifully animated vertical accordion. Click on any section header to smoothly slide open its dedicated content area.
                                </p>
                                ${SPPUX.Accordion.render([
                                    { title: '🛡️ Zero Inline HTML Literals', content: 'SPP enforces strict architectural standards by rendering external partials rather than writing raw HTML strings in backend controllers.' },
                                    { title: '🔒 Distributed Mutex Locking', content: 'SPPDeploy prevents race conditions during critical cluster deployments by wrapping execution in distributed mutex locks.' },
                                    { title: '📖 Novice-First Documentation', content: 'All guides are structured so that a total novice can understand the entire framework inside and out.' }
                                ], this.state.accordionIndex, (idx) => this.setState({ accordionIndex: this.state.accordionIndex === idx ? -1 : idx }))}
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Accordion.render([<br>
                                &nbsp;&nbsp;{ title: 'Tab 1', content: 'Details...' }<br>
                                ], this.state.activeIndex, (idx) =&gt; this.setState({ activeIndex: idx }));
                            </div>
                        </div>

                        <!-- Tabs & Pagination -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🗂️</span> Tabs & Pagination
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    Clean horizontal tabs paired with an elegant pagination bar designed to paginate through enterprise records effortlessly.
                                </p>
                                <div style="margin-bottom:2rem;">
                                    ${SPPUX.Tabs.render([
                                        { id: 't1', label: '🚀 Overview', content: html`<div style="padding:1rem;background:var(--sppux-card-bg, rgba(255,255,255,0.02));border-radius:8px;margin-top:10px;">Tab 1: High-speed overview data.</div>`, onClick: () => this.setState({ subTabId: 't1' }) },
                                        { id: 't2', label: '⚙️ Configs', content: html`<div style="padding:1rem;background:var(--sppux-card-bg, rgba(255,255,255,0.02));border-radius:8px;margin-top:10px;">Tab 2: Advanced runtime configs.</div>`, onClick: () => this.setState({ subTabId: 't2' }) }
                                    ], this.state.subTabId)}
                                </div>
                                <div style="display:flex; flex-direction:column; gap:10px;">
                                    <span style="font-weight:600; font-size:0.9rem;">Pagination Navigation (Page ${this.state.pageIndex} of 5):</span>
                                    ${SPPUX.Pagination.render(this.state.pageIndex, 5, (p) => {
                                        this.setState({ pageIndex: p });
                                        this.notify(`Navigated to page ${p}`, 'info');
                                    })}
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Pagination.render(<br>
                                &nbsp;&nbsp;this.state.currentPage,<br>
                                &nbsp;&nbsp;totalPages,<br>
                                &nbsp;&nbsp;(p) =&gt; this.setState({ currentPage: p })<br>
                                );
                            </div>
                        </div>

                        <!-- Data Grid -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🧮</span> Clean Data Grid Table
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A lightweight, elegant static data table component perfect for displaying structured analytical info or quick entity lists.
                                </p>
                                ${SPPUX.DataGrid.render([
                                    { key: 'param', label: 'Telemetry Parameter' },
                                    { key: 'val', label: 'Live Value' },
                                    { key: 'status', label: 'Health' }
                                ], [
                                    { param: 'Trace Context', val: 'W3C Active', status: '🟢 OK' },
                                    { param: 'Binary Indexer', val: 'O(log N)', status: '🟢 OK' },
                                    { param: 'DAG Queue', val: 'Throttled', status: '🟡 Busy' }
                                ])}
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.DataGrid.render([<br>
                                &nbsp;&nbsp;{ key: 'id', label: 'ID' }<br>
                                ], [<br>
                                &nbsp;&nbsp;{ id: 1, name: 'Sample' }<br>
                                ]);
                            </div>
                        </div>

                        <!-- Virtual List -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between; grid-column: 1 / -1;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>📜</span> High-Performance Virtual List
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    Renders thousands of items instantly by only drawing what's visible on screen. Extremely memory-efficient!
                                </p>
                                <div style="margin-bottom:2rem; background:rgba(0,0,0,0.2); padding:1rem; border-radius:8px;">
                                    ${SPPUX.VirtualList.render(
                                        Array.from({length: 1000}, (_, i) => ({ id: i, label: 'Virtual Item ' + (i + 1) })),
                                        40,
                                        5,
                                        (item) => html`<div style="height:40px; padding:0 1rem; display:flex; align-items:center; border-bottom:1px solid rgba(255,255,255,0.05);">${item.label}</div>`
                                    )}
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.VirtualList.render(<br>
                                &nbsp;&nbsp;itemsArray, 40 /* itemHeight */, 5 /* visibleCount */,<br>
                                &nbsp;&nbsp;(item) =&gt; html\`&lt;div&gt;\${item.label}&lt;/div&gt;\`<br>
                                );
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 4: STUNNING DATA VISUALIZATION -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>📈</span> Section 4: Stunning Data Visualization & Indicators
                    </h3>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
                        
                        <!-- Radar Chart -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between; align-items:center; text-align:center;">
                            <div style="width:100%;">
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; justify-content:center; gap:10px;">
                                    <span>🕸️</span> Enterprise Radar Chart
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A fully scalable SVG radar chart designed for multi-variable architectural evaluations and performance mapping.
                                </p>
                                <div style="display:flex; justify-content:center; padding:1rem; background:rgba(255,255,255,0.01); border-radius:16px;">
                                    ${SPPUX.RadarChart.render(['Speed', 'Security', 'Reliability', 'Scalability', 'UI/UX'], [0.95, 0.98, 0.92, 0.90, 1.0], 'var(--sppux-primary, #6366f1)', 280, 280)}
                                </div>
                            </div>
                            <div class="sppux-code-block" style="width:100%; text-align:left;">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.RadarChart.render(<br>
                                &nbsp;&nbsp;['Speed', 'Security', 'Scalability'],<br>
                                &nbsp;&nbsp;[0.95, 0.98, 0.90],<br>
                                &nbsp;&nbsp;'#6366f1', 280, 280<br>
                                );
                            </div>
                        </div>

                        <!-- Donut Chart -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between; align-items:center; text-align:center;">
                            <div style="width:100%;">
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; justify-content:center; gap:10px;">
                                    <span>⭕</span> Animated Donut Chart
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    An elegant circular SVG progress donut featuring fluid stroke-dashoffset transitions and clean center labeling.
                                </p>
                                <div style="display:flex; justify-content:center; padding:2rem 0;">
                                    ${SPPUX.DonutChart.render(92, 'Overall Health', '#10b981', 180, 16)}
                                </div>
                            </div>
                            <div class="sppux-code-block" style="width:100%; text-align:left;">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.DonutChart.render(<br>
                                &nbsp;&nbsp;92,<br>
                                &nbsp;&nbsp;'Overall Health',<br>
                                &nbsp;&nbsp;'#10b981', 180, 16<br>
                                );
                            </div>
                        </div>

                        <!-- Sparkline & Heatmap -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🔥</span> Sparklines & Heatmap
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    Breathtaking micro-charts designed for high-density dashboards. Below is a live line sparkline, bar sparkline, and activity heatmap.
                                </p>
                                <div style="display:flex; flex-direction:column; gap:1.5rem; align-items:center; background:rgba(255,255,255,0.01); padding:1rem; border-radius:12px;">
                                    <div>
                                        <div style="font-size:0.8rem; opacity:0.6; margin-bottom:4px; text-align:center;">Load Spike (Line Sparkline)</div>
                                        ${SPPUX.Chart.renderSparkline([12, 25, 18, 45, 35, 60, 55, 70], 'line', '#6366f1', 250, 50)}
                                    </div>
                                    <div>
                                        <div style="font-size:0.8rem; opacity:0.6; margin-bottom:4px; text-align:center;">Memory (Bar Sparkline)</div>
                                        ${SPPUX.Chart.renderSparkline([20, 40, 30, 70, 50, 90, 80, 100], 'bar', '#10b981', 250, 50)}
                                    </div>
                                    <div>
                                        <div style="font-size:0.8rem; opacity:0.6; margin-bottom:4px; text-align:center;">Weekly Commit Heatmap</div>
                                        ${SPPUX.Heatmap.render([[1,3,2,0,1,2,3], [0,1,3,2,0,1,2], [3,2,1,0,3,2,1], [1,2,2,3,3,1,0], [2,3,1,1,2,3,2]], 12, 3)}
                                    </div>
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Chart.renderSparkline([10,25,15,40], 'line', '#6366f1', 250, 50);<br><br>
                                SPPUX.Heatmap.render([[1,3,2],[0,1,2]], 12, 3);
                            </div>
                        </div>

                        <!-- Alerts, Progress & Skeleton -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🔔</span> Alerts, Progress & Skeleton
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    Critical UI indicators including inline status alerts, determinate/indeterminate loading progress bars, and animated shimmer skeletons.
                                </p>
                                <div style="display:flex; flex-direction:column; gap:1.2rem;">
                                    ${SPPUX.Alert.render('Enterprise cluster fully synchronized!', 'success', () => this.notify('Alert dismissed', 'info'))}
                                    <div>
                                        <div style="font-size:0.85rem; margin-bottom:6px; display:flex; justify-content:space-between;"><span>Determinate Progress</span><span>75%</span></div>
                                        ${SPPUX.Progress.render(75)}
                                    </div>
                                    <div>
                                        <div style="font-size:0.85rem; margin-bottom:6px;">Indeterminate Progress</div>
                                        ${SPPUX.Progress.render('indeterminate')}
                                    </div>
                                    <div>
                                        <div style="font-size:0.85rem; margin-bottom:6px;">Shimmer Skeleton Loader</div>
                                        ${SPPUX.Skeleton.render()}
                                    </div>
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.Alert.render('Message', 'success');<br><br>
                                SPPUX.Progress.render(75); // or 'indeterminate'<br><br>
                                SPPUX.Skeleton.render();
                            </div>
                        </div>

                        <!-- Stats Card, Avatar & Tooltips -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>💎</span> Stats Card, Avatar & Tooltips
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    High-fidelity micro-components including trend stats cards, automatic initial avatars, and declarative hovering tooltips.
                                </p>
                                <div style="display:flex; flex-direction:column; gap:1.5rem;">
                                    ${SPPUX.StatsCard.render('Enterprise Revenue', '$4,285,900', { trend: '+24.8%', trendType: 'success', icon: '💰' })}
                                    <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border-radius:12px;">
                                        <span style="font-weight:600;">System Architect Avatar</span>
                                        ${SPPUX.Avatar.render('Satya Shukla', null, 'lg')}
                                    </div>
                                    <div data-spp-tooltip="✨ SPP-UX Tooltip: Flawlessly rendered via declarative attributes!" style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.08); color:#fff; border:1px dashed rgba(255,255,255,0.2); border-radius:10px; text-align:center; font-weight:600; cursor:help;">
                                        💡 Hover Over Me For Tooltip
                                    </div>
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.StatsCard.render('Title', '$100', { trend: '+10%' });<br><br>
                                SPPUX.Avatar.render('Satya Shukla', null, 'lg');<br><br>
                                &lt;div data-spp-tooltip="Tooltip text"&gt;Hover&lt;/div&gt;
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 5: CONVERSATIONAL AI & MEDIA -->
                <div style="margin-bottom:2rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🤖</span> Section 5: Conversational AI, Media & System Sensors
                    </h3>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
                        
                        <!-- AI Chat Window -->
                        <div style="grid-column:1/-1; padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px;">
                            <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                <span>✨</span> Live AI Assistant Chat Window
                            </h4>
                            <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                An immersive conversational chat window component equipped with real-time typing bubble animations and fluid message state synchronization. Type a message below and press Send to test the simulated AI response!
                            </p>
                            
                            <div style="margin-bottom:2rem;">
                                ${SPPUX.ChatWindow.render(this.state.chatMessages, (text) => this.handleChatSend(text), this.state.isTyping)}
                            </div>

                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.ChatWindow.render(<br>
                                &nbsp;&nbsp;this.state.messages,<br>
                                &nbsp;&nbsp;(newText) =&gt; sendMessage(newText),<br>
                                &nbsp;&nbsp;this.state.isTyping<br>
                                );
                            </div>
                        </div>

                        <!-- Media Player -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🎬</span> High-Fidelity Media Player
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    A sleek glassmorphic media player wrapper providing pristine styling around HTML5 video and audio streams.
                                </p>
                                <div style="display:flex; justify-content:center;">
                                    ${SPPUX.MediaPlayer.render('https://www.w3schools.com/html/mov_bbb.mp4', 'video', 'https://picsum.photos/800/450')}
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.MediaPlayer.render(<br>
                                &nbsp;&nbsp;'https://url-to-video.mp4',<br>
                                &nbsp;&nbsp;'video',<br>
                                &nbsp;&nbsp;'https://url-to-poster.jpg'<br>
                                );
                            </div>
                        </div>

                        <!-- System Sensors & FAB Info -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>📡</span> System Sensors & FAB Menu
                                </h4>
                                <p style="margin:0 0 1.5rem; font-size:0.95rem; opacity:0.7; line-height:1.6;">
                                    SPP-UX silently monitors online/offline network statuses and battery level changes in the background. Notice the <b>⚡ Floating Action Button (FAB)</b> at the bottom right of your screen — hover over it to access instant global shortcut triggers!
                                </p>
                                <div class="sppux-alert sppux-alert-success" style="padding:1.2rem; border-radius:12px; display:flex; align-items:center; gap:12px; margin-bottom:0;">
                                    <span style="font-size:1.5rem;">🟢</span>
                                    <div><b>System Sensors Active</b><br><span style="font-size:0.85rem; opacity:0.8;">Online network listener and battery monitor are operational.</span></div>
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                SPPUX.FAB.render('⚡', [<br>
                                &nbsp;&nbsp;{ label: 'Confetti', icon: '🎉', action: () =&gt; {} }<br>
                                ]);<br><br>
                                SPPUX.System.init(); // Auto-boots sensors
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 6: PREMIUM EXTENSIONS (SPPEXT) -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>💎</span> Section 6: Premium Extensions (SPPEXT)
                    </h3>
                    <p style="margin:0 0 2rem; font-size:1rem; opacity:0.8; line-height:1.6;">
                        Native wrappers for the world's most powerful third-party JavaScript libraries, seamlessly integrated into the SPPUX component lifecycle.
                    </p>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
                        
                        <!-- Premium Components Launchers -->
                        <div style="padding:1.8rem; background:var(--sppux-card-bg, rgba(255,255,255,0.02)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.07)); border-radius:16px; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <h4 style="margin:0 0 1rem; font-size:1.2rem; display:flex; align-items:center; gap:10px;">
                                    <span>🧩</span> Embedded Premium Modules
                                </h4>
                                <div style="display:flex; flex-direction:column; gap:1rem;">
                                    <button @click="${() => this.showEditorDemo()}" style="padding:0.8rem; background:var(--sppux-primary, #6366f1); color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-align:left;">📝 Open Rich Text Editor (Quill)</button>
                                    <button @click="${() => this.showCodeDemo()}" style="padding:0.8rem; background:#10b981; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-align:left;">💻 Open Code Engine (Monaco)</button>
                                    <button @click="${() => this.showMapDemo()}" style="padding:0.8rem; background:#f59e0b; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-align:left;">🗺️ Open Geospatial Map (Leaflet)</button>
                                    <button @click="${() => this.showCalendarDemo()}" style="padding:0.8rem; background:#ec4899; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-align:left;">📅 Open Advanced Calendar (Flatpickr)</button>
                                    <button @click="${() => this.showSortableDemo()}" style="padding:0.8rem; background:#8b5cf6; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; text-align:left;">🔄 Open Sortable List (SortableJS)</button>
                                </div>
                            </div>
                            <div class="sppux-code-block">
                                <div class="sppux-code-header">// IMPLEMENTATION CODE:</div>
                                const editor = new SPPUX.Editor({<br>
                                &nbsp;&nbsp;label: 'Document Content',<br>
                                &nbsp;&nbsp;value: '...',<br>
                                &nbsp;&nbsp;height: '400px'<br>
                                }, container);<br>
                                editor.onMount();
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 7: HEADLESS ECOSYSTEM (SPPEX) -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🧠</span> Section 7: Headless Ecosystem (SPPEX State & Logic)
                    </h3>
                    <p style="margin:0 0 2rem; font-size:1rem; opacity:0.8; line-height:1.6;">
                        Non-visual logic wrappers ported from the React ecosystem (Query, Formik, Zustand, XState, etc.).
                    </p>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:1rem;">
                        ${['Query', 'Form', 'StoreSync', 'Machine', 'Helmet', 'i18n', 'WebSocket', 'Motion'].map(comp => html`
                            <div style="background:var(--sppux-card-bg, rgba(255,255,255,0.03)); border:1px solid var(--sppux-glass-border, rgba(255,255,255,0.08)); padding:1.5rem; border-radius:12px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:600; color:var(--sppux-text, #e2e8f0);">${comp}</span>
                                <span class="badge success" style="text-transform:none;">Active 🟢</span>
                            </div>
                        `)}
                    </div>
                </div>

                <!-- SECTION 8: PRO VISUAL PRIMITIVES (SPPEX-PRO) -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>✨</span> Section 8: Pro Visual Primitives (SPPEX-PRO)
                    </h3>
                    <p style="margin:0 0 2rem; font-size:1rem; opacity:0.8; line-height:1.6;">
                        Premium structural components.
                    </p>
                    <div style="margin-bottom:1.5rem; padding:1rem; border-left:4px solid var(--sppux-warning); background:var(--sppux-warning-bg, rgba(180, 83, 9, 0.1)); border-radius:4px; font-size:0.9rem; color:var(--sppux-text);">
                        <strong>⚠️ Crucial Precaution:</strong> Notice how the buttons below are rendered using an array <code>map()</code>. You MUST use <code>html&grave;... &grave;</code> tagged template literals inside the map and NOT standard string literals with <code>.join('')</code>. Using standard strings will cause Lit to auto-escape the HTML for XSS protection, rendering literal raw text strings on the screen instead of buttons!
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                        ${['Carousel', 'Select', 'DatePicker', 'Markdown', 'InfiniteScroll', 'Floating'].map(comp => html`
                            <button @click="${() => this.openEcoDemo(comp)}" class="btn primary" style="padding:1rem; border-radius:12px; font-weight:600;">
                                Launch ${comp}
                            </button>
                        `)}
                    </div>
                </div>

                <!-- SECTION 9: ULTRA ENTERPRISE COMPONENTS (SPPEX-ULTRA) -->
                <div style="margin-bottom:4rem;">
                    <h3 style="font-size:1.5rem; color:var(--sppux-primary, #6366f1); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span>🏢</span> Section 9: Ultra Enterprise Components (SPPEX-ULTRA)
                    </h3>
                    <p style="margin:0 0 2rem; font-size:1rem; opacity:0.8; line-height:1.6;">
                        Heavy-duty layout and data structures.
                    </p>
                    <div style="margin-bottom:1.5rem; padding:1rem; border-left:4px solid var(--sppux-info); background:var(--sppux-info-bg, rgba(14, 165, 233, 0.1)); border-radius:4px; font-size:0.9rem; color:var(--sppux-text);">
                        <strong>🎨 Theming Precaution:</strong> Never use hardcoded inline hex or rgba colors (e.g., <code>style="background:#10b981;"</code>). These buttons use framework classes (<code>class="btn success"</code>) so they automatically adapt perfectly to both Dark and Light themes.
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                        ${['Masonry', 'Timeline', 'RangeSlider', 'Highlight', 'AvatarGroup', 'Badge', 'Breadcrumbs', 'CopyToClipboard', 'Resizable', 'DnD'].map(comp => html`
                            <button @click="${() => this.openEcoDemo(comp)}" class="btn success" style="padding:1rem; border-radius:12px; font-weight:600;">
                                Launch ${comp}
                            </button>
                        `)}
                    </div>
                </div>

            </div>
        `;
    }
}