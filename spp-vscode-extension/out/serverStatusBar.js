"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.activateServerStatusBar = activateServerStatusBar;
const vscode = require("vscode");
let statusBarItem;
let serverTerminal;
let isRunning = false;
function activateServerStatusBar(context) {
    statusBarItem = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Left, 100);
    statusBarItem.command = 'spp.toggleServer';
    context.subscriptions.push(statusBarItem);
    updateStatusBar();
    statusBarItem.show();
    context.subscriptions.push(vscode.commands.registerCommand('spp.toggleServer', () => {
        const workspaceRoot = vscode.workspace.workspaceFolders && vscode.workspace.workspaceFolders.length > 0
            ? vscode.workspace.workspaceFolders[0].uri.fsPath : undefined;
        if (!workspaceRoot) {
            vscode.window.showErrorMessage('No workspace folder open');
            return;
        }
        if (isRunning) {
            // Stop server
            if (serverTerminal) {
                serverTerminal.dispose();
                serverTerminal = undefined;
            }
            isRunning = false;
            vscode.window.showInformationMessage('SPP Server stopped');
        }
        else {
            // Start server
            serverTerminal = vscode.window.createTerminal('SPP Server');
            serverTerminal.show();
            serverTerminal.sendText('php spp.php serve');
            isRunning = true;
            vscode.window.showInformationMessage('SPP Server started');
        }
        updateStatusBar();
    }));
    // Listen for terminal close to update state
    vscode.window.onDidCloseTerminal(t => {
        if (t === serverTerminal) {
            isRunning = false;
            serverTerminal = undefined;
            updateStatusBar();
        }
    });
}
function updateStatusBar() {
    if (isRunning) {
        statusBarItem.text = '$(zap) SPP Server: Running';
        statusBarItem.tooltip = 'Click to stop the SPP Development Server';
        statusBarItem.backgroundColor = new vscode.ThemeColor('statusBarItem.warningBackground');
    }
    else {
        statusBarItem.text = '$(play) Start SPP Server';
        statusBarItem.tooltip = 'Click to start the SPP Development Server (php spp.php serve)';
        statusBarItem.backgroundColor = undefined;
    }
}
//# sourceMappingURL=serverStatusBar.js.map