<?php

$baseDir = 'c:/projects/apache/school1/src/lekhak/modules';
$directories = glob($baseDir . '/*', GLOB_ONLYDIR);

$configs = [
    'automated_logout' => [
        'timeout' => ['type' => 'number', 'title' => 'Timeout (in seconds)', 'default' => 900, 'description' => 'Time of inactivity before automatic logout.'],
        'redirect_url' => ['type' => 'text', 'title' => 'Redirect URL', 'default' => '/admin/login', 'description' => 'URL to redirect to after logout.']
    ],
    'google_analytics' => [
        'tracking_id' => ['type' => 'text', 'title' => 'Google Analytics Tracking ID', 'default' => '', 'description' => 'E.g. UA-XXXXX-Y or G-XXXXXXX'],
        'anonymize_ip' => ['type' => 'checkbox', 'title' => 'Anonymize IP', 'default' => true]
    ],
    'redis' => [
        'host' => ['type' => 'text', 'title' => 'Redis Host', 'default' => '127.0.0.1'],
        'port' => ['type' => 'number', 'title' => 'Redis Port', 'default' => 6379],
        'password' => ['type' => 'text', 'title' => 'Redis Password', 'default' => '']
    ],
    'varnish' => [
        'control_terminal' => ['type' => 'text', 'title' => 'Varnish Control Terminal', 'default' => '127.0.0.1:6082'],
        'secret' => ['type' => 'text', 'title' => 'Varnish Secret Key', 'default' => '']
    ],
    'shield' => [
        'user' => ['type' => 'text', 'title' => 'HTTP Auth Username', 'default' => ''],
        'pass' => ['type' => 'text', 'title' => 'HTTP Auth Password', 'default' => ''],
        'message' => ['type' => 'text', 'title' => 'Authentication Message', 'default' => 'Protected Site']
    ],
    'password_policy' => [
        'min_length' => ['type' => 'number', 'title' => 'Minimum Length', 'default' => 8],
        'require_uppercase' => ['type' => 'checkbox', 'title' => 'Require Uppercase', 'default' => true],
        'require_numbers' => ['type' => 'checkbox', 'title' => 'Require Numbers', 'default' => true],
        'require_symbols' => ['type' => 'checkbox', 'title' => 'Require Symbols', 'default' => false]
    ],
    'seo_checklist' => [
        'enable_checks' => ['type' => 'checkbox', 'title' => 'Enable Background SEO Checks', 'default' => true],
        'notify_admin' => ['type' => 'checkbox', 'title' => 'Notify Admin of Critical SEO Issues', 'default' => false]
    ]
];

$count = 0;
foreach ($directories as $dir) {
    $machineName = basename($dir);
    $file = $dir . '/module.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // If it already has the config hook, skip
        if (strpos($content, 'function hook_config_form') !== false) {
            continue;
        }

        // Only inject if it has @configure in docblock
        if (strpos($content, '@configure') !== false) {
            
            // Generate schema array
            $schema = [];
            if (isset($configs[$machineName])) {
                $schema = $configs[$machineName];
            } else {
                // Fallback default config
                $schema = [
                    'enabled' => ['type' => 'checkbox', 'title' => 'Enable advanced features', 'default' => true],
                    'log_level' => ['type' => 'select', 'title' => 'Log Level', 'options' => ['info' => 'Info', 'warning' => 'Warning', 'error' => 'Error'], 'default' => 'warning']
                ];
            }

            // Generate PHP code for the array
            $schemaCode = var_export($schema, true);
            // Replace array syntax to modern short array and format
            $schemaCode = str_replace(['array (', ')'], ['[', ']'], $schemaCode);

            $methodCode = "\n    /**\n     * Defines the configuration form schema for this module.\n     */\n    public static function hook_config_form(): array\n    {\n        return $schemaCode;\n    }\n";
            
            // Insert before the last closing brace
            $pos = strrpos($content, '}');
            if ($pos !== false) {
                $newContent = substr($content, 0, $pos) . $methodCode . substr($content, $pos);
                file_put_contents($file, $newContent);
                $count++;
            }
        }
    }
}

echo "Added hook_config_form to $count modules.\n";

