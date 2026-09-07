<?php
/**
 * Flagship SPPReport Dual-Mode (Screen & Print) Template.
 * Demonstrates a highly developer-friendly, unified templating architecture.
 * Features dedicated containers for screen interaction and optimized printer-friendly layout.
 */
$rows = $data['data'] ?? [];
$sql = $data['sql'] ?? '';
$error = $data['error'] ?? null;
$configPayload = $data['config_payload'] ?? '{}';
$configObj = json_decode($configPayload, true) ?? [];
$encodedPayload = urlencode($configPayload);
$reportTitle = $configObj['report_name'] ?? $data['report_title'] ?? 'Enterprise BI Executive Summary';
$orgName = $data['org_name'] ?? 'SPP Global Enterprise Solutions';
$confidentialityNotice = $data['confidentiality'] ?? 'CONFIDENTIAL - FOR INTERNAL USE ONLY. DO NOT DISTRIBUTE.';
?>
<!-- Unified Dual-Mode Report Wrapper -->
<div class="spp-dual-mode-template" style="width: 100%; animation: sppFadeIn 0.3s ease-out;">

    <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 14px; padding: 16px 20px; color: #ef4444; font-weight: 600; display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
            <span style="font-size: 1.4rem;">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- 1. SCREEN MODE SECTION (.spp-screen-mode .no-print)                       -->
    <!-- ========================================================================= -->
    <div class="spp-screen-mode no-print" style="display: flex; flex-direction: column; gap: 28px;">
        
        <!-- Screen Header & Actions Bar -->
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; background: var(--sppux-card-bg, rgba(255, 255, 255, 0.7)); border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.1)); border-radius: 18px; padding: 22px 28px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 8px 32px rgba(0,0,0,0.03);">
            <div>
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: rgba(99, 102, 241, 0.15); color: #6366f1; padding: 4px 10px; border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.3);">Dual-Mode Template : Screen Active</span>
                <h3 style="font-size: 1.4rem; font-weight: 800; margin: 8px 0 4px 0; color: var(--sppux-text, #0f172a);"><?= htmlspecialchars($reportTitle) ?></h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--sppux-text-dim, #64748b);">Interactive view with live local filtering, table scrolling, and rapid exports.</p>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="button" onclick="window.print()" class="spp-btn-premium" style="padding: 10px 20px; border-radius: 12px; border: none; background: linear-gradient(135deg, #10b981, #14b8a6); color: white; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3); transition: transform 0.1s ease;">
                    🖨️ Print / Save PDF
                </button>
                <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_csv&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 9px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                    📄 CSV
                </a>
                <a href="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=export_xls&payload=<?= $encodedPayload ?>" target="_blank" class="spp-btn-export" style="background: var(--sppux-bg, white); border: 1px solid rgba(100, 116, 139, 0.3); padding: 9px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; color: var(--sppux-text, #334155); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s ease;">
                    📊 Excel
                </a>
            </div>
        </div>

        <!-- Interactive Filter Bar (Screen Only) -->
        <div style="background: var(--sppux-panel, rgba(255, 255, 255, 0.5)); border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.1)); border-radius: 16px; padding: 20px 24px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 280px;">
                    <span style="font-size: 1.2rem;">🔍</span>
                    <input type="text" id="spp-local-table-filter" onkeyup="sppFilterTable()" placeholder="Quick filter all columns..." style="width: 100%; border: 1px solid rgba(0,0,0,0.15); padding: 10px 16px; border-radius: 10px; background: var(--sppux-bg, white); font-size: 0.9rem; color: var(--sppux-text, #0f172a); outline: none;">
                </div>
                <div style="font-size: 0.85rem; color: var(--sppux-text-dim, #64748b);">
                    Showing <strong id="spp-screen-row-count"><?= count($rows) ?></strong> records
                </div>
            </div>
        </div>

        <!-- Scrollable Data Table Wrapper (Screen Only) -->
        <div class="spp-grid-wrapper" style="border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.15)); border-radius: 18px; overflow-x: auto; max-height: 600px; box-shadow: 0 8px 32px rgba(0,0,0,0.03); background: var(--sppux-card-bg, rgba(255, 255, 255, 0.7)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);">
            <?php if (empty($rows)): ?>
                <div style="padding: 48px; text-align: center; color: var(--sppux-text-dim, #64748b);">
                    <div style="font-size: 2.4rem; margin-bottom: 12px;">📁</div>
                    <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; font-weight: 600; color: var(--sppux-text, #334155);">No matching records found</h4>
                    <p style="margin: 0; font-size: 0.9rem;">Try adjusting your filter criteria in the query builder.</p>
                </div>
            <?php else: ?>
                <table id="spp-screen-data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: var(--sppux-glass-bg, rgba(241, 245, 249, 0.9)); position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.15));">
                            <?php foreach (array_keys($rows[0]) as $th): ?>
                                <th style="padding: 14px 18px; font-weight: 600; color: var(--sppux-text, #0f172a);"><?= htmlspecialchars($th) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $index => $row): ?>
                            <tr class="spp-data-row" style="border-bottom: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.08)); background: <?= $index % 2 === 0 ? 'transparent' : 'rgba(248, 250, 252, 0.4)' ?>;">
                                <?php foreach ($row as $val): ?>
                                    <td style="padding: 12px 18px; color: var(--sppux-text-dim, #334155);"><?= htmlspecialchars((string) $val) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Generated SQL Statement -->
        <?php if ($sql): ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #6366f1; letter-spacing: 0.5px;">Generated SQL Query</div>
                <pre style="background: #0f172a; color: #38bdf8; padding: 16px 20px; border-radius: 14px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.85rem; overflow-x: auto; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); margin: 0;"><code><?= htmlspecialchars($sql) ?></code></pre>
            </div>
        <?php endif; ?>

    </div>

    <!-- ========================================================================= -->
    <!-- 2. PRINT MODE SECTION (.spp-print-mode .only-print)                       -->
    <!-- ========================================================================= -->
    <div class="spp-print-mode only-print" style="display: none; width: 100%; color: #000; background: #fff; font-family: Arial, Helvetica, sans-serif;">
        
        <!-- Document Header -->
        <div style="border-bottom: 2px solid #000; padding-bottom: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="font-size: 24pt; font-weight: bold; margin: 0 0 6px 0; color: #000;"><?= htmlspecialchars($reportTitle) ?></h1>
                <div style="font-size: 11pt; color: #333; font-weight: bold;"><?= htmlspecialchars($orgName) ?></div>
            </div>
            <div style="text-align: right; font-size: 10pt; color: #555;">
                <div>Generated: <?= date('Y-m-d H:i:s') ?></div>
                <div>Records: <?= count($rows) ?></div>
            </div>
        </div>

        <!-- Unconstrained Printer Table -->
        <?php if (empty($rows)): ?>
            <div style="padding: 40px 0; text-align: center; font-size: 12pt; font-style: italic;">No records to display.</div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 10pt; page-break-inside: auto;">
                <thead style="display: table-header-group;">
                    <tr style="border-bottom: 2px solid #000; background-color: #f2f2f2 !important;">
                        <?php foreach (array_keys($rows[0]) as $th): ?>
                            <th style="padding: 8px 10px; font-weight: bold; color: #000;"><?= htmlspecialchars($th) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr style="border-bottom: 1px solid #ccc; page-break-inside: avoid;">
                            <?php foreach ($row as $val): ?>
                                <td style="padding: 8px 10px; color: #000;"><?= htmlspecialchars((string) $val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Corporate Branding, Confidentiality Notice & Signature Blocks Footer -->
        <div style="margin-top: 48px; border-top: 1px solid #000; padding-top: 20px; page-break-inside: avoid;">
            <div style="font-size: 9pt; font-weight: bold; color: #d32f2f; margin-bottom: 24px; text-align: center;">
                <?= htmlspecialchars($confidentialityNotice) ?>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 40px; padding: 0 20px;">
                <div style="width: 240px; text-align: center;">
                    <div style="border-bottom: 1px solid #000; height: 30px; margin-bottom: 6px;"></div>
                    <div style="font-size: 10pt; font-weight: bold;">Prepared By</div>
                    <div style="font-size: 8pt; color: #555;">Lead Data Analyst</div>
                </div>
                <div style="width: 240px; text-align: center;">
                    <div style="border-bottom: 1px solid #000; height: 30px; margin-bottom: 6px;"></div>
                    <div style="font-size: 10pt; font-weight: bold;">Reviewed By</div>
                    <div style="font-size: 8pt; color: #555;">VP of Operations / CFO</div>
                </div>
                <div style="width: 240px; text-align: center;">
                    <div style="border-bottom: 1px solid #000; height: 30px; margin-bottom: 6px;"></div>
                    <div style="font-size: 10pt; font-weight: bold;">Approved By</div>
                    <div style="font-size: 8pt; color: #555;">Executive Committee</div>
                </div>
            </div>

            <div style="margin-top: 32px; font-size: 8pt; color: #777; text-align: center;">
                <?= htmlspecialchars($orgName) ?> • All Rights Reserved • Generated via SPPReport Hypermedia Core
            </div>
        </div>

    </div>

</div>

<!-- Realtime Client-Side Filtering Script -->
<script>
function sppFilterTable() {
    const input = document.getElementById('spp-local-table-filter');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const table = document.getElementById('spp-screen-data-table');
    if (!table) return;
    const rows = table.getElementsByClassName('spp-data-row');
    let count = 0;
    for (let i = 0; i < rows.length; i++) {
        const text = rows[i].textContent || rows[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            rows[i].style.display = "";
            count++;
        } else {
            rows[i].style.display = "none";
        }
    }
    const countElem = document.getElementById('spp-screen-row-count');
    if (countElem) countElem.innerText = count;
}
</script>
