import * as vscode from 'vscode';
import * as fs from 'fs';
import * as path from 'path';

export class AppStudioProvider implements vscode.TreeDataProvider<TreeItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<TreeItem | undefined | void> = new vscode.EventEmitter<TreeItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<TreeItem | undefined | void> = this._onDidChangeTreeData.event;

    constructor(private workspaceRoot: string | undefined) {}

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: TreeItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: TreeItem): Thenable<TreeItem[]> {
        if (!this.workspaceRoot) {
            vscode.window.showInformationMessage('No workspace found');
            return Promise.resolve([]);
        }

        if (element) {
            // If it's an app, show its subdirectories (controllers, views, workflows)
            if (element.contextValue === 'app') {
                return Promise.resolve(this.getAppComponents(element.resourcePath));
            } else if (element.contextValue === 'app-folder') {
                return Promise.resolve(this.getFiles(element.resourcePath));
            }
            return Promise.resolve([]);
        } else {
            // Root level: show apps from etc/apps
            const appsPath = path.join(this.workspaceRoot, 'etc', 'apps');
            if (fs.existsSync(appsPath)) {
                return Promise.resolve(this.getApps(appsPath));
            } else {
                return Promise.resolve([]);
            }
        }
    }

    private getApps(appsPath: string): TreeItem[] {
        const apps = fs.readdirSync(appsPath).filter(f => fs.statSync(path.join(appsPath, f)).isDirectory());
        return apps.map(app => {
            const appPath = path.join(appsPath, app);
            return new TreeItem(app, vscode.TreeItemCollapsibleState.Collapsed, appPath, 'app');
        });
    }

    private getAppComponents(appPath: string): TreeItem[] {
        const components = [];
        
        // Typical structure in SPP might be scattered between etc/apps and src/, 
        // but for this demo we just look for 'workflows' in etc/apps/APP 
        // and link src/APP
        const workflowsPath = path.join(appPath, 'workflows');
        if (fs.existsSync(workflowsPath)) {
            components.push(new TreeItem('Workflows', vscode.TreeItemCollapsibleState.Collapsed, workflowsPath, 'app-folder'));
        }

        // Try to find the src/ directory for this app
        const appName = path.basename(appPath);
        if (this.workspaceRoot) {
            // Check src/appName or src/AppName
            const srcNames = [appName, appName.charAt(0).toUpperCase() + appName.slice(1)];
            for (const name of srcNames) {
                const srcPath = path.join(this.workspaceRoot, 'src', name);
                if (fs.existsSync(srcPath)) {
                    const dirs = fs.readdirSync(srcPath).filter(f => fs.statSync(path.join(srcPath, f)).isDirectory());
                    for (const dir of dirs) {
                        components.push(new TreeItem(dir, vscode.TreeItemCollapsibleState.Collapsed, path.join(srcPath, dir), 'app-folder'));
                    }
                    break;
                }
            }
        }

        return components;
    }

    private getFiles(dirPath: string): TreeItem[] {
        const items: TreeItem[] = [];
        if (fs.existsSync(dirPath)) {
            const files = fs.readdirSync(dirPath);
            for (const file of files) {
                const fullPath = path.join(dirPath, file);
                const isDir = fs.statSync(fullPath).isDirectory();
                if (isDir) {
                    items.push(new TreeItem(file, vscode.TreeItemCollapsibleState.Collapsed, fullPath, 'app-folder'));
                } else {
                    const item = new TreeItem(file, vscode.TreeItemCollapsibleState.None, fullPath, 'file');
                    item.command = {
                        command: 'vscode.open',
                        title: 'Open File',
                        arguments: [vscode.Uri.file(fullPath)]
                    };
                    items.push(item);
                }
            }
        }
        return items;
    }
}

export class TreeItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly resourcePath: string,
        public readonly contextValue: string
    ) {
        super(label, collapsibleState);
        this.tooltip = this.resourcePath;
        if (contextValue === 'file') {
            this.iconPath = vscode.ThemeIcon.File;
        } else if (contextValue === 'app-folder') {
            this.iconPath = vscode.ThemeIcon.Folder;
        } else if (contextValue === 'app') {
            this.iconPath = new vscode.ThemeIcon('window');
        }
    }
}
