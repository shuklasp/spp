<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class CreateAppCommand extends Command
{
    public function execute(array $args): void
    {
        $appName = $args[2] ?? '';
        if (empty($appName) || str_starts_with($appName, '--')) {
            echo "Error: Please provide a valid target application identifier. Example: php spp.php make:app dashboard\n";
            return;
        }

        $appName = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($appName));
        $mode = 'spa';
        $aiBlueprint = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--mode=')) {
                $mode = strtolower(substr($arg, 7));
            } elseif (str_starts_with($arg, '--ai-blueprint=')) {
                $aiBlueprint = trim(substr($arg, 15), " '\"");
            }
        }

        $this->scaffoldApplication($appName, $mode, $aiBlueprint);
    }

    public function getName(): string
    {
        return 'make:app-legacy';
    }

    public function getDescription(): string
    {
        return 'Legacy scaffolder — use make:app instead (kept for backward compatibility)';
    }

    private function scaffoldApplication(string $appName, string $mode, ?string $aiBlueprint = null): void
    {
        $baseDir = SPP_APP_DIR . '/src/' . $appName;

        echo "🚀 Provisioning self-contained Instructional Skeleton App: '{$appName}'...\n";

        $dirs = [
            $baseDir,
            $baseDir . '/pages',
            $baseDir . '/serv',
            $baseDir . '/etc',
            $baseDir . '/components',
            $baseDir . '/assets',
            $baseDir . '/assets/js',
            $baseDir . '/assets/css',
            $baseDir . '/assets/img',
            $baseDir . '/events',
            $baseDir . '/resources/views',
            $baseDir . '/resources/themes/default/views',
            $baseDir . '/resources/themes/default/css',
            $baseDir . '/resources/themes/default/js',
            $baseDir . '/resources/themes/default/img',
            $baseDir . '/comp/templates',
            $baseDir . '/modules'
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0777, true)) {
                    echo "  📁 Created directory map: /src/{$appName}/" . basename($dir) . "\n";
                } else {
                    echo "Error: Failed to instantiate directory tree: {$dir}\n";
                    return;
                }
            }
        }

        // Copy SPP framework logo asset to target application's local asset container to enforce full branding
        $sourceLogo = SPP_APP_DIR . '/res/spp/images/logo.jpg';
        $targetLogo = $baseDir . '/assets/logo.jpg';
        if (file_exists($sourceLogo)) {
            @copy($sourceLogo, $targetLogo);
            echo "  🖼️ Copied SPP core branding logo asset: /src/{$appName}/assets/logo.jpg\n";
        } else {
            // Create fallback premium colored placeholder image buffer natively if admin logo file is missing
            $im = @imagecreatetruecolor(200, 60);
            if ($im) {
                $bg = imagecolorallocate($im, 99, 102, 241);
                $fg = imagecolorallocate($im, 255, 255, 255);
                imagefill($im, 0, 0, $bg);
                imagestring($im, 5, 25, 20, "SPP BRAND", $fg);
                imagejpeg($im, $targetLogo, 90);
                imagedestroy($im);
                echo "  🖼️ Synthesized fallback SPP logo asset: /src/{$appName}/assets/logo.jpg\n";
            }
        }

        // 1. Generate local embedded etc/config.yml ensuring absolute self-containment
        $configYml = "################################################################################\n";
        $configYml .= "# Application Runtime Configuration: {$appName}\n";
        $configYml .= "# Embedded local target directory mapping ensuring perfect self-containment.\n";
        $configYml .= "################################################################################\n\n";
        $configYml .= "app_name: \"{$appName}\"\n";
        $configYml .= "navigation_mode: \"{$mode}\"\n";
        $configYml .= "default_layout: \"premium-glass\"\n";
        $configYml .= "api_integrity_hmac: true\n\n";
        $configYml .= "# Declarative asset mapping auto-registered into routes by Drishyam at boot time\n";
        $configYml .= "assets:\n";
        $configYml .= "  theme-assets: \"resources/themes\"\n";
        $configYml .= "  app-assets: \"assets\"\n";

        file_put_contents($baseDir . '/etc/config.yml', $configYml);
        echo "  📄 Created embedded configuration: /src/{$appName}/etc/config.yml\n";

        // 2. Generate local embedded routes map (routes.yml) configuring standard controllers and pages
        $routesYml = "################################################################################\n";
        $routesYml .= "# Application Isolated Routes\n";
        $routesYml .= "# NOTE: Static asset directories are declared in config.yml under 'assets' and auto-booted.\n";
        $routesYml .= "################################################################################\n\n";
        $routesYml .= "routes:\n";
        $routesYml .= "  - path: \"login\"\n";
        $routesYml .= "    target: \"\\\\App\\\\{$appName}\\\\Serv\\\\AuthController@login\"\n";
        $routesYml .= "    type: \"controller\"\n";
        $routesYml .= "    absolute: false\n\n";
        $routesYml .= "  - path: \"logout\"\n";
        $routesYml .= "    target: \"\\\\App\\\\{$appName}\\\\Serv\\\\AuthController@logout\"\n";
        $routesYml .= "    type: \"controller\"\n";
        $routesYml .= "    absolute: false\n\n";
        $routesYml .= "  - path: \"\"\n";
        $routesYml .= "    target: \"src/{$appName}/pages/index.php\"\n";
        $routesYml .= "    type: \"page_view\"\n";
        $routesYml .= "    absolute: false\n";

        file_put_contents($baseDir . '/etc/routes.yml', $routesYml);
        echo "  📄 Created routing rules map: /src/{$appName}/etc/routes.yml\n";

        // 2.5 Generate default theme manifest and base login view template
        $themeYml = "name: \"default\"\nversion: \"1.0.0\"\ndescription: \"Default presentation theme scaffolding\"\n";
        file_put_contents($baseDir . '/resources/themes/default/theme.yml', $themeYml);
        echo "  🎨 Created default theme descriptor: /src/{$appName}/resources/themes/default/theme.yml\n";

        $loginTpl = <<<BLADE
<div style="max-width: 400px; margin: 4rem auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <h2 style="margin-top: 0; color: #333;">Workspace Login</h2>
    @if(!empty(\$error)) <div style="color: red; margin-bottom: 1rem;">{{ \$error }}</div> @endif
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required style="width: 100%; padding: 0.8rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 6px;" />
        <input type="password" name="password" placeholder="Password" required style="width: 100%; padding: 0.8rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 6px;" />
        <button type="submit" style="width: 100%; padding: 0.8rem; background: #6366f1; color: #fff; border: none; border-radius: 6px; cursor: pointer;">Sign In</button>
    </form>
</div>
BLADE;
        file_put_contents($baseDir . '/resources/views/login.blade.php', $loginTpl);
        echo "  🔒 Created reusable core login view: /src/{$appName}/resources/views/login.blade.php\n";

        // 2.6 Generate reusable production AuthController executing single-argument guard login calls cleanly
        $authControllerPhp = <<<PHP
<?php
namespace App\\{$appName}\\Serv;

/**
 * Class AuthController
 * Scaffolds local contextual session management delegating to direct WebGuard authorization endpoints.
 */
class AuthController
{
    public function login()
    {
        \$error = '';
        if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
            \$username = trim(\$_POST['username'] ?? '');
            \$password = \$_POST['password'] ?? '';

            if (!empty(\$username) && !empty(\$password)) {
                try {
                    // Fallback local static admin credential lookup
                    if (\$username === 'admin' && (\$password === 'admin' || \$password === 'password')) {
                        \$user = (object)['id' => 'admin', 'username' => 'admin', 'email' => 'admin@localhost'];
                        \\SPPMod\\SPPAuth\\SPPAuth::guard('web')->login(\$user);
                        header("Location: " . \\SPP\\App::getBaseUrl('{$appName}'));
                        exit;
                    } else {
                        \$error = 'Invalid administrative credentials.';
                    }
                } catch (\Exception \$e) {
                    \$error = 'Authentication exception: ' . \$e->getMessage();
                }
            }
        }

        // Delegate rendering to primary View engine
        if (class_exists('\\\\SPPMod\\\\Drishyam\\\\Drishyam')) {
            return \\SPPMod\\Drishyam\\Drishyam::render("login", ['error' => \$error]);
        }
        return "Authentication portal rendering fallback active. Error: " . htmlspecialchars(\$error);
    }

    public function logout()
    {
        if (class_exists('\\\\SPPMod\\\\SPPAuth\\\\SPPAuth')) {
            \\SPPMod\\SPPAuth\\SPPAuth::guard('web')->logout();
        }
        header("Location: " . \\SPP\\App::getBaseUrl('{$appName}') . "/login");
        exit;
    }
}
PHP;
        file_put_contents($baseDir . '/serv/AuthController.php', $authControllerPhp);
        echo "  ⚡ Created local authentication controller: /src/{$appName}/serv/AuthController.php\n";

        // 3. Generate local embedded etc/services.yml
        $servicesYml = "################################################################################\n";
        $servicesYml .= "# Discovered & Registered Dynamic Services\n";
        $servicesYml .= "################################################################################\n\n";
        $servicesYml .= "services:\n";
        $servicesYml .= "  - name: \"task.create\"\n";
        $servicesYml .= "    script: \"src/{$appName}/serv/task_create.php\"\n";
        $servicesYml .= "    method: \"POST\"\n";

        file_put_contents($baseDir . '/etc/services.yml', $servicesYml);
        echo "  📄 Created service registry: /src/{$appName}/etc/services.yml\n";

        // 4. Generate sample action model script (task_create.php) demonstrating app logic
        $servScript = "<?php\n";
        $servScript .= "// Action service processing form payloads inside isolated application boundary\n";
        $servScript .= "\$taskTitle = trim(\$_POST['taskTitle'] ?? 'Untitled Task');\n";
        $servScript .= "\$taskPriority = trim(\$_POST['taskPriority'] ?? 'Normal');\n\n";
        $servScript .= "// Simulate persistent storage logic return\n";
        $servScript .= "\$renderedCard = \"<div class='glass-card item-card' style='margin-top: 1rem; border-left: 4px solid #6366f1;'>\";\n";
        $servScript .= "\$renderedCard .= \"<h4 style='margin:0;'>\" . htmlspecialchars(\$taskTitle) . \"</h4>\";\n";
        $servScript .= "\$renderedCard .= \"<span class='badge' style='margin-top:0.5rem;'>Priority: \" . htmlspecialchars(\$taskPriority) . \"</span>\";\n";
        $servScript .= "\$renderedCard .= \"</div>\";\n\n";
        $servScript .= "\$response = [\n";
        $servScript .= "    'status' => 'success',\n";
        $servScript .= "    'message' => 'Task successfully synthesized and appended!',\n";
        $servScript .= "    'html' => \$renderedCard\n";
        $servScript .= "];\n";

        file_put_contents($baseDir . '/serv/task_create.php', $servScript);
        echo "  ⚡ Created action service model: /src/{$appName}/serv/task_create.php\n";

        // 5. Generate reusable skeleton Component tag element blueprint
        $compHtml = "<div class='premium-component-box' style='padding: 1.5rem; background: rgba(99,102,241,0.05); border: 1px dashed #6366f1; border-radius: 16px; margin-bottom: 2rem;'>\n";
        $compHtml .= "    <h3 style='margin-top:0; color: #a5b4fc;'>🧩 Embedded Shell Component</h3>\n";
        $compHtml .= "    <p style='font-size: 0.85rem; color: #94a3b8; margin: 0;'>This fragment resides in <code>/src/{$appName}/components/shell_banner.html</code> and is mounted cleanly via native tags.</p>\n";
        $compHtml .= "</div>\n";

        file_put_contents($baseDir . '/components/shell_banner.html', $compHtml);
        echo "  🧩 Created UI skeleton component: /src/{$appName}/components/shell_banner.html\n";

        // 5.5. Generate sample event listener/override script demonstrating application-level event mapping
        $eventPhp = "<?php\n";
        $eventPhp .= "namespace EventHandlers;\n\n";
        $eventPhp .= "/**\n";
        $eventPhp .= " * Sample localized application Event Handler.\n";
        $eventPhp .= " * Discovered dynamically by SPPEvent engine under /src/{\$appName}/events directory.\n";
        $eventPhp .= " */\n";
        $eventPhp .= "class UserRegisteredHandler extends \\SPP\\EventHandler {\n";
        $eventPhp .= "    public function afterHandler(&\$params = []) {\n";
        $eventPhp .= "        // Intercept event payload natively inside application boundaries\n";
        $eventPhp .= "        if (defined('SPP_DEBUG') && SPP_DEBUG) {\n";
        $eventPhp .= "            @file_put_contents(SPP_APP_DIR . '/var/logs/{$appName}_events.log', '['.date('Y-m-d H:i:s').'] Intercepted UserRegistered target event flawlessly.\\n', FILE_APPEND);\n";
        $eventPhp .= "        }\n";
        $eventPhp .= "    }\n";
        $eventPhp .= "}\n";

        file_put_contents($baseDir . '/events/UserRegisteredHandler.php', $eventPhp);
        echo "  ⚡ Created local skeleton event handler: /src/{$appName}/events/UserRegisteredHandler.php\n";

        // 6. Generate sample secondary instructional sub-page (about.php)
        $aboutPhp = <<<HTML
<div class="page-view" style="animation: fadeIn 0.3s ease;">
    <h2>📖 Architectural Flow Explained</h2>
    <p style="color: var(--text-dim); line-height: 1.6;">
        Every SPP application operates cleanly decoupled:
    </p>
    <ul style="text-align: left; color: var(--text-dim); line-height: 1.8;">
        <li><b>Pages</b> (<code>/pages/</code>): Contain raw layout blueprints with Zero-JS directive hooks.</li>
        <li><b>Components</b> (<code>/components/</code>): Reusable modular UI building blocks.</li>
        <li><b>Services</b> (<code>/serv/</code>): Isolated data processing endpoints mapping directly to client targets.</li>
        <li><b>Events</b> (<code>/events/</code>): Houses localized domain event listeners and overriding hook handlers natively.</li>
        <li><b>Assets</b> (<code>/assets/</code>): Holds full local application media branding routed statically.</li>
        <li><b>Configurations</b> (<code>/etc/</code>): Application-specific definitions and service maps.</li>
    </ul>
    <button class="btn" style="margin-top:1rem;" onclick="location.reload()">Back to Live Preview</button>
</div>
HTML;
        file_put_contents($baseDir . '/pages/about.php', $aboutPhp);
        echo "  📄 Created secondary skeleton page: /src/{$appName}/pages/about.php\n";

        // 7. Generate rich responsive master landing skeleton inside pages/index.php prominently showcasing SPP Branding
        $navAttr = ($mode === 'spa') ? 'data-spp-navigation="spa"' : 'data-spp-navigation="standard"';

        $blueprintBox = "";
        if (!empty($aiBlueprint)) {
            $safeBlueprint = htmlspecialchars($aiBlueprint, ENT_QUOTES);
            $blueprintBox = <<<BOX
        <!-- Autonomous AI Blueprint Injector Box -->
        <div style="background: rgba(14, 165, 233, 0.1); border: 1px solid #0ea5e9; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; text-align: left;">
            <span style="font-size: 0.75rem; font-weight: 800; color: #38bdf8; text-transform: uppercase;">🤖 AI Blueprint Spec Setup</span>
            <p style="margin: 0.4rem 0 0 0; color: #f0f9ff; font-size: 0.95rem;">"{\$safeBlueprint}"</p>
            <div style="margin-top: 0.8rem; display: flex; gap: 0.5rem;">
                <span class="badge" style="background: #0284c7; color: #fff;">Pre-Warmed</span>
                <span class="badge" style="background: #0f766e; color: #ccfbf1;">Ambient Tokens Active</span>
            </div>
        </div>
BOX;
        }

        $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Enterprise Workspace - {\$appName}</title>
    <!-- Premium responsive UI styles -->
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --accent: #6366f1;
            --input-bg: rgba(0, 0, 0, 0.2);
        }
        body {
            margin: 0; padding: 2rem; font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-gradient); color: var(--text-main);
            min-height: 100vh; display: flex; justify-content: center; align-items: flex-start;
        }
        .app-shell {
            width: 100%; max-width: 900px; padding: 2.5rem;
            background: var(--card-bg); backdrop-filter: blur(20px);
            border: 1px solid var(--card-border); border-radius: 28px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .header-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--card-border); padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .branding-block { display: flex; align-items: center; gap: 1.2rem; }
        .app-logo { height: 50px; width: auto; object-fit: contain; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        h1 { font-size: 1.8rem; margin: 0; font-weight: 800; letter-spacing: -0.03em; }
        .badge {
            display: inline-block; padding: 0.35rem 1rem; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em; border-radius: 999px;
            background: rgba(99,102,241,0.2); color: #a5b4fc;
        }
        .grid-layout { display: grid; grid-template-columns: 1fr 1.2fr; gap: 2rem; }
        @media(max-width: 768px) { .grid-layout { grid-template-columns: 1fr; } }
        
        .form-panel { background: rgba(0,0,0,0.15); padding: 1.5rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); }
        .form-group { margin-bottom: 1.2rem; display: flex; flex-direction: column; text-align: left; }
        label { font-size: 0.85rem; color: var(--text-dim); margin-bottom: 0.4rem; font-weight: 600; }
        input[type="text"], select {
            padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid var(--card-border);
            background: var(--input-bg); color: white; font-size: 0.95rem; outline: none;
            transition: border 0.2s;
        }
        input[type="text"]:focus, select:focus { border-color: var(--accent); }
        
        .btn {
            padding: 0.85rem 1.5rem; font-size: 0.95rem; font-weight: 600; width: 100%;
            background: var(--accent); color: white; border: none; border-radius: 12px; cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(99,102,241,0.3); }
        
        .glass-card { background: rgba(255,255,255,0.03); padding: 1rem 1.5rem; border-radius: 16px; border: 1px solid var(--card-border); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body {\$navAttr}>

    <div class="app-shell">
        <header class="header-bar">
            <div class="branding-block">
                <!-- Prominently rendered SPP Framework Branding Asset Logo routed straight through local isolated asset container -->
                <img src="assets/logo.jpg" alt="SPP Framework Brand Logo" class="app-logo" onerror="this.style.display='none'" />
                <div>
                    <h1>{\$appName}</h1>
                    <span style="font-size:0.85rem; color:var(--text-dim);">Powered by SPP Engine</span>
                </div>
            </div>
            <div class="badge">Mode: {$mode}</div>
        </header>
        {\$blueprintBox}

        <!-- Dynamic Demonstration Component tag usage -->
        <spp-component name="shell_banner">
            <!-- Simulated server inclusion fallback -->
            <div style="padding:1rem; border:1px solid #334155; border-radius:12px; margin-bottom:1.5rem; color:#94a3b8; font-size:0.85rem;">
                🧩 Mounted Component: <code>/components/shell_banner.html</code> pre-rendered.
            </div>
        </spp-component>

        <main class="grid-layout">
            <!-- Left Side: Interactive Starter Form (Demonstrates data-spp-post actions) -->
            <div class="form-panel">
                <h3 style="margin-top:0; margin-bottom:1rem; font-size:1.1rem;">⚡ Interactive Action Form</h3>
                
                <!-- Zero-JS Action Trigger: submitting appends the task output directly into #tasks-list container -->
                <form data-spp-post="task.create" data-spp-target="#tasks-list" data-spp-transition="scale">
                    <div class="form-group">
                        <label>Task Title (Live Bound)</label>
                        <!-- Demonstrates Two-Way Signal Binding -->
                        <input type="text" name="taskTitle" data-spp-bind="liveTaskName" placeholder="Enter task output..." required />
                    </div>
                    <div class="form-group">
                        <label>Priority Level</label>
                        <select name="taskPriority">
                            <option value="High">🔥 High Priority</option>
                            <option value="Normal" selected>⚡ Normal Priority</option>
                            <option value="Low">💤 Low Priority</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Synthesize Task</button>
                </form>
            </div>

            <!-- Right Side: Live Feedback Binding & Rendered Output Target -->
            <div>
                <!-- Real-time Two-Way Signal bound container preview -->
                <div class="glass-card" style="margin-bottom:1.5rem; background: rgba(0,0,0,0.2);">
                    <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Live Binding Preview</span>
                    <h3 style="margin: 0.3rem 0; color: #38bdf8;" data-spp-text="liveTaskName">Start typing on left...</h3>
                </div>

                <h3 style="margin-top:0; font-size:1.1rem; border-bottom:1px solid var(--card-border); padding-bottom:0.5rem;">📋 Synthesized Tasks Output</h3>
                
                <!-- Dynamic server responses append directly into this container -->
                <div id="tasks-list">
                    <p style="color:var(--text-dim); font-size:0.9rem; font-style:italic;">Submit the action form to append layout cards here seamlessly.</p>
                </div>
            </div>
        </main>
        
        <footer style="margin-top:3rem; padding-top:1.5rem; border-top:1px solid var(--card-border); text-align:center; font-size:0.85rem; color:var(--text-dim);">
            💡 To inspect framework operational layout maps, open <a href="about.php" style="color:var(--accent); text-decoration:none; font-weight:600;">about.php</a> directly.
        </footer>
    </div>

</body>
</html>
HTML;

        file_put_contents($baseDir . '/pages/index.php', $indexHtml);
        echo "  ✨ Created UI blueprint landing view: /src/{$appName}/pages/index.php\n";
        echo "================================================================================\n";
        echo "🎉 Skeleton Application '{$appName}' scaffolded successfully with full SPP Branding!\n";
        echo "Launch straight away via URL path targets matching your host parameters.\n";
        echo "================================================================================\n";
    }
}
