"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.activateWorkflowVisualizer = activateWorkflowVisualizer;
const vscode = require("vscode");
const path = require("path");
function activateWorkflowVisualizer(context) {
    let disposable = vscode.commands.registerCommand('spp.visualizeWorkflow', () => {
        const editor = vscode.window.activeTextEditor;
        if (!editor || (!editor.document.fileName.endsWith('.yml') && !editor.document.fileName.endsWith('.yaml'))) {
            vscode.window.showErrorMessage('Please open a Workflow YAML file to visualize it.');
            return;
        }
        const yamlContent = editor.document.getText();
        // Write to temp file
        const fs = require('fs');
        const os = require('os');
        const tmpPath = path.join(os.tmpdir(), `spp_workflow_${Date.now()}.yml`);
        fs.writeFileSync(tmpPath, yamlContent);
        const workspaceRoot = vscode.workspace.workspaceFolders?.[0]?.uri.fsPath;
        if (!workspaceRoot) {
            vscode.window.showErrorMessage('SPP Workflow visualizer requires an open workspace.');
            return;
        }
        const panel = vscode.window.createWebviewPanel('workflowVisualizer', 'SPP Workflow Visualizer', vscode.ViewColumn.Beside, { enableScripts: true });
        panel.webview.html = `<!DOCTYPE html><html><body><h2>Loading workflow...</h2></body></html>`;
        const cp = require('child_process');
        cp.exec(`php spp.php workflow:dump --file="${tmpPath}" --format=mermaid`, { cwd: workspaceRoot }, (error, stdout, stderr) => {
            fs.unlinkSync(tmpPath); // Cleanup
            let mermaidGraph = 'stateDiagram-v2\n    [*] --> Error\n    Error: Failed to parse workflow';
            if (!error && stdout.includes('```mermaid')) {
                const parts = stdout.split('```mermaid');
                if (parts.length > 1) {
                    const mermaidParts = parts[1].split('```');
                    mermaidGraph = mermaidParts[0].trim();
                }
            }
            else {
                console.error("Workflow Dump Error:", stderr || stdout || error);
            }
            panel.webview.html = getWebviewContent(mermaidGraph);
        });
    });
    context.subscriptions.push(disposable);
}
function getWebviewContent(mermaidGraph) {
    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Visualizer</title>
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
        mermaid.initialize({ startOnLoad: true, theme: 'base' });
    </script>
    <style>
        body { background-color: white; padding: 20px; }
        .mermaid { display: flex; justify-content: center; }
    </style>
</head>
<body>
    <div class="mermaid">
        ${mermaidGraph}
    </div>
</body>
</html>`;
}
//# sourceMappingURL=workflowVisualizer.js.map