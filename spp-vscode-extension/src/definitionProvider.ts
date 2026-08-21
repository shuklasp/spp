import * as vscode from 'vscode';
import * as path from 'path';
import * as fs from 'fs';

export class SPPDefinitionProvider implements vscode.DefinitionProvider {
    public async provideDefinition(
        document: vscode.TextDocument,
        position: vscode.Position,
        token: vscode.CancellationToken
    ): Promise<vscode.Definition | null> {

        const lineText = document.lineAt(position).text;
        
        // Match partials/ or streams/ paths in strings
        const patterns = [
            /['"](?:partials|streams)\/([^'"]+)['"]/g,
            /@spppartial\(\s*['"]([^'"]+)['"]/g
        ];

        for (const pattern of patterns) {
            let match;
            while ((match = pattern.exec(lineText)) !== null) {
                const matchStart = match.index;
                const matchEnd = matchStart + match[0].length;
                
                if (position.character >= matchStart && position.character <= matchEnd) {
                    // Extract the full relative path (e.g., "partials/user_row.html")
                    let filePath = match[0].replace(/^['"]|['"]$/g, '').replace(/@spppartial\(\s*['"]/, '');
                    if (!filePath.includes('/')) {
                        filePath = match[1]; // fallback to capture group
                    }
                    
                    // Search in multiple locations
                    const workspaceRoot = vscode.workspace.workspaceFolders?.[0]?.uri.fsPath;
                    const searchDirs = [
                        path.dirname(document.uri.fsPath), // relative to current file
                    ];
                    if (workspaceRoot) {
                        searchDirs.push(workspaceRoot); // workspace root
                        // Also search in src/*/ directories
                        const srcDir = path.join(workspaceRoot, 'src');
                        if (fs.existsSync(srcDir)) {
                            const apps = fs.readdirSync(srcDir).filter(f => {
                                return fs.statSync(path.join(srcDir, f)).isDirectory();
                            });
                            for (const app of apps) {
                                searchDirs.push(path.join(srcDir, app));
                            }
                        }
                    }

                    for (const dir of searchDirs) {
                        const fullPath = path.join(dir, filePath);
                        if (fs.existsSync(fullPath)) {
                            return new vscode.Location(
                                vscode.Uri.file(fullPath),
                                new vscode.Position(0, 0)
                            );
                        }
                    }
                }
            }
        }

        return null;
    }
}
