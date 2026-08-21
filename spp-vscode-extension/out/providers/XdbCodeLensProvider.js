"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.XdbCodeLensProvider = void 0;
const vscode = require("vscode");
const path = require("path");
class XdbCodeLensProvider {
    constructor() {
        this._onDidChangeCodeLenses = new vscode.EventEmitter();
        this.onDidChangeCodeLenses = this._onDidChangeCodeLenses.event;
    }
    provideCodeLenses(document, token) {
        const codeLenses = [];
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
exports.XdbCodeLensProvider = XdbCodeLensProvider;
//# sourceMappingURL=XdbCodeLensProvider.js.map