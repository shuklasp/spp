<?php
require_once __DIR__ . '/../../spp/sppinit.php';
\SPP\Scheduler::setContext('PremiumDropIn');

// Low-Code Form Auto-Detection & Processing
if (class_exists('\SPPMod\SPPView\ViewPage')) {
    \SPPMod\SPPView\ViewPage::processForms();
}

// Simple Router for Drop-in files
$page = (isset($_GET['q']) && $_GET['q'] !== '') ? $_GET['q'] : 'index';
$viewPath = __DIR__ . '/resources/views/';
$file = $viewPath . $page;

if (file_exists($file . '.php')) {
    include $file . '.php';
} elseif (file_exists($file . '.html')) {
    echo file_get_contents($file . '.html');
} else {
    echo "<h1>404 - Page Not Found</h1><p>File '{$page}' not found in {$viewPath}</p>";
}