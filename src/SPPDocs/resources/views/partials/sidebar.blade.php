<div class="sidebar-search-box" style="margin-bottom: 1.25rem; position: relative;">
    <input type="search" 
           name="query" 
           placeholder="Filter docs..." 
           hx-get="{{ \SPP\App::getBaseUrl() }}/search?project={{ $project_id ?? '' }}"
           hx-trigger="keyup changed delay:300ms, search"
           hx-target="#search-results"
           style="width: 100%; padding: 0.45rem 0.65rem; font-size: 0.85rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-soft, #f8f9fa); color: var(--vp-c-text-1); box-sizing: border-box; outline: none;">
    <div id="search-results" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-top: none; z-index: 100; max-height: 280px; overflow-y: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 0 0 6px 6px;">
    </div>
</div>

@if(isset($project['motto']))
    <div style="font-size: 0.9rem; color: var(--vp-c-text-2); font-style: italic; margin-bottom: 1.5rem; line-height: 1.4; padding-left: 0.5rem; border-left: 2px solid var(--vp-c-divider);">
        {{ $project['motto'] }}
    </div>
@endif

@if(!empty($project['package_name']))
    <div class="sidebar-pkg-card" style="margin-bottom: 1.25rem; padding: 0.75rem; background: var(--vp-c-bg-soft, rgba(249, 115, 22, 0.08)); border: 1px solid rgba(249, 115, 22, 0.25); border-radius: 8px;">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: #f97316; font-weight: 700; margin-bottom: 4px;">📦 SatyaLab Store Package</div>
        <a href="{{ \SPP\App::getBaseUrl('store') }}/package/{{ urlencode($project['package_name']) }}" style="font-size: 0.88rem; font-weight: 600; text-decoration: none; color: var(--vp-c-text-1); display: block; margin-bottom: 6px;">
            {{ $project['package_name'] }} ↗
        </a>
        <div style="font-size: 0.75rem; font-family: monospace; background: rgba(0,0,0,0.2); padding: 3px 6px; border-radius: 4px; color: var(--vp-c-text-2); user-select: all;">
            php spp.php pkg:install {{ $project['package_name'] }}
        </div>
    </div>
@endif

@php
    $sidebarUser = $_SESSION['sppdocs_user'] ?? null;
    $navItems = \App\SPPDocs\Services\MenuRegistry::getSidebarNavigation($project ?? [], $project_id ?? '', $sidebarUser);
    $adminNav = \App\SPPDocs\Services\MenuRegistry::getSidebarAdminNavigation($project ?? [], $project_id ?? '', $sidebarUser);
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

@if(!empty($adminNav['project']) || !empty($adminNav['global']))
    <div style="margin-bottom: 1.5rem;" hx-boost="false">
        <h2>Administration</h2>
        <ul>
            @foreach($adminNav['project'] as $pAdminItem)
                <li>
                    <a href="{{ $pAdminItem['url'] }}" class="sidebar-link-flex">
                        <span>{{ $pAdminItem['icon'] ?? '⚙️' }} {{ $pAdminItem['title'] }}</span>
                    </a>
                </li>
            @endforeach
            @foreach($adminNav['global'] as $gAdminItem)
                <li>
                    <a href="{{ $gAdminItem['url'] }}" class="sidebar-link-flex">
                        <span>{{ $gAdminItem['icon'] ?? '👑' }} {{ $gAdminItem['title'] }}</span>
                    </a>
                </li>
            @endforeach
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

<div id="spp-sidebar-chapters">
    @spppartial('partials/sidebar_chapters.blade.php', [
        'project' => $project,
        'project_id' => $project_id,
        'version' => $version,
        'current_path' => $current_path ?? null
    ])
</div>

