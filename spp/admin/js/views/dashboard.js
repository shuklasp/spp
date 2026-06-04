/**
 * DashboardView - Welcome and Onboarding
 * 
 * Provides a friendly, informative landing page for novice and expert developers.
 */

export default class DashboardView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: false,
            username: this.app.user?.username || 'Developer'
        };
    }

    render() {
        return html`
            <div class="view-content-wrapper fade-in" style="max-width: 1000px; margin: 0 auto; padding-top: 2rem;">
                <!-- Hero Section -->
                <div class="glass-panel" style="text-align: center; padding: 3rem 2rem; background: linear-gradient(145deg, rgba(30,32,40,0.8) 0%, rgba(15,17,26,0.9) 100%); position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: radial-gradient(circle, rgba(56, 189, 248, 0.1) 0%, transparent 70%); transform: rotate(30deg); pointer-events: none;"></div>
                    <div style="position: absolute; bottom: -50%; right: -10%; width: 50%; height: 200%; background: radial-gradient(circle, rgba(244, 63, 94, 0.1) 0%, transparent 70%); transform: rotate(-30deg); pointer-events: none;"></div>
                    
                    <h1 class="gradient-text" style="font-size: 3rem; margin-bottom: 0.5rem; font-family: 'Outfit', sans-serif;">Welcome to Developer Heaven</h1>
                    <p style="color: var(--text-dim); font-size: 1.2rem; max-width: 600px; margin: 0 auto 2rem auto;">
                        Hello, ${this.state.username}. We've made the SPP Framework extremely easy and straightforward. Start building your next big idea right here.
                    </p>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <button class="btn primary-btn shine-effect" style="padding: 12px 24px; font-size: 1.1rem;" @click=${() => location.hash='apps'}>🚀 Launch App Studio</button>
                        <button class="btn ghost-btn" style="padding: 12px 24px; font-size: 1.1rem;" @click=${() => location.hash='copilot'}>🤖 Open AI Copilot</button>
                    </div>
                </div>

                <!-- Quick Start Architecture Map -->
                <h2 style="margin: 3rem 0 1rem 0; font-size: 1.5rem; text-align: center;">How It Works</h2>
                <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                    
                    <!-- Step 1: Apps & Modules -->
                    <div class="item-card" style="cursor: pointer; padding: 2rem;" @click=${() => location.hash='apps'}>
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📱</div>
                        <h3 style="margin-top:0;">1. Create an Application</h3>
                        <p style="color: var(--text-dim); font-size: 0.9rem;">
                            Everything starts here. An application is a self-contained environment. You can also define reusable Kernel Modules.
                        </p>
                    </div>

                    <!-- Step 2: Entities -->
                    <div class="item-card" style="cursor: pointer; padding: 2rem;" @click=${() => location.hash='entities'}>
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🏗️</div>
                        <h3 style="margin-top:0;">2. Define Entities</h3>
                        <p style="color: var(--text-dim); font-size: 0.9rem;">
                            Forget SQL. Use the visual schema builder to define your database tables, relationships, and data structures instantly.
                        </p>
                    </div>

                    <!-- Step 3: Forms -->
                    <div class="item-card" style="cursor: pointer; padding: 2rem;" @click=${() => location.hash='forms'}>
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
                        <h3 style="margin-top:0;">3. Scaffold Forms</h3>
                        <p style="color: var(--text-dim); font-size: 0.9rem;">
                            Turn your Entities into beautiful, fully-functional HTML forms with zero coding required.
                        </p>
                    </div>
                    
                </div>
                
                <!-- Documentation Links -->
                <div style="margin-top: 3rem; text-align: center;">
                    <a href="/api/docs" target="_blank" class="btn ghost-btn" style="color: var(--text-dim);">📚 View Interactive API Documentation</a>
                </div>
            </div>
        `;
    }
}
