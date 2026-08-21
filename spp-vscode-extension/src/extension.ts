import * as vscode from 'vscode';
import * as cp from 'child_process';
import * as path from 'path';
import * as fs from 'fs';
import { subscribeToDocumentChanges } from './diagnostics';
import { registerCodeActions } from './codeActions';
import { SPPCompletionItemProvider } from './completions';
import { SPPDefinitionProvider } from './definitionProvider';
import { activateWorkflowVisualizer } from './workflowVisualizer';
import { activateStubSynchronizer } from './stubSynchronizer';
import { AppStudioProvider } from './providers/AppStudioProvider';
import { EntityProvider } from './providers/EntityProvider';
import { CommandProvider, CommandItem } from './providers/CommandProvider';
import { PortalProvider } from './providers/PortalProvider';
import { ParikshakProvider } from './providers/ParikshakProvider';
import { XdbProvider } from './providers/XdbProvider';
import { ApiCodeLensProvider } from './providers/ApiCodeLensProvider';
import { ManPageCodeLensProvider } from './providers/ManPageCodeLensProvider';
import { activateDiagnosticsChannel } from './diagnosticsChannel';
import { activateServerStatusBar } from './serverStatusBar';
import { activateScaffolding } from './scaffolding';
import { SppTaskProvider } from './providers/SppTaskProvider';
import { SppHoverProvider } from './providers/SppHoverProvider';
import { SppDocumentLinkProvider } from './providers/DocumentLinkProvider';
import { ModuleProvider } from './providers/ModuleProvider';
import { RouteProvider } from './providers/RouteProvider';
import { EnvProvider } from './providers/EnvProvider';
import { CronProvider } from './providers/CronProvider';
import { QueueProvider } from './providers/QueueProvider';
import { CqrsProvider } from './providers/CqrsProvider';
import { CqrsCodeLensProvider } from './providers/CqrsCodeLensProvider';
import { FeatureFlagProvider } from './providers/FeatureFlagProvider';
import { TelemetryCodeLensProvider } from './providers/TelemetryCodeLensProvider';
import { XdbCodeLensProvider } from './providers/XdbCodeLensProvider';
import { EbpfCodeLensProvider } from './providers/EbpfCodeLensProvider';

export function activate(context: vscode.ExtensionContext) {
    console.log('SPP VS Code Extension is now active!');

    const workspaceRoot = vscode.workspace.workspaceFolders && vscode.workspace.workspaceFolders.length > 0 
        ? vscode.workspace.workspaceFolders[0].uri.fsPath : undefined;

    // FIX A3: Define runInTerminal BEFORE any commands reference it
    const runInTerminal = (commandText: string) => {
        const terminal = vscode.window.activeTerminal || vscode.window.createTerminal('SPP CLI');
        terminal.show();
        terminal.sendText(commandText);
    };

    // ── Hover Documentation Provider ─────────────────────────────
    const hoverProvider = new SppHoverProvider();
    vscode.languages.registerHoverProvider({ language: 'php', scheme: 'file' }, hoverProvider);

    // ── Document Link Provider (Ctrl+Click partials/streams) ─────
    const documentLinkProvider = new SppDocumentLinkProvider();
    vscode.languages.registerDocumentLinkProvider(
        [{ language: 'php', scheme: 'file' }, { language: 'html', scheme: 'file' }],
        documentLinkProvider
    );

    // ── VS Code Build Tasks ──────────────────────────────────────
    const taskProvider = vscode.tasks.registerTaskProvider(SppTaskProvider.CustomBuildScriptType, new SppTaskProvider());
    context.subscriptions.push(taskProvider);

    // ── Context Menu Scaffolding ─────────────────────────────────
    activateScaffolding(context);

    // ── Status Bar Server ────────────────────────────────────────
    activateServerStatusBar(context);

    // ── Native Diagnostics Channel ───────────────────────────────
    activateDiagnosticsChannel(context);

    // ── Code Lens Providers ──────────────────────────────────────
    const apiCodeLensProvider = new ApiCodeLensProvider();
    vscode.languages.registerCodeLensProvider({ language: 'php', scheme: 'file' }, apiCodeLensProvider);

    const manPageCodeLensProvider = new ManPageCodeLensProvider();
    vscode.languages.registerCodeLensProvider({ language: 'php', scheme: 'file' }, manPageCodeLensProvider);

    const cqrsCodeLensProvider = new CqrsCodeLensProvider();
    vscode.languages.registerCodeLensProvider({ language: 'php', scheme: 'file' }, cqrsCodeLensProvider);

    const telemetryCodeLensProvider = new TelemetryCodeLensProvider();
    vscode.languages.registerCodeLensProvider({ language: 'php', scheme: 'file' }, telemetryCodeLensProvider);

    const xdbCodeLensProvider = new XdbCodeLensProvider();
    vscode.languages.registerCodeLensProvider({ language: 'xml', scheme: 'file' }, xdbCodeLensProvider);

    const ebpfCodeLensProvider = new EbpfCodeLensProvider();
    vscode.languages.registerCodeLensProvider({ language: 'php', scheme: 'file' }, ebpfCodeLensProvider);

    // ── Native Parikshak Testing ─────────────────────────────────
    const parikshakProvider = new ParikshakProvider(workspaceRoot);
    parikshakProvider.activate(context);

    // ── SPP Workbench Activity Bar (Tree Views) ──────────────────
    const appStudioProvider = new AppStudioProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-app-studio', appStudioProvider);
    
    const entityProvider = new EntityProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-entities', entityProvider);
    
    const commandProvider = new CommandProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-commands', commandProvider);
    
    const xdbProvider = new XdbProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-xdb', xdbProvider);

    const portalProvider = new PortalProvider();
    vscode.window.registerTreeDataProvider('spp-portals', portalProvider);

    // ── Phase G: New Tree Views ──────────────────────────────────
    const moduleProvider = new ModuleProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-modules', moduleProvider);

    const routeProvider = new RouteProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-routes', routeProvider);

    const envProvider = new EnvProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-env', envProvider);

    const cronProvider = new CronProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-cron', cronProvider);

    const queueProvider = new QueueProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-queue', queueProvider);

    const cqrsProvider = new CqrsProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-cqrs', cqrsProvider);

    const featureFlagProvider = new FeatureFlagProvider(workspaceRoot);
    vscode.window.registerTreeDataProvider('spp-feature-flags', featureFlagProvider);

    // ── Tree View Commands ───────────────────────────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.runCommand', (node: CommandItem) => {
        if (node && node.commandSignature) {
            runInTerminal(`php spp.php ${node.commandSignature}`);
        }
    }));
    
    context.subscriptions.push(vscode.commands.registerCommand('spp.openAdminPortal', (target: string) => {
        const adminUrl = vscode.workspace.getConfiguration('spp').get<string>('adminUrl', 'http://localhost/sppadmin');
        const url = `${adminUrl}/#${target}`;
        vscode.commands.executeCommand('simpleBrowser.show', url);
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.generateManPage', (cmdName: string) => {
        runInTerminal(`php spp.php man:generate ${cmdName}`);
    }));

    // ── Diagnostics (Linter) ─────────────────────────────────────
    const sppDiagnostics = vscode.languages.createDiagnosticCollection('spp');
    context.subscriptions.push(sppDiagnostics);
    subscribeToDocumentChanges(context, sppDiagnostics);

    // ── Code Actions (Quick Fix) — FIX A4: register for both php AND html ──
    registerCodeActions(context);

    // ── Auto-Complete (IntelliSense) ─────────────────────────────
    context.subscriptions.push(
        vscode.languages.registerCompletionItemProvider(
            ['php', 'html'],
            new SPPCompletionItemProvider(),
            "'", '"'
        )
    );

    // ── Click-to-Navigate (Definition) ───────────────────────────
    context.subscriptions.push(
        vscode.languages.registerDefinitionProvider(
            ['php', 'html'],
            new SPPDefinitionProvider()
        )
    );

    // ── Workflow Visualizer (Webview) ────────────────────────────
    activateWorkflowVisualizer(context);

    // ── Stub Synchronizer (Find Stub) ────────────────────────────
    activateStubSynchronizer(context);

    // ── SPP: Deploy Push ─────────────────────────────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.deployPush', async () => {
        runInTerminal(`php spp.php deploy:push`);
    }));

    // ── SPP: CQRS Commands ───────────────────────────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.exploreHistory', async (modelClass?: string) => {
        if (!modelClass) {
            modelClass = await vscode.window.showInputBox({ prompt: 'Enter Entity Class (e.g., \\SPPMod\\SPPDB\\SPPUser)' });
        }
        if (!modelClass) return;

        const entityId = await vscode.window.showInputBox({ prompt: 'Enter Entity ID' });
        if (!entityId) return;

        cqrsProvider.setEntity(modelClass, entityId);
        vscode.commands.executeCommand('spp-cqrs.focus');
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.diffSnapshot', async (modelClass: string, entityId: string, revId: string) => {
        if (!workspaceRoot) return;
        const currentCmd = `php spp.php diff:history --type=${modelClass} --id=${entityId} --json`;
        const snapshotCmd = `php spp.php diff:compare --type=${modelClass} --id=${entityId} --rev=${revId} --json`;

        cp.exec(snapshotCmd, { cwd: workspaceRoot }, (err, stdout) => {
            if (err) {
                vscode.window.showErrorMessage(`Failed to fetch snapshot: ${err.message}`);
                return;
            }
            try {
                const snapshotJson = JSON.parse(stdout);
                if (snapshotJson.error) {
                    vscode.window.showErrorMessage(snapshotJson.error);
                    return;
                }
                
                // For simplicity, create a temp file for the snapshot and one for current state, then diff
                const snapshotTempPath = path.join(workspaceRoot, '.spp', 'temp', `snapshot_rev${revId}.json`);
                if (!fs.existsSync(path.dirname(snapshotTempPath))) fs.mkdirSync(path.dirname(snapshotTempPath), { recursive: true });
                fs.writeFileSync(snapshotTempPath, JSON.stringify(snapshotJson, null, 2));

                // Fetch current (we can just fetch revision latest or we could just run a simple DB fetch, for now we just write the snapshot)
                // Assuming we want to compare with another file, but let's just open the snapshot for now
                vscode.workspace.openTextDocument(vscode.Uri.file(snapshotTempPath)).then(doc => {
                    vscode.window.showTextDocument(doc);
                });

            } catch (e) {
                vscode.window.showErrorMessage('Failed to parse snapshot output.');
            }
        });
    }));

    // ── SPP: Ask Swarm AI ─────────────────────────────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.askAi', async () => {
        const editor = vscode.window.activeTextEditor;
        if (!editor) return;

        const selection = editor.selection;
        const text = editor.document.getText(selection);
        if (!text) {
            vscode.window.showInformationMessage('Please select some code to ask Swarm AI about.');
            return;
        }

        const prompt = await vscode.window.showInputBox({ prompt: 'Ask Swarm AI (e.g. Refactor this to use SPP best practices)' });
        if (!prompt) return;

        if (!workspaceRoot) return;

        // Save selected text to a temp file
        const tempInput = path.join(workspaceRoot, '.spp', 'temp', 'ai_input.txt');
        if (!fs.existsSync(path.dirname(tempInput))) fs.mkdirSync(path.dirname(tempInput), { recursive: true });
        fs.writeFileSync(tempInput, text);

        vscode.window.withProgress({
            location: vscode.ProgressLocation.Notification,
            title: "Asking SPP Swarm AI...",
            cancellable: false
        }, async (progress) => {
            return new Promise<void>((resolve) => {
                // Execute SPP AiPromptCommand (or similar) with the prompt
                cp.exec(`php spp.php ai:prompt "${prompt}" --file="${tempInput}"`, { cwd: workspaceRoot }, (err, stdout, stderr) => {
                    if (err) {
                        vscode.window.showErrorMessage(`Swarm AI Error: ${err.message}`);
                        resolve();
                        return;
                    }
                    
                    // Replace selection with AI output
                    editor.edit(editBuilder => {
                        editBuilder.replace(selection, stdout);
                    });
                    
                    resolve();
                });
            });
        });
    }));

    // ── SPP: Generate Man Pages (Dual-Format) ────────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.generateManPages', async () => {
        const commandName = await vscode.window.showInputBox({ prompt: 'Enter Command Signature (e.g., myapp:command)' });
        if (!commandName) return;
        const description = await vscode.window.showInputBox({ prompt: 'Enter brief description for the Man Page' });
        if (!description) return;

        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders) return;
        const rootPath = workspaceFolders[0].uri.fsPath;
        
        const safeName = commandName.replace(/:/g, '-');
        const mdPath = path.join(rootPath, 'docs', 'commands', `${safeName}.md`);
        const troffPath = path.join(rootPath, 'docs', 'commands', 'man', `${safeName}.1`);
        
        fs.mkdirSync(path.dirname(mdPath), { recursive: true });
        fs.mkdirSync(path.dirname(troffPath), { recursive: true });

        const mdTemplate = `## ${commandName}\n**Purpose**: ${description}\n\n### Synopsis\n\`\`\`bash\nphp spp.php ${commandName} [options]\n\`\`\`\n\n### Extended Usage\nDetailed explanation of behavior here.\n\n### Options Available\n- \`--force\`: Force execution.\n\n### Under the Hood Activity\nNo destructive filesystem writes.\n`;
        fs.writeFileSync(mdPath, mdTemplate);

        const troffTemplate = `.TH ${commandName.toUpperCase()} 1\n.SH NAME\n${commandName} \\- ${description}\n.SH SYNOPSIS\nphp spp.php ${commandName} [options]\n.SH DESCRIPTION\nDetailed explanation of behavior here.\n.SH OPTIONS\n\\fB\\-\\-force\\fR Force execution.\n.SH UNDER THE HOOD\nNo destructive filesystem writes.\n`;
        fs.writeFileSync(troffPath, troffTemplate);

        vscode.window.showInformationMessage(`Generated Man Pages for ${commandName}`);
    }));

    // ── SPP: Auto-Refactor to Enterprise Standards ───────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.autoRefactorEnterprise', async (uri?: vscode.Uri) => {
        const targetPath = uri ? uri.fsPath : vscode.window.activeTextEditor?.document.fileName;
        if (!targetPath) {
            vscode.window.showErrorMessage('No file selected for refactoring.');
            return;
        }
        const workspaceFolders = vscode.workspace.workspaceFolders;
        let finalPath = targetPath;
        if (workspaceFolders && targetPath.startsWith(workspaceFolders[0].uri.fsPath)) {
            finalPath = path.relative(workspaceFolders[0].uri.fsPath, targetPath);
        }
        runInTerminal(`php spp.php ai:refactor:enterprise ${finalPath}`);
    }));

    // ── SPP: Generate Tutorial Scaffold ──────────────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.generateTutorial', async () => {
        const topicName = await vscode.window.showInputBox({ prompt: 'Enter Tutorial Topic (e.g., SPP_View_Transitions)' });
        if (!topicName) return;

        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders) return;
        const rootPath = workspaceFolders[0].uri.fsPath;
        
        const safeName = topicName.replace(/\s+/g, '_').toLowerCase();
        const docsPath = path.join(rootPath, 'docs', 'tutorials', `${safeName}.md`);
        
        fs.mkdirSync(path.dirname(docsPath), { recursive: true });

        const template = `# ${topicName} Tutorial\n\n## Foundational Concepts\n<!-- Clearly explain what this feature is, why it exists in the framework, and what problem it solves in plain, accessible language for a novice. -->\n\n## Lifecycle & Architecture\n<!-- Provide an end-to-end breakdown of how this feature interacts with other core modules (such as Routing, ViewControllers, CQRS, Workflows, or CLI daemons). -->\n\n## Step-by-Step Tutorial\n<!-- Include clear, copy-pasteable examples and practical walkthroughs showing exactly how a novice developer configures, deploys, and interacts with the feature from scratch. -->\n\n### 1. Initial Setup\n...\n\n### 2. Execution\n...\n\n## Impact of Deletions/Modifications (If Applicable)\n<!-- If this modifies or deletes an older feature, explicitly document the legacy behavior, the rationale behind the change, and the exact migration or replacement steps required. -->\n`;
        fs.writeFileSync(docsPath, template);
        const doc = await vscode.workspace.openTextDocument(vscode.Uri.file(docsPath));
        await vscode.window.showTextDocument(doc);
        vscode.window.showInformationMessage(`Generated Novice-First Tutorial Scaffold for ${topicName}`);
    }));
    // ── SPP: Run Chaos Fuzzing ───────────────────────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.runFuzzing', async (uri?: vscode.Uri) => {
        let targetPath = uri ? uri.fsPath : vscode.window.activeTextEditor?.document.fileName;
        if (!targetPath) return;

        // Try to extract the class name from the file
        const content = fs.readFileSync(targetPath, 'utf8');
        const namespaceMatch = content.match(/namespace\s+([^;]+);/);
        const classMatch = content.match(/class\s+(\w+)\s+extends/);
        
        if (!classMatch) {
            vscode.window.showErrorMessage('Could not determine entity class from file.');
            return;
        }

        const className = classMatch[1];
        let fullClassName = className;
        if (namespaceMatch) {
            fullClassName = `\\${namespaceMatch[1]}\\${className}`;
        }

        runInTerminal(`php spp.php test:monkey --entity="${fullClassName}"`);
    }));

    // ── Phase 4: Ultimate Integration Commands ───────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.toggleFeature', async () => {
        const flagName = await vscode.window.showInputBox({ prompt: 'Enter Feature Flag Name' });
        if (!flagName) return;
        const enable = await vscode.window.showQuickPick(['true', 'false'], { placeHolder: 'Enable?' });
        if (!enable) return;
        const canary = await vscode.window.showInputBox({ prompt: 'Enter Canary Percentage (0-100)', value: '100' });
        if (!canary) return;

        runInTerminal(`php spp.php feature:toggle --flag="${flagName}" --enable="${enable}" --canary="${canary}"`);
        // Refresh tree view after small delay
        setTimeout(() => featureFlagProvider.refresh(), 1000);
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.refactorEnterprise', async (fsPath?: string) => {
        let targetPath = fsPath || vscode.window.activeTextEditor?.document.fileName;
        if (!targetPath) return;

        runInTerminal(`php spp.php ai:refactor-enterprise --file="${targetPath}"`);
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.rebuildXdbIndex', async (fsPath?: string) => {
        let targetPath = fsPath || vscode.window.activeTextEditor?.document.fileName;
        if (!targetPath) return;

        const tableName = path.basename(targetPath, '.xml');
        const columnName = await vscode.window.showInputBox({ prompt: 'Enter XML Node/Column to index' });
        if (!columnName) return;

        // E.g. php spp.php xdb:query (or a specific command we can inject if needed)
        // Since XdbBinaryIndexer is generally called from code, we could create an inline script or just use a generic command if it existed.
        // For now, let's just run tinker with the raw PHP snippet.
        runInTerminal(`php spp.php tinker --execute="\\SPPMod\\SPPXDB\\Index\\XdbBinaryIndexer::buildIndex('${path.dirname(targetPath)}', '${tableName}', '${columnName}', []);"`);
    }));

    // ── Phase 5: Subatomic Integration Commands ──────────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.attachEbpf', async (symbol?: string) => {
        let targetSymbol = symbol;
        if (!targetSymbol) {
            targetSymbol = await vscode.window.showInputBox({ prompt: 'Enter Symbol to attach eBPF uprobe' });
            if (!targetSymbol) return;
        }

        runInTerminal(`php spp.php ebpf:profile:attach --type=uprobe --symbol="${targetSymbol}"`);
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.monitorArena', async () => {
        const arena = await vscode.window.showInputBox({ prompt: 'Enter Arena Name', value: 'spp_cqrs_worker_arena' });
        if (!arena) return;

        runInTerminal(`php spp.php arena:memory:monitor --arena="${arena}"`);
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.exportAiManifest', async () => {
        runInTerminal(`php spp.php manifest:export`);
    }));

    // ── Phase 6: Cryptographic & Infrastructure Core ─────────────
    context.subscriptions.push(vscode.commands.registerCommand('spp.generateCryptoShard', async () => {
        runInTerminal(`php spp.php crypto:shard:generate`);
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.orchestrateDbPool', async () => {
        runInTerminal(`php spp.php db:pool:orchestrate`);
    }));

    context.subscriptions.push(vscode.commands.registerCommand('spp.compileOsKernel', async () => {
        runInTerminal(`php spp.php tinker --execute="\\SPPMod\\SPPOS\\KernelCompiler::compile(); echo \\"OS Kernel Cache Compiled Successfully.\\\\n\\";"`);
    }));

}

export function deactivate() {}
