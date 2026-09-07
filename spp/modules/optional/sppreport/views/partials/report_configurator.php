<?php
/**
 * Standalone external partial for SPPReport schema explorer and query configurator.
 * Adheres to Zero Inline HTML Literals rule in controllers by encapsulating layout here.
 */
$schema = $data['schema'] ?? [];
$selectedTable = $data['table'] ?? (empty($schema) ? '' : array_key_first($schema));
$columns = $data['columns'] ?? ($selectedTable ? ($schema[$selectedTable] ?? []) : []);
$driver = $data['driver'] ?? 'PDO MySQL';
?>
<div class="spp-report-configurator" style="width: 100%; display: grid; grid-template-columns: minmax(320px, 360px) 1fr; gap: 28px; animation: sppFadeIn 0.3s ease-out;">
    <!-- Configurator Sidebar Panel -->
    <div class="spp-config-sidebar" style="background: var(--sppux-panel, rgba(255,255,255,0.6)); border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.1)); border-radius: 18px; padding: 24px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 8px 32px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--sppux-text, #0f172a);">Query Builder</h3>
            <span style="font-size: 0.75rem; background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.3);"><?= htmlspecialchars($driver) ?></span>
        </div>
        
        <form id="spp-report-query-form" hx-post="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=preview" hx-target="#spp-report-preview-container" hx-swap="innerHTML" style="display: flex; flex-direction: column; gap: 20px;">
            <!-- AI Natural Language Prompt -->
            <div style="display: flex; gap: 8px; align-items: center; background: rgba(99, 102, 241, 0.05); border: 1px dashed rgba(99, 102, 241, 0.4); padding: 12px 16px; border-radius: 14px;">
                <span style="font-size: 1.2rem;">✨</span>
                <input type="text" name="query" placeholder="Ask AI: Sales over 1000 by region..." style="flex: 1; border: none; background: transparent; outline: none; font-size: 0.9rem; color: var(--sppux-text, #0f172a);">
                <button type="button" hx-post="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=ai_build" hx-target="#spp-configurator-container" class="spp-btn-ai" style="background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.4); color: #6366f1; font-weight: 700; padding: 6px 12px; border-radius: 10px; cursor: pointer; transition: all 0.2s ease;">AI Build</button>
            </div>

            <!-- Table Selection -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--sppux-text, #334155); margin-bottom: 8px;">Base Table</label>
                <select name="table" hx-get="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=schema" hx-target="#spp-configurator-container" hx-trigger="change" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.2)); background: var(--sppux-bg, #ffffff); color: var(--sppux-text, #0f172a); font-weight: 600; outline: none; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                    <?php foreach ($schema as $tableName => $cols): ?>
                        <option value="<?= htmlspecialchars($tableName) ?>" <?= $tableName === $selectedTable ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tableName) ?> (<?= count($cols) ?> cols)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Template Selection -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--sppux-text, #334155); margin-bottom: 8px;">Presentation Template</label>
                <select name="template_name" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.2)); background: var(--sppux-bg, #ffffff); color: var(--sppux-text, #0f172a); font-weight: 600; outline: none; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                    <option value="partials/report_template_dual_mode.php">⭐ Flagship Dual-Mode (Screen & Print)</option>
                    <option value="partials/report_preview.php">Standard Data Grid Preview</option>
                </select>
            </div>

            <!-- Columns Selector -->
            <div>
                <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--sppux-text, #334155); margin-bottom: 8px;">Select Columns</label>
                <div style="max-height: 180px; overflow-y: auto; border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.15)); border-radius: 12px; padding: 12px; background: rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($columns as $col): ?>
                        <label style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: var(--sppux-text, #334155); cursor: pointer;">
                            <input type="checkbox" name="columns[]" value="<?= htmlspecialchars($col) ?>" checked style="accent-color: #6366f1; width: 16px; height: 16px;">
                            <?= htmlspecialchars($col) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Filters Section -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label style="font-weight: 600; font-size: 0.85rem; color: var(--sppux-text, #334155);">Runtime Filters</label>
                    <button type="button" onclick="addFilterRow()" style="background: none; border: none; color: #6366f1; font-weight: 700; font-size: 0.85rem; cursor: pointer;">+ Add Filter</button>
                </div>
                <div id="spp-filter-rows-container" style="display: flex; flex-direction: column; gap: 10px;">
                    <div class="spp-filter-row" style="display: flex; gap: 8px; align-items: center;">
                        <select name="filter_field[]" style="flex: 2; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.15); background: var(--sppux-bg, white); font-size: 0.85rem;">
                            <?php foreach ($columns as $col): ?>
                                <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="filter_operator[]" style="flex: 1; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.15); background: var(--sppux-bg, white); font-size: 0.85rem;">
                            <option value="=">=</option>
                            <option value="!=">!=</option>
                            <option value=">">&gt;</option>
                            <option value="<">&lt;</option>
                            <option value="LIKE">LIKE</option>
                        </select>
                        <input type="text" name="filter_value[]" placeholder="Value" style="flex: 2; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.15); background: var(--sppux-bg, white); font-size: 0.85rem;">
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #ef4444; font-size: 1.2rem; cursor: pointer;">×</button>
                    </div>
                </div>
            </div>

            <!-- Group By & Order By -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--sppux-text, #334155); margin-bottom: 8px;">Order By</label>
                    <select name="order_by" style="width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.15); background: var(--sppux-bg, white); font-size: 0.85rem;">
                        <option value="">None</option>
                        <?php foreach ($columns as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--sppux-text, #334155); margin-bottom: 8px;">Direction</label>
                    <select name="order_direction" style="width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.15); background: var(--sppux-bg, white); font-size: 0.85rem;">
                        <option value="ASC">ASC</option>
                        <option value="DESC">DESC</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 10px;">
                <button type="submit" class="spp-btn-premium" style="flex: 1; padding: 14px; border-radius: 14px; border: none; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; font-weight: 700; cursor: pointer; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3); transition: transform 0.1s ease;">
                    🚀 Generate Preview
                </button>
            </div>
        </form>
    </div>

    <!-- Preview Area Mounting Point -->
    <div id="spp-report-preview-container" style="background: var(--sppux-panel, rgba(255,255,255,0.6)); border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.1)); border-radius: 18px; padding: 28px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 8px 32px rgba(0,0,0,0.04); display: flex; flex-direction: column;">
        <div style="flex: 1; display: flex; align-items: center; justify-content: center; border: 1px dashed rgba(100, 116, 139, 0.3); border-radius: 14px; background: rgba(100, 116, 139, 0.02); min-height: 400px;">
            <div style="text-align: center; color: var(--sppux-text-dim, #64748b);">
                <div style="font-size: 2.8rem; margin-bottom: 12px;">📊</div>
                <h3 style="font-size: 1.2rem; font-weight: 600; margin: 0 0 6px 0; color: var(--sppux-text, #334155);">Ready to Query</h3>
                <p style="margin: 0; font-size: 0.9rem;">Adjust the table, columns, and filters in the query builder, then click 'Generate Preview'.</p>
            </div>
        </div>
    </div>
</div>

<script>
function addFilterRow() {
    const container = document.getElementById('spp-filter-rows-container');
    if (!container) return;
    const firstRow = container.querySelector('.spp-filter-row');
    if (!firstRow) return;
    const clone = firstRow.cloneNode(true);
    clone.querySelector('input').value = '';
    container.appendChild(clone);
}
</script>
