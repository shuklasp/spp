/**
 * ViewsBuilderUX
 * 
 * Simple Vanilla JS implementation for a drag-and-drop Views Builder.
 * This script is meant to be included in the Lekhak Admin Panel when editing a ViewDefinition.
 */

document.addEventListener('DOMContentLoaded', () => {
    const builderContainer = document.getElementById('spp-views-builder');
    if (!builderContainer) return;

    // A mock state of available fields
    const availableFields = ['id', 'title', 'body', 'created_at', 'status', 'author'];
    
    // Build UI
    builderContainer.innerHTML = `
        <div class="spp-views-layout">
            <div class="spp-views-sidebar">
                <h3>Available Fields</h3>
                <ul id="spp-available-fields" class="spp-draggable-list">
                    ${availableFields.map(f => `<li draggable="true" data-field="${f}">${f}</li>`).join('')}
                </ul>
            </div>
            <div class="spp-views-main">
                <div class="spp-views-section">
                    <h3>Selected Fields <small>(Drag here to add)</small></h3>
                    <ul id="spp-selected-fields" class="spp-droppable-list spp-draggable-list">
                        <!-- populated via JS or server -->
                    </ul>
                </div>
                
                <div class="spp-views-section">
                    <h3>Filters</h3>
                    <button type="button" id="spp-add-filter" class="spp-btn spp-btn-sm">+ Add Filter</button>
                    <div id="spp-filters-list"></div>
                </div>

                <div class="spp-views-section">
                    <h3>Sort Criteria</h3>
                    <button type="button" id="spp-add-sort" class="spp-btn spp-btn-sm">+ Add Sort</button>
                    <div id="spp-sorts-list"></div>
                </div>
            </div>
        </div>
        <style>
            .spp-views-layout { display: flex; gap: 20px; }
            .spp-views-sidebar { width: 250px; background: #f8f9fa; padding: 15px; border-radius: 5px; }
            .spp-views-main { flex: 1; }
            .spp-views-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
            .spp-draggable-list { list-style: none; padding: 0; min-height: 50px; border: 1px dashed #ccc; }
            .spp-draggable-list li { padding: 8px 12px; margin: 5px; background: #fff; border: 1px solid #ddd; cursor: grab; }
            .spp-droppable-list.drag-over { background: #e9ecef; }
            .spp-filter-row, .spp-sort-row { display: flex; gap: 10px; margin-top: 10px; align-items: center; }
        </style>
    `;

    // Drag and Drop Logic
    let draggedItem = null;

    document.querySelectorAll('.spp-draggable-list li').forEach(item => {
        item.addEventListener('dragstart', function() {
            draggedItem = this;
            setTimeout(() => this.style.display = 'none', 0);
        });
        item.addEventListener('dragend', function() {
            setTimeout(() => {
                draggedItem.style.display = 'block';
                draggedItem = null;
                updateJSONFields();
            }, 0);
        });
    });

    const dropzones = document.querySelectorAll('.spp-droppable-list');
    dropzones.forEach(zone => {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        zone.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        zone.addEventListener('drop', function() {
            this.classList.remove('drag-over');
            if (draggedItem && !this.contains(draggedItem)) {
                // clone if dragging from available to selected
                const clone = draggedItem.cloneNode(true);
                // setup drag events for clone
                clone.addEventListener('dragstart', function() {
                    draggedItem = this;
                    setTimeout(() => this.style.display = 'none', 0);
                });
                clone.addEventListener('dragend', function() {
                    setTimeout(() => {
                        draggedItem.style.display = 'block';
                        draggedItem = null;
                        updateJSONFields();
                    }, 0);
                });
                // add delete button to clone
                const delBtn = document.createElement('span');
                delBtn.innerHTML = ' &times;';
                delBtn.style.cursor = 'pointer';
                delBtn.style.color = 'red';
                delBtn.onclick = function() { clone.remove(); updateJSONFields(); };
                clone.appendChild(delBtn);

                this.appendChild(clone);
            }
        });
    });

    // Helper to sync UI to a hidden input
    function updateJSONFields() {
        const selected = Array.from(document.getElementById('spp-selected-fields').children).map(li => li.getAttribute('data-field'));
        const input = document.getElementById('edit-fields');
        if (input) input.value = JSON.stringify(selected);
    }
});
