<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lekhak Admin | Professional Workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Core Framework Styles -->
    <link rel="stylesheet" href="{{ $base_url }}/spp/res/css/spp.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg: #0f172a;
            --sidebar-bg: #1e293b;
            --header-bg: #1e293b;
            --border: #334155;
            --text: #f1f5f9;
            --text-dim: #94a3b8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        h1, h2, h3, h4, .logo-text { font-family: 'Outfit', sans-serif; }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 50;
        }

        .sidebar-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-icon {
            width: 28px;
            height: 28px;
            background: var(--primary);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: white;
            font-size: 0.9rem;
        }

        .logo-text {
            font-size: 1.15rem;
            font-weight: 700;
        }

        .nav-list {
            list-style: none;
            padding: 1.5rem 1rem;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            color: var(--text-dim);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.15s;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: #334155;
            color: var(--text);
        }

        .nav-link.active {
            background: var(--primary);
            color: white;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
        }

        /* Main Content */
        .main-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .content-header {
            height: 64px;
            padding: 0 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border);
            z-index: 40;
        }

        .view-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dim);
        }

        .viewport {
            flex-grow: 1;
            overflow-y: auto;
            padding: 2.5rem;
            position: relative;
        }

        /* Loading */
        #view-loader {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Toasts */
        #toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .toast {
            padding: 1rem 1.25rem;
            background: var(--sidebar-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        #modal-container {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 200;
            background: var(--bg);
            display: none;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">L</div>
            <div class="logo-text">Lekhak <span>CMS</span></div>
        </div>
        
        <nav class="nav-list">
            <div class="nav-item">
                <a class="nav-link active" data-view="dashboard">
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link" data-view="content">
                    Content Manager
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link" data-view="canvas">
                    Visual Canvas
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link" data-view="settings">
                    Setup Engine
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a href="{{ $base_url }}" class="nav-link">
                ← Exit Site
            </a>
        </div>
    </aside>

    <main class="main-wrapper">
        <header class="content-header">
            <h2 class="view-title" id="view-title">Dashboard</h2>
            <div id="header-actions"></div>
        </header>

        <div class="viewport" id="viewport">
            <div id="view-loader"><div class="spinner"></div></div>
            <div id="view-container"></div>
        </div>
    </main>

    <div id="toast-container"></div>
    <div id="modal-container"></div>

    <!-- SPP Infrastructure -->
    <script src="{{ $base_url }}/spp/res/js/spp.js"></script>
    <script type="module" src="{{ $base_url }}/src/lekhak/resources/admin/standalone-shell.js"></script>

    <script>
        window.LEKHAK_CONFIG = {
            apiBase: '{{ $base_url }}/admin-api',
            baseUrl: '{{ $base_url }}',
            adminUrl: '{{ $admin_url }}'
        };
    </script>
</body>
</html>
