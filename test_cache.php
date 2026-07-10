<?php
  define('SPP_BASE_DIR', __DIR__ . '/spp');
  define('SPP_DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? '');
  define('SPP_APP_DIR', __DIR__);
  define('APP_BASE_DIR', SPP_APP_DIR);
  define('SPP_CORE_DIR', SPP_BASE_DIR . '/core');
  define('SPP_ETC_DIR', SPP_BASE_DIR . '/etc');
  define('APP_ETC_DIR', SPP_APP_DIR . '/etc/apps');
  define('SPP_DS', '/');

  $appname = 'lekhak';
  $cacheFile = 'var/cache/modules_lekhak.php';
  if (!file_exists($cacheFile)) {
      require_once __DIR__ . '/spp/sppinit.php';
      if (class_exists('\SPP\App')) {
          try { new \SPP\App($appname); } catch (\Exception $e) {}
      }
  }
  if (!file_exists($cacheFile)) {
      echo "Cache file $cacheFile does not exist (app not fully configured or cache cleared). Skipping compiled check.\n";
      exit(0);
  }
  $compiled = require $cacheFile;
  $meta = $compiled['__meta'];
  $manifest_mtime = $meta['manifest_mtime'];
  echo "manifest_mtime: $manifest_mtime\n";

  $isValid = true;
  $manifests = [
      SPP_ETC_DIR . SPP_DS . 'modules.yml',
      SPP_ETC_DIR . SPP_DS . 'apps' . SPP_DS . $appname . SPP_DS . 'modules.yml',
      APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'modsconf' . SPP_DS . 'modules.yml'
  ];
  foreach ($manifests as $m) {
      if (file_exists($m)) {
          $mt = filemtime($m);
          echo "File: $m, mtime: $mt\n";
          if ($mt > $manifest_mtime) {
              echo "=> INVALIDATED by $m\n";
              $isValid = false; break;
          }
      }
  }

  if ($isValid) {
      foreach ($compiled as $name => $data) {
          if ($name === '__meta') continue;
          $modManifest = $data['path'] . SPP_DS . 'module.yml';
          if (!file_exists($modManifest)) $modManifest = $data['path'] . SPP_DS . 'module.xml';
          if (file_exists($modManifest)) {
              $mt = filemtime($modManifest);
              if ($mt > $manifest_mtime) {
                  echo "=> INVALIDATED by module $name ($modManifest), mtime: $mt\n";
                  $isValid = false; break;
              }
          }
      }
  }

  echo "Cache isValid? " . ($isValid ? 'YES' : 'NO') . "\n";
