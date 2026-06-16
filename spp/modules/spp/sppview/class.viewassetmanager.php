<?php

namespace SPPMod\SPPView;

class ViewAssetManager
{
    private static $jsincludelist = [];
    private static $cssincludelist = [];
    private static $jscontentlist = [];
    private static $csscontentlist = [];

    public static function getJsFiles()
    {
        return self::$jsincludelist;
    }

    public static function getCssFiles()
    {
        return self::$cssincludelist;
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

    public static function includeCSSFilesDynamic()
    {
        foreach (self::$cssincludelist as $cssfile) {
            self::includeCSSDynamic($cssfile);
        }
    }

    public static function includeJSFilesDynamic()
    {
        foreach (self::$jsincludelist as $jsfile) {
            self::includeJSDynamic($jsfile);
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

    public static function includeCSSDynamic($cssfile)
    {
        echo '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars((string) $cssfile, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
    }

    public static function includeJqueryDynamic()
    {
        echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>' . "\n";
    }
}
