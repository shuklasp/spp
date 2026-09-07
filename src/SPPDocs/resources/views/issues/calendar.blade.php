@extends('layouts.base')
@section('title', 'Calendar - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@php
    $typeColors = ['bug' => '#ef4444', 'feature' => '#22c55e', 'task' => '#3b82f6', 'epic' => '#8b5cf6', 'subtask' => '#94a3b8'];
    $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
    $nextMonth = $month + 1;
    $nextYear = $year;
    if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
    $today = (int) date('j');
    $todayMonth = (int) date('n');
    $todayYear = (int) date('Y');
@endphp

@section('content')
<div style="max-width: 960px; margin: 0 auto;">
    {{-- Header with Project Context --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="margin: 0; font-size: 1.75rem;">📅 Calendar</h1>
                @if(!empty($all_projects) && count($all_projects) > 1)
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--vp-c-bg-mute); border: 1px solid var(--vp-c-divider); padding: 0.25rem 0.6rem; border-radius: 20px;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--vp-c-text-2); text-transform: uppercase;">Project:</span>
                        <select onchange="window.location.href='{{ \SPP\App::url('issues/calendar') }}?projectId=' + encodeURIComponent(this.value)" style="border: none; background: transparent; color: var(--vp-c-text-1); font-weight: 600; font-size: 0.85rem; cursor: pointer; outline: none;">
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
                Due dates and schedule timeline for <strong>{{ $project['title'] ?? $project_id }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; font-size: 0.85rem; align-items: center;">
            <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📝 List</a>
            <a href="{{ \SPP\App::url('issues/board') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📋 Board</a>
            <span style="padding: 0.5rem 0.75rem; background: var(--vp-c-brand); color: white; border-radius: 6px; font-weight: 600;">📅 Calendar</span>
            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('milestones', $project))
                <a href="{{ \SPP\App::url('milestones') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">🏁 Milestones</a>
            @endif
            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('pm_dashboard', $project))
                <a href="{{ \SPP\App::url('pm/dashboard') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📊 Dashboard</a>
            @endif
        </div>
    </div>

    {{-- Month Navigation --}}
    <div style="display: flex; justify-content: center; align-items: center; gap: 2rem; margin-bottom: 1.5rem;">
        <a href="{{ \SPP\App::getBaseUrl() }}/issues/calendar?projectId={{ $project_id }}&month={{ $prevMonth }}&year={{ $prevYear }}" style="padding: 0.5rem 1rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-1); font-weight: 600;">← Prev</a>
        <h2 style="margin: 0; font-size: 1.4rem; border: none;">{{ $monthNames[$month] }} {{ $year }}</h2>
        <a href="{{ \SPP\App::getBaseUrl() }}/issues/calendar?projectId={{ $project_id }}&month={{ $nextMonth }}&year={{ $nextYear }}" style="padding: 0.5rem 1rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-1); font-weight: 600;">Next →</a>
    </div>

    {{-- Calendar Grid --}}
    <div style="display: grid; grid-template-columns: repeat(7, 1fr); border: 1px solid var(--vp-c-divider); border-radius: 10px; overflow: hidden;">
        {{-- Day headers --}}
        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
        <div style="padding: 0.6rem; text-align: center; font-weight: 700; font-size: 0.8rem; color: var(--vp-c-text-3); background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider);">{{ $dayName }}</div>
        @endforeach

        {{-- Empty cells before month starts --}}
        @for($i = 0; $i < $start_weekday; $i++)
        <div style="padding: 0.5rem; min-height: 90px; background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider); border-right: 1px solid var(--vp-c-divider); opacity: 0.3;"></div>
        @endfor

        {{-- Day cells --}}
        @for($day = 1; $day <= $days_in_month; $day++)
        @php
            $isToday = ($day === $today && $month === $todayMonth && $year === $todayYear);
            $dayIssues = $issues_by_day[$day] ?? [];
            $hasOverdue = false;
            foreach ($dayIssues as $di) {
                if (($di['status'] ?? 'open') === 'open') $hasOverdue = true;
            }
            $isPast = mktime(0,0,0,$month,$day,$year) < mktime(0,0,0,$todayMonth,$today,$todayYear);
        @endphp
        <div style="padding: 0.4rem; min-height: 90px; border-bottom: 1px solid var(--vp-c-divider); border-right: 1px solid var(--vp-c-divider); {{ $isToday ? 'background: #eff6ff; outline: 2px solid #3b82f6; outline-offset: -2px; border-radius: 2px;' : '' }} {{ $isPast && $hasOverdue ? 'background: #fef2f2;' : '' }}">
            <div style="font-size: 0.8rem; font-weight: {{ $isToday ? '800' : '600' }}; color: {{ $isToday ? '#3b82f6' : 'var(--vp-c-text-2)' }}; margin-bottom: 0.25rem;">{{ $day }}</div>
            @foreach(array_slice($dayIssues, 0, 3) as $di)
            <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $di['id'] }}"
               style="display: block; padding: 0.15rem 0.35rem; margin-bottom: 0.2rem; border-radius: 4px; font-size: 0.65rem; font-weight: 600; text-decoration: none; color: white; background: {{ $typeColors[$di['type'] ?? 'task'] ?? '#3b82f6' }}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
               title="{{ $di['title'] }}">{{ $di['title'] }}</a>
            @endforeach
            @if(count($dayIssues) > 3)
            <span style="font-size: 0.65rem; color: var(--vp-c-text-3);">+{{ count($dayIssues) - 3 }} more</span>
            @endif
        </div>
        @endfor

        {{-- Empty cells after month ends --}}
        @php $totalCells = $start_weekday + $days_in_month; $remaining = (7 - ($totalCells % 7)) % 7; @endphp
        @for($i = 0; $i < $remaining; $i++)
        <div style="padding: 0.5rem; min-height: 90px; background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider); border-right: 1px solid var(--vp-c-divider); opacity: 0.3;"></div>
        @endfor
    </div>

    {{-- Legend --}}
    <div style="display: flex; gap: 1rem; margin-top: 1rem; font-size: 0.8rem; color: var(--vp-c-text-3); justify-content: center;">
        @foreach($typeColors as $type => $color)
        <span style="display: flex; align-items: center; gap: 0.3rem;"><span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $color }};"></span> {{ ucfirst($type) }}</span>
        @endforeach
    </div>
</div>
@endsection
