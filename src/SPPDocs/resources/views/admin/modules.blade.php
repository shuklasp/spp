@extends('layouts.admin')

@section('title', 'Modules & Extensions — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            🧩 Modules &amp; Extensions
            <span class="adm-title-project-chip">📁 {{ $project['title'] ?? $project_id }}</span>
        </h1>
        <p class="adm-page-desc">Manage modular app extensions, custom event listeners, and hook integrations for <strong>{{ $project['title'] ?? $project_id }}</strong>.</p>
    </div>
    <div class="adm-page-actions">
        <span class="adm-badge adm-badge-active">Extensible Core</span>
    </div>
</div>

<!-- Alert Banner -->
<div id="moduleFeedbackAlert" class="adm-alert" style="display: none; margin-bottom: 1.5rem;">
    <span id="moduleFeedbackIcon">✅</span>
    <div id="moduleFeedbackText"></div>
</div>

<!-- Category Tabs -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <button type="button" class="adm-btn adm-btn-primary adm-btn-sm category-filter-btn" data-cat="all">All Modules ({{ count($modules) }})</button>
    @foreach($categories as $catName => $catMods)
        <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm category-filter-btn" data-cat="{{ strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $catName)) }}">
            {{ $catName }} ({{ count($catMods) }})
        </button>
    @endforeach
</div>

<!-- Modules Grid -->
<div class="adm-media-grid" style="grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.25rem;">
    @foreach($modules as $mod)
    <div class="adm-card module-card" data-category="{{ strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $mod['category'] ?? 'General')) }}" style="margin: 0; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                <div>
                    <h3 style="margin: 0 0 0.25rem 0; font-size: 1.05rem; font-weight: 700; color: var(--vp-c-text-1);">
                        {{ $mod['name'] }}
                    </h3>
                    <code style="font-size: 0.75rem; color: var(--vp-c-brand); background: var(--vp-c-bg-mute); padding: 0.15rem 0.4rem; border-radius: 4px;">{{ $mod['id'] }}</code>
                </div>
                <label class="adm-switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
                    <input type="checkbox" class="module-toggle-checkbox" 
                           data-module-id="{{ $mod['id'] }}" 
                           {{ !empty($mod['enabled']) ? 'checked' : '' }}
                           style="opacity: 0; width: 0; height: 0;">
                    <span class="adm-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--vp-c-divider); transition: .3s; border-radius: 24px;"></span>
                </label>
            </div>
            
            <p style="font-size: 0.86rem; color: var(--vp-c-text-2); line-height: 1.45; margin: 0 0 1rem 0;">
                {{ $mod['description'] ?: 'No description provided.' }}
            </p>
        </div>

        <div style="padding-top: 0.75rem; border-top: 1px solid var(--vp-c-divider); font-size: 0.75rem; color: var(--vp-c-text-3); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <span class="adm-badge adm-badge-mute">v{{ $mod['version'] }}</span>
                <span style="color: var(--vp-c-text-2);">by {{ $mod['author'] }}</span>
            </div>
            <span class="adm-badge" style="background: var(--vp-c-bg-mute); color: var(--vp-c-text-2); border: 1px solid var(--vp-c-divider);">
                📁 {{ $mod['scope'] === 'project' ? 'Project' : 'App Core' }}
            </span>
        </div>
    </div>
    @endforeach

    @if(empty($modules))
    <div class="adm-media-empty-card" style="grid-column: 1 / -1;">
        <div class="adm-media-empty-icon">🧩</div>
        <div class="adm-media-empty-title">No Modules Found</div>
        <div class="adm-media-empty-desc">No app-level or project-level modules have been installed for this project yet.</div>
    </div>
    @endif
</div>

<style>
.adm-switch input:checked + .adm-slider {
    background-color: var(--vp-c-brand);
}
.adm-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
.adm-switch input:checked + .adm-slider:before {
    transform: translateX(20px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = "{{ $csrf_token }}";
    const projectId = "{{ $project_id }}";
    const toggleUrl = "{{ \SPP\App::getBaseUrl() }}/admin/modules/toggle";
    const feedbackAlert = document.getElementById('moduleFeedbackAlert');
    const feedbackText = document.getElementById('moduleFeedbackText');
    const feedbackIcon = document.getElementById('moduleFeedbackIcon');

    function showFeedback(msg, success) {
        if (!feedbackAlert) return;
        feedbackText.textContent = msg;
        feedbackAlert.className = 'adm-alert ' + (success ? 'adm-alert-success' : 'adm-alert-danger');
        feedbackIcon.textContent = success ? '✅' : '⚠️';
        feedbackAlert.style.display = 'flex';
        setTimeout(() => { feedbackAlert.style.display = 'none'; }, 4000);
    }

    // Module toggle handler
    document.querySelectorAll('.module-toggle-checkbox').forEach(chk => {
        chk.addEventListener('change', function() {
            const moduleId = this.dataset.moduleId;
            const enable = this.checked ? 1 : 0;
            
            const formData = new FormData();
            formData.append('project_id', projectId);
            formData.append('module_id', moduleId);
            formData.append('enable', enable);
            formData.append('csrf_token', csrfToken);

            fetch(toggleUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showFeedback(data.message, true);
                } else {
                    showFeedback(data.error || 'Failed to update module', false);
                    chk.checked = !chk.checked; // revert
                }
            })
            .catch(err => {
                showFeedback('Network error while toggling module', false);
                chk.checked = !chk.checked;
            });
        });
    });

    // Category filter tabs
    const catButtons = document.querySelectorAll('.category-filter-btn');
    const cards = document.querySelectorAll('.module-card');
    catButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            catButtons.forEach(b => b.className = 'adm-btn adm-btn-secondary adm-btn-sm category-filter-btn');
            this.className = 'adm-btn adm-btn-primary adm-btn-sm category-filter-btn';
            
            const filterCat = this.dataset.cat;
            cards.forEach(card => {
                if (filterCat === 'all' || card.dataset.category === filterCat) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>
@endsection