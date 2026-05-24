import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';

export default class PathautoView extends BaseComponent {
    async onInit() {
        this.state = { loading: true, patterns: [] };
        await this.fetchPatterns();
    }

    async fetchPatterns() {
        this.setState({ loading: true });
        // Assuming we add a quick API call in admin-api.php or mock it
        try {
            const res = await this.admin.api('pathauto_get_patterns');
            if (res.success) {
                this.setState({ patterns: res.patterns || [], loading: false });
            } else {
                this.setState({ loading: false });
            }
        } catch (e) {
            this.setState({ loading: false });
        }
    }

    render() {
        if (this.state.loading) return `<div style="padding:40px;text-align:center;">Loading Pathauto Config...</div>`;
        return `
        <div style="padding: 2.5rem 2rem; max-width: 1000px; margin: 0 auto; font-family: 'Inter', sans-serif;">
            <header style="margin-bottom: 2rem;">
                <h1 style="font-size: 2.2rem; font-weight: 800; margin: 0;">Pathauto Patterns</h1>
                <p style="color: var(--text-dim);">Configure automated SEO URL generation patterns.</p>
            </header>
            
            <div style="background: var(--glass-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px;">
                <h3>Content Types</h3>
                <table style="width:100%; text-align:left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="padding: 10px;">Entity Type</th>
                            <th style="padding: 10px;">Pattern</th>
                            <th style="padding: 10px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.state.patterns.map(p => `
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px;">${p.entity_type} / ${p.bundle}</td>
                            <td style="padding: 10px;"><code>${p.pattern}</code></td>
                            <td style="padding: 10px;"><button class="btn" style="background:#6366f1;color:#fff;border:none;padding:5px 10px;border-radius:4px;">Edit</button></td>
                        </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top:20px;">
                <button class="btn" style="background:#10b981;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:bold;">+ Add Pattern</button>
            </div>
        </div>
        `;
    }
}
