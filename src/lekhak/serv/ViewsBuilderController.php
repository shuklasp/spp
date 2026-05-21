<?php
namespace App\Lekhak\Serv;

/**
 * Class ViewsBuilderController
 * Visual administration UI for defining and configuring entity Views using SppEntityQuery.
 */
class ViewsBuilderController
{
    /**
     * Display the main views administration UI.
     */
    public function index()
    {
        $views = $this->loadViewsConfig();
        
        // Using Drishyam if available, otherwise just output a raw interactive HTML admin layout
        if (class_exists('\\SPPMod\\Drishyam\\Drishyam')) {
            try {
                return \SPPMod\Drishyam\Drishyam::render("views_admin", ['views' => $views]);
            } catch (\Exception $e) {
                // Fallback to raw output if template doesn't exist
            }
        }
        
        return $this->rawAdminHtml($views);
    }

    /**
     * API to get configured views.
     */
    public function apiGetViews()
    {
        $views = $this->loadViewsConfig();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'data' => array_values($views)]);
        exit;
    }

    /**
     * API to save a view configuration.
     */
    public function apiSaveView()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid view configuration']);
            exit;
        }

        $views = $this->loadViewsConfig();
        $views[$input['id']] = $input;
        $this->saveViewsConfig($views);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'View saved successfully']);
        exit;
    }

    /**
     * Execute a view by its ID and render its output.
     */
    public function executeView(string $viewId)
    {
        $views = $this->loadViewsConfig();
        if (!isset($views[$viewId])) {
            return "<div class='spp-view-error'>View not found: " . htmlspecialchars($viewId) . "</div>";
        }

        $config = $views[$viewId];
        $entityClass = $config['entity_class'] ?? '\\SPPMod\\Lekhak\\Core\\LekhakNode';

        if (!class_exists($entityClass)) {
            return "<div class='spp-view-error'>Entity class not found: " . htmlspecialchars($entityClass) . "</div>";
        }

        try {
            $query = new \SPPMod\SPPEntity\SppEntityQuery($entityClass);

            if (!empty($config['conditions'])) {
                foreach ($config['conditions'] as $cond) {
                    if (empty($cond['field'])) continue;
                    $val = $cond['value'] ?? '';
                    $op = $cond['operator'] ?? '=';
                    
                    if (!empty($cond['dynamic'])) {
                        $query->dynamicCondition($cond['field'], $val, $op);
                    } else {
                        $query->condition($cond['field'], $val, $op);
                    }
                }
            }

            if (!empty($config['sort'])) {
                $query->sort($config['sort']['field'], $config['sort']['direction'] ?? 'ASC');
            }

            if (!empty($config['limit'])) {
                $query->limit((int)$config['limit'], isset($config['offset']) ? (int)$config['offset'] : null);
            }

            $results = $query->execute();

            $template = $config['template'] ?? null;
            if ($template && class_exists('\\SPPMod\\Drishyam\\Drishyam')) {
                return \SPPMod\Drishyam\Drishyam::render($template, ['results' => $results, 'config' => $config]);
            }

            return $this->renderGenericTable($results, $config);

        } catch (\Exception $e) {
            return "<div class='spp-view-error'>Error executing view: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    protected function loadViewsConfig(): array
    {
        $file = SPP_APP_DIR . '/var/views.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    protected function saveViewsConfig(array $views): void
    {
        $dir = SPP_APP_DIR . '/var';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . '/views.json';
        file_put_contents($file, json_encode($views, JSON_PRETTY_PRINT));
    }

    protected function renderGenericTable($results, $config)
    {
        if (empty($results)) {
            return "<div class='spp-view-empty'>No results found.</div>";
        }

        $fields = $config['display_fields'] ?? ['id', 'title'];

        $html = "<table class='spp-view-table' style='width: 100%; border-collapse: collapse;'>";
        $html .= "<thead><tr>";
        foreach ($fields as $field) {
            $html .= "<th style='text-align: left; padding: 8px; border-bottom: 2px solid #ddd;'>" . htmlspecialchars(ucfirst($field)) . "</th>";
        }
        $html .= "</tr></thead><tbody>";

        foreach ($results as $entity) {
            $html .= "<tr>";
            foreach ($fields as $field) {
                // Determine if it's a dynamic field or base field by using generic accessor
                $value = method_exists($entity, 'get') ? $entity->get($field) : ($entity->{$field} ?? '');
                $html .= "<td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars((string)$value) . "</td>";
            }
            $html .= "</tr>";
        }

        $html .= "</tbody></table>";
        return $html;
    }

    protected function rawAdminHtml($views)
    {
        $viewsJson = htmlspecialchars(json_encode(array_values($views)), ENT_QUOTES, 'UTF-8');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPP Views Builder</title>
    <style>
        :root { --bg: #111827; --card: #1f2937; --text: #f9fafb; --accent: #3b82f6; --accent-hover: #2563eb; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 2rem; margin: 0; }
        h1 { margin-top: 0; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: var(--card); border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .btn { background: var(--accent); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn:hover { background: var(--accent-hover); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 0.5rem; border-radius: 6px; border: 1px solid #4b5563; background: #374151; color: white; }
        .view-item { padding: 1rem; border: 1px solid #374151; border-radius: 8px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .view-item h3 { margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SPP Views Builder</h1>
        <p>Visually build queries utilizing the SppEntityQuery API.</p>
        
        <div class="card" id="viewsList" style="margin-bottom: 2rem;">
            <h2>Existing Views</h2>
            <div id="viewsContainer"></div>
        </div>

        <div class="card" id="viewEditor">
            <h2>Create / Edit View</h2>
            <form id="viewForm">
                <div class="form-group">
                    <label>View ID (Machine Name)</label>
                    <input type="text" id="viewId" name="id" required placeholder="e.g. recent_articles">
                </div>
                <div class="form-group">
                    <label>View Name</label>
                    <input type="text" id="viewName" name="name" required placeholder="e.g. Recent Articles">
                </div>
                <div class="form-group">
                    <label>Entity Class</label>
                    <input type="text" id="viewEntityClass" name="entity_class" value="\SPPMod\Lekhak\Core\LekhakNode" required>
                </div>
                <div class="form-group">
                    <label>Display Fields (comma separated)</label>
                    <input type="text" id="viewDisplayFields" name="display_fields" value="id,title,status" required>
                </div>
                <div class="form-group">
                    <label>Limit</label>
                    <input type="number" id="viewLimit" name="limit" value="10">
                </div>
                <!-- Simplified for demonstration; a real builder would have dynamic condition rows -->
                <button type="submit" class="btn">Save View Configuration</button>
            </form>
        </div>
    </div>

    <script>
        const viewsData = {$viewsJson};
        
        function renderViewsList() {
            const container = document.getElementById('viewsContainer');
            container.innerHTML = '';
            if (viewsData.length === 0) {
                container.innerHTML = '<p>No views configured yet.</p>';
                return;
            }
            
            viewsData.forEach(view => {
                const div = document.createElement('div');
                div.className = 'view-item';
                div.innerHTML = `
                    <div>
                        <h3>\${view.name}</h3>
                        <small>ID: \${view.id} | Entity: \${view.entity_class}</small>
                    </div>
                    <button class="btn" onclick="editView('\${view.id}')">Edit</button>
                `;
                container.appendChild(div);
            });
        }

        function editView(id) {
            const view = viewsData.find(v => v.id === id);
            if (!view) return;
            document.getElementById('viewId').value = view.id;
            document.getElementById('viewName').value = view.name;
            document.getElementById('viewEntityClass').value = view.entity_class || '';
            document.getElementById('viewDisplayFields').value = (view.display_fields || []).join(',');
            document.getElementById('viewLimit').value = view.limit || 10;
        }

        document.getElementById('viewForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                id: document.getElementById('viewId').value,
                name: document.getElementById('viewName').value,
                entity_class: document.getElementById('viewEntityClass').value,
                display_fields: document.getElementById('viewDisplayFields').value.split(',').map(s => s.trim()),
                limit: parseInt(document.getElementById('viewLimit').value)
            };
            
            try {
                const res = await fetch('?action=apiSaveView', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert('View saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        });

        renderViewsList();
    </script>
</body>
</html>
HTML;
    }
}
