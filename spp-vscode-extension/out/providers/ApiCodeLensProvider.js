"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ApiCodeLensProvider = void 0;
const vscode = require("vscode");
class ApiCodeLensProvider {
    constructor() {
        this.codeLenses = [];
        this._onDidChangeCodeLenses = new vscode.EventEmitter();
        this.onDidChangeCodeLenses = this._onDidChangeCodeLenses.event;
        this.regex = /class\s+(\w+Controller)/g;
    }
    provideCodeLenses(document, token) {
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
exports.ApiCodeLensProvider = ApiCodeLensProvider;
//# sourceMappingURL=ApiCodeLensProvider.js.map