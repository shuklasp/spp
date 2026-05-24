<?php
namespace Lekhak\Modules\LekhakDrupalBridge\Core\Routing;

class DrupalRouter {
    
    public static function handleRequest() {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Strip base path if we are in a subdirectory (e.g. /school1/lekhak)
        $appRoot = \SPP\App::getBaseUrl();
        if (strpos($uri, $appRoot) === 0) {
            $uri = substr($uri, strlen($appRoot));
        }
        if (empty($uri)) $uri = '/';
        $uri = '/' . ltrim($uri, '/'); // Ensure leading slash

        if (!class_exists('\SPPMod\Lekhak\Core\ModuleRegistry')) return false;

        $allMods = \SPPMod\Lekhak\Core\ModuleRegistry::getModules();
        $installed = [];
        foreach ($allMods as $machineName => $info) {
            if (\SPPMod\Lekhak\Core\ModuleRegistry::isModuleEnabled($machineName)) {
                $installed[$machineName] = true;
            }
        }
        
        foreach ($installed as $machineName => $version) {
            if (!isset($allMods[$machineName]['path'])) continue;
            
            $routingFile = $allMods[$machineName]['path'] . '/' . $machineName . '.routing.yml';
            if (file_exists($routingFile)) {
                $routes = function_exists('yaml_parse_file') ? yaml_parse_file($routingFile) : []; // Simple parse fallback needed if no yaml extension
                
                // Fallback for missing yaml extension
                if (empty($routes) && !function_exists('yaml_parse_file')) {
                    $routes = self::simpleYamlParse(file_get_contents($routingFile));
                }

                foreach ($routes as $routeName => $routeConfig) {
                    if (isset($routeConfig['path']) && $routeConfig['path'] === $uri) {
                        self::executeRoute($routeName, $routeConfig);
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    protected static function executeRoute($routeName, $routeConfig) {
        // Mock permission check
        if (isset($routeConfig['requirements']['_permission'])) {
            $permission = $routeConfig['requirements']['_permission'];
            if (!\Drupal::currentUser()->hasPermission($permission)) {
                http_response_code(403);
                echo "Access Denied";
                exit;
            }
        }

        $defaults = $routeConfig['defaults'] ?? [];
        $title = $defaults['_title'] ?? 'Drupal Page';
        
        ob_start();
        
        if (isset($defaults['_form'])) {
            $formClass = $defaults['_form'];
            if (class_exists($formClass)) {
                $form = new $formClass();
                $formState = new \Lekhak\Modules\LekhakDrupalBridge\Core\Form\FormState();
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $formState->setValues($_POST);
                    $form->validateForm([], $formState);
                    if (!$formState->hasAnyErrors()) {
                        $form->submitForm([], $formState);
                    }
                }
                
                $formArray = $form->buildForm([], $formState);
                echo \Drupal::service('renderer')->render($formArray);
            } else {
                echo "Form class not found: " . htmlspecialchars($formClass);
            }
        } elseif (isset($defaults['_controller'])) {
            // Handle controller (e.g. \Drupal\mymodule\Controller\MyController::method)
            $controllerDef = $defaults['_controller'];
            $parts = explode('::', $controllerDef);
            $controllerClass = $parts[0];
            $method = $parts[1] ?? '__invoke';
            
            if (class_exists($controllerClass)) {
                // Should use ContainerInjectionInterface, but for now just instantiate
                $controller = new $controllerClass();
                if (method_exists($controller, $method)) {
                    $build = $controller->{$method}();
                    if (is_array($build)) {
                        echo \Drupal::service('renderer')->render($build);
                    } else {
                        echo $build;
                    }
                }
            } else {
                echo "Controller class not found: " . htmlspecialchars($controllerClass);
            }
        }
        
        $content = ob_get_clean();
        
        // Render inside Lekhak layout
        // For simplicity, we just echo it inside a generic HTML structure, 
        // but normally we should return this to a View.
        // Let's integrate it with Lekhak's generic admin view if possible.
        echo self::wrapInLekhakLayout($title, $content);
        exit;
    }

    protected static function wrapInLekhakLayout($title, $content) {
        $appRoot = \SPP\App::getBaseUrl();
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$title} | Lekhak</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; color: #111827; }
        .drupal-bridge-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
        .form-item { margin-bottom: 15px; }
        .form-item label { display: block; font-weight: 500; margin-bottom: 5px; font-size: 14px; }
        .form-item input[type="text"], .form-item textarea { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
        .form-item input[type="submit"], .button { background: #2563eb; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: 500; }
        .form-item input[type="submit"]:hover, .button:hover { background: #1d4ed8; }
        .messages { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .messages.error { background: #fee2e2; color: #991b1b; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-drupal-ajax]').forEach(function(el) {
                el.addEventListener('change', function(e) {
                    var wrapper = el.getAttribute('data-ajax-wrapper');
                    if (wrapper) {
                        var target = document.getElementById(wrapper);
                        if (target) {
                            target.innerHTML = '<i>Loading...</i>';
                            // In a full implementation, we would make a fetch() to the callback
                            // For this bridge, we visually simulate it unless a real endpoint is available
                            setTimeout(() => {
                                target.innerHTML = '<div class="messages">AJAX processed by bridge</div>';
                            }, 500);
                        }
                    }
                });
            });
        });
    </script>
</head>
<body>
    <div class="drupal-bridge-container">
        <a href="{$appRoot}/admin" style="display:inline-block; margin-bottom: 15px; color: #6b7280; text-decoration: none;">&larr; Back to Lekhak Admin</a>
        <h1>{$title}</h1>
        {$content}
    </div>
</body>
</html>
HTML;
    }

    // A very rudimentary YAML parser for basic routing files if yaml extension is missing
    public static function simpleYamlParse($content) {
        $lines = explode("\n", $content);
        $result = [];
        $currentRoute = null;
        $currentSection = null;
        
        foreach ($lines as $line) {
            if (trim($line) === '' || strpos(ltrim($line), '#') === 0) continue;
            
            // Route name (no indentation)
            if (preg_match('/^([a-zA-Z0-9_\-\.]+):/', $line, $matches)) {
                $currentRoute = $matches[1];
                $result[$currentRoute] = [];
                $currentSection = null;
            } elseif ($currentRoute && preg_match('/^\s{2}([a-zA-Z0-9_]+):\s*(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $val = trim($matches[2], " '\"");
                if ($val === '') {
                    $currentSection = $key;
                    $result[$currentRoute][$key] = [];
                } else {
                    $result[$currentRoute][$key] = $val;
                }
            } elseif ($currentRoute && $currentSection && preg_match('/^\s{4}([a-zA-Z0-9_]+):\s*(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $val = trim($matches[2], " '\"");
                $result[$currentRoute][$currentSection][$key] = $val;
            }
        }
        return $result;
    }
}
