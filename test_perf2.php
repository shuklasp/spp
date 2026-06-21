<?php
$start = microtime(true);
function tlog($msg) {
    global $start;
    echo number_format(microtime(true) - $start, 4) . "s: $msg\n";
}

tlog("Start sppinit copy");

  define('SPP_BASE_DIR', __DIR__ . '/spp');
  define('SPP_DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? '');
  define('SPP_APP_DIR', __DIR__);
  define('SPP_ROOT_DIR', dirname(__DIR__));
  define('APP_BASE_DIR', SPP_APP_DIR);
  define('APP_BASE_URI', '/school1');

  define('SPP_CORE_DIR', SPP_BASE_DIR . '/core');
  define('SPP_ETC_DIR', SPP_BASE_DIR . '/etc');
  define('SPP_MODULES_DIR', SPP_BASE_DIR . '/modules');
  
  define('APP_ETC_DIR', SPP_APP_DIR . '/etc/apps');
  define('SPP_LOG_DIR', SPP_APP_DIR . '/var/logs');
  define('SPP_DS', '/');

  tlog("Before composer autoloader");
  $composer_autoload = SPP_APP_DIR . '/vendor/autoload.php';
  if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
  }

  tlog("Before native autoloader");
  require_once SPP_CORE_DIR . '/class.autoloader.php';
  \SPP\Core\Autoloader::register();

  tlog("Before App class load");
  require_once SPP_CORE_DIR . '/class.app.php';

  tlog("Before App::boot");
  \SPP\App::boot();
  tlog("After App::boot");

