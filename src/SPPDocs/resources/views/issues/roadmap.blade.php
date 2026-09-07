@extends('layouts.base')
@section('title', 'Roadmap - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@section('content')
<div style="max-width: 100%;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h1 style="margin: 0; font-size: 1.75rem;">🗺️ Roadmap</h1>
        <div style="display: flex; gap: 0.5rem; font-size: 0.85rem;">
            <a href="{{ \SPP\App::getBaseUrl() }}/issues?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📝 List</a>
            <a href="{{ \SPP\App::getBaseUrl() }}/pm/dashboard?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📊 Dashboard</a>
            <span style="padding: 0.5rem 0.75rem; background: var(--vp-c-brand); color: white; border-radius: 6px; font-weight: 600;">🗺️ Roadmap</span>
        </div>
    </div>

    @if(empty($items))
    <div style="text-align: center; padding: 3rem; color: var(--vp-c-text-3); background: var(--vp-c-bg-soft); border-radius: 10px;">
        <p style="font-size: 1.1rem;">No roadmap items yet.</p>
        <p style="font-size: 0.9rem;">Create milestones with dates or epics with due dates to populate the roadmap.</p>
    </div>
    @else

    {{-- Legend --}}
    <div style="display: flex; gap: 1.5rem; margin-bottom: 1rem; font-size: 0.8rem; color: var(--vp-c-text-3);">
        <span style="display: flex; align-items: center; gap: 0.3rem;"><span style="width: 14px; height: 8px; border-radius: 4px; background: #3b82f6;"></span> Milestone</span>
        <span style="display: flex; align-items: center; gap: 0.3rem;"><span style="width: 14px; height: 8px; border-radius: 4px; background: #8b5cf6;"></span> Epic</span>
        <span style="display: flex; align-items: center; gap: 0.3rem;"><span style="width: 6px; height: 100%; border-left: 2px dashed #ef4444;"></span> Today</span>
    </div>

    {{-- Timeline --}}
    <div style="border: 1px solid var(--vp-c-divider); border-radius: 10px; overflow-x: auto; background: var(--vp-c-bg);">
        {{-- Month markers --}}
        <div style="position: relative; height: 30px; background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider);">
            @foreach($month_markers as $marker)
            <div style="position: absolute; left: {{ $marker['pct'] }}%; top: 0; height: 100%; border-left: 1px solid var(--vp-c-divider); padding-left: 6px; display: flex; align-items: center; font-size: 0.7rem; font-weight: 600; color: var(--vp-c-text-3); white-space: nowrap;">{{ $marker['label'] }}</div>
            @endforeach

            {{-- Today line --}}
            @php
                $todayOffset = (strtotime(date('Y-m-d')) - strtotime($timeline_start)) / 86400;
                $todayPct = ($todayOffset / $total_days) * 100;
            @endphp
            @if($todayPct >= 0 && $todayPct <= 100)
            <div style="position: absolute; left: {{ $todayPct }}%; top: 0; bottom: -1000px; width: 2px; background: #ef4444; z-index: 10; opacity: 0.6;"></div>
            @endif
        </div>

        {{-- Items --}}
        <div style="position: relative; min-height: {{ count($items) * 50 + 20 }}px; padding: 10px 0;">
            @foreach($items as $idx => $item)
            @php
                $startOffset = (strtotime($item['start']) - strtotime($timeline_start)) / 86400;
                $endOffset = (strtotime($item['end']) - strtotime($timeline_start)) / 86400;
                $leftPct = max(0, ($startOffset / $total_days) * 100);
                $widthPct = max(2, (($endOffset - $startOffset) / $total_days) * 100);
                $top = $idx * 46 + 5;
            @endphp
            <div style="position: absolute; left: {{ $leftPct }}%; top: {{ $top }}px; width: {{ $widthPct }}%; height: 36px; display: flex; flex-direction: column; min-width: 100px;">
                <div style="flex: 1; background: {{ $item['color'] }}22; border: 2px solid {{ $item['color'] }}; border-radius: 6px; position: relative; overflow: hidden; display: flex; align-items: center; padding: 0 8px;">
                    {{-- Progress fill --}}
                    <div style="position: absolute; left: 0; top: 0; bottom: 0; width: {{ $item['progress'] }}%; background: {{ $item['color'] }}44;"></div>
                    <span style="position: relative; z-index: 1; font-size: 0.75rem; font-weight: 700; color: {{ $item['color'] }}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $item['type'] === 'milestone' ? '🏁' : '🏔️' }} {{ $item['title'] }} ({{ $item['progress'] }}%)
                    </span>
                </div>
            </div>
            @endforeach

            {{-- Today marker in the items area --}}
            @if($todayPct >= 0 && $todayPct <= 100)
            <div style="position: absolute; left: {{ $todayPct }}%; top: 0; bottom: 0; width: 2px; background: #ef4444; opacity: 0.6;"></div>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
