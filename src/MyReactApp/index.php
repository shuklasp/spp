<?php
require_once __DIR__ . '/../../spp/sppinit.php';
\SPP\App::getApp('MyReactApp');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MyReactApp - React App</title>
</head>
<body>
    <div id="root"></div>
    <script type="module">
        // Basic React Bootstrap Placeholder
        console.log("React app MyReactApp initialized.");
        document.getElementById('root').innerHTML = "<h1>Welcome to React on SPP</h1><p>Run your bundler to inject the real React code here.</p>";
    </script>
</body>
</html>