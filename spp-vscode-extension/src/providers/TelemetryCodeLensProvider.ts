import * as vscode from 'vscode';
import * as fs from 'fs';

export class TelemetryCodeLensProvider implements vscode.CodeLensProvider {
    private _onDidChangeCodeLenses: vscode.EventEmitter<void> = new vscode.EventEmitter<void>();
    public readonly onDidChangeCodeLenses: vscode.Event<void> = this._onDidChangeCodeLenses.event;

    public provideCodeLenses(document: vscode.TextDocument, token: vscode.CancellationToken): vscode.CodeLens[] | Thenable<vscode.CodeLens[]> {
        const codeLenses: vscode.CodeLens[] = [];
        const text = document.getText();

        // Looking for classes extending Controller (e.g. ViewController, ResourceController, etc)
        const classRegex = /class\s+(\w+)\s+extends\s+(?:[\\\w]*Controller)/g;
        let match;

        while ((match = classRegex.exec(text)) !== null) {
            const line = document.positionAt(match.index).line;
            const range = new vscode.Range(line, 0, line, match[0].length);
            const className = match[1];

            // If it doesn't already contain W3CTraceContext
            if (!text.includes('W3CTraceContext::startSpan')) {
                const lens = new vscode.CodeLens(range, {
                    title: '$(hubot) Apply Enterprise Rules (W3CTraceContext)',
                    command: 'spp.refactorEnterprise',
                    arguments: [document.uri.fsPath]
                });
                codeLenses.push(lens);
            }
        }

        return codeLenses;
    }
}
