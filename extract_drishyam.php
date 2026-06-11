<?php
$json = json_decode(file_get_contents('docs_response.txt'), true);
print_r($json['data']['Modules\Spp\Drishyam\Configurations']);
