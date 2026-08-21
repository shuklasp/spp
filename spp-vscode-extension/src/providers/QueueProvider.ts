import * as vscode from 'vscode';

export class QueueProvider implements vscode.TreeDataProvider<QueueItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<QueueItem | undefined | void> = new vscode.EventEmitter<QueueItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<QueueItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: QueueItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: QueueItem): Thenable<QueueItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve(this.getQueueJobs());
        }
    }

    private async getQueueJobs(): Promise<QueueItem[]> {
        const jobs: QueueItem[] = [];
        if (!this.workspaceRoot) return jobs;

        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php queue:list', { cwd: this.workspaceRoot }, (error: any, stdout: string, stderr: string) => {
                if (error) {
                    resolve(jobs);
                    return;
                }

                // Parse the output of php spp.php queue:list
                // Expected format: "  JobName      status      Description"
                const lines = stdout.split('\n');

                for (const line of lines) {
                    const match = line.match(/^\s{2}(\S+)\s{2,}(\S+)\s{2,}(.*)$/);
                    if (match) {
                        const jobName = match[1];
                        const status = match[2].trim();
                        const description = match[3].trim();
                        const item = new QueueItem(jobName, vscode.TreeItemCollapsibleState.None, status, description);
                        jobs.push(item);
                        continue;
                    }

                    // Fallback: simpler two-column format "  JobName      Description"
                    const matchSimple = line.match(/^\s{2}([a-z0-9:\-_\\]+)\s{2,}(.*)$/i);
                    if (matchSimple) {
                        const jobName = matchSimple[1];
                        const description = matchSimple[2].trim();
                        const item = new QueueItem(jobName, vscode.TreeItemCollapsibleState.None, undefined, description);
                        jobs.push(item);
                    }
                }

                resolve(jobs);
            });
        });
    }
}

export class QueueItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly status?: string,
        public readonly descriptionText?: string
    ) {
        super(label, collapsibleState);
        const parts: string[] = [];
        if (status) parts.push(`Status: ${status}`);
        if (descriptionText) parts.push(descriptionText);
        this.tooltip = `Queue Job: ${label}${parts.length ? '\n' + parts.join('\n') : ''}`;
        this.description = status ? `[${status}] ${descriptionText || ''}`.trim() : descriptionText;
        this.contextValue = 'queuejob';
        this.iconPath = new vscode.ThemeIcon('tasklist');
    }
}
