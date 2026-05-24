<?php
$modulesNeedingConfig = [
    'automated_logout', 'backup_migrate', 'captcha', 'color', 'contact', 'crop', 'dropzonejs', 'fast_404', 
    'focal_point', 'google_analytics', 'honeypot', 'imageapi_optimize', 'lazy', 'lekhak_ab_testing', 
    'lekhak_affiliates', 'lekhak_antibot', 'lekhak_audit_trail', 'lekhak_automation', 'lekhak_backend_shield', 
    'lekhak_classifieds', 'lekhak_commerce', 'lekhak_gdpr', 'lekhak_healthcare', 'lekhak_helpdesk', 
    'lekhak_logger', 'lekhak_memberships', 'lekhak_newsletter', 'lekhak_optimizer', 'lekhak_pdf', 
    'lekhak_pwa', 'lekhak_redirects', 'lekhak_search_pro', 'lekhak_security', 'lekhak_seo', 
    'lekhak_seo_analyzer', 'lekhak_seo_sitemap', 'lekhak_sitemap', 'lekhak_store', 'lekhak_subscriptions', 
    'lekhak_watermark', 'lekhak_webhooks', 'login_security', 'media_library', 'memcache', 'paranoia', 
    'password_policy', 'rabbit_hole', 'redis', 'schema_metatag', 'search_api', 'seo_checklist', 'shield', 
    'social_connect', 'tfa', 'token', 'varnish', 'automated_cron'
];

$baseDir = 'c:/projects/apache/school1/src/lekhak/modules';
$directories = glob($baseDir . '/*', GLOB_ONLYDIR);

$count = 0;
foreach ($directories as $dir) {
    $machineName = basename($dir);
    if (in_array($machineName, $modulesNeedingConfig) || strpos($machineName, 'lekhak_') === 0 || in_array($machineName, ['login_security', 'redis', 'memcache', 'varnish'])) {
        $file = $dir . '/module.php';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            // If not already configured
            if (strpos($content, '@configure') === false) {
                // Find first docblock
                if (preg_match('#/\*\*(.*?)\*/#s', $content, $matches)) {
                    $docblock = $matches[0];
                    $newDocblock = str_replace(' */', " * @configure admin/config/{$machineName}\n */", $docblock);
                    $content = str_replace($docblock, $newDocblock, $content);
                    file_put_contents($file, $content);
                    $count++;
                }
            }
        }
    }
}

echo "Added @configure tag to $count modules.\n";
