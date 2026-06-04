<?php
require 'spp/sppinit.php';

echo "Triggering Python async daemon call...\n";
\SPP\PolyglotBridge::callAsync('python', 'services/python/daemon_service.py', 'generate', ['Test Async'], true);
echo "Async call dispatched in background. Script finishing immediately.\n";
