"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CronItem = exports.CronProvider = void 0;
const vscode = require("vscode");
class CronProvider {
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
            return Promise.resolve(this.getCronTasks());
        }
    }
    async getCronTasks() {
        const tasks = [];
        if (!this.workspaceRoot)
            return tasks;
        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php cron:list', { cwd: this.workspaceRoot }, (error, stdout, stderr) => {
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
exports.CronProvider = CronProvider;
class CronItem extends vscode.TreeItem {
    constructor(label, collapsibleState, schedule, descriptionText) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.schedule = schedule;
        this.descriptionText = descriptionText;
        const parts = [];
        if (schedule)
            parts.push(`Schedule: ${schedule}`);
        if (descriptionText)
            parts.push(descriptionText);
        this.tooltip = `Cron: ${label}${parts.length ? '\n' + parts.join('\n') : ''}`;
        this.description = schedule ? `[${schedule}] ${descriptionText || ''}`.trim() : descriptionText;
        this.contextValue = 'cron';
        this.iconPath = new vscode.ThemeIcon('clock');
    }
}
exports.CronItem = CronItem;
//# sourceMappingURL=CronProvider.js.map