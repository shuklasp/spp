import * as vscode from 'vscode';
import * as fs from 'fs';
import * as path from 'path';

export class EntityProvider implements vscode.TreeDataProvider<EntityItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<EntityItem | undefined | void> = new vscode.EventEmitter<EntityItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<EntityItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: EntityItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: EntityItem): Thenable<EntityItem[]> {
        if (!this.workspaceRoot) {
            return Promise.resolve([]);
        }

        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve(this.getEntities());
        }
    }

    private getEntities(): EntityItem[] {
        const entities: EntityItem[] = [];
        if (!this.workspaceRoot) return entities;
        
        const srcPath = path.join(this.workspaceRoot, 'src');
        if (!fs.existsSync(srcPath)) return entities;

        const findPhpFiles = (dir: string) => {
            const results: string[] = [];
            const list = fs.readdirSync(dir);
            list.forEach(file => {
                const fullPath = path.join(dir, file);
                const stat = fs.statSync(fullPath);
                if (stat && stat.isDirectory()) {
                    results.push(...findPhpFiles(fullPath));
                } else if (file.endsWith('.php')) {
                    results.push(fullPath);
                }
            });
            return results;
        };

        const phpFiles = findPhpFiles(srcPath);
        for (const file of phpFiles) {
            const content = fs.readFileSync(file, 'utf8');
            // Look for classes extending SPPEntity or Database
            if (content.includes('extends SPPEntity') || content.includes('extends \\SPP\\SPPEntity')) {
                const classNameMatch = /class\s+(\w+)\s+extends/.exec(content);
                if (classNameMatch) {
                    const item = new EntityItem(classNameMatch[1], vscode.TreeItemCollapsibleState.None, file);
                    item.command = {
                        command: 'vscode.open',
                        title: 'Open Entity',
                        arguments: [vscode.Uri.file(file)]
                    };
                    entities.push(item);
                }
            }
        }
        
        return entities;
    }
}

export class EntityItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly filePath: string
    ) {
        super(label, collapsibleState);
        this.tooltip = this.filePath;
        this.iconPath = new vscode.ThemeIcon('database');
    }
}
