import * as vscode from 'vscode';

export class CronProvider implements vscode.TreeDataProvider<CronItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<CronItem | undefined | void> = new vscode.EventEmitter<CronItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<CronItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: CronItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: CronItem): Thenable<CronItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve(this.getCronTasks());
        }
    }

    private async getCronTasks(): Promise<CronItem[]> {
        const tasks: CronItem[] = [];
        if (!this.workspaceRoot) return tasks;

        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php cron:list', { cwd: this.workspaceRoot }, (error: any, stdout: string, stderr: string) => {
                if (error) {
                    resolve(tasks);
                    return;
                }

                // Parse the output of php spp.php cron:list
                // Expected format: "  task:name      schedule      Description"
                const lines = stdout.split('\n');

                for (const line of lines) {
                    const match = line.match(/^\s{2}(\S+)\s{2,}(\S+(?:\s+\S+)*?)\s{2,}(.*)$/);
                    if (match) {
                        const taskName = match[1];
                        const schedule = match[2].trim();
                        const description = match[3].trim();
                        const item = new CronItem(taskName, vscode.TreeItemCollapsibleState.None, schedule, description);
                        tasks.push(item);
                        continue;
                    }

                    // Fallback: simpler two-column format "  task:name      Description"
                    const matchSimple = line.match(/^\s{2}([a-z0-9:\-_]+)\s{2,}(.*)$/i);
                    if (matchSimple) {
                        const taskName = matchSimple[1];
                        const description = matchSimple[2].trim();
                        const item = new CronItem(taskName, vscode.TreeItemCollapsibleState.None, undefined, description);
                        tasks.push(item);
                    }
                }

                resolve(tasks);
            });
        });
    }
}

export class CronItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly schedule?: string,
        public readonly descriptionText?: string
    ) {
        super(label, collapsibleState);
        const parts: string[] = [];
        if (schedule) parts.push(`Schedule: ${schedule}`);
        if (descriptionText) parts.push(descriptionText);
        this.tooltip = `Cron: ${label}${parts.length ? '\n' + parts.join('\n') : ''}`;
        this.description = schedule ? `[${schedule}] ${descriptionText || ''}`.trim() : descriptionText;
        this.contextValue = 'cron';
        this.iconPath = new vscode.ThemeIcon('clock');
    }
}
