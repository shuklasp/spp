@extends('layouts.base')
@section('title', 'Dashboard - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@section('content')
<div style="max-width: 960px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1 style="margin: 0; font-size: 1.75rem;">📊 Project Dashboard</h1>
        <div style="display: flex; gap: 0.5rem; font-size: 0.85rem;">
            <a href="{{ \SPP\App::getBaseUrl() }}/issues?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📝 List</a>
            <a href="{{ \SPP\App::getBaseUrl() }}/issues/board?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📋 Board</a>
            <a href="{{ \SPP\App::getBaseUrl() }}/pm/roadmap?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">🗺️ Roadmap</a>
            <a href="{{ \SPP\App::getBaseUrl() }}/activity?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📜 Activity</a>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
        <div style="padding: 1.25rem; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #6366f1); color: white;">
            <div style="font-size: 2rem; font-weight: 800;">{{ $total_count }}</div>
            <div style="font-size: 0.85rem; opacity: 0.9;">Total Issues</div>
        </div>
        <div style="padding: 1.25rem; border-radius: 10px; background: linear-gradient(135deg, #22c55e, #10b981); color: white;">
            <div style="font-size: 2rem; font-weight: 800;">{{ $open_count }}</div>
            <div style="font-size: 0.85rem; opacity: 0.9;">Open</div>
        </div>
        <div style="padding: 1.25rem; border-radius: 10px; background: linear-gradient(135deg, #f59e0b, #f97316); color: white;">
            <div style="font-size: 2rem; font-weight: 800;">{{ $closed_count }}</div>
            <div style="font-size: 0.85rem; opacity: 0.9;">Closed</div>
        </div>
        <div style="padding: 1.25rem; border-radius: 10px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white;">
            <div style="font-size: 2rem; font-weight: 800;">{{ $overdue_count }}</div>
            <div style="font-size: 0.85rem; opacity: 0.9;">Overdue</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        {{-- Velocity Chart --}}
        <div style="padding: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 10px;">
            <h3 style="margin: 0 0 1rem 0; font-size: 1rem; border: none;">📈 Weekly Velocity</h3>
            @php $maxV = max(1, max(array_column($velocity, 'count'))); @endphp
            <div style="display: flex; align-items: flex-end; gap: 6px; height: 120px;">
                @foreach($velocity as $v)
                @php $h = ($v['count'] / $maxV) * 100; @endphp
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%;">
                    <span style="font-size: 0.65rem; font-weight: 700; color: var(--vp-c-text-2); margin-bottom: 2px;">{{ $v['count'] }}</span>
                    <div style="width: 100%; height: {{ max(4, $h) }}%; background: linear-gradient(to top, #3b82f6, #818cf8); border-radius: 4px 4px 0 0;"></div>
                    <span style="font-size: 0.6rem; color: var(--vp-c-text-3); margin-top: 4px; white-space: nowrap;">{{ $v['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Issue Types Donut --}}
        <div style="padding: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 10px;">
            <h3 style="margin: 0 0 1rem 0; font-size: 1rem; border: none;">🧩 By Type</h3>
            @php
                $typeColors = ['task' => '#3b82f6', 'bug' => '#ef4444', 'feature' => '#22c55e', 'epic' => '#8b5cf6'];
                $typeIcons = ['task' => '✅', 'bug' => '🐛', 'feature' => '✨', 'epic' => '🏔️'];
                $totalT = max(1, array_sum($by_type));
            @endphp
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <svg viewBox="0 0 36 36" style="width: 100px; height: 100px; transform: rotate(-90deg);">
                    @php $offset = 0; @endphp
                    @foreach($by_type as $type => $count)
                    @if($count > 0)
                    @php $pct = ($count / $totalT) * 100; $dash = $pct; @endphp
                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="{{ $typeColors[$type] ?? '#94a3b8' }}" stroke-width="3.5" stroke-dasharray="{{ $dash }} {{ 100 - $dash }}" stroke-dashoffset="{{ -$offset }}" />
                    @php $offset += $dash; @endphp
                    @endif
                    @endforeach
                </svg>
                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                    @foreach($by_type as $type => $count)
                    <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem;">
                        <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $typeColors[$type] ?? '#94a3b8' }};"></span>
                        <span>{{ $typeIcons[$type] ?? '' }} {{ ucfirst($type) }}: {{ $count }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        {{-- Workload --}}
        <div style="padding: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 10px;">
            <h3 style="margin: 0 0 1rem 0; font-size: 1rem; border: none;">👥 Workload</h3>
            @php $maxW = max(1, !empty($workload) ? max($workload) : 1); @endphp
            @forelse($workload as $person => $count)
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span style="width: 24px; height: 24px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; flex-shrink: 0;">{{ strtoupper(substr($person, 0, 1)) }}</span>
                <span style="font-size: 0.8rem; width: 60px; flex-shrink: 0;">{{ $person }}</span>
                <div style="flex: 1; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                    <div style="height: 100%; width: {{ ($count / $maxW) * 100 }}%; background: linear-gradient(90deg, #f59e0b, #ef4444); border-radius: 999px;"></div>
                </div>
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--vp-c-text-2); flex-shrink: 0;">{{ $count }}</span>
            </div>
            @empty
            <p style="color: var(--vp-c-text-3); font-size: 0.85rem;">No assignments yet.</p>
            @endforelse
        </div>

        {{-- Priority Distribution --}}
        <div style="padding: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 10px;">
            <h3 style="margin: 0 0 1rem 0; font-size: 1rem; border: none;">🚦 Open by Priority</h3>
            @php
                $prioColors = ['critical' => '#dc2626', 'high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#22c55e'];
                $prioIcons = ['critical' => '🚨', 'high' => '🔴', 'medium' => '🟠', 'low' => '🟡'];
                $maxP = max(1, max($by_priority));
            @endphp
            @foreach($by_priority as $prio => $count)
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.6rem;">
                <span style="font-size: 0.8rem; width: 70px; flex-shrink: 0;">{{ $prioIcons[$prio] ?? '' }} {{ ucfirst($prio) }}</span>
                <div style="flex: 1; height: 12px; background: #f1f5f9; border-radius: 999px; overflow: hidden;">
                    <div style="height: 100%; width: {{ ($count / $maxP) * 100 }}%; background: {{ $prioColors[$prio] ?? '#94a3b8' }}; border-radius: 999px;"></div>
                </div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--vp-c-text-2); flex-shrink: 0; width: 20px; text-align: right;">{{ $count }}</span>
            </div>
            @endforeach
            <div style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--vp-c-text-3);">⏱️ Total time logged: <strong>{{ $total_hours }}h</strong></div>
        </div>
    </div>

    {{-- Recent Activity --}}
    <div style="padding: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1rem; border: none;">📜 Recent Activity</h3>
            <a href="{{ \SPP\App::getBaseUrl() }}/activity?projectId={{ $project_id }}" style="font-size: 0.8rem; color: var(--vp-c-brand); text-decoration: none;">View all →</a>
        </div>
        @forelse($recent_activity as $event)
        <div style="display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.4rem 0; border-bottom: 1px solid var(--vp-c-divider);">
            <span style="width: 24px; height: 24px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; flex-shrink: 0; margin-top: 0.1rem;">{{ strtoupper(substr($event['actor'] ?? '?', 0, 1)) }}</span>
            <div>
                <span style="font-size: 0.85rem;"><strong>{{ $event['actor'] ?? 'Unknown' }}</strong> {{ $event['summary'] ?? '' }}</span>
                <div style="font-size: 0.7rem; color: var(--vp-c-text-3);">{{ date('M j, g:i A', $event['timestamp'] ?? 0) }}</div>
            </div>
        </div>
        @empty
        <p style="color: var(--vp-c-text-3); font-size: 0.85rem;">No activity recorded yet. Activity will appear here as issues are created and updated.</p>
        @endforelse
    </div>
</div>
@endsection
