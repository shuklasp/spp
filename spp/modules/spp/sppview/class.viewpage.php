<?php

namespace SPPMod\SPPView;

require_once __DIR__ . '/class.viewtag.php';
require_once __DIR__ . '/class.sppformelement.php';
require_once __DIR__ . '/class.viewform.php';
require_once __DIR__ . '/formelements/classes.formelements.php';

use SPP\SPPException;
use SPP\SPPGlobal;
use SPPMod\SPPView\Pages;
use SPPMod\SPPView\ViewForm;

\SPP\SPPEvent::registerEvent('event_spp_include_css_files');
\SPP\SPPEvent::registerEvent('event_spp_include_js_files');
\SPP\SPPEvent::registerEvent('event_spp_process_xml_form');
\SPP\SPPEvent::registerEvent('event_spp_process_xml_form_element');
\SPP\SPPEvent::registerEvent('event_spp_process_xml_form_validation');
\SPP\SPPEvent::registerEvent('event_spp_view_render_theme');
\SPP\SPPEvent::registerEvent('event_spp_view_pre_render');
\SPP\SPPEvent::registerEvent('event_spp_view_post_render');
\SPP\SPPEvent::registerEvent('event_spp_view_before_augment');
\SPP\SPPEvent::registerEvent('event_spp_view_render');

/**
 * class ViewPage
 *
 * Defines a HTML page in SPP.
 *
 * @author Satya Prakash Shukla
 */
class ViewPage extends \SPP\SPPObject
{
    protected static $pageid;
    protected static $jsincludelist = [];
    protected static $jscontentlist = [];
    protected static $cssincludelist = [];
    protected static $csscontentlist = [];
    protected static $formslist = [];
    protected static $elementslist = [];
    protected static $pagetitle;
    protected static $pagedescription;
    protected static $pagekeywords;
    protected static $pageauthor;
    protected static $pagecontent;
    protected static $pageheader;
    protected static $pagefooter;
    protected static $pagehead;
    protected static $pagebody;
    protected static $pagemeta;
    protected static $xml;
    protected static $validators = [];

    /**
     * Function setPageId($id)
     * Sets the page id.
     *
     * @param string $id
     * @return void
     */
    public static function setPageId($id)
    {
        self::$pageid = $id;
    }

    public static function getJsFiles()
    {
        return self::$jsincludelist;
    }

    public static function getCssFiles()
    {
        return self::$cssincludelist;
    }

    /**
     * Main entry point for rendering a page based on the current URL or provided page ID.
     */
    public static function showPage($page = null, array $options = [])
    {
        $q = isset($_GET['q']) ? $_GET['q'] : null;
        $pageData = [];
        $src = Pages::getDefault('pagedir');

        if ($q == null) {
            $def = Pages::getDefault('home');
            $pageData = Pages::getPage($def);
        } else {
            $pageData = Pages::getPage();
        }

        // Configuration defaults - Force true for sppux apps to ensure the loader and runtime always initialize
        $isSppUx = (\SPP\App::getApp()->type === 'sppux');
        $doAugment = $options['augment'] ?? ($isSppUx ?: (bool)(\SPP\Module::getConfig('auto_page_augmentation', 'spphtml') ?? \SPP\Module::getConfig('auto_page_augmentation', 'sppview') ?? true));
        $doInjectJs = $options['inject_js'] ?? ($isSppUx ?: (bool)(\SPP\Module::getConfig('auto_js_injection', 'spphtml') ?? \SPP\Module::getConfig('auto_js_injection', 'sppview') ?? true));

        if ($pageData['special'] == 1) {
            if (isset($pageData['method'])) {
                $method = $pageData['method'];
                $context = $pageData['context'] ?? [];
                echo \SPPMod\SPPView\Pages::$method($q ?? '', $context);
                return true;
            }
            $includePath = SPP_APP_DIR . SPP_DS . ltrim($pageData['url'], '/\\');
            if (file_exists($includePath)) {
                include($includePath);
            } else {
                // Fallback to legacy root inclusion (safety)
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

        // Safety check: Prevent infinite recursion if pagedir resolution fails or points to root index
        if ($filename !== null && $filename === SPP_APP_DIR . SPP_US . 'index.php') {
            throw new \SPP\SPPException('Router safety: Infinite recursion detected. Please check your "pagedir" setting in pages.yml.');
        }

        // --- Controller Execution ---
        if (isset($pageData['controller'])) {
            $parts = explode('@', $pageData['controller']);
            $class = $parts[0];
            $method = $parts[1] ?? 'index';

            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $method)) {
                    $params = $pageData['params'] ?? [];
                    $result = call_user_func_array([$controller, $method], $params);
                    if (is_string($result)) {
                        echo $result;
                        // If we have a controller result, we might not need to render the file
                        $filename = null;
                    }
                }
            }
        }

        if ($filename && file_exists($filename) && is_file($filename)) {
            // Inject common variables for templates
            $app = \SPP\App::getApp();
            $pageData['base_url'] = rtrim((defined('APP_BASE_URI') ? APP_BASE_URI : ''), '/') . '/' . ltrim($app->base_url ?? '', '/');
            $pageData['base_url'] = rtrim($pageData['base_url'], '/');
            if ($pageData['base_url'] === '') {
                $pageData['base_url'] = '/';
            }

            $pageData['admin_url'] = rtrim($pageData['base_url'], '/') . '/spp/admin/';

            if ($doAugment) {
                ob_start();
            }

            $preParams = ['pageData' => &$pageData, 'filename' => $filename];
            \SPP\SPPEvent::fireEvent('event_spp_view_pre_render', $preParams);

            self::renderFile($filename, $pageData);

            if ($doAugment) {
                $html = ob_get_clean();
                $postParams = ['html' => &$html, 'pageData' => $pageData];
                \SPP\SPPEvent::fireEvent('event_spp_view_post_render', $postParams);
                $appName = \SPP\Scheduler::getContext();

                // 1. Scan for <php-comp name="X" ... /> tags
                $html = preg_replace_callback('/<php-comp\s+name="([^"]+)"([^>]*)\/?>/i', function ($matches) use ($appName) {
                    $compName = $matches[1];
                    $attrs = $matches[2];

                    // Parse attributes into a state object
                    $state = [];
                    if (preg_match_all('/([a-zA-Z0-9_-]+)="([^"]+)"/', $attrs, $attrMatches)) {
                        for ($i = 0; $i < count($attrMatches[0]); $i++) {
                            $state[$attrMatches[1][$i]] = $attrMatches[2][$i];
                        }
                    }

                    // Resolve JS Inclusion
                    self::resolveTieredJS($appName, $compName);

                    $jsonState = htmlspecialchars(json_encode($state), ENT_QUOTES, 'UTF-8');
                    return "<div data-spp-component=\"{$compName}\" data-state='{$jsonState}'></div>";
                }, $html);

                // 4. Inject Debug Bar (Phase 5 Evolution)
                if (\SPP\Module::isEnabled('sppdebug') || (defined('SPP_DEBUG') && SPP_DEBUG)) {
                    $debugData = \SPP\Core\Debug::getData();
                    $debugJson = htmlspecialchars(json_encode($debugData), ENT_QUOTES, 'UTF-8');
                    $html .= "<div id='spp-debug-bar' data-metrics='{$debugJson}'></div>";
                    self::addJsIncludeFile('/school1/res/spp/js/spp-debug.js');
                    self::addCssIncludeFile('/school1/res/spp/css/spp-debug.css');
                }

                // Final Augmentation
                $jsList = self::$jsincludelist;
                $cssList = self::$cssincludelist;

                if (\SPP\SPPConfig::get('system.bundle_assets', false)) {
                    $jsList = [AssetOrchestrator::orchestrate($jsList, 'js')];
                    $cssList = [AssetOrchestrator::orchestrate($cssList, 'css')];
                }

                $augParams = ['html' => &$html, 'js_list' => &$jsList, 'css_list' => &$cssList];
                \SPP\SPPEvent::fireEvent('event_spp_view_before_augment', $augParams);

                $finalHtml = FormAugmentor::augment($html, $jsList, $cssList);

                // HMR Injection for Developer Heaven
                if (getenv('APP_ENV') === 'local') {
                    $finalHtml = str_replace('</body>', '<script src="/spp-hmr.js"></script></body>', $finalHtml);
                }

                // Event-Driven Theming: Allow modules to wrap output in a theme
                $renderParams = [
                    'html'     => &$finalHtml,
                    'pageData' => $pageData,
                    'theme'    => $app->getAppConf('theme')
                ];
                \SPP\SPPEvent::fireEvent('event_spp_view_render_theme', $renderParams);

                echo $finalHtml;

                self::includeJqueryDynamic();
                self::includeCSSFilesDynamic();
                return true;
            }

            self::includeJqueryDynamic();
            return true;
        }

        return false;
    }

    /**
     * Renders a file using the appropriate engine based on extension.
     * Now overridable via event_spp_view_render.
     */
    private static function renderFile(string $filename, array $pageData): void
    {
        $params = ['filename' => $filename, 'pageData' => $pageData];
        \SPP\SPPEvent::fireEvent('event_spp_view_render', $params, 'DefaultViewRenderHandler');
    }

    /**
     * Tiered JS Resolution Logic:
     * 1. Static: Check if pre-built JS exists.
     * 2. Fallback: Use dynamic generator route via SPPAjax.
     */
    public static function resolveTieredJS(string $appName, string $compName): void
    {
        $staticPath = "res/apps/{$appName}/generated/{$compName}.js";
        if (file_exists(SPP_APP_DIR . '/' . $staticPath)) {
            self::addJsIncludeFile($staticPath);
        } else {
            // Priority 3: Fallback - Dynamic generation route
            self::addJsIncludeFile("?__js_comp={$compName}");
        }
    }

    public static function includeCSSFilesDynamic()
    {
        foreach (self::$cssincludelist as $cssfile) {
            self::includeCSSDynamic($cssfile);
        }
    }

    public static function includeJSDynamic($jsfile)
    {
        $path = $jsfile;
        $options = [];

        if (is_array($jsfile)) {
            $path = $jsfile['path'] ?? '';
            $options = $jsfile['options'] ?? [];
        }

        if ($path === '') {
            return;
        }

        $attrs = '';
        foreach ($options as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $key);
            if ($safeKey === '') {
                continue;
            }
            if ($value === true) {
                $attrs .= ' ' . $safeKey;
            } else {
                $attrs .= ' ' . $safeKey . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        if (!isset($options['type'])) {
            $attrs = ' type="text/javascript"' . $attrs;
        }

        echo '<script' . $attrs . ' src="' . htmlspecialchars((string) $path, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }

    public static function includeJSFilesDynamic()
    {
        foreach (self::$jsincludelist as $jsfile) {
            self::includeJSDynamic($jsfile);
        }
    }

    public static function includeCSSDynamic($cssfile)
    {
        echo '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars((string) $cssfile, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    }

    public static function includeJqueryDynamic()
    {
        // Modernized: Avoid document.write to prevent parser-blocking warnings.
        // We now use a standard script tag. If jQuery is already loaded by the app,
        // this will still load but jQuery handles multiple inclusions gracefully.
        // However, for SPP-UX apps, we recommend using addJsIncludeFile instead.
        echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>' . "\n";
    }

    public static function render($page = null)
    {
        $url = ($page != null) ? Pages::getPage($page)['url'] : null;
        $pageid = self::getPageId();
        echo self::getPageHeader();
        echo self::getPageHead();
        echo self::getPageMeta();
        echo self::getPageBody();
        echo self::getPageFooter();
    }

    /**
     * Function getPageId()
     * Gets the page id.
     *
     * @return string
     */
    public static function getPageId()
    {
        return self::$pageid;
    }

    /**
     * Function addValidator($validator)
     * Adds a validator to the list of validators.
     *
     * @param object $validator
     * @return void
     */
    public static function addValidator($validator)
    {
        self::$validators[] = $validator;
    }

    /**
     * Function getValidators()
     * Gets the list of validators.
     *
     * @return array
     */
    public static function getValidators()
    {
        return self::$validators;
    }

    /***** Getters and Setters *****/
    public static function setPageTitle($title)
    {
        self::$pagetitle = $title;
    }

    public static function getPageTitle()
    {
        return self::$pagetitle;
    }

    public static function setPageDescription($desc)
    {
        self::$pagedescription = $desc;
    }

    public static function getPageDescription()
    {
        return self::$pagedescription;
    }

    public static function setPageKeywords($keywords)
    {
        self::$pagekeywords = $keywords;
    }

    public static function getPageKeywords()
    {
        return self::$pagekeywords;
    }

    public static function setPageAuthor($author)
    {
        self::$pageauthor = $author;
    }

    public static function getPageAuthor()
    {
        return self::$pageauthor;
    }

    public static function setPageContent($content)
    {
        self::$pagecontent = $content;
    }

    public static function getPageContent()
    {
        return self::$pagecontent;
    }

    public static function setPageHeader($header)
    {
        self::$pageheader = $header;
    }

    public static function getPageHeader()
    {
        return self::$pageheader;
    }

    public static function setPageFooter($footer)
    {
        self::$pagefooter = $footer;
    }

    public static function getPageFooter()
    {
        return self::$pagefooter;
    }

    public static function setPageHead($head)
    {
        self::$pagehead = $head;
    }

    public static function getPageHead()
    {
        return self::$pagehead;
    }

    public static function setPageBody($body)
    {
        self::$pagebody = $body;
    }

    public static function getPageBody()
    {
        return self::$pagebody;
    }

    public static function setPageMeta($meta)
    {
        self::$pagemeta = $meta;
    }

    public static function getPageMeta()
    {
        return self::$pagemeta;
    }

    public static function getXML()
    {
        return self::$xml;
    }

    public static function setXML($xml)
    {
        self::$xml = $xml;
    }

    public static function getJsIncludeList()
    {
        return self::$jsincludelist;
    }

    public static function getCssIncludeList()
    {
        return self::$cssincludelist;
    }

    public static function getFormsList()
    {
        return self::$formslist;
    }

    public static function addJsIncludeFile($fpath, array $options = [])
    {
        $entry = ['path' => $fpath, 'options' => $options];
        foreach (self::$jsincludelist as $fl) {
            if (is_array($fl) && $fl['path'] == $fpath) {
                return false;
            }
            if ($fl == $fpath) {
                return false;
            }
        }
        self::$jsincludelist[] = $entry;
        return true;
    }

    public static function addCssIncludeFile($fpath)
    {
        foreach (self::$cssincludelist as $fl) {
            if ($fl == $fpath) {
                return false;
            }
        }
        self::$cssincludelist[] = $fpath;
        return true;
    }

    public static function addJsContent($content)
    {
        self::$jscontentlist[] = $content;
    }

    public static function addCssContent($content)
    {
        self::$csscontentlist[] = $content;
    }

    public static function addForm(ViewForm $form)
    {
        foreach (self::$formslist as $fl) {
            if ($fl == $form) {
                return false;
            }
        }
        self::$formslist[$form->getAttribute('id')] = $form;
        return true;
    }

    public static function processForms()
    {
        if (array_key_exists('__spp_form', $_POST)) {
            $formId = $_POST['__spp_form'];
            if (!array_key_exists($formId, self::$formslist)) {
                // throw new SPPException('Submitted form ID "' . htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') . '" is not registered on this page.');
                return; // Let controllers handle it
            }
            $callfunc = $formId . '_submitted';
            self::$formslist[$formId]->doValidation();
            if (function_exists($callfunc)) {
                $callfunc();
            }
        }
    }

    public static function readXMLFile($fl)
    {
        if (file_exists($fl)) {
            self::$xml = simplexml_load_file($fl);
            return true;
        } else {
            return false;
        }
    }

    public static function readFormFile($fl)
    {
        if (!file_exists($fl)) {
            return false;
        }

        $ext = strtolower(pathinfo($fl, PATHINFO_EXTENSION));
        if ($ext === 'yml' || $ext === 'yaml') {
            $data = \Symfony\Component\Yaml\Yaml::parseFile($fl);
            self::processFormArray($data);
            return true;
        } else {
            if (self::readXMLFile($fl)) {
                self::processXMLForm();
                return true;
            }
        }
        return false;
    }

    private static function wrapArray($item): array
    {
        if (!is_array($item)) {
            return [];
        }
        if (isset($item[0])) {
            return $item;
        }
        return [$item];
    }

    public static function processXMLForm()
    {
        $xml = self::$xml;
        $arr = \SPP\SPPUtils::xml2phpArray($xml);
        self::processFormArray($arr);
    }

    public static function processFormArray(array $arr)
    {
        if (!isset($arr['form'])) {
            return;
        }

        $forms = self::wrapArray($arr['form']);

        foreach ($forms as $form) {
            $fname = $form['name'] ?? 'unnamed_form';
            $faction = $form['action'] ?? '';
            $fid = $form['id'] ?? null;
            $fmethod = $form['method'] ?? 'post';

            $frm = new ViewForm($fname, $fmethod, $faction, $fid);
            self::$formslist[$fname] = $frm;

            if (isset($form['controls'])) {
                $controlsBlocks = self::wrapArray($form['controls']);
                foreach ($controlsBlocks as $cb) {
                    if (isset($cb['control'])) {
                        $controls = self::wrapArray($cb['control']);
                        foreach ($controls as $control) {
                            $cnt = self::createElementFromArray($control);
                            $frm->addElement($cnt);
                            self::addElement($cnt);
                        }
                    }
                }
            }

            if (class_exists('\SPPMod\SPPView\ViewValidator')) {
                if (isset($form['validations'])) {
                    $validationsBlocks = self::wrapArray($form['validations']);
                    foreach ($validationsBlocks as $vb) {
                        if (isset($vb['validation'])) {
                            $validations = self::wrapArray($vb['validation']);
                            foreach ($validations as $validation) {
                                self::validationsFromArray($frm, $validation);
                            }
                        }
                    }
                }
            }
        }
    }

    private static function validationsFromArray(ViewForm $form, array $arr)
    {
        $val = '';
        $type = $arr['type'];
        if (strpos($type, '\\') !== 0 && strpos($type, 'SPPMod\\SPPView\\') !== 0) {
            $type = __NAMESPACE__ . '\\' . $type;
        }

        if (array_key_exists('control', $arr)) {
            $val = new $type(self::$elementslist[$arr['control']]);
        } elseif (array_key_exists('controls', $arr)) {
            $ctrls = [];
            foreach ($arr['controls'] as $controls) {
                foreach ($controls['control'] as $control) {
                    $ctrls[] = self::$elementslist[$control['name']];
                }
            }
            $val = new $type($ctrls);
        } else {
            \SPP\SPPError::triggerDevError('Error reading validations from array');
        }
        if (array_key_exists('message', $arr)) {
            $form->addValidator($val, $arr['message']);
        } else {
            $form->addValidator($val);
        }
        if (array_key_exists('attach', $arr)) {
            foreach ($arr['attach'] as $attach) {
                $element = self::$elementslist[$attach['element']];
                $form->attachValidator($val, $element, $attach['event'], $attach['errorholder']);
            }
        }
    }

    private static function createElementFromArray($arr): ViewTag
    {
        require_once __DIR__ . '/class.sppformelement.php';
        require_once __DIR__ . '/formelements/classes.formelements.php';

        $type = $arr['type'];
        if (strpos($type, '\\') !== 0 && strpos($type, 'SPPMod\\SPPView\\') !== 0) {
            $type = __NAMESPACE__ . '\\' . $type;
        }

        if (!class_exists($type)) {
            throw new \SPP\SPPException("Form element class '{$type}' not found.");
        }

        $elem = new $type($arr['name']);
        $elem->readFromArray($arr);
        return $elem;
    }

    public static function addElement(\SPPMod\SPPView\ViewTag $ename)
    {
        foreach (self::$elementslist as $fl) {
            if ($fl == $ename) {
                return false;
            }
        }
        self::$elementslist[$ename->getAttribute('id')] = $ename;
        return true;
    }
}
