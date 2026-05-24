@extends('layout')

@section('actions')
<a href="/school1/lekhak/admin/content" class="btn btn-secondary">
    ⬅️ Back to Content
</a>
@endsection

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

<div class="glass-panel" style="padding: 20px;">
    @if(empty($revisions))
        <p style="color: var(--text-dim);">No revisions found for this node. (Revisions are tracked automatically when edits are saved).</p>
    @else
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid var(--glass-border);">
                    <th style="padding: 12px; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase;">Date / Time</th>
                    <th style="padding: 12px; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase;">Author ID</th>
                    <th style="padding: 12px; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase;">Changes Made</th>
                    <th style="padding: 12px; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($revisions as $rev)
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.2s;">
                    <td style="padding: 16px 12px; vertical-align: top;">
                        <div style="font-weight: bold; color: var(--text-main);">{{ date('M j, Y', strtotime($rev['revision_timestamp'])) }}</div>
                        <div style="font-size: 0.8rem; color: var(--text-dim);">{{ date('H:i:s', strtotime($rev['revision_timestamp'])) }}</div>
                    </td>
                    <td style="padding: 16px 12px; vertical-align: top; color: var(--text-main);">
                        User #{{ $rev['author_id'] }}
                    </td>
                    <td style="padding: 16px 12px; vertical-align: top;">
                        @php
                            $delta = json_decode($rev['state_delta'], true);
                        @endphp
                        @if(is_array($delta))
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                            @foreach($delta as $field => $changes)
                                <div style="background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); border-radius: 4px; padding: 8px;">
                                    <strong style="color: var(--accent-primary); font-size: 0.85rem; display: block; margin-bottom: 4px;">{{ $field }}</strong>
                                    
                                    @if(isset($changes['old']))
                                    <div style="font-family: monospace; font-size: 0.8rem; color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 2px 4px; border-radius: 2px; margin-bottom: 2px; white-space: pre-wrap; word-break: break-all;">- {{ is_string($changes['old']) ? htmlspecialchars($changes['old']) : json_encode($changes['old']) }}</div>
                                    @endif
                                    
                                    @if(isset($changes['new']))
                                    <div style="font-family: monospace; font-size: 0.8rem; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 2px 4px; border-radius: 2px; white-space: pre-wrap; word-break: break-all;">+ {{ is_string($changes['new']) ? htmlspecialchars($changes['new']) : json_encode($changes['new']) }}</div>
                                    @endif
                                </div>
                            @endforeach
                            </div>
                        @else
                            <span style="color: var(--text-dim); font-style: italic;">No delta parseable</span>
                        @endif
                    </td>
                    <td style="padding: 16px 12px; vertical-align: top; text-align: right;">
                        <a href="/school1/lekhak/admin/content/{{ $node->id }}/revisions/{{ $rev['id'] }}/revert" 
                           class="btn btn-primary" 
                           style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;"
                           onclick="return confirm('Are you sure you want to revert to this state? This will create a new revision tracking the revert.')">
                            ↺ Revert
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
