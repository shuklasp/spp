<?php

namespace SPP;

/**
 * class \SPP\Scheduler
 *
 * Handles context and process scheduling for SPP.
 *
 * @author
 *     Satya Prakash Shukla
 * @version
 *     2.1 compatible with legacy SPP 1.x
 */
class Scheduler extends \SPP\SPPObject
{
    /** @var string */
    private static string $AppContext = '';

    /** @var array<string,\SPP\App> */
    private static array $procs = [];

    /**
     * Set or switch application context.
     *
     * @throws \SPP\SPPException
     */
    public static function setContext(string $context): void
    {
        $oldContext = self::$AppContext;
        $context = trim($context);
        $context = ($context === '') ? 'default' : $context;

        if (!array_key_exists($context, self::$procs)) {
            if (class_exists('\\SPP\\App')) {
                try {
                    new \SPP\App($context);
                } catch (\Throwable $e) {}
            }
            if (!array_key_exists($context, self::$procs)) {
                throw new \SPP\SPPException('Unregistered context: ' . $context);
            }
        }

        if (self::$AppContext === '' || self::$AppContext === $context) {
            self::$AppContext = $context;
            return;
        }

        $currProc = isset(self::$procs[self::$AppContext]) ? self::$procs[self::$AppContext] : null;
        $newProc = self::getProcObj($context);

        if ($currProc) {
            $currProc->setStatus(\SPP\App::APP_WAITING);
        }
        $newProc->setStatus(\SPP\App::APP_EXEC);

        self::$AppContext = $context;

        // Trace Logging
        if (defined('SPP_DEBUG') && SPP_DEBUG) {
            $logMsg = date('[Y-m-d H:i:s]') . " Context Switch: {$oldContext} -> {$context}\n";
            @file_put_contents(SPP_LOG_DIR . '/spp_context.log', $logMsg, FILE_APPEND);
        }
    }

    /**
     * Register a new \SPP\App process.
     *
     * @throws \SPP\SPPException
     */
    public static function regProc(\SPP\App $proc): void
    {
        $pname = $proc->getName();

        if (!isset(self::$procs[$pname])) {
            self::$procs[$pname] = $proc;
        }
    }

    /**
     * Get current active context name.
     */
    public static function getContext(): string
    {
        return self::$AppContext;
    }

    /**
     * Check if an application context has been set.
     */
    public static function hasContext(): bool
    {
        return self::$AppContext !== '';
    }

    /**
     * Get module configuration directory for current process.
     */
    public static function getModsConfDir(): string
    {
        return self::getActiveProc()->getModsConfDir();
    }

    /**
     * Get \SPP\App object for a specific process.
     *
     * @throws \SPP\SPPException
     */
    public static function getProcObj(string $pname): \SPP\App
    {
        if (!array_key_exists($pname, self::$procs)) {
            throw new \SPP\SPPException('Unregistered process: ' . $pname);
        }

        return self::$procs[$pname];
    }

    /**
     * Get currently active \SPP\App process.
     *
     * @throws \SPP\SPPException
     */
    public static function getActiveProc(): \SPP\App
    {
        if (self::$AppContext === '') {
            throw new \SPP\SPPException('Application context not set.');
        }

        if (!isset(self::$procs[self::$AppContext])) {
            return self::getProcObj(self::$AppContext);
        }

        return self::$procs[self::$AppContext];
    }

    /**
     * Get active SPPError object from the current app.
     */
    public static function getActiveErrorObj(): ?\SPP\SPPError
    {
        return self::getActiveProc()->getErrorObj();
    }

    /**
     * Detects the app context based on Request URI and base_url in global registry.
     * Enforces strict prefixing.
     */
    public static function detectAndEnforceContext(): void
    {
        \SPP\Registry::loadShared();
        \SPP\SPPEvent::registerEvent('event_spp_context_enforce');
        \SPP\SPPEvent::registerEvent('event_spp_route_resolve');

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = explode('?', $uri)[0];

        // Normalize URI if running in a subdirectory
        if (defined('APP_BASE_URI') && APP_BASE_URI !== '' && APP_BASE_URI !== '/') {
            $baseUri = rtrim(APP_BASE_URI, '/');
            if (stripos($uri, $baseUri) === 0) {
                $uri = substr($uri, strlen($baseUri));
            }
        } else {
            $root = str_replace('\\', '/', SPP_DOC_ROOT);
            $appBase = str_replace('\\', '/', SPP_APP_DIR);
            
            // Only try string replace if appBase actually starts with root to avoid false stripping
            if ($root !== '' && stripos($appBase, $root) === 0) {
                $subDir = trim(substr($appBase, strlen($root)), '/');
                if ($subDir !== '') {
                    if (stripos($uri, '/' . $subDir) === 0) {
                        $uri = '/' . ltrim(substr($uri, strlen('/' . $subDir)), '/');
                    }
                }
            }
        }
        $uri = ($uri === '') ? '/' : $uri;

        $apps = \SPP\App::getGlobalSettings('apps') ?: [];

        // 0. Domain / Virtual Host Resolution (Single vs Multi Domain Mode)
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $defaultApp = null;
        if ($host !== '' && class_exists('\SPP\Core\Router\DomainRouter')) {
            $domainConfig = \SPP\Core\Router\DomainRouter::resolveDomain($host);

            if ($domainConfig !== null) {
                // Domain or virtual host matched: use its default application
                $defaultApp = $domainConfig['default_app'];

                if (!\SPP\Core\Router\DomainRouter::isSingleDomainMode()) {
                    // Canonical Host Redirection (e.g. www.school.org -> school.org)
                    $targetCanonical = \SPP\Core\Router\DomainRouter::shouldRedirectToCanonical($host);
                    if ($targetCanonical !== null && php_sapi_name() !== 'cli') {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $reqUri = $_SERVER['REQUEST_URI'] ?? '/';
                        header('Location: ' . $scheme . '://' . $targetCanonical . $reqUri, true, 301);
                        exit;
                    }

                    // HTTPS Enforcement (force_https: true)
                    if (\SPP\Core\Router\DomainRouter::shouldRedirectHttps($host) && php_sapi_name() !== 'cli') {
                        $targetHost = $targetCanonical ?: ($host ?: 'localhost');
                        $reqUri = $_SERVER['REQUEST_URI'] ?? '/';
                        header('Location: https://' . $targetHost . $reqUri, true, 301);
                        exit;
                    }

                    // SEO Prefix Normalization (e.g. /school/students -> /students on school default domain)
                    $seoRedirect = \SPP\Core\Router\DomainRouter::getSeoRedirectUri($uri, $host);
                    if ($seoRedirect !== null && php_sapi_name() !== 'cli') {
                        $basePrefix = defined('APP_BASE_URI') && APP_BASE_URI !== '' && APP_BASE_URI !== '/' ? rtrim(APP_BASE_URI, '/') : '';
                        header('Location: ' . $basePrefix . $seoRedirect, true, 301);
                        exit;
                    }
                }
            } else {
                if (\SPP\Core\Router\DomainRouter::isSingleDomainMode()) {
                    // In Single Domain Mode, unmapped hosts serve immediately without 404 or enforcement
                    // ("just a copy paste will do").
                    $defaultApp = \SPP\Core\Router\DomainRouter::getDefaultApp($host);
                } else {
                    // Domain unmapped: check if it's an allowed dev/loopback host (e.g. localhost, 127.0.0.1)
                    $isDevHost = \SPP\Core\Router\DomainRouter::isAllowedUnmappedDevHost($host);
                    if ($isDevHost) {
                        $defaultApp = \SPP\Core\Router\DomainRouter::getDefaultApp($host);
                    } else {
                        // Evaluate active virtual host policy mode for unmapped subdomain / host
                        $policyInfo = \SPP\Core\Router\DomainRouter::getUnmappedSubdomainPolicy($host);
                        $policy = $policyInfo['policy'];
                        $parentDomain = $policyInfo['parent_domain'];
                        $parentConfig = $policyInfo['parent_config'];

                        if ($policy === \SPP\Core\Router\DomainRouter::MODE_REDIRECT && $parentDomain !== null) {
                            // 302 Redirection to parent domain
                            if (php_sapi_name() !== 'cli') {
                                $targetParent = \SPP\Core\Router\DomainRouter::getCanonicalHost($parentDomain);
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $reqUri = $_SERVER['REQUEST_URI'] ?? '/';
                                header('Location: ' . $scheme . '://' . $targetParent . $reqUri, true, 302);
                                exit;
                            }
                        } elseif ($policy === \SPP\Core\Router\DomainRouter::MODE_INHERIT && $parentDomain !== null && !empty($parentConfig['default_app'])) {
                            // Inherit parent domain's default application
                            $defaultApp = $parentConfig['default_app'];
                        } elseif ($policy === \SPP\Core\Router\DomainRouter::MODE_DYNAMIC_TENANT) {
                            // Dynamic tenant resolution hook
                            $subdomain = explode('.', $host)[0] ?? '';
                            $tenantEvt = new \SPP\EventParams([
                                'subdomain' => $subdomain,
                                'host' => $host,
                                'tenant' => null,
                                'app' => null
                            ]);
                            \SPP\SPPEvent::fireEvent('event_spp_tenant_resolve', $tenantEvt);
                            $tPayload = $tenantEvt->getPayload();
                            if (!empty($tPayload['app'])) {
                                $defaultApp = $tPayload['app'];
                            } elseif (!empty($tPayload['tenant'])) {
                                $defaultApp = $tPayload['tenant'];
                            }
                        } elseif ($policy === \SPP\Core\Router\DomainRouter::MODE_PERMISSIVE) {
                            // Permissive mode: fallback to global default app
                            $defaultApp = \SPP\Core\Router\DomainRouter::getDefaultApp($host);
                        }

                        // If defaultApp is still not resolved, strict 404 enforcement (exempting installer mode)
                        if (!$defaultApp) {
                            $isInstaller = (defined('SPP_INSTALLER_MODE') && SPP_INSTALLER_MODE) ||
                                (isset($_SERVER['SCRIPT_NAME']) && (str_ends_with($_SERVER['SCRIPT_NAME'], 'install.php') || str_ends_with($_SERVER['SCRIPT_NAME'], 'spp-domain-installer.php')));

                            if ($isInstaller) {
                                $defaultApp = \SPP\Core\Router\DomainRouter::getDefaultApp($host) ?: (\SPP\App::getGlobalSettings('base_app') ?: 'default');
                            } elseif (php_sapi_name() !== 'cli') {
                                http_response_code(404);
                                header('Content-Type: text/html; charset=utf-8');

                                $siteName = \SPP\Core\Router\DomainRouter::getSetting('site_name', 'SPP Application Platform', $host);
                                $adminEmail = \SPP\Core\Router\DomainRouter::getSetting('admin_email', null, $host);
                                $customMessage = \SPP\Core\Router\DomainRouter::getSetting('error_message', null, $host);
                                $mode = $policy;
                                $errorFile = \SPP\Core\Router\DomainRouter::getDomainNotFoundView($host);

                                if ($errorFile && file_exists($errorFile)) {
                                    include $errorFile;
                                }
                                exit;
                            } else {
                                throw new \SPP\SPPException("Virtual host or subdomain '{$host}' is not configured (Strict Mode).");
                            }
                        }
                    }
                }
            }
        }
        if (!$defaultApp) {
            $defaultApp = \SPP\App::getGlobalSettings('base_app') ?: 'default';
        }

        // Check if the incoming URI targets a secondary registered application prefix
        $targetSecondaryApp = null;
        $cleanUri = '/' . ltrim($uri, '/');
        $uriLower = strtolower($cleanUri);

        // 1. Direct framework workbench detection (sppdev, sppadmin, sppmail)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($uriLower === '/sppdev' || str_starts_with($uriLower, '/sppdev/') || str_starts_with($uriLower, '/sppdev-api') || str_contains($scriptName, '/spp/dev/')) {
            $targetSecondaryApp = 'sppdev';
        } elseif ($uriLower === '/sppadmin' || str_starts_with($uriLower, '/sppadmin/') || str_starts_with($uriLower, '/spp/admin') || str_starts_with($uriLower, '/spp-api') || str_contains($scriptName, '/spp/admin/')) {
            $targetSecondaryApp = 'sppadmin';
        } elseif ($uriLower === '/sppmail' || str_starts_with($uriLower, '/sppmail/') || str_starts_with($uriLower, '/sppmail-api') || str_contains($scriptName, '/spp/mail/')) {
            $targetSecondaryApp = 'sppmail';
        } else {
            // Check domain-specific sub_apps first
            $matchedSubApp = null;
            if (class_exists('\SPP\Core\Router\DomainRouter')) {
                $matchedSubApp = \SPP\Core\Router\DomainRouter::getSubAppForUri($cleanUri, $host);
            }

            if ($matchedSubApp !== null) {
                if (($matchedSubApp['type'] ?? '') === 'non_spp') {
                    if (class_exists('\SPP\Core\Server\NonSppAppHandler')) {
                        \SPP\Core\Server\NonSppAppHandler::handle($matchedSubApp, $_SERVER['REQUEST_URI'] ?? $cleanUri);
                        return;
                    }
                } else {
                    $targetSecondaryApp = $matchedSubApp['app_name'] ?? basename($matchedSubApp['route']);
                }
            } else {
                $defaultAppName = is_array($defaultApp) ? ($defaultApp['app_name'] ?? 'default') : (string)$defaultApp;
                foreach ($apps as $name => $cfg) {
                    // Default app is served at root, so skip checking its base_url here
                    if (strcasecmp($name, $defaultAppName) === 0) {
                        continue;
                    }
                    $base = trim($cfg['base_url'] ?? '/' . $name, '/');
                    if ($base === '') {
                        continue;
                    }
                    $basePattern = '/' . strtolower($base);
                    if ($uriLower === $basePattern || str_starts_with($uriLower, $basePattern . '/')) {
                        $targetSecondaryApp = $name;
                        break;
                    }
                }

                if ($targetSecondaryApp === null && class_exists('\SPP\Core\Router\DomainRouter')) {
                    $matchedCfg = \SPP\Core\Router\DomainRouter::resolveDomain($host);
                    if (!empty($matchedCfg['disabled_apps']) && is_array($matchedCfg['disabled_apps'])) {
                        foreach ($matchedCfg['disabled_apps'] as $disApp) {
                            $disPattern = '/' . strtolower($disApp);
                            if ($uriLower === $disPattern || str_starts_with($uriLower, $disPattern . '/')) {
                                $targetSecondaryApp = $disApp;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $domainContext = null;
        if ($targetSecondaryApp !== null) {
            // Validate domain access permission for this secondary app (Multi-domain mode only)
            if (!in_array($targetSecondaryApp, ['sppdev', 'sppadmin', 'sppmail'], true)
                && class_exists('\SPP\Core\Router\DomainRouter') 
                && !\SPP\Core\Router\DomainRouter::isSingleDomainMode() 
                && !\SPP\Core\Router\DomainRouter::isAppAllowed($targetSecondaryApp, $host)) {
                if (php_sapi_name() !== 'cli') {
                    http_response_code(403);
                    header('Content-Type: text/html; charset=utf-8');

                    $matchedCfg = \SPP\Core\Router\DomainRouter::getMatchedDomainConfig();
                    $customView = $matchedCfg['error_view'] ?? \SPP\Core\Router\DomainRouter::getSetting('error_view', null, $host);

                    $errorFile = null;
                    if ($customView && file_exists($customView)) {
                        $errorFile = $customView;
                    } elseif (defined('SPP_BASE_DIR') && file_exists(SPP_BASE_DIR . '/views/errors/domain_forbidden.php')) {
                        $errorFile = SPP_BASE_DIR . '/views/errors/domain_forbidden.php';
                    } elseif (defined('SPP_APP_DIR') && file_exists(SPP_APP_DIR . '/spp/views/errors/domain_forbidden.php')) {
                        $errorFile = SPP_APP_DIR . '/spp/views/errors/domain_forbidden.php';
                    } elseif (file_exists(__DIR__ . '/../views/errors/domain_forbidden.php')) {
                        $errorFile = __DIR__ . '/../views/errors/domain_forbidden.php';
                    }

                    $appName = $targetSecondaryApp;
                    $siteName = \SPP\Core\Router\DomainRouter::getSetting('site_name', 'SPP Application Platform', $host);
                    $adminEmail = \SPP\Core\Router\DomainRouter::getSetting('admin_email', null, $host);
                    $customMessage = \SPP\Core\Router\DomainRouter::getSetting('error_message', null, $host);
                    $canonicalHost = \SPP\Core\Router\DomainRouter::getCanonicalHost($host);
                    $homeUrl = defined('APP_BASE_URI') && APP_BASE_URI !== '' && APP_BASE_URI !== '/' ? rtrim(APP_BASE_URI, '/') . '/' : '/';

                    if ($errorFile && file_exists($errorFile)) {
                        include $errorFile;
                    }
                    exit;
                } else {
                    throw new \SPP\SPPException("Application '{$targetSecondaryApp}' is disabled on domain '{$host}'.");
                }
            }
            $domainContext = $targetSecondaryApp;
        } else {
            // No secondary app prefix matched: routes belong to the default app directly at root
            if (is_array($defaultApp) && ($defaultApp['type'] ?? '') === 'non_spp') {
                if (class_exists('\SPP\Core\Server\NonSppAppHandler')) {
                    \SPP\Core\Server\NonSppAppHandler::handle($defaultApp, $_SERVER['REQUEST_URI'] ?? $cleanUri);
                    return;
                }
            }
            $domainContext = is_array($defaultApp) ? ($defaultApp['app_name'] ?? 'default') : $defaultApp;
        }

        // Domain Mode Telemetry & Canonical Link Header
        if (php_sapi_name() !== 'cli' && !headers_sent() && class_exists('\SPP\Core\Router\DomainRouter')) {
            if (\SPP\Core\Router\DomainRouter::isSingleDomainMode()) {
                header('X-SPP-Domain-Mode: single');
            } else {
                header('X-SPP-Domain-Mode: multi');
                $canonicalHost = \SPP\Core\Router\DomainRouter::getCanonicalHost($host);
                if ($canonicalHost) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    header('Link: <' . $scheme . '://' . $canonicalHost . $cleanUri . '>; rel="canonical"', false);
                }
            }
        }

        $params = ['uri' => $uri, 'apps' => $apps, 'context' => $domainContext, 'host' => $host];
        $evtParams = new \SPP\EventParams($params);
        \SPP\SPPEvent::fireEvent('event_spp_context_enforce', $evtParams, function ($p) {
            $payload = $p->getPayload();
            if ($payload['context'] !== null) {
                return; // Matched via Virtual Host Domain routing
            }
            foreach ($payload['apps'] as $name => $cfg) {
                $base = $cfg['base_url'] ?? '/' . $name;
                if (strtolower($payload['uri']) === strtolower($base) || stripos($payload['uri'], $base . '/') === 0) {
                    $payload['context'] = $name;
                    $p->setPayload($payload);
                    return;
                }
            }
        });
        $params = $evtParams->getPayload();

        $defaultAppName = is_array($defaultApp) ? ($defaultApp['app_name'] ?? 'default') : (string)$defaultApp;
        $matchedApp = $params['context'] ?: $defaultAppName;

        $routeParams = ['uri' => $uri, 'context' => $matchedApp];
        $routeEvtParams = new \SPP\EventParams($routeParams);
        \SPP\SPPEvent::fireEvent('event_spp_route_resolve', $routeEvtParams, function ($p) {
            // Default: do nothing, context already matched
        });
        $routeParams = $routeEvtParams->getPayload();
        $matchedApp = $routeParams['context'];

        self::$AppContext = $matchedApp;
    }

    /**
     * Executes a callback within a specific application context.
     * Safely switches and restores context.
     *
     * @param string $context
     * @param callable $callback
     * @return mixed
     */
    public static function withContext(string $context, callable $callback)
    {
        $oldContext = self::$AppContext;
        if ($oldContext === $context) {
            return $callback();
        }

        // Ensure the target app is initialized if it's not already
        if (!isset(self::$procs[$context])) {
            new \SPP\App($context, false, 3);
        }

        self::setContext($context);
        try {
            return $callback();
        } finally {
            if ($oldContext !== '') {
                self::setContext($oldContext);
            } else {
                self::$AppContext = '';
            }
        }
    }
}
