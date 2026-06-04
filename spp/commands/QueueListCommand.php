<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class QueueListCommand extends Command
{
    protected string $name = 'queue:list';
    protected string $description = 'List all jobs currently in the queue';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
        }
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() {
            echo "Background Job Queue\n";
            echo str_repeat("=", 80) . "\n";
            
            try {
                if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                    ob_start();
                    try {
                        $db = new \SPPMod\SPPDB\SPPDB();
                    } catch (\Exception $e) {
                        ob_end_clean();
                        echo "Queue is empty or unavailable. " . $e->getMessage() . "\n";
                        return;
                    }
                    ob_end_clean();
                    
                    $res = $db->query("SELECT * FROM spp_jobs ORDER BY available_at ASC LIMIT 50");
                    
                    if (empty($res)) {
                        echo "The queue is currently empty.\n";
                        return;
                    }
                    
                    echo str_pad("ID", 10) . str_pad("Available At", 25) . str_pad("Created At", 25) . "Payload Snippet\n";
                    echo str_repeat("-", 80) . "\n";
                    
                    foreach ($res as $row) {
                        $id = $row['id'] ?? 'N/A';
                        $avail = $row['available_at'] ?? 'N/A';
                        $created = $row['created_at'] ?? 'N/A';
                        
                        $payload = $row['payload'] ?? '';
                        $snippet = substr($payload, 0, 30);
                        if (strlen($payload) > 30) $snippet .= '...';
                        
                        echo str_pad($id, 10) . str_pad($avail, 25) . str_pad($created, 25) . $snippet . "\n";
                    }
                    
                    $countRes = $db->query("SELECT COUNT(*) as cnt FROM spp_jobs");
                    $total = $countRes[0]['cnt'] ?? count($res);
                    
                    if ($total > 50) {
                        echo "\nShowing 50 of {$total} total jobs in the queue.\n";
                    } else {
                        echo "\nTotal jobs in queue: {$total}\n";
                    }
                } else {
                    echo "Database module is not available.\n";
                }
            } catch (\Exception $e) {
                // If the table doesn't exist or other DB errors occur
                ob_end_clean(); // just in case
                echo "Error reading from queue: " . $e->getMessage() . "\n";
                echo "It's possible the 'spp_jobs' table hasn't been created yet.\n";
            }
        });
    }
}
