import * as vscode from 'vscode';
import * as cp from 'child_process';

export class FeatureFlagItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly isEnabled: boolean,
        public readonly canaryPct: number,
        public readonly killSwitchTriggered: boolean,
        public readonly errors: number,
        public readonly threshold: number,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState
    ) {
        super(label, collapsibleState);
        this.tooltip = `Enabled: ${this.isEnabled}\nCanary: ${this.canaryPct}%\nErrors: ${this.errors}/${this.threshold}`;
        this.description = this.killSwitchTriggered ? 'KILL SWITCH TRIGGERED' : (this.isEnabled ? `ON (${this.canaryPct}%)` : 'OFF');
        
        if (this.killSwitchTriggered) {
            this.iconPath = new vscode.ThemeIcon('error', new vscode.ThemeColor('testing.iconFailed'));
        } else if (this.isEnabled) {
            this.iconPath = new vscode.ThemeIcon('pass', new vscode.ThemeColor('testing.iconPassed'));
        } else {
            this.iconPath = new vscode.ThemeIcon('circle-outline', new vscode.ThemeColor('disabledForeground'));
        }

        this.contextValue = 'featureFlag';
    }
}

export class FeatureFlagProvider implements vscode.TreeDataProvider<FeatureFlagItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<FeatureFlagItem | undefined | void> = new vscode.EventEmitter<FeatureFlagItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<FeatureFlagItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: FeatureFlagItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: FeatureFlagItem): Thenable<FeatureFlagItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        return new Promise((resolve) => {
            cp.exec('php spp.php feature:toggle --json', { cwd: this.workspaceRoot }, (error, stdout) => {
                if (error) {
                    vscode.window.showErrorMessage('Error fetching feature flags: ' + error.message);
                    return resolve([]);
                }
                
                try {
                    const parsed = JSON.parse(stdout);
                    if (parsed && parsed.flags) {
                        const items = parsed.flags.map((flag: any) => {
                            return new FeatureFlagItem(
                                flag.name,
                                flag.enabled,
                                flag.canary,
                                flag.killSwitchTriggered,
                                flag.errors,
                                flag.threshold,
                                vscode.TreeItemCollapsibleState.None
                            );
                        });
                        resolve(items);
                    } else {
                        resolve([]);
                    }
                } catch (e) {
                    resolve([]);
                }
            });
        });
    }
}
