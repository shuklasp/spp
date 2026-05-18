import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';

/**
 * Lekhak Commerce View Controller
 * Products are persisted to the database via the admin API.
 */
export default class CommerceView extends BaseComponent {
    async onInit(params = {}) {
        this.state = {
            filterTab: params.tab || 'all',
            products: [],
            loading: true
        };

        window.__spp_handlers = window.__spp_handlers || {};
        window.__spp_handlers['nav-lekhak'] = () => location.hash = 'lekhak';
        window.__spp_handlers['nav-content'] = () => location.hash = 'content';
        window.__spp_handlers['nav-canvas'] = () => location.hash = 'canvas';
        window.__spp_handlers['nav-commerce'] = () => location.hash = 'commerce';
        window.__spp_handlers['nav-translations'] = () => location.hash = 'translations';
        window.__spp_handlers['nav-settings'] = () => location.hash = 'settings';
        window.__spp_handlers['nav-media'] = () => location.hash = 'media';
        window.__spp_handlers['nav-structure'] = () => location.hash = 'structure';
    }

    async onMount() { await this.fetchProducts(); }

    async fetchProducts() {
        try {
            const res = await this.api.listProducts();
            if (res.success) {
                this.setState({ products: res.products || [], loading: false });
            }
        } catch (e) {
            console.error('Commerce fetch error:', e);
            this.setState({ loading: false });
        }
    }

    async addProduct() {
        const title = prompt("Product name:");
        if (!title) return;
        const price = prompt("Price:", "$29.99") || "$29.99";
        const sku = prompt("SKU:", "SKU-" + Math.floor(Math.random() * 9000 + 1000)) || "SKU-0000";
        try {
            const res = await this.api.saveProduct({ title, price, sku, stock: 10, active: 1 });
            if (res.success) {
                this.admin?.notify?.("Product created.", "success");
                await this.fetchProducts();
            }
        } catch (e) {
            this.admin?.notify?.("Failed to create product.", "error");
        }
    }

    async editProduct(id) {
        const item = this.state.products.find(p => p.id == id);
        if (!item) return;
        const title = prompt("Product name:", item.title);
        if (title === null) return;
        const price = prompt("Price:", item.price) || item.price;
        const stock = parseInt(prompt("Stock:", item.stock), 10) || 0;
        try {
            const res = await this.api.saveProduct({ id, title, price, sku: item.sku, stock, active: stock > 0 ? 1 : 0 });
            if (res.success) {
                this.admin?.notify?.("Product updated.", "success");
                await this.fetchProducts();
            }
        } catch (e) {
            this.admin?.notify?.("Update failed.", "error");
        }
    }

    async deleteProduct(id) {
        if (!confirm("Delete this product permanently?")) return;
        try {
            const res = await this.api.deleteProduct({ id });
            if (res.success) {
                this.admin?.notify?.("Product deleted.", "info");
                await this.fetchProducts();
            }
        } catch (e) {
            this.admin?.notify?.("Delete failed.", "error");
        }
    }

    render() { return { content: '' }; }

    afterUpdate() {
        const countEl = document.getElementById('spp-commerce-count');
        if (countEl) countEl.textContent = this.state.products.length;

        const bodyEl = document.getElementById('spp-commerce-container-body');
        if (!bodyEl) return;

        const addBtn = document.getElementById('spp-commerce-add-btn');
        if (addBtn && !addBtn._bound) { addBtn.onclick = () => this.addProduct(); addBtn._bound = true; }

        const pillAll = document.getElementById('spp-commerce-pill-all');
        const pillActive = document.getElementById('spp-commerce-pill-active');
        if (pillAll && !pillAll._bound) { pillAll.onclick = () => this.setState({ filterTab: 'all' }); pillAll._bound = true; }
        if (pillActive && !pillActive._bound) { pillActive.onclick = () => this.setState({ filterTab: 'active' }); pillActive._bound = true; }
        if (pillAll) pillAll.classList.toggle('active', this.state.filterTab === 'all');
        if (pillActive) pillActive.classList.toggle('active', this.state.filterTab === 'active');

        const filtered = this.state.products.filter(p => {
            if (this.state.filterTab === 'active') return p.active == 1 && p.stock > 0;
            return true;
        });

        if (!filtered.length) {
            bodyEl.innerHTML = `<div class="empty-state-box"><div style="font-size:3rem;margin-bottom:12px;">📦</div><h3>No products found</h3><p style="color:var(--text-dim);max-width:400px;margin:8px auto;">Add your first product using the button above.</p></div>`;
            return;
        }

        let gridHtml = '<div class="commerce-grid">';
        filtered.forEach(p => {
            const stockLabel = p.stock > 0 ? `🟢 ${p.stock} in stock` : `🔴 Out of stock`;
            gridHtml += `
                <div class="product-card" data-id="${p.id}">
                    <div>
                        <div class="product-card-header">
                            <span class="product-sku">${p.sku}</span>
                            <span style="font-size:0.72rem;background:var(--header-bg);padding:2px 6px;border-radius:4px;color:var(--text-dim);">#${p.id}</span>
                        </div>
                        <div class="product-title">${p.title}</div>
                        <div class="product-stock">${stockLabel}</div>
                        <div class="product-price">${p.price}</div>
                    </div>
                    <div class="product-card-footer">
                        <button class="btn-card-action edit-prod">Edit</button>
                        <button class="btn-card-action del-prod" style="color:#ef4444;">Delete</button>
                    </div>
                </div>`;
        });
        gridHtml += '</div>';
        bodyEl.innerHTML = gridHtml;

        bodyEl.querySelectorAll('.product-card').forEach(card => {
            const id = card.getAttribute('data-id');
            card.querySelector('.edit-prod').onclick = () => this.editProduct(id);
            card.querySelector('.del-prod').onclick = () => this.deleteProduct(id);
        });
    }
}
