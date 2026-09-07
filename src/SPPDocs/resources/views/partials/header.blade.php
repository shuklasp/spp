@php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
            $headerTargetUrl = !empty($headerProjId) ? ($base_url . '/project/' . $headerProjId) : ($base_url . '/');
        @endphp
        <a href="{{ $headerTargetUrl }}" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit;">
            @if(isset($project['logo']))
                <img src="{{ (str_starts_with($project['logo'], 'http') || str_starts_with($project['logo'], '/')) ? $project['logo'] : $base_url . '/' . ltrim($project['logo'], '/') }}" alt="{{ $project['title'] ?? 'Logo' }}" class="header-logo" />
            @else
                <img src="{{ $base_url }}/assets/logo.jpg" alt="SPP Logo" class="header-logo" />
            @endif
            <h1 class="header-title" style="margin: 0;">
                {{ $project['title'] ?? 'SPP Developer Portal' }}
            </h1>
        </a>
    </div>

    <!-- Center Search Text Box -->
    <div class="sppdocs-header-search-wrap">
        <div class="sppdocs-header-search-box" onclick="openCommandPalette('nav')" title="Search documentation, issues, commands... (Ctrl+K)">
            <span class="sppdocs-search-icon">🔍</span>
            <input type="text" class="sppdocs-header-search-input" placeholder="Search documentation, issues, commands... (Ctrl+K)" readonly onclick="openCommandPalette('nav')" onfocus="this.blur(); openCommandPalette('nav');">
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
        <a href="{{ $base_url }}/" class="header-link-brand">Portal Home</a>

        @if($isLoggedIn)
            @if($isGlobalAdmin)
                <a href="@url('admin' . (!empty($project_id) ? '?project=' . $project_id : ''))" class="header-admin-btn" hx-boost="false" title="Open Admin Console">⚙️ Admin Console</a>
            @elseif($isProjectAdmin && !empty($project_id))
                <a href="@url('admin/project/' . $project_id)" class="header-admin-btn" hx-boost="false" title="Open Project Administration">⚙️ Project Settings</a>
            @endif

            <div class="header-user-badge">
                <span class="header-user-avatar">{{ strtoupper(substr($currentUser, 0, 1)) }}</span>
                <span class="header-user-name">{{ $currentUser }}</span>
                @if($isGlobalAdmin)
                    <span class="header-role-tag super-admin">Super Admin</span>
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
