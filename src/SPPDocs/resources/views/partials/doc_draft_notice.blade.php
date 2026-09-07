<div class="sppdocs-status-notice" style="margin: 2rem 0; padding: 2rem; border-radius: 12px; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); text-align: center;">
    <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">
        @if(($status ?? '') === 'scheduled')
            ⏳
        @else
            📝
        @endif
    </div>
    <h2 style="margin: 0 0 0.5rem 0; font-size: 1.35rem; color: var(--vp-c-text-1);">
        @if(($status ?? '') === 'scheduled')
            Scheduled Content
        @else
            Unpublished Draft
        @endif
    </h2>
    <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.95rem; max-width: 480px; margin: 0 auto; line-height: 1.6;">
        @if(($status ?? '') === 'scheduled')
            This document is scheduled for publication on <strong>{{ $publish_date ?? 'a future date' }}</strong>. It is currently only visible to authorized project editors and preview link holders.
        @else
            This article is currently in draft mode and undergoing review. It will become publicly available once published.
        @endif
    </p>
</div>

