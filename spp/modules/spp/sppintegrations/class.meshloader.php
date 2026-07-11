<?php
namespace SPPMod\SPPIntegrations;

use SPP\Core\Router\Router;

/**
 * Class MeshLoader
 * 
 * Parses the mesh.yml configuration file and mounts legacy applications
 * into the Router as passthrough routes with A La Carte features.
 */
class MeshLoader
{
    private static $configFile = __DIR__ . '/../../etc/mesh.yml';

    public static function load()
    {
        if (!file_exists(self::$configFile)) {
            return; // No mesh configured
        }

        // Ideally we use yaml_parse_file, but for environments without the pecl-yaml extension,
        // we can implement a basic flat parser, or use a cached compiled PHP array.
        
        $routes = self::parseYaml(self::$configFile);
        
        if (isset($routes['mesh_routes']) && is_array($routes['mesh_routes'])) {
            foreach ($routes['mesh_routes'] as $uri => $config) {
                $target = $config['target'] ?? null;
                $features = $config['features'] ?? [];
                
                if ($target) {
                    Router::passthrough($uri, $target, $features);
                }
            }
        }
    }

    /**
     * A highly simplified YAML parser fallback for the Mesh Router.
     */
    private static function parseYaml(string $filePath): array
    {
        if (function_exists('yaml_parse_file')) {
            return yaml_parse_file($filePath);
        }

        // Extremely rudimentary YAML parser specifically for the mesh.yml structure
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $result = ['mesh_routes' => []];
        $currentRoute = null;

        foreach ($lines as $line) {
            // Remove comments
            $line = preg_replace('/#.*$/', '', $line);
            if (trim($line) === '') continue;

            if (preg_match('/^\s{2}([^\s:]+):/', $line, $matches)) {
                $currentRoute = $matches[1];
                $result['mesh_routes'][$currentRoute] = ['features' => []];
            } elseif ($currentRoute && preg_match('/^\s{4}target:\s*(.+)$/', $line, $matches)) {
                $result['mesh_routes'][$currentRoute]['target'] = trim($matches[1]);
            } elseif ($currentRoute && preg_match('/^\s{4}integration:\s*(.+)$/', $line, $matches)) {
                $result['mesh_routes'][$currentRoute]['integration'] = trim($matches[1]);
            } elseif ($currentRoute && preg_match('/^\s{6}-\s*(.+)$/', $line, $matches)) {
                $result['mesh_routes'][$currentRoute]['features'][] = trim($matches[1]);
            }
        }

        return $result;
    }
}
