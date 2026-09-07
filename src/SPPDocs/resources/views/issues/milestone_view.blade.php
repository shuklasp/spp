@extends('layouts.base')
@section('title', $milestone['title'] . ' - Milestones')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@php
    $typeIcons = ['epic' => '🏔️', 'task' => '✅', 'bug' => '🐛', 'feature' => '✨', 'subtask' => '↳'];
@endphp

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <a href="{{ \SPP\App::getBaseUrl() }}/milestones?projectId={{ $project_id }}" style="color: var(--vp-c-text-3); text-decoration: none; font-size: 0.85rem;">← Back to Milestones</a>

    <div style="margin-top: 1rem; margin-bottom: 1.5rem;">
        <h1 style="margin: 0; font-size: 1.6rem;">🏁 {{ $milestone['title'] }}</h1>
        <div style="font-size: 0.85rem; color: var(--vp-c-text-3); margin-top: 0.3rem;">
            @if(!empty($milestone['start_date']) && !empty($milestone['end_date']))
                {{ $milestone['start_date'] }} → {{ $milestone['end_date'] }}
            @elseif(!empty($milestone['end_date']))
                Due {{ $milestone['end_date'] }}
            @endif
            · Created by {{ $milestone['created_by'] ?? 'unknown' }}
            @if($total_hours > 0)
                · ⏱️ {{ $total_hours }}h logged
            @endif
        </div>
    </div>

    @if(!empty($milestone['description']))
    <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; margin-bottom: 1.5rem; color: var(--vp-c-text-2); line-height: 1.6; white-space: pre-wrap;">{{ $milestone['description'] }}</div>
    @endif

    {{-- Progress --}}
    <div style="margin-bottom: 2rem; padding: 1.25rem; background: var(--vp-c-bg-soft); border-radius: 10px;">
        <div style="display: flex; justify-content: space-around; text-align: center; margin-bottom: 1rem;">
            <div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--vp-c-brand);">{{ $total }}</div>
                <div style="font-size: 0.8rem; color: var(--vp-c-text-3);">Total</div>
            </div>
            <div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #22c55e;">{{ $closed }}</div>
                <div style="font-size: 0.8rem; color: var(--vp-c-text-3);">Closed</div>
            </div>
            <div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #f59e0b;">{{ $total - $closed }}</div>
                <div style="font-size: 0.8rem; color: var(--vp-c-text-3);">Open</div>
            </div>
            <div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #6366f1;">{{ $pct }}%</div>
                <div style="font-size: 0.8rem; color: var(--vp-c-text-3);">Complete</div>
            </div>
        </div>
        <div style="height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
            <div style="height: 100%; width: {{ $pct }}%; background: linear-gradient(90deg, #22c55e, #3b82f6); border-radius: 999px; transition: width 0.3s;"></div>
        </div>
    </div>

    {{-- Issue List --}}
    <h3 style="margin: 0 0 1rem 0; border: none;">Issues ({{ $total }})</h3>
    <div style="border: 1px solid var(--vp-c-divider); border-radius: 8px; overflow: hidden;">
        @forelse($issues as $issue)
        <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $issue['id'] }}"
           style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-bottom: 1px solid var(--vp-c-divider); text-decoration: none; color: inherit; {{ ($issue['status'] ?? 'open') === 'closed' ? 'opacity: 0.6;' : '' }}"
           onmouseover="this.style.background='var(--vp-c-bg-soft)'" onmouseout="this.style.background='transparent'">
            <span style="width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; background: {{ ($issue['status'] ?? 'open') === 'open' ? '#22c55e' : '#94a3b8' }};"></span>
            <span>{{ $typeIcons[$issue['type'] ?? 'task'] ?? '✅' }}</span>
            <span style="flex: 1; font-weight: 600; font-size: 0.9rem; {{ ($issue['status'] ?? 'open') === 'closed' ? 'text-decoration: line-through;' : '' }}">{{ $issue['title'] }}</span>
            @if(!empty($issue['assignees']))
            <span style="font-size: 0.75rem; color: var(--vp-c-text-3);">{{ implode(', ', $issue['assignees']) }}</span>
            @endif
        </a>
        @empty
        <div style="padding: 2rem; text-align: center; color: var(--vp-c-text-3);">No issues linked to this milestone yet.</div>
        @endforelse
    </div>

    {{-- Admin Actions --}}
    @if($user && $user['role'] === 'admin')
    <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--vp-c-divider);">
        <form action="{{ \SPP\App::getBaseUrl() }}/milestones/delete" method="POST" onsubmit="return confirm('Delete this milestone? Issues will be unlinked but not deleted.')">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <input type="hidden" name="milestone_id" value="{{ $milestone['id'] }}">
            <button type="submit" style="padding: 0.5rem 1rem; background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; font-weight: 600;">Delete Milestone</button>
        </form>
    </div>
    @endif
</div>
@endsection
