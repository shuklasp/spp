"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.FeatureFlagProvider = exports.FeatureFlagItem = void 0;
const vscode = require("vscode");
const cp = require("child_process");
class FeatureFlagItem extends vscode.TreeItem {
    constructor(label, isEnabled, canaryPct, killSwitchTriggered, errors, threshold, collapsibleState) {
        super(label, collapsibleState);
        this.label = label;
        this.isEnabled = isEnabled;
        this.canaryPct = canaryPct;
        this.killSwitchTriggered = killSwitchTriggered;
        this.errors = errors;
        this.threshold = threshold;
        this.collapsibleState = collapsibleState;
        this.tooltip = `Enabled: ${this.isEnabled}\nCanary: ${this.canaryPct}%\nErrors: ${this.errors}/${this.threshold}`;
        this.description = this.killSwitchTriggered ? 'KILL SWITCH TRIGGERED' : (this.isEnabled ? `ON (${this.canaryPct}%)` : 'OFF');
        if (this.killSwitchTriggered) {
            this.iconPath = new vscode.ThemeIcon('error', new vscode.ThemeColor('testing.iconFailed'));
        }
        else if (this.isEnabled) {
            this.iconPath = new vscode.ThemeIcon('pass', new vscode.ThemeColor('testing.iconPassed'));
        }
        else {
            this.iconPath = new vscode.ThemeIcon('circle-outline', new vscode.ThemeColor('disabledForeground'));
        }
        this.contextValue = 'featureFlag';
    }
}
exports.FeatureFlagItem = FeatureFlagItem;
class FeatureFlagProvider {
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
        return new Promise((resolve) => {
            cp.exec('php spp.php feature:toggle --json', { cwd: this.workspaceRoot }, (error, stdout) => {
                if (error) {
                    vscode.window.showErrorMessage('Error fetching feature flags: ' + error.message);
                    return resolve([]);
                }
                try {
                    const parsed = JSON.parse(stdout);
                    if (parsed && parsed.flags) {
                        const items = parsed.flags.map((flag) => {
                            return new FeatureFlagItem(flag.name, flag.enabled, flag.canary, flag.killSwitchTriggered, flag.errors, flag.threshold, vscode.TreeItemCollapsibleState.None);
                        });
                        resolve(items);
                    }
                    else {
                        resolve([]);
                    }
                }
                catch (e) {
                    resolve([]);
                }
            });
        });
    }
}
exports.FeatureFlagProvider = FeatureFlagProvider;
//# sourceMappingURL=FeatureFlagProvider.js.map