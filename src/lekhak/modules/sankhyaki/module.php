<?php
namespace Lekhak\Modules\Sankhyaki;

use SPPMod\SPPDB\SPPDB;
use SPP\App;

class LekhakModuleSankhyaki
{

    public function hook_init()
    {
        // Autoloading
        spl_autoload_register(function ($class) {
            if (strpos($class, 'Lekhak\\Modules\\Sankhyaki\\') === 0) {
                $parts = explode('\\', $class);
                $relPath = implode('/', array_slice($parts, 3)) . '.php';
                $path = __DIR__ . '/src/' . $relPath;
                if (file_exists($path)) {
                    require_once $path;
                }
            }
        });
    }

    public function hook_request_init()
    {
        // Install schema if not exists
        $db = new SPPDB();
        $this->ensureTable($db);

        // Standalone API Route
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        if (preg_match('#/api/sankhyaki/stats/?$#', $path)) {
            header('Content-Type: application/json');
            require_once __DIR__ . '/src/Controller/StatsController.php';
            $controller = new \Lekhak\Modules\Sankhyaki\Controller\StatsController();
            echo $controller->getStats();
            exit;
        }

        // Dashboard UI Route
        if (preg_match('#/admin/sankhyaki/?$#', $path)) {
            require_once __DIR__ . '/ui/dashboard.php';
            exit;
        }

        // Ping Route for JS Tracker (Time on Page)
        if (preg_match('#/api/sankhyaki/ping/?$#', $path)) {
            $data = json_decode(file_get_contents('php://input'), true);
            $session = session_id() ?: ($data['session_id'] ?? '');
            $url = $data['url'] ?? '';
            $time_on_page = (int) ($data['time_on_page'] ?? 0);
            if ($session && $url && $time_on_page > 0) {
                // Update the most recent log for this session and url
                try {
                    $db->execute_query("UPDATE lek_sankhyaki_log SET time_on_page = ? WHERE session_id = ? AND url = ? ORDER BY id DESC LIMIT 1", [$time_on_page, $session, $url]);
                } catch (\Exception $e) {
                }
            }
            exit;
        }

        // Avoid logging static files, admin routes, or API calls
        if (preg_match('#\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$#i', $path)) {
            return;
        }
        if (strpos($path, '/admin') !== false || strpos($path, '/api/') !== false || strpos($path, '/jsonapi/') !== false) {
            return;
        }

        // Gather visitor info
        $session_id = session_id() ?: md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $settings = ConfigManager::getSettings();
        if ($settings['ip_privacy'] === 'hash') {
            $ip_address = md5($ip_address . 'sankhyaki_salt');
        }

        $user_id = $_SESSION['uid'] ?? 0;
        $url = $path ?: '/';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        // Parse search engine and query
        $search_engine = '';
        $search_query = '';
        if ($referrer) {
            $parsed_ref = parse_url($referrer);
            $host = strtolower($parsed_ref['host'] ?? '');
            if (strpos($host, 'google.') !== false) {
                $search_engine = 'Google';
                parse_str($parsed_ref['query'] ?? '', $ref_query);
                $search_query = $ref_query['q'] ?? '';
            } elseif (strpos($host, 'bing.com') !== false) {
                $search_engine = 'Bing';
                parse_str($parsed_ref['query'] ?? '', $ref_query);
                $search_query = $ref_query['q'] ?? '';
            } elseif (strpos($host, 'yahoo.com') !== false) {
                $search_engine = 'Yahoo';
                parse_str($parsed_ref['query'] ?? '', $ref_query);
                $search_query = $ref_query['p'] ?? '';
            }
        }

        // Parse Device, OS, Browser
        $deviceInfo = \Lekhak\Modules\Sankhyaki\DeviceParser::parse($user_agent);

        // UTM Tracking
        $utm_source = $_GET['utm_source'] ?? '';
        $utm_medium = $_GET['utm_medium'] ?? '';
        $utm_campaign = $_GET['utm_campaign'] ?? '';

        // Geolocation
        $country = \Lekhak\Modules\Sankhyaki\GeoLocator::getCountry($ip_address);

        $created_at = date('Y-m-d H:i:s');

        // Log the visit asynchronously-ish
        $sql = "INSERT INTO lek_sankhyaki_log (session_id, ip_address, user_id, url, user_agent, referrer, search_engine, search_query, os, browser, device_type, utm_source, utm_medium, utm_campaign, country, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try {
            $db->execute_query($sql, [
                $session_id,
                $ip_address,
                $user_id,
                $url,
                $user_agent,
                $referrer,
                $search_engine,
                $search_query,
                $deviceInfo['os'],
                $deviceInfo['browser'],
                $deviceInfo['device_type'],
                $utm_source,
                $utm_medium,
                $utm_campaign,
                $country,
                $created_at
            ]);
        } catch (\Exception $e) {
            // Ignore DB errors on logging to prevent breaking the site
        }

        // Data Retention Cleanup (1% chance to run garbage collection on any request to save performance)
        if (mt_rand(1, 100) === 1) {
            $days = (int) $settings['retention_days'];
            if ($days > 0) {
                $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                try {
                    $db->execute_query("DELETE FROM lek_sankhyaki_log WHERE created_at < ?", [$cutoff]);
                } catch (\Exception $e) {
                }
            }
        }
    }

    private function ensureTable($db)
    {
        $schema = "id INTEGER PRIMARY KEY AUTO_INCREMENT, 
                   session_id VARCHAR(100), 
                   ip_address VARCHAR(100), 
                   user_id INTEGER DEFAULT 0, 
                   url VARCHAR(500), 
                   user_agent VARCHAR(500), 
                   referrer VARCHAR(500), 
                   search_engine VARCHAR(100), 
                   search_query VARCHAR(255), 
                   os VARCHAR(100),
                   browser VARCHAR(100),
                   device_type VARCHAR(100),
                   utm_source VARCHAR(100),
                   utm_medium VARCHAR(100),
                   utm_campaign VARCHAR(100),
                   country VARCHAR(100),
                   time_on_page INTEGER DEFAULT 0,
                   created_at DATETIME";

        try {
            $db->execute_query("SELECT 1 FROM lek_sankhyaki_log LIMIT 1");
        } catch (\Exception $e) {
            // Use SPP DB abstraction wrapper
            $driver = method_exists($db, 'getDriver') ? $db->getDriver() : 'sqlite';
            if ($driver === 'sqlite') {
                $schema = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $schema);
            }
            $db->execute_query("CREATE TABLE IF NOT EXISTS lek_sankhyaki_log ({$schema})");
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'sankhyaki',
    'title' => 'Sankhyaki Analytics',
    'instance' => new LekhakModuleSankhyaki()
];
