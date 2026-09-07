@if(empty($results))
    <div class="search-empty" style="padding: 1rem; color: var(--vp-c-text-2); text-align: center;">No results found.</div>
@else
    <ul class="search-list" style="list-style: none; padding: 0; margin: 0;" hx-target="#doc-content" hx-swap="innerHTML" hx-push-url="true">
        @foreach($results as $res)
        <li style="border-bottom: 1px solid var(--vp-c-divider);">
            <a href="{{ \SPP\App::getBaseUrl() }}{{ $res['url'] }}" 
               onclick="document.getElementById('search-results').innerHTML=''"
               style="display: block; padding: 0.75rem 1rem; color: var(--vp-c-text-1); text-decoration: none;">
                <strong>{{ $res['title'] }}</strong>
            </a>
        </li>
        @endforeach
    </ul>
@endif
