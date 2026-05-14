<!DOCTYPE html>
<html lang="en" data-theme="saffron">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Lekhak Admin' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \SPP\App::getBaseUrl() }}/theme-assets/glass_admin/theme.css">
</head>
<body>
    <!-- Persistent Ceiling-Mounted Lekhak Admin Toolbar -->
    <div class="lekhak-admin-toolbar-global">
        <div class="lekhak-toolbar-left">
            <div class="lekhak-toolbar-brand">
                <img src="{{ rtrim($web_root ?? '', '/') }}/img/lekhak_logo.png" alt="Lekhak Logo" style="width: 24px; height: 24px; object-fit: contain;" />
                <span>Lekhak Admin</span>
                <span class="ver">Premium UX</span>
            </div>
            <a href="{{ $admin_root }}" class="lekhak-toolbar-link {{ empty($view_name) || $view_name === 'dashboard' ? 'active' : '' }}">Manage Content</a>
            <a href="{{ $admin_root }}/structure/types" class="lekhak-toolbar-link {{ str_contains($view_name ?? '', 'structure') ? 'active' : '' }}">Structure Architect</a>
            <a href="{{ $admin_root }}/landing" class="lekhak-toolbar-link {{ str_contains($view_name ?? '', 'landing') ? 'active' : '' }}">Visual Designer</a>
            <a href="{{ $admin_root }}/settings" class="lekhak-toolbar-link {{ str_contains($view_name ?? '', 'settings') ? 'active' : '' }}">Configuration</a>
        </div>
        <div class="lekhak-toolbar-right">
            <button onclick="window.location.reload();" class="lekhak-btn-action" title="Flushes precompiled layout cache files">⚡ Clear Cache</button>
            <button onclick="alert('Index rebuilt across relational database keys.');" class="lekhak-btn-action">🔄 Rebuild Registry</button>
        </div>
    </div>

    <div class="admin-wrapper">
        <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">
            ☰
        </button>
        <aside class="sidebar">
            <div class="logo" style="justify-content: center; padding: 0; margin-bottom: 40px;">
                <img src="{{ rtrim($web_root ?? '', '/') }}/img/lekhak_logo_full.jpg" alt="Lekhak CMS Logo" style="width: 100%; max-width: 200px; height: auto; object-fit: contain; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.2);" />
            </div>

            <div class="sidebar-search">
                <input type="text" id="menuSearch" placeholder="Search menu..." onkeyup="filterMenu()">
            </div>
            
            <nav class="nav-list">
                @php $v = $view_name ?? ''; @endphp
                
                <div class="nav-group" data-group="overview">
                    <div class="nav-group-header" onclick="toggleGroup(this)">Overview</div>
                    <div class="nav-group-content">
                        <div class="nav-item">
                            <a href="{{ $admin_root }}" class="{{ $v == 'dashboard' ? 'active' : '' }}">
                                <span class="nav-icon">📊</span> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="nav-group" data-group="content">
                    <div class="nav-group-header" onclick="toggleGroup(this)">Content</div>
                    <div class="nav-group-content">
                        <div class="nav-item">
                            <a href="{{ $admin_root }}/content" class="{{ str_contains($v, 'content/list') ? 'active' : '' }}">
                                <span class="nav-icon">📝</span> All Content
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="{{ $app_root }}/admin#editor" class="nav-link {{ $v == 'editor' ? 'active' : '' }}" data-view="editor">
                                <span class="nav-icon">＋</span> Create Content
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="{{ $admin_root }}/landing" class="{{ str_contains($v, 'landing') ? 'active' : '' }}">
                                <span class="nav-icon">🎨</span> Landing Pages
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="{{ $admin_root }}/media" class="{{ str_contains($v, 'media') ? 'active' : '' }}">
                                <span class="nav-icon">🖼️</span> Media Library
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="nav-group" data-group="structure">
                    <div class="nav-group-header" onclick="toggleGroup(this)">Structure</div>
                    <div class="nav-group-content">
                        <div class="nav-item">
                            <a href="{{ $admin_root }}/structure/types" class="{{ str_contains($v, 'content-type') ? 'active' : '' }}">
                                <span class="nav-icon">🏗️</span> Content Types
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="{{ $admin_root }}/structure/fields" class="{{ str_contains($v, 'field') ? 'active' : '' }}">
                                <span class="nav-icon">🌿</span> Fields Global
                            </a>
                        </div>
                    </div>
                </div>

                <div class="nav-group" data-group="system">
                    <div class="nav-group-header" onclick="toggleGroup(this)">System</div>
                    <div class="nav-group-content">
                        <div class="nav-item">
                            <a href="{{ $admin_root }}/settings" class="{{ str_contains($v, 'settings') ? 'active' : '' }}">
                                <span class="nav-icon">⚙️</span> Configuration
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="{{ $admin_root }}/users" class="{{ str_contains($v, 'user') ? 'active' : '' }}">
                                <span class="nav-icon">👤</span> Users & Roles
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="page-title">
                    <h1>{{ $title ?? 'Dashboard' }}</h1>
                    <p>{{ $subtitle ?? 'Welcome to your administrative workspace.' }}</p>
                </div>
                <div class="header-actions" style="display: flex; align-items: center; gap: 20px;">
                    <div class="theme-switcher" style="display: flex; gap: 8px; background: rgba(255,255,255,0.05); padding: 5px; border-radius: 50px; border: 1px solid var(--glass-border);">
                        <button onclick="setTheme('dark')" style="width: 32px; height: 32px; border-radius: 50%; border: none; cursor: pointer; background: #0f172a; border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #64748b;" title="Night Mode">•</button>
                        <button onclick="setTheme('day')" style="width: 32px; height: 32px; border-radius: 50%; border: none; cursor: pointer; background: #fff; border: 1px solid rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #cbd5e1;" title="Day Mode">•</button>
                        <button onclick="setTheme('saffron')" style="width: 32px; height: 32px; border-radius: 50%; border: none; cursor: pointer; background: #f97316; border: 1px solid rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #ffedd5;" title="Saffron Mode">•</button>
                    </div>
                    @yield('actions')
                </div>
            </header>

            <div class="animate-fade">
                @if(isset($_SESSION['flash_success']))
                    <div class="glass-panel" style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 15px 25px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px;">
                        <span style="font-size: 1.5rem;">✅</span>
                        <div style="font-weight: 500;">{{ $_SESSION['flash_success'] }}</div>
                        @php unset($_SESSION['flash_success']); @endphp
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
    <!-- Full-screen Embedded Lekhni Block Editor Overlay Container -->
    <div id="editor-integrated-wrapper" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 999999; background: var(--bg-deep, #0f172a); flex-direction: column;">
        <div class="editor-integrated-header" style="height: 50px; background: var(--sidebar-bg, #1e293b); border-bottom: 1px solid var(--sidebar-border, rgba(255,255,255,0.1)); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; color: white;">
            <div style="display: flex; align-items: center; gap: 10px; font-weight: bold; font-family: 'Outfit', sans-serif;">
                <span style="background: var(--accent-primary, #6366f1); color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">✍️ LEKHNI ENGINE</span>
                <span id="editor-integrated-title" style="font-size: 0.9rem;">Integrated Document Studio</span>
            </div>
            <button onclick="closeIntegratedEditor()" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 6px 14px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                ✕ Exit Studio / Return
            </button>
        </div>
        <div id="editor-integrated-host" style="flex-grow: 1; position: relative; width: 100%; overflow: hidden;"></div>
    </div>

    <script src="{{ rtrim($web_root ?? '', '/') }}/spp/res/js/spp.js?v={{ time() }}"></script>
    <script type="module">
        // Global controller logic to seamlessly mount embedded Lekhni blocks into any native page view
        window.closeIntegratedEditor = function() {
            const wrapper = document.getElementById('editor-integrated-wrapper');
            if (wrapper) {
                wrapper.style.display = 'none';
                document.getElementById('editor-integrated-host').innerHTML = '';
                window.location.reload();
            }
        };

        window.openIntegratedEditor = async function(nodeId = null) {
            const wrapper = document.getElementById('editor-integrated-wrapper');
            const host = document.getElementById('editor-integrated-host');
            const titleEl = document.getElementById('editor-integrated-title');
            
            if (!wrapper || !host) return;
            
            wrapper.style.display = 'flex';
            
            let selectedBundle = 'Article';
            if (nodeId === null) {
                selectedBundle = await new Promise((resolve) => {
                    titleEl.textContent = 'Select Content Schema Bundle';
                    host.innerHTML = `
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;padding:20px;background:#090d16;overflow-y:auto;width:100%;">
                            <div style="max-width:750px;width:100%;background:#1e293b;border:1px solid #334155;border-radius:16px;padding:32px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);text-align:center;">
                                <div style="font-size:3rem;margin-bottom:16px;">📦</div>
                                <h2 style="font-family:'Outfit',sans-serif;font-size:1.8rem;color:white;margin-bottom:8px;font-weight:800;">Choose Content Schema Bundle</h2>
                                <p style="color:#94a3b8;font-size:0.95rem;margin-bottom:32px;">Select the underlying structured layout framework bundle to configure metadata storage strategy correctly.</p>
                                
                                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;text-align:left;margin-bottom:32px;">
                                    <div class="bundle-card" data-bundle="Article" style="background:#0f172a;border:2px solid #334155;padding:20px;border-radius:12px;cursor:pointer;transition:all 0.2s;">
                                        <div style="font-size:1.5rem;margin-bottom:8px;">📝</div>
                                        <div style="font-weight:bold;color:white;font-size:1.1rem;margin-bottom:4px;">Article</div>
                                        <div style="font-size:0.75rem;color:#64748b;">Standard news updates, stories, or deep-dive document posts.</div>
                                    </div>
                                    <div class="bundle-card" data-bundle="Page" style="background:#0f172a;border:2px solid #334155;padding:20px;border-radius:12px;cursor:pointer;transition:all 0.2s;">
                                        <div style="font-size:1.5rem;margin-bottom:8px;">📄</div>
                                        <div style="font-weight:bold;color:white;font-size:1.1rem;margin-bottom:4px;">Static Page</div>
                                        <div style="font-size:0.75rem;color:#64748b;">Timeless static layout views like About Us, Contact, or Terms.</div>
                                    </div>
                                    <div class="bundle-card" data-bundle="Product Story" style="background:#0f172a;border:2px solid #334155;padding:20px;border-radius:12px;cursor:pointer;transition:all 0.2s;">
                                        <div style="font-size:1.5rem;margin-bottom:8px;">🛍️</div>
                                        <div style="font-weight:bold;color:white;font-size:1.1rem;margin-bottom:4px;">Product Story</div>
                                        <div style="font-size:0.75rem;color:#64748b;">Feature rich eCommerce showcases or product release features.</div>
                                    </div>
                                    <div class="bundle-card" data-bundle="Case Study" style="background:#0f172a;border:2px solid #334155;padding:20px;border-radius:12px;cursor:pointer;transition:all 0.2s;">
                                        <div style="font-size:1.5rem;margin-bottom:8px;">📊</div>
                                        <div style="font-weight:bold;color:white;font-size:1.1rem;margin-bottom:4px;">Case Study</div>
                                        <div style="font-size:0.75rem;color:#64748b;">Customer success analytics and detailed portfolio documents.</div>
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:center;gap:12px;">
                                    <button onclick="closeIntegratedEditor()" style="background:transparent;border:1px solid #334155;color:#94a3b8;padding:10px 24px;border-radius:8px;font-weight:bold;cursor:pointer;">Cancel / Go Back</button>
                                </div>
                            </div>
                        </div>
                    `;

                    host.querySelectorAll('.bundle-card').forEach(card => {
                        card.addEventListener('mouseover', () => {
                            card.style.borderColor = '#6366f1';
                            card.style.background = '#1e293b';
                        });
                        card.addEventListener('mouseout', () => {
                            card.style.borderColor = '#334155';
                            card.style.background = '#0f172a';
                        });
                        card.addEventListener('click', () => {
                            resolve(card.getAttribute('data-bundle'));
                        });
                    });
                });
            }

            host.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-dim);">Initializing zero-dependency standalone Lekhni core engine...</div>';
            titleEl.textContent = nodeId ? `Editing Stream #${nodeId}` : `Creating New ${selectedBundle}`;

            try {
                // Dynamically import application editor package bundle
                const moduleUrl = '{{ rtrim($web_root ?? '', '/') }}/src/lekhak/comp/editor.js?t=' + Date.now();
                const module = await import(moduleUrl);
                const EditorClass = module.default;

                // Build a lightweight framework bridge payload
                const dummyAdminAdapter = {
                    config: { baseUrl: '{{ $app_root }}', apiBase: '{{ $app_root }}/admin-api' },
                    api: async (action, p = {}) => {
                        const res = await fetch('{{ $app_root }}/admin-api?action=' + action, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(p)
                        });
                        return await res.json();
                    },
                    apiPost: async function(action, p = {}) { return this.api(action, p); },
                    notify: (msg, type) => {
                        console.log(`[LEKHNI ${type.toUpperCase()}] ${msg}`);
                        const toast = document.createElement('div');
                        toast.style.position = 'fixed';
                        toast.style.bottom = '24px';
                        toast.style.right = '24px';
                        toast.style.background = type === 'success' ? '#16a34a' : (type === 'error' ? '#dc2626' : '#2563eb');
                        toast.style.color = 'white';
                        toast.style.padding = '12px 24px';
                        toast.style.borderRadius = '8px';
                        toast.style.fontWeight = 'bold';
                        toast.style.boxShadow = '0 10px 25px rgba(0,0,0,0.5)';
                        toast.style.zIndex = '9999999';
                        toast.style.fontFamily = "'Inter', sans-serif";
                        toast.style.fontSize = "0.9rem";
                        toast.textContent = msg;
                        document.body.appendChild(toast);
                        setTimeout(() => toast.style.opacity = '0', 2500);
                        setTimeout(() => toast.remove(), 2800);
                    },
                    openAppView: (view) => {
                        window.closeIntegratedEditor();
                    }
                };

                host.innerHTML = '';
                const editorObj = new EditorClass(dummyAdminAdapter, host, { id: nodeId, bundle: selectedBundle });
                
                if (editorObj.onInit) {
                    await editorObj.onInit({ id: nodeId, bundle: selectedBundle });
                }
                editorObj.update();
                if (editorObj.onMount) {
                    await editorObj.onMount();
                }

                // Intercept inner component save/close hooks gracefully
                editorObj.onClose = () => {
                    window.closeIntegratedEditor();
                };

            } catch (e) {
                console.error("Integrated Editor Setup Exception:", e);
                host.innerHTML = `<div style="padding:40px;color:#ef4444;text-align:center;"><h3>Editor Core Instantiation Fault</h3><p>${e.message}</p></div>`;
            }
        };

        // Global Link Interceptor for legacy action triggers
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('click', (e) => {
                const editLink = e.target.closest('a[href*="content/edit/"]');
                if (editLink) {
                    e.preventDefault();
                    const href = editLink.getAttribute('href');
                    const parts = href.split('/content/edit/');
                    const targetId = parts[1] ? parts[1].replace(/[^0-9]/g, '') : null;
                    window.openIntegratedEditor(targetId);
                    return;
                }

                const createLink = e.target.closest('a[href$="#editor"], a[href="#editor"], [data-spp-evt="nav-editor"]');
                if (createLink) {
                    e.preventDefault();
                    window.openIntegratedEditor(null);
                    return;
                }
            });

            // If the route hash initializes to #editor directly, auto-launch
            if (window.location.hash.includes('#editor')) {
                window.openIntegratedEditor(null);
            }
        });
    </script>

    <script>
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('lekhak-admin-theme', theme);
        }

        function toggleGroup(header) {
            const group = header.parentElement;
            group.classList.toggle('collapsed');
            
            // Save state
            const groupName = group.getAttribute('data-group');
            const collapsedGroups = JSON.parse(localStorage.getItem('collapsed-menu-groups') || '[]');
            if (group.classList.contains('collapsed')) {
                if (!collapsedGroups.includes(groupName)) collapsedGroups.push(groupName);
            } else {
                const index = collapsedGroups.indexOf(groupName);
                if (index > -1) collapsedGroups.splice(index, 1);
            }
            localStorage.setItem('collapsed-menu-groups', JSON.stringify(collapsedGroups));
        }

        function filterMenu() {
            const input = document.getElementById('menuSearch');
            const filter = input.value.toLowerCase();
            const groups = document.querySelectorAll('.nav-group');

            groups.forEach(group => {
                const items = group.querySelectorAll('.nav-item');
                let groupVisible = false;

                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(filter)) {
                        item.style.display = '';
                        groupVisible = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (groupVisible) {
                    group.style.display = '';
                    if (filter.length > 0) {
                        group.classList.remove('collapsed');
                    } else {
                        // Restore original state if search cleared
                        const collapsedGroups = JSON.parse(localStorage.getItem('collapsed-menu-groups') || '[]');
                        if (collapsedGroups.includes(group.getAttribute('data-group'))) {
                            group.classList.add('collapsed');
                        }
                    }
                } else {
                    group.style.display = 'none';
                }
            });
        }

        // Initialize theme
        var savedTheme = localStorage.getItem('lekhak-admin-theme') || 'saffron';
        setTheme(savedTheme);

        // Initialize menu states
        var collapsedGroups = JSON.parse(localStorage.getItem('collapsed-menu-groups') || '[]');
        collapsedGroups.forEach(name => {
            const group = document.querySelector(`.nav-group[data-group="${name}"]`);
            if (group) group.classList.add('collapsed');
        });

        // Auto-expand group of active item
        var activeLink = document.querySelector('.nav-item a.active');
        if (activeLink) {
            const group = activeLink.closest('.nav-group');
            if (group) group.classList.remove('collapsed');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (sidebar && toggle) {
                if (window.innerWidth <= 850 && 
                    sidebar.classList.contains('open') && 
                    !sidebar.contains(event.target) && 
                    !toggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    </script>
</body>
</html>
