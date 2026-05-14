<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Enterprise Workspace - eventapp</title>
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
<body data-spp-navigation="spa">

    <div class="app-shell">
        <header class="header-bar">
            <div class="branding-block">
                <!-- Prominently rendered SPP Framework Branding Asset Logo routed straight through local isolated asset container -->
                <img src="assets/logo.jpg" alt="SPP Framework Brand Logo" class="app-logo" onerror="this.style.display='none'" />
                <div>
                    <h1>eventapp</h1>
                    <span style="font-size:0.85rem; color:var(--text-dim);">Powered by SPP Engine</span>
                </div>
            </div>
            <div class="badge">Mode: strtoupper($mode)</div>
        </header>

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