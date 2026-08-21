"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.QueueItem = exports.QueueProvider = void 0;
const vscode = require("vscode");
class QueueProvider {
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
            return Promise.resolve(this.getQueueJobs());
        }
    }
    async getQueueJobs() {
        const jobs = [];
        if (!this.workspaceRoot)
            return jobs;
        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php queue:list', { cwd: this.workspaceRoot }, (error, stdout, stderr) => {
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
exports.QueueProvider = QueueProvider;
class QueueItem extends vscode.TreeItem {
    constructor(label, collapsibleState, status, descriptionText) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.status = status;
        this.descriptionText = descriptionText;
        const parts = [];
        if (status)
            parts.push(`Status: ${status}`);
        if (descriptionText)
            parts.push(descriptionText);
        this.tooltip = `Queue Job: ${label}${parts.length ? '\n' + parts.join('\n') : ''}`;
        this.description = status ? `[${status}] ${descriptionText || ''}`.trim() : descriptionText;
        this.contextValue = 'queuejob';
        this.iconPath = new vscode.ThemeIcon('tasklist');
    }
}
exports.QueueItem = QueueItem;
//# sourceMappingURL=QueueProvider.js.map