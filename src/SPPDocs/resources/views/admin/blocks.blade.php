@extends('layouts.admin')

@section('title', 'Regions & Blocks — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            🧱 Regions &amp; Blocks
            <span class="adm-title-project-chip">📁 {{ $project['title'] ?? $project_id }}</span>
        </h1>
        <p class="adm-page-desc">
            Manage theme regions and pluggable blocks for the active theme: 
            <strong>🎨 {{ $theme['name'] ?? ucfirst($theme['id'] ?? 'default') }}</strong>
        </p>
    </div>
    <div class="adm-page-actions">
        <button type="button" class="adm-btn adm-btn-primary" onclick="openBlockModal()">
            + Place Block
        </button>
    </div>
</div>

<!-- Theme Regions List -->
<div style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 1rem;">
    @foreach($regions as $regKey => $regLabel)
        @php
            $regionBlocks = $allBlocks[$regKey] ?? [];
            usort($regionBlocks, function($a, $b) { return ($a['weight'] ?? 0) - ($b['weight'] ?? 0); });
        @endphp
        <div class="adm-card" style="margin: 0; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--vp-c-text-1);">
                        {{ $regLabel }}
                    </h2>
                    <code style="font-size: 0.75rem; color: var(--vp-c-brand); background: var(--vp-c-bg-mute); padding: 0.15rem 0.4rem; border-radius: 4px;">{{ $regKey }}</code>
                </div>
                <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick="openBlockModal('{{ $regKey }}')">
                    + Add to {{ $regKey }}
                </button>
            </div>

            @if(empty($regionBlocks))
                <div style="padding: 1.5rem; text-align: center; background: var(--vp-c-bg-alt); border-radius: 8px; border: 1px dashed var(--vp-c-divider);">
                    <p style="margin: 0; font-size: 0.88rem; color: var(--vp-c-text-2);">No blocks placed in this region.</p>
                </div>
            @else
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @foreach($regionBlocks as $block)
                        <div style="display: flex; justify-content: space-between; align-items: center; background: var(--vp-c-bg-alt); border: 1px solid var(--vp-c-divider); padding: 0.85rem 1rem; border-radius: 8px;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="cursor: grab; color: var(--vp-c-text-3);">⋮⋮</span>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <strong style="color: var(--vp-c-text-1); font-size: 0.95rem;">{{ $block['title'] }}</strong>
                                        <span class="adm-badge adm-badge-active" style="font-size: 0.7rem;">{{ strtoupper($block['type'] ?? 'alert') }}</span>
                                        <span style="font-size: 0.75rem; color: var(--vp-c-text-3);">Weight: {{ $block['weight'] ?? 0 }}</span>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--vp-c-text-2); margin-top: 0.2rem;">
                                        <span>Paths: <code>{{ implode(', ', (array)($block['visibility']['paths'] ?? ['*'])) }}</code></span>
                                        <span style="margin-left: 0.5rem;">Roles: <code>{{ implode(', ', (array)($block['visibility']['roles'] ?? ['*'])) }}</code></span>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <form method="POST" action="{{ \SPP\App::getBaseUrl() }}/admin/blocks/delete" onsubmit="return confirm('Are you sure you want to delete this block?');" style="margin: 0;">
                                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                                    <input type="hidden" name="id" value="{{ $block['id'] }}">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>

<!-- Place/Edit Block Modal -->
<div id="blockModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="adm-card" style="width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; margin: 1rem; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 1rem; margin-bottom: 1rem;">
            <h3 id="modalTitle" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--vp-c-text-1);">Place New Block</h3>
            <button type="button" onclick="closeBlockModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--vp-c-text-2);">&times;</button>
        </div>

        <form method="POST" action="{{ \SPP\App::getBaseUrl() }}/admin/blocks/save" id="blockForm">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <input type="hidden" name="id" id="blockIdField" value="">

            <div class="adm-form-group" style="margin-bottom: 1rem;">
                <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Target Region</label>
                <select name="region" id="regionField" class="adm-input" required style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
                    @foreach($regions as $regKey => $regLabel)
                        <option value="{{ $regKey }}">{{ $regLabel }} ({{ $regKey }})</option>
                    @endforeach
                </select>
            </div>

            <div class="adm-form-group" style="margin-bottom: 1rem;">
                <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Block Title</label>
                <input type="text" name="title" id="titleField" class="adm-input" placeholder="e.g., Announcements, Table of Contents" required style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
            </div>

            <div class="adm-form-group" style="margin-bottom: 1rem;">
                <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Block Type</label>
                <select name="type" id="typeField" class="adm-input" onchange="handleTypeChange(this.value)" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
                    <option value="alert">Alert Notice (Info, Warning, Tip)</option>
                    <option value="view">Dynamic View / Studio Query</option>
                    <option value="taxonomy_tree">Taxonomy Category Tree</option>
                    <option value="toc">Page Table of Contents</option>
                    <option value="custom">Custom Module Plugin</option>
                </select>
            </div>

            <!-- Conditional View Selector -->
            <div id="viewOptions" class="type-opt" style="display: none; margin-bottom: 1rem;">
                <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Select View</label>
                <select name="view_id" class="adm-input" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
                    @foreach($views as $vId => $vDef)
                        <option value="{{ $vId }}">{{ $vDef['name'] ?? $vId }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Conditional Taxonomy Vocab Selector -->
            <div id="taxonomyOptions" class="type-opt" style="display: none; margin-bottom: 1rem;">
                <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Select Vocabulary</label>
                <select name="vocab" class="adm-input" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
                    @foreach($vocabularies as $vocKey => $vocDef)
                        <option value="{{ $vocKey }}">{{ $vocDef['name'] ?? ucfirst($vocKey) }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Conditional Alert Content -->
            <div id="alertOptions" class="type-opt" style="display: block; margin-bottom: 1rem;">
                <div style="margin-bottom: 0.75rem;">
                    <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Alert Variant</label>
                    <select name="alert_type" class="adm-input" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
                        <option value="info">Info (Blue)</option>
                        <option value="warning">Warning (Yellow/Amber)</option>
                        <option value="success">Success (Green)</option>
                        <option value="danger">Danger (Red)</option>
                    </select>
                </div>
                <div>
                    <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Alert Message</label>
                    <textarea name="content" rows="3" class="adm-input" placeholder="Important announcement or message..." style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);"></textarea>
                </div>
            </div>

            <!-- Conditional Custom Plugin -->
            <div id="customOptions" class="type-opt" style="display: none; margin-bottom: 1rem;">
                <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Plugin Machine ID</label>
                <input type="text" name="plugin_id" class="adm-input" placeholder="e.g., reading_time_stats" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
            </div>

            <div class="adm-form-group" style="margin-bottom: 1rem;">
                <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Weight (Sort Order)</label>
                <input type="number" name="weight" id="weightField" value="0" class="adm-input" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
            </div>

            <!-- Visibility Settings -->
            <div style="border-top: 1px solid var(--vp-c-divider); padding-top: 1rem; margin-top: 1rem;">
                <h4 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 700; color: var(--vp-c-text-1);">Visibility Conditions</h4>
                <div class="adm-form-group" style="margin-bottom: 1rem;">
                    <label class="adm-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; color: var(--vp-c-text-1);">Path Patterns</label>
                    <textarea name="paths" rows="2" class="adm-input" placeholder="* for all pages&#10;&lt;front&gt; for homepage&#10;guides/*" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">*</textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="closeBlockModal()">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">Save Block Placement</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBlockModal(regKey) {
    document.getElementById('modalTitle').textContent = 'Place New Block';
    document.getElementById('blockIdField').value = '';
    document.getElementById('titleField').value = '';
    document.getElementById('typeField').value = 'alert';
    document.getElementById('weightField').value = '0';
    if (regKey) {
        document.getElementById('regionField').value = regKey;
    }
    handleTypeChange('alert');
    var modal = document.getElementById('blockModal');
    modal.style.display = 'flex';
}

function closeBlockModal() {
    var modal = document.getElementById('blockModal');
    modal.style.display = 'none';
}

function handleTypeChange(val) {
    document.querySelectorAll('.type-opt').forEach(function(el) {
        el.style.display = 'none';
    });
    if (val === 'alert') {
        document.getElementById('alertOptions').style.display = 'block';
    } else if (val === 'view') {
        document.getElementById('viewOptions').style.display = 'block';
    } else if (val === 'taxonomy_tree') {
        document.getElementById('taxonomyOptions').style.display = 'block';
    } else if (val === 'custom') {
        document.getElementById('customOptions').style.display = 'block';
    }
}
</script>
@endsection
