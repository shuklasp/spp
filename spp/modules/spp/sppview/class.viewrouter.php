<?php

namespace SPPMod\SPPView;

use SPP\SPPGlobal;
use SPP\SPPException;
use SPPMod\SPPRouter\SPPRouter;

class ViewRouter
{
    public static function showPage($page = null, array $options = [])
    {
        $q = isset($_GET['q']) ? $_GET['q'] : null;
        $pageData = [];
        $src = SPPRouter::getDefault('pagedir');

        if ($q == null) {
            $def = SPPRouter::getDefault('home');
            $pageData = SPPRouter::getPage($def);
        } else {
            $pageData = SPPRouter::getPage();
        }

        $isSppUx = (\SPP\App::getApp()->type === 'sppux');
        $doAugment = $options['augment'] ?? ($isSppUx ?: (bool) (\SPP\Module::getConfig('auto_page_augmentation', 'spphtml') ?? \SPP\Module::getConfig('auto_page_augmentation', 'sppview') ?? true));
        $doInjectJs = $options['inject_js'] ?? ($isSppUx ?: (bool) (\SPP\Module::getConfig('auto_js_injection', 'spphtml') ?? \SPP\Module::getConfig('auto_js_injection', 'sppview') ?? true));

        if ($pageData['special'] == 1) {
            if (isset($pageData['method'])) {
                $method = $pageData['method'];
                $context = $pageData['context'] ?? [];
                echo \SPPMod\SPPRouter\SPPRouter::$method($q ?? '', $context);
                return true;
            }
            $includePath = SPP_APP_DIR . SPP_DS . ltrim($pageData['url'], '/\\');
            if (file_exists($includePath)) {
                include($includePath);
            } else {
                $legacyPath = SPP_APP_DIR . SPP_DS . $src . SPP_DS . ltrim($pageData['url'], '/\\');
                if (file_exists($legacyPath)) {
                    include($legacyPath);
                }
            }
            return true;
        }

        SPPGlobal::set('page', $pageData);
        SPPGlobal::set('url', $pageData['url']);
        SPPGlobal::set('params', $pageData['params']);
        SPPGlobal::set('q', $q);
        SPPGlobal::set('numparams', count($pageData['params']));

        $filename = ($pageData['url'] !== '') ? (SPP_APP_DIR . SPP_DS . ltrim($pageData['url'], '/\\')) : null;
        if ($filename !== null && !file_exists($filename)) {
            $srcPath = rtrim(SPP_APP_DIR, '/\\') . SPP_DS . trim($src, '/\\') . SPP_DS . ltrim($pageData['url'], '/\\');
            if (file_exists($srcPath)) {
                $filename = $srcPath;
            }
        }

        if ($filename !== null && $filename === SPP_APP_DIR . SPP_US . 'index.php') {
            throw new SPPException('Router safety: Infinite recursion detected. Please check your "pagedir" setting in pages.yml.');
        }

        // --- Controller Execution ---
        if (isset($pageData['controller'])) {
            $parts = explode('@', $pageData['controller']);
            $class = $parts[0];
            $method = $parts[1] ?? 'index';

            if (class_exists($class)) {
                if (!empty($pageData['middleware']) && is_array($pageData['middleware'])) {
                    foreach ($pageData['middleware'] as $mwClass) {
                        if (class_exists($mwClass)) {
                            $mwInstance = new $mwClass();
                            if (method_exists($mwInstance, 'handle')) {
                                $mwInstance->handle();
                            }
                        }
                    }
                }

                $controller = new $class();
                if (method_exists($controller, $method)) {
                    $params = $pageData['params'] ?? [];
                    $result = call_user_func_array([$controller, $method], $params);
                    if (is_string($result)) {
                        echo $result;
                        $filename = null;
                    }
                }
            }
        }

        // --- Multi-Engine Paradigm Router ---
        if ($filename && \SPP\Module::isEnabled('drishyam')) {
            $viewName = str_replace(['/', '\\'], '.', preg_replace('/\.html$/', '', ltrim($pageData['url'], '/\\')));

            $app = \SPP\App::getApp();
            $pageData['base_url'] = \SPP\App::getBaseUrl($app->getName());
            if ($pageData['base_url'] === '') {
                $pageData['base_url'] = '/';
            }
            $pageData['admin_url'] = rtrim($pageData['base_url'], '/') . '/spp/admin/';

            if (class_exists(\SPPMod\Drishyam\SPPTwig::class)) {
                try {
                    $html = \SPPMod\Drishyam\SPPTwig::render($viewName, ['pageData' => $pageData]);
                    echo $html;
                    return true;
                } catch (\Twig\Error\LoaderError $e) {
                } catch (\Exception $e) {
                    if (strpos(strtolower($e->getMessage()), 'unable to find template') === false) {
                        throw $e;
                    }
                }
            }

            if (class_exists(\SPPMod\Drishyam\SPPBlade::class)) {
                try {
                    $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
                    $html = $blade->renderInstance($viewName, ['pageData' => $pageData]);
                    if (strpos(strtolower($html), 'blade error: template not found') !== false || strpos(strtolower($html), 'blade error: template does not exist') !== false || strpos(strtolower($html), 'not exist') !== false) {
                        // Blade template not found. Fall through to native PHP view rendering.
                    } else {
                        echo $html;
                        return true;
                    }
                } catch (\Exception $e) {
                    if (strpos(strtolower($e->getMessage()), 'not found') === false && strpos(strtolower($e->getMessage()), 'does not exist') === false && strpos(strtolower($e->getMessage()), 'not exist') === false) {
                        throw $e;
                    }
                }
            }
        }

        if ($filename && file_exists($filename) && is_file($filename)) {
            $app = \SPP\App::getApp();
            $pageData['base_url'] = \SPP\App::getBaseUrl($app->getName());
            if ($pageData['base_url'] === '') {
                $pageData['base_url'] = '/';
            }
            $pageData['admin_url'] = rtrim($pageData['base_url'], '/') . '/spp/admin/';

            if ($doAugment) {
                ob_start();
            }

            $preParams = ['pageData' => $pageData, 'filename' => $filename];
            $evtPreParams = new \SPP\EventParams($preParams);
            \SPP\SPPEvent::fireEvent('event_spp_view_pre_render', $evtPreParams);
            $preParams = $evtPreParams->getPayload();
            $pageData = $preParams['pageData'];
            $filename = $preParams['filename'];

            ViewRenderer::renderFile($filename, $pageData);

            if ($doAugment) {
                $html = ob_get_clean();
                $postParams = ['html' => $html, 'pageData' => $pageData];
                $evtPostParams = new \SPP\EventParams($postParams);
                \SPP\SPPEvent::fireEvent('event_spp_view_post_render', $evtPostParams);
                $html = $evtPostParams->getPayload()['html'];
                $appName = \SPP\Scheduler::getContext();

                if (\SPP\Module::isEnabled('sppdebug') || (defined('SPP_DEBUG') && SPP_DEBUG)) {
                    $debugData = \SPP\Core\Debug::getData();
                    $debugJson = htmlspecialchars(json_encode($debugData), ENT_QUOTES, 'UTF-8');
                    $html .= "<div id='spp-debug-bar' data-metrics='{$debugJson}'></div>";
                    ViewAssetManager::addJsIncludeFile('/school1/res/spp/js/spp-debug.js');
                    ViewAssetManager::addCssIncludeFile('/school1/res/spp/css/spp-debug.css');
                }

                $jsList = ViewAssetManager::getJsFiles();
                $cssList = ViewAssetManager::getCssFiles();

                if (\SPP\SPPConfig::get('system.bundle_assets', false)) {
                    $jsList = [AssetOrchestrator::orchestrate($jsList, 'js')];
                    $cssList = [AssetOrchestrator::orchestrate($cssList, 'css')];
                }

                $augParams = ['html' => $html, 'js_list' => $jsList, 'css_list' => $cssList];
                $evtAugParams = new \SPP\EventParams($augParams);
                \SPP\SPPEvent::fireEvent('event_spp_view_before_augment', $evtAugParams);
                $augParams = $evtAugParams->getPayload();
                $html = $augParams['html'];
                $jsList = $augParams['js_list'];
                $cssList = $augParams['css_list'];

                $finalHtml = FormAugmentor::augment($html, $jsList, $cssList);

                if (getenv('APP_ENV') === 'local') {
                    $finalHtml = str_replace('</body>', '<script src="/spp-hmr.js"></script></body>', $finalHtml);
                }

                $renderParams = [
                    'html' => $finalHtml,
                    'pageData' => $pageData,
                    'theme' => $app->getAppConf('theme')
                ];
                $evtRenderParams = new \SPP\EventParams($renderParams);
                \SPP\SPPEvent::fireEvent('event_spp_view_render_theme', $evtRenderParams);
                $finalHtml = $evtRenderParams->getPayload()['html'];

                echo $finalHtml;

                ViewAssetManager::includeJqueryDynamic();
                ViewAssetManager::includeCSSFilesDynamic();
                return true;
            }

            ViewAssetManager::includeJqueryDynamic();
            return true;
        }

        return false;
    }
}
