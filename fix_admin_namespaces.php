<?php

$dir = new RecursiveDirectoryIterator('c:/projects/apache/school1/spp/admin');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'])) {
        $path = $file->getPathname();
        $content = file_get_contents($path);

        $newContent = str_replace(
            [
                'SPPMod\\\\SPPEntity\\\\SPPEntity',
                'SPPMod\SPPEntity\SPPEntity',
                'SPPMod\\\\SPPEntity\\\\SPPGroup',
                'SPPMod\SPPEntity\SPPGroup',
                'SPPMod\\\\SPPGroup',
                'SPPMod\SPPGroup'
            ],
            [
                'SPPMod\\\\SppDb\\\\SPPEntity',
                'SPPMod\SPPDB\SPPEntity',
                'SPPMod\\\\SPPAuth\\\\SPPGroup',
                'SPPMod\SPPAuth\SPPGroup',
                'SPPMod\\\\SPPAuth',
                'SPPMod\SPPAuth'
            ],
            $content
        );

        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "Updated: $path\n";
        }
    }
}
echo "Done.\n";
