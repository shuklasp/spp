<div class="doc-body">
    @if(!preg_match('/<h1[\s>]/i', $content))
        <h1>{{ $title }}</h1>
    @endif
    {!! $content !!}

    @if(!empty($prev_page) || !empty($next_page))
        <nav class="doc-pagination" aria-label="Chapter Pagination" hx-target="#doc-content" hx-swap="innerHTML" hx-push-url="true">
            @if(!empty($prev_page))
                <a href="{{ $prev_page['url'] }}" class="doc-pager-card doc-pager-prev" rel="prev">
                    <span class="doc-pager-direction">{{ !empty($is_book) ? '← Previous Chapter' : '← Previous' }}</span>
                    <span class="doc-pager-title">{{ $prev_page['title'] }}</span>
                </a>
            @else
                <div class="doc-pager-spacer"></div>
            @endif

            @if(!empty($next_page))
                <a href="{{ $next_page['url'] }}" class="doc-pager-card doc-pager-next" rel="next">
                    <span class="doc-pager-direction">{{ !empty($is_book) ? 'Next Chapter →' : 'Next →' }}</span>
                    <span class="doc-pager-title">{{ $next_page['title'] }}</span>
                </a>
            @endif
        </nav>
    @endif

    @spppartial('partials/doc_feedback.blade.php', ['project_id' => $project_id ?? ($project['id'] ?? ''), 'project' => $project ?? []])
</div>

@if(!empty($is_htmx) || !empty($_SERVER['HTTP_HX_REQUEST']))
<div id="spp-sidebar-chapters" hx-swap-oob="true">
    @spppartial('partials/sidebar_chapters.blade.php', [
        'project' => $project ?? [],
        'project_id' => $project_id ?? ($project['id'] ?? ''),
        'version' => $version ?? ($project['default_version'] ?? 'v1'),
        'current_path' => $current_path ?? null
    ])
</div>
@endif
