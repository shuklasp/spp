<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewServiceTestCommand extends Command
{
    protected string $name = 'view:service:test';
    protected string $description = 'Test an AJAX service endpoint from the CLI';

    public function execute(array $args): void
    {
        $appname = 'default';
        $name = null;
        $payload = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            } elseif (str_starts_with($arg, '--name=')) {
                $name = substr($arg, 7);
            } elseif (str_starts_with($arg, '--payload=')) {
                $json = substr($arg, 10);
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                } else {
                    echo "Warning: Invalid JSON payload provided. Using empty payload.\n";
                }
            }
        }

        if (!$name) {
            echo "Usage: php spp.php view:service:test --name=<service> [--app=default] [--payload='{\"key\":\"value\"}']\n";
            return;
        }

        echo "Testing AJAX Service '{$name}' for app '{$appname}'...\n";
        echo "Payload: " . json_encode($payload) . "\n";
        echo str_repeat("-", 50) . "\n";

        // Mock request environment so services reading $_POST or $_GET still work
        $_POST = $payload;
        $_REQUEST = $payload;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Capture output since resolveAndExecute will likely call exit()
        ob_start();

        \SPP\Scheduler::withContext($appname, function () use ($name, $payload) {
            try {
                // Suppress headers already sent warnings in CLI mode
                @\SPPMod\SPPAPI\SPPAjax::resolveAndExecute($name, $payload);
            } catch (\Exception $e) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Service exception: ' . $e->getMessage()
                ], JSON_PRETTY_PRINT);
            }
        });

        $output = ob_get_clean();

        // Try to pretty-print JSON if it's JSON
        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($output, $jsonStart);
            $decodedOutput = json_decode($jsonStr, true);
            if ($decodedOutput !== null) {
                echo json_encode($decodedOutput, JSON_PRETTY_PRINT) . "\n";
            } else {
                echo $output . "\n";
            }
        } else {
            echo $output . "\n";
        }

        echo str_repeat("-", 50) . "\n";
        echo "Test completed.\n";
    }
}
