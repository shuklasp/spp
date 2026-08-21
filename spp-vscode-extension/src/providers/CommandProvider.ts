import * as vscode from 'vscode';
import * as fs from 'fs';
import * as path from 'path';

export class CommandProvider implements vscode.TreeDataProvider<CommandItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<CommandItem | undefined | void> = new vscode.EventEmitter<CommandItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<CommandItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: CommandItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: CommandItem): Thenable<CommandItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve(this.getCommands());
        }
    }

    private async getCommands(): Promise<CommandItem[]> {
        const commands: CommandItem[] = [];
        if (!this.workspaceRoot) return commands;
        
        return new Promise((resolve) => {
            const { exec } = require('child_process');
            exec('php spp.php list', { cwd: this.workspaceRoot }, (error: any, stdout: string, stderr: string) => {
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

export class CommandItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly commandSignature: string,
        public readonly descriptionText?: string
    ) {
        super(label, collapsibleState);
        this.tooltip = `Run php spp.php ${commandSignature}\n\n${descriptionText || ''}`;
        this.description = descriptionText;
        this.contextValue = 'command';
        this.iconPath = new vscode.ThemeIcon('terminal');
    }
}
