<?php

namespace SPPMod\SPPAPI;

class ApiDocController
{
    public static function render(): void
    {
        $endpoints = self::discoverEndpoints();

        header('Content-Type: text/html; charset=utf-8');
        echo self::generateHtml($endpoints);
    }

    private static function discoverEndpoints(): array
    {
        $endpoints = [];

        // 1. Discover all SPPEntity configs dynamically
        $yamlFiles = [];
        $srcDir = SPP_APP_DIR . '/src';
        
        if (is_dir($srcDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'yml') {
                    // Check if it's in an 'entities' subfolder
                    if (basename(dirname($file->getPathname())) === 'entities') {
                        $yamlFiles[] = $file->getPathname();
                    }
                }
            }
        }

        foreach ($yamlFiles as $yaml) {
            $data = \Symfony\Component\Yaml\Yaml::parseFile($yaml);
            $base = basename($yaml, '.yml');
            $entityName = preg_replace('/^entity\./', '', $base);
            
            if ((isset($data['attributes']) || isset($data['table'])) && !empty($data['enable_api'])) {
                $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
                
                foreach ($methods as $method) {
                    $endpoints[] = [
                        'method' => $method,
                        'path' => (defined('APP_BASE_URI') ? APP_BASE_URI : '') . '/api/v1/' . $entityName . ($method !== 'POST' && $method !== 'GET' ? '/{id}' : ''),
                        'summary' => "{$method} operation on {$entityName}",
                        'attributes' => $data['attributes'] ?? []
                    ];
                }
            }
        }

        // 2. Discover standard Controllers using Reflection
        if (class_exists('\SPP\CLI\Commands\ApiRouteListCommand')) {
            // we could borrow logic, but let's do a simple scan of src/controllers
            $ctrlDir = SPP_APP_DIR . '/src/controllers';
            if (is_dir($ctrlDir)) {
                $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ctrlDir));
                foreach ($iter as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $class = '\\App\\Default\\Controllers\\' . str_replace('.php', '', $file->getFilename());
                        if (class_exists($class)) {
                            $ref = new \ReflectionClass($class);
                            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                                if ($method->class === $class) {
                                    $endpoints[] = [
                                        'method' => 'GET/POST', // Simplification
                                        'path' => '/api/' . strtolower(str_replace('Controller', '', $ref->getShortName())) . '/' . $method->getName(),
                                        'summary' => "Custom Controller Method",
                                        'attributes' => []
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. Add Hardcoded Framework Endpoints
        $endpoints[] = [
            'method' => 'POST',
            'path' => (defined('APP_BASE_URI') ? APP_BASE_URI : '') . '/api/v1/auth/token',
            'summary' => 'Issue a temporary JWT for authentication',
            'attributes' => [
                'username' => 'string',
                'password' => 'string'
            ]
        ];

        return $endpoints;
    }

    private static function generateHtml(array $endpoints): string
    {
        $html = '<!DOCTYPE html><html><head><title>SPP API Explorer</title>';
        $html .= '<style>
            body { font-family: -apple-system, system-ui, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; margin: 0; }
            h1 { color: #f43f5e; font-weight: 800; letter-spacing: -0.05em; }
            .endpoint { background: #1e293b; margin-bottom: 1rem; border-radius: 8px; overflow: hidden; border: 1px solid #334155; }
            .header { padding: 1rem; display: flex; align-items: center; cursor: pointer; }
            .method { font-weight: bold; padding: 0.25rem 0.75rem; border-radius: 4px; margin-right: 1rem; font-size: 0.85rem; }
            .GET { background: #0ea5e9; color: #fff; }
            .POST { background: #10b981; color: #fff; }
            .PUT, .PATCH { background: #f59e0b; color: #fff; }
            .DELETE { background: #ef4444; color: #fff; }
            .path { font-family: monospace; font-size: 1.1rem; flex-grow: 1; }
            .summary { color: #94a3b8; font-size: 0.9rem; }
            .body { padding: 1.5rem; background: #0b1120; border-top: 1px solid #334155; display: none; }
            .endpoint.active .body { display: block; }
            .try-btn { background: #f43f5e; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-weight: bold; }
            pre { background: #000; padding: 1rem; border-radius: 4px; overflow-x: auto; color: #38bdf8; }
        </style>';
        
        $html .= '<script>
            function toggle(el) { el.parentElement.classList.toggle("active"); }
            async function tryItOut(btn, method, path) {
                const resDiv = btn.nextElementSibling;
                resDiv.innerHTML = "Fetching...";
                
                const apiKey = document.getElementById("api-key-input").value.trim();
                
                try {
                    let realPath = path.replace("/{id}", "/1"); // Mock ID for testing
                    
                    let headers = {"Accept": "application/json"};
                    if (apiKey) {
                        headers["Authorization"] = "Bearer " + apiKey;
                    }
                    
                    let req = { method: method.split("/")[0], headers: headers };
                    if (req.method !== "GET" && req.method !== "DELETE") {
                        req.headers["Content-Type"] = "application/json";
                        req.headers["X-SPP-API"] = "1";
                        req.body = "{}";
                    } else if (req.method === "DELETE") {
                        req.headers["X-SPP-API"] = "1";
                    }
                    const res = await fetch(realPath, req);
                    const json = await res.json();
                    resDiv.innerHTML = JSON.stringify(json, null, 2);
                } catch (e) {
                    resDiv.innerHTML = e.message;
                }
            }
        </script>';
        
        $html .= '</head><body>';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center;">';
        $html .= '<h1>📚 SPP Zero-Touch API Explorer</h1>';
        $html .= '<div><input type="text" id="api-key-input" placeholder="Enter Bearer Token / API Key" style="padding: 0.5rem 1rem; border-radius: 4px; border: 1px solid #334155; background: #1e293b; color: #fff; width: 300px;"></div>';
        $html .= '</div>';
        $html .= '<p style="color: #94a3b8; margin-bottom: 0.5rem;">Auto-generated documentation based on Entities and Controllers.</p>';
        $html .= '<p style="color: #94a3b8; margin-bottom: 2rem;"><strong>Authentication:</strong> All endpoints are protected by default. To authenticate, generate a permanent API Key via <code>sppadmin</code>, or request a temporary token using the <code>/api/v1/auth/token</code> endpoint below. Enter the token in the input box above.</p>';

        foreach ($endpoints as $ep) {
            $html .= '<div class="endpoint">';
            $html .= '<div class="header" onclick="toggle(this)">';
            $html .= '<span class="method ' . htmlspecialchars(explode('/', $ep['method'])[0]) . '">' . htmlspecialchars($ep['method']) . '</span>';
            $html .= '<span class="path">' . htmlspecialchars($ep['path']) . '</span>';
            $html .= '<span class="summary">' . htmlspecialchars($ep['summary']) . '</span>';
            $html .= '</div>';
            $html .= '<div class="body">';
            
            if (!empty($ep['attributes'])) {
                $html .= '<h4 style="margin-top:0;">Payload Attributes:</h4><pre>';
                $html .= htmlspecialchars(json_encode($ep['attributes'], JSON_PRETTY_PRINT));
                $html .= '</pre>';
            }

            $html .= '<button class="try-btn" onclick="tryItOut(this, \''.htmlspecialchars($ep['method']).'\', \''.htmlspecialchars($ep['path']).'\')">⚡ Try it out!</button>';
            $html .= '<pre style="margin-top: 1rem;">// Output will appear here</pre>';

            $html .= '</div></div>';
        }

        $html .= '</body></html>';
        return $html;
    }
}
