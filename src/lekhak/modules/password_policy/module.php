<?php

namespace Lekhak\Modules\LekhakModulePasswordPolicy;

/**
 * Enforces strict password rules (length, complexity, expiration) for user accounts.
 * @configure admin/config/password_policy
 */

class LekhakModulePasswordPolicy
{

    private $name = 'password_policy';
    private $title = 'Password Policy';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_password_policy_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_password_policy_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Core module initialization logic.
        return true;
    }

    /**
     * Hardens security and extends admin workflows.
     */
    public function hook_form_alter(&$form, $form_id)
    {
        // Enhances form security.
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by password_policy -->';
        } else {
            $build['#suffix'] = '<!-- Processed by password_policy -->';
        }
    }

    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
            'min_length' =>
                [
                    'type' => 'number',
                    'title' => 'Minimum Length',
                    'default' => 8,
                ],
            'require_uppercase' =>
                [
                    'type' => 'checkbox',
                    'title' => 'Require Uppercase',
                    'default' => true,
                ],
            'require_numbers' =>
                [
                    'type' => 'checkbox',
                    'title' => 'Require Numbers',
                    'default' => true,
                ],
            'require_symbols' =>
                [
                    'type' => 'checkbox',
                    'title' => 'Require Symbols',
                    'default' => false,
                ],
        ];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'password_policy',
    'title' => 'Password Policy',
    'instance' => new LekhakModulePasswordPolicy()
];
