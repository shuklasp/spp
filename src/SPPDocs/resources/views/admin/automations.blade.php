@extends('layouts.admin')

@section('title', 'CI/CD Automations — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            ⚡ CI/CD Automation Rules
            <span class="adm-title-project-chip">📁 {{ $project['title'] ?? $project_id }}</span>
        </h1>
        <p class="adm-page-desc">Define trigger-condition-action workflow rules for project <strong>{{ $project['title'] ?? $project_id }}</strong> (<code>{{ $project_id }}</code>) that execute on issue lifecycle events.</p>
    </div>
    <div class="adm-page-actions">
        <button type="button" class="adm-btn adm-btn-primary" onclick="toggleRuleForm()">➕ New Rule</button>
    </div>
</div>

<!-- Add / Edit Rule Form Card (Collapsible) -->
<div id="rule-form-card" class="adm-card" style="display: none;">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Create Automation Rule</h2>
    </div>
    <form action="@url('admin/automations/save')" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        <div class="adm-form-grid">
            <div class="adm-form-group col-span-2">
                <label class="adm-form-label">Rule Name *</label>
                <input type="text" name="rule_name" required placeholder="e.g. Auto-assign bugs to triage lead" class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">When (Trigger Event)</label>
                <select name="trigger_event" class="adm-form-select">
                    <option value="issue.created">Issue Created</option>
                    <option value="issue.closed">Issue Closed</option>
                    <option value="issue.commented">Comment Added</option>
                    <option value="issue.moved">Status Changed</option>
                </select>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">If (Condition Field)</label>
                <select name="condition_field" class="adm-form-select">
                    <option value="">Always (No condition)</option>
                    <option value="type">Type equals</option>
                    <option value="priority">Priority equals</option>
                    <option value="labels_contain">Labels contain</option>
                </select>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Condition Value</label>
                <input type="text" name="condition_value" placeholder="e.g. bug, critical, backend" class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Then (Action)</label>
                <select name="action_type" class="adm-form-select">
                    <option value="assign">Assign to user</option>
                    <option value="assign_team">Assign to team</option>
                    <option value="set_priority">Set priority</option>
                    <option value="add_label">Add label</option>
                    <option value="set_milestone">Set milestone</option>
                </select>
            </div>

            <div class="adm-form-group col-span-2">
                <label class="adm-form-label">Action Value</label>
                <input type="text" name="action_value" placeholder="Target username, team_id, or value" class="adm-form-input">
            </div>
        </div>

        <div class="adm-toggle-item">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Enable Automation Rule</div>
                <div class="adm-toggle-desc">When active, this rule evaluates automatically on every matching project event.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="enabled" value="1" checked>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-card-footer">
            <button type="button" class="adm-btn adm-btn-secondary" onclick="toggleRuleForm()">Cancel</button>
            <button type="submit" class="adm-btn adm-btn-primary">💾 Save Rule</button>
        </div>
    </form>
</div>

<!-- Active Rules Card -->
<div class="adm-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Configured Automation Rules ({{ count($rules) }})</h2>
    </div>

    @if(!empty($rules))
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Rule Name</th>
                        <th>Status</th>
                        <th>Trigger & Logic</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rules as $ruleId => $rule)
                        <tr>
                            <td>
                                <strong>{{ $rule['name'] ?? 'Unnamed' }}</strong>
                            </td>
                            <td>
                                @if(!empty($rule['enabled']))
                                    <span class="adm-badge adm-badge-active">Active</span>
                                @else
                                    <span class="adm-badge adm-badge-inactive">Disabled</span>
                                @endif
                            </td>
                            <td>
                                <div>When <code class="adm-code-sm">{{ $rule['trigger_event'] ?? '?' }}</code></div>
                                @if(!empty($rule['condition_field']))
                                    <div class="adm-rule-detail">If <code>{{ $rule['condition_field'] }}</code> = <code>{{ $rule['condition_value'] }}</code></div>
                                @endif
                                <div class="adm-rule-action">&rarr; <strong>{{ $rule['action_type'] ?? '?' }}</strong>: <code>{{ $rule['action_value'] ?? '' }}</code></div>
                            </td>
                            <td class="text-right">
                                <form action="@url('admin/automations/delete')" method="POST" onsubmit="return confirm('Delete this automation rule?');" class="adm-inline-form">
                                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                                    <input type="hidden" name="rule_id" value="{{ $ruleId }}">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="adm-empty-state">
            <div class="adm-empty-icon">⚡</div>
            <div class="adm-empty-title">No Automation Rules Configured</div>
            <div class="adm-empty-desc">Create workflow rules above to automatically triage bugs, assign incoming issues, set milestones, or tag priorities.</div>
            <button type="button" class="adm-btn adm-btn-primary" onclick="toggleRuleForm()">➕ Add First Rule</button>
        </div>
    @endif
</div>

@section('scripts')
<script>
function toggleRuleForm() {
    const card = document.getElementById('rule-form-card');
    if (!card) return;
    card.style.display = card.style.display === 'none' ? 'block' : 'none';
    if (card.style.display === 'block') {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>
@endsection
@endsection
