<?php
/**
 * External View Partial: element_details.php
 * Context: Element Detail View
 */
$base_url = \SPP\App::getBaseUrl('ptable');
?>
<?php if (isset($element)): ?>
<div class="element-card <?= htmlspecialchars($element['category']) ?> detailed-card">
    <div class="element-card-header">
        <div class="card-symbol-badge">
            <span class="card-atomic"><?= $element['atomic'] ?></span>
            <span class="card-symbol"><?= $element['symbol'] ?></span>
        </div>
        <div class="card-title">
            <h2><?= htmlspecialchars($element['name']) ?></h2>
            <p class="card-category"><?= ucwords(str_replace('-', ' ', $element['category'])) ?></p>
        </div>
    </div>
    
    <div class="element-card-body two-column">
        <div class="element-image-wrapper">
            <?php if (!empty($wiki['local_image'])): ?>
                <img src="<?= htmlspecialchars($wiki['local_image']) ?>" alt="<?= htmlspecialchars($element['name']) ?> image" class="element-image" />
            <?php else: ?>
                <div class="element-image-placeholder">
                    <span>No Image Available</span>
                </div>
            <?php endif; ?>
            <p class="wiki-description"><?= htmlspecialchars($wiki['description']) ?></p>
        </div>
        
        <div class="element-specs">
            <h3>Physical & Chemical Properties</h3>
            <div class="detail-row">
                <span class="detail-label">Category</span>
                <span class="detail-value"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $wiki['category'] ?? $element['category']))) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Atomic Mass</span>
                <span class="detail-value"><?= htmlspecialchars($wiki['atomic_mass'] ?? $element['mass']) ?> u</span>
            </div>
            <?php if (!empty($wiki['density'])): ?>
            <div class="detail-row">
                <span class="detail-label">Density</span>
                <span class="detail-value"><?= htmlspecialchars($wiki['density']) ?> g/cm³</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($wiki['phase'])): ?>
            <div class="detail-row">
                <span class="detail-label">State at STP</span>
                <span class="detail-value"><?= htmlspecialchars($wiki['phase']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($wiki['melt'])): ?>
            <div class="detail-row">
                <span class="detail-label">Melting Point</span>
                <span class="detail-value"><?= htmlspecialchars($wiki['melt']) ?> K</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($wiki['boil'])): ?>
            <div class="detail-row">
                <span class="detail-label">Boiling Point</span>
                <span class="detail-value"><?= htmlspecialchars($wiki['boil']) ?> K</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($wiki['electron_configuration'])): ?>
            <div class="detail-row">
                <span class="detail-label">Electron Config.</span>
                <span class="detail-value"><?= \App\ptable\Serv\LocalDataService::formatElectronConfig($wiki['electron_configuration']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($wiki['electronegativity_pauling'])): ?>
            <div class="detail-row">
                <span class="detail-label">Electronegativity</span>
                <span class="detail-value"><?= htmlspecialchars($wiki['electronegativity_pauling']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($wiki['electron_affinity'])): ?>
            <div class="detail-row">
                <span class="detail-label">Electron Affinity</span>
                <span class="detail-value"><?= htmlspecialchars($wiki['electron_affinity']) ?> kJ/mol</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($wiki['discovered_by'])): ?>
            <div class="detail-row">
                <span class="detail-label">Discovered By</span>
                <span class="detail-value" style="font-size: 0.95rem; text-align: right;"><?= htmlspecialchars($wiki['discovered_by']) ?></span>
            </div>
            <?php endif; ?>

            <div class="action-row" style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 1.5rem;">
                <a href="<?= \SPP\App::getBaseUrl('ptable') ?>/element/<?= $element['symbol'] ?>/study" class="btn study-btn" style="flex: 1;">📚 Study In-Depth</a>
                <a href="<?= \SPP\App::getBaseUrl('ptable') ?>/element/<?= $element['symbol'] ?>/compounds" class="btn btn-outline" style="flex: 1;">🧪 Explore Compounds</a>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    <p>Element data not found.</p>
<?php endif; ?>