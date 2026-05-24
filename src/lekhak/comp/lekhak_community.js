import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta } from './lekhak-nav.js';

export default class LekhakCommunityComponent extends BaseComponent {
    async onInit() {
        registerNavHandlers();
        setPageMeta('Community', 'Manage user profiles and social groups');
    }

    render() {
        return `
        <div style="padding: 2.5rem 2rem; max-width: 1200px; margin: 0 auto; font-family: 'Inter', sans-serif;">
            <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; margin: 0; color: var(--text);">
                        <span style="font-size: 2.5rem; margin-right: 10px; vertical-align: middle;">👥</span>
                        Community
                    </h1>
                    <p style="color: var(--text-dim); margin-top: 5px;">Manage user profiles and social groups</p>
                </div>
                <button class="btn" style="background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;border:none;cursor:pointer;font-weight:600;">+ Create New</button>
            </header>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div style="background:var(--bg); border:1px solid var(--border); padding:1rem; border-radius:8px;">Members: 1,204</div><div style="background:var(--bg); border:1px solid var(--border); padding:1rem; border-radius:8px;">Groups: 15</div>
            </div>
            
            <div style="background:var(--glass-bg,#fff); border:1px solid var(--border); border-radius:12px; padding:3rem; text-align:center; color:var(--text-dim);">
                <span style="font-size:3rem; display:block; margin-bottom:1rem; opacity:0.5;">👥</span>
                <h3>Dashboard Initialized</h3>
                <p>The Community engine is active. Configuration and data views will appear here.</p>
            </div>
        </div>
        `;
    }
}