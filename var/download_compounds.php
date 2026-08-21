<?php
$compoundsFile = dirname(__DIR__) . '/src/ptable/data/compounds.json';
$outputDir = dirname(__DIR__) . '/src/ptable/assets/compounds';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$data = json_decode(file_get_contents($compoundsFile), true);

foreach ($data as $compound) {
    $name = $compound['pubchem_name'];
    $id = $compound['id'];
    $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/{$name}/PNG";
    $outFile = "{$outputDir}/{$id}.png";
    
    echo "Downloading {$name} to {$id}.png...\n";
    $image = file_get_contents($url);
    if ($image !== false) {
        file_put_contents($outFile, $image);
        echo "Success.\n";
    } else {
        echo "Failed.\n";
    }
    // Respect rate limits a bit
    usleep(200000); 
}

echo "Done.\n";
