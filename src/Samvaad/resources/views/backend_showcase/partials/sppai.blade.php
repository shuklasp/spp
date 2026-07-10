<div class="glass-panel">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid var(--surface-border); padding-bottom: 1rem;">
        <h2 style="margin: 0; border: none; padding: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            Universal AI Gateway (SPPAI)
        </h2>
        <span style="background: rgba(167, 139, 250, 0.2); color: #a78bfa; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600;">Ollama / OpenAI / Sarvam AI</span>
    </div>
    
    <p>SPPAI is the framework's native AI orchestration engine. It abstracts multiple providers (Ollama, OpenAI, Gemini, Claude, DeepSeek, Sarvam AI) into a single, unified interface for structured completion, tool calling, and RAG embeddings.</p>

    <div style="margin-top: 2rem;">
        <form hx-post="<?= \SPP\App::url('backend-showcase/sppai/prompt', 'samvaad') ?>" hx-target="#ai-response" hx-indicator="#ai-spinner">
            <div class="form-group">
                <label class="form-label" style="color: #a78bfa;">Enter a Prompt for the AI</label>
                <textarea name="prompt" class="form-input" rows="4" placeholder="E.g., Write a 2-sentence summary of why MVC architecture is useful..." required style="resize: vertical; font-family: inherit;"></textarea>
            </div>
            <button type="submit" class="btn" style="background: #8b5cf6; margin-bottom: 1rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Generate Response
            </button>
            <span id="ai-spinner" class="htmx-indicator" style="margin-left: 10px; color: #a78bfa;">
                Thinking... <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
            </span>
        </form>

        <div id="ai-response">
            <!-- AI Response renders here -->
        </div>
    </div>
</div>
