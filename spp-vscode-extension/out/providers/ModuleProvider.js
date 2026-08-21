"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.ModuleItem = exports.ModuleProvider = void 0;
const vscode = require("vscode");
class ModuleProvider {
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
            return Promise.resolve(this.getModules());
        }
    }
    async getModules() {
        const modules = [];
        if (!this.workspaceRoot)
            return modules;
        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php module:list', { cwd: this.workspaceRoot }, (error, stdout, stderr) => {
                if (error) {
                    resolve(modules);
                    return;
                }
                // Parse the output of php spp.php module:list
                // Expected format: "  ModuleName      enabled|disabled  Description"
                const lines = stdout.split('\n');
                for (const line of lines) {
                    const match = line.match(/^\s{2}(\S+)\s+(enabled|disabled)\s*(.*)$/i);
                    if (match) {
                        const moduleName = match[1];
                        const status = match[2].toLowerCase();
                        const description = match[3].trim();
                        const item = new ModuleItem(moduleName, vscode.TreeItemCollapsibleState.None, status, description);
                        modules.push(item);
                    }
                }
                resolve(modules);
            });
        });
    }
}
exports.ModuleProvider = ModuleProvider;
class ModuleItem extends vscode.TreeItem {
    constructor(label, collapsibleState, status, descriptionText) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.status = status;
        this.descriptionText = descriptionText;
        this.tooltip = `Module: ${label}\nStatus: ${status}${descriptionText ? '\n' + descriptionText : ''}`;
        this.description = descriptionText || status;
        this.contextValue = status === 'enabled' ? 'module' : 'module-disabled';
        this.iconPath = new vscode.ThemeIcon(status === 'enabled' ? 'extensions' : 'extensions-disabled');
    }
}
exports.ModuleItem = ModuleItem;
//# sourceMappingURL=ModuleProvider.js.map