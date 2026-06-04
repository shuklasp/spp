/**
 * Lekhak Commerce Management View
 */
export default class CommerceView extends BaseComponent {
    render() {
        return html`
            <div class="commerce-container">
                <div class="glass-panel" style="padding: 40px; text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">🛒</div>
                    <h2>Lekhak Commerce</h2>
                    <p style="color: var(--text-dim); max-width: 500px; margin: 0 auto 30px;">
                        Manage your products, orders, and storefront settings. Lekhak Commerce provides a seamless shopping experience integrated with your CMS.
                    </p>
                    
                    <div class="glass-panel" style="padding: 0; text-align: left; background: rgba(0,0,0,0.2);">
                        <div style="padding: 15px 20px; border-bottom: 1px solid var(--glass-border); font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
                            <span>Recent Orders</span>
                            <button class="btn ghost-btn btn-sm">View All</button>
                        </div>
                        <div style="padding: 40px; text-align: center; color: var(--text-dim);">
                            <p>No orders found yet.</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; text-align: left;">
                        <div class="glass-panel" style="padding: 20px;">
                            <h4 style="margin-top: 0;">📦 Inventory</h4>
                            <p style="font-size: 0.85rem; color: var(--text-dim);">Track stock levels and product variants across all your stores.</p>
                            <button class="btn secondary-btn btn-sm">Manage Products</button>
                        </div>
                        <div class="glass-panel" style="padding: 20px;">
                            <h4 style="margin-top: 0;">💳 Payments</h4>
                            <p style="font-size: 0.85rem; color: var(--text-dim);">Configure gateways like Razorpay, Stripe, and PayPal.</p>
                            <button class="btn secondary-btn btn-sm">Configure Gateway</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
}
