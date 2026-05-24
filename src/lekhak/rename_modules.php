<?php
// c:\projects\apache\school1\src\lekhak\rename_modules.php

$base_dir = __DIR__ . '/modules';

$rename_map = [
    'webform' => 'lekhak_forms',
    'commerce' => 'lekhak_store',
    'views' => 'lekhak_query_builder',
    'panelizer' => 'lekhak_page_builder',
    'pathauto' => 'lekhak_routing',
    'dblog' => 'lekhak_logger',
    'advagg' => 'lekhak_optimizer',
    'ctools' => 'lekhak_tools',
    'paragraphs' => 'lekhak_blocks_nested',
    'xmlsitemap' => 'lekhak_sitemap',
    'security_review' => 'lekhak_security',
    'captcha' => 'lekhak_antibot',
    'redirect' => 'lekhak_redirects',
    'metatag' => 'lekhak_seo',
    'rules' => 'lekhak_automation',
    'admin_toolbar' => 'lekhak_toolbar',
    'entity_api' => 'lekhak_core_entities',
    'display_suite' => 'lekhak_layouts',
    'simple_sitemap' => 'lekhak_seo_sitemap',
    'yoast_seo' => 'lekhak_seo_analyzer',
];

function camel_case($str) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
}

foreach ($rename_map as $old => $new) {
    $old_dir = $base_dir . '/' . $old;
    $new_dir = $base_dir . '/' . $new;
    
    if (is_dir($old_dir)) {
        // Rename directory
        rename($old_dir, $new_dir);
        
        $module_file = $new_dir . '/module.php';
        if (file_exists($module_file)) {
            $content = file_get_contents($module_file);
            
            $old_camel = camel_case($old);
            $new_camel = camel_case($new);
            
            // 1. Update Namespace
            // e.g. namespace Lekhak\Modules\Webform; -> namespace Lekhak\Modules\LekhakForms;
            $content = preg_replace('/namespace Lekhak\\\\Modules\\\\'.$old_camel.'/i', 'namespace Lekhak\Modules\\'.$new_camel, $content);
            
            // 2. Update Registry Call
            // e.g. \Lekhak\ModuleRegistry::register('webform', '\Lekhak\Modules\Webform\Module');
            // to \Lekhak\ModuleRegistry::register('lekhak_forms', '\Lekhak\Modules\LekhakForms\Module');
            $content = preg_replace('/register\s*\(\s*[\'"]'.$old.'[\'"]\s*,\s*[\'"]\\\\Lekhak\\\\Modules\\\\'.$old_camel.'\\\\Module[\'"]\s*\)/i', 
                                    'register(\''.$new.'\', \'\\Lekhak\\Modules\\'.$new_camel.'\\Module\')', $content);
            
            // Note: Since some modules were registered via expand_modules.php using dynamic strings or specific cases, 
            // let's do a more generic replacement just in case.
            $content = str_ireplace("'{$old}'", "'{$new}'", $content);
            $content = str_ireplace("\\Lekhak\\Modules\\{$old_camel}\\Module", "\\Lekhak\\Modules\\{$new_camel}\\Module", $content);
            $content = str_ireplace("namespace Lekhak\\Modules\\{$old_camel}", "namespace Lekhak\\Modules\\{$new_camel}", $content);

            file_put_contents($module_file, $content);
            echo "Renamed: $old -> $new\n";
        }
    } else {
        echo "Skipped: $old (does not exist)\n";
    }
}
echo "Module renaming complete.\n";
