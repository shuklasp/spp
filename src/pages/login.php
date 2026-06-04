<?php
// src/pages/login.php

$authForm = current(array_filter([
    class_exists('App\Default\Components\AuthForm') ? new \App\Default\Components\AuthForm($_GET) : null,
    class_exists('App\Spp\Components\AuthForm') ? new \App\Spp\Components\AuthForm($_GET) : null
]));

if (!$authForm) {
    // Ensure the class is loaded if standard autoload didn't find it (which it should have)
    require_once SPP_APP_DIR . '/src/default/components/AuthForm.php';
    $authForm = new \App\Default\Components\AuthForm($_GET);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication</title>
    <!-- MDB5 CSS for premium aesthetics -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.0.0/mdb.min.css" rel="stylesheet" />
    <style>
        body { background-color: #508bfc; }
    </style>
</head>
<body>
    <header>
        <div class="header-content text-center py-4">
            <h1 class="text-white">Virtual Shiksha Vidyala</h1>
        </div>
    </header>

    <div class="main-content">
        <?php echo $authForm->render(); ?>
    </div>

    <!-- MDB5 JS -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.0.0/mdb.umd.min.js"></script>
</body>
</html>