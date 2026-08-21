"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.SppDocumentLinkProvider = void 0;
const vscode = require("vscode");
const path = require("path");
const fs = require("fs");
class SppDocumentLinkProvider {
    provideDocumentLinks(document, token) {
        const links = [];
        const text = document.getText();
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders || workspaceFolders.length === 0) {
            return links;
        }
        for (const pattern of SppDocumentLinkProvider.PATTERNS) {
            // Reset the regex lastIndex for each document scan
            const regex = new RegExp(pattern.source, pattern.flags);
            let match;
            while ((match = regex.exec(text)) !== null) {
                if (token.isCancellationRequested) {
                    return links;
                }
                const relativePath = match[1];
                const fullMatchStart = match.index;
                // Find the position of the captured path within the full match
                const pathStartInMatch = match[0].indexOf(relativePath);
                const pathStart = fullMatchStart + pathStartInMatch;
                const pathEnd = pathStart + relativePath.length;
                const startPos = document.positionAt(pathStart);
                const endPos = document.positionAt(pathEnd);
                const range = new vscode.Range(startPos, endPos);
                // Check for duplicate ranges (since multiple patterns may match the same string)
                const isDuplicate = links.some((existing) => existing.range.start.isEqual(range.start) &&
                    existing.range.end.isEqual(range.end));
                if (isDuplicate) {
                    continue;
                }
                // Try to resolve the file in workspace folders
                const resolvedUri = this.resolveFileUri(relativePath, workspaceFolders);
                if (resolvedUri) {
                    const link = new vscode.DocumentLink(range, resolvedUri);
                    link.tooltip = `Open ${relativePath}`;
                    links.push(link);
                }
            }
        }
        return links;
    }
    /**
     * Searches workspace folders for the given relative path.
     * Checks common SPP directory structures:
     *   - <workspace>/partials/... or <workspace>/streams/...
     *   - <workspace>/src/<AppName>/partials/...
     *   - <workspace>/etc/apps/<AppName>/partials/...
     */
    resolveFileUri(relativePath, workspaceFolders) {
        for (const folder of workspaceFolders) {
            const rootPath = folder.uri.fsPath;
            // Direct match at workspace root
            const directPath = path.join(rootPath, relativePath);
            if (fs.existsSync(directPath)) {
                return vscode.Uri.file(directPath);
            }
            // Search in src/ subdirectories (src/<AppName>/partials/...)
            const srcDir = path.join(rootPath, 'src');
            if (fs.existsSync(srcDir)) {
                const found = this.searchInSubdirectories(srcDir, relativePath);
                if (found) {
                    return vscode.Uri.file(found);
                }
            }
            // Search in etc/apps/ subdirectories (etc/apps/<AppName>/partials/...)
            const etcAppsDir = path.join(rootPath, 'etc', 'apps');
            if (fs.existsSync(etcAppsDir)) {
                const found = this.searchInSubdirectories(etcAppsDir, relativePath);
                if (found) {
                    return vscode.Uri.file(found);
                }
            }
        }
        return undefined;
    }
    /**
     * Searches immediate subdirectories of parentDir for the given relativePath.
     */
    searchInSubdirectories(parentDir, relativePath) {
        try {
            const entries = fs.readdirSync(parentDir, { withFileTypes: true });
            for (const entry of entries) {
                if (entry.isDirectory()) {
                    const candidate = path.join(parentDir, entry.name, relativePath);
                    if (fs.existsSync(candidate)) {
                        return candidate;
                    }
                }
            }
        }
        catch {
            // Directory not readable, skip
        }
        return undefined;
    }
}
exports.SppDocumentLinkProvider = SppDocumentLinkProvider;
/**
 * Patterns to match SPP partial and stream references:
 *  - 'partials/some_file.html'  or  "partials/some_file.php"
 *  - 'streams/some_file.html'   or  "streams/some_file.php"
 *  - @spppartial('partials/some_file.html', ...)
 */
SppDocumentLinkProvider.PATTERNS = [
    // @spppartial('path/to/file') or @spppartial("path/to/file")
    /@spppartial\(\s*['"]((partials|streams)\/[^'"]+)['"]/g,
    // String literal references: 'partials/...' or "partials/..."
    /['"]((partials|streams)\/[^'"]+)['"]/g,
];
//# sourceMappingURL=DocumentLinkProvider.js.map