<?php
$dir = __DIR__;
$files = glob($dir . '/*.html');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Replace the specific nav items with the updated list
    $pattern = '/<li><a href="10_advanced_reporting.html".*?>10\. Advanced Reporting<\/a><\/li>(?:\s*<li><a href="11_multi_engine_routing.html".*?>.*?<\/a><\/li>)?(?:\s*<li><a href="12_live_components.html".*?>.*?<\/a><\/li>)?(?:\s*<li><a href="13_security_hardening.html".*?>.*?<\/a><\/li>)?/';
    $replacement = '<li><a href="10_advanced_reporting.html">10. Advanced Reporting</a></li>
                <li><a href="11_multi_engine_routing.html">11. Multi-Engine Routing</a></li>
                <li><a href="12_live_components.html">12. Live Components</a></li>
                <li><a href="13_security_hardening.html">13. Security Hardening</a></li>
                <li><a href="14_blogging_platform.html">14. Project: Blogging Platform</a></li>';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Fix active class
    $basename = basename($file);
    $content = preg_replace('/<li><a href="'.$basename.'">/', '<li><a href="'.$basename.'" class="active">', $content);
    
    file_put_contents($file, $content);
    echo "Updated $basename\n";
}
