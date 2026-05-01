<?php
/**
 * Verification script for Centralized Settings (Restored to global-settings.yml)
 */
require_once __DIR__ . '/../../sppinit.php';

use SPP\SPPConfig;
use SPPMod\SPPConfig\SPPConfig as LegacyConfig;

echo "--- Testing Centralized SPPConfig (Restored) ---\n";

$appname = 'lekhak';
if (!isset(\SPP\Registry::$reg['__apps'][$appname])) {
    new \SPP\App($appname, false, 1);
}
\SPP\Scheduler::setContext($appname);

// 1. Test Global Settings (from global-settings.yml)
echo "\n1. Global Settings:\n";
$siteName = SPPConfig::get('global:site_name');
echo "  site_name (global:site_name): $siteName\n";

$debug = SPPConfig::get('debug'); // Should fallback to global
echo "  debug (no prefix fallback): " . ($debug ? 'true' : 'false') . "\n";

// 2. Test App Settings (from settings.yml in app dir)
echo "\n2. App Settings for '$appname':\n";
SPPConfig::set('app:theme', 'ocean');
$theme = SPPConfig::get('app:theme');
echo "  theme (app:theme): $theme\n";

// 3. Test Module Delegation
echo "\n3. Module Delegation (sppdb):\n";
$prefix = SPPConfig::get('mod:sppdb:table_prefix');
echo "  sppdb table_prefix (mod:sppdb:table_prefix): $prefix\n";

// 4. Test SPPConfig Wrapper
echo "\n4. Legacy Compatibility:\n";
$siteNameLegacy = LegacyConfig::get('global:site_name');
echo "  site_name via LegacyConfig: $siteNameLegacy\n";

// 5. Test Hierarchical Settings
echo "\n5. Hierarchical Settings:\n";
SPPConfig::set('global:ui.branding.color', '#00ff00');
$color = SPPConfig::get('global:ui.branding.color');
echo "  Nested value: $color\n";

echo "\n--- Verification Complete ---\n";
