<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lekhak Admin | Premium CMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-hover: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --accent: #8b5cf6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text);
            overflow: hidden;
            height: 100vh;
        }

        h1, h2, h3 { font-family: 'Outfit', sans-serif; }

        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2.5rem;
            text-decoration: none;
        }

        .nav-menu {
            list-style: none;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--surface-hover);
            color: var(--text);
        }

        .nav-link.active {
            background: var(--primary);
            color: white;
        }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .header {
            height: 64px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
        }

        .breadcrumb {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .viewport {
            flex-grow: 1;
            overflow-y: auto;
            padding: 2rem;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.05), transparent);
        }

        /* Views Overlay */
        #editor-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: none;
        }

        /* Loading Spinner */
        .loader {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.1);
            border-left-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <a href="#" class="logo">
                <i class="fas fa-feather-pointed"></i>
                <span>Lekhak</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link active" data-view="dashboard">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="content">
                        <i class="fas fa-file-lines"></i> Content Manager
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="editor" style="background: var(--primary); color: white;">
                        <i class="fas fa-plus"></i> Create Content
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="canvas">
                        <i class="fas fa-palette"></i> Visual Canvas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/landing">
                        <i class="fas fa-rocket"></i> Landing Pages
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="media">
                        <i class="fas fa-photo-film"></i> Media Library
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="structure">
                        <i class="fas fa-cubes"></i> Content Types
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/structure/views">
                        <i class="fas fa-eye"></i> Views Builder
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="blocks">
                        <i class="fas fa-th-large"></i> Blocks & Views
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="commerce">
                        <i class="fas fa-cart-shopping"></i> eCommerce Store
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="translations">
                        <i class="fas fa-language"></i> Translation Center
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-view="settings">
                        <i class="fas fa-paint-brush"></i> Themes & Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/settings">
                        <i class="fas fa-cog"></i> Configuration
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/users">
                        <i class="fas fa-users"></i> Users & Roles
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="../" class="nav-link">
                    <i class="fas fa-eye"></i> View Site
                </a>
            </div>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="breadcrumb" id="breadcrumb">Lekhak / Dashboard</div>
                <div class="user-profile">
                    <button id="btn-new-doc" class="nav-link" style="background: var(--primary); color: white; border: none; padding: 0.5rem 1rem;">
                        <i class="fas fa-plus"></i> New Document
                    </button>
                </div>
            </header>
            <div id="viewport" class="viewport">
                <div class="loader"><div class="spinner"></div></div>
            </div>
        </main>
    </div>

    <!-- Fullscreen Editor Overlay -->
    <div id="editor-container"></div>

    <script type="module">
        import { LekhakAdmin } from './resources/admin/js/admin.js';
        
        window.addEventListener('DOMContentLoaded', () => {
            const admin = new LekhakAdmin();
            admin.init();
        });
    </script>
</body>
</html>
