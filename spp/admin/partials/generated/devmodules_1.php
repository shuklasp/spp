<?php
// Extracted from DevModulesCommand.php
echo "
        <div class='config-path-banner' style='font-size: 0.75rem; color: #888; margin-bottom: 15px; padding: 8px 12px; background: rgba(0,0,0,0.05); border-radius: 6px; border-left: 3px solid #007bff;'>
            <span style='opacity: 0.7; text-transform: uppercase; font-weight: bold; font-size: 0.65rem; margin-right: 8px;'>Effective Config:</span> 
            <code style='color: #007bff; word-break: break-all;'>$yamlPath</code>
            <input type='hidden' id='setup-modname' value='$modname'>
            <input type='hidden' id='setup-appname' value='$appname'>
        </div>
        
        <div class='tabs spp-tabs' style='margin-bottom:15px; border-bottom:1px solid #ddd; display:flex; gap:10px;'>
            <button id='tab-interactive' class='tab-btn active' onclick=\"admin.switchSetupTab('interactive')\" style='padding:8px 15px; border:none; background:none; cursor:pointer; border-bottom:2px solid #007bff;'>Interactive</button>
            <button id='tab-yaml' class='tab-btn' onclick=\"admin.switchSetupTab('yaml')\" style='padding:8px 15px; border:none; background:none; cursor:pointer;'>YAML (Raw)</button>
        </div>
        
        <div id='setup-pane-container' class='tab-content-container' style='min-height: 400px;'>
            <div id='setup-pane-interactive' class='setup-pane active'>
                $formHtml
            </div>
            <div id='setup-pane-yaml' class='setup-pane' style='display:none;'>
                <p class='help-text' style='margin-bottom:10px; color:#666; font-size: 0.85rem;'>Directly edit the <code>config.yml</code> file for this module.</p>
                <textarea id='raw-config-editor' class='code-editor' style='width:100%; height:350px; font-family:monospace; padding:10px; border:1px solid #ccc; border-radius:4px;'>$yamlContent</textarea>
                <input type='hidden' id='raw-config-format' value='yml'>
            </div>
        </div>
    ";
