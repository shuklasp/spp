<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\AbstractDriver;

/**
 * Class MoodleDriver
 * 
 * Integrates with Moodle using its native Web Services API.
 */
class MoodleDriver extends AbstractDriver
{
    protected function initialize(): void
    {
        if (!isset($this->config['base_url'])) {
            throw new \Exception("Moodle driver requires a 'base_url' in config.");
        }
        if (!isset($this->config['token'])) {
            throw new \Exception("Moodle driver requires a 'token' in config (Web Service Token).");
        }
    }

    private function getMoodleUrl(string $functionName): string
    {
        return $this->config['base_url'] . '/webservice/rest/server.php?wstoken=' . $this->config['token'] . '&wsfunction=' . $functionName . '&moodlewsrestformat=json';
    }

    public function syncUser(array $userData): bool
    {
        if (isset($this->config['local_path'])) {
            return $this->syncUserNative($userData);
        }

        $url = $this->getMoodleUrl('core_user_create_users');
        
        $payload = [
            'users' => [
                [
                    'username' => strtolower($userData['username'] ?? ''),
                    'password' => 'TempMoodlePass1!', // Moodle requires specific password formats
                    'firstname' => $userData['firstname'] ?? 'SPP',
                    'lastname'  => $userData['lastname'] ?? 'User',
                    'email'     => $userData['email'] ?? ''
                ]
            ]
        ];

        $response = $this->makeHttpRequest($url, 'POST', $payload);
        return $response['success'] && !isset($response['data']['exception']);
    }

    private function syncUserNative(array $userData): bool
    {
        $path = $this->config['local_path'];
        $jsonPayload = escapeshellarg(json_encode($userData));
        $script = "
            define('CLI_SCRIPT', true);
            require_once '{$path}/config.php';
            require_once '{$path}/user/lib.php';

            \$data = json_decode({$jsonPayload}, true);
            
            global \$DB;
            \$existing = \$DB->get_record('user', ['username' => strtolower(\$data['username'])]);
            
            if (!\$existing) {
                \$user = new \stdClass();
                \$user->username = strtolower(\$data['username']);
                \$user->password = hash_internal_user_password('TempMoodlePass1!');
                \$user->firstname = \$data['firstname'] ?? 'SPP';
                \$user->lastname = \$data['lastname'] ?? 'User';
                \$user->email = \$data['email'];
                \$user->confirmed = 1;
                \$user->mnethostid = \$CFG->mnet_localhost_id;
                
                user_create_user(\$user);
                echo 'SUCCESS';
            } else {
                echo 'EXISTS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false || strpos($output, 'EXISTS') !== false;
    }

    public function loginUser(array $userData): bool
    {
        if (!isset($this->config['local_path'])) {
            return false;
        }

        $path = $this->config['local_path'];
        $username = escapeshellarg($userData['username']);
        $script = "
            define('CLI_SCRIPT', true);
            require_once '{$path}/config.php';
            
            global \$DB;
            \$user = \$DB->get_record('user', ['username' => strtolower({$username})]);
            
            if (\$user) {
                complete_user_login(\$user);
                echo 'SUCCESS';
            }
        ";
        $output = shell_exec('php -r ' . escapeshellarg($script));
        return strpos($output, 'SUCCESS') !== false;
    }

    public function fetchData(string $endpoint): array
    {
        // For Moodle, the 'endpoint' acts as the wsfunction name
        $url = $this->getMoodleUrl($endpoint);
        $response = $this->makeHttpRequest($url, 'GET');
        return $response['success'] ? $response['data'] : [];
    }

    public function pushEvent(string $eventName, array $payload): bool
    {
        // Custom local plugin endpoint in Moodle
        $url = $this->getMoodleUrl('local_spp_webhook_receive');
        $response = $this->makeHttpRequest($url, 'POST', [
            'event' => $eventName,
            'payload' => json_encode($payload)
        ]);
        return $response['success'];
    }
}
