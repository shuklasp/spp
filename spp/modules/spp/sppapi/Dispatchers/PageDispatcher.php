<?php
declare(strict_types=1);

namespace SPPMod\SPPAPI\Dispatchers;

use SPPMod\SppApi\SPPAjax;

class PageDispatcher
{
    public static function dispatch(): void
    {
        $q = isset($_GET['q']) ? trim($_GET['q']) : null;

        $page = \SPPMod\SPPView\Pages::getPage($q);

        if (empty($page['url'])) {
            SPPAjax::respond('error', ['message' => 'Page not found.'], 404);
        }

        $pageDir = \SPP\Module::getConfig('spa_page_dir', 'sppajax') ?: '/src/pages';
        $filename = SPP_APP_DIR . $pageDir . '/' . ltrim($page['url'], '/');

        // Resolve symlinks and prevent path traversal
        $realBase = realpath(SPP_APP_DIR . $pageDir);
        $realFile = realpath($filename);

        if ($realFile === false || !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
            SPPAjax::respond('error', ['message' => 'Forbidden.'], 403);
        }

        if (!file_exists($realFile) || !is_file($realFile)) {
            SPPAjax::respond('error', ['message' => 'Page file not found.'], 404);
        }

        // Capture the page output
        ob_start();
        include $realFile;
        $html = ob_get_clean();

        SPPAjax::respond('ok', [
            'html' => $html,
            'title' => $page['title'] ?? ''
        ]);
    }
}
