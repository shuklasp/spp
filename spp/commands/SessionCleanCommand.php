<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class SessionCleanCommand extends Command
{
    protected string $name = 'session:clean';
    protected string $description = 'Clean up expired sessions';

    public function execute(array $args): void
    {
        echo "Running session garbage collection...\n";
        
        $sessionDir = session_save_path();
        if (empty($sessionDir)) {
            $sessionDir = sys_get_temp_dir();
        }
        
        echo "Session dir: {$sessionDir}\n";
        $maxlifetime = ini_get('session.gc_maxlifetime');
        echo "Max lifetime: {$maxlifetime} seconds\n";
        
        $files = glob($sessionDir . '/sess_*');
        $now = time();
        $cleaned = 0;
        
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file) > $maxlifetime)) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        echo "Cleaned {$cleaned} expired session files.\n";
    }
}
