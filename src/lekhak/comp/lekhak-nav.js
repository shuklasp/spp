/**
 * Lekhak Shared Navigation Module
 * 
 * Eliminates duplicated nav handlers across all Lekhak components.
 * Uses SPPEX.Helmet for dynamic page titles and SPPEX.Breadcrumbs for trail rendering.
 */

/**
 * Registers all standard Lekhak navigation handlers on `window.__spp_handlers`.
 * Call once from any component's onInit().
 */
export function registerNavHandlers() {
    window.__spp_handlers = window.__spp_handlers || {};
    window.__spp_handlers['nav-lekhak']       = () => location.hash = 'lekhak';
    window.__spp_handlers['nav-content']      = () => location.hash = 'content';
    window.__spp_handlers['nav-canvas']       = () => location.hash = 'canvas';
    window.__spp_handlers['nav-settings']     = () => location.hash = 'settings';
    window.__spp_handlers['nav-editor']       = () => location.hash = 'editor';
    window.__spp_handlers['nav-commerce']     = () => location.hash = 'commerce';
    window.__spp_handlers['nav-translations'] = () => location.hash = 'translations';
    window.__spp_handlers['nav-media']        = () => location.hash = 'media';
    window.__spp_handlers['nav-structure']    = () => location.hash = 'structure';
    window.__spp_handlers['nav-blocks']       = () => location.hash = 'blocks';
    window.__spp_handlers['nav-modules']      = () => location.hash = 'modules';
}

/**
 * Sets the document title and meta description via SPPEX.Helmet.
 * @param {string} title - The page title to set.
 * @param {string} [description] - Optional meta description.
 */
export function setPageMeta(title, description) {
    if (typeof SPPEX !== 'undefined' && SPPEX.Helmet) {
        SPPEX.Helmet.set({
            title: `${title} | Lekhak CMS`,
            meta: description ? { description } : {}
        });
    } else {
        document.title = `${title} | Lekhak CMS`;
    }
}

/**
 * Renders breadcrumbs HTML for the current view.
 * @param {string} currentPage - The name of the current page.
 * @returns {string} HTML string for breadcrumbs.
 */
export function renderBreadcrumbs(currentPage) {
    if (typeof SPPEX !== 'undefined' && SPPEX.Breadcrumbs) {
        return SPPEX.Breadcrumbs.render([
            { name: 'Home', url: '#lekhak' },
            { name: currentPage }
        ]);
    }
    return `<div style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
        <a href="#lekhak" style="color:var(--primary);text-decoration:none;">Home</a>
        <span style="margin:0 6px;color:#ccc;">/</span>
        <span>${currentPage}</span>
    </div>`;
}
