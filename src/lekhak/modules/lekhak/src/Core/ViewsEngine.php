<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPDB\SPPDB;

/**
 * Class ViewsEngine
 * Simulates a Drupal-style Views query builder using YAML declarations.
 */
class ViewsEngine
{
    /**
     * Executes a view by name or direct YAML array definition.
     */
    public static function executeView($viewInput): array
    {
        $viewData = [];
        if (is_array($viewInput)) {
            $viewData = $viewInput;
        } elseif (is_string($viewInput)) {
            // Load from YAML file in configuration directory
            $viewFile = self::resolveViewFile($viewInput);
            if ($viewFile && file_exists($viewFile)) {
                if (function_exists('yaml_parse_file')) {
                    $viewData = yaml_parse_file($viewFile);
                } elseif (class_exists('\Symfony\Component\Yaml\Yaml')) {
                    $viewData = \Symfony\Component\Yaml\Yaml::parseFile($viewFile);
                }
            }
        }

        if (empty($viewData)) {
            return [];
        }

        $baseTable = $viewData['base_table'] ?? 'nodes';
        $db = new SPPDB();
        $table = SPPDB::sppTable($baseTable);

        $sql = "SELECT * FROM %tab%";
        $clauses = [];
        $values = [];

        // Apply filters
        if (!empty($viewData['filters']) && is_array($viewData['filters'])) {
            foreach ($viewData['filters'] as $field => $val) {
                $fieldClean = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                $clauses[] = "{$fieldClean} = ?";
                $values[] = $val;
            }
        }

        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        // Apply sorting
        if (!empty($viewData['sort']) && is_array($viewData['sort'])) {
            $sorts = [];
            foreach ($viewData['sort'] as $field => $dir) {
                $fieldClean = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                $dirClean = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $sorts[] = "{$fieldClean} {$dirClean}";
            }
            $sql .= " ORDER BY " . implode(", ", $sorts);
        }

        // Apply limit
        $limit = isset($viewData['pager']['limit']) ? (int)$viewData['pager']['limit'] : null;
        if ($limit) {
            $sql .= " LIMIT " . $limit;
        }

        $rows = $db->exec_squery($sql, $table, $values);
        
        // Hydrate node entities if listing nodes
        if ($baseTable === 'nodes') {
            $nodes = [];
            foreach ($rows as $row) {
                $node = new \App\Lekhak\Entities\Node();
                $node->setId($row['id']);
                foreach ($row as $k => $v) {
                    if (!is_numeric($k)) {
                        $node->set($k, $v);
                    }
                }
                $node->after_load();
                $nodes[] = $node;
            }
            return $nodes;
        }

        return $rows;
    }

    /**
     * Locate a view definition file.
     */
    protected static function resolveViewFile(string $viewName): ?string
    {
        $appname = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
        $paths = [];
        $ds = DIRECTORY_SEPARATOR;
        if (defined('APP_ETC_DIR')) {
            $paths[] = APP_ETC_DIR . $ds . $appname . $ds . 'views' . $ds . $viewName . '.view.yml';
            $paths[] = APP_ETC_DIR . $ds . 'views' . $ds . $viewName . '.view.yml';
        }
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }
}
