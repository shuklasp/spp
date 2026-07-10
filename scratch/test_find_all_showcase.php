<?php
require_once __DIR__ . '/../spp/system/spp_kernel.php';
$items = \App\Samvaad\Entities\ShowcaseItem::find_all();
var_dump($items);
