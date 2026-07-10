<?php return array (
  'backend-showcase' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@index',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => 'backend.showcase.index',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/intro' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialIntro',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/orm' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialOrm',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/orm/create' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@createItem',
    'method' => 
    array (
      0 => 'POST',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/orm/delete/{id}' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@deleteItem',
    'method' => 
    array (
      0 => 'DELETE',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/cqrs' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialCqrs',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/cqrs/event' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@appendEvent',
    'method' => 
    array (
      0 => 'POST',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/workflow' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialWorkflow',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/workflow/transition' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@applyTransition',
    'method' => 
    array (
      0 => 'POST',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/workflow/reset' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@resetWorkflow',
    'method' => 
    array (
      0 => 'POST',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/routing' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialRouting',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/routing/demo-auth' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@demoAuth',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/routing/demo-post' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@demoPost',
    'method' => 
    array (
      0 => 'POST',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/sppai' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialSppai',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/sppai/prompt' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@sppaiPrompt',
    'method' => 
    array (
      0 => 'POST',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/queue' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialQueue',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/queue/status' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@queueStatus',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/partial/polyglot' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@partialPolyglot',
    'method' => 
    array (
      0 => 'GET',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
  'backend-showcase/polyglot/execute' => 
  array (
    'controller' => 'App\\Samvaad\\Serv\\BackendShowcaseController@executePolyglot',
    'method' => 
    array (
      0 => 'POST',
    ),
    'name' => '',
    'middleware' => 
    array (
    ),
  ),
);