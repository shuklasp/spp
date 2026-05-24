<?php
require 'spp/sppinit.php';

// Check if file exists to delete it properly
$cache = __DIR__ . '/src/lekhak/var/cache/module_registry.php';
if (file_exists($cache)) {
    unlink($cache);
}

$mods = \SPPMod\Lekhak\Core\ModuleRegistry::getModules();
echo array_key_exists('sankhyaki', $mods) ? "YES - sankhyaki is registered\n" : "NO - sankhyaki is missing\n";
