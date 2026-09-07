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
        $blockContext = [
            'project_id' => $activeProjectId,
            'current_path' => $current_path ?? ($_SERVER['REQUEST_URI'] ?? ''),
            'user' => $_SESSION['sppdocs_user'] ?? 'guest',
        ];
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

    <div class="main-layout">
        <aside class="sidebar">
            {{-- Region: sidebar_top --}}
            {!! \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'sidebar_top', $blockContext) !!}

            <!-- Sidebar Section (Demonstrating Blade yields) -->
            @yield('sidebar')

            {{-- Region: sidebar_bottom --}}
            {!! \App\SPPDocs\Services\BlockManager::renderRegion($activeProjectId, 'sidebar_bottom', $blockContext) !!}
        </aside>

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
        document.body.addEventListener('htmx:afterSwap', function(evt) {
            Prism.highlightAll();
            window.renderMermaidDiagrams();
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
            
            // Tell mermaid to render all elements with the 'mermaid' class
            mermaid.init(undefined, document.querySelectorAll('.mermaid'));
        };

        document.addEventListener('DOMContentLoaded', () => {
            window.renderMermaidDiagrams();
        });

        document.body.addEventListener('htmx:afterSwap', function(evt) {
            Prism.highlightAll();
            window.renderMermaidDiagrams();
        });
    </script>

    {{-- Linear-Grade UX: Command Palette & Keyboard Maestro --}}
    @spppartial('partials/command_palette.blade.php', ['project_id' => !empty($project_id) ? $project_id : ($_SESSION['sppdocs_current_project'] ?? 'demo-app')])
    @spppartial('partials/keyboard_shortcuts.blade.php')

    @if(!empty($themeAssets['js']))
        @foreach($themeAssets['js'] as $jsSrc)
            <script src="{{ $jsSrc }}"></script>
        @endforeach
    @endif
</body>
</html>

