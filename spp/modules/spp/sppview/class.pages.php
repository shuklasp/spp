<?php
namespace SPPMod\SPPView;
use Symfony\Component\Yaml\Yaml;

/**
 * class Pages
 *
 * Resolves page routes from YAML and/or database with configurable priority
 * and automatic fallback. Database source is only active when the sppdb
 * module is enabled. Tables are created automatically on first use.
 *
 * Priority is configured in modsconf/sppview/config.yml:
 *   page_source_primary:  yaml | db
 *   page_source_fallback: yaml | db | none
 *
 * @author Satya Prakash Shukla
 */
class Pages extends \SPP\SPPObject
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

        $primary  = \SPP\Module::getConfig('page_source_primary',  'sppview', $appname) ?: 'yaml';
        $fallback = \SPP\Module::getConfig('page_source_fallback', 'sppview', $appname) ?: 'none';

        // Normalize aliases
        $map = ['dbfirst' => 'db', 'filefirst' => 'yaml', 'file' => 'yaml'];
        $primary  = $map[$primary] ?? $primary;
        $fallback = $map[$fallback] ?? $fallback;

        // Silently demote DB to 'none' when sppdb is not loaded
        if (!$dbAvailable) {
            if ($primary  === 'db') $primary  = 'yaml';
            if ($fallback === 'db') $fallback = 'none';
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
        } else {
            $file = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'pages.yml';
        }

        if (!file_exists($file)) {
            $legacyFile = APP_ETC_DIR . SPP_DS . 'pages.yml';
            if (file_exists($legacyFile)) return $legacyFile;
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
            self::$yamlFileCache[$ymlFile] = Yaml::parseFile($ymlFile);
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            throw new \SPP\SPPException('Failed to parse pages.yml (' . $ymlFile . '): ' . $e->getMessage(), 1000, $e);
        }

        return self::$yamlFileCache[$ymlFile];
    }

    // -------------------------------------------------------------------------
    // Database driver
    // -------------------------------------------------------------------------

    /**
     * Ensures all three routing tables exist, creating them if absent.
     */
    public static function ensureDbSchema(): void
    {
        $db = new \SPPMod\SPPDB\SPPDB();

        $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages') . ' (
            id    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name  VARCHAR(255) NOT NULL UNIQUE,
            url   VARCHAR(500) NOT NULL
        )');

        $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_defaults') . ' (
            id     INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            defkey VARCHAR(100) NOT NULL UNIQUE,
            defval VARCHAR(500) NOT NULL
        )');

        $db->execute_query('CREATE TABLE IF NOT EXISTS ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_specials') . ' (
            id     INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name   VARCHAR(100) NOT NULL UNIQUE,
            method VARCHAR(100) NOT NULL
        )');
    }

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

        self::ensureDbSchema();
        $db = new \SPPMod\SPPDB\SPPDB();

        $pages    = $db->execute_query('SELECT name, url FROM '    . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages'));
        $defaults = $db->execute_query('SELECT defkey, defval FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_defaults'));
        $specials = $db->execute_query('SELECT name, method FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_specials'));

        // Normalise into the same shape getYaml() returns
        $defaultsMap = [];
        foreach ($defaults as $row) {
            $defaultsMap[$row['defkey']] = $row['defval'];
        }

        self::$dbAppCache[$appname] = [
            'pages'    => $pages,
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

        // Exact match prioritized
        if (isset($yaml['pages'][$q])) {
             $res = self::processRoute($q, $yaml['pages'][$q], $q, $appname, $ymlFile);
             if ($res) return $res;
        }

        foreach ($yaml['pages'] as $name => $routeConfig) {
            $name = (string)$name;
            // Robust prefix matching for parameters / delegation
            if ($name !== '' && strpos($q, $name) === 0) {
                $res = self::processRoute($name, $routeConfig, $q, $appname, $ymlFile);
                if ($res) return $res;
            }
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
            return self::buildPage($name, $resolvedUrl, $q);
        }
        
        // Case D: Asset Directory Support
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
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return ltrim($path, '/\\');
        }

        if ($modulePath) {
            // Convert absolute module path to relative path from SPP_APP_DIR
            $root = realpath(SPP_APP_DIR);
            $mod  = realpath($modulePath);
            
            if ($root && $mod && str_starts_with($mod, $root)) {
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
        
        if ($root && $src && str_starts_with($src, $root)) {
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
            if ($parm === 'q') continue;
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
        // If no route is defined, try to find the file in the app source directory
        $fallbackPath = $q;
        if (!str_ends_with($fallbackPath, '.php')) $fallbackPath .= '.php';
        
        $resolvedFallback = self::resolveInternalPath($fallbackPath, null, $appname);
        if (file_exists(SPP_APP_DIR . SPP_DS . $resolvedFallback)) {
             return self::buildPage($q, $resolvedFallback, $q);
        }

        // --- Not found ---
        $arr = ['page' => $q];
        \SPP\SPPEvent::fireEvent('PageNotFound', $arr, function () {
            throw new \SPP\SPPException('Page not found');
        });
        return ['url' => '', 'params' => [], 'named_params' => [], 'special' => 0];
    }

    /**
     * Core recursive resolution logic.
     */
    private static function resolvePage(string $q, string $appname, string $ymlFile = null): ?array
    {
        [$primary, $fallback] = self::getSources($appname);
        $spl = explode('/', $q)[0];

        // --- Specials ---
        $result = self::trySourceSpecial($primary, $spl, $q, $appname)
               ?? self::trySourceSpecial($fallback, $spl, $q, $appname);
        if ($result !== null) return $result;

        // --- Regular pages ---
        $result = self::trySourcePage($primary, $q, $appname, $ymlFile)
               ?? self::trySourcePage($fallback, $q, $appname, $ymlFile);
        if ($result !== null) return $result;

        // --- Module Route Discovery ---
        $result = self::findPageInModules($q, $appname);
        if ($result !== null) return $result;

        // --- App Config Route Discovery ---
        $result = self::findPageInAppConfig($q, $appname);
        if ($result !== null) return $result;
        
        return $result;
    }

    /**
     * Scans all registered modules for route declarations.
     */
    private static function findPageInModules(string $q, ?string $appname): ?array
    {
        $mods = \SPP\Registry::get('__modobj');
        if (!is_array($mods)) return null;

        foreach ($mods as $mod) {
            if (isset($mod->Routes) && is_array($mod->Routes)) {
                foreach ($mod->Routes as $name => $cfg) {
                    if ($q === $name || strpos($q, $name . '/') === 0) {
                        return self::processRoute($name, $cfg, $q, $appname, null, $mod->ModPath);
                    }
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
        if (!is_array($appRoutes)) return null;

        foreach ($appRoutes as $name => $cfg) {
            if ($q === $name || strpos($q, $name . '/') === 0) {
                return self::processRoute($name, $cfg, $q, $appname, null);
            }
        }
        return null;
    }

    private static function trySourceSpecial(string $source, string $spl, string $q, string $appname = null): ?array
    {
        if ($source === 'yaml') return self::findSpecialInYaml($spl, $q, $appname);
        if ($source === 'db')   return self::findSpecialInDb($spl, $q, $appname);
        return null;
    }

    private static function trySourcePage(string $source, string $q, string $appname, string $ymlFile = null): ?array
    {
        if ($source === 'yaml') return self::findPageInYaml($q, $appname, $ymlFile);
        if ($source === 'db')   return self::findPageInDb($q, $appname);
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
        if ($source === 'yaml') return self::findDefaultInYaml($def);
        if ($source === 'db')   return self::findDefaultInDb($def);
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
        $file = rtrim($base, '/\\') . '/' . ltrim($rel, '/\\');
        
        return self::serveFile($file, $q);
    }

    private static function serveFile(string $file, string $q): string
    {
        $fullPath = SPP_APP_DIR . '/' . ltrim($file, '/\\');

        if (file_exists($fullPath) && is_file($fullPath)) {
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
                'woff2'=> 'font/woff2',
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
        self::ensureDbSchema();
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
            if (!isset($yaml['pages'])) $yaml['pages'] = [];

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
        } else if ($source === 'db') {
            self::ensureDbSchema();
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
            if (!file_exists($file)) return false;

            $yaml = Yaml::parseFile($file);
            if (!isset($yaml['pages'])) return false;

            $oldCount = count($yaml['pages']);
            $yaml['pages'] = array_values(array_filter($yaml['pages'], fn($p) => ($p['name'] ?? '') !== $name));
            
            if (count($yaml['pages']) === $oldCount) return false;

            file_put_contents($file, Yaml::dump($yaml, 4, 2));
        } else if ($source === 'db') {
            self::ensureDbSchema();
            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('sppview_pages') . ' WHERE name=?', [$name]);
        }

        self::clearCache();
        return true;
    }
}
