<?php
/**
 * Script to download compound images and 3D SDF structures from PubChem.
 */

$jsonFile = __DIR__ . '/../src/ptable/data/compounds.json';
$imagesDir = __DIR__ . '/../src/ptable/assets/compounds';
$sdfDir = __DIR__ . '/../src/ptable/assets/compounds/3d';
$jsDir = __DIR__ . '/../src/ptable/assets/js';

// Create directories if they don't exist
if (!is_dir($imagesDir)) mkdir($imagesDir, 0777, true);
if (!is_dir($sdfDir)) mkdir($sdfDir, 0777, true);
if (!is_dir($jsDir)) mkdir($jsDir, 0777, true);

// 1. Download 3Dmol-min.js
$molJsUrl = 'https://3dmol.csb.pitt.edu/build/3Dmol-min.js';
$molJsPath = $jsDir . '/3Dmol-min.js';
if (!file_exists($molJsPath)) {
    echo "Downloading 3Dmol-min.js...\n";
    $jsData = file_get_contents($molJsUrl);
    if ($jsData) {
        file_put_contents($molJsPath, $jsData);
    }
} else {
    echo "3Dmol-min.js already exists.\n";
}

$compounds = json_decode(file_get_contents($jsonFile), true);

foreach ($compounds as $c) {
    $id = $c['id'];
    $cid = $c['pubchem_cid'];
    
    // Download 2D PNG
    $pngPath = $imagesDir . '/' . $id . '.png';
    if (!file_exists($pngPath)) {
        echo "Downloading 2D PNG for $id...\n";
        $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/PNG?image_size=300x300";
        $data = @file_get_contents($url);
        if ($data) {
            file_put_contents($pngPath, $data);
        } else {
            echo "  Failed to download PNG for $id\n";
        }
    } else {
        echo "2D PNG for $id already exists.\n";
    }

    // Download 3D SDF
    $sdfPath = $sdfDir . '/' . $id . '.sdf';
    if (!file_exists($sdfPath)) {
        echo "Downloading 3D SDF for $id...\n";
        $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/record/SDF/?record_type=3d";
        $data = @file_get_contents($url);
        if ($data) {
            file_put_contents($sdfPath, $data);
        } else {
            echo "  Failed to download SDF for $id, trying 2D SDF as fallback...\n";
            $urlFallback = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/{$cid}/record/SDF/?record_type=2d";
            $dataFallback = @file_get_contents($urlFallback);
            if ($dataFallback) {
                file_put_contents($sdfPath, $dataFallback);
            } else {
                 echo "  Failed to download any SDF for $id\n";
            }
        }
    } else {
        echo "3D SDF for $id already exists.\n";
    }
    
    usleep(200000); // 0.2s delay to avoid rate limiting
}

echo "\nDone!\n";
