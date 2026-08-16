<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AiPromptCommand extends Command
{
    protected string $name = 'ai:prompt';
    protected string $description = 'Send a prompt to the AI provider';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        $prompt = null;
        $provider = null;
        $model = null;
        
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            if (!$prompt) $prompt = $arg;
        }
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            } elseif (str_starts_with($arg, '--provider=')) {
                $provider = substr($arg, 11);
            } elseif (str_starts_with($arg, '--model=')) {
                $model = substr($arg, 8);
            }
        }

        if (!$prompt) {
            echo "Usage: php spp.php ai:prompt \"<prompt>\" [--provider=<provider>] [--model=<model>]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($prompt, $provider, $model) {
            try {
                \SPP\Module::loadModule('sppai');
                if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
                    echo "SPPAI module is not installed or available.\n";
                    return;
                }
                
                $ai = \SPPMod\SPPAI\SPPAI::class;
                if ($provider) $ai = $ai::using($provider);
                if ($model) $ai = $ai::withModel($model);
                
                echo "Thinking...\n";
                $result = $ai::complete($prompt);
                
                echo "\nAI Response:\n";
                echo str_repeat("-", 80) . "\n";
                echo $result . "\n";
                echo str_repeat("-", 80) . "\n";
            } catch (\Exception $e) {
                echo "AI Error: " . $e->getMessage() . "\n";
            }
        });
    }
}
