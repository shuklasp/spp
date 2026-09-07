<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPPDocs Admin Login</title>
    <link rel="stylesheet" href="@url('css/admin_login.css')?v={{ file_exists(APP_BASE_DIR . '/src/SPPDocs/resources/css/admin_login.css') ? filemtime(APP_BASE_DIR . '/src/SPPDocs/resources/css/admin_login.css') : 1 }}">
</head>
<body>
    <div class="login-box">
        <h1>SPPDocs Admin</h1>
        @if(isset($error))
            <div class="error">{{ $error }}</div>
        @endif
        <form method="POST" action="{{ \SPP\App::getBaseUrl() }}/admin/login">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
