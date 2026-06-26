<?php

namespace SPPMod\SPPDeploy\Ui;

class Dashboard
{
    public static function render(): void
    {
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f8; }
        .migrate-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .header {
            border-bottom: 2px solid #e1e4e8;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            background: #0366d6;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }
        .btn:hover { background: #005cc5; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #d73a49; }
        .btn-danger:hover { background: #cb2431; }
        
        .result-box {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            border-radius: 6px;
            display: none;
        }
        .diff-section { margin-top: 15px; }
        .diff-list { max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #eee; padding: 10px; border-radius: 4px; }
        .diff-item { font-family: monospace; font-size: 12px; margin-bottom: 4px; }
        .diff-create { color: #28a745; }
        .diff-update { color: #b08800; }
        .diff-delete { color: #d73a49; }
        
        /* 5-Strike Warning Modal */
        #strikeModal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); z-index: 1000;
        }
        .strike-content {
            background: #fff; width: 500px; margin: 100px auto; padding: 30px;
            border-radius: 8px; text-align: center; border-top: 5px solid #d73a49;
        }
        .strike-step { display: none; }
        .strike-step.active { display: block; }
        .strike-content h3 { color: #d73a49; margin-top: 0; }
    </style>
</head>
<body>
    <div class="migrate-container">
        <div class="header">
            <h2>SPPMigrate: Deployment Console</h2>
        </div>
        <div class="form-group">
            <label>Target Remote URL</label>
            <input type="url" id="target_url" placeholder="https://production-server.com" value="">
        </div>
        <div class="form-group">
            <label>Remote API Key</label>
            <input type="password" id="api_key" placeholder="Enter remote SPPMigrate API Key">
        </div>
        <div class="form-group">
            <label>Sync Mode</label>
            <select id="sync_mode">
                <option value="incremental">Incremental Sync (Safe - Ignores remote deletions)</option>
                <option value="full">Full State Mirror (Destructive - Applies remote deletions)</option>
            </select>
        </div>
        
        <button class="btn" onclick="checkDiff()">Check Differences</button>
        
        <div class="result-box" id="resultBox"></div>
    </div>

    <div id="strikeModal">
        <div class="strike-content">
            <h3>⚠️ CRITICAL WARNING ⚠️</h3>
            <div id="step1" class="strike-step active">
                <p>You are about to execute a <strong>Full Destructive Mirror</strong>.</p>
                <p>Files on the remote server that do not exist locally will be PERMANENTLY DELETED.</p>
                <button class="btn btn-danger" onclick="nextStrike(2)">I understand, continue</button>
                <button class="btn" onclick="cancelStrike()">Cancel</button>
            </div>
            <div id="step2" class="strike-step">
                <p>Step 2: Are you absolutely sure?</p>
                <label><input type="checkbox" id="chk1"> I confirm I want to overwrite the remote server.</label><br><br>
                <button class="btn btn-danger" onclick="nextStrike(3)">Next</button>
                <button class="btn" onclick="cancelStrike()">Cancel</button>
            </div>
            <div id="step3" class="strike-step">
                <p>Step 3: Auto-Backup Notice</p>
                <p>An auto-backup will be triggered on the remote server before changes apply.</p>
                <button class="btn btn-danger" onclick="nextStrike(4)">Acknowledge</button>
            </div>
            <div id="step4" class="strike-step">
                <p>Step 4: Final Confirmation</p>
                <p>Type <strong>CONFIRM</strong> in the box below to authorize this destructive sync.</p>
                <input type="text" id="confirmText" style="padding: 8px; width: 200px;"><br><br>
                <button class="btn btn-danger" onclick="nextStrike(5)">Verify & Deploy</button>
                <button class="btn" onclick="cancelStrike()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/lekhak/api/sppmigrate/sender';
        let currentDiffData = null;

        async function checkDiff() {
            const url = document.getElementById('target_url').value;
            const key = document.getElementById('api_key').value;
            const box = document.getElementById('resultBox');
            
            if (!url) {
                alert('Target URL is required');
                return;
            }

            box.style.display = 'block';
            box.innerHTML = 'Scanning local files and requesting diff from remote... please wait.';
            document.getElementById('resultBox').scrollIntoView({ behavior: 'smooth' });

            try {
                const response = await fetch(API_BASE + '/diff', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ target_url: url, api_key: key })
                });

                const data = await response.json();
                
                if (data.status === 'ok') {
                    currentDiffData = data.diff;
                    renderDiff(data.diff);
                } else {
                    box.innerHTML = '<span style="color:red">Error: ' + data.message + '</span>';
                }
            } catch (e) {
                box.innerHTML = '<span style="color:red">Exception: ' + e.message + '</span>';
            }
        }

        function renderDiff(diff) {
            const box = document.getElementById('resultBox');
            let html = '<h3>Deployment Plan</h3>';
            
            const totalChanges = diff.files.create.length + diff.files.update.length + diff.files.delete.length;
            
            if (totalChanges === 0) {
                html += '<p style="color:#28a745; font-weight:bold;">✅ Target is fully up to date!</p>';
                box.innerHTML = html;
                return;
            }

            html += `<p>Total changes: <strong>${totalChanges}</strong> files.</p>`;
            
            html += '<div class="diff-section"><div class="diff-list">';
            diff.files.create.forEach(f => { html += `<div class="diff-item diff-create">[NEW] ${f}</div>`; });
            diff.files.update.forEach(f => { html += `<div class="diff-item diff-update">[MOD] ${f}</div>`; });
            diff.files.delete.forEach(f => { html += `<div class="diff-item diff-delete">[DEL] ${f}</div>`; });
            html += '</div></div>';
            
            html += '<div style="margin-top: 20px;">';
            html += '<button class="btn btn-success" onclick="initiateDeploy()">Deploy Changes Now</button>';
            html += '</div>';
            
            box.innerHTML = html;
        }

        function initiateDeploy() {
            const mode = document.getElementById('sync_mode').value;
            const delCount = currentDiffData.files.delete.length;

            if (mode === 'full' || delCount > 50) {
                document.getElementById('strikeModal').style.display = 'block';
                showStrikeStep(1);
            } else {
                if (confirm('Deploy ' + document.getElementById('sync_mode').value + ' sync to remote server?')) {
                    executeDeploy();
                }
            }
        }

        function showStrikeStep(step) {
            document.querySelectorAll('.strike-step').forEach(el => el.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');
        }

        function nextStrike(step) {
            if (step === 3) {
                if (!document.getElementById('chk1').checked) {
                    alert('You must check the confirmation box.'); return;
                }
            }
            if (step === 5) {
                if (document.getElementById('confirmText').value !== 'CONFIRM') {
                    alert('You must type CONFIRM to proceed.'); return;
                }
                document.getElementById('strikeModal').style.display = 'none';
                executeDeploy();
                return;
            }
            showStrikeStep(step);
        }

        function cancelStrike() {
            document.getElementById('strikeModal').style.display = 'none';
            document.getElementById('chk1').checked = false;
            document.getElementById('confirmText').value = '';
        }

        async function executeDeploy() {
            const url = document.getElementById('target_url').value;
            const key = document.getElementById('api_key').value;
            const mode = document.getElementById('sync_mode').value;
            const box = document.getElementById('resultBox');
            
            box.innerHTML = '<h3>Deploying...</h3><p>Packaging local changes and transmitting payload to remote server. Do not close this window.</p>';

            try {
                const response = await fetch(API_BASE + '/deploy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        target_url: url, 
                        api_key: key,
                        mode: mode,
                        payload: currentDiffData
                    })
                });

                const data = await response.json();
                if (data.status === 'ok') {
                    box.innerHTML = '<h3 style="color:#28a745;">✅ Deployment Successful!</h3><p>' + data.message + '</p>';
                } else {
                    box.innerHTML = '<h3 style="color:#d73a49;">❌ Deployment Failed</h3><p>' + data.message + '</p>';
                }
            } catch (e) {
                box.innerHTML = '<h3 style="color:#d73a49;">❌ Exception</h3><p>' + e.message + '</p>';
            }
        }
    </script>
</body>
</html>
HTML;
    }
}
