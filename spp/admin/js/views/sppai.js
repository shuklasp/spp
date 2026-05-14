/**
 * SPPAI Management View
 * Manages AI providers, models, and testing.
 */
export default class SPPAIView extends BaseComponent {
    async onInit() {
        this.state = {
            registry: {},
            selectedProvider: '',
            selectedModel: '',
            testPrompt: 'Explain SPP Architecture in one sentence.',
            testResult: null,
            loading: true
        };
        await this.refresh();
    }

    async refresh() {
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
                console.log("[SPPAI] Prompt result received:", res.data.result);
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

    render() {
        const { registry, selectedProvider, selectedModel, testPrompt, testResult, loading, testing } = this.state;

        if (loading) return this.renderLoading('Waking up AI Engine...');

        const providerData = registry[selectedProvider] || { models: [] };

        return html`
            <div class="sppai-container">
                <div class="glass-panel" style="margin-bottom: 24px; padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                            <span>🤖</span> SPPAI Universal Gateway
                        </h3>
                        <button class="btn ghost-btn btn-sm" @click=${this.refresh}>🔄 Refresh Drivers</button>
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
                    <h4>🚀 Interactive Playground</h4>
                    <div class="form-group">
                        <label>Prompt</label>
                        <textarea class="spp-element" style="height: 120px; font-family: 'JetBrains Mono', monospace;" 
                            .value=${testPrompt} @input=${e => this.setState({ testPrompt: e.target.value })}></textarea>
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
                        <button class="btn primary-btn" ?disabled=${testing} @click=${this.testPrompt}>
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

                <div class="alert info-alert" style="margin-top: 24px;">
                    <p><strong>Note:</strong> SPPAI uses a unified driver architecture. API keys are managed in <code>spp/modules/spp/sppai/etc/config.yml</code> or environment variables.</p>
                </div>
            </div>
        `;
    }
}
