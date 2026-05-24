<?php
$file = __DIR__ . '/resources/themes/lekhak_themes/glass_admin/views/views_admin.blade.php';
$content = file_get_contents($file);

$posForm = strpos($content, '<form id="viewsBuilderForm">');
$header = substr($content, 0, $posForm);

$newFormAndScript = <<<'HTML'
<form id="viewsBuilderForm">
            <!-- Master Configuration -->
            <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--glass-border);">
                <h4 style="margin-top: 0; color: var(--text-main); margin-bottom: 16px;">Master Query Configuration</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">View ID (Machine Name)</label>
                        <input type="text" id="viewId" name="id" required placeholder="e.g. recent_articles" class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">View Name</label>
                        <input type="text" id="viewName" name="name" required placeholder="e.g. Recent Articles" class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Entity Schema</label>
                    <select id="viewEntityClass" name="entity_class" required class="form-control" style="width: 100%; padding: 10px 14px; background: var(--sidebar-bg); border: 1px solid var(--glass-border); color: var(--text); border-radius: 6px; outline: none; font-size: 0.9rem; cursor: pointer;">
                        <?php foreach ($entitySchemas as $label => $class): ?>
                            <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Display Fields (Comma Separated)</label>
                    <input type="text" id="viewDisplayFields" name="display_fields" value="id,title,status" required placeholder="id,title,status,created" class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Query Conditions</label>
                        <button type="button" onclick="addConditionRow()" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem; background: rgba(255,255,255,0.1); border:none; border-radius:4px; color:white;">➕ Add Condition</button>
                    </div>
                    <div id="conditionsContainer" style="display: flex; flex-direction: column; gap: 8px;"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Sort By Field</label>
                        <input type="text" id="viewSortField" placeholder="e.g. created" class="form-control" style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 6px; outline: none; font-size: 0.9rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 500; font-size: 0.85rem;">Direction</label>
                        <select id="viewSortDirection" class="form-control" style="width: 100%; padding: 10px 14px; background: var(--sidebar-bg); border: 1px solid var(--glass-border); color: var(--text); border-radius: 6px; outline: none; font-size: 0.9rem; cursor: pointer;">
                            <option value="DESC">Descending (Newest First)</option>
                            <option value="ASC">Ascending (Oldest First)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Displays Section -->
            <div style="margin-bottom: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h4 style="margin: 0; color: var(--text-main);">Displays</h4>
                    <div>
                        <button type="button" onclick="addDisplay('page')" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--primary); border:none; border-radius:4px; color:white; margin-right: 8px;">➕ Add Page</button>
                        <button type="button" onclick="addDisplay('block')" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--accent-secondary); border:none; border-radius:4px; color:white;">➕ Add Block</button>
                    </div>
                </div>
                <div id="displaysContainer" style="display: flex; flex-direction: column; gap: 16px;"></div>
            </div>

            <div style="text-align: right;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 1rem; border-radius: 8px; background: var(--accent-primary); border: none; color: white; cursor: pointer; font-weight: 600;">
                    💾 Save View Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-control:focus {
    border-color: var(--accent-primary) !important;
    background: rgba(255,255,255,0.06) !important;
}
.view-item-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    padding: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.view-item-card:hover {
    border-color: var(--accent-primary);
    background: rgba(255, 255, 255, 0.05);
}
.view-item-card.active {
    border-color: var(--accent-primary);
    background: rgba(99, 102, 241, 0.1);
    box-shadow: inset 0 0 10px rgba(99, 102, 241, 0.15);
}
.display-card {
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    position: relative;
}
</style>

<script>
const viewsData = @json(array_values($views ?? []));

let currentEditingId = null;
let displayCount = 0;

function renderViewsList() {
    const container = document.getElementById('viewsListContainer');
    container.innerHTML = '';
    
    if (viewsData.length === 0) {
        container.innerHTML = '<div style="color: var(--text-dim); text-align: center; padding: 20px; font-size: 0.9rem;">No custom views built yet.</div>';
        return;
    }
    
    viewsData.forEach(view => {
        const activeClass = currentEditingId === view.id ? 'active' : '';
        const card = document.createElement('div');
        card.className = `view-item-card ${activeClass}`;
        card.onclick = () => editView(view.id);
        
        let displaysBadge = (view.displays || []).length;
        
        card.innerHTML = `
            <div style="flex-grow: 1;">
                <div style="font-weight: 600; color: var(--text-main); font-size: 0.95rem;">${view.name}</div>
                <div style="font-size: 0.75rem; color: var(--text-dim); font-family: monospace; margin-top: 2px;">ID: ${view.id} | ${displaysBadge} Displays</div>
            </div>
            <span style="font-size: 1.2rem;">👁️</span>
        `;
        container.appendChild(card);
    });
}

function createNewView() {
    currentEditingId = null;
    document.getElementById('formPanelTitle').textContent = 'Create Query View';
    
    document.getElementById('viewId').value = '';
    document.getElementById('viewId').disabled = false;
    document.getElementById('viewName').value = '';
    
    const entityClassSelect = document.getElementById('viewEntityClass');
    if (entityClassSelect.options.length > 0) {
        let found = false;
        for(let i=0; i<entityClassSelect.options.length; i++) {
            if (entityClassSelect.options[i].value === '\\SPPMod\\Lekhak\\Core\\LekhakNode') {
                entityClassSelect.selectedIndex = i;
                found = true; break;
            }
        }
        if (!found) entityClassSelect.selectedIndex = 0;
    }
    
    document.getElementById('viewDisplayFields').value = 'id,title,status';
    document.getElementById('viewSortField').value = '';
    document.getElementById('viewSortDirection').value = 'DESC';
    document.getElementById('conditionsContainer').innerHTML = '';
    document.getElementById('displaysContainer').innerHTML = '';
    
    addDisplay('page');
    renderViewsList();
}

function editView(id) {
    const view = viewsData.find(v => v.id === id);
    if (!view) return;
    
    currentEditingId = id;
    document.getElementById('formPanelTitle').textContent = 'Edit Query View: ' + view.name;
    
    document.getElementById('viewId').value = view.id;
    document.getElementById('viewId').disabled = true;
    document.getElementById('viewName').value = view.name;
    
    if (view.entity_class) {
        let cls = view.entity_class.replace(/\\\\/g, '\\');
        document.getElementById('viewEntityClass').value = cls;
    }
    
    document.getElementById('viewDisplayFields').value = (view.display_fields || []).join(', ');
    
    if (view.sort) {
        document.getElementById('viewSortField').value = view.sort.field || '';
        document.getElementById('viewSortDirection').value = view.sort.direction || 'DESC';
    } else {
        document.getElementById('viewSortField').value = '';
        document.getElementById('viewSortDirection').value = 'DESC';
    }
    
    const container = document.getElementById('conditionsContainer');
    container.innerHTML = '';
    if (view.conditions && view.conditions.length > 0) {
        view.conditions.forEach(cond => {
            addConditionRow(cond.field, cond.operator, cond.value);
        });
    }
    
    document.getElementById('displaysContainer').innerHTML = '';
    if (view.displays && view.displays.length > 0) {
        view.displays.forEach(disp => {
            addDisplay(disp.type, disp);
        });
    } else {
        if (view.limit || view.template) {
            addDisplay('page', { limit: view.limit, template: view.template, path: '/view/' + view.id, name: 'Legacy Page' });
        }
    }
    
    renderViewsList();
}

function addConditionRow(field = '', operator = '=', value = '') {
    const container = document.getElementById('conditionsContainer');
    const rowId = 'cond-row-' + Date.now() + Math.random().toString(36).substr(2, 5);
    
    const row = document.createElement('div');
    row.id = rowId;
    row.className = 'condition-row';
    row.style.display = 'grid';
    row.style.gridTemplateColumns = '2fr 1fr 2fr auto';
    row.style.gap = '8px';
    row.style.alignItems = 'center';
    
    row.innerHTML = `
        <input type="text" placeholder="Field key (e.g. status)" value="${field}" class="form-control cond-field" style="padding: 8px 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); color: white; border-radius: 4px; font-size: 0.85rem;">
        <select class="form-control cond-operator" style="padding: 8px 12px; background: var(--sidebar-bg); border: 1px solid var(--glass-border); color: var(--text); border-radius: 4px; font-size: 0.85rem;">
            <option value="=" ${operator === '=' ? 'selected' : ''}>=</option>
            <option value="!=" ${operator === '!=' ? 'selected' : ''}>!=</option>
            <option value="LIKE" ${operator === 'LIKE' ? 'selected' : ''}>LIKE</option>
            <option value=">" ${operator === '>' ? 'selected' : ''}>&gt;</option>
            <option value="<" ${operator === '<' ? 'selected' : ''}>&lt;</option>
            <option value="IN" ${operator === 'IN' ? 'selected' : ''}>IN</option>
        </select>
        <input type="text" placeholder="Value" value="${value}" class="form-control cond-value" style="padding: 8px 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); color: white; border-radius: 4px; font-size: 0.85rem;">
        <button type="button" onclick="document.getElementById('${rowId}').remove()" class="btn btn-secondary" style="padding: 8px; color: var(--danger); font-size: 0.85rem; border: none; background: transparent; cursor: pointer;">✕</button>
    `;
    
    container.appendChild(row);
}

function addDisplay(type, data = {}) {
    displayCount++;
    const container = document.getElementById('displaysContainer');
    const dispId = 'display-card-' + Date.now() + displayCount;
    
    const card = document.createElement('div');
    card.id = dispId;
    card.className = 'display-card';
    card.setAttribute('data-type', type);
    
    const name = data.name || (type === 'page' ? 'Page Display' : 'Block Display');
    const limit = data.limit || 10;
    const template = data.template || '';
    const path = data.path || '';
    const pagination = data.pagination || 'none';
    
    let specificFields = '';
    if (type === 'page') {
        specificFields = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="display: block; margin-bottom: 4px; font-size: 0.8rem; color: var(--text-main);">Page Path</label>
                    <input type="text" class="form-control disp-path" placeholder="/news" value="${path}" required style="width: 100%; padding: 8px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px; font-size: 0.85rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="display: block; margin-bottom: 4px; font-size: 0.8rem; color: var(--text-main);">Pagination</label>
                    <select class="form-control disp-pagination" style="width: 100%; padding: 8px 12px; background: var(--sidebar-bg); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px; font-size: 0.85rem;">
                        <option value="none" ${pagination === 'none' ? 'selected' : ''}>None</option>
                        <option value="traditional" ${pagination === 'traditional' ? 'selected' : ''}>Traditional (Next/Prev)</option>
                        <option value="infinite" ${pagination === 'infinite' ? 'selected' : ''}>Infinite Scroll</option>
                    </select>
                </div>
            </div>
        `;
    }

    card.innerHTML = `
        <button type="button" onclick="document.getElementById('${dispId}').remove()" style="position: absolute; top: 12px; right: 12px; background: transparent; border: none; color: #ef4444; cursor: pointer;">✕ Remove</button>
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
            <span style="font-size: 1.2rem;">${type === 'page' ? '📄' : '🧱'}</span>
            <input type="text" class="form-control disp-name" value="${name}" required style="background: transparent; border: none; border-bottom: 1px dashed var(--glass-border); color: var(--text-main); font-size: 1rem; font-weight: 600; outline: none; padding: 4px 0; width: 250px;">
        </div>
        ${specificFields}
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 4px; font-size: 0.8rem; color: var(--text-main);">Items Limit</label>
                <input type="number" class="form-control disp-limit" value="${limit}" min="0" style="width: 100%; padding: 8px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px; font-size: 0.85rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 4px; font-size: 0.8rem; color: var(--text-main);">Template (Optional)</label>
                <input type="text" class="form-control disp-template" placeholder="views/my-template" value="${template}" style="width: 100%; padding: 8px 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px; font-size: 0.85rem;">
            </div>
        </div>
    `;
    
    container.appendChild(card);
}

document.getElementById('viewsBuilderForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const displayFieldsVal = document.getElementById('viewDisplayFields').value;
    const displayFields = displayFieldsVal.split(',').map(s => s.trim()).filter(Boolean);
    
    const conditionRows = document.querySelectorAll('.condition-row');
    const conditions = [];
    conditionRows.forEach(row => {
        const field = row.querySelector('.cond-field').value.trim();
        const operator = row.querySelector('.cond-operator').value;
        const value = row.querySelector('.cond-value').value.trim();
        if (field) conditions.push({ field, operator, value });
    });
    
    const sortField = document.getElementById('viewSortField').value.trim();
    const sortDirection = document.getElementById('viewSortDirection').value;
    
    const displays = [];
    document.querySelectorAll('.display-card').forEach((card, idx) => {
        const type = card.getAttribute('data-type');
        const disp = {
            id: type + '_' + idx,
            type: type,
            name: card.querySelector('.disp-name').value.trim(),
            limit: parseInt(card.querySelector('.disp-limit').value) || 10,
            template: card.querySelector('.disp-template').value.trim() || null
        };
        if (type === 'page') {
            disp.path = card.querySelector('.disp-path').value.trim();
            disp.pagination = card.querySelector('.disp-pagination').value;
        }
        displays.push(disp);
    });

    if (displays.length === 0) {
        alert("You must define at least one Display (Page or Block) for this view.");
        return;
    }
    
    const payload = {
        id: document.getElementById('viewId').value.trim(),
        name: document.getElementById('viewName').value.trim(),
        entity_class: document.getElementById('viewEntityClass').value.trim(),
        display_fields: displayFields,
        conditions: conditions,
        sort: sortField ? { field: sortField, direction: sortDirection } : null,
        displays: displays
    };
    
    try {
        const res = await fetch('?action=apiSaveView', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert('View configuration saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (err) {
        alert('API Request Failed. Check connection.');
    }
});

// Initial Render
renderViewsList();
if (viewsData.length > 0) {
    editView(viewsData[0].id);
} else {
    createNewView();
}
</script>
@endsection
HTML;

file_put_contents($file, $header . $newFormAndScript);
echo "UI updated successfully with pagination options!";
