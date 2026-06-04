<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class SessionDestroyAllCommand extends Command
{
    protected string $name = 'session:destroy-all';
    protected string $description = 'Invalidate all active sessions across the application';

    public function execute(array $args): void
    {
        echo "DANGER: This will log out all users and invalidate all active sessions.\n";
        
        $sessionDir = session_save_path();
        if (empty($sessionDir)) {
            $sessionDir = sys_get_temp_dir();
        }
        
        $files = glob($sessionDir . '/sess_*');
        $count = 0;
        
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
        }
        
        echo "Destroyed {$count} active sessions.\n";
    }
}
