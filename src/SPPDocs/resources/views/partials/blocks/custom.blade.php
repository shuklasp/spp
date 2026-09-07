{{-- Custom Module Provided Block --}}
@if(!empty($block['custom_html']))
    {!! $block['custom_html'] !!}
@elseif(!empty($block['content']))
    <div class="sppdocs-custom-block" style="font-size: 0.88rem; color: var(--vp-c-text-1);">
        {{ $block['content'] }}
    </div>
@endif