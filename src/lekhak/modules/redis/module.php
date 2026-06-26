<?php

namespace Lekhak\Modules\LekhakModuleRedis;

/**
 * Integrates with Redis to provide high-performance key-value caching and session storage.
 * @configure admin/config/redis
 */

class LekhakModuleRedis
{

    private $name = 'redis';
    private $title = 'Redis';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_redis_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_redis_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native caching capabilities.
     */
    public function hook_cache_backend_override()
    {
        // Replaces native file/DB cache with Redis daemon connection.
        try {
            if (class_exists('Redis')) {
                $redis = new \Redis();
                $redis->connect('127.0.0.1', 6379);
                return $redis;
            }
        } catch (\Exception $e) {
        }
        return null;
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by redis -->';
        } else {
            $build['#suffix'] = '<!-- Processed by redis -->';
        }
    }

    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
            'host' =>
                [
                    'type' => 'text',
                    'title' => 'Redis Host',
                    'default' => '127.0.0.1',
                ],
            'port' =>
                [
                    'type' => 'number',
                    'title' => 'Redis Port',
                    'default' => 6379,
                ],
            'password' =>
                [
                    'type' => 'text',
                    'title' => 'Redis Password',
                    'default' => '',
                ],
        ];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'redis',
    'title' => 'Redis',
    'instance' => new LekhakModuleRedis()
];
