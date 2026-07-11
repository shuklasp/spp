<?php
/**
 * File sppinit.php
 * Initiates the SPP.
 */
if (!defined('SPP_VER')) {
  // Automatically resolve debug mode from global settings if not already defined
  if (!defined('SPP_DEBUG')) {
    $gsPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'global-settings.yml';
    $gs = [];
    if (file_exists($gsPath)) {
      // Use a simple parser if Yaml isn't loaded yet, or just assume sppinit will load it later
      // But since we need it NOW for exception handling, we'll do a quick check
      $content = file_get_contents($gsPath);
      if (preg_match('/debug:\s*(true|1)/i', $content)) {
        define('SPP_DEBUG', true);
      } else {
        define('SPP_DEBUG', false);
      }
    } else {
      define('SPP_DEBUG', false);
    }
  }

  /**
   * Resolve framework version from centralized configuration
   */
  $verPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'sppver.yml';
  $sppVer = '0.5'; // Fallback
  if (file_exists($verPath)) {
    $verContent = file_get_contents($verPath);
    if (preg_match('/version:\s*([0-9\.]+)/i', $verContent, $matches)) {
      $sppVer = $matches[1];
    }
  }
  define('SPP_VER', $sppVer);
  //define('SPP_DS',DIRECTORY_SEPARATOR);
  define('SPP_DS', '/');
  define('SPP_US', '/');
  if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__FILE__));
  }

  if (!defined('SPP_DOC_ROOT')) {
    define('SPP_DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? '');
  }

  if (!defined('SPP_APP_DIR')) {
    define('SPP_APP_DIR', dirname(SPP_BASE_DIR, 1));
    define('SPP_ROOT_DIR', dirname(SPP_APP_DIR));
  }

  if (!defined('APP_BASE_DIR')) {
    define('APP_BASE_DIR', SPP_APP_DIR);
  }

  if (!defined('APP_BASE_URI')) {
    $appBaseUri = '';
    if (isset($_SERVER['SCRIPT_NAME'])) {
      $appBaseUri = dirname($_SERVER['SCRIPT_NAME']);
      if (str_contains($appBaseUri, '/spp/admin')) {
        $appBaseUri = substr($appBaseUri, 0, strpos($appBaseUri, '/spp/admin'));
      } elseif (str_contains($appBaseUri, '/sppadmin')) {
        $appBaseUri = substr($appBaseUri, 0, strpos($appBaseUri, '/sppadmin'));
      } elseif (str_contains($appBaseUri, '/spp')) {
        $appBaseUri = substr($appBaseUri, 0, strpos($appBaseUri, '/spp'));
      }
      if ($appBaseUri === DIRECTORY_SEPARATOR || $appBaseUri === '.')
        $appBaseUri = '';
    }
    define('APP_BASE_URI', rtrim(str_replace('\\', '/', $appBaseUri), '/'));
  }

  define('SPP_CORE_DIR', SPP_BASE_DIR . SPP_DS . 'core');
  define('SPP_RES_URI', APP_BASE_URI . '/spp/res');
  define('SPP_JS_URI', SPP_RES_URI . '/js');
  define('SPP_CSS_URI', SPP_RES_URI . '/css');
  define('SPP_IMG_URI', SPP_RES_URI . '/images');
  define('SPP_DOJO_URI', SPP_JS_URI . '/dojotoolkit');
  define('SPP_DEV_DIR', SPP_BASE_DIR . SPP_DS . 'dev');
  define('SPP_MODULES_DIR', SPP_BASE_DIR . SPP_DS . 'modules');
  define('SPP_ETC_DIR', SPP_BASE_DIR . SPP_DS . 'etc');

  define('APP_ETC_DIR', SPP_APP_DIR . SPP_DS . 'etc' . SPP_DS . 'apps');
  define('SPP_LOG_DIR', SPP_APP_DIR . SPP_DS . 'var' . SPP_DS . 'logs');

  // Include Composer autoloader
  $composer_autoload = SPP_APP_DIR . SPP_DS . 'vendor' . SPP_DS . 'autoload.php';
  if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
  }

  // Load Native Autoloader
  require_once SPP_CORE_DIR . SPP_DS . 'class.autoloader.php';
  \SPP\Core\Autoloader::register();

  // Load App class to ensure it's available before booting
  require_once SPP_CORE_DIR . SPP_DS . 'class.app.php';

  // Enforce strict session security parameters before any session_start() call
  if (session_status() === PHP_SESSION_NONE) {
      session_set_cookie_params([
          'lifetime' => 0,
          'path' => '/',
          'domain' => '', // Current domain
          'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
          'httponly' => true,
          'samesite' => 'Strict'
      ]);
  }

  // Boot the application
  \SPP\App::boot();
}

// Universally expose global translation shorthand helper
if (!function_exists('__')) {
  function __($key, $paramsOrLocale = [], ?string $locale = null)
  {
    return \SPP\Core\Translation::translate($key, $paramsOrLocale, $locale);
  }
}
?>