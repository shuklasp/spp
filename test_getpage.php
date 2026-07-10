<?php
$_SERVER['argv'] = ['spp.php', 'tinker']; // fake cli
require 'spp.php';
// Boot context
\SPP\Scheduler::withContext('Samvaad', function() {
    $res = \SPPMod\SPPRouter\SPPRouter::getPage('backend-showcase', 'Samvaad');
    print_r($res);
});
