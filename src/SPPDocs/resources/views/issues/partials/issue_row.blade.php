{{-- Recursive issue row partial --}}
@php
    $typeIcons = ['epic' => '🏔️', 'task' => '✅', 'bug' => '🐛', 'feature' => '✨', 'subtask' => '↳'];
    $priorityColors = ['low' => '#eab308', 'medium' => '#f97316', 'high' => '#ef4444', 'critical' => '#dc2626'];
    $type = $issue['type'] ?? 'task';
    $priority = $issue['priority'] ?? 'medium';
    $status = $issue['status'] ?? 'open';
    $assignees = $issue['assignees'] ?? [];
    $labels = $issue['labels'] ?? [];
    $commentCount = count($issue['comments'] ?? []);
    $subtaskCount = count($issue['children'] ?? []);
    $indent = ($depth ?? 0) * 24;
@endphp

<div class="issue-row-container"
     style="display: flex; align-items: center; padding: 0 1rem; padding-left: {{ 0.85 + ($indent / 16) }}rem; border-bottom: 1px solid var(--vp-c-divider); transition: background 0.15s; {{ $status === 'closed' ? 'opacity: 0.6;' : '' }}"
     onmouseover="this.style.background='var(--vp-c-bg-soft)'" onmouseout="this.style.background='transparent'">

    <input type="checkbox" class="issue-checkbox" value="{{ $issue['id'] }}" style="margin-right: 0.75rem; width: 16px; height: 16px; cursor: pointer; accent-color: var(--vp-c-brand); flex-shrink: 0;" onclick="event.stopPropagation(); if(typeof updateBulkActionBar === 'function') updateBulkActionBar();">

    <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $issue['id'] }}"
       style="display: flex; flex: 1; min-width: 0; align-items: center; gap: 0.75rem; padding: 0.85rem 0; text-decoration: none; color: inherit;">
        
        {{-- Status dot --}}
        <span style="width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; background: {{ $status === 'open' ? '#22c55e' : '#ef4444' }};"></span>

        {{-- Type icon --}}
        <span style="font-size: 0.9rem; flex-shrink: 0;">{{ $typeIcons[$type] ?? '✅' }}</span>

        {{-- Title & Labels --}}
        <div style="flex: 1; min-width: 0;">
            <span style="font-weight: 600; font-size: 0.9rem; {{ $status === 'closed' ? 'text-decoration: line-through;' : '' }}">{{ $issue['title'] }}</span>
            @foreach($labels as $label)
                <span style="display: inline-block; margin-left: 0.35rem; padding: 0.1rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; background: #dbeafe; color: #1e40af;">{{ $label }}</span>
            @endforeach
            <div style="font-size: 0.75rem; color: var(--vp-c-text-3); margin-top: 0.15rem;">
                #{{ substr($issue['id'], -8) }} · opened {{ date('M j', $issue['created_at'] ?? 0) }} by {{ $issue['author'] }}
                @if($subtaskCount > 0)
                    · {{ $subtaskCount }} subtask{{ $subtaskCount > 1 ? 's' : '' }}
                @endif
            </div>
        </div>

        {{-- Priority --}}
        <span style="width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; background: {{ $priorityColors[$priority] ?? '#f97316' }};" title="{{ ucfirst($priority) }} priority"></span>

        {{-- Assignees --}}
        @if(!empty($assignees))
            <div style="display: flex; flex-shrink: 0; margin-left: 0.25rem;">
                @foreach(array_slice($assignees, 0, 3) as $a)
                    <span style="width: 24px; height: 24px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; margin-left: -4px; border: 2px solid var(--vp-c-bg);" title="{{ $a }}">{{ strtoupper(substr($a, 0, 1)) }}</span>
                @endforeach
                @if(count($assignees) > 3)
                    <span style="width: 24px; height: 24px; border-radius: 50%; background: #94a3b8; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; margin-left: -4px; border: 2px solid var(--vp-c-bg);">+{{ count($assignees) - 3 }}</span>
                @endif
            </div>
        @endif

        {{-- Comment count --}}
        @if($commentCount > 0)
            <span style="font-size: 0.8rem; color: var(--vp-c-text-3); flex-shrink: 0; display: flex; align-items: center; gap: 0.2rem;">💬 {{ $commentCount }}</span>
        @endif
    </a>
</div>

{{-- Render subtasks recursively --}}
@if(!empty($issue['children']))
    @foreach($issue['children'] as $child)
        @spppartial('issues/partials/issue_row.blade.php', ['issue' => $child, 'project_id' => $project_id, 'depth' => ($depth ?? 0) + 1])
    @endforeach
@endif
