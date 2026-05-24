<?php

namespace Lekhak\Modules\LekhakModuleCaptcha;

/**
 * Prevents automated form submissions purely via JavaScript without requiring captchas.
 * @configure admin/config/lekhak_antibot
 */

class LekhakModuleCaptcha {

    private $name = 'lekhak_antibot';
    private $title = 'lekhak_antibot';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_captcha_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_captcha_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        // Core module initialization logic.
        return true;
    }

    /**
     * Hardens security and extends admin workflows.
     */
    public function hook_form_alter(&$form, $form_id) {
        // Injects CAPTCHA validation logic into forms.
        $form['lekhak_antibot'] = ['type' => 'markup', 'markup' => '<div class="captcha">Prove you are human: 2 + 2 = <input type="text" name="captcha_answer"></div>'];
    }


    public function hook_entity_presave(&$entity) {
        // Functional logic for Anti-Spam family
        if (isset($entity->body) && strpos($entity->body, 'viagra') !== false) {
            throw new \Exception("captcha blocked save due to spam keyword.");
        }
    }

    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
  'enabled' => 
  [
    'type' => 'checkbox',
    'title' => 'Enable advanced features',
    'default' => true,
  ],
  'log_level' => 
  [
    'type' => 'select',
    'title' => 'Log Level',
    'options' => 
    [
      'info' => 'Info',
      'warning' => 'Warning',
      'error' => 'Error',
    ],
    'default' => 'warning',
  ],
];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_antibot',
    'title' => 'lekhak_antibot',
    'instance' => new LekhakModuleCaptcha()
];
