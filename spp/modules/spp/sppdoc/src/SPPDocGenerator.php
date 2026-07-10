<?php
namespace SPPMod\SPPDoc;

class SPPDocGenerator {
    
    public function generate() {
        $routes = (new SPPRouteDocCollector())->collect();
        $entities = (new SPPEntityDocCollector())->collect();
        
        $templatePath = __DIR__ . '/../resources/templates/api_doc_template.html';
        if (file_exists($templatePath)) {
            $html = file_get_contents($templatePath);
            $html = str_replace('{{routes}}', json_encode($routes, JSON_PRETTY_PRINT), $html);
            $html = str_replace('{{entities}}', json_encode($entities, JSON_PRETTY_PRINT), $html);
            return $html;
        }
        
        return "Template not found.";
    }

    public function exportOpenAPI(): array {
        $routes = (new SPPRouteDocCollector())->collect();
        $entities = (new SPPEntityDocCollector())->collect();

        $paths = [];
        foreach ($routes as $route) {
            $path = $route['path'] ?? '/';
            $method = strtolower($route['method'] ?? 'get');
            $paths[$path][$method] = [
                'summary' => $route['description'] ?? ($route['action'] ?? 'API Route'),
                'responses' => [
                    '200' => [
                        'description' => 'Successful operation'
                    ]
                ]
            ];
        }

        $schemas = [];
        foreach ($entities as $name => $entity) {
            $properties = [];
            if (!empty($entity['attributes'])) {
                foreach ($entity['attributes'] as $attr => $def) {
                    $properties[$attr] = [
                        'type' => is_string($def) ? $def : ($def['type'] ?? 'string')
                    ];
                }
            } else {
                $properties['id'] = ['type' => 'integer'];
            }
            $schemas[$name] = [
                'type' => 'object',
                'properties' => $properties
            ];
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'SPP Framework OpenAPI Specification',
                'version' => '1.0.0',
                'description' => 'Automatically generated OpenAPI 3.0 specs from SPP routes and entities.'
            ],
            'paths' => $paths,
            'components' => [
                'schemas' => $schemas
            ]
        ];
    }
}
