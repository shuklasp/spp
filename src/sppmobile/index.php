<?php
/**
 * Mobile Studio Pro - Main Workspace
 */
if (!\SPP\SPPSession::sessionVarExists('studio_user')) {
    $baseUrl = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/');
    header("Location: " . $baseUrl . "/sppmobile/login");
    exit;
}

// Prepare Frontend Config & Rights Mapper
$sessionUser = \SPP\SPPSession::getSessionVar('studio_user');
$role = $sessionUser['role'] ?? 'viewer';

$matrix = [
    'admin' => ['studio_view', 'studio_edit', 'studio_save', 'studio_sync', 'studio_build', 'api_access'],
    'developer' => ['studio_view', 'studio_edit', 'studio_save', 'studio_sync', 'studio_build', 'api_access'],
    'designer' => ['studio_view', 'studio_edit', 'studio_save', 'api_access'],
    'viewer' => ['studio_view', 'api_access']
];
$rights = $matrix[$role] ?? $matrix['viewer'];
\SPP\SPPGlobal::set('studio_rights', $rights); // Re-sync for UI components

$studioConfig = [
    'apiEndpoint' => 'api.php',
    'user' => [
        'id' => $sessionUser['id'] ?? 'unknown',
        'name' => $sessionUser['name'] ?? 'Guest',
        'role' => $role
    ],
    'rights' => $rights
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satya Studio | Visual Builder Pro</title>
    
    <!-- Studio Orchestrator Modal -->
    <div id="studio-modal-overlay" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 10000; align-items: center; justify-content: center;">
        <div class="studio-modal" style="background: #1e1e2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; width: 400px; padding: 30px; box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
            <h3 id="modal-title" style="margin: 0 0 10px 0; font-size: 1.2rem; color: #fff;">Satya Studio Action</h3>
            <p id="modal-desc" style="margin: 0 0 20px 0; font-size: 0.85rem; color: #888;">Satya Studio requires your input.</p>
            
            <div id="modal-input-container" style="margin-bottom: 25px;">
                <label style="display: block; font-size: 0.7rem; color: #666; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">Application Name</label>
                <input type="text" id="modal-input" style="width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 12px; border-radius: 6px; font-size: 0.9rem; outline: none; margin-bottom: 20px;" placeholder="Project name...">
                
                <label style="display: block; font-size: 0.7rem; color: #666; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">Project Blueprint</label>
                <select id="modal-blueprint" style="width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 12px; border-radius: 6px; font-size: 0.9rem; outline: none; cursor: pointer;">
                    <option value="dashboard" style="background: #1e1e2e;">📊 Enterprise Dashboard</option>
                    <option value="ecommerce" style="background: #1e1e2e;">🛒 Modern E-Commerce</option>
                    <option value="crm" style="background: #1e1e2e;">💼 Professional CRM</option>
                    <option value="hyper_book" style="background: #1e1e2e;">📖 Interactive Hyper-Book</option>
                    <option value="button_book" style="background: #1e1e2e;">🔘 Navigation Button Book</option>
                    <option value="minimal" style="background: #1e1e2e;">🌑 Minimalist Starter</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn" id="modal-cancel-btn" style="background: transparent; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 8px 20px; font-size: 0.85rem;">Cancel</button>
                <button class="btn primary-btn" id="modal-confirm-btn" style="padding: 8px 25px; font-size: 0.85rem; font-weight: 600;">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Framework Context Injection -->
    <script>
        window.SPP_CONFIG = <?php echo json_encode($studioConfig); ?>;
        window.rights = <?php echo json_encode($rights); ?>;
    </script>
    <link rel="icon" type="image/png" href="assets/satya_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700;900&family=Montserrat:wght@300;400;600;700&family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Space+Mono:wght@400;700&family=Lexend:wght@300;400;500;600&family=Syne:wght@400;700;800&family=Cinzel:wght@400;700&family=Lato:wght@300;400;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Framework Assets injected by SppmobileApp via ViewPage routines -->
    <?php if (class_exists('\SPPMod\SPPView\ViewPage')): ?>
        <?php foreach (\SPPMod\SPPView\ViewPage::getCssFiles() as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body data-theme="night">
    <div id="toast-container"></div>
    
    <!-- Branded Loading Orchestrator -->
    <div id="studio-loading-screen" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #06070d; z-index: 20000; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.6s var(--transition);">
        <div style="width: 140px; margin-bottom: 40px; filter: drop-shadow(0 0 15px rgba(234, 88, 12, 0.4));">
            <img src="assets/satya_logo.png" style="width: 100%; animation: float 4s ease-in-out infinite;" alt="Satya Studio">
        </div>
        <div style="width: 250px; height: 3px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; position: relative;">
            <div id="loading-progress-bar" style="position: absolute; top: 0; left: 0; height: 100%; width: 5%; background: linear-gradient(90deg, #ea580c, #f97316); transition: width 0.4s ease;"></div>
        </div>
        <div id="loading-status-text" style="margin-top: 20px; font-size: 0.65rem; color: #444; text-transform: uppercase; letter-spacing: 2px; font-weight: 700;">Synchronizing Orchestrator...</div>
    </div>

    <div id="workspace-layer" style="display: flex; height: 100vh;">
        <!-- Compact Mini-Sidebar -->
        <aside class="sidebar mini-sidebar" style="width: 70px; flex-shrink: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(10px); display: flex; flex-direction: column; align-items: center; padding: 20px 0; border-right: 1px solid var(--glass-border);">
            <div class="logo-wrap" style="width: 50px; height: 50px; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="location.reload()">
                <img src="assets/satya_logo.png" style="width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 0 8px rgba(234, 88, 12, 0.5));" alt="Satya Studio">
            </div>
            <nav style="flex: 1;">
                <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 20px;">
                    <li><a href="#" id="nav-studio" title="Studio" class="active" style="font-size: 1.5rem; text-decoration: none;">🏗️</a></li>
                    <li><a href="#" id="nav-assets" title="Assets" style="font-size: 1.5rem; text-decoration: none;">📁</a></li>
                    <li><a href="#" id="nav-code" title="Code" style="font-size: 1.5rem; text-decoration: none;">💻</a></li>
                </ul>
            </nav>
            <div class="sidebar-bottom" style="display: flex; flex-direction: column; gap: 15px; align-items: center; padding-bottom: 20px;">
                <div class="theme-switcher" style="display: flex; flex-direction: column; gap: 10px;">
                    <div class="theme-orb" onclick="setTheme('night')" title="Night Mode" style="width: 12px; height: 12px; border-radius: 50%; background: #6366f1; cursor: pointer; border: 2px solid rgba(255,255,255,0.2);"></div>
                    <div class="theme-orb" onclick="setTheme('day')" title="Day Mode" style="width: 12px; height: 12px; border-radius: 50%; background: #fff; cursor: pointer; border: 2px solid rgba(0,0,0,0.1);"></div>
                    <div class="theme-orb" onclick="setTheme('saffron')" title="Saffron Mode" style="width: 12px; height: 12px; border-radius: 50%; background: #ea580c; cursor: pointer; border: 2px solid rgba(255,255,255,0.2);"></div>
                </div>
                <a href="#" title="System Settings" style="font-size: 1.2rem; text-decoration: none; opacity: 0.3;">⚙️</a>
            </div>
            <script>
                function setTheme(theme) {
                    document.body.setAttribute('data-theme', theme);
                    localStorage.setItem('spp_mobile_theme', theme);
                }
                const savedTheme = localStorage.getItem('spp_mobile_theme') || 'night';
                document.body.setAttribute('data-theme', savedTheme);
            </script>
        </aside>

        <!-- Main Studio Area -->
        <main class="main-content" style="flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden;">
            <!-- Project Title Bar (Top Tier) -->
            <header class="project-header" style="background: var(--panel-bg); border-bottom: 1px solid var(--glass-border); padding: 10px 25px; display: flex; justify-content: space-between; align-items: center; z-index: 101;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <h2 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-bright); letter-spacing: -0.5px;">Satya Studio <span style="font-weight: 400; color: var(--primary-color);">Pro</span></h2>
                    <span class="badge" style="background: var(--primary-subtle); color: var(--primary-color); font-size: 0.6rem; border: 1px solid var(--primary-glow); padding: 2px 8px; border-radius: 4px;">V2.0</span>
                </div>
                <div class="header-tools" style="display: flex; gap: 10px; align-items: center;">
                    <!-- Project Selector -->
                    <div class="project-selector" style="position: relative;">
                        <button class="btn btn-sm studio-btn-soft" id="project-menu-btn" style="font-size: 0.7rem; padding: 6px 12px; border-radius: 4px; display: flex; align-items: center; gap: 8px;">
                            <span id="current-project-name">Select Project</span>
                            <span style="font-size: 0.5rem; opacity: 0.5;">▼</span>
                        </button>
                        <div id="project-dropdown" class="studio-dropdown" style="display: none; position: absolute; top: 100%; left: 0; border-radius: 6px; width: 220px; z-index: 1000; margin-top: 5px; padding: 5px 0;">
                            <div id="project-list-container" style="max-height: 200px; overflow-y: auto;">
                                <!-- Projects will be injected here -->
                                <div style="padding: 10px 15px; font-size: 0.7rem; color: #888; text-align: center;">Loading projects...</div>
                            </div>
                            <div style="border-top: 1px solid rgba(255,255,255,0.05); margin-top: 5px; padding-top: 5px;">
                                <div class="dropdown-item" id="create-project-btn" style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; color: var(--primary-color) !important; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                                    <span>➕</span> <span>New Project</span>
                                </div>
                                <div class="dropdown-item" id="open-portfolio-btn" style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; color: #fff; display: flex; align-items: center; gap: 10px;">
                                    <span>📂</span> <span>Project Portfolio</span>
                                </div>
                                <div class="dropdown-item" id="delete-current-project-btn" style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; color: var(--danger-color) !important; display: flex; align-items: center; gap: 10px; opacity: 0.7;">
                                    <span>🗑️</span> <span>Delete Current</span>
                                </div>
                            </div>
                        </div>
                    </div>

    <!-- Project Portfolio Modal -->
    <div id="portfolio-modal-overlay" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 10001; align-items: center; justify-content: center;">
        <div class="studio-modal" style="background: #1e1e2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; width: 700px; max-height: 80vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 40px 80px rgba(0,0,0,0.6);">
            <header style="padding: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="color: #fff; margin-bottom: 10px; font-weight: 700;">Welcome to Satya Studio Pro</h2>
                <p style="margin-bottom: 30px; font-size: 1rem; opacity: 0.6; max-width: 400px; text-align: center;">Orchestrate your mobile vision with Satya Studio's advanced design system engine.</p>
                </div>
                <button class="btn" id="close-portfolio-btn" style="background: rgba(255,255,255,0.05); border: none; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;">✕</button>
            </header>
            
            <div id="portfolio-list" style="flex: 1; overflow-y: auto; padding: 10px 20px;">
                <!-- Project Portfolio items will be injected here -->
            </div>

            <footer style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center;">
                <span id="portfolio-count" style="font-size: 0.75rem; color: #666;">0 Projects found.</span>
                <button class="btn primary-btn" onclick="mobileStudio.createNewProject()" style="padding: 10px 25px; font-size: 0.85rem; font-weight: 600;">Create New Project</button>
            </footer>
        </div>
    </div>

                    <?php 
                        $rights = \SPP\SPPGlobal::is_set('studio_rights') ? \SPP\SPPGlobal::get('studio_rights') : [];
                        if (in_array('studio_save', $rights)): 
                    ?>
                        <button class="btn primary-btn btn-sm" id="save-project-btn" style="font-size: 0.7rem; font-weight: 600; padding: 6px 15px;">Save Project</button>
                    <?php endif; ?>

                    <?php if (in_array('studio_build', $rights)): ?>
                        <div class="build-orchestrator" style="position: relative;">
                            <button class="btn btn-sm studio-btn-secondary" id="build-menu-btn" style="font-size: 0.7rem; padding: 6px 15px; border-radius: 4px; font-weight: 600;">Build / Export</button>
                            <div id="build-dropdown" class="studio-dropdown" style="display: none; position: absolute; top: 100%; right: 0; border-radius: 6px; width: 180px; z-index: 1000; margin-top: 5px;">
                                <div class="dropdown-item" id="export-source-btn" style="padding: 12px 15px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 10px; background: rgba(168, 85, 247, 0.1); color: #a855f7 !important; font-weight: 700;">
                                    <span>📦</span> <span>Export Source Code (Flutter)</span>
                                </div>
                                <div class="dropdown-item" data-platform="android" style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 10px;">
                                    <span>🤖</span> <span>Android (APK/AAB)</span>
                                </div>
                                <div class="dropdown-item" data-platform="ios" style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 10px;">
                                    <span>🍎</span> <span>iOS (IPA)</span>
                                </div>
                                <div class="dropdown-item" data-platform="windows" style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 10px;">
                                    <span>🪟</span> <span>Windows (EXE)</span>
                                </div>
                                <div class="dropdown-item" data-platform="web" style="padding: 10px 15px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                                    <span>🌐</span> <span>Web (Flutter Web)</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <a href="logout" class="btn btn-sm" style="font-size: 0.7rem; color: var(--danger-color); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 6px 12px; border-radius: 4px; text-decoration: none; display: flex; align-items: center; gap: 5px;">
                        <span>Sign Out</span>
                    </a>
                </div>
            </header>

            <section id="view-container" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;"></section>
        </main>
    </div>

    <!-- Framework Runtime & Script Injection -->
    <?php if (class_exists('\SPPMod\SPPView\ViewPage')): ?>
        <?php foreach (\SPPMod\SPPView\ViewPage::getJsFiles() as $js): ?>
            <script src="<?php echo htmlspecialchars($js['path']); ?>" <?php foreach ($js['options'] as $k => $v) echo "$k=\"$v\" "; ?>></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        window.SPP_CSRF_TOKEN = '<?php echo \SPP\SPPSession::getCsrfToken(); ?>';
        if (window.SPPUX) {
            window.BaseComponent = SPPUX.BaseComponent;
            window.html = SPPUX.html;
            window.TrustedHTML = SPPUX.TrustedHTML;
        }
    </script>
</body>
</html>
