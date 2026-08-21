@foreach($compounds as $compound)
    <a href="@url('compound/' . $compound['id'])" class="compound-card">
        <div class="compound-image-container">
            <img src="@url('assets/compounds/' . $compound['id'] . '.png')" alt="{{ $compound['name'] }}" class="compound-image" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23cbd5e1\' stroke-width=\'2\'><circle cx=\'12\' cy=\'12\' r=\'10\'></circle><line x1=\'12\' y1=\'8\' x2=\'12\' y2=\'12\'></line><line x1=\'12\' y1=\'16\' x2=\'12.01\' y2=\'16\'></line></svg>';" />
        </div>
        
        <div class="compound-details">
            <div class="compound-header">
                <h3 class="compound-name">{{ $compound['name'] }}</h3>
                <div class="compound-formula">{!! $compound['html_formula'] !!}</div>
            </div>
            
            <div class="compound-tags">
                @if($compound['organic'])
                    <span class="tag organic">Organic</span>
                @else
                    <span class="tag inorganic">Inorganic</span>
                @endif
                <span class="tag">{{ $compound['state'] }}</span>
            </div>
            
            <p class="compound-desc">{{ $compound['description'] }}</p>
        </div>
    </a>
@endforeach

@if($hasMore)
    <div id="load-more-container" style="grid-column: 1 / -1; display: flex; justify-content: center; margin-top: 2rem;">
        <button type="button" class="btn btn-primary" 
                hx-get="@url('compounds')" 
                hx-include="#filterForm"
                hx-vals='{"page": {{ $page + 1 }}, "append": "1"}'
                hx-target="#load-more-container"
                hx-swap="outerHTML">
            Load More Compounds...
        </button>
    </div>
@endif
