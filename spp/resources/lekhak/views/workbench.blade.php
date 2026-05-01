<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | Lekhak CMS</title>
    <link rel="stylesheet" href="/res/spp/sppux/css/sppux.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .workbench-header { background: #1a1a1a; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .workbench-container { display: flex; min-height: calc(100-vh - 64px); }
        .sidebar { width: 260px; background: #fff; border-right: 1px solid #ddd; padding: 1rem; }
        .main-content { flex: 1; padding: 2rem; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li { padding: 0.8rem; border-radius: 4px; cursor: pointer; transition: 0.2s; }
        .sidebar-menu li:hover { background: #f0f0f0; }
        .sidebar-menu li.active { background: #e0f2f1; color: #00897b; font-weight: 600; }
    </style>
</head>
<body>
    <header class="workbench-header">
        <div class="logo">LEKHAK <span style="font-weight: 200; opacity: 0.7;">WORKBENCH</span></div>
        <div class="user-menu">Admin</div>
    </header>

    <div class="workbench-container">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="active">Content</li>
                <li>Structure</li>
                <li>Appearance</li>
                <li>Plugins</li>
                <li>Settings</li>
            </ul>
        </aside>

        <main class="main-content">
            <h1>Content Management</h1>
            <div id="content-grid">
                @sppux('SppGrid', ['entity' => 'LekhakNode', 'columns' => ['title', 'status', 'changed']])
            </div>
            
            <div style="margin-top: 2rem;">
                <button class="spp-btn spp-btn-primary">Create New Content</button>
            </div>
        </main>
    </div>

    <script src="/res/spp/sppux/js/sppux.js"></script>
</body>
</html>
