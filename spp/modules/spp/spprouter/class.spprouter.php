<?php

namespace SPPMod\SPPRouter;

use Symfony\Component\Yaml\Yaml;

/**
 * class SPPRouter
 *
 * Resolves page routes from YAML and/or database with configurable priority
 * and automatic fallback. Database source is only active when the sppdb
 * module is enabled. Tables are created automatically on first use.
 *
 * Priority is configured in modsconf/spprouter/config.yml:
 *   page_source_primary:  yaml | db
 *   page_source_fallback: yaml | db | none
 *
 * @author Satya Prakash Shukla
 */
class SPPRouter extends \SPP\SPPObject
{
    /** @var array<string,mixed> In-memory YAML cache partitioned by file path */
    private static array $yamlFileCache = [];

    /** @var array<string,mixed> Resolved DB pages cache partitioned by appname */
    private static array $dbAppCache = [];

    /** @var array<string,string[]> Resolved [primary, fallback] sources partitioned by appname */
    private static array $sourceAppCache = [];

    /**
     * Whitelisted methods callable via 'specials' routing in pages.yml / DB.
     * Add new router methods here to keep call_user_func tightly controlled.
     */
    private static array $allowedSpecialMethods = [
        'getResource',
        'getFile',
        'serveResource',
        'serveDirectory',
    ];

    // -------------------------------------------------------------------------
    // Source resolution
    // -------------------------------------------------------------------------

    /**
     * Returns [primary, fallback] source names for a specific app context.
     * @param string|null $appname
     * @return string[]
     */
    private static function getSources(string $appname = null): array
    {
        $appname = $appname ?: \SPP\Scheduler::getContext();

        if (isset(self::$sourceAppCache[$appname])) {
            return self::$sourceAppCache[$appname];
        }

        $dbAvailable = \SPP\Module::isEnabled('sppdb');

        $primary  = \SPP\Module::getConfig('page_source_primary', 'spprouter', $appname) ?: 'yaml';
        $fallback = \SPP\Module::getConfig('page_source_fallback', 'spprouter', $appname) ?: 'none';

        // Normalize aliases
        $map = ['dbfirst' => 'db', 'filefirst' => 'yaml', 'file' => 'yaml'];
        $primary  = $map[$primary] ?? $primary;
        $fallback = $map[$fallback] ?? $fallback;

        // Silently demote DB to 'none' when sppdb is not loaded
        if (!$dbAvailable) {
            if ($primary  === 'db') {
                $primary  = 'yaml';
            }
            if ($fallback === 'db') {
                $fallback = 'none';
            }
        }

        self::$sourceAppCache[$appname] = [$primary, $fallback];
        return self::$sourceAppCache[$appname];
    }

    /**
     * Resolves the pages.yml file path for an app, honoring etc_path config.
     */
    public static function getAppPagesFile(string $appname = ''): string
    {
        $appname = $appname ?: \SPP\Scheduler::getContext();
        $etcPath = \SPP\App::getAppConf('etc_path', $appname);

        if ($etcPath) {
            $base = (str_starts_with($etcPath, '/') || str_contains($etcPath, ':'))
                ? rtrim($etcPath, '/\\')
                : SPP_APP_DIR . SPP_DS . rtrim($etcPath, '/\\');
            $file = $base . SPP_DS . 'pages.yml';
            if (!file_exists($file) && file_exists($base . SPP_DS . 'routes.yml')) {
                return $base . SPP_DS . 'routes.yml';
            }
        } else {
            $file = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'pages.yml';
            if (!file_exists($file) && file_exists(APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'routes.yml')) {
                return APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'routes.yml';
            }
        }

        if (!file_exists($file)) {
            $legacyFile = APP_ETC_DIR . SPP_DS . 'pages.yml';
            if (file_exists($legacyFile)) {
                return $legacyFile;
            }
            $legacyRoutes = APP_ETC_DIR . SPP_DS . 'routes.yml';
            if (file_exists($legacyRoutes)) {
                return $legacyRoutes;
            }
        }
        return $file;
    }

    // -------------------------------------------------------------------------
    // YAML driver
    // -------------------------------------------------------------------------

    private static function getYaml(string $ymlFile = null): array
    {
        $ymlFile = $ymlFile ?: self::getAppPagesFile();

        if (isset(self::$yamlFileCache[$ymlFile])) {
            return self::$yamlFileCache[$ymlFile];
        }

        if (!file_exists($ymlFile)) {
            self::$yamlFileCache[$ymlFile] = ['pages' => [], 'defaults' => [], 'specials' => []];
            return self::$yamlFileCache[$ymlFile];
        }

        try {
            $data = Yaml::parseFile($ymlFile);
            if (isset($data['routes']) && is_array($data['routes'])) {
                $pagesMap = [];
                foreach ($data['routes'] as $r) {
                    $p = ltrim($r['path'] ?? '', '/');
                    $t = $r['target'] ?? '';
                    $type = $r['type'] ?? '';
                    if ($type === 'static_asset') {
                        $pagesMap[$p] = ['assets' => $t];
                    } else {
                        $pagesMap[$p] = ['url' => $t];
                    }
                }
                $data['pages'] = $pagesMap;
                unset($data['routes']);
            } elseif (isset($data['pages']) && is_array($data['pages'])) {
                $pagesMap = [];
                foreach ($data['pages'] as $k => $pageObj) {
                    if (is_array($pageObj) && isset($pageObj['name'])) {
                        $name = (string)$pageObj['name'];
                        $pagesMap[$name] = $pageObj;
                    } else {
                        $pagesMap[$k] = $pageObj;
                    }
                }
                $data['pages'] = $pagesMap;
            }
            // Merge Attribute Routes
            if (class_exists('\SPPMod\SPPView\AttributeRouter')) {
                $attrRoutes = \SPPMod\SPPView\AttributeRouter::getRoutes(\SPP\Scheduler::getContext());
                $data['pages'] = array_merge($data['pages'] ?? [], $attrRoutes);
            }

            self::$yamlFileCache[$ymlFile] = $data;
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            throw new \SPP\SPPException('Failed to parse pages/routes YAML (' . $ymlFile . '): ' . $e->getMessage(), 1000, $e);
        }

        return self::$yamlFileCache[$ymlFile];
    }

    // -------------------------------------------------------------------------
    // Database driver
    // -------------------------------------------------------------------------

    /**
     * Returns the full DB routing dataset, mirroring the YAML structure.
     * @param string|null $appname
     * @return array{pages: array, defaults: array, specials: array}
     */
    private static function getDb(string $appname = null): array
    {
        $appname = $appname ?: \SPP\Scheduler::getContext();

        if (isset(self::$dbAppCache[$appname])) {
            return self::$dbAppCache[$appname];
        }

        $db = new \SPPMod\SPPDB\SPPDB();

        try {
            $pages    = $db->execute_query('SELECT name, url FROM '    . \SPPMod\SPPDB\SPPDB::sppTable('spprouter_pages'));
            $defaults = $db->execute_query('SELECT defkey, defval FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('spprouter_defaults'));
            $specials = $db->execute_query('SELECT name, method FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('spprouter_specials'));
        } catch (\Exception $e) {
            $pages = [];
            $defaults = [];
            $specials = [];
        }

        // Normalise into the same shape getYaml() returns
        $pagesMap = [];
        if (is_array($pages)) {
            foreach ($pages as $row) {
                if (isset($row['name'])) {
                    $pagesMap[(string)$row['name']] = $row;
                }
            }
        }

        $defaultsMap = [];
        foreach ($defaults as $row) {
            $defaultsMap[$row['defkey']] = $row['defval'];
        }

        self::$dbAppCache[$appname] = [
            'pages'    => $pagesMap,
            'defaults' => $defaultsMap,
            'specials' => $specials,
        ];

        return self::$dbAppCache[$appname];
    }

    // -------------------------------------------------------------------------
    // Internal helpers — single-source lookups
    // -------------------------------------------------------------------------

    private static function findPageInYaml(string $q, string $appname = null, string $ymlFile = null): ?array
    {
        $appname = $appname ?: \SPP\Scheduler::getContext();
        $ymlFile = $ymlFile ?: self::getAppPagesFile($appname);

        $yaml = self::getYaml($ymlFile);

        // Handle empty routes by falling back to the 'home' setting
        if ($q === '' && isset($yaml['home'])) {
            $q = (string)$yaml['home'];
        }

        if (!isset($yaml['pages']) || !is_array($yaml['pages'])) {
            return null;
        }

        // 1. Exact match has absolute priority
        if (isset($yaml['pages'][$q])) {
            return self::processRoute($q, $yaml['pages'][$q], $q, $appname, $ymlFile);
        }

        // 1.5. Pattern match for routes with placeholders (e.g., {id})
        foreach ($yaml['pages'] as $name => $routeConfig) {
            $name = (string)$name;
            if (str_contains($name, '{')) {
                $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $name);
                if (preg_match('#^' . $pattern . '$#', $q, $m)) {
                    array_shift($m); // Remove full match
                    $res = self::processRoute($name, $routeConfig, $q, $appname, $ymlFile);
                    if ($res) {
                        $res['params'] = $m;
                    }
                    return $res;
                }
            }
        }

        // 2. Find all prefix matches
        $matches = [];
        foreach ($yaml['pages'] as $name => $routeConfig) {
            $name = (string)$name;
            // A match is valid if q is exactly name OR starts with name followed by a slash
            if ($name !== '' && (strpos($q, $name . '/') === 0 || $q === $name)) {
                $matches[$name] = $routeConfig;
            }
        }

        if (!empty($matches)) {
            // Sort by key length descending to find the most specific match
            uksort($matches, function ($a, $b) {
                return strlen($b) <=> strlen($a);
            });

            reset($matches);
            $bestName = key($matches);
            $bestConfig = current($matches);

            @file_put_contents(SPP_LOG_DIR . '/debug_lekhak.log', "[".date('Y-m-d H:i:s')."] DEBUG: Longest match routing: Picked '{$bestName}' for request '{$q}'\n", FILE_APPEND);
            return self::processRoute($bestName, $bestConfig, $q, $appname, $ymlFile);
        }

        return null;
    }

    /**
     * Helper to process a matched route entry and handle app/subpages delegation.
     */
    private static function processRoute($name, $route, $q, $appname, $ymlFile, ?string $modulePath = null): ?array
    {
        $remaining = ltrim(substr($q, strlen($name)), '/');

        // Case A: Delegate to another App
        if ($remaining !== '' && isset($route['app'])) {
            return self::resolvePage($remaining, $route['app']);
        }

        // Case B: Delegate to another YAML file
        if ($remaining !== '' && isset($route['subpages'])) {
            $subpagesFile = $route['subpages'];
            if (!str_starts_with($subpagesFile, '/') && !str_contains($subpagesFile, ':')) {
                $subpagesFile = dirname($ymlFile) . SPP_DS . $subpagesFile;
            }
            return self::findPageInYaml($remaining, $appname, $subpagesFile);
        }

        // Case C: Resolve as a direct page
        if (isset($route['url'])) {
            $resolvedUrl = self::resolveInternalPath($route['url'], $modulePath, $appname);
            $pg = self::buildPage($name, $resolvedUrl, $q);
            if (isset($route['controller'])) {
                $pg['controller'] = $route['controller'];
            }
            if (isset($route['special'])) {
                $pg['special'] = (int)$route['special'];
                if (isset($route['method'])) {
                    $pg['method'] = $route['method'];
                }
            }
            return $pg;
        }

        // Case D: Controller-only Route
        if (isset($route['controller'])) {
            $pg = self::buildPage($name, '', $q);
            $pg['controller'] = $route['controller'];
            return $pg;
        }

        // Case E: Asset Directory Support
        if (isset($route['assets'])) {
            $resolvedAssets = self::resolveInternalPath($route['assets'], $modulePath, $appname);
            return [
                'url' => $resolvedAssets . '/' . $remaining,
                'special' => 1,
                'method' => 'serveDirectory',
                'context' => [
                    'base_dir' => $resolvedAssets,
                    'relative_path' => $remaining
                ]
            ];
        }

        return null;
    }

    /**
     * Resolves a route path according to framework rules:
     * 1. Starts with '/': Relative to APP_BASE_DIR (root)
     * 2. No '/':
     *    - If modulePath provided: Relative to module base
     *    - Otherwise: Relative to src/ directory
     */
    private static function resolveInternalPath(string $path, ?string $modulePath = null, ?string $appname = null): string
    {
        if (str_contains($path, ':') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        if ($modulePath) {
            // Convert absolute module path to relative path from SPP_APP_DIR
            $root = realpath(SPP_APP_DIR);
            $mod  = realpath($modulePath);

            if ($root && $mod && stripos($mod, $root) === 0) {
                $rel = ltrim(substr($mod, strlen($root)), '/\\');
                return str_replace('\\', '/', $rel) . '/' . ltrim($path, '/\\');
            }
            // Fallback: if realpath fails or is outside root, use basename if it looks safe
            return 'modules/' . basename($modulePath) . '/' . ltrim($path, '/\\');
        }

        // Application Context: Resolve relative to the app's configured source directory
        $appname = $appname ?: \SPP\Scheduler::getContext();
        $app = \SPP\App::getApp($appname);
        $srcDir = $app->getAppSrcDir();

        // Convert absolute src directory to relative path from SPP_APP_DIR
        $root = realpath(SPP_APP_DIR);
        $src  = realpath($srcDir);

        if ($root && $src && stripos($src, $root) === 0) {
            $rel = ltrim(substr($src, strlen($root)), '/\\');
            return str_replace('\\', '/', $rel) . '/' . ltrim($path, '/\\');
        }

        // Fallback to convention if path resolution fails
        return 'src/' . $appname . '/' . ltrim($path, '/\\');
    }

    /** @return array|null Matched page array or null */
    private static function findPageInDb(string $q, string $appname = null): ?array
    {
        $data = self::getDb($appname);
        foreach ($data['pages'] as $row) {
            if (substr_compare(trim($row['name']), $q, 0, strlen($row['name'])) === 0) {
                return self::buildPage($row['name'], $row['url'], $q);
            }
        }

        // --- Entity Routing (CMS Aliases) ---
        return self::findPageInEntities($q, $appname);
    }

    /**
     * Searches for routes in custom entity tables (e.g. CMS nodes).
     * Requests routeable entities from SPPDB to ensure centralized DB logic.
     */
    private static function findPageInEntities(string $q, string $appname = null): ?array
    {
        $entities = \SPPMod\SPPDB\SPPDB::getRouteEntities();

        if (empty($entities)) {
            return null;
        }

        $db = new \SPPMod\SPPDB\SPPDB();

        foreach ($entities as $table => $e) {
            $field = $e['field'] ?? 'alias';
            $url   = $e['url'] ?? '';

            if (empty($url)) {
                continue;
            }

            $res = $db->execute_query("SELECT * FROM " . \SPPMod\SPPDB\SPPDB::sppTable($table) . " WHERE {$field} = ?", [$q]);
            if ($res && count($res) > 0) {
                $row = $res[0];
                $pg = self::buildPage($q, $url, $q);
                // Attach the full entity record
                $pg['entity'] = $row;
                $pg['entity_table'] = $table;
                if (isset($e['id_field']) && isset($row[$e['id_field']])) {
                    $pg['params'][] = $row[$e['id_field']];
                }
                return $pg;
            }
        }

        return null;
    }

    /** Builds the standard page result array used throughout the framework */
    private static function buildPage(string $name, string $url, string $q): array
    {
        $url = ltrim($url, '/');
        $pg  = ['url' => $url, 'name' => $name, 'special' => 0];

        if ($name !== $q) {
            $pos = strpos($q, $name);
            $pr  = ($pos !== false) ? substr_replace($q, '', $pos, strlen($name)) : '';
            $pr  = ltrim($pr, '/');
            $pg['params'] = explode('/', $pr);
        } else {
            $pg['params'] = [];
        }

        $pg['named_params'] = [];
        foreach ($_GET as $parm => $value) {
            if ($parm === 'q') {
                continue;
            }
            $pg['named_params'][$parm] = $value;
        }

        return $pg;
    }

    /** @return string|null Special route URL or null */
    private static function findSpecialInYaml(string $spl, string $q, string $appname = null): ?array
    {
        $yaml = self::getYaml(self::getAppPagesFile($appname));
        foreach ($yaml['specials'] ?? [] as $special) {
            if ($special['name'] === $spl) {
                return self::dispatchSpecial($special['method'], $q);
            }
        }
        return null;
    }

    /** @return array|null Special route result or null */
    private static function findSpecialInDb(string $spl, string $q, string $appname = null): ?array
    {
        $data = self::getDb($appname);
        foreach ($data['specials'] as $row) {
            if ($row['name'] === $spl) {
                return self::dispatchSpecial($row['method'], $q);
            }
        }
        return null;
    }

    /** Validates and dispatches a special method, returning the route result */
    private static function dispatchSpecial(string $method, string $q, array $context = []): array
    {
        if (!in_array($method, self::$allowedSpecialMethods, true)) {
            throw new \SPP\SPPException('Disallowed special route method: ' . $method);
        }
        return ['url' => call_user_func([__CLASS__, $method], $q, $context), 'special' => 1];
    }

    /** @return string|null Default value from YAML or null */
    private static function findDefaultInYaml(string $def): ?string
    {
        $yaml = self::getYaml();
        return $yaml['defaults'][$def] ?? null;
    }

    /** @return string|null Default value from DB or null */
    private static function findDefaultInDb(string $def): ?string
    {
        $data = self::getDb();
        return $data['defaults'][$def] ?? null;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Resolves a page route using the configured source priority.
     */
    public static function getPage($page = null): array
    {
        $q = (isset($_GET['q']) && $_GET['q'] != null) ? $_GET['q'] : $page;
        $q = ($page === null) ? $q : $page;
        $appname = \SPP\Scheduler::getContext();

        $result = self::resolvePage($q, $appname);

        if ($result !== null) {
            return $result;
        }

        // --- Physical Discovery Fallback ---
        // 1. Check auto-routing 'pages/' directory
        $pageExts = ['.php', '.html', '.vue'];
        foreach ($pageExts as $ext) {
            $autoRoutePath = 'pages/' . ltrim($q, '/');
            if (!str_ends_with($autoRoutePath, $ext) && !str_contains($autoRoutePath, '.')) {
                $autoRoutePath .= $ext;
            }
            $resolvedAuto = self::resolveInternalPath($autoRoutePath, null, $appname);
            if (file_exists(SPP_APP_DIR . SPP_DS . $resolvedAuto)) {
                return self::buildPage($q, $resolvedAuto, $q);
            }
        }

        // 2. Legacy fallback to app root
        $fallbackPath = $q;
        if (!str_ends_with($fallbackPath, '.php')) {
            $fallbackPath .= '.php';
        }

        $resolvedFallback = self::resolveInternalPath($fallbackPath, null, $appname);
        if (file_exists(SPP_APP_DIR . SPP_DS . $resolvedFallback)) {
            return self::buildPage($q, $resolvedFallback, $q);
        }

        // --- Not found ---
        $arr = ['page' => $q];
        \SPP\SPPEvent::fireEvent('PageNotFound', new \SPP\EventParams($arr), function () {
            throw new \SPP\SPPException('Page not found');
        });
        return ['url' => '', 'params' => [], 'named_params' => [], 'special' => 0];
    }

    /**
     * Core recursive resolution logic.
     */
    private static function resolvePage(string $q, string $appname, string $ymlFile = null): ?array
    {
        // --- Dynamic Registered Routes ---
        $result = self::findPageInDynamicRoutes($q, $appname);
        if ($result !== null) {
            return $result;
        }

        // --- Module Asset Discovery ---
        $result = self::findPageInAssets($q);
        if ($result !== null) {
            return $result;
        }

        [$primary, $fallback] = self::getSources($appname);
        $spl = explode('/', $q)[0];

        // --- Specials ---
        $result = self::trySourceSpecial($primary, $spl, $q, $appname)
               ?? self::trySourceSpecial($fallback, $spl, $q, $appname);
        if ($result !== null) {
            return $result;
        }

        // --- Regular pages ---
        $result = self::trySourcePage($primary, $q, $appname, $ymlFile)
               ?? self::trySourcePage($fallback, $q, $appname, $ymlFile);
        if ($result !== null) {
            return $result;
        }

        // --- Attribute Route Discovery ---
        $result = self::findPageInAttributes($q, $appname);
        if ($result !== null) {
            return $result;
        }

        // --- Module Route Discovery ---
        $result = self::findPageInModules($q, $appname);
        if ($result !== null) {
            return $result;
        }

        // --- App Config Route Discovery ---
        $result = self::findPageInAppConfig($q, $appname);
        if ($result !== null) {
            return $result;
        }

        return $result;
    }

    /**
     * Dynamically maps /modasset/<modname> routes to module asset directories.
     */
    private static function findPageInAssets(string $q): ?array
    {
        if (strpos($q, 'modasset/') !== 0) {
            return null;
        }

        $parts = explode('/', $q);
        if (count($parts) < 2) return null;
        
        $modname = $parts[1];
        
        $mod = \SPP\Registry::get('__modobj=>' . $modname);
        if ($mod && !empty($mod->Assets)) {
            $assetDirs = is_array($mod->Assets['directories'] ?? null) ? $mod->Assets['directories'] : (array) $mod->Assets;
            foreach ($assetDirs as $aDir) {
                if (is_string($aDir)) {
                    $assetDir = $mod->ModPath . SPP_DS . trim($aDir, '/');
                    $currentPath = 'modasset/' . $modname;
                    $remaining = ltrim(substr($q, strlen($currentPath)), '/');
                    return [
                        'url' => rtrim($assetDir, '/\\') . '/' . ltrim($remaining, '/\\'),
                        'special' => 1,
                        'method' => 'serveDirectory',
                        'context' => [
                            'base_dir' => rtrim($assetDir, '/\\'),
                            'relative_path' => ltrim($remaining, '/\\')
                        ]
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Scans all registered modules for route declarations.
     */
    private static function findPageInModules(string $q, ?string $appname): ?array
    {
        $mods = \SPP\Registry::get('__modobj');
        if (!is_array($mods)) {
            return null;
        }

        foreach ($mods as $mod) {
            $routes = [];
            
            // 1. Routes from module.yml
            if (isset($mod->Routes) && is_array($mod->Routes)) {
                $routes = array_merge($routes, $mod->Routes);
            }
            
            // 2. Routes from etc/routes.yml
            $routesYml = $mod->ModPath . SPP_DS . 'etc' . SPP_DS . 'routes.yml';
            if (file_exists($routesYml)) {
                try {
                    $rdata = Yaml::parseFile($routesYml);
                    if (is_array($rdata)) {
                        if (isset($rdata['routes']) && is_array($rdata['routes'])) {
                            foreach ($rdata['routes'] as $r) {
                                if (isset($r['path'])) {
                                    $routes[ltrim($r['path'], '/')] = $r;
                                }
                            }
                        } elseif (isset($rdata['pages']) && is_array($rdata['pages'])) {
                            $routes = array_merge($routes, $rdata['pages']);
                        } else {
                            $routes = array_merge($routes, $rdata);
                        }
                    }
                } catch (\Exception $e) {
                    // Silently ignore or log parsing error
                }
            }
            
            foreach ($routes as $name => $cfg) {
                if ($q === $name || strpos($q, $name . '/') === 0) {
                    return self::processRoute($name, $cfg, $q, $appname, null, $mod->ModPath);
                }
            }
        }
        return null;
    }

    /**
     * Checks for routes declared directly in the app configuration.
     */
    private static function findPageInAppConfig(string $q, ?string $appname): ?array
    {
        $appRoutes = \SPP\App::getAppConf('routes', $appname);
        if (!is_array($appRoutes)) {
            return null;
        }

        foreach ($appRoutes as $name => $cfg) {
            if ($q === $name || strpos($q, $name . '/') === 0) {
                return self::processRoute($name, $cfg, $q, $appname, null);
            }
        }
        return null;
    }

    private static function trySourceSpecial(string $source, string $spl, string $q, string $appname = null): ?array
    {
        if ($source === 'yaml') {
            return self::findSpecialInYaml($spl, $q, $appname);
        }
        if ($source === 'db') {
            return self::findSpecialInDb($spl, $q, $appname);
        }
        return null;
    }

    private static function trySourcePage(string $source, string $q, string $appname, string $ymlFile = null): ?array
    {
        if ($source === 'yaml') {
            return self::findPageInYaml($q, $appname, $ymlFile);
        }
        if ($source === 'db') {
            return self::findPageInDb($q, $appname);
        }
        return null;
    }

    /**
     * Resolves a default value using the configured source priority.
     */
    public static function getDefault($def): mixed
    {
        [$primary, $fallback] = self::getSources();

        $val = self::trySourceDefault($primary, $def)
            ?? self::trySourceDefault($fallback, $def);

        if ($val !== null) {
            return $val;
        }

        // Hardcoded Framework Defaults to avoid breaking new apps
        if ($def === 'pagedir') {
            return ''; // We now resolve paths fully in the router
        }
        if ($def === 'home') {
            return 'index';
        }

        $arr = ['def' => $def];
        \SPP\SPPEvent::fireEvent('DefaultNotFound', $arr, function (&$arr) {
            throw new \SPP\SPPException('Default ' . $arr['def'] . ' not found');
        });
        return false;
    }

    private static function trySourceDefault(string $source, string $def): ?string
    {
        if ($source === 'yaml') {
            return self::findDefaultInYaml($def);
        }
        if ($source === 'db') {
            return self::findDefaultInDb($def);
        }
        return null;
    }

    /**
     * Returns the physical filesystem path to a resource URL.
     */
    public static function getResource($url): string
    {
        $dir = self::getDefault('resdir');
        return self::stripAndJoin($dir, $url);
    }

    /**
     * Returns the physical filesystem path to a file URL.
     */
    public static function getFile($url): string
    {
        $dir = self::getDefault('filesdir');
        return self::stripAndJoin($dir, $url);
    }

    /**
     * Standard asset server with MIME detection and headers.
     * Use as a special method in pages.yml.
     */
    public static function serveResource($q): string
    {
        $file = self::getResource($q);
        return self::serveFile($file, $q);
    }

    /**
     * Serves a file from a declared asset directory.
     */
    public static function serveDirectory(string $q, array $context): string
    {
        $base = $context['base_dir'] ?? '';
        $rel  = $context['relative_path'] ?? '';
        
        $basePath = (str_starts_with($base, '/') || str_contains($base, ':')) ? $base : SPP_APP_DIR . '/' . ltrim($base, '/\\');
        $realBase = realpath($basePath);
        
        if ($realBase === false) {
            return self::serveFile('', $q);
        }
        
        $file = $realBase . '/' . ltrim($rel, '/\\');
        $realFile = realpath($file);
        
        if ($realFile === false || strpos($realFile, $realBase) !== 0) {
            return self::serveFile('', $q);
        }

        return self::serveFile($realFile, $q, $realBase);
    }

    private static function serveFile(string $file, string $q, string $allowedBase = SPP_APP_DIR): string
    {
        if (empty($file)) {
            $fullPath = '';
        } else {
            $proposedPath = (str_contains($file, ':') || str_starts_with($file, '/') || str_starts_with($file, '\\'))
                ? $file
                : SPP_APP_DIR . '/' . ltrim($file, '/\\');

            $fullPath = realpath($proposedPath);
            $basePath = realpath($allowedBase);
            
            // SECURITY: Prevent LFI / Path Traversal
            // Ensure the resolved path actually exists and is inside the allowed base directory.
            if ($fullPath === false || $basePath === false || strpos($fullPath, $basePath) !== 0) {
                $fullPath = '';
            }
        }

        if ($fullPath !== '' && file_exists($fullPath) && is_file($fullPath)) {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $mimes = [
                'js'   => 'application/javascript',
                'css'  => 'text/css',
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'svg'  => 'image/svg+xml',
                'ico'  => 'image/x-icon',
                'json' => 'application/json',
                'txt'  => 'text/plain',
                'pdf'  => 'application/pdf',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf'  => 'font/ttf'
            ];

            $mime = $mimes[$ext] ?? 'application/octet-stream';
            header("Content-Type: $mime");
            header("Content-Length: " . filesize($fullPath));

            // Set cache headers for assets
            header("Cache-Control: public, max-age=31536000");
            header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

            readfile($fullPath);
            exit;
        }

        @file_put_contents(SPP_LOG_DIR . '/debug_routes.log', "SERVE FILE 404: file={$file}, fullPath={$fullPath}, exists=" . (file_exists($fullPath) ? 'yes' : 'no') . "\n", FILE_APPEND);
        header("HTTP/1.0 404 Not Found");
        echo "Resource not found: " . htmlspecialchars($q);
        exit;
    }

    private static function stripAndJoin(string $dir, string $url): string
    {
        $dir = ltrim($dir, '/');
        $url = ltrim($url, '/');
        $spl = explode('/', $url)[0];
        $url = substr($url, strlen($spl));
        return $dir . $url;
    }

    // -------------------------------------------------------------------------
    // Migration utility
    // -------------------------------------------------------------------------

    /**
     * One-time utility: imports all data from pages.yml into the DB tables.
     * Safe to call multiple times — existing rows are skipped via INSERT IGNORE.
     *
     * @return array{pages: int, defaults: int, specials: int} Counts of inserted rows
     */
    public static function importYamlToDb(): array
    {
        $db   = new \SPPMod\SPPDB\SPPDB();
        $yaml = self::getYaml();
        $counts = ['pages' => 0, 'defaults' => 0, 'specials' => 0];

        foreach ($yaml['pages'] ?? [] as $page) {
            $db->execute_query(
                'INSERT IGNORE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages') . ' (name, url) VALUES (?, ?)',
                [$page['name'], $page['url']]
            );
            $counts['pages']++;
        }

        foreach ($yaml['defaults'] ?? [] as $key => $val) {
            $db->execute_query(
                'INSERT IGNORE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_defaults') . ' (defkey, defval) VALUES (?, ?)',
                [$key, $val]
            );
            $counts['defaults']++;
        }

        foreach ($yaml['specials'] ?? [] as $special) {
            $db->execute_query(
                'INSERT IGNORE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_specials') . ' (name, method) VALUES (?, ?)',
                [$special['name'], $special['method']]
            );
            $counts['specials']++;
        }

        return $counts;
    }

    // -------------------------------------------------------------------------
    // Cache management
    // -------------------------------------------------------------------------

    private static array $dynamicRoutes = [];

    /**
     * Dynamically registers a runtime routing or asset prefix entry directly into the routing cache buffer.
     */
    public static function registerRoute(string $path, array $target, string $appname = ''): void
    {
        $appname = $appname ?: \SPP\Scheduler::getContext();
        if (!isset(self::$dynamicRoutes[$appname])) {
            self::$dynamicRoutes[$appname] = [];
        }
        self::$dynamicRoutes[$appname][$path] = $target;

        $ymlFile = self::getAppPagesFile($appname);
        self::getYaml($ymlFile);
        if (!isset(self::$yamlFileCache[$ymlFile]['pages'])) {
            self::$yamlFileCache[$ymlFile]['pages'] = [];
        }
        self::$yamlFileCache[$ymlFile]['pages'][$path] = $target;
    }

    private static function findPageInDynamicRoutes(string $q, string $appname): ?array
    {
        $contexts = array_unique(array_filter([$appname, \SPP\Scheduler::getContext(), 'default']));
        foreach ($contexts as $ctx) {
            $routes = self::$dynamicRoutes[$ctx] ?? [];
            foreach ($routes as $name => $cfg) {
                if ($q === $name || strpos($q, $name . '/') === 0) {
                    return self::processRoute($name, $cfg, $q, $ctx, self::getAppPagesFile($ctx), null);
                }
            }
        }
        return null;
    }

    /**
     * Clears all in-memory caches. Call after modifying routes at runtime.
     */
    public static function clearCache(): void
    {
        self::$yamlFileCache = [];
        self::$dbAppCache   = [];
        self::$sourceAppCache = [];
    }

    /**
     * Returns the array of registered pages from both YAML and DB.
     */
    public static function listPages(): array
    {
        $yaml = self::getYaml();
        $ymlPages = $yaml['pages'] ?? [];
        $ymlFile = self::getAppPagesFile();
        foreach ($ymlPages as &$p) {
            $p['source'] = 'yaml';
            $p['source_path'] = $ymlFile;
        }

        $dbPages = [];
        if (\SPP\Module::isEnabled('sppdb')) {
            $data = self::getDb();
            $dbPages = $data['pages'] ?? [];
            $db = new \SPPMod\SPPDB\SPPDB();
            $dbSummary = $db->getConnectionSummary();
            foreach ($dbPages as &$p) {
                $p['source'] = 'db';
                $p['db_summary'] = $dbSummary;
            }
        }

        return array_merge($ymlPages, $dbPages);
    }

    /**
     * Saves (Add or Update) a page route in either pages.yml or the database.
     */
    public static function savePage(string $name, string $url, string $source = 'yaml'): bool
    {
        if ($source === 'yaml') {
            $appname = \SPP\Scheduler::getContext();
            $file = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'pages.yml';

            $yaml = file_exists($file) ? Yaml::parseFile($file) : ['pages' => [], 'defaults' => [], 'specials' => []];
            if (!isset($yaml['pages'])) {
                $yaml['pages'] = [];
            }

            $updated = false;
            foreach ($yaml['pages'] as &$p) {
                if ($p['name'] === $name) {
                    $p['url'] = $url;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $yaml['pages'][] = ['name' => $name, 'url' => $url];
            }

            file_put_contents($file, Yaml::dump($yaml, 4, 2));
        } elseif ($source === 'db') {

            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query(
                'REPLACE INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages') . ' (name, url) VALUES (?, ?)',
                [$name, $url]
            );
        }

        self::clearCache();
        return true;
    }

    /**
     * Removes a page route from either pages.yml or the database by name.
     */
    public static function removePage(string $name, string $source = 'yaml'): bool
    {
        if ($source === 'yaml') {
            $appname = \SPP\Scheduler::getContext();
            $file = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'pages.yml';
            if (!file_exists($file)) {
                return false;
            }

            $yaml = Yaml::parseFile($file);
            if (!isset($yaml['pages'])) {
                return false;
            }

            $oldCount = count($yaml['pages']);
            $yaml['pages'] = array_values(array_filter($yaml['pages'], fn ($p) => ($p['name'] ?? '') !== $name));

            if (count($yaml['pages']) === $oldCount) {
                return false;
            }

            file_put_contents($file, Yaml::dump($yaml, 4, 2));
        } elseif ($source === 'db') {

            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages') . ' WHERE name=?', [$name]);
        }

        self::clearCache();
        return true;
    }

    /**
     * Resolves routes discovered via PHP Attributes (#[Route]).
     * Scans controller directories and caches the routing table.
     */
    private static function findPageInAttributes(string $q, string $appname): ?array
    {
        $cacheFile = SPP_BASE_DIR . '/var/cache/routes_' . $appname . '.php';
        
        // Cache busting during development or if cache is missing
        $isDev = getenv('APP_ENV') === 'local' || (defined('SPP_DEBUG') && SPP_DEBUG);
        
        if (!file_exists($cacheFile) || $isDev) {
            $routes = [];
            // Assuming standard location is SPP_APP_DIR/controllers or SPP_APP_DIR/src/Controllers
            $dirsToScan = [
                SPP_APP_DIR . '/controllers',
                SPP_APP_DIR . '/src/Controllers',
                SPP_APP_DIR . '/src/controllers'
            ];
            
            foreach ($dirsToScan as $dir) {
                if (is_dir($dir)) {
                    $scanned = RouteScanner::scan($dir);
                    $routes = array_merge($routes, $scanned);
                }
            }
            
            if (!is_dir(dirname($cacheFile))) {
                mkdir(dirname($cacheFile), 0777, true);
            }
            
            file_put_contents($cacheFile, '<?php return ' . var_export($routes, true) . ';');
        } else {
            $routes = include $cacheFile;
        }

        // Exact match
        if (isset($routes[$q])) {
            $route = $routes[$q];
            return self::buildPage($q, '', $q, ['controller' => $route['controller'], 'middleware' => $route['middleware']]);
        }

        // Dynamic parameter matching (e.g., /users/{id})
        foreach ($routes as $routePath => $route) {
            if (strpos($routePath, '{') !== false) {
                $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $routePath);
                $pattern = '#^' . $pattern . '$#';
                
                if (preg_match($pattern, $q, $matches)) {
                    $params = [];
                    foreach ($matches as $k => $v) {
                        if (is_string($k)) {
                            $params[$k] = $v;
                        }
                    }
                    
                    $pageDef = self::buildPage($q, '', $q, ['controller' => $route['controller'], 'middleware' => $route['middleware']]);
                    $pageDef['params'] = array_values($params);
                    $pageDef['named_params'] = $params;
                    return $pageDef;
                }
            }
        }

        return null;
    }
}
