import * as vscode from 'vscode';

export class ApiCodeLensProvider implements vscode.CodeLensProvider {
    private codeLenses: vscode.CodeLens[] = [];
    private regex: RegExp;
    private _onDidChangeCodeLenses: vscode.EventEmitter<void> = new vscode.EventEmitter<void>();
    public readonly onDidChangeCodeLenses: vscode.Event<void> = this._onDidChangeCodeLenses.event;

    constructor() {
        this.regex = /class\s+(\w+Controller)/g;
    }

    public provideCodeLenses(document: vscode.TextDocument, token: vscode.CancellationToken): vscode.CodeLens[] | Thenable<vscode.CodeLens[]> {
        if (vscode.workspace.getConfiguration("spp").get("enableCodeLens", true)) {
            this.codeLenses = [];
            const text = document.getText();
            let matches;
            
            while ((matches = this.regex.exec(text)) !== null) {
                const line = document.positionAt(matches.index).line;
                const indexOf = matches[0].indexOf(matches[1]);
                const position = new vscode.Position(line, indexOf);
                const range = document.getWordRangeAtPosition(position, new RegExp(this.regex));
                
                if (range) {
                    this.codeLenses.push(new vscode.CodeLens(range, {
                        title: "🚀 Explore in SPP API Explorer",
                        tooltip: "Open SPP API Explorer for this controller",
                        command: "spp.openAdminPortal",
                        arguments: ['http://localhost/spp/admin/index.php?view=api']
                    }));
                }
            }
            return this.codeLenses;
        }
        return [];
    }
}
