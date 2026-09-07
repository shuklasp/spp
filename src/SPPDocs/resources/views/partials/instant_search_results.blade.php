{{-- Instant Search Results Partial --}}
@if(empty($results))
    <div class="sppdocs-search-empty">
        <span class="sppdocs-search-empty-icon">🔎</span>
        <p>No matching sections found for "<em>{{ htmlspecialchars($query ?? '') }}</em>"</p>
        <small>Try using different keywords or ask the AI Assistant</small>
    </div>
@else
    <div class="sppdocs-instant-results-list">
        <div class="sppdocs-cmd-section">Documentation Matches ({{ count($results) }})</div>
        @foreach($results as $res)
            @php
                $chunk = $res['chunk'] ?? [];
                $score = $res['score'] ?? 0;
                $link = \SPP\App::getBaseUrl() . '/project/' . urlencode($project_id) . '/' . ($chunk['page_slug'] ?? '') . (!empty($chunk['anchor']) ? ('#' . $chunk['anchor']) : '');
                $snippet = substr(strip_tags($chunk['text'] ?? ''), 0, 140);
            @endphp
            <a href="{{ $link }}" class="sppdocs-instant-result-item" hx-boost="false" onclick="if(window.closeCommandPalette) window.closeCommandPalette()">
                <div class="sppdocs-instant-result-main">
                    <div class="sppdocs-instant-result-header">
                        <span class="sppdocs-instant-badge-page">{{ $chunk['page_title'] ?? 'Docs' }}</span>
                        <span class="sppdocs-instant-result-arrow">&rarr;</span>
                        <strong class="sppdocs-instant-result-section">{{ $chunk['section_title'] ?? 'Section' }}</strong>
                    </div>
                    @if(!empty($snippet))
                        <div class="sppdocs-instant-result-snippet">{{ $snippet }}...</div>
                    @endif
                </div>
                <div class="sppdocs-instant-result-meta">
                    <span class="sppdocs-instant-score-badge" title="Relevance Score: {{ $score }}">BM25 {{ number_format($score, 1) }}</span>
                    <span class="sppdocs-instant-jump-icon">⏎</span>
                </div>
            </a>
        @endforeach
    </div>
@endif
