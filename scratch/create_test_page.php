<?php
require_once __DIR__ . '/../spp/sppinit.php';
use \SPPMod\Lekhak\Core\LandingPage;
use \SPPMod\Lekhak\Core\LandingBlock;

$page = new LandingPage();
$page->title = "Test Landing Page";
$page->alias = "test-landing";
$page->bundle = "landing_page";
$page->save();

$blocks = [
    ['type' => 'hero', 'region' => 'header', 'title' => 'Welcome to our site'],
    ['type' => 'text', 'region' => 'main', 'content' => 'This is some sample text content.'],
    ['type' => 'features', 'region' => 'main', 'title' => 'Cool Features'],
    ['type' => 'cta', 'region' => 'footer', 'text' => 'Contact us today!']
];

foreach ($blocks as $i => $b) {
    $block = new LandingBlock();
    $block->page_id = $page->id;
    $block->block_type = $b['type'];
    $block->region = $b['region'];
    $block->weight = $i + 1;
    unset($b['type'], $b['region']);
    $block->setContent($b);
    $block->save();
}

echo "Created landing page ID: " . $page->id . "\n";
