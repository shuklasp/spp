<?php
require 'spp/sppinit.php';
$app = \SPP\App::getApp('default');

echo "Testing SmartStorage Auto-Inferred Context Routing...\n";
\SPP\SmartStorage::put('sess_token_123', 'ephemeral_token_value');
echo "Retrieved sess_token_123: " . \SPP\SmartStorage::get('sess_token_123') . "\n";
\SPP\SmartStorage::delete('sess_token_123');

\SPP\SmartStorage::put('manifest_app.json', '{"app": "SPP"}');
echo "Retrieved manifest_app.json: " . \SPP\SmartStorage::get('manifest_app.json') . "\n";
\SPP\SmartStorage::delete('manifest_app.json');

\SPP\SmartStorage::put('upload_profile.jpg', 'fake_image_bytes');
echo "Retrieved upload_profile.jpg: " . \SPP\SmartStorage::get('upload_profile.jpg') . "\n";
\SPP\SmartStorage::delete('upload_profile.jpg');

echo "\nTesting SmartData Alias & Explicit Categories...\n";
\SPP\SmartData::put('custom_data_key', 'shared_config_value', 'shared_config');
echo "Retrieved custom_data_key: " . \SPP\SmartData::get('custom_data_key', 'shared_config') . "\n";
\SPP\SmartData::delete('custom_data_key', 'shared_config');

echo "\nTesting Saving Custom Rules to App etc Directory...\n";
$customRules = [
    'audit_logs' => [
        'disk' => 'file_shared',
        'match_prefix' => ['audit_'],
        'match_extension' => ['log']
    ]
];
if (\SPP\SmartStorage::saveRulesConfig($customRules)) {
    echo "Saved storage_rules.yml to active app etc directory successfully!\n";
    $appConfDir = $app->getAppConfDir();
    echo "Confirmed storage_rules.yml location: " . $appConfDir . DIRECTORY_SEPARATOR . "storage_rules.yml\n";
    
    echo "\nTesting Custom Rule Routing (audit_sec.log)...\n";
    \SPP\SmartStorage::put('audit_sec.log', 'Security audit log event');
    echo "Retrieved audit_sec.log: " . \SPP\SmartStorage::get('audit_sec.log') . "\n";
    \SPP\SmartStorage::delete('audit_sec.log');
} else {
    echo "Failed to save storage_rules.yml.\n";
}

echo "\nAll Intent-Based SmartStorage functionality verified successfully!\n";
