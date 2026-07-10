<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ignition Error: <?php echo htmlspecialchars($title ?? 'Exception'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #09090b; 
            --surface: rgba(24, 24, 27, 0.75); 
            --surface-hover: rgba(39, 39, 42, 0.85);
            --primary: #f43f5e; 
            --primary-glow: rgba(244, 63, 94, 0.2);
            --text: #f4f4f5; 
            --muted: #a1a1aa; 
            --border: rgba(255, 255, 255, 0.1);
            --accent: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.2);
            --success: #10b981;
            --code-bg: #000000;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: var(--bg); 
            background-image: 
                radial-gradient(circle at 100% 0%, var(--primary-glow), transparent 500px),
                radial-gradient(circle at 0% 100%, var(--accent-glow), transparent 500px);
            background-attachment: fixed;
            color: var(--text); 
            font-family: 'Inter', sans-serif; 
            line-height: 1.6; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container { 
            max-width: 1300px; 
            width: 100%; 
            margin: 40px auto; 
            padding: 0 24px; 
            animation: fadeUp 0.5s ease-out forwards; 
        }
        
        /* Navbar / Brand Header */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .brand {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .brand-pill {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .env-indicator {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .env-badge {
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        /* Hero Exception Banner */
        .header-card { 
            background: var(--surface); 
            backdrop-filter: blur(16px);
            padding: 40px; 
            border-radius: 20px; 
            border: 1px solid var(--border);
            border-left: 8px solid var(--primary); 
            margin-bottom: 32px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
        }
        .exception-type { 
            font-size: 0.9rem; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            color: var(--primary); 
            font-weight: 800; 
            margin-bottom: 12px; 
            display: inline-block;
        }
        .message { 
            font-size: 2.2rem; 
            font-weight: 800; 
            letter-spacing: -0.8px; 
            line-height: 1.25; 
            color: white;
            margin-bottom: 20px;
        }
        .location-tag { 
            color: #38bdf8; 
            font-family: 'Fira Code', monospace; 
            font-size: 0.9rem; 
            background: rgba(0, 0, 0, 0.4); 
            padding: 10px 16px; 
            border-radius: 8px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            border: 1px solid rgba(56, 189, 248, 0.2);
        }

        /* Tab Navigation */
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
            overflow-x: auto;
        }
        .tab-btn {
            background: var(--surface);
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .tab-btn:hover {
            background: var(--surface-hover);
            color: var(--text);
        }
        .tab-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            box-shadow: 0 10px 20px -5px var(--accent-glow);
        }

        /* Tab Content Views */
        .tab-pane {
            display: none;
            background: var(--surface); 
            backdrop-filter: blur(16px);
            padding: 40px; 
            border-radius: 20px; 
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.4); 
        }
        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        /* Grid for Stack Trace & Snippets */
        .trace-layout {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 32px;
        }
        @media (max-width: 1024px) {
            .trace-layout { grid-template-columns: 1fr; }
        }
        .frame-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 700px;
            overflow-y: auto;
            padding-right: 12px;
        }
        /* Custom scrollbar for frames */
        .frame-list::-webkit-scrollbar { width: 6px; }
        .frame-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 10px; }
        .frame-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }

        .frame-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border);
            padding: 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .frame-card:hover {
            background: var(--surface-hover);
            transform: translateX(4px);
        }
        .frame-card.active {
            border-left: 4px solid var(--accent);
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.4);
        }
        .frame-func {
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            color: #38bdf8;
            font-weight: 600;
            margin-bottom: 6px;
            word-break: break-all;
        }
        .frame-path {
            font-size: 0.8rem;
            color: var(--muted);
            word-break: break-all;
        }

        /* Active Code Preview */
        .code-preview-container {
            display: flex;
            flex-direction: column;
        }
        .code-header {
            background: rgba(0, 0, 0, 0.6);
            padding: 12px 20px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            border: 1px solid var(--border);
            border-bottom: none;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .code-snippet-box {
            background: var(--code-bg);
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            border: 1px solid var(--border);
            padding: 20px 0;
            overflow-x: auto;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            box-shadow: inset 0 2px 15px rgba(0,0,0,0.8);
        }
        .code-line {
            padding: 4px 20px;
            white-space: pre;
            color: #e2e8f0;
            display: flex;
        }
        .code-line:hover { background: rgba(255, 255, 255, 0.04); }
        .code-line.error-line {
            background: rgba(244, 63, 94, 0.2);
            border-left: 4px solid var(--primary);
            padding-left: 16px;
            color: white;
            font-weight: 600;
        }
        .line-num {
            color: #475569;
            width: 50px;
            flex-shrink: 0;
            user-select: none;
            border-right: 1px solid #262626;
            margin-right: 20px;
            text-align: right;
            padding-right: 12px;
        }

        /* Actionable Fix Pane */
        .fix-box {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-left: 6px solid var(--success);
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        .fix-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #34d399;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .fix-desc {
            color: #e2e8f0;
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .fix-cmd {
            background: #000;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 16px 24px;
            border-radius: 12px;
            font-family: 'Fira Code', monospace;
            color: #a7f3d0;
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .copy-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .copy-btn:hover { opacity: 0.9; }

        /* Key Value Info Tables */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table th, .info-table td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .info-table th {
            width: 250px;
            font-weight: 600;
            color: #38bdf8;
            background: rgba(0,0,0,0.2);
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        .info-table td {
            color: var(--text);
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            background: rgba(0,0,0,0.1);
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        /* CLI Guide Pane */
        .cli-guide-card {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 32px;
            border-radius: 16px;
        }
        .cli-heading {
            font-size: 1.4rem;
            font-weight: 700;
            color: #818cf8;
            margin-bottom: 16px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Brand Header -->
        <header class="top-bar">
            <div class="brand">
                <span>🔥 SPP Ignition</span>
                <span class="brand-pill">DEVELOPER MODE</span>
            </div>
            <div class="env-indicator">
                <span>App Context: <strong><?php echo htmlspecialchars(\SPP\Scheduler::getContext() ?: 'default'); ?></strong></span>
                <span class="env-badge">SPP_DEBUG = TRUE</span>
            </div>
        </header>

        <!-- Hero Exception Card -->
        <div class="header-card">
            <div class="exception-type"><?php echo htmlspecialchars($title ?? 'Exception'); ?></div>
            <h1 class="message"><?php echo htmlspecialchars($message ?? 'An unexpected fatal error occurred.'); ?></h1>
            <div class="location-tag">
                <span>📁 <?php echo htmlspecialchars($file ?? 'Unknown File'); ?></span>
                <span>::</span>
                <span>Line <?php echo htmlspecialchars($line ?? '0'); ?></span>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('trace')">🔥 Stack Trace & Snippets</button>
            <button class="tab-btn" onclick="switchTab('fix')">💡 Actionable AI Solution</button>
            <button class="tab-btn" onclick="switchTab('request')">🌍 Request & Routing</button>
            <button class="tab-btn" onclick="switchTab('env')">🛡️ Environment & CLI Switch</button>
        </div>

        <!-- Tab Content 1: Stack Trace & Snippets -->
        <div id="tab-trace" class="tab-pane active">
            <div class="trace-layout">
                <!-- Left Column: Frame List -->
                <div class="frame-list">
                    <!-- Main Exception Frame -->
                    <div class="frame-card active" onclick="selectFrame(0)">
                        <div class="frame-func">💥 Exception Thrown</div>
                        <div class="frame-path"><?php echo htmlspecialchars(basename($file ?? '')); ?>:<?php echo htmlspecialchars($line ?? ''); ?></div>
                    </div>
                    <!-- Trace Frames -->
                    <?php if (isset($trace) && is_array($trace)): ?>
                        <?php foreach ($trace as $i => $t): ?>
                            <div class="frame-card" onclick="selectFrame(<?php echo $i + 1; ?>)">
                                <div class="frame-func">
                                    <?php echo htmlspecialchars(isset($t['class']) ? $t['class'] . $t['type'] : ''); ?><?php echo htmlspecialchars($t['function'] ?? 'unknown'); ?>()
                                </div>
                                <?php if (isset($t['file'])): ?>
                                    <div class="frame-path"><?php echo htmlspecialchars(basename($t['file'])); ?>:<?php echo htmlspecialchars($t['line'] ?? 0); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Active Code Snippets -->
                <div class="code-preview-container">
                    <!-- Main Exception Snippet (Frame 0) -->
                    <div id="snippet-0" class="code-snippet-box-wrapper">
                        <div class="code-header">
                            <span><?php echo htmlspecialchars($file ?? ''); ?></span>
                            <span>Line <?php echo htmlspecialchars($line ?? ''); ?></span>
                        </div>
                        <div class="code-snippet-box">
                            <?php 
                            if (isset($file) && file_exists($file)) {
                                $lines = file($file);
                                $start = max(0, $line - 10);
                                $end = min(count($lines), $line + 10);
                                for ($j = $start; $j < $end; $j++) {
                                    $isErrorLine = ($j + 1) === (int)$line;
                                    $lineStr = htmlspecialchars($lines[$j]);
                                    $class = $isErrorLine ? 'code-line error-line' : 'code-line';
                                    echo "<div class='{$class}'><span class='line-num'>" . ($j + 1) . "</span> " . $lineStr . "</div>";
                                }
                            } else {
                                echo "<div class='code-line'>Code snippet not available.</div>";
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Trace Frame Snippets (Frame 1..N) -->
                    <?php if (isset($trace) && is_array($trace)): ?>
                        <?php foreach ($trace as $i => $t): ?>
                            <div id="snippet-<?php echo $i + 1; ?>" class="code-snippet-box-wrapper" style="display: none;">
                                <div class="code-header">
                                    <span><?php echo htmlspecialchars($t['file'] ?? 'Internal Closure / Core'); ?></span>
                                    <span>Line <?php echo htmlspecialchars($t['line'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="code-snippet-box">
                                    <?php 
                                    if (isset($t['file']) && file_exists($t['file'])) {
                                        $fLines = file($t['file']);
                                        $fLine = (int)($t['line'] ?? 0);
                                        $fStart = max(0, $fLine - 10);
                                        $fEnd = min(count($fLines), $fLine + 10);
                                        for ($k = $fStart; $k < $fEnd; $k++) {
                                            $isErrorLine = ($k + 1) === $fLine;
                                            $lineStr = htmlspecialchars($fLines[$k]);
                                            $class = $isErrorLine ? 'code-line error-line' : 'code-line';
                                            echo "<div class='{$class}'><span class='line-num'>" . ($k + 1) . "</span> " . $lineStr . "</div>";
                                        }
                                    } else {
                                        echo "<div class='code-line'>Code snippet not available for this frame.</div>";
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Content 2: Actionable AI Fix -->
        <div id="tab-fix" class="tab-pane">
            <?php
            // Simple Expert System to provide precise, actionable solutions for common SPP errors
            $errTitle = $title ?? '';
            $errMsg = $message ?? '';
            $fixTitle = "Recommended Action";
            $fixDesc = "Review the stack trace to determine the source of the exception. Check your entity configurations, controllers, or service definitions.";
            $fixCmd = "php spp/spp.php --help";

            if (stripos($errMsg, 'Unclosed') !== false || stripos($errMsg, 'ParseError') !== false || stripos($errTitle, 'ParseError') !== false) {
                $fixTitle = "BladeOne / Template Compilation Error";
                $fixDesc = "The template engine encountered malformed syntax or unescaped directives (such as `@extends` or nested `{{ }}` braces). Ensure you use HTML entities (`&#64;`, `&#123;`, `&#125;`) for literal strings in your view templates.";
                $fixCmd = "php spp/spp.php cache:clear";
            } elseif (stripos($errMsg, 'registerCommand') !== false || stripos($errMsg, 'undefined method') !== false) {
                $fixTitle = "Missing or Incompatible Method Call";
                $fixDesc = "A module or service attempted to invoke a method that does not exist in the target class. Verify that your core files (e.g. `class.commandmanager.php`) are fully up to date and include the expected API definitions.";
                $fixCmd = "php spp/spp.php env:mode dev";
            } elseif (stripos($errMsg, 'table') !== false || stripos($errMsg, 'database') !== false || stripos($errMsg, 'sql') !== false) {
                $fixTitle = "Database / Schema Connectivity Issue";
                $fixDesc = "The application failed to query the required database tables or hit a connection timeout. Verify your `sppdbpool` settings and ensure migrations have run.";
                $fixCmd = "php spp/spp.php make:scaffold";
            }
            ?>
            <div class="fix-box">
                <div class="fix-title">💡 <?php echo htmlspecialchars($fixTitle); ?></div>
                <div class="fix-desc"><?php echo htmlspecialchars($fixDesc); ?></div>
                <div class="fix-cmd">
                    <span id="cmd-text"><?php echo htmlspecialchars($fixCmd); ?></span>
                    <button class="copy-btn" onclick="copyCommand()">📋 Copy Command</button>
                </div>
            </div>
            
            <div class="cli-guide-card">
                <h3 class="cli-heading">🔄 Need to test in Production Mode?</h3>
                <p style="color: var(--muted); margin-bottom: 16px;">
                    You can switch out of developer error pages and preview the clean 500 error page instantly from your terminal:
                </p>
                <div style="background: #000; padding: 16px 24px; border-radius: 12px; font-family: 'Fira Code', monospace; color: #818cf8;">
                    php spp/spp.php env:mode prod
                </div>
            </div>
        </div>

        <!-- Tab Content 3: Request & Routing -->
        <div id="tab-request" class="tab-pane">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">🌍 HTTP Request Context</h2>
            <table class="info-table">
                <tr>
                    <th>Request URL</th>
                    <td><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></td>
                </tr>
                <tr>
                    <th>Request Method</th>
                    <td><?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'GET'); ?></td>
                </tr>
                <tr>
                    <th>Application Context</th>
                    <td><?php echo htmlspecialchars(\SPP\Scheduler::getContext() ?: 'default'); ?></td>
                </tr>
                <tr>
                    <th>Query Parameters ($_GET)</th>
                    <td><?php echo htmlspecialchars(json_encode($_GET, JSON_PRETTY_PRINT)); ?></td>
                </tr>
                <tr>
                    <th>Body Parameters ($_POST)</th>
                    <td><?php echo htmlspecialchars(json_encode($_POST, JSON_PRETTY_PRINT)); ?></td>
                </tr>
            </table>
        </div>

        <!-- Tab Content 4: Environment & CLI Switch -->
        <div id="tab-env" class="tab-pane">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">🛡️ SPP Architecture & Environment</h2>
            <table class="info-table" style="margin-bottom: 32px;">
                <tr>
                    <th>SPP Framework Version</th>
                    <td><?php echo htmlspecialchars(defined('SPP_VER') ? SPP_VER : '0.5'); ?></td>
                </tr>
                <tr>
                    <th>PHP Runtime Version</th>
                    <td><?php echo htmlspecialchars(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <th>SAPI Mode</th>
                    <td><?php echo htmlspecialchars(PHP_SAPI); ?></td>
                </tr>
                <tr>
                    <th>Debug Mode (SPP_DEBUG)</th>
                    <td><span style="color: #34d399; font-weight: bold;">TRUE (Developer Ignition Mode)</span></td>
                </tr>
            </table>

            <div class="cli-guide-card">
                <h3 class="cli-heading">🚀 Instant Environment Switching via CLI</h3>
                <p style="color: var(--muted); margin-bottom: 16px; line-height: 1.8;">
                    The SPP framework provides a powerful CLI command to toggle your global environment error reporting mode seamlessly. It automatically updates <code>spp/etc/global-settings.yml</code>.
                </p>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="background: #000; padding: 16px 24px; border-radius: 12px; font-family: 'Fira Code', monospace; color: #a7f3d0;">
                        <div style="color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">// Switch to Developer Ignition Mode (SPP_DEBUG = true)</div>
                        php spp/spp.php env:mode dev
                    </div>
                    <div style="background: #000; padding: 16px 24px; border-radius: 12px; font-family: 'Fira Code', monospace; color: #f43f5e;">
                        <div style="color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">// Switch to Clean Production 500 Pages (SPP_DEBUG = false)</div>
                        php spp/spp.php env:mode prod
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vanilla JS Interactivity -->
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            // Find active button and pane
            const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => btn.getAttribute('onclick').includes(tabId));
            if (activeBtn) activeBtn.classList.add('active');
            
            const activePane = document.getElementById('tab-' + tabId);
            if (activePane) activePane.classList.add('active');
        }

        function selectFrame(index) {
            document.querySelectorAll('.frame-card').forEach(card => card.classList.remove('active'));
            document.querySelectorAll('.code-snippet-box-wrapper').forEach(snippet => snippet.style.display = 'none');

            const selectedCard = document.querySelectorAll('.frame-card')[index];
            if (selectedCard) selectedCard.classList.add('active');

            const selectedSnippet = document.getElementById('snippet-' + index);
            if (selectedSnippet) selectedSnippet.style.display = 'block';
        }

        function copyCommand() {
            const cmdText = document.getElementById('cmd-text').innerText;
            navigator.clipboard.writeText(cmdText).then(() => {
                const copyBtn = document.querySelector('.copy-btn');
                copyBtn.innerText = '✅ Copied!';
                setTimeout(() => copyBtn.innerText = '📋 Copy Command', 2000);
            });
        }
    </script>
</body>
</html>
