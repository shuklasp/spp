<div style="margin-bottom: 1.5rem; position: relative;">
    <input type="search" 
           name="query" 
           placeholder="Search docs..." 
           hx-get="{{ \SPP\App::getBaseUrl() }}/search?project={{ $project_id }}"
           hx-trigger="keyup changed delay:300ms, search"
           hx-target="#search-results"
           style="width: 100%; padding: 0.5rem; border-radius: 4px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); color: var(--vp-c-text-1);">
    
    <div id="search-results" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-top: none; z-index: 100; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
    </div>
</div>

@if(isset($project['motto']))
    <div style="font-size: 0.9rem; color: var(--vp-c-text-2); font-style: italic; margin-bottom: 1.5rem; line-height: 1.4; padding-left: 0.5rem; border-left: 2px solid var(--vp-c-divider);">
        {{ $project['motto'] }}
    </div>
@endif

@php
    $navItems = \App\SPPDocs\Services\FeatureManager::getNavigationItems($project ?? [], $project_id ?? '');
    $sidebarUser = $_SESSION['sppdocs_user'] ?? null;
    $sidebarIsGlobalAdmin = $sidebarUser ? \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($sidebarUser) : false;
    $sidebarIsProjectAdmin = ($sidebarUser && !empty($project_id)) ? \App\SPPDocs\Services\PermissionManager::isProjectAdmin($project_id, $sidebarUser) : false;
@endphp
@if(!empty($navItems) || (isset($project['links']) && is_array($project['links'])))
    <div style="margin-bottom: 1.5rem;">
        <h2>Modules & Navigation</h2>
        <ul>
            @foreach($navItems as $key => $item)
                @if($key !== 'docs')
                    <li>
                        <a href="{{ $item['url'] }}" @if(!empty($item['is_external'])) target="_blank" rel="noopener" @endif class="sidebar-link-flex">
                            <span>{{ $item['icon'] ?? '' }} {{ $item['title'] }}</span>
                            @if(!empty($item['is_external']))
                                <span class="sidebar-ext-arrow">↗</span>
                            @endif
                        </a>
                    </li>
                @endif
            @endforeach
            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('book_export', $project ?? []))
                <li>
                    <a href="@url('export/book?project=' . ($project_id ?? ''))" target="_blank" hx-boost="false" class="sidebar-link-flex">
                        <span>📕 Full-Book PDF</span>
                        <span class="sidebar-ext-arrow">↗</span>
                    </a>
                </li>
            @endif
            @if(isset($project['links']['source']))
                <li><a href="@external_url($project['links']['source'])" target="_blank" rel="noopener">💻 Source Code &rarr;</a></li>
            @endif
            @if(!empty($project['links']['custom']) && is_array($project['links']['custom']))
                @foreach($project['links']['custom'] as $clink)
                    @if(!empty($clink['url']) && !empty($clink['title']))
                        <li>
                            <a href="@external_url($clink['url'])" target="_blank" rel="noopener" class="sidebar-link-flex">
                                <span>{{ $clink['icon'] ?? '🔗' }} {{ $clink['title'] }}</span>
                                <span class="sidebar-ext-arrow">↗</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            @endif
        </ul>
    </div>
@endif

@if($sidebarIsGlobalAdmin || $sidebarIsProjectAdmin)
    <div style="margin-bottom: 1.5rem;" hx-boost="false">
        <h2>Administration</h2>
        <ul>
            @if($sidebarIsProjectAdmin && !empty($project_id))
                <li><a href="@url('admin/project/' . $project_id)">⚙️ Project Settings</a></li>
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('views', $project ?? []))
                    <li><a href="@url('admin/views?project=' . $project_id)">🔍 Views Studio</a></li>
                @endif
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('taxonomy', $project ?? []))
                    <li><a href="@url('admin/taxonomy?project=' . $project_id)">🏷️ Taxonomy Manager</a></li>
                @endif
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('modules', $project ?? []))
                    <li><a href="@url('admin/modules?project=' . $project_id)">🧩 Module Manager</a></li>
                @endif
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('blocks', $project ?? []))
                    <li><a href="@url('admin/blocks?project=' . $project_id)">🧱 Block Placements</a></li>
                @endif
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('schemas', $project ?? []))
                    <li><a href="@url('admin/schemas?project=' . $project_id)">📐 Content Type Schemas</a></li>
                @endif
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('media', $project ?? []))
                    <li><a href="@url('admin/media?project=' . $project_id)">🖼️ Media &amp; Image Styles</a></li>
                @endif
                @if(\App\SPPDocs\Services\FeatureManager::isEnabled('lms', $project ?? []))
                    <li><a href="@url('admin/lms?project=' . $project_id)">🎓 Course Studio &amp; LMS</a></li>
                @endif
            @endif
            @if($sidebarIsGlobalAdmin)
                <li><a href="@url('admin' . (!empty($project_id) ? '?project=' . $project_id : ''))">👑 Global Console</a></li>
                <li><a href="@url('admin/global-settings')">🌐 System Config</a></li>
                <li><a href="@url('admin/users')">👥 User Directory</a></li>
            @endif
        </ul>
    </div>
@endif

@if(isset($project['releases']) && is_array($project['releases']))
    <div style="margin-bottom: 1.5rem;">
        <h2>Releases</h2>
        <ul>
            @foreach($project['releases'] as $release)
                <li><a href="{{ $release['url'] ?? '#' }}" target="_blank" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>{{ $release['version'] }}</span>
                    @if(isset($release['date']))
                        <span style="font-size: 0.75rem; color: var(--vp-c-text-3);">{{ $release['date'] }}</span>
                    @endif
                </a></li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $sidebarData = $project['sidebar'] ?? null;
    if (empty($sidebarData) && !empty($project['pages_dir'])) {
        $pDir = $project['pages_dir'];
        if (!str_starts_with($pDir, '/') && !str_contains($pDir, ':\\')) {
            $pDir = dirname(SPP_BASE_DIR) . '/' . $pDir;
        }
        $sidebarData = \App\SPPDocs\Services\DocNavigationService::buildSidebar($pDir);
    }
@endphp

@if(!empty($sidebarData))
    @foreach($sidebarData as $category => $links)
        <h2>{{ $category }}</h2>
        <ul hx-target="#doc-content" hx-swap="innerHTML" hx-push-url="true">
            @foreach($links as $link)
                @foreach($link as $title => $path)
                    <li>
                        <a href="{{ \SPP\App::getBaseUrl() }}/docs/{{ $project_id }}/{{ $version }}/{{ $path }}"
                           class="{{ (isset($current_path) && $current_path === $path) ? 'active' : '' }}">
                            {{ $title }}
                        </a>
                    </li>
                @endforeach
            @endforeach
        </ul>
    @endforeach
@else
    <p style="font-size: 0.85rem; color: var(--vp-c-text-3);">No documentation pages found.</p>
@endif

