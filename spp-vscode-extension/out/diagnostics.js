"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.refreshDiagnostics = refreshDiagnostics;
exports.subscribeToDocumentChanges = subscribeToDocumentChanges;
const vscode = require("vscode");
const path = require("path");
const fs = require("fs");
const os = require("os");
const cp = require("child_process");
function refreshDiagnostics(doc, diagnosticCollection) {
    if (doc.languageId !== 'php' && doc.languageId !== 'html') {
        return;
    }
    const workspaceFolders = vscode.workspace.workspaceFolders;
    if (!workspaceFolders)
        return;
    const workspaceRoot = workspaceFolders[0].uri.fsPath;
    // Write current document state to a temp file
    const content = doc.getText();
    const ext = path.extname(doc.fileName) || (doc.languageId === 'php' ? '.php' : '.html');
    const tmpPath = path.join(os.tmpdir(), `spp_lint_${Date.now()}${ext}`);
    // We need to pass the original filename to the linter for context (like whether it's a Command)
    // The SPP lint command currently takes --file=... which it reads from disk.
    // If we want it to know the original file path, we can either pass it as an argument, or
    // just let it lint the tmp file, but wait, the tmp file won't have 'Command' in the name!
    // We should rename the tmp file to include the original basename!
    const originalBasename = path.basename(doc.fileName);
    const contextTmpPath = path.join(os.tmpdir(), `spp_lint_${Date.now()}_${originalBasename}`);
    fs.writeFileSync(contextTmpPath, content);
    cp.exec(`php spp.php lint --file="${contextTmpPath}" --json`, { cwd: workspaceRoot }, (error, stdout, stderr) => {
        // Cleanup temp file
        if (fs.existsSync(contextTmpPath)) {
            fs.unlinkSync(contextTmpPath);
        }
        try {
            const rawOutput = stdout.trim();
            // In case there's any PHP notices before the JSON
            const jsonStart = rawOutput.indexOf('[');
            if (jsonStart === -1) {
                diagnosticCollection.set(doc.uri, []);
                return;
            }
            const jsonStr = rawOutput.substring(jsonStart);
            const issues = JSON.parse(jsonStr);
            const diagnostics = [];
            for (const issue of issues) {
                // Line is 1-indexed, VSCode is 0-indexed
                const line = Math.max(0, issue.line - 1);
                // Create a range for the entire line
                const lineText = doc.lineAt(line).text;
                const range = new vscode.Range(line, 0, line, lineText.length);
                const severity = issue.severity === 'Warning'
                    ? vscode.DiagnosticSeverity.Warning
                    : vscode.DiagnosticSeverity.Error;
                const diagnostic = new vscode.Diagnostic(range, issue.message, severity);
                diagnostic.code = issue.code;
                diagnostics.push(diagnostic);
            }
            diagnosticCollection.set(doc.uri, diagnostics);
        }
        catch (e) {
            console.error("SPP Linter failed to parse JSON:", e, stdout);
        }
    });
}
function subscribeToDocumentChanges(context, diagnosticCollection) {
    if (vscode.window.activeTextEditor) {
        refreshDiagnostics(vscode.window.activeTextEditor.document, diagnosticCollection);
    }
    context.subscriptions.push(vscode.window.onDidChangeActiveTextEditor(editor => {
        if (editor) {
            refreshDiagnostics(editor.document, diagnosticCollection);
        }
    }));
    // Debounce the change event so we don't spawn CLI commands on every keystroke immediately
    let timeout = undefined;
    context.subscriptions.push(vscode.workspace.onDidChangeTextDocument(e => {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(() => {
            refreshDiagnostics(e.document, diagnosticCollection);
        }, 500);
    }));
    context.subscriptions.push(vscode.workspace.onDidCloseTextDocument(doc => diagnosticCollection.delete(doc.uri)));
}
//# sourceMappingURL=diagnostics.js.map