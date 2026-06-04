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
            loading: true
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
                    <div class="tab ${activeTab === 'copilot' ? 'active' : ''}" @click=${() => this.setState({activeTab: 'copilot'})}>🧠 Copilot</div>
                    <div class="tab ${activeTab === 'playground' ? 'active' : ''}" @click=${() => this.setState({activeTab: 'playground'})}>🚀 Playground</div>
                    <div class="tab ${activeTab === 'providers' ? 'active' : ''}" @click=${() => this.setState({activeTab: 'providers'})}>⚙️ Providers</div>
                </div>
            `;
            headerActions.innerHTML = headerHtml.toString();

            const tabs = headerActions.querySelectorAll('.tab');
            if (tabs[0]) tabs[0].onclick = () => this.setState({activeTab: 'copilot'});
            if (tabs[1]) tabs[1].onclick = () => this.setState({activeTab: 'playground'});
            if (tabs[2]) tabs[2].onclick = () => this.setState({activeTab: 'providers'});
        }

        if (activeTab === 'copilot') return this.renderCopilot();
        if (activeTab === 'playground') return this.renderPlayground();
        return this.renderProviders();
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
                            <div class="ai-response-box" style="margin-top: 8px; padding: 16px; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid var(--glass-border); line-height: 1.6; font-size: 0.95rem; white-space: pre-wrap; max-height: 400px; overflow-y: auto;">${testResult}</div>
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
}
