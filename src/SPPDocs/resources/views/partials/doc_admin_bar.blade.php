{{-- Document Quick Admin Action Bar --}}
<div class="sppdocs-doc-admin-bar">
    @if(!empty($history_url))
        <a href="{{ $history_url }}" class="sppdocs-doc-history-btn" hx-boost="false">🕒 History</a>
    @endif
    @if(!empty($edit_url))
        <a href="{{ $edit_url }}" class="sppdocs-doc-edit-btn" hx-boost="false">✏️ Edit Page</a>
    @endif
</div>
