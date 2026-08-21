import * as vscode from 'vscode';

export class EnvProvider implements vscode.TreeDataProvider<EnvItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<EnvItem | undefined | void> = new vscode.EventEmitter<EnvItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<EnvItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: EnvItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: EnvItem): Thenable<EnvItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve(this.getEnvVars());
        }
    }

    private async getEnvVars(): Promise<EnvItem[]> {
        const envVars: EnvItem[] = [];
        if (!this.workspaceRoot) return envVars;

        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php env:list', { cwd: this.workspaceRoot }, (error: any, stdout: string, stderr: string) => {
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

export class EnvItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly value: string
    ) {
        super(label, collapsibleState);
        this.tooltip = `${label} = ${value}`;
        this.description = `= ${value}`;
        this.contextValue = 'envvar';
        this.iconPath = new vscode.ThemeIcon('symbol-variable');
    }
}
