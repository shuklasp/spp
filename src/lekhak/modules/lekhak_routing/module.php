<?php
namespace Lekhak\Modules\LekhakRouting;

/**
 * Handles URL alias generation, path routing, and clean URL management.
 * @configure admin/config/lekhak_routing
 */

class LekhakModulePathauto {
    private $name = 'lekhak_routing';
    private $title = 'lekhak_routing';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_url_aliases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_path VARCHAR(255) NOT NULL,
                alias_path VARCHAR(255) NOT NULL,
                entity_type VARCHAR(50),
                entity_id INTEGER
            )");
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_pathauto_patterns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                bundle VARCHAR(50),
                pattern VARCHAR(255) NOT NULL,
                weight INTEGER DEFAULT 0
            )");
            
            // Seed a default pattern if table is empty
            $res = $db->execute_query("SELECT id FROM lekhak_pathauto_patterns LIMIT 1");
            if (empty($res)) {
                $db->execute_query("INSERT INTO lekhak_pathauto_patterns (entity_type, bundle, pattern) VALUES (?, ?, ?)", ['node', 'article', '/article/[node:title]']);
            }
        } catch (\Exception $e) {}
        
        return true;
    }

    /**
     * Intercepts entity save to auto-generate SEO URL patterns.
     */
    public function hook_entity_presave(&$entity) {
        if (!isset($entity->bundle) || !isset($entity->title)) return;

        // Determine if we need an alias generated
        $needs_alias = empty($entity->alias);

        if ($needs_alias) {
            $db = new \SPPMod\SPPDB\SPPDB();
            // Get pattern for this entity type
            $patterns = $db->execute_query("SELECT pattern FROM lekhak_pathauto_patterns WHERE entity_type=? ORDER BY weight DESC LIMIT 1", ['node']);
            
            if (!empty($patterns)) {
                $pattern = $patterns[0]['pattern'];
                
                // Use Token module if available
                $alias = $pattern;
                if (class_exists('\\Lekhak\\Modules\\Token\\LekhakModuleToken')) {
                    $tokenMod = new \Lekhak\Modules\Token\LekhakModuleToken();
                    $alias = $tokenMod->replaceTokens($pattern, ['node' => (array)$entity]);
                } else {
                    // Fallback dumb replacement
                    $alias = str_replace('[node:title]', $entity->title, $pattern);
                }

                // Slugify the alias (except slashes)
                $parts = explode('/', $alias);
                $slugifiedParts = array_map(function($p) {
                    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $p), '-'));
                }, $parts);
                $finalAlias = '/' . ltrim(implode('/', $slugifiedParts), '/');

                $entity->alias = ltrim($finalAlias, '/');
            }
        }
    }
    
    /**
     * Intercepts post-save to record the alias in the registry.
     */
    public function hook_entity_insert($entity) {
        if (!empty($entity->alias) && !empty($entity->id)) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $source = 'lekhak/node/' . ltrim($entity->alias, '/');
            $alias = ltrim($entity->alias, '/');
            $db->execute_query("INSERT INTO lekhak_url_aliases (source_path, alias_path, entity_type, entity_id) VALUES (?, ?, ?, ?)", [$source, $alias, 'node', $entity->id]);
        }
    }

    /**
     * Intercepts request to map URL aliases back to source paths
     */
    public function hook_request_init() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Very basic alias resolution check
        if ($uri !== '/' && strpos($uri, '/admin') !== 0) {
            $db = new \SPPMod\SPPDB\SPPDB();
            try {
                $aliasRecord = $db->execute_query("SELECT source_path FROM lekhak_url_aliases WHERE alias_path = ? LIMIT 1", [$uri]);
                if (!empty($aliasRecord)) {
                    // Internally rewrite the request
                    $_SERVER['REQUEST_URI'] = $aliasRecord[0]['source_path'];
                    $_GET['q'] = $aliasRecord[0]['source_path'];
                }
            } catch (\Exception $e) {}
        }
    }


    // sh404SEF Extension
    public static function hook_page_not_found($path) {
        error_log("[Pathauto sh404SEF] Logging 404 for path: " . $path);
        // Implement automatic redirection suggestion
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_sh404_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT, hits INTEGER)");
        $db->execute_query("INSERT INTO lekhak_sh404_logs (path, hits) VALUES (?, 1) ON CONFLICT(path) DO UPDATE SET hits = hits + 1", [$path]);
    }


    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
  'enabled' => 
  [
    'type' => 'checkbox',
    'title' => 'Enable advanced features',
    'default' => true,
  ],
  'log_level' => 
  [
    'type' => 'select',
    'title' => 'Log Level',
    'options' => 
    [
      'info' => 'Info',
      'warning' => 'Warning',
      'error' => 'Error',
    ],
    'default' => 'warning',
  ],
];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_routing',
    'title' => 'lekhak_routing',
    'instance' => new LekhakModulePathauto()
];
