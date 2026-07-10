<ul style="list-style: none; padding: 0; margin: 0; position: relative; border-left: 2px solid var(--surface-border); margin-left: 1rem;">
    @forelse($history ?? [] as $record)
        <li style="position: relative; padding-left: 1.5rem; margin-bottom: 1.5rem;">
            <div style="position: absolute; left: -0.4rem; top: 0.2rem; width: 0.8rem; height: 0.8rem; border-radius: 50%; background: var(--primary);"></div>
            <div style="font-weight: bold; color: var(--text-main);">{{ $record['transition'] }}</div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">State became: <span style="color: #34d399;">{{ $record['state'] }}</span></div>
            <div style="font-size: 0.8rem; color: rgba(255,255,255,0.3); margin-top: 0.25rem;">{{ $record['time'] }}</div>
        </li>
    @empty
        <li style="padding-left: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">
            No transitions yet.
        </li>
    @endforelse
</ul>
