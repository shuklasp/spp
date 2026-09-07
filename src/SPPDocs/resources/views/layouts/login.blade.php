<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login') — SPP Developer Portal</title>
    <link rel="stylesheet" href="@url('css/login.css')?v={{ file_exists(APP_BASE_DIR . '/src/SPPDocs/resources/css/login.css') ? filemtime(APP_BASE_DIR . '/src/SPPDocs/resources/css/login.css') : 1 }}">
</head>
<body>
    <header class="login-header">
        <div class="login-header-left">
            <img src="{{ $base_url }}/assets/logo.jpg" alt="SPP Logo" class="login-logo" width="36" height="36" onerror="this.style.display='none'" />
            <a href="{{ $base_url }}/" class="login-brand">SPP Developer Portal</a>
        </div>
        <a href="{{ $base_url }}/" class="login-back">&larr; Back to Portal</a>
    </header>
    <div class="login-wrapper">
        @yield('content')
    </div>
    <footer class="login-footer">&copy; {{ date('Y') }} SPPDocs &bull; Built with SPP Framework</footer>
</body>
</html>
