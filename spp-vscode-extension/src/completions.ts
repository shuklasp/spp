import * as vscode from 'vscode';
import * as path from 'path';
import * as fs from 'fs';

export class SPPCompletionItemProvider implements vscode.CompletionItemProvider {
    public async provideCompletionItems(
        document: vscode.TextDocument,
        position: vscode.Position,
        token: vscode.CancellationToken,
        context: vscode.CompletionContext
    ): Promise<vscode.CompletionItem[]> {
        
        const linePrefix = document.lineAt(position).text.substring(0, position.character);
        
        const completionItems: vscode.CompletionItem[] = [];

        // 1. Partial & Stream auto-complete
        // Triggered by renderPartial(', renderStaticPartial(', @spppartial(', or stream('
        if (linePrefix.match(/(?:renderPartial|renderStaticPartial|@spppartial|stream)\s*\(\s*['"](?:partials\/|streams\/)?$/)) {
            const files = await vscode.workspace.findFiles('**/{partials,streams}/*.{html,php,js}', '**/node_modules/**');
            
            for (const uri of files) {
                // Determine if it's a stream or partial
                const fsPath = uri.fsPath;
                const isStream = fsPath.includes(`${path.sep}streams${path.sep}`);
                const dirName = isStream ? 'streams' : 'partials';
                const basename = path.basename(fsPath);

                const item = new vscode.CompletionItem(basename, vscode.CompletionItemKind.File);
                item.insertText = `${dirName}/${basename}`;
                item.detail = `SPP ${isStream ? 'Stream' : 'Partial'}: ${basename}`;
                completionItems.push(item);
            }
        }

        // 2. Workflow auto-complete
        // Triggered by applyTransition(' or transitionEntity(..., '
        if (linePrefix.match(/(?:applyTransition|transitionEntity)\s*\([^,]*(?:,\s*|)['"]$/)) {
            // Very naive workflow parsing: look for etc/apps/<app>/workflows/*.yml in workspace
            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (workspaceFolders) {
                const rootPath = workspaceFolders[0].uri.fsPath;
                const appsDir = path.join(rootPath, 'etc', 'apps');
                if (fs.existsSync(appsDir)) {
                    const apps = fs.readdirSync(appsDir);
                    for (const app of apps) {
                        const workflowsDir = path.join(appsDir, app, 'workflows');
                        if (fs.existsSync(workflowsDir)) {
                            const wFiles = fs.readdirSync(workflowsDir);
                            for (const wFile of wFiles) {
                                if (wFile.endsWith('.yml')) {
                                    const ymlContent = fs.readFileSync(path.join(workflowsDir, wFile), 'utf8');
                                    const transitionMatches = ymlContent.matchAll(/([a-zA-Z0-9_]+):\s*\n\s+from:/g);
                                    for (const match of transitionMatches) {
                                        const transitionName = match[1];
                                        const item = new vscode.CompletionItem(transitionName, vscode.CompletionItemKind.Event);
                                        item.detail = `Workflow Transition (App: ${app})`;
                                        completionItems.push(item);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. XDB Collection Autocomplete
        if (linePrefix.match(/Xdb(?:Database|Connection)::(?:query|get|insert|update)\s*\(\s*['"]$/)) {
            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (workspaceFolders) {
                const xdbPath = path.join(workspaceFolders[0].uri.fsPath, 'spp', 'modules', 'spp', 'sppxdb', 'data');
                if (fs.existsSync(xdbPath)) {
                    const dbs = fs.readdirSync(xdbPath);
                    for (const db of dbs) {
                        const dbPath = path.join(xdbPath, db);
                        if (fs.statSync(dbPath).isDirectory()) {
                            const item = new vscode.CompletionItem(db, vscode.CompletionItemKind.Folder);
                            item.detail = `SPP XDB Database Collection`;
                            completionItems.push(item);
                        }
                    }
                }
            }
        }

        // 4. Workflow STATE auto-complete (Phase C4)
        // Triggered by getWorkflowState, or in workflow YAML context
        if (linePrefix.match(/(?:canTransition|getWorkflowState)\s*\(\s*['"]$/)) {
            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (workspaceFolders) {
                const rootPath = workspaceFolders[0].uri.fsPath;
                const appsDir = path.join(rootPath, 'etc', 'apps');
                if (fs.existsSync(appsDir)) {
                    const apps = fs.readdirSync(appsDir);
                    for (const app of apps) {
                        const workflowsDir = path.join(appsDir, app, 'workflows');
                        if (fs.existsSync(workflowsDir)) {
                            const wFiles = fs.readdirSync(workflowsDir);
                            for (const wFile of wFiles) {
                                if (wFile.endsWith('.yml')) {
                                    const ymlContent = fs.readFileSync(path.join(workflowsDir, wFile), 'utf8');
                                    // Parse states from YAML
                                    const stateMatches = ymlContent.matchAll(/^\s+-\s+([a-zA-Z0-9_]+)\s*$/gm);
                                    for (const match of stateMatches) {
                                        const stateName = match[1];
                                        const item = new vscode.CompletionItem(stateName, vscode.CompletionItemKind.EnumMember);
                                        item.detail = `Workflow State (App: ${app})`;
                                        completionItems.push(item);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 5. Event Name auto-complete (Phase C5)
        // Triggered by fireEvent(' or triggerHook('
        if (linePrefix.match(/(?:fireEvent|triggerHook)\s*\(\s*['"]$/)) {
            const knownEvents = [
                'workflow.before_transition', 'workflow.after_transition',
                'workflow:before_transition', 'workflow:after_transition',
                'entity.created', 'entity.updated', 'entity.deleted',
                'user.login', 'user.logout', 'user.registered',
                'cache.cleared', 'cache.warmed',
                'deploy.started', 'deploy.completed', 'deploy.failed',
                'module.installed', 'module.uninstalled', 'module.enabled', 'module.disabled'
            ];
            for (const eventName of knownEvents) {
                const item = new vscode.CompletionItem(eventName, vscode.CompletionItemKind.Event);
                item.detail = 'SPP Event';
                completionItems.push(item);
            }
        }

        // 6. Entity Name auto-complete (Phase C6)
        // Triggered by SPPEntity::find(' or new *Entity
        if (linePrefix.match(/(?:SPPEntity::find|::where)\s*\(\s*['"]$/)) {
            const workspaceFolders = vscode.workspace.workspaceFolders;
            if (workspaceFolders) {
                const srcDir = path.join(workspaceFolders[0].uri.fsPath, 'src');
                if (fs.existsSync(srcDir)) {
                    const findEntities = (dir: string) => {
                        const results: string[] = [];
                        try {
                            const items = fs.readdirSync(dir);
                            for (const item of items) {
                                const fullPath = path.join(dir, item);
                                const stat = fs.statSync(fullPath);
                                if (stat.isDirectory()) {
                                    results.push(...findEntities(fullPath));
                                } else if (item.endsWith('.php')) {
                                    const content = fs.readFileSync(fullPath, 'utf8');
                                    if (content.includes('extends SPPEntity') || content.includes('extends \\SPP\\SPPEntity')) {
                                        const classMatch = /class\s+(\w+)\s+extends/.exec(content);
                                        if (classMatch) {
                                            results.push(classMatch[1]);
                                        }
                                    }
                                }
                            }
                        } catch (e) { /* skip */ }
                        return results;
                    };
                    const entities = findEntities(srcDir);
                    for (const entity of entities) {
                        const item = new vscode.CompletionItem(entity, vscode.CompletionItemKind.Class);
                        item.detail = 'SPP Entity';
                        completionItems.push(item);
                    }
                }
            }
        }

        return completionItems;
    }
}
