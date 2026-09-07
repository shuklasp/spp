<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Console') — SPPDocs</title>
    <!-- Include local HTMX as per SPP rules -->
    <script src="{{ defined('APP_BASE_URI') ? APP_BASE_URI : '' }}/sppadmin/js/htmx.min.js"></script>
    <link rel="stylesheet" href="@url('css/sppdocs.css')?v={{ file_exists(APP_BASE_DIR . '/src/SPPDocs/resources/css/sppdocs.css') ? filemtime(APP_BASE_DIR . '/src/SPPDocs/resources/css/sppdocs.css') : 1 }}">
    <link rel="stylesheet" href="@url('css/admin.css')?v={{ file_exists(APP_BASE_DIR . '/src/SPPDocs/resources/css/admin.css') ? filemtime(APP_BASE_DIR . '/src/SPPDocs/resources/css/admin.css') : 1 }}">
</head>
<body hx-boost="true">
    <div id="spp-progress"></div>
    <?php
    $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';
    $isGlobalAdmin = \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($currentUser);
    ?>

    <!-- Top Header -->
    <header class="adm-header">
        <div class="adm-header-left">
            <a href="@url('admin')" class="adm-brand-title">
                🚀 SPPDocs <span class="adm-brand-badge">Control Center</span>
            </a>
            @if($isGlobalAdmin)
                <span class="adm-badge adm-badge-admin">👑 Global Super Admin</span>
            @endif
            @if(!empty($project_id))
                <div class="adm-header-active-project" title="Active Documentation Project Scope">
                    <span class="adm-project-dot"></span>
                    <span class="adm-header-proj-prefix">Project:</span>
                    <strong class="adm-header-proj-title">{{ $project['title'] ?? $project_id }}</strong>
                    <code class="adm-header-proj-code">{{ $project_id }}</code>
                </div>
            @endif
        </div>

        <!-- Header Center: Interactive Search Text Box -->
        <div class="adm-header-search-wrap">
            <div class="adm-header-search-box" onclick="openCommandPalette('nav')" title="Search documentation, issues, commands... (Ctrl+K)">
                <span class="adm-search-icon">🔍</span>
                <input type="text" class="adm-header-search-input" placeholder="Search documentation, issues, commands... (Ctrl+K)" readonly onclick="openCommandPalette('nav')" onfocus="this.blur(); openCommandPalette('nav');">
                <kbd class="adm-search-kbd">Ctrl+K</kbd>
            </div>
        </div>

        <div class="adm-header-right">
            <a href="@url('')" target="_blank" hx-boost="false" class="adm-btn adm-btn-secondary adm-btn-sm">🌐 View Portal &rarr;</a>
            <div class="adm-user-badge">
                <div class="adm-user-avatar">{{ strtoupper(substr($currentUser, 0, 1)) }}</div>
                <span>{{ $currentUser }}</span>
            </div>
            <a href="@url('admin/logout')" hx-boost="false" class="adm-btn adm-btn-danger adm-btn-sm">Logout</a>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="adm-layout">
        <!-- Sidebar Navigation -->
        <aside class="adm-sidebar">
            <!-- Global Platform Controls -->
            <div>
                <div class="adm-nav-group-title">Platform Administration</div>
                <ul class="adm-nav-list">
                    <li>
                        <a href="@url('admin')" class="adm-nav-link {{ empty($active_tab) || $active_tab === 'dashboard' ? 'active' : '' }}">
                            <span>📊</span> Projects Dashboard
                        </a>
                    </li>
                    @if($isGlobalAdmin)
                    <li>
                        <a href="@url('admin/global-settings')" class="adm-nav-link {{ ($active_tab ?? '') === 'global-settings' ? 'active' : '' }}">
                            <span>⚙️</span> Global Platform Settings
                        </a>
                    </li>
                    <li>
                        <a href="@url('admin/users')" class="adm-nav-link {{ ($active_tab ?? '') === 'users' ? 'active' : '' }}">
                            <span>👥</span> User & Access Governance
                        </a>
                    </li>
                    <li>
                        <a href="@url('admin/roles')" class="adm-nav-link {{ ($active_tab ?? '') === 'roles' ? 'active' : '' }}">
                            <span>🛡️</span> Roles & Permissions
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            <!-- Project Specific Controls (if a project is active) -->
            @if(!empty($project_id))
            <div>
                <div class="adm-nav-group-title">Project: {{ $project['title'] ?? $project_id }}</div>
                <ul class="adm-nav-list">
                    <li>
                        <a href="@url('admin/project/' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'project-manage' ? 'active' : '' }}">
                            <span>🛠️</span> Project Settings & Features
                        </a>
                    </li>
                    <li>
                        <a href="@url('admin/roles?projectId=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'roles' ? 'active' : '' }}">
                            <span>🛡️</span> Team Members & RBAC
                        </a>
                    </li>
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('media', $project ?? []))
                    <li>
                        <a href="@url('admin/media?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'media' ? 'active' : '' }}">
                            <span>🖼️</span> Media Library
                        </a>
                    </li>
                    @endif
                    <li>
                        <a href="@url('admin/snippets?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'snippets' ? 'active' : '' }}">
                            <span>🧩</span> Global Snippets
                        </a>
                    </li>
                    <li>
                        <a href="@url('admin/analytics?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'analytics' ? 'active' : '' }}">
                            <span>📊</span> Reader Analytics & Demand
                        </a>
                    </li>
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('webhooks', $project ?? []))
                    <li>
                        <a href="@url('admin/webhooks?projectId=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'webhooks' ? 'active' : '' }}">
                            <span>🪝</span> Git & Outbox Webhooks
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('automations', $project ?? []))
                    <li>
                        <a href="@url('admin/automations?projectId=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'automations' ? 'active' : '' }}">
                            <span>⚡</span> CI/CD Automations
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('schemas', $project ?? []))
                    <li>
                        <a href="@url('admin/schemas?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'schemas' ? 'active' : '' }}">
                            <span>📐</span> Schema & Content Studio
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('openapi', $project ?? []))
                    <li>
                        <a href="@url('admin/openapi?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'openapi' ? 'active' : '' }}">
                            <span>⚡</span> Interactive API Explorer
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('i18n', $project ?? []))
                    <li>
                        <a href="@url('admin/i18n?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'i18n' ? 'active' : '' }}">
                            <span>🌐</span> Multi-Language (i18n) Hub
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('views', $project ?? []))
                    <li>
                        <a href="@url('admin/views?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'views' ? 'active' : '' }}">
                            <span>🔍</span> Views &amp; Query Studio
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('taxonomy', $project ?? []))
                    <li>
                        <a href="@url('admin/taxonomy?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'taxonomy' ? 'active' : '' }}">
                            <span>🏷️</span> Taxonomy &amp; Vocabularies
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('modules', $project ?? []))
                    <li>
                        <a href="@url('admin/modules?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'modules' ? 'active' : '' }}">
                            <span>🧩</span> Modules &amp; Extensions
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('blocks', $project ?? []))
                    <li>
                        <a href="@url('admin/blocks?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'blocks' ? 'active' : '' }}">
                            <span>🧱</span> Regions &amp; Blocks
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('lms', $project ?? []))
                    <li>
                        <a href="@url('admin/lms?project=' . $project_id)" class="adm-nav-link {{ ($active_tab ?? '') === 'lms' ? 'active' : '' }}">
                            <span>🎓</span> Course Studio &amp; LMS
                        </a>
                    </li>
                    @endif
                    @if(\App\SPPDocs\Services\FeatureManager::isEnabled('book_export', $project ?? []))
                    <li>
                        <a href="@url('export/book?project=' . $project_id)" target="_blank" hx-boost="false" class="adm-nav-link">
                            <span>📕</span> Full-Book PDF & Export &rarr;
                        </a>
                    </li>
                    @endif
                    <li>
                        <a href="@url('project/' . $project_id)" target="_blank" hx-boost="false" class="adm-nav-link">
                            <span>📖</span> Live Documentation &rarr;
                        </a>
                    </li>
                </ul>
            </div>
            @endif
        </aside>

        <!-- Main Content -->
        <main class="adm-main" id="adm-main-content">
            @if(!empty($project_id))
            <div class="adm-project-context-strip">
                <div class="adm-project-context-breadcrumbs">
                    <a href="@url('admin')" class="adm-context-crumb">Platform</a>
                    <span class="adm-context-sep">/</span>
                    <a href="@url('admin/project/' . $project_id)" class="adm-context-crumb adm-context-project">
                        <span class="adm-context-icon">📁</span>
                        <strong class="adm-context-name">{{ $project['title'] ?? $project_id }}</strong>
                        <code class="adm-context-slug">{{ $project_id }}</code>
                    </a>
                    @if(!empty($active_tab))
                    <span class="adm-context-sep">/</span>
                    <span class="adm-context-current">
                        @if($active_tab === 'project-manage') 🛠️ Project Settings
                        @elseif($active_tab === 'roles') 🛡️ Team Members &amp; RBAC
                        @elseif($active_tab === 'media') 🖼️ Media Library
                        @elseif($active_tab === 'snippets') 🧩 Global Snippets
                        @elseif($active_tab === 'analytics') 📊 Reader Analytics &amp; Demand
                        @elseif($active_tab === 'webhooks') 🪝 Git &amp; Outbox Webhooks
                        @elseif($active_tab === 'automations') ⚡ CI/CD Automations
                        @elseif($active_tab === 'schemas') 📐 Schema &amp; Content Studio
                        @elseif($active_tab === 'openapi') ⚡ Interactive API Explorer
                        @elseif($active_tab === 'i18n') 🌐 Multi-Language (i18n) Hub
                        @elseif($active_tab === 'views') 🔍 Views &amp; Query Studio
                        @elseif($active_tab === 'taxonomy') 🏷️ Taxonomy &amp; Vocabularies
                        @else {{ ucfirst($active_tab) }}
                        @endif
                    </span>
                    @endif
                </div>
                <div class="adm-project-context-quicknav">
                    <a href="@url('project/' . $project_id)" target="_blank" hx-boost="false" class="adm-btn adm-btn-secondary adm-btn-xs" title="View live documentation site">
                        📖 Live Docs &rarr;
                    </a>
                    <a href="@url('admin')" class="adm-btn adm-btn-secondary adm-btn-xs" title="Switch to another project">
                        ⇄ Switch Project
                    </a>
                </div>
            </div>
            @endif
            @if(!empty($flash_success))
                <div class="adm-alert adm-alert-success">
                    <span>✅</span>
                    <div>{{ $flash_success }}</div>
                </div>
            @endif

            @if(!empty($flash_error))
                <div class="adm-alert adm-alert-danger">
                    <span>⚠️</span>
                    <div>{{ $flash_error }}</div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- SPP-UX HTMX Integration -->
    <script>
        document.body.addEventListener('htmx:beforeRequest', function() {
            const p = document.getElementById('spp-progress');
            if (p) {
                p.style.opacity = '1';
                p.style.width = '20%';
                clearInterval(window.sppProgressInt);
                window.sppProgressInt = setInterval(() => {
                    let w = parseFloat(p.style.width);
                    if (w < 85) p.style.width = (w + 5) + '%';
                }, 250);
            }
        });
        document.body.addEventListener('htmx:afterRequest', function() {
            const p = document.getElementById('spp-progress');
            if (p) {
                clearInterval(window.sppProgressInt);
                p.style.width = '100%';
                setTimeout(() => { p.style.opacity = '0'; }, 250);
                setTimeout(() => { p.style.width = '0%'; }, 500);
            }
        });
        document.body.addEventListener('htmx:beforeOnLoad', function (evt) {
            if (evt.detail.xhr && evt.detail.xhr.status >= 400) {
                evt.detail.shouldSwap = true;
                evt.detail.isError = false;
            }
        });
    </script>

    @yield('scripts')
    @spppartial('partials/command_palette.blade.php', ['project_id' => !empty($project_id) ? $project_id : ($_SESSION['sppdocs_current_project'] ?? 'demo-app')])
    @spppartial('partials/keyboard_shortcuts.blade.php')
</body>
</html>
