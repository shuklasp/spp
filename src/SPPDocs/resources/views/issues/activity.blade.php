@extends('layouts.base')
@section('title', 'Activity - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@php
    $eventIcons = [
        'issue.created' => '✨', 'issue.closed' => '✅', 'issue.open' => '🔓',
        'issue.commented' => '💬', 'issue.moved' => '➡️', 'issue.time_logged' => '⏱️',
        'milestone.saved' => '🏁',
    ];
    $eventColors = [
        'issue.created' => '#22c55e', 'issue.closed' => '#6366f1', 'issue.open' => '#f59e0b',
        'issue.commented' => '#3b82f6', 'issue.moved' => '#8b5cf6', 'issue.time_logged' => '#f97316',
        'milestone.saved' => '#10b981',
    ];
@endphp

@section('content')
<div style="max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1 style="margin: 0; font-size: 1.75rem;">📜 Activity Feed</h1>
        <a href="{{ \SPP\App::getBaseUrl() }}/pm/dashboard?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2); font-size: 0.85rem;">📊 Dashboard</a>
    </div>

    <div style="border-left: 3px solid var(--vp-c-divider); padding-left: 1.5rem; margin-left: 0.75rem;">
        @forelse($activity as $event)
        @php $color = $eventColors[$event['type'] ?? ''] ?? '#94a3b8'; @endphp
        <div style="position: relative; margin-bottom: 1.5rem;">
            {{-- Timeline dot --}}
            <div style="position: absolute; left: -2.1rem; top: 0.15rem; width: 14px; height: 14px; border-radius: 50%; background: {{ $color }}; border: 3px solid var(--vp-c-bg);"></div>
            <div style="padding: 0.75rem 1rem; background: var(--vp-c-bg-soft); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <span style="font-size: 0.9rem;">
                            {{ $eventIcons[$event['type'] ?? ''] ?? '📌' }}
                            <strong>{{ $event['actor'] ?? 'Unknown' }}</strong>
                            {{ $event['summary'] ?? '' }}
                        </span>
                    </div>
                    <span style="font-size: 0.7rem; color: var(--vp-c-text-3); white-space: nowrap; margin-left: 1rem;">{{ date('M j, g:i A', $event['timestamp'] ?? 0) }}</span>
                </div>
                @if(!empty($event['target']))
                <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $event['target'] }}" style="font-size: 0.75rem; color: var(--vp-c-brand); text-decoration: none; margin-top: 0.2rem; display: inline-block;">View issue →</a>
                @endif
            </div>
        </div>
        @empty
        <div style="text-align: center; padding: 3rem; color: var(--vp-c-text-3);">
            <p style="font-size: 1.1rem;">No activity yet.</p>
            <p style="font-size: 0.9rem;">Activity will appear here as issues are created, updated, and commented on.</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($total_pages > 1)
    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;">
        @if($page > 1)
        <a href="{{ \SPP\App::getBaseUrl() }}/activity?projectId={{ $project_id }}&page={{ $page - 1 }}" style="padding: 0.4rem 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">← Prev</a>
        @endif
        <span style="padding: 0.4rem 0.8rem; color: var(--vp-c-text-3);">Page {{ $page }} of {{ $total_pages }}</span>
        @if($page < $total_pages)
        <a href="{{ \SPP\App::getBaseUrl() }}/activity?projectId={{ $project_id }}&page={{ $page + 1 }}" style="padding: 0.4rem 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">Next →</a>
        @endif
    </div>
    @endif
</div>
@endsection
