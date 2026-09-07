@extends('layouts.admin')
@section('title', 'Schema Studio - ' . ($project['title'] ?? 'Project'))

@section('content')
<div class="adm-content-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="margin: 0; font-size: 1.6rem;">📐 Visual Content-Type & Schema Studio</h1>
            <p style="margin: 0.35rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.9rem;">
                Visually design collections, custom frontmatter fields, and validation rules for <strong>{{ $project['title'] ?? $projectId }}</strong>
            </p>
        </div>
        <button onclick="openSchemaModal()" class="adm-btn adm-btn-primary" style="padding: 0.6rem 1.25rem; font-weight: 600; cursor: pointer;">
            + New Content Type
        </button>
    </div>

    {{-- Explainer Banner --}}
    <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 2rem; font-size: 0.85rem; line-height: 1.6; color: var(--vp-c-text-2);">
        <strong style="color: var(--vp-c-text-1);">💡 Strapi/Sanity-Grade Schema Modeling:</strong>
        Define custom collections with typed fields (`text`, `textarea`, `number`, `boolean`, `date`, `select`, `tags`, `image`).
        When authors edit content of this type, the editor automatically renders bespoke UI form controls for these fields!
    </div>

    {{-- Schemas Grid --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
        @foreach($schemas as $typeName => $schema)
        <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <h2 style="margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                            {{ $schema['title'] ?? ucfirst($typeName) }}
                            @if(!empty($schema['is_builtin']))
                                <span style="font-size: 0.7rem; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); padding: 0.15rem 0.5rem; border-radius: 999px; color: var(--vp-c-text-3); font-weight: normal;">Built-in</span>
                            @else
                                <span style="font-size: 0.7rem; background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); padding: 0.15rem 0.5rem; border-radius: 999px; font-weight: 600;">Custom Collection</span>
                            @endif
                        </h2>
                        <code style="font-size: 0.8rem; color: var(--vp-c-brand); margin-top: 0.2rem; display: inline-block;">type: {{ $typeName }}</code>
                    </div>
                </div>

                <p style="margin: 0 0 1.25rem 0; color: var(--vp-c-text-2); font-size: 0.85rem; line-height: 1.5;">
                    {{ $schema['description'] ?: 'No description provided.' }}
                </p>

                <div style="border-top: 1px solid var(--vp-c-divider); padding-top: 1rem;">
                    <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--vp-c-text-3); margin-bottom: 0.75rem;">
                        Configured Fields ({{ count($schema['fields'] ?? []) }})
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($schema['fields'] ?? [] as $fKey => $field)
                        <div style="display: flex; justify-content: space-between; align-items: center; background: var(--vp-c-bg-soft); padding: 0.4rem 0.75rem; border-radius: 6px; border: 1px solid var(--vp-c-divider); font-size: 0.8rem;">
                            <div>
                                <strong style="color: var(--vp-c-text-1);">{{ $field['label'] ?? $fKey }}</strong>
                                <code style="color: var(--vp-c-text-3); font-size: 0.75rem; margin-left: 0.35rem;">{{ $fKey }}</code>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.4rem;">
                                @if(!empty($field['required']))
                                    <span style="color: #ef4444; font-size: 0.7rem; font-weight: bold;">*req</span>
                                @endif
                                <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; padding: 0.1rem 0.45rem; border-radius: 4px; font-size: 0.7rem; font-family: monospace; font-weight: 600;">
                                    {{ $field['type'] ?? 'text' }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--vp-c-divider); display: flex; justify-content: flex-end; gap: 0.5rem;">
                @if(empty($schema['is_builtin']))
                    <button onclick="editSchema('{{ $typeName }}', {{ json_encode($schema) }})" class="adm-btn adm-btn-secondary" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; cursor: pointer;">
                        Edit Schema
                    </button>
                    <form method="POST" action="{{ \SPP\App::getBaseUrl() }}/admin/schemas/delete" onsubmit="return confirm('Are you sure you want to delete this custom schema?');" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $projectId }}">
                        <input type="hidden" name="type" value="{{ $typeName }}">
                        <button type="submit" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; border: 1px solid #ef4444; color: #ef4444; background: transparent; border-radius: 6px; cursor: pointer;">
                            Delete
                        </button>
                    </form>
                @else
                    <span style="font-size: 0.8rem; color: var(--vp-c-text-3); font-style: italic;">Protected core schema</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- Schema Builder Modal --}}
<div id="schema-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
    <div style="background: var(--vp-c-bg); border-radius: 12px; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.25); padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 id="modal-title" style="margin: 0;">Create New Content Type</h2>
            <button onclick="document.getElementById('schema-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--vp-c-text-2);">✕</button>
        </div>

        <form method="POST" action="{{ \SPP\App::getBaseUrl() }}/admin/schemas/save">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $projectId }}">

            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Content Type Slug (lowercase, alphanumeric, hyphens) *</label>
                    <input type="text" id="schema-type-input" name="type" required placeholder="e.g. changelog, tutorial, release" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; font-family: monospace; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Display Title *</label>
                    <input type="text" id="schema-title-input" name="title" required placeholder="e.g. Product Changelog" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Description</label>
                    <textarea id="schema-desc-input" name="description" rows="2" placeholder="Explain the purpose of this content type..." style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; box-sizing: border-box;"></textarea>
                </div>
            </div>

            {{-- Fields List --}}
            <div style="border-top: 1px solid var(--vp-c-divider); padding-top: 1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="margin: 0; font-size: 1rem;">Custom Frontmatter Fields</h3>
                    <button type="button" onclick="addFieldRow()" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 6px; cursor: pointer;">
                        + Add Field
                    </button>
                </div>

                <div id="fields-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <!-- Dynamically populated rows -->
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('schema-modal').style.display='none'" style="padding: 0.6rem 1.2rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; background: var(--vp-c-bg); cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 0.6rem 1.5rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Save Content Type</button>
            </div>
        </form>
    </div>
</div>

<script>
let fieldCounter = 0;

function addFieldRow(key = '', label = '', type = 'text', required = false, options = '') {
    fieldCounter++;
    const container = document.getElementById('fields-container');
    const row = document.createElement('div');
    row.id = 'field-row-' + fieldCounter;
    row.style.cssText = 'background: var(--vp-c-bg-soft); padding: 0.85rem; border-radius: 8px; border: 1px solid var(--vp-c-divider); display: flex; flex-direction: column; gap: 0.5rem;';
    
    row.innerHTML = `
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" name="fields[${fieldCounter}][key]" value="${key}" placeholder="field_name" required style="flex: 1; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-family: monospace; font-size: 0.85rem;">
            <input type="text" name="fields[${fieldCounter}][label]" value="${label}" placeholder="Display Label" required style="flex: 1.5; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.85rem;">
            <select name="fields[${fieldCounter}][type]" style="flex: 1; padding: 0.45rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.85rem;">
                <option value="text" ${type === 'text' ? 'selected' : ''}>text</option>
                <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>textarea</option>
                <option value="markdown" ${type === 'markdown' ? 'selected' : ''}>markdown</option>
                <option value="number" ${type === 'number' ? 'selected' : ''}>number</option>
                <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>boolean</option>
                <option value="date" ${type === 'date' ? 'selected' : ''}>date</option>
                <option value="select" ${type === 'select' ? 'selected' : ''}>select</option>
                <option value="tags" ${type === 'tags' ? 'selected' : ''}>tags</option>
                <option value="image" ${type === 'image' ? 'selected' : ''}>image</option>
            </select>
            <label style="font-size: 0.8rem; display: flex; align-items: center; gap: 0.25rem; white-space: nowrap;">
                <input type="checkbox" name="fields[${fieldCounter}][required]" value="1" ${required ? 'checked' : ''}> Req
            </label>
            <button type="button" onclick="document.getElementById('field-row-${fieldCounter}').remove()" style="background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer; padding: 0 0.4rem;">✕</button>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" name="fields[${fieldCounter}][options]" value="${options}" placeholder="Options (comma-separated, for select type)" style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem;">
        </div>
    `;
    container.appendChild(row);
}

function openSchemaModal() {
    document.getElementById('modal-title').textContent = 'Create New Content Type';
    document.getElementById('schema-type-input').value = '';
    document.getElementById('schema-type-input').readOnly = false;
    document.getElementById('schema-title-input').value = '';
    document.getElementById('schema-desc-input').value = '';
    document.getElementById('fields-container').innerHTML = '';
    addFieldRow('title', 'Document Title', 'text', true);
    addFieldRow('description', 'Summary', 'textarea', false);
    document.getElementById('schema-modal').style.display = 'flex';
}

function editSchema(typeName, schema) {
    document.getElementById('modal-title').textContent = 'Edit Content Type: ' + (schema.title || typeName);
    document.getElementById('schema-type-input').value = typeName;
    document.getElementById('schema-type-input').readOnly = true;
    document.getElementById('schema-title-input').value = schema.title || '';
    document.getElementById('schema-desc-input').value = schema.description || '';
    
    const container = document.getElementById('fields-container');
    container.innerHTML = '';
    
    if (schema.fields) {
        for (const [key, field] of Object.entries(schema.fields)) {
            const opts = Array.isArray(field.options) ? field.options.join(', ') : (field.options || '');
            addFieldRow(key, field.label || key, field.type || 'text', !!field.required, opts);
        }
    }
    document.getElementById('schema-modal').style.display = 'flex';
}
</script>
@endsection