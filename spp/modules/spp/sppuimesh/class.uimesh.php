<?php
namespace SPPMod\SPPUIMesh;

use SPP\ViewController;

/**
 * Class UIMeshCompositor
 * 
 * The Backend DOM Compositor for the WebOS UI Mesh.
 * Fetches HTML from Guest Apps (e.g. WordPress, Magento) and extracts 
 * specific components to be stitched into the SPA.
 */
class UIMeshCompositor extends ViewController
{
    /**
     * Renders a unified SPP shell wrapped around an extracted DOM fragment.
     * Used for initial Server-Side Rendering (SSR) to fix SEO and event trapping.
     */
    public function renderSsrPage(string $appAlias, string $path, string $selector = 'body')
    {
        $html = $this->fetchAppHtml($appAlias, $path);
        $fragment = $this->extractDomFragment($html, $selector);
        
        // Serve a fully rendered HTML page with the fragment pre-injected
        header('Content-Type: text/html');
        echo '<!DOCTYPE html><html><head><title>SPP WebOS - ' . htmlspecialchars($appAlias) . '</title>';
        echo '<script src="/sppuimesh.js" defer></script>';
        echo '</head><body>';
        echo '<div id="spp-uimesh-container" data-ssr-loaded="true">';
        echo $fragment; // Pre-rendered for SEO crawlers!
        echo '</div>';
        echo '</body></html>';
        exit;
    }

    /**
     * Returns ONLY the bare fragment. Used by the JS router during client-side HTMX navigation.
     */
    public function renderFragment(string $appAlias, string $path, string $selector = 'body')
    {
        // 1. Fetch full HTML from the target app locally via the Mesh
        $html = $this->fetchAppHtml($appAlias, $path);
        
        // 2. Parse HTML and extract the requested selector (e.g. '#content')
        $fragment = $this->extractDomFragment($html, $selector);

        // 3. Return the bare fragment for the Frontend Router (sppuimesh.js)
        header('Content-Type: text/html');
        echo $fragment;
        exit;
    }

    private function fetchAppHtml(string $appAlias, string $path): string
    {
        // In reality, this would use local_path Bootstrapping or HTTP to the guest app.
        // Mocked for architectural demonstration.
        return '<html><head><style>h1 { color: red; }</style></head><body><div id="content"><h1>Welcome to ' . htmlspecialchars($appAlias) . '</h1><p>Dynamic Content loaded from ' . htmlspecialchars($path) . '</p></div></body></html>';
    }

    private function extractDomFragment(string $html, string $selector): string
    {
        // Very basic mock extraction. In production, we'd use DOMDocument.
        if (preg_match('/<div id="content">(.*?)<\/div>/s', $html, $matches)) {
            return $matches[0];
        }
        return '<div>Error extracting DOM fragment</div>';
    }
}
