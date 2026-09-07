<?php
/**
 * External View Partial: spp.php
 * Context: SPPDocs
 * Designed to be inserted or updated at a particular place in the main page via HTMX or ViewController::renderPartial().
 */
?>
<div class="spp-partial-container" id="partial-spp">
    <div class="partial-header">
        <h4>spp.php</h4>
        <span class="badge badge-primary">SPPDocs</span>
    </div>
    <div class="partial-body">
        <p>This dynamic PHP partial was rendered externally without inline HTML string literals.</p>
        <?php if (isset($item)): ?>
            <div class="item-details">
                <pre><?= htmlspecialchars(json_encode($item, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>