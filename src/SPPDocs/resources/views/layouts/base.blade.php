<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SPPDocs')</title>
    @yield('meta')
    <!-- Include local HTMX as per SPP rules -->
    <script src="{{ defined('APP_BASE_URI') ? APP_BASE_URI : '' }}/sppadmin/js/htmx.min.js"></script>
    <link href="{{ defined('APP_BASE_URI') ? APP_BASE_URI : '' }}/res/shared/css/prism-tomorrow.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="@url('css/sppdocs.css')?v={{ file_exists(APP_BASE_DIR . '/src/SPPDocs/resources/css/sppdocs.css') ? filemtime(APP_BASE_DIR . '/src/SPPDocs/resources/css/sppdocs.css') : 1 }}">
    <link rel="manifest" href="/school1/public/manifest.json">
    <meta name="theme-color" content="#ea580c">
    @php
        $activeProjectId = $project_id ?? ($projectId ?? ($_SESSION['sppdocs_current_project'] ?? 'spp'));
        $themeAssets = \App\SPPDocs\Services\ThemeManager::getThemeAssets($activeProjectId);
        $normPath = $current_path ?? ($_SERVER['REQUEST_URI'] ?? '');
        if (defined('APP_BASE_URI') && APP_BASE_URI !== '') {
            $basePrefix = trim(APP_BASE_URI, '/');
            $normPath = trim($normPath, '/');
            if ($basePrefix !== '' && (str_starts_with($normPath, $basePrefix . '/') || $normPath === $basePrefix)) {
                $normPath = trim(substr($normPath, strlen($basePrefix)), '/');
            }
        }
        $blockContext = [
            'project_id' => $activeProjectId,
            'current_path' => trim(parse_url($normPath, PHP_URL_PATH) ?? '', '/'),
            'user' => $_SESSION['sppdocs_user'] ?? 'guest',
        ];
        $sidebarTop = \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'sidebar_top', $blockContext);
        $sidebarBottom = \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'sidebar_bottom', $blockContext);
        $sidebarSection = trim($this->yieldContent('sidebar'));
        $hasSidebar = !empty($sidebarSection) || !empty(trim($sidebarTop)) || !empty(trim($sidebarBottom));
    @endphp
    @if(!empty($themeAssets['css']))
        @foreach($themeAssets['css'] as $cssHref)
            <link rel="stylesheet" href="{{ $cssHref }}">
        @endforeach
    @endif
</head>
<body hx-boost="true">
    <div id="spp-progress"></div>

    {{-- Region: header_top --}}
    {!! \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'header_top', $blockContext) !!}

    <!-- Header Partial (Demonstrating isolated component injection) -->
    @spppartial('partials/header.blade.php', ['base_url' => $base_url, 'project' => $project ?? null, 'project_id' => $project_id ?? null])

    <div class="main-layout {{ !$hasSidebar ? 'no-sidebar' : '' }}">
        @if($hasSidebar)
        <aside class="sidebar">
            {{-- Region: sidebar_top --}}
            {!! $sidebarTop !!}

            <!-- Sidebar Section (Pre-evaluated cleanly) -->
            {!! $sidebarSection !!}

            {{-- Region: sidebar_bottom --}}
            {!! $sidebarBottom !!}
        </aside>
        @endif

        <div class="content-container">
            <main class="content" id="doc-content">
                {{-- Region: content_top --}}
                {!! \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'content_top', $blockContext) !!}

                <!-- Main Content Section -->
                @yield('content')

                {{-- Region: content_bottom --}}
                {!! \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'content_bottom', $blockContext) !!}
            </main>
        </div>
    </div>

    {{-- Region: footer_bottom --}}
    {!! \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'footer_bottom', $blockContext) !!}
    
    <!-- SPP-UX HTMX Integration -->
    <script>
        document.body.addEventListener('htmx:beforeRequest', function() {
            const p = document.getElementById('spp-progress');
            p.style.opacity = '1';
            p.style.width = '20%';
            window.sppProgressInt = setInterval(() => {
                let w = parseFloat(p.style.width);
                if (w < 85) p.style.width = (w + 5) + '%';
            }, 300);
        });
        document.body.addEventListener('htmx:afterRequest', function() {
            const p = document.getElementById('spp-progress');
            clearInterval(window.sppProgressInt);
            p.style.width = '100%';
            setTimeout(() => { p.style.opacity = '0'; }, 300);
            setTimeout(() => { p.style.width = '0%'; }, 600);
        });
        document.body.addEventListener('htmx:beforeOnLoad', function (evt) {
            // Tell HTMX to swap even if the server returns 404 or 500 error pages
            if (evt.detail.xhr.status >= 400) {
                evt.detail.shouldSwap = true;
                evt.detail.isError = false;
            }
        });
    </script>
    <script src="{{ APP_BASE_URI }}/res/shared/js/prism.min.js"></script>
    <script src="{{ APP_BASE_URI }}/res/shared/js/prism-php.min.js"></script>
    <script src="{{ APP_BASE_URI }}/res/shared/js/prism-bash.min.js"></script>
    <script src="{{ APP_BASE_URI }}/res/shared/js/prism-javascript.min.js"></script>
    <script src="{{ APP_BASE_URI }}/res/shared/js/prism-markup-templating.min.js"></script>
    <script src="{{ APP_BASE_URI }}/res/shared/js/mermaid.min.js"></script>
    <script>
        mermaid.initialize({ startOnLoad: false, theme: 'default' });

        window.renderMermaidDiagrams = function() {
            try {
                const blocks = document.querySelectorAll('pre code.language-mermaid');
                for (let i = 0; i < blocks.length; i++) {
                    const block = blocks[i];
                    const pre = block.parentElement;
                    const code = block.textContent;
                    
                    const container = document.createElement('div');
                    container.className = 'mermaid';
                    container.style.display = 'flex';
                    container.style.justifyContent = 'center';
                    container.style.margin = '2rem 0';
                    container.textContent = code;
                    
                    pre.parentNode.replaceChild(container, pre);
                }
                
                if (typeof mermaid !== 'undefined') {
                    const elements = document.querySelectorAll('.mermaid');
                    if (elements.length > 0) {
                        mermaid.init(undefined, elements);
                    }
                }
            } catch (err) {
                console.warn('Mermaid rendering notice:', err);
            }
        };

        window.updateActiveSidebarLink = function(targetPath) {
            try {
                let curPath = targetPath || window.location.pathname;
                try {
                    curPath = new URL(curPath, window.location.origin).pathname;
                } catch(e) {}
                curPath = curPath.replace(/\/$/, '');
                const curSlug = curPath.split('/').pop();

                const sidebarLinks = document.querySelectorAll('.sidebar ul a');
                let matched = false;
                sidebarLinks.forEach(a => {
                    if (a.classList.contains('sidebar-link-flex')) return;
                    let href = a.getAttribute('href') || '';
                    try {
                        href = new URL(href, window.location.origin).pathname;
                    } catch(e) {}
                    href = href.replace(/\/$/, '');
                    const linkSlug = href.split('/').pop();

                    const isMatch = href && (
                        curPath === href || 
                        curPath.endsWith(href) || 
                        (linkSlug && linkSlug === curSlug)
                    );

                    if (isMatch && !matched) {
                        a.classList.add('active');
                        matched = true;
                        try {
                            a.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        } catch(e) {}
                    } else {
                        a.classList.remove('active');
                    }
                });
            } catch(e) {
                console.warn('Sidebar sync notice:', e);
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            window.updateActiveSidebarLink();
            window.renderMermaidDiagrams();
        });

        document.addEventListener('click', function(e) {
            const link = e.target.closest('.sidebar ul a');
            if (link && !link.classList.contains('sidebar-link-flex')) {
                document.querySelectorAll('.sidebar ul a').forEach(a => {
                    if (!a.classList.contains('sidebar-link-flex')) a.classList.remove('active');
                });
                link.classList.add('active');
            }
        });

        document.body.addEventListener('htmx:afterSwap', function(evt) {
            const newPath = evt.detail?.pathInfo?.requestPath || window.location.pathname;
            window.updateActiveSidebarLink(newPath);
            if (evt.detail?.target?.id === 'doc-content') {
                window.scrollTo(0, 0);
            }
            if (typeof Prism !== 'undefined') {
                try { Prism.highlightAll(); } catch(e) {}
            }
            window.renderMermaidDiagrams();
        });

        document.body.addEventListener('htmx:afterSettle', function(evt) {
            window.updateActiveSidebarLink();
        });

        window.addEventListener('popstate', function() {
            window.updateActiveSidebarLink();
        });
    </script>

    {{-- Linear-Grade UX: Command Palette & Keyboard Maestro --}}
    @spppartial('partials/command_palette.blade.php', ['project_id' => !empty($project_id) ? $project_id : ($_SESSION['sppdocs_current_project'] ?? 'demo-app'), 'project' => $project ?? []])
    @spppartial('partials/keyboard_shortcuts.blade.php', ['project_id' => !empty($project_id) ? $project_id : ($_SESSION['sppdocs_current_project'] ?? 'demo-app'), 'project' => $project ?? []])

    @if(!empty($themeAssets['js']))
        @foreach($themeAssets['js'] as $jsSrc)
            <script src="{{ $jsSrc }}"></script>
        @endforeach
    @endif
</body>
</html>

