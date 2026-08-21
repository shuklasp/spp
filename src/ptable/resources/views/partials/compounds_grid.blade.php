<div class="compounds-grid" id="compoundsGrid">
    @if(count($compounds) === 0)
        <div class="no-results" id="noResults" style="display: block;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h3>No compounds match your filters</h3>
            <p>Try removing some filters to see more results.</p>
        </div>
    @else
        @include('partials.compound_cards', ['compounds' => $compounds, 'hasMore' => $hasMore, 'page' => $page])
    @endif
</div>
