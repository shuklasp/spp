@extends('layouts.admin')

@section('title', 'Git & Outbox Webhooks — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            🪝 Git &amp; Outbox Webhooks
            <span class="adm-title-project-chip">📁 {{ $project['title'] ?? $project_id }}</span>
        </h1>
        <p class="adm-page-desc">Configure automated event webhooks for project <strong>{{ $project['title'] ?? $project_id }}</strong> (<code>{{ $project_id }}</code>) to integrate with CI/CD pipelines, Discord, Slack, or GitHub.</p>
    </div>
    <div class="adm-page-actions">
        <button type="button" class="adm-btn adm-btn-primary" onclick="toggleWebhookForm()">➕ New Webhook</button>
    </div>
</div>

<!-- Inbound Git Webhook Info Card -->
<div class="adm-card adm-inbound-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">📥 Inbound Git Webhook (Commit Auto-Closer)</h2>
        <span class="adm-badge adm-badge-active">Ready</span>
    </div>
    <p class="adm-form-desc">Add this webhook endpoint to your GitHub, GitLab, or Gitea repository settings (Push events) to automatically close issues when commit messages mention <code>fixes #issue_xxx</code> or <code>closes #issue_xxx</code>.</p>
    <div class="adm-webhook-endpoint-box">
        <code class="adm-webhook-code" id="inboundWebhookUrl">{{ \SPP\App::getBaseUrl() }}/api/webhook/git?projectId={{ $project_id }}</code>
        <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick="copyWebhookUrl()">📋 Copy URL</button>
    </div>
</div>

<!-- Add / Edit Webhook Form Card (Collapsible) -->
<div id="hook-form-card" class="adm-card adm-hook-form-card" style="display: none;">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Register Outbound HTTP Webhook</h2>
    </div>
    <form action="@url('admin/webhooks/save')" method="POST">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">

        <div class="adm-form-grid">
            <div class="adm-form-group col-span-2">
                <label class="adm-form-label">Payload URL *</label>
                <input type="url" name="webhook_url" required placeholder="https://api.yourserver.com/hooks/issues or https://discord.com/api/webhooks/..." class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Secret Key (HMAC-SHA256 Signature)</label>
                <input type="text" name="webhook_secret" placeholder="Leave blank to auto-generate" class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Subscribed Events (Comma-separated)</label>
                <input type="text" name="events" value="issue.created,issue.closed,issue.commented,issue.moved,milestone.saved" class="adm-form-input">
            </div>
        </div>

        <div class="adm-toggle-item">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">Enable Immediate Delivery</div>
                <div class="adm-toggle-desc">When active, HTTP POST payloads with HMAC SHA-256 signatures are dispatched synchronously or queued to the outbox dispatcher.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="enabled" value="1" checked>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-card-footer">
            <button type="button" class="adm-btn adm-btn-secondary" onclick="toggleWebhookForm()">Cancel</button>
            <button type="submit" class="adm-btn adm-btn-primary">💾 Save Webhook</button>
        </div>
    </form>
</div>

<!-- Active Outbound Webhooks Card -->
<div class="adm-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Configured Outbound Webhooks ({{ count($hooks) }})</h2>
    </div>

    @if(!empty($hooks))
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Payload Destination</th>
                        <th>Status</th>
                        <th>Subscribed Events</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hooks as $hookId => $hook)
                        <tr>
                            <td>
                                <code class="adm-webhook-table-code">{{ $hook['url'] ?? 'No URL' }}</code>
                                @if(!empty($hook['secret']))
                                    <div class="adm-webhook-secret-hint">HMAC Secret: <code>{{ substr($hook['secret'], 0, 6) }}••••••••</code></div>
                                @endif
                            </td>
                            <td>
                                @if(!empty($hook['enabled']))
                                    <span class="adm-badge adm-badge-active">Active</span>
                                @else
                                    <span class="adm-badge adm-badge-inactive">Disabled</span>
                                @endif
                            </td>
                            <td>
                                <div class="adm-event-tags">
                                    @foreach($hook['events'] ?? [] as $ev)
                                        <span class="adm-event-chip">{{ $ev }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-right">
                                <form action="@url('admin/webhooks/delete')" method="POST" onsubmit="return confirm('Are you sure you want to delete this webhook?');" class="adm-inline-form">
                                    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                                    <input type="hidden" name="hook_id" value="{{ $hookId }}">
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
            <div class="adm-empty-icon">🪝</div>
            <div class="adm-empty-title">No Outbound Webhooks Configured</div>
            <div class="adm-empty-desc">Register outbound webhooks above to automatically dispatch HTTP events to CI/CD runners, chat notifications, or external outbox processors.</div>
            <button type="button" class="adm-btn adm-btn-primary" onclick="toggleWebhookForm()">➕ Add First Webhook</button>
        </div>
    @endif
</div>

@section('scripts')
<script>
function toggleWebhookForm() {
    const card = document.getElementById('hook-form-card');
    if (!card) return;
    card.style.display = card.style.display === 'none' ? 'block' : 'none';
    if (card.style.display === 'block') {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function copyWebhookUrl() {
    const el = document.getElementById('inboundWebhookUrl');
    if (!el) return;
    navigator.clipboard.writeText(el.textContent.trim()).then(() => {
        alert('Inbound Webhook URL copied to clipboard!');
    }).catch(() => {
        prompt('Copy this Inbound Webhook URL:', el.textContent.trim());
    });
}
</script>
@endsection
@endsection
