<?php
return array (
  'profile' => 'dev',
  'shared_groups' => 
  array (
    'core' => 
    array (
      'table_prefix' => '',
      'entities' => 
      array (
        0 => 'users',
        1 => 'roles',
        2 => 'rights',
      ),
    ),
    'academic' => 
    array (
      'extends' => 'core',
      'table_prefix' => 'sch_',
      'entities' => 
      array (
        0 => 'Student',
        1 => 'Teacher',
        2 => 'Class',
      ),
    ),
    'example_shared' => 
    array (
      'extends' => 'core',
      'table_prefix' => 'example_',
      'entities' => 
      array (
        0 => 'User',
        1 => 'Right',
      ),
    ),
    'test_reactivity' => 
    array (
      'extends' => 'core',
      'table_prefix' => 'test_',
      'entities' => 
      array (
      ),
    ),
  ),
  'admin_auth' => 
  array (
    'username' => 'admin',
    'password' => 'admin123',
  ),
  'apps' => 
  array (
    'default' => 
    array (
      'base_url' => '/default',
      'table_prefix' => '',
      'shared_group' => 'academic',
      'etc_path' => '',
      'src_path' => '',
      'app_init' => '',
    ),
    'sppadmin' => 
    array (
      'base_url' => '/spp/admin',
      'table_prefix' => '',
      'shared_group' => 'core',
      'etc_path' => 'etc',
      'src_path' => 'spp/admin',
      'app_init' => 'init.php',
    ),
    'autodemo' => 
    array (
      'base_url' => '/autodemo',
      'table_prefix' => 'autodemo_',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/autodemo',
      'src_path' => 'src/autodemo',
      'app_init' => 'init.php',
    ),
    'cms' => 
    array (
      'base_url' => '/cms',
      'type' => 'drupal',
      'drupal_root' => 'vshiksha_theme/..',
      'table_prefix' => 'drupal_',
      'shared_group' => 'core',
      'app_init' => 'init.php',
    ),
    'test1' => 
    array (
      'base_url' => '/test1',
      'table_prefix' => 'test1_',
      'type' => 'sppux',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/test1',
      'src_path' => 'src/test1',
      'app_init' => 'init.php',
    ),
    'MyBladeApp' => 
    array (
      'base_url' => '/MyBladeApp',
      'table_prefix' => 'mba_',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/MyBladeApp',
      'src_path' => 'src/MyBladeApp',
      'app_init' => 'init.php',
    ),
    'IntegratedApp' => 
    array (
      'base_url' => '/IntegratedApp',
      'table_prefix' => 'ia_',
      'shared_group' => 'academic',
      'etc_path' => 'etc/apps/IntegratedApp',
      'src_path' => 'src/IntegratedApp',
      'app_init' => 'init.php',
    ),
    'PremiumApp' => 
    array (
      'base_url' => '/PremiumApp',
      'table_prefix' => 'pre_',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/PremiumApp',
      'src_path' => 'src/PremiumApp',
      'app_init' => 'init.php',
    ),
    'SppUxApp' => 
    array (
      'base_url' => '/SppUxApp',
      'table_prefix' => 'SppUxApp_',
      'type' => 'sppux',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/SppUxApp',
      'src_path' => 'src/SppUxApp',
      'app_init' => 'init.php',
    ),
    'PremiumBlade' => 
    array (
      'base_url' => '/PremiumBlade',
      'table_prefix' => 'PremiumBlade_',
      'type' => 'blade',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/PremiumBlade',
      'src_path' => 'src/PremiumBlade',
      'app_init' => 'init.php',
    ),
    'PremiumDropIn' => 
    array (
      'base_url' => '/PremiumDropIn',
      'table_prefix' => 'PremiumDropIn_',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/PremiumDropIn',
      'src_path' => 'src/PremiumDropIn',
      'app_init' => 'init.php',
    ),
    'PremiumSppUx' => 
    array (
      'base_url' => '/PremiumSppUx',
      'table_prefix' => 'PremiumSppUx_',
      'type' => 'sppux',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/PremiumSppUx',
      'src_path' => 'src/PremiumSppUx',
      'app_init' => 'init.php',
    ),
    'lekhak' => 
    array (
      'base_url' => '/lekhak',
      'is_base_app' => true,
      'app_init' => 'init.php',
      'etc_path' => 'src/lekhak/etc',
      'var_path' => 'src/lekhak/var',
      'modules_path' => 'src/lekhak/modules',
      'theme' => 'eduxpro',
      'lekhni' => 
      array (
        'media_path' => 'var/media/lekhni',
      ),
      'admin_icon' => '🖋️',
      'admin_title' => 'Lekhak',
      'admin_menu' => 
      array (
        0 => 
        array (
          'id' => 'marketing',
          'title' => 'Marketing',
          'icon' => 'dY"',
        ),
        1 => 
        array (
          'id' => 'commerce',
          'title' => 'Commerce',
          'icon' => 'dY>\'',
        ),
      ),
      'table_prefix' => 'lek_',
      'shared_group' => 'core',
      'src_path' => 'src/lekhak',
      'assets' => 
      array (
        'theme-assets' => 'resources/themes',
        'comp-assets' => 'comp',
      ),
    ),
    'sppmobile' => 
    array (
      'base_url' => '/sppmobile',
      'table_prefix' => '',
      'shared_group' => 'core',
      'etc_path' => 'src/sppmobile/etc',
      'src_path' => 'src/sppmobile',
      'app_init' => '',
    ),
    '--help' => 
    array (
      'base_url' => '/--help',
      'table_prefix' => '--help_',
      'type' => 'native',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/--help',
      'src_path' => 'src/--help',
    ),
    'MyReactApp' => 
    array (
      'base_url' => '/MyReactApp',
      'table_prefix' => 'MyReactApp_',
      'type' => 'react',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/MyReactApp',
      'src_path' => 'src/MyReactApp',
    ),
    'MyNativeApp' => 
    array (
      'base_url' => '/MyNativeApp',
      'table_prefix' => 'MyNativeApp_',
      'type' => 'native',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/MyNativeApp',
      'src_path' => 'src/MyNativeApp',
    ),
    'MyDrupalApp' => 
    array (
      'base_url' => '/MyDrupalApp',
      'table_prefix' => 'MyDrupalApp_',
      'type' => 'drupal',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/MyDrupalApp',
      'src_path' => 'src/MyDrupalApp',
    ),
    'events_test' => 
    array (
      'base_url' => '/events_test',
      'table_prefix' => 'events_test_',
      'type' => 'native',
      'shared_group' => 'core',
      'etc_path' => 'etc/apps/events_test',
      'src_path' => 'src/events_test',
    ),
    'crm_app' => 
    array (
      'type' => 'user',
      'base_url' => '/crm',
      'table_prefix' => 'crm_',
      'options_yaml' => 'template: crm
created_at: 1780656744',
    ),
    'spp_docs' => 
    array (
      'base_url' => '/spp/docs',
      'table_prefix' => '',
      'etc_path' => 'src/lekhak/etc',
      'src_path' => 'src/lekhak',
      'app_init' => 'init.php',
    ),
  ),
  'base_app' => 'lekhak',
  'prototyping' => 
  array (
    'auto_evolution' => 'manual',
    'view_generation' => 'php_html',
  ),
  'bridge' => 
  array (
    'shared_dir' => 'var/shared',
  ),
  'dev' => 
  array (
    'testing' => 
    array (
      'storage_strategy' => 'same_db',
      'table_prefix' => 'spptest__',
      'auto_generate_tests' => true,
      'fuzz_intensity' => 10,
    ),
  ),
  'settings' => 
  array (
    'site_name' => 'Lekhak Portal',
    'debug' => true,
    'compulsory_modules' => 
    array (
      0 => 'sppauth',
      1 => 'sppdb',
      2 => 'sppconfig',
      3 => 'sppview',
      4 => 'drishyam',
    ),
    'ui' => 
    array (
      'branding' => 
      array (
        'color' => '#00ff00',
      ),
    ),
  ),
  'test_key' => 'test_val',
);
