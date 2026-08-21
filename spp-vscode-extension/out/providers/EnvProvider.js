"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EnvItem = exports.EnvProvider = void 0;
const vscode = require("vscode");
class EnvProvider {
    constructor(workspaceRoot) {
        this.workspaceRoot = workspaceRoot;
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
    }
    refresh() {
        this._onDidChangeTreeData.fire();
    }
    getTreeItem(element) {
        return element;
    }
    getChildren(element) {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }
        if (element) {
            return Promise.resolve([]);
        }
        else {
            return Promise.resolve(this.getEnvVars());
        }
    }
    async getEnvVars() {
        const envVars = [];
        if (!this.workspaceRoot)
            return envVars;
        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php env:list', { cwd: this.workspaceRoot }, (error, stdout, stderr) => {
                if (error) {
                    resolve(envVars);
                    return;
                }
                // Parse the output of php spp.php env:list
                // Expected format: "  KEY=value" or "  KEY      value"
                const lines = stdout.split('\n');
                for (const line of lines) {
                    // Match KEY=value format
                    const matchEquals = line.match(/^\s{2}([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/);
                    if (matchEquals) {
                        const key = matchEquals[1];
                        const value = matchEquals[2].trim();
                        const item = new EnvItem(key, vscode.TreeItemCollapsibleState.None, value);
                        envVars.push(item);
                        continue;
                    }
                    // Match "KEY      value" whitespace-separated format
                    const matchSpaced = line.match(/^\s{2}([A-Z_][A-Z0-9_]*)\s{2,}(.*)$/);
                    if (matchSpaced) {
                        const key = matchSpaced[1];
                        const value = matchSpaced[2].trim();
                        const item = new EnvItem(key, vscode.TreeItemCollapsibleState.None, value);
                        envVars.push(item);
                    }
                }
                resolve(envVars);
            });
        });
    }
}
exports.EnvProvider = EnvProvider;
class EnvItem extends vscode.TreeItem {
    constructor(label, collapsibleState, value) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.value = value;
        this.tooltip = `${label} = ${value}`;
        this.description = `= ${value}`;
        this.contextValue = 'envvar';
        this.iconPath = new vscode.ThemeIcon('symbol-variable');
    }
}
exports.EnvItem = EnvItem;
//# sourceMappingURL=EnvProvider.js.map