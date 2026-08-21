"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.SppHoverProvider = void 0;
const vscode = require("vscode");
// Central registry of SPP API documentation
const SPP_API_DOCS = {
    'transitionEntity': {
        title: 'SPP Workflow API: `transitionEntity()`',
        description: 'Applies a workflow transition to an entity and automatically performs Smart Content Negotiation (HTMX partials or Turbo Stream updates based on request headers).',
        rule: 'NEVER perform manual transition checks. ALWAYS use this helper in ResourceControllers.'
    },
    'acquireDeploymentLock': {
        title: 'SPP Deployer: `acquireDeploymentLock()`',
        description: 'Acquires a distributed mutex lock to prevent concurrent deployment race conditions.',
        rule: 'MUST wrap all deployment orchestration commands in a try/finally block using this lock.'
    },
    'releaseDeploymentLock': {
        title: 'SPP Deployer: `releaseDeploymentLock()`',
        description: 'Releases the distributed mutex lock acquired by `acquireDeploymentLock()`.',
        rule: 'MUST be called in a `finally` block to ensure the lock is always released.'
    },
    'renderStaticPartial': {
        title: 'SPP View API: `renderStaticPartial()`',
        description: 'Renders a high-performance static HTML fragment utilizing ETags & Cache-Control headers.',
        rule: 'Zero Inline HTML. ALWAYS use this instead of inline HTML strings.'
    },
    'renderPartial': {
        title: 'SPP View API: `renderPartial()`',
        description: 'Renders dynamic partials for HTMX / AJAX responses. Accepts a partial path and optional data array.',
        rule: 'Zero Inline HTML. ALWAYS use this instead of inline HTML strings.'
    },
    'getWorkflowState': {
        title: 'SPP Entity API: `getWorkflowState()`',
        description: 'Retrieves the current state markings of a workflow-managed entity, including parallel concurrent states.',
        rule: 'Use this instead of directly accessing the raw `status` property.'
    },
    'canTransition': {
        title: 'SPP Entity API: `canTransition()`',
        description: 'Checks if a specific transition is valid from the entity\'s current workflow state. Evaluates guards and pre-conditions.',
        rule: 'Always check `canTransition()` before calling `applyTransition()` for safe state changes.'
    },
    'applyTransition': {
        title: 'SPP Entity API: `applyTransition()`',
        description: 'Executes a workflow transition: evaluates guards, triggers before/after event hooks, logs audit history in `spp_entity_workflow_history`.',
        rule: 'Do NOT directly set `$entity->status`. ALWAYS use `applyTransition()` for state changes.'
    },
    'getWorkflowHistory': {
        title: 'SPP Entity API: `getWorkflowHistory()`',
        description: 'Retrieves the complete lineage of workflow state changes from `spp_entity_workflow_history`.',
        rule: 'Use for audit trails and debugging workflow transition sequences.'
    },
    'fireEvent': {
        title: 'SPP Event Bus: `fireEvent()`',
        description: 'Fires a named event through the SPP Event system. All registered listeners will be notified.',
        rule: 'When firing workflow events, you MUST also call `triggerHook()` for the same event to ensure full coverage.'
    },
    'triggerHook': {
        title: 'SPP Event Bus: `triggerHook()`',
        description: 'Triggers a named hook through the SPP Hook system. Complementary to `fireEvent()` for module-level subscribers.',
        rule: 'MUST be called alongside `fireEvent()` for `workflow.before_transition` and `workflow.after_transition` events.'
    },
    'isCLIOnly': {
        title: 'SPP CLI Guard: `isCLIOnly()`',
        description: 'When this method returns `true`, `CommandManager::execute()` blocks execution from web contexts (`PHP_SAPI !== "cli"`).',
        rule: 'Any command performing high-privilege system operations MUST override this to return `true`.'
    },
    'escapeIdentifier': {
        title: 'SPP Schema Security: `escapeIdentifier()`',
        description: 'Sanitizes table and column names for safe use in DDL statements. Prevents SQL injection in schema operations.',
        rule: 'NEVER interpolate raw user input into SQL DDL statements. ALWAYS use this helper.'
    },
    'callTool': {
        title: 'SPP AI Integration: `SPPAI::callTool()`',
        description: 'Sends a prompt to the configured AI provider with tool definitions. Returns structured tool call results.',
        rule: 'Use `\\SPPMod\\SPPAI\\SPPAI::callTool($prompt, $tools)` for AI-powered automation.'
    },
    'EventStore': {
        title: 'SPP CQRS: `EventStore`',
        description: 'Provides event sourcing capabilities: stores domain events and retrieves point-in-time snapshots.',
        rule: 'Use `\\SPPMod\\SPPWorkflow\\CQRS\\EventStore` for event logging and snapshot retrieval.'
    },
    'DagJobOrchestrator': {
        title: 'SPP Queue: `DagJobOrchestrator`',
        description: 'Token-bucket throttled job dispatching with dependency graph (DAG) support.',
        rule: 'Use `\\SPPMod\\SPPQueue\\DagJobOrchestrator` for complex job orchestration with dependencies.'
    }
};
class SppHoverProvider {
    provideHover(document, position, token) {
        const range = document.getWordRangeAtPosition(position, /[a-zA-Z_]+/);
        if (!range)
            return null;
        const word = document.getText(range);
        const doc = SPP_API_DOCS[word];
        if (doc) {
            // Verify context: make sure the word is actually used as a method/class, not just a variable name
            const lineText = document.lineAt(position).text;
            const charBefore = position.character > 0 ? lineText[range.start.character - 1] : '';
            const charAfter = range.end.character < lineText.length ? lineText[range.end.character] : '';
            // For common words like "stream", only trigger if it looks like a method call
            if (word === 'stream' && charAfter !== '(') {
                return null;
            }
            return new vscode.Hover(new vscode.MarkdownString(`**${doc.title}**\n\n${doc.description}\n\n` +
                `*Rule (AGENTS.md)*: ${doc.rule}`));
        }
        return null;
    }
}
exports.SppHoverProvider = SppHoverProvider;
//# sourceMappingURL=SppHoverProvider.js.map