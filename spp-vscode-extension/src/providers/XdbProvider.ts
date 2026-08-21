import * as vscode from 'vscode';
import * as fs from 'fs';
import * as path from 'path';

export class XdbProvider implements vscode.TreeDataProvider<XdbItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<XdbItem | undefined | void> = new vscode.EventEmitter<XdbItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<XdbItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: XdbItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: XdbItem): Thenable<XdbItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            if (element.contextValue === 'db-folder') {
                return Promise.resolve(this.getFiles(element.resourcePath));
            }
            return Promise.resolve([]);
        } else {
            const xdbPath = path.join(this.workspaceRoot, 'spp', 'modules', 'spp', 'sppxdb', 'data');
            if (fs.existsSync(xdbPath)) {
                return Promise.resolve(this.getDatabases(xdbPath));
            }
            return Promise.resolve([]);
        }
    }

    private getDatabases(xdbPath: string): XdbItem[] {
        const dbs = fs.readdirSync(xdbPath).filter(f => fs.statSync(path.join(xdbPath, f)).isDirectory());
        return dbs.map(db => {
            const dbPath = path.join(xdbPath, db);
            return new XdbItem(db, vscode.TreeItemCollapsibleState.Collapsed, dbPath, 'db-folder');
        });
    }

    private getFiles(dirPath: string): XdbItem[] {
        const items: XdbItem[] = [];
        if (fs.existsSync(dirPath)) {
            const files = fs.readdirSync(dirPath);
            for (const file of files) {
                const fullPath = path.join(dirPath, file);
                const isDir = fs.statSync(fullPath).isDirectory();
                if (isDir) {
                    items.push(new XdbItem(file, vscode.TreeItemCollapsibleState.Collapsed, fullPath, 'db-folder'));
                } else if (file.endsWith('.xml')) {
                    const item = new XdbItem(file, vscode.TreeItemCollapsibleState.None, fullPath, 'xml-file');
                    item.command = {
                        command: 'vscode.open',
                        title: 'Open XML',
                        arguments: [vscode.Uri.file(fullPath)]
                    };
                    items.push(item);
                }
            }
        }
        return items;
    }
}

export class XdbItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly resourcePath: string,
        public readonly contextValue: string
    ) {
        super(label, collapsibleState);
        this.tooltip = this.resourcePath;
        if (contextValue === 'xml-file') {
            this.iconPath = vscode.ThemeIcon.File;
        } else if (contextValue === 'db-folder') {
            this.iconPath = new vscode.ThemeIcon('database');
        }
    }
}
