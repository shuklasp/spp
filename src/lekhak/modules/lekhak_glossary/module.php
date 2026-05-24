<?php
namespace Lekhak\Modules\LekhakGlossary;

/**
 * Creates dynamic glossaries and tooltips for defined terms throughout site content.
 * @configure admin/config/lekhak_glossary
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_glossary', '\Lekhak\Modules\LekhakGlossary\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_glossary_config (key TEXT, value TEXT)');
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
