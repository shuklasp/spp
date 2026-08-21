"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.activateStubSynchronizer = activateStubSynchronizer;
const vscode = require("vscode");
const path = require("path");
const fs = require("fs");
function activateStubSynchronizer(context) {
    let disposable = vscode.commands.registerCommand('spp.findStub', async (uri) => {
        const targetPath = uri ? uri.fsPath : vscode.window.activeTextEditor?.document.fileName;
        if (!targetPath) {
            vscode.window.showErrorMessage('No file selected to find stub for.');
            return;
        }
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders) {
            vscode.window.showErrorMessage('No workspace open.');
            return;
        }
        const rootPath = workspaceFolders[0].uri.fsPath;
        const fileName = path.basename(targetPath); // e.g. MyController.php
        // Phase L: Intelligent Stub Mapping
        let stubTargetName = '';
        const lowerName = fileName.toLowerCase();
        // 1. Direct mappings based on exact suffixes
        if (lowerName.endsWith('controller.php')) {
            if (lowerName.includes('api'))
                stubTargetName = 'apicontroller.stub';
            else if (lowerName.includes('dashboard'))
                stubTargetName = 'dashboardcontroller.stub';
            else if (lowerName.includes('auth'))
                stubTargetName = 'authcontroller.stub';
            else if (lowerName.includes('home'))
                stubTargetName = 'homecontroller.stub';
            else
                stubTargetName = 'controller.stub'; // or scaffold_controller.stub depending on context
        }
        else if (lowerName.endsWith('service.php')) {
            stubTargetName = 'service.stub';
        }
        else if (lowerName.endsWith('command.php')) {
            stubTargetName = 'command.stub';
        }
        else if (lowerName.endsWith('livecomponent.php')) {
            stubTargetName = 'livecomponent.stub';
        }
        else if (lowerName.endsWith('middleware.php')) {
            stubTargetName = 'middleware.stub';
        }
        else if (lowerName.endsWith('model.php')) {
            stubTargetName = 'model.stub';
        }
        else if (lowerName.endsWith('eventhandler.php')) {
            stubTargetName = 'eventhandler.stub';
        }
        else if (lowerName.endsWith('authguard.php')) {
            stubTargetName = 'authguard.stub';
        }
        else {
            // 2. Language/Polyglot services
            if (lowerName.endsWith('.js') && targetPath.includes('services')) {
                stubTargetName = 'node_service.stub';
            }
            else if (lowerName.endsWith('.py') && targetPath.includes('services')) {
                stubTargetName = 'python_service.stub';
            }
            else if (lowerName.endsWith('.go') && targetPath.includes('services')) {
                stubTargetName = 'go_service.stub';
            }
            else if (lowerName.endsWith('.java') && targetPath.includes('services')) {
                stubTargetName = 'java_service.stub';
            }
            else {
                // 3. Fallback
                stubTargetName = fileName.replace(/\.[^/.]+$/, "").toLowerCase() + '.stub';
            }
        }
        // Search dirs
        const searchDirs = [
            path.join(rootPath, 'spp', 'commands', 'stubs')
        ];
        let foundStubPath = '';
        for (const dir of searchDirs) {
            if (fs.existsSync(dir)) {
                const stubPath = path.join(dir, stubTargetName);
                if (fs.existsSync(stubPath)) {
                    foundStubPath = stubPath;
                    break;
                }
            }
        }
        if (foundStubPath) {
            const doc = await vscode.workspace.openTextDocument(vscode.Uri.file(foundStubPath));
            await vscode.window.showTextDocument(doc, { viewColumn: vscode.ViewColumn.Beside });
            vscode.window.showInformationMessage(`Opened corresponding stub: ${stubTargetName}`);
        }
        else {
            vscode.window.showWarningMessage(`Could not find a corresponding stub for ${fileName}. Expected: ${stubTargetName}`);
        }
    });
    context.subscriptions.push(disposable);
}
//# sourceMappingURL=stubSynchronizer.js.map