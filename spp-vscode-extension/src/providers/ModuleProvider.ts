import * as vscode from 'vscode';

export class ModuleProvider implements vscode.TreeDataProvider<ModuleItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<ModuleItem | undefined | void> = new vscode.EventEmitter<ModuleItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<ModuleItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: ModuleItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: ModuleItem): Thenable<ModuleItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve(this.getModules());
        }
    }

    private async getModules(): Promise<ModuleItem[]> {
        const modules: ModuleItem[] = [];
        if (!this.workspaceRoot) return modules;

        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php module:list', { cwd: this.workspaceRoot }, (error: any, stdout: string, stderr: string) => {
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
                        const status = match[2].toLowerCase() as 'enabled' | 'disabled';
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

export class ModuleItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly status: 'enabled' | 'disabled',
        public readonly descriptionText?: string
    ) {
        super(label, collapsibleState);
        this.tooltip = `Module: ${label}\nStatus: ${status}${descriptionText ? '\n' + descriptionText : ''}`;
        this.description = descriptionText || status;
        this.contextValue = status === 'enabled' ? 'module' : 'module-disabled';
        this.iconPath = new vscode.ThemeIcon(status === 'enabled' ? 'extensions' : 'extensions-disabled');
    }
}
