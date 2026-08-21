import * as vscode from 'vscode';

export class RouteProvider implements vscode.TreeDataProvider<RouteItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<RouteItem | undefined | void> = new vscode.EventEmitter<RouteItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<RouteItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: RouteItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: RouteItem): Thenable<RouteItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve(this.getRoutes());
        }
    }

    private async getRoutes(): Promise<RouteItem[]> {
        const routes: RouteItem[] = [];
        if (!this.workspaceRoot) return routes;

        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php view:page:list', { cwd: this.workspaceRoot }, (error: any, stdout: string, stderr: string) => {
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

export class RouteItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly method: string,
        public readonly descriptionText?: string
    ) {
        super(label, collapsibleState);
        this.tooltip = `${method} ${label}${descriptionText ? '\n' + descriptionText : ''}`;
        this.description = `[${method}] ${descriptionText || ''}`.trim();
        this.contextValue = 'route';
        this.iconPath = new vscode.ThemeIcon('globe');
    }
}
