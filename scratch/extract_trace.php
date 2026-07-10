<?php
$html = file_get_contents('http://localhost/school1/samvaad/backend-showcase/orm/delete/1', false, stream_context_create(['http' => ['method' => 'DELETE']]));
preg_match_all('/<div class="frame-file">(.*?)<\/div>/s', $html, $matches);
foreach ($matches[1] as $match) {
    echo trim(strip_tags($match)) . "\n";
}
