<?php
// polyglot-error.php
$langText = isset($lang) && $lang ? ' (' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . ')' : '';
$errorText = htmlspecialchars($error ?? 'Unknown Error', ENT_QUOTES, 'UTF-8');
?>
<div class="spp-partial-container error">
    <div class="partial-header"><h4>Polyglot Partial Error<?= $langText ?></h4></div>
    <div class="partial-body"><pre><?= $errorText ?></pre></div>
</div>
