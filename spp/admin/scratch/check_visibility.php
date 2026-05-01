<?php
require_once __DIR__ . '/../../../spp/sppinit.php';
$reflection = new ReflectionMethod(\SPP\App::class, 'resolvePath');
echo "Visibility of resolvePath: " . ($reflection->isPublic() ? "PUBLIC" : ($reflection->isPrivate() ? "PRIVATE" : "PROTECTED")) . "\n";
