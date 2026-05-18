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
        if ($appBaseUri === DIRECTORY_SEPARATOR || $appBaseUri === '.') $appBaseUri = '';
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

  /**
   * Include core files.
   */

  spl_autoload_register(function ($class_name) {
    $path = explode('\\', $class_name);
    $class = array_pop($path);
    $search_paths = [
      SPP_CORE_DIR . SPP_DS . 'class.' . strtolower($class) . '.php',
      SPP_CORE_DIR . SPP_DS . 'int.' . strtolower($class) . '.php',
      SPP_CORE_DIR . SPP_DS . 'interface.' . strtolower(str_replace('Interface', '', $class)) . '.php',
      SPP_CORE_DIR . SPP_DS . 'middleware' . SPP_DS . 'class.' . strtolower($class) . '.php'
    ];
    foreach ($search_paths as $file) {
      if (file_exists($file)) {
        require_once $file;
        return;
      }
    }
  });

  spl_autoload_register(function ($class_name) {
    if (substr($class_name, strlen('Exception') * (-1)) == 'Exception') {
      require_once SPP_CORE_DIR . SPP_DS . 'class.sppexception.php';
      if (!class_exists($class_name)) {
        class_alias('SPP\SPPException', $class_name);
      }
      if (!class_exists('SPPException')) {
        class_alias('SPP\SPPException', 'SPPException');
      }
    }
  });

  spl_autoload_register(function ($class_name) {
    $path = explode('\\', $class_name);
    $class = array_pop($path);
    if (file_exists(SPP_CORE_DIR . SPP_DS . strtolower($class) . '.php')) {
      require_once SPP_CORE_DIR . SPP_DS . strtolower($class) . '.php';
    }
  });

  spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'SPPMod\\') === 0) {
      $parts = explode('\\', $class_name);
      array_shift($parts); // Remove SPPMod
      $mod = strtolower(array_shift($parts));

      $remaining = $parts;
      $class = array_pop($remaining);

      foreach (['spp', 'school'] as $bucket) {
        $modDir = SPP_MODULES_DIR . SPP_DS . $bucket . SPP_DS . $mod;
        if (is_dir($modDir)) {
          if (loadModuleFromDir($modDir, $class, $parts)) return;
        }
      }

      // 3. Try app-specific modules
      $ctx = \SPP\Scheduler::getContext();
      $srcPath = \SPP\App::getAppConf('src_path', $ctx) ?? ('src' . SPP_DS . $ctx);
      $appDir = SPP_APP_DIR . SPP_DS . $srcPath;
      $appModDir = $appDir . SPP_DS . 'modules' . SPP_DS . $mod;
      
      if (is_dir($appModDir)) {
        if (loadModuleFromDir($appModDir, $class, $parts)) return;
      }
    }
  });

  if (!function_exists('loadModuleFromDir')) {
    function loadModuleFromDir($modDir, $class, $parts) {
      // 1. Try legacy class.<name>.php at root
      $legacyFile = $modDir . SPP_DS . 'class.' . strtolower($class) . '.php';
      if (file_exists($legacyFile)) {
        require_once $legacyFile;
        return true;
      }
      
      $interfaceFile = $modDir . SPP_DS . 'int.' . strtolower($class) . '.php';
      if (file_exists($interfaceFile)) {
        require_once $interfaceFile;
        return true;
      }

      // 2. Try PSR-4 style in src/
      if (!empty($parts)) {
        $psrPath = $modDir . SPP_DS . 'src' . SPP_DS . implode(SPP_DS, $parts) . '.php';
        if (file_exists($psrPath)) {
          require_once $psrPath;
          return true;
        }
      }
      return false;
    }
  }

  // Interface Compatibility Aliases
  if (!interface_exists('\SPP\CacheInterface')) {
    class_alias('\SPP\Core\CacheInterface', '\SPP\CacheInterface');
  }
  if (!interface_exists('\SPP\MiddlewareInterface')) {
    class_alias('\SPP\Core\MiddlewareInterface', '\SPP\MiddlewareInterface');
  }
  if (!interface_exists('\SPP\iModule')) {
    class_alias('\SPP\Core\iModule', '\SPP\iModule');
  }

  spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'App\\') === 0) {
      $parts = explode('\\', $class_name);
      if (count($parts) >= 3) {
        $appName = strtolower($parts[1]);
        
        // Resolve src_path for the app
        $srcPath = \SPP\App::getGlobalSettings("apps.{$appName}.src_path");
        if ($srcPath !== null && $srcPath !== '') {
            $baseSrc = SPP_APP_DIR . SPP_DS . rtrim($srcPath, '/\\');
        } else {
            $baseSrc = SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $appName;
        }

        if (count($parts) >= 4) {
          $type = strtolower($parts[2]);
          $name = strtolower($parts[3]);

          $file = '';
          if ($type === 'entities') {
            $file = $baseSrc . SPP_DS . 'entities' . SPP_DS . 'entity.' . $name . '.php';
          } elseif ($type === 'components') {
            $file = $baseSrc . SPP_DS . 'components' . SPP_DS . $parts[3] . '.php';
          } elseif ($type === 'serv') {
            $file = $baseSrc . SPP_DS . 'serv' . SPP_DS . $parts[3] . '.php';
          } else {
            // General PSR-4 fallback within the app's src directory
            $remaining = array_slice($parts, 2);
            $file = $baseSrc . SPP_DS . implode(SPP_DS, $remaining) . '.php';
          }

          if ($file && file_exists($file)) {
            require_once $file;
          }
        } elseif (count($parts) === 3) {
          $className = $parts[2];
          $file = $baseSrc . SPP_DS . $className . '.php';
          if (file_exists($file)) {
            require_once $file;
          }
        }
      }
    }
  });

  if (defined('SPP_DEBUG') && SPP_DEBUG && class_exists('\SPP\SPPError')) {
    set_exception_handler('\SPP\SPPError::exceptionHandler');

    // Initialize Debug metrics if active
    if (defined('SPP_DEBUG') && SPP_DEBUG) {
      \SPP\Core\Debug::start();
    }
  }

  if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
      // 1. Check for Redis Session Driver
      $redisEnabled = \SPP\Module::getConfig('enabled', 'redis');
      if (($redisEnabled === true || $redisEnabled === '1' || $redisEnabled === 'true') && \SPP\RedisCache::isAvailable()) {
        session_set_save_handler(new \SPP\Core\RedisSessionHandler(), true);
      }
      session_start();
    }
  } else {
    // In CLI mode, ensure $_SESSION is at least an empty array to prevent bridge/core failures
    if (!isset($_SESSION)) {
      $_SESSION = [];
    }
  }

  \SPP\Scheduler::detectAndEnforceContext();
  $context = \SPP\Scheduler::getContext();

  // Resolve App Type
  $appType = 'standard';
  $drupalRoot = '../drupal';
  $settingsPath = SPP_ETC_DIR . '/global-settings.yml';
  if (file_exists($settingsPath)) {
    $gs = \Symfony\Component\Yaml\Yaml::parseFile($settingsPath);
    $appConfig = $gs['apps'][$context] ?? [];
    $appType = $appConfig['type'] ?? 'standard';
    $drupalRoot = $appConfig['drupal_root'] ?? $drupalRoot;
  }

  $appClass = "\\App\\" . ucfirst($context) . "\\" . ucfirst($context) . "App";
  if ($appType === 'drupal' && class_exists('\\SPP\\DrupalApp')) {
    $app = new \SPP\DrupalApp($context, $drupalRoot);
  } elseif (class_exists($appClass)) {
    $app = new $appClass($context);
  } else {
    $app = new \SPP\App($context);
  }

  // Bridge Configuration Export
  if (defined('SPP_BASE_DIR') && class_exists('\SPP\PolyglotBridge')) {
    \SPP\PolyglotBridge::setup();
  }
}
\SPP\SPPEvent::registerEvent('spp_init');
$appinit = \SPP\App::getGlobalSettings('apps.' . $context . '.app_init');
if ($appinit !== '') {
    $initFile = '';
    if (str_contains($appinit, '/') || str_contains($appinit, '\\')) {
        $initFile = SPP_APP_DIR . SPP_DS . ltrim($appinit, '/\\');
    } else {
        // Resolve relative to src_path
        $srcPath = \SPP\App::getGlobalSettings("apps.{$context}.src_path");
        if ($srcPath !== null && $srcPath !== '') {
            $initFile = SPP_APP_DIR . SPP_DS . rtrim($srcPath, '/\\') . SPP_DS . $appinit;
        } else {
            $initFile = SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $context . SPP_DS . $appinit;
        }
    }

    if (file_exists($initFile)) {
        require_once $initFile;
    }
}
\SPP\SPPEvent::registerEvent('event_spp_module_install');

// Universally guarantee cross-context registration for presentation layout overriding hooks
$themeEvtPath = SPP_APP_DIR . '/src/lekhak/modules/spptheme/events/ThemeEventHandler.php';
if (file_exists($themeEvtPath)) {
    require_once $themeEvtPath;
    \SPP\SPPEvent::registerHandler('event_spp_view_render_theme', '\\SPPMod\\SppTheme\\Events\\ThemeEventHandler', false, 'onRenderTheme');
}

register_shutdown_function(['\\SPP\\SPPEvent', 'persistTrace']);
?>
