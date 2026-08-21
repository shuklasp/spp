"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.TelemetryCodeLensProvider = void 0;
const vscode = require("vscode");
class TelemetryCodeLensProvider {
    constructor() {
        this._onDidChangeCodeLenses = new vscode.EventEmitter();
        this.onDidChangeCodeLenses = this._onDidChangeCodeLenses.event;
    }
    provideCodeLenses(document, token) {
        const codeLenses = [];
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
exports.TelemetryCodeLensProvider = TelemetryCodeLensProvider;
//# sourceMappingURL=TelemetryCodeLensProvider.js.map