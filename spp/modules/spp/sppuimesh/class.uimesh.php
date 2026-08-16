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
        ob_start();
        include __DIR__ . '/uimesh_ssr.php';
        echo ob_get_clean();
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
        // Mocked for architectural demonstration.
        ob_start();
        include __DIR__ . '/uimesh_mock_app.php';
        return ob_get_clean();
    }

    private function extractDomFragment(string $html, string $selector): string
    {
        if (extension_loaded('dom')) {
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new \DOMXPath($dom);
            
            $xpathQuery = '//*';
            if (strpos($selector, '#') === 0) {
                $id = substr($selector, 1);
                $xpathQuery = "//*[@id='{$id}']";
            }
            
            $nodes = $xpath->query($xpathQuery);
            if ($nodes->length > 0) {
                return $dom->saveHTML($nodes->item(0));
            }
        } else {
            // Fallback to strict regex parsing
            $id = ltrim($selector, '#');
            if (preg_match('/<div[^>]*id="' . preg_quote($id, '/') . '"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
                return $matches[0];
            }
        }
        
        ob_start();
        include __DIR__ . '/uimesh_error.php';
        return ob_get_clean();
    }
}
