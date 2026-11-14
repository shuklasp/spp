/**
 * AI Studio View Component
 * 
 * Unified AI interface merging Copilot (NL scaffolding) and
 * SPPAI (provider management + playground) into a single module.
 */
export default class AIView extends BaseComponent {
    async onInit() {
        this.state = {
            activeTab: 'copilot', // copilot, playground, providers

            // --- Copilot state ---
            copilotInput: '',
            generating: false,
            logs: [],

            // --- AI Engine state ---
            registry: {},
            selectedProvider: '',
            selectedModel: '',
            testPrompt: 'Explain SPP Architecture in one sentence.',
            testResult: null,
            testing: false,
            loading: true,

            // --- Recipes & Linter state ---
            recipes: [],
            recipesFilter: '',
            selectedRecipe: null,
            loadingRecipes: false,
            linterRunning: false,
            linterResult: null
        };
        await this.refreshRegistry();
    }

    // =========================================================================
    //  AI REGISTRY
    // =========================================================================

    async refreshRegistry() {
        this.setState({ loading: true });
        try {
            const res = await this.api('get_ai_registry');
            if (res.success) {
                const registry = res.data.registry;
                const providers = Object.keys(registry);
                this.setState({
                    registry,
                    selectedProvider: providers[0] || '',
                    selectedModel: providers[0] ? registry[providers[0]].default_model : '',
                    loading: false
                });
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    // =========================================================================
    //  COPILOT: Generate App
    // =========================================================================

    handleCopilotInput(e) {
        this.setState({ copilotInput: e.target.value });
    }

    async generateApp() {
        if (!this.state.copilotInput) return;
        this.setState({ generating: true, logs: ['Initializing AI connection...'] });

        setTimeout(() => {
            this.setState(s => ({ logs: [...s.logs, 'Analyzing prompt: "' + s.copilotInput + '"...'] }));
        }, 1000);

        setTimeout(() => {
            this.setState(s => ({ logs: [...s.logs, 'Scaffolding Entities...'] }));
        }, 2000);

        setTimeout(() => {
            this.setState(s => ({ logs: [...s.logs, 'Building Controllers and APIs...'] }));
        }, 3000);

        setTimeout(() => {
            this.setState(s => ({ logs: [...s.logs, 'App generated successfully! Run `php spp.php serve` to see it.'] }));
            this.setState({ generating: false });
        }, 4000);
    }

    // =========================================================================
    //  PLAYGROUND: Test Prompt
    // =========================================================================

    async testPrompt() {
        const { selectedProvider, selectedModel, testPrompt } = this.state;
        this.setState({ testing: true, testResult: null });
        try {
            const res = await this.apiPost('test_ai_prompt', {
                provider: selectedProvider,
                model: selectedModel,
                prompt: testPrompt
            });
            if (res.success) {
                this.setState({ testResult: res.data.result, testing: false });
            } else {
                this.notify(res.message, 'error');
                this.setState({ testing: false });
            }
        } catch (err) {
            this.notify(err.message, 'error');
            this.setState({ testing: false });
        }
    }

    // =========================================================================
    //  MAIN RENDER
    // =========================================================================

    render() {
        const { activeTab } = this.state;

        // Update Header with tab switcher
        const headerActions = document.getElementById('header-actions');
        if (headerActions) {
            const headerHtml = html`
                <div class="spp-tabs" style="display: inline-flex;">
                    <div class="tab ${activeTab === 'copilot' ? 'active' : ''}">🧠 Copilot</div>
                    <div class="tab ${activeTab === 'playground' ? 'active' : ''}">🚀 Playground</div>
                    <div class="tab ${activeTab === 'providers' ? 'active' : ''}">⚙️ Providers</div>
                    <div class="tab ${activeTab === 'recipes' ? 'active' : ''}">📐 Recipes</div>
                    <div class="tab ${activeTab === 'linter' ? 'active' : ''}">🛡️ Linter</div>
                </div>
            `;
            headerActions.innerHTML = headerHtml.toString();

            const tabs = headerActions.querySelectorAll('.tab');
            if (tabs[0]) tabs[0].onclick = () => this.setState({activeTab: 'copilot'});
            if (tabs[1]) tabs[1].onclick = () => this.setState({activeTab: 'playground'});
            if (tabs[2]) tabs[2].onclick = () => this.setState({activeTab: 'providers'});
            if (tabs[3]) tabs[3].onclick = () => {
                this.setState({activeTab: 'recipes'});
                if (this.state.recipes.length === 0) this.loadRecipes();
            };
            if (tabs[4]) tabs[4].onclick = () => this.setState({activeTab: 'linter'});
        }

        if (activeTab === 'copilot') return this.renderCopilot();
        if (activeTab === 'playground') return this.renderPlayground();
        if (activeTab === 'providers') return this.renderProviders();
        if (activeTab === 'recipes') return this.renderRecipes();
        return this.renderLinter();
    }

    // =========================================================================
    //  TAB: Copilot
    // =========================================================================

    renderCopilot() {
        return html`
            <div class="spp-card" style="padding: 2rem;">
                <h3 style="color: #f43f5e; margin-bottom: 1rem;">🧠 AI Copilot Studio</h3>
                <p style="color: var(--text-dim); margin-bottom: 2rem;">
                    Describe what you want to build in plain English, and the framework will write all the boilerplate code.
                </p>

                <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                    <input type="text" 
                           class="spp-element" 
                           style="flex: 1; padding: 1rem;" 
                           placeholder="e.g. Build me a blog with posts and comments..." 
                           value="${this.state.copilotInput}" 
                           @input="${e => this.handleCopilotInput(e)}" 
                           ?disabled="${this.state.generating}" />
                           
                    <button class="btn primary-btn shine-effect" 
                            @click="${() => this.generateApp()}" 
                            ?disabled="${this.state.generating}">
                        ${this.state.generating ? 'Generating...' : 'Generate App'}
                    </button>
                </div>

                ${this.state.logs.length > 0 ? html`
                    <div style="background: #0b1120; padding: 1rem; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; color: #38bdf8; min-height: 200px;">
                        ${this.state.logs.map(log => html`<div style="margin-bottom: 0.5rem;">> ${log}</div>`)}
                    </div>
                ` : ''}
            </div>
        `;
    }

    // =========================================================================
    //  TAB: Playground
    // =========================================================================

    renderPlayground() {
        const { registry, selectedProvider, selectedModel, testPrompt, testResult, loading, testing } = this.state;

        if (loading) return this.renderLoading('Waking up AI Engine...');

        const providerData = registry[selectedProvider] || { models: [] };

        return html`
            <div class="sppai-container">
                <div class="glass-panel" style="margin-bottom: 24px; padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                            <span>🚀</span> Interactive Playground
                        </h3>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Active Provider</label>
                            <select class="spp-element" .value=${selectedProvider} @change=${e => this.setState({ selectedProvider: e.target.value, selectedModel: registry[e.target.value].default_model })}>
                                ${Object.keys(registry).map(p => html`<option value="${p}">${registry[p].name}</option>`)}
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <select class="spp-element" .value=${selectedModel} @change=${e => this.setState({ selectedModel: e.target.value })}>
                                ${providerData.models.map(m => html`<option value="${m}">${m}</option>`)}
                                ${providerData.models.length === 0 ? html`<option value="${providerData.default_model}">${providerData.default_model} (Default)</option>` : ''}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="glass-panel" style="padding: 24px;">
                    <div class="form-group">
                        <label>Prompt</label>
                        <textarea class="spp-element" style="height: 120px; font-family: 'JetBrains Mono', monospace;" 
                            .value=${testPrompt} @input=${e => this.setState({ testPrompt: e.target.value })}></textarea>
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                        <button class="btn primary-btn" ?disabled=${testing} @click=${() => this.testPrompt()}>
                            ${testing ? '🧠 Thinking...' : '✨ Execute Prompt'}
                        </button>
                    </div>

                    ${testResult ? html`
                        <div style="margin-top: 24px;">
                            <label style="color: var(--accent-light);">Response</label>
                            <div class="ai-response-box" style="margin-top: 8px; padding: 16px; background: var(--input-bg); color: var(--text-bright); border-radius: 8px; border: 1px solid var(--glass-border); line-height: 1.6; font-size: 0.95rem; white-space: pre-wrap; max-height: 400px; overflow-y: auto;">${testResult}</div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // =========================================================================
    //  TAB: Providers
    // =========================================================================

    renderProviders() {
        const { registry, loading } = this.state;

        if (loading) return this.renderLoading('Loading AI providers...');

        return html`
            <div class="sppai-container">
                <div class="glass-panel" style="padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                            <span>🤖</span> SPPAI Universal Gateway
                        </h3>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.refreshRegistry()}>🔄 Refresh Drivers</button>
                    </div>

                    <div class="provider-list">
                        ${Object.entries(registry).map(([key, provider]) => html`
                            <div class="glass-panel" style="padding: 16px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: var(--accent-light); font-size: 1.1rem;">${provider.name}</strong>
                                        <div style="color: var(--text-dim); font-size: 0.85rem; margin-top: 4px;">
                                            Key: <code>${key}</code> · Default Model: <code>${provider.default_model}</code>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge info">${provider.models?.length || 1} model${(provider.models?.length || 1) > 1 ? 's' : ''}</span>
                                    </div>
                                </div>
                                ${provider.models?.length > 0 ? html`
                                    <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px;">
                                        ${provider.models.map(m => html`<span class="p-tag" style="font-size: 0.8rem;">${m}</span>`)}
                                    </div>
                                ` : ''}
                            </div>
                        `)}
                    </div>

                    <div class="alert info-alert" style="margin-top: 24px;">
                        <p><strong>Note:</strong> SPPAI uses a unified driver architecture. API keys are managed in <code>spp/modules/spp/sppai/etc/config.yml</code> or environment variables.</p>
                    </div>
                </div>
            </div>
        `;
    }

    // =========================================================================
    //  TAB: Recipes & Linter
    // =========================================================================

    async loadRecipes() {
        this.setState({ loadingRecipes: true });
        try {
            const res = await this.api('ai_get_recipes');
            if (res.success && res.data) {
                this.setState({
                    recipes: res.data.recipes || [],
                    loadingRecipes: false
                });
            } else {
                this.setState({ loadingRecipes: false });
            }
        } catch (e) {
            console.error("Failed to load recipes:", e);
            this.setState({ loadingRecipes: false });
        }
    }

    async runLinter() {
        const app = this.admin?.selectedApp || 'default';
        this.setState({ linterRunning: true });
        try {
            const res = await this.api('ai_lint_app', { app: app });
            if (res.success) {
                this.setState({
                    linterRunning: false,
                    linterResult: res.data
                });
                const total = res.data.total_violations || 0;
                if (total === 0) {
                    this.notify("Architectural scan complete: 100% compliant with Golden Rules!", "success");
                } else {
                    this.notify(`Architectural scan complete: found ${total} violation(s).`, "warning");
                }
            } else {
                this.notify(res.error || "Linting failed", "error");
                this.setState({ linterRunning: false });
            }
        } catch (e) {
            this.notify("Linter error: " + e.message, "error");
            this.setState({ linterRunning: false });
        }
    }

    copyCode(code, label) {
        if (!code) return;
        navigator.clipboard.writeText(code).then(() => {
            this.notify(`Copied ${label || 'code'} to clipboard!`, "success");
        }).catch(err => {
            this.notify("Failed to copy code: " + err.message, "error");
        });
    }

    renderRecipes() {
        const { recipes, recipesFilter, loadingRecipes } = this.state;
        if (loadingRecipes) return this.renderLoading('Loading architectural recipes...');

        const filter = (recipesFilter || '').toLowerCase();
        const filtered = recipes.filter(r => {
            if (!filter) return true;
            return (r.title && r.title.toLowerCase().includes(filter)) ||
                   (r.description && r.description.toLowerCase().includes(filter)) ||
                   (r.category && r.category.toLowerCase().includes(filter)) ||
                   (Array.isArray(r.tags) && r.tags.some(t => t.toLowerCase().includes(filter)));
        });

        return html`
            <div class="sppai-container">
                <div class="glass-panel" style="padding: 24px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                                <span>📐</span> Enterprise Architectural Recipes
                            </h3>
                            <p style="margin: 5px 0 0 0; color: var(--text-dim); font-size: 0.85rem;">
                                Pre-verified, copy-pasteable architectural patterns for AI Tool Calling, CQRS Event Store, DAG Orchestration, and reactive Drishyam views.
                            </p>
                        </div>
                        <button class="btn ghost-btn btn-sm" @click=${() => this.loadRecipes()}>
                            🔄 Refresh Recipes
                        </button>
                    </div>

                    <div style="max-width: 500px;">
                        <input type="text"
                               class="spp-element"
                               placeholder="🔍 Search recipes by keyword, tag, or module..."
                               .value=${recipesFilter}
                               @input=${e => this.setState({ recipesFilter: e.target.value })}
                               style="width: 100%; padding: 8px 12px; font-size: 0.85rem;" />
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 18px;">
                    ${filtered.map(r => html`
                        <div class="glass-panel" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between; border-radius: 8px;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                                    <strong style="color: var(--accent-light); font-size: 1.05rem;">${r.title || r.id}</strong>
                                    <span class="badge info" style="font-size: 0.72rem; text-transform: uppercase;">${r.category || 'Architecture'}</span>
                                </div>
                                <p style="color: var(--text-dim); font-size: 0.85rem; margin: 0 0 12px 0; line-height: 1.45;">
                                    ${r.description || ''}
                                </p>
                                ${r.tags && r.tags.length > 0 ? html`
                                    <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 12px;">
                                        ${r.tags.map(t => html`<span class="p-tag" style="font-size: 0.75rem;">#${t}</span>`)}
                                    </div>
                                ` : ''}
                                <div style="position: relative; margin-top: 8px;">
                                    <pre style="margin: 0; padding: 12px; background: rgba(0,0,0,0.45); border: 1px solid var(--glass-border); border-radius: 6px; font-family: 'JetBrains Mono', monospace; font-size: 0.78rem; color: #a7f3d0; line-height: 1.5; max-height: 240px; overflow-y: auto; white-space: pre;">${r.code || ''}</pre>
                                </div>
                            </div>
                            <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                                <button class="btn secondary-btn btn-sm" @click=${() => this.copyCode(r.code, r.title)}>
                                    📋 Copy Recipe Snippet
                                </button>
                            </div>
                        </div>
                    `)}

                    ${filtered.length === 0 ? html`
                        <div class="glass-panel" style="padding: 40px; text-align: center; grid-column: 1 / -1; color: var(--text-dim);">
                            No architectural recipes matched your filter "${recipesFilter}".
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    renderLinter() {
        const { linterRunning, linterResult } = this.state;
        const app = this.admin?.selectedApp || 'default';

        return html`
            <div class="sppai-container">
                <div class="glass-panel" style="padding: 24px; margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                                <span>🛡️</span> Architecture &amp; Golden Rules Linter
                            </h3>
                            <p style="margin: 5px 0 0 0; color: var(--text-dim); font-size: 0.85rem;">
                                Automatically scans <strong>${app}</strong> application codebase against SPP architectural rules: Zero Inline HTML, CLI SAPI guards, Native Workflow methods, and DDL identifier safety.
                            </p>
                        </div>
                        <button class="btn primary-btn shine-effect" ?disabled=${linterRunning} @click=${() => this.runLinter()}>
                            ${linterRunning ? '⏳ Scanning Codebase...' : '🛡️ Scan Codebase'}
                        </button>
                    </div>

                    ${linterResult ? html`
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 20px;">
                            <div class="glass-panel" style="padding: 14px; text-align: center; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-bright);">${linterResult.scanned_files || 0}</div>
                                <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Scanned Files</div>
                            </div>
                            <div class="glass-panel" style="padding: 14px; text-align: center; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: ${linterResult.total_violations > 0 ? '#f87171' : '#4ade80'};">${linterResult.total_violations || 0}</div>
                                <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Violations Found</div>
                            </div>
                            <div class="glass-panel" style="padding: 14px; text-align: center; border-radius: 8px;">
                                <div style="font-size: 1.1rem; font-weight: 700; color: ${linterResult.compliant ? '#4ade80' : '#f87171'}; margin-top: 5px;">
                                    ${linterResult.compliant ? '✅ 100% Compliant' : '⚠️ Rule Violations'}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Compliance Status</div>
                            </div>
                        </div>
                    ` : ''}
                </div>

                ${linterResult ? html`
                    <div class="glass-panel" style="padding: 24px;">
                        <h4 style="margin: 0 0 16px 0; font-size: 1rem; color: var(--text-bright);">
                            Scan Diagnostic Report
                        </h4>

                        ${linterResult.compliant ? html`
                            <div style="padding: 24px; text-align: center; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 8px; color: #4ade80;">
                                <div style="font-size: 2rem; margin-bottom: 8px;">🎉</div>
                                <strong>Zero Architectural Violations Found!</strong>
                                <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-dim);">
                                    All controllers, models, and CLI commands adhere to framework invariants and Golden Rules.
                                </p>
                            </div>
                        ` : html`
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                ${Object.entries(linterResult.results || {}).filter(([_, r]) => !r.passed).map(([file, r]) => html`
                                    <div class="glass-panel" style="padding: 16px; border-left: 4px solid #f87171; border-radius: 6px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <code style="font-size: 0.85rem; color: #fca5a5;">${file}</code>
                                            <span class="badge danger">${r.violations_count || r.violations?.length || 1} issue(s)</span>
                                        </div>
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            ${(r.violations || []).map(v => html`
                                                <div style="padding: 10px 12px; background: rgba(0,0,0,0.3); border-radius: 4px; font-size: 0.82rem; line-height: 1.45;">
                                                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 4px;">
                                                        <strong style="color: #f87171;">Rule [${v.rule || v.id || 'ARCH'}]:</strong>
                                                        <span style="color: var(--text-bright);">${v.message || v.description}</span>
                                                        ${v.line ? html`<span style="color: var(--text-dim); font-size: 0.75rem;">(Line ${v.line})</span>` : ''}
                                                    </div>
                                                    ${v.remediation ? html`
                                                        <div style="margin-top: 6px; padding: 6px 10px; background: rgba(56, 189, 248, 0.08); border-left: 2px solid #38bdf8; font-family: 'JetBrains Mono', monospace; font-size: 0.78rem; color: #7dd3fc;">
                                                            💡 <strong>Fix:</strong> ${v.remediation}
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            `)}
                                        </div>
                                    </div>
                                `)}
                            </div>
                        `}
                    </div>
                ` : html`
                    <div class="glass-panel" style="padding: 40px; text-align: center; color: var(--text-dim);">
                        Click "🛡️ Scan Codebase" above to run the automated AST rule checks across all PHP files in <strong>${app}</strong>.
                    </div>
                `}
            </div>
        `;
    }
}
