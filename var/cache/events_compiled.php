<?php
return array (
  'listeners' => 
  array (
    'core.error.exception' => 
    array (
      0 => 
      array (
        'callback' => '\\SPPMod\\SPPAPI\\Subscribers\\ApiErrorSubscriber',
        'priority' => 100,
      ),
    ),
    'api.request.start' => 
    array (
      0 => 
      array (
        'callback' => '\\SPPMod\\SPPAPI\\Middleware\\ApiThrottleMiddleware',
        'priority' => 10,
      ),
    ),
  ),
  'definitions' => 
  array (
    'event_spp_context_enforce' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_route_resolve' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_app_init' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_include_css_files' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_include_js_files' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_process_xml_form' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_process_xml_form_element' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_process_xml_form_validation' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_view_render_theme' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_view_pre_render' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_view_post_render' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_view_before_augment' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'event_spp_view_render' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'xdb.before_insert' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'xdb.after_insert' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'xdb.before_update' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'xdb.after_update' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'xdb.before_delete' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
    'xdb.after_delete' => 
    array (
      'default_handler' => NULL,
      'overridable' => true,
    ),
  ),
);
