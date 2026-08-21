"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.activateDiagnosticsChannel = activateDiagnosticsChannel;
const vscode = require("vscode");
const fs = require("fs");
const path = require("path");
let sppOutputChannel;
let tailWatcher;
function activateDiagnosticsChannel(context) {
    sppOutputChannel = vscode.window.createOutputChannel('SPP Logs');
    context.subscriptions.push(sppOutputChannel);
    const workspaceRoot = vscode.workspace.workspaceFolders && vscode.workspace.workspaceFolders.length > 0
        ? vscode.workspace.workspaceFolders[0].uri.fsPath : undefined;
    context.subscriptions.push(vscode.commands.registerCommand('spp.tailLogs', () => {
        if (!workspaceRoot) {
            vscode.window.showErrorMessage('No workspace found to tail logs.');
            return;
        }
        const logPath = path.join(workspaceRoot, 'var', 'logs', 'spp_debug.log');
        if (!fs.existsSync(logPath)) {
            vscode.window.showInformationMessage(`Log file not found at ${logPath}`);
            return;
        }
        sppOutputChannel.show(true);
        sppOutputChannel.appendLine(`--- Tailing SPP Logs: ${logPath} ---`);
        // Close existing watcher if any
        if (tailWatcher) {
            tailWatcher.close();
        }
        let lastSize = fs.statSync(logPath).size;
        tailWatcher = fs.watch(logPath, (eventType, filename) => {
            if (eventType === 'change') {
                const stats = fs.statSync(logPath);
                if (stats.size > lastSize) {
                    const stream = fs.createReadStream(logPath, {
                        encoding: 'utf8',
                        start: lastSize,
                        end: stats.size
                    });
                    stream.on('data', (chunk) => {
                        sppOutputChannel.append(chunk.toString());
                    });
                    lastSize = stats.size;
                }
                else if (stats.size < lastSize) {
                    // Log rotated or truncated
                    lastSize = stats.size;
                    sppOutputChannel.appendLine('--- Log truncated/rotated ---');
                }
            }
        });
        context.subscriptions.push({ dispose: () => tailWatcher?.close() });
    }));
}
//# sourceMappingURL=diagnosticsChannel.js.map