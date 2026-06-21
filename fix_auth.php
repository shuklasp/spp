<?php
$file = 'spp/admin/services/Auth.php';
$content = file_get_contents($file);

$functions = [
    'live_Auth_Login',
    'live_Auth_VerifyMFA',
    'live_Auth_SendMagicLink',
    'live_Auth_ConsumeMagicLink',
    'live_Auth_Logout',
    'live_Auth_Profile'
];

foreach ($functions as $func) {
    $pattern = '/(function\s+'.$func.'\s*\([^)]*\)\s*\{)(.*?)(^\})/ms';
    if (preg_match($pattern, $content, $matches)) {
        $body = $matches[2];
        // Only wrap if not already wrapped
        if (strpos($body, 'withContext(\'sppadmin\'') === false) {
            $newBody = "\n    return \\SPP\\Scheduler::withContext('sppadmin', function() use (\$la, \$params) {" . $body . "    });\n";
            $content = str_replace($matches[0], $matches[1] . $newBody . $matches[3], $content);
        }
    }
}
file_put_contents($file, $content);
echo "Fixed.\n";
