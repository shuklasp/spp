/**
 * SPP UI Mesh - Frontend Router
 * 
 * Intercepts navigation to Guest Apps (WordPress, Magento) and dynamically 
 * fetches their DOM fragments, injecting them into the SPP unified shell 
 * using Shadow DOM to prevent CSS collision.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. HYDRATION: Check if the page was Server-Side Rendered
    const meshContainer = document.getElementById('spp-uimesh-container');
    if (meshContainer && meshContainer.getAttribute('data-ssr-loaded') === 'true') {
        console.log("UI Mesh hydrated from SSR initial load.");
        // We leave the HTML in the normal Light DOM so SEO crawlers can read it,
        // and global event listeners attached to body will work for the initial page!
    }

    // Intercept clicks on links pointing to guest apps
    document.body.addEventListener('click', async (e) => {
        const link = e.target.closest('a[data-uimesh-target]');
        if (!link) return;

        e.preventDefault();
        
        const appAlias = link.getAttribute('data-uimesh-target');
        const path = link.getAttribute('href');
        
        await navigateUiMesh(appAlias, path);
    });
});

async function navigateUiMesh(appAlias, path) {
    const meshContainer = document.getElementById('spp-uimesh-container');
    if (!meshContainer) return;
    
    // Show loading state
    meshContainer.innerHTML = '<div class="spp-loader">Loading ' + appAlias + '...</div>';
    
    try {
        // Fetch the HTML fragment from the Backend DOM Compositor
        const response = await fetch(`/api/mesh/fragment?app=${appAlias}&path=${encodeURIComponent(path)}`);
        const html = await response.text();
        
        // 1. Clear the container completely to avoid any subsequent page corruption
        meshContainer.innerHTML = '';
        meshContainer.removeAttribute('data-ssr-loaded'); // We are no longer in SSR mode
        
        // 2. SHADOW DOM ISOLATION (Clean Slate)
        // By creating a brand new host element on every navigation, we guarantee
        // that no CSS or state from the previous page bleeds into the new one!
        const host = document.createElement('div');
        const shadowRoot = host.attachShadow({ mode: 'open' });
        
        // 3. Inject the HTML into the new shadow boundary
        shadowRoot.innerHTML = html;
        
        // 4. Mount the shadow host into the DOM
        meshContainer.appendChild(host);
        
        // 5. Update browser history API
        window.history.pushState({ app: appAlias, path: path }, '', path);
        
    } catch (err) {
        meshContainer.innerHTML = '<div class="spp-error">Failed to load UI Mesh component.</div>';
        console.error("UI Mesh Error:", err);
    }
}
