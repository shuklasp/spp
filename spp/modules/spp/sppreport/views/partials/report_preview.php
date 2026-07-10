<?php
/**
 * Standalone external partial for SPPReport data table preview and generated SQL.
 * Encapsulates presentation logic to completely eliminate inline HTML in controllers.
 */
$rows = $data['data'] ?? [];
$sql = $data['sql'] ?? '';
$error = $data['error'] ?? null;
$configPayload = $data['config_payload'] ?? '{}';
$encodedPayload = urlencode($configPayload);
?>
<div class="spp-report-preview-content" style="display: flex; flex-direction: column; gap: 24px; width: 100%; animation: sppFadeIn 0.3s ease-out;">
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
            <p style="margin: 0; font-size: 0.85rem; color: var(--sppux-text-dim, #64748b);">Showing <?= count($rows) ?> rows fetched directly from the database connection.</p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_csv&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 8px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                📄 CSV
            </a>
            <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_xls&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 8px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                📊 Excel
            </a>
            <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_pdf&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 8px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
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

    <!-- Data Table Grid -->
    <div class="spp-grid-wrapper" style="border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.15)); border-radius: 16px; overflow-x: auto; box-shadow: 0 4px 16px rgba(0,0,0,0.02);">
        <?php if (empty($rows)): ?>
            <div style="padding: 48px; text-align: center; color: var(--sppux-text-dim, #64748b);">
                <div style="font-size: 2.4rem; margin-bottom: 12px;">📁</div>
                <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; font-weight: 600; color: var(--sppux-text, #334155);">No matching records found</h4>
                <p style="margin: 0; font-size: 0.9rem;">Try broadening your filter criteria in the query configurator.</p>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--sppux-glass-bg, rgba(241, 245, 249, 0.8)); border-bottom: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.15));">
                        <?php foreach (array_keys($rows[0]) as $th): ?>
                            <th style="padding: 14px 18px; font-weight: 600; color: var(--sppux-text, #0f172a);"><?= htmlspecialchars($th) ?></th>
                        <?php endforeach; ?>
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
