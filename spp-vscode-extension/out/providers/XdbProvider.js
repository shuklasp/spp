"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.XdbItem = exports.XdbProvider = void 0;
const vscode = require("vscode");
const fs = require("fs");
const path = require("path");
class XdbProvider {
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
            if (element.contextValue === 'db-folder') {
                return Promise.resolve(this.getFiles(element.resourcePath));
            }
            return Promise.resolve([]);
        }
        else {
            const xdbPath = path.join(this.workspaceRoot, 'spp', 'modules', 'spp', 'sppxdb', 'data');
            if (fs.existsSync(xdbPath)) {
                return Promise.resolve(this.getDatabases(xdbPath));
            }
            return Promise.resolve([]);
        }
    }
    getDatabases(xdbPath) {
        const dbs = fs.readdirSync(xdbPath).filter(f => fs.statSync(path.join(xdbPath, f)).isDirectory());
        return dbs.map(db => {
            const dbPath = path.join(xdbPath, db);
            return new XdbItem(db, vscode.TreeItemCollapsibleState.Collapsed, dbPath, 'db-folder');
        });
    }
    getFiles(dirPath) {
        const items = [];
        if (fs.existsSync(dirPath)) {
            const files = fs.readdirSync(dirPath);
            for (const file of files) {
                const fullPath = path.join(dirPath, file);
                const isDir = fs.statSync(fullPath).isDirectory();
                if (isDir) {
                    items.push(new XdbItem(file, vscode.TreeItemCollapsibleState.Collapsed, fullPath, 'db-folder'));
                }
                else if (file.endsWith('.xml')) {
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
exports.XdbProvider = XdbProvider;
class XdbItem extends vscode.TreeItem {
    constructor(label, collapsibleState, resourcePath, contextValue) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.resourcePath = resourcePath;
        this.contextValue = contextValue;
        this.tooltip = this.resourcePath;
        if (contextValue === 'xml-file') {
            this.iconPath = vscode.ThemeIcon.File;
        }
        else if (contextValue === 'db-folder') {
            this.iconPath = new vscode.ThemeIcon('database');
        }
    }
}
exports.XdbItem = XdbItem;
//# sourceMappingURL=XdbProvider.js.map