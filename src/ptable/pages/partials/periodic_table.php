<?php
/**
 * External View Partial: periodic_table.php
 * Context: Periodic Table Grid
 */
?>
<div class="periodic-table">
    <!-- Periodic Table Legend / Block Names -->
    <div class="ptable-legend" style="grid-column: 3 / 13; grid-row: 1 / 4; display: flex; flex-direction: column; justify-content: center; padding: 2rem;">
        <h4 style="margin: 0 0 1rem 0; font-size: 1.1rem; color: var(--text-main); border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">Categories & Blocks</h4>
        <div class="legend-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.8rem;">
            <div class="legend-item"><span class="legend-color alkali-metal"></span> Alkali Metals (s-block)</div>
            <div class="legend-item"><span class="legend-color alkaline-earth-metal"></span> Alkaline Earth (s-block)</div>
            <div class="legend-item"><span class="legend-color transition-metal"></span> Transition Metals (d-block)</div>
            <div class="legend-item"><span class="legend-color post-transition-metal"></span> Post-Transition (p-block)</div>
            <div class="legend-item"><span class="legend-color metalloid"></span> Metalloids (p-block)</div>
            <div class="legend-item"><span class="legend-color nonmetal"></span> Nonmetals (p-block)</div>
            <div class="legend-item"><span class="legend-color halogen"></span> Halogens (p-block)</div>
            <div class="legend-item"><span class="legend-color noble-gas"></span> Noble Gases (p-block)</div>
            <div class="legend-item"><span class="legend-color lanthanide"></span> Lanthanides (f-block)</div>
            <div class="legend-item"><span class="legend-color actinide"></span> Actinides (f-block)</div>
        </div>
    </div>

    <?php if (isset($elements) && is_array($elements)): ?>
        <?php foreach ($elements as $el): ?>
            <a href="<?= \SPP\App::getBaseUrl('ptable') ?>/element/<?= $el['symbol'] ?>"
               hx-get="<?= \SPP\App::getBaseUrl('ptable') ?>/element/<?= $el['symbol'] ?>"
               hx-target="#element-modal-content"
               hx-swap="innerHTML"
               class="element-box <?= htmlspecialchars($el['category']) ?>" style="--group: <?= $el['group'] ?>; --period: <?= $el['period'] ?>;">
                
                <div class="element-content">
                    <span class="atomic-number"><?= $el['atomic'] ?></span>
                    <span class="symbol"><?= $el['symbol'] ?></span>
                    <span class="name"><?= $el['name'] ?></span>
                    <span class="mass"><?= $el['mass'] ?></span>
                </div>
                
                <div class="element-tooltip">
                    <div style="font-size: 1.1em; font-weight: bold; margin-bottom: 0.25rem;"><?= $el['name'] ?> (<?= $el['symbol'] ?>)</div>
                    <div style="color: rgba(255,255,255,0.7); margin-bottom: 0.25rem;">Z = <?= $el['atomic'] ?> | Mass: <?= $el['mass'] ?></div>
                    <hr style="margin: 0.25rem 0; border: none; border-top: 1px solid rgba(255,255,255,0.2);">
                    <table style="width: 100%; border-spacing: 0; text-align: left; line-height: 1.4;">
                        <tr><td style="color: #94a3b8; padding-right: 0.5rem;">Category</td><td><?= ucwords(str_replace('-', ' ', $el['category'])) ?></td></tr>
                        <tr><td style="color: #94a3b8; padding-right: 0.5rem;">Phase</td><td><?= $el['phase'] ?? 'Unknown' ?></td></tr>
                        <?php if(!empty($el['electron_configuration'])): ?>
                        <tr><td style="color: #94a3b8; padding-right: 0.5rem;">Config</td><td style="font-family: monospace; font-size: 0.9em;"><?= $el['electron_configuration'] ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No elements found.</p>
    <?php endif; ?>
</div>