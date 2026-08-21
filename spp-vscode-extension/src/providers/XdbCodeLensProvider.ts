import * as vscode from 'vscode';
import * as path from 'path';

export class XdbCodeLensProvider implements vscode.CodeLensProvider {
    private _onDidChangeCodeLenses: vscode.EventEmitter<void> = new vscode.EventEmitter<void>();
    public readonly onDidChangeCodeLenses: vscode.Event<void> = this._onDidChangeCodeLenses.event;

    public provideCodeLenses(document: vscode.TextDocument, token: vscode.CancellationToken): vscode.CodeLens[] | Thenable<vscode.CodeLens[]> {
        const codeLenses: vscode.CodeLens[] = [];
        
        // Only provide if it's an XML file inside xdb/
        if (document.fileName.includes(path.sep + 'xdb' + path.sep) && document.fileName.endsWith('.xml')) {
            const range = new vscode.Range(0, 0, 0, 0);
            const lens = new vscode.CodeLens(range, {
                title: '$(database) Rebuild O(log N) Binary Index',
                command: 'spp.rebuildXdbIndex',
                arguments: [document.uri.fsPath]
            });
            codeLenses.push(lens);
        }

        return codeLenses;
    }
}
