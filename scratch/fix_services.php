<?php
$dir = 'c:\projects\apache\school1\src\Samvaad\serv';
foreach (glob($dir . '/enterprise_*.php') as $file) {
    $content = file_get_contents($file);
    if (strpos($content, '$service = new') === false) {
        $className = basename($file, '.php');
        $append = "\n" . '$service = new \Samvaad\serv\\' . $className . "();\n";
        $append .= '$payload = json_decode(file_get_contents(\'php://input\'), true) ?: $_POST;' . "\n";
        $append .= '$service->execute($payload);' . "\n";
        file_put_contents($file, $content . $append);
        echo "Updated $file\n";
    }
}
