<html>
<head>
    <style>h1 { color: red; }</style>
</head>
<body>
    <div id="content">
        <h1>Welcome to <?= htmlspecialchars($appAlias) ?></h1>
        <p>Dynamic Content loaded from <?= htmlspecialchars($path) ?></p>
    </div>
</body>
</html>
