<?php
require_once __DIR__ . '/../../../spp/sppinit.php';
use SPPMod\SPPAuth\SPPAuth;
use SPPMod\SPPAuth\AnonymousUser;

$user = SPPAuth::user();
echo "User Type: " . get_class($user) . "\n";
echo "Rights: " . implode(', ', (array) \SPP\Registry::get('__sppauth_debug_cache__') ?: []) . "\n"; // I'll add this registry set in WebGuard
echo "Can view_content: " . (SPPAuth::can('view_content') ? "YES" : "NO") . "\n";
echo "Can publish_document: " . (SPPAuth::can('publish_document') ? "YES" : "NO") . "\n";
