<?php
namespace SPPMod\SPPMigrate\Ui;

class Dashboard {
    public static function render(): void {
        $context = \SPP\Scheduler::getContext();
        
        $assets = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $css = $assets . '/res/spp/css/spp.css';

        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>SPPMigrate Dashboard</title>
    <link rel="stylesheet" href="$css">
    <style>
        .migrate-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 15px;
            background: #0056b3;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #004494;
        }
        .result-box {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="migrate-container">
        <div class="header">
            <h2>SPPMigrate: Deploy to Remote Server</h2>
        </div>
        <div class="form-group">
            <label>Target URL</label>
            <input type="url" id="target_url" placeholder="https://live-server.com" value="">
        </div>
        <div class="form-group">
            <label>API Key</label>
            <input type="text" id="api_key" placeholder="Remote SPPMigrate API Key">
        </div>
        
        <button class="btn" onclick="checkDiff()">Check Differences</button>
        <button class="btn" onclick="deploy()" style="background: #28a745; display:none;" id="btnDeploy">Deploy Changes</button>
        
        <div class="result-box" id="resultBox"></div>
    </div>

    <script>
        async function checkDiff() {
            const url = document.getElementById('target_url').value;
            const key = document.getElementById('api_key').value;
            const box = document.getElementById('resultBox');
            
            box.style.display = 'block';
            box.innerHTML = 'Scanning local files...';

            try {
                // Here we would call a local API to initiate the scan and diff process against the remote server
                box.innerHTML = 'Scan complete. Diff functionality is WIP.';
            } catch (e) {
                box.innerHTML = '<span style="color:red">Error: ' + e.message + '</span>';
            }
        }

        async function deploy() {
            // Initiate deploy process
        }
    </script>
</body>
</html>
HTML;
    }
}
