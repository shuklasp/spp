<?php
// display-errors.php
?>
<div class="error-holder <?= htmlspecialchars($errorHolder, ENT_QUOTES, 'UTF-8') ?>">
<?php foreach ($errors as $errorType => $errorsMessages): ?>
    <p><?= htmlspecialchars($errorType, ENT_QUOTES, 'UTF-8') ?>:</p>
    <?php foreach ($errorsMessages as $key => $errorMessage): ?>
        <p class="error-message <?= htmlspecialchars($errorType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php
        unset($errorMessage);
        unset(self::$errorHolders[$errorHolder][$errorType][$key]);
        ?>
    <?php endforeach; ?>
<?php endforeach; ?>
</div>
