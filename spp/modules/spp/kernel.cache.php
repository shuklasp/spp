<?php
return array (
  'iam_rules' => 
  array (
    'wordpress:blog' => 
    array (
      'can_write' => false,
    ),
    'magento:store' => 
    array (
      'can_write' => true,
    ),
  ),
  'mesh_routes' => 
  array (
    '/shop' => 'magento',
    '/blog' => 'wordpress',
  ),
  'compiled_at' => 1783739048,
);
