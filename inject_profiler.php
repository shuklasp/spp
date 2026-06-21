<?php
$content = file_get_contents('spp/core/class.app.php');
$content = preg_replace('/public static function boot\(\): void\s*\{/', "public static function boot(): void {\n\$GLOBALS['boot_logs'] = []; \$GLOBALS['boot_start'] = microtime(true); function bl(\$msg) { \$GLOBALS['boot_logs'][] = number_format(microtime(true) - \$GLOBALS['boot_start'], 4) . 's: ' . \$msg; } bl('start');\n", $content);
$content = preg_replace('/(\/\/\s*(Load \.env|Register modern|Initialize Debug|1\. Check for Redis|Resolve App Type|Perform App Construction|Perform Locale Negotiation))/', "bl('$2'); $1", $content);
$content = str_replace("register_shutdown_function(['\\SPP\\SPPEvent', 'persistTrace']);", "register_shutdown_function(['\\SPP\\SPPEvent', 'persistTrace']); bl('end'); file_put_contents('boot_perf.log', implode(\"\\n\", \$GLOBALS['boot_logs']) . \"\\n\");", $content);

file_put_contents('spp/core/class.app.php.bak', file_get_contents('spp/core/class.app.php'));
file_put_contents('spp/core/class.app.php', $content);
echo "Injected profiler.\n";
