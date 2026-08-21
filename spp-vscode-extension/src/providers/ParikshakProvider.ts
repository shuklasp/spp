import * as vscode from 'vscode';
import * as fs from 'fs';
import * as path from 'path';
import { exec } from 'child_process';

export class ParikshakProvider {
    private controller: vscode.TestController;

    constructor(private workspaceRoot: string | undefined) {
        this.controller = vscode.tests.createTestController('sppParikshak', 'SPP Parikshak');
        
        // When the user clicks the "Refresh" button in the Test Explorer
        this.controller.resolveHandler = async test => {
            if (!test) {
                await this.discoverTests();
            }
        };

        // When the user clicks "Run"
        this.controller.createRunProfile('Run Tests', vscode.TestRunProfileKind.Run, (request, token) => {
            this.runHandler(request, token);
        });
    }

    public activate(context: vscode.ExtensionContext) {
        context.subscriptions.push(this.controller);
        this.discoverTests(); // initial discovery
    }

    private async discoverTests() {
        if (!this.workspaceRoot) return;
        const testsDir = path.join(this.workspaceRoot, 'tests');
        if (!fs.existsSync(testsDir)) return;

        // Clear existing tests
        this.controller.items.replace([]);

        const findPhpFiles = (dir: string): string[] => {
            const results: string[] = [];
            const list = fs.readdirSync(dir);
            list.forEach(file => {
                const fullPath = path.join(dir, file);
                const stat = fs.statSync(fullPath);
                if (stat && stat.isDirectory()) {
                    results.push(...findPhpFiles(fullPath));
                } else if (file.endsWith('.php')) {
                    results.push(fullPath);
                }
            });
            return results;
        };

        const testFiles = findPhpFiles(testsDir);
        for (const file of testFiles) {
            const content = fs.readFileSync(file, 'utf8');
            // Try to extract class name (e.g. class MyTest)
            const classMatch = /class\s+(\w+Test)/i.exec(content);
            const className = classMatch ? classMatch[1] : path.basename(file, '.php');

            const fileUri = vscode.Uri.file(file);
            const classItem = this.controller.createTestItem(className, className, fileUri);

            // Find methods (e.g. public function testSomething)
            const methodRegex = /public\s+function\s+(test\w+)/gi;
            let methodMatch;
            let foundMethods = false;
            while ((methodMatch = methodRegex.exec(content)) !== null) {
                foundMethods = true;
                const methodName = methodMatch[1];
                const methodId = `${className}::${methodName}`;
                // Get line number roughly
                const line = content.substring(0, methodMatch.index).split('\n').length - 1;
                
                const testItem = this.controller.createTestItem(methodId, methodName, fileUri);
                testItem.range = new vscode.Range(line, 0, line, methodMatch[0].length);
                
                classItem.children.add(testItem);
            }

            // Also support Pest-style test('description', function() { ... })
            const pestRegex = /test\(['"](.*?)['"]/gi;
            let pestMatch;
            while ((pestMatch = pestRegex.exec(content)) !== null) {
                foundMethods = true;
                const description = pestMatch[1];
                const methodId = `${path.basename(file)}::${description}`;
                const line = content.substring(0, pestMatch.index).split('\n').length - 1;

                const testItem = this.controller.createTestItem(methodId, description, fileUri);
                testItem.range = new vscode.Range(line, 0, line, pestMatch[0].length);
                
                classItem.children.add(testItem);
            }

            if (foundMethods) {
                this.controller.items.add(classItem);
            }
        }
    }

    private async runHandler(request: vscode.TestRunRequest, token: vscode.CancellationToken) {
        const run = this.controller.createTestRun(request);

        if (!this.workspaceRoot) {
            run.end();
            return;
        }

        // Determine which tests to run. For simplicity in this demo, we'll run the full suite
        // and parse the output, matching them to our discovered items.
        // If request.include is populated, we could pass specific files to `php spp.php test`, 
        // but `php spp.php test` might only run everything if it doesn't take arguments.
        
        // Enqueue all items that are part of the run
        const runQueue: vscode.TestItem[] = [];
        const queueItem = (item: vscode.TestItem) => {
            if (request.include && !request.include.includes(item)) {
                // If this is a class, check if any of its children are included
                let childIncluded = false;
                item.children.forEach(c => { if (request.include!.includes(c)) childIncluded = true; });
                if (!childIncluded) return;
            }
            if (request.exclude?.includes(item)) return;

            run.enqueued(item);
            item.children.forEach(queueItem);
            if (item.children.size === 0) {
                runQueue.push(item);
            }
        };

        this.controller.items.forEach(queueItem);
        runQueue.forEach(item => run.started(item));

        // Execute php spp.php test
        exec('php spp.php test', { cwd: this.workspaceRoot }, (error, stdout, stderr) => {
            const output = stdout + '\n' + stderr;
            const lines = output.split('\n');

            let currentErrorLines: string[] = [];
            let lastFailedTest: vscode.TestItem | null = null;

            for (let i = 0; i < lines.length; i++) {
                const line = lines[i].trim();
                
                // Parse PASS
                if (line.startsWith('✔ PASS')) {
                    // Extract the class and method, e.g. "✔ PASS SPP\Tests\Core\DataProviderTest::testAddition"
                    const match = line.match(/PASS\s+(.*?::.*?)(?:\s|$)/);
                    if (match) {
                        const fullSignature = match[1];
                        // signature might be "SPP\Tests\Core\DataProviderTest::testAddition"
                        // Our items are named "DataProviderTest::testAddition" or "test.PestStyleTest.php::addition works"
                        const id = this.matchSignatureToTestId(fullSignature, runQueue);
                        if (id) {
                            run.passed(id, 1);
                        }
                    }
                } 
                // Parse FAIL
                else if (line.startsWith('✘ FAIL')) {
                    const match = line.match(/FAIL\s+(.*?::.*?)(?:\s|$)/);
                    if (match) {
                        const fullSignature = match[1];
                        lastFailedTest = this.matchSignatureToTestId(fullSignature, runQueue);
                        currentErrorLines = [];
                    }
                }
                // Capture error details for the failed test (until next PASS/FAIL or empty line)
                else if (lastFailedTest && line !== '') {
                    currentErrorLines.push(line);
                } 
                // End of error block
                else if (lastFailedTest && line === '') {
                    if (currentErrorLines.length > 0) {
                        const msg = new vscode.TestMessage(currentErrorLines.join('\n'));
                        run.failed(lastFailedTest, msg, 1);
                    } else {
                        run.failed(lastFailedTest, new vscode.TestMessage("Failed"), 1);
                    }
                    lastFailedTest = null;
                    currentErrorLines = [];
                }
                
                run.appendOutput(line + '\r\n');
            }

            // Flush any remaining error block
            if (lastFailedTest) {
                run.failed(lastFailedTest, new vscode.TestMessage(currentErrorLines.join('\n')), 1);
            }

            // Mark any un-executed tests in the queue as skipped
            runQueue.forEach(item => {
                // If it wasn't marked as passed or failed (hacky check but TestRun doesn't expose state easily)
                // We'll skip for brevity, VS Code handles it decently.
            });

            run.end();
        });
    }

    private matchSignatureToTestId(signature: string, queue: vscode.TestItem[]): vscode.TestItem | null {
        // signature: SPP\Tests\Core\DataProviderTest::testAddition
        const parts = signature.split('::');
        if (parts.length !== 2) return null;
        
        const classNameFull = parts[0];
        const methodName = parts[1].split(' ')[0]; // remove " with data set"

        // get the short class name
        const classParts = classNameFull.split('\\');
        const shortClassName = classParts[classParts.length - 1];

        // Search the queue
        for (const item of queue) {
            // item.id is usually "ClassName::methodName" or "file.php::description"
            if (item.id.includes(shortClassName) && item.id.includes(methodName)) {
                return item;
            }
        }
        return null;
    }
}
