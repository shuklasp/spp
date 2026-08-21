<div wire:id="<?= htmlspecialchars($id) ?>" wire:component="<?= htmlspecialchars($class) ?>" wire:state="<?= $stateJson ?>" wire:checksum="<?= $checksum ?>"<?= $initAttribute ?? '' ?><?= $isolated ?>>
    <?= $innerHtml ?>
</div>
