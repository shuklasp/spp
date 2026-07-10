<h3 style="margin-top: 0;">Event Stream</h3>
<div style="display: flex; flex-direction: column; gap: 1rem;">
    @forelse($events ?? [] as $event)
        <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-left: 4px solid var(--primary); border-radius: 4px;">
            <div style="font-weight: bold; margin-bottom: 0.5rem; color: #a78bfa;">{{ $event['name'] }}</div>
            <pre style="margin: 0; padding: 0.5rem; background: transparent; border: none; font-size: 0.85rem;">{{ $event['payload'] }}</pre>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">Recorded at: {{ $event['time'] }}</div>
        </div>
    @empty
        <p>No events logged yet for this aggregate.</p>
    @endforelse
</div>
