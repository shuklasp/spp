/**
 * SPP Framework Standalone Command Palette Controller
 * Handles capture-phase accelerator interception (Ctrl+K / Cmd+K), Omnibox suppression,
 * keyboard navigation, dynamic application commands, and debounced remote search.
 */
(function () {
    'use strict';

    let paletteBackdrop = null;
    let searchInput = null;
    let resultsContainer = null;
    let activeIndex = -1;
    let debounceTimer = null;
    const customCommands = [];

    // Cheatsheet modal variables
    let cheatsheetBackdrop = null;
    let cheatsheetSearchInput = null;
    let cheatsheetBody = null;

    // Hardware-level key identification to beat browser Omnibox interception
    function isKeyK(e) {
        if (!e) return false;
        return (e.key && e.key.toLowerCase() === 'k') ||
               e.code === 'KeyK' ||
               e.keyCode === 75 ||
               e.which === 75;
    }

    function isPaletteAccelerator(e) {
        if (!e) return false;
        // Ctrl+K or Cmd+K (no Alt)
        if ((e.ctrlKey || e.metaKey) && !e.altKey && isKeyK(e)) return true;
        // Ctrl+Shift+P or Cmd+Shift+P
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key?.toLowerCase() === 'p' || e.code === 'KeyP')) return true;
        // F1 (standard help/command palette)
        if (e.key === 'F1' && !e.ctrlKey && !e.metaKey && !e.altKey) return true;
        return false;
    }

    function isCheatsheetAccelerator(e) {
        if (!e) return false;
        // Ctrl+/ or Cmd+/ (no Alt)
        if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === '/' || e.code === 'Slash')) return true;
        // Shift+? (when not editing an input)
        if (e.shiftKey && (e.key === '?' || e.code === 'Slash') && !e.ctrlKey && !e.altKey && !e.metaKey) {
            const targetTag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            const isEditing = targetTag === 'input' || targetTag === 'textarea' || (e.target && e.target.isContentEditable);
            if (!isEditing) return true;
        }
        return false;
    }

    function showShortcutToast(title, shortcut) {
        let toast = document.getElementById('spp-shortcut-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'spp-shortcut-toast';
            toast.className = 'spp-shortcut-toast';
            document.body.appendChild(toast);
        }
        toast.innerHTML = `<span class="spp-shortcut-toast-icon">⚡</span> <span class="spp-shortcut-toast-text">${escapeHtml(title)}</span> <kbd class="spp-cmd-kbd" style="background:rgba(255,255,255,0.2); color:#fff; border-color:rgba(255,255,255,0.3); font-weight:700;">${escapeHtml(shortcut)}</kbd>`;
        toast.classList.add('visible');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => {
            toast.classList.remove('visible');
        }, 1400);
    }

    // Comprehensive Hotkeys Reference Registry
    const SHORTCUT_GROUPS = [
        {
            title: '⚡ Universal & Command Palette',
            items: [
                { kbd: 'Ctrl+K', label: 'Command Palette', desc: 'Universal command search & workbench launcher', action: () => openPalette() },
                { kbd: 'Ctrl+/', label: 'Hotkeys Cheatsheet', desc: 'Toggle this interactive keyboard shortcuts reference', action: () => toggleCheatsheet() },
                { kbd: 'Ctrl+Shift+P', label: 'Command Palette (Alt)', desc: 'VS Code-style command palette accelerator', action: () => openPalette() },
                { kbd: 'F1', label: 'Help / Command Palette', desc: 'Standard IDE function key accelerator', action: () => openPalette() },
                { kbd: 'Esc', label: 'Close Active Overlay', desc: 'Dismiss command palette, cheatsheet, or dialog' }
            ]
        },
        {
            title: '🌐 Sovereign Hosting & Operations (Alt + Key)',
            items: [
                { kbd: 'Alt+0', label: 'Operations Dashboard', desc: 'Operational overview, CPU/RAM telemetry & health', view: 'dashboard' },
                { kbd: 'Alt+M', label: 'Mail & Gateway Workstation', desc: 'e-Vaarta Sovereign Mail, DKIM diagnostics & mailboxes', view: 'mail' },
                { kbd: 'Alt+D', label: 'Virtual Hosts & Domains', desc: 'Domain routing, vhosts, SSL/TLS certificates & DNS', view: 'domains' },
                { kbd: 'Alt+E', label: 'File Manager & Storage', desc: 'Web file explorer, zip extraction & disk treemap', view: 'files' },
                { kbd: 'Alt+B', label: 'Database Studio & SQL', desc: 'Relational database manager, SQL console & XDB', view: 'database' },
                { kbd: 'Alt+T', label: 'Cloud Web Terminal', desc: 'Interactive secure CLI shell & environment control', view: 'terminal' },
                { kbd: 'Alt+R', label: 'Deployments & Releases', desc: 'Git release pipelines, rollback & snapshot backups', view: 'deploy' },
                { kbd: 'Alt+F', label: 'Fleet & DNS Cluster', desc: 'Multi-server fleet orchestrator & BIND DNS replication', view: 'fleet' },
                { kbd: 'Alt+C', label: 'Scheduled Tasks & Cron', desc: 'Cron job manager, queue workers & DAG orchestrator', view: 'cron' },
                { kbd: 'Alt+S', label: 'Security & WAF Shield', desc: 'Web application firewall, IP blocker & rate limits', view: 'shield' },
                { kbd: 'Alt+L', label: 'Metrics & Analytics', desc: 'Server bandwidth metrics, HTTP traffic & access logs', view: 'metrics' },
                { kbd: 'Alt+P', label: 'APM & Flame Graphs', desc: 'In-panel profiler, Speedscope flame graphs & P99 latency', view: 'apm' },
                { kbd: 'Alt+W', label: 'Webhook Relay & Tunnel', desc: 'Sovereign webhook ingress relay & HMAC inspector (ngrok)', view: 'webhooks' },
                { kbd: 'Alt+A', label: 'API Studio & OpenAPI', desc: 'Polyglot API client, OpenAPI 3.1 generator & curl runner', view: 'apistudio' },
                { kbd: 'Alt+I', label: 'Identity & Access (IAM)', desc: 'User accounts, RBAC roles & FIDO2 passkeys', view: 'identity' },
                { kbd: 'Alt+O', label: 'Observability & Diagnostics', desc: 'Server diagnostics, live error traces & health checks', view: 'system' }
            ]
        },
        {
            title: '💻 Developer Studio & App Architecture',
            items: [
                { kbd: 'Alt+K', label: 'Code Studio & Scaffolder', desc: 'In-browser IDE, code explorer & generators', view: 'coder' },
                { kbd: 'Alt+V', label: 'SPP Studio (Visual Builder)', desc: 'Visual app designer, canvas, split view & HTMX', view: 'pages' },
                { kbd: 'Ctrl+`', label: 'Web Tinker REPL', desc: 'Interactive PHP expression eval sandbox drawer', action: () => {
                    closeCheatsheet();
                    if (window.admin?.viewInstances?.pages?.toggleBottomDrawer) {
                        window.admin.viewInstances.pages.setState({ bottomDrawerOpen: true, bottomDrawerTab: 'tinker' });
                    } else {
                        navigateToView('tinker');
                    }
                }},
                { kbd: 'Alt+1', label: 'Studio: Visual Canvas', desc: 'WYSIWYG live editing canvas with component inspector' },
                { kbd: 'Alt+2', label: 'Studio: Live Split View', desc: 'Side-by-side interactive visual canvas and code editor' },
                { kbd: 'Alt+3', label: 'Studio: Code Studio', desc: 'Full-page syntax highlighted Blade / PHP source editor' },
                { kbd: 'Alt+4', label: 'Studio: Database CRUD', desc: 'ERD schema canvas, migrations & table browser' },
                { kbd: 'Alt+5', label: 'Studio: API Tester', desc: 'Interactive polyglot API request runner & inspector' }
            ]
        },
        {
            title: '🚀 Instant Enterprise Actions (Ctrl + Shift + Key)',
            items: [
                { kbd: 'Ctrl+Shift+U', label: 'Run Parikshak Tests', desc: 'Execute automated unit and feature test suite', action: () => {
                    closeCheatsheet();
                    if (window.admin?.viewInstances?.pages?.runParikshakStudio) {
                        window.admin.viewInstances.pages.setState({ bottomDrawerOpen: true, bottomDrawerTab: 'parikshak' });
                        window.admin.viewInstances.pages.runParikshakStudio();
                    } else {
                        navigateToView('testing');
                    }
                }},
                { kbd: 'Ctrl+Shift+D', label: 'BIND DNS Cluster Sync', desc: 'Broadcast and synchronize authoritative DNS zones', action: () => {
                    closeCheatsheet();
                    navigateToView('fleet');
                    setTimeout(() => { if (window.admin?.viewInstances?.fleet?.syncCluster) window.admin.viewInstances.fleet.syncCluster(); }, 300);
                }},
                { kbd: 'Ctrl+Shift+K', label: 'FIDO2 Hardware Passkeys', desc: 'Manage WebAuthn security keys & biometrics', action: () => {
                    closeCheatsheet();
                    navigateToView('identity');
                    setTimeout(() => { if (window.admin?.viewInstances?.identity?.switchTab) window.admin.viewInstances.identity.switchTab('passkeys'); }, 300);
                }},
                { kbd: 'Ctrl+Shift+O', label: 'Export OpenAPI 3.1 Spec', desc: 'Export complete OpenAPI specification JSON', action: () => {
                    closeCheatsheet();
                    navigateToView('apistudio');
                    setTimeout(() => { if (window.admin?.viewInstances?.apistudio?.exportSpec) window.admin.viewInstances.apistudio.exportSpec('json'); }, 300);
                }},
                { kbd: 'Ctrl+Shift+W', label: 'Inspect Webhook Relays', desc: 'View inbound webhook payload stream & inspector', action: () => {
                    closeCheatsheet();
                    navigateToView('webhooks');
                }},
                { kbd: 'Ctrl+Shift+F', label: 'APM Flame Graphs', desc: 'Analyze execution bottlenecks & slow SQL spans', action: () => {
                    closeCheatsheet();
                    navigateToView('apm');
                }},
                { kbd: 'Ctrl+Shift+S', label: 'Simulate Workflow & Saga', desc: 'Interactive dry-run simulator for states & rollbacks', action: () => {
                    closeCheatsheet();
                    navigateToView('workflows');
                    setTimeout(() => { if (window.admin?.viewInstances?.workflows?.openSimulationModal) window.admin.viewInstances.workflows.openSimulationModal(); }, 300);
                }},
                { kbd: 'Ctrl+Shift+E', label: 'Neutralize Tracking Pixels', desc: 'Disarm 1x1 surveillance beacons in mail', action: () => {
                    closeCheatsheet();
                    navigateToView('mail');
                }},
                { kbd: 'Ctrl+Shift+C', label: 'Purge Caches & Refresh', desc: 'Clear view, schema & Drishyam caches and reload', action: () => {
                    closeCheatsheet();
                    if (window.admin?.viewInstances?.pages?.purgeStudioCache) window.admin.viewInstances.pages.purgeStudioCache();
                    else location.reload();
                }},
                { kbd: 'Ctrl+Shift+H', label: 'Smoke Test Page Health', desc: 'Validate DOM nesting, orphan partials & auto-fix', action: () => {
                    closeCheatsheet();
                    if (window.admin?.viewInstances?.pages?.checkPageHealth) window.admin.viewInstances.pages.checkPageHealth();
                    else navigateToView('pages');
                }},
                { kbd: 'Ctrl+I', label: 'Inline AI Copilot', desc: 'Ask AI assistant for code generation or refactoring', action: () => {
                    closeCheatsheet();
                    if (window.admin?.viewInstances?.pages?.toggleAiCopilot) window.admin.viewInstances.pages.toggleAiCopilot();
                    else navigateToView('ai');
                }}
            ]
        },
        {
            title: '📬 e-Vaarta Sovereign Mail Operations (in Mail View)',
            items: [
                { kbd: 'c', label: 'Compose New Email', desc: 'Open e-Vaarta rich composer modal', action: () => {
                    closeCheatsheet();
                    navigateToView('mail');
                    setTimeout(() => { if (window.mailViewInstance?.openComposer) window.mailViewInstance.openComposer(); }, 300);
                }},
                { kbd: 'j / k', label: 'Next / Prev Conversation', desc: 'Navigate between email threads in list' },
                { kbd: 'e', label: 'Archive Conversation', desc: 'Move current conversation to archive' },
                { kbd: 's', label: 'Star / Flag Conversation', desc: 'Toggle star/important flag on selected thread' },
                { kbd: 'u', label: 'Toggle Unread Status', desc: 'Mark thread as read or unread' },
                { kbd: 'b', label: 'Snooze Conversation', desc: 'Defer conversation until later' },
                { kbd: '#', label: 'Move to Trash', desc: 'Move selected thread to trash folder' },
                { kbd: 'r', label: 'Quick Reply', desc: 'Open inline reply to active conversation' },
                { kbd: '?', label: 'Mail Shortcuts Modal', desc: 'Show e-Vaarta native shortcuts cheatsheet', action: () => {
                    closeCheatsheet();
                    if (window.mailViewInstance?.openShortcutsModal) window.mailViewInstance.openShortcutsModal();
                }}
            ]
        }
    ];

    function ensureCheatsheetDOM() {
        if (document.getElementById('spp-shortcuts-cheatsheet-backdrop')) {
            cheatsheetBackdrop = document.getElementById('spp-shortcuts-cheatsheet-backdrop');
            cheatsheetSearchInput = document.getElementById('spp-shortcuts-search-input');
            cheatsheetBody = document.getElementById('spp-shortcuts-body');
            return;
        }

        const backdrop = document.createElement('div');
        backdrop.id = 'spp-shortcuts-cheatsheet-backdrop';
        backdrop.className = 'spp-shortcuts-backdrop';
        backdrop.style.display = 'none';
        backdrop.innerHTML = `
            <div class="spp-shortcuts-modal" onclick="event.stopPropagation()">
                <div class="spp-shortcuts-header">
                    <div class="spp-shortcuts-title-wrap">
                        <span class="spp-shortcuts-icon">⌨️</span>
                        <div>
                            <h3 class="spp-shortcuts-heading">SPP Workbench Keyboard Accelerators</h3>
                            <p class="spp-shortcuts-subheading">High-performance capture-phase hotkeys across sovereign hosting, studio & enterprise workflows</p>
                        </div>
                    </div>
                    <div class="spp-shortcuts-header-right">
                        <input type="text" id="spp-shortcuts-search-input" class="spp-shortcuts-search-input" placeholder="Filter hotkeys (Ctrl, Alt, View)..." autocomplete="off">
                        <kbd class="spp-cmd-esc-kbd" id="spp-shortcuts-close-btn" style="cursor:pointer;" title="Close (ESC)">ESC</kbd>
                    </div>
                </div>
                <div class="spp-shortcuts-body" id="spp-shortcuts-body"></div>
                <div class="spp-shortcuts-footer">
                    <span>⚡ Press <kbd class="spp-cmd-kbd">Ctrl+K</kbd> for Command Palette, or <kbd class="spp-cmd-kbd">Ctrl+/</kbd> to toggle this reference.</span>
                    <button type="button" id="spp-shortcuts-close-btn2" class="spp-cmd-kbd" style="cursor:pointer; padding:4px 10px;">Close (ESC)</button>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);

        cheatsheetBackdrop = backdrop;
        cheatsheetSearchInput = document.getElementById('spp-shortcuts-search-input');
        cheatsheetBody = document.getElementById('spp-shortcuts-body');

        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) closeCheatsheet();
        });

        const closeBtn = document.getElementById('spp-shortcuts-close-btn');
        if (closeBtn) closeBtn.addEventListener('click', closeCheatsheet);
        const closeBtn2 = document.getElementById('spp-shortcuts-close-btn2');
        if (closeBtn2) closeBtn2.addEventListener('click', closeCheatsheet);

        if (cheatsheetSearchInput) {
            cheatsheetSearchInput.addEventListener('input', function () {
                renderCheatsheetGrid(cheatsheetSearchInput.value);
            });
            cheatsheetSearchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    closeCheatsheet();
                }
            });
        }
    }

    function renderCheatsheetGrid(filterQuery) {
        if (!cheatsheetBody) return;
        const q = (filterQuery || '').trim().toLowerCase();

        let html = '';
        SHORTCUT_GROUPS.forEach((group) => {
            const matchingItems = group.items.filter(item => {
                if (!q) return true;
                const kbdLower = (item.kbd || '').toLowerCase();
                const labelLower = (item.label || '').toLowerCase();
                const descLower = (item.desc || '').toLowerCase();
                return kbdLower.includes(q) || labelLower.includes(q) || descLower.includes(q);
            });

            if (matchingItems.length === 0) return;

            html += `
                <div class="spp-shortcuts-section">
                    <div class="spp-shortcuts-section-title">${escapeHtml(group.title)}</div>
            `;

            matchingItems.forEach((item) => {
                html += `
                    <div class="spp-shortcuts-row" data-kbd="${escapeHtml(item.kbd)}" data-label="${escapeHtml(item.label)}" data-view="${escapeHtml(item.view || '')}">
                        <div class="spp-shortcuts-row-info">
                            <span class="spp-shortcuts-label">${escapeHtml(item.label)}</span>
                            <span class="spp-shortcuts-desc">${escapeHtml(item.desc)}</span>
                        </div>
                        <div class="spp-shortcuts-kbd-wrap">
                            <kbd class="spp-cmd-kbd">${escapeHtml(item.kbd)}</kbd>
                        </div>
                    </div>
                `;
            });

            html += `</div>`;
        });

        if (!html) {
            html = `<div class="spp-cmd-empty" style="grid-column: 1 / -1;">No shortcuts matching "${escapeHtml(filterQuery)}".</div>`;
        }

        cheatsheetBody.innerHTML = html;

        // Bind clicks on rows
        const rows = cheatsheetBody.querySelectorAll('.spp-shortcuts-row');
        rows.forEach(row => {
            row.addEventListener('click', () => {
                const kbd = row.getAttribute('data-kbd');
                const label = row.getAttribute('data-label');
                const view = row.getAttribute('data-view');

                let targetItem = null;
                for (const g of SHORTCUT_GROUPS) {
                    const found = g.items.find(i => i.label === label);
                    if (found) { targetItem = found; break; }
                }

                closeCheatsheet();
                if (targetItem && typeof targetItem.action === 'function') {
                    showShortcutToast(label, kbd);
                    targetItem.action();
                } else if (view) {
                    showShortcutToast(label, kbd);
                    navigateToView(view);
                }
            });
        });
    }

    function openCheatsheet() {
        closePalette();
        ensureCheatsheetDOM();
        if (!cheatsheetBackdrop) return;
        cheatsheetBackdrop.style.display = 'flex';
        if (cheatsheetSearchInput) cheatsheetSearchInput.value = '';
        renderCheatsheetGrid('');
        setTimeout(() => {
            if (cheatsheetSearchInput) cheatsheetSearchInput.focus();
        }, 50);
    }

    function closeCheatsheet() {
        if (!cheatsheetBackdrop) return;
        cheatsheetBackdrop.style.display = 'none';
        if (cheatsheetSearchInput) cheatsheetSearchInput.blur();
    }

    function toggleCheatsheet() {
        if (cheatsheetBackdrop && cheatsheetBackdrop.style.display === 'flex') {
            closeCheatsheet();
        } else {
            openCheatsheet();
        }
    }

    function ensurePaletteDOM() {
        if (document.getElementById('spp-cmd-palette-backdrop')) {
            paletteBackdrop = document.getElementById('spp-cmd-palette-backdrop');
            searchInput = document.getElementById('spp-cmd-palette-input');
            resultsContainer = document.getElementById('spp-cmd-palette-results');
            return;
        }

        const backdrop = document.createElement('div');
        backdrop.id = 'spp-cmd-palette-backdrop';
        backdrop.className = 'spp-cmd-backdrop';
        backdrop.style.display = 'none';
        backdrop.innerHTML = `
            <div class="spp-cmd-modal" onclick="event.stopPropagation()">
                <div class="spp-cmd-header">
                    <span class="spp-cmd-search-icon">🔍</span>
                    <input type="text" id="spp-cmd-palette-input" class="spp-cmd-input" placeholder="Type a command or search... (Press Esc to close)" autocomplete="off">
                    <kbd class="spp-cmd-esc-kbd">ESC</kbd>
                </div>
                <div id="spp-cmd-palette-results" class="spp-cmd-results"></div>
                <div class="spp-cmd-footer">
                    <span>⚡ SPP Developer Studio Command Palette</span>
                    <div class="spp-cmd-footer-keys">
                        <span class="spp-cmd-footer-key" id="spp-cmd-footer-cheatsheet-btn" style="cursor:pointer;" title="View all hotkeys"><kbd class="spp-cmd-kbd">Ctrl+/</kbd> Hotkeys Reference</span>
                        <span class="spp-cmd-footer-key"><kbd class="spp-cmd-kbd">↑</kbd><kbd class="spp-cmd-kbd">↓</kbd> Navigate</span>
                        <span class="spp-cmd-footer-key"><kbd class="spp-cmd-kbd">↵</kbd> Select</span>
                        <span class="spp-cmd-footer-key"><kbd class="spp-cmd-kbd">ESC</kbd> Close</span>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);

        paletteBackdrop = backdrop;
        searchInput = document.getElementById('spp-cmd-palette-input');
        resultsContainer = document.getElementById('spp-cmd-palette-results');

        // Close on clicking backdrop
        paletteBackdrop.addEventListener('click', function (e) {
            if (e.target === paletteBackdrop) {
                closePalette();
            }
        });

        // Click on footer Hotkeys button
        const footerCheatsheetBtn = document.getElementById('spp-cmd-footer-cheatsheet-btn');
        if (footerCheatsheetBtn) {
            footerCheatsheetBtn.addEventListener('click', function () {
                openCheatsheet();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(performSearch, 50);
            });

            searchInput.addEventListener('keydown', handleKeyNavigation);
        }
    }

    function initPalette() {
        ensurePaletteDOM();
    }

    function openPalette() {
        ensurePaletteDOM();
        if (!paletteBackdrop) return;

        paletteBackdrop.style.display = 'flex';
        activeIndex = -1;
        if (searchInput) {
            searchInput.value = '';
        }

        // Explicitly re-query resultsContainer in case DOM was rebuilt since init
        resultsContainer = document.getElementById('spp-cmd-palette-results');

        // Render initial results immediately (synchronous — DOM is already attached)
        performSearch();

        // Focus with slight delay (browser needs a frame for focus to take effect)
        setTimeout(() => {
            if (searchInput) searchInput.focus();
            // Safety net: If results are still empty after focus, re-render
            if (resultsContainer && !resultsContainer.querySelector('.spp-cmd-item')) {
                resultsContainer = document.getElementById('spp-cmd-palette-results');
                performSearch();
            }
        }, 30);
    }

    function closePalette() {
        if (!paletteBackdrop) return;
        paletteBackdrop.style.display = 'none';
        if (searchInput) searchInput.blur();
    }

    function togglePalette() {
        if (paletteBackdrop && paletteBackdrop.style.display === 'flex') {
            closePalette();
        } else {
            openPalette();
        }
    }

    // Comprehensive Built-in Commands Provider
    function getBuiltInCommands() {
        const commands = [
            // 0. UI Mode Switchers (Normal vs Expert)
            {
                id: 'mode_toggle_normal',
                title: 'Switch to Normal Mode (Site & App View)',
                subtitle: 'Streamlined workspace focused on domains, files, databases, and emails',
                category: '🚀 Quick Start',
                icon: '🌐',
                keywords: ['normal mode', 'simple mode', 'site mode', 'beginner mode', 'switch mode', 'cpanel mode', 'normal'],
                action: () => {
                    if (window.admin && typeof window.admin.setUIMode === 'function') {
                        window.admin.setUIMode('normal');
                    }
                }
            },
            {
                id: 'mode_toggle_expert',
                title: 'Switch to Expert Mode (Server & DevOps View)',
                subtitle: 'Complete sovereign server operations: Docker, UFW firewall, Watchdog & terminal',
                category: '🚀 Quick Start',
                icon: '⚡',
                keywords: ['expert mode', 'advanced mode', 'server mode', 'whm mode', 'devops mode', 'sysadmin mode', 'switch mode', 'expert'],
                action: () => {
                    if (window.admin && typeof window.admin.setUIMode === 'function') {
                        window.admin.setUIMode('expert');
                    }
                }
            },

            // 0. Quick Start & Common Tasks (Novice-First)
            {
                id: 'qs_add_website',
                title: 'Add / Host a Website or Domain',
                subtitle: 'Configure virtual hosts, Hybrid (Nginx+Apache) or standalone, and free SSL',
                category: '🚀 Quick Start',
                icon: '🌐',
                keywords: ['add website', 'add domain', 'create website', 'host website', 'nginx', 'apache', 'hybrid', 'ssl', 'vhost', 'new site', 'domain'],
                action: () => navigateToView('domains')
            },
            {
                id: 'qs_create_database',
                title: 'Create Database & Credentials',
                subtitle: 'Provision MySQL, PostgreSQL, or SQLite databases and manage user access',
                category: '🚀 Quick Start',
                icon: '🗄️',
                keywords: ['create database', 'add database', 'mysql', 'postgres', 'sqlite', 'db user', 'new database', 'sql', 'database'],
                action: () => navigateToView('database')
            },
            {
                id: 'qs_redis_cache',
                title: 'Manage Redis In-Memory Cache',
                subtitle: 'Check memory footprint, hit/miss ratios, key search & 1-click flush',
                category: '🚀 Quick Start',
                icon: '⚡',
                keywords: ['redis', 'cache', 'in-memory', 'flush cache', 'keys', 'resp', 'telemetry'],
                action: () => navigateToView('database')
            },
            {
                id: 'qs_firewall_ports',
                title: 'Open / Block Firewall Ports',
                subtitle: 'Manage inbound traffic rules (UFW/Netsh) for ports 80, 443, 22, 3306',
                category: '🚀 Quick Start',
                icon: '🔌',
                keywords: ['open port', 'firewall', 'allow port', 'block port', 'ufw', 'netsh', 'rules', 'port'],
                action: () => navigateToView('shield')
            },
            {
                id: 'qs_fail2ban_unban',
                title: 'Fail2ban Jails & Unban IP',
                subtitle: 'Inspect automated brute-force lockouts and release blocked IPs',
                category: '🚀 Quick Start',
                icon: '🛡️',
                keywords: ['fail2ban', 'unban', 'jail', 'banned ip', 'sshd', 'lockout', 'brute force'],
                action: () => navigateToView('shield')
            },
            {
                id: 'qs_ssh_hardening',
                title: 'SSH Daemon Security Hardening',
                subtitle: 'Safely change SSH port, disable root login & enforce SSH keys with sshd -t test',
                category: '🚀 Quick Start',
                icon: '🔐',
                keywords: ['ssh', 'ssh port', 'root login', 'harden', 'sshd', 'disable password', 'keys'],
                action: () => navigateToView('shield')
            },
            {
                id: 'qs_docker_containers',
                title: 'Docker Workstation & Containers',
                subtitle: 'Inspect running containers, start/stop/restart, stream logs & prune',
                category: '🚀 Quick Start',
                icon: '🐳',
                keywords: ['docker', 'container', 'containers', 'restart container', 'docker logs', 'prune'],
                action: () => navigateToView('server')
            },
            {
                id: 'qs_swap_memory',
                title: 'Allocate Virtual Swap Memory',
                subtitle: 'Create or resize swap space (1GB, 2GB, 4GB) to prevent Linux OOM crashes',
                category: '🚀 Quick Start',
                icon: '💾',
                keywords: ['swap', 'virtual memory', 'swapfile', 'oom', 'ram', 'memory'],
                action: () => navigateToView('server')
            },
            {
                id: 'qs_watchdog_alerts',
                title: 'Watchdog Self-Healing & Alerts',
                subtitle: 'Automated service auto-recovery & Telegram, Slack, Discord notifications',
                category: '🚀 Quick Start',
                icon: '🐕',
                keywords: ['watchdog', 'self healing', 'alerts', 'telegram', 'slack', 'discord', 'auto restart'],
                action: () => navigateToView('server')
            },
            {
                id: 'qs_upload_files',
                title: 'Upload Code & File Manager',
                subtitle: 'Browse files, upload ZIP archives with automatic unpack, and edit code',
                category: '🚀 Quick Start',
                icon: '📁',
                keywords: ['upload', 'files', 'file manager', 'zip', 'extract', 'code editor'],
                action: () => navigateToView('files')
            },
            {
                id: 'qs_web_terminal',
                title: 'Open Web Terminal',
                subtitle: 'Direct browser CLI shell with history, diagnostics & live command ticker',
                category: '🚀 Quick Start',
                icon: '⬛',
                keywords: ['terminal', 'cli', 'bash', 'shell', 'command', 'run test'],
                action: () => navigateToView('terminal')
            },

            // 1. Hosting & Sovereign Operations Views
            {
                id: 'nav_mail',
                title: 'Mail & Gateway (e-Vaarta)',
                subtitle: 'Sovereign email workstation, hosted domains, mailboxes & DKIM diagnostics',
                category: 'Hosting & Operations',
                icon: '📬',
                shortcut: 'Alt+M',
                keywords: ['mail', 'email', 'inbox', 'evaarta', 'gateway', 'smtp', 'imap', 'dkim', 'deliverability', 'messages'],
                action: () => navigateToView('mail')
            },
            {
                id: 'nav_domains',
                title: 'Virtual Hosts & Domains',
                subtitle: 'Domain mapping, vhosts, DNS zone editor & SSL certificates',
                category: 'Hosting & Operations',
                icon: '🌐',
                shortcut: 'Alt+D',
                keywords: ['domains', 'domain', 'vhost', 'virtual hosts', 'dns', 'ssl', 'routing'],
                action: () => navigateToView('domains')
            },
            {
                id: 'nav_files',
                title: 'File Manager & Storage',
                subtitle: 'Web file explorer, upload, archive extraction, FTP & disk usage',
                category: 'Hosting & Operations',
                icon: '📁',
                shortcut: 'Alt+E',
                keywords: ['files', 'file', 'manager', 'storage', 'explorer', 'upload', 'ftp', 'permissions', 'disk'],
                action: () => navigateToView('files')
            },
            {
                id: 'nav_database',
                title: 'Database & Storage',
                subtitle: 'Database management, SQL query console, table browser & backups',
                category: 'Hosting & Operations',
                icon: '🗄️',
                shortcut: 'Alt+B',
                keywords: ['database', 'db', 'sql', 'mysql', 'sqlite', 'tables', 'phpmyadmin', 'storage', 'xdb'],
                action: () => navigateToView('database')
            },
            {
                id: 'nav_terminal',
                title: 'Web Terminal & Shell',
                subtitle: 'Interactive web terminal, secure CLI shell execution & environment control',
                category: 'Hosting & Operations',
                icon: '⬛',
                shortcut: 'Alt+T',
                keywords: ['terminal', 'ssh', 'shell', 'bash', 'console', 'cli', 'cmd'],
                action: () => navigateToView('terminal')
            },
            {
                id: 'nav_deploy',
                title: 'Deployments & Backups',
                subtitle: 'Zero-downtime release pipelines, git deploy, rollback & full backups',
                category: 'Hosting & Operations',
                icon: '🚀',
                shortcut: 'Alt+R',
                keywords: ['deploy', 'deployment', 'backups', 'backup', 'rollback', 'git', 'release'],
                action: () => navigateToView('deploy')
            },
            {
                id: 'nav_fleet',
                title: 'Multi-Server Fleet & DNS Cluster',
                subtitle: 'Multi-server fleet orchestrator, BIND DNS master-secondary replication & node health',
                category: 'Hosting & Operations',
                icon: '🌐',
                shortcut: 'Alt+F',
                keywords: ['fleet', 'whm', 'cluster', 'nodes', 'bind', 'dns', 'replication', 'multi-server', 'orchestrator', 'nameservers'],
                action: () => navigateToView('fleet')
            },
            {
                id: 'nav_cron',
                title: 'Scheduled Tasks & Cron',
                subtitle: 'Cron job scheduler, recurring tasks, daemon workers & queue orchestrator',
                category: 'Hosting & Operations',
                icon: '⏱️',
                shortcut: 'Alt+C',
                keywords: ['cron', 'jobs', 'scheduled', 'scheduler', 'tasks', 'queue', 'workers', 'dag'],
                action: () => navigateToView('cron')
            },
            {
                id: 'nav_shield',
                title: 'Security & Firewall',
                subtitle: 'Web Application Firewall (WAF), IP blocking, rate limits & SSL/TLS protection',
                category: 'Hosting & Operations',
                icon: '🛡️',
                shortcut: 'Alt+S',
                keywords: ['shield', 'security', 'firewall', 'ip', 'blocker', 'modsecurity', 'ssl', 'tls', 'imunify'],
                action: () => navigateToView('shield')
            },
            {
                id: 'nav_metrics',
                title: 'Metrics & Analytics',
                subtitle: 'Server bandwidth metrics, HTTP traffic, access logs & error diagnostics',
                category: 'Hosting & Operations',
                icon: '📊',
                shortcut: 'Alt+L',
                keywords: ['metrics', 'analytics', 'bandwidth', 'traffic', 'stats', 'visitors', 'awstats', 'logs'],
                action: () => navigateToView('metrics')
            },
            {
                id: 'nav_server',
                title: 'Server & Process Workstation',
                subtitle: 'Live CPU/RAM load, active process manager, service restart & hardware health',
                category: 'Hosting & Operations',
                icon: '🖥️',
                keywords: ['server', 'process', 'cpu', 'memory', 'ram', 'services', 'status', 'restart', 'hardware'],
                action: () => navigateToView('server')
            },
            {
                id: 'nav_runtime',
                title: 'PHP & Runtime Environments',
                subtitle: 'Multi-PHP version switcher, php.ini configuration, extensions & modules',
                category: 'Hosting & Operations',
                icon: '⚙️',
                keywords: ['runtime', 'php', 'version', 'modules', 'ini', 'extensions', 'node', 'python'],
                action: () => navigateToView('runtime')
            },
            {
                id: 'nav_apptoolkit',
                title: 'CMS & App Marketplace',
                subtitle: 'One-click application installer (WordPress, Laravel, Drupal) & scripts',
                category: 'Hosting & Operations',
                icon: '📦',
                keywords: ['apptoolkit', 'apps', 'wordpress', 'cms', 'marketplace', 'softaculous', 'install'],
                action: () => navigateToView('apptoolkit')
            },
            {
                id: 'nav_reseller',
                title: 'Resellers & Multi-Tenancy',
                subtitle: 'Reseller accounts, tenant management, hosting packages & resource quotas',
                category: 'Hosting & Operations',
                icon: '👥',
                keywords: ['reseller', 'accounts', 'tenants', 'packages', 'whm', 'multi-tenancy', 'quotas'],
                action: () => navigateToView('reseller')
            },
            {
                id: 'nav_identity',
                title: 'Identity & Access (IAM)',
                subtitle: 'User management, RBAC role assignments, MFA/2FA & security audit log',
                category: 'Hosting & Operations',
                icon: '🛡️',
                shortcut: 'Alt+I',
                keywords: ['identity', 'iam', 'users', 'roles', 'permissions', '2fa', 'mfa', 'rbac', 'access', 'passkeys', 'webauthn', 'fido2'],
                action: () => navigateToView('identity')
            },
            {
                id: 'nav_api_keys',
                title: 'API Keys & Integrations',
                subtitle: 'API keys, webhook tokens, third-party credentials & rate limits',
                category: 'Hosting & Operations',
                icon: '🔑',
                keywords: ['api_keys', 'api keys', 'tokens', 'credentials', 'integrations', 'webhooks'],
                action: () => navigateToView('api_keys')
            },
            {
                id: 'nav_apm',
                title: 'Native In-Panel APM & Flame Graphs',
                subtitle: 'Zero-SaaS in-panel APM, Speedscope flame graphs, latency P95/P99 & bottleneck traces',
                category: 'Hosting & Operations',
                icon: '🔥',
                shortcut: 'Alt+P',
                keywords: ['apm', 'flame', 'flamegraph', 'speedscope', 'latency', 'p95', 'p99', 'profiler', 'spans', 'transactions', 'bottlenecks', 'performance'],
                action: () => navigateToView('apm')
            },
            {
                id: 'nav_system',
                title: 'Observability & Diagnostics',
                subtitle: 'Core system observability, error traces, health checks & telemetry',
                category: 'Hosting & Operations',
                icon: '🖥️',
                shortcut: 'Alt+O',
                keywords: ['system', 'observability', 'diagnostics', 'health', 'logs', 'telemetry'],
                action: () => navigateToView('system')
            },
            {
                id: 'nav_webhooks',
                title: 'Webhook Relay & Tunnel (ngrok Killer)',
                subtitle: 'Sovereign inbound webhook relay, secure tunnel, HMAC signature verifier & payload replay inspector',
                category: 'Hosting & Operations',
                icon: '⚡',
                shortcut: 'Alt+W',
                keywords: ['webhooks', 'relay', 'tunnel', 'ngrok', 'stripe', 'github', 'shopify', 'payloads', 'hmac', 'signatures', 'inspect', 'replay'],
                action: () => navigateToView('webhooks')
            },
            {
                id: 'nav_apps',
                title: 'Applications & Modules',
                subtitle: 'Multi-application workspace manager, module dependencies & marketplace',
                category: 'Hosting & Operations',
                icon: '📱',
                keywords: ['apps', 'applications', 'modules', 'multi-app', 'tenant', 'packages'],
                action: () => navigateToView('apps')
            },
            {
                id: 'nav_manifests',
                title: 'Manifest OS & Graph',
                subtitle: 'System capability graph, module manifests & dependency topology',
                category: 'Hosting & Operations',
                icon: '🛡️',
                keywords: ['manifests', 'manifest os', 'graph', 'topology', 'dependencies'],
                action: () => navigateToView('manifests')
            },

            // 2. Developer Studio & App Architecture Views
            {
                id: 'nav_dashboard',
                title: 'Studio Dashboard',
                subtitle: 'Developer Studio Hub, telemetry & ecosystem overview',
                category: 'Developer Studio',
                icon: '👋',
                shortcut: 'Alt+0',
                keywords: ['dashboard', 'home', 'hub', 'welcome', 'telemetry', 'overview'],
                action: () => navigateToView('dashboard')
            },
            {
                id: 'nav_coder',
                title: 'Code Studio & Scaffolder',
                subtitle: 'In-browser IDE, code explorer, generator & manuals',
                category: 'Developer Studio',
                icon: '💻',
                shortcut: 'Alt+K',
                keywords: ['code', 'coder', 'studio', 'ide', 'scaffolder', 'files', 'editor'],
                action: () => navigateToView('coder')
            },
            {
                id: 'nav_tinker',
                title: 'Web Tinker REPL',
                subtitle: 'Interactive PHP Shell & Expression REPL sandbox',
                category: 'Developer Studio',
                icon: '💬',
                shortcut: 'Ctrl+`',
                keywords: ['tinker', 'repl', 'php', 'eval', 'execute', 'console', 'sandbox'],
                action: () => navigateToView('tinker')
            },
            {
                id: 'nav_pages',
                title: 'SPP Studio (Visual Builder)',
                subtitle: 'Visual App Lifecycle, Canvas, Split View & HTMX Builder',
                category: 'Developer Studio',
                icon: '📐',
                shortcut: 'Alt+V',
                keywords: ['pages', 'studio', 'visual', 'builder', 'designer', 'canvas', 'htmx'],
                action: () => navigateToView('pages')
            },
            {
                id: 'nav_workflows',
                title: 'Workflows & State Machines',
                subtitle: 'Visual Workflow Designer, Saga orchestrator & SLA timeouts',
                category: 'Developer Studio',
                icon: '🔄',
                shortcut: 'Ctrl+Shift+S',
                keywords: ['workflows', 'workflow', 'states', 'transitions', 'saga', 'sla', 'status'],
                action: () => navigateToView('workflows')
            },
            {
                id: 'nav_entities',
                title: 'Entities & Schemas',
                subtitle: 'Entity Architect, SQL Migrations & NoSQL XDB Indexer',
                category: 'Developer Studio',
                icon: '🏗️',
                keywords: ['entities', 'entity', 'schemas', 'models', 'database', 'tables', 'xdb', 'orm'],
                action: () => navigateToView('entities')
            },
            {
                id: 'nav_forms',
                title: 'Forms & Locales',
                subtitle: 'Visual Form Builder, validation engine & translations',
                category: 'Developer Studio',
                icon: '📝',
                keywords: ['forms', 'form', 'locales', 'i18n', 'validation', 'fields'],
                action: () => navigateToView('forms')
            },
            {
                id: 'nav_routing',
                title: 'Routing & Endpoints',
                subtitle: 'Routing Table, Middleware pipeline & Polyglot Services',
                category: 'Developer Studio',
                icon: '🛤️',
                keywords: ['routing', 'routes', 'endpoints', 'middleware', 'urls', 'services'],
                action: () => navigateToView('routing')
            },
            {
                id: 'nav_reports',
                title: 'Reports & Analytics',
                subtitle: 'Interactive Visual Report Studio, Pivot OLAP & BI dashboards',
                category: 'Developer Studio',
                icon: '📊',
                keywords: ['reports', 'analytics', 'charts', 'olap', 'bi', 'pivot', 'sql'],
                action: () => navigateToView('reports')
            },
            {
                id: 'nav_ai',
                title: 'AI Copilot & Playground',
                subtitle: 'AI Copilot, Prompt Playground & SPPAI Gateway',
                category: 'Developer Studio',
                icon: '🤖',
                keywords: ['ai', 'copilot', 'playground', 'prompt', 'assistant', 'sppai', 'llm'],
                action: () => navigateToView('ai')
            },
            {
                id: 'nav_mesh',
                title: 'Integration Mesh',
                subtitle: 'Integration Container Mesh & RPC Services Sandbox',
                category: 'Developer Studio',
                icon: '🕸️',
                keywords: ['mesh', 'integration', 'rpc', 'services', 'container'],
                action: () => navigateToView('mesh')
            },
            {
                id: 'nav_polyglot',
                title: 'Universal Polyglot Studio',
                subtitle: '47 Language runtimes, activation toggles, live playground & compilers',
                category: 'Developer Studio',
                icon: '🌐',
                shortcut: 'Ctrl+Shift+P',
                keywords: ['polyglot', 'python', 'node', 'rust', 'formula', 'dsl', 'languages', 'runtimes', 'compilers', 'toggle'],
                action: () => navigateToView('polyglot')
            },
            {
                id: 'nav_testing',
                title: 'Parikshak Test Studio',
                subtitle: 'Automated Parikshak test runner, coverage & event traces',
                category: 'Developer Studio',
                icon: '🧪',
                shortcut: 'Ctrl+Shift+U',
                keywords: ['testing', 'parikshak', 'tests', 'unit', 'suite', 'runner', 'coverage'],
                action: () => navigateToView('testing')
            },
            {
                id: 'nav_commands',
                title: 'CLI Command Center',
                subtitle: 'Interactive CLI Console, Command Runner & Job Scheduler',
                category: 'Developer Studio',
                icon: '⚡',
                keywords: ['commands', 'cli', 'console', 'terminal', 'artisan', 'runner', 'spp'],
                action: () => navigateToView('commands')
            },
            {
                id: 'nav_apistudio',
                title: 'API Studio & OpenAPI 3.1 Workbench',
                subtitle: 'Interactive polyglot API client, OpenAPI 3.1 generator, latency benchmark & curl runner',
                category: 'Developer Studio',
                icon: '🚀',
                shortcut: 'Alt+A',
                keywords: ['apistudio', 'api studio', 'openapi', 'swagger', 'postman', 'insomnia', 'rest', 'endpoints', 'latency', 'curl', 'benchmark'],
                action: () => navigateToView('apistudio')
            },
            {
                id: 'action_dev_seeders',
                title: 'Database: Seeders & Mock Fixtures Studio',
                subtitle: 'Generate synthetic domain fixtures, workflow states & run seeders',
                category: 'Developer Studio',
                icon: '🌱',
                keywords: ['seeders', 'seeder', 'fixtures', 'mock', 'database', 'fake', 'data', 'generate'],
                action: () => {
                    navigateToView('database');
                    setTimeout(() => {
                        if (window.databaseViewInstance?.switchTab) window.databaseViewInstance.switchTab('seeders');
                    }, 100);
                }
            },
            {
                id: 'action_dev_ai_recipes',
                title: 'AI Studio: Enterprise Architectural Recipes',
                subtitle: 'Browse verified, copy-pasteable enterprise patterns (AI Tools, CQRS, DAG, Indexing)',
                category: 'Developer Studio',
                icon: '📐',
                keywords: ['recipes', 'patterns', 'ai', 'enterprise', 'cqrs', 'dag', 'snippets', 'code'],
                action: () => {
                    navigateToView('ai');
                    setTimeout(() => {
                        const view = window.admin?.currentViewInstance || window.aiViewInstance;
                        if (view?.setState) {
                            view.setState({ activeTab: 'recipes' });
                            if (view.state.recipes.length === 0) view.loadRecipes();
                        }
                    }, 100);
                }
            },
            {
                id: 'action_dev_ai_linter',
                title: 'AI Studio: Golden Rules Architectural Linter',
                subtitle: 'Scan codebase for the 10 Golden Invariants (Zero Inline HTML, CLI Guards, Workflow APIs)',
                category: 'Developer Studio',
                icon: '🛡️',
                keywords: ['linter', 'audit', 'golden rules', 'ast', 'compliance', 'rules', 'quality', 'scan'],
                action: () => {
                    navigateToView('ai');
                    setTimeout(() => {
                        const view = window.admin?.currentViewInstance || window.aiViewInstance;
                        if (view?.setState) view.setState({ activeTab: 'linter' });
                    }, 100);
                }
            },
            {
                id: 'action_dev_mesh_events',
                title: 'Integration Mesh: Event Bus & Hooks Sandbox',
                subtitle: 'Inspect registered listeners and dispatch synthetic events across dual event bus',
                category: 'Developer Studio',
                icon: '⚡',
                keywords: ['events', 'event bus', 'hooks', 'listeners', 'subscribers', 'dispatch', 'mesh'],
                action: () => {
                    navigateToView('mesh');
                    setTimeout(() => {
                        const view = window.admin?.currentViewInstance || window.meshViewInstance;
                        if (view?.switchTab) view.switchTab('events');
                    }, 100);
                }
            },
            {
                id: 'action_dev_component_playground',
                title: 'Drishyam Studio: Component & Partial Playground',
                subtitle: 'Live isolated preview of external partials with mock JSON props (zero inline HTML)',
                category: 'Developer Studio',
                icon: '🧩',
                keywords: ['playground', 'components', 'partials', 'preview', 'props', 'blade', 'drishyam', 'pages'],
                action: () => {
                    navigateToView('pages');
                    setTimeout(() => {
                        const view = window.admin?.currentViewInstance || window.pagesViewInstance;
                        if (view?.switchStudioModeTab) view.switchStudioModeTab('playground');
                    }, 100);
                }
            },

            // 3. e-Vaarta Sovereign Mail Operations
            {
                id: 'evaarta_compose',
                title: 'Compose New Email',
                subtitle: 'Open e-Vaarta rich composer modal',
                category: 'e-Vaarta Sovereign Mail',
                icon: '✏️',
                shortcut: 'c',
                keywords: ['compose', 'new', 'mail', 'email', 'write', 'send'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'mail' && window.admin?.currentView !== 'mail') {
                        navigateToView('mail');
                        setTimeout(() => {
                            if (window.mailViewInstance?.openComposer) window.mailViewInstance.openComposer();
                        }, 300);
                    } else {
                        if (window.mailViewInstance?.openComposer) window.mailViewInstance.openComposer();
                    }
                }
            },
            {
                id: 'evaarta_sync',
                title: 'Sync Mailbox (Quick 50)',
                subtitle: 'Fetch latest emails immediately from configured mail server',
                category: 'e-Vaarta Sovereign Mail',
                icon: '🔄',
                keywords: ['sync', 'fetch', 'receive', 'refresh', 'inbox', 'check mail'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'mail' && window.admin?.currentView !== 'mail') {
                        navigateToView('mail');
                        setTimeout(() => {
                            if (window.mailViewInstance?.syncCurrentAccount) window.mailViewInstance.syncCurrentAccount(50);
                        }, 300);
                    } else {
                        if (window.mailViewInstance?.syncCurrentAccount) window.mailViewInstance.syncCurrentAccount(50);
                    }
                }
            },
            {
                id: 'evaarta_filter_starred',
                title: 'Show Starred Conversations',
                subtitle: 'Filter e-Vaarta inbox to starred/important threads',
                category: 'e-Vaarta Sovereign Mail',
                icon: '⭐',
                keywords: ['starred', 'star', 'favorites', 'flagged', 'important'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'mail' && window.admin?.currentView !== 'mail') {
                        navigateToView('mail');
                        setTimeout(() => {
                            if (window.mailViewInstance) {
                                window.mailViewInstance.setState({ filterStarred: true, filterUnread: false, filterNeedsAction: false, filterHasAttachment: false, filterP0: false });
                                window.mailViewInstance.fetchInbox(1, false);
                            }
                        }, 300);
                    } else if (window.mailViewInstance) {
                        window.mailViewInstance.setState({ filterStarred: !window.mailViewInstance.state.filterStarred });
                        window.mailViewInstance.fetchInbox(1, false);
                    }
                }
            },
            {
                id: 'evaarta_filter_unread',
                title: 'Show Unread Conversations',
                subtitle: 'Filter e-Vaarta inbox to unread messages only',
                category: 'e-Vaarta Sovereign Mail',
                icon: '✉️',
                keywords: ['unread', 'new', 'unread mail', 'inbox'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'mail' && window.admin?.currentView !== 'mail') {
                        navigateToView('mail');
                        setTimeout(() => {
                            if (window.mailViewInstance) {
                                window.mailViewInstance.setState({ filterUnread: true, filterStarred: false, filterNeedsAction: false, filterHasAttachment: false, filterP0: false });
                                window.mailViewInstance.fetchInbox(1, false);
                            }
                        }, 300);
                    } else if (window.mailViewInstance) {
                        window.mailViewInstance.setState({ filterUnread: !window.mailViewInstance.state.filterUnread });
                        window.mailViewInstance.fetchInbox(1, false);
                    }
                }
            },
            {
                id: 'evaarta_mailboxes',
                title: 'Server Mailboxes & Forwarders',
                subtitle: 'Manage local domain mailboxes, virtual email accounts & quotas',
                category: 'e-Vaarta Sovereign Mail',
                icon: '📬',
                keywords: ['mailboxes', 'accounts', 'forwarders', 'server mail', 'email quota', 'domains'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'mail' && window.admin?.currentView !== 'mail') {
                        navigateToView('mail');
                        setTimeout(() => {
                            if (window.mailViewInstance?.switchTab) window.mailViewInstance.switchTab('accounts');
                        }, 300);
                    } else if (window.mailViewInstance?.switchTab) {
                        window.mailViewInstance.switchTab('accounts');
                    }
                }
            },
            {
                id: 'evaarta_deliverability',
                title: 'Deliverability & DKIM Diagnostics',
                subtitle: 'Inspect SPF, DKIM 2048-bit, DMARC alignment and email health score',
                category: 'e-Vaarta Sovereign Mail',
                icon: '🛡️',
                keywords: ['deliverability', 'dkim', 'spf', 'dmarc', 'audit', 'score', 'smtp test'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'mail' && window.admin?.currentView !== 'mail') {
                        navigateToView('mail');
                        setTimeout(() => {
                            if (window.mailViewInstance?.switchTab) window.mailViewInstance.switchTab('deliverability');
                        }, 300);
                    } else if (window.mailViewInstance?.switchTab) {
                        window.mailViewInstance.switchTab('deliverability');
                    }
                }
            },
            {
                id: 'evaarta_shortcuts',
                title: 'e-Vaarta Keyboard Shortcuts',
                subtitle: 'Show executive keyboard bindings modal (j/k, e, s, u, b, #, r, c)',
                category: 'e-Vaarta Sovereign Mail',
                icon: '⌨️',
                shortcut: '?',
                keywords: ['shortcuts', 'keys', 'hotkeys', 'cheatsheet', 'mail shortcuts'],
                action: () => {
                    closePalette();
                    if (window.mailViewInstance?.openShortcutsModal) {
                        window.mailViewInstance.openShortcutsModal();
                    }
                }
            },

            // 4. Quick Actions
            {
                id: 'act_run_tests',
                title: 'Run Parikshak Automated Tests',
                subtitle: 'Execute full test suite for current active application context',
                category: 'Quick Actions',
                icon: '🧪',
                shortcut: 'Ctrl+Shift+U',
                keywords: ['run', 'tests', 'parikshak', 'test', 'suite', 'unit'],
                action: () => {
                    if (window.admin?.viewInstances?.pages?.runParikshakStudio) {
                        window.admin.viewInstances.pages.setState({ bottomDrawerOpen: true, bottomDrawerTab: 'parikshak' });
                        window.admin.viewInstances.pages.runParikshakStudio();
                    } else {
                        navigateToView('testing');
                    }
                }
            },
            {
                id: 'act_sync_dns',
                title: '1-Click BIND DNS Zone Cluster Sync',
                subtitle: 'Broadcast and synchronize authoritative DNS zones across all secondary fleet nodes',
                category: 'Quick Actions',
                icon: '🌐',
                shortcut: 'Ctrl+Shift+D',
                keywords: ['dns', 'bind', 'sync', 'cluster', 'zone', 'broadcast', 'fleet', 'nameservers'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'fleet' && window.admin?.currentView !== 'fleet') {
                        navigateToView('fleet');
                        setTimeout(() => {
                            if (window.admin?.viewInstances?.fleet?.syncCluster) {
                                window.admin.viewInstances.fleet.syncCluster();
                            }
                        }, 300);
                    } else if (window.admin?.viewInstances?.fleet?.syncCluster) {
                        window.admin.viewInstances.fleet.syncCluster();
                    }
                }
            },
            {
                id: 'act_webauthn_passkeys',
                title: 'Manage FIDO2 Hardware Passkeys & Biometrics',
                subtitle: 'Register or review WebAuthn security keys, Touch ID / Windows Hello credentials',
                category: 'Quick Actions',
                icon: '🛡️',
                shortcut: 'Ctrl+Shift+K',
                keywords: ['passkey', 'passkeys', 'fido2', 'webauthn', 'hardware key', 'biometrics', 'touch id', 'windows hello', 'yubikey', 'mfa', 'iam', 'identity'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'identity' && window.admin?.currentView !== 'identity') {
                        navigateToView('identity');
                        setTimeout(() => {
                            if (window.admin?.viewInstances?.identity?.switchTab) {
                                window.admin.viewInstances.identity.switchTab('passkeys');
                            }
                        }, 300);
                    } else if (window.admin?.viewInstances?.identity?.switchTab) {
                        window.admin.viewInstances.identity.switchTab('passkeys');
                    }
                }
            },
            {
                id: 'act_openapi_export',
                title: 'Export OpenAPI 3.1 Specification (JSON/YAML)',
                subtitle: 'Generate and export complete OpenAPI 3.1 specification for active endpoints',
                category: 'Quick Actions',
                icon: '📄',
                shortcut: 'Ctrl+Shift+O',
                keywords: ['openapi', 'swagger', 'export', 'spec', 'json', 'yaml', 'schema', 'api', 'apistudio'],
                action: () => {
                    closePalette();
                    if (window.admin?.activeView !== 'apistudio' && window.admin?.currentView !== 'apistudio') {
                        navigateToView('apistudio');
                        setTimeout(() => {
                            if (window.admin?.viewInstances?.apistudio?.exportSpec) {
                                window.admin.viewInstances.apistudio.exportSpec('json');
                            }
                        }, 300);
                    } else if (window.admin?.viewInstances?.apistudio?.exportSpec) {
                        window.admin.viewInstances.apistudio.exportSpec('json');
                    }
                }
            },
            {
                id: 'act_webhook_replay',
                title: 'Webhook Relay: Inspect & Replay Last Event',
                subtitle: 'View inbound webhook payload stream or replay last payload against local handler',
                category: 'Quick Actions',
                icon: '⚡',
                shortcut: 'Ctrl+Shift+W',
                keywords: ['webhook', 'replay', 'payload', 'inspect', 'tunnel', 'retry', 'ngrok'],
                action: () => {
                    closePalette();
                    navigateToView('webhooks');
                    setTimeout(() => {
                        if (window.admin?.viewInstances?.webhooks?.refreshDeliveries) {
                            window.admin.viewInstances.webhooks.refreshDeliveries();
                        }
                    }, 300);
                }
            },
            {
                id: 'act_simulate_workflow',
                title: 'Simulate Workflow & Saga Compensation',
                subtitle: 'Interactive dry-run simulator for states, guards and saga rollback compensations',
                category: 'Quick Actions',
                icon: '🔄',
                shortcut: 'Ctrl+Shift+S',
                keywords: ['workflow', 'simulate', 'saga', 'rollback', 'compensation', 'state machine', 'transitions'],
                action: () => {
                    closePalette();
                    navigateToView('workflows');
                    setTimeout(() => {
                        if (window.admin?.viewInstances?.workflows?.openSimulationModal) {
                            window.admin.viewInstances.workflows.openSimulationModal();
                        }
                    }, 300);
                }
            },
            {
                id: 'act_apm_flamegraph',
                title: 'View APM Flame Graph & Hotspot Traces',
                subtitle: 'Analyze execution bottlenecks, slow SQL queries, and P99 latency spikes',
                category: 'Quick Actions',
                icon: '🔥',
                shortcut: 'Ctrl+Shift+F',
                keywords: ['apm', 'flame', 'flamegraph', 'hotspot', 'profile', 'latency', 'bottleneck', 'traces'],
                action: () => {
                    closePalette();
                    navigateToView('apm');
                }
            },
            {
                id: 'act_spy_pixel_shield',
                title: 'e-Vaarta Privacy Shield: Neutralize Tracking Pixels',
                subtitle: 'Inspect and disarm hidden 1x1 surveillance web beacons and sender telemetry in mail',
                category: 'e-Vaarta Sovereign Mail',
                icon: '🛡️',
                shortcut: 'Ctrl+Shift+E',
                keywords: ['pixel', 'spy', 'tracking', 'privacy', 'shield', 'surveillance', 'mail', 'beacon'],
                action: () => {
                    closePalette();
                    navigateToView('mail');
                }
            },
            {
                id: 'act_ai_copilot',
                title: 'Inline AI Copilot & Code Generation',
                subtitle: 'Ask AI assistant for code refactoring, scaffolding, or explanations',
                category: 'Quick Actions',
                icon: '✨',
                shortcut: 'Ctrl+I',
                keywords: ['ai', 'copilot', 'generate', 'code', 'prompt', 'ask'],
                action: () => {
                    if (window.admin?.viewInstances?.pages?.toggleAiCopilot) {
                        window.admin.viewInstances.pages.toggleAiCopilot();
                    } else {
                        navigateToView('ai');
                    }
                }
            },
            {
                id: 'act_open_tinker',
                title: 'Open Web Tinker Sandbox',
                subtitle: 'Execute arbitrary PHP expressions against framework active context',
                category: 'Quick Actions',
                icon: '💬',
                shortcut: 'Ctrl+`',
                keywords: ['tinker', 'repl', 'eval', 'php', 'sandbox'],
                action: () => {
                    if (window.admin?.viewInstances?.pages?.toggleBottomDrawer) {
                        window.admin.viewInstances.pages.setState({ bottomDrawerOpen: true, bottomDrawerTab: 'tinker' });
                    } else {
                        navigateToView('tinker');
                    }
                }
            },
            {
                id: 'act_purge_cache',
                title: 'Fresh Canvas: Purge All Caches & Reload',
                subtitle: 'Clear Drishyam, view templates, entity schema caches and refresh',
                category: 'Quick Actions',
                icon: '🔄',
                shortcut: 'Ctrl+Shift+C',
                keywords: ['purge', 'cache', 'clear', 'fresh', 'reload', 'canvas'],
                action: () => {
                    if (window.admin?.viewInstances?.pages?.purgeStudioCache) {
                        window.admin.viewInstances.pages.purgeStudioCache();
                    } else if (window.admin?.refreshView) {
                        window.admin.refreshView();
                    } else {
                        location.reload();
                    }
                }
            },
            {
                id: 'act_shortcuts_help',
                title: 'Universal Keyboard Shortcuts Cheatsheet',
                subtitle: 'Interactive hotkey matrix and accelerator reference across all views and studio tools',
                category: 'Quick Actions',
                icon: '⌨️',
                shortcut: 'Ctrl+/',
                keywords: ['shortcuts', 'keys', 'hotkeys', 'cheatsheet', 'accelerators', 'help', 'keyboard', 'ctrl+k', '?'],
                action: () => openCheatsheet()
            },

            // 5. Theme & Appearance
            {
                id: 'theme_night',
                title: 'Switch Theme: Night Mode (Dark)',
                subtitle: 'Sleek dark glassmorphic interface for low-light environments',
                category: 'Appearance',
                icon: '🌙',
                keywords: ['theme', 'night', 'dark', 'mode', 'black', 'appearance'],
                action: () => { window.admin?.setTheme ? window.admin.setTheme('night') : setThemeLocal('night'); }
            },
            {
                id: 'theme_day',
                title: 'Switch Theme: Light Mode (Day)',
                subtitle: 'High-contrast clean light mode for bright workspaces',
                category: 'Appearance',
                icon: '☀️',
                keywords: ['theme', 'day', 'light', 'mode', 'white', 'appearance'],
                action: () => { window.admin?.setTheme ? window.admin.setTheme('day') : setThemeLocal('day'); }
            },
            {
                id: 'theme_saffron',
                title: 'Switch Theme: Saffron Mode (Sunrise)',
                subtitle: 'Warm rich saffron aesthetics inspired by classical Indian design',
                category: 'Appearance',
                icon: '🌅',
                keywords: ['theme', 'saffron', 'sunrise', 'orange', 'warm', 'mode', 'appearance'],
                action: () => { window.admin?.setTheme ? window.admin.setTheme('saffron') : setThemeLocal('saffron'); }
            }
        ];

        // 6. Dynamic Application Switcher Items
        try {
            const availableApps = window.admin?.availableApps || [];
            if (Array.isArray(availableApps) && availableApps.length > 0) {
                availableApps.forEach(app => {
                    const name = String(typeof app === 'string' ? app : (app?.name || '')).trim();
                    const title = String(typeof app === 'string' ? app : (app?.title || app?.name || '')).trim();
                    const icon = (typeof app === 'object' && app?.icon) ? app.icon : '📱';
                    if (!name) return;

                    const displayTitle = title ? (title.charAt(0).toUpperCase() + title.slice(1)) : (name.charAt(0).toUpperCase() + name.slice(1));

                    commands.push({
                        id: `app_switch_${name}`,
                        title: `Switch Context to: ${displayTitle}`,
                        subtitle: `Switch active application workspace, schemas, workflows and routes to '${name}'`,
                        category: 'Switch Application',
                        icon: icon,
                        keywords: ['switch', 'app', 'application', 'context', name, title],
                        action: () => {
                            if (window.admin?.switchApp) {
                                window.admin.switchApp(name);
                            }
                        }
                    });
                });
            }
        } catch (e) {
            console.warn('[SPPCommandPalette] Could not parse availableApps:', e);
        }

        // 7. Context-Aware Studio Commands (if currently viewing SPP Studio)
        const currentHash = (window.location.hash || '').replace('#', '');
        const pagesInstance = window.admin?.viewInstances?.pages;
        if (currentHash === 'pages' && pagesInstance) {
            commands.push(
                {
                    id: 'studio_canvas',
                    title: 'Studio: Switch to Visual Canvas',
                    subtitle: 'WYSIWYG live editing canvas with component inspector',
                    category: 'SPP Studio',
                    icon: '🎨',
                    shortcut: 'Alt+1',
                    keywords: ['canvas', 'visual', 'wysiwyg', 'edit'],
                    action: () => pagesInstance.setStudioMode('canvas')
                },
                {
                    id: 'studio_split',
                    title: 'Studio: Switch to Live Split View',
                    subtitle: 'Side-by-side interactive visual canvas and real-time code editor',
                    category: 'SPP Studio',
                    icon: '⚡',
                    shortcut: 'Alt+2',
                    keywords: ['split', 'code', 'canvas', 'live'],
                    action: () => pagesInstance.setStudioMode('split')
                },
                {
                    id: 'studio_code',
                    title: 'Studio: Switch to Code Studio',
                    subtitle: 'Full-page syntax highlighted Blade / PHP source code editor',
                    category: 'SPP Studio',
                    icon: '💻',
                    shortcut: 'Alt+3',
                    keywords: ['code', 'source', 'editor', 'blade'],
                    action: () => pagesInstance.setStudioMode('code')
                },
                {
                    id: 'studio_db',
                    title: 'Studio: Switch to Database & CRUD Studio',
                    subtitle: 'ERD relationship canvas, schema migrations & table browser',
                    category: 'SPP Studio',
                    icon: '🗄️',
                    shortcut: 'Alt+4',
                    keywords: ['database', 'crud', 'erd', 'schema', 'sql'],
                    action: () => pagesInstance.setStudioMode('database')
                },
                {
                    id: 'studio_api',
                    title: 'Studio: Switch to API Tester',
                    subtitle: 'Interactive polyglot API request runner & contract inspector',
                    category: 'SPP Studio',
                    icon: '🔌',
                    shortcut: 'Alt+5',
                    keywords: ['api', 'tester', 'rest', 'http', 'request'],
                    action: () => pagesInstance.setStudioMode('api')
                },
                {
                    id: 'studio_page_health',
                    title: 'Studio: Run Page Health Smoke Test & Auto-Fix',
                    subtitle: 'Validate DOM nesting, orphan partials, missing CSS and repair',
                    category: 'SPP Studio',
                    icon: '🩺',
                    shortcut: 'Ctrl+Shift+H',
                    keywords: ['health', 'smoke', 'test', 'fix', 'audit'],
                    action: () => pagesInstance.checkPageHealth()
                }
            );
        }

        // 7. Dynamic File Manager Directory Favorites
        try {
            const cachedFavs = localStorage.getItem('spp_fm_favorites');
            if (cachedFavs) {
                const favsList = JSON.parse(cachedFavs);
                if (Array.isArray(favsList)) {
                    favsList.forEach(fav => {
                        if (!fav || !fav.path) return;
                        commands.push({
                            id: 'fav_jump_' + (fav.id || fav.path),
                            title: 'Jump to Favorite: ' + (fav.label || fav.path),
                            subtitle: 'Open ' + fav.path + ' in File Manager',
                            category: 'File Manager Favorites',
                            icon: fav.icon || '⭐',
                            keywords: ['favorite', 'folder', 'directory', 'files', (fav.label || '').toLowerCase(), (fav.path || '').toLowerCase()],
                            action: () => {
                                window.location.hash = '#files?path=' + encodeURIComponent(fav.path);
                                if (window.filesViewInstance) {
                                    window.filesViewInstance.navigateTo(fav.path);
                                }
                            }
                        });
                    });
                }
            }
        } catch (e) {}

        return commands.concat(customCommands);
    }

    function navigateToView(view) {
        closePalette();
        const targetHash = `#${view}`;
        if (window.location.hash === targetHash) {
            if (window.admin && typeof window.admin.loadView === 'function') {
                window.admin.loadView(view);
            }
        } else {
            window.location.hash = targetHash;
        }

        document.querySelectorAll('.nav-item').forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-view') === view);
        });
    }

    function setThemeLocal(theme) {
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem('spp_admin_theme', theme);
    }

    function performSearch() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const allCommands = getBuiltInCommands();

        let filtered = [];
        if (!query) {
            // Show top items across each group
            filtered = allCommands.slice(0, 24);
        } else if (query === '?' || query === 'help' || query === 'shortcuts' || query === 'hotkeys' || query === 'keys') {
            // Specialized booster: show universal cheatsheet item, followed by all commands with hotkey badges
            const withShortcuts = allCommands.filter(c => c.shortcut);
            const helpCmd = allCommands.find(c => c.id === 'act_shortcuts_help');
            filtered = helpCmd ? [helpCmd, ...withShortcuts.filter(c => c.id !== 'act_shortcuts_help')] : withShortcuts;
        } else {
            // Fuzzy & keyword relevance scoring
            const scored = [];
            allCommands.forEach(cmd => {
                let score = 0;
                const titleLower = (cmd.title || '').toLowerCase();
                const subLower = (cmd.subtitle || '').toLowerCase();
                const catLower = (cmd.category || '').toLowerCase();
                const keywords = (cmd.keywords || []).map(k => String(k).toLowerCase());

                if (titleLower === query) score += 100;
                else if (titleLower.startsWith(query)) score += 60;
                else if (titleLower.includes(query)) score += 40;

                if (subLower.includes(query)) score += 20;
                if (catLower.includes(query)) score += 25;

                keywords.forEach(kw => {
                    if (kw === query) score += 50;
                    else if (kw.startsWith(query)) score += 30;
                    else if (kw.includes(query)) score += 15;
                });

                if (score > 0) {
                    scored.push({ cmd, score });
                }
            });

            scored.sort((a, b) => b.score - a.score);
            filtered = scored.map(s => s.cmd).slice(0, 25);
        }

        renderResults(filtered);
    }

    function renderResults(items) {
        if (!resultsContainer) {
            resultsContainer = document.getElementById('spp-cmd-palette-results');
        }
        if (!resultsContainer) return;

        if (!items || items.length === 0) {
            resultsContainer.innerHTML = '<div class="spp-cmd-empty">No matching commands or views found.</div>';
            activeIndex = -1;
            return;
        }

        let html = '';
        let currentCat = null;

        items.forEach((item, idx) => {
            const cat = item.category || 'General';
            if (cat !== currentCat) {
                currentCat = cat;
                html += `<div class="spp-cmd-group-title">${escapeHtml(currentCat)}</div>`;
            }

            const icon = item.icon || '⚡';
            const shortcut = item.shortcut ? `<kbd class="spp-cmd-kbd">${escapeHtml(item.shortcut)}</kbd>` : '';
            const subtitle = item.subtitle ? `<span class="spp-cmd-subtitle">${escapeHtml(item.subtitle)}</span>` : '';

            html += `
                <div class="spp-cmd-item" data-index="${idx}">
                    <div class="spp-cmd-item-left">
                        <span class="spp-cmd-icon">${escapeHtml(icon)}</span>
                        <div class="spp-cmd-item-text">
                            <span class="spp-cmd-title">${escapeHtml(item.title)}</span>
                            ${subtitle}
                        </div>
                    </div>
                    ${shortcut}
                </div>
            `;
        });

        resultsContainer.innerHTML = html;
        activeIndex = 0;
        updateHighlight();

        // Bind click events on rendered items
        const renderedItems = resultsContainer.querySelectorAll('.spp-cmd-item');
        renderedItems.forEach((el, idx) => {
            el.addEventListener('click', () => {
                executeItem(items[idx]);
            });
            el.addEventListener('mouseenter', () => {
                activeIndex = idx;
                updateHighlight();
            });
        });
    }

    function executeItem(item) {
        if (!item) return;
        closePalette();
        try {
            if (typeof item.action === 'function') {
                item.action();
            } else if (item.url) {
                window.location.href = item.url;
            }
        } catch (err) {
            console.error('[SPPCommandPalette] Error executing action:', err);
        }
    }

    function handleKeyNavigation(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            closePalette();
            return;
        }

        const items = resultsContainer ? resultsContainer.querySelectorAll('.spp-cmd-item') : [];
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % items.length;
            updateHighlight();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + items.length) % items.length;
            updateHighlight();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0 && activeIndex < items.length) {
                items[activeIndex].click();
            }
        }
    }

    function updateHighlight() {
        if (!resultsContainer) return;
        const items = resultsContainer.querySelectorAll('.spp-cmd-item');
        items.forEach((el, idx) => {
            if (idx === activeIndex) {
                el.classList.add('active');
                el.scrollIntoView({ block: 'nearest' });
            } else {
                el.classList.remove('active');
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================================
    // GLOBAL CAPTURE-PHASE ACCELERATOR INTERCEPTION (Crucial for Chrome Omnibox)
    // =========================================================================
    function handleGlobalKeyDown(e) {
        if (isPaletteAccelerator(e)) {
            // STOP CHROME OMNIBOX IMMEDIATELY AT CAPTURE ROOT
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            if (cheatsheetBackdrop && cheatsheetBackdrop.style.display === 'flex') {
                closeCheatsheet();
            }
            togglePalette();
            return false;
        }

        if (isCheatsheetAccelerator(e)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            if (paletteBackdrop && paletteBackdrop.style.display === 'flex') {
                closePalette();
            }
            toggleCheatsheet();
            return false;
        }

        if (e.key === 'Escape') {
            let handled = false;
            if (paletteBackdrop && paletteBackdrop.style.display === 'flex') {
                closePalette();
                handled = true;
            }
            if (cheatsheetBackdrop && cheatsheetBackdrop.style.display === 'flex') {
                closeCheatsheet();
                handled = true;
            }
            if (handled) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }

        // Direct Global Accelerators (When palette and cheatsheet are closed and not inside an input/textarea)
        const isPaletteOpen = paletteBackdrop && paletteBackdrop.style.display === 'flex';
        const isCheatsheetOpen = cheatsheetBackdrop && cheatsheetBackdrop.style.display === 'flex';

        if (!isPaletteOpen && !isCheatsheetOpen) {
            const targetTag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            const isEditing = targetTag === 'input' || targetTag === 'textarea' || (e.target && e.target.isContentEditable);
            const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;
            const keyLower = (e.key || '').toLowerCase();
            const code = e.code || '';

            // A. Action Accelerators: Ctrl+Shift+[Key]
            if (!isEditing && isCtrlOrCmd && e.shiftKey) {
                // Ctrl+Shift+U -> Run Parikshak Automated Tests
                if (keyLower === 'u' || code === 'KeyU') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Run Parikshak Tests', 'Ctrl+Shift+U');
                    if (window.admin?.viewInstances?.pages?.runParikshakStudio) {
                        window.admin.viewInstances.pages.setState({ bottomDrawerOpen: true, bottomDrawerTab: 'parikshak' });
                        window.admin.viewInstances.pages.runParikshakStudio();
                    } else {
                        navigateToView('testing');
                    }
                    return false;
                }
                // Ctrl+Shift+D -> 1-Click DNS Sync (#fleet)
                if (keyLower === 'd' || code === 'KeyD') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('BIND DNS Cluster Sync', 'Ctrl+Shift+D');
                    navigateToView('fleet');
                    setTimeout(() => {
                        if (window.admin?.viewInstances?.fleet?.syncCluster) {
                            window.admin.viewInstances.fleet.syncCluster();
                        }
                    }, 300);
                    return false;
                }
                // Ctrl+Shift+O -> Export OpenAPI Spec (#apistudio)
                if (keyLower === 'o' || code === 'KeyO') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Export OpenAPI 3.1 Spec', 'Ctrl+Shift+O');
                    navigateToView('apistudio');
                    setTimeout(() => {
                        if (window.admin?.viewInstances?.apistudio?.exportSpec) {
                            window.admin.viewInstances.apistudio.exportSpec('json');
                        }
                    }, 300);
                    return false;
                }
                // Ctrl+Shift+W -> Webhook Relay & Inspector (#webhooks)
                if (keyLower === 'w' || code === 'KeyW') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Webhook Relay & Tunnel', 'Ctrl+Shift+W');
                    navigateToView('webhooks');
                    setTimeout(() => {
                        if (window.admin?.viewInstances?.webhooks?.refreshDeliveries) {
                            window.admin.viewInstances.webhooks.refreshDeliveries();
                        }
                    }, 300);
                    return false;
                }
                // Ctrl+Shift+F -> APM Flame Graphs (#apm)
                if (keyLower === 'f' || code === 'KeyF') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('APM Flame Graphs & Profiler', 'Ctrl+Shift+F');
                    navigateToView('apm');
                    return false;
                }
                // Ctrl+Shift+S -> Simulate Workflow & Saga (#workflows)
                if (keyLower === 's' || code === 'KeyS') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Simulate Workflow & Saga', 'Ctrl+Shift+S');
                    navigateToView('workflows');
                    setTimeout(() => {
                        if (window.admin?.viewInstances?.workflows?.openSimulationModal) {
                            window.admin.viewInstances.workflows.openSimulationModal();
                        }
                    }, 300);
                    return false;
                }
                // Ctrl+Shift+K -> FIDO2 Hardware Passkeys (#identity)
                if (isKeyK(e)) {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('FIDO2 Hardware Passkeys', 'Ctrl+Shift+K');
                    navigateToView('identity');
                    setTimeout(() => {
                        if (window.admin?.viewInstances?.identity?.switchTab) {
                            window.admin.viewInstances.identity.switchTab('passkeys');
                        }
                    }, 300);
                    return false;
                }
                // Ctrl+Shift+E -> Neutralize Tracking Pixels (#mail)
                if (keyLower === 'e' || code === 'KeyE') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Neutralize Tracking Pixels', 'Ctrl+Shift+E');
                    navigateToView('mail');
                    return false;
                }
                // Ctrl+Shift+C -> Purge Studio / Framework Caches (Fresh Canvas)
                if (keyLower === 'c' || code === 'KeyC') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Purge All Caches & Reload', 'Ctrl+Shift+C');
                    if (window.admin?.viewInstances?.pages?.purgeStudioCache) {
                        window.admin.viewInstances.pages.purgeStudioCache();
                    } else if (window.admin?.refreshView) {
                        window.admin.refreshView();
                    } else {
                        location.reload();
                    }
                    return false;
                }
                // Ctrl+Shift+H -> Page Health Smoke Test (#pages)
                if (keyLower === 'h' || code === 'KeyH') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Studio: Page Health Smoke Test', 'Ctrl+Shift+H');
                    if (window.admin?.viewInstances?.pages?.checkPageHealth) {
                        window.admin.viewInstances.pages.checkPageHealth();
                    } else {
                        navigateToView('pages');
                    }
                    return false;
                }
            }

            // B. Direct Quick Accelerators: Ctrl+[Key]
            if (!isEditing && isCtrlOrCmd && !e.shiftKey) {
                // Ctrl+I -> AI Copilot
                if (keyLower === 'i' || code === 'KeyI') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('AI Copilot Assistant', 'Ctrl+I');
                    if (window.admin?.viewInstances?.pages?.toggleAiCopilot) {
                        window.admin.viewInstances.pages.toggleAiCopilot();
                    } else {
                        navigateToView('ai');
                    }
                    return false;
                }
                // Ctrl+` -> Web Tinker Sandbox Drawer
                if (e.key === '`' || code === 'Backquote') {
                    e.preventDefault();
                    e.stopPropagation();
                    showShortcutToast('Web Tinker REPL', 'Ctrl+`');
                    if (window.admin?.viewInstances?.pages?.toggleBottomDrawer) {
                        window.admin.viewInstances.pages.setState({ bottomDrawerOpen: true, bottomDrawerTab: 'tinker' });
                    } else {
                        navigateToView('tinker');
                    }
                    return false;
                }
            }

            // C. Fast View Accelerators: Alt+[Key]
            if (!isEditing && e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                // Alt+0 or Alt+H -> Operations Dashboard
                if (e.key === '0' || keyLower === 'h' || code === 'Digit0') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Operations Dashboard', 'Alt+0');
                    navigateToView('dashboard');
                    return false;
                }
                // Alt+M -> Mail & Gateway (#mail)
                if (keyLower === 'm' || code === 'KeyM') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Mail & Gateway (e-Vaarta)', 'Alt+M');
                    navigateToView('mail');
                    return false;
                }
                // Alt+D -> Virtual Hosts & Domains (#domains)
                if (keyLower === 'd' || code === 'KeyD') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Virtual Hosts & Domains', 'Alt+D');
                    navigateToView('domains');
                    return false;
                }
                // Alt+E -> File Manager & Storage (#files)
                if (keyLower === 'e' || code === 'KeyE') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('File Manager & Storage', 'Alt+E');
                    navigateToView('files');
                    return false;
                }
                // Alt+B -> Database Studio (#database)
                if (keyLower === 'b' || code === 'KeyB') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Database Studio & SQL', 'Alt+B');
                    navigateToView('database');
                    return false;
                }
                // Alt+T -> Web Terminal (#terminal)
                if (keyLower === 't' || code === 'KeyT') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Cloud Web Terminal', 'Alt+T');
                    navigateToView('terminal');
                    return false;
                }
                // Alt+R -> Deployments & Releases (#deploy)
                if (keyLower === 'r' || code === 'KeyR') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Deployments & Releases', 'Alt+R');
                    navigateToView('deploy');
                    return false;
                }
                // Alt+F -> Fleet & DNS Cluster (#fleet)
                if (keyLower === 'f' || code === 'KeyF') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Fleet & DNS Cluster', 'Alt+F');
                    navigateToView('fleet');
                    return false;
                }
                // Alt+C -> Scheduled Tasks & Cron (#cron)
                if (keyLower === 'c' || code === 'KeyC') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Scheduled Tasks & Cron', 'Alt+C');
                    navigateToView('cron');
                    return false;
                }
                // Alt+S -> Security & Firewall (#shield)
                if (keyLower === 's' || code === 'KeyS') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Security & WAF Shield', 'Alt+S');
                    navigateToView('shield');
                    return false;
                }
                // Alt+L -> Metrics & Analytics (#metrics)
                if (keyLower === 'l' || code === 'KeyL') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Metrics & Analytics', 'Alt+L');
                    navigateToView('metrics');
                    return false;
                }
                // Alt+P -> APM & Flame Graphs (#apm)
                if (keyLower === 'p' || code === 'KeyP') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('APM & Flame Graphs', 'Alt+P');
                    navigateToView('apm');
                    return false;
                }
                // Alt+W -> Webhook Relay & Tunnel (#webhooks)
                if (keyLower === 'w' || code === 'KeyW') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Webhook Relay & Tunnel', 'Alt+W');
                    navigateToView('webhooks');
                    return false;
                }
                // Alt+A -> API Studio & OpenAPI (#apistudio)
                if (keyLower === 'a' || code === 'KeyA') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('API Studio & OpenAPI', 'Alt+A');
                    navigateToView('apistudio');
                    return false;
                }
                // Alt+I -> Identity & Access (IAM) (#identity)
                if (keyLower === 'i' || code === 'KeyI') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Identity & Access (IAM)', 'Alt+I');
                    navigateToView('identity');
                    return false;
                }
                // Alt+O -> Observability & Diagnostics (#system)
                if (keyLower === 'o' || code === 'KeyO') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Observability & Diagnostics', 'Alt+O');
                    navigateToView('system');
                    return false;
                }
                // Alt+K -> Code Studio & Scaffolder (#coder)
                if (isKeyK(e)) {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('Code Studio & Scaffolder', 'Alt+K');
                    navigateToView('coder');
                    return false;
                }
                // Alt+V -> SPP Studio (#pages)
                if (keyLower === 'v' || code === 'KeyV') {
                    e.preventDefault(); e.stopPropagation();
                    showShortcutToast('SPP Studio (Visual Builder)', 'Alt+V');
                    navigateToView('pages');
                    return false;
                }

                // D. Studio Mode Accelerators: Alt+1..5 (when viewing #pages)
                const currentHash = (window.location.hash || '').replace('#', '');
                const pagesInst = window.admin?.viewInstances?.pages;
                if (currentHash === 'pages' && pagesInst) {
                    if (e.key === '1' || code === 'Digit1') {
                        e.preventDefault(); e.stopPropagation();
                        showShortcutToast('Studio: Visual Canvas', 'Alt+1');
                        pagesInst.setStudioMode('canvas');
                        return false;
                    }
                    if (e.key === '2' || code === 'Digit2') {
                        e.preventDefault(); e.stopPropagation();
                        showShortcutToast('Studio: Live Split View', 'Alt+2');
                        pagesInst.setStudioMode('split');
                        return false;
                    }
                    if (e.key === '3' || code === 'Digit3') {
                        e.preventDefault(); e.stopPropagation();
                        showShortcutToast('Studio: Code Studio', 'Alt+3');
                        pagesInst.setStudioMode('code');
                        return false;
                    }
                    if (e.key === '4' || code === 'Digit4') {
                        e.preventDefault(); e.stopPropagation();
                        showShortcutToast('Studio: Database CRUD', 'Alt+4');
                        pagesInst.setStudioMode('database');
                        return false;
                    }
                    if (e.key === '5' || code === 'Digit5') {
                        e.preventDefault(); e.stopPropagation();
                        showShortcutToast('Studio: API Tester', 'Alt+5');
                        pagesInst.setStudioMode('api');
                        return false;
                    }
                }
            }
        }
    }

    function handleGlobalKeyUp(e) {
        if (isPaletteAccelerator(e)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            return false;
        }
    }

    function handleGlobalKeyPress(e) {
        if (isPaletteAccelerator(e)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }

    // Idempotent registration with CAPTURE = TRUE
    if (!window._sppCommandPaletteHotkeysBound) {
        window._sppCommandPaletteHotkeysBound = true;
        window.addEventListener('keydown', handleGlobalKeyDown, true);
        window.addEventListener('keyup', handleGlobalKeyUp, true);
        window.addEventListener('keypress', handleGlobalKeyPress, true);
    }

    // Auto-init on DOM readiness
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPalette);
    } else {
        initPalette();
    }

    // Expose global helper API
    const API = {
        open: openPalette,
        close: closePalette,
        toggle: togglePalette,
        openCheatsheet: openCheatsheet,
        closeCheatsheet: closeCheatsheet,
        toggleCheatsheet: toggleCheatsheet,
        showToast: showShortcutToast,
        registerCommand: (cmd) => {
            if (cmd && cmd.id && cmd.title) {
                customCommands.push(cmd);
            }
        },
        getCommands: getBuiltInCommands,
        getShortcuts: () => SHORTCUT_GROUPS
    };

    window.SPPCommandPalette = API;
    window.openCommandPalette = openPalette;
    window.closeCommandPalette = closePalette;
    window.toggleCommandPalette = togglePalette;
    window.openShortcutsCheatsheet = openCheatsheet;
    window.closeShortcutsCheatsheet = closeCheatsheet;
    window.toggleShortcutsCheatsheet = toggleCheatsheet;
})();
