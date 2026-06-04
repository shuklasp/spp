#!/usr/bin/env php
<?php
/**
 * SPP Enterprise CLI Gateway & Polyglot Bridge
 * This script initializes the modernized CommandManager framework, serving as the central proxy for PHP and foreign languages (Go, Python, Java, etc).
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/spp/spp.php';
