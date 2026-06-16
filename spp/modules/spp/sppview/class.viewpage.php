<?php

namespace SPPMod\SPPView;

use SPP\SPPException;
use SPP\SPPGlobal;

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
 * class ViewPage (Facade)
 *
 * Defines a HTML page in SPP.
 * Refactored into a Facade to delegate to ViewRouter, ViewRenderer, ViewAssetManager, ViewFormDispatcher.
 *
 * @author Satya Prakash Shukla
 */
class ViewPage extends \SPP\SPPObject
{
    protected static $pageid;
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

    // --- Core Routing & Rendering (Delegated) ---
    public static function showPage($page = null, array $options = [])
    {
        return ViewRouter::showPage($page, $options);
    }

    private static function renderFile(string $filename, array $pageData): void
    {
        ViewRenderer::renderFile($filename, $pageData);
    }

    public static function render($page = null)
    {
        $url = ($page != null) ? \SPPMod\SPPRouter\SPPRouter::getPage($page)['url'] : null;
        $pageid = self::getPageId();
        echo self::getPageHeader();
        echo self::getPageHead();
        echo self::getPageMeta();
        echo self::getPageBody();
        echo self::getPageFooter();
    }

    public static function resolveTieredJS(string $appName, string $compName): void
    {
        $staticPath = "res/apps/{$appName}/generated/{$compName}.js";
        if (file_exists(SPP_APP_DIR . '/' . $staticPath)) {
            self::addJsIncludeFile($staticPath);
        } else {
            self::addJsIncludeFile("?__js_comp={$compName}");
        }
    }

    // --- Asset Management (Delegated) ---
    public static function getJsFiles() { return ViewAssetManager::getJsFiles(); }
    public static function getCssFiles() { return ViewAssetManager::getCssFiles(); }
    public static function getJsIncludeList() { return ViewAssetManager::getJsFiles(); }
    public static function getCssIncludeList() { return ViewAssetManager::getCssFiles(); }
    
    public static function addJsIncludeFile($fpath, array $options = []) { return ViewAssetManager::addJsIncludeFile($fpath, $options); }
    public static function addCssIncludeFile($fpath) { return ViewAssetManager::addCssIncludeFile($fpath); }
    public static function addJsContent($content) { ViewAssetManager::addJsContent($content); }
    public static function addCssContent($content) { ViewAssetManager::addCssContent($content); }
    
    public static function includeCSSFilesDynamic() { ViewAssetManager::includeCSSFilesDynamic(); }
    public static function includeJSFilesDynamic() { ViewAssetManager::includeJSFilesDynamic(); }
    public static function includeJSDynamic($jsfile) { ViewAssetManager::includeJSDynamic($jsfile); }
    public static function includeCSSDynamic($cssfile) { ViewAssetManager::includeCSSDynamic($cssfile); }
    public static function includeJqueryDynamic() { ViewAssetManager::includeJqueryDynamic(); }

    // --- Form & Element Dispatcher (Delegated) ---
    public static function getFormsList() { return ViewFormDispatcher::getFormsList(); }
    public static function addForm(ViewForm $form) { return ViewFormDispatcher::addForm($form); }
    public static function processForms() { ViewFormDispatcher::processForms(); }
    public static function addElement(\SPPMod\SPPView\ViewTag $ename) { return ViewFormDispatcher::addElement($ename); }

    // --- Form File Parsing ---
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
        if (!is_array($item)) return [];
        if (isset($item[0])) return $item;
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
        if (!isset($arr['form'])) return;
        $forms = self::wrapArray($arr['form']);

        foreach ($forms as $form) {
            $fname = $form['name'] ?? 'unnamed_form';
            $faction = $form['action'] ?? '';
            $fid = $form['id'] ?? null;
            $fmethod = $form['method'] ?? 'post';

            $frm = new ViewForm($fname, $fmethod, $faction, $fid);
            self::addForm($frm);

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
            $val = new $type(ViewFormDispatcher::getElement($arr['control']));
        } elseif (array_key_exists('controls', $arr)) {
            $ctrls = [];
            foreach ($arr['controls'] as $controls) {
                foreach ($controls['control'] as $control) {
                    $ctrls[] = ViewFormDispatcher::getElement($control['name']);
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
                $element = ViewFormDispatcher::getElement($attach['element']);
                $form->attachValidator($val, $element, $attach['event'], $attach['errorholder']);
            }
        }
    }

    private static function createElementFromArray($arr): ViewTag
    {
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

    // --- State Getters and Setters ---
    public static function setPageId($id) { self::$pageid = $id; }
    public static function getPageId() { return self::$pageid; }
    public static function addValidator($validator) { self::$validators[] = $validator; }
    public static function getValidators() { return self::$validators; }
    public static function setPageTitle($title) { self::$pagetitle = $title; }
    public static function getPageTitle() { return self::$pagetitle; }
    public static function setPageDescription($desc) { self::$pagedescription = $desc; }
    public static function getPageDescription() { return self::$pagedescription; }
    public static function setPageKeywords($keywords) { self::$pagekeywords = $keywords; }
    public static function getPageKeywords() { return self::$pagekeywords; }
    public static function setPageAuthor($author) { self::$pageauthor = $author; }
    public static function getPageAuthor() { return self::$pageauthor; }
    public static function setPageContent($content) { self::$pagecontent = $content; }
    public static function getPageContent() { return self::$pagecontent; }
    public static function setPageHeader($header) { self::$pageheader = $header; }
    public static function getPageHeader() { return self::$pageheader; }
    public static function setPageFooter($footer) { self::$pagefooter = $footer; }
    public static function getPageFooter() { return self::$pagefooter; }
    public static function setPageHead($head) { self::$pagehead = $head; }
    public static function getPageHead() { return self::$pagehead; }
    public static function setPageBody($body) { self::$pagebody = $body; }
    public static function getPageBody() { return self::$pagebody; }
    public static function setPageMeta($meta) { self::$pagemeta = $meta; }
    public static function getPageMeta() { return self::$pagemeta; }
    public static function getXML() { return self::$xml; }
    public static function setXML($xml) { self::$xml = $xml; }
}
