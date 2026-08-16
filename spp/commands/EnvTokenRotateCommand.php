<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EnvTokenRotateCommand extends Command
{
    protected string $name = 'env:token:rotate';
    protected string $description = 'Rotate the system deployment token';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        echo "Rotating SPP Deployment Token...\n";
        
        \SPP\Scheduler::withContext($appname, function() {
            try {
                $token = bin2hex(random_bytes(16));
                
                $xdb = new \SPPMod\SPPXDB\SPP_XDB('sys', 'security');
                $xdb->setEncryptedFields(['value']);
                $existing = $xdb->queryX("//row[key = 'deployment_token']");
                
                if ($existing) {
                    $xdb->update(['value' => $token], "key = 'deployment_token'");
                } else {
                    $xdb->insert(['key' => 'deployment_token', 'value' => $token]);
                }
                
                echo "Success: Security token rotated successfully.\n";
                echo "New Token: {$token}\n";
            } catch (\Exception $e) {
                echo "Error: Failed to rotate token. " . $e->getMessage() . "\n";
            }
        });
    }
}
