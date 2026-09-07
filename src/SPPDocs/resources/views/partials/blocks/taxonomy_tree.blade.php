{{-- Taxonomy Category Tree / Tag Cloud Block --}}
@if(!empty($block['tree']))
<div style="background: var(--vp-c-bg-soft); border-radius: 8px; border: 1px solid var(--vp-c-divider); padding: 0.85rem 1rem;">
    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.85rem;">
        @foreach($block['tree'] as $term)
        <li style="margin-bottom: 0.4rem;">
            <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ rawurlencode($project_id) }}/taxonomy/{{ rawurlencode($block['vocab'] ?? 'categories') }}/{{ rawurlencode($term['slug']) }}" style="color: var(--vp-c-brand); text-decoration: none; display: flex; align-items: center; gap: 0.4rem;">
                <span>{{ $term['icon'] ?? '🏷️' }}</span>
                <strong>{{ $term['name'] }}</strong>
            </a>
            @if(!empty($term['children']))
                <ul style="list-style: none; padding-left: 1.25rem; margin: 0.3rem 0 0 0;">
                    @foreach($term['children'] as $child)
                    <li style="margin-bottom: 0.25rem;">
                        <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ rawurlencode($project_id) }}/taxonomy/{{ rawurlencode($block['vocab'] ?? 'categories') }}/{{ rawurlencode($child['slug']) }}" style="color: var(--vp-c-text-2); text-decoration: none; font-size: 0.8rem;">
                            ↳ {{ $child['name'] }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </li>
        @endforeach
    </ul>
</div>
@endif