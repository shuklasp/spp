<?php
namespace App\Lekhak\Services;

use App\Lekhak\Entities\ViewDefinition;

/**
 * ViewRenderer
 * 
 * Compiles a ViewDefinition into SQL, executes it, and renders HTML.
 */
class ViewRenderer
{
    /**
     * Render a view by its machine name.
     *
     * @param string $viewName
     * @param array $runtimeParams Query string params for exposed filters/pagination
     * @return string HTML output
     */
    public static function render(string $viewName, array $runtimeParams = []): string
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('view_definitions');

        if (!$db->tableExists($table))
            return "View definitions table missing.";

        $rows = $db->execute_query("SELECT * FROM {$table} WHERE name = ?", [$viewName]);
        if (empty($rows))
            return "View '{$viewName}' not found.";

        $view = $rows[0];
        $baseTable = $view['base_table'];
        $fields = json_decode($view['fields'], true) ?? ['*'];
        $filters = json_decode($view['filters'], true) ?? [];
        $sorts = json_decode($view['sorts'], true) ?? [];
        $limit = (int) $view['pagination'];
        $format = $view['display_format'] ?? 'list';

        // Very basic SQL builder
        $select = implode(', ', array_map(fn($f) => "`{$f}`", $fields));
        $sql = "SELECT {$select} FROM " . \SPPMod\SPPDB\SPPDB::sppTable($baseTable);

        $where = [];
        $params = [];
        foreach ($filters as $filter) {
            $col = $filter['field'];
            $op = $filter['op'] ?? '=';
            $val = $filter['value'] ?? null;

            // Exposed filter override
            if (!empty($filter['exposed']) && isset($runtimeParams[$col])) {
                $val = $runtimeParams[$col];
            }

            if ($val !== null && $val !== '') {
                $where[] = "`{$col}` {$op} ?";
                if ($op === 'LIKE')
                    $val = "%{$val}%";
                $params[] = $val;
            }
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($sorts)) {
            $order = [];
            foreach ($sorts as $sort) {
                $order[] = "`{$sort['field']}` " . strtoupper($sort['dir']);
            }
            $sql .= " ORDER BY " . implode(', ', $order);
        }

        if ($limit > 0) {
            $page = max(1, (int) ($runtimeParams['page'] ?? 1));
            $offset = ($page - 1) * $limit;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        try {
            $results = $db->execute_query($sql, $params);
            return self::formatResults($results, $format);
        } catch (\Exception $e) {
            return "Error rendering view: " . $e->getMessage();
        }
    }

    private static function formatResults(array $results, string $format): string
    {
        if (empty($results))
            return "<div class='spp-view-empty'>No results found.</div>";

        $html = "<div class='spp-view spp-view-{$format}'>";

        if ($format === 'table') {
            $html .= "<table class='spp-table'><thead><tr>";
            foreach (array_keys($results[0]) as $col) {
                $html .= "<th>" . htmlspecialchars($col) . "</th>";
            }
            $html .= "</tr></thead><tbody>";
            foreach ($results as $row) {
                $html .= "<tr>";
                foreach ($row as $val) {
                    $html .= "<td>" . htmlspecialchars((string) $val) . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</tbody></table>";
        } else {
            // Grid or List
            $html .= "<ul class='spp-view-list'>";
            foreach ($results as $row) {
                $html .= "<li>";
                foreach ($row as $col => $val) {
                    $html .= "<div class='view-field-{$col}'><strong>{$col}:</strong> " . htmlspecialchars((string) $val) . "</div>";
                }
                $html .= "</li>";
            }
            $html .= "</ul>";
        }

        $html .= "</div>";
        return $html;
    }
}
