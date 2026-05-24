@extends('layout')

@section('actions')
<button onclick="openCreateTypeModal()" class="btn btn-primary">
    <span>➕</span> Add Content Type
</button>
@endsection

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

<div class="types-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">
    @foreach($schemas ?? [] as $id => $schema)
    <div class="glass-panel type-card" style="padding: 20px; position: relative; transition: transform 0.2s; border: 1px solid var(--glass-border);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div>
                <h3 style="margin: 0 0 5px 0; font-size: 1.25rem; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 1.5rem;">📄</span> {{ $schema['name'] }}
                </h3>
                <div style="font-size: 0.75rem; font-family: monospace; color: var(--accent-primary); margin-bottom: 8px;">Machine name: {{ $id }}</div>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-dim);">{{ $schema['description'] }}</p>
            </div>
            
            <form method="POST" action="" style="margin: 0;" onsubmit="return confirm('Are you sure you want to permanently delete this content type? This does not delete the existing nodes.');">
                <input type="hidden" name="action" value="delete_type">
                <input type="hidden" name="id" value="{{ $id }}">
                <button type="submit" style="background: transparent; border: none; color: var(--danger); cursor: pointer; padding: 4px; border-radius: 4px;" title="Delete Content Type" onmouseover="this.style.background='rgba(239, 68, 68, 0.1)'" onmouseout="this.style.background='transparent'">
                    🗑️
                </button>
            </form>
        </div>
        
        <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 15px;">
            <div style="font-size: 0.85rem; color: var(--text-dim);">
                {{ count($schema['fields'] ?? []) }} custom fields
            </div>
            <button onclick='openFieldsModal("{{ $id }}", {{ json_encode($schema["name"]) }}, {{ json_encode($schema["fields"] ?? []) }})' class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;">
                ⚙️ Manage Fields
            </button>
        </div>
    </div>
    @endforeach
</div>

<!-- Modal: Add Content Type -->
<div id="typeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; padding: 30px; position: relative; animation: modalSlideIn 0.3s ease-out;">
        <button type="button" onclick="closeCreateTypeModal()" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-dim); font-size: 1.25rem; cursor: pointer;">✕</button>
        
        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.25rem; color: var(--text-main);">Add Content Type</h3>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_type">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-size: 0.85rem;">Name</label>
                <input type="text" name="name" id="newTypeName" required class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px;" onkeyup="generateMachineName()">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-size: 0.85rem;">Machine Name</label>
                <input type="text" name="id" id="newTypeId" required class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--accent-primary); border-radius: 6px; font-family: monospace;">
                <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.</div>
            </div>
            
            <div class="form-group" style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-size: 0.85rem;">Description</label>
                <textarea name="description" rows="3" class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeCreateTypeModal()" class="btn btn-secondary" style="padding: 10px 20px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Save Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Manage Fields -->
<div id="fieldsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div class="glass-panel" style="max-width: 700px; width: 100%; padding: 30px; position: relative; animation: modalSlideIn 0.3s ease-out; display: flex; flex-direction: column; max-height: 90vh;">
        <button type="button" onclick="closeFieldsModal()" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-dim); font-size: 1.25rem; cursor: pointer;">✕</button>
        
        <h3 id="fieldsModalTitle" style="margin-top: 0; margin-bottom: 10px; font-size: 1.25rem; color: var(--text-main);">Manage Fields</h3>
        <p style="margin: 0 0 20px 0; font-size: 0.85rem; color: var(--text-dim);">Define the dynamic JSON data schema for this content type.</p>
        
        <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
            <button type="button" onclick="addFieldRow()" class="btn btn-secondary" style="font-size: 0.85rem; padding: 6px 12px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border);">
                ➕ Add New Field
            </button>
        </div>
        
        <div style="overflow-y: auto; flex: 1; margin-bottom: 20px; padding-right: 10px;">
            <div id="fieldsContainer" style="display: flex; flex-direction: column; gap: 10px;">
                <!-- Dynamically populated -->
            </div>
        </div>
        
        <form method="POST" action="" id="fieldsForm" style="border-top: 1px solid var(--glass-border); padding-top: 20px;">
            <input type="hidden" name="action" value="save_fields">
            <input type="hidden" name="type_id" id="fieldsTypeId">
            <input type="hidden" name="fields_json" id="fieldsJson">
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeFieldsModal()" class="btn btn-secondary" style="padding: 10px 20px;">Cancel</button>
                <button type="button" onclick="saveFields()" class="btn btn-primary" style="padding: 10px 20px;">💾 Save Schema</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.type-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
}
.field-row {
    display: grid;
    grid-template-columns: 2fr 2fr 2fr auto;
    gap: 12px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 12px;
    border-radius: 6px;
    align-items: center;
}
</style>

<script>
function openCreateTypeModal() {
    document.getElementById('typeModal').style.display = 'flex';
}

function closeCreateTypeModal() {
    document.getElementById('typeModal').style.display = 'none';
}

function generateMachineName() {
    const name = document.getElementById('newTypeName').value;
    const machineName = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
    document.getElementById('newTypeId').value = machineName;
}

function openFieldsModal(typeId, typeName, fields) {
    document.getElementById('fieldsModalTitle').textContent = 'Manage Fields: ' + typeName;
    document.getElementById('fieldsTypeId').value = typeId;
    
    const container = document.getElementById('fieldsContainer');
    container.innerHTML = '';
    
    if (fields.length === 0) {
        container.innerHTML = '<div id="emptyFieldsMsg" style="text-align: center; color: var(--text-dim); padding: 20px; font-size: 0.9rem;">No custom fields defined yet.</div>';
    } else {
        fields.forEach(f => addFieldRow(f.name, f.label, f.type));
    }
    
    document.getElementById('fieldsModal').style.display = 'flex';
}

function closeFieldsModal() {
    document.getElementById('fieldsModal').style.display = 'none';
}

function addFieldRow(name = '', label = '', type = 'text') {
    const msg = document.getElementById('emptyFieldsMsg');
    if (msg) msg.remove();
    
    const container = document.getElementById('fieldsContainer');
    const rowId = 'field-row-' + Date.now() + Math.random().toString(36).substr(2, 5);
    
    const row = document.createElement('div');
    row.id = rowId;
    row.className = 'field-row';
    
    row.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 0.75rem; color: var(--text-dim);">Field Label</label>
            <input type="text" class="f-label form-control" value="${label.replace(/"/g, '&quot;')}" placeholder="e.g. Hero Image" style="background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.85rem;" onkeyup="generateFieldMachineName(this)">
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 0.75rem; color: var(--text-dim);">Machine Name</label>
            <input type="text" class="f-name form-control" value="${name}" placeholder="e.g. field_hero_image" style="background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--accent-primary); padding: 6px 10px; border-radius: 4px; font-size: 0.85rem; font-family: monospace;">
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 0.75rem; color: var(--text-dim);">Field Type</label>
            <select class="f-type form-control" style="background: var(--sidebar-bg); border: 1px solid var(--glass-border); color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.85rem;">
                <option value="text" ${type === 'text' ? 'selected' : ''}>Text (Plain)</option>
                <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>Long Text</option>
                <option value="image" ${type === 'image' ? 'selected' : ''}>Image Reference</option>
                <option value="tags" ${type === 'tags' ? 'selected' : ''}>Taxonomy Tags</option>
                <option value="date" ${type === 'date' ? 'selected' : ''}>Date</option>
                <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>Boolean (Checkbox)</option>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end; padding-bottom: 2px;">
            <button type="button" onclick="document.getElementById('${rowId}').remove()" class="btn btn-secondary" style="padding: 6px 10px; color: var(--danger); background: transparent; border: none; cursor: pointer;" title="Remove Field">🗑️</button>
        </div>
    `;
    
    container.appendChild(row);
}

function generateFieldMachineName(labelInput) {
    const row = labelInput.closest('.field-row');
    const nameInput = row.querySelector('.f-name');
    
    // Only auto-generate if name input is empty or we just started typing
    if (nameInput.value === '' || nameInput.value.startsWith('field_')) {
        const base = labelInput.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
        if (base) {
            nameInput.value = 'field_' + base;
        } else {
            nameInput.value = '';
        }
    }
}

function saveFields() {
    const rows = document.querySelectorAll('.field-row');
    const fields = [];
    let hasError = false;
    
    rows.forEach(row => {
        const label = row.querySelector('.f-label').value.trim();
        const name = row.querySelector('.f-name').value.trim();
        const type = row.querySelector('.f-type').value;
        
        if (!name || !label) {
            hasError = true;
            row.style.borderColor = 'var(--danger)';
        } else {
            row.style.borderColor = 'rgba(255,255,255,0.05)';
            fields.push({ name, label, type });
        }
    });
    
    if (hasError) {
        alert("Please ensure all fields have both a Label and a Machine Name.");
        return;
    }
    
    document.getElementById('fieldsJson').value = JSON.stringify(fields);
    document.getElementById('fieldsForm').submit();
}

window.addEventListener('click', function(event) {
    const m1 = document.getElementById('typeModal');
    const m2 = document.getElementById('fieldsModal');
    if (event.target === m1) closeCreateTypeModal();
    if (event.target === m2) closeFieldsModal();
});
</script>
@endsection
