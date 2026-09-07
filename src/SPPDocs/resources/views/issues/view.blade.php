@extends('layouts.base')
@section('title', $issue['title'] . ' - Issues')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@php
    $typeIcons = ['epic' => '🏔️', 'task' => '✅', 'bug' => '🐛', 'feature' => '✨', 'subtask' => '↳'];
    $priorityLabels = ['low' => ['🟡', 'Low'], 'medium' => ['🟠', 'Medium'], 'high' => ['🔴', 'High'], 'critical' => ['🚨', 'Critical']];
    $type = $issue['type'] ?? 'task';
    $status = $issue['status'] ?? 'open';
    $priority = $issue['priority'] ?? 'medium';
@endphp

@section('content')
<div style="max-width: 960px; margin: 0 auto;">

    {{-- Breadcrumb --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ \SPP\App::getBaseUrl() }}/issues?projectId={{ $project_id }}" style="color: var(--vp-c-text-3); text-decoration: none; font-size: 0.85rem;">← Back to Issues</a>
        @if($parent_issue)
            <span style="color: var(--vp-c-text-3); font-size: 0.85rem;"> · Parent: 
                <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $parent_issue['id'] }}" style="color: var(--vp-c-brand);">{{ $parent_issue['title'] }}</a>
            </span>
        @endif
    </div>

    {{-- Header --}}
    <div style="display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 0.5rem;">
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                <span style="font-size: 1.1rem;">{{ $typeIcons[$type] ?? '✅' }}</span>
                <span style="padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; color: white; background: {{ $status === 'open' ? '#22c55e' : '#ef4444' }};">{{ ucfirst($status) }}</span>
                <span style="padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; background: #f1f5f9; color: #475569;">{{ ucfirst($type) }}</span>
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--vp-c-brand);">#{{ $issue['number'] ?? substr($issue['id'], -8) }}</span>
                @php
                    $branchSlug = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower(trim($issue['title'])));
                    $branchSlug = trim(substr($branchSlug, 0, 40), '-');
                    $branchName = 'feature/' . ($issue['number'] ?? substr($issue['id'], -6)) . '-' . $branchSlug;
                @endphp
                <button type="button" onclick="navigator.clipboard.writeText('git checkout -b {{ $branchName }}'); this.innerText='✓ Copied!'; setTimeout(() => this.innerText='📋 git branch', 2000)" style="padding: 0.2rem 0.55rem; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.75rem; cursor: pointer; color: var(--vp-c-text-2); font-family: monospace;" title="Click to copy git checkout -b command">📋 git branch</button>
            </div>
            <h1 style="margin: 0; font-size: 1.6rem; line-height: 1.3; {{ $status === 'closed' ? 'text-decoration: line-through; opacity: 0.7;' : '' }}">{{ $issue['title'] }}</h1>
            <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--vp-c-text-3);">
                Opened by <strong>{{ $issue['author'] }}</strong> on {{ date('M j, Y \a\t g:i A', $issue['created_at'] ?? 0) }}
                @if(($issue['updated_at'] ?? 0) > ($issue['created_at'] ?? 0))
                    · Updated {{ date('M j, Y', $issue['updated_at']) }}
                @endif
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 280px; gap: 2rem; margin-top: 1.5rem;">

        {{-- ====== LEFT COLUMN: Description + Timeline ====== --}}
        <div>
            {{-- Description --}}
            @if(!empty($issue['description']))
            <div class="sppdocs-markdown-body" style="padding: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; margin-bottom: 1.5rem; background: var(--vp-c-bg); line-height: 1.7;">
                {!! \App\SPPDocs\Services\IssueMarkdownService::render($issue['description'], $project_id) !!}
            </div>
            @endif

            {{-- Subtasks Section --}}
            @if(!empty($subtasks) || ($type !== 'subtask'))
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <h3 style="margin: 0; font-size: 1rem; border: none;">Subtasks ({{ count($subtasks) }})</h3>
                    @if($user && $status === 'open')
                        <button onclick="document.getElementById('subtask-form').style.display = document.getElementById('subtask-form').style.display === 'none' ? 'block' : 'none'" style="font-size: 0.8rem; padding: 0.3rem 0.7rem; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 6px; cursor: pointer;">+ Add Subtask</button>
                    @endif
                </div>

                {{-- Subtask progress bar --}}
                @if(!empty($subtasks))
                @php
                    $closedSubs = count(array_filter($subtasks, fn($s) => ($s['status'] ?? 'open') === 'closed'));
                    $totalSubs = count($subtasks);
                    $pct = $totalSubs > 0 ? round(($closedSubs / $totalSubs) * 100) : 0;
                @endphp
                <div style="margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--vp-c-text-3); margin-bottom: 0.25rem;">
                        <span>{{ $closedSubs }}/{{ $totalSubs }} completed</span>
                        <span>{{ $pct }}%</span>
                    </div>
                    <div style="height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                        <div style="height: 100%; width: {{ $pct }}%; background: #22c55e; border-radius: 999px; transition: width 0.3s;"></div>
                    </div>
                </div>
                @endif

                {{-- Subtask list --}}
                @foreach($subtasks as $sub)
                    <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid var(--vp-c-divider);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: {{ ($sub['status'] ?? 'open') === 'open' ? '#22c55e' : '#94a3b8' }}; flex-shrink: 0;"></span>
                        <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $sub['id'] }}" style="text-decoration: none; color: var(--vp-c-text-1); font-size: 0.9rem; {{ ($sub['status'] ?? 'open') === 'closed' ? 'text-decoration: line-through; opacity: 0.6;' : '' }}">{{ $sub['title'] }}</a>
                        @if(!empty($sub['assignees']))
                            <span style="margin-left: auto; font-size: 0.75rem; color: var(--vp-c-text-3);">{{ implode(', ', $sub['assignees']) }}</span>
                        @endif
                    </div>
                @endforeach

                {{-- Add Subtask Form --}}
                <div id="subtask-form" style="display: none; margin-top: 0.75rem; padding: 1rem; background: var(--vp-c-bg-soft); border-radius: 8px;">
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/create" method="POST">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="parent_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="type" value="subtask">
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="title" required placeholder="Subtask title..." style="flex: 1; padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
                            <button type="submit" style="padding: 0.5rem 1rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Add</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            {{-- Epic Children (if this IS an epic) --}}
            @if(($issue['type'] ?? 'task') === 'epic' && !empty($epic_children))
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin: 0 0 0.75rem 0; font-size: 1rem; border: none;">Tasks in this Epic ({{ count($epic_children) }})</h3>
                @foreach($epic_children as $child)
                    <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid var(--vp-c-divider);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: {{ ($child['status'] ?? 'open') === 'open' ? '#22c55e' : '#94a3b8' }};"></span>
                        <span>{{ $typeIcons[$child['type'] ?? 'task'] ?? '✅' }}</span>
                        <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $child['id'] }}" style="text-decoration: none; color: var(--vp-c-text-1); font-size: 0.9rem;">{{ $child['title'] }}</a>
                    </div>
                @endforeach
            </div>
            @endif

            {{-- Attachments --}}
            @if(!empty($issue['attachments']) || $user)
            <div style="margin-top: 1.5rem; padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.9rem; border: none;">📎 Attachments ({{ count($issue['attachments'] ?? []) }})</h4>
                @foreach($issue['attachments'] ?? [] as $att)
                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; border-bottom: 1px solid var(--vp-c-divider);">
                    @php $isImage = str_starts_with($att['mime'] ?? '', 'image/'); @endphp
                    <span>{{ $isImage ? '🖼️' : '📄' }}</span>
                    <a href="{{ \SPP\App::getBaseUrl() }}/uploads/serve?projectId={{ $project_id }}&file={{ urlencode($att['stored_name']) }}" target="_blank" style="font-size: 0.85rem; color: var(--vp-c-brand); text-decoration: none; flex: 1;">{{ $att['filename'] }}</a>
                    <span style="font-size: 0.7rem; color: var(--vp-c-text-3);">{{ round(($att['size'] ?? 0) / 1024) }}KB · {{ $att['uploaded_by'] ?? '' }}</span>
                </div>
                @endforeach
                @if($user)
                <form action="{{ \SPP\App::getBaseUrl() }}/uploads/store" method="POST" enctype="multipart/form-data" style="margin-top: 0.75rem; display: flex; gap: 0.5rem; align-items: center;">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                    <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                    <input type="file" name="attachment" required style="font-size: 0.8rem; flex: 1;">
                    <button type="submit" style="padding: 0.35rem 0.75rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Upload</button>
                </form>
                @endif
            </div>
            @endif

            {{-- Timeline / Comments --}}
            <h3 style="margin: 2rem 0 1rem 0; font-size: 1rem; border: none;">Activity</h3>
            <div style="border-left: 2px solid var(--vp-c-divider); margin-left: 0.75rem; padding-left: 1.25rem;">
                @forelse($issue['comments'] ?? [] as $comment)
                    @if(($comment['type'] ?? '') === 'event')
                        {{-- System event --}}
                        <div style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; margin-left: -1.65rem;"></span>
                            <span style="font-size: 0.8rem; color: var(--vp-c-text-3); font-style: italic;">{{ $comment['content'] }} · {{ date('M j, g:i A', $comment['timestamp'] ?? $comment['created_at'] ?? time()) }}</span>
                        </div>
                    @else
                        {{-- User comment --}}
                        <div style="margin-bottom: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; overflow: hidden;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 1rem; background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider);">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="width: 24px; height: 24px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700;">{{ strtoupper(substr($comment['author'], 0, 1)) }}</span>
                                    <strong style="font-size: 0.85rem;">{{ $comment['author'] }}</strong>
                                </div>
                                <span style="font-size: 0.75rem; color: var(--vp-c-text-3);">{{ date('M j, Y g:i A', $comment['timestamp'] ?? $comment['created_at'] ?? time()) }}</span>
                            </div>
                            <div class="sppdocs-markdown-body" style="padding: 1rem; font-size: 0.9rem; line-height: 1.6;">
                                {!! \App\SPPDocs\Services\IssueMarkdownService::render($comment['content'], $project_id) !!}
                            </div>
                        </div>
                    @endif
                @empty
                    <p style="color: var(--vp-c-text-3); font-size: 0.85rem;">No activity yet.</p>
                @endforelse
            </div>

            {{-- Add Comment Form & Status Controls (Zero Nested Forms) --}}
            @if($status === 'open' && ($user || !empty($project['forums_guest_posting'])))
            <div style="margin-top: 1.5rem; padding: 1.25rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; background: var(--vp-c-bg);">
                <form action="{{ \SPP\App::getBaseUrl() }}/issues/comment" method="POST" id="issue-comment-form">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                    <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--vp-c-text-2);">Write a comment</span>
                        <button type="button" id="toggle-comment-editor-btn" onclick="toggleCommentEditorMode()" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 4px; cursor: pointer;">✨ Rich WYSIWYG</button>
                    </div>

                    <textarea name="content" id="comment-plain-textarea" required rows="4" placeholder="Leave a comment... (Markdown & @mentions supported)" style="width: 100%; padding: 0.75rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; margin-bottom: 0.5rem; font-family: inherit; font-size: 0.9rem; box-sizing: border-box; line-height: 1.5;"></textarea>
                    
                    <div id="comment-wysiwyg-container" style="display: none; margin-bottom: 0.75rem; border-radius: 8px; overflow: hidden; border: 1px solid var(--vp-c-divider);"></div>

                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" style="padding: 0.6rem 1.2rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Comment</button>
                    </div>
                </form>

                @if($user)
                <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px dashed var(--vp-c-divider); display: flex; justify-content: flex-end;">
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="toggle_status">
                        <button type="submit" style="padding: 0.45rem 1rem; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">Close Issue</button>
                    </form>
                </div>
                @endif
            </div>
            @elseif($status === 'closed' && $user)
            <div style="margin-top: 1.5rem;">
                <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST">
                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                    <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                    <input type="hidden" name="action" value="toggle_status">
                    <button type="submit" style="padding: 0.6rem 1.2rem; background: #22c55e; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Reopen Issue</button>
                </form>
            </div>
            @endif
        </div>

        {{-- ====== RIGHT COLUMN: Sidebar Properties ====== --}}
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            {{-- Assignees --}}
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Assignees</h4>
                @if(!empty($issue['assignees']))
                    @foreach($issue['assignees'] as $a)
                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0;">
                            <span style="width: 24px; height: 24px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700;">{{ strtoupper(substr($a, 0, 1)) }}</span>
                            <span style="font-size: 0.85rem;">{{ $a }}</span>
                        </div>
                    @endforeach
                @else
                    <p style="color: var(--vp-c-text-3); font-size: 0.8rem; margin: 0;">No one assigned</p>
                @endif
                @if($user)
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Change</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="assign">
                        <select name="assignees[]" multiple style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem; min-height: 60px;">
                            @foreach($registered_users as $u)
                                <option value="{{ $u }}" {{ in_array($u, $issue['assignees'] ?? []) ? 'selected' : '' }}>{{ $u }}</option>
                            @endforeach
                        </select>
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Update</button>
                    </form>
                </details>
                @endif
            </div>

            {{-- Team --}}
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Team</h4>
                @php $teamName = isset($issue['team_id']) && isset($teams[$issue['team_id']]) ? $teams[$issue['team_id']]['name'] : null; @endphp
                <p style="margin: 0; font-size: 0.85rem;">{{ $teamName ?? 'None' }}</p>
                @if($user)
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Change</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="assign_team">
                        <select name="team_id" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                            <option value="">— None —</option>
                            @foreach($teams as $tid => $team)
                                <option value="{{ $tid }}" {{ ($issue['team_id'] ?? '') === $tid ? 'selected' : '' }}>{{ $team['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Update</button>
                    </form>
                </details>
                @endif
            </div>

            {{-- Priority --}}
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Priority</h4>
                <p style="margin: 0; font-size: 0.85rem;">{{ $priorityLabels[$priority][0] ?? '🟠' }} {{ $priorityLabels[$priority][1] ?? 'Medium' }}</p>
                @if($user)
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Change</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="set_priority">
                        <select name="priority" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                            <option value="low" {{ $priority === 'low' ? 'selected' : '' }}>🟡 Low</option>
                            <option value="medium" {{ $priority === 'medium' ? 'selected' : '' }}>🟠 Medium</option>
                            <option value="high" {{ $priority === 'high' ? 'selected' : '' }}>🔴 High</option>
                            <option value="critical" {{ $priority === 'critical' ? 'selected' : '' }}>🚨 Critical</option>
                        </select>
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Update</button>
                    </form>
                </details>
                @endif
            </div>

            {{-- Labels --}}
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Labels</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                    @forelse($issue['labels'] ?? [] as $label)
                        <span style="padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: #dbeafe; color: #1e40af;">{{ $label }}</span>
                    @empty
                        <p style="color: var(--vp-c-text-3); font-size: 0.8rem; margin: 0;">None</p>
                    @endforelse
                </div>
                @if($user)
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Change</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="set_labels">
                        <input type="text" name="labels" value="{{ implode(', ', $issue['labels'] ?? []) }}" placeholder="bug, ui, backend" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Update</button>
                    </form>
                </details>
                @endif
            </div>

            {{-- Link to Epic --}}
            @if(!empty($epics) && $type !== 'epic')
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Epic</h4>
                @if(!empty($issue['epic_id']))
                    @php $linkedEpic = array_values(array_filter($epics, fn($e) => $e['id'] === $issue['epic_id'])); @endphp
                    @if(!empty($linkedEpic))
                        <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $linkedEpic[0]['id'] }}" style="font-size: 0.85rem; text-decoration: none; color: var(--vp-c-brand);">🏔️ {{ $linkedEpic[0]['title'] }}</a>
                    @endif
                @else
                    <p style="color: var(--vp-c-text-3); font-size: 0.8rem; margin: 0;">Not linked</p>
                @endif
                @if($user)
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Link</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="link_epic">
                        <select name="epic_id" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                            <option value="">— None —</option>
                            @foreach($epics as $epic)
                                <option value="{{ $epic['id'] }}" {{ ($issue['epic_id'] ?? '') === $epic['id'] ? 'selected' : '' }}>{{ $epic['title'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Update</button>
                    </form>
                </details>
                @endif
            </div>
            @endif
            {{-- Due Date --}}
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Due Date</h4>
                @if(!empty($issue['due_date']))
                    @php $dueTs = is_numeric($issue['due_date']) ? $issue['due_date'] : strtotime($issue['due_date']); $overdue = $dueTs && $dueTs < time() && $status === 'open'; @endphp
                    <p style="margin: 0; font-size: 0.85rem; {{ $overdue ? 'color: #ef4444; font-weight: 700;' : '' }}">📅 {{ date('M j, Y', $dueTs) }}{{ $overdue ? ' (Overdue!)' : '' }}</p>
                @else
                    <p style="color: var(--vp-c-text-3); font-size: 0.8rem; margin: 0;">Not set</p>
                @endif
                @if($user)
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Change</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="set_due_date">
                        <input type="date" name="due_date" value="{{ !empty($issue['due_date']) ? (is_numeric($issue['due_date']) ? date('Y-m-d', $issue['due_date']) : $issue['due_date']) : '' }}" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Update</button>
                    </form>
                </details>
                @endif
            </div>

            {{-- Milestone --}}
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Milestone</h4>
                @if(!empty($issue['milestone_id']) && isset($milestones[$issue['milestone_id']]))
                    <a href="{{ \SPP\App::getBaseUrl() }}/milestones/view?projectId={{ $project_id }}&milestoneId={{ $issue['milestone_id'] }}" style="font-size: 0.85rem; text-decoration: none; color: var(--vp-c-brand);">🏁 {{ $milestones[$issue['milestone_id']]['title'] }}</a>
                @else
                    <p style="color: var(--vp-c-text-3); font-size: 0.8rem; margin: 0;">None</p>
                @endif
                @if($user && !empty($milestones))
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Change</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="set_milestone">
                        <select name="milestone_id" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                            <option value="">— None —</option>
                            @foreach($milestones as $msId => $ms)
                                <option value="{{ $msId }}" {{ ($issue['milestone_id'] ?? '') === $msId ? 'selected' : '' }}>{{ $ms['title'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Update</button>
                    </form>
                </details>
                @endif
            </div>

            {{-- Time Tracking --}}
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Time Tracked</h4>
                <p style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--vp-c-brand);">⏱️ {{ $total_hours }}h</p>
                @if(!empty($issue['time_logs']))
                <div style="margin-top: 0.5rem; max-height: 120px; overflow-y: auto;">
                    @foreach(array_reverse($issue['time_logs']) as $log)
                    <div style="font-size: 0.75rem; color: var(--vp-c-text-3); padding: 0.2rem 0; border-bottom: 1px solid var(--vp-c-divider);">
                        <strong>{{ $log['user'] }}</strong> · {{ $log['hours'] }}h · {{ date('M j', $log['logged_at']) }}
                        @if(!empty($log['description'])) <br>{{ $log['description'] }} @endif
                    </div>
                    @endforeach
                </div>
                @endif
                @if($user)
                <details style="margin-top: 0.5rem;">
                    <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer;">Log Time</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/log-time" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="number" name="hours" step="0.25" min="0.25" required placeholder="Hours" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem; margin-bottom: 0.35rem;">
                        <input type="text" name="description" placeholder="What did you work on?" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: #22c55e; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Log</button>
                    </form>
                </details>
                @endif
            </div>

            {{-- Merge Issues --}}
            @if($user && $status === 'open')
            <div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.8rem; text-transform: uppercase; color: var(--vp-c-text-3); letter-spacing: 0.05em;">Merge Into This</h4>
                <details>
                    <summary style="font-size: 0.8rem; color: #f97316; cursor: pointer;">Merge another issue</summary>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/update" method="POST" style="margin-top: 0.5rem;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="issue_id" value="{{ $issue['id'] }}">
                        <input type="hidden" name="action" value="merge">
                        <input type="text" name="source_issue_id" placeholder="Issue ID to merge" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
                        <button type="submit" style="margin-top: 0.35rem; width: 100%; padding: 0.35rem; background: #f97316; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Merge</button>
                    </form>
                </details>
            </div>
            @endif

        </div>
    </div>
</div>

<link rel="stylesheet" href="/school1/public/assets/tui-editor/toastui-editor.min.css" />
<script src="/school1/public/assets/tui-editor/toastui-editor-all.min.js"></script>
<script>
(function() {
    let commentTuiEditor = null;
    let isWysiwygActive = false;

    window.toggleCommentEditorMode = function() {
        const plainTextarea = document.getElementById('comment-plain-textarea');
        const wysiwygContainer = document.getElementById('comment-wysiwyg-container');
        const toggleBtn = document.getElementById('toggle-comment-editor-btn');
        if (!plainTextarea || !wysiwygContainer || !toggleBtn) return;

        isWysiwygActive = !isWysiwygActive;

        if (isWysiwygActive) {
            plainTextarea.style.display = 'none';
            wysiwygContainer.style.display = 'block';
            toggleBtn.textContent = '📝 Markdown Mode';
            toggleBtn.style.background = 'var(--vp-c-brand)';
            toggleBtn.style.color = 'white';

            if (!commentTuiEditor && window.toastui && window.toastui.Editor) {
                commentTuiEditor = new toastui.Editor({
                    el: wysiwygContainer,
                    height: '220px',
                    initialEditType: 'wysiwyg',
                    previewStyle: 'tab',
                    initialValue: plainTextarea.value || '',
                });
            } else if (commentTuiEditor) {
                commentTuiEditor.setMarkdown(plainTextarea.value || '');
            }
        } else {
            if (commentTuiEditor) {
                plainTextarea.value = commentTuiEditor.getMarkdown();
            }
            wysiwygContainer.style.display = 'none';
            plainTextarea.style.display = 'block';
            toggleBtn.textContent = '✨ Rich WYSIWYG';
            toggleBtn.style.background = 'var(--vp-c-bg-soft)';
            toggleBtn.style.color = 'inherit';
        }
    };

    const form = document.getElementById('issue-comment-form');
    if (form) {
        form.addEventListener('submit', function() {
            if (isWysiwygActive && commentTuiEditor) {
                const plainTextarea = document.getElementById('comment-plain-textarea');
                if (plainTextarea) {
                    plainTextarea.value = commentTuiEditor.getMarkdown();
                }
            }
        });
    }
})();
</script>
@endsection
