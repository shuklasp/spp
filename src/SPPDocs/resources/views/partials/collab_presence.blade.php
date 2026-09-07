{{-- Real-Time Collaborative Presence Bar Partial --}}
<div class="sppdocs-collab-bar" id="sppdocs-collab-bar">
    <div class="sppdocs-collab-left">
        <span class="sppdocs-collab-pulse" id="sppdocs-collab-pulse"></span>
        <span class="sppdocs-collab-status-text" id="sppdocs-collab-status-text">Only you are editing</span>
    </div>

    <div class="sppdocs-collab-peers" id="sppdocs-collab-peers-container">
        @if(!empty($peers))
            @foreach($peers as $peer)
                <span class="sppdocs-collab-avatar" 
                      style="background-color: {{ htmlspecialchars($peer['color'] ?? '#3b82f6') }};" 
                      title="{{ htmlspecialchars($peer['username']) }} ({{ htmlspecialchars($peer['status']) }})">
                    {{ htmlspecialchars($peer['avatar'] ?? 'U') }}
                </span>
            @endforeach
        @endif
    </div>
</div>
