<?php
$base_url = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '/school1', '/');
$admin_url = $base_url . '/spp/admin/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lekhak Admin | Professional Workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">

    <!-- Core Framework Styles -->
    <link rel="preload" href="<?php echo $base_url; ?>/spp/res/css/spp.css" as="style">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/spp/res/css/spp.css">
    <script>
        // Execute synchronously in head to prevent Flash of Unstyled Content (FOUC)
        var savedMode = localStorage.getItem('lekhak-admin-theme') || 'saffron';
        document.documentElement.setAttribute('data-theme', savedMode);
    </script>

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
            --bg-gradient: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.15) 0%, transparent 40%);
        }

        [data-theme="day"] {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --bg: #f8fafc;
            --sidebar-bg: #ffffff;
            --header-bg: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-dim: #64748b;
            --bg-gradient: radial-gradient(circle at 10% 20%, rgba(79, 70, 229, 0.08) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(124, 58, 237, 0.08) 0%, transparent 40%);
        }

        [data-theme="saffron"] {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --bg: #fffaf5;
            --sidebar-bg: #ffedd5;
            --header-bg: #ffedd5;
            --border: #fed7aa;
            --text: #431407;
            --text-dim: #9a3412;
            --bg-gradient: linear-gradient(135deg, #fffaf5 0%, #fff7ed 50%, #ffedd5 100%), radial-gradient(circle at 10% 20%, rgba(249, 115, 22, 0.12) 0%, transparent 50%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            background-image: var(--bg-gradient);
            color: var(--text);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        h1,
        h2,
        h3,
        h4,
        .logo-text {
            font-family: 'Outfit', sans-serif;
        }

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
            background: rgba(128, 128, 128, 0.15);
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
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

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        #modal-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 200;
            background: var(--bg);
            display: none;
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="sidebar-header" style="justify-content: center; padding: 0; margin-bottom: 30px;">
            <img src="<?php echo $base_url; ?>/img/lekhak_logo_full.jpg" alt="Lekhak CMS Logo"
                style="width: 100%; max-width: 180px; height: auto; object-fit: contain; border-radius: 12px;" />
        </div>

        <nav class="nav-list" id="sidebar-nav">
            <?php
$active_modules = [];
try {
    $db = new \SPPMod\SPPDB\SPPDB();
    // We also consider core non-module views as "active"
    $core_views = ['dashboard', 'content', 'media', 'structure', 'canvas', 'settings', 'users'];
    $res = $db->execute_query("SELECT machine_name FROM lekhak_modules WHERE status = 1");
    if ($res && is_array($res)) {
        $active_modules = array_merge($core_views, array_column($res, 'machine_name'));
    } else {
        // Fallback: If table doesnt exist yet, enable all for demonstration
        $active_modules = $core_views;
        $all_dirs = scandir(__DIR__ . '/../../modules/');
        foreach ($all_dirs as $d) {
            if ($d !== '.' && $d !== '..')
                $active_modules[] = $d;
        }
    }
} catch (\Exception $e) {
    // Fallback on error
}
            ?>
            <div style="padding: 0 1rem 1rem;">
                <input type="text" id="nav-search" placeholder="Search menus..."
                    style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border); background: rgba(0,0,0,0.1); color: var(--text); outline: none;">
            </div>
            <div class="nav-group">
                <div class="nav-section-header"
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                    onclick="toggleNavGroup(this)">
                    <span>Overview & Core</span>
                    <span class="toggle-icon" style="font-size: 0.6rem; transition: transform 0.2s;">▼</span>
                </div>
                <div class="nav-group-content">
                    <?php if (in_array('dashboard', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="dashboard" href="#dashboard">
                            <span class="nav-icon">📊</span> <span class="nav-text">Dashboard</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('content', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="content" href="#content">
                            <span class="nav-icon">📝</span> <span class="nav-text">Content Manager</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('media', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="media" href="#media">
                            <span class="nav-icon">🖼️</span> <span class="nav-text">Media Library</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-section-header"
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                    onclick="toggleNavGroup(this)">
                    <span>Structure & Design</span>
                    <span class="toggle-icon" style="font-size: 0.6rem; transition: transform 0.2s;">▼</span>
                </div>
                <div class="nav-group-content">
                    <?php if (in_array('structure', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="structure" href="#structure">
                            <span class="nav-icon">🏗️</span> <span class="nav-text">Content Types</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_query_builder', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_query_builder" href="#lekhak_query_builder">
                            <span class="nav-icon">👁️</span> <span class="nav-text">Views Builder</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_blocks_nested', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_blocks_nested" href="#lekhak_blocks_nested">
                            <span class="nav-icon">🧱</span> <span class="nav-text">Blocks & Layouts</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('canvas', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="canvas" href="#canvas">
                            <span class="nav-icon">🎨</span> <span class="nav-text">Visual Canvas</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-section-header"
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                    onclick="toggleNavGroup(this)">
                    <span>Community & Audience</span>
                    <span class="toggle-icon" style="font-size: 0.6rem; transition: transform 0.2s;">▼</span>
                </div>
                <div class="nav-group-content">
                    <?php if (in_array('lekhak_forum', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_forum" href="#lekhak_forum">
                            <span class="nav-icon">💬</span> <span class="nav-text">Community Forum</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_community', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_community" href="#lekhak_community">
                            <span class="nav-icon">👥</span> <span class="nav-text">Social Profiles</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_qa', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_qa" href="#lekhak_qa">
                            <span class="nav-icon">❓</span> <span class="nav-text">Questions & Answers</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_newsletter', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_newsletter" href="#lekhak_newsletter">
                            <span class="nav-icon">✉️</span> <span class="nav-text">Newsletters</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_popups', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_popups" href="#lekhak_popups">
                            <span class="nav-icon">💥</span> <span class="nav-text">Popups & Leads</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-section-header"
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                    onclick="toggleNavGroup(this)">
                    <span>E-Commerce & Subscriptions</span>
                    <span class="toggle-icon" style="font-size: 0.6rem; transition: transform 0.2s;">▼</span>
                </div>
                <div class="nav-group-content">
                    <?php if (in_array('lekhak_store', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_store" href="#lekhak_store">
                            <span class="nav-icon">🛒</span> <span class="nav-text">eCommerce Store</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_subscriptions', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_subscriptions" href="#lekhak_subscriptions">
                            <span class="nav-icon">💳</span> <span class="nav-text">Subscriptions</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_memberships', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_memberships" href="#lekhak_memberships">
                            <span class="nav-icon">🔑</span> <span class="nav-text">Memberships</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_donations', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_donations" href="#lekhak_donations">
                            <span class="nav-icon">❤️</span> <span class="nav-text">Donations</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-section-header"
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                    onclick="toggleNavGroup(this)">
                    <span>Education & Services</span>
                    <span class="toggle-icon" style="font-size: 0.6rem; transition: transform 0.2s;">▼</span>
                </div>
                <div class="nav-group-content">
                    <?php if (in_array('lekhak_academy', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_academy" href="#lekhak_academy">
                            <span class="nav-icon">🎓</span> <span class="nav-text">Academy LMS</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_helpdesk', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_helpdesk" href="#lekhak_helpdesk">
                            <span class="nav-icon">🎫</span> <span class="nav-text">Helpdesk Tickets</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_events', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_events" href="#lekhak_events">
                            <span class="nav-icon">📅</span> <span class="nav-text">Events Calendar</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_healthcare', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_healthcare" href="#lekhak_healthcare">
                            <span class="nav-icon">⚕️</span> <span class="nav-text">Healthcare</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-section-header"
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                    onclick="toggleNavGroup(this)">
                    <span>Directories & Media</span>
                    <span class="toggle-icon" style="font-size: 0.6rem; transition: transform 0.2s;">▼</span>
                </div>
                <div class="nav-group-content">
                    <?php if (in_array('lekhak_realestate', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_realestate" href="#lekhak_realestate">
                            <span class="nav-icon">🏠</span> <span class="nav-text">Real Estate</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_classifieds', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_classifieds" href="#lekhak_classifieds">
                            <span class="nav-icon">🏷️</span> <span class="nav-text">Classified Ads</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_reviews', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_reviews" href="#lekhak_reviews">
                            <span class="nav-icon">⭐</span> <span class="nav-text">Reviews & Ratings</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_portfolio', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_portfolio" href="#lekhak_portfolio">
                            <span class="nav-icon">📁</span> <span class="nav-text">Portfolio</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_gallery', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_gallery" href="#lekhak_gallery">
                            <span class="nav-icon">🖼️</span> <span class="nav-text">Media Gallery</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-section-header"
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); padding: 0.75rem 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                    onclick="toggleNavGroup(this)">
                    <span>System & Utilities</span>
                    <span class="toggle-icon" style="font-size: 0.6rem; transition: transform 0.2s;">▼</span>
                </div>
                <div class="nav-group-content">
                    <?php if (in_array('settings', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="settings" href="#settings">
                            <span class="nav-icon">🎨</span> <span class="nav-text">Themes & Settings</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('users', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-php-route="true" href="<?php    echo $base_url; ?>/lekhak/admin/users">
                            <span class="nav-icon">👤</span> <span class="nav-text">Users & Roles</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_security', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_security" href="#lekhak_security">
                            <span class="nav-icon">🛡️</span> <span class="nav-text">Security Firewall</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_migrations', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_migrations" href="#lekhak_migrations">
                            <span class="nav-icon">🚚</span> <span class="nav-text">Data Migrations</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array('lekhak_audit_trail', $active_modules)): ?>
                    <div class="nav-item">
                        <a class="nav-link" data-view="lekhak_audit_trail" href="#lekhak_audit_trail">
                            <span class="nav-icon">🕵️</span> <span class="nav-text">Audit Trail</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="sidebar-footer" style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="<?php echo $base_url; ?>" class="nav-link">
                ← Exit Site
            </a>
            <a href="<?php echo $base_url; ?>/lekhak/admin/logout" class="nav-link" style="color: #ef4444;">
                ⏏ Log Out
            </a>
        </div>
    </aside>

    <main class="main-wrapper">
        <header class="content-header">
            <h2 class="view-title" id="view-title">Dashboard</h2>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="theme-switcher"
                    style="display: flex; gap: 6px; background: rgba(128,128,128,0.1); padding: 4px; border-radius: 20px; border: 1px solid var(--border);">
                    <button onclick="setThemeMode('dark')"
                        style="width: 28px; height: 28px; border-radius: 50%; border: none; cursor: pointer; background: #0f172a; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #64748b;"
                        title="Night Mode">•</button>
                    <button onclick="setThemeMode('day')"
                        style="width: 28px; height: 28px; border-radius: 50%; border: none; cursor: pointer; background: #fff; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #cbd5e1;"
                        title="Day Mode">•</button>
                    <button onclick="setThemeMode('saffron')"
                        style="width: 28px; height: 28px; border-radius: 50%; border: none; cursor: pointer; background: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #ffedd5;"
                        title="Saffron Mode">•</button>
                </div>
                <div id="header-actions"></div>
            </div>
        </header>

        <div class="viewport" id="viewport">
            <div id="view-loader">
                <div class="spinner"></div>
            </div>
            <div id="view-container"></div>
        </div>
    </main>

    <div id="toast-container"></div>
    <div id="modal-container"></div>

    <!-- Pre-warm decoupled UI Component templates natively -->
    <?php
$candidates = [
    $_SERVER['DOCUMENT_ROOT'] . $base_url . '/src/lekhak/comp/templates',
    realpath(__DIR__ . '/../../comp/templates'),
    'c:/projects/apache/school1/src/lekhak/comp/templates'
];
$tplDir = false;
foreach ($candidates as $c) {
    if ($c && is_dir($c)) {
        $tplDir = $c;
        break;
    }
}
if ($tplDir && is_dir($tplDir)) {
    foreach (scandir($tplDir) as $f) {
        if (str_ends_with($f, '.html')) {
            $tplName = strtolower(pathinfo($f, PATHINFO_FILENAME));
            $tplContent = @file_get_contents($tplDir . '/' . $f);
            if ($tplContent) {
                echo "<template id=\"spp-tpl-{$tplName}\">\n" . $tplContent . "\n</template>\n";
            }
        }
    }
}
    ?>

    <!-- SPP Infrastructure -->
    <script src="<?php echo $base_url; ?>/spp/res/js/spp.js?v=<?php echo time(); ?>"></script>
    <script type="module"
        src="<?php echo $base_url; ?>/src/lekhak/resources/admin/standalone-shell.js?v=<?php echo time(); ?>"></script>

    <script>
        window.LEKHAK_CONFIG = {
            apiBase: '<?php echo $base_url; ?>/lekhak/admin-api',
            baseUrl: '<?php echo $base_url; ?>',
            adminUrl: '<?php echo $admin_url; ?>'
        };
        window.SPP_CONFIG = {
            apiEndpoint: '<?php echo $base_url; ?>/lekhak/admin-api'
        };

        function setThemeMode(mode) {
            document.documentElement.setAttribute('data-theme', mode);
            localStorage.setItem('lekhak-admin-theme', mode);
        }
    </script>
    <script>
        function toggleNavGroup(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                icon.style.transform = 'rotate(-90deg)';
            }
        }

        document.getElementById('nav-search').addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const groups = document.querySelectorAll('.nav-group');

            groups.forEach(group => {
                const links = group.querySelectorAll('.nav-item');
                let hasVisible = false;

                links.forEach(link => {
                    const text = link.querySelector('.nav-text').textContent.toLowerCase();
                    if (text.includes(term)) {
                        link.style.display = 'block';
                        hasVisible = true;
                    } else {
                        link.style.display = 'none';
                    }
                });

                const content = group.querySelector('.nav-group-content');
                const icon = group.querySelector('.toggle-icon');

                if (term.length > 0) {
                    if (hasVisible) {
                        group.style.display = 'block';
                        content.style.display = 'block';
                        icon.style.transform = 'rotate(0deg)';
                    } else {
                        group.style.display = 'none';
                    }
                } else {
                    group.style.display = 'block';
                    link.style.display = 'block'; // Reset
                }
            });

            // If empty search, reset everything to visible but maybe keep some collapsed
            if (term.length === 0) {
                document.querySelectorAll('.nav-item').forEach(l => l.style.display = 'block');
                // You could collapse some by default here if desired.
            }
        });
    </script>
    <script>
        function toggleNavGroup(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                icon.style.transform = 'rotate(-90deg)';
            }
        }

        document.getElementById('nav-search').addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const groups = document.querySelectorAll('.nav-group');

            groups.forEach(group => {
                const links = group.querySelectorAll('.nav-item');
                let hasVisible = false;

                links.forEach(link => {
                    const text = link.querySelector('.nav-text').textContent.toLowerCase();
                    if (text.includes(term)) {
                        link.style.display = 'block';
                        hasVisible = true;
                    } else {
                        link.style.display = 'none';
                    }
                });

                const content = group.querySelector('.nav-group-content');
                const icon = group.querySelector('.toggle-icon');

                if (term.length > 0) {
                    if (hasVisible) {
                        group.style.display = 'block';
                        content.style.display = 'block';
                        icon.style.transform = 'rotate(0deg)';
                    } else {
                        group.style.display = 'none';
                    }
                } else {
                    group.style.display = 'block';
                    link.style.display = 'block'; // Reset
                }
            });

            // If empty search, reset everything to visible but maybe keep some collapsed
            if (term.length === 0) {
                document.querySelectorAll('.nav-item').forEach(l => l.style.display = 'block');
                // You could collapse some by default here if desired.
            }
        });
    </script>
    <script>
        function toggleNavGroup(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                icon.style.transform = 'rotate(-90deg)';
            }
        }

        document.getElementById('nav-search').addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const groups = document.querySelectorAll('.nav-group');

            groups.forEach(group => {
                const links = group.querySelectorAll('.nav-item');
                let hasVisible = false;

                links.forEach(link => {
                    const text = link.querySelector('.nav-text').textContent.toLowerCase();
                    if (text.includes(term)) {
                        link.style.display = 'block';
                        hasVisible = true;
                    } else {
                        link.style.display = 'none';
                    }
                });

                const content = group.querySelector('.nav-group-content');
                const icon = group.querySelector('.toggle-icon');

                if (term.length > 0) {
                    if (hasVisible) {
                        group.style.display = 'block';
                        content.style.display = 'block';
                        icon.style.transform = 'rotate(0deg)';
                    } else {
                        group.style.display = 'none';
                    }
                } else {
                    group.style.display = 'block';
                    link.style.display = 'block'; // Reset
                }
            });

            // If empty search, reset everything to visible but maybe keep some collapsed
            if (term.length === 0) {
                document.querySelectorAll('.nav-item').forEach(l => l.style.display = 'block');
                // You could collapse some by default here if desired.
            }
        });
    </script>
    <script>
        function toggleNavGroup(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                icon.style.transform = 'rotate(-90deg)';
            }
        }

        document.getElementById('nav-search').addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const groups = document.querySelectorAll('.nav-group');

            groups.forEach(group => {
                const links = group.querySelectorAll('.nav-item');
                let hasVisible = false;

                links.forEach(link => {
                    const text = link.querySelector('.nav-text').textContent.toLowerCase();
                    if (text.includes(term)) {
                        link.style.display = 'block';
                        hasVisible = true;
                    } else {
                        link.style.display = 'none';
                    }
                });

                const content = group.querySelector('.nav-group-content');
                const icon = group.querySelector('.toggle-icon');

                if (term.length > 0) {
                    if (hasVisible) {
                        group.style.display = 'block';
                        content.style.display = 'block';
                        icon.style.transform = 'rotate(0deg)';
                    } else {
                        group.style.display = 'none';
                    }
                } else {
                    group.style.display = 'block';
                    link.style.display = 'block'; // Reset
                }
            });

            // If empty search, reset everything to visible but maybe keep some collapsed
            if (term.length === 0) {
                document.querySelectorAll('.nav-item').forEach(l => l.style.display = 'block');
                // You could collapse some by default here if desired.
            }
        });
    </script>
</body>

</html>

</html>

</html>

</html>

</html>