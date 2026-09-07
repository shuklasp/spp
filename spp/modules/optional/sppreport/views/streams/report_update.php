<?php
/**
 * Standalone real-time Turbo Streams template for SPPReport live analytics broadcasts.
 * Fully configurable target, action, streaming interval, and custom widget types.
 */
$target = $data['target'] ?? 'spp-configurator-container';
$action = $data['action'] ?? 'replace';
$interval = $data['interval'] ?? 5000; // Configurable streaming interval in ms
$widgetType = $data['widget_type'] ?? 'grid'; // Configurable widget type: grid, kpi, or alert
$widgetTitle = $data['widget_title'] ?? 'Live Analytics Stream';
$rows = $data['data'] ?? [];
$metrics = $data['metrics'] ?? [];
?>
<turbo-stream action="<?= htmlspecialchars($action) ?>" target="<?= htmlspecialchars($target) ?>">
    <template>
        <div class="spp-turbo-stream-widget spp-widget-<?= htmlspecialchars($widgetType) ?>" data-stream-interval="<?= (int)$interval ?>" style="background: var(--sppux-card-bg, rgba(255,255,255,0.7)); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 18px; padding: 24px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 10px 32px rgba(99, 102, 241, 0.1); animation: sppPulse 1s ease-out;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="spp-live-indicator" style="width: 10px; height: 10px; background-color: #10b981; border-radius: 50%; display: inline-block; box-shadow: 0 0 8px #10b981;"></span>
                    <h4 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--sppux-text, #0f172a);"><?= htmlspecialchars($widgetTitle) ?></h4>
                </div>
                <span style="font-size: 0.75rem; background: rgba(99, 102, 241, 0.15); color: #6366f1; font-weight: 700; padding: 4px 12px; border-radius: 20px; border: 1px solid rgba(99, 102, 241, 0.3);">
                    ⚡ Live Stream (<?= $interval / 1000 ?>s)
                </span>
            </div>

            <?php if ($widgetType === 'kpi'): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
                    <?php foreach ($metrics as $label => $val): ?>
                        <div style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.08); border-radius: 14px; padding: 16px;">
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--sppux-text-dim, #64748b); text-transform: uppercase;"><?= htmlspecialchars($label) ?></div>
                            <div style="font-size: 1.8rem; font-weight: 800; color: #6366f1; margin-top: 6px;"><?= htmlspecialchars((string)$val) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($widgetType === 'alert'): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 14px; padding: 18px; color: #ef4444; font-weight: 600;">
                    <?= htmlspecialchars($data['alert_message'] ?? 'Threshold alert condition met.') ?>
                </div>
            <?php else: ?>
                <!-- Default Grid Widget -->
                <div style="border: 1px solid var(--sppux-glass-border, rgba(0,0,0,0.12)); border-radius: 14px; overflow-x: auto;">
                    <?php if (empty($rows)): ?>
                        <div style="padding: 32px; text-align: center; color: var(--sppux-text-dim, #64748b);">No live data streaming currently.</div>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                            <thead>
                                <tr style="background: rgba(241, 245, 249, 0.8); border-bottom: 1px solid rgba(0,0,0,0.1);">
                                    <?php foreach (array_keys($rows[0]) as $th): ?>
                                        <th style="padding: 12px 16px; font-weight: 600; color: var(--sppux-text, #0f172a);"><?= htmlspecialchars($th) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $index => $row): ?>
                                    <tr style="border-bottom: 1px solid rgba(0,0,0,0.05); background: <?= $index % 2 === 0 ? 'transparent' : 'rgba(248, 250, 252, 0.4)' ?>;">
                                        <?php foreach ($row as $val): ?>
                                            <td style="padding: 10px 16px; color: var(--sppux-text-dim, #334155);"><?= htmlspecialchars((string) $val) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </template>
</turbo-stream>
