<?php
// c:\projects\apache\school1\src\lekhak\patch_router.php

$shell_file = __DIR__ . '/resources/admin/standalone-shell.js';
$content = file_get_contents($shell_file);

$modules = [
    'lekhak_forum', 'lekhak_community', 'lekhak_qa', 'lekhak_newsletter', 'lekhak_popups',
    'lekhak_academy', 'lekhak_helpdesk', 'lekhak_events', 'lekhak_classifieds', 'lekhak_realestate',
    'lekhak_healthcare', 'lekhak_donations', 'lekhak_gallery', 'lekhak_portfolio', 'lekhak_documents',
    'lekhak_widgets', 'lekhak_lightbox', 'lekhak_subscriptions', 'lekhak_memberships', 'lekhak_backend_shield',
    'lekhak_journal', 'lekhak_reviews', 'lekhak_glossary', 'lekhak_reading_time', 'lekhak_authors',
    'lekhak_migrations', 'lekhak_webhooks', 'lekhak_ab_testing', 'lekhak_audit_trail', 'lekhak_pwa',
    'lekhak_pdf', 'lekhak_watermark', 'lekhak_affiliates', 'lekhak_gdpr', 'lekhak_search_pro'
];

$possible_views_str = implode("', '", $modules);
$content = preg_replace(
    "/const possibleViews = \[(.*?)]/s", 
    "const possibleViews = [$1, '$possible_views_str']", 
    $content
);

$view_map_str = "";
foreach ($modules as $m) {
    $view_map_str .= "                '$m': '$m',\n";
}

$content = preg_replace(
    "/'views': 'views'\s*\n\s*};/s",
    "'views': 'views',\n" . rtrim($view_map_str, ",\n") . "\n            };",
    $content
);

file_put_contents($shell_file, $content);
echo "Router updated successfully!\n";
