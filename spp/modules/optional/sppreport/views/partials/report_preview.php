<?php
/**
 * Standalone external partial for SPPReport data table preview and generated SQL.
 * Encapsulates presentation logic to completely eliminate inline HTML in controllers.
 * Includes interactive live search, column sorting, clipboard export, and print capabilities.
 */
$rows = $data['data'] ?? [];
$sql = $data['sql'] ?? '';
$error = $data['error'] ?? null;
$configPayload = $data['config_payload'] ?? '{}';
$encodedPayload = urlencode($configPayload);
$gridId = 'spp_report_' . uniqid();
$totalRows = count($rows);
?>
<div id="<?= $gridId ?>" class="spp-report-preview-content" style="display: flex; flex-direction: column; gap: 24px; width: 100%; animation: sppFadeIn 0.3s ease-out;">
    <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 14px; padding: 16px 20px; color: #ef4444; font-weight: 600; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.4rem;">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Preview Header & Export Actions -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; border-bottom: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.1)); padding-bottom: 20px;">
        <div>
            <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0 0 4px 0; color: var(--sppux-text, #0f172a);">Query Preview Results</h3>
            <p style="margin: 0; font-size: 0.85rem; color: var(--sppux-text-dim, #64748b);">
                Showing <strong class="spp-row-counter" style="color: #6366f1;"><?= $totalRows ?></strong> of <?= $totalRows ?> rows fetched from active database connection.
            </p>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
            <button type="button" onclick="sppCopyTableData('<?= $gridId ?>')" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 7px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; color: var(--sppux-text, #334155); cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                📋 Copy
            </button>
            <button type="button" onclick="window.print()" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 7px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; color: var(--sppux-text, #334155); cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                🖨️ Print
            </button>
            <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_csv&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 7px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                📄 CSV
            </a>
            <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_xls&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 7px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                📊 Excel
            </a>
            <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_pdf&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 7px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                📑 PDF
            </a>
        </div>
    </div>

    <!-- Generated SQL Statement -->
    <?php if ($sql): ?>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #6366f1; letter-spacing: 0.5px;">Generated SQL Query</div>
            <pre style="background: #0f172a; color: #38bdf8; padding: 16px 20px; border-radius: 14px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.85rem; overflow-x: auto; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); margin: 0;"><code><?= htmlspecialchars($sql) ?></code></pre>
        </div>
    <?php endif; ?>

    <!-- Interactive Search Toolbar -->
    <?php if (!empty($rows)): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
            <div style="position: relative; flex: 1; max-width: 380px;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 0.9rem; color: #94a3b8;">🔍</span>
                <input type="search" placeholder="Search records in real-time..." oninput="sppFilterReportTable('<?= $gridId ?>', this.value)" style="width: 100%; padding: 9px 12px 9px 36px; border: 1px solid var(--sppux-glass-border, #cbd5e1); border-radius: 10px; font-size: 0.85rem; outline: none; background: var(--sppux-bg, #fff); color: var(--sppux-text, #0f172a); transition: border-color 0.2s;" onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='var(--sppux-glass-border, #cbd5e1)'">
            </div>
            <span style="font-size: 0.78rem; color: var(--sppux-text-dim, #64748b);">Click column header to sort</span>
        </div>
    <?php endif; ?>

    <!-- Data Table Grid -->
    <div class="spp-grid-wrapper" style="border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.15)); border-radius: 16px; overflow-x: auto; box-shadow: 0 4px 16px rgba(0,0,0,0.02);">
        <?php if (empty($rows)): ?>
            <div style="padding: 48px; text-align: center; color: var(--sppux-text-dim, #64748b);">
                <div style="font-size: 2.4rem; margin-bottom: 12px;">📁</div>
                <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; font-weight: 600; color: var(--sppux-text, #334155);">No matching records found</h4>
                <p style="margin: 0; font-size: 0.9rem;">Try broadening your filter criteria in the query configurator.</p>
            </div>
        <?php else: ?>
            <table class="spp-report-data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--sppux-glass-bg, rgba(241, 245, 249, 0.8)); border-bottom: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.15)); user-select: none;">
                        <?php $colIdx = 0; foreach (array_keys($rows[0]) as $th): ?>
                            <th onclick="sppSortReportTable('<?= $gridId ?>', <?= $colIdx ?>)" style="padding: 14px 18px; font-weight: 600; color: var(--sppux-text, #0f172a); cursor: pointer; white-space: nowrap; transition: background 0.15s;" onmouseover="this.style.background='rgba(99, 102, 241, 0.08)'" onmouseout="this.style.background='transparent'">
                                <?= htmlspecialchars($th) ?> <span class="sort-indicator" style="font-size: 0.75rem; color: #94a3b8; margin-left: 4px;">↕</span>
                            </th>
                        <?php $colIdx++; endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr style="border-bottom: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.08)); background: <?= $index % 2 === 0 ? 'transparent' : 'rgba(248, 250, 252, 0.4)' ?>;">
                            <?php foreach ($row as $val): ?>
                                <td style="padding: 12px 18px; color: var(--sppux-text-dim, #334155);"><?= htmlspecialchars((string) $val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
if (typeof window.sppFilterReportTable === 'undefined') {
    window.sppFilterReportTable = function(containerId, query) {
        const root = document.getElementById(containerId);
        if (!root) return;
        const q = query.trim().toLowerCase();
        const trs = root.querySelectorAll('tbody tr');
        let visibleCount = 0;

        trs.forEach(tr => {
            const text = tr.textContent.toLowerCase();
            const match = q === '' || text.indexOf(q) !== -1;
            tr.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        const counter = root.querySelector('.spp-row-counter');
        if (counter) counter.textContent = visibleCount;
    };

    window.sppSortReportTable = function(containerId, colIdx) {
        const root = document.getElementById(containerId);
        if (!root) return;
        const tbody = root.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const th = root.querySelectorAll('th')[colIdx];
        if (!th) return;

        const isAsc = th.getAttribute('data-order') !== 'asc';
        root.querySelectorAll('th').forEach(h => {
            h.removeAttribute('data-order');
            const ind = h.querySelector('.sort-indicator');
            if (ind) ind.textContent = '↕';
        });

        th.setAttribute('data-order', isAsc ? 'asc' : 'desc');
        const indicator = th.querySelector('.sort-indicator');
        if (indicator) indicator.textContent = isAsc ? '▲' : '▼';

        rows.sort((a, b) => {
            const aVal = (a.children[colIdx]?.textContent || '').trim();
            const bVal = (b.children[colIdx]?.textContent || '').trim();
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAsc ? aNum - bNum : bNum - aNum;
            }
            return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });

        rows.forEach(r => tbody.appendChild(r));
    };

    window.sppCopyTableData = function(containerId) {
        const root = document.getElementById(containerId);
        if (!root) return;
        const table = root.querySelector('table');
        if (!table) return;

        let tsv = [];
        table.querySelectorAll('tr').forEach(tr => {
            if (tr.style.display === 'none') return;
            let row = [];
            tr.querySelectorAll('th, td').forEach(cell => {
                row.push(cell.innerText.replace(/\s+/g, ' ').trim());
            });
            tsv.push(row.join('\t'));
        });

        navigator.clipboard.writeText(tsv.join('\n')).then(() => {
            alert('Table data copied to clipboard!');
        }).catch(() => {
            alert('Unable to copy to clipboard.');
        });
    };
}
</script>
