"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CommandItem = exports.CommandProvider = void 0;
const vscode = require("vscode");
class CommandProvider {
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
            return Promise.resolve(this.getCommands());
        }
    }
    async getCommands() {
        const commands = [];
        if (!this.workspaceRoot)
            return commands;
        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php list', { cwd: this.workspaceRoot }, (error, stdout, stderr) => {
                if (error) {
                    resolve(commands);
                    return;
                }
                // Parse the output of php spp.php list
                const lines = stdout.split('\n');
                let isCommandSection = false;
                for (const line of lines) {
                    // Check if we reached the commands section (usually indented or follows a header)
                    // The output format is: "  command:name      Description"
                    const match = line.match(/^\s{2}([a-z0-9:\-]+)\s+(.*)$/);
                    if (match) {
                        const cmdName = match[1];
                        const cmdDesc = match[2].trim();
                        const item = new CommandItem(cmdName, vscode.TreeItemCollapsibleState.None, cmdName, cmdDesc);
                        commands.push(item);
                    }
                }
                resolve(commands);
            });
        });
    }
}
exports.CommandProvider = CommandProvider;
class CommandItem extends vscode.TreeItem {
    constructor(label, collapsibleState, commandSignature, descriptionText) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.commandSignature = commandSignature;
        this.descriptionText = descriptionText;
        this.tooltip = `Run php spp.php ${commandSignature}\n\n${descriptionText || ''}`;
        this.description = descriptionText;
        this.contextValue = 'command';
        this.iconPath = new vscode.ThemeIcon('terminal');
    }
}
exports.CommandItem = CommandItem;
//# sourceMappingURL=CommandProvider.js.map