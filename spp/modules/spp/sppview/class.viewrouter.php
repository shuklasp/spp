<?php

namespace SPPMod\SPPView;

use SPP\SPPGlobal;
use SPP\SPPException;
use SPPMod\SPPRouter\SPPRouter;

class ViewRouter
{
    public static function showPage($page = null, array $options = [])
    {
        $q = ($page !== null && $page !== '') ? $page : ($_GET['q'] ?? null);
        $pageData = [];
        $src = SPPRouter::getDefault('pagedir');

        if ($q === null || $q === '') {
            $def = SPPRouter::getDefault('home') ?: 'index';
            $pageData = SPPRouter::getPage($def);
            $q = $def;
        } else {
            $pageData = SPPRouter::getPage($q);
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
            $relUrl = ltrim($pageData['url'], '/\\');
            $legacyPath = ($src !== '') ? (rtrim(SPP_APP_DIR, '/\\') . SPP_DS . trim($src, '/\\') . SPP_DS . $relUrl) : null;
            $includePath = SPP_APP_DIR . SPP_DS . $relUrl;

            if ($legacyPath !== null && file_exists($legacyPath)) {
                include($legacyPath);
            } elseif (file_exists($includePath) && !self::isRootIndex($includePath)) {
                include($includePath);
            }
            return true;
        }

        SPPGlobal::set('page', $pageData);
        SPPGlobal::set('url', $pageData['url']);
        SPPGlobal::set('params', $pageData['params']);
        SPPGlobal::set('q', $q);
        SPPGlobal::set('numparams', count($pageData['params']));

        $filename = null;
        if ($pageData['url'] !== '') {
            $relUrl = ltrim($pageData['url'], '/\\');
            $srcPath = ($src !== '') ? (rtrim(SPP_APP_DIR, '/\\') . SPP_DS . trim($src, '/\\') . SPP_DS . $relUrl) : null;
            $rootPath = SPP_APP_DIR . SPP_DS . $relUrl;

            if ($srcPath !== null && file_exists($srcPath)) {
                $filename = $srcPath;
            } elseif (file_exists($rootPath) && !self::isRootIndex($rootPath)) {
                $filename = $rootPath;
            } elseif ($srcPath !== null) {
                $filename = $srcPath;
            } else {
                $filename = $rootPath;
            }
        }

        if ($filename !== null && self::isRootIndex($filename)) {
            throw new SPPException('Router safety: Infinite recursion detected. Please check your "pagedir" setting in pages.yml.');
        }

        // --- Controller Execution ---
        if (isset($pageData['controller'])) {
            $parts = explode('@', $pageData['controller']);
            $class = '\\' . ltrim(preg_replace('/\\\\+/', '\\', $parts[0]), '\\');
            $method = $parts[1] ?? 'index';

            if (!class_exists($class)) {
                $altClass = ltrim($class, '\\');
                if (class_exists($altClass)) {
                    $class = $altClass;
                }
            }

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

                if (class_exists('\\SPP\\Core\\Container')) {
                    try {
                        $controller = \SPP\Core\Container::getInstance()->get($class);
                    } catch (\Throwable $e) {
                        $controller = new $class();
                    }
                } else {
                    $controller = new $class();
                }
                if (method_exists($controller, $method)) {
                    $params = $pageData['params'] ?? [];
                    $namedParams = $pageData['named_params'] ?? [];
                    
                    if (property_exists($controller, 'routeParams')) {
                        $controller->routeParams = $namedParams;
                    }

                    try {
                        $refMethod = new \ReflectionMethod($controller, $method);
                        $finalParams = [];
                        $positionalIndex = 0;
                        
                        foreach ($refMethod->getParameters() as $refParam) {
                            $paramName = $refParam->getName();
                            $paramType = $refParam->getType();
                            $className = null;
                            if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
                                $className = $paramType->getName();
                            }

                            // 1. Dependency injection for FormRequest with automatic validation
                            if ($className && is_subclass_of($className, '\\SPPMod\\SPPView\\FormRequest')) {
                                /** @var \SPPMod\SPPView\FormRequest $formRequest */
                                $formRequest = new $className();
                                if (!$formRequest->authorize()) {
                                    $formRequest->failedAuthorization();
                                    $filename = null;
                                    return true;
                                }
                                $valResult = $formRequest->validate();
                                if (!$valResult->isValid()) {
                                    $formRequest->failedValidation($valResult);
                                    $filename = null;
                                    return true;
                                }
                                $finalParams[] = $formRequest;
                                continue;
                            }

                            // 2. Dependency injection for SPPRequest
                            if ($className && (is_subclass_of($className, '\\SPP\\SPPRequest') || $className === 'SPP\\SPPRequest' || $className === 'SPPMod\\SPPView\\SPPRequest' || is_subclass_of($className, '\\SPPMod\\SPPView\\SPPRequest'))) {
                                $finalParams[] = new $className();
                                continue;
                            }

                            // 2. Automatic Route Model Binding for SPPEntity subclasses
                            if ($className && (is_subclass_of($className, '\\SPPMod\\SPPDB\\SPPEntity') || is_subclass_of($className, '\\SPPMod\\SPPEntity\\SPPEntity'))) {
                                $idVal = $namedParams[$paramName] ?? ($namedParams[$paramName . '_id'] ?? ($namedParams['id'] ?? ($params[$positionalIndex] ?? null)));
                                if ($idVal !== null && $idVal !== '') {
                                    $entity = $className::find($idVal);
                                    if ($entity === null && !$refParam->allowsNull()) {
                                        throw new \SPPMod\SPPDB\EntityNotFoundException("Entity {$className} with ID '{$idVal}' not found.");
                                    }
                                    $finalParams[] = $entity;
                                    $positionalIndex++;
                                    continue;
                                } elseif ($refParam->allowsNull()) {
                                    $finalParams[] = null;
                                    $positionalIndex++;
                                    continue;
                                }
                            }

                            // 3. Named route params or positional fallback
                            if (array_key_exists($paramName, $namedParams)) {
                                $finalParams[] = $namedParams[$paramName];
                            } elseif (array_key_exists($positionalIndex, $params)) {
                                $finalParams[] = $params[$positionalIndex];
                            } else {
                                $finalParams[] = $refParam->isDefaultValueAvailable() ? $refParam->getDefaultValue() : null;
                            }
                            $positionalIndex++;
                        }
                        $params = $finalParams;
                    } catch (\ReflectionException $e) {
                        // Fallback to basic positional binding if reflection fails
                    }

                    // Lifecycle hook: beforeAction
                    if (method_exists($controller, 'beforeAction')) {
                        $hookResult = $controller->beforeAction($method, $params);
                        if ($hookResult !== null) {
                            if ($hookResult instanceof \SPP\Response || (class_exists('\\SPPMod\\SPPView\\SPPResponse') && $hookResult instanceof \SPPMod\SPPView\SPPResponse)) {
                                $hookResult->send();
                            } elseif ($hookResult instanceof \Stringable) {
                                echo (string)$hookResult;
                            } elseif (is_string($hookResult)) {
                                echo $hookResult;
                            }
                            $filename = null;
                            return true;
                        }
                    }

                    $result = call_user_func_array([$controller, $method], $params);

                    // Lifecycle hook: afterAction
                    if (method_exists($controller, 'afterAction')) {
                        $controller->afterAction($method, $result);
                    }

                    if ($result instanceof \SPP\Response || (class_exists('\\SPPMod\\SPPView\\SPPResponse') && $result instanceof \SPPMod\SPPView\SPPResponse)) {
                        $result->send();
                        $filename = null;
                        return true;
                    } elseif (is_string($result)) {
                        echo $result;
                        $filename = null;
                        return true;
                    } elseif ($result instanceof \Stringable) {
                        echo (string)$result;
                        $filename = null;
                        return true;
                    } elseif ($result instanceof \JsonSerializable) {
                        if (!headers_sent()) {
                            header('Content-Type: application/json; charset=utf-8');
                        }
                        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $filename = null;
                        return true;
                    } elseif (is_array($result)) {
                        if (isset($result['view']) && isset($result['data']) && is_string($result['view']) && is_array($result['data']) && method_exists($controller, 'render')) {
                            echo $controller->render($result['view'], $result['data']);
                        } else {
                            if (!headers_sent()) {
                                header('Content-Type: application/json; charset=utf-8');
                            }
                            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        $filename = null;
                        return true;
                    } elseif ($result !== null) {
                        if (!headers_sent()) {
                            header('Content-Type: application/json; charset=utf-8');
                        }
                        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $filename = null;
                        return true;
                    } else {
                        if (headers_sent() || ob_get_length() > 0) {
                            $filename = null;
                            return true;
                        }
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

    /**
     * Checks if a resolved path targets the root front controller index.php.
     */
    public static function isRootIndex(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }
        $normPath = str_replace('\\', '/', $path);
        $normRoot = str_replace('\\', '/', SPP_APP_DIR) . '/index.php';
        if ($normPath === $normRoot) {
            return true;
        }
        $rp = realpath($path);
        $rr = realpath(SPP_APP_DIR . '/index.php');
        return ($rp !== false && $rr !== false && $rp === $rr);
    }
}
