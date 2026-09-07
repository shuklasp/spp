{{-- Alert / Callout Block --}}
@php
    $alertStyle = $block['style'] ?? ($block['level'] ?? 'info');
    $alertContent = $block['content'] ?? ($block['message'] ?? '');
    $alertIcon = $block['icon'] ?? match($alertStyle) {
        'success' => '✅',
        'warning' => '⚠️',
        'danger', 'error' => '🛑',
        'primary' => '🚀',
        default => 'ℹ️',
    };
@endphp
<div class="adm-alert adm-alert-{{ $alertStyle }}" style="display: flex; gap: 0.85rem; align-items: flex-start; padding: 0.9rem 1.25rem; border-radius: 8px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <span style="font-size: 1.3rem; line-height: 1.3;">{{ $alertIcon }}</span>
    <div style="flex-grow: 1;">
        @if(!empty($block['title']) && empty($block['hide_title']))
            <strong style="display: block; font-size: 0.95rem; margin-bottom: 0.2rem; color: var(--vp-c-text-1);">{{ $block['title'] }}</strong>
        @endif
        <div style="font-size: 0.88rem; color: var(--vp-c-text-1); line-height: 1.5;">
            {!! nl2br(htmlspecialchars($alertContent)) !!}
        </div>
    </div>
</div>