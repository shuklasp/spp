#!/usr/bin/env php
<?php
/**
 * SPP Enterprise CLI Gateway
 * This script initializes the modernized CommandManager framework, serving as the central proxy.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/spp/spp.php';
