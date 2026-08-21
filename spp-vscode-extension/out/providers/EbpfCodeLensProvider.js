"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EbpfCodeLensProvider = void 0;
const vscode = require("vscode");
class EbpfCodeLensProvider {
    constructor() {
        this._onDidChangeCodeLenses = new vscode.EventEmitter();
        this.onDidChangeCodeLenses = this._onDidChangeCodeLenses.event;
    }
    provideCodeLenses(document, token) {
        const codeLenses = [];
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
exports.EbpfCodeLensProvider = EbpfCodeLensProvider;
//# sourceMappingURL=EbpfCodeLensProvider.js.map