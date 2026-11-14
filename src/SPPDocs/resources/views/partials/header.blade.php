@php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

$isLoggedIn = false;
$currentUser = null;
$userRole = null;

if (!empty($_SESSION['sppdocs_user'])) {
    $isLoggedIn = true;
    $currentUser = $_SESSION['sppdocs_user'];
    $userRole = $_SESSION['sppdocs_role'] ?? 'user';
} elseif (class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
    $isLoggedIn = true;
    $authObj = \SPPMod\SPPAuth\SPPAuth::user();
    $currentUser = $authObj->username ?? ($authObj->id ?? 'User');
    $userRole = $authObj->role ?? 'user';
}

$loginUrl = \SPP\App::url('login');
$logoutUrl = \SPP\App::url('auth/logout');

$isGlobalAdmin = false;
$isProjectAdmin = false;

if ($isLoggedIn && $currentUser) {
    $isGlobalAdmin = \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($currentUser);
    if (!empty($project_id)) {
        $isProjectAdmin = \App\SPPDocs\Services\PermissionManager::isProjectAdmin($project_id, $currentUser);
    }
}

// Notification count
$bellCount = 0;
$bellProjectId = $project_id ?? ($_GET['projectId'] ?? null);
if ($isLoggedIn && $bellProjectId) {
    try {
        $bellConfigFile = __DIR__ . '/../../etc/sppdocs.yml';
        if (file_exists($bellConfigFile)) {
            $bellConfig = \Symfony\Component\Yaml\Yaml::parseFile($bellConfigFile);
            $bellProjectPath = $bellConfig['projects'][$bellProjectId] ?? null;
            if ($bellProjectPath) {
                if (strpos($bellProjectPath, 'C:/') === 0 || strpos($bellProjectPath, 'c:/') === 0) {
                    $bellProjectPath = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $bellProjectPath);
                }
                if (!str_starts_with($bellProjectPath, '/') && !str_contains($bellProjectPath, ':\\')) {
                    $bellProjectPath = dirname(SPP_BASE_DIR) . '/' . $bellProjectPath;
                }
                $bellIssuesDir = dirname($bellProjectPath) . '/issues';
                if (is_dir($bellIssuesDir . '/notifications') && $currentUser) {
                    $bellFile = $bellIssuesDir . '/notifications/' . basename($currentUser) . '.json';
                    if (file_exists($bellFile)) {
                        $bellNotifs = json_decode(file_get_contents($bellFile), true) ?: [];
                        $bellCount = count(array_filter($bellNotifs, fn($n) => empty($n['read'])));
                    }
                }
            }
        }
    } catch (\Exception $e) {}
}
@endphp

<header hx-boost="false">
    <div class="header-left">
        @php
            $headerProjId = !empty($project_id) ? $project_id : (!empty($_SESSION['sppdocs_current_project']) ? $_SESSION['sppdocs_current_project'] : null);
            if (empty($project) && !empty($headerProjId)) {
                $project = \App\SPPDocs\Services\MenuRegistry::resolveProject($headerProjId);
            }
            $headerTargetUrl = !empty($headerProjId) ? ($base_url . '/project/' . $headerProjId) : ($base_url . '/');
        @endphp
        <a href="{{ $headerTargetUrl }}" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit;">
            @if(!empty($project['logo']))
                @php
                    $rawLogo = $project['logo'];
                    if (preg_match('#^https?://#i', $rawLogo)) {
                        $resolvedLogo = $rawLogo;
                    } else {
                        $cleanLogo = ltrim($rawLogo, '/');
                        $baseClean = trim($base_url, '/');
                        if (!empty($baseClean) && str_starts_with($cleanLogo, $baseClean . '/')) {
                            $resolvedLogo = '/' . $cleanLogo;
                        } else {
                            $resolvedLogo = $base_url . '/' . $cleanLogo;
                        }
                    }
                @endphp
                <img src="{{ $resolvedLogo }}" alt="{{ $project['title'] ?? 'Logo' }}" class="header-logo" onerror="this.style.display='none'" />
            @else
                <img src="{{ $base_url }}/assets/logo.jpg" alt="SPP Logo" class="header-logo" onerror="this.style.display='none'" />
            @endif
            <h1 class="header-title" style="margin: 0;">
                {{ $project['title'] ?? 'SPP Developer Portal' }}
            </h1>
        </a>
    </div>

    <!-- Center Search Text Box -->
    <div class="sppdocs-header-search-wrap">
        @php
            $hasIssues = \App\SPPDocs\Services\FeatureManager::isEnabled('issues', $project ?? []);
            $searchPlaceholder = $hasIssues 
                ? 'Search documentation, issues, commands... (Ctrl+K)' 
                : 'Search documentation, commands... (Ctrl+K)';
        @endphp
        <div class="sppdocs-header-search-box" onclick="openCommandPalette('nav')" title="{{ $searchPlaceholder }}">
            <span class="sppdocs-search-icon">🔍</span>
            <input type="text" class="sppdocs-header-search-input" placeholder="{{ $searchPlaceholder }}" readonly onclick="openCommandPalette('nav')" onfocus="this.blur(); openCommandPalette('nav');">
            <kbd class="sppdocs-search-kbd">Ctrl+K</kbd>
        </div>
    </div>

    <div class="header-right">
        @if(isset($project['links']['website']))
            <a href="@external_url($project['links']['website'])" target="_blank" rel="noopener" class="header-link">Website</a>
        @endif
        @if(!empty($project['links']['custom']) && is_array($project['links']['custom']))
            @foreach(array_slice($project['links']['custom'], 0, 2) as $clink)
                @if(!empty($clink['url']) && !empty($clink['title']))
                    <a href="@external_url($clink['url'])" target="_blank" rel="noopener" class="header-link" title="{{ $clink['title'] }}">{{ $clink['icon'] ?? '' }} {{ $clink['title'] }}</a>
                @endif
            @endforeach
        @endif
        @spppartial('partials/locale_switcher.blade.php', [
            'project' => $project ?? [],
            'project_id' => $project_id ?? '',
            'version' => $version ?? 'v1',
            'current_path' => $current_path ?? null,
            'locale' => $locale ?? null,
        ])

        @php
            $hasExplicitRoleParam = !empty($_GET['role']) || !empty($_GET['as']);
            $personasEnabled = !empty($project['personas_enabled']) || !empty($project['personas']) || !empty($project['features']['personas']) || $hasExplicitRoleParam;

            $activeRole = $active_role ?? ($_GET['role'] ?? ($_GET['as'] ?? ($_COOKIE['sppdocs_active_role'] ?? ($_SESSION['sppdocs_active_role'] ?? 'visitor'))));
            if (in_array(strtolower($activeRole), ['faculty', 'instructor', 'teacher'])) $activeRole = 'developer';
            if (in_array(strtolower($activeRole), ['guardian', 'father', 'mother', 'parent', 'client'])) $activeRole = 'stakeholder';
            if (in_array(strtolower($activeRole), ['student', 'user', 'guest', 'reader'])) $activeRole = 'visitor';
            if (in_array(strtolower($activeRole), ['principal', 'superadmin', 'manager', 'admin'])) $activeRole = 'maintainer';

            if (!empty($project['personas']) && is_array($project['personas'])) {
                $rolesRegistry = $project['personas'];
            } else {
                $rolesRegistry = [
                    'visitor' => [
                        'name' => 'Visitor / Evaluator',
                        'icon' => '👀',
                        'desc' => 'Overview, quickstart guides & feature highlights',
                    ],
                    'developer' => [
                        'name' => 'Developer',
                        'icon' => '💻',
                        'desc' => 'Architecture, APIs, code samples & developer guides',
                    ],
                    'stakeholder' => [
                        'name' => 'Client / Stakeholder',
                        'icon' => '👔',
                        'desc' => 'Project roadmap, delivery status & communication',
                    ],
                    'maintainer' => [
                        'name' => 'Maintainer / DevOps',
                        'icon' => '🛠️',
                        'desc' => 'CI/CD, deployments, settings & issue triage',
                    ],
                    'all' => [
                        'name' => 'All Modules',
                        'icon' => '🌐',
                        'desc' => 'Unfiltered view of all operational suites',
                    ],
                ];
            }
            $currentRoleDef = $rolesRegistry[$activeRole] ?? ($rolesRegistry['visitor'] ?? reset($rolesRegistry));
        @endphp

        @if($personasEnabled)
        <!-- Compact Dropdown Role Switcher -->
        <div class="sppdocs-role-dropdown-wrap" id="sppdocs-role-dropdown">
            <button type="button" class="sppdocs-role-dropdown-btn" id="sppdocs-role-btn" onclick="toggleRoleDropdown(event)" title="Switch Active Persona / Perspective">
                <span class="sppdocs-role-active-icon">{{ $currentRoleDef['icon'] }}</span>
                <span class="sppdocs-role-active-label">{{ $currentRoleDef['name'] }}</span>
                <span class="sppdocs-chevron">▼</span>
            </button>
            <div class="sppdocs-role-menu" id="sppdocs-role-menu">
                <div class="sppdocs-role-menu-header">Select Perspective / Role</div>
                @foreach($rolesRegistry as $rKey => $rDef)
                    <button type="button" class="sppdocs-role-menu-item {{ $activeRole === $rKey ? 'active' : '' }}"
                            data-role-target="{{ $rKey }}"
                            onclick="sppSwitchRole('{{ $rKey }}')">
                        <div class="sppdocs-role-item-main">
                            <span class="sppdocs-role-item-icon">{{ $rDef['icon'] }}</span>
                            <div>
                                <div class="sppdocs-role-item-title">{{ $rDef['name'] }}</div>
                                <div class="sppdocs-role-item-desc">{{ $rDef['desc'] }}</div>
                            </div>
                        </div>
                        <span class="sppdocs-role-item-check">✓</span>
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        @php
            $headerSuites = \App\SPPDocs\Services\MenuRegistry::getLauncherSuites($project ?? [], $headerProjId ?? '', $currentUser);
            $totalLauncherApps = 0;
            foreach ($headerSuites as $hs) {
                $totalLauncherApps += count($hs['apps'] ?? []);
            }
        @endphp

        @if($totalLauncherApps > 1)
        <!-- 9-Dot Ecosystem Apps Launcher -->
        <button type="button" class="sppdocs-app-launcher-btn" onclick="toggleAppLauncher()" title="Ecosystem Apps & Workspaces (Ctrl+M)">
            <span class="sppdocs-app-launcher-dots">
                <span></span><span></span><span></span>
                <span></span><span></span><span></span>
                <span></span><span></span><span></span>
            </span>
            <span class="sppdocs-app-launcher-label">Apps</span>
            <span class="sppdocs-app-launcher-badge">Suite</span>
        </button>
        @endif

        <a href="{{ $base_url }}/" class="header-link-brand">Portal Home</a>

        @if($isLoggedIn)
            @if($isGlobalAdmin)
                <a href="@url('admin' . (!empty($project_id) ? '?project=' . $project_id : ''))" class="header-admin-btn" hx-boost="false" title="Open Admin Console">⚙️ Admin</a>
            @elseif($isProjectAdmin && !empty($project_id))
                <a href="@url('admin/project/' . $project_id)" class="header-admin-btn" hx-boost="false" title="Open Project Administration">⚙️ Settings</a>
            @endif

            <div class="header-user-badge">
                <span class="header-user-avatar">{{ strtoupper(substr($currentUser, 0, 1)) }}</span>
                <span class="header-user-name">{{ $currentUser }}</span>
                @if($isGlobalAdmin)
                    <span class="header-role-tag super-admin">Admin</span>
                @elseif($isProjectAdmin)
                    <span class="header-role-tag">Project Admin</span>
                @endif
            </div>

            @if($bellProjectId && \App\SPPDocs\Services\FeatureManager::isEnabled('issues', $project ?? null))
                <a href="{{ $base_url }}/notifications?projectId={{ $bellProjectId }}" class="header-notif-btn" title="Notifications">
                    🔔@if($bellCount > 0)<span class="header-notif-badge">{{ $bellCount > 9 ? '9+' : $bellCount }}</span>@endif
                </a>
            @endif

            <a href="{{ $logoutUrl }}" class="header-logout-btn" hx-boost="false">Log Out</a>
        @else
            <a href="{{ $loginUrl }}?redirect={{ urlencode($_SERVER['REQUEST_URI'] ?? '') }}" class="header-login-btn" hx-boost="false">Log In</a>
        @endif
    </div>
</header>

@if(!empty($totalLauncherApps) && $totalLauncherApps > 1)
{{-- Ecosystem App Launcher Popover Modal --}}
@spppartial('partials/app_launcher_modal.blade.php', ['project_id' => $headerProjId, 'project' => $project ?? []])
@endif

<script>
(function() {
    var rolesMap = {
        'visitor':     { name: 'Visitor / Evaluator', icon: '👀', desc: 'Overview, quickstart guides & feature highlights' },
        'developer':   { name: 'Developer', icon: '💻', desc: 'Architecture, APIs, code samples & developer guides' },
        'stakeholder': { name: 'Client / Stakeholder', icon: '👔', desc: 'Project roadmap, delivery status & communication' },
        'maintainer':  { name: 'Maintainer / DevOps', icon: '🛠️', desc: 'CI/CD, deployments, settings & issue triage' },
        'all':         { name: 'All Modules', icon: '🌐', desc: 'Unfiltered view of all operational suites' }
    };

    window.toggleRoleDropdown = function(e) {
        if (e) e.stopPropagation();
        var dropdown = document.getElementById('sppdocs-role-dropdown');
        if (dropdown) {
            dropdown.classList.toggle('open');
        }
    };

    // Close on outside click or Escape
    document.addEventListener('click', function(e) {
        var dropdown = document.getElementById('sppdocs-role-dropdown');
        if (dropdown && dropdown.classList.contains('open')) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var dropdown = document.getElementById('sppdocs-role-dropdown');
            if (dropdown) dropdown.classList.remove('open');
        }
    });

    window.sppSwitchRole = function(role) {
        role = (role || 'visitor').toLowerCase();
        if (role === 'student' || role === 'guest' || role === 'reader') role = 'visitor';
        if (role === 'teacher' || role === 'faculty') role = 'developer';
        if (role === 'parent' || role === 'guardian' || role === 'client') role = 'stakeholder';
        if (role === 'admin' || role === 'superadmin') role = 'maintainer';

        var dropdown = document.getElementById('sppdocs-role-dropdown');
        if (dropdown) dropdown.classList.remove('open');

        // 1. Persist in storage & cookie
        try {
            localStorage.setItem('sppdocs_active_role', role);
            document.cookie = "sppdocs_active_role=" + role + "; path=/; max-age=2592000; SameSite=Lax";
        } catch(e) {}

        // 2. Update Header Button Label and Icon
        var roleDef = rolesMap[role] || rolesMap['visitor'];
        var labelEl = document.querySelector('.sppdocs-role-active-label');
        var iconEl = document.querySelector('.sppdocs-role-active-icon');
        if (labelEl) labelEl.textContent = roleDef.name;
        if (iconEl) iconEl.textContent = roleDef.icon;

        // 3. Update active states in dropdown and in-page buttons
        document.querySelectorAll('.sppdocs-role-menu-item').forEach(function(item) {
            if (item.getAttribute('data-role-target') === role) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        document.querySelectorAll('.sppdocs-page-role-btn').forEach(function(btn) {
            if (btn.getAttribute('data-role-target') === role) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // 4. Update in-page perspective badges if present
        var badge = document.getElementById('spp-current-role-badge');
        if (badge) {
            badge.textContent = (roleDef.name || role).toUpperCase();
        }

        var desc = document.getElementById('spp-current-role-desc');
        if (desc) {
            if (role === 'visitor') desc.textContent = 'Showing project overview, quickstart guides, and feature highlights for evaluators.';
            else if (role === 'developer') desc.textContent = 'Showing developer guides, architecture deep-dives, code samples, and API references.';
            else if (role === 'stakeholder') desc.textContent = 'Showing delivery milestones, project roadmap, and stakeholder communication threads.';
            else if (role === 'maintainer') desc.textContent = 'Showing system administration, deployment configurations, and triage tools.';
            else desc.textContent = 'Showing all available operating modules across all perspectives.';
        }

        // 5. Filter all [data-role] elements on the page
        document.querySelectorAll('[data-role]').forEach(function(card) {
            var cardRoles = (card.getAttribute('data-role') || '').toLowerCase().split(',').map(function(s) { return s.trim(); });
            if (role === 'all' || cardRoles.indexOf(role) !== -1 || cardRoles.indexOf('all') !== -1) {
                card.style.display = '';
                card.classList.remove('spp-fade-in');
                void card.offsetWidth; // Trigger CSS reflow
                card.classList.add('spp-fade-in');
            } else {
                card.style.display = 'none';
                card.classList.remove('spp-fade-in');
            }
        });

        // 6. Update URL query parameter without full reload
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('role', role);
            window.history.replaceState({}, '', url);
        } catch(e) {}

        // 7. Dispatch custom event for subscribers
        window.dispatchEvent(new CustomEvent('spp:role-changed', { detail: { role: role } }));
    };

    // Auto-init on page load: check URL query or localStorage
    document.addEventListener('DOMContentLoaded', function() {
        var urlParams = new URLSearchParams(window.location.search);
        var initialRole = urlParams.get('role') || localStorage.getItem('sppdocs_active_role');
        if (initialRole && rolesMap[initialRole.toLowerCase()]) {
            sppSwitchRole(initialRole.toLowerCase());
        }
    });
})();
</script>
