@extends('layouts.base')
@section('title', 'Milestones - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@php
    $statusColors = ['active' => '#3b82f6', 'completed' => '#22c55e', 'overdue' => '#ef4444', 'upcoming' => '#8b5cf6'];
    $statusIcons = ['active' => '🔵', 'completed' => '✅', 'overdue' => '🔴', 'upcoming' => '🟣'];
@endphp

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    {{-- Header with Project Context --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="margin: 0; font-size: 1.75rem;">🏁 Milestones</h1>
                @if(!empty($all_projects) && count($all_projects) > 1)
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--vp-c-bg-mute); border: 1px solid var(--vp-c-divider); padding: 0.25rem 0.6rem; border-radius: 20px;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--vp-c-text-2); text-transform: uppercase;">Project:</span>
                        <select onchange="window.location.href='{{ \SPP\App::url('milestones') }}?projectId=' + encodeURIComponent(this.value)" style="border: none; background: transparent; color: var(--vp-c-text-1); font-weight: 600; font-size: 0.85rem; cursor: pointer; outline: none;">
                            @foreach($all_projects as $pKey => $pCfg)
                                <option value="{{ $pKey }}" {{ $pKey === $project_id ? 'selected' : '' }}>
                                    📁 {{ $pCfg['title'] ?? $pKey }} ({{ $pKey }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <span style="font-size: 0.85rem; font-weight: 600; background: var(--vp-c-bg-mute); border: 1px solid var(--vp-c-divider); padding: 0.25rem 0.6rem; border-radius: 20px; color: var(--vp-c-text-1);">
                        📁 {{ $project['title'] ?? $project_id }}
                    </span>
                @endif
            </div>
            <p style="margin: 0.35rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.85rem;">
                Milestones, sprints, and delivery targets for <strong>{{ $project['title'] ?? $project_id }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; font-size: 0.85rem; align-items: center;">
            <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📝 List</a>
            <a href="{{ \SPP\App::url('issues/board') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📋 Board</a>
            <a href="{{ \SPP\App::url('issues/calendar') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📅 Calendar</a>
            <span style="padding: 0.5rem 0.75rem; background: var(--vp-c-brand); color: white; border-radius: 6px; font-weight: 600;">🏁 Milestones</span>
            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('pm_dashboard', $project))
                <a href="{{ \SPP\App::url('pm/dashboard') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📊 Dashboard</a>
            @endif
        </div>
    </div>

    @if($user)
    <button onclick="document.getElementById('ms-form').style.display = document.getElementById('ms-form').style.display === 'none' ? 'block' : 'none'" style="padding: 0.6rem 1.2rem; background: #22c55e; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-bottom: 1.5rem;">+ New Milestone</button>

    <div id="ms-form" style="display: none; margin-bottom: 2rem; padding: 1.5rem; background: var(--vp-c-bg-soft); border-radius: 10px; border: 1px solid var(--vp-c-divider);">
        <h3 style="margin: 0 0 1rem 0; border: none;">Create Milestone</h3>
        <form action="{{ \SPP\App::getBaseUrl() }}/milestones/save" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Title *</label>
                    <input type="text" name="title" required placeholder="Sprint 1" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Description</label>
                    <textarea name="description" rows="3" placeholder="Goals for this milestone..." style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Start Date</label>
                        <input type="date" name="start_date" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                    </div>
                    <div>
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">End Date</label>
                        <input type="date" name="end_date" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                    </div>
                </div>
                <button type="submit" style="padding: 0.6rem 1.5rem; background: #22c55e; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; align-self: flex-start;">Create</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Milestone Cards --}}
    @forelse($milestones as $msId => $ms)
    @php $s = $stats[$msId] ?? ['total' => 0, 'closed' => 0, 'pct' => 0, 'status' => 'active']; @endphp
    <div style="margin-bottom: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 10px; overflow: hidden; border-left: 4px solid {{ $statusColors[$s['status']] ?? '#3b82f6' }};">
        <div style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                <div>
                    <a href="{{ \SPP\App::getBaseUrl() }}/milestones/view?projectId={{ $project_id }}&milestoneId={{ $msId }}" style="font-size: 1.2rem; font-weight: 700; text-decoration: none; color: var(--vp-c-text-1);">{{ $ms['title'] }}</a>
                    <div style="font-size: 0.8rem; color: var(--vp-c-text-3); margin-top: 0.2rem;">
                        @if($ms['start_date'] && $ms['end_date'])
                            {{ $ms['start_date'] }} → {{ $ms['end_date'] }}
                        @elseif($ms['end_date'])
                            Due {{ $ms['end_date'] }}
                        @endif
                        · Created by {{ $ms['created_by'] ?? 'unknown' }}
                    </div>
                </div>
                <span style="padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; color: white; background: {{ $statusColors[$s['status']] ?? '#3b82f6' }};">{{ $statusIcons[$s['status']] ?? '' }} {{ ucfirst($s['status']) }}</span>
            </div>

            @if(!empty($ms['description']))
            <p style="color: var(--vp-c-text-2); font-size: 0.9rem; margin: 0 0 0.75rem 0;">{{ $ms['description'] }}</p>
            @endif

            {{-- Progress Bar --}}
            <div style="margin-bottom: 0.25rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--vp-c-text-3); margin-bottom: 0.25rem;">
                    <span>{{ $s['closed'] }}/{{ $s['total'] }} issues completed</span>
                    <span>{{ $s['pct'] }}%</span>
                </div>
                <div style="height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                    <div style="height: 100%; width: {{ $s['pct'] }}%; background: {{ $statusColors[$s['status']] ?? '#3b82f6' }}; border-radius: 999px; transition: width 0.3s;"></div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div style="text-align: center; padding: 3rem; color: var(--vp-c-text-3); background: var(--vp-c-bg-soft); border-radius: 10px;">
        <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No milestones yet.</p>
        <p style="font-size: 0.9rem;">Create a milestone to group and track sets of issues.</p>
    </div>
    @endforelse
</div>
@endsection
