"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CqrsItem = exports.CqrsProvider = void 0;
const vscode = require("vscode");
const cp = require("child_process");
class CqrsProvider {
    constructor(workspaceRoot) {
        this.workspaceRoot = workspaceRoot;
        this._onDidChangeTreeData = new vscode.EventEmitter();
        this.onDidChangeTreeData = this._onDidChangeTreeData.event;
    }
    refresh() {
        this._onDidChangeTreeData.fire();
    }
    setEntity(modelClass, entityId) {
        this.modelClass = modelClass;
        this.entityId = entityId;
        this.refresh();
    }
    getTreeItem(element) {
        return element;
    }
    getChildren(element) {
        if (!this.workspaceRoot || !this.modelClass || !this.entityId) {
            return Promise.resolve([]);
        }
        if (element) {
            return Promise.resolve([]);
        }
        return new Promise((resolve) => {
            const cmd = `php spp.php diff:history --type=${this.modelClass} --id=${this.entityId} --json`;
            cp.exec(cmd, { cwd: this.workspaceRoot }, (err, stdout) => {
                if (err) {
                    vscode.window.showErrorMessage(`Failed to fetch history: ${err.message}`);
                    resolve([]);
                    return;
                }
                try {
                    const history = JSON.parse(stdout);
                    if (history.error) {
                        vscode.window.showErrorMessage(history.error);
                        resolve([]);
                        return;
                    }
                    if (!Array.isArray(history)) {
                        resolve([]);
                        return;
                    }
                    const items = history.map((h) => {
                        const item = new CqrsItem(`Rev ${h.id}`, vscode.TreeItemCollapsibleState.None, `Date: ${h.created_at} | User: ${h.user_id}`);
                        item.command = {
                            command: 'spp.diffSnapshot',
                            title: 'Diff Snapshot',
                            arguments: [this.modelClass, this.entityId, h.id]
                        };
                        return item;
                    });
                    resolve(items);
                }
                catch (e) {
                    vscode.window.showErrorMessage('Failed to parse diff:history output.');
                    resolve([]);
                }
            });
        });
    }
}
exports.CqrsProvider = CqrsProvider;
class CqrsItem extends vscode.TreeItem {
    constructor(label, collapsibleState, description) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.description = description;
        this.tooltip = this.description;
        this.iconPath = new vscode.ThemeIcon('history');
    }
}
exports.CqrsItem = CqrsItem;
//# sourceMappingURL=CqrsProvider.js.map