<?php
// We can just include spp.php with some args to boot it in CLI
$_SERVER['argv'] = ['spp.php', 'tinker'];
require 'spp.php'; // wait this starts the interactive shell.

// Instead, let's boot it manually:
// SPP CLI boot script
