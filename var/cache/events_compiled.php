<?php
return array (
  'event_spp_context_enforce' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_route_resolve' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_app_init' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'sppdb_connection' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\sppdb_connection',
        'method' => NULL,
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'event_spp_include_css_files' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_include_js_files' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_process_xml_form' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_process_xml_form_element' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_process_xml_form_validation' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_view_render_theme' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\PageRenderHookEventHandler',
        'method' => 'onPostTheme',
        'priority' => 100,
      ),
    ),
    'overriders' => false,
  ),
  'event_spp_view_pre_render' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_view_post_render' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_view_before_augment' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_view_render' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  '' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'event_spp_kernel_boot' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'xdb.before_insert' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'xdb.after_insert' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\XdbTriggerHandler',
        'method' => 'onAfterInsert',
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'xdb.before_update' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\XdbTriggerHandler',
        'method' => 'onBeforeUpdate',
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'xdb.after_update' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'xdb.before_delete' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\XdbTriggerHandler',
        'method' => 'onBeforeDelete',
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'xdb.after_delete' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
    ),
    'overriders' => false,
  ),
  'PageNotFound' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\PageNotFound',
        'method' => NULL,
        'priority' => 500,
      ),
    ),
    'overriders' => true,
  ),
  'test_event' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\test_event',
        'method' => NULL,
        'priority' => 500,
      ),
    ),
    'overriders' => true,
  ),
  'UserRegisteredHandler' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\EventHandlers\\UserRegisteredHandler',
        'method' => NULL,
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'user.login' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => 'App\\Events_test\\Events\\UserLoginHandler',
        'method' => NULL,
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'my_event' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => 'App\\School\\Events\\MyHandler',
        'method' => NULL,
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'parikshak.suite_started' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\SPPMod\\Parikshak\\Events\\ParikshakLogSubscriber',
        'method' => 'onSuiteStarted',
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
  'parikshak.entity_test_failed' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\SPPMod\\Parikshak\\Events\\ParikshakLogSubscriber',
        'method' => 'onTestFailed',
        'priority' => 900,
      ),
    ),
    'overriders' => false,
  ),
  'parikshak.suite_completed' => 
  array (
    'defaulthandler' => NULL,
    'handlers' => 
    array (
      0 => 
      array (
        'class' => '\\SPPMod\\Parikshak\\Events\\ParikshakLogSubscriber',
        'method' => 'onSuiteCompleted',
        'priority' => 500,
      ),
    ),
    'overriders' => false,
  ),
);
