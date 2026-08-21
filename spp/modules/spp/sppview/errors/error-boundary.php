<?php
// error-boundary.php
?>
<div class="sppux-alert sppux-alert-danger" style="margin: 1rem 0; padding: 1rem; border-radius: 8px; font-family: system-ui; text-align: left;">
    <strong>💥 View Template Error: <code><?= htmlspecialchars($shortFile, ENT_QUOTES, 'UTF-8') ?></code></strong><br>
    <span style="font-family: monospace; font-size: 0.85rem; opacity: 0.8;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
</div>
