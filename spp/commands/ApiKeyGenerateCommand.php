<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;
class ApiKeyGenerateCommand extends Command {
    protected string $name = 'api:key:generate';
    protected string $description = 'Generate a new API access token';
    public function execute(array $args): void {
        echo "Generating a new API access token...\n";
        $token = bin2hex(random_bytes(32));
        echo "Token: {$token}\n";
        echo "Note: Stub logic. SPPAPI requires DB integration to persist.\n";
    }
}
