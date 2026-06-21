<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lekhak Admin</title>
    <link rel="stylesheet" href="{{ $web_root }}/res/spp/css/spp.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; margin: 0; }
        .admin-wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; }
        .main-content { flex-grow: 1; padding: 40px; }
        .sidebar h2 { margin-top: 0; }
        .sidebar nav ul { list-style: none; padding: 0; }
        .sidebar nav ul li { margin: 15px 0; }
        .sidebar nav ul li a { color: #bdc3c7; text-decoration: none; transition: color 0.3s; }
        .sidebar nav ul li a:hover { color: white; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar">
            <h2>Lekhak Admin</h2>
            <nav>
                <ul>
                    <li><a href="{{ $admin_root }}">Dashboard</a></li>
                    <li><a href="{{ $admin_root }}/structure/types">Content Types</a></li>
                    <li><a href="{{ $admin_root }}/landing">Landing Pages</a></li>
                    <li><a href="{{ $admin_root }}/content">All Content</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            @yield('content')
        </div>
    </div>
    
    <!-- SPP Live Subsystem -->
    <script src="/school1/spp/modules/spp/sppview/js/spplive.js"></script>
</body>
</html>
