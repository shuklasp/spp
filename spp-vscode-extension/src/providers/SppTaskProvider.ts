import * as vscode from 'vscode';

export class SppTaskProvider implements vscode.TaskProvider {
    static CustomBuildScriptType = 'spp';
    private sppPromise: Thenable<vscode.Task[]> | undefined = undefined;

    public provideTasks(): Thenable<vscode.Task[]> | undefined {
        if (!this.sppPromise) {
            this.sppPromise = getSppTasks();
        }
        return this.sppPromise;
    }

    public resolveTask(_task: vscode.Task): vscode.Task | undefined {
        const definition = _task.definition;
        if (definition.type === SppTaskProvider.CustomBuildScriptType && definition.command) {
            const task = new vscode.Task(
                definition,
                vscode.TaskScope.Workspace,
                definition.command,
                SppTaskProvider.CustomBuildScriptType,
                new vscode.ShellExecution(`php spp.php ${definition.command}`)
            );
            task.group = vscode.TaskGroup.Build;
            return task;
        }
        return undefined;
    }
}

async function getSppTasks(): Promise<vscode.Task[]> {
    const workspaceRoot = vscode.workspace.workspaceFolders && vscode.workspace.workspaceFolders.length > 0 
        ? vscode.workspace.workspaceFolders[0].uri.fsPath : undefined;
    if (!workspaceRoot) {
        return [];
    }

    const tasks: vscode.Task[] = [];

    // The core orchestrated build/deploy tasks for SPP
    const coreCommands = [
        { name: 'sys:upgrade', desc: 'Sync DB Schema' },
        { name: 'sys:seed', desc: 'Run DB Seeders' },
        { name: 'migrate', desc: 'Run Migrations' },
        { name: 'view:cache', desc: 'Precompile AST Views' },
        { name: 'cache:clear', desc: 'Clear All Caches' },
        { name: 'test', desc: 'Run Parikshak Test Suite' },
        { name: 'deploy:push', desc: 'Deploy to Remote Server' },
        { name: 'doctor', desc: 'System Health Diagnostics' },
        { name: 'kernel:compile', desc: 'Compile Kernel Cache' },
        { name: 'drishyam:compile', desc: 'Pre-compile Templates' },
        // Phase K additions
        { name: 'polyglot:worker', desc: 'Start Polyglot Persistent Worker' },
        { name: 'polyglot:list', desc: 'List Polyglot Services' },
        { name: 'mesh:list', desc: 'List Mesh Passthrough Routes' },
        { name: 'serve:async', desc: 'Start Async Server Runtime' }
    ];

    coreCommands.forEach(cmd => {
        const task = new vscode.Task(
            { type: SppTaskProvider.CustomBuildScriptType, command: cmd.name },
            vscode.TaskScope.Workspace,
            cmd.name,
            SppTaskProvider.CustomBuildScriptType,
            new vscode.ShellExecution(`php spp.php ${cmd.name}`, { cwd: workspaceRoot })
        );
        task.group = vscode.TaskGroup.Build;
        task.detail = cmd.desc;
        tasks.push(task);
    });

    return tasks;
}
