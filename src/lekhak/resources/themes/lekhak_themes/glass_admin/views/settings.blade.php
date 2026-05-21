@extends('layout')

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

@if(isset($_GET['saved']))
<div style="background: rgba(46, 160, 67, 0.15); border: 1px solid var(--success); color: var(--success); padding: 12px 20px; border-radius: 8px; margin-bottom: 25px;">
    ✅ Configuration saved successfully!
</div>
@endif

<form method="POST" action="">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <!-- Lekhni Editor Settings -->
        <div class="glass-panel" style="padding: 20px;">
            <h3 style="margin-top: 0; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; color: var(--accent-primary);">Lekhni Editor Settings</h3>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: var(--text-main); font-weight: 500;">Default Workspace Mode</label>
                <select name="lekhni_default_mode" class="form-control" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px;">
                    <option value="document" {{ ($settings['lekhni_default_mode'] ?? '') == 'document' ? 'selected' : '' }}>📝 Document Mode</option>
                    <option value="code" {{ ($settings['lekhni_default_mode'] ?? '') == 'code' ? 'selected' : '' }}>💻 Code Editor</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="lekhni_ai_copilot" value="1" {{ !empty($settings['lekhni_ai_copilot']) ? 'checked' : '' }}>
                    <span style="color: var(--text-main);">Enable AI Co-Pilot Widget</span>
                </label>
                <div style="font-size: 0.8rem; color: var(--text-dim); margin-left: 24px;">Allows intelligent content generation within the editor.</div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: var(--text-main); font-weight: 500;">Default Code Language</label>
                <select name="lekhni_code_language" class="form-control" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px;">
                    <option value="html" {{ ($settings['lekhni_code_language'] ?? '') == 'html' ? 'selected' : '' }}>HTML</option>
                    <option value="css" {{ ($settings['lekhni_code_language'] ?? '') == 'css' ? 'selected' : '' }}>CSS</option>
                    <option value="javascript" {{ ($settings['lekhni_code_language'] ?? '') == 'javascript' ? 'selected' : '' }}>JavaScript</option>
                    <option value="php" {{ ($settings['lekhni_code_language'] ?? '') == 'php' ? 'selected' : '' }}>PHP</option>
                    <option value="json" {{ ($settings['lekhni_code_language'] ?? '') == 'json' ? 'selected' : '' }}>JSON</option>
                </select>
            </div>
        </div>

        <!-- Visual Designer Settings -->
        <div class="glass-panel" style="padding: 20px;">
            <h3 style="margin-top: 0; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; color: var(--accent-secondary);">Visual Designer Settings</h3>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="designer_grid_snap" value="1" {{ !empty($settings['designer_grid_snap']) ? 'checked' : '' }}>
                    <span style="color: var(--text-main);">Enable Drag & Drop Grid Snapping</span>
                </label>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: var(--text-main); font-weight: 500;">Autosave Interval (Seconds)</label>
                <input type="number" name="designer_autosave" value="{{ $settings['designer_autosave'] ?? 30 }}" min="10" max="600" class="form-control" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px;">
                <div style="font-size: 0.8rem; color: var(--text-dim); margin-top: 4px;">How often to save visual drafts.</div>
            </div>
        </div>

        <!-- Structure Architect Settings -->
        <div class="glass-panel" style="padding: 20px;">
            <h3 style="margin-top: 0; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; color: #d2a8ff;">Structure Architect</h3>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="structure_strict_schema" value="1" {{ !empty($settings['structure_strict_schema']) ? 'checked' : '' }}>
                    <span style="color: var(--text-main);">Enforce Strict Schema Validation</span>
                </label>
                <div style="font-size: 0.8rem; color: var(--text-dim); margin-left: 24px;">Block saving nodes if they violate field constraints.</div>
            </div>
        </div>

        <!-- Content Management -->
        <div class="glass-panel" style="padding: 20px;">
            <h3 style="margin-top: 0; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px; color: var(--success);">Content Management</h3>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: var(--text-main); font-weight: 500;">Default Node Status</label>
                <select name="content_default_status" class="form-control" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px;">
                    <option value="draft" {{ ($settings['content_default_status'] ?? '') == 'draft' ? 'selected' : '' }}>Draft (Hidden)</option>
                    <option value="published" {{ ($settings['content_default_status'] ?? '') == 'published' ? 'selected' : '' }}>Published (Live)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="content_revision_tracking" value="1" {{ !empty($settings['content_revision_tracking']) ? 'checked' : '' }}>
                    <span style="color: var(--text-main);">Enable Revision History</span>
                </label>
                <div style="font-size: 0.8rem; color: var(--text-dim); margin-left: 24px;">Maintain previous versions of nodes for rollback.</div>
            </div>
        </div>

    </div>

    <div style="text-align: right;">
        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 1rem; border-radius: 8px; background: var(--accent-primary); border: none; color: white; cursor: pointer; font-weight: 600;">
            💾 Save Configuration
        </button>
    </div>
</form>
@endsection
