"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EntityItem = exports.EntityProvider = void 0;
const vscode = require("vscode");
const fs = require("fs");
const path = require("path");
class EntityProvider {
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
            return Promise.resolve(this.getEntities());
        }
    }
    getEntities() {
        const entities = [];
        if (!this.workspaceRoot)
            return entities;
        const srcPath = path.join(this.workspaceRoot, 'src');
        if (!fs.existsSync(srcPath))
            return entities;
        const findPhpFiles = (dir) => {
            const results = [];
            const list = fs.readdirSync(dir);
            list.forEach(file => {
                const fullPath = path.join(dir, file);
                const stat = fs.statSync(fullPath);
                if (stat && stat.isDirectory()) {
                    results.push(...findPhpFiles(fullPath));
                }
                else if (file.endsWith('.php')) {
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
exports.EntityProvider = EntityProvider;
class EntityItem extends vscode.TreeItem {
    constructor(label, collapsibleState, filePath) {
        super(label, collapsibleState);
        this.label = label;
        this.collapsibleState = collapsibleState;
        this.filePath = filePath;
        this.tooltip = this.filePath;
        this.iconPath = new vscode.ThemeIcon('database');
    }
}
exports.EntityItem = EntityItem;
//# sourceMappingURL=EntityProvider.js.map