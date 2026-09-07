@extends('layouts.base')
@section('title', 'Board - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@php
    $typeIcons = ['epic' => '🏔️', 'task' => '✅', 'bug' => '🐛', 'feature' => '✨', 'subtask' => '↳'];
    $priorityColors = ['low' => '#eab308', 'medium' => '#f97316', 'high' => '#ef4444', 'critical' => '#dc2626'];
@endphp

@section('content')
<div class="kanban-board-container">
    {{-- Header with Project Context --}}
    <div class="kanban-board-header" style="flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 class="kanban-board-title" style="margin: 0;">📋 Kanban Board</h1>
                @if(!empty($all_projects) && count($all_projects) > 1)
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--vp-c-bg-mute); border: 1px solid var(--vp-c-divider); padding: 0.25rem 0.6rem; border-radius: 20px;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--vp-c-text-2); text-transform: uppercase;">Project:</span>
                        <select onchange="window.location.href='{{ \SPP\App::url('issues/board') }}?projectId=' + encodeURIComponent(this.value)" style="border: none; background: transparent; color: var(--vp-c-text-1); font-weight: 600; font-size: 0.85rem; cursor: pointer; outline: none;">
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
                Visual task pipeline and sprint workflow for <strong>{{ $project['title'] ?? $project_id }}</strong>
            </p>
        </div>
        <div class="kanban-board-nav">
            <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}" class="kanban-nav-btn">📝 List</a>
            <span class="kanban-nav-btn active">📋 Board</span>
            <a href="{{ \SPP\App::url('issues/calendar') }}?projectId={{ $project_id }}" class="kanban-nav-btn">📅 Calendar</a>
            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('milestones', $project))
                <a href="{{ \SPP\App::url('milestones') }}?projectId={{ $project_id }}" class="kanban-nav-btn">🏁 Milestones</a>
            @endif
            @if(\App\SPPDocs\Services\FeatureManager::isEnabled('pm_dashboard', $project))
                <a href="{{ \SPP\App::url('pm/dashboard') }}?projectId={{ $project_id }}" class="kanban-nav-btn">📊 Dashboard</a>
            @endif
        </div>
    </div>

    {{-- Board Columns --}}
    <div id="kanban-board" class="kanban-columns-wrapper">
        @foreach($columns as $col)
        @php $colIssues = $grouped[$col['id']] ?? []; @endphp
        <div class="kanban-col" data-col-id="{{ $col['id'] }}"
             ondragover="event.preventDefault(); this.classList.add('drag-over');"
             ondragleave="this.classList.remove('drag-over');"
             ondrop="handleDrop(event, '{{ $col['id'] }}'); this.classList.remove('drag-over');">
            {{-- Column Header --}}
            <div class="kanban-col-header" style="border-bottom-color: {{ $col['color'] }};">
                <span class="kanban-col-title" style="color: {{ $col['color'] }};">{{ $col['title'] }}</span>
                <span id="count-{{ $col['id'] }}" class="kanban-col-count" style="background: {{ $col['color'] }}22; color: {{ $col['color'] }};">{{ count($colIssues) }}</span>
            </div>
            {{-- Cards Container --}}
            <div id="col-cards-{{ $col['id'] }}" class="kanban-cards" data-status="{{ $col['id'] }}">
                @foreach($colIssues as $issue)
                <div id="card-{{ $issue['id'] }}" class="kanban-card" draggable="true" data-issue-id="{{ $issue['id'] }}"
                     ondragstart="handleDragStart(event, '{{ $issue['id'] }}')"
                     ondragend="handleDragEnd(event)">
                    {{-- Type & Priority --}}
                    <div class="kanban-card-top">
                        <span class="kanban-card-type">{{ $typeIcons[$issue['type'] ?? 'task'] ?? '✅' }}</span>
                        <span class="kanban-card-priority" style="background: {{ $priorityColors[$issue['priority'] ?? 'medium'] ?? '#f97316' }};" title="{{ ucfirst($issue['priority'] ?? 'medium') }}"></span>
                    </div>
                    {{-- Title --}}
                    <a href="{{ \SPP\App::getBaseUrl() }}/issues/view?projectId={{ $project_id }}&issueId={{ $issue['id'] }}" 
                       class="kanban-card-title"
                       onclick="if(window._dragging){event.preventDefault()}">{{ $issue['title'] }}</a>
                    {{-- Labels --}}
                    @if(!empty($issue['labels']))
                    <div class="kanban-card-labels">
                        @foreach(array_slice($issue['labels'], 0, 3) as $label)
                        <span class="kanban-label-chip">{{ $label }}</span>
                        @endforeach
                    </div>
                    @endif
                    {{-- Footer: Assignees + Due Date --}}
                    <div class="kanban-card-footer">
                        <div class="kanban-assignees-box">
                            @foreach(array_slice($issue['assignees'] ?? [], 0, 3) as $a)
                            <span class="kanban-avatar-chip" title="{{ $a }}">{{ strtoupper(substr($a, 0, 1)) }}</span>
                            @endforeach
                        </div>
                        @if(!empty($issue['due_date']))
                        @php $dueTs = is_numeric($issue['due_date']) ? $issue['due_date'] : strtotime($issue['due_date']); $overdue = $dueTs && $dueTs < time() && ($issue['status'] ?? 'open') !== 'done'; @endphp
                        <span class="kanban-due-chip {{ $overdue ? 'overdue' : '' }}">📅 {{ date('M j', $dueTs) }}</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>

<script>
(function() {
    const projectId = "{{ $project_id }}";
    const csrfToken = "{{ $csrf_token }}";
    const baseUrl = "{{ \SPP\App::getBaseUrl() }}";
    let lastEventId = 0;
    window._dragging = false;

    window.handleDragStart = function(e, issueId) {
        window._dragging = true;
        e.dataTransfer.setData('issueId', issueId);
        const card = document.getElementById('card-' + issueId);
        if (card) {
            card.classList.add('is-dragging');
        }
    };

    window.handleDragEnd = function(e) {
        setTimeout(function() { window._dragging = false; }, 50);
        document.querySelectorAll('.kanban-card.is-dragging').forEach(function(el) {
            el.classList.remove('is-dragging');
        });
    };

    function getDragAfterElement(container, y) {
        const draggableElements = Array.from(container.querySelectorAll('.kanban-card:not(.is-dragging)'));
        return draggableElements.reduce(function(closest, child) {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function placeCardInCol(card, targetCol, targetIndex) {
        if (targetIndex !== undefined && targetIndex !== null) {
            const currentCards = Array.from(targetCol.querySelectorAll('.kanban-card:not(#' + card.id + ')'));
            if (targetIndex < currentCards.length) {
                targetCol.insertBefore(card, currentCards[targetIndex]);
                return;
            }
        }
        targetCol.appendChild(card);
    }

    window.handleDrop = function(e, newStatus) {
        e.preventDefault();
        const issueId = e.dataTransfer.getData('issueId');
        if (!issueId) return;

        const card = document.getElementById('card-' + issueId);
        const targetCol = document.getElementById('col-cards-' + newStatus);
        if (!card || !targetCol) return;

        const oldCol = card.closest('.kanban-cards');
        const oldStatus = oldCol ? oldCol.dataset.status : '';
        const oldNextSibling = card.nextElementSibling;

        // Determine exact insert position
        const afterElement = getDragAfterElement(targetCol, e.clientY);
        if (afterElement == null) {
            targetCol.appendChild(card);
        } else {
            targetCol.insertBefore(card, afterElement);
        }

        const cardsInCol = Array.from(targetCol.querySelectorAll('.kanban-card'));
        const targetIndex = cardsInCol.indexOf(card);

        if (oldCol !== targetCol) {
            updateCounters(oldStatus, newStatus);
        }

        // Dispatch to server in background
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('project_id', projectId);
        fd.append('issue_id', issueId);
        fd.append('new_status', newStatus);
        fd.append('target_index', targetIndex >= 0 ? targetIndex : 0);

        fetch(baseUrl + '/issues/move', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.ok) {
                    rollbackCard(card, oldCol, oldNextSibling, oldStatus, newStatus);
                }
            })
            .catch(function() {
                rollbackCard(card, oldCol, oldNextSibling, oldStatus, newStatus);
            });
    };

    function rollbackCard(card, originalCol, originalNextSibling, oldStatus, newStatus) {
        if (originalCol && card) {
            if (originalNextSibling) {
                originalCol.insertBefore(card, originalNextSibling);
            } else {
                originalCol.appendChild(card);
            }
            if (oldStatus !== newStatus) {
                updateCounters(newStatus, oldStatus);
            }
        }
    }

    function updateCounters(fromStatus, toStatus) {
        if (fromStatus) {
            const countEl = document.getElementById('count-' + fromStatus);
            if (countEl) {
                countEl.textContent = Math.max(0, parseInt(countEl.textContent || '1', 10) - 1);
            }
        }
        if (toStatus) {
            const countEl = document.getElementById('count-' + toStatus);
            if (countEl) {
                countEl.textContent = parseInt(countEl.textContent || '0', 10) + 1;
            }
        }
    }

    // Real-Time Live Sync via Server-Sent Events (SSE)
    function initRealtimeSync() {
        if (!window.EventSource) {
            startPollingFallback();
            return;
        }

        const sseUrl = baseUrl + '/issues/events?project=' + encodeURIComponent(projectId);
        const eventSource = new EventSource(sseUrl);

        eventSource.addEventListener('issue.moved', function(e) {
            try {
                const data = JSON.parse(e.data);
                const card = document.getElementById('card-' + data.issue_id);
                const targetCol = document.getElementById('col-cards-' + data.new_status);
                if (card && targetCol && !card.classList.contains('is-dragging')) {
                    const oldCol = card.closest('.kanban-cards');
                    const oldStatus = oldCol ? oldCol.dataset.status : data.old_status;
                    
                    placeCardInCol(card, targetCol, data.target_index);
                    if (oldStatus !== data.new_status) {
                        updateCounters(oldStatus, data.new_status);
                    }
                    
                    // Flash card to indicate remote live move
                    card.style.transition = 'background-color 0.4s';
                    card.style.backgroundColor = 'rgba(234, 88, 12, 0.15)';
                    setTimeout(function() { card.style.backgroundColor = ''; }, 1000);
                }
            } catch (err) {}
        });

        eventSource.onerror = function() {
            eventSource.close();
            startPollingFallback();
        };
    }

    // Smart Polling Fallback if SSE is restricted on shared hosting
    let pollInterval = null;
    function startPollingFallback() {
        if (pollInterval) return;
        pollInterval = setInterval(function() {
            fetch(baseUrl + '/issues/poll?project=' + encodeURIComponent(projectId) + '&last_id=' + lastEventId)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.events && res.events.length) {
                        lastEventId = res.last_id;
                        res.events.forEach(function(evt) {
                            if (evt.event === 'issue.moved') {
                                const card = document.getElementById('card-' + evt.data.issue_id);
                                const targetCol = document.getElementById('col-cards-' + evt.data.new_status);
                                if (card && targetCol && !card.classList.contains('is-dragging')) {
                                    const oldCol = card.closest('.kanban-cards');
                                    const oldStatus = oldCol ? oldCol.dataset.status : evt.data.old_status;
                                    placeCardInCol(card, targetCol, evt.data.target_index);
                                    if (oldStatus !== evt.data.new_status) {
                                        updateCounters(oldStatus, evt.data.new_status);
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(function() {});
        }, 3000);
    }

    document.addEventListener('DOMContentLoaded', initRealtimeSync);
})();
</script>
@endsection
