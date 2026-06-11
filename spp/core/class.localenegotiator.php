<?php

namespace SPP\Core;

/**
 * Class LocaleNegotiator
 * Detects the user's preferred locale from URL prefixes, Cookies, Sessions, or HTTP headers.
 * Strips locale prefixes from URIs so downstream routing works transparently.
 */
class LocaleNegotiator
{
    public static function negotiate(): void
    {
        $locale = 'en'; // Default fallback
        $foundInUrl = false;

        // 1. Check URL Prefix in $_GET['q'] (Standard SPP rewrite pattern)
        if (isset($_GET['q'])) {
            $qSegments = explode('/', ltrim($_GET['q'], '/'));
            $potentialLocale = $qSegments[0] ?? '';
            if (self::isValidLocaleTag($potentialLocale)) {
                $locale = $potentialLocale;
                $foundInUrl = true;
                
                array_shift($qSegments);
                $_GET['q'] = implode('/', $qSegments);
                $_REQUEST['q'] = $_GET['q'];
            }
        } 
        
        // 2. Check pure REQUEST_URI if q is not set
        if (!$foundInUrl && isset($_SERVER['REQUEST_URI'])) {
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $segments = explode('/', ltrim($path, '/'));
            $potentialLocale = $segments[0] ?? '';
            
            if (self::isValidLocaleTag($potentialLocale)) {
                $locale = $potentialLocale;
                $foundInUrl = true;
                
                array_shift($segments);
                $newPath = '/' . implode('/', $segments);
                if ($newPath === '/') $newPath = '';
                
                $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
                $_SERVER['REQUEST_URI'] = $newPath . ($query ? '?' . $query : '');
            }
        }

        // Set cookie if we found it in the URL
        if ($foundInUrl && !headers_sent()) {
            setcookie('spp_locale', $locale, time() + 86400 * 30, '/');
        }
        
        if (!$foundInUrl) {
            // 3. Check Session & Cookie
            if (isset($_SESSION['spp_locale']) && self::isValidLocaleTag($_SESSION['spp_locale'])) {
                $locale = $_SESSION['spp_locale'];
            } elseif (isset($_COOKIE['spp_locale']) && self::isValidLocaleTag($_COOKIE['spp_locale'])) {
                $locale = $_COOKIE['spp_locale'];
            } 
            // 4. Check Accept-Language Header
            elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
                $best = trim(explode(';', $langs[0])[0]);
                if (preg_match('/^[a-z]{2}/i', $best)) {
                    $locale = strtolower(substr($best, 0, 2));
                }
            }
        }
        
        // Globally load the translations for this locale
        Translation::load($locale);
    }

    private static function isValidLocaleTag(string $tag): bool
    {
        return (strlen($tag) === 2 || strlen($tag) === 5) && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $tag);
    }
}
