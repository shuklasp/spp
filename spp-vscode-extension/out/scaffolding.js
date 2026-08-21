"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.activateScaffolding = activateScaffolding;
const vscode = require("vscode");
const SCAFFOLDING_COMMANDS = [
    // Original 9 commands
    { command: 'spp.makeController', cliCommand: 'make:controller', prompt: 'Enter Controller Name (e.g. UserController)' },
    { command: 'spp.makeEntity', cliCommand: 'make:entity', prompt: 'Enter Entity Name (e.g. User)' },
    { command: 'spp.makePartial', cliCommand: 'make:partial', prompt: 'Enter Partial Name (e.g. user_row.html)' },
    { command: 'spp.makeStream', cliCommand: 'make:stream', prompt: 'Enter Stream Name (e.g. update.php)' },
    { command: 'spp.makeLiveComponent', cliCommand: 'make:livecomponent', prompt: 'Enter LiveComponent Name (e.g. Counter)' },
    { command: 'spp.makeService', cliCommand: 'make:service', prompt: 'Enter Service Name (e.g. PaymentService)' },
    { command: 'spp.makeCommand', cliCommand: 'make:command', prompt: 'Enter Command Name (e.g. sync:data)' },
    { command: 'spp.makeApp', cliCommand: 'make:app', prompt: 'Enter App Name (e.g. MyApp)' },
    { command: 'spp.makeModule', cliCommand: 'make:module', prompt: 'Enter Module Name (e.g. AuthModule)' },
    // Phase D1: 11 new context menu generators
    { command: 'spp.makeMigration', cliCommand: 'make:migration', prompt: 'Enter Migration Name (e.g. create_users_table)' },
    { command: 'spp.makeMiddleware', cliCommand: 'make:middleware', prompt: 'Enter Middleware Name (e.g. AuthMiddleware)' },
    { command: 'spp.makeForm', cliCommand: 'make:form', prompt: 'Enter Form Name (e.g. UserForm)' },
    { command: 'spp.makeEvent', cliCommand: 'make:event', prompt: 'Enter Event Name (e.g. UserRegistered)' },
    { command: 'spp.makeEventHandler', cliCommand: 'make:eventhand', prompt: 'Enter Event Handler Name (e.g. SendWelcomeEmail)' },
    { command: 'spp.makeModel', cliCommand: 'make:model', prompt: 'Enter Model Name (e.g. Product)' },
    { command: 'spp.makeSeeder', cliCommand: 'make:seeder', prompt: 'Enter Seeder Name (e.g. UserSeeder)' },
    { command: 'spp.makeScaffold', cliCommand: 'make:scaffold', prompt: 'Enter Entity Name for Full CRUD Scaffold (e.g. Product)' },
    { command: 'spp.makeUxComponent', cliCommand: 'make:ux-component', prompt: 'Enter UX Component Name (e.g. SearchBar)' },
    { command: 'spp.makeReactComponent', cliCommand: 'make:react-component', prompt: 'Enter React Component Name (e.g. Dashboard)' },
    { command: 'spp.makeVueComponent', cliCommand: 'make:vue-component', prompt: 'Enter Vue Component Name (e.g. TodoList)' },
    // Phase D2: 5 command palette only generators
    { command: 'spp.makeDeployment', cliCommand: 'make:deployment', prompt: 'Enter Deployment Config Name (e.g. production)' },
    { command: 'spp.makePolyglot', cliCommand: 'make:polyglot', prompt: 'Enter Polyglot Service Name (e.g. DataPipeline)' },
    { command: 'spp.makeBladeScaffold', cliCommand: 'make:blade-scaffold', prompt: 'Enter Entity Name for Blade Scaffold (e.g. Article)' },
    { command: 'spp.makeSppView', cliCommand: 'make:sppview', prompt: 'Enter View Name (e.g. dashboard)' },
    { command: 'spp.makeTwig', cliCommand: 'make:twig', prompt: 'Enter Twig Template Name (e.g. layout)' },
    // Phase J: XDB Scaffolding
    { command: 'spp.makeXdbMigration', cliCommand: 'xdb:make:migration', prompt: 'Enter XDB Migration Name (e.g. create_users_xml)' },
    { command: 'spp.makeXdbSeeder', cliCommand: 'xdb:make:seeder', prompt: 'Enter XDB Seeder Name (e.g. UsersSeeder)' },
    // Phase K: Polyglot Scaffolding
    { command: 'spp.makeNodeService', cliCommand: 'make:node-service', prompt: 'Enter Node.js Service Name' },
    { command: 'spp.makePythonService', cliCommand: 'make:python-service', prompt: 'Enter Python Service Name' },
    { command: 'spp.makeGoService', cliCommand: 'make:go-service', prompt: 'Enter Go Service Name' },
    { command: 'spp.makeJavaService', cliCommand: 'make:java-service', prompt: 'Enter Java Service Name' },
    { command: 'spp.makePolyglotPartial', cliCommand: 'make:polyglot-partial', prompt: 'Enter Polyglot Partial Name' },
    // Phase O: SPP Swarm AI
    { command: 'spp.makeAiWorkflow', cliCommand: 'make:ai-workflow', prompt: 'Enter AI Workflow Name' },
    // Phase 4: DAG Job
    { command: 'spp.makeDagJob', cliCommand: 'make:dag-job', prompt: 'Enter DAG Job Name (e.g. ProcessVideoJob)' },
];
function activateScaffolding(context) {
    // Use a shared terminal to avoid spawning dozens
    let scaffoldTerminal;
    const getTerminal = () => {
        if (scaffoldTerminal && !scaffoldTerminal.exitStatus) {
            return scaffoldTerminal;
        }
        scaffoldTerminal = vscode.window.createTerminal('SPP Scaffolding');
        return scaffoldTerminal;
    };
    // Listen for terminal close to clean up reference
    vscode.window.onDidCloseTerminal(t => {
        if (t === scaffoldTerminal) {
            scaffoldTerminal = undefined;
        }
    });
    for (const def of SCAFFOLDING_COMMANDS) {
        context.subscriptions.push(vscode.commands.registerCommand(def.command, async (uri) => {
            const name = await vscode.window.showInputBox({ prompt: def.prompt });
            if (name) {
                const terminal = getTerminal();
                terminal.show();
                terminal.sendText(`php spp.php ${def.cliCommand} ${name}`);
            }
        }));
    }
}
//# sourceMappingURL=scaffolding.js.map