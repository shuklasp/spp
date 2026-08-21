"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.PortalItem = exports.PortalProvider = void 0;
const vscode = require("vscode");
class PortalProvider {
    constructor() {
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
        if (element) {
            return Promise.resolve([]);
        }
        else {
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
exports.PortalProvider = PortalProvider;
class PortalItem extends vscode.TreeItem {
    constructor(label, type, target, iconName) {
        super(label, vscode.TreeItemCollapsibleState.None);
        this.label = label;
        this.type = type;
        this.target = target;
        this.iconPath = new vscode.ThemeIcon(iconName);
        if (type === 'admin') {
            this.tooltip = `Open SPP Admin Portal: ${label}`;
            this.command = {
                command: 'spp.openAdminPortal',
                title: 'Open Portal',
                arguments: [this.target]
            };
        }
        else if (type === 'cmd') {
            this.tooltip = `Run php spp.php ${target}`;
            this.command = {
                command: 'spp.runCommand',
                title: 'Run Command',
                arguments: [{ commandSignature: target }] // Matching the shape expected by runCommand
            };
        }
    }
}
exports.PortalItem = PortalItem;
//# sourceMappingURL=PortalProvider.js.map