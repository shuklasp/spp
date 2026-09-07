{{-- Document Table of Contents Block --}}
@if(!empty($context['headings']))
<div style="background: var(--vp-c-bg-soft); border-radius: 8px; border: 1px solid var(--vp-c-divider); padding: 0.85rem 1rem;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--vp-c-text-3); margin-bottom: 0.5rem;">On This Page</div>
    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.82rem;">
        @foreach($context['headings'] as $h)
        <li style="margin-bottom: 0.3rem; padding-left: {{ (($h['level'] ?? 2) - 2) * 0.75 }}rem;">
            <a href="#{{ $h['id'] ?? '' }}" style="color: var(--vp-c-text-2); text-decoration: none;">
                {{ $h['text'] ?? '' }}
            </a>
        </li>
        @endforeach
    </ul>
</div>
@endif