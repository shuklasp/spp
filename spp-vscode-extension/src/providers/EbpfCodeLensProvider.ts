import * as vscode from 'vscode';

export class EbpfCodeLensProvider implements vscode.CodeLensProvider {
    private _onDidChangeCodeLenses: vscode.EventEmitter<void> = new vscode.EventEmitter<void>();
    public readonly onDidChangeCodeLenses: vscode.Event<void> = this._onDidChangeCodeLenses.event;

    public provideCodeLenses(document: vscode.TextDocument, token: vscode.CancellationToken): vscode.CodeLens[] | Thenable<vscode.CodeLens[]> {
        const codeLenses: vscode.CodeLens[] = [];
        const text = document.getText();

        // Looking for public methods
        const methodRegex = /public\s+function\s+([a-zA-Z0-9_]+)\s*\(/g;
        let match;

        while ((match = methodRegex.exec(text)) !== null) {
            const line = document.positionAt(match.index).line;
            const range = new vscode.Range(line, 0, line, match[0].length);
            const methodName = match[1];

            const lens = new vscode.CodeLens(range, {
                title: '$(microscope) Attach eBPF uprobe',
                command: 'spp.attachEbpf',
                arguments: [methodName]
            });
            codeLenses.push(lens);
        }

        return codeLenses;
    }
}
