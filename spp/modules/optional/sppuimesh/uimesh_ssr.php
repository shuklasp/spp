<!DOCTYPE html>
<html>
<head>
    <title>SPP WebOS - <?= htmlspecialchars($appAlias) ?></title>
    <script src="/sppuimesh.js" defer></script>
</head>
<body>
    <div id="spp-uimesh-container" data-ssr-loaded="true">
        <?= $fragment ?>
    </div>
</body>
</html>
