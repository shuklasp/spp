<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;
use PDO;

/**
 * Class PhpBbDriver
 * 
 * Integrates with phpBB using a hybrid approach: 
 * Tries REST API extension first, falls back to direct DB connection if unavailable.
 * Caches the active strategy to prevent double requests on every page refresh.
 */
class PhpBbDriver extends AbstractDriver
{
    private string $strategy = 'unknown'; // 'rest' or 'direct_db'
    private string $cacheFile;

    protected function initialize(): void
    {
        if (!isset($this->config['base_url']) && !isset($this->config['db'])) {
            throw new \Exception("phpBB driver requires 'base_url' (for REST) and/or 'db' credentials in config.");
        }
        
        // Define a simple cache file to store the strategy (in a real SPP app, use SPP Cache layer)
        $this->cacheFile = sys_get_temp_dir() . '/spp_phpbb_strategy_' . md5($this->config['base_url'] ?? 'local') . '.cache';
        
        if (file_exists($this->cacheFile)) {
            $this->strategy = file_get_contents($this->cacheFile);
        }
    }

    private function setStrategy(string $strategy): void
    {
        $this->strategy = $strategy;
        file_put_contents($this->cacheFile, $strategy);
    }

    public function syncUser(array $userData): bool
    {
        if (isset($this->config['local_path'])) {
            return $this->syncUserNative($userData);
        }

        if ($this->strategy === 'unknown' || $this->strategy === 'rest') {
            $success = $this->syncUserViaRest($userData);
            if ($success) {
                if ($this->strategy === 'unknown') $this->setStrategy('rest');
                return true;
            }
            
            // REST failed, fallback to direct DB if strategy is unknown
            if ($this->strategy === 'unknown') {
                $this->setStrategy('direct_db');
            } else {
                return false; // Already confirmed REST strategy but it failed (maybe temporary down)
            }
        }

        if ($this->strategy === 'direct_db') {
            return $this->syncUserViaDb($userData);
        }
        
        return false;
    }

    private function syncUserNative(array $userData): bool
    {
        $path = $this->config['local_path'];
        $jsonPayload = escapeshellarg(json_encode($userData));
        $script = "
            define('IN_PHPBB', true);
            \$phpbb_root_path = '{$path}/';
            \$phpEx = substr(strrchr(__FILE__, '.'), 1);
            require(\$phpbb_root_path . 'common.' . \$phpEx);
            require(\$phpbb_root_path . 'includes/functions_user.' . \$phpEx);

            \$data = json_decode({$jsonPayload}, true);
            
            \$user_row = [
                'username'      => \$data['username'],
                'user_password' => phpbb_hash(\$data['password'] ?? bin2hex(random_bytes(10))),
                'user_email'    => \$data['email'],
                'group_id'      => 2,
                'user_type'     => USER_NORMAL,
            ];
            
            if (user_add(\$user_row)) {
                echo 'SUCCESS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false;
    }

    public function loginUser(array $userData): bool
    {
        if (!isset($this->config['local_path'])) {
            return false;
        }

        $path = $this->config['local_path'];
        $username = escapeshellarg($userData['username']);
        $script = "
            define('IN_PHPBB', true);
            \$phpbb_root_path = '{$path}/';
            \$phpEx = substr(strrchr(__FILE__, '.'), 1);
            require(\$phpbb_root_path . 'common.' . \$phpEx);

            \$sql = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE username_clean = ' . \$db->sql_escape(utf8_clean_string({$username}));
            \$result = \$db->sql_query(\$sql);
            \$row = \$db->sql_fetchrow(\$result);
            \$db->sql_freeresult(\$result);

            if (\$row) {
                \$user->session_create(\$row['user_id'], false, true);
                echo 'SUCCESS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false;
    }

    private function syncUserViaRest(array $userData): bool
    {
        if (!isset($this->config['base_url'])) return false;

        $endpoint = $this->config['base_url'] . '/api/v1/users';
        $headers = ['Content-Type: application/json'];
        if (isset($this->config['token'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['token'];
        }

        $payload = [
            'username' => $userData['username'] ?? '',
            'email'    => $userData['email'] ?? '',
            'password' => bin2hex(random_bytes(10)) // Needs proper hash handling usually
        ];

        $response = $this->makeHttpRequest($endpoint, 'POST', $payload, $headers);
        return $response['success'];
    }

    private function syncUserViaDb(array $userData): bool
    {
        if (!isset($this->config['db'])) return false;

        try {
            $dbConfig = $this->config['db'];
            $pdo = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
                $dbConfig['user'],
                $dbConfig['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Basic insert into phpBB users table
            $stmt = $pdo->prepare("INSERT INTO phpbb_users (username, username_clean, user_password, user_email, group_id, user_type) VALUES (?, ?, ?, ?, ?, ?)");
            return $stmt->execute([
                $userData['username'],
                strtolower($userData['username']),
                password_hash(bin2hex(random_bytes(10)), PASSWORD_BCRYPT),
                $userData['email'],
                2, // Registered users group
                0  // Normal user
            ]);
        } catch (\PDOException $e) {
            // Log error
            return false;
        }
    }

    public function fetchData(string $endpoint): array
    {
        // Typically fetching data requires REST. If direct_db, we would need to map endpoints to SQL queries.
        if ($this->strategy === 'rest' || $this->strategy === 'unknown') {
            $url = $this->config['base_url'] . '/api/v1/' . ltrim($endpoint, '/');
            $response = $this->makeHttpRequest($url, 'GET');
            return $response['success'] ? $response['data'] : [];
        }
        return [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Webhooks only make sense for REST
        return false;
    }
}
