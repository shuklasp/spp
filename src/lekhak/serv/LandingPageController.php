<?php
namespace App\Lekhak\Serv;

use SPPMod\Lekhak\Core\LandingPage;
use SPPMod\Lekhak\Core\LekhakNode;

/**
 * Class LandingPageController
 * Handles frontend rendering of landing pages and nodes.
 */
class LandingPageController
{
    public function index()
    {
        $page = LandingPage::getDefault();
        
        if ($page) {
            return $this->renderLandingPage($page);
        }

        // Fallback to default index view
        return $this->render("index");
    }

    public function show($slug = null)
    {
        // 1. Try finding a landing page by alias or ID
        $page = null;
        if (is_numeric($slug)) {
            $page = LandingPage::find_one(['id' => $slug]);
        }
        if (!$page) {
            $page = LandingPage::find_one(['alias' => $slug]);
        }
        if ($page) {
            return $this->renderLandingPage($page);
        }

        // 2. Fallback to generic Lekhak node
        $node = null;
        if (is_numeric($slug)) {
            $node = LekhakNode::find_one(['id' => $slug]);
        }
        if (!$node) {
            $node = LekhakNode::find_one(['alias' => $slug]);
        }
        if ($node) {
            try {
                return $this->render("node", ['node' => $node]);
            } catch (\Exception $e) {
                // Return beautiful responsive inline fallback presentation if template is absent
                $title = htmlspecialchars($node->title ?? 'Untitled Node');
                $body = $node->body ?? '<p>No content specified.</p>';
                return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$title} - Live Preview</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 3rem; }
        .preview-card { max-width: 800px; margin: 0 auto; background: #fff; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        h1 { margin-top: 0; color: #1e293b; font-size: 2.2rem; }
        .meta { font-size: 0.85rem; color: #64748b; margin-bottom: 2rem; pb: 1rem; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="preview-card">
        <span style="background:#e0e7ff; color:#4f46e5; font-size:0.75rem; font-weight:bold; padding:4px 8px; border-radius:4px;">LIVE PREVIEW NODE</span>
        <h1>{$title}</h1>
        <div class="meta">Rendered dynamically via application preview runtime engine</div>
        <div>{$body}</div>
    </div>
</body>
</html>
HTML;
            }
        }

        // 3. Final Fallback: Graceful 404
        return $this->render("404", [
            'title' => 'Content Not Found',
            'message' => "The resource or preview entity '{$slug}' could not be resolved in the current workspace.",
            'slug' => $slug
        ]);
    }

    protected function renderLandingPage(LandingPage $page)
    {
        $blocks = $page->getBlocks();
        $regions = [];
        foreach ($blocks as $block) {
            $r = $block->region ?: 'main';
            $regions[$r][] = $block;
        }
        
        return $this->render("landing-page", [
            'page' => $page,
            'blocks' => $blocks,
            'regions' => $regions
        ]);
    }

    protected function render($view, $data = [])
    {
        $renderer = \SPPMod\Lekhak\Core\Renderer::getInstance();
        $appRoot = \SPP\App::getBaseUrl('lekhak');
        
        $data['web_root'] = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $data['app_root'] = $appRoot;
        $data['base_url'] = $appRoot;
        $data['admin_root'] = $appRoot . '/admin';
        $data['view_name'] = $view;
        
        
        return $renderer->render($view, $data);
    }
}
