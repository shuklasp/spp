<?php
namespace SPPMod\SPPAuth\Commands;

use SPP\CLI\Command;
use SPPMod\SPPAuth\SCIMHandler;

class SCIMProvisionCommand extends Command
{
    protected string $signature = 'scim:test:user {username} {email?}';
    protected string $description = 'Test SCIM User Provisioning locally';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $username = $args['username'] ?? null;
        $email = $args['email'] ?? $username . '@example.com';

        if (!$username) {
            echo "Usage: php spp.php scim:test:user <username> [email]\n";
            return;
        }

        // We will fake a SCIM request and pass it to SCIMHandler
        $payload = [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'userName' => $username,
            'emails' => [['primary' => true, 'value' => $email]],
            'name' => ['givenName' => 'SCIM', 'familyName' => 'User'],
            'active' => true
        ];

        // Since handleRequest checks TokenGuard, we bypass handleRequest and call createUser directly via reflection for testing CLI
        try {
            $handler = new SCIMHandler();
            $reflection = new \ReflectionClass($handler);
            $method = $reflection->getMethod('createUser');
            $method->setAccessible(true);

            // Capture output
            ob_start();
            $method->invokeArgs($handler, [$payload]);
            $output = ob_get_clean();

            echo "SCIM Provisioning Test Successful!\n";
            echo "---------------------------------\n";
            echo "JSON Response:\n";
            echo $output . "\n";
            echo "---------------------------------\n";

        } catch (\Exception $e) {
            echo "SCIM Error: " . $e->getMessage() . "\n";
        }
    }
}
