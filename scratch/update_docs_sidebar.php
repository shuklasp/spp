<?php
$dir = 'c:\\projects\\apache\\school1\\docs\\tut\\';
$files = glob($dir . '*.html');

$find = '<li><a href="09_project_twitter.html">09. Project: SPP-Twitter</a></li>';
$replace = '<li><a href="09_project_twitter.html">09. Project: SPP-Twitter</a></li>' . "\n" . '                <li><a href="10_advanced_reporting.html">10. Advanced Reporting</a></li>';

foreach ($files as $f) {
    if (basename($f) == '10_advanced_reporting.html') continue;
    $content = file_get_contents($f);
    if (strpos($content, '10_advanced_reporting.html') === false) {
        $content = str_replace($find, $replace, $content);
        file_put_contents($f, $content);
        echo "Updated $f\n";
    }
}
echo "Done.\n";
