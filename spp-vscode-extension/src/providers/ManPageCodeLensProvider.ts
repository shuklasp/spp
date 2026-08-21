import * as vscode from 'vscode';
import * as path from 'path';

export class ManPageCodeLensProvider implements vscode.CodeLensProvider {
    private codeLenses: vscode.CodeLens[] = [];
    private regex: RegExp;
    private _onDidChangeCodeLenses: vscode.EventEmitter<void> = new vscode.EventEmitter<void>();
    public readonly onDidChangeCodeLenses: vscode.Event<void> = this._onDidChangeCodeLenses.event;

    constructor() {
        this.regex = /class\s+(\w+Command)\s+extends/g;
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
                    // Try to guess the command name from the file or class name
                    // e.g. MakeCommand -> make
                    let cmdName = matches[1].replace('Command', '').toLowerCase();
                    // Or we could parse the `$signature = '...'`
                    const signatureRegex = /protected\s+\$signature\s*=\s*['"]([^'"]+)['"]/;
                    const sigMatch = signatureRegex.exec(text);
                    if (sigMatch) {
                        cmdName = sigMatch[1];
                        // If it has arguments like 'make:app {name}', just take the first part
                        cmdName = cmdName.split(' ')[0];
                    }

                    this.codeLenses.push(new vscode.CodeLens(range, {
                        title: "📖 Generate Dual-Format Man Page (MD & Unix)",
                        tooltip: "Runs php spp.php man:generate to create Markdown and Unix roff pages",
                        command: "spp.generateManPage",
                        arguments: [cmdName]
                    }));
                }
            }
            return this.codeLenses;
        }
        return [];
    }
}
