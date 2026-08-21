import * as vscode from 'vscode';

export class PortalProvider implements vscode.TreeDataProvider<PortalItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<PortalItem | undefined | void> = new vscode.EventEmitter<PortalItem | undefined | void>();
    readonly onDidChangeTreeData: vscode.Event<PortalItem | undefined | void> = this._onDidChangeTreeData.event;

    refresh(): void {
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: PortalItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: PortalItem): Thenable<PortalItem[]> {
        if (element) {
            return Promise.resolve([]);
        } else {
            return Promise.resolve([
                new PortalItem('Identity & Access', 'admin', 'identity', 'shield'),
                new PortalItem('Report Builder', 'admin', 'reports', 'graph'),
                new PortalItem('API Keys', 'admin', 'api_keys', 'key'),
                new PortalItem('API Explorer', 'admin', 'api', 'book'),
                new PortalItem('Mobile Studio', 'admin', 'mobile', 'device-mobile'),
                new PortalItem('InterDB Mesh', 'admin', 'interdb', 'database'),
                new PortalItem('XML Database', 'admin', 'xdb', 'server'),
                new PortalItem('Translations', 'admin', 'spplang', 'globe'),
                new PortalItem('Services (DI & AJAX)', 'admin', 'services', 'plug'),
                new PortalItem('Parikshak Testing', 'admin', 'parikshak', 'beaker'),
                new PortalItem('Deployment Lifecycle', 'admin', 'lifecycle', 'rocket'),
                new PortalItem('System Diagnostics', 'admin', 'system', 'server-environment'),
                new PortalItem('Clear Cache', 'cmd', 'cache:clear', 'trash'),
            ]);
        }
    }
}

export class PortalItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly type: 'admin' | 'cmd',
        public readonly target: string,
        iconName: string
    ) {
        super(label, vscode.TreeItemCollapsibleState.None);
        
        this.iconPath = new vscode.ThemeIcon(iconName);
        
        if (type === 'admin') {
            this.tooltip = `Open SPP Admin Portal: ${label}`;
            this.command = {
                command: 'spp.openAdminPortal',
                title: 'Open Portal',
                arguments: [this.target]
            };
        } else if (type === 'cmd') {
            this.tooltip = `Run php spp.php ${target}`;
            this.command = {
                command: 'spp.runCommand',
                title: 'Run Command',
                arguments: [{ commandSignature: target }] // Matching the shape expected by runCommand
            };
        }
    }
}
