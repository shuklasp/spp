<?php
namespace Lekhak\Modules\LekhakDrupalApi;

use SPP\App;

class LekhakModuleDrupalApi {

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_lekhak_drupal_api_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_lekhak_drupal_api_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        // Autoloading for the Drupal API module
        spl_autoload_register(function ($class) {
            if (strpos($class, 'Lekhak\\Modules\\LekhakDrupalApi\\') === 0) {
                $parts = explode('\\', $class);
                $relPath = implode('/', array_slice($parts, 3)) . '.php';
                $path = __DIR__ . '/src/' . $relPath;
                if (file_exists($path)) {
                    require_once $path;
                }
            }
        });
    }

    public function hook_request_init() {
        // Handle Basic Authentication if present
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $uname = $_SERVER['PHP_AUTH_USER'];
            $passwd = $_SERVER['PHP_AUTH_PW'];
            
            // Validate credentials against SPP users table
            $db = new \SPPMod\SPPDB\SPPDB();
            $user = $db->execute_query("SELECT * FROM users WHERE username = ? AND password = ?", [$uname, $passwd]);
            if (!empty($user)) {
                // Emulate login by keeping it in the session or internal state
                // This simulates token/basic auth for the duration of the request
                $_SESSION['uid'] = $user[0]['id'];
                $_SESSION['uname'] = $user[0]['username'];
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $base_url = rtrim(App::getBaseUrl(), '/');
        $relative_path = substr($path, strlen($base_url));

        // Detect JSON:API or REST requests
        if (strpos($relative_path, '/jsonapi/') === 0 || (isset($_GET['_format']) && $_GET['_format'] === 'json')) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            \Lekhak\Modules\LekhakDrupalApi\Router::handle($relative_path, $method);
            exit; // Bypass normal HTML rendering
        }
    }

    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by lekhak_drupal_api -->';
        } else {
            $build['#suffix'] = '<!-- Processed by lekhak_drupal_api -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_drupal_api',
    'title' => 'Lekhak Drupal API (JSON:API & REST)',
    'instance' => new LekhakModuleDrupalApi()
];
