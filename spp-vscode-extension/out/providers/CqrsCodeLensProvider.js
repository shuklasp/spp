"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CqrsCodeLensProvider = void 0;
const vscode = require("vscode");
class CqrsCodeLensProvider {
    provideCodeLenses(document, token) {
        const lenses = [];
        const text = document.getText();
        // Match SPP entity classes
        const classMatch = text.match(/class\s+(\w+)\s+extends\s+(SPPEntity|Model)/);
        if (classMatch) {
            const className = classMatch[1];
            const namespaceMatch = text.match(/namespace\s+([^;]+);/);
            let fullClassName = className;
            if (namespaceMatch) {
                fullClassName = `\\${namespaceMatch[1]}\\${className}`;
            }
            const range = new vscode.Range(document.positionAt(classMatch.index || 0), document.positionAt((classMatch.index || 0) + classMatch[0].length));
            const lens = new vscode.CodeLens(range, {
                title: 'View Snapshot History (CQRS)',
                command: 'spp.exploreHistory',
                arguments: [fullClassName]
            });
            lenses.push(lens);
        }
        return lenses;
    }
}
exports.CqrsCodeLensProvider = CqrsCodeLensProvider;
//# sourceMappingURL=CqrsCodeLensProvider.js.map