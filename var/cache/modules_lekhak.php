<?php
// SPP Compiled Module Registry - DO NOT EDIT
return array (
  'sppdb' => 
  array (
    'name' => 'sppdb',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppdb',
    'type' => 'system',
    'version' => '1.2',
    'dependencies' => 
    array (
    ),
    'includes' => 
    array (
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
    ),
  ),
  'sppconfig' => 
  array (
    'name' => 'sppconfig',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppconfig',
    'type' => 'system',
    'version' => '1.1',
    'dependencies' => 
    array (
    ),
    'includes' => 
    array (
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
    ),
  ),
  'sppauth' => 
  array (
    'name' => 'sppauth',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppauth',
    'type' => 'system',
    'version' => '0.5',
    'dependencies' => 
    array (
      0 => 'sppdb',
      1 => 'sppconfig',
    ),
    'includes' => 
    array (
      0 => 'class.sppauth.php',
      1 => 'class.sppright.php',
      2 => 'class.spprole.php',
      3 => 'class.sppuser.php',
      4 => 'class.sppusersession.php',
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
    ),
  ),
  'sppprofile' => 
  array (
    'name' => 'sppprofile',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppprofile',
    'type' => 'system',
    'version' => '1.1',
    'dependencies' => 
    array (
    ),
    'includes' => 
    array (
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
    ),
  ),
  'sppwizard' => 
  array (
    'name' => 'sppwizard',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppwizard',
    'type' => 'system',
    'version' => '0.5',
    'dependencies' => 
    array (
      0 => 'sppdb',
      1 => 'sppconfig',
    ),
    'includes' => 
    array (
      0 => 'class.sppwizard.php',
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
    ),
  ),
  'sppview' => 
  array (
    'name' => 'spphtml',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppview',
    'type' => 'system',
    'version' => '0.5',
    'dependencies' => 
    array (
      0 => 'sppdb',
      1 => 'sppconfig',
    ),
    'includes' => 
    array (
      0 => 'class.viewtag.php',
      1 => 'class.sppformelement.php',
      2 => 'class.phpcomponent.php',
      3 => 'class.jsgenerator.php',
      4 => 'class.viewvalidator.php',
      5 => 'class.viewpage.php',
      6 => 'class.viewform.php',
      7 => 'class.ajax.php',
      8 => 'class.pages.php',
      9 => 'ajaxexceptions.php',
      10 => 'formelements/classes.formelements.php',
      11 => 'sppvalidator/class.validationresult.php',
      12 => 'sppvalidator/class.sppsinglevalidator.php',
      13 => 'sppvalidator/class.sppmultiplevalidator.php',
      14 => 'sppvalidator/classes.sppvalidators.php',
      15 => 'class.viewformbuilder.php',
      16 => 'class.formaugmentor.php',
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
      0 => 'page_source_primary',
      1 => 'page_source_fallback',
      2 => 'auto_page_augmentation',
      3 => 'auto_js_injection',
    ),
  ),
  'sppajax' => 
  array (
    'name' => 'sppajax',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppajax',
    'type' => 'system',
    'version' => '1.0',
    'dependencies' => 
    array (
      0 => 'sppconfig',
      1 => 'sppview',
    ),
    'includes' => 
    array (
      0 => 'class.sppajax.php',
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
      0 => 'spa_enabled',
      1 => 'spa_container',
      2 => 'spa_default_mode',
      3 => 'spa_loading_indicator',
      4 => 'spa_push_state',
      5 => 'spa_transition',
      6 => 'spa_page_dir',
      7 => 'spa_service_dir',
      8 => 'spa_services_registry',
    ),
  ),
  'sppux' => 
  array (
    'name' => 'sppux',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppux',
    'type' => 'system',
    'version' => '1.0',
    'dependencies' => 
    array (
      0 => 'sppview',
      1 => 'sppajax',
    ),
    'includes' => 
    array (
      0 => 'class.sppux.php',
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
      'runtime_path' => 'spp/modules/spp/sppux/js/sppux.js',
      'loader_path' => 'spp/modules/spp/sppux/js/spp-loader.js',
      'component_base' => 'src/{app}/comp',
      'auto_mount' => true,
      'expose_bridge' => true,
    ),
  ),
  'parikshak' => 
  array (
    'name' => 'parikshak',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/parikshak',
    'type' => 'system',
    'version' => '1.0.0',
    'dependencies' => 
    array (
      0 => 'sppdb',
      1 => 'sppentity',
    ),
    'includes' => 
    array (
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
    ),
  ),
  'sppblade' => 
  array (
    'name' => 'sppblade',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppblade',
    'type' => 'system',
    'version' => '1.0',
    'dependencies' => 
    array (
    ),
    'includes' => 
    array (
      0 => 'class.sppblade.php',
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
      'views_path' => 'resources/views',
      'cache_path' => 'var/cache/blade',
      'mode' => 'auto',
    ),
  ),
  'sppdrupal' => 
  array (
    'name' => 'sppdrupal',
    'path' => '/mnt/c/projects/apache/school1/spp/modules/spp/sppdrupal',
    'type' => 'system',
    'version' => '1.0',
    'dependencies' => 
    array (
    ),
    'includes' => 
    array (
      0 => 'class.sppdrupal.php',
    ),
    'services' => 
    array (
    ),
    'config' => 
    array (
      'drupal_root' => '../drupal',
      'api_url' => '',
    ),
  ),
);
