<div class="sppdocs-preview-banner" style="position: sticky; top: 0; z-index: 1000; background: #3b82f6; color: white; padding: 0.6rem 1rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; font-weight: 500; box-shadow: 0 2px 10px rgba(0,0,0,0.15);">
    <div style="display: flex; align-items: center; gap: 0.6rem;">
        <span style="font-size: 1rem;">👁️</span>
        <span><strong>Preview Mode</strong>: You are viewing an unreleased draft of <em>{{ $docTitle ?? 'this document' }}</em>.</span>
        <span style="background: rgba(255,255,255,0.25); padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">Status: {{ strtoupper($frontmatter['status'] ?? 'DRAFT') }}</span>
    </div>
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span style="opacity: 0.85; font-size: 0.75rem;">Shared via secure token</span>
        @if(!empty($editUrl))
            <a href="{{ $editUrl }}" style="background: white; color: #2563eb; padding: 0.25rem 0.65rem; border-radius: 4px; text-decoration: none; font-size: 0.75rem; font-weight: 600;">Edit Document</a>
        @endif
    </div>
</div>

