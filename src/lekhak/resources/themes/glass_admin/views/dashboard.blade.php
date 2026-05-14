@extends('layout')

@section('actions')
    <div class="lekhak-actions-dropdown" style="position: relative; display: inline-block;">
        <button onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block';" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
            ⚡ Quick Operations ⌄
        </button>
        <div class="dropdown-content glass-panel" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 8px; min-width: 200px; z-index: 1000; padding: 8px; border-radius: 8px;">
            <a href="{{ $admin_root }}/structure/types" style="display: block; padding: 8px 12px; color: var(--text-main); text-decoration: none; font-size: 0.8rem; border-radius: 4px;">＋ Add Bundle</a>
            <a href="{{ $admin_root }}/landing" style="display: block; padding: 8px 12px; color: var(--text-main); text-decoration: none; font-size: 0.8rem; border-radius: 4px;">🎨 Visual Design</a>
            <a href="#" onclick="alert('Diagnostic traces re-indexed.'); return false;" style="display: block; padding: 8px 12px; color: var(--text-dim); text-decoration: none; font-size: 0.8rem; border-radius: 4px;">🔍 Flush Traces</a>
        </div>
    </div>
@endsection

@section('content')
<!-- Unified Secondary Local Tasks Navigation Header -->
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px; display: flex; gap: 10px;">
    <button onclick="filterTaskTab('all', this)" class="task-tab-pill active" style="background: var(--accent-primary); color: white; border: none; padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
        Overview Metrics
    </button>
    <button onclick="filterTaskTab('published', this)" class="task-tab-pill" style="background: rgba(255,255,255,0.05); color: var(--text-dim); border: 1px solid var(--glass-border); padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
        Published Streams
    </button>
    <button onclick="filterTaskTab('system', this)" class="task-tab-pill" style="background: rgba(255,255,255,0.05); color: var(--text-dim); border: 1px solid var(--glass-border); padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
        System Diagnostics
    </button>
</div>

<div id="metrics-tab-all" class="task-tab-content">
    <div class="stats-grid">
        <div class="glass-panel stat-card" style="border-left: 4px solid var(--accent-primary);">
            <div class="stat-icon" style="color: var(--accent-primary)">📄</div>
            <div class="stat-info">
                <h3>Total Nodes</h3>
                <div class="value">{{ $stats['nodes'] ?? 0 }}</div>
                <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;">Structured DB Streams</div>
            </div>
        </div>
        <div class="glass-panel stat-card" style="border-left: 4px solid var(--accent-secondary);">
            <div class="stat-icon" style="color: var(--accent-secondary)">🧩</div>
            <div class="stat-info">
                <h3>Content Types</h3>
                <div class="value">{{ $stats['types'] ?? 0 }}</div>
                <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;">Active Schemas</div>
            </div>
        </div>
        <div class="glass-panel stat-card" style="border-left: 4px solid var(--success);">
            <div class="stat-icon" style="color: var(--success)">🚀</div>
            <div class="stat-info">
                <h3>Landing Pages</h3>
                <div class="value">{{ $stats['landing'] ?? 0 }}</div>
                <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;">Dynamic Canvas Blocks</div>
            </div>
        </div>
        <div class="glass-panel stat-card" style="border-left: 4px solid var(--warning);">
            <div class="stat-icon" style="color: var(--warning)">👥</div>
            <div class="stat-info">
                <h3>Active Users</h3>
                <div class="value">{{ $stats['users'] ?? 0 }}</div>
                <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;">IAM Framework Access</div>
            </div>
        </div>
    </div>
</div>

<div class="glass-panel">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px;">
        <h2 style="font-size: 1.2rem;">Recent Workspace Streams</h2>
        <div style="display: flex; gap: 8px;">
            <input type="text" id="liveActivitySearch" onkeyup="filterActivityTable()" placeholder="Filter live stream records..." style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2); color: white; font-size: 0.8rem; outline: none;">
            <a href="{{ $admin_root }}/content" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 12px;">View Repository</a>
        </div>
    </div>
    
    <div style="overflow-x: auto;">
        <table id="activityTable">
            <thead>
                <tr>
                    <th>Stream Title</th>
                    <th>Schema Bundle</th>
                    <th>Identity Mapping</th>
                    <th>State Milestone</th>
                    <th>Created Milestone</th>
                    <th style="text-align: right;">Grouped Operations</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recent_nodes ?? [] as $node)
                <tr class="activity-row" data-status="{{ $node->status }}">
                    <td>
                        <strong>{{ $node->title }}</strong>
                        <div style="font-size: 0.7rem; color: var(--text-dim); font-family: monospace;">Entity ID Key: #{{ $node->id }}</div>
                    </td>
                    <td><span class="badge badge-warning">{{ $node->bundle }}</span></td>
                    <td>{{ $node->author_name ?? 'System Authority' }}</td>
                    <td><span class="badge badge-success">{{ $node->status }}</span></td>
                    <td>{{ date('M d, Y', strtotime($node->created)) }}</td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 4px;">
                            <a href="{{ $admin_root }}/content/edit/{{ $node->id }}" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.7rem;">Edit</a>
                            <a href="{{ $admin_root }}/content/delete/{{ $node->id }}" onclick="return confirm('Confirm permanently purging entity stream?');" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.7rem; color: var(--danger);">Purge</a>
                        </div>
                    </td>
                </tr>
                @endforeach
                @if(empty($recent_nodes))
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 40px;">No incoming streams buffered in repository indices.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<script>
    function filterTaskTab(mode, button) {
        document.querySelectorAll('.task-tab-pill').forEach(b => {
            b.style.background = 'rgba(255,255,255,0.05)';
            b.style.color = 'var(--text-dim)';
            b.style.border = '1px solid var(--glass-border)';
        });
        button.style.background = 'var(--accent-primary)';
        button.style.color = 'white';
        button.style.border = 'none';

        const rows = document.querySelectorAll('.activity-row');
        rows.forEach(row => {
            if (mode === 'all') {
                row.style.display = '';
            } else if (mode === 'published') {
                row.style.display = row.getAttribute('data-status') === 'published' ? '' : 'none';
            } else {
                row.style.display = row.getAttribute('data-status') !== 'published' ? '' : 'none';
            }
        });
    }

    function filterActivityTable() {
        const input = document.getElementById('liveActivitySearch');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('.activity-row');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }

    // Close open quick dropdowns if user clicks outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.lekhak-actions-dropdown')) {
            document.querySelectorAll('.dropdown-content').forEach(d => d.style.display = 'none');
        }
    });
</script>
@endsection
