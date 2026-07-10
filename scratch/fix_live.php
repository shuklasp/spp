<?php
$files = glob('c:/projects/apache/school1/src/*/comp/live_demo.php');
foreach($files as $f) {
    $c = file_get_contents($f);
    $c = preg_replace("/return \\\$this->renderPartial\('(.+?)', \['count' => \\\$this->count\]\);/", "return '\$1';", $c);
    file_put_contents($f, $c);
}
echo "Fixed " . count($files) . " files for renderPartial\n";
