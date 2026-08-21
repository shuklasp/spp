import * as vscode from 'vscode';
import * as cp from 'child_process';
import * as path from 'path';
import * as fs from 'fs';

export class SPPCodeActionProvider implements vscode.CodeActionProvider {
    public static readonly providedCodeActionKinds = [
        vscode.CodeActionKind.QuickFix
    ];

    public provideCodeActions(document: vscode.TextDocument, range: vscode.Range, context: vscode.CodeActionContext): vscode.CodeAction[] {
        const actions: vscode.CodeAction[] = [];
        for (const diagnostic of context.diagnostics) {
            if (diagnostic.code === 'spp_zero_inline_html') {
                const action = new vscode.CodeAction('Extract to SPP Partial', vscode.CodeActionKind.QuickFix);
                action.command = {
                    command: 'spp.extractPartial',
                    title: 'Extract to SPP Partial',
                    arguments: [document, diagnostic.range]
                };
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
            if (diagnostic.code === 'spp_cdn_asset') {
                const action = new vscode.CodeAction('Replace with Local SPP Admin Asset', vscode.CodeActionKind.QuickFix);
                action.edit = new vscode.WorkspaceEdit();
                action.edit.replace(document.uri, diagnostic.range, `<script src="<?= APP_BASE_URI ?>/sppadmin/js/htmx.min.js"></script>`);
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
            if (diagnostic.code === 'spp_anti_bypass') {
                const action = new vscode.CodeAction('Convert to SPP-UX Synthetic Event Directive', vscode.CodeActionKind.QuickFix);
                const originalText = document.getText(diagnostic.range);
                const newText = '@' + originalText.substring(2);
                action.edit = new vscode.WorkspaceEdit();
                action.edit.replace(document.uri, diagnostic.range, newText);
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
            if (diagnostic.code === 'spp_missing_deploy_mutex') {
                const action = new vscode.CodeAction('Wrap in Deploy Mutex Lock', vscode.CodeActionKind.QuickFix);
                action.edit = new vscode.WorkspaceEdit();
                action.edit.insert(document.uri, diagnostic.range.start, `// TODO: Wrap execute() logic in:\n// \\SPPMod\\SPPDeploy\\Deployer\\TargetConnection::acquireDeploymentLock();\n// try { ... } finally { \\SPPMod\\SPPDeploy\\Deployer\\TargetConnection::releaseDeploymentLock(); }\n`);
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
            // Phase B1: Quick Fix for missing CLI guard
            if (diagnostic.code === 'spp_missing_cli_guard') {
                const action = new vscode.CodeAction('Add isCLIOnly() Guard Method', vscode.CodeActionKind.QuickFix);
                action.edit = new vscode.WorkspaceEdit();
                // Find the line after the class opening brace to insert
                const classLine = diagnostic.range.start.line;
                const insertPos = new vscode.Position(classLine + 1, 0);
                action.edit.insert(document.uri, insertPos, `\n    public function isCLIOnly(): bool { return true; }\n`);
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
            // Phase B2: Quick Fix for DDL security
            if (diagnostic.code === 'spp_ddl_security') {
                const action = new vscode.CodeAction('Use escapeIdentifier() for DDL Safety', vscode.CodeActionKind.QuickFix);
                action.edit = new vscode.WorkspaceEdit();
                action.edit.insert(document.uri, diagnostic.range.start, `// TODO: Replace raw variable interpolation with:\n// \$this->escapeIdentifier(\$variableName)\n`);
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
            // Phase B3: Quick Fix for dual event bus
            if (diagnostic.code === 'spp_dual_event_bus') {
                const action = new vscode.CodeAction('Add Missing triggerHook() Call', vscode.CodeActionKind.QuickFix);
                action.edit = new vscode.WorkspaceEdit();
                // Insert a triggerHook call right after the fireEvent line
                const fireEventLine = diagnostic.range.end.line;
                const insertPos = new vscode.Position(fireEventLine + 1, 0);
                action.edit.insert(document.uri, insertPos, `        \\SPP\\SPPEvent::triggerHook('workflow:after_transition', $data);\n`);
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
            // Phase B4: Quick Fix for missing form guard
            if (diagnostic.code === 'spp_missing_form_guard') {
                const action = new vscode.CodeAction('Add SPPWorkflowGuardValidator', vscode.CodeActionKind.QuickFix);
                action.edit = new vscode.WorkspaceEdit();
                action.edit.insert(document.uri, diagnostic.range.start, `// TODO: Add to your validation rules:\n// 'workflow_guard' => 'SPPWorkflowGuardValidator'\n`);
                action.diagnostics = [diagnostic];
                action.isPreferred = true;
                actions.push(action);
            }
        }
        return actions;
    }
}

export function registerCodeActions(context: vscode.ExtensionContext) {
    // FIX A4: Register for BOTH php AND html
    context.subscriptions.push(
        vscode.languages.registerCodeActionsProvider('php', new SPPCodeActionProvider(), {
            providedCodeActionKinds: SPPCodeActionProvider.providedCodeActionKinds
        })
    );
    context.subscriptions.push(
        vscode.languages.registerCodeActionsProvider('html', new SPPCodeActionProvider(), {
            providedCodeActionKinds: SPPCodeActionProvider.providedCodeActionKinds
        })
    );

    context.subscriptions.push(
        vscode.commands.registerCommand('spp.extractPartial', async (document: vscode.TextDocument, range: vscode.Range) => {
            const partialName = await vscode.window.showInputBox({ prompt: 'Enter new partial name (e.g., alert.html)' });
            if (!partialName) return;

            const htmlContent = document.getText(range).replace(/^['"]|['"]$/g, '');
            
            const fileDir = path.dirname(document.uri.fsPath);
            const partialsDir = path.join(fileDir, 'partials');
            if (!fs.existsSync(partialsDir)) {
                fs.mkdirSync(partialsDir, { recursive: true });
            }
            
            const partialPath = path.join(partialsDir, partialName);
            fs.writeFileSync(partialPath, htmlContent, 'utf8');

            const edit = new vscode.WorkspaceEdit();
            edit.replace(document.uri, range, `$this->renderPartial('partials/${partialName}')`);
            await vscode.workspace.applyEdit(edit);
            
            vscode.window.showInformationMessage(`Extracted HTML to ${partialName}`);
        })
    );
}
