<?php
$dir = __DIR__ . '/spp/commands';
foreach (glob($dir . '/*.php') as $file) {
    $content = file_get_contents($file);
    $newContent = preg_replace('/isset\(\$this->getArgument\((.*?)\)\)/', 'null !== $this->getArgument($1)', $content);
    // Also fix !isset(...)
    $newContent = preg_replace('/!isset\(\$this->getArgument\((.*?)\)\)/', 'null === $this->getArgument($1)', $newContent);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo 'Fixed isset in ' . basename($file) . PHP_EOL;
    }
}
