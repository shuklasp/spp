"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.RouteItem = exports.RouteProvider = void 0;
const vscode = require("vscode");
class RouteProvider {
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
            return Promise.resolve(this.getRoutes());
        }
    }
    async getRoutes() {
        const routes = [];
        if (!this.workspaceRoot)
            return routes;
        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php view:page:list', { cwd: this.workspaceRoot }, (error, stdout, stderr) => {
                if (error) {
                    resolve(routes);
                    return;
                }
                // Parse the output of php spp.php view:page:list
                // Expected format: "  GET|POST  /route/path      Description or handler"
                const lines = stdout.split('\n');
                for (const line of lines) {
                    const match = line.match(/^\s{2}(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS|ANY)(?:\|[\w|]+)?\s+(\/\S*)\s*(.*)$/i);
                    if (match) {
                        const method = match[1].toUpperCase();
                        const routePath = match[2];
                        const description = match[3].trim();
                        const item = new RouteItem(routePath, vscode.TreeItemCollapsibleState.None, method, description);
                        routes.push(item);
                    }
                }
                resolve(routes);
            });
        });
    }
}
exports.RouteProvider = RouteProvider;
class RouteItem extends vscode.TreeItem {
    constructor(label, collapsibleState, method, descriptionText) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.method = method;
        this.descriptionText = descriptionText;
        this.tooltip = `${method} ${label}${descriptionText ? '\n' + descriptionText : ''}`;
        this.description = `[${method}] ${descriptionText || ''}`.trim();
        this.contextValue = 'route';
        this.iconPath = new vscode.ThemeIcon('globe');
    }
}
exports.RouteItem = RouteItem;
//# sourceMappingURL=RouteProvider.js.map