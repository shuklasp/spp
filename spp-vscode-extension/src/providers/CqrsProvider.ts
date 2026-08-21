import * as vscode from 'vscode';
import * as cp from 'child_process';
import * as path from 'path';

export class CqrsProvider implements vscode.TreeDataProvider<CqrsItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<CqrsItem | undefined | void> = new vscode.EventEmitter<CqrsItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<CqrsItem | undefined | void> = this._onDidChangeTreeData.event;

    private modelClass: string | undefined;
    private entityId: string | undefined;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    setEntity(modelClass: string, entityId: string): void {
        this.modelClass = modelClass;
        this.entityId = entityId;
        this.refresh();
    }

    getTreeItem(element: CqrsItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: CqrsItem): Thenable<CqrsItem[]> {
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

                    const items = history.map((h: any) => {
                        const item = new CqrsItem(`Rev ${h.id}`, vscode.TreeItemCollapsibleState.None, `Date: ${h.created_at} | User: ${h.user_id}`);
                        item.command = {
                            command: 'spp.diffSnapshot',
                            title: 'Diff Snapshot',
                            arguments: [this.modelClass, this.entityId, h.id]
                        };
                        return item;
                    });
                    resolve(items);
                } catch (e) {
                    vscode.window.showErrorMessage('Failed to parse diff:history output.');
                    resolve([]);
                }
            });
        });
    }
}

export class CqrsItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly description: string
    ) {
        super(label, collapsibleState);
        this.tooltip = this.description;
        this.iconPath = new vscode.ThemeIcon('history');
    }
}
