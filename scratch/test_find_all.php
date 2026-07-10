<?php
$_SERVER['REQUEST_URI'] = '/';
require 'index.php';
$items = \App\Samvaad\Entities\ShowcaseItem::find_all();
var_dump($items);
