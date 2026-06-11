<?php
$json = json_decode(file_get_contents('docs_response_v2.txt'), true);
$configs = [];
foreach($json['data'] as $cat => $classes) {
    foreach($classes as $k => $c) {
        if(isset($c['type']) && $c['type'] == 'config') {
            $configs[$cat][$k] = $c['file'];
        }
    }
}
print_r($configs['Apps\Lekhak\Modules\Configurations'] ?? []);
