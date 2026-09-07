@extends('layouts.base')
@section('title', 'Issues - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@section('content')
<div style="max-width: 960px; margin: 0 auto;">

    {{-- Header with Project Context --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="margin: 0; font-size: 1.75rem;">🎯 Issue Tracker</h1>
                @if(!empty($all_projects) && count($all_projects) > 1)
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--vp-c-bg-mute); border: 1px solid var(--vp-c-divider); padding: 0.25rem 0.6rem; border-radius: 20px;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--vp-c-text-2); text-transform: uppercase;">Project:</span>
                        <select onchange="window.location.href='{{ \SPP\App::url('issues') }}?projectId=' + encodeURIComponent(this.value)" style="border: none; background: transparent; color: var(--vp-c-text-1); font-weight: 600; font-size: 0.85rem; cursor: pointer; outline: none;">
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
                Managing issues, milestones, and sprints for <strong>{{ $project['title'] ?? $project_id }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ \SPP\App::getBaseUrl() }}/issues/teams?projectId={{ $project_id }}" style="padding: 0.6rem 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; text-decoration: none; color: var(--vp-c-text-1); font-weight: 500; font-size: 0.85rem;">👥 Teams</a>
            @if($user || !empty($project['forums_guest_posting']))
                <button onclick="document.getElementById('new-issue-modal').style.display='flex'" style="padding: 0.6rem 1.2rem; background: #22c55e; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.85rem;">+ New Issue</button>
            @endif
        </div>
    </div>

    {{-- View Switcher --}}
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; font-size: 0.85rem;">
        <span style="padding: 0.5rem 0.75rem; background: var(--vp-c-brand); color: white; border-radius: 6px; font-weight: 600;">📝 List</span>
        <a href="{{ \SPP\App::url('issues/board') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📋 Board</a>
        <a href="{{ \SPP\App::url('issues/calendar') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📅 Calendar</a>
        @if(\App\SPPDocs\Services\FeatureManager::isEnabled('milestones', $project))
            <a href="{{ \SPP\App::url('milestones') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">🏁 Milestones</a>
        @endif
        @if(\App\SPPDocs\Services\FeatureManager::isEnabled('pm_dashboard', $project))
            <a href="{{ \SPP\App::url('pm/dashboard') }}?projectId={{ $project_id }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2);">📊 Dashboard</a>
        @endif
    </div>

    {{-- Search & Filters --}}
    <form method="GET" action="{{ \SPP\App::url('issues') }}" style="margin-bottom: 1.5rem;">
        <input type="hidden" name="projectId" value="{{ $project_id }}">
        <input type="hidden" name="status" value="{{ $filter_status }}">
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <input type="text" name="search" value="{{ $search_q }}" placeholder="Search issues..." style="flex: 1; min-width: 200px; padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-size: 0.9rem;">
            <select name="type" style="padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-size: 0.85rem;">
                <option value="">All Types</option>
                <option value="epic" {{ $filter_type === 'epic' ? 'selected' : '' }}>🏔️ Epic</option>
                <option value="task" {{ $filter_type === 'task' ? 'selected' : '' }}>✅ Task</option>
                <option value="bug" {{ $filter_type === 'bug' ? 'selected' : '' }}>🐛 Bug</option>
                <option value="feature" {{ $filter_type === 'feature' ? 'selected' : '' }}>✨ Feature</option>
            </select>
            <select name="assignee" style="padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-size: 0.85rem;">
                <option value="">All Assignees</option>
                @foreach($registered_users as $u)
                    <option value="{{ $u }}" {{ $filter_assignee === $u ? 'selected' : '' }}>{{ $u }}</option>
                @endforeach
            </select>
            @if(!empty($all_labels))
            <select name="label" style="padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-size: 0.85rem;">
                <option value="">All Labels</option>
                @foreach($all_labels as $label)
                    <option value="{{ $label }}" {{ $filter_label === $label ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @endif
            <button type="submit" style="padding: 0.5rem 1rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">Filter</button>
            @if(!empty($search_q) || !empty($filter_type) || !empty($filter_assignee) || !empty($filter_label))
                <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}&status={{ $filter_status }}" style="padding: 0.5rem 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-2); font-size: 0.85rem;">Clear</a>
            @endif
        </div>
    </form>

    {{-- Status Tabs --}}
    @php
        $extraParams = '';
        if (!empty($search_q)) $extraParams .= '&search=' . urlencode($search_q);
        if (!empty($filter_type)) $extraParams .= '&type=' . urlencode($filter_type);
        if (!empty($filter_assignee)) $extraParams .= '&assignee=' . urlencode($filter_assignee);
        if (!empty($filter_label)) $extraParams .= '&label=' . urlencode($filter_label);
    @endphp
    <div style="display: flex; gap: 0; margin-bottom: 1.5rem; border-bottom: 2px solid var(--vp-c-divider);">
        <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}&status=open{{ $extraParams }}"
           style="padding: 0.75rem 1.25rem; text-decoration: none; font-weight: 600; font-size: 0.9rem; border-bottom: 2px solid {{ $filter_status === 'open' ? 'var(--vp-c-brand)' : 'transparent' }}; color: {{ $filter_status === 'open' ? 'var(--vp-c-brand)' : 'var(--vp-c-text-2)' }}; margin-bottom: -2px;">
            🟢 Open ({{ $open_count }})
        </a>
        <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}&status=closed{{ $extraParams }}"
           style="padding: 0.75rem 1.25rem; text-decoration: none; font-weight: 600; font-size: 0.9rem; border-bottom: 2px solid {{ $filter_status === 'closed' ? '#ef4444' : 'transparent' }}; color: {{ $filter_status === 'closed' ? '#ef4444' : 'var(--vp-c-text-2)' }}; margin-bottom: -2px;">
            🔴 Closed ({{ $closed_count }})
        </a>
        <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}&status=all{{ $extraParams }}"
           style="padding: 0.75rem 1.25rem; text-decoration: none; font-weight: 600; font-size: 0.9rem; border-bottom: 2px solid {{ $filter_status === 'all' ? '#6366f1' : 'transparent' }}; color: {{ $filter_status === 'all' ? '#6366f1' : 'var(--vp-c-text-2)' }}; margin-bottom: -2px;">
            All
        </a>
    </div>

    {{-- Issue List --}}
    <div style="display: flex; flex-direction: column; gap: 0; border: 1px solid var(--vp-c-divider); border-radius: 8px; overflow: hidden; background: var(--vp-c-bg);">
        {{-- List Header with Bulk Select All --}}
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 1rem; background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider); font-size: 0.85rem; color: var(--vp-c-text-2);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <input type="checkbox" id="select-all-issues" onclick="toggleSelectAllIssues(this)" style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--vp-c-brand);" title="Select All Issues On This Page">
                <span style="font-weight: 600;">Select All</span>
            </div>
            <div>
                Showing {{ $from_item }}–{{ $to_item }} of {{ $total_items }} issues
            </div>
        </div>

        @forelse($issues as $issue)
            @spppartial('issues/partials/issue_row.blade.php', ['issue' => $issue, 'project_id' => $project_id, 'depth' => 0])
        @empty
            <div style="text-align: center; padding: 3rem; color: var(--vp-c-text-3);">
                <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No issues found.</p>
                <p style="font-size: 0.9rem;">Create a new issue to get started.</p>
            </div>
        @endforelse
    </div>

    {{-- Server-Side Pagination Bar --}}
    @if($total_pages > 1)
    @php
        $pageBaseUrl = \SPP\App::url('issues') . '?projectId=' . urlencode($project_id) . '&status=' . urlencode($filter_status) . $extraParams;
    @endphp
    <div class="sppdocs-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding: 0.75rem 0; flex-wrap: wrap; gap: 1rem;">
        <div style="color: var(--vp-c-text-2); font-size: 0.85rem;">
            Showing <strong>{{ $from_item }}</strong> to <strong>{{ $to_item }}</strong> of <strong>{{ $total_items }}</strong> issues
        </div>
        <div style="display: flex; align-items: center; gap: 0.35rem;">
            {{-- Prev Button --}}
            @if($page > 1)
                <a href="{{ $pageBaseUrl }}&page={{ $page - 1 }}&limit={{ $limit }}" style="padding: 0.4rem 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-1); font-size: 0.85rem;">← Prev</a>
            @else
                <span style="padding: 0.4rem 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; color: var(--vp-c-text-3); font-size: 0.85rem; opacity: 0.5; cursor: not-allowed;">← Prev</span>
            @endif

            {{-- Page Numbers --}}
            @for($p = 1; $p <= $total_pages; $p++)
                @if($p === 1 || $p === $total_pages || ($p >= $page - 2 && $p <= $page + 2))
                    @if($p === $page)
                        <span style="padding: 0.4rem 0.8rem; background: var(--vp-c-brand); color: white; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">{{ $p }}</span>
                    @else
                        <a href="{{ $pageBaseUrl }}&page={{ $p }}&limit={{ $limit }}" style="padding: 0.4rem 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-1); font-size: 0.85rem;">{{ $p }}</a>
                    @endif
                @elseif($p === $page - 3 || $p === $page + 3)
                    <span style="padding: 0.4rem 0.4rem; color: var(--vp-c-text-3);">...</span>
                @endif
            @endfor

            {{-- Next Button --}}
            @if($page < $total_pages)
                <a href="{{ $pageBaseUrl }}&page={{ $page + 1 }}&limit={{ $limit }}" style="padding: 0.4rem 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; text-decoration: none; color: var(--vp-c-text-1); font-size: 0.85rem;">Next →</a>
            @else
                <span style="padding: 0.4rem 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; color: var(--vp-c-text-3); font-size: 0.85rem; opacity: 0.5; cursor: not-allowed;">Next →</span>
            @endif
        </div>
        {{-- Page size selector --}}
        <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: var(--vp-c-text-2);">
            <span>Per page:</span>
            <select onchange="window.location.href='{{ $pageBaseUrl }}&page=1&limit=' + this.value" style="padding: 0.35rem 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; background: var(--vp-c-bg); color: var(--vp-c-text-1); font-size: 0.85rem;">
                <option value="10" {{ $limit == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ $limit == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ $limit == 100 ? 'selected' : '' }}>100</option>
            </select>
        </div>
    </div>
    @endif
</div>

{{-- Floating Bulk Actions Bar --}}
<div id="bulk-action-bar" class="sppdocs-bulk-bar" style="display: none; position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 0.75rem 1.5rem; border-radius: 50px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.4); z-index: 999; align-items: center; gap: 1rem;">
    <span id="bulk-selected-count" style="font-weight: 600; font-size: 0.9rem;">0 selected</span>
    <div style="height: 18px; width: 1px; background: rgba(255,255,255,0.2);"></div>
    <form id="bulk-action-form" method="POST" action="{{ \SPP\App::getBaseUrl() }}/issues/bulk-update" style="display: inline-flex; align-items: center; gap: 0.5rem; margin: 0;">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">
        <input type="hidden" name="action" id="bulk-action-input" value="">
        <div id="bulk-selected-ids-container"></div>
        <button type="button" onclick="submitBulkAction('close')" style="padding: 0.35rem 0.85rem; background: #ef4444; color: white; border: none; border-radius: 20px; font-weight: 600; font-size: 0.8rem; cursor: pointer;">🔴 Close</button>
        <button type="button" onclick="submitBulkAction('reopen')" style="padding: 0.35rem 0.85rem; background: #22c55e; color: white; border: none; border-radius: 20px; font-weight: 600; font-size: 0.8rem; cursor: pointer;">🟢 Reopen</button>
        <button type="button" onclick="if(confirm('Are you sure you want to permanently delete selected issues?')) submitBulkAction('delete')" style="padding: 0.35rem 0.85rem; background: #475569; color: white; border: none; border-radius: 20px; font-weight: 600; font-size: 0.8rem; cursor: pointer;">🗑️ Delete</button>
    </form>
    <button type="button" onclick="clearBulkSelection()" style="background: none; border: none; color: #94a3b8; font-size: 1.1rem; cursor: pointer; padding: 0 0.25rem;" title="Clear Selection">✕</button>
</div>

{{-- New Issue Modal --}}
<div id="new-issue-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
    <div style="background: var(--vp-c-bg); border-radius: 12px; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.25); padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="margin: 0; border: none;">Create New Issue</h2>
            <button onclick="document.getElementById('new-issue-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--vp-c-text-2);">✕</button>
        </div>
        <form id="new-issue-form" action="{{ \SPP\App::getBaseUrl() }}/issues/create" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Title *</label>
                    <input type="text" name="title" required placeholder="Brief summary of the issue" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                        <label style="font-weight: 600; font-size: 0.85rem;">Description</label>
                        <button type="button" id="toggle-new-issue-editor-btn" onclick="toggleNewIssueEditorMode()" style="padding: 0.2rem 0.55rem; font-size: 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; background: var(--vp-c-bg-soft); cursor: pointer; color: inherit;">✨ Rich WYSIWYG</button>
                    </div>
                    <textarea id="new-issue-description" name="description" rows="5" placeholder="Detailed description (supports Markdown)..." style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;"></textarea>
                    <div id="new-issue-wysiwyg-container" style="display: none; border: 1px solid var(--vp-c-divider); border-radius: 6px; overflow: hidden;"></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Type</label>
                        <select name="type" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                            <option value="task">✅ Task</option>
                            <option value="bug">🐛 Bug</option>
                            <option value="feature">✨ Feature Request</option>
                            <option value="epic">🏔️ Epic</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Priority</label>
                        <select name="priority" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                            <option value="low">🟡 Low</option>
                            <option value="medium" selected>🟠 Medium</option>
                            <option value="high">🔴 High</option>
                            <option value="critical">🚨 Critical</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Assign To</label>
                        <select name="assignees[]" multiple style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; min-height: 70px;">
                            @foreach($registered_users as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>
                        <span style="font-size: 0.75rem; color: var(--vp-c-text-3);">Hold Ctrl/Cmd for multiple</span>
                    </div>
                    <div>
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Team</label>
                        <select name="team_id" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                            <option value="">— None —</option>
                            @foreach($teams as $tid => $team)
                                <option value="{{ $tid }}">{{ $team['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Labels</label>
                    <input type="text" name="labels" placeholder="bug, ui, backend (comma separated)" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('new-issue-modal').style.display='none'" style="padding: 0.6rem 1.2rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; background: var(--vp-c-bg); cursor: pointer; font-weight: 500;">Cancel</button>
                <button type="submit" style="padding: 0.6rem 1.5rem; background: #22c55e; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Submit Issue</button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="/school1/public/assets/tui-editor/toastui-editor.min.css">
<script src="/school1/public/assets/tui-editor/toastui-editor-all.min.js"></script>

<script>
// Bulk Selection Logic
function updateBulkActionBar() {
    const checkboxes = document.querySelectorAll('.issue-checkbox:checked');
    const bar = document.getElementById('bulk-action-bar');
    const countEl = document.getElementById('bulk-selected-count');
    const selectAllCb = document.getElementById('select-all-issues');

    if (checkboxes.length > 0) {
        bar.style.display = 'inline-flex';
        countEl.textContent = checkboxes.length + (checkboxes.length === 1 ? ' issue selected' : ' issues selected');
    } else {
        bar.style.display = 'none';
    }

    const allCheckboxes = document.querySelectorAll('.issue-checkbox');
    if (selectAllCb && allCheckboxes.length > 0) {
        selectAllCb.checked = (checkboxes.length === allCheckboxes.length);
    }
}

function toggleSelectAllIssues(masterCb) {
    const checkboxes = document.querySelectorAll('.issue-checkbox');
    checkboxes.forEach(cb => cb.checked = masterCb.checked);
    updateBulkActionBar();
}

function clearBulkSelection() {
    document.querySelectorAll('.issue-checkbox').forEach(cb => cb.checked = false);
    const selectAllCb = document.getElementById('select-all-issues');
    if (selectAllCb) selectAllCb.checked = false;
    updateBulkActionBar();
}

function submitBulkAction(action) {
    const checkboxes = document.querySelectorAll('.issue-checkbox:checked');
    if (!checkboxes.length) return;

    const form = document.getElementById('bulk-action-form');
    const actionInput = document.getElementById('bulk-action-input');
    const idsContainer = document.getElementById('bulk-selected-ids-container');
    
    actionInput.value = action;
    idsContainer.innerHTML = '';
    
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'issue_ids[]';
        input.value = cb.value;
        idsContainer.appendChild(input);
    });

    form.submit();
}

// WYSIWYG Editor on New Issue Modal
(function() {
    let newIssueTuiEditor = null;
    let isWysiwygActive = false;

    window.toggleNewIssueEditorMode = function() {
        const plainTextarea = document.getElementById('new-issue-description');
        const wysiwygContainer = document.getElementById('new-issue-wysiwyg-container');
        const toggleBtn = document.getElementById('toggle-new-issue-editor-btn');
        if (!plainTextarea || !wysiwygContainer || !toggleBtn) return;

        isWysiwygActive = !isWysiwygActive;

        if (isWysiwygActive) {
            plainTextarea.style.display = 'none';
            wysiwygContainer.style.display = 'block';
            toggleBtn.textContent = '📝 Markdown Mode';
            toggleBtn.style.background = 'var(--vp-c-brand)';
            toggleBtn.style.color = 'white';

            if (!newIssueTuiEditor && window.toastui && window.toastui.Editor) {
                newIssueTuiEditor = new toastui.Editor({
                    el: wysiwygContainer,
                    height: '240px',
                    initialEditType: 'wysiwyg',
                    previewStyle: 'tab',
                    initialValue: plainTextarea.value || '',
                });
            } else if (newIssueTuiEditor) {
                newIssueTuiEditor.setMarkdown(plainTextarea.value || '');
            }
        } else {
            if (newIssueTuiEditor) {
                plainTextarea.value = newIssueTuiEditor.getMarkdown();
            }
            wysiwygContainer.style.display = 'none';
            plainTextarea.style.display = 'block';
            toggleBtn.textContent = '✨ Rich WYSIWYG';
            toggleBtn.style.background = 'var(--vp-c-bg-soft)';
            toggleBtn.style.color = 'inherit';
        }
    };

    const form = document.getElementById('new-issue-form');
    if (form) {
        form.addEventListener('submit', function() {
            if (isWysiwygActive && newIssueTuiEditor) {
                const plainTextarea = document.getElementById('new-issue-description');
                if (plainTextarea) {
                    plainTextarea.value = newIssueTuiEditor.getMarkdown();
                }
            }
        });
    }
})();

@if(!empty($_GET['new']))
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('new-issue-modal');
    if (modal) {
        modal.style.display = 'flex';
        const titleInput = modal.querySelector('input[name="title"]');
        if (titleInput) titleInput.focus();
    }
});
@endif
</script>
@endsection
