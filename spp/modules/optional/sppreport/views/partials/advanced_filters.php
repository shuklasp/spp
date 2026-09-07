<?php
/**
 * Advanced Filters Partial
 * Dynamically renders filter inputs based on the database schema.
 */
$schema = $schema ?? [];
$table = $table ?? '';
$activeFilters = $activeFilters ?? [];

$columns = $schema[$table] ?? [];
?>
<div class="spp-advanced-filters card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Advanced Filters</h5>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-filter-btn">+ Add Filter</button>
    </div>
    <div class="card-body">
        <form id="spp-filter-form" hx-post="?report_action=preview" hx-target="#spp-report-results">
            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
            <div id="filter-rows">
                <!-- Template row for JS -->
                <div class="filter-row-template d-none row mb-2">
                    <div class="col-md-3">
                        <select name="filter_field[]" class="form-select filter-field-select">
                            <option value="">Select Field...</option>
                            <?php foreach ($columns as $col): ?>
                                <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $col))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="filter_operator[]" class="form-select filter-op-select">
                            <option value="=">Equals (=)</option>
                            <option value="!=">Not Equals (!=)</option>
                            <option value=">">Greater Than (>)</option>
                            <option value="<">Less Than (<)</option>
                            <option value="LIKE">Contains (LIKE)</option>
                            <option value="BETWEEN">Between</option>
                            <option value="IN">In List</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="filter_value[]" class="form-control filter-value-input" placeholder="Value...">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger remove-filter-btn">&times;</button>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Apply Filters & Refresh</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('add-filter-btn');
    const container = document.getElementById('filter-rows');
    const template = document.querySelector('.filter-row-template');
    
    if (addBtn && container && template) {
        addBtn.addEventListener('click', function() {
            const clone = template.cloneNode(true);
            clone.classList.remove('d-none', 'filter-row-template');
            
            clone.querySelector('.remove-filter-btn').addEventListener('click', function() {
                clone.remove();
            });
            
            container.appendChild(clone);
        });
    }
});
</script>
