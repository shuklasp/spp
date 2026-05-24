<?php
namespace Lekhak\Modules\SocialConnect;

/**
 * Allows users to register and log in using their social media accounts.
 * @configure admin/config/social_connect
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('social_connect', '\Lekhak\Modules\SocialConnect\Module');
    }

    public static function hook_user_login_alter(&$login_methods) {
        $login_methods["oauth_facebook"] = "Login with Facebook (JFBConnect)";
        $login_methods["oauth_google"] = "Login with Google";
    }
    public static function hook_page_meta_alter(&$meta) {
        // Auto OpenGraph tags
        $meta["og:site_name"] = "Lekhak CMS";
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
