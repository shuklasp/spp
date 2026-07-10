<?php
require 'spp/sppinit.php';
try {
    $items = App\Samvaad\Entities\ShowcaseItem::find_all();
    print_r($items);
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage();
}
